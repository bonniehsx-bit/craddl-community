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

namespace app\services;

use app\helpers\ConstantHelper;
use app\helpers\HttpHelper;
use app\helpers\JobHelper;
use app\helpers\QrcodeHelper;
use app\helpers\ServerHelper;
use app\jobs\common\UnlinkJob;
use yii\helpers\FileHelper;

/**
 * 海报素材与成图文件 —— 推广海报、商品海报的接口共用
 *
 * - download():远程图片下到临时目录,对象销毁时统一删掉
 * - qrCode():小程序端给小程序码,拿不到就退成 H5 二维码
 * - save():原子写入成图 + 删掉同一前缀的旧图 + 到期清理
 */
class PosterAssets
{
    /** 生成出来的海报留多久。缓存 key 已包含所有影响画面的输入,改了就是新文件,这里只管清理磁盘 */
    const CACHE_SECONDS = 3600;

    private $mallId;

    /** @var string[] 本次下载到 runtime/temp 的素材 */
    private $tempFiles = [];

    public function __construct(int $mallId)
    {
        $this->mallId = $mallId;
    }

    public function __destruct()
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /** App 端也给小程序码(App 里没有可扫的落地页) */
    public static function platform(): string
    {
        $platform = \Yii::$app->platform ?: 'H5';
        return $platform == ConstantHelper::PLATFORM_APP ? ConstantHelper::PLATFORM_MPWX : $platform;
    }

    /** 下载远程图片到临时目录;失败返回 null,由模板画占位 */
    public function download($url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') return null;
        try {
            $path = HttpHelper::getTempPath($url);
            // getTempPath 遇到本地已存在的路径会原样返回,那不是临时文件,不能删
            if ($path !== $url) {
                $this->tempFiles[] = $path;
            }
            return $path;
        } catch (\Throwable $e) {
            \Yii::warning("海报素材下载失败 [{$url}]: " . $e->getMessage());
            return null;
        }
    }

    public static function defaultAvatar(): ?string
    {
        $path = \Yii::getAlias('@app/web/statics/default/user/avatar.png');
        return is_file($path) ? $path : null;
    }

    /**
     * 小程序端给小程序码,拿不到(小程序没发布、配置错)就退成 H5 二维码。
     *
     * ⚠ 不要自己调 wxa/getwxacodeunlimit 只看 HTTP 200:微信出错时也返回 200,
     *   正文是 errcode JSON,当图片存下来海报就会静悄悄地没有码。
     *   MiniProgramCodeService 会识别 errcode 并抛异常,这里才能降级。
     *
     * @param string $page 小程序页面路径,不带开头的 /
     * @param string $scene 如 "id=12&pid=3"(小程序码 scene 最长 32 字符)
     * @return array [码的本地路径, 码的种类 mini=小程序码 / qrcode=普通二维码]
     */
    public function qrCode(string $page, string $scene, string $platform): array
    {
        if ($platform == ConstantHelper::PLATFORM_MPWX) {
            try {
                $webPath = (new MiniProgramCodeService())->getUnlimitedSceneCode($page, $scene, $this->mallId);
                $path = \Yii::getAlias('@webroot') . $webPath;
                if (is_file($path)) {
                    return [$path, 'mini'];
                }
            } catch (\Throwable $e) {
                \Yii::warning("海报:小程序码获取失败({$page}?{$scene}),改用 H5 二维码。" . $e->getMessage());
            }
        }

        $link = ServerHelper::getHost() . '/h5/#/' . $page . "?mall_id={$this->mallId}&{$scene}";
        $res = QrcodeHelper::create($link, 'share/' . md5($link) . '.png');
        if (empty($res['path']) || !is_file($res['path'])) {
            throw new \Exception('二维码生成失败');
        }
        return [$res['path'], 'qrcode'];
    }

    /**
     * 查缓存:文件在且没过期就返回可访问的 URL,否则 null
     */
    public static function cachedUrl(string $webPath): ?string
    {
        $file = \Yii::getAlias('@webroot') . $webPath;
        if (is_file($file) && time() - filemtime($file) < self::CACHE_SECONDS) {
            return ServerHelper::getHost() . $webPath . '?v=' . filemtime($file);
        }
        return null;
    }

    /**
     * 写成图并返回 URL。
     * 先写临时文件再改名:同一用户连点两次时,另一个请求不会读到写了一半的图;
     * 写完删掉同目录下 $oldGlob 匹配到的旧图(不动这次刚写的)。
     *
     * @param resource|\GdImage $image
     * @param string $webPath 如 /poster/goods/MPWX/12_34_ab12cd.jpg
     * @param string $oldGlob 同目录下旧图的文件名通配,如 12_34_*.jpg
     */
    public static function save($image, string $webPath, string $oldGlob): string
    {
        $file = \Yii::getAlias('@webroot') . $webPath;
        FileHelper::createDirectory(dirname($file));
        $tmp = $file . '.' . uniqid('', true) . '.tmp';
        $written = imagejpeg($image, $tmp, 90);
        imagedestroy($image);
        if (!$written || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \Exception('海报文件写入失败:' . $file);
        }
        foreach (glob(dirname($file) . '/' . $oldGlob) ?: [] as $old) {
            if ($old !== $file) {
                @unlink($old);
            }
        }
        JobHelper::push(new UnlinkJob(['path' => $file]), self::CACHE_SECONDS);

        return ServerHelper::getHost() . $webPath . '?v=' . time();
    }

    /** 缓存指纹:所有会影响画面的输入拼起来取短 md5 */
    public static function fingerprint(array $inputs): string
    {
        return substr(md5(json_encode($inputs, JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
