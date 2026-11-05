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
 * 老提现(cash)链路下线:移除后台菜单 + 删除 jxmall_cash 表。
 *
 * 背景:提现已全量迁到 withdraw 插件(plugin_withdraw_order)。
 * 老链的 C 端页面、申请接口、打款策略、审核后台、cron、回调、Cash 模型均已删除,
 * 这两个后台菜单点进去只会是空白页。
 *
 * ⚠ 安全闸:jxmall_cash 有数据时**不删表**(只摘菜单),并打印提示。
 * 这张表是历史提现流水,删错就是丢账;生产是否为空我核不到(dev 为 0 行)。
 * 确认可弃后自行备份/清空,再重跑本迁移即可删表。
 * —— 注意这里与 huifu 那次的处理不同:那次直接抛异常中止,因为汇付表纯属死壳;
 *    这次菜单必须摘掉(页面已删,留着就是坏链接),所以不能整体中止,改为"摘菜单 + 保表 + 告警"。
 */
class m260726_140000_offline_legacy_cash extends Migration
{
    /** 老链的两个后台菜单 name(见 m260106_011912_export_menu) */
    private const MENUS = ['finance-cash', 'finance-cash-setting'];

    public function safeUp()
    {
        // 1. 摘菜单(页面文件已删,留着点进去是空白)
        $menuTable = $this->db->schema->getRawTableName('{{%menu}}');
        if ($this->db->schema->getTableSchema($menuTable, true) !== null) {
            $n = $this->db->createCommand()
                ->delete($menuTable, ['name' => self::MENUS])
                ->execute();
            echo "    > 已移除后台菜单 " . implode('/', self::MENUS) . "(影响 {$n} 行)\n";
        } else {
            echo "    > 菜单表不存在,跳过菜单清理\n";
        }

        // 2. 删表(仅当为空)
        $cashTable = $this->db->schema->getRawTableName('{{%cash}}');
        if ($this->db->schema->getTableSchema($cashTable, true) === null) {
            echo "    > 表 {$cashTable} 不存在,跳过\n";
            return true;
        }
        $count = (int) $this->db->createCommand('SELECT COUNT(*) FROM ' . $this->db->quoteTableName($cashTable))->queryScalar();
        if ($count > 0) {
            echo "    > ⚠ 表 {$cashTable} 尚有 {$count} 行历史提现流水,**未删除**。\n";
            echo "    >   代码侧已完全不再读写它。确认数据可弃并备份后,手动 DROP 或重跑本迁移。\n";
            return true;
        }
        $this->dropTable('{{%cash}}');
        echo "    > 已删除空表 {$cashTable}\n";
        return true;
    }

    /**
     * 回滚只恢复表结构(空表)。菜单不恢复 —— 对应的 admin 页面文件已删除,
     * 恢复菜单只会得到一个点进去空白的坏链接。
     */
    public function safeDown()
    {
        $cashTable = $this->db->schema->getRawTableName('{{%cash}}');
        if ($this->db->schema->getTableSchema($cashTable, true) !== null) {
            echo "    > 表 {$cashTable} 已存在,跳过重建\n";
            return true;
        }
        $this->createTable('{{%cash}}', [
            'id'            => $this->primaryKey(),
            'mall_id'       => $this->integer()->notNull()->comment('商城ID'),
            'user_id'       => $this->integer()->notNull()->comment('用户ID'),
            'order_no'      => $this->string(64)->notNull()->comment('提现单号'),
            'type'          => $this->string(20)->notNull()->comment('提现资产:balance/price/integral'),
            'cash_type'     => $this->string(20)->null()->comment('到账方式:bank/alipay/wechat/balance'),
            'cash_price'    => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('申请提现总额'),
            'service_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('手续费'),
            'tax_price'     => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('税费'),
            'price'         => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('实际到账'),
            'status'        => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0待审1待打款2打款中3已打款4已驳回5失败'),
            'fund_class'    => $this->string(150)->null()->comment('打款网关类名'),
            'platform'      => $this->string(20)->null()->comment('下单平台'),
            'extra_info'    => $this->text()->null()->comment('扩展信息JSON'),
            'remarks'       => $this->string(255)->null()->comment('备注'),
        ]);
        return true;
    }
}
