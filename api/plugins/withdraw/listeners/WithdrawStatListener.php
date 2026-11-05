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

namespace app\plugins\withdraw\listeners;

use app\events\WithdrawStatEvent;
use app\listeners\BaseListener;
use app\plugins\withdraw\models\WithdrawOrder;
use Yii;
use yii\db\Expression;

/**
 * 提现统计应答(响应 WithdrawStatEvent::NAME)。
 *
 * 核心多处要「提现中 / 已提现 / 待审核单数」,但不该认识本插件的表。
 * 这里由插件自己应答 —— 插件卸载后核心那些位置自动回落到 0,不报错。
 *
 * 口径(与后台状态机一致):
 *   在途 = 待审核(0) + 待打款(1) + 打款中(2)
 *   已到账 = 已到账(3)
 *   已驳回(4) / 打款失败(5) / 已退回(6) 都不计入 —— 钱已退回或未出账
 *
 * 性能:三个指标用**一条**条件聚合 SQL 一次扫出,不因调用方只要其中一个而多查。
 */
class WithdrawStatListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(WithdrawStatEvent::NAME, function (WithdrawStatEvent $e) {
            try {
                // mall_id 必须是硬条件,不能走 andFilterWhere ——
                // filterWhere 遇到空值会把整个条件【丢掉】,mallId=0 时就变成跨商城合计,
                // 多商城环境下直接串场(A 商城的后台看到全平台的提现总额)。
                // userId=0 / assetType='' 是设计内的"不限",mallId=0 不是,它只可能是漏传。
                $mallId = (int) $e->mallId;
                if ($mallId <= 0) {
                    Yii::warning('[WithdrawStat] 未指定 mallId,拒绝跨商城统计', 'withdraw');
                    return; // 三个字段保持 null,调用方按 ?? 0 兜底,不会拿到别家商城的数
                }

                $q = WithdrawOrder::find()
                    ->where(['is_delete' => 0, 'mall_id' => $mallId])
                    ->andFilterWhere(['user_id' => $e->userId ?: null])
                    ->andFilterWhere(['asset_type' => $e->assetType ?: null]);

                $pendingStates = [
                    WithdrawOrder::STATUS_PENDING_AUDIT,
                    WithdrawOrder::STATUS_PENDING_PAYOUT,
                    WithdrawOrder::STATUS_PAYING,
                ];
                $pendingIn = implode(',', $pendingStates);
                $audit     = (int) WithdrawOrder::STATUS_PENDING_AUDIT;
                $done      = (int) WithdrawOrder::STATUS_SUCCESS;

                $row = $q->select([
                    'pending_amount'   => new Expression("IFNULL(SUM(CASE WHEN `status` IN ({$pendingIn}) THEN `amount` ELSE 0 END), 0)"),
                    'completed_amount' => new Expression("IFNULL(SUM(CASE WHEN `status` = {$done} THEN `amount` ELSE 0 END), 0)"),
                    'pending_count'    => new Expression("SUM(CASE WHEN `status` = {$audit} THEN 1 ELSE 0 END)"),
                ])->asArray()->one();

                // 累加而非赋值 —— 将来可能有第二个提现来源插件一起应答(见事件类注释)
                $e->addPending((float) ($row['pending_amount'] ?? 0));
                $e->addCompleted((float) ($row['completed_amount'] ?? 0));
                $e->addPendingCount((int) ($row['pending_count'] ?? 0));
            } catch (\Throwable $ex) {
                // 统计失败不该让宿主页面 500:保持 null,调用方按 ?? 0 兜底
                Yii::warning('[WithdrawStat] 统计失败: ' . $ex->getMessage(), 'withdraw');
            }
        });
    }
}
