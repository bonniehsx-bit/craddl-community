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

namespace app\services\order\pipeline\stages\creation;

use app\models\Attr;
use app\models\Goods;
use app\models\UserCoupon;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;

class LockResourcesStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        $goodsList = $payload->data['goods_list'] ?? [];
        $userCouponId = (int)($payload->data['user_coupon_id'] ?? 0);
        $isUseIntegral = !empty($payload->data['is_use_integral']) ? 1 : 0;

        $goodsIds = array_column($goodsList, 'goods_id');
        $attrIds = array_column($goodsList, 'goods_attr_id');

        // 去重并排序，防止死锁
        $goodsIds = array_unique($goodsIds);
        sort($goodsIds);

        // 锁商品
        // 限定下单人所在商城:商品 ID 来自客户端,不限定的话能拿别的商城的商品下单
        $payload->goodsMap = Goods::find()
            ->where(['id' => $goodsIds, 'mall_id' => (int)$payload->mall_id])
            ->indexBy('id')
            ->forUpdate()
            ->all();

        // 锁规格
        if (!empty($attrIds)) {
            $attrIds = array_unique(array_filter($attrIds));
            if (!empty($attrIds)) {
                sort($attrIds);
                $payload->attrMap = Attr::find()
                    ->where(['id' => $attrIds])
                    ->indexBy('id')
                    ->forUpdate()
                    ->all();
            }
        }
        // 规格必须属于同一行的商品(所有订单类型都经过这里,插件自己算价的也拦得住)
        foreach ($goodsList as $item) {
            $attrId = (int)($item['goods_attr_id'] ?? 0);
            if ($attrId > 0) {
                $attr = $payload->attrMap[$attrId] ?? null;
                if (!$attr || (int)$attr->goods_id !== (int)($item['goods_id'] ?? 0)) {
                    throw new \Exception('商品规格不存在或已变更,请刷新后重新选择');
                }
            }
        }

        // 锁优惠券:明确指定的券;没指定(0)但预览自动选了券并且真的减了钱,锁的是预览选中的那张
        //(以前只看请求原值,0 时减了钱却不锁不核销,同一张券能无限次用)
        if ($userCouponId <= 0 && $userCouponId !== -1 && (float)($payload->previewData['coupon_cut_price'] ?? 0) > 0) {
            $userCouponId = (int)($payload->previewData['user_coupon_id'] ?? 0);
        }
        if ($userCouponId > 0) {
            $payload->selectedCoupon = UserCoupon::find()
                ->where(['id' => $userCouponId, 'user_id' => $payload->user->id, 'status' => 0])
                ->forUpdate()
                ->one();
            if (!$payload->selectedCoupon) {
                throw new \Exception(\app\helpers\NounHelper::parse('coupon') . '已被使用或失效');
            }
        }

        // 检查积分
        if ($isUseIntegral == 1 && ($payload->previewData['total_use_integral'] ?? 0) > 0) {
            $payload->user->refresh();
            if ($payload->user->integral < $payload->previewData['total_use_integral']) {
                throw new \Exception('用户' . \app\helpers\NounHelper::parse('integral') . '不足');
            }
        }

        return $next($payload);
    }
}
