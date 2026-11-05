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

namespace app\plugins\mch\listeners;

use app\events\DiyComponentEvent;
use app\forms\mall\mall\DiyForm;
use app\helpers\ImageHelper;
use app\listeners\BaseListener;
use Yii;

/**
 * 把多商户(mch)插件的 DIY 组件挂到后台装修器组件库
 *
 * 目前提供：
 *   - mch-select-shops：精选商家（后台手动勾选要展示哪些商户的橱窗）
 *
 * 数据流：
 *   - admin 端用 @/components/diy/MchSelectShops(.vue) + MchSelectShopsConfig(.vue) 渲染
 *     配置面板里用 /plugin/mch/mall/mch/search 远程多选商户，存 data.mch_ids（有序数组）
 *   - C 端 uniapp 由 tui-diy-view 按 item.name='mch-select-shops' 派发到
 *     @/components/thorui/tui-mch-select-shops/tui-mch-select-shops.vue
 *   - 数据接口：GET /plugin/mch/api/index/diy-select-shops?mch_ids=1,2,3
 */
class DiyComponentListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(DiyForm::EVENT_DIY_COMPONENT_REGISTER, [$this, 'onDiyComponentRegister']);
    }

    public function onDiyComponentRegister(DiyComponentEvent $event)
    {
        // ── 精选商家（后台选商家橱窗）──
        $event->components[] = [
            'is_fixed' => 0,
            'group' => '多商户',
            'name' => 'mch-select-shops',
            'display_name' => '精选商家',
            'active' => false,
            'icon' => ImageHelper::getUrl('/assets/diy/goods.png'),
            'data' => [
                'title'        => '精选商家',
                'subtitle'     => '为您甄选优质商户',
                'mch_ids'      => [],   // 后台勾选要展示的商户ID（有序，决定展示顺序）
                'show_rating'  => 1,    // 显示评分
                'show_address' => 1,    // 显示地址
                'show_more'    => 0,    // 查看更多（跳商家列表页）
                'style'        => 'grid', // grid 网格 / scroll 横滑 / list 大图列表
                'is_padding'   => 1,    // 1=有左右边距 0=贴边
                'bg_color'     => '',
                'title_color'  => '',
                'item_bg_color' => '',
            ],
        ];
    }
}
