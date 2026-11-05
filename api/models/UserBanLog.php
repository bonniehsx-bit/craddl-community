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

namespace app\models;

/**
 * 用户封禁/解封操作审计日志
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $action        ban / unban / auto_unban / anonymize
 * @property string $reason
 * @property int    $operator_id   admin.id, 0 = 系统
 * @property string $operator_name 操作人名称快照 (防止 admin 改名后日志看不懂)
 * @property int|null $banned_until 本次操作的解封时间戳, NULL=永久 / 不适用
 * @property string|null $snapshot JSON, 操作前状态快照
 * @property int    $is_delete
 * @property int    $created_at
 * @property int    $updated_at
 */
class UserBanLog extends BaseActiveRecord
{
    const ACTION_BAN        = 'ban';
    const ACTION_UNBAN      = 'unban';
    const ACTION_AUTO_UNBAN = 'auto_unban';
    const ACTION_ANONYMIZE  = 'anonymize';
    const ACTION_DELETE     = 'delete';  // 后台代为注销账号
    const ACTION_RESTORE    = 'restore'; // 后台恢复已注销账号

    public static function tableName()
    {
        return '{{%user_ban_log}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'operator_id', 'banned_until', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['action'], 'string', 'max' => 16],
            [['reason'], 'string', 'max' => 255],
            [['operator_name'], 'string', 'max' => 64],
            [['snapshot'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
        ];
    }
}
