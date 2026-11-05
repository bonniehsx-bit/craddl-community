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
 * 入驻字段配置 —— 按 mall_id + 主体类型,覆盖系统字段注册表(MchApplyFieldRegistry)的默认值。
 * 表内无记录的字段一律回退注册表默认,保证未配置时等于当前写死行为。
 *
 * This is the model class for table "{{%plugin_mch_apply_field}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $type 主体类型 1=线上 2=门店 4=个人
 * @property string $field_key 系统字段标识
 * @property int $is_enabled 是否启用
 * @property int $is_required 是否必填
 * @property string|null $label 自定义标签(空=用注册表默认)
 * @property string|null $placeholder 自定义占位(空=用注册表默认)
 * @property int $sort 排序
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class MchApplyField extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_mch_apply_field}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'type', 'field_key'], 'required'],
            [['mall_id', 'type', 'is_enabled', 'is_required', 'sort', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['field_key'], 'string', 'max' => 64],
            [['label'], 'string', 'max' => 64],
            [['placeholder'], 'string', 'max' => 255],
        ];
    }
}
