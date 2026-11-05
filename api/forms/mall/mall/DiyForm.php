<?php

/**
 * @link=>http=>//www.dujxmall.com/
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author=> ganxiaohao
 * Date=> 2020-11-11
 * Time=> 10=>12
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

namespace app\forms\mall\mall;


use app\core\ApiCode;
use app\helpers\ImageHelper;
use app\models\BaseModel;
use app\models\Plugin;
use app\events\DiyComponentEvent;

class DiyForm extends BaseModel
{
    const EVENT_DIY_COMPONENT_REGISTER = 'diyComponentRegister';


    /**
     * 组件分组排序：先内置 → 再插件，插件按注册顺序追加在尾部
     * 前端按 `group` 字段渲染分组标题；老接口消费者读 list 字段仍按原顺序拿到全部组件
     */
    const GROUP_BASIC     = '基础组件';
    const GROUP_MEDIA     = '图文媒体';
    const GROUP_GOODS     = '商品营销';
    const GROUP_STYLE     = '风格组件';
    const GROUP_FALLBACK  = '其他扩展';

    public function getComponents()
    {
        $list = [
            // ── 基础组件 ──
            [
                'is_fixed' => 0, //固定组件一个页面只能有一个
                'group' => self::GROUP_BASIC,
                'name' => 'search-bar',
                'display_name' => '搜索',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/search.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_BASIC,
                'name' => 'blank',
                'display_name' => '占位块',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/blank.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_BASIC,
                'name' => 'notice',
                'display_name' => '公告',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/notice.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_BASIC,
                'name' => 'title-bar',
                'display_name' => '标题栏',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/title.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_BASIC,
                'name' => 'navbar',
                'display_name' => '导航按钮',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/nav.png"),
                'data' => null
            ],
            // ── 图文媒体 ──
            [
                'is_fixed' => 0,
                'group' => self::GROUP_MEDIA,
                'name' => 'img-group',
                'display_name' => '图片魔方',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/magic.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_MEDIA,
                'name' => 'banner',
                'display_name' => '轮播图',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/banner.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_MEDIA,
                'name' => 'video-block',
                'display_name' => '视频',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/video.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_MEDIA,
                'name' => 'hot-image',
                'display_name' => '热点图',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/hot-image.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_MEDIA,
                'name' => 'header-banner',
                'display_name' => '头部轮播',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/banner.png"),
                'data' => [
                    'banners' => [
                        [
                            'pic_url' => '',
                            'link' => '',
                            'bg_color' => '#D71F1F'
                        ]
                    ],
                    'search_placeholder' => '搜索好物、店铺',
                    'show_location' => 1,
                    'show_message' => 1
                ]
            ],
            // ── 风格组件:带配色预设的成品版块(主视觉 / 分类宫格 / 入口卡…),拼出有调性的首页 ──
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'brand-search',
                'display_name' => '顶栏搜索',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/search.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'hero-card',
                'display_name' => '主视觉卡',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/banner.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'feature-strip',
                'display_name' => '卖点条',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/notice.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'cat-grid',
                'display_name' => '分类宫格',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/nav.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'section-title',
                'display_name' => '区块标题',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/title.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'promo-card',
                'display_name' => '入口卡',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/magic.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'video-card',
                'display_name' => '视频卡',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/video.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_STYLE,
                'name' => 'contact-card',
                'display_name' => '联系卡',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/fab.png"),
                'data' => null
            ],
            // ── 商品营销 ──
            [
                'is_fixed' => 0,
                'group' => self::GROUP_GOODS,
                'name' => 'goods-group',
                'display_name' => '商品组',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/goods.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_GOODS,
                'name' => 'custom-goods',
                'display_name' => '模拟商品',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/goods.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_GOODS,
                'name' => 'dynamic-goods',
                'display_name' => '动态商品',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/goods.png"),
                'data' => null
            ],
            [
                'is_fixed' => 0,
                'group' => self::GROUP_GOODS,
                'name' => 'tab-goods',
                'display_name' => '标签商品',
                'active' => false,
                'icon' => ImageHelper::getUrl("/statics/diy/goods.png"),
                'data' => null
            ],
        ];

        // 触发全站事件，让各个插件把自己的 DIY 组件挂载进来
        // 每个插件 listener 可以在自己的 component 项里声明 `group` 字段（用插件名作为分组名），
        // 老 listener 没声明 group 的，本方法兜底打到 GROUP_FALLBACK
        $event = new DiyComponentEvent();
        \Yii::$app->trigger(self::EVENT_DIY_COMPONENT_REGISTER, $event);

        if (!empty($event->components)) {
            foreach ($event->components as &$comp) {
                if (empty($comp['group'])) {
                    $comp['group'] = self::GROUP_FALLBACK;
                }
            }
            unset($comp);
            $list = array_merge($list, $event->components);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}
