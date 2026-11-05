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
 * Class BossFilter
 * @package app\plugins\mch\filters
 *
 * [优化版 V3.1] 商家后台核心安全过滤器
 * 1. 验证用户登录。
 * 2. 智能解析 mch_id (优先取Header/Get，无参数则自动查找名下店铺)。
 * 3. 验证 商户(Mch) 和 员工关系(Clerk) 的有效性。
 * 4. 注入全局上下文。
 */
class BossFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        // 1. 登录检查
        if (Yii::$app->user->isGuest) {
            return $this->denyAccess('用户未登录', ApiCode::CODE_NOT_LOGIN);
        }

        $userId = Yii::$app->user->id;

        // 2. 确定 mch_id (优先从请求获取)
        $mchId = Yii::$app->request->headers->get('X-Mch-Id') ?: Yii::$app->request->get('mch_id');

        // [多租户隔离] 商户上下文必须限定在当前商城内(防止前端残留的跨商城 current_mch_id 串号)。
        $mallId = (int)(Yii::$app->mall->id ?? 0);
        $mallCond = $mallId ? ['mall_id' => $mallId] : [];

        // 3. 准备上下文数据
        $mch = null;
        $clerkRelation = null;

        // 4. 场景分流处理
        if ($mchId) {
            // A. 指定了 mch_id：验证该店铺是否存在，且当前用户是否为该店关联人员（店员或店长）
            $mch = Mch::findOne(array_merge(['id' => $mchId, 'is_delete' => 0, 'status' => 1], $mallCond));
            if (!$mch) {
                return $this->denyAccess('商户不存在或已被禁用', ApiCode::CODE_FAIL);
            }

            // 查询关联关系
            $clerkRelation = MchClerk::findOne([
                'user_id' => $userId,
                'mch_id'  => $mchId,
                'status'  => 1,
                'is_delete' => 0
            ]);
        } else {
            // B. 未指定 mch_id：尝试查找用户拥有的默认店铺（兼容单店模式不传ID的情况）
            // 逻辑：先找 Mch 表看是否是店主
            $mch = Mch::findOne(array_merge(['user_id' => $userId, 'is_delete' => 0, 'status' => 1], $mallCond));

            if ($mch) {
                $mchId = $mch->id;
                // 即使是店主，通常也会在 mch_clerk 表有一条 is_manager=1 的记录，这里做统一查询校验
                $clerkRelation = MchClerk::findOne([
                    'user_id' => $userId,
                    'mch_id'  => $mchId,
                    'status'  => 1,
                    'is_delete' => 0
                ]);
            } else {
                // 如果名下没有店，查找他是否是某家店的店员（可选逻辑，取第一家关联的店）
                // 如果业务要求必须传 mch_id 才能访问店员功能，这里可以直接报错
                $clerkQuery = MchClerk::find()
                    ->alias('mc')
                    ->joinWith(['mch m']) // 需要关联查询确保店铺也是正常的
                    ->where([
                        'mc.user_id' => $userId,
                        'mc.status' => 1,
                        'mc.is_delete' => 0,
                        'm.status' => 1,
                        'm.is_delete' => 0
                    ]);
                if ($mallId) {
                    $clerkQuery->andWhere(['m.mall_id' => $mallId]);
                }
                $clerkRelation = $clerkQuery->one();

                if ($clerkRelation) {
                    $mch = $clerkRelation->mch; // 获取关联的店铺
                    $mchId = $mch->id;
                }
            }
        }

        // 5. 最终校验
        if (!$mch || !$clerkRelation) {
            return $this->denyAccess('您不是该商铺的店长或店员，请核对商户ID或去申请入驻', ApiCode::TO_APPLY_MCH);
        }

        // 6. 组装角色对象
        $mchRole = new MchRole([
            'is_manager'    => (bool)$clerkRelation->is_manager,
            // 注意：这里可能需要确认 is_manager 为 1 时是否一定是店长，还是说只要是管理员权限
            'is_clerk'      => !(bool)$clerkRelation->is_manager,
            'clerkRelation' => $clerkRelation
        ]);

        // 7. 注入全局
        Yii::$app->set('mch', $mch);
        Yii::$app->set('mchRole', $mchRole);

        return parent::beforeAction($action);
    }

    protected function denyAccess($message, $code = ApiCode::TO_APPLY_MCH)
    {
        Yii::$app->response->data = [
            'code' => $code,
            'msg'  => $message,
        ];
        return false;
    }
}