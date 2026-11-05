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

namespace app\plugins\mch\forms\mall;

// [修改] 命名空间改为 mall
use app\core\ApiCode;
use app\plugins\mch\forms\admin\statistics\DashboardForm as MchDashboardFormBase;
use Yii;

/**
 * [新增] 平台后台用于查询单个商户营业数据的 Form
 */
class MchDashboardForm extends MchDashboardFormBase // [核心] 继承自商家后台的 DashboardForm
{
    public $mch_id; // [新增] 接收外部传入的商户ID

    public function rules()
    {
        // 合并父类的规则，并增加对 mch_id 的验证
        return array_merge(parent::rules(), [
            [['mch_id'], 'required'],
            [['mch_id'], 'integer'],
        ]);
    }

    /**
     * [重写] 重写父类的 getStats 方法
     */
    public function getStats()
    {
        // 先进行自身的验证 (主要是 mch_id)
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // [核心修改] 不再从 Yii::$app->mch->id 获取 mchId，而是直接使用传入的 mch_id
        $mchId = (int)$this->mch_id;

        // [越权拦截] mch_id 是从 GET 直接透传进来的($form->attributes = $this->request->get()),
        // 而下面三个统计方法只按 mch_id 过滤、不带 mall_id —— 不校验归属的话,A 商城的运营
        // 只要把别的商城的商户 id 填进 URL,就能读到对方的营业额、订单积压和商品数(跨租户泄露)。
        // 商家自己那条链路由 MchFilter 保证归属,平台这条链路必须在这里自己校验。
        $belongsToCurrentMall = \app\plugins\mch\models\Mch::find()
            ->where(['id' => $mchId, 'mall_id' => \Yii::$app->mall->id, 'is_delete' => 0])
            ->exists();
        if (!$belongsToCurrentMall) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在或不属于当前商城');
        }

        // --- 后续逻辑完全复用父类 ---
        $revenueStats = $this->getRevenueStatistics($mchId);
        $orderStats = $this->getOrderStatistics($mchId);
        $goodsStats = $this->getGoodsStatistics($mchId);

        $data = [
            'revenueStats' => $revenueStats,
            'orderStats'   => $orderStats,
            'goodsStats'   => $goodsStats,
        ];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * [重写] 父类的 getTimeRange 方法，使其变为 public
     * 这样我们可以在控制器中直接调用
     * 注意：如果父类的此方法已经是 public，则无需重写
     */
    public function getTimeRange()
    {
        return parent::getTimeRange();
    }
}