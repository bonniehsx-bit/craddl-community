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
 * 证照库定义 —— 后台可增减的证照种类(营业执照及未来任意许可证),每种可配 必填/有效期/适用类型。
 * 营业执照为预置项(preset_key=business_license, is_system=1),由 MchApplyConfigService 惰性补齐。
 *
 * This is the model class for table "{{%plugin_mch_cert_def}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string $name 证照名称
 * @property string|null $preset_key 预置标识;自定义证照为空
 * @property string $types 适用主体类型,逗号分隔如 "1,2"
 * @property int $is_required 是否必填
 * @property int $need_validity 是否需要有效期
 * @property int $is_system 是否预置(不可删)
 * @property int $is_enabled 是否启用
 * @property int $sort 排序
 * @property int $is_delete
 */
class MchCertDef extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_mch_cert_def}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'name'], 'required'],
            [['mall_id', 'is_required', 'need_validity', 'is_system', 'is_enabled', 'sort', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['name'], 'string', 'max' => 64],
            [['preset_key'], 'string', 'max' => 32],
            [['types'], 'string', 'max' => 64],
        ];
    }

    /**
     * 适用主体类型 → int 数组(过滤空值)
     * @return int[]
     */
    public function getTypeList()
    {
        $list = array_map('intval', array_filter(explode(',', (string)$this->types), function ($v) {
            return $v !== '' && $v !== null;
        }));
        return array_values(array_filter($list, function ($v) {
            return $v > 0;
        }));
    }
}
