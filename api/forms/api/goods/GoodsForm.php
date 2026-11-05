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

namespace app\forms\api\goods;

use app\services\goods\StandaloneGoodsGuard;
use app\core\ApiCode;
use app\helpers\JobHelper;
use app\helpers\StringHelper;
use app\helpers\UserHelper;
use app\jobs\goods\GoodsFootMarkJob;
use app\models\Attr;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsComment;
use app\models\GoodsFavorite;
use app\models\Order;
use app\models\User;
use Yii;
use yii\helpers\Json;

class GoodsForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'integer'],
        ];
    }

    /**
     * 详情页「预计获得 N 积分」。
     *
     * 与 NormalOrderHandler::… 的发放算法保持同一口径 ——
     *   type=1(按比例):(int) (实付价 × 百分比 / 100),取整截断
     *   type=0(固定值):每件几分,详情页按 1 件预估
     * 改这里必须同步看那边,两处算法一旦分叉,用户看到的和到账的就对不上。
     *
     * @param Goods  $goods
     * @param string $showPrice 详情页展示价(已折算会员价),对应下单时的 pay_price
     */
    private function calcExpectedIntegral($goods, $showPrice)
    {
        $send = (string)$goods->send_integral;
        if (bccomp($send, '0', 2) <= 0) {
            return 0;
        }
        if ((int)$goods->send_integral_type === 1) {
            $price = (string)($showPrice === null || $showPrice === '' ? '0' : $showPrice);
            if (bccomp($price, '0', 2) <= 0) {
                return 0;
            }
            return (int)bcdiv(bcmul($price, $send, 2), '100', 0);
        }
        return (int)$send;
    }

    public function search()
    {
        // id 不合法(空、undefined、被截断的分享链接……)时不要把框架生成的
        // 「商品ID必须是整数」透出去 —— 前端是直接 showModal(res.msg) 的,
        // 用户看到的就是这句技术黑话。对用户来说「链接坏了」和「商品没了」是同一件事,
        // 统一给同一句人话。
        if (!$this->validate() || !$this->id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }

        $goods = Goods::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }
        if ($goods->status == 0) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品已下架');
        }
        // 插件专属商品(来信、充值……)不在商城通用详情页售卖:有专属入口的让前端跳过去
        // (兜住推广 / 分享出去的旧链接),没有入口的(收银台、账单缴费占位商品)按商品不存在处理
        if (StandaloneGoodsGuard::isStandalone($goods)) {
            $entry = StandaloneGoodsGuard::entryPath($goods);
            return $entry
                ? $this->apiResponse(ApiCode::CODE_FAIL, '正在前往购买页', ['entry_path' => $entry])
                : $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }

        $goods_info = $goods->info;

        // 1. 确定当前用户等级
        if (!\Yii::$app->user->isGuest) {
            JobHelper::push(new GoodsFootMarkJob(['goods_id' => $goods->id, 'user_id' => UserHelper::getMyId(), 'mall_id' => \Yii::$app->mall->id]), 3);
            $level = \Yii::$app->user->identity->level_id;
        } else {
            $level = 0; // 游客
        }

        $base_price = $goods->price;

        // 2. 计算好评率
        $commentStats = GoodsComment::find()
            ->where(['goods_id' => $this->id, 'is_delete' => 0, 'is_second' => 0])
            ->select([
                'total_count' => 'COUNT(id)',
                'good_count' => "SUM(CASE WHEN grade_level >= 4 THEN 1 ELSE 0 END)",
            ])
            ->asArray()
            ->one();

        $goodRatingRate = '100.00';
        $totalCommentCount = (int)($commentStats['total_count'] ?? 0);
        $goodCommentCount = (int)($commentStats['good_count'] ?? 0);

        if ($totalCommentCount > 0) {
            $goodRatingRate = bcmul(bcdiv($goodCommentCount, $totalCommentCount, 4), '100', 2);
        }

        // 3. 组装基础信息
        $goodsArr = $goods->toArray();
        // [核心] 计算主商品显示的会员价 (用于列表展示或无规格展示)
        $goodsArr['price'] = $goods->applyLevelDiscountToPrice($base_price, $level);
        $goodsArr['good_rating_rate'] = $goodRatingRate;
        // 详情页价格下面那排服务标签:上传商品时勾的(平台/商家各自维护标签库),停用和删掉的不出
        $goodsArr['service_tags'] = \app\models\GoodsServiceTag::namesOfGoods($goods);
        $goodsArr['total_comment_count'] = $totalCommentCount;
        // 4. 权限检查
        if ($goods_info['permission']) {
            $permission = Json::decode($goods_info['permission']);
            if ($permission && !empty($permission['explode_is_level'])) {
                if (!in_array($level, $permission['explode_level_ids'])) {
                    return $this->apiResponse(ApiCode::CODE_FAIL, '您无权查看该商品!');
                }
            }
        }

        if ($goods_info['pic_list']) {
            $goods_info['pic_list'] = Json::decode($goods_info['pic_list']);
        } else {
            $goods_info['pic_list'] = [];
        }

        // 5. 处理规格数据 (Specs & SKU)
        $specs_data = [
            'spec_groups' => [],
            'sku_list' => [],
        ];

        if ($goods->is_attr) {
            // 获取数据库原始 SKU 数据 (使用原生查询避免二次封装污染)
            $sku_list_raw = Attr::find()
                ->where(['goods_id' => $goods->id, 'is_delete' => 0])
                ->asArray()
                ->all();

            // 解析规格组模板
            if ($goods_info && !empty($goods_info['spec_template'])) {
                try {
                    $spec_groups = Json::decode($goods_info['spec_template']);
                    if (is_string($spec_groups)) {
                        $spec_groups = Json::decode($spec_groups);
                    }
                    $specs_data['spec_groups'] = is_array($spec_groups) ? $spec_groups : [];
                } catch (\Exception $e) {
                    $specs_data['spec_groups'] = [];
                }
            }

            // [核心修复] 遍历每一个 SKU，计算其会员价并重组纯净节点
            foreach ($sku_list_raw as $sku) {
                $specs = [];
                if (!empty($sku['spec_data'])) {
                    try {
                        $decoded = Json::decode($sku['spec_data']);
                        if (is_string($decoded)) {
                            $decoded = Json::decode($decoded);
                        }
                        if (is_array($decoded) && !empty($decoded)) {
                            $specs = $decoded;
                        }
                    } catch (\Exception $e) {
                    }
                }

                // ========================================================
                // 获取 SKU 原价并计算 SKU 真实的会员折扣价
                // ========================================================
                $skuOriginalPrice = (float)$sku['price'];
                $skuMemberPrice = $goods->applyLevelDiscountToPrice($skuOriginalPrice, $level);

                // 生成前端组件可能需要的 sign 签名
                $keyParts = [];
                if (!empty($specs)) {
                    foreach ($specs as $spec) {
                        if (isset($spec['value'])) {
                            $keyParts[] = $spec['value'];
                        }
                    }
                }
                sort($keyParts);
                $sign = implode(';', $keyParts);

                // ========================================================
                // 重新组装一个干净的 SKU 节点
                // ========================================================
                $cleanSku = [
                    'id' => (int)$sku['id'],
                    'goods_id' => (int)$sku['goods_id'],
                    'stock' => (int)$sku['stock'],
                    'price' => (string)$skuMemberPrice, // 前端切换规格时显示的当前高亮价格（会员价）
                    'original_price' => (string)$skuOriginalPrice, // 留给前端做灰色划线用的原价
                    'no' => $sku['no'] ?? '',
                    'weight' => (float)($sku['weight'] ?? 0),
                    'cover_pic' => $sku['cover_pic'] ?? '',
                    'specs' => $specs,
                    'sign' => $sign,
                ];

                $specs_data['sku_list'][] = $cleanSku;
            }

            // 如果 spec_template 为空但有 sku 数据，尝试自动重建规格组 (容错处理)
            if (empty($specs_data['spec_groups']) && !empty($sku_list_raw)) {
                $rebuilt_groups = [];
                $temp_values_by_group_name = [];

                foreach ($sku_list_raw as $attr) {
                    if (isset($attr['attr_name'])) {
                        $values = explode(';', $attr['attr_name']);
                        foreach ($values as $index => $value) {
                            $group_name = "规格" . ($index + 1);
                            if (!isset($rebuilt_groups[$group_name])) {
                                $rebuilt_groups[$group_name] = ['name' => $group_name, 'values' => []];
                            }
                            $temp_values_by_group_name[$group_name][] = trim($value);
                        }
                    }
                }

                foreach ($rebuilt_groups as &$group) {
                    if (isset($temp_values_by_group_name[$group['name']])) {
                        $unique_values = array_unique($temp_values_by_group_name[$group['name']]);
                        foreach ($unique_values as $val) {
                            $group['values'][] = ['name' => $val];
                        }
                    }
                }
                $specs_data['spec_groups'] = array_values($rebuilt_groups);
            }
        }

        // 6. 其他关联数据
        $is_favorite = 0;
        if (!\Yii::$app->user->isGuest) {
            $favorite = GoodsFavorite::findOne(['user_id' => \Yii::$app->user->identity->id, 'goods_id' => $goods->id, 'is_delete' => 0]);
            if ($favorite) {
                $is_favorite = 1;
            }
        }

        $comment = GoodsComment::find()
            ->alias('c')
            ->andWhere(['c.goods_id' => $goods->id, 'c.is_delete' => 0])
            ->leftJoin(['u' => User::tableName()], 'u.id=c.user_id')
            ->select('c.*,u.nickname,u.avatar_url')
            ->orderBy('c.id DESC')
            ->asArray()->one();
        if ($comment) {
            $comment['nickname'] = StringHelper::replaceMiddleKeepEnds($comment['nickname']);
            $comment['pic_list'] = $comment['pic_list'] ? Json::decode($comment['pic_list']) : [];
        }

        // 7. 营销奖励计算 (首单补贴等)
        // send_integral 的含义由 send_integral_type 决定:0=每件固定送几分,1=**百分比**。
        // 这里原来不分类型直接把配置值当积分数下发 —— 按比例的商品配 5(意思是 5%),
        // 详情页就显示「预计获得 5 积分」,而 288 元的单子实际到账 14 分。
        //
        // 口径必须跟真正发放的 NormalOrderHandler 对齐(那边是 pay_price × % 再取整截断),
        // 否则「预计」和「实际」对不上,比不显示更糟。
        // 价格用 $goodsArr['price'] 而不是 $goods->price:前者是会员价折算后的**用户实付价**,
        // 发放时按 pay_price 算,预估自然也该按它。
        $rewards = [
            'integral' => $this->calcExpectedIntegral($goods, $goodsArr['price']),
            'first_order_discount' => '0.00',
            'first_order_discount_total_stock' => 0,
        ];

        $mch = $goods->mch;
        if ($goods->mch_id > 0 && !\Yii::$app->user->isGuest) {
            if ($mch) {
                $now = time();
                if ($mch->first_order_discount_start_time && strtotime($mch->first_order_discount_start_time) < $now && strtotime($mch->first_order_discount_end_time) > $now) {
                    $userId = \Yii::$app->user->id;
                    $isFirstOrder = !Order::find()->where(['user_id' => $userId, 'pay_status' => Order::PAY_STATUS_PAID])->exists();
                    if ($mch->is_first_order_discount_enabled == 1 && $isFirstOrder) {
                        $rewards['first_order_discount'] = (float)$mch->first_order_discount_amount;
                        $rewards['first_order_discount_total_stock'] = $mch->first_order_discount_total_stock;
                    }
                }
            }
        }

        $goodsArr['expected_rewards'] = $rewards;

        // 8. 返回结果
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'goods' => $goodsArr,
            // 标记位：如果显示价格不等于原价，说明触发了会员价逻辑 (用于前端显示“会员价”标签)
            'is_member_price' => bccomp((string)$goodsArr['price'], (string)$base_price, 2) !== 0 ? 1 : 0,
            'comment' => $comment,
            'is_favorite' => $is_favorite,
            'info' => $goods_info,
            'specs_data' => $specs_data
        ]);
    }
}
