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

namespace app\plugins\mch\services;

use app\helpers\NounHelper;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\OrderDetail;
use app\models\CommissionLog;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchCommissionType;
use app\services\CommissionService;
use app\services\WalletRegistry;
use app\services\WalletService;
use Yii;

/**
 * 商家资金结算服务 (Snapshot Version)
 * * 核心原则：
 * 1. 严格依据下单时写入数据库的 mch_income_price (快照) 进行结算。
 * 2. 拒绝任何形式的“现场重算”，确保资金流向与下单时的约定绝对一致。
 */
class MchSettlementService extends BaseModel
{
    /**
     * 商家结算流向(mch_setting.mch_settle_target)的取值域 —— **开放注册表,不是封闭枚举**。
     *
     * 合法值 =
     *   1) 核心账户:'price'(佣金/奖励) / 'balance'(余额)
     *   2) 历史别名:'commission' → 'price'
     *   3) 任意插件通过 Plugin::getWallets() 声明的扩展钱包 key
     *      (mch 自己的 'mch.funds'、xwy 的 'xwy.channel'、以及将来任何插件的钱包)
     *
     * 之所以能直接把钱包 key 当 payout 类型:CommissionService::settle()/revert() 的 default 分支
     * 就是 `WalletRegistry::exists($payoutType) ? WalletService::add/sub(...)`,与 withdraw 插件的
     * AssetService 同源。范本见 xwy\services\RegionService::REWARD_ACCOUNT_PAYOUT('channel' => 'xwy.channel')。
     *
     * 不含 'split'(收银台订单的外部分账走 cashier 插件的 flow_to,由 CashierSettlementService 独占)。
     * 不含 'integral'(货款结算进积分无业务意义;技术上 CommissionService 支持,如需放开在此追加)。
     */
    public const CORE_SETTLE_TARGETS = [
        CommissionService::PAYOUT_TO_PRICE,     // 'price'
        CommissionService::PAYOUT_TO_BALANCE,   // 'balance'
    ];

    /** 历史别名 → 真实 payout 类型(存量配置里 mch_settle_target 多为 'commission') */
    public const SETTLE_TARGET_ALIASES = [
        'commission' => CommissionService::PAYOUT_TO_PRICE,
    ];

    /**
     * 结算流向是否合法(写入端校验用)。空值 = 未配置,视为合法(读取时回退默认)。
     * @param mixed $target
     */
    public static function isValidSettleTarget($target): bool
    {
        if ($target === null || $target === '') {
            return true; // 未配置 → 读取时回退 commission
        }
        if (!is_string($target)) {
            return false;
        }
        return isset(self::SETTLE_TARGET_ALIASES[$target])
            || in_array($target, self::CORE_SETTLE_TARGETS, true)
            || WalletRegistry::exists($target);
    }

    /**
     * 解析结算流向 → CommissionService payout 类型。
     *
     * 资金路径禁止静默降级:未知/笔误的 target(如 'split'、'mch_fund')记 error 日志后回退 commission,
     * 保证资金不卡死、同时让错配置可被发现。合法的扩展钱包 key 原样透传,由 CommissionService 路由。
     *
     * @param mixed $target mch_setting.mch_settle_target 的原始值
     */
    public static function resolvePayoutType($target): string
    {
        if (!is_string($target) || $target === '') {
            return CommissionService::PAYOUT_TO_PRICE; // 未配置 = 历史默认行为
        }
        if (isset(self::SETTLE_TARGET_ALIASES[$target])) {
            return self::SETTLE_TARGET_ALIASES[$target];
        }
        if (in_array($target, self::CORE_SETTLE_TARGETS, true)) {
            return $target;
        }
        if (WalletRegistry::exists($target)) {
            return $target; // 扩展钱包:CommissionService default 分支 → WalletService::add()
        }
        Yii::error(
            "[商家结算] 非法的 mch_settle_target='{$target}',已回退到 commission(佣金账户)。"
            . '合法值:' . implode(' / ', array_merge(
                array_keys(self::SETTLE_TARGET_ALIASES),
                self::CORE_SETTLE_TARGETS,
                array_keys(WalletRegistry::allRaw())
            ))
        );
        return CommissionService::PAYOUT_TO_PRICE;
    }

    /**
     * 读取本商城配置的商家结算流向原始值(未解析;'' 或未配置 = 历史默认 'commission')。
     * settleOneDetail(货款)/settleFreight(运费)/resolveMerchantWithdrawable(商家看板余额)
     * 三处共用同一份配置读取,避免同一商家"钱结算进哪个账户"与"看板读哪个账户"各说各话。
     */
    public static function configuredTarget(int $mallId): string
    {
        $mchSetting = OptionHelper::get('mch_setting', $mallId, []);
        return (is_array($mchSetting) && !empty($mchSetting['mch_settle_target']))
            ? $mchSetting['mch_settle_target'] : 'commission';
    }

    /**
     * 商家当前"可提现货款"实际余额:按 mch_settle_target 解析出的账户读取对应余额。
     *
     * 背景(真实缺陷):MchFinanceDashboardForm 曾硬编码只读扩展钱包 mch.funds 的余额,
     * 而 mch_settle_target 的历史默认值是 'commission'(→ User.price 佣金账户,与 mch.funds 完全无关)——
     * 于是绝大多数走默认配置的商家,看板上的"可提现货款"永远显示 0.00,即便 User.price 里明明有钱。
     * 结算写入哪个账户(见 settleOneDetail/settleFreight)与看板读哪个账户,必须解析同一份配置。
     *
     * @param User $user 已加载的店长用户(避免调用方重复查询)
     */
    public static function resolveMerchantWithdrawable(User $user, int $mallId): float
    {
        return self::withdrawableForPayoutType($user, self::resolvePayoutType(self::configuredTarget($mallId)));
    }

    /**
     * 按已解析的 payout 类型读取对应账户余额(从 resolveMerchantWithdrawable 拆出,
     * 供测试直接注入 payoutType,不必读写 mch_setting 就能覆盖 price/balance/扩展钱包三条分支)。
     */
    public static function withdrawableForPayoutType(User $user, string $payoutType): float
    {
        switch ($payoutType) {
            case CommissionService::PAYOUT_TO_PRICE:
                return (float)$user->price;
            case CommissionService::PAYOUT_TO_BALANCE:
                return (float)$user->money;
            default:
                // 扩展钱包(mch 自己的 mch.funds、或将来其它插件钱包)
                return (float)WalletService::balance((int)$user->id, $payoutType);
        }
    }

    /**
     * 该 payout 资产是否有【至少一条启用的提现渠道】能取出来(软依赖 withdraw 插件,不装则跳过检查)。
     *
     * 背景:商家「提现」按钮(funds-center.vue)现在强制直接进 withdraw 插件页面,而
     * withdraw\PrepareForm 对没有可用渠道的资产是【静默从列表里剔除】——运营在本页把
     * mch_settle_target 配成某个资产后,若忘了去「提现插件-渠道管理」给这个资产配一条渠道,
     * 商家会看得到货款余额、点提现却发现这项资产干脆不在列表里,且没有任何报错提示原因。
     * 这里只做只读检测供 saveSetting() 拼一句非阻断的提醒,不改变保存行为。
     */
    public static function hasWithdrawChannelFor(int $mallId, string $assetType): bool
    {
        $channelClass = '\\app\\plugins\\withdraw\\models\\Channel';
        if (!class_exists($channelClass)) {
            return true; // withdraw 插件未安装,不属于本检查范围,不误报
        }
        /** @var \app\plugins\withdraw\models\Channel[] $channels */
        $channels = $channelClass::find()
            ->where(['mall_id' => $mallId, 'status' => 1, 'is_delete' => 0])
            ->all();
        foreach ($channels as $channel) {
            if ($channel->allowsAssetType($assetType)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 后台「商家结算流向」下拉选项:核心账户 + 本商城已授权的可提现钱包。
     * 名称一律走品牌词库($noun),装了什么插件就有什么钱包,新增插件零改代码。
     *
     * @return array [['key'=>'commission','label'=>'佣金','plugin_label'=>''], ...]
     */
    public static function settleTargetOptions(int $mallId): array
    {
        $options = [
            ['key' => 'commission', 'label' => NounHelper::parse('commission', $mallId), 'plugin_label' => ''],
            ['key' => CommissionService::PAYOUT_TO_BALANCE, 'label' => NounHelper::parse('balance', $mallId), 'plugin_label' => ''],
        ];
        foreach (WalletRegistry::all($mallId) as $wallet) {
            $options[] = [
                'key' => $wallet['key'],
                'label' => $wallet['name'], // WalletRegistry::all 已按词库解析
                'plugin_label' => $wallet['plugin_label'] ?? '',
            ];
        }
        return $options;
    }

    /**
     * [入口A] 普通电商订单结算
     * 通常在订单状态变为“已完成/已收货”且过售后期后调用
     * @param Order $order
     */
    public function settleByOrder(Order $order)
    {
        Yii::warning("[商家结算] 开始处理普通订单结算 OrderID:{$order->id}, mch_id:{$order->mch_id}");

        // 1. 结算货款
        // 查找未结算且状态正常的订单详情
        $details = OrderDetail::findAll([
            'order_id' => $order->id,
            'is_mch_settled' => 0,
            'status' => OrderDetail::STATUS_VALID
        ]);

        Yii::warning("[商家结算] OrderID:{$order->id} 查到待结算明细数: " . count($details));

        if (empty($details)) {
            // 诊断：查一下所有明细的实际状态，定位为什么查不到
            $allDetails = OrderDetail::findAll(['order_id' => $order->id]);
            foreach ($allDetails as $d) {
                Yii::warning("[商家结算][诊断] DetailID:{$d->id} status:{$d->status} is_mch_settled:{$d->is_mch_settled} mch_income_price:{$d->mch_income_price}");
            }
        }

        foreach ($details as $detail) {
            Yii::warning("[商家结算] 正在结算 DetailID:{$detail->id} 金额:{$detail->mch_income_price}");
            $this->settleOneDetail($detail, MchCommissionType::REWARD);
        }

        // 2. 结算运费 (独立处理)
        $this->settleFreight($order);

        Yii::warning("[商家结算] 普通订单处理完毕 OrderID:{$order->id}");
    }

    // ⚠ 这里曾有个 settleByVerify()「[入口B] 团购/权益券核销结算,在核销成功后立即调用」——
    //   **它从来没有被调用过**(全库零调用点、无字符串反射、无子类,git log -S 也证实
    //   从未有过调用方)。而那句注释极具误导性:让人以为核销走独立结算分支。
    //   2026-08-07 就因为它写错了给商家看的结算说明文档。
    //
    //   核销的真实路径:OrderShipmentService::verifyPickup 里
    //     $order->finish_at = time();  → orderSettle($order, true)   // force,不等售后期
    //   然后经 EVENT_ORDER_SETTLED → mch/OrderListener → settleByOrder(),
    //   与快递单**同一个出口**,区别只是不等售后期。
    //   要加核销专属逻辑请改那条链,别在这儿再开一个入口。

    /**
     * [核心逻辑] 处理单个订单详情的资金结算
     * 逻辑：读取快照 -> 确定流向 -> 调用分佣中台 -> 更新状态
     * * @param OrderDetail $detail
     * @param string $logType 分佣记录类型
     * @throws \Exception
     */
    private function settleOneDetail(OrderDetail $detail, string $logType)
    {
        $order = $detail->order;
        if (!$order) return;

        $mch = Mch::findOne($order->mch_id);
        if (!$mch) {
            Yii::error("[商家结算] 找不到商户信息 MchID:{$order->mch_id}");
            return;
        }

        // 商家结算流向(per-mall 配置,默认 commission=进店长佣金账户,与历史行为一致):
        //   commission → 佣金账户(price);balance → 余额;mch.funds → 独立货款账户(与佣金分开,提现规则不同)
        // 默认 commission,保证未配置的商城零行为变化;需要"货款账户"分离的商城在商家设置里改 mch_settle_target。
        $target = self::configuredTarget($order->mall_id);

        // 2. 获取应结金额 (商家实收)
        // [核心修正] 直接读取数据库快照，严禁重新计算
        // 这里的 mch_income_price 是在下单(OrderHandler)时已经扣除了平台抽佣后的金额
        $settleAmount = floatval($detail->mch_income_price);

        // 反算平台抽点比例，用于 commission_log 展示
        $concessionAmount = floatval($detail->concession_amount);
        $totalPrice = floatval($detail->total_price);
        $concessionRate = ($totalPrice > 0 && $concessionAmount > 0)
            ? round($concessionAmount / $totalPrice * 100, 2)
            : null;

        // 如果数据库里是 0，说明这单就是 0 元结算（可能是全额抽佣，或者是 0 元单）
        if ($settleAmount <= 0) {
            $detail->is_mch_settled = 1;
            $detail->save();
            Yii::warning("[商家结算] 结算金额为0 (快照数据)，标记完成 DetailID:{$detail->id}");
            return;
        }

        // 3. 准备分佣服务
        $commissionService = new CommissionService();
        $transaction = Yii::$app->db->beginTransaction();

        try {
            // 4. 确定收款人ID
            $targetUserId = $mch->user_id;
            if (!$targetUserId) {
                throw new \Exception("商户 #{$mch->id} 未绑定店长用户(user_id)，无法结算");
            }

            // 5. 映射 Payout Type(未知值会记 error 日志后回退 commission,不再静默)
            $payoutType = self::resolvePayoutType($target);

            // 6. 第一步：创建分佣记录 (Log)
            $log = $commissionService->log(
                $targetUserId,
                $settleAmount,
                $logType,
                $payoutType,
                [
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                    'buy_user_id' => $order->user_id,
                    'calculation_price' => $detail->total_price,
                    'commission_rate' => $concessionRate,
                    'remark' => "订单[{$order->order_no}]货款结算(扣除平台服务费¥{$concessionAmount})",
                    'extra_data' => [
                        'mch_id' => $mch->id,
                        'mch_name' => $mch->mch_name,
                        'goods_name' => $detail->snapshotName() ?: '商品',   // 下单时的商品名(快照),老数据回退商品表
                        'concession_snapshot' => $detail->concession_amount,
                        'rate_label' => '平台抽点',
                        'rate_desc' => '服务费 ¥' . number_format($concessionAmount, 2, '.', ''),
                    ]
                ]
            );

            // 7. 第二步：立即结算 (Settle)
            $commissionService->settle($log);

            // 8. 更新详情状态为已结算
            $detail->is_mch_settled = 1;
            if (!$detail->save()) {
                throw new \Exception("更新订单详情结算状态失败");
            }

            $transaction->commit();
            Yii::warning("[商家结算] 成功 DetailID:{$detail->id} 金额:{$settleAmount} 流向:{$payoutType}");

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("[商家结算] 失败 DetailID:{$detail->id} 错误:" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * [辅助] 结算运费
     * 运费通常不参与抽佣，全额给商家 (依据下单时的 express_price)
     * @param Order $order
     */
    private function settleFreight(Order $order)
    {
        // 打款门:用独立的 is_express_paid(而非财务汇总标志 is_express_settled),否则结算时 calculateFinalIncome
        // 已把 is_express_settled 置1,这里会被误早退、运费永不打款(H1 资损根因)。
        if ($order->is_express_paid == 1) return;
        if ($order->express_price <= 0) {
            $order->is_express_paid = 1;
            $order->save();
            return;
        }

        // [归属门] 运费归谁,以 express_income_mch_id 为准 —— 这正是核心财务账本的判据
        // (OrderSettlementService::calculateFinalIncome:「==0 → 计入平台毛利,否则计入商家收入」)。
        // 此前这里只看 is_express_paid + express_price,不看归属:一旦某业态把运费判给平台
        // (自提/无物流,或插件在下单阶段钉死 express_income_mch_id=0),平台账面已把这笔算作
        // 平台收入,商家这边却照样打款 —— 同一笔运费被付两次。两处必须用同一个判据。
        if ((int)$order->express_income_mch_id !== (int)$order->mch_id) {
            // 只写这一列且跳过校验:Order 字段众多,一次全模型校验失败就会让标记写不进去,
            // 兜底任务于是每晚重复捞取同一单 —— 那正是这里要消除的问题。
            $order->is_express_paid = 1;
            $order->save(false, ['is_express_paid']);
            Yii::warning("[商家结算] 运费归属非本商家(express_income_mch_id={$order->express_income_mch_id}, mch_id={$order->mch_id}),不打款 OrderID:{$order->id}");
            return;
        }

        $mch = Mch::findOne($order->mch_id);
        if (!$mch || !$mch->user_id) {
            // 不早退而不标记会让兜底任务每晚无限重捞且不留原因,这里记因并结清
            $order->is_express_paid = 1;
            $order->save(false, ['is_express_paid']);
            Yii::error("[商家结算] 运费结算跳过:商户不存在或未绑定店长 OrderID:{$order->id} mch_id:{$order->mch_id}");
            return;
        }

        // 运费全额结算
        $amount = floatval($order->express_price);

        // 运费与货款走同一份商家结算流向配置(此前硬编码 PAYOUT_TO_PRICE,导致商家配置 mch_settle_target=
        // mch.funds 等扩展钱包后,货款进了独立账户、运费却仍跑去佣金账户,同一订单两笔钱分裂到两个账户)。
        $payoutType = self::resolvePayoutType(self::configuredTarget($order->mall_id));

        $commissionService = new CommissionService();
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $log = $commissionService->log(
                $mch->user_id,
                $amount,
                MchCommissionType::REWARD, // 类型：运费奖励
                $payoutType,
                [
                    'order_id' => $order->id,
                    'remark' => "订单[{$order->order_no}]运费结算",
                    'extra_data' => [
                        'type' => 'express_fee',
                        'mch_id' => $mch->id,
                        'rate_label' => '运费结算',
                        'rate_desc' => '全额 ¥' . number_format($amount, 2, '.', ''),
                    ]
                ]
            );

            $commissionService->settle($log);

            $order->is_express_paid = 1; // 运费已打款(独立于财务汇总标志 is_express_settled)
            $order->save();

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("[商家结算] 运费结算失败 OrderID:{$order->id} " . $e->getMessage());
        }
    }
}