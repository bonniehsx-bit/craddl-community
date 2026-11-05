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

namespace app\services;

use app\helpers\IntegralLogHelper;
use app\models\IntegralLog;
use yii\base\Component;

/**
 * 积分中台服务(积分统一业务出入口,镜像 CommissionService)
 *
 * 定位:所有"发/扣/撤"积分的**业务入口**。它负责业务语义(类型注册、可撤销、幂等、透传),
 *       底层账本原子写仍委托唯一入口 IntegralLogHelper(其内部维护批次/有效期并派发 CHANGED 事件)。
 *
 * 架构红线(可插拔 & 低耦合):
 *   - 本类在核心 api/services/,插件只能"调它",核心从不反向调插件;
 *   - 新增积分发放/消耗一律走本网关,IntegralLogHelper 降级为账本原语(仅本类内部调用);
 *   - 类型字符串走 IntegralTypeRegistry 开放注册,禁在此硬编码枚举。
 *
 * 用法:
 *   (new IntegralService())->grant($uid, 100, 'order_base', ['order_no'=>'x', 'idempotencyKey'=>'x']);
 *   (new IntegralService())->consume($uid, 50, 'parking_deduct', ['idempotencyKey'=>'y']);
 *   (new IntegralService())->revert($grantLogOrId);   // 退款回收
 */
class IntegralService extends Component
{
    /**
     * 发放积分(唯一入口)
     *
     * @param int    $userId 获积分用户
     * @param float  $amount 数额(> 0)
     * @param string $type   业务类型(走 IntegralTypeRegistry,如 order_base/sign/exchange_refund)
     * @param array  $params [idempotencyKey, content, title, order_no, mch_id, source_ref, ...]
     * @return int  integral_log.id(幂等命中时返回已存在的日志 ID)
     * @throws \Throwable
     */
    public function grant(int $userId, float $amount, string $type, array $params = []): int
    {
        if ($amount <= 0) {
            throw new \Exception('积分数额必须大于0');
        }
        $meta    = IntegralTypeRegistry::get($type);
        $content = $params['content'] ?? null;
        $title   = $params['title'] ?? ($meta['label'] ?: '积分收入');
        $idem    = $params['idempotencyKey'] ?? null;

        return (int) IntegralLogHelper::add($userId, $amount, $content, $title, $idem, $type, $params);
    }

    /**
     * 消耗积分(唯一出口)
     *
     * @param bool $ignoreBalance 允许扣成负数(退款追回场景);默认 false 校验余额充足
     * @return int integral_log.id
     * @throws \Throwable
     */
    public function consume(int $userId, float $amount, string $type, array $params = [], bool $ignoreBalance = false): int
    {
        if ($amount <= 0) {
            throw new \Exception('积分数额必须大于0');
        }
        $meta    = IntegralTypeRegistry::get($type);
        $content = $params['content'] ?? null;
        $title   = $params['title'] ?? ($meta['label'] ?: '积分支出');
        $idem    = $params['idempotencyKey'] ?? null;

        return (int) IntegralLogHelper::sub($userId, $amount, $content, $title, $idem, $ignoreBalance, $type, $params);
    }

    /**
     * 撤销一条发放(退款回收):按原发放数额回收积分。
     *
     * 幂等:一条发放只回收一次(key=integral_revert_{logId})。
     * 允许扣负:用户可能已把这些分花掉,回收允许把余额扣成负,后续获取时先填平。
     * 可撤销校验:类型显式声明 revertible=false 时拒绝回收(防误伤签到等不可退类型);
     *            biz_type 为空(历史调用)时放行,由调用方负责。
     *
     * @param int|IntegralLog $grantLog 发放日志或其 ID
     * @throws \Throwable
     */
    public function revert($grantLog): void
    {
        $log = $grantLog instanceof IntegralLog
            ? $grantLog
            : IntegralLog::findOne(['id' => (int) $grantLog]);

        if (!$log) {
            throw new \Exception('积分回收失败:发放记录不存在');
        }
        if ((int) $log->type !== IntegralLog::TYPE_ADD) {
            throw new \Exception('积分回收失败:只能撤销"收入"类记录');
        }
        $bizType = $log->biz_type ?: '';
        if ($bizType !== '' && !IntegralTypeRegistry::isRevertible($bizType)) {
            throw new \Exception("积分类型[{$bizType}]不可撤销");
        }

        // 回收额(口径已定案 2026-07-25,见 docs/方案-积分统一网关与有效期.md §10-C1):
        // · 有效期已跟踪该批次(remaining 非 null)→ 只回收"仍存活"的剩余,已花掉/已过期的不再追。
        //   既避免把余额扣成负数,也杜绝「过期回收 + 退款回收」对同一批分双扣。代价:已消费部分平台让利。
        // · 未跟踪(remaining=null,未开启有效期)→ 按原发放额回收,允许扣负(用户已花掉,后续获取时填平)。
        if ($log->remaining !== null) {
            $clawback = min((float) $log->integral, max(0.0, (float) $log->remaining));
            if ($clawback <= 0) {
                return; // 该批已被消费/过期回收殆尽,无可回收
            }
            $ignoreBalance = false;
        } else {
            $clawback = (float) $log->integral;
            $ignoreBalance = true;
        }

        $this->consume((int) $log->user_id, $clawback, $bizType ?: 'revert', [
            'idempotencyKey' => 'integral_revert_' . $log->id,
            'title'          => '积分回收',
            'content'        => '撤销发放记录 #' . $log->id,
            'revert_of'      => $log->id,
        ], $ignoreBalance);
    }
}
