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

use app\core\Migration;
use yii\db\Query;

/**
 * 平台后台「商品管理 → 服务标签」菜单
 *
 * 页面 admin/src/views/goods/service-tag.vue,接口 /mall/goods/service-tag-*。
 * 商家端那一页走前端静态路由(mch/src/router/modules/mall.js),不在这张表里。
 */
class m260920_100500_goods_service_tag_menu extends Migration
{
    const MENU_ID = 'goods-service-tag';

    public function safeUp()
    {
        if ((new Query())->from('{{%menu}}')->where(['menu_id' => self::MENU_ID])->exists()) {
            echo "    > 菜单 " . self::MENU_ID . " 已存在，跳过。\n";
            return;
        }
        $this->insert('{{%menu}}', [
            'menu_id'     => self::MENU_ID,
            'menu_name'   => '服务标签',
            'icon'        => 'el-icon-price-tag',
            'menu_type'   => 'C',
            'order_num'   => 3, // 跟在「商品分类」(2)后面
            'parent_id'   => 'goods',
            'is_cache'    => 1,
            'hidden'      => 0,
            'is_frame'    => 1,
            'status'      => 0,
            'path'        => 'service-tag',
            'component'   => 'goods/service-tag',
            'query'       => '',
            'perms'       => '',
            'link'        => '',
            'plugin'      => 'mall',
            'active_menu' => '',
            'breadcrumb'  => 1,
            'name'        => 'goods-service-tag',
            'show_tag'    => 1,
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => self::MENU_ID]);
    }
}
