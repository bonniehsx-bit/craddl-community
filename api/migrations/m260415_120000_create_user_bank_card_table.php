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
 * 用户快捷支付绑卡表
 *
 * 存储用户通过快付(FastPay)等第三方快捷支付网关绑定的消费银行卡。
 * 敏感字段以 _enc 后缀结尾，存储 AES 加密后的密文（由 UserBankCard 模型自动加解密）。
 *
 * 注意：
 * 1. cvv2_enc 字段为合规灰区，保留该字段是商户明确要求；CVV2 依常规 PCI-DSS 规范不应落库，
 *    如果未来要通过安全审计，请移除该字段并在 FastPayQuickPayment::applyBindCard() 完成后立即丢弃。
 * 2. bank_card_last4 字段为明文末 4 位，仅用于列表展示。
 */
class m260415_120000_create_user_bank_card_table extends \app\core\Migration
{
    public function safeUp()
    {
        $columns = [
            'id'               => $this->primaryKey(11)->comment('主键ID'),
            'mall_id'          => $this->integer(11)->notNull()->defaultValue(0)->comment('商城ID'),
            'user_id'          => $this->integer(11)->notNull()->defaultValue(0)->comment('用户ID'),
            'gateway'          => $this->string(128)->notNull()->defaultValue('')->comment('支付网关类FQN'),
            'bind_card_id'     => $this->string(255)->notNull()->defaultValue('')->comment('网关侧返回的绑卡凭证ID'),
            'holder_name'      => $this->string(64)->notNull()->defaultValue('')->comment('持卡人姓名(明文)'),
            'mobile_enc'       => $this->text()->null()->comment('预留手机号(密文)'),
            'id_type'          => $this->string(8)->notNull()->defaultValue('00')->comment('证件类型 00=身份证'),
            'id_no_enc'        => $this->text()->null()->comment('证件号(密文)'),
            'bank_card_no_enc' => $this->text()->null()->comment('银行卡号(密文)'),
            'bank_card_last4'  => $this->string(4)->notNull()->defaultValue('')->comment('卡号末4位(展示用)'),
            'bank_name'        => $this->string(64)->notNull()->defaultValue('')->comment('银行名称'),
            'bank_code'        => $this->string(16)->notNull()->defaultValue('')->comment('银行编码'),
            'card_type'        => $this->char(1)->notNull()->defaultValue('D')->comment('卡类型 D储蓄 C信用'),
            'valid_date_enc'   => $this->text()->null()->comment('信用卡有效期YYYYMM(密文)'),
            'cvv2_enc'         => $this->text()->null()->comment('CVV2(密文, 商户明确要求存储)'),
            'bind_status'      => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('绑定状态 0待验证 1已生效 2已解绑 3失效'),
            'is_default'       => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否默认卡'),
            'remark'           => $this->string(255)->notNull()->defaultValue('')->comment('备注'),
        ];

        $this->createTable('{{%user_bank_card}}', $columns, null, '用户快捷支付绑卡表');

        $this->createIndex(
            'idx-user_bank_card-user_gateway',
            '{{%user_bank_card}}',
            ['user_id', 'gateway', 'is_delete']
        );
        $this->createIndex(
            'idx-user_bank_card-bind_card_id',
            '{{%user_bank_card}}',
            'bind_card_id'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_bank_card}}');
    }
}
