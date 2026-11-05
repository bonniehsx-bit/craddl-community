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
 * 可靠事件外发箱(transactional outbox)。
 * 业务事务里写一条待办 → 可重试 worker(EventOutboxTask)消费 → 团队统计增量,事件永不丢。
 * 首个消费者:团队统计(order_paid / order_settled / commission_revert / level_changed)。
 */
class m260702_160000_event_outbox extends Migration
{
    public function safeUp()
    {
        $t = '{{%event_outbox}}';
        $this->createTable($t, [
            'id'           => $this->bigPrimaryKey(),
            'mall_id'      => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'type'         => $this->string(40)->notNull()->comment('事件类型:order_paid/order_settled/commission_revert/level_changed'),
            'payload'      => $this->text()->comment('处理所需最小数据(JSON)'),
            'status'       => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('0待处理 1已完成 2死信'),
            'attempts'     => $this->integer()->notNull()->defaultValue(0)->comment('已尝试次数'),
            'available_at' => $this->integer()->notNull()->defaultValue(0)->comment('可处理时间戳(退避用)'),
            'locked_token' => $this->string(40)->null()->comment('领取令牌(防并发重复处理)'),
            'locked_at'    => $this->integer()->notNull()->defaultValue(0)->comment('领取时间(超时可被重新领取)'),
            'last_error'   => $this->string(500)->null()->comment('最后一次失败原因'),
        ], null, '可靠事件外发箱 transactional outbox');

        // 取件过滤:待处理 + 到期 + 锁状态
        $this->createIndex('idx-event_outbox-claim', $t, ['status', 'available_at', 'locked_at']);
        $this->createIndex('idx-event_outbox-mall_status', $t, ['mall_id', 'status']);
        // 领取令牌回捞
        $this->createIndex('idx-event_outbox-token', $t, ['locked_token']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%event_outbox}}');
    }
}
