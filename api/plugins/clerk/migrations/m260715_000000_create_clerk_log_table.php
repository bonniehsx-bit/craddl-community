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

/**
 * 统一核销台账 plugin_clerk_log。
 *
 * 核销治理阶段3:平台/商家/插件提货 三条核销链核销时统一写一条(guarded 双写),
 * 与既有 MchVerifyLog / Order 反查过渡并存,读侧后续渐进切换。
 * 补齐审计缺位:多次核销/失败尝试/操作备注均留痕(平台侧原 $remark 死字段激活)。
 *
 * 继承 app\core\Migration,自动追加 is_delete/created_at/updated_at/deleted_at/
 * created_time/updated_time/deleted_time,故此处不重复声明。
 */
class m260715_000000_create_clerk_log_table extends \app\core\Migration
{
    public function safeUp()
    {
        $this->createTable('{{%plugin_clerk_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID:0=平台自营,>0=商家'),
            'biz_type' => $this->string(64)->notNull()->defaultValue('')->comment('业务类型:normal/card/hotel/group/xwy/brand...'),
            'biz_type_name' => $this->string(64)->notNull()->defaultValue('')->comment('业务类型显示名(词库解析后)'),
            'code' => $this->string(255)->notNull()->defaultValue('')->comment('核销码/凭证码'),
            'order_no' => $this->string(255)->notNull()->defaultValue('')->comment('关联订单号(可空)'),
            'clerk_id' => $this->integer()->notNull()->defaultValue(0)->comment('操作人 用户ID'),
            'clerk_name' => $this->string(255)->notNull()->defaultValue('')->comment('操作人昵称快照'),
            'source' => $this->string(32)->notNull()->defaultValue('')->comment('来源:platform/mch/plugin_landing'),
            'products_snapshot' => $this->text()->comment('商品快照 JSON [{name,spec,num,image}]'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('核销备注'),
            'result' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1=成功 0=失败'),
            'fail_reason' => $this->string(255)->notNull()->defaultValue('')->comment('失败原因(补审计)'),
            'verify_at' => $this->integer()->notNull()->defaultValue(0)->comment('核销时间戳'),
        ]);

        $this->createIndex('idx_mall_mch', '{{%plugin_clerk_log}}', ['mall_id', 'mch_id']);
        $this->createIndex('idx_clerk_id', '{{%plugin_clerk_log}}', 'clerk_id');
        $this->createIndex('idx_biz_type', '{{%plugin_clerk_log}}', 'biz_type');
        $this->createIndex('idx_code', '{{%plugin_clerk_log}}', 'code');
    }

    public function safeDown()
    {
        $this->dropTable('{{%plugin_clerk_log}}');
    }
}
