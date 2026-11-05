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
 * 数据修复：把库中存量的 mch 移动端页面路径从「拼音缩写」改写为「扁平英文名」。
 *
 * 背景：uniapp/plugins1/mch 的页面已由 hkzx/hkzx、sjdd/sjdd 等改名为 funds-center、orders 等
 *      （见 commit 7dda8de40）。但页面路径会被后台「链接选择器」(LinkPicker) 存进数据库，
 *      代码 grep 抓不到，必须用本迁移把存量数据一并改写，否则运营配置过的入口会跳到不存在的页面。
 *
 * 经全库扫描（1578 个文本列）确认，只有以下三处真正存储页面路径：
 *   - {{%option}}.value                        JSON 配置：mch_center_menu_setting(商家中心菜单)
 *                                              / user_center_setting / diy_home
 *   - {{%diy_page}}.data                       DIY 页面 JSON（含首页）
 *   - {{%plugin_plaza_workbench_action}}.link  plaza 商户工作台入口（该插件未装则跳过）
 *
 * ⚠️ 刻意不改 {{%admin_action_log}}.before_update / after_update ——
 *    那是后台操作审计日志，记录的是「当时」提交的内容，改写等于篡改历史，必须原样保留。
 *
 * 未改名的路径（/plugins1/mch/index、/plugins1/mch/apply、/plugins1/mch/admin/*、
 * /plugins1/mch/guide/guide）不在映射表内，自然不受影响。
 */
class m260708_100000_rename_mch_page_paths extends Migration
{
    /** 需要改写的「表 => 列」；表名用 {{%}} 前缀占位 */
    private const TARGETS = [
        '{{%option}}' => 'value',
        '{{%diy_page}}' => 'data',
        '{{%plugin_plaza_workbench_action}}' => 'link',
    ];

    /** 旧路径(相对 /plugins1/mch/) => 新路径 */
    private const MAP = [
        'bjyhq/bjyhq' => 'coupon-edit',
        'bjyhq/goods-picker' => 'components/coupon-goods-picker',
        'djsdd/djsdd' => 'settlement-pending',
        'dpm/dpm' => 'shop-qrcode',
        'dygl/dygl' => 'clerk-list',
        'ffyhq/ffyhq' => 'coupon-grant',
        'fjsj/fjsj' => 'nearby-shops',
        'fwsdd/fwsdd' => 'provider-tasks',
        'fwsddxq/fwsddxq' => 'provider-task-detail',
        'fwssq/fwssq' => 'provider-apply',
        'fwszx/fwszx' => 'provider-center',
        'hkzx/hkzx' => 'funds-center',
        'hxjl/hxjl' => 'verify-records',
        'pssz/pssz' => 'delivery-setting',
        'psygl/psygl' => 'rider-list',
        'qsrwxq/qsrwxq' => 'rider-task-detail',
        'qszx/qszx' => 'rider-center',
        'shdd/shdd' => 'refund-orders',
        'sjdd/xssjdd' => 'orders-online',
        'sjdd/sjdd' => 'orders',
        'sjdd/action-drawer' => 'components/order-action-drawer',
        'sjdd/ship-modal' => 'components/order-ship-modal',
        'sjddxq/sjddxq' => 'order-detail',
        'sjhygl/sjhygl' => 'member-list',
        'sjhygl/remark-editor' => 'components/member-remark-editor',
        'sjlb/sjlb' => 'shop-list',
        'skm0/skm0' => 'paycode-apply',
        'syjl/syjl' => 'cashier-board',
        'tjdy/tjdy' => 'clerk-add',
        'tjpsy/tjpsy' => 'rider-add',
        'txjl/txjl' => 'withdraw-records',
        'wdhx/wdhx' => 'my-verify',
        'wdskm/wdskm' => 'my-paycode',
        'wfwdmd/wfwdmd' => 'my-shops',
        'yhqcyz/yhqcyz' => 'coupon-holders',
        'yhqgl/yhqgl' => 'coupon-list',
    ];

    private const PREFIX = '/plugins1/mch/';

    public function safeUp()
    {
        $pairs = [];
        foreach (self::MAP as $old => $new) {
            $pairs[] = [self::PREFIX . $old, self::PREFIX . $new];
        }
        $this->applyReplacements($pairs);
        return true;
    }

    public function safeDown()
    {
        $pairs = [];
        foreach (self::MAP as $old => $new) {
            $pairs[] = [self::PREFIX . $new, self::PREFIX . $old];
        }
        $this->applyReplacements($pairs);
        return true;
    }

    /**
     * @param array $pairs [[search, replace], ...]
     *
     * 按 search 长度倒序执行：/plugins1/mch/orders 是 /plugins1/mch/orders-online 的前缀，
     * 若先替换短的会把长的截断成 /plugins1/mch/sjdd/sjdd-online。回滚方向尤其致命。
     */
    private function applyReplacements(array $pairs)
    {
        usort($pairs, function ($a, $b) {
            return strlen($b[0]) <=> strlen($a[0]);
        });

        foreach (self::TARGETS as $table => $column) {
            if (!$this->tableReady($table)) {
                echo "    > 跳过 {$table}（表不存在，插件未安装）\n";
                continue;
            }
            $total = 0;
            foreach ($pairs as [$search, $replace]) {
                $affected = $this->db->createCommand(
                    "UPDATE {$table} SET [[{$column}]] = REPLACE([[{$column}]], :s, :r) WHERE [[{$column}]] LIKE :like",
                    [':s' => $search, ':r' => $replace, ':like' => '%' . $search . '%']
                )->execute();
                $total += $affected;
            }
            echo "    > {$table}.{$column} 改写 {$total} 行次\n";
        }
    }

    private function tableReady(string $table): bool
    {
        $real = $this->db->schema->getRawTableName($table);
        return $this->db->schema->getTableSchema($real, true) !== null;
    }
}
