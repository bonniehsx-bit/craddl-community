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
 * 营销活动新增"领取型"支持:每人限领字段。
 * 领取型活动(type=3)在 C 端领券中心展示,用户主动领取。
 */
class m260703_120000_campaign_add_claim extends Migration
{
    public function safeUp()
    {
        $t = '{{%plugin_marketing_campaign}}';
        $schema = $this->db->getTableSchema($t, true);
        if ($schema && $schema->getColumn('per_user_limit') === null) {
            $this->addColumn($t, 'per_user_limit', $this->integer()->notNull()->defaultValue(1)
                ->comment('每人限领次数(仅领取型,0=不限)'));
        }
    }

    public function safeDown()
    {
        $t = '{{%plugin_marketing_campaign}}';
        $schema = $this->db->getTableSchema($t, true);
        if ($schema && $schema->getColumn('per_user_limit') !== null) {
            $this->dropColumn($t, 'per_user_limit');
        }
    }
}
