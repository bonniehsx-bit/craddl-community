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
 * 商品「赠送积分」增加赠送时机:0=订单结算后赠送(现状,默认) 1=支付成功即赠送。
 *
 *   · goods.send_integral_timing        —— 商家配置(后台可配)
 *   · order_detail.send_integral_timing —— 下单时快照(与 send_integral 金额快照同理,
 *                                          避免商家事后改配置导致发放时机漂移;发放/追回都以快照为准)
 *
 * 时机决定"何时发"与"是否需要追回":
 *   - 结算后赠送(0):结算才发,售后窗口已过 → 天然不涉及追回(现状行为,完全向后兼容)
 *   - 支付即赠送(1):支付即发,需在退款/订单关闭时按 order_detail_id 追回赠送积分
 *
 * 列存在性走 information_schema 直查(本环境 getTableSchema 带缓存,refresh 不稳),确保幂等。
 */
class m260618_120000_add_send_integral_timing extends Migration
{
    public function safeUp()
    {
        if (!$this->columnExists('goods', 'send_integral_timing')) {
            $this->addColumn(
                '{{%goods}}',
                'send_integral_timing',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('赠送时机:0=订单结算后赠送 1=支付成功即赠送')->after('send_integral_type')
            );
        }
        if (!$this->columnExists('order_detail', 'send_integral_timing')) {
            $this->addColumn(
                '{{%order_detail}}',
                'send_integral_timing',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('下单快照·赠送时机:0=结算后 1=支付即赠')->after('send_integral')
            );
        }
    }

    public function safeDown()
    {
        if ($this->columnExists('goods', 'send_integral_timing')) {
            $this->dropColumn('{{%goods}}', 'send_integral_timing');
        }
        if ($this->columnExists('order_detail', 'send_integral_timing')) {
            $this->dropColumn('{{%order_detail}}', 'send_integral_timing');
        }
    }

    private function columnExists(string $table, string $col): bool
    {
        $db = \Yii::$app->db;
        $fullTable = $db->tablePrefix . $table;
        return (bool)$db->createCommand(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            [':t' => $fullTable, ':c' => $col]
        )->queryScalar();
    }
}
