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
 * 推广(邀请)海报绘制 —— 五套固定模板
 *
 * | key       | 名称     | 主题色 | 背景图                 |
 * |-----------|----------|--------|------------------------|
 * | ticket    | 邀请票券 | 跟随   | 可选,铺上半张          |
 * | simple    | 简约白   | 跟随   | 可选,铺进顶部大卡片    |
 * | redpacket | 喜庆红包 | 固定红 | 不用                   |
 * | magazine  | 杂志大图 | 点缀色 | 建议配,铺满整张        |
 * | blackgold | 黑金尊享 | 固定金 | 不用                   |
 *
 * 只负责画:素材(头像、码、logo、背景图)由调用方准备好本地路径传进来,这里不碰网络和数据库,
 * 所以能在命令行里用假素材直接出图对版。后台预览 admin/src/views/poster/components/InvitePosterPreview.vue
 * 按同一套坐标用 HTML 画,改一边记得改另一边。
 *
 * 绘图基础方法在 PosterCanvas;文字宽度都按实测收:标题放不下先缩字号再截断,其它单行截断加省略号
 */
class InvitePosterRenderer extends PosterCanvas
{
    const DEFAULT_THEME = '#FF4B3E';
    const DEFAULT_TEMPLATE = 'ticket';

    /** 模板 key => 名称(后台选择器、配置校验共用) */
    const TEMPLATES = [
        'ticket' => '邀请票券',
        'simple' => '简约白',
        'redpacket' => '喜庆红包',
        'magazine' => '杂志大图',
        'blackgold' => '黑金尊享',
    ];


    /**
     * @param array $p [
     *   template, theme_color, main_title, sub_title,
     *   bg_path(可空), nickname, avatar_path(可空), qr_path(可空), qr_kind(mini|qrcode),
     *   mall_name, logo_path(可空)
     * ]
     * @return resource|\GdImage 750×1334 真彩图
     */
    public function render(array $p)
    {
        $this->theme = self::parseHex($p['theme_color'] ?? '') ?: self::parseHex(self::DEFAULT_THEME);
        $template = isset(self::TEMPLATES[$p['template'] ?? '']) ? $p['template'] : self::DEFAULT_TEMPLATE;

        $this->p = [
            'main_title' => self::cleanText($p['main_title'] ?? '') ?: '诚邀加入',
            'sub_title' => self::cleanText($p['sub_title'] ?? ''),
            'nickname' => self::cleanText($p['nickname'] ?? '') ?: '好友',
            'mall_name' => self::cleanText($p['mall_name'] ?? ''),
            'bg_path' => $p['bg_path'] ?? null,
            'avatar_path' => $p['avatar_path'] ?? null,
            'logo_path' => $p['logo_path'] ?? null,
            'qr_path' => $p['qr_path'] ?? null,
            'is_mini' => ($p['qr_kind'] ?? 'mini') === 'mini',
        ];

        $this->begin();

        switch ($template) {
            case 'simple':
                $this->drawSimple();
                break;
            case 'redpacket':
                $this->drawRedPacket();
                break;
            case 'magazine':
                $this->drawMagazine();
                break;
            case 'blackgold':
                $this->drawBlackGold();
                break;
            default:
                $this->drawTicket();
        }

        return $this->finish();
    }

    private function codeTip(): string
    {
        return $this->p['is_mini'] ? '长按识别小程序码' : '长按识别二维码';
    }

    // ═════════════════════════ ① 邀请票券 ═════════════════════════

    private function drawTicket()
    {
        $bg = $this->loadOptional($this->p['bg_path']);
        // 有背景图时下半张要压得更深,白卡片才托得住
        $dark = self::mix($this->theme, [0, 0, 0], $bg ? 0.38 : 0.15);

        if ($bg) {
            $this->rect(0, 0, self::W, self::H, $dark);
            $this->pasteCover($bg, 0, 0, self::W, 760);
            imagedestroy($bg);
            // 顶部黑 55% → 45% 处黑 25% → 88% 处主题深色 85% → 底部完全过渡到主题深色
            $this->shadeStops(0, 760, [
                [0, [0, 0, 0], 0.55],
                [0.45, [0, 0, 0], 0.25],
                [0.88, $dark, 0.85],
                [1, $dark, 1],
            ]);
        } else {
            $this->verticalGradient(0, self::H, $this->theme, $dark);
            $this->circle(690, 80, 280, [255, 255, 255], 0.08);
            $this->circle(30, 480, 150, [255, 255, 255], 0.06);
            $this->circle(600, 480, 60, [255, 255, 255], 0.10);
        }

        $mallName = $this->p['mall_name'];
        $this->drawRoundBrand(56, 94, [255, 255, 255]);

        $this->pill('专属邀请', 56, 206);
        $this->titleLine($this->p['main_title'], 52, 337, 104, 72, 750 - 104, [255, 255, 255], 6);
        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 32, 750 - 112, 1);
            $this->text($sub, $this->fontRegular, 32, [255, 255, 255], 56, 434, 0.88, 1);
        }

        $cx = 40;
        $cy = 600;
        $cw = 670;
        $dashY = $cy + 246;
        // 票券两侧缺口的颜色 = 缺口处背景的真实颜色,先取色再画卡片
        $notchL = $this->pick(20, $dashY);
        $notchR = $this->pick(730, $dashY);
        $this->roundRect($cx, $cy, $cw, 674, 32, [255, 255, 255]);
        $this->circle($cx, $dashY, 22, $notchL);
        $this->circle($cx + $cw, $dashY, 22, $notchR);
        for ($x = $cx + 44; $x < $cx + $cw - 44; $x += 14) {
            $this->rect($x, $dashY - 1.5, min(7, $cx + $cw - 44 - $x), 3, [230, 232, 236]);
        }

        $this->avatar(375, $cy, 63, [[70, [255, 255, 255]]]);

        $nick = $this->fit($this->p['nickname'], $this->fontBold, 36, $cw - 80);
        $this->textCentered($nick, $this->fontBold, 36, [31, 34, 41], 375, $cy + 121);

        $lead = '邀请你加入 ';
        $leadW = $this->textWidth($lead, $this->fontRegular, 26);
        $name = $mallName !== '' ? $this->fit($mallName, $this->fontBold, 26, $cw - 80 - $leadW) : '';
        if ($name === '') {
            $this->textCentered('邀请你一起加入', $this->fontRegular, 26, [138, 143, 155], 375, $cy + 170);
        } else {
            $x0 = 375 - ($leadW + $this->textWidth($name, $this->fontBold, 26)) / 2;
            $this->text($lead, $this->fontRegular, 26, [138, 143, 155], $x0, $cy + 170);
            $this->text($name, $this->fontBold, 26, $this->theme, $x0 + $leadW, $cy + 170);
        }

        $this->roundRect(225, $cy + 292, 300, 300, 24, self::mix($this->theme, [255, 255, 255], 0.92));
        $this->pasteQr(247, $cy + 314, 256);

        $tip = $this->codeTip();
        $tipY = $cy + 631;
        $tipW = $this->textWidth($tip, $this->fontRegular, 24, 2);
        $this->textCentered($tip, $this->fontRegular, 24, [160, 165, 176], 375, $tipY, 2);
        $this->rect(375 - $tipW / 2 - 64, $tipY - 1, 48, 2, [225, 227, 232]);
        $this->rect(375 + $tipW / 2 + 16, $tipY - 1, 48, 2, [225, 227, 232]);
    }

    // ═════════════════════════ ② 简约白 ═════════════════════════

    private function drawSimple()
    {
        $page = [244, 245, 247];
        $soft = self::mix($this->theme, [255, 255, 255], 0.9);
        $this->rect(0, 0, self::W, self::H, $page);

        // 顶部大卡片:有背景图铺图并压暗顶部,没有就主题色渐变 + 礼盒
        $hx = 40;
        $hy = 40;
        $hw = 670;
        $hh = 820;
        $bg = $this->loadOptional($this->p['bg_path']);
        if ($bg) {
            $this->pasteCover($bg, $hx, $hy, $hw, $hh);
            imagedestroy($bg);
            $this->shadeStops($hy, $hy + $hh, [
                [0, [0, 0, 0], 0.55],
                [0.45, [0, 0, 0], 0.08],
                [1, [0, 0, 0], 0],
            ], $hx, $hw);
        } else {
            $this->verticalGradient($hy, $hy + $hh, self::mix($this->theme, [255, 255, 255], 0.12),
                self::mix($this->theme, [0, 0, 0], 0.1), $hx, $hw);
            $this->circle(620, 150, 260, [255, 255, 255], 0.07);
            $this->circle(620, 150, 180, [255, 255, 255], 0.07);
            $this->drawGift(225, 400, $soft);
        }
        // 卡片外溢出的装饰圆/图片刷回页面底色,再抠圆角
        $this->rect(0, 0, self::W, $hy, $page);
        $this->rect($hx + $hw, 0, self::W - $hx - $hw, $hy + $hh, $page);
        $this->maskCorners($hx, $hy, $hw, $hh, 28, $page);

        // 品牌:圆角方 logo + 商城名
        $mallName = $this->p['mall_name'];
        if ($mallName !== '' || $this->p['logo_path']) {
            $this->roundRect(88, 88, 52, 52, 14, [255, 255, 255]);
            $logo = $this->loadOptional($this->p['logo_path']);
            if ($logo) {
                $this->pasteCover($logo, 92, 92, 44, 44);
                imagedestroy($logo);
                $this->maskCorners(92, 92, 44, 44, 11, [255, 255, 255]);
            } elseif ($mallName !== '') {
                $this->textCentered(mb_substr($mallName, 0, 1), $this->fontBold, 26, $this->theme, 114, 114);
            }
            if ($mallName !== '') {
                $name = $this->fit($mallName, $this->fontBold, 26, 520);
                $this->text($name, $this->fontBold, 26, [255, 255, 255], 154, 114);
            }
        }

        $this->titleLine($this->p['main_title'], 88, 238, 88, 64, 574, [255, 255, 255], 4);
        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 30, 570);
            $this->text($sub, $this->fontRegular, 30, [255, 255, 255], 90, 321, 0.9);
        }

        // 底部白卡片:左边头像 + 两步引导,右边码
        $this->roundRect(40, 900, 670, 394, 28, [255, 255, 255]);
        $this->avatarAt(132, 996, 48);
        $nick = $this->fit($this->p['nickname'], $this->fontBold, 34, 230);
        $this->text($nick, $this->fontBold, 34, [31, 34, 41], 200, 975);
        $this->text('邀请你加入', $this->fontRegular, 24, [138, 143, 155], 200, 1017);
        $this->rect(84, 1080, 340, 2, [240, 241, 244]);

        $steps = [$this->p['is_mini'] ? '长按识别右侧小程序码' : '长按识别右侧二维码', '打开即可进入商城'];
        foreach ($steps as $i => $step) {
            $cy = 1134 + $i * 48;
            $this->circle(101, $cy, 17, $soft);
            $this->textCentered((string)($i + 1), $this->fontBold, 20, $this->theme, 101, $cy);
            $this->text($step, $this->fontRegular, 24, [90, 95, 107], 130, $cy);
        }

        $this->pasteQr(450, 956, 220);
        $this->textCentered($this->codeTip(), $this->fontRegular, 22, [160, 165, 176], 560, 1205);
    }

    /** 礼盒图标(300×300 区域,左上角 gx/gy) */
    private function drawGift($gx, $gy, array $ribbon)
    {
        $white = [255, 255, 255];
        $this->roundRect($gx + 40, $gy + 120, 220, 160, 18, $white, 0.95);
        $this->roundRect($gx + 24, $gy + 84, 252, 56, 14, $white);
        $this->rect($gx + 132, $gy + 84, 36, 196, $ribbon);
        // 蝴蝶结两翼:三次贝塞尔 150,84 → (110,20)(50,40) → 80,84
        $this->bezierLoop($gx, $gy, [150, 84], [110, 20], [50, 40], [80, 84], $white);
        $this->bezierLoop($gx, $gy, [150, 84], [190, 20], [250, 40], [220, 84], $white);
        $this->circle($gx + 150, $gy + 84, 16, $ribbon);
    }

    // ═════════════════════════ ③ 喜庆红包 ═════════════════════════

    private function drawRedPacket()
    {
        $red = [200, 34, 28];
        $flap = [227, 55, 43];
        $goldLight = [255, 224, 163];

        $this->rect(0, 0, self::W, self::H, $red);
        $this->halo(375, 210, 300, [255, 120, 80], 0.5);
        $this->circle(375, 5, 525, $flap);
        // 封口金线:金色 55% 叠在封口红上,直接用混好的实色画,避免半透明线段接缝处叠深
        $this->ring(375, -15, 523, 4, self::mix($flap, [255, 217, 154], 0.55));

        $mallName = $this->p['mall_name'];
        $brand = $mallName !== '' ? $this->fit($mallName, $this->fontRegular, 26, 460, 6) . ' · 诚意相邀' : '诚意相邀';
        $this->textCentered($brand, $this->fontRegular, 26, [255, 227, 179], 375, 102, 6);
        $this->titleCentered($this->p['main_title'], 210, 110, 72, 650, $goldLight, 10);
        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 30, 640, 2);
            $this->textCentered($sub, $this->fontRegular, 30, [255, 215, 199], 375, 321, 2);
        }

        // 金色「邀」字圆章
        $this->circle(375, 515, 105, [255, 217, 154], 0.28);
        $this->circleGradient(375, 515, 95, [255, 231, 176], [246, 195, 107]);
        $this->textCentered('邀', $this->fontBold, 96, $red, 375, 515);

        // 米色卡片
        $this->roundRect(70, 690, 610, 580, 24, [255, 248, 236]);
        $this->avatar(375, 772, 42, [[47, [246, 217, 166]]]);
        $nick = $this->fit($this->p['nickname'], $this->fontBold, 30, 530);
        $this->textCentered($nick, $this->fontBold, 30, [107, 42, 26], 375, 851);
        $this->textCentered('邀请你一起加入', $this->fontRegular, 24, [162, 106, 78], 375, 893);

        $this->roundRect(250, 940, 250, 250, 18, [243, 210, 156]);
        $this->roundRect(253, 943, 244, 244, 15, [255, 255, 255]);
        $this->pasteQr(268, 958, 214);
        $this->textCentered('长按识别 · 立即加入', $this->fontRegular, 22, [185, 138, 110], 375, 1227, 3);
    }

    // ═════════════════════════ ④ 杂志大图 ═════════════════════════

    private function drawMagazine()
    {
        $ink = [14, 14, 18];
        $bg = $this->loadOptional($this->p['bg_path']);
        if ($bg) {
            $this->pasteCover($bg, 0, 0, self::W, self::H);
            imagedestroy($bg);
        } else {
            $this->verticalGradient(0, self::H, self::mix($this->theme, [255, 255, 255], 0.1), self::mix($this->theme, [0, 0, 0], 0.55));
            $this->halo(480, 420, 380, [255, 255, 255], 0.35);
        }
        $this->shadeStops(0, self::H, [
            [0, [0, 0, 0], 0.35],
            [0.22, [0, 0, 0], 0],
            [0.45, [0, 0, 0], 0],
            [0.72, [10, 10, 14], 0.82],
            [1, $ink, 1],
        ]);

        $white = [255, 255, 255];
        $mallName = $this->p['mall_name'];
        if ($mallName !== '') {
            $this->text($this->fit($mallName, $this->fontRegular, 24, 420, 6), $this->fontRegular, 24, $white, 56, 81, 0.92, 6);
        }
        $en = 'INVITATION';
        $this->text($en, $this->fontRegular, 24, $white, 694 - $this->textWidth($en, $this->fontRegular, 24, 4), 81, 0.7, 4);
        $this->rect(56, 112, 638, 1, $white, 0.35);

        $accent = self::mix($this->theme, $white, 0.45);
        $this->text('专属邀请', $this->fontBold, 24, $accent, 56, 837, 1.0, 8);
        $this->titleLine($this->p['main_title'], 52, 923, 120, 80, 646, $white, 2);
        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 30, 638);
            $this->text($sub, $this->fontRegular, 30, $white, 56, 1017, 0.78);
        }

        $this->avatar(94, 1224, 38, [[41, $white, 0.9]]);
        $nick = $this->fit($this->p['nickname'], $this->fontBold, 28, 300);
        $this->text($nick, $this->fontBold, 28, $white, 150, 1206);
        $this->text('邀请你加入', $this->fontRegular, 22, $white, 150, 1242, 0.6);

        $this->roundRect(480, 1066, 214, 230, 20, $white);
        $this->pasteQr(502, 1084, 170);
        $this->textCentered('长按识别', $this->fontRegular, 20, [138, 143, 155], 587, 1274);
    }

    // ═════════════════════════ ⑤ 黑金尊享 ═════════════════════════

    private function drawBlackGold()
    {
        $ink = [17, 17, 20];
        $gold = self::GOLD;
        $white = [255, 255, 255];

        $this->rect(0, 0, self::W, self::H, $ink);
        $this->halo(375, 190, 320, $gold, 0.22);
        $this->strokeRect(36, 36, 678, 1262, 1.5, $gold, 0.55);
        $this->strokeRect(48, 48, 654, 1238, 1, $gold, 0.22);
        foreach ([[30, 30, 1, 1], [720, 30, -1, 1], [30, 1304, 1, -1], [720, 1304, -1, -1]] as [$x, $y, $dx, $dy]) {
            $this->rect($dx > 0 ? $x : $x - 30, $dy > 0 ? $y : $y - 4, 30, 4, $gold);
            $this->rect($dx > 0 ? $x : $x - 4, $dy > 0 ? $y : $y - 30, 4, 30, $gold);
        }

        $mallName = $this->p['mall_name'];
        if ($mallName !== '') {
            $this->textCentered($this->fit($mallName, $this->fontRegular, 24, 560, 10), $this->fontRegular, 24, $gold, 375, 137, 10);
        }
        $this->textCentered('INVITATION', $this->fontRegular, 22, $gold, 375, 211, 12, 0.7);
        $this->titleGradientCentered($this->p['main_title'], 305, 100, 64, 600, 18,
            [[0, [251, 231, 184]], [0.6, $gold], [1, [168, 129, 63]]]);

        $this->rect(243, 410, 110, 1.5, $gold, 0.6);
        $this->rect(397, 410, 110, 1.5, $gold, 0.6);
        $this->polygon([[375, 402.5], [383.5, 411], [375, 419.5], [366.5, 411]], $gold);

        if ($this->p['sub_title'] !== '') {
            $sub = $this->fit($this->p['sub_title'], $this->fontRegular, 30, 600, 4);
            $this->textCentered($sub, $this->fontRegular, 30, $white, 375, 461, 4, 0.72);
        }

        $this->avatar(375, 626, 66, [[72, $gold], [70, $ink]]);
        $nick = $this->fit($this->p['nickname'], $this->fontBold, 34, 560, 2);
        $this->textCentered($nick, $this->fontBold, 34, $white, 375, 744, 2);
        $this->textCentered('邀请你一起加入', $this->fontRegular, 24, $white, 375, 789, 3, 0.55);

        $this->roundRect(218, 848, 314, 314, 28, $gold, 0.12);
        $this->roundRect(228.5, 858.5, 293, 293, 17.5, $gold);
        $this->roundRect(230, 860, 290, 290, 16, $white);
        $this->pasteQr(250, 880, 250);
        $this->textCentered($this->codeTip(), $this->fontRegular, 22, $gold, 375, 1205, 6, 0.8);
    }
}
