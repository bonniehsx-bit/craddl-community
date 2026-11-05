<?php
/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * 官网：https://www.craddl.cn
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the
 * “License”); you may not use this file except in compliance with the License. You may obtain a
 * copy of the License from LICENSE in the source package or
 * https://opensource.org/license/cpal-1.0. The License is based on the Mozilla Public License
 * Version 1.1 but Sections 14 and 15 have been added to cover use of software over a computer
 * network and provide for limited attribution for the Original Developer. In addition, Exhibit A
 * has been modified to be consistent with Exhibit B.
 * Software distributed under the License is distributed on an “AS IS” basis, WITHOUT WARRANTY OF
 * ANY KIND, either express or implied. See the License for the specific language governing rights
 * and limitations under the License.
 * The Original Code is Craddl Community Edition.
 * The Original Developer is the Initial Developer.
 * The Initial Developer of the Original Code is 佛山今日鼎盛信息科技有限公司. All portions of the code written
 * by 佛山今日鼎盛信息科技有限公司 are Copyright (c) 2026. All Rights Reserved.
 */

namespace app\helpers;

/**
 * 本地上传图片的缩略图。
 *
 * 商品封面原图 1440×1440、一张 800KB 上下,首页 / 列表里只显示 170 个点宽,小带宽服务器刷一屏要下 5MB。
 * 上传时(老图由 `php yii image-thumb/run` 补)在原图旁边生成一张小图,首页 / 列表用它:
 *
 *   /uploads/image/original/20260819/abc.jpg  →  /uploads/image/original/20260819/abc_thumb.jpg
 *
 * 规则是**固定的文件名约定**,前端只凭原图地址就能推出缩略图地址(uniapp utils/thumb.js 同一条规则),
 * 不用改任何业务表;缩略图不存在时前端自动退回原图。所以凡是处理过的图**都**生成缩略图(小图也复制一份),
 * 规则才能一律成立。
 *
 * 只处理 jpg / jpeg / png / webp:gif 可能是动图,ico / bmp 不用于商品展示。
 */
class ImageThumbHelper
{
    /** 缩略图最长边。列表卡片 170 个点宽,三倍屏约 510 像素 */
    const MAX_SIDE = 500;
    const QUALITY = 78;
    const SUFFIX = '_thumb';
    const EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    /** 原图路径 / 地址 → 缩略图路径 / 地址;不支持的格式返回 null */
    public static function thumbName(string $pathOrUrl): ?string
    {
        if (!preg_match('/^(.+?)(\.(jpe?g|png|webp))(\?.*)?$/i', $pathOrUrl, $m)) {
            return null;
        }
        if (substr($m[1], -strlen(self::SUFFIX)) === self::SUFFIX) {
            return null; // 本身就是缩略图
        }
        return $m[1] . self::SUFFIX . $m[2] . ($m[4] ?? '');
    }

    /**
     * 读本地图片的大小和尺寸
     * @return array{size:int,width:int,height:int}
     */
    public static function probe(string $path): array
    {
        clearstatcache(true, $path);
        $size = is_file($path) ? (int) filesize($path) : 0;
        $info = $size ? @getimagesize($path) : false;
        return ['size' => $size, 'width' => $info ? (int) $info[0] : 0, 'height' => $info ? (int) $info[1] : 0];
    }

    /**
     * 给本地原图生成缩略图(已存在且比原图新就跳过)。成功返回缩略图路径,不支持 / 失败返回 null。
     * 原图本来就不大于 MAX_SIDE 的直接复制一份,保证文件名规则一律成立。
     */
    public static function make(string $srcPath, bool $force = false): ?string
    {
        $dst = self::thumbName($srcPath);
        if ($dst === null || !is_file($srcPath) || !function_exists('imagecreatetruecolor')) {
            return null;
        }
        if (!$force && is_file($dst) && filemtime($dst) >= filemtime($srcPath)) {
            return $dst;
        }
        $info = @getimagesize($srcPath);
        if (!$info || $info[0] <= 0 || $info[1] <= 0) {
            return null;
        }
        [$w, $h] = $info;
        if (max($w, $h) <= self::MAX_SIDE) {
            return @copy($srcPath, $dst) ? $dst : null;
        }

        $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($srcPath);
                break;
            case 'png':
                $src = @imagecreatefrompng($srcPath);
                break;
            case 'webp':
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false;
                break;
            default:
                $src = false;
        }
        if (!$src) {
            return null;
        }

        $scale = self::MAX_SIDE / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        if ($ext === 'png' || $ext === 'webp') {
            // 保留透明
            imagealphablending($out, false);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagesavealpha($out, true);
        }
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        switch ($ext) {
            case 'png':
                $ok = imagepng($out, $dst, 7);
                break;
            case 'webp':
                $ok = function_exists('imagewebp') && imagewebp($out, $dst, self::QUALITY);
                break;
            default:
                $ok = imagejpeg($out, $dst, self::QUALITY);
        }
        return $ok ? $dst : null; // PHP 8 起 GdImage 离开作用域自动释放,不再 imagedestroy
    }

    /** 删原图时顺带删缩略图 */
    public static function remove(?string $srcPath): void
    {
        $thumb = $srcPath ? self::thumbName($srcPath) : null;
        if ($thumb && is_file($thumb)) {
            @unlink($thumb);
        }
    }
}
