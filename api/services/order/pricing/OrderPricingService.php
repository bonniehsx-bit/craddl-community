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

namespace app\services\order\pricing;

use app\helpers\CityDeliveryHelper;
use app\helpers\OptionHelper;
use app\models\Attr;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\services\order\context\BasePricing;
use app\services\order\context\OrderContext;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * 订单定价服务 - 计算订单基础价格
 *
 * 职责：
 * 1. 计算商品价格(应用会员折扣)
 * 2. 计算运费(快递/同城)
 * 3. 校验商品权限(会员等级、限购等)
 *
 * 注意：本服务只计算基础价格，不涉及优惠券、积分等营销优惠
 */
class OrderPricingService
{
    /**
     * 计算订单基础定价
     *
     * @param OrderContext $context
     * @return BasePricing
     * @throws \Exception
     */
    public function calculate(OrderContext $context): BasePricing
    {
        \app\services\order\OrderInputGuard::assertItems($context->items);
        // 1. 准备商品数据
        $goodsData = $this->prepareGoodsData($context);

        // 2. 计算商品总价(含会员折扣)
        $pricingResult = $this->calculateGoodsPricing(
            $goodsData['goods_map'],
            $goodsData['attr_map'],
            $context->items,
            $context->getUserLevelId()
        );

        // 3. 检查购买权限
        if ($context->user) {
            $this->checkPurchasePermissions(
                $context->user,
                $goodsData['goods_map'],
                $context->items,
                $goodsData['purchase_history']
            );
        }

        // 4. 计算运费(同城配送:范围/起送金额只做标记,硬拦截在创建阶段 ProcessDeliveryStage)
        $shippingResult = $this->calculateShipping(
            $goodsData['goods_map'],
            $context
        );

        return new BasePricing([
            'goods_total_price' => $pricingResult['total_price'],
            'member_discount_price' => $pricingResult['member_discount'],
            'express_price' => $shippingResult['fee'],
            'items' => $pricingResult['items'],
            'can_use_integral' => $pricingResult['can_use_integral'],
            'city_delivery_info' => $shippingResult['city_info'],
        ]);
    }

    /**
     * 准备商品基础数据(商品、规格、购买历史)
     *
     * @param OrderContext $context
     * @return array
     * @throws \Exception
     */
    private function prepareGoodsData(OrderContext $context): array
    {
        $goodsIds = array_column($context->items, 'goods_id');
        $attrIds = array_column($context->items, 'goods_attr_id');

        // 查询商品
        $goodsMap = Goods::find()
            ->where(['id' => $goodsIds, 'status' => Goods::STATUS_UP, 'is_delete' => 0])
            // 未过审的【普通商品】不可下单 —— 补上平台商品审核的最后一道闸:
            // 上传端的 is_check 拦截被有意移除(允许未审先"标称上架"),约定由客户端不显示,
            // 但只挡列表不挡下单,拿到链接照样能买、能发货、能结算,审核等于没有。
            // ⚠ 只对 type=normal 生效:插件商品(dining/lucky/voucher/yuda 等)不走平台审核流,
            //   实测库中它们大量 is_check=0 且正常在售,一刀切会直接让这些业态下不了单。
            ->andWhere(['or', ['!=', 'type', Goods::TYPE_NORMAL], ['is_check' => 1]])
            ->with('info') // 预加载权限配置
            ->indexBy('id')
            ->all();

        if (empty($goodsMap)) {
            throw new \Exception('未获取到下单商品列表');
        }

        // 查询规格
        $attrMap = !empty($attrIds)
            ? Attr::find()->where(['id' => $attrIds, 'is_delete' => 0])->indexBy('id')->all()
            : [];

        // 查询购买历史(限购商品用)
        $purchaseHistory = [];
        if ($context->user) {
            $limitGoodsIds = array_filter($goodsIds, function ($id) use ($goodsMap) {
                return isset($goodsMap[$id]) && $goodsMap[$id]->is_limit;
            });

            if (!empty($limitGoodsIds)) {
                $history = OrderDetail::find()->alias('od')
                    ->joinWith(['order o'])
                    ->where([
                        'o.user_id' => $context->user->id,
                        'od.goods_id' => $limitGoodsIds,
                        'o.is_delete' => 0,
                    ])
                    // Only count orders that are not closed (canceled)
                    ->andWhere(['!=', 'o.status', Order::STATUS_CLOSED])
                    ->select(['od.goods_id', 'total_num' => 'SUM(od.num)'])
                    ->groupBy('od.goods_id')
                    ->asArray()
                    ->all();

                $purchaseHistory = ArrayHelper::map($history, 'goods_id', 'total_num');
            }
        }

        return [
            'goods_map' => $goodsMap,
            'attr_map' => $attrMap,
            'purchase_history' => $purchaseHistory,
        ];
    }

    /**
     * 计算商品定价(应用会员折扣)
     *
     * @param array $goodsMap
     * @param array $attrMap
     * @param array $items
     * @param int $userLevelId
     * @return array
     * @throws \Exception
     */
    private function calculateGoodsPricing(
        array $goodsMap,
        array $attrMap,
        array &$items,
        int   $userLevelId
    ): array {
        $totalPrice = '0.00';
        $totalMemberDiscount = '0.00';
        $canUseIntegral = false;

        foreach ($items as &$item) {
            /** @var Goods $goods */
            $goods = $goodsMap[$item['goods_id']];

            // 标记订单是否支持积分
            if ($goods->is_integral == 1) {
                $canUseIntegral = true;
            }

            // 1. 确定基础价格(商品价或规格价)
            $basePrice = $goods->price;
            $goodsAttr = null;

            if (!empty($item['goods_attr_id'])) {
                $goodsAttr = $attrMap[$item['goods_attr_id']] ?? null;
                // 规格必须属于这个商品:否则带上别的便宜商品的规格 id,就能按那个规格的价下单
                if (!$goodsAttr || (int)$goodsAttr->goods_id !== (int)$goods->id) {
                    throw new \Exception("您选择的商品规格「{$goods->name}」已下架或发生变更，请重新选择");
                }
                $basePrice = $goodsAttr->price;
            }

            // 2. 应用会员折扣
            $memberPrice = $goods->applyLevelDiscountToPrice($basePrice, $userLevelId);

            \app\services\order\OrderInputGuard::assertAmount($basePrice);
            \app\services\order\OrderInputGuard::assertAmount($memberPrice);

            // 3. 计算单件会员优惠
            $unitMemberDiscount = bcsub((string)$basePrice, (string)$memberPrice, 2);
            $unitMemberDiscount = max('0.00', $unitMemberDiscount);

            // 4. 填充商品明细
            $item['goods'] = $goods; // 保留对象引用(后续策略需要)
            $item['attr'] = $goodsAttr;
            $item['price'] = $memberPrice;
            $item['total_price'] = bcmul($memberPrice, (string)$item['num'], 2);
            $item['unit_member_discount'] = $unitMemberDiscount;
            $item['total_member_discount_price'] = bcmul($unitMemberDiscount, (string)$item['num'], 2);
            $item['name'] = $goods->name;
            $item['cover_pic'] = ($goodsAttr && $goodsAttr->cover_pic)
                ? $goodsAttr->cover_pic
                : $goods->cover_pic;

            // 规格数据
            if ($goodsAttr && $goodsAttr->spec_data) {
                $item['specs_data'] = Json::decode($goodsAttr->spec_data);
            }

            // 初始化优惠字段(后续策略填充)
            $item['coupon_cut_price'] = '0.00';
            $item['first_order_discount_price'] = '0.00';
            $item['integral_cut_price'] = '0.00';
            $item['use_integral'] = 0;
            $item['pickup_discount_price'] = '0.00';
            $item['platform_subsidy_random_minus'] = '0.00';

            // 5. 累加总价
            $totalPrice = bcadd($totalPrice, $item['total_price'], 2);
            $totalMemberDiscount = bcadd($totalMemberDiscount, $item['total_member_discount_price'], 2);
        }

        return [
            'total_price' => $totalPrice,
            'member_discount' => $totalMemberDiscount,
            'items' => $items,
            'can_use_integral' => $canUseIntegral,
        ];
    }

    /**
     * 校验购买权限
     *
     * @param mixed $user
     * @param array $goodsMap
     * @param array $items
     * @param array $purchaseHistory
     * @throws \Exception
     */
    private function checkPurchasePermissions($user, array $goodsMap, array $items, array $purchaseHistory): void
    {
        // 交易规则(后台「交易规则」可配文案):等级限购 / 限购 的提示语,支持占位符;留空/未配置用默认文案。
        $setting = OptionHelper::get('mall_setting', (int)$user->mall_id, []);
        $levelMsgTpl = !empty($setting['buy_level_limit_msg']) ? $setting['buy_level_limit_msg'] : '您的用户等级不可购买商品: {name}';
        $limitMsgTpl = !empty($setting['buy_limit_num_msg']) ? $setting['buy_limit_num_msg'] : '商品：{name}，超出了购买限制(限购{limit}件，已购{history}件)';

        foreach ($items as $item) {
            /** @var Goods $goods */
            $goods = $goodsMap[$item['goods_id']];

            // 1. 会员等级限制
            if ($goods->info) {
                $permission = Json::decode($goods->info->permission);
                if (($permission['buy_is_level'] ?? 0) == 1) {
                    $allowedLevels = $permission['buy_level_ids'] ?? [];
                    if (!in_array($user->level_id, $allowedLevels)) {
                        throw new \Exception(str_replace('{name}', $goods->name, $levelMsgTpl));
                    }
                }
            }

            // 2. 限购检查
            if ($goods->is_limit && $goods->limit_num > 0) {
                $historyNum = $purchaseHistory[$goods->id] ?? 0;
                $newTotal = $historyNum + $item['num'];

                if ($newTotal > $goods->limit_num) {
                    throw new \Exception(str_replace(
                        ['{name}', '{limit}', '{history}'],
                        [$goods->name, $goods->limit_num, $historyNum],
                        $limitMsgTpl
                    ));
                }
            }
        }
    }

    /**
     * 计算运费
     *
     * @param array $goodsMap
     * @param OrderContext $context
     * @return array ['fee' => '0.00', 'city_info' => null]
     * @throws \Exception
     */
    private function calculateShipping(array $goodsMap, OrderContext $context): array
    {
        $fee = '0.00';
        $cityInfo = null;

        if ($context->deliverType === Order::DELIVER_TYPE_EXPRESS) {
            // 快递运费
            $freightItems = $this->buildFreightItems($context->items, $goodsMap);
            $calculator = new ShippingCalculator();
            $fee = $calculator->calculateExpressFee($freightItems, $context->address);

            if ($fee === false) {
                throw new \Exception('当前地址不在配送范围内');
            }
        } elseif ($context->deliverType === Order::DELIVER_TYPE_CITY && $context->address && $context->mch) {
            // 同城配送
            $config = $context->mch->city_delivery_config
                ? Json::decode($context->mch->city_delivery_config)
                : [];

            $minOrderAmount = (float)($config['order_amount_limit']['min_order_amount'] ?? 0);

            // [关键] 地址缺经纬度 → 无法算距离/范围。以前这里 code=1 被直接丢弃,
            // 导致同城单以「运费 0、distance 0、不校验范围/起送」静默放行(资损)。
            // 现在标记 is_location_missing,随预览下发供前端警告,创建阶段(ProcessDeliveryStage)据此硬拦。
            if (empty($context->address->longitude) || empty($context->address->latitude)) {
                $cityInfo = [
                    'fee'                 => '0.00',
                    'distance'            => null,
                    'delivery_radius'     => $config['delivery_radius'] ?? 0,
                    'is_out_of_range'     => false,
                    'is_location_missing' => true,
                    'min_order_amount'    => $minOrderAmount,
                ];
                $fee = '0.00';
            } else {
                $result = CityDeliveryHelper::calculate(
                    $context->mch->longitude,
                    $context->mch->latitude,
                    $context->address->longitude,
                    $context->address->latitude,
                    $config
                );

                if ($result['code'] === 0) {
                    $cityInfo = $result['data'];
                    $cityInfo['is_location_missing'] = false;
                    // 起送金额随预览一起下发:前端据此显示警告,创建阶段(ProcessDeliveryStage)据此硬拦
                    $cityInfo['min_order_amount'] = $minOrderAmount;
                    $fee = $cityInfo['fee'] ?? '0.00';
                }
            }
        }

        return [
            'fee' => $fee,
            'city_info' => $cityInfo,
        ];
    }

    /**
     * 构建运费计算项
     *
     * @param array $items
     * @param array $goodsMap
     * @return array
     */
    private function buildFreightItems(array $items, array $goodsMap): array
    {
        $freightItems = [];

        foreach ($items as $item) {
            /** @var Goods $goods */
            $goods = $goodsMap[$item['goods_id']];

            if ($goods->express_type != 0) {
                $freightItems[] = [
                    'freight_id' => $goods->freight_id,
                    'express_type' => $goods->express_type,
                    'freight_price' => $goods->freight_price,
                    'num' => $item['num'],
                    'weight' => bcmul((string)$goods->weight, (string)$item['num'], 2),
                ];
            }
        }

        return $freightItems;
    }
}
