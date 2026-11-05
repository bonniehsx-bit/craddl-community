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
 * 给 user 表加"封禁元数据"相关字段, 支撑后台拉黑的原因/操作人/到期自动解封/审计
 *
 * 字段说明:
 *  - ban_reason    VARCHAR(255)   封禁原因, 后台必填 (C 端登录被拒时回显给用户)
 *  - banned_by     INT            执行封禁的管理员 ID (admin.id, 0 = 系统)
 *  - banned_at     INT            封禁时间戳 (Unix 秒)
 *  - banned_until  INT NULL       解封时间戳; NULL = 永久封禁; 有值且 < now() = 已过期(下次登录 lazy 自动解封)
 */
class m260416_100000_add_ban_fields_to_user extends Migration
{
    public function init()
    {
        $this->db = 'db';
        parent::init();
    }

    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'ban_reason',
            $this->string(255)->notNull()->defaultValue('')->after('status')->comment('封禁原因')
        );
        $this->addColumn('{{%user}}', 'banned_by',
            $this->integer(11)->notNull()->defaultValue(0)->after('ban_reason')->comment('执行封禁的管理员ID')
        );
        $this->addColumn('{{%user}}', 'banned_at',
            $this->integer(11)->notNull()->defaultValue(0)->after('banned_by')->comment('封禁时间戳')
        );
        $this->addColumn('{{%user}}', 'banned_until',
            $this->integer(11)->null()->defaultValue(null)->after('banned_at')->comment('解封时间戳, NULL=永久')
        );
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'banned_until');
        $this->dropColumn('{{%user}}', 'banned_at');
        $this->dropColumn('{{%user}}', 'banned_by');
        $this->dropColumn('{{%user}}', 'ban_reason');
    }
}
