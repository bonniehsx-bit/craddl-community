<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-22
 * Time: 21:49
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


class PosterHelper
{


    /** 推广海报标题、副标题的字数上限(超了海报上会缩字号/截断,后台保存时就拦住) */
    const INVITER_MAIN_TITLE_MAX = 10;
    const INVITER_SUB_TITLE_MAX = 24;

    /**
     * 推广(邀请)海报配置,后台读取和 C 端生成都走这里,保证两边口径一致。
     *
     * 五套固定模板(见 app\services\InvitePosterRenderer::TEMPLATES),后台配五项:
     * 模板 / 主题色 / 主标题 / 副标题 / 背景图。老数据里的 style_type、text_color、avatar_shape
     * 等字段已经不用,读出来一律丢掉;缺的、不合法的用默认值补(模板默认邀请票券)。
     */
    public static function getInviterPoster($mall_id): array
    {
        $raw = OptionHelper::get('inviter_poster_setting', $mall_id);
        // 没单独配过海报主题色,就跟商城主题色走,新商城开箱就和店铺配色一致
        if (!is_array($raw) || empty($raw['theme_color'])) {
            $mallSetting = OptionHelper::get('mall_setting', $mall_id);
            $raw = is_array($raw) ? $raw : [];
            $raw['theme_color'] = is_array($mallSetting) ? ($mallSetting['theme_color'] ?? '') : '';
        }
        return self::normalizeInviterPoster($raw);
    }

    /**
     * 海报顶部用的商城名和 logo:优先取后台「商城设置」里的,没填再用商城本身的
     * @return array [名称, logo 地址]
     */
    public static function mallBrand($mall_id): array
    {
        $setting = OptionHelper::get('mall_setting', $mall_id);
        $setting = is_array($setting) ? $setting : [];
        $name = trim((string)($setting['name'] ?? ''));
        $logo = trim((string)($setting['logo'] ?? ''));
        if ($name === '' || $logo === '') {
            $mall = \app\models\Mall::findOne($mall_id);
            if ($mall) {
                $name = $name !== '' ? $name : trim((string)$mall->name);
                $logo = $logo !== '' ? $logo : trim((string)$mall->logo);
            }
        }
        return [$name, $logo];
    }

    public static function normalizeInviterPoster($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $theme = is_string($raw['theme_color'] ?? null) ? trim($raw['theme_color']) : '';
        // 海报字体画不出 emoji,存之前就去掉,后台预览和生成的海报保持一致
        $main = is_string($raw['main_title'] ?? null) ? \app\services\InvitePosterRenderer::cleanText($raw['main_title']) : '';
        $sub = is_string($raw['sub_title'] ?? null) ? \app\services\InvitePosterRenderer::cleanText($raw['sub_title']) : '';
        $bg = is_string($raw['bg_image'] ?? null) ? trim($raw['bg_image']) : '';

        $template = is_string($raw['template'] ?? null) ? $raw['template'] : '';

        return [
            'template' => isset(\app\services\InvitePosterRenderer::TEMPLATES[$template])
                ? $template
                : \app\services\InvitePosterRenderer::DEFAULT_TEMPLATE,
            'theme_color' => \app\services\InvitePosterRenderer::parseHex($theme)
                ? strtoupper('#' . ltrim($theme, '#'))
                : \app\services\InvitePosterRenderer::DEFAULT_THEME,
            'main_title' => $main !== '' ? mb_substr($main, 0, self::INVITER_MAIN_TITLE_MAX) : '诚邀加入',
            // 副标题允许留空(海报上就不画这一行)
            'sub_title' => array_key_exists('sub_title', $raw) ? mb_substr($sub, 0, self::INVITER_SUB_TITLE_MAX) : '诚挚邀请您加入,好物好价一起享',
            'bg_image' => $bg,
        ];
    }


    /** 商品海报文案字数上限(超了海报上会缩字号/截断,后台保存时就拦住) */
    const GOODS_MAIN_TITLE_MAX = 8;
    const GOODS_SUB_TITLE_MAX = 20;
    const GOODS_RECOMMEND_MAX = 12;

    /**
     * 商品海报配置,后台读取和 C 端生成都走这里。
     *
     * 四套固定模板(见 app\services\GoodsPosterRenderer::TEMPLATES),后台配:
     * 模板 / 主题色 / 主标题 / 副标题 / 推荐语。商品名、价格、商品图都从商品数据自动取。
     * 老数据(坐标版 bg_pic/pic/price… 或只有 bg_image 的一版)一律忽略,按默认值补齐。
     */
    public static function getGoodsPoster($mall_id): array
    {
        $raw = OptionHelper::get('goods_poster_setting', $mall_id);
        // 没单独配过主题色,跟商城主题色走,和商品详情页的价格颜色一致
        if (!is_array($raw) || empty($raw['theme_color'])) {
            $mallSetting = OptionHelper::get('mall_setting', $mall_id);
            $raw = is_array($raw) ? $raw : [];
            $raw['theme_color'] = is_array($mallSetting) ? ($mallSetting['theme_color'] ?? '') : '';
        }
        return self::normalizeGoodsPoster($raw);
    }

    public static function normalizeGoodsPoster($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $renderer = \app\services\GoodsPosterRenderer::class;
        $str = function ($key) use ($raw) {
            return is_string($raw[$key] ?? null) ? \app\services\PosterCanvas::cleanText($raw[$key]) : '';
        };
        $template = is_string($raw['template'] ?? null) ? $raw['template'] : '';
        $theme = is_string($raw['theme_color'] ?? null) ? trim($raw['theme_color']) : '';
        $main = $str('main_title');
        $recommend = $str('recommend');

        return [
            'template' => isset($renderer::TEMPLATES[$template]) ? $template : $renderer::DEFAULT_TEMPLATE,
            'theme_color' => \app\services\PosterCanvas::parseHex($theme)
                ? strtoupper('#' . ltrim($theme, '#'))
                : $renderer::DEFAULT_THEME,
            'main_title' => $main !== '' ? mb_substr($main, 0, self::GOODS_MAIN_TITLE_MAX) : '好物推荐',
            // 副标题允许留空(海报上就不画这一行)
            'sub_title' => array_key_exists('sub_title', $raw)
                ? mb_substr($str('sub_title'), 0, self::GOODS_SUB_TITLE_MAX)
                : '精选好物，值得拥有',
            'recommend' => $recommend !== '' ? mb_substr($recommend, 0, self::GOODS_RECOMMEND_MAX) : '为你推荐一件好物',
        ];
    }
}
