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
 * 核心 —— 通用用户钱包(可扩展账户底座)
 *
 * 背景:货款 mch_funds 等"账户"原先散在 User 列;现统一进通用钱包表,任意插件 getWallets() 声明的钱包都落这里。
 * 核心(CommissionService)与提现插件都经 app\services\WalletService 原子存取。
 *
 *  - user_wallet      用户某钱包余额(user_id + wallet_key 唯一)
 *  - user_wallet_log  钱包流水(入账/扣减)
 *
 * 自包含:建表前 DROP IF EXISTS,可重复执行。
 */
class m260629_120000_user_wallet extends Migration
{
    public function safeUp()
    {
        $this->db->createCommand('DROP TABLE IF EXISTS {{%user_wallet_log}}')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS {{%user_wallet}}')->execute();

        $this->createTable('{{%user_wallet}}', [
            'id'         => $this->primaryKey(),
            'mall_id'    => $this->integer()->notNull()->comment('商城ID'),
            'user_id'    => $this->integer()->notNull()->comment('用户ID'),
            'wallet_key' => $this->string(64)->notNull()->comment('钱包标识(插件 getWallets 声明,如 mch_funds / xwy.channel)'),
            'balance'    => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('当前可用余额'),
            'total'      => $this->decimal(14, 2)->notNull()->defaultValue(0)->comment('累计入账'),
        ]);
        $this->createIndex('uk_user_wallet', '{{%user_wallet}}', ['user_id', 'wallet_key'], true);
        $this->createIndex('idx_mall_wallet', '{{%user_wallet}}', ['mall_id', 'wallet_key']);

        $this->createTable('{{%user_wallet_log}}', [
            'id'            => $this->primaryKey(),
            'mall_id'       => $this->integer()->notNull()->comment('商城ID'),
            'user_id'       => $this->integer()->notNull()->comment('用户ID'),
            'wallet_key'    => $this->string(64)->notNull()->comment('钱包标识'),
            'type'          => $this->tinyInteger()->notNull()->comment('1=入账 2=扣减'),
            'amount'        => $this->decimal(12, 2)->notNull()->comment('变动额(正数)'),
            'balance_after' => $this->decimal(12, 2)->notNull()->comment('变动后余额'),
            'remark'        => $this->string(255)->null()->comment('备注'),
            'title'         => $this->string(100)->null()->comment('标题'),
            'source'        => $this->string(100)->null()->comment('来源标识(订单/业务key)'),
        ]);
        $this->createIndex('idx_user_wallet_log', '{{%user_wallet_log}}', ['user_id', 'wallet_key']);
    }

    public function safeDown()
    {
        $this->db->createCommand('DROP TABLE IF EXISTS {{%user_wallet_log}}')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS {{%user_wallet}}')->execute();
    }
}
