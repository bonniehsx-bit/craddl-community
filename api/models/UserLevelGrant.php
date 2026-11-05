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
 * 用户等级授予台账(带有效期)
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property int $level_id            授予的等级
 * @property int $level_weight        授予等级权重
 * @property int $before_level_id     授予前等级(降级回退目标)
 * @property int $before_level_weight 授予前等级权重
 * @property int $grant_at
 * @property int $expire_at           到点降级时间
 * @property int $status              1有效 0已降级 2已撤销
 * @property string $source
 * @property string $biz_no
 * @property string $remark
 */
class UserLevelGrant extends BaseActiveRecord
{
    const STATUS_ACTIVE  = 1; // 有效
    const STATUS_EXPIRED = 0; // 已降级
    const STATUS_REVOKED = 2; // 已撤销(退款等)

    public static function tableName()
    {
        return '{{%user_level_grant}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'level_id'], 'required'],
            [['mall_id', 'user_id', 'level_id', 'level_weight', 'before_level_id', 'before_level_weight', 'grant_at', 'expire_at', 'status'], 'integer'],
            [['source', 'biz_no'], 'string', 'max' => 64],
            [['remark'], 'string', 'max' => 255],
            [['source', 'biz_no', 'remark'], 'default', 'value' => ''],
        ];
    }
}
