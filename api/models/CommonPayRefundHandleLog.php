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

namespace app\models;

/**
 * 退款人工处理记录:「退款失败」处理台上的每一次操作都记一条,只追加、不修改。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $refund_id 退款流水 common_pay_refund.id
 * @property string $action query=查询结果 retry=重新发起 mark_refunded=登记已退款 resync=补做后续处理
 * @property int $result 1=成功 0=没成功(如查询结果仍未退、后续处理报错)
 * @property int $status_before 操作前的退款状态
 * @property int $status_after 操作后的退款状态
 * @property int $operator_id 操作的后台账号 admin.id
 * @property string $operator_name 操作人名称快照
 * @property string $remark 处理说明
 * @property string $voucher 凭证图片地址
 * @property string|null $detail 网关返回等原始信息
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 */
class CommonPayRefundHandleLog extends BaseActiveRecord
{
    const ACTION_QUERY = 'query';
    const ACTION_RETRY = 'retry';
    const ACTION_MARK_REFUNDED = 'mark_refunded';
    const ACTION_RESYNC = 'resync';

    const ACTION_TEXT = [
        self::ACTION_QUERY => '查询结果',
        self::ACTION_RETRY => '重新发起',
        self::ACTION_MARK_REFUNDED => '登记已退款',
        self::ACTION_RESYNC => '补做后续处理',
    ];

    public static function tableName()
    {
        return '{{%common_pay_refund_handle_log}}';
    }

    /**
     * 一批退款各自的处理记录,按先后排:[退款 id => [记录, …]]。
     * 「退款失败」处理台和「退款记录」页共用 —— 处理完的退款会离开处理台,之后只能在退款记录里看到这些记录。
     */
    public static function listByRefund(array $refundIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $refundIds))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (self::find()->where(['refund_id' => $ids, 'is_delete' => 0])->orderBy(['id' => SORT_ASC])->asArray()->all() as $l) {
            $out[(int)$l['refund_id']][] = [
                'action' => $l['action'],
                'action_text' => self::ACTION_TEXT[$l['action']] ?? $l['action'],
                'result' => (int)$l['result'],
                // 命令行 / 定时任务里做的没有后台账号(operator_id=0),留空由页面显示「系统」
                'operator_name' => $l['operator_name'] ?: ((int)$l['operator_id'] ? '#' . $l['operator_id'] : ''),
                'remark' => (string)$l['remark'],
                'voucher' => (string)$l['voucher'],
                'detail' => mb_substr((string)$l['detail'], 0, 200),
                'created_at' => date('Y-m-d H:i:s', (int)$l['created_at']),
            ];
        }
        return $out;
    }
}
