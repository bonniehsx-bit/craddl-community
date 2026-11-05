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
use yii\helpers\BaseArrayHelper;

/**
 * This is the model class for table "{{%plugin}}".
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string $version
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property int $is_delete
 * @property int $type 0 分润类 1营销类 2工具
 * @property int $install_type 0本地 1在线
 * @property string|null $logo
 * @property string $description
 */
class Plugin extends BaseActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'label', 'version'], 'required'],
            [['created_at', 'deleted_at', 'updated_at', 'is_delete', 'type', 'install_type'], 'integer'],
            [['name', 'label'], 'string', 'max' => 45],
            [['logo'], 'string'],
            [['description'], 'string', 'max' => 255],
            [['version'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'label' => 'label',
            'version' => 'Version',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'is_delete' => 'Is Delete',
            'type' => '0 分润类 1营销类 2工具',
            'install_type' => '0本地 1在线',
            'logo' => 'logo'
        ];
    }
}
