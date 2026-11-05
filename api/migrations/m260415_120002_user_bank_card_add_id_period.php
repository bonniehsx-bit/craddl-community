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

use yii\db\Migration;

/**
 * 为 {{%user_bank_card}} 增加证件有效期字段
 *
 * 快付 KJ_CARD_APPLY 接口要求 (是否必填以接口响应为准, 实测要求必填):
 *  - idDocPeriodBegin: 证件开始日期, 格式 yyyyMMdd
 *  - idDocPeriodEnd  : 证件结束日期, 格式 yyyyMMdd, 长期填 29991231
 *
 * 这两个字段明文上送 (文档未要求密文), 数据库也直接明文存储。
 */
class m260415_120002_user_bank_card_add_id_period extends Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        $this->addColumn(
            '{{%user_bank_card}}',
            'id_period_begin',
            $this->string(8)->notNull()->defaultValue('')->after('id_no_enc')->comment('证件有效期开始 yyyyMMdd')
        );
        $this->addColumn(
            '{{%user_bank_card}}',
            'id_period_end',
            $this->string(8)->notNull()->defaultValue('')->after('id_period_begin')->comment('证件有效期结束 yyyyMMdd, 长期=29991231')
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user_bank_card}}', 'id_period_end');
        $this->dropColumn('{{%user_bank_card}}', 'id_period_begin');
    }
}
