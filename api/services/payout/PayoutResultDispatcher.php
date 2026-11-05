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

namespace app\services\payout;

use Yii;

/**
 * 打款结果统一回流分发器。
 *
 * 网关只负责"打款"，结果（同步返回 / 异步回调 / 主动轮询）统一交给本分发器，
 * 由它按 source_type（源单模型 FQCN）路由到实现 HandlesPayoutResult 的业务模型，
 * 各业务更新自己的单。—— 网关全程不知道、也不需要知道调用方是谁。
 *
 * source_type 非类名（历史遗留的字符串标签等）或未实现 HandlesPayoutResult 时，
 * 一律不接管、返回 false，由调用方自行处理 —— 不抛异常,保证未知来源不会打断打款回流。
 */
class PayoutResultDispatcher
{
    /**
     * @param string $sourceType 源单类型：约定为源单模型 FQCN（如 app\plugins\withdraw\models\WithdrawOrder）
     * @param int    $sourceId   源单ID
     * @param array  $result     规范化结果 ['status'=>'success|failed|doing','payout_no'=>?,'message'=>?]
     * @return bool 是否被统一处理器接管（false=未知类型 / 未实现契约，调用方自处理）
     */
    public static function dispatch(string $sourceType, int $sourceId, array $result): bool
    {
        if ($sourceType === '' || !class_exists($sourceType)) {
            // 非模型类(历史字符串标签等)：不接管,交由调用方自处理
            Yii::info("[PayoutDispatcher] source_type '{$sourceType}' 非模型类，跳过（调用方自处理）", 'payout');
            return false;
        }
        if (!is_subclass_of($sourceType, HandlesPayoutResult::class)) {
            Yii::warning("[PayoutDispatcher] {$sourceType} 未实现 HandlesPayoutResult，无法统一回流", 'payout');
            return false;
        }

        try {
            /** @var HandlesPayoutResult $sourceType */
            $sourceType::onPayoutResult($sourceId, $result);
            return true;
        } catch (\Throwable $e) {
            Yii::error("[PayoutDispatcher] 回流处理异常 {$sourceType}#{$sourceId}：" . $e->getMessage(), 'payout');
            return false;
        }
    }
}
