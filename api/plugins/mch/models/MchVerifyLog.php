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

namespace app\plugins\mch\models;

use app\models\BaseActiveRecord;

/**
 * class MchVerifyLog
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id
 * @property int $clerk_id
 * @property string $clerk_name
 * @property string $verify_type
 * @property string $verify_type_name
 * @property string $order_no
 * @property string $products_snapshot
 * @property string $remark
 * @property int $verify_at
 * @property int $is_delete
 * @property string $created_at
 * @property string $updated_at
 */
class MchVerifyLog extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_mch_verify_log}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'mch_id', 'clerk_id'], 'required'],
            [['mall_id', 'mch_id', 'clerk_id', 'verify_at', 'is_delete'], 'integer'],
            [['products_snapshot'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['clerk_name', 'order_no', 'remark'], 'string', 'max' => 255],
            [['verify_type', 'verify_type_name'], 'string', 'max' => 64],
        ];
    }
}
