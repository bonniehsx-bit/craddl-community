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

use app\helpers\LockHelper;
use app\helpers\OptionHelper;

// 确保引入了 OptionHelper
use Yii;

/**
 * This is the model class for table "{{%common_pay_order}}".
 *
 * @property int $id 主键ID
 * @property int|null $mall_id mall_id
 * @property string|null $order_no 订单号
 * @property int|null $order_id 订单ID
 * @property int|null $user_id 用户ID
 * @property string|null $order_class 调用的类
 * @property float|null $pay_price 支付金额
 * @property int|null $is_pay 是否支付
 * @property int|null $pay_at 支付时间
 * @property string|null $pay_openid 支付时候的openid
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 * @property string|null $platform 平台
 * @property string|null $remarks 备注
 * @property string|null $transaction_id 交易ID
 * @property int|null $is_refund 0 未退款  1已退款 2退款失败
 * @property int|null $refund_at 退款时间
 * @property string|null $refund_remarks 退款备注
 * @property string|null $key 多个订单唯一key
 * @property float|null $total_price 总额
 * @property string|null $err_msg 支付错误信息
 * @property string|null $refund_order_no 退款单号
 * @property string|null $out_trade_no 商户支付单号(与支付网关交互)
 * @property string|null $payment 支付
 * @property int $is_need_refund 是否需要退款
 * @property int $refund_order_detail_id 退款详情ID
 * @property float $refund_price 退款金额
 * @property int|null $is_push_wechat 是否推送到微信端
 * @property string|null $push_errors 推送错误信息
 * @property int $push_retry_count 推送重试次数
 * @property int $is_refund_fail 是否退款失败
 * @property int $is_cancel 是否取消 0未取消 1已取消
 * @property int|null $cancel_at 取消时间
 * @property int|null $expire_at 订单过期/自动关闭时间
 * @property OrderDetail $orderDetail
 * @property mixed|null $payment_config 支付信息
 * @property mixed|null $push_wechat_params 发货推送到微信端的参数
 */
class CommonPayOrder extends BaseActiveRecord
{
    const COMMON_PAY_ORDER_PAY = 'common_pay_order_pay';

    // 支付状态
    const STATUS_UNPAID = 0;
    const STATUS_PAID = 1;

    // 退款状态 (is_refund)
    const REFUND_STATUS_NONE = 0; // 未退款
    const REFUND_STATUS_PARTIAL = 1; // 部分退款 (注注释：原注释写的是"已退款"，但在实际业务中可能含部分退款，需结合 refund_price 判断)
    const REFUND_STATUS_FAILED = 2; // 退款失败

    // 取消状态
    const CANCEL_STATUS_NONE = 0;
    const CANCEL_STATUS_TRUE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%common_pay_order}}';
    }

    /**
     * 永不出现在 API 响应里的列。
     *
     * 🔒 payment_config 是下单时那份网关配置的快照,里面有 **merchantPrivateKey**
     * (1600+ 字符的 RSA 私钥,给快付请求签名用,一个商城共用一把)。拿到它就能以商户身份
     * 签发下单 / NORMAL_ORDER_REFUND 退款 / SETTLE_ORDER_APPLY 结算 / 代付 —— 直接能动钱。
     * 而 PayForm::getOrder() 是把整个模型塞进响应的(`['order' => $order]`),
     * 于是任何一个登录用户拿一个 pay_order_id(自增整数)就能把私钥读走。
     *
     * key 是 pay_data_key,拿着它能换支付参数,等同一次性凭证;
     * pay_openid / push_wechat_params 含用户 openid 与推送报文,同样不该出给前端。
     *
     * ⚠️ 本方法只作用于**模型实例的 toArray()**。用 `asArray()` + `select('o.*')` 查出来的
     * 数组绕过 fields(),必须在各自的 select 里显式列字段(见 forms/mall/payment/OrderListForm)。
     */
    protected function getHiddenFields()
    {
        return ['payment_config', 'key', 'pay_openid', 'push_wechat_params'];
    }

    /**
     * 关联用户
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 关联业务订单 (商城订单)
     * 注意：common_pay_order 表中的 order_id 对应 Order 表的 id
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'order_id', 'refund_at', 'is_refund_fail', 'refund_order_detail_id', 'is_need_refund', 'user_id', 'is_push_wechat', 'push_retry_count', 'is_pay', 'pay_at', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'is_refund', 'is_cancel', 'cancel_at', 'expire_at'], 'integer'],
            [['pay_price', 'refund_price', 'total_price'], 'number'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['order_no', 'transaction_id', 'key', 'refund_order_no', 'out_trade_no'], 'string', 'max' => 64],
            [['order_class'], 'string', 'max' => 255],
            [['platform'], 'string', 'max' => 45],
            [['pay_openid', 'remarks'], 'string', 'max' => 128],
            [['push_errors'], 'string', 'max' => 5120],
            [['payment_config', 'push_wechat_params'], 'string'],
            [['refund_remarks', 'err_msg', 'payment'], 'string', 'max' => 512],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键ID',
            'mall_id' => 'mall_id',
            'order_id' => '订单ID',
            'order_no' => '订单号',
            'user_id' => '用户ID',
            'order_class' => '调用的类',
            'pay_price' => '支付金额',
            'is_pay' => '是否支付',
            'pay_at' => '支付时间',
            'pay_openid' => '支付时候的openid',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
            'platform' => '平台',
            'remarks' => '备注',
            'transaction_id' => '交易ID',
            'is_refund' => '0 未退款  1已退款 2退款失败',
            'refund_at' => '退款时间',
            'refund_remarks' => '退款备注',
            'key' => '多个订单唯一key',
            'total_price' => '总额',
            'err_msg' => '支付错误信息',
            'refund_order_no' => '退款单号',
            'out_trade_no' => '商户支付单号',
            'payment' => '支付',
            'is_need_refund' => '是否需要退款',
            'refund_order_detail_id' => '退款详情ID',
            /**
             * @var float $refund_price 累计已退款金额 (缓存字段/快照，非单次退款额)
             */
            'refund_price' => '退款金额',
            'is_push_wechat' => '是否推送到微信端',
            'push_errors' => '推送错误信息',
            'push_retry_count' => '推送重试次数',
            'is_refund_fail' => '是否退款失败',
            'payment_config' => '支付信息',
            'push_wechat_params' => '发货推送到微信端的参数',
            'is_cancel' => '是否取消',
            'cancel_at' => '取消时间',
            'expire_at' => '过期时间',
        ];
    }

    /**
     * 构造传给支付网关 placeOrder() 的标准数据（**所有发起支付的入口都必须走这里**）。
     *
     * ## 为什么要收口
     * 原先两个入口各拼各的数组,而且**单号键是互斥的**:
     *   PayForm(C端常规支付)        给 out_trade_no,不给 order_no
     *   MpLaunchPayForm(App借小程序) 给 order_no,不给 out_trade_no
     * 网关各按各的假设读:多数读 out_trade_no,FastPayQuickPayment / JoinPayMpwxPayment 读 order_no。
     * 一旦读到对方没提供的那个键,PHP 8 下就是 Undefined array key + null,
     * 而 null 又会被网关里的 array_filter 静默滤掉 —— 商户单号从报文里凭空消失,
     * 最后只换来平台一句语焉不详的报错,极难往回追。
     *
     * 这里一次性把两个单号都给全,网关读哪个都有值;将来新增字段也只改这一处,
     * 不必再记得两个入口同步。
     *
     * ## out_trade_no 的语义
     * 它是**与网关交互的商户单号**,每次发起支付都要新的 —— 同一笔支付单允许用户反复
     * 发起(上次没付完、换支付方式、超时重来),而绝大多数网关不接受重复的商户单号。
     * order_no 则是我们的系统单号,永不变,供业务侧关联。
     * 详见 docs/规范-支付.md §26.1。
     *
     * @param string $openid  本次支付使用的 openid(小程序/公众号场景各自获取,不复用库里的)
     * @param string $body    支付账单上显示的描述
     * @param bool   $renewOutTradeNo 是否重新生成商户单号。默认 true;
     *                        调用方若已在自己的事务里生成好(如 PayForm),传 false 避免重复写库。
     * @param array  $extra   网关特有的补充字段(如快捷支付的 bind_card_id)
     */
    public function buildGatewayOrderData(string $openid, string $body, bool $renewOutTradeNo = true, array $extra = []): array
    {
        if ($renewOutTradeNo || empty($this->out_trade_no)) {
            $this->out_trade_no = $this->nextTradeNo();
            $this->save(false, ['out_trade_no']);
        }
        // 每一个发出去过的商户单号都登记,否则重新发起后旧号的回调将匹配不到订单(掉单)
        $this->issueTradeNo($this->out_trade_no);

        return array_merge([
            'body'         => \app\helpers\PaymentDescriptionHelper::resolve((int) $this->mall_id, $body),
            'order_no'     => $this->order_no,      // 系统单号:恒定,业务侧关联用
            'out_trade_no' => $this->out_trade_no,  // 商户单号:每次发起支付都新的,透传给网关
            'pay_order_id' => $this->id,
            'pay_price'    => $this->pay_price,
            'openid'       => $openid,
            'user_id'      => $this->user_id,
            'notify_url'   => '',
        ], $extra);
    }

    /**
     * 登记一个已发给网关的商户单号。
     *
     * out_trade_no 每次发起支付都会重算并覆盖主表那一列(换支付方式/超时重来时多数
     * 网关不接受重复单号,是刻意设计)。但覆盖之后,旧号就再也查不回订单了 —— 用户
     * 若在旧的预支付单上完成支付(点了付、取消、隔一秒再点,旧收银台在 H5/公众号里
     * 往往还能拉起来),回调带回旧号,匹配 out_trade_no 失败、兜底 order_no 也失败
     * (旧号形如 order_no + '_' + 时间戳),结果就是钱扣了单还挂着。
     *
     * 所以每发出去一个号就在这里留一份底,配合 findByTradeNo() 三级匹配认回来。
     *
     * 只增不改;重复登记由唯一索引挡掉。历史登记失败**绝不能**影响支付主流程,
     * 因此整段吞异常只记日志。
     */
    /**
     * 生成一个**本单没用过**的商户单号。
     *
     * 以 order_no 为前缀便于人工对账;order_no 本身可能已带 _ 后缀,取第一段防止越接越长。
     *
     * ⚠ 为什么要查重:原来直接 `order_no . '_' . time()`,而 **time() 只到秒** ——
     * 用户在同一秒里点两次支付(切支付方式、或单纯手快),生成的号**完全相同**。
     * 于是 issueTradeNo 发现该号已存在就直接跳过,登记表里那行仍记着**第一次**的支付类;
     * 回调按单号反查支付类时就会分到错的网关,和 57311 那起事故是同一个后果。
     * 实测线上确有一单(57345)撞上:登记 BalancePayment、订单字段 FastPayAliJsPayment。
     *
     * 「一个号 = 一次尝试 = 一个支付类」是 resolvePaymentByTradeNo 成立的前提,
     * 必须在生成侧就保证,不能指望调用方。
     */
    /**
     * @param string $baseOrderNo 号段前缀。留空则用本支付单的 order_no;
     *        PayForm 传的是**商城订单**的 order_no(与支付单的可能不同),那是既有口径,保持。
     */
    public function nextTradeNo(string $baseOrderNo = ''): string
    {
        $prefix = $baseOrderNo !== '' ? $baseOrderNo : (string) $this->order_no;
        $base = explode('_', $prefix)[0] . '_' . time();
        $candidate = $base;
        // 同秒重发时退化成 _1788597998、_17885979981、_17885979982 …
        // 只多 1~2 个字符,列是 varchar(64),各网关的 merOrderNo 也都够长。
        for ($i = 1; $i <= 20; $i++) {
            $taken = CommonPayOrderTradeNo::find()->where(['out_trade_no' => $candidate])->exists();
            if (!$taken) {
                return $candidate;
            }
            $candidate = $base . $i;
        }
        // 20 次还撞(几乎不可能),退回随机后缀保证唯一,别把支付流程卡死
        return $base . strtolower(\Yii::$app->security->generateRandomString(4));
    }

    /**
     * 订单上那份下单时的配置快照,是否属于指定支付类。
     *
     * payment_config 与 payment 一样是订单级的、被每次支付尝试覆盖。但两者是各网关
     * placeOrder 里**同一次 updateAll 一起写**的,所以天然配套 —— 唯一会打破配对的是
     * BalancePayment:它只改 payment、不写 payment_config。于是「payment=余额、
     * 而 payment_config 还是上一次网关的」这种组合很常见(实测 4721 单),
     * 此时那份配置对**那个网关**仍然是有效的,不该因为对不上 payment 字段就丢掉。
     *
     * 判断依据用配置自带的 payment_type,而不是拿 $this->payment 当代理 —— 后者在上述
     * 组合下会误判。实测 23232 份快照全都带 payment_type,且「已支付且走真实网关」的
     * 13163 单里 payment_type 指向别家网关的有 0 单。
     */
    public function paymentConfigBelongsTo(string $paymentClass): bool
    {
        if ($paymentClass === '' || empty($this->payment_config)) {
            return false;
        }
        try {
            $cfg = \yii\helpers\Json::decode($this->payment_config);
        } catch (\Throwable $e) {
            return false;
        }
        return is_array($cfg) && (string) ($cfg['payment_type'] ?? '') === $paymentClass;
    }

    public function issueTradeNo(string $tradeNo, string $payment = ''): void
    {
        $tradeNo = trim($tradeNo);
        if ($tradeNo === '' || !$this->id) {
            return;
        }
        // 放在 try 外:下面的 catch 也要用它,写在里面 PHP 会报未定义变量
        $want = $payment !== '' ? $payment : (string) $this->payment;

        try {
            /** @var CommonPayOrderTradeNo|null $existing */
            $existing = CommonPayOrderTradeNo::findOne(['out_trade_no' => $tradeNo]);
            if ($existing) {
                // 号已登记过。正常情况这是同一次尝试被重复登记,直接返回即可。
                //
                // 但如果登记的支付类和本次不一样,说明**同一个号被两次不同的支付尝试用了**
                // (nextTradeNo 已在生成侧防住同秒重发,这里是最后一道)。
                // 此时必须纠正成本次的 —— 网关最终收到这个号的是最后一次下单,
                // 回调也会按最后一次的通道回来。留着旧值会让回调分发到错的网关(掉单)。
                // ⚠ 只纠正**属于本订单**的行。findOne 是按单号全局查的,而这一行现在
                // 决定「回调该交给哪个网关」—— 万一号真的跨单撞了(理论上不会,单号以各自
                // order_no 为前缀),改它就等于篡改另一笔订单的支付路由。宁可不改并告警。
                if ((int) $existing->pay_order_id !== (int) $this->id) {
                    Yii::error("[CommonPayOrder] 商户单号 {$tradeNo} 已属于另一笔支付单 "
                        . "#{$existing->pay_order_id},本单 #{$this->id} 不覆盖它", 'payment');
                    return;
                }
                if ($want !== '' && $existing->payment !== $want) {
                    Yii::warning("[CommonPayOrder] 商户单号 {$tradeNo} 被复用:登记的支付类 "
                        . "{$existing->payment} → 纠正为 {$want}(pay_order_id={$this->id})", 'payment');
                    $existing->payment = $want;
                    $existing->save(false, ['payment']);
                }
                return;
            }
            $log = new CommonPayOrderTradeNo();
            $log->mall_id      = (int) $this->mall_id;
            $log->pay_order_id = (int) $this->id;
            $log->order_no     = (string) $this->order_no;
            $log->out_trade_no = $tradeNo;
            $log->payment      = $want;
            $log->save();
        } catch (\Throwable $e) {
            // 并发下两个请求同时登记同一个号时唯一索引会抛,属预期内,吞掉即可。
            //
            // 但**不能只是吞掉**:上面的 findOne 查空、INSERT 又被唯一索引挡下,
            // 说明有另一个请求在这两步之间刚插进去(check-then-act 竞态)。
            // 此时该行记的是对方的支付类,本次的就丢了 —— 回调按单号分发会分到对方那个。
            // 补一次纠正,与上面「号被复用」同口径:最后一次下单的通道才是网关收到的。
            Yii::warning("[CommonPayOrder] 商户单号登记失败 pay_order_id={$this->id} out_trade_no={$tradeNo}: " . $e->getMessage(), 'payment');
            try {
                $raced = CommonPayOrderTradeNo::findOne(['out_trade_no' => $tradeNo]);
                // 同上:只碰属于本订单的行
                if ($raced && (int) $raced->pay_order_id === (int) $this->id
                    && $want !== '' && $raced->payment !== $want) {
                    $raced->payment = $want;
                    $raced->save(false, ['payment']);
                    Yii::warning("[CommonPayOrder] 并发登记冲突后已纠正支付类 {$tradeNo} → {$want}", 'payment');
                }
            } catch (\Throwable $ignore) {
                // 纠正失败也不能影响支付主流程,记到上面那条日志就够了
            }
        }
    }

    /**
     * 按商户单号反查支付单 —— **所有网关回调/查单都必须走这里**,不要再写裸的
     * findOne(['out_trade_no' => ...])，否则重新发起过支付的订单会掉单。
     *
     * 三级匹配:
     *   1. 当前 out_trade_no —— 绝大多数情况一次命中
     *   2. 历史商户单号     —— 认回被覆盖掉的旧号(本方法存在的理由)
     *   3. order_no         —— 改造前部分网关直接拿系统单号当商户单号发出去的老单
     *
     * @param string $tradeNo    网关回传的商户单号
     * @param array  $extraCond  附加约束(如 ['is_delete' => 0] / ['mall_id' => x]),
     *                           原调用点写的什么就原样传进来,语义不变
     */
    public static function findByTradeNo($tradeNo, array $extraCond = []): ?CommonPayOrder
    {
        $tradeNo = trim((string) $tradeNo);
        if ($tradeNo === '') {
            return null;
        }

        $order = static::findOne(array_merge(['out_trade_no' => $tradeNo], $extraCond));
        if ($order) {
            return $order;
        }

        $payOrderId = CommonPayOrderTradeNo::find()
            ->select('pay_order_id')
            ->where(['out_trade_no' => $tradeNo, 'is_delete' => 0])
            ->scalar();
        if ($payOrderId) {
            $order = static::findOne(array_merge(['id' => (int) $payOrderId], $extraCond));
            if ($order) {
                Yii::warning("[CommonPayOrder] 回调命中历史商户单号 {$tradeNo} → pay_order_id={$payOrderId}(该单曾重复发起支付)", 'payment');
                return $order;
            }
        }

        return static::findOne(array_merge(['order_no' => $tradeNo], $extraCond));
    }

    /**
     * 这个商户单号当初是用哪个支付类发出去的。
     *
     * ============================================================
     * 为什么不能直接读 $order->payment
     * ============================================================
     * 主表的 payment / out_trade_no / payment_config 记的都是**最后一次支付尝试**,
     * 每发起一次支付就被覆盖(PayForm:137 + 各网关下单时的 updateAll)。
     * 而一笔回调针对的是**某一次特定尝试**,由它带回来的 out_trade_no 唯一确定。
     * 用户在支付方式之间切一下再回头付前一个,两者就对不上了。
     *
     * 真实事故(2026-09-05,支付单 57311,¥299):
     *   15:21:55 余额    → payment=Balance
     *   15:22:00 支付宝  → payment=FastPayAliJs   ← 用户真正付掉钱的是这一笔
     *   15:22:40 余额    → payment=Balance        ← 字段停在这里
     *   15:24:04 支付宝推 SUCCESS 回调
     * 回调进来时 payment 是 Balance,FastController 的余额短路分支直接
     * return SUCCESS 把回调丢了,而且告诉网关"收到了"→ 网关不再重推。
     * 掉单补偿 cron 同样按 payment 实例化出 BalancePayment,而它的 queryOrder
     * 只读本地 is_pay、从不问外部网关 → 也补不回来。两道防线因同一个原因全失效,
     * 最后靠人工补单。
     *
     * 所以「这笔回调属于哪个支付类」必须由单号决定,而登记表每行都记着。
     *
     * @param string $tradeNo 网关回传的商户单号
     * @param self|null $order 已查到的订单,用于兜底(登记表里没有该号时回落它的 payment)
     * @return string 支付类全名;实在取不到返回空串,调用方需自行判断
     */
    public static function resolvePaymentByTradeNo(string $tradeNo, ?self $order = null): string
    {
        $tradeNo = trim($tradeNo);
        if ($tradeNo !== '') {
            $payment = (string) CommonPayOrderTradeNo::find()
                ->select('payment')
                ->where(['out_trade_no' => $tradeNo, 'is_delete' => 0])
                ->scalar();
            if ($payment !== '') {
                return $payment;
            }
        }

        // 登记表里没有:要么是本表上线前的老单,要么是 issueTradeNo 当时写失败了
        // (那段是吞异常的)。回落订单字段 —— 与改造前完全一致,不会更糟。
        return $order ? (string) $order->payment : '';
    }

    /**
     * 该支付单发出去过的所有商户单号,按登记顺序。
     *
     * 掉单补偿要用:只查主表那个 out_trade_no 的话,查的是最后一次尝试的号,
     * 而用户真正付掉钱的可能是前面某一次。返回 [out_trade_no => payment]。
     *
     * 兜底把主表当前的号也并进去 —— 万一它没进登记表(老单/登记失败),
     * 不能因此漏查。
     */
    /**
     * 某个商户单号是哪一天发起的(Ymd),给快付查单/退款的 oglOrderDate 用。
     *
     * 该字段语义是「原订单日期」,即那次下单报给网关的 merOrderTime 的日期。原来六处一律取
     * **支付单创建日** —— 而支付单是结算时建的,用户第二天才从订单列表点「去支付」,两者就不是
     * 同一天(实测 08-03 至今真实登记行里 0 例,但结构上随时可能发生,发生了退款就对不上原单)。
     *
     * 登记表每个号的 created_at 就是发起时刻(与 placeOrder 同一请求)。但要防两种脏值:
     *   · 迁移回填的存量行:created_at 是**迁移那一刻**,不是发起时刻(xwy 实库 654 行全是 08-03 20:32);
     *   · 付款之后才发的号:那次显然不是付款那次。
     * 所以只信「发起时刻落在 [支付单创建, 支付成功] 区间」的登记行,其余一律回落创建日 ——
     * 回落值就是改造前的取法,不会比原来更错。已支付单 pay_at 恒有值(实库 44684 单 0 缺)。
     */
    public function tradeNoDate(string $tradeNo): string
    {
        $fallback = date('Ymd', (int) $this->created_at);
        $tradeNo = trim($tradeNo);
        if ($tradeNo === '') {
            return $fallback;
        }
        $issuedAt = (int) CommonPayOrderTradeNo::find()
            ->select('created_at')
            ->where(['out_trade_no' => $tradeNo, 'pay_order_id' => $this->id, 'is_delete' => 0])
            ->scalar();
        if ($issuedAt <= 0 || $issuedAt < (int) $this->created_at) {
            return $fallback;
        }
        if ($this->is_pay && (int) $this->pay_at > 0 && $issuedAt > (int) $this->pay_at) {
            return $fallback;
        }
        return date('Ymd', $issuedAt);
    }

    public function allTradeNos(): array
    {
        $rows = CommonPayOrderTradeNo::find()
            ->select(['out_trade_no', 'payment'])
            ->where(['pay_order_id' => $this->id, 'is_delete' => 0])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        $map = [];
        foreach ($rows as $r) {
            $no = trim((string) $r['out_trade_no']);
            if ($no !== '') {
                $map[$no] = (string) $r['payment'];
            }
        }

        $current = trim((string) $this->out_trade_no);
        if ($current !== '' && !isset($map[$current])) {
            $map[$current] = (string) $this->payment;
        }

        // 最后兜底:改造前的老单 out_trade_no 是空的(实库 47329 个),网关那边拿的是 order_no。
        // 不补这一条,调用方(掉单补偿)会因为拿到空数组而把整单跳过 —— 比改造前**少**覆盖,
        // 那是回归。与 findByTradeNo 的第三级匹配同口径。
        if (!$map) {
            $orderNo = trim((string) $this->order_no);
            if ($orderNo !== '') {
                $map[$orderNo] = (string) $this->payment;
            }
        }
        return $map;
    }

    /**
     * 支付成功统一入口（所有支付网关回调后调用此方法）
     *
     * 收口了：幂等检查、加锁、标记已支付、业务回调，支付网关只需一行调用。
     *
     * @param string $transactionId 第三方支付流水号
     * @param string|null $paymentClass 支付类（如 BalancePayment::class），不传则保持现有值
     * @param string|null $paidTradeNo  **实际付了钱的**商户单号,不传则保持现有值。
     *        out_trade_no 平时记的是最后一次发起支付的号,而用户可能付在更早的号上
     *        (同一订单反复发起时很常见,09-05 一天 15 笔支付宝回调里就有 2 笔)。
     *        快付三个 JS 类的 queryOrder/doRefund 问网关用的都是 out_trade_no,
     *        不在这里把付款号写回,之后退款/查单就会拿一个从没付过钱的号去问 —— 退不出去。
     *        必须在锁内、refresh() 之后写,挂在实例上传进来会被 refresh 冲掉。
     * @param string|null $paidAmount   网关回传的**实付金额**(元,字符串)。传了就与 pay_price 比对,
     *        不等则拒绝置已支付、留痕并返回 false;不传(null)跳过比对(网关没给这个字段时用)。
     *        原来没有任何一个回调比对金额:网关签名保证报文没被篡改,但保证不了「这笔钱和这张单对得上」——
     *        收银台已拉起、订单又被改价/换券,用户按旧金额付,回调 SUCCESS 照样置已付。
     *        在这里一处比对,10 个网关都不用各写一遍。
     * @return bool
     */
    public function paySuccess(string $transactionId, ?string $paymentClass = null, ?string $paidTradeNo = null, ?string $paidAmount = null): bool
    {
        // 1. 快速幂等检查（无锁）
        if ($this->is_pay == 1) {
            Yii::warning("[CommonPayOrder::paySuccess] 订单 {$this->order_no} 已支付，跳过。");
            return true;
        }

        // 2. 已取消则阻断
        if ($this->is_cancel == 1) {
            $this->refundPaidAfterCancel($transactionId, $paymentClass, $paidTradeNo);
            return false;
        }

        // 3. 加锁防并发。拿不到锁时短暂等一下(最多约 3 秒):持锁的可能是同一笔支付的另一次回调,
        //    也可能是正在关单的 AutoCancelOrderTask。以前拿不到立即 false —— 网关回调都不看返回值、照样回 SUCCESS,
        //    网关就不再重推,这笔钱既没入账也没留痕。
        $lockToken = false;
        for ($i = 0; $i < 30 && !$lockToken; $i++) {
            $lockToken = LockHelper::lock('COMMON_PAY_ORDER_' . $this->id, 300);
            if (!$lockToken) {
                usleep(100000);
            }
        }
        if (!$lockToken) {
            $this->reloadLatest();
            if ($this->is_pay == 1) {
                return true; // 等待期间别的回调已经入账
            }
            if ($this->is_cancel == 1) {
                $this->refundPaidAfterCancel($transactionId, $paymentClass, $paidTradeNo); // 等待期间被关单:钱到了单关了,原路退回
                return false;
            }
            Yii::error("[CommonPayOrder::paySuccess] 🔴 订单 {$this->order_no}(#{$this->id})获取并发锁失败 transaction_id={$transactionId},需人工核对是否已收款", 'payment');
            try {
                static::updateAll(
                    ['err_msg' => "回调时获取并发锁失败 transaction_id={$transactionId}"],
                    ['and', ['id' => $this->id], ['or', ['err_msg' => null], ['err_msg' => '']]]
                );
            } catch (\Throwable $e) {
                Yii::error("[CommonPayOrder] 写 err_msg 失败 #{$this->id}: " . $e->getMessage(), 'payment');
            }
            return false;
        }

        try {
            // 4. 锁内刷新当前实例，双重检查(锁定读:余额支付在自己的事务里调进来,普通 refresh 读到的是事务快照,
            //    看不见等锁期间关单任务刚提交的 is_cancel=1,会把已关闭的单重新置为已付)
            $this->reloadLatest();
            if ($this->is_cancel == 1) {
                // 锁外刚检查过还是取消了 —— 说明就在这几毫秒里被 AutoCancelOrderTask 关掉,
                // 是这个竞态最典型的形态,同样要留证
                $this->refundPaidAfterCancel($transactionId, $paymentClass, $paidTradeNo);
                return false;
            }
            if ($this->is_pay == 1) {
                Yii::warning("[CommonPayOrder::paySuccess] 锁内检查：订单 {$this->order_no} 已支付，跳过。");
                return true;
            }

            // 4.5 金额比对(锁内、refresh 之后,用的是最新的 pay_price)。
            //     比对失败**不写任何支付流水**:钱确实到了但不属于这张单的当前金额,
            //     该退还是该按实付放行是业务决定,这里只留痕、告警、拒绝。
            $paidAmount = $paidAmount === null ? '' : trim($paidAmount);
            if ($paidAmount !== '') {
                // PHP 8 的 bccomp 遇到非数字串会抛 ValueError(\Error),下面的 catch (\Exception) 接不住 → 回调 500。
                // 网关不会给非数字,但这是公网入口:格式不对只记日志、跳过比对,不能因此把回调打挂。
                if (!is_numeric($paidAmount)) {
                    Yii::error("[CommonPayOrder::paySuccess] 订单 {$this->order_no} 回调金额不是数字,跳过比对: '{$paidAmount}'", 'payment');
                } elseif (bccomp($paidAmount, (string) $this->pay_price, 2) !== 0) {
                    $this->flagAmountMismatch($transactionId, $paidAmount);
                    return false;
                }
            }

            // 5. 先写入支付流水信息（不标记 is_pay，防止回调失败时状态不一致）
            $this->pay_at = time();
            $this->transaction_id = $transactionId;
            if ($paymentClass) {
                $this->payment = $paymentClass;
            }
            if ($paidTradeNo !== null && trim($paidTradeNo) !== '') {
                $this->out_trade_no = trim($paidTradeNo);
            }
            $this->save(false);

            // 6. 执行业务回调（Order 状态变更、事件触发、各插件监听器）
            //
            // 这里**不再按 order_class 反射**。本系统所有订单一律走核心 Order
            // (唯一写入点 PostCreationStage 写死 Order::class),反射带来的只有坏处:
            // 把依赖藏进数据库里的字符串,IDE 的"查找引用"和重构工具完全看不见 ——
            // 类一改名,历史待支付单就永久失效(反射不出来 → 钱收了订单不置已付),
            // 而且只在支付回调的 catch 里留一行日志,前台毫无异常。
            //
            // order_class 字段保留,仍照常写入 —— 排查时它是唯一能看出这单当初
            // 走的哪条回调的痕迹,只是不再拿它做分派。
            // 同理锁定读订单:调用方事务的快照里订单可能还是「待付款」,其实已被关单任务关掉
            $order = Order::find()->where(['id' => $this->order_id])->forUpdate()->one();
            if (!$order) {
                throw new \Exception("支付单 {$this->order_no} 找不到对应订单 order_id={$this->order_id}");
            }
            $order->pay($this);

            // 6.5 业务回调后复核:订单已关闭、也没被置成已付 —— 说明它在上面「没取消」的检查之后、改订单之前
            //     被关掉了(顾客手动取消、后台关单都不抢本支付单的锁)。pay() 对已关闭的单只是跳过,以前这里照样
            //     标已付、回成功:钱收了、单关了、不退款、也不告警。与「取消先到」一样按已取消后收到支付留证。
            //     只认「已关闭 + 未付」:其余没置成已付的情况(如订单 save 失败)见第 7 步的注释,行为不变
            $after = Order::find()->where(['id' => $this->order_id])->forUpdate()->one();
            if ($after && (int)$after->status === Order::STATUS_CLOSED && (int)$after->pay_status === Order::PAY_STATUS_UNPAID) {
                $this->refundPaidAfterCancel($transactionId, $paymentClass, $paidTradeNo);
                return false;
            }

            // 7. 标记 is_pay=1
            // ⚠ 注意:上面 pay() 的返回值目前**没有被检查**。它返回 false 的唯一场景是
            //    订单 save() 失败,那时钱已收、订单没置为已支付,而这里照样标 is_pay=1
            //    → 网关不会重试回调 → 支付单说已付、订单说未付的分裂状态。
            //    改成检查返回值是全局行为变化(所有订单所有网关),需单独评估后再动。
            $this->is_pay = 1;
            if (!$this->save(false)) {
                Yii::error("[CommonPayOrder::paySuccess] 标记 is_pay 失败: " . json_encode($this->getErrors()));
            }

        } catch (\Exception $e) {
            Yii::error("[CommonPayOrder::paySuccess] 异常: " . $e->getMessage());
            return false;
        } finally {
            LockHelper::unlock('COMMON_PAY_ORDER_' . $this->id, $lockToken);
        }

        return true;
    }

    /**
     * 「回调金额 ≠ 订单应付」—— 留证并告警,拒绝置为已支付。
     * 运营可查:SELECT * FROM common_pay_order WHERE is_pay=0 AND err_msg LIKE '回调金额不符%'
     */
    private function flagAmountMismatch(string $transactionId, string $paidAmount): void
    {
        $msg = "回调金额不符 实付={$paidAmount} 应付={$this->pay_price} transaction_id={$transactionId}";
        Yii::error("[CommonPayOrder::paySuccess] 🔴 订单 {$this->order_no}(#{$this->id}){$msg}"
            . " —— 已拒绝置为已支付,需人工核对(改价/换券后用旧收银台付款是典型成因)", 'payment');
        try {
            static::updateAll(
                ['err_msg' => $msg],
                ['and', ['id' => $this->id], ['or', ['err_msg' => null], ['err_msg' => '']]]
            );
        } catch (\Throwable $e) {
            Yii::error("[CommonPayOrder] 写 err_msg 失败 #{$this->id}: " . $e->getMessage(), 'payment');
        }
    }

    /**
     * 按数据库最新已提交数据重载(锁定读)。paySuccess 可能在调用方事务里执行(余额支付、收银台代下单),
     * 可重复读下普通 refresh() 返回事务快照,锁内复检就形同虚设。
     */
    private function reloadLatest(): void
    {
        $fresh = static::find()->where(['id' => $this->id])->forUpdate()->one();
        if ($fresh) {
            static::populateRecord($this, $fresh->getAttributes());
            $this->afterRefresh();
        }
    }

    /**
     * 「订单已取消,但钱到了」—— 原路退回,并留证告警。
     *
     * 这是一个**真实发生过**的竞态(线上支付单 45546,¥299):
     *   12:33:28 expire_at 到期
     *   12:34:00 AutoCancelOrderTask 关单
     *   12:51:51 用户完成支付,微信真实流水 4500000286202607311727026627
     * 各网关的 handleCallback 都丢弃 paySuccess 的返回值,拿到 false 也照样回 SUCCESS、不再重推,
     * OrderPayCheckTask 又只看未取消的单 —— 所以在这里一处收口。
     *
     * 2026-09-26 起不再只留证等人工处理,钱按原支付方式退回(用户定;单已关、库存和券已退回,
     * 没有「恢复订单」这条路,通行做法也是原路退款):
     *   · 支付单记下这笔钱(已付、流水号、实际收款的支付方式、网关单号),仍保持已取消;
     *   · 建一条 paid_after_cancel 退款任务,由 RefundTask 按原支付方式退回;
     *   · 订单保持已关闭、不动 —— 它从没按已付处理过(没发货、没记业绩、没分佣),退款成功后也不碰它。
     * 幂等:同一张支付单只建一条退款任务(createRefundTask 按 来源 + 支付单 id 判重),网关重复回调也只退一次。
     * 余额支付:paySuccess 返回 false 后调用方整笔回滚,余额没扣,这里写的也一起回滚,不会多退。
     * 痕迹仍写进 err_msg,运营可查:
     *   SELECT * FROM common_pay_order WHERE is_cancel=1 AND err_msg LIKE '已取消后收到支付%'
     */
    private function refundPaidAfterCancel(string $transactionId, ?string $paymentClass, ?string $paidTradeNo): void
    {
        $msg = "已取消后收到支付 transaction_id={$transactionId} 金额={$this->pay_price}";
        try {
            // 记下这笔钱:退款要按实际收款的支付方式、网关单号去退,createRefundTask 也只认已付的支付单
            $paid = ['is_pay' => 1, 'pay_at' => time(), 'transaction_id' => $transactionId];
            if ($paymentClass) {
                $paid['payment'] = $paymentClass;
            }
            if ($paidTradeNo !== null && trim($paidTradeNo) !== '') {
                $paid['out_trade_no'] = trim($paidTradeNo);
            }
            static::updateAll($paid, ['id' => $this->id, 'is_pay' => 0]);
            if (bccomp((string)$this->pay_price, '0', 2) > 0) {
                (new \app\services\RefundTaskService())->createRefundTask(
                    $this->order_id, $this->pay_price, CommonPayRefund::SOURCE_PAID_AFTER_CANCEL, $this->id
                );
                $msg .= ',已发起原路退款';
            }
        } catch (\Throwable $e) {
            $msg .= ',自动退款没发起成功,需人工退款:' . mb_substr($e->getMessage(), 0, 80);
        }
        Yii::error("[CommonPayOrder::paySuccess] 🔴 订单 {$this->order_no}(#{$this->id}){$msg}", 'payment');
        try {
            // 只补空的,不覆盖已有的错误信息;不走 save() 以免触发校验/事件
            static::updateAll(
                ['err_msg' => mb_substr($msg, 0, 250)],
                ['and', ['id' => $this->id], ['or', ['err_msg' => null], ['err_msg' => '']]]
            );
        } catch (\Throwable $e) {
            Yii::error("[CommonPayOrder] 写 err_msg 失败 #{$this->id}: " . $e->getMessage(), 'payment');
        }
    }

    /**
     * @deprecated 请使用 paySuccess() 代替。保留此方法是为了兼容可能的外部调用。
     */
    public function pay()
    {
        return $this->paySuccess($this->transaction_id ?: '', $this->payment ?: null);
    }

    /**
     * 【新增】关联退款流水表
     * @return \yii\db\ActiveQuery
     */
    public function getRefundLogs()
    {
        return $this->hasMany(CommonPayRefund::class, ['common_pay_order_id' => 'id']);
    }

    /**
     * 【新增】获取已退款总金额 (包含：成功、处理中、待处理)
     * 排除“退款失败”的记录，因为失败的额度应该释放出来允许再次申请
     * @return float
     */
    public function getRefundedAmount()
    {
        // 注意：这里必须包含 STATUS_PENDING 和 STATUS_PROCESSING
        // 否则并发申请时，第一笔还在处理中，第二笔就会认为额度够用，导致超退
        $sum = $this->getRefundLogs()
            ->where(['is_delete' => 0])
            ->andWhere([
                'in',
                'status',
                [
                    CommonPayRefund::STATUS_PENDING,
                    CommonPayRefund::STATUS_PROCESSING,
                    CommonPayRefund::STATUS_SUCCESS
                ]
            ])
            ->sum('refund_price');

        return (float) $sum;
    }

    /**
     * 这笔支付现在还能退多少:实付 − 已占用(已退 + 在退 + 待退),与建退款任务时的额度守卫同一口径,不会是负数。
     * 关单只退这一部分 —— 售后先退过其中几件的单,关单退剩下的,不会超退。
     * 算完马上要建退款的,调用前先把支付单行锁住(FOR UPDATE),不然并发下读到的额度可能已经变了
     */
    public function getRefundableAmount(): string
    {
        $left = bcsub((string)$this->pay_price, (string)$this->getRefundedAmount(), 2);
        return bccomp($left, '0', 2) > 0 ? $left : '0.00';
    }

    /**
     * @param $order_no
     */
    public static function orderCancel($order_no, $order_detail_id = 0, $refund_price = 0)
    {
        $key = self::find()->andWhere(['order_no' => $order_no, 'is_delete' => 0])->select(['key'])->scalar();
        if (!$key) return 0;

        // 使用原子累加而非覆盖，防止多次部分退款丢失金额
        return Yii::$app->db->createCommand()->update(
            self::tableName(),
            [
                'is_need_refund' => 1,
                'refund_order_detail_id' => $order_detail_id,
                'refund_price' => new \yii\db\Expression("refund_price + :rp", [':rp' => $refund_price]),
            ],
            ['key' => $key, 'is_pay' => 1]
        )->execute();
    }

    public function getOrderDetail()
    {
        return $this->hasOne(OrderDetail::class, ['id' => 'refund_order_detail_id']);
    }
}
