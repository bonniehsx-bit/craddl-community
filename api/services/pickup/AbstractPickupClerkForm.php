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

namespace app\services\pickup;

use app\core\ApiCode;
use app\helpers\LockHelper;
use app\models\BaseModel;
use app\plugins\clerk\models\ClerkLog;
use app\services\verify\VerifyContext;
use Yii;

/**
 * 插件云仓提货单——到店自提「扫码核销」C端通用底座。
 *
 * 背景:xwy/brand 等插件各有自己的提货单表(*PickupOrder,状态机逐字节相同),
 * 到店自提核销走"买家出示码二维码 → 门店微信扫一扫打开共享落地页 → 确认核销"。
 * 这套逻辑原在 xwy 内联一份、brand 缺失(死链),现收口到此底座,子类只声明模型类。
 *
 * 不走平台核销中心(PlatformVerifyForm):那套只认核心 Order + 平台核销员(ClerkUser);
 * 而插件提货单是自有表、执行人是门店/渠道服务商,到店自提属线下信任,登录即可核销,
 * 核销人记入 clerk_id/clerk_name 备查。核销即完成(与商家后台 send() 自提分支一致)。
 *
 * 子类须提供的提货单模型须含:常量 STATUS_UNPAID/WAIT_SEND/SENT/COMPLETE、
 * DELIVER_TYPE_PICKUP;方法 getStatusText();关系 detail;列 mall_id/pickup_code/
 * deliver_type/status/clerk_id/clerk_name/name/mobile/order_no/confirm_at/send_at/
 * express_company/express_no/created_at/is_delete。
 */
abstract class AbstractPickupClerkForm extends BaseModel
{
    public $order_id;
    public $pickup_code;

    /** 具体提货单模型类名(如 XwyPickupOrder::class) */
    abstract protected function pickupOrderModelClass(): string;

    /** 业务类型标识,回写给前端(如 'xwy' / 'brand') */
    abstract protected function bizType(): string;

    /** 并发锁 key 前缀(区分插件,如 'xwy_pickup' / 'brand_pickup') */
    abstract protected function lockPrefix(): string;

    public function rules()
    {
        return [
            [['order_id'], 'integer'],
            [['pickup_code'], 'string'],
        ];
    }

    /** 按核销码或订单ID定位本商城的提货单 */
    protected function findOrder()
    {
        $model = $this->pickupOrderModelClass(); // class-string,下面用 $model::find()/常量
        $query = $model::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'is_delete' => 0,
        ]);

        if ($this->pickup_code) {
            $query->andWhere(['pickup_code' => $this->pickup_code]);
        } elseif ($this->order_id) {
            $query->andWhere(['id' => $this->order_id]);
        } else {
            return null;
        }

        return $query->with(['detail'])->one();
    }

    /** 核销落地页——获取订单详情 */
    public function getInfo()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $model = $this->pickupOrderModelClass();
        $order = $this->findOrder();
        if (!$order) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单不存在或核销码无效'];
        }
        if ($order->deliver_type != $model::DELIVER_TYPE_PICKUP) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '该订单非到店自提订单'];
        }

        $goodsList = [];
        foreach ($order->detail as $detail) {
            $attrStr = '';
            $attrInfo = json_decode($detail->attr_info, true);
            if (!empty($attrInfo) && is_array($attrInfo)) {
                $attrList = $attrInfo['attr_list'] ?? $attrInfo;
                foreach ($attrList as $attr) {
                    if (isset($attr['attr_group_name'], $attr['attr_name'])) {
                        $attrStr .= $attr['attr_group_name'] . ':' . $attr['attr_name'] . ' ';
                    }
                }
            }
            $goodsList[] = [
                'goods_name' => $detail->goods_name,
                'cover_pic' => $detail->cover_pic,
                'attr_info' => trim($attrStr),
                'num' => $detail->num,
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'type' => $this->bizType(),
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status,
                'status_text' => $order->getStatusText(),
                'pickup_code' => $order->pickup_code,
                'name' => $order->name,
                'mobile' => $order->mobile,
                'clerk_name' => $order->clerk_name,
                'confirm_at' => $order->confirm_at ? date('Y-m-d H:i', $order->confirm_at) : '',
                'send_at' => $order->send_at ? date('Y-m-d H:i', $order->send_at) : '',
                'created_at' => date('Y-m-d H:i', $order->created_at),
                'goods_list' => $goodsList,
            ],
        ];
    }

    /** 执行核销 */
    public function verification()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $model = $this->pickupOrderModelClass();
        $order = $this->findOrder();
        if (!$order) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单不存在或核销码无效'];
        }
        if ($order->deliver_type != $model::DELIVER_TYPE_PICKUP) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '该订单非到店自提订单'];
        }

        // [并发防重] 同一提货单串行化,避免两人同刻各扫一次造成重复核销
        $lockKey = $this->lockPrefix() . '_verify_' . Yii::$app->mall->id . '_' . $order->id;
        $lockToken = LockHelper::lock($lockKey, 10);
        if (!$lockToken) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '该订单正在核销中,请勿重复提交'];
        }

        try {
            if ($order->status != $model::STATUS_WAIT_SEND) {
                if (in_array($order->status, [$model::STATUS_SENT, $model::STATUS_COMPLETE])) {
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单已核销,请勿重复操作'];
                }
                if ($order->status == $model::STATUS_UNPAID) {
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单未支付,无法核销'];
                }
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '订单状态异常,无法核销'];
            }

            $user = Yii::$app->user->identity;
            $order->clerk_id = $user->id;
            $order->clerk_name = $user->nickname ?? '';
            // 自提无需物流,核销即完成(与商家后台 PickupOrderForm::send 自提分支一致)
            $order->express_company = '无需物流';
            $order->express_no = '-';
            $order->send_at = time();
            $order->status = $model::STATUS_COMPLETE;
            $order->confirm_at = time();

            if (!$order->save()) {
                return $this->responseErrorInfo($order);
            }

            // 统一核销台账留痕(guarded,写失败不影响主流程)
            ClerkLog::record(
                new VerifyContext(Yii::$app->mall->id, 0, (int)$user->id, $order->clerk_name, 0, 'plugin_landing'),
                [
                    'biz_type' => $this->bizType(),
                    'biz_type_name' => '云仓提货自提',
                    'code' => (string)$order->pickup_code,
                    'order_no' => (string)$order->order_no,
                    'result' => 1,
                ]
            );

            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '核销成功'];
        } finally {
            LockHelper::unlock($lockKey, $lockToken);
        }
    }
}
