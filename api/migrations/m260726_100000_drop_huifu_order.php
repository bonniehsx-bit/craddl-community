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
 * 删除汇付订单表 jxmall_huifu_order。
 *
 * 背景:汇付天下(BS/Hf)整条业务线已移除(支付网关 BsMicro/BsMpwx/BsPayJsApi/HfMpwx、
 * 打款网关 BsFund/BsBalanceFund、SDK、后台配置页全部删除),此表随之失去唯一写入方,
 * 模型 app\models\HuifuOrder 也已删除。
 *
 * ⚠ 安全闸:表里有数据时**直接中止,不删**。
 * 生产是否真的为空我核不到(dev 为 0 行),而这张表存的是支付/退款流水,
 * 删错就是丢账。宁可迁移报错让人来看一眼,也不能静默删掉有数据的表。
 * 若确认那些数据可弃,先自行备份/清空,再重跑本迁移。
 */
class m260726_100000_drop_huifu_order extends Migration
{
    private const TABLE = '{{%huifu_order}}';

    public function safeUp()
    {
        $raw = $this->db->schema->getRawTableName(self::TABLE);
        if ($this->db->schema->getTableSchema($raw, true) === null) {
            echo "    > 表 {$raw} 不存在,跳过\n";
            return true;
        }

        $count = (int) $this->db->createCommand('SELECT COUNT(*) FROM ' . $this->db->quoteTableName($raw))->queryScalar();
        if ($count > 0) {
            // 不 dropTable、也不静默 return true —— 抛出让迁移失败,强制人工确认
            throw new \yii\base\Exception(
                "表 {$raw} 尚有 {$count} 行数据(支付/退款流水),已中止删除。"
                . "请先确认这些数据可弃并自行备份/清空后,再重跑本迁移。"
            );
        }

        $this->dropTable(self::TABLE);
        echo "    > 已删除空表 {$raw}\n";
        return true;
    }

    /**
     * 回滚:按原始建表语句(m260101_000000_exclude_jxmall_plugin_)重建同构空表。
     * 只恢复结构,数据无法恢复 —— 但本迁移只在表为空时才会执行,故不丢数据。
     */
    public function safeDown()
    {
        $this->createTable(self::TABLE, [
            'id'                => $this->primaryKey(),
            'mall_id'           => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'req_seq_id'        => $this->string(128)->null()->comment('请求流水号'),
            'req_date'          => $this->string(64)->null()->comment('请求日期'),
            'huifu_id'          => $this->string(64)->null()->comment('汇付ID'),
            'ord_amt'           => $this->decimal(10, 2)->null()->comment('退款金额'),
            'trans_amt'         => $this->decimal(10, 2)->null()->comment('交易金额'),
            'order_no'          => $this->string(64)->null()->comment('商户订单号'),
            'trade_type'        => $this->string(20)->null()->comment('交易类型'),
            'refund_order_no'   => $this->string(64)->null()->comment('退款订单号'),
            'refund_req_seq_id' => $this->string(128)->null()->comment('退款请求流水号'),
            'refund_req_date'   => $this->string(20)->null()->comment('退款请求日期'),
            'pay_at'            => $this->timestamp()->null()->comment('支付时间'),
            'refund_at'         => $this->timestamp()->null()->comment('退款时间'),
            'status'            => $this->tinyInteger()->null()->defaultValue(0)->comment('1 支付 2退款'),
            'error'             => $this->text()->null()->comment('错误信息'),
        ]);
        return true;
    }
}
