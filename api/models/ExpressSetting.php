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

use Yii;

/**
 * This is the model class for table "{{%express_setting}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string|null $kdniao_appcode
 * @property string|null $kdniao_customer
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $is_delete
 */
class ExpressSetting extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%express_setting}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            [['mall_id', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['kdniao_appcode', 'kdniao_customer'], 'string', 'max' => 64],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'Mall ID',
            'kdniao_appcode' => 'Kdniao Appcode',
            'kdniao_customer' => 'Kdniao Customer',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'is_delete' => 'Is Delete',
        ];
    }

    /**
     * [推荐] 创建或更新快递设置
     * @param int $mall_id
     * @param string $eBusinessId
     * @param string $appKey
     * @return bool
     */
    public static function set(int $mall_id, string $eBusinessId, string $appKey): bool
    {
        $setting = self::findOne(['mall_id' => $mall_id, 'is_delete' => 0]);
        if (!$setting) {
            $setting = new self();
            $setting->mall_id = $mall_id;
        }
        $setting->kdniao_customer = $eBusinessId;
        $setting->kdniao_appcode = $appKey;

        return $setting->save();
    }
}
