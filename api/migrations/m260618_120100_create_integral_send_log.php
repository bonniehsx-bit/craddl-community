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
 * 积分赠送记录 / 追回锚点表。
 *
 * 精细到 order_detail_id,记录每一笔"购物赠送积分"的发放,用于:
 *   1. 发放幂等:同一明细只发一次(配合 IntegralLog.idempotency_key 双保险)
 *   2. 退款/关闭追回:支付即赠的积分在 order.commission.revert(取消/售后)时按本表逐明细追回,
 *      status 由 granted→reverted 防重复追回;追回金额取本表 integral(发多少追多少)
 *
 * 与团队统计 revert_log 同款锚点思路(见 m260616_110000)。结算后赠送的明细同样落记录,
 * 仅在极端"结算后又退款"场景被追回;正常结算后不触发回滚,不受影响。
 */
class m260618_120100_create_integral_send_log extends Migration
{
    public function safeUp()
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%integral_send_log}}') !== null) {
            return;
        }
        $this->createTable('{{%integral_send_log}}', [
            'id'              => $this->primaryKey(),
            'mall_id'         => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'user_id'         => $this->integer()->notNull()->comment('赠送对象(下单买家)'),
            'order_id'        => $this->integer()->notNull()->comment('订单ID'),
            'order_detail_id' => $this->integer()->notNull()->comment('订单明细ID(追回锚点)'),
            'goods_id'        => $this->integer()->notNull()->defaultValue(0)->comment('商品ID'),
            'integral'        => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('赠送积分数(追回时按此扣回)'),
            'timing'          => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('赠送时机:0=结算后 1=支付即赠'),
            'status'          => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('状态:1=已赠送 2=已追回'),
            'integral_log_id' => $this->integer()->notNull()->defaultValue(0)->comment('对应 integral_log 发放流水ID'),
            'granted_at'      => $this->integer()->notNull()->defaultValue(0)->comment('赠送时间'),
            'reverted_at'     => $this->integer()->notNull()->defaultValue(0)->comment('追回时间'),
        ], null, '积分赠送记录/追回锚点');

        // 唯一锚点:一个订单明细只赠一次
        $this->createIndex('uniq_send_order_detail', '{{%integral_send_log}}', ['order_detail_id'], true);
        // 整单追回(order_detail_id=0)时按订单批量查
        $this->createIndex('idx_send_order', '{{%integral_send_log}}', ['order_id']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%integral_send_log}}');
    }
}
