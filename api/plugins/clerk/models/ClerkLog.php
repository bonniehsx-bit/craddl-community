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

namespace app\plugins\clerk\models;

use app\models\BaseActiveRecord;
use app\services\verify\VerifyContext;
use Yii;

/**
 * 统一核销台账。核销治理阶段3:平台/商家/插件提货三条核销链统一留痕。
 * 与既有 MchVerifyLog / Order 反查过渡并存,读侧后续渐进切换。
 *
 * @property int $mall_id
 * @property int $mch_id
 * @property string $biz_type
 * @property string $biz_type_name
 * @property string $code
 * @property string $order_no
 * @property int $clerk_id
 * @property string $clerk_name
 * @property string $source
 * @property string $products_snapshot
 * @property string $remark
 * @property int $result
 * @property string $fail_reason
 * @property int $verify_at
 */
class ClerkLog extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_clerk_log}}';
    }

    /**
     * 统一核销留痕。**guarded:台账写入失败绝不影响核销主流程**,只记 error 日志。
     * @param VerifyContext $ctx 核销上下文(mall/mch/clerk/source)
     * @param array $data biz_type/biz_type_name/code/order_no/products(数组)/remark/result(1成0败)/fail_reason
     */
    public static function record(VerifyContext $ctx, array $data): void
    {
        try {
            $log = new self();
            $log->mall_id = $ctx->mallId;
            $log->mch_id = $ctx->mchId;
            $log->clerk_id = $ctx->clerkId ?: (Yii::$app->user->id ?? 0);
            $log->clerk_name = $ctx->clerkName ?: (Yii::$app->user->identity->nickname ?? '');
            $log->source = $ctx->source;
            $log->biz_type = (string)($data['biz_type'] ?? '');
            $log->biz_type_name = (string)($data['biz_type_name'] ?? '');
            $log->code = (string)($data['code'] ?? '');
            $log->order_no = (string)($data['order_no'] ?? '');
            $log->products_snapshot = isset($data['products']) ? json_encode($data['products'], JSON_UNESCAPED_UNICODE) : null;
            $log->remark = (string)($data['remark'] ?? '');
            $log->result = !empty($data['result']) || !isset($data['result']) ? 1 : 0;
            $log->fail_reason = (string)($data['fail_reason'] ?? '');
            $log->verify_at = time();
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::error('ClerkLog record 失败: ' . $e->getMessage());
        }
    }
}
