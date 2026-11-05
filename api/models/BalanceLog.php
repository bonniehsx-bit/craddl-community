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
 * This is the model class for table "{{%balance_log}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property int $type 类型: 0=支出(减少) 1=收入(增加)
 * @property float $money 变动金额
 * @property float $current 当前余额
 * @property string|null $title 标题/业务类型
 * @property string|null $content 详情/备注
 * @property int $from_user_id 来源用户ID (例如转账人)
 * @property int $to_user_id 目标用户ID
 * @property string|null $date_at 日期 (Y-m-d)
 * @property string|null $month_at 月份 (Y-m)
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int $is_delete 是否删除
 *
 * @property User $user 关联用户
 */
class BalanceLog extends BaseActiveRecord
{
    const TYPE_SUB = 0; // 支出
    const TYPE_ADD = 1; // 收入

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%balance_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['mall_id', 'user_id', 'type', 'from_user_id', 'to_user_id', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['money', 'current'], 'number'],
            [['title', 'date_at', 'month_at'], 'string', 'max' => 45],
            [['content'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'type' => '类型',
            'money' => '变动金额',
            'current' => '当前余额',
            'title' => '标题',
            'content' => '详情',
            'from_user_id' => '来源用户ID',
            'to_user_id' => '目标用户ID',
            'date_at' => '日期',
            'month_at' => '月份',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'is_delete' => '是否删除',
        ];
    }

    /**
     * 自动填充数据钩子
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // 自动填充月份
            if (empty($this->month_at)) {
                $this->month_at = date('Y-m');
            }
            // 自动填充日期
            if (empty($this->date_at)) {
                $this->date_at = date('Y-m-d');
            }
            // 自动填充默认标题
            if (empty($this->title)) {
                $this->title = $this->type == self::TYPE_ADD ? '收入' : '支出';
            }
            return true;
        }
        return false;
    }

    /**
     * 关联用户表
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}