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

namespace app\cron;

use app\core\Task;
use app\helpers\OptionHelper;
use app\helpers\WechatHelper;
use app\models\CommonPayOrder;
use app\models\Express;
use app\models\Order;
use app\services\order\OrderBehaviorRegistry;
use app\payments\FastPayMpwxPayment;
use app\payments\MpwxPayment;
use Yii;
use yii\helpers\Json;

/**
 * 推送订单履约状态至微信支付
 *
 * 扫描已发货/已完成的微信支付订单，根据配送类型构造履约信息推送给微信，
 * 用于资金解冻和用户通知。支持快递、同城、自提、虚拟商品等全场景。
 */
class PushRealFulfillmentTask extends Task
{

    // 微信物流模式
    const LOGISTICS_EXPRESS = 1; // 实体快递
    const LOGISTICS_CITY    = 2; // 同城配送
    const LOGISTICS_VIRTUAL = 3; // 虚拟/自提/服务

    // 微信视为成功的返回码
    const SUCCESS_CODES = [0, 10060001, 10060002, 10060003, 10060004, 10060023];

    // 失败最大重试次数
    const MAX_RETRY = 3;

    public function run($params = null)
    {
        $mallId = Yii::$app->mall->id;
        $log = "[微信履约推送][Mall:{$mallId}] ";

        Yii::$app->platform = 'MPWX';

        $query = Order::find()->alias('o')
            ->joinWith('commonPayOrder cpo')
            ->where([
                'o.mall_id'      => $mallId,
                'o.is_delete'    => 0,
                'o.pay_status'   => Order::PAY_STATUS_PAID,
            ])
            ->andWhere(['>=', 'o.created_at', time() - 30 * 86400]) // 近30天
            ->andWhere(['!=', 'o.status', Order::STATUS_CLOSED])
            ->andWhere(['cpo.payment' => [MpwxPayment::class, FastPayMpwxPayment::class]])
            // 未推送(0) 或 失败待重试(2且未超限)
            ->andWhere(['or',
                ['cpo.is_push_wechat' => 0],
                ['and', ['cpo.is_push_wechat' => 2], ['<', 'cpo.push_retry_count', self::MAX_RETRY]],
            ])
            ->with(['expressLog', 'commonPayOrder', 'orderDetails.goods']);

        $success = 0;
        $fail = 0;

        foreach ($query->batch(50) as $orders) {
            foreach ($orders as $order) {
                $result = $this->processOrder($order, $log);
                if ($result === true) $success++;
                elseif ($result === false) $fail++;
                // null = 跳过（状态不满足）
            }
        }

        if ($success > 0 || $fail > 0) {
            Yii::warning("{$log}完成 成功:{$success} 失败:{$fail}");
        }
    }

    /**
     * 处理单个订单
     * @return bool|null true=成功, false=失败, null=跳过
     */
    private function processOrder(Order $order, string $log): ?bool
    {
        $cpo = $order->commonPayOrder;

        // 基础数据校验
        if (!$cpo || empty($cpo->transaction_id) || empty($cpo->pay_openid)) {
            if ($cpo) $this->markFailed($cpo, '数据缺失:无支付单号或OpenID');
            return false;
        }

        $mchId = $this->getMchId($cpo);
        if (!$mchId) {
            Yii::error("{$log}订单 {$order->order_no} 无法获取MchID");
            return false;
        }

        try {
            // 判断履约类型并构建 payload。放在 try 里:组 payload 要查快递表、读缓存、可能拉微信运力列表,
            // 一单出错只算这一单,不能把本商城这一轮剩下的单全打断
            $payload = $this->buildPayload($order, $cpo, $mchId);
            if (!$payload) return null; // 状态不满足，跳过

            $res = WechatHelper::upload_shipping_info($payload, Yii::$app->mall->id);
            $cpo->push_wechat_params = Json::encode($payload);

            if (isset($res['errcode']) && in_array($res['errcode'], self::SUCCESS_CODES)) {
                $cpo->is_push_wechat = 1;
                $cpo->save(false, ['is_push_wechat', 'push_wechat_params']);
                return true;
            }

            // 失败：记录错误并增加重试计数
            $cpo->is_push_wechat = 2;
            $cpo->push_errors = $res['errmsg'] ?? '未知错误';
            $cpo->push_retry_count = ($cpo->push_retry_count ?? 0) + 1;
            $cpo->save(false, ['is_push_wechat', 'push_wechat_params', 'push_errors', 'push_retry_count']);
            Yii::warning("{$log}订单 {$order->order_no} 推送失败(第{$cpo->push_retry_count}次): " . ($res['errmsg'] ?? ''));
            return false;

        } catch (\Throwable $e) {
            Yii::error("{$log}订单 {$order->order_no} 异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 商家改了快递单号后，把新单号重新同步给微信
     *
     * 微信每个支付单只给一次「重新发货」机会：已经改过一次再推会返回 10060003。
     * 定时推送把 10060003 当成功（那里是重复推同一份数据），这里不行 ——
     * 它意味着新单号没同步上去，必须如实告诉操作人。
     *
     * 只处理已推送成功（is_push_wechat=1）的单：还没推过的单，定时任务会直接推新单号。
     *
     * @return array ['status' => skipped|synced|failed, 'message' => string]
     */
    public function repushExpress(Order $order): array
    {
        $cpo = $order->commonPayOrder;
        if (!$cpo
            || !in_array($cpo->payment, [MpwxPayment::class, FastPayMpwxPayment::class], true)
            || (int)$cpo->is_push_wechat !== 1
        ) {
            return ['status' => 'skipped', 'message' => ''];
        }
        if (empty($cpo->transaction_id) || empty($cpo->pay_openid)) {
            return ['status' => 'failed', 'message' => '微信支付单数据缺失，未能同步新单号'];
        }
        $mchId = $this->getMchId($cpo);
        if (!$mchId) {
            return ['status' => 'failed', 'message' => '获取不到微信商户号，未能同步新单号'];
        }

        // 和定时任务同一个上下文:它在 run() 一开头就切到 MPWX,组 payload(含拉微信运力列表)也在里面
        $platform = Yii::$app->platform;
        Yii::$app->platform = 'MPWX';
        try {
            $payload = $this->buildPayload($order, $cpo, $mchId);
            if (!$payload) {
                return ['status' => 'skipped', 'message' => ''];
            }
            $res = WechatHelper::upload_shipping_info($payload, $order->mall_id);
        } catch (\Throwable $e) {
            Yii::error("[微信履约推送]订单 {$order->order_no} 改单号重推异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => '同步微信失败：' . $e->getMessage()];
        } finally {
            Yii::$app->platform = $platform;
        }

        $code = (int)($res['errcode'] ?? -1);
        if ($code === 0) {
            $cpo->push_wechat_params = Json::encode($payload);
            $cpo->save(false, ['push_wechat_params']);
            return ['status' => 'synced', 'message' => '已同步到微信'];
        }
        if ($code === 10060003) {
            return ['status' => 'failed', 'message' => '微信侧已修改过一次发货信息，不能再次同步；买家在微信里看到的仍是旧单号'];
        }
        if ($code === 10060004) {
            return ['status' => 'failed', 'message' => '微信侧订单已完成或已关闭，新单号未同步'];
        }
        Yii::warning("[微信履约推送]订单 {$order->order_no} 改单号重推失败: " . Json::encode($res));
        return ['status' => 'failed', 'message' => '同步微信失败：' . ($res['errmsg'] ?? '未知错误')];
    }

    /**
     * 根据订单类型构建推送 payload
     */
    private function buildPayload(Order $order, CommonPayOrder $cpo, string $mchId): ?array
    {
        // 判断物流类型和是否满足推送条件
        $logisticsType = $this->resolveLogisticsType($order);
        if (!$this->isReadyToPush($order, $logisticsType)) {
            return null;
        }

        $goodsSummary = $this->getGoodsSummary($order);
        $shippingList = [];

        switch ($logisticsType) {
            case self::LOGISTICS_EXPRESS:
                $express = $order->expressLog;
                if (!$express || empty($express->express_no)) return null;
                $company = $this->resolveExpressCompany($express, (int)$order->mall_id);
                $item = [
                    'tracking_no'     => $express->express_no,
                    'express_company' => $company,
                    'item_desc'       => $goodsSummary ?: '商品已发货',
                ];
                // 微信要求:顺丰必须带收件人或寄件人联系方式,掩码传输且末 4 位不能打码。
                // 名称里带「顺丰」的(顺丰快运等)也带上,联系方式是掩码的,多带不会被拒
                if (strtoupper($company) === 'SF' || mb_strpos((string)$express->express_name, '顺丰') !== false) {
                    $contact = $this->maskContact((string)($express->mobile ?: $order->mobile));
                    if ($contact !== '') {
                        $item['contact'] = ['receiver_contact' => $contact];
                    }
                }
                $shippingList[] = $item;
                break;

            case self::LOGISTICS_CITY:
                $shippingList[] = [
                    'item_desc' => $goodsSummary ? "{$goodsSummary} 配送中" : '同城订单已开始配送',
                ];
                break;

            case self::LOGISTICS_VIRTUAL:
                $shippingList[] = [
                    'item_desc' => $this->getVirtualItemDesc($order, $goodsSummary),
                ];
                break;
        }

        return [
            'order_key' => [
                'mchid'             => $mchId,
                'order_number_type' => 2,
                'transaction_id'    => $cpo->transaction_id,
            ],
            'delivery_mode'  => 1,
            'logistics_type' => $logisticsType,
            'upload_time'    => date('c'),
            'payer'          => ['openid' => $cpo->pay_openid],
            'shipping_list'  => $shippingList,
        ];
    }

    /**
     * 我们的快递公司 → 微信运力 delivery_id
     *
     * 微信要的是自己那套编码(get_delivery_list 返回),不是名字。原来这里读的
     * express_company 字段 ExpressLog 上根本没有,永远填「其他快递」。
     * 我们存的 express_code 来源不一:后台/商户端选快递存的是 express.key(SF/YTO…,
     * 和微信编码大体一致),部分旧数据/导入存的是 express.code(shunfeng…),所以先回 express 表
     * 把两种都换成 key 再去对。
     *
     * 对不上时兜底用我们的 key:编码对不上微信会拒,错误原因记进 push_errors 看得见,
     * 比填一个肯定不对的值强。
     */
    private function resolveExpressCompany(\app\models\ExpressLog $express, int $mallId): string
    {
        $name = trim((string)$express->express_name);
        $code = trim((string)$express->express_code);

        $row = $this->findExpressRow($name, $code, $mallId);

        // 按优先级排:express 表里的 key 最可信,其次是物流记录上存的原值
        $codes = array_values(array_unique(array_filter([
            $row ? strtoupper(trim((string)$row['key'])) : '',
            strtoupper($code),
        ])));
        $rawNames = array_values(array_filter([$name, $row ? (string)$row['name'] : '']));
        $fallback = $codes[0] ?? '';

        $list = WechatHelper::get_delivery_list($mallId);
        if (!$list) {
            return $fallback;
        }
        $byId = [];
        $byName = [];   // 原名(去空格、小写)→ id
        $byLoose = [];  // 去掉「快递/速运…」后缀 → [id, ...]
        foreach ($list as $item) {
            $id = (string)($item['delivery_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[strtoupper($id)] = $byId[strtoupper($id)] ?? $id;
            $deliveryName = (string)($item['delivery_name'] ?? '');
            $exact = $this->plainExpressName($deliveryName);
            if ($exact !== '') {
                $byName[$exact] = $byName[$exact] ?? $id;
            }
            $loose = $this->normalizeExpressName($deliveryName);
            if ($loose !== '') {
                $byLoose[$loose][] = $id;
            }
        }
        // 1) 编码一致
        foreach ($codes as $c) {
            if (isset($byId[$c])) {
                return $byId[$c];
            }
        }
        // 2) 名称完全一致
        foreach ($rawNames as $n) {
            $n = $this->plainExpressName($n);
            if (isset($byName[$n])) {
                return $byName[$n];
            }
        }
        // 3) 去掉后缀再比,但只认唯一的:「顺丰速运」「顺丰快运」去完后缀都是「顺丰」,
        //    却是两个不同的运力,撞了就不猜,交给兜底
        foreach ($rawNames as $n) {
            $ids = array_values(array_unique($byLoose[$this->normalizeExpressName($n)] ?? []));
            if (count($ids) === 1) {
                return $ids[0];
            }
        }
        return $fallback;
    }

    /**
     * 物流记录 → express 表那一行。key 和 code 两列的值会撞(联邦快递国内的 key=FEDEX,
     * 国外那条的 code=fedex),随便取一条就看运气。本商城自定义的快递优先于平台公共的。
     */
    private function findExpressRow(string $name, string $code, int $mallId): ?array
    {
        if ($code === '' && $name === '') {
            return null;
        }
        $cond = ['or'];
        if ($code !== '') {
            $cond[] = ['key' => $code];
            $cond[] = ['code' => $code];
        }
        if ($name !== '') {
            $cond[] = ['name' => $name];
        }
        $rows = Express::find()->where(['is_delete' => 0, 'mall_id' => [0, $mallId]])->andWhere($cond)
            ->orderBy(['mall_id' => SORT_DESC, 'id' => SORT_ASC])->asArray()->all();

        // 库里比较不分大小写:code=fedex 会同时命中 key=FEDEX(国内)和 code=fedex(国外)。
        // 所以在这里按「大小写完全一致 > 不分大小写」、「key > code > 名称」挑
        $checks = [
            fn($r) => $code !== '' && (string)$r['key'] === $code,
            fn($r) => $code !== '' && (string)$r['code'] === $code,
            fn($r) => $code !== '' && strcasecmp((string)$r['key'], $code) === 0,
            fn($r) => $code !== '' && strcasecmp((string)$r['code'], $code) === 0,
            fn($r) => $name !== '' && (string)$r['name'] === $name,
        ];
        foreach ($checks as $check) {
            foreach ($rows as $r) {
                if ($check($r)) {
                    return $r;
                }
            }
        }
        return $rows[0] ?? null;
    }

    /**
     * 按微信要求给联系方式打码:末 4 位保留,前面打码(如 138****1234)
     */
    private function maskContact(string $mobile): string
    {
        $mobile = (string)preg_replace('/\s+/', '', $mobile);
        $tail = substr($mobile, -4);
        if (!preg_match('/^\d{4}$/', $tail)) {
            return '';
        }
        $head = strlen($mobile) >= 11 ? substr($mobile, 0, 3) : '';
        return $head . '****' . $tail;
    }

    private function plainExpressName(string $name): string
    {
        return mb_strtolower((string)preg_replace('/\s+/u', '', $name), 'UTF-8');
    }

    private function normalizeExpressName(string $name): string
    {
        // preg_* 遇到非法 UTF-8 返回 null,统一转回字符串,别让一条脏数据把整批推送打断
        $name = mb_strtolower((string)preg_replace('/\s+/u', '', $name), 'UTF-8');
        $name = (string)preg_replace('/[（(][^）)]*[）)]/u', '', $name);
        $trimmed = (string)preg_replace('/(股份有限公司|有限公司|快递|速递|速运|快运|物流|国际)+$/u', '', $name);
        // 「物流」「快递」这种整个名字都是后缀的,别削成空串
        return $trimmed !== '' ? $trimmed : $name;
    }

    /**
     * 判断订单的物流类型
     */
    private function resolveLogisticsType(Order $order): int
    {
        // 无需物流的订单（send_type=2）统一走虚拟
        if ($order->send_type == 2) {
            return self::LOGISTICS_VIRTUAL;
        }

        switch ($order->deliver_type) {
            case Order::DELIVER_TYPE_EXPRESS:
                return self::LOGISTICS_EXPRESS;
            case Order::DELIVER_TYPE_CITY:
                return self::LOGISTICS_CITY;
            default:
                // pickup / none / 其他 → 虚拟
                return self::LOGISTICS_VIRTUAL;
        }
    }

    /**
     * 判断订单是否满足推送条件
     */
    private function isReadyToPush(Order $order, int $logisticsType): bool
    {
        // 快递和同城：已发货或已完成即可推送
        if ($logisticsType === self::LOGISTICS_EXPRESS || $logisticsType === self::LOGISTICS_CITY) {
            return in_array($order->status, [Order::STATUS_SHIPPED, Order::STATUS_COMPLETED]);
        }

        // 实物单被商家用「无需物流」方式发货：履约事实就是"已发货"，
        // 它落到虚拟只是因为没有快递单号（微信侧没单号只能报 logistics_type=3），
        // 不是因为它需要核销。按虚拟那套等"已完成"，就得等买家确认收货或
        // OrderAutoConfirmFallbackTask 默认 7 天兜底 —— 会超过微信的发货录入时限。
        if ($this->isPhysicalSentWithoutLogistics($order)) {
            return in_array($order->status, [Order::STATUS_SHIPPED, Order::STATUS_COMPLETED]);
        }

        // 真·虚拟/自提/服务：必须已完成才算真正履约（没核销就不算）
        return $order->status === Order::STATUS_COMPLETED;
    }

    /**
     * 实物单以「无需物流」方式发货？
     *
     * send_type=2 单独看是不够的 —— 自提、卡券、充值这些本来就没有物流，
     * 它们也可能是 2。真正的判别点是 deliver_type 仍然是实体配送方式：
     * 商家选的是快递/同城，只是发货时没填单号。
     */
    private function isPhysicalSentWithoutLogistics(Order $order): bool
    {
        return (int)$order->send_type === Order::SEND_TYPE_NONE
            && in_array($order->deliver_type, [Order::DELIVER_TYPE_EXPRESS, Order::DELIVER_TYPE_CITY], true);
    }

    /**
     * 根据商品类型生成虚拟履约文案
     */
    private function getVirtualItemDesc(Order $order, string $goodsSummary): string
    {
        // 实物单走「无需物流」发出的，这里推的是发货事实，不是核销。
        // 放在注册表之前：插件声明的文案描述的是各自的核销/兑换语义
        //（"已办理入住"、"已兑换"），套到一件刚发出去的实物上是错的。
        if ($this->isPhysicalSentWithoutLogistics($order)) {
            return $goodsSummary ? "{$goodsSummary} 已发货" : '商品已发货';
        }

        // 文案由各插件 Plugin::getOrderBehaviors() 声明：
        //   fulfillment_text_with_summary（含 {summary} 占位，有商品摘要时优先）/ fulfillment_text
        // 核心不再硬编码 hotel / checkout / voucher 等业态。
        $text = OrderBehaviorRegistry::fulfillmentText((string)$order->type, $goodsSummary);
        if ($text !== null) {
            return $text;
        }

        return $goodsSummary ? "{$goodsSummary} 已核销" : '商品/服务已核销';
    }

    /**
     * 获取商品名称摘要（前2个商品，限80字）
     */
    private function getGoodsSummary(Order $order): string
    {
        $names = [];
        if ($order->orderDetails) {
            foreach ($order->orderDetails as $detail) {
                // 下单时的商品名(快照),老数据回退商品表
                $name = $detail->snapshotName();
                if ($name !== '') $names[] = $name;
            }
        }
        if (empty($names)) return '';

        return mb_substr(implode('、', array_slice($names, 0, 2)), 0, 80, 'UTF-8');
    }

    /**
     * 获取微信商户号
     */
    private function getMchId(CommonPayOrder $cpo): ?string
    {
        if (!empty($cpo->payment_config)) {
            try {
                $conf = Json::decode($cpo->payment_config);
                $mchid = $conf['mchid'] ?? $conf['mch_id'] ?? null;
                if ($mchid) return (string)$mchid;
            } catch (\Exception $e) {}
        }

        $wxapp = OptionHelper::get('platform_mpwx', $cpo->mall_id);
        return isset($wxapp['mchid']) ? (string)$wxapp['mchid'] : null;
    }

    /**
     * 标记为失败
     */
    private function markFailed(CommonPayOrder $cpo, string $msg)
    {
        $cpo->is_push_wechat = 2;
        $cpo->push_errors = $msg;
        $cpo->push_retry_count = ($cpo->push_retry_count ?? 0) + 1;
        $cpo->save(false, ['is_push_wechat', 'push_errors', 'push_retry_count']);
    }

    public function rule()
    {
        return "*/1 * * * *";
    }

    public function description()
    {
        return "【微信发货】推送订单履约状态至微信支付（快递/同城/自提/虚拟）";
    }
}
