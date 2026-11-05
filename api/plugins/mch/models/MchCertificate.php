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
 * 商家提交的证照值 —— 图片 + 有效期。valid_end 独立成列,便于「证照到期提醒」直接查询(二期)。
 *
 * This is the model class for table "{{%plugin_mch_certificate}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id 商家ID plugin_mch.id
 * @property int $cert_def_id 关联证照定义ID
 * @property string|null $preset_key 冗余预置标识,便于回写兼容列
 * @property string $name_snapshot 提交时证照名快照
 * @property string $image 证照图片
 * @property string|null $valid_start 有效期起 (Y-m-d)
 * @property string|null $valid_end 有效期止 (Y-m-d)
 * @property int $is_long_term 是否长期有效
 * @property int $is_delete
 */
class MchCertificate extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_mch_certificate}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'mch_id'], 'required'],
            [['mall_id', 'mch_id', 'cert_def_id', 'is_long_term', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['valid_start', 'valid_end', 'created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['preset_key'], 'string', 'max' => 32],
            [['name_snapshot'], 'string', 'max' => 64],
            [['image'], 'string', 'max' => 255],
        ];
    }
}
