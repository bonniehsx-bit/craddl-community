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
 * 商品海报绘制 —— 四套固定模板
 *
 * | key       | 名称     | 用色                         |
 * |-----------|----------|------------------------------|
 * | classic   | 经典白卡 | 价格用主题色                 |
 * | promo     | 主题大促 | 顶部底色、价格角标用主题色   |
 * | magazine  | 大图杂志 | 小标题、价格用主题色         |
 * | blackgold | 黑金臻选 | 固定黑金                     |
 *
 * 只负责画:商品图、头像、码、logo 由调用方准备好本地路径传进来,不碰网络和数据库。
 * 后台预览 admin/src/views/poster/components/GoodsPosterPreview.vue 按同一套坐标用 HTML 画,改一边记得改另一边。
 */
class GoodsPosterRenderer extends PosterCanvas
{
    const DEFAULT_THEME = '#FF4B3E';
    const DEFAULT_TEMPLATE = 'classic';

    /** 模板 key => 名称(后台选择器、配置校验共用) */
    const TEMPLATES = [
        'classic' => '经典白卡',
        'promo' => '主题大促',
        'magazine' => '大图杂志',
        'blackgold' => '黑金臻选',
    ];

    const INK = [31, 34, 41];
    const GRAY = [138, 143, 155];
    const LIGHT_GRAY = [160, 165, 176];

    /**
     * @param array $p [
     *   template, theme_color, main_title, sub_title, recommend,
     *   goods_name, price, origin_price, is_negotiable, sales, goods_pic_path(可空),
     *   nickname, avatar_path(可空), qr_path(可空), qr_kind(mini|qrcode),
     *   mall_name, logo_path(可空)
     * ]
     * @return resource|\GdImage 750×1334 真彩图
     */
    public function render(array $p)
    {
        $this->theme = self::parseHex($p['theme_color'] ?? '') ?: self::parseHex(self::DEFAULT_THEME);
        $template = isset(self::TEMPLATES[$p['template'] ?? '']) ? $p['template'] : self::DEFAULT_TEMPLATE;

        $price = (float)($p['price'] ?? 0);
        $origin = (float)($p['origin_price'] ?? 0);
        $this->p = [
            'main_title' => self::cleanText($p['main_title'] ?? '') ?: '好物推荐',
            'sub_title' => self::cleanText($p['sub_title'] ?? ''),
            'recommend' => self::cleanText($p['recommend'] ?? '') ?: '为你推荐一件好物',
            'goods_name' => self::cleanText($p['goods_name'] ?? '') ?: '商品',
            'price' => $price,
            // 划线价只在比售价高时才画,不然「原价」反而比现价低很奇怪
            'origin_price' => $origin > $price ? $origin : 0,
            'is_negotiable' => !empty($p['is_negotiable']),
            'sales' => max(0, (int)($p['sales'] ?? 0)),
            'goods_pic_path' => $p['goods_pic_path'] ?? null,
            'nickname' => self::cleanText($p['nickname'] ?? '') ?: '好友',
            'mall_name' => self::cleanText($p['mall_name'] ?? ''),
            'avatar_path' => $p['avatar_path'] ?? null,
            'logo_path' => $p['logo_path'] ?? null,
            'qr_path' => $p['qr_path'] ?? null,
            'is_mini' => ($p['qr_kind'] ?? 'mini') === 'mini',
        ];

        $this->begin();
        switch ($template) {
            case 'promo':
                $this->drawPromo();
                break;
            case 'magazine':
                $this->drawMagazine();
                break;
            case 'blackgold':
                $this->drawBlackGold();
                break;
            default:
                $this->drawClassic();
        }
        return $this->finish();
    }

    // ═════════════════════════ ① 经典白卡 ═════════════════════════

    private function drawClassic()
    {
        $page = [244, 245, 247];
        $white = [255, 255, 255];
        $this->rect(0, 0, self::W, self::H, $page);

        $this->drawBrandRow(60, 74, self::INK, [230, 232, 236]);

        $this->roundRect(40, 130, 670, 1164, 28, $white);
        $this->goodsPic(70, 160, 610, 610, 20, $white);

        $this->nameLines(80, 826, 34, 49, 590, self::INK);
        $this->priceRow(80, 990, 76, $this->theme, 26, self::LIGHT_GRAY);

        // 销量标签(和详情页一样读虚拟销量,0 就不画)
        if ($this->p['sales'] > 0) {
            $label = '已售 ' . self::formatCount($this->p['sales']);
            $w = $this->textWidth($label, $this->fontRegular, 22) + 32;
            $this->roundRect(670 - $w, 938, $w, 36, 18, $page);
            $this->textCentered($label, $this->fontRegular, 22, self::GRAY, 670 - $w / 2, 956);
        }

        for ($x = 80; $x < 670; $x += 14) {
            $this->rect($x, 1029, min(7, 670 - $x), 2, [230, 232, 236]);
        }

        $this->avatarAt(120, 1120, 40);
        $this->text($this->fit($this->p['nickname'], $this->fontBold, 28, 280), $this->fontBold, 28, self::INK, 178, 1102);
        $this->text($this->fit($this->p['recommend'], $this->fontRegular, 24, 280), $this->fontRegular, 24, self::GRAY, 178, 1142);

        $this->pasteQr(480, 1060, 190);
        $this->textCentered('长按识别购买', $this->fontRegular, 20, self::LIGHT_GRAY, 575, 1270);
    }

    // ═════════════════════════ ② 主题大促 ═════════════════════════

    private function drawPromo()
    {
        $white = [255, 255, 255];
        $this->rect(0, 0, self::W, self::H, [246, 246, 248]);
        $this->verticalGradient(0, 640, self::mix($this->theme, $white, 0.15), self::mix($this->theme, [0, 0, 0], 0.1));
        $this->circle(670, 60, 260, $white, 0.08);
        $this->circle(40, 430, 130, $white, 0.07);

        $this->drawBrandRow(50, 80, $white);
        $this->titleLine($this->p['main_title'], 50, 176, 64, 48, 650, $white, 4);
        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 28, 650);
            $this->text($sub, $this->fontRegular, 28, $white, 52, 252, 0.88);
        }

        // 卡片投影:往下错 6px 的极淡圆角块
        $this->roundRect(40, 326, 670, 974, 28, [0, 0, 0], 0.04);
        $this->roundRect(40, 320, 670, 974, 28, $white);
        $this->goodsPic(64, 344, 622, 560, 18, $white);

        // 价格角标:左边贴图片边,右边圆头
        if ($this->p['is_negotiable']) {
            $w = $this->textWidth('价格面议', $this->fontBold, 36) + 48;
            $this->roundRect(64, 840, $w, 64, 32, $this->theme);
            $this->rect(64, 840, 32, 64, $this->theme);
            $this->text('价格面议', $this->fontBold, 36, $white, 86, 872);
        } else {
            $amount = self::formatPrice($this->p['price']);
            $w = $this->advance('¥', $this->fontBold, 24) + 4 + $this->advance($amount, $this->fontBold, 44) + 44;
            $this->roundRect(64, 840, $w, 64, 32, $this->theme);
            $this->rect(64, 840, 32, 64, $this->theme);
            $x = 86 + $this->textAtBaseline('¥', $this->fontBold, 24, $white, 86, 888) + 4;
            $this->textAtBaseline($amount, $this->fontBold, 44, $white, $x, 888);
        }

        $this->nameLines(76, 955, 32, 46, 598, self::INK);
        if ($this->p['origin_price'] > 0 && !$this->p['is_negotiable']) {
            $lead = '原价 ';
            $leadW = $this->textAtBaseline($lead, $this->fontRegular, 24, self::LIGHT_GRAY, 76, 1063);
            $this->strikeText('¥' . self::formatPrice($this->p['origin_price']), 24, self::LIGHT_GRAY, 76 + $leadW, 1063);
        }

        $soft = self::mix($this->theme, $white, 0.93);
        $muted = self::mix($this->theme, [110, 110, 110], 0.55);
        $this->roundRect(64, 1096, 622, 174, 18, $soft);
        $this->avatar(124, 1170, 36, [[40, $white]]);
        $this->text($this->fit($this->p['nickname'], $this->fontBold, 26, 270), $this->fontBold, 26, self::INK, 176, 1152);
        $this->text($this->fit($this->p['recommend'], $this->fontRegular, 22, 270), $this->fontRegular, 22, $muted, 176, 1188);

        $this->roundRect(468, 1064, 200, 200, 18, [0, 0, 0], 0.05);
        $this->roundRect(468, 1056, 200, 200, 18, $white);
        $this->pasteQr(480, 1068, 176);
        $this->textCentered('长按识别购买', $this->fontRegular, 20, $muted, 568, 1281);
    }

    // ═════════════════════════ ③ 大图杂志 ═════════════════════════

    private function drawMagazine()
    {
        $white = [255, 255, 255];
        $this->rect(0, 0, self::W, self::H, $white);
        $this->goodsPic(0, 0, self::W, 900, 0, $white);
        $this->shadeStops(0, 220, [[0, [0, 0, 0], 0.4], [1, [0, 0, 0], 0]]);

        $this->drawBrandRow(50, 76, $white, null, 2);

        // 底部白色面板,上边两个圆角(下边超出画布,不用管)
        $this->roundRect(0, 820, self::W, 600, 40, $white);

        $this->text($this->fit($this->p['main_title'], $this->fontBold, 22, 400, 6), $this->fontBold, 22, $this->theme, 56, 897, 1.0, 6);
        $this->nameLines(56, 952, 36, 52, 420, self::INK);
        $this->priceRow(56, 1118, 68, $this->theme, 24, self::LIGHT_GRAY);

        $this->pasteQr(500, 880, 200);
        $this->textCentered('长按识别购买', $this->fontRegular, 20, self::LIGHT_GRAY, 600, 1104, 1);

        $this->rect(56, 1160, 638, 1, [236, 237, 240]);
        $this->avatarAt(88, 1234, 32);
        $nick = $this->fit($this->p['nickname'], $this->fontBold, 26, 300);
        $nickW = $this->textWidth($nick, $this->fontBold, 26);
        $this->text($nick, $this->fontBold, 26, self::INK, 136, 1234);
        $rec = $this->fit($this->p['recommend'], $this->fontRegular, 24, 694 - 136 - $nickW - 12);
        $this->text($rec, $this->fontRegular, 24, self::GRAY, 136 + $nickW + 12, 1234);
    }

    // ═════════════════════════ ④ 黑金臻选 ═════════════════════════

    private function drawBlackGold()
    {
        $ink = [18, 18, 21];
        $gold = self::GOLD;
        $white = [255, 255, 255];

        $this->rect(0, 0, self::W, self::H, $ink);
        $this->halo(375, 530, 270, $gold, 0.2);

        if ($this->p['mall_name'] !== '') {
            $this->textCentered($this->fit($this->p['mall_name'], $this->fontRegular, 24, 600, 10), $this->fontRegular, 24, $gold, 375, 81, 10);
        }
        $this->textCentered('SELECTED', $this->fontRegular, 20, $gold, 375, 124, 12, 0.6);

        $this->strokeRect(75, 170, 600, 600, 1.5, $gold, 0.7);
        $this->goodsPic(95, 190, 560, 560, 0, $ink);

        $lines = $this->wrapLines($this->p['goods_name'], $this->fontBold, 34, 570, 2);
        foreach ($lines as $i => $line) {
            $this->textCentered($line, $this->fontBold, 34, $white, 375, 834 + $i * 49, 1);
        }

        $this->rect(276, 925, 80, 1.5, $gold, 0.6);
        $this->rect(394, 925, 80, 1.5, $gold, 0.6);
        $this->polygon([[375, 918.7], [382, 925.7], [375, 932.7], [368, 925.7]], $gold);

        // 价格整体居中:先量出整行宽度再画
        $priceGold = [233, 201, 138];
        $rowW = $this->priceRowWidth(72, 24);
        $this->priceRow(375 - $rowW / 2, 1020, 72, $priceGold, 24, $white, 0.4);

        $this->rect(60, 1080, 630, 1, $gold, 0.25);
        $this->avatar(96, 1176, 36, [[40, $gold], [38, $ink]]);
        $this->text($this->fit($this->p['nickname'], $this->fontBold, 26, 320), $this->fontBold, 26, $white, 148, 1158);
        $tip = $this->p['is_mini'] ? '长按识别右侧小程序码购买' : '长按识别右侧二维码购买';
        $this->text($this->fit($tip, $this->fontRegular, 22, 360), $this->fontRegular, 22, $gold, 148, 1194, 0.8);

        $this->roundRect(538.5, 1108.5, 153, 153, 13.5, $gold);
        $this->roundRect(540, 1110, 150, 150, 12, $white);
        $this->pasteQr(550, 1120, 130);
    }

    // ═════════════════════════ 商品模板公共部分 ═════════════════════════

    /**
     * 顶部品牌行:圆形 logo + 商城名。$ringColor 给 logo 外面加一圈细边(白底页面上用)
     */
    private function drawBrandRow($x, $cy, array $nameColor, ?array $ringColor = null, $spacing = 0)
    {
        $mallName = $this->p['mall_name'];
        if ($mallName === '' && !$this->p['logo_path']) {
            return;
        }
        if ($ringColor) {
            $this->circle($x + 24, $cy, 25, $ringColor);
        }
        $this->circle($x + 24, $cy, 24, [255, 255, 255]);
        $logo = $this->loadOptional($this->p['logo_path']);
        if ($logo) {
            $this->pasteCircle($logo, $x + 4, $cy - 20, 40);
            imagedestroy($logo);
        } elseif ($mallName !== '') {
            $this->textCentered(mb_substr($mallName, 0, 1), $this->fontBold, 24, $this->theme, $x + 24, $cy);
        }
        if ($mallName !== '') {
            $name = $this->fit($mallName, $this->fontBold, 26, 750 - $x - 62 - 60, $spacing);
            $this->text($name, $this->fontBold, 26, $nameColor, $x + 62, $cy, 1.0, $spacing);
        }
    }

    /** 商品图:cover 裁切,$radius>0 时用 $cornerColor 抠圆角(底色必须是纯色);没图画浅灰占位 */
    private function goodsPic($x, $y, $w, $h, $radius, array $cornerColor)
    {
        $pic = $this->loadOptional($this->p['goods_pic_path']);
        if ($pic) {
            $this->pasteCover($pic, $x, $y, $w, $h);
            imagedestroy($pic);
        } else {
            $this->rect($x, $y, $w, $h, [236, 238, 242]);
        }
        if ($radius > 0) {
            $this->maskCorners($x, $y, $w, $h, $radius, $cornerColor);
        }
    }

    /** 商品名,最多两行。$firstCy 是第一行中线,$lineH 行高 */
    private function nameLines($x, $firstCy, $size, $lineH, $maxW, array $rgb)
    {
        foreach ($this->wrapLines($this->p['goods_name'], $this->fontBold, $size, $maxW, 2) as $i => $line) {
            $this->text($line, $this->fontBold, $size, $rgb, $x, $firstCy + $i * $lineH);
        }
    }

    /**
     * 价格行:¥(半号) + 整数(整号) + 小数(六成号) + 划线价,同一基线
     * 面议商品画「价格面议」,不画划线价
     */
    private function priceRow($x, $baseline, $size, array $rgb, $originSize, array $originRgb, float $originOpacity = 1.0)
    {
        if ($this->p['is_negotiable']) {
            $this->textAtBaseline('价格面议', $this->fontBold, $size * 0.6, $rgb, $x, $baseline);
            return;
        }
        [$int, $dec] = self::splitPrice($this->p['price']);
        $cursor = $x;
        $cursor += $this->textAtBaseline('¥', $this->fontBold, $size * 0.5, $rgb, $cursor, $baseline) + 4;
        $cursor += $this->textAtBaseline($int, $this->fontBold, $size, $rgb, $cursor, $baseline) + 2;
        $cursor += $this->textAtBaseline($dec, $this->fontBold, $size * 0.6, $rgb, $cursor, $baseline);
        if ($this->p['origin_price'] > 0) {
            $this->strikeText('¥' . self::formatPrice($this->p['origin_price']), $originSize, $originRgb, $cursor + 16, $baseline, $originOpacity);
        }
    }

    private function priceRowWidth($size, $originSize): float
    {
        if ($this->p['is_negotiable']) {
            return $this->textWidth('价格面议', $this->fontBold, $size * 0.6);
        }
        [$int, $dec] = self::splitPrice($this->p['price']);
        $w = $this->advance('¥', $this->fontBold, $size * 0.5) + 4
            + $this->advance($int, $this->fontBold, $size) + 2
            + $this->advance($dec, $this->fontBold, $size * 0.6);
        if ($this->p['origin_price'] > 0) {
            $w += 16 + $this->advance('¥' . self::formatPrice($this->p['origin_price']), $this->fontRegular, $originSize);
        }
        return $w;
    }

    /** 带删除线的文字(划线价) */
    private function strikeText(string $text, $size, array $rgb, $x, $baseline, float $opacity = 1.0)
    {
        $w = $this->textAtBaseline($text, $this->fontRegular, $size, $rgb, $x, $baseline, $opacity);
        $this->rect($x, $baseline - $size * 0.36, $w, max(1.5, $size / 14), $rgb, $opacity);
    }

    /** 99 → "99.00";1234.5 → "1234.50" */
    public static function formatPrice($price): string
    {
        return number_format((float)$price, 2, '.', '');
    }

    /** 99.5 → ["99", ".50"] */
    private static function splitPrice($price): array
    {
        $s = self::formatPrice($price);
        $dot = strpos($s, '.');
        return [substr($s, 0, $dot), substr($s, $dot)];
    }

    /** 23456 → "2.3万";999 → "999" */
    public static function formatCount(int $n): string
    {
        if ($n >= 10000) {
            return rtrim(rtrim(number_format($n / 10000, 1, '.', ''), '0'), '.') . '万';
        }
        return (string)$n;
    }
}
