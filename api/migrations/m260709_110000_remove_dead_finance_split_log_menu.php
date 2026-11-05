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
use app\models\Menu;

/**
 * 删除旧的「财务中心 / 分账记录」死菜单(menu_id=finance-split-log)。
 *
 * 该菜单由 m260106_011912_export_menu 建立,指向 component `finance/split-log`。
 * 分账记录功能已整体迁入收银台插件(见插件迁移 m260709_100000_cashier_split_records_menu),
 * 核心页面 admin/views/finance/split-log.vue 与后端 FinanceController::actionOrderSplitLog 均已删除,
 * 此菜单成为指向不存在页面的死链,故清除。
 *
 * 注意:另两条 split 菜单不动 ——
 *   · finance-split(分账配置 split/index)、finance-split-record(分账进件 split/list)
 *     属于「分账网关进件」链,仍在核心/mch,保留。
 */
class m260709_110000_remove_dead_finance_split_log_menu extends Migration
{
    public function safeUp()
    {
        Menu::deleteAll(['menu_id' => 'finance-split-log']);
    }

    public function safeDown()
    {
        // 忠实还原原始菜单行(不强制 id,由自增分配),便于回滚到迁移前的菜单状态
        Menu::deleteAll(['menu_id' => 'finance-split-log']);
        $this->batchInsert('{{%menu}}',
            [
                'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id',
                'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query', 'perms',
                'is_delete', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag',
            ],
            [
                [
                    'finance-split-log', '分账记录', 'el-icon-pie-chart', 'C', 6, 'finance',
                    1, 0, 0, 0, 'split-log', 'finance/split-log', '', '',
                    0, '', 'mall', '/finance/split-log', 1, 'finance-split-log', 1,
                ],
            ]
        );
    }
}
