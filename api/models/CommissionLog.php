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
use yii\helpers\Json;

/**
 * This is the model class for table "{{%commission_log}}".
 *
 * 分佣记录表
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property string $type
 * @property float $commission_amount
 * @property int $status
 * @property int $order_id
 * @property int $order_detail_id
 * @property int $buy_user_id
 * @property float|null $calculation_price
 * @property float|null $commission_rate
 * @property string|null $remark
 * @property int $level
 * @property string|null $extra_data
 * @property int $settled_at
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 */
class CommissionLog extends BaseActiveRecord
{
    // --- 状态常量 ---
    const STATUS_PENDING = 0;   // 待结算
    const STATUS_SETTLED = 1;   // 已结算
    const STATUS_INVALID = 2;   // 已失效

    // --- 类型常量已全部下放至各插件 ---
    // 新架构：每个插件自持 {Plugin}CommissionType 常量类（例: DistributionCommissionType::DISTRIBUTION、
    // AreaCommissionType::AREA、DealerCommissionType::DIFF 等）。核心 CommissionLog 不再维护
    // 业务类型枚举，实现"核心与插件完全解耦"。
    //
    // 新增/修改类型的姿势：
    //   1) 在插件 models/ 下建或编辑 {Plugin}CommissionType.php
    //   2) 在插件 listeners/CommissionTypeListener.php 里通过 CommissionTypeEvent::EVENT_COLLECT 登记展示元数据
    //   核心文件（本文件、CommissionTypeRegistry）无需改动。

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%commission_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'commission_amount', 'status', 'type'], 'required'],
            [['mall_id', 'user_id', 'order_id', 'order_detail_id', 'buy_user_id', 'status', 'settled_at', 'level', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['commission_amount', 'calculation_price', 'commission_rate'], 'number'],
            [['type'], 'string', 'max' => 45],
            [['remark'], 'string', 'max' => 255],
            [['extra_data'], 'string'],
            [['order_id', 'order_detail_id', 'buy_user_id', 'level', 'settled_at'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
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
            'user_id' => '获得者ID',
            'type' => '分佣类型',
            'commission_amount' => '分佣金额',
            'status' => '状态', // 0:待结算 1:已结算 2:已失效
            'order_id' => '关联订单ID',
            'order_detail_id' => '关联详情ID',
            'buy_user_id' => '触发用户ID',
            'calculation_price' => '计算基数',
            'commission_rate' => '佣金比例',
            'remark' => '备注',
            'level' => '分销等级',
            'extra_data' => '扩展数据',
            'settled_at' => '结算时间',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
        ];
    }

    // ================= 关联关系 =================

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getBuyUser()
    {
        return $this->hasOne(User::class, ['id' => 'buy_user_id']);
    }

    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function getOrderDetail()
    {
        return $this->hasOne(OrderDetail::class, ['id' => 'order_detail_id']);
    }

    // ================= 辅助方法 =================

    /**
     * 获取类型映射表 [type => label]
     *
     * [重构] 委托 CommissionTypeRegistry，数据合并顺序：运行时 register ∪ 内置 ∪ DB DISTINCT type。
     * 插件新增类型不需要修改本常量 —— 在 listener::register() 里调用
     * CommissionTypeRegistry::register($type, $meta) 登记即可，或直接写入 log 自动出现。
     *
     * @param int|null $mallId 传入商城 ID 可启用 DB 自动发现；不传仅用注册表+内置
     * @return array
     */
    public static function getTypeMap(?int $mallId = null): array
    {
        if ($mallId === null && Yii::$app->has('mall') && Yii::$app->mall) {
            $mallId = Yii::$app->mall->id;
        }
        return \app\services\CommissionTypeRegistry::getTypeMap($mallId);
    }

    /**
     * [新增] 获取状态映射表
     * @return array
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_PENDING => '待结算',
            self::STATUS_SETTLED => '已结算',
            self::STATUS_INVALID => '已失效',
        ];
    }

    /**
     * 获取类型名称
     * @return string
     */
    public function getTypeText(): string
    {
        $map = self::getTypeMap();
        return $map[$this->type] ?? $this->type;
    }

    /**
     * 获取状态名称
     * @return string
     */
    public function getStatusText(): string
    {
        $map = self::getStatusMap();
        return $map[$this->status] ?? '未知状态';
    }
}