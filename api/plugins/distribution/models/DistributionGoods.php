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

namespace app\plugins\distribution\models;

use app\models\BaseActiveRecord;

/**
 * 分销插件-商品独立配置表
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $goods_id 商品ID
 * @property int $is_commission_open 是否开启分销 0否 1是
 * @property string $commission_rule_type 规则类型: default/custom
 * @property string|null $commission_rules 自定义规则JSON
 * @property string|null $level_commission_rules 按分销商等级独立配置JSON
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int $is_delete 是否删除
 */
class DistributionGoods extends BaseActiveRecord
{
    // --- 常量定义 ---
    const IS_OPEN_TRUE = 1;
    const IS_OPEN_FALSE = 0;

    const RULE_TYPE_DEFAULT = 'default';   // 跟随全局
    const RULE_TYPE_CUSTOM = 'custom';     // 商品统一自定义（覆盖所有分销商）
    const RULE_TYPE_BY_LEVEL = 'by_level'; // 按分销商等级独立配置

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_distribution_goods}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'goods_id'], 'required'],
            [['mall_id', 'goods_id', 'is_commission_open', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['commission_rules', 'level_commission_rules'], 'string'],
            [['commission_rule_type'], 'string', 'max' => 32],

            // 默认值设置
            [['is_commission_open'], 'default', 'value' => self::IS_OPEN_FALSE],
            [['commission_rule_type'], 'default', 'value' => self::RULE_TYPE_DEFAULT],
            [['is_delete'], 'default', 'value' => 0],
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
            'goods_id' => '商品ID',
            'is_commission_open' => '是否开启分销',
            'commission_rule_type' => '规则类型',
            'commission_rules' => '自定义分销规则',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'is_delete' => '是否删除',
        ];
    }
}