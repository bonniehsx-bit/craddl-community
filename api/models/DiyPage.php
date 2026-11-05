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
 * This is the model class for table "{{%diy_page}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $is_home
 * @property int $is_audit_page
 * @property string $data
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property int $is_enable
 * @property string $name
 * @property string $title_color
 * @property string $text_color
 * @property string $page_params
 */
class DiyPage extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%diy_page}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'data'], 'required'],
            [['mall_id', 'is_home', 'is_audit_page', 'is_delete', 'created_at', 'deleted_at', 'updated_at', 'is_enable'], 'integer'],
            [['data', 'name', 'page_params'], 'string'],
            [['title_color','text_color'], 'string', 'max' => 10],
            [['name'], 'string', 'max' => 45]
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
            'is_home' => 'Is Home',
            'is_audit_page' => '是否为审核版页面',
            'data' => 'Data',
            'is_delete' => 'Is Delete',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'is_enable' => 'Is Enable',
            'name' => 'name',
            'page_params' => '页面私有配置'
        ];
    }
}
