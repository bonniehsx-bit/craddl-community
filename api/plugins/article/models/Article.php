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


namespace app\plugins\article\models;

use app\models\BaseActiveRecord;

use Yii;

/**
 * This is the model class for table "{{%plugin_article}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string $title
 * @property string|null $created_by 由谁创建
 * @property string $detail
 * @property string $video
 * @property string $cover_pic
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property int|null $created_at
 * @property int $is_delete
 * @property int|null $cat_id
 * @property int $views
 * @property integer $sort
 * @property integer $is_published
 * @property string $sub_title
 */
class Article extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_article}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'title', 'detail'], 'required'],
            [['mall_id', 'deleted_at', 'updated_at', 'created_at', 'is_delete', 'cat_id', 'views', 'sort', 'is_published'], 'integer'],
            [['detail'], 'string'],
            [['title', 'created_by'], 'string', 'max' => 45],
            [['sub_title'],'string','max'=>128],
            [['video', 'cover_pic'], 'string', 'max' => 255],
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
            'title' => 'Title',
            'created_by' => '由谁创建',
            'detail' => 'Detail',
            'video' => 'Video',
            'cover_pic' => 'Cover Pic',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
            'is_delete' => 'Is Delete',
            'cat_id' => 'Cat ID',
            'views' => '阅读量'
        ];
    }
}
