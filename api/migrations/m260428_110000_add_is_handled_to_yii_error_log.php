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

class m260428_110000_add_is_handled_to_yii_error_log extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%yii_error_log}}', 'is_handled', $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否已处理 0=未处理 1=已处理')->after('last_seen_at'));
        $this->createIndex('idx_is_handled', '{{%yii_error_log}}', 'is_handled');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_is_handled', '{{%yii_error_log}}');
        $this->dropColumn('{{%yii_error_log}}', 'is_handled');
    }
}
