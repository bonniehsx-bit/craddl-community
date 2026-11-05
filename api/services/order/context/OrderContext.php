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

namespace app\services\order\context;

use app\models\Order;
use app\models\User;
use app\models\UserAddress;
use app\plugins\mch\models\Mch;
use Yii;

/**
 * 订单上下文 - 封装订单处理所需的所有数据
 *
 * 职责：
 * 1. 统一数据传递格式
 * 2. 减少方法参数数量
 * 3. 便于扩展新字段
 */
class OrderContext
{
    /** @var User 当前用户 */
    public $user;

    /** @var UserAddress|null 收货地址 */
    public $address;

    /** @var array 商品列表（包含goods对象、attr对象、数量等） */
    public $items = [];

    /** @var Mch|null 商户信息 */
    public $mch;

    /** @var int 商户ID */
    public $mchId = 0;

    /** @var int 商城ID */
    public $mallId;

    /** @var string 配送方式: express|pickup|city|cashier */
    public $deliverType = 'express';

    /** @var string 配送时间 */
    public $deliveryTime = '';

    /** @var int 是否立即配送(同城配送用) */
    public $isImmediately = 1;

    /** @var array 购买历史(限购商品用) */
    public $purchaseHistory = [];

    /** @var string 提货人姓名(自提用) */
    public $pickupName = '';

    /** @var string 提货人手机号(自提用) */
    public $pickupMobile = '';

    /** @var string 订单备注 */
    public $remarks = '';
    public $orderType = 'normal';

    /**
     * 从请求数据构建上下文
     *
     * @param array $data 请求数据
     * @param User|null $user 当前用户
     * @return self
     * @throws \Exception
     */
    public static function fromRequestData(array $data, ?User $user = null): self
    {
        $context = new self();

        // 基础信息
        $context->user = $user;
        $context->mallId = Yii::$app->mall->id;
        $context->mchId = (int)($data['mch_id'] ?? 0);
        $context->deliverType = $data['deliver_type'] ?? Order::DELIVER_TYPE_NONE;
        $context->deliveryTime = $data['delivery_time'] ?? '';
        $context->isImmediately = (int)($data['is_immediately'] ?? 0);
        $context->remarks = $data['remarks'] ?? '';

        // 自提信息
        $context->pickupName = $data['name'] ?? '';
        $context->pickupMobile = $data['mobile'] ?? '';

        // 解析地址
        if ($user) {
            $addressId = (int)($data['address_id'] ?? 0);
            $context->address = self::resolveAddress($user->id, $addressId);
        }

        // 解析商户
        if ($context->mchId > 0) {
            $context->mch = Mch::findOne($context->mchId);
            if (!$context->mch) {
                throw new \Exception('商户不存在');
            }
        }
        // 解析商品列表(暂时只是简单传递，具体解析在PricingService中)
        $context->items = $data['goods_list'] ?? [];
        $context->orderType = $data['order_type'] ?? 'normal'; // 新增这行
        return $context;
    }

    /**
     * 获取用户地址
     *
     * @param int $userId
     * @param int $addressId
     * @return UserAddress|null
     */
    private static function resolveAddress(int $userId, int $addressId): ?UserAddress
    {
        if ($addressId > 0) {
            return UserAddress::find()
                ->where(['id' => $addressId, 'user_id' => $userId, 'is_delete' => 0])
                ->one();
        }

        // 使用默认地址
        return UserAddress::find()
            ->where(['is_default' => 1, 'user_id' => $userId, 'is_delete' => 0])
            ->one();
    }

    /**
     * 检查配送方式是否需要地址
     *
     * @return bool
     */
    public function requiresAddress(): bool
    {
        return in_array($this->deliverType, ['express', 'city']);
    }

    /**
     * 检查配送方式是否需要自提人信息
     *
     * @return bool
     */
    public function requiresPickupInfo(): bool
    {
        return $this->deliverType === 'pickup';
    }

    /**
     * 获取用户等级ID
     *
     * @return int
     */
    public function getUserLevelId(): int
    {
        return $this->user ? (int)$this->user->level_id : 0;
    }

    /**
     * 获取用户积分
     *
     * @return int
     */
    public function getUserIntegral(): int
    {
        return $this->user ? (int)$this->user->integral : 0;
    }
}
