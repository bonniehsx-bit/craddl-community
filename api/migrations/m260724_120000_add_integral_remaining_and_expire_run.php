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
 * 积分有效期(P3)基础设施:
 * 1) integral_log 加 remaining 列 —— 收益行(type=1)即一个"批次",remaining=本批剩余;
 *    FIFO 消费按 created_at 从最老批次扣 remaining。仅当商城开启有效期时维护,历史/未开启为 null。
 * 2) integral_expire_run —— 过期回收操作流水(管理端审计,防争议):每次回收一条,run_no 贯穿被回收的行。
 */
class m260724_120000_add_integral_remaining_and_expire_run extends Migration
{
    public function safeUp()
    {
        $log = '{{%integral_log}}';
        $t = \Yii::$app->db->schema->getTableSchema($log, true);
        if ($t && !isset($t->columns['remaining'])) {
            $this->addColumn(
                $log,
                'remaining',
                $this->decimal(12, 2)->null()->comment('本批剩余积分(仅收益行有效期批次维护;null=未开启有效期/支出行)')->after('biz_type')
            );
            // FIFO 扫描:按用户捞未用尽的收益行,再按 created_at 排序
            $this->createIndex('idx_integral_log_fifo', $log, ['mall_id', 'user_id', 'remaining']);
        }

        $run = '{{%integral_expire_run}}';
        if (\Yii::$app->db->schema->getTableSchema($run, true) === null) {
            $this->createTable($run, [
                'id'              => $this->primaryKey(),
                'mall_id'         => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
                'run_no'          => $this->string(32)->notNull()->comment('本次回收编号(贯穿被回收的收益行与支出流水)'),
                'mode'            => $this->string(8)->notNull()->defaultValue('manual')->comment('manual=手动 / rolling=自动滚动'),
                'clear_before'    => $this->string(10)->notNull()->comment('回收截止日 YYYY-MM-DD(回收入账早于此日的未用积分)'),
                'affected_users'  => $this->integer()->notNull()->defaultValue(0)->comment('影响用户数'),
                'total_recovered' => $this->decimal(14, 2)->notNull()->defaultValue(0)->comment('回收积分总额'),
                'operator_id'     => $this->integer()->notNull()->defaultValue(0)->comment('操作管理员ID(0=系统自动)'),
                'executed_at'     => $this->integer()->null()->comment('执行时间戳'),
            ]);
            $this->createIndex('idx_expire_run_mall', $run, ['mall_id', 'run_no']);
        }
    }

    public function safeDown()
    {
        $log = '{{%integral_log}}';
        $t = \Yii::$app->db->schema->getTableSchema($log, true);
        if ($t && isset($t->columns['remaining'])) {
            $this->dropIndex('idx_integral_log_fifo', $log);
            $this->dropColumn($log, 'remaining');
        }
        $run = '{{%integral_expire_run}}';
        if (\Yii::$app->db->schema->getTableSchema($run, true) !== null) {
            $this->dropTable($run);
        }
    }
}
