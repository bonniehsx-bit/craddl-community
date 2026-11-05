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

namespace app\services\verify;

use app\plugins\clerk\models\ClerkLog;
use Yii;

/**
 * 统一核销台账记账门面。
 *
 * 各核销入口(通用收银台 / 商家核销 / 插件独立核销 / 提货核销)核销成功后
 * 调 record() 一次即可,无需手搓 VerifyContext、也无需记住 data 数组结构。
 *
 * 设计要点:
 *  - 身份与上下文默认从当前登录态解析(mall_id / clerk_id / clerk_name),
 *    调用方只关心"业务是什么"。这样避免了手搓 clerk_id 传错的典型坑
 *    (核销员的 user id ≠ 门店 MchClerk 行 id)。
 *  - 需要显式指定时(如后台/脚本非当前登录人操作),传对应键覆盖即可。
 *  - guarded:底层 ClerkLog::record 台账写失败绝不影响核销主流程,只记 error 日志。
 *
 * 见 docs/方案-核销体系统一治理.md。
 */
class VerifyLedgerService
{
    /**
     * 记一条统一核销台账。
     *
     * @param array $p 业务与可选覆盖字段:
     *   必填:
     *     - biz_type       string  业务类型标识(如 card_physical / normal / 插件自定义)
     *     - biz_type_name  string  业务类型中文名(报表展示)
     *     - code           string  核销码/卡号/订单号等定位码
     *   常用:
     *     - order_no       string  关联单号(便于反查)
     *     - products       array   商品/扣款快照(数组,内部自动 json)
     *     - remark         string  备注
     *     - mch_id         int     商户ID,0=平台自营
     *     - source         string  来源:platform | mch | plugin_landing
     *   可选覆盖(默认从登录态/常量取):
     *     - mall_id        int     默认 Yii::$app->mall->id
     *     - clerk_id       int     默认当前登录 user id(核销员)
     *     - clerk_name     string  默认当前登录昵称
     *     - is_manual_force int    是否强制核销(1=是)
     *     - result         int     1=成功(默认) 0=失败
     *     - fail_reason    string  失败原因(result=0 时)
     */
    public static function record(array $p): void
    {
        $mallId    = (int)($p['mall_id'] ?? (Yii::$app->mall->id ?? 0));
        $clerkId   = (int)($p['clerk_id'] ?? (Yii::$app->user->id ?? 0));
        $clerkName = (string)($p['clerk_name'] ?? (Yii::$app->user->identity->nickname ?? ''));

        $ctx = new VerifyContext(
            $mallId,
            (int)($p['mch_id'] ?? 0),
            $clerkId,
            $clerkName,
            (int)($p['is_manual_force'] ?? 0),
            (string)($p['source'] ?? '')
        );

        ClerkLog::record($ctx, [
            'biz_type'      => (string)($p['biz_type'] ?? ''),
            'biz_type_name' => (string)($p['biz_type_name'] ?? ''),
            'code'          => (string)($p['code'] ?? ''),
            'order_no'      => (string)($p['order_no'] ?? ''),
            'products'      => $p['products'] ?? null,
            'remark'        => (string)($p['remark'] ?? ''),
            'result'        => $p['result'] ?? 1,
            'fail_reason'   => (string)($p['fail_reason'] ?? ''),
        ]);
    }
}
