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
 * This is the model class for table "{{%merchant_rule}}".
 *
 * @property int $id 主键ID
 * @property int $mall_id 商城ID
 * @property string $title 规则标题
 * @property string $content 规则内容 (富文本)
 * @property int $sort 排序，升序
 *  --- Common Fields ---
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 */
class MerchantRule extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%merchant_rule}}';
    }


    public function rules()
    {
        return [
            [['mall_id', 'title', 'content'], 'required'],
            [['mall_id', 'sort', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['content'], 'string'],
            [['title'], 'string', 'max' => 255],
            [['sort'], 'default', 'value' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'title' => '规则标题',
            'content' => '规则内容',
            'sort' => '排序',
        ];
    }
}