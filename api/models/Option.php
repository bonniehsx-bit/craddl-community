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
 * This is the model class for table "{{%option}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string $name
 * @property string|null $value
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $is_delete
 */
class Option extends BaseActiveRecord
{

    const NAME_SYS_SETTING = 'sys';
    const NAME_MALL_SETTING = "mall_setting";

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%option}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'name'], 'required'],
            [['mall_id', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['value'], 'string'],
            [['name'], 'string', 'max' => 45],
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
            'name' => 'Name',
            'value' => 'Value',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'is_delete' => 'Is Delete',
        ];
    }

    /**
     * 配置的商城号由调用方明确给出,原样保存:0 就是平台级配置(sys、domain、短信、wechat_mch_* 等)。
     *
     * 基类插入时会把「空」商城号换成当前商城,对别的表是省事,对这张表是把平台配置存错了地方:
     * 后台请求带了商城、命令行默认商城 1,第一次保存就落到当前商城下;读的人按商城号 0 查不到,
     * 全靠缓存撑着,缓存一丢配置就没了;而且之后每保存一次都按 0 查不到、再插一行。
     */
    public function beforeSave($insert)
    {
        $mallId = $this->mall_id;
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($mallId !== null && $mallId !== '') {
            $this->mall_id = (int) $mallId;
        }
        return true;
    }
}
