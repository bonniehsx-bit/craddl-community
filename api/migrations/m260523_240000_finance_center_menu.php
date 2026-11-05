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

/**
 * Class m260523_240000_finance_center_menu
 *
 * 「收付中心」统一菜单 —— 自包含、幂等。
 *
 * 取代历史上零散的 payment / fund / split 菜单聚合迁移：
 * 把「收款(payment) / 代付(fund) / 分账(split)」三类资金工具收敛到一个父菜单
 * 「收付中心(finance-hub)」下，作为 6 个并列子菜单。
 *
 * 设计原则（不再修改任何旧迁移文件）：
 *   - safeUp 先 DELETE 掉所有旧的 payment / fund / split / finance-hub 菜单
 *     （含父菜单、各聚合页、以及组件早已移入 components/ 的孤儿隐藏配置菜单）
 *   - 再以全新语义化 menu_id 整套 INSERT，从任何历史状态跑都收敛到同一干净终态
 *   - 组件文件不变：仍指向 payment/index、fund/index、split/index、payment/order 等
 *
 * 注意：菜单 menu_id 变更后，需在后台「角色管理」重新给角色勾选收付中心相关菜单权限。
 */
class m260523_240000_finance_center_menu extends Migration
{
    /** 需要彻底清理的旧分组父菜单 menu_id */
    private $oldGroupIds = ['payment', 'fund', 'split', 'finance-hub'];

    public function safeUp()
    {
        // 1. 清理所有旧的 payment / fund / split / finance-hub 菜单（父 + 全部子，含孤儿隐藏配置）
        $this->delete('{{%menu}}', ['parent_id' => $this->oldGroupIds]);
        $this->delete('{{%menu}}', ['menu_id' => $this->oldGroupIds]);
        // 兼容历史上可能由其它迁移建的新版聚合页 menu_id
        $this->delete('{{%menu}}', ['menu_id' => [
            'payment-index-master', 'fund-index-master', 'split-index-master',
            'payment-order-log', 'fund-user-settle-list', 'split-list',
        ]]);

        // 2. 整套插入「收付中心」结构（1 父 + 6 子），全新语义化 menu_id
        $now = date('Y-m-d H:i:s');
        $cols = ['id', 'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id', 'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query', 'perms', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'created_time', 'updated_time', 'deleted_time', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag'];

        $this->batchInsert('{{%menu}}', $cols, [
            // 父菜单
            [null, 'finance-hub', '收付中心', 'el-icon-bank-card', 'M', 1, '0', 1, 0, 1, 0, 'finance-hub', '', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-hub', 1],

            // 配置类（聚合页）
            [null, 'finance-collect', '收款配置', 'el-icon-bank-card', 'C', 0, 'finance-hub', 1, 0, 1, 0, 'collect', 'payment/index', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-collect', 1],
            [null, 'finance-payout', '代付配置', 'el-icon-coin', 'C', 1, 'finance-hub', 1, 0, 1, 0, 'payout', 'fund/index', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-payout', 1],
            [null, 'finance-split', '分账配置', 'el-icon-pie-chart', 'C', 2, 'finance-hub', 1, 0, 1, 0, 'split-config', 'split/index', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-split', 1],

            // 记录/日志类
            [null, 'finance-payment-order', '支付订单', 'el-icon-document', 'C', 10, 'finance-hub', 1, 0, 1, 0, 'payment-order', 'payment/order', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-payment-order', 1],
            [null, 'finance-payout-settle', '代付进件', 'el-icon-document', 'C', 11, 'finance-hub', 1, 0, 1, 0, 'payout-settle', 'fund/user-settle-list', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-payout-settle', 1],
            [null, 'finance-split-record', '分账进件', 'el-icon-document', 'C', 12, 'finance-hub', 1, 0, 1, 0, 'split-record', 'split/list', '', '', 0, 0, 0, 0, $now, null, null, '', 'mall', '', 1, 'finance-split-record', 1],
        ]);
    }

    public function safeDown()
    {
        // 仅移除「收付中心」整套菜单；旧的零散菜单不再恢复（其迁移文件已废弃）
        $this->delete('{{%menu}}', ['parent_id' => 'finance-hub']);
        $this->delete('{{%menu}}', ['menu_id' => 'finance-hub']);
    }
}
