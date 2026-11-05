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

/**
 * 海报画布基类 —— 推广海报、商品海报共用的 GD 绘图方法
 *
 * 画法约定:
 * - 整张先按 S 倍超采样画,子类最后调 finish() 缩回 750×1334。GD 画圆和圆角没有抗锯齿,
 *   缩小这一步就是抗锯齿,边缘才不会一圈锯齿
 * - 所有方法的坐标、字号都是 750 画布上的值(和 CSS 像素一致),内部统一乘超采样倍数
 * - $this->p 放本次渲染的素材:avatar_path / qr_path / logo_path / mall_name 等,
 *   公共组件(头像、码、品牌行)直接从这里取
 */
abstract class PosterCanvas
{
    const W = 750;
    const H = 1334;
    const S = 2; // 超采样倍数

    const GOLD = [214, 178, 110];

    protected $img;
    protected $fontRegular;
    protected $fontBold;

    /** @var int[] 主题色 RGB */
    protected $theme;

    /** @var array 本次渲染参数(已清洗) */
    protected $p;

    public function __construct()
    {
        $this->fontRegular = \Yii::getAlias('@app/web/statics/fonts/NotoSansSC-Regular.otf');
        $this->fontBold = \Yii::getAlias('@app/web/statics/fonts/NotoSansSC-Bold.otf');
    }

    /** 建一张超采样画布 */
    protected function begin()
    {
        $this->img = imagecreatetruecolor(self::W * self::S, self::H * self::S);
        imagealphablending($this->img, true);
        imagesavealpha($this->img, false);
    }

    /** 缩回 750×1334 并返回成图 */
    protected function finish()
    {
        $out = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($out, $this->img, 0, 0, 0, 0, self::W, self::H, self::W * self::S, self::H * self::S);
        imagedestroy($this->img);
        return $out;
    }

    // ═════════════════════════ 公共组件 ═════════════════════════

    /** 圆形 logo + 商城名(票券模板顶部) */
    protected function drawRoundBrand($x, $cy, array $nameColor)
    {
        $mallName = $this->p['mall_name'];
        if ($mallName === '' && !$this->p['logo_path']) {
            return;
        }
        $this->circle($x + 30, $cy, 30, [255, 255, 255]);
        $logo = $this->loadOptional($this->p['logo_path']);
        if ($logo) {
            $this->pasteCircle($logo, $x + 4, $cy - 26, 52);
            imagedestroy($logo);
        } elseif ($mallName !== '') {
            $this->textCentered(mb_substr($mallName, 0, 1), $this->fontBold, 28, $this->theme, $x + 30, $cy);
        }
        if ($mallName !== '') {
            $name = $this->fit($mallName, $this->fontBold, 28, 750 - $x - 76 - 56);
            $this->text($name, $this->fontBold, 28, $nameColor, $x + 76, $cy, 1.0, 1);
        }
    }

    /** 「专属邀请」半透明胶囊 */
    protected function pill(string $label, $x, $y)
    {
        $w = $this->textWidth($label, $this->fontRegular, 24, 4) + 48;
        $this->roundRect($x, $y, $w, 48, 24, [255, 255, 255], 0.18);
        $this->text($label, $this->fontRegular, 24, [255, 255, 255], $x + 24, $y + 24, 1.0, 4);
    }

    /** 头像:$rings = [[半径, 颜色, 不透明度?], ...] 从外往里画底圈 */
    protected function avatar($cx, $cy, $r, array $rings = [])
    {
        foreach ($rings as $ring) {
            $this->circle($cx, $cy, $ring[0], $ring[1], $ring[2] ?? 1.0);
        }
        $this->avatarAt($cx, $cy, $r);
    }

    protected function avatarAt($cx, $cy, $r)
    {
        $avatar = $this->loadOptional($this->p['avatar_path']);
        if ($avatar) {
            $this->pasteCircle($avatar, $cx - $r, $cy - $r, $r * 2);
            imagedestroy($avatar);
        } else {
            $this->circle($cx, $cy, $r, [244, 200, 170]);
        }
    }

    protected function pasteQr($x, $y, $size)
    {
        $qr = $this->loadOptional($this->p['qr_path']);
        if ($qr) {
            $this->pasteContain($qr, $x, $y, $size, $size);
            imagedestroy($qr);
        }
    }

    /** 左对齐标题:放不下先缩字号,再截断 */
    protected function titleLine(string $title, $x, $cy, $size, $minSize, $maxW, array $rgb, $spacing)
    {
        $size = $this->shrink($title, $this->fontBold, $size, $minSize, $maxW, $spacing);
        $title = $this->fit($title, $this->fontBold, $size, $maxW, $spacing);
        $this->text($title, $this->fontBold, $size, $rgb, $x, $cy, 1.0, $spacing);
    }

    protected function titleCentered(string $title, $cy, $size, $minSize, $maxW, array $rgb, $spacing)
    {
        $size = $this->shrink($title, $this->fontBold, $size, $minSize, $maxW, $spacing);
        $title = $this->fit($title, $this->fontBold, $size, $maxW, $spacing);
        $this->textCentered($title, $this->fontBold, $size, $rgb, 375, $cy, $spacing);
    }

    protected function shrink(string $text, string $font, $size, $minSize, $maxW, $spacing): int
    {
        while ($size > $minSize && $this->textWidth($text, $font, $size, $spacing) > $maxW) {
            $size -= 4;
        }
        return $size;
    }

    // ═════════════════════════ 绘图基础 ═════════════════════════

    protected function color(array $rgb, float $opacity = 1.0, $img = null)
    {
        $alpha = (int)round(127 * (1 - max(0, min(1, $opacity))));
        return imagecolorallocatealpha($img ?: $this->img, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    protected function rect($x, $y, $w, $h, array $rgb, float $opacity = 1.0)
    {
        $s = self::S;
        if ($w <= 0 || $h <= 0) return;
        imagefilledrectangle($this->img, (int)round($x * $s), (int)round($y * $s),
            (int)round(($x + $w) * $s) - 1, (int)round(($y + $h) * $s) - 1, $this->color($rgb, $opacity));
    }

    /** 描边矩形:上下两条通长,左右两条夹在中间,拐角不叠深 */
    protected function strokeRect($x, $y, $w, $h, $t, array $rgb, float $opacity = 1.0)
    {
        $this->rect($x, $y, $w, $t, $rgb, $opacity);
        $this->rect($x, $y + $h - $t, $w, $t, $rgb, $opacity);
        $this->rect($x, $y + $t, $t, $h - 2 * $t, $rgb, $opacity);
        $this->rect($x + $w - $t, $y + $t, $t, $h - 2 * $t, $rgb, $opacity);
    }

    protected function circle($cx, $cy, $r, array $rgb, float $opacity = 1.0)
    {
        $s = self::S;
        imagefilledellipse($this->img, (int)round($cx * $s), (int)round($cy * $s),
            (int)round($r * 2 * $s), (int)round($r * 2 * $s), $this->color($rgb, $opacity));
    }

    /** 竖向渐变填充的实心圆(逐行画弦) */
    protected function circleGradient($cx, $cy, $r, array $from, array $to)
    {
        $s = self::S;
        $R = $r * $s;
        $CX = $cx * $s;
        $top = (int)round(($cy - $r) * $s);
        for ($row = 0; $row < (int)round(2 * $R); $row++) {
            $dy = $row + 0.5 - $R;
            $half = sqrt(max(0, $R * $R - $dy * $dy));
            $c = $this->color(self::mix($from, $to, $row / max(1, 2 * $R - 1)));
            imageline($this->img, (int)round($CX - $half), $top + $row, (int)round($CX + $half) - 1, $top + $row, $c);
        }
    }

    /** 圆环:沿圆周画短粗线段。用实色,半透明的话线段接缝会叠深 */
    protected function ring($cx, $cy, $r, $thickness, array $rgb)
    {
        $s = self::S;
        imagesetthickness($this->img, (int)max(1, round($thickness * $s)));
        $c = $this->color($rgb);
        $n = 720;
        $px = $cx * $s + $r * $s;
        $py = $cy * $s;
        for ($i = 1; $i <= $n; $i++) {
            $a = $i / $n * 2 * M_PI;
            $x = $cx * $s + $r * $s * cos($a);
            $y = $cy * $s + $r * $s * sin($a);
            imageline($this->img, (int)round($px), (int)round($py), (int)round($x), (int)round($y), $c);
            $px = $x;
            $py = $y;
        }
        imagesetthickness($this->img, 1);
    }

    /** 光晕:一圈圈缩小的半透明圆叠出从中心向外淡出的效果 */
    protected function halo($cx, $cy, $r, array $rgb, float $centerOpacity, int $steps = 48)
    {
        $each = 1 - pow(1 - $centerOpacity, 1 / $steps);
        for ($i = 0; $i < $steps; $i++) {
            $this->circle($cx, $cy, $r * (1 - $i / $steps), $rgb, $each);
        }
    }

    protected function polygon(array $points, array $rgb, float $opacity = 1.0)
    {
        $flat = [];
        foreach ($points as [$x, $y]) {
            $flat[] = (int)round($x * self::S);
            $flat[] = (int)round($y * self::S);
        }
        imagefilledpolygon($this->img, $flat, $this->color($rgb, $opacity));
    }

    /** 三次贝塞尔曲线首尾相连围成的形状(礼盒蝴蝶结),坐标相对 ox/oy */
    protected function bezierLoop($ox, $oy, array $p0, array $c1, array $c2, array $p1, array $rgb)
    {
        $pts = [];
        for ($i = 0; $i <= 40; $i++) {
            $t = $i / 40;
            $u = 1 - $t;
            $pts[] = [
                $ox + $u * $u * $u * $p0[0] + 3 * $u * $u * $t * $c1[0] + 3 * $u * $t * $t * $c2[0] + $t * $t * $t * $p1[0],
                $oy + $u * $u * $u * $p0[1] + 3 * $u * $u * $t * $c1[1] + 3 * $u * $t * $t * $c2[1] + $t * $t * $t * $p1[1],
            ];
        }
        $this->polygon($pts, $rgb);
    }

    /**
     * 圆角矩形。半透明时不能拿「矩形 + 四个圆」拼(重叠处会叠深),改成逐行画横线
     */
    protected function roundRect($x, $y, $w, $h, $r, array $rgb, float $opacity = 1.0)
    {
        $c = $this->color($rgb, $opacity);
        $this->roundRows($x, $y, $w, $h, $r, function () use ($c) {
            return $c;
        });
    }

    protected function roundRows($x, $y, $w, $h, $r, callable $colorAt)
    {
        $s = self::S;
        $X = $x * $s;
        $Y = (int)round($y * $s);
        $W = $w * $s;
        $H = (int)round($h * $s);
        $R = min($r * $s, $W / 2, $H / 2);
        for ($row = 0; $row < $H; $row++) {
            $py = $row + 0.5;
            $inset = 0;
            if ($py < $R) {
                $d = $R - $py;
                $inset = $R - sqrt(max(0, $R * $R - $d * $d));
            } elseif ($py > $H - $R) {
                $d = $py - ($H - $R);
                $inset = $R - sqrt(max(0, $R * $R - $d * $d));
            }
            $x1 = (int)round($X + $inset);
            $x2 = (int)round($X + $W - $inset) - 1;
            if ($x2 >= $x1) {
                imageline($this->img, $x1, $Y + $row, $x2, $Y + $row, $colorAt($row / max(1, $H - 1)));
            }
        }
    }

    /** 把矩形四个角圆角以外的部分刷成 $rgb(用于给贴图、渐变块做圆角,前提是底色是纯色) */
    protected function maskCorners($x, $y, $w, $h, $r, array $rgb)
    {
        $s = self::S;
        $X = (int)round($x * $s);
        $Y = (int)round($y * $s);
        $W = (int)round($w * $s);
        $H = (int)round($h * $s);
        $R = (int)round($r * $s);
        $c = $this->color($rgb);
        for ($i = 0; $i < $R; $i++) {
            for ($j = 0; $j < $R; $j++) {
                $dx = $R - ($j + 0.5);
                $dy = $R - ($i + 0.5);
                if ($dx * $dx + $dy * $dy <= $R * $R) {
                    continue;
                }
                imagesetpixel($this->img, $X + $j, $Y + $i, $c);
                imagesetpixel($this->img, $X + $W - 1 - $j, $Y + $i, $c);
                imagesetpixel($this->img, $X + $j, $Y + $H - 1 - $i, $c);
                imagesetpixel($this->img, $X + $W - 1 - $j, $Y + $H - 1 - $i, $c);
            }
        }
    }

    protected function verticalGradient($y0, $y1, array $from, array $to, $x = 0, $w = self::W)
    {
        $s = self::S;
        $top = (int)round($y0 * $s);
        $bottom = (int)round($y1 * $s);
        $span = max(1, $bottom - $top - 1);
        $x1 = (int)round($x * $s);
        $x2 = (int)round(($x + $w) * $s) - 1;
        for ($y = $top; $y < $bottom; $y++) {
            imageline($this->img, $x1, $y, $x2, $y, $this->color(self::mix($from, $to, ($y - $top) / $span)));
        }
    }

    /**
     * 竖向多段半透明遮罩。$stops = [[位置0~1, 颜色, 不透明度], ...],相邻两段之间颜色和不透明度线性插值
     */
    protected function shadeStops($y0, $y1, array $stops, $x = 0, $w = self::W)
    {
        $s = self::S;
        $top = (int)round($y0 * $s);
        $bottom = (int)round($y1 * $s);
        $span = max(1, $bottom - $top);
        $x1 = (int)round($x * $s);
        $x2 = (int)round(($x + $w) * $s) - 1;
        for ($y = $top; $y < $bottom; $y++) {
            $t = ($y - $top) / $span;
            for ($k = 1; $k < count($stops); $k++) {
                if ($t <= $stops[$k][0] || $k === count($stops) - 1) {
                    [$t0, $c0, $a0] = $stops[$k - 1];
                    [$t1, $c1, $a1] = $stops[$k];
                    $f = $t1 > $t0 ? max(0, min(1, ($t - $t0) / ($t1 - $t0))) : 1;
                    $a = $a0 + ($a1 - $a0) * $f;
                    if ($a > 0.002) {
                        imageline($this->img, $x1, $y, $x2, $y, $this->color(self::mix($c0, $c1, $f), $a));
                    }
                    break;
                }
            }
        }
    }

    /** 取画布上某点当前的颜色(750 坐标) */
    protected function pick($x, $y): array
    {
        $rgb = imagecolorat($this->img, (int)($x * self::S), (int)($y * self::S));
        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    // ═════════════════════════ 文字 ═════════════════════════

    /**
     * 画一行文字。$cy 是这一行的垂直中线,$spacing 是字间距
     */
    protected function text(string $text, string $font, $size, array $rgb, $x, $cy, float $opacity = 1.0, $spacing = 0)
    {
        $this->textOn($this->img, $text, $font, $size, $this->color($rgb, $opacity), $x * self::S, $cy * self::S, $spacing);
    }

    protected function textOn($img, string $text, string $font, $size, $color, float $X, float $CY, $spacing = 0)
    {
        if ($text === '') return;
        $pt = $this->pt($size);
        $baseline = $this->baseline($font, $pt, $CY);
        if ($spacing == 0) {
            imagettftext($img, $pt, 0, (int)round($X), $baseline, $color, $font, $text);
            return;
        }
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            imagettftext($img, $pt, 0, (int)round($X), $baseline, $color, $font, $ch);
            $X += $this->rawWidth($ch, $font, $pt) + $spacing * self::S;
        }
    }

    protected function textCentered(string $text, string $font, $size, array $rgb, $cx, $cy, $spacing = 0, float $opacity = 1.0)
    {
        $w = $this->textWidth($text, $font, $size, $spacing);
        $this->text($text, $font, $size, $rgb, $cx - $w / 2, $cy, $opacity, $spacing);
    }

    /**
     * 竖向渐变色的居中标题(黑金模板)。先把字画到透明图层取字形透明度,再按行上色贴回
     * @param array $stops [[位置0~1, 颜色], ...]
     */
    protected function titleGradientCentered(string $title, $cy, $size, $minSize, $maxW, $spacing, array $stops)
    {
        $size = $this->shrink($title, $this->fontBold, $size, $minSize, $maxW, $spacing);
        $title = $this->fit($title, $this->fontBold, $size, $maxW, $spacing);
        $s = self::S;
        $w = $this->textWidth($title, $this->fontBold, $size, $spacing);
        $LW = (int)ceil(($w + 20) * $s);
        $LH = (int)ceil($size * 1.6 * $s);

        $layer = imagecreatetruecolor($LW, $LH);
        imagesavealpha($layer, true);
        imagealphablending($layer, false);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagealphablending($layer, true);
        $this->textOn($layer, $title, $this->fontBold, $size, imagecolorallocatealpha($layer, 255, 255, 255, 0), 10 * $s, $LH / 2, $spacing);
        imagealphablending($layer, false);

        // 渐变范围取字形的实际上下沿(按「汉」字高度),而不是整个图层,颜色才分布得和设计稿一样
        $pt = $this->pt($size);
        $box = imagettfbbox($pt, 0, $this->fontBold, '汉');
        $glyphH = max($box[1], $box[3]) - min($box[5], $box[7]);
        $gTop = $LH / 2 - $glyphH / 2;

        for ($py = 0; $py < $LH; $py++) {
            $t = max(0, min(1, ($py - $gTop) / max(1, $glyphH)));
            $rgb = $stops[count($stops) - 1][1];
            for ($k = 1; $k < count($stops); $k++) {
                if ($t <= $stops[$k][0]) {
                    $f = ($t - $stops[$k - 1][0]) / max(0.0001, $stops[$k][0] - $stops[$k - 1][0]);
                    $rgb = self::mix($stops[$k - 1][1], $stops[$k][1], $f);
                    break;
                }
            }
            for ($px = 0; $px < $LW; $px++) {
                $alpha = (imagecolorat($layer, $px, $py) >> 24) & 0x7F;
                if ($alpha < 127) {
                    imagesetpixel($layer, $px, $py, imagecolorallocatealpha($layer, $rgb[0], $rgb[1], $rgb[2], $alpha));
                }
            }
        }
        imagecopy($this->img, $layer, (int)round((375 - $w / 2 - 10) * $s), (int)round($cy * $s - $LH / 2), 0, 0, $LW, $LH);
        imagedestroy($layer);
    }

    /**
     * 按基线画一行文字(同一行里混排不同字号时用,比如价格的 ¥ / 整数 / 小数)
     * @return float 从 $x 到字形右边缘的距离,下一段文字从 $x + 返回值 接着画
     *   (不能用 textWidth:那是字形墨迹宽度,不含左侧留白,接着画会和上一段叠在一起)
     */
    protected function textAtBaseline(string $text, string $font, $size, array $rgb, $x, $baseline, float $opacity = 1.0): float
    {
        if ($text === '') return 0;
        $pt = $this->pt($size);
        imagettftext($this->img, $pt, 0, (int)round($x * self::S), (int)round($baseline * self::S),
            $this->color($rgb, $opacity), $font, $text);
        return $this->advance($text, $font, $size);
    }

    /** 从起笔点到字形右边缘的距离(750 坐标) */
    protected function advance(string $text, string $font, $size): float
    {
        if ($text === '') return 0;
        $box = imagettfbbox($this->pt($size), 0, $font, $text);
        return max($box[2], $box[4]) / self::S;
    }

    /**
     * 按宽度折行,最多 $maxLines 行,超出的最后一行截断加省略号
     * @return string[]
     */
    protected function wrapLines(string $text, string $font, $size, $maxW, int $maxLines): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if ($current !== '' && $this->textWidth($current . $ch, $font, $size) > $maxW) {
                $lines[] = $current;
                $current = $ch;
                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $current .= $ch;
            }
        }
        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
            $current = '';
        }
        if ($current !== '' || mb_strlen(implode('', $lines)) < mb_strlen($text)) {
            // 放不下的部分:最后一行从尾部去字,直到加上省略号放得下
            $last = array_pop($lines);
            while (mb_strlen($last) > 1 && $this->textWidth($last . '…', $font, $size) > $maxW) {
                $last = mb_substr($last, 0, mb_strlen($last) - 1);
            }
            $lines[] = $last . '…';
        }
        return $lines;
    }

    /** 文字宽度(750 坐标),字间距只算字与字之间 */
    protected function textWidth(string $text, string $font, $size, $spacing = 0): float
    {
        if ($text === '') return 0;
        $pt = $this->pt($size);
        if ($spacing == 0) {
            return $this->rawWidth($text, $font, $pt) / self::S;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $w = 0;
        foreach ($chars as $ch) {
            $w += $this->rawWidth($ch, $font, $pt);
        }
        return ($w + $spacing * self::S * (count($chars) - 1)) / self::S;
    }

    /** 放不下就从尾部截字并加省略号 */
    protected function fit(string $text, string $font, $size, $maxW, $spacing = 0): string
    {
        if ($this->textWidth($text, $font, $size, $spacing) <= $maxW) {
            return $text;
        }
        while (mb_strlen($text) > 1) {
            $text = mb_substr($text, 0, mb_strlen($text) - 1);
            if ($this->textWidth($text . '…', $font, $size, $spacing) <= $maxW) {
                break;
            }
        }
        return $text . '…';
    }

    /** CSS 像素字号 → GD 磅值(GD 按 96dpi 换算),再乘超采样 */
    protected function pt($px): float
    {
        return $px * self::S * 0.75;
    }

    protected function rawWidth(string $text, string $font, float $pt): float
    {
        $box = imagettfbbox($pt, 0, $font, $text);
        return max($box[2], $box[4]) - min($box[0], $box[6]);
    }

    /** 用「汉」字的字形高度把一行文字的中线对到 $cy 上,中英文混排也不跳 */
    protected function baseline(string $font, float $pt, float $cy): int
    {
        $box = imagettfbbox($pt, 0, $font, '汉');
        $top = min($box[5], $box[7]);
        $bottom = max($box[1], $box[3]);
        return (int)round($cy - ($top + $bottom) / 2);
    }

    // ═════════════════════════ 贴图 ═════════════════════════

    protected function loadOptional($path)
    {
        if (!is_string($path) || $path === '' || !is_file($path)) return null;
        $data = @file_get_contents($path);
        if ($data === false || $data === '') return null;
        $im = @imagecreatefromstring($data);
        return $im ?: null;
    }

    /** 按 cover 铺满目标区域,多出的部分居中裁掉 */
    protected function pasteCover($src, $x, $y, $w, $h)
    {
        $s = self::S;
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = $w / $scale;
        $ch = $h / $scale;
        imagecopyresampled($this->img, $src, (int)round($x * $s), (int)round($y * $s),
            (int)round(($sw - $cw) / 2), (int)round(($sh - $ch) / 2),
            (int)round($w * $s), (int)round($h * $s), (int)round($cw), (int)round($ch));
    }

    /** 按 contain 放进目标区域,保留透明(小程序码是透明底) */
    protected function pasteContain($src, $x, $y, $w, $h)
    {
        $s = self::S;
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($w / $sw, $h / $sh);
        $dw = $sw * $scale;
        $dh = $sh * $scale;
        imagecopyresampled($this->img, $src,
            (int)round(($x + ($w - $dw) / 2) * $s), (int)round(($y + ($h - $dh) / 2) * $s),
            0, 0, (int)round($dw * $s), (int)round($dh * $s), $sw, $sh);
    }

    /** 裁成正方形 cover 后抠圆贴上 */
    protected function pasteCircle($src, $x, $y, $d)
    {
        $s = self::S;
        $D = (int)round($d * $s);
        $tmp = imagecreatetruecolor($D, $D);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $clear = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $clear);

        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        imagealphablending($tmp, true);
        imagecopyresampled($tmp, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $D, $D, $side, $side);
        imagealphablending($tmp, false);

        $r = $D / 2;
        for ($py = 0; $py < $D; $py++) {
            for ($px = 0; $px < $D; $px++) {
                $dx = $px + 0.5 - $r;
                $dy = $py + 0.5 - $r;
                if ($dx * $dx + $dy * $dy > $r * $r) {
                    imagesetpixel($tmp, $px, $py, $clear);
                }
            }
        }
        imagecopy($this->img, $tmp, (int)round($x * $s), (int)round($y * $s), 0, 0, $D, $D);
        imagedestroy($tmp);
    }

    // ═════════════════════════ 文字清洗 / 颜色工具 ═════════════════════════

    /**
     * 去掉字体画不出来的字符。微信昵称里 emoji 很常见:微软雅黑没有 emoji 字形,
     * GD 还会把 4 字节 UTF-8 拆坏,画出来是「ð□□」一串乱码,不如直接去掉。
     * 去掉:辅助平面(绝大多数 emoji)、杂项符号/装饰符号(☀✨❤ 等)、变体选择符、零宽字符、控制字符
     */
    public static function cleanText($text): string
    {
        $text = preg_replace('/\s+/u', ' ', (string)$text);
        if ($text === null) {
            // 非法 UTF-8 时 preg 返回 null,整段丢掉比画乱码强
            return '';
        }
        $text = preg_replace('/[\x{10000}-\x{10FFFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200B}-\x{200F}\x{20E3}\x{00}-\x{1F}\x{7F}]/u', '', $text);
        if ($text === null) {
            return '';
        }
        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    /** '#RGB' / '#RRGGBB' → [r,g,b];不合法返回 null */
    public static function parseHex($hex): ?array
    {
        if (!is_string($hex)) return null;
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return null;
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    protected static function mix(array $a, array $b, float $t): array
    {
        return [
            (int)round($a[0] + ($b[0] - $a[0]) * $t),
            (int)round($a[1] + ($b[1] - $a[1]) * $t),
            (int)round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }
}
