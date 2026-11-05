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

namespace app\plugins\mch\filters;

use app\core\ApiCode;
use app\plugins\mch\components\MchRole;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Yii;
use yii\base\ActionFilter;

/**
 * Class MchFilter
 * @package app\plugins\mch\filters
 *
 * [优化版 V3.1] 商家后台核心安全过滤器
 */
class MchFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        // 0. 登录检查
        if (Yii::$app->user->isGuest) {
            return $this->denyAccess('用户未登录', ApiCode::CODE_NOT_LOGIN);
        }
        $user = Yii::$app->user->identity;

        // 1. 获取 mch_id (优先 Header/GET，否则自动查找)
        $mchId = Yii::$app->request->headers->get('X-Mch-Id') ?: Yii::$app->request->get('mch_id');

        // [多租户隔离] 商户上下文必须限定在当前商城内。
        // 前端 current_mch_id 是全局缓存,切商城时可能残留别的商城的店铺ID(见 uniapp 状态管理),
        // 这里按 m.mall_id 兜底,防止跨商城把 A 商城的店铺注入到 B 商城的请求上下文。
        $mallId = (int)(Yii::$app->mall->id ?? 0);

        // 构建查询构建器：查询当前用户的有效店员记录，并预加载商户信息
        // 前提：MchClerk 模型中必须定义了 getMch() 关联方法
        $query = MchClerk::find()
            ->alias('mc')
            ->joinWith(['mch m']) // 关联查询 Mch 表，别名为 m
            ->where([
                'mc.user_id' => $user->id,
                'mc.status' => 1,
                'mc.is_delete' => 0,
                'm.status' => 1,      // 确保商户也是启用状态
                'm.is_delete' => 0
            ]);

        if ($mallId) {
            $query->andWhere(['m.mall_id' => $mallId]);
        }

        if ($mchId) {
            // 场景A: 指定了 mch_id，精确查询
            $query->andWhere(['mc.mch_id' => $mchId]);
        } else {
            // 场景B: 未指定，自动查找（通常取最近加入的或 ID 最小的）
            // 优化：去掉了 is_manager=1 的限制，允许普通店员自动进入默认店铺
            $query->orderBy(['mc.id' => SORT_ASC]);
        }

        /** @var MchClerk|null $clerkRelation */
        $clerkRelation = $query->one();

        // 2. 综合验证
        if (!$clerkRelation) {
            // 区分报错信息（为了通过审核或用户体验，有时可以模糊处理）
            if ($mchId) {
                return $this->denyAccess('您不是该店铺的成员或店铺状态异常');
            } else {
                return $this->denyAccess('您尚未加入任何商户，请先申请入驻', ApiCode::TO_APPLY_MCH);
            }
        }

        // 获取关联的 Mch 对象 (因为用了 joinWith/with，这里不会再次查询数据库)
        $mch = $clerkRelation->mch;

        // 3. 注入上下文
        $mchRole = new MchRole([
            'is_manager' => (bool)$clerkRelation->is_manager,
            'is_clerk' => !(bool)$clerkRelation->is_manager,
            'clerkRelation' => $clerkRelation
        ]);

        Yii::$app->set('mch', $mch);
        Yii::$app->set('mchRole', $mchRole);

        // 4. [核心权限] 角色权限检查
        // 检查是否被禁止访问
        if (!$this->checkPermission($action, $mchRole)) {
            return $this->denyAccess('权限不足：只有店长可执行此操作');
        }

        return parent::beforeAction($action);
    }

    /**
     * 权限检查逻辑分离，保持主流程清晰
     * @param \yii\base\Action $action
     * @param MchRole $mchRole
     * @return bool
     */
    protected function checkPermission($action, $mchRole)
    {
        $controllerId = $action->controller->id;
        $actionId = $action->id;

        // 定义仅【店长】可访问的敏感区域
        // 格式: '控制器ID' => ['禁止店员访问的方法列表' | '*' 代表全部禁止]
        // 口径:与 MchActionsForm::getDefaultAllActions() 里已标 managerOnly 的功能【逐项对齐】。
        // 那边的 managerOnly 只是"不给店员显示按钮",接口本身完全没设防 —— 店员照样能直接构造请求
        // 拿到店铺资料、货款账本、待结算金额,或改配送设置、发优惠券。UI 藏了、API 没锁,等于没锁。
        // 这里只补齐"产品已经判定为店长专属"的那几项,不额外扩大范围:
        // 运费配置 / 订单管理 / 收款码进件 产品侧【没有】标 managerOnly(店员可用),故不在此锁,
        // 要不要收紧属于产品决策,不在本次修复范围内。
        $managerOnlyMap = [
            'clerk'  => '*',        // 店员管理 + 核销记录(下方白名单放行店员看自己的记录)
            'coupon' => '*',        // 优惠券管理
            'mch'    => [
                'edit',                     // 资料(店铺信息编辑)
                'finance-dashboard',        // 货款明细(账本+可提现余额)
                'pending-settlement-list',  // 待结算金额明细
                'delivery-config',          // 配送设置
            ],
        ];

        // 定义店员豁免白名单 (即使在上面的黑名单中，也允许访问)
        $clerkWhitelist = [
            'clerk/my-records', // 允许店员查看自己的记录
            'clerk/info',       // 假设有这个：店员查看自己资料
        ];

        $route = $controllerId . '/' . $actionId;

        // 如果当前用户是店长，直接放行
        if ($mchRole->is_manager) {
            return true;
        }

        // --- 以下是针对【店员】的检查 ---

        // 1. 检查白名单 (优先级最高)
        if (in_array($route, $clerkWhitelist)) {
            return true;
        }

        // 2. 检查黑名单
        if (isset($managerOnlyMap[$controllerId])) {
            $forbiddenActions = $managerOnlyMap[$controllerId];
            // 如果是 '*' 或者当前方法在禁止列表中
            if ($forbiddenActions === '*' || in_array($actionId, $forbiddenActions)) {
                return false;
            }
        }

        return true;
    }

    protected function denyAccess($message, $code = ApiCode::TO_APPLY_MCH)
    {
        Yii::$app->response->data = [
            'code' => $code,
            'msg' => $message,
        ];
        return false;
    }
}