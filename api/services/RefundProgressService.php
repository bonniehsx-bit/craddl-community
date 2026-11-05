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

use app\helpers\NounHelper;
use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use app\models\CommonPayRefundHandleLog;
use app\models\Order;
use app\payments\BalancePayment;
use app\payments\SystemPayment;

/**
 * 给顾客看的退款进度:订单详情的「退款进度」卡、售后详情的标题和进度时间线。
 *
 * 退款都落在退款流水 common_pay_refund,由定时任务按原支付方式去退。订单上的 pay_status / refund_status
 * 不代表钱到没到(取消订单要等退款成功才改;遇搭一取消就先改了),所以进度只看退款流水本身。
 * 说给顾客的只有四种,每一句都必须属实:
 *  · 退款中       还在正常处理;偶发失败、系统马上会自动重试的也算这里,不惊动顾客;
 *  · 退款遇到延迟 要人管的(与后台「退款失败处理」同一口径 RefundAttentionService::needsAttention),
 *                 顾客看到延迟时处理台里一定有这一笔;不说原因,只说商家正在处理;
 *  · 已退款       按实际怎么退的说:原路退回微信 / 支付宝;退回到余额;后台人工登记的(可能在支付平台或线下退的)
 *                 只说商家已处理,不说原路;
 *  · 商家线下退款 后台支付的单:系统这边直接记成功,钱要商家线下退,不能说「已退回」。
 * 失败原文、支付账户余额不足、网关配置这些一概不给顾客。
 */
class RefundProgressService
{
    const STATE_PROCESSING = 'processing';
    const STATE_DELAYED = 'delayed';
    const STATE_DONE = 'done';
    const STATE_OFFLINE = 'offline';

    /** 顾客看到的退款名目 */
    const SOURCE_TITLE = [
        CommonPayRefund::SOURCE_CANCEL_ORDER => '订单取消退款',
        CommonPayRefund::SOURCE_AFTER_SALES => '售后退款',
        CommonPayRefund::SOURCE_PAID_AFTER_CANCEL => '订单已取消,付款退回',
        CommonPayRefund::SOURCE_YUDA_CANCEL => '拼局取消退款',
        CommonPayRefund::SOURCE_LUCKY_FAIL => '拼团未成功退款',
    ];

    /** 这个订单的全部退款,按发起先后 */
    public static function forOrder(int $orderId, int $mallId): array
    {
        return self::forOrders([$orderId], $mallId)[$orderId] ?? [];
    }

    /**
     * 一批订单各自的退款:[订单 id => 按发起先后的进度列表],没有退款的订单不在结果里。固定三次查询,列表页用。
     * 订单和支付单一一对应(没有合并支付),按支付单把退款归到订单
     */
    public static function forOrders(array $orderIds, int $mallId): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) {
            return [];
        }
        $payOrders = CommonPayOrder::find()->where(['order_id' => $orderIds, 'mall_id' => $mallId, 'is_delete' => 0])->indexBy('id')->all();
        if (!$payOrders) {
            return [];
        }
        $rows = CommonPayRefund::find()
            ->where(['common_pay_order_id' => array_keys($payOrders), 'mall_id' => $mallId, 'is_delete' => 0])
            ->orderBy(['id' => SORT_ASC])->all();
        $items = self::presentAll($rows, $payOrders, $mallId);
        $out = [];
        foreach ($rows as $i => $r) {
            $out[(int)$payOrders[(int)$r->common_pay_order_id]->order_id][] = $items[$i];
        }
        return $out;
    }

    /**
     * 订单列表卡片上的一句话。一单多笔时挑顾客最该知道的那一类:遇到延迟 > 退款中 > 商家线下退款 > 已退款,
     * 金额是这一类的合计(如一笔已退、一笔在退:「退款中 ¥在退那笔」)。没有退款返回 null
     * @return array{state:string,text:string}|null
     */
    public static function summary(array $items): ?array
    {
        $order = [
            self::STATE_DELAYED => '退款遇到延迟',
            self::STATE_PROCESSING => '退款中',
            self::STATE_OFFLINE => '商家线下退款',
            self::STATE_DONE => '已退款',
        ];
        foreach ($order as $state => $text) {
            $hit = array_filter($items, fn($it) => $it['state'] === $state);
            if ($hit) {
                $sum = array_sum(array_map(fn($it) => (float)$it['amount'], $hit));
                return ['state' => $state, 'text' => $text . ' ¥' . number_format($sum, 2, '.', '')];
            }
        }
        return null;
    }

    /** 某张售后单最新的一笔退款(同一售后单可能有多条);还没有退款返回 null */
    public static function forAfterSale(int $orderRefundId, int $mallId): ?array
    {
        $r = RefundAttentionService::latestForAfterSales([$orderRefundId])[$orderRefundId] ?? null;
        if (!$r || (int)$r->mall_id !== $mallId) {
            return null;
        }
        $po = CommonPayOrder::findOne((int)$r->common_pay_order_id);
        return self::presentAll([$r], $po ? [(int)$po->id => $po] : [], $mallId)[0] ?? null;
    }

    /** @param CommonPayRefund[] $rows @param CommonPayOrder[] $payOrders 支付单 id => 支付单 */
    private static function presentAll(array $rows, array $payOrders, int $mallId): array
    {
        if (!$rows) {
            return [];
        }
        // 后台「登记已退款」的:钱可能是在支付平台或线下退的,不能说原路退回
        $manual = array_flip(array_map('intval', CommonPayRefundHandleLog::find()->select('refund_id')->where([
            'refund_id' => array_map(fn($r) => (int)$r->id, $rows),
            'action' => CommonPayRefundHandleLog::ACTION_MARK_REFUNDED, 'result' => 1, 'is_delete' => 0,
        ])->column()));
        $out = [];
        foreach ($rows as $r) {
            $po = $payOrders[(int)$r->common_pay_order_id] ?? null;
            $out[] = self::present($r, $po ? (string)$po->payment : '', isset($manual[(int)$r->id]), $mallId);
        }
        return $out;
    }

    private static function present(CommonPayRefund $r, string $payment, bool $manual, int $mallId): array
    {
        $base = [
            'id' => (int)$r->id,
            'title' => self::SOURCE_TITLE[$r->source_type] ?? '退款',
            'amount' => number_format((float)$r->refund_price, 2, '.', ''),
        ];
        if ((int)$r->status === CommonPayRefund::STATUS_SUCCESS) {
            $time = ['time_label' => '退款时间', 'time' => date('Y-m-d H:i', (int)$r->success_at)];
            if ($manual) {
                return $base + ['state' => self::STATE_DONE, 'state_text' => '已退款', 'desc' => '商家已处理退款,如有疑问请联系商家'] + $time;
            }
            if ($payment === SystemPayment::class) {
                return $base + ['state' => self::STATE_OFFLINE, 'state_text' => '商家线下退款',
                        'desc' => '这笔付款由商家线下退回,请留意商家联系;如有疑问请联系商家'] + $time;
            }
            if ($payment === BalancePayment::class) {
                return $base + ['state' => self::STATE_DONE, 'state_text' => '已退款',
                        'desc' => '已退回到' . NounHelper::parse('balance', $mallId)] + $time;
            }
            $channel = self::channelName($payment);
            return $base + ['state' => self::STATE_DONE, 'state_text' => '已退款',
                    'desc' => $channel !== '' ? "已原路退回{$channel},到账时间以{$channel}为准" : '已原路退回,到账时间以支付渠道为准'] + $time;
        }
        $time = ['time_label' => '发起时间', 'time' => date('Y-m-d H:i', (int)$r->created_at)];
        if (RefundAttentionService::needsAttention($r)) {
            return $base + ['state' => self::STATE_DELAYED, 'state_text' => '退款遇到延迟',
                    'desc' => '商家正在处理,请耐心等待;如有疑问请联系商家'] + $time;
        }
        return $base + ['state' => self::STATE_PROCESSING, 'state_text' => '退款中', 'desc' => '正在按原支付方式退回,请耐心等待'] + $time;
    }

    /** 微信 / 支付宝(按 Order::paymentMap 归类);其他渠道返回空串,只说「原路退回」 */
    private static function channelName(string $payment): string
    {
        $map = Order::paymentMap();
        foreach (['wechat' => '微信', 'alipay' => '支付宝'] as $key => $name) {
            if (in_array($payment, $map[$key]['payments'] ?? [], true)) {
                return $name;
            }
        }
        return '';
    }
}
