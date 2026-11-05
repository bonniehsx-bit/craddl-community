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
 * 退款人工处理记录表:「退款失败」处理台上每一次操作(查询结果 / 重新发起 / 登记已退款 / 补做后续处理)记一条,只追加不修改。
 *
 * ## 为什么单独一张表
 * 一笔退款可能被处理好几次(先查、再重发、又失败、最后线下退),记在退款流水表上只能留最后一次;
 * 涉及钱的人工操作要能逐次追溯:谁、什么时候、做了什么、结果如何、凭什么(说明 / 凭证)。
 *
 * ## 注意
 * · 只建新表,不动现有表,不影响正在跑的退款;
 * · 幂等:表已存在就跳过。
 */
class m260926_100000_create_common_pay_refund_handle_log extends Migration
{
    const T = '{{%common_pay_refund_handle_log}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::T) !== null) {
            echo "    > 表 common_pay_refund_handle_log 已存在,跳过建表。\n";
            return true;
        }
        $this->createTable(self::T, [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城 ID'),
            'refund_id' => $this->integer()->notNull()->defaultValue(0)->comment('退款流水 common_pay_refund.id'),
            'action' => $this->string(20)->notNull()->defaultValue('')->comment('query=查询结果 retry=重新发起 mark_refunded=登记已退款 resync=补做后续处理'),
            'result' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('1=成功 0=没成功'),
            'status_before' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('操作前的退款状态'),
            'status_after' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('操作后的退款状态'),
            'operator_id' => $this->integer()->notNull()->defaultValue(0)->comment('操作的后台账号 admin.id'),
            'operator_name' => $this->string(64)->notNull()->defaultValue('')->comment('操作人名称快照'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('处理说明'),
            'voucher' => $this->string(512)->notNull()->defaultValue('')->comment('凭证图片地址'),
            'detail' => $this->text()->null()->comment('网关返回等原始信息'),
        ], null, '退款人工处理记录');
        $this->createIndex('idx_refund_handle_refund', self::T, ['mall_id', 'refund_id']);
        return true;
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::T) !== null) {
            $this->dropTable(self::T);
        }
        return true;
    }
}
