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

namespace app\plugins\withdraw\forms\api\recharge;

use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\Goods;
use app\plugins\withdraw\listeners\RechargeGoodsListener;
use app\plugins\withdraw\models\RechargeGoods;
use app\plugins\withdraw\models\RechargePlan;
use app\plugins\withdraw\services\AssetService;
use app\plugins\withdraw\services\RechargeService;
use Yii;

/**
 * C 端 —— 可购买的充值商品及其档位。
 *
 * 一个商品 = 一种到账资产(「余额充值」「积分充值」各一个),
 * 商品下面挂若干档位(充100送10 / 充1000送200)。
 * C 端把商品渲染成 tab,档位渲染成卡片。
 *
 * 下单时把 `goods_id` 与 `plan_id` 一起提交,后端会校验档位确实属于该商品。
 */
class PlanListForm extends BaseModel
{
    /** 只看某个资产的商品（可选，如只想充积分） */
    public $asset_type;

    public function rules()
    {
        return [
            [['asset_type'], 'string', 'max' => 32],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId   = (int)Yii::$app->mall->id;
        $userId   = (int)UserHelper::getMyId();
        $userType = RechargeService::userTypeOf($userId);

        // 1. 在架的充值商品
        $goodsList = Goods::find()
            ->where([
                'mall_id'   => $mallId,
                'type'      => RechargeGoodsListener::GOODS_TYPE,
                'status'    => Goods::STATUS_UP,
                'is_delete' => 0,
            ])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        if (!$goodsList) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功',
                ['list' => []] + self::emptyHint('unconfigured'));
        }

        $goodsIds = array_map(fn($g) => (int)$g->id, $goodsList);

        // 2. 批量取配置与档位（剿灭 N+1）
        $cfgMap = RechargeGoods::find()
            ->where(['goods_id' => $goodsIds, 'mall_id' => $mallId, 'is_delete' => 0])
            ->indexBy('goods_id')->all();

        $planRows = RechargePlan::find()
            ->where([
                'goods_id'  => $goodsIds,
                'mall_id'   => $mallId,
                'status'    => RechargePlan::STATUS_ON,
                'is_delete' => 0,
            ])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $plansByGoods = [];
        foreach ($planRows as $p) {
            $plansByGoods[(int)$p->goods_id][] = $p;
        }

        // 3. 组装
        //
        // 顺带统计「为什么一个都没剩」。空列表在 C 端只会变成一句提示,
        // 而原先那句写死的「请联系管理员在后台配置」在身份不匹配时是**误导** ——
        // 配置明明是全的,运营会去后台翻半天(bonnie 上真实发生过:四个档位
        // 都只勾了「普通」,用商户号点进来就是这句话)。
        $skipped = ['no_asset' => 0, 'no_plan' => 0, 'identity' => 0];
        $list = [];
        foreach ($goodsList as $g) {
            $gid = (int)$g->id;
            $cfg = $cfgMap[$gid] ?? null;

            // 没配到账账户 / 账户已停用的商品直接不下发,别让用户充进一个不存在的钱包
            if (!$cfg || !AssetService::isValid((string)$cfg->asset_type)) {
                $skipped['no_asset']++;
                continue;
            }
            if ($this->asset_type && $cfg->asset_type !== $this->asset_type) {
                continue;
            }

            $rawPlans = $plansByGoods[$gid] ?? [];
            $plans = [];
            foreach ($rawPlans as $p) {
                // 用户类型不匹配的档位不下发,前端不需要知道有这一档
                if (!$p->allowsUserType($userType)) {
                    continue;
                }
                $plans[] = [
                    'id'          => (int)$p->id,
                    'title'       => (string)$p->title,
                    'pay_amount'  => (string)$p->pay_amount,
                    'gain_amount' => (string)$p->gain_amount,
                    'gift_amount' => (string)$p->gift_amount,
                    'total_gain'  => $p->totalGain(),
                    'has_gift'    => bccomp((string)$p->gift_amount, '0', 2) > 0,
                    // 分期赠品预览。C 端选中档位后在下方展开成"赠品分 N 期",
                    // 每期显示送什么 + 什么时候到 —— 不展示的话分期就只剩"少送点"的坏处
                    'gift_periods' => \app\plugins\withdraw\services\RechargeGiftService::previewOf(
                        (int)$p->id, (string)$cfg->asset_type
                    ),
                ];
            }
            if (!$plans) {
                // 分清两件事:这个商品本来就没配档位,还是配了但都不对当前身份开放
                $skipped[$rawPlans ? 'identity' : 'no_plan']++;
                continue;
            }

            $list[] = [
                'goods_id'    => $gid,
                'name'        => (string)$g->name,
                'cover_pic'   => (string)$g->cover_pic,
                'asset_type'  => (string)$cfg->asset_type,
                'asset_label' => $cfg->assetLabel(),
                'remark'      => (string)$cfg->remark,
                'plans'       => $plans,
            ];
        }

        $extra = $list ? [] : self::emptyHint(
            // 只要有商品是被身份挡掉的,就按身份提示 —— 那是用户能理解、
            // 也是运营最该先看的原因
            $skipped['identity'] > 0 ? 'identity' : 'unconfigured'
        );

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $list] + $extra);
    }

    /**
     * 列表为空时给 C 端一句说得通的话。
     *
     * 文案放后端,是因为「为什么空」只有后端知道 —— 前端拿到的就是个空数组,
     * 自己判不出来是没配置还是身份不符。
     *
     * @param string $reason unconfigured=还没配 / identity=配了但当前身份买不了
     * @return array
     */
    private static function emptyHint(string $reason): array
    {
        $map = [
            'identity' => [
                'title' => '当前账号暂不支持充值',
                'sub'   => '这些充值档位仅对部分用户开放，如需开通请联系客服',
            ],
            'unconfigured' => [
                'title' => '暂无可用的充值档位',
                'sub'   => '请联系管理员在后台配置',
            ],
        ];
        $hint = $map[$reason] ?? $map['unconfigured'];
        return [
            'empty_reason' => $reason,
            'empty_title'  => $hint['title'],
            'empty_sub'    => $hint['sub'],
        ];
    }
}
