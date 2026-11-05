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

use app\models\CommonPayRefund;
use app\models\Order;
use app\payments\BasePayment;
use Yii;
use yii\db\ActiveQuery;

/**
 * 失败退款怎么办:什么时候自动再试、什么时候要人管 —— 定时退款(cron/RefundTask)、「退款失败」处理台、
 * 后台账户菜单的数字、首页提示共用这一处口径。
 *
 * 退款由 cron/RefundTask 每分钟按原支付方式执行。失败后按原因算好下次自动再试的时间(next_retry_at,0 = 不再试):
 *  · 余额不足  失败后隔 10 分钟、1 小时、6 小时、24 小时各再试一次(进账了多半就能退成);同时马上出现在处理台,好让人去充值;
 *  · 配置类    支付方式没开启 / 没配置 / 证书有问题 / 不支持原路退 / 支付单上记的不是支付方式:不改配置永远不会好,不再试;
 *  · 其他      网络超时、结果未知、网关的其他拒绝:隔 1 分钟、5 分钟、30 分钟各再试一次。
 * 记过退款成功的(网关或人工确认过),之后就算被网关回调改回失败,也绝不自动再退,只交给人。
 *
 * 需要人管的(处理台列出来的):
 *  · fail     失败且系统不再自动重试的;以及余额不足的(后台照样按时重试,但要有人去充值);
 *  · stuck    「处理中」超过 30 分钟还没结果:定时任务退到一半中断了;
 *  · unsynced 钱已退成功,但后续业务处理(售后单完成、订单状态同步等)30 分钟还没做完。
 */
class RefundAttentionService
{
    /** 「处理中」「后续没做完」超过多久算卡住 */
    const STUCK_SECONDS = 1800;

    /** RefundTask 给「请求发出去了但没收到网关回应」打的前缀(与 RefundTask::OUTCOME_UNKNOWN_PREFIX 同一个值) */
    const OUTCOME_UNKNOWN_PREFIX = '[结果未知]';

    const KIND_FAIL = 'fail';
    const KIND_STUCK = 'stuck';
    const KIND_UNSYNCED = 'unsynced';

    const KIND_TEXT = [
        self::KIND_FAIL => '退款失败',
        self::KIND_STUCK => '处理中无结果',
        self::KIND_UNSYNCED => '已退款,后续处理未完成',
    ];

    /** 失败类别 */
    const FAIL_BALANCE = 'balance';
    const FAIL_CONFIG = 'config';
    const FAIL_OTHER = 'other';

    /** 第 N 次失败后隔多久再试(秒);超出这张表 = 不再自动重试 */
    const RETRY_DELAYS = [
        self::FAIL_BALANCE => [600, 3600, 21600, 86400],
        self::FAIL_CONFIG => [],
        self::FAIL_OTHER => [60, 300, 1800],
    ];

    /** 余额不足的关键词。处理台要用 SQL 按它筛,所以不写正则;LIKE 与 stripos 都不分大小写 */
    const BALANCE_KEYWORDS = ['余额不足', '资金不足', 'NOTENOUGH', 'NOT_ENOUGH'];

    /** 配置类原因:[正则, 原因, 建议] */
    const CONFIG_RULES = [
        ['/未开启|未配置|配置不全|缺少.*配置/u', '支付方式没开启或配置不全', '到支付设置里开启、补全对应支付方式后点「重新发起」'],
        ['/证书|cert/iu', '支付证书有问题', '更新支付证书后点「重新发起」'],
        ['/不支持/u', '这种支付方式不支持原路退款', '到支付平台或线下把钱退给顾客,再「登记已退款」'],
    ];

    /** 需要人工处理的退款(按商城) */
    public static function query(int $mallId, ?int $now = null): ActiveQuery
    {
        $now = $now ?? time();
        return CommonPayRefund::find()->alias('r')
            ->where(['r.mall_id' => $mallId, 'r.is_delete' => 0])
            ->andWhere(['or',
                ['and', ['r.status' => CommonPayRefund::STATUS_FAIL], ['or',
                    ['r.next_retry_at' => 0],                   // 系统不再自动重试
                    ['>', 'r.success_at', 0],                   // 记过退款成功的,不会被自动再退
                    self::balanceCondition('r.error_msg'),      // 余额不足:后台照样按时重试,但要有人去充值
                ]],
                ['and', ['r.status' => CommonPayRefund::STATUS_PROCESSING], ['<', 'r.updated_at', $now - self::STUCK_SECONDS]],
                ['and', ['r.status' => CommonPayRefund::STATUS_SUCCESS],
                    ['or', ['r.business_synced_at' => 0], ['r.business_synced_at' => null]],
                    ['<', 'r.success_at', $now - self::STUCK_SECONDS]],
            ]);
    }

    public static function count(int $mallId): int
    {
        return (int)self::query($mallId)->count();
    }

    /** 这一笔现在要不要人管 —— 与 query() 同一口径,逐条判断用(改一处要同步改另一处,test-refund-handle 有对照) */
    public static function needsAttention(CommonPayRefund $r, ?int $now = null): bool
    {
        $now = $now ?? time();
        switch ((int)$r->status) {
            case CommonPayRefund::STATUS_FAIL:
                return (int)$r->next_retry_at === 0 || (int)$r->success_at > 0 || self::isBalance($r->error_msg);
            case CommonPayRefund::STATUS_PROCESSING:
                return $r->updated_at !== null && (int)$r->updated_at < $now - self::STUCK_SECONDS;
            case CommonPayRefund::STATUS_SUCCESS:
                return (int)$r->business_synced_at === 0 && $r->success_at !== null && (int)$r->success_at < $now - self::STUCK_SECONDS;
        }
        return false;
    }

    /**
     * 售后列表上这笔退款的一句话进度。state:processing 处理中 / retrying 失败了系统会按时重试 / attention 要人管 / done 已退款
     * @param bool $forMch 给商户看:只说「处理中 / 遇到问题平台在处理 / 已退款」,不讲支付账户余额、网关配置这些平台内部的事
     * @return array{state:string,text:string,attention:bool}
     */
    public static function brief(CommonPayRefund $r, bool $forMch, ?int $now = null): array
    {
        $attention = self::needsAttention($r, $now);
        if ((int)$r->status === CommonPayRefund::STATUS_SUCCESS) {
            $text = !$attention ? '已退款 ' . date('m-d H:i', (int)$r->success_at)
                : ($forMch ? '已退款,售后状态同步中' : '已退款,售后单等后续处理未完成');
            return ['state' => $attention ? 'attention' : 'done', 'text' => $text, 'attention' => $attention];
        }
        if (!$attention) {
            $retrying = !$forMch && self::autoRetryPending($r);
            return [
                'state' => $retrying ? 'retrying' : 'processing',
                'text' => $retrying ? '退款失败,' . date('m-d H:i', (int)$r->next_retry_at) . ' 自动重试' : '退款处理中',
                'attention' => false,
            ];
        }
        if ($forMch) {
            $text = '退款遇到问题,平台正在处理';
        } elseif ((int)$r->status === CommonPayRefund::STATUS_PROCESSING) {
            $text = '退款超过 30 分钟没有结果,需人工核实';
        } elseif (self::isBalance($r->error_msg)) {
            $text = self::autoRetryPending($r)
                ? '支付账户余额不足,' . date('m-d H:i', (int)$r->next_retry_at) . ' 自动再试' : '支付账户余额不足,需人工处理';
        } else {
            $text = '退款失败(' . self::explain($r->error_msg)[0] . '),需人工处理';
        }
        return ['state' => 'attention', 'text' => $text, 'attention' => true];
    }

    /**
     * 一批售后单各自最新的一条退款流水:[售后单 id => 流水]。
     * 同一售后单可能有多条(失败的额度会释放,见 RefundTask::dueQuery),按 id 升序 indexBy,后面的盖前面的 = 最新一条
     * @return CommonPayRefund[]
     */
    public static function latestForAfterSales(array $orderRefundIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderRefundIds))));
        if (!$ids) {
            return [];
        }
        return CommonPayRefund::find()
            ->where(['source_type' => CommonPayRefund::SOURCE_AFTER_SALES, 'source_id' => $ids, 'is_delete' => 0])
            ->orderBy(['id' => SORT_ASC])->indexBy('source_id')->all();
    }

    public static function kindOf(CommonPayRefund $r): string
    {
        if ((int)$r->status === CommonPayRefund::STATUS_SUCCESS) {
            return self::KIND_UNSYNCED;
        }
        return (int)$r->status === CommonPayRefund::STATUS_PROCESSING ? self::KIND_STUCK : self::KIND_FAIL;
    }

    /** 失败了、系统还会按时自动再试的(与 RefundTask::dueQuery 的捞单条件对应) */
    public static function autoRetryPending(CommonPayRefund $r): bool
    {
        return (int)$r->status === CommonPayRefund::STATUS_FAIL && (int)$r->next_retry_at > 0 && (int)$r->success_at <= 0;
    }

    /**
     * 失败后下次什么时候自动再试;0 = 不再自动重试,交给人
     * @param int $attempts 已经试了几次(含刚失败的这次)
     * @param bool $payable 支付单上记的是不是一种支付方式(不是的,重发多少次都失败)
     */
    public static function nextRetryAt(?string $errorMsg, int $attempts, bool $payable, int $now): int
    {
        $delays = self::RETRY_DELAYS[self::failType($errorMsg, $payable)];
        return isset($delays[$attempts - 1]) ? $now + $delays[$attempts - 1] : 0;
    }

    public static function failType(?string $errorMsg, bool $payable = true): string
    {
        if (!$payable) {
            return self::FAIL_CONFIG;
        }
        $msg = (string)$errorMsg;
        // 结果未知的按常规节奏再试:能查的网关会先查、查不了的不换号重发(等回调),见 RefundTask
        if (self::outcomeUnknown($msg)) {
            return self::FAIL_OTHER;
        }
        if (self::isBalance($msg)) {
            return self::FAIL_BALANCE;
        }
        foreach (self::CONFIG_RULES as [$re]) {
            if (preg_match($re, $msg)) {
                return self::FAIL_CONFIG;
            }
        }
        return self::FAIL_OTHER;
    }

    public static function isBalance(?string $errorMsg): bool
    {
        foreach (self::BALANCE_KEYWORDS as $kw) {
            if (stripos((string)$errorMsg, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /** 失败原因是余额不足(SQL 条件) */
    public static function balanceCondition(string $column): array
    {
        return array_merge(['or'], array_map(fn($kw) => ['like', $column, $kw], self::BALANCE_KEYWORDS));
    }

    /** 上次请求发出去了但没收到回应,不知道退没退成 */
    public static function outcomeUnknown(?string $errorMsg): bool
    {
        return str_starts_with((string)$errorMsg, self::OUTCOME_UNKNOWN_PREFIX);
    }

    /**
     * 重新发起前为什么必须先人工核实;返回空串 = 不用核实。这几种都可能已经退过,直接重发会退两次:
     *  · 上次请求发出去没收到网关回应;
     *  · 「处理中」卡住:定时任务退到一半中断,请求可能已经到了网关;
     *  · 记过退款成功(网关或人工确认过),后来又被网关回调改回了失败。
     */
    public static function verifyReason(CommonPayRefund $r): string
    {
        if (self::outcomeUnknown($r->error_msg)) {
            return '上次退款请求没收到网关回应,不知道退没退成';
        }
        if ((int)$r->status === CommonPayRefund::STATUS_PROCESSING) {
            return '这笔退到一半中断了,退款请求可能已经到了网关';
        }
        return (int)$r->success_at > 0 ? '这笔之前记过退款成功,后来又被网关通知失败' : '';
    }

    /**
     * 支付单上记的是不是一种支付方式。不是的(类已不存在、或根本不是支付类),系统没法原路退款,重发多少次都失败,
     * 只能在支付平台或线下退了再登记。本地测试造的支付单就有记成订单类的(integral-order-flow)
     */
    public static function isPayment(?string $paymentClass): bool
    {
        return $paymentClass && class_exists($paymentClass) && is_subclass_of($paymentClass, BasePayment::class);
    }

    /** 这种支付方式能不能向网关查退款结果(子类自己实现了 queryRefund;基类默认直接抛「不支持」) */
    public static function canQuery(?string $paymentClass): bool
    {
        if (!self::isPayment($paymentClass)) {
            return false;
        }
        return (new \ReflectionMethod($paymentClass, 'queryRefund'))->getDeclaringClass()->getName() !== BasePayment::class;
    }

    /**
     * 把网关的失败原因翻译成运营看得懂的话,并给出处理建议。原文照样在页面上显示,这里只是帮着读。
     * @return array{0:string,1:string} [原因, 建议]
     */
    public static function explain(?string $errorMsg): array
    {
        $msg = (string)$errorMsg;
        if (self::outcomeUnknown($msg)) {
            return ['上次退款请求没收到网关回应,不知道退没退成', '先到支付平台核实(能查的点「查询结果」):没退就勾选「已核实」重新发起,已退了就「登记已退款」'];
        }
        if (self::isBalance($msg)) {
            return ['支付账户余额不足', '给支付账户充值后点「重新发起」;不处理的话,系统在失败后隔 '
                . self::delaysText(self::RETRY_DELAYS[self::FAIL_BALANCE]) . ' 各自动再试一次'];
        }
        foreach (self::CONFIG_RULES as [$re, $reason, $advice]) {
            if (preg_match($re, $msg)) {
                return [$reason, $advice];
            }
        }
        if (preg_match('/超时|timed? ?out|连接|网络/iu', $msg)) {
            return ['网络超时', '系统已自动重试过几次都没成功;点「重新发起」再试'];
        }
        return ['其他原因', '看失败原文判断,必要时联系支付渠道;钱在别处退了就「登记已退款」'];
    }

    /** [600, 3600] → 「10 分钟、1 小时」 */
    private static function delaysText(array $delays): string
    {
        return implode('、', array_map(fn($s) => $s % 3600 === 0 ? ($s / 3600) . ' 小时' : ($s / 60) . ' 分钟', $delays));
    }

    /**
     * 支付方式的名称:取支付类自己的 name。取不到时(支付方式被关掉或没配置,构造就报错 —— 如 xwy 关了快付微信JSAPI)
     * 按订单列表「按支付方式筛选」那张对照表(Order::paymentMap)说大类,如「微信支付」;表里也没有才用类名
     */
    public static function paymentName(?string $paymentClass, int $mallId): string
    {
        static $cache = [];
        if (!$paymentClass) {
            return '';
        }
        if (!array_key_exists($paymentClass, $cache)) {
            $name = '';
            try {
                if (self::isPayment($paymentClass)) {
                    $name = (string)(Yii::createObject($paymentClass, [$mallId])->name ?? '');
                }
            } catch (\Throwable $e) {
                $name = '';
            }
            if ($name === '') {
                foreach (Order::paymentMap() as $group) {
                    if (in_array(ltrim($paymentClass, '\\'), $group['payments'], true)) {
                        $name = (string)$group['label'];
                        break;
                    }
                }
            }
            $cache[$paymentClass] = $name ?: substr((string)strrchr('\\' . $paymentClass, '\\'), 1);
        }
        return $cache[$paymentClass];
    }
}
