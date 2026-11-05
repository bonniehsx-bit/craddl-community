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

namespace app\plugins\withdraw;

/**
 * 提现插件
 *
 * 解决两个核心痛点：
 *  1. 控制"什么类型的用户用什么方式提现" —— 由「提现渠道」的 user_types 字段决定（商户 / 普通用户）。
 *  2. 每种提现方式的资料可配置对应的打款网关 —— 渠道 1:1 绑定 fund_class(反射) + onboarding_override(资料schema)。
 *
 * 与老 Cash 链路完全脱钩，独立内聚，灵活性拉满：
 *  - 同一个"提现到银行卡"可建多条渠道走不同网关，做灰度/AB；
 *  - 改一个下拉就能给某渠道切网关，不动代码。
 */
class Plugin extends \app\plugins\Plugin
{
    public function getName()
    {
        return 'withdraw';
    }

    public function getLabel()
    {
        return '提现中心';
    }

    /**
     * 应用中心图标（element-ui 图标类名）。矢量图标不依赖文件/host，免设计、免托管。
     */
    public function getIcon(): string
    {
        return 'el-icon-wallet';
    }

    public function getLogo()
    {
        return parent::getLogo();
    }

    public function getType()
    {
        return \app\plugins\Plugin::TYPE_TOOL;
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function getHistoryVersion()
    {
        return [];
    }

    public function getDsc()
    {
        return '可配置的提现渠道：按用户类型授权提现方式，每种方式绑定打款网关与资料要求';
    }

    public function getLinks()
    {
        $links = [
            // 我的钱包(通用总览):列出全部钱包余额,点进通用明细/提现。新增钱包零改动自动纳入。
            ['name' => '我的钱包', 'url' => '/plugins2/withdraw/wallet'],
            ['name' => '提现', 'url' => '/plugins2/withdraw/withdraw'],
            // 资产中心页（复刻自老 new/* 中心页，供逐步替换硬编码跳转）
            ['name' => '余额中心', 'url' => '/plugins2/withdraw/balance'],
            ['name' => '佣金中心', 'url' => '/plugins2/withdraw/price'],
            ['name' => '积分中心', 'url' => '/plugins2/withdraw/integral'],
            ['name' => '提现记录', 'url' => '/plugins2/withdraw/records'],
            // 充值(提现的反向链路):各业务插件的「余额/积分不足去充值」都指到这里
            ['name' => '账户充值', 'url' => '/plugins2/withdraw/recharge'],
            ['name' => '充值记录', 'url' => '/plugins2/withdraw/recharge-records'],
            // 资产互转:三个页面都不需要任何参数,是独立功能页 —— 之前漏挂了,
            // 运营在链接选择器里根本配不出这几个入口
            ['name' => '余额转赠', 'url' => '/plugins2/withdraw/transfer-balance'],
            ['name' => '积分转赠', 'url' => '/plugins2/withdraw/transfer-integral'],
            ['name' => '余额转积分', 'url' => '/plugins2/withdraw/balance-to-integral'],
            // 注:钱包明细 /plugins2/withdraw/detail 需要 asset_type 参数,
            //     裸挂进去点开是空页,故不列 —— 从「我的钱包」点进去才有上下文
            // 直达指定状态 tab：选了哪个，进页面就默认筛到那个 tab（与 records.vue filters 对应）
            ['name' => '提现记录·待审核', 'url' => '/plugins2/withdraw/records?status=0'],
            ['name' => '提现记录·待打款', 'url' => '/plugins2/withdraw/records?status=1'],
            ['name' => '提现记录·打款中', 'url' => '/plugins2/withdraw/records?status=2'],
            ['name' => '提现记录·已到账', 'url' => '/plugins2/withdraw/records?status=3'],
            ['name' => '提现记录·已驳回', 'url' => '/plugins2/withdraw/records?status=4'],
            ['name' => '提现记录·已退回', 'url' => '/plugins2/withdraw/records?status=6'],
        ];

        // 直达指定充值商品：一个充值商品 = 一种到账资产(余额充值 / 积分充值)。
        // 不列出来的话,运营只能配到充值总入口,用户进去永远停在第一个 tab 上。
        // 与 emotion / lottery 同套路:查库失败就静默降级为只有总入口,绝不让链接选择器 500。
        try {
            $mallId = (int)\Yii::$app->mall->id;
            $rows = \app\models\Goods::find()
                ->where([
                    'mall_id'   => $mallId,
                    'type'      => \app\plugins\withdraw\listeners\RechargeGoodsListener::GOODS_TYPE,
                    'status'    => \app\models\Goods::STATUS_UP,
                    'is_delete' => 0,
                ])
                ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
                ->select(['id', 'name'])
                ->asArray()
                ->all();

            foreach ($rows as $r) {
                $links[] = [
                    'name' => '账户充值·' . $r['name'],
                    'url'  => '/plugins2/withdraw/recharge?goods_id=' . $r['id'],
                ];
            }
        } catch (\Throwable $e) {
            // 无 mall 上下文(CLI)/ 表还没建 —— 保持只有总入口
        }

        return $links;
    }

    public function getListeners()
    {
        return [
            // 提现统计应答:核心(后台首页 / C端首页 / 管理中心 / 佣金中心)派发
            // WithdrawStatEvent 问「提现中/已提现/待审核单数」,由本插件作答。
            // 核心因此不需要认识 WithdrawOrder,插件卸载后那些位置自动回落 0。
            \app\plugins\withdraw\listeners\WithdrawStatListener::class,
            // 快付代付异步通知落地:核心只抛 fast_agent_pay 事件,由本插件按 merOrderNo
            // 找到提现单并回流打款结果。此前无人监听 → 网关回的 FAIL 被静默丢弃。
            \app\plugins\withdraw\listeners\FastPayAgentNotifyListener::class,
            // 充值到账:挂结算事件而不是支付事件,避开与分润类插件在 PAID 上的顺序竞态,
            // 且后台手动补结算走的也是这个事件,能天然兜住卡单。
            \app\plugins\withdraw\listeners\RechargeSettledListener::class,
            // 充值积分的业务类型登记,否则后台积分明细显示裸 key
            \app\plugins\withdraw\listeners\RechargeIntegralTypeListener::class,
            // 充值商品的配置挂载:让「充值」成为一个正经商品,运营可见可编辑,
            // 编辑时「分销设置」tab 自然出现 —— 要不要给充值分润由分润插件按商品自己决定。
            \app\plugins\withdraw\listeners\RechargeGoodsListener::class,
        ];
    }

    /**
     * 订单列表「按类型可配置展示」声明，详见基类 Plugin::getOrderTypes()。
     * 充值是虚拟商品订单，不发货、付款即完成。
     */
    public function getOrderTypes(): array
    {
        return [[
            'key'     => 'withdraw_recharge',
            'name'    => '账户充值订单',
            'plugins' => ['withdraw'],
            'icon'    => '',
        ]];
    }

    /**
     * 订单/商品行为特征，详见基类 Plugin::getOrderBehaviors()。
     *
     * · virtual_goods —— 充值商品是虚拟档位，goods.stock 恒为 0，无库存概念。
     *   ⚠ 这个标记曾经是**必须**的:GoodsDownTask 早先按黑名单办事，默认把
     *   stock<=0 的商品一律下架，只放过声明了 virtual_goods 的类型 —— 接口
     *   联调时充值商品就是这么被静默下架的(第二单起报「未获取到下单商品列表」)。
     *   该任务已于 2026-08-21 改成白名单(只管 normal + 声明了 auto_off_shelf 的)，
     *   所以现在**不声明也不会被下架**，此处保留仅作语义登记。
     *   ⛔ 反过来说:充值商品永远不要声明 auto_off_shelf。
     * · fulfillment_text* —— 充值订单 deliver_type=none + 付款即完成，会被
     *   PushRealFulfillmentTask 推到微信「发货信息管理」。不声明就落到核心
     *   兜底文案「已核销」，充值说核销读起来莫名其妙。
     *
     * ⛔ 不要声明 self_settled：充值到账靠核心 OrderSettleTask 推进结算后
     *    触发 EVENT_ORDER_SETTLED，RechargeSettledListener 才发钱。一旦声明
     *    自主结算，核心不再推进 → 用户付了钱永远不到账。
     * ⛔ 不要声明 goods_protected：运营需要能在后台编辑/上下架充值商品。
     */
    public function getOrderBehaviors(): array
    {
        return [
            'withdraw_recharge' => [
                'virtual_goods'                 => true,
                // 专属商品:金额按档位,只能在充值页选档位下单;走商城通用下单会按商品价收钱却不建充值单
                'standalone'                    => true,
                'entry_path'                    => '/plugins2/withdraw/recharge?goods_id={goods_id}',
                'fulfillment_text_with_summary' => '{summary} 已到账',
                'fulfillment_text'              => '账户充值已到账',
            ],
        ];
    }

    public function getCrons(): array
    {
        return [
            \app\plugins\withdraw\cron\WithdrawPayoutQueryTask::class,
            \app\plugins\withdraw\cron\WithdrawRechargeGiftTask::class,
        ];
    }

    /**
     * 依赖 mch（基础依赖插件）：UserTypeResolver 读 Mch 判定商户身份。
     * 见 docs §21；运行期仍有 class_exists 兜底，但正式声明依赖避免装载顺序问题。
     */
    public function getDependencies(): array
    {
        return ['mch'];
    }

    /**
     * 声明本插件不可清空的表
     *
     * - plugin_withdraw_channel: 提现渠道配置表，丢失后所有提现方式失效，必须保护
     * - plugin_withdraw_order:   故意不保护，属于提现流水记录，测试时可清空重建
     */
    public function getProtectedTables(): array
    {
        return [
            'plugin_withdraw_channel',
            // 充值档位与充值商品配置都是运营配置(不是业务流水),清库重建会让充值入口空掉
            'plugin_withdraw_recharge_plan',
            'plugin_withdraw_recharge_goods',
        ];
    }

    /**
     * 提现相关页面标题(移动端)品牌词库:后台「词库配置 → 提现中心」分组可逐个改名,前端 $noun('withdraw.title_*')。
     * 默认沿用现叫法;客户可解耦核心词单独命名(如「我的余额」→「渠道服务账户中心」)。
     */
    public function getNouns(): array
    {
        return [
            'withdraw.title_withdraw' => '提现中心', // withdraw/withdraw
            'withdraw.title_records'  => '提现记录', // withdraw/records
            'withdraw.title_wallet'   => '我的钱包', // withdraw/wallet
            'withdraw.title_balance'  => '我的余额', // withdraw/balance
            'withdraw.title_price'    => '我的佣金', // withdraw/price
            'withdraw.title_integral' => '我的积分', // withdraw/integral
            'withdraw.detail_suffix'  => '明细',     // withdraw/detail:钱包名 + 该后缀

            // 各资产页动作/类型标签(逐页专属可配,默认=各页现状原词;提现按钮走核心词 withdraw)。客户在词库改新叫法。
            'withdraw.balance_transfer'      => '转赠',     // 余额页 转赠按钮
            'withdraw.balance_all'           => '全部',     // 余额页 Tab-全部(不筛收支)
            'withdraw.balance_income'        => '收入',     // 余额页 收/支 Tab-收入
            'withdraw.balance_expense'       => '支出',     // 余额页 收/支 Tab-支出
            'withdraw.balance_total_income'  => '累计收入', // 余额页 累计标签
            'withdraw.price_all'             => '全部',     // 佣金页 Tab-全部(不筛收支)
            'withdraw.price_income'          => '收入',     // 佣金页 收/支 Tab-收入(该页无转赠按钮)
            'withdraw.price_expense'         => '支出',     // 佣金页 收/支 Tab-支出
            'withdraw.price_total_income'    => '累计佣金', // 佣金页 累计标签
            'withdraw.integral_transfer'     => '转赠',     // 积分页 转赠按钮
            'withdraw.integral_all'          => '全部',     // 积分页 Tab-全部(不筛收支)
            'withdraw.integral_income'       => '获得',     // 积分页 Tab-获得
            'withdraw.integral_expense'      => '使用',     // 积分页 Tab-使用
            'withdraw.integral_total_income' => '累计获得', // 积分页 累计标签

            // 转赠交易流水描述(BalanceTransferForm/IntegralTransferForm 写入明细的文案,用户在明细可见)。
            // 同时复用为对应转赠/兑换页的完整导航标题(setNavigationBarTitle),页面标题禁止前端拼词、必须走这里可配。默认=原文。
            'withdraw.balance_transfer_title'   => '余额转赠',       // 余额转赠 流水标题 + 页面标题
            'withdraw.integral_transfer_title'  => '积分转赠',       // 积分转赠 流水标题 + 页面标题
            'withdraw.balance_to_integral_title' => '余额转积分',   // 余额转积分 页面标题
            'withdraw.transfer_action'         => '转赠',           // 动作词(提示语/积分明细句中)
            'withdraw.transfer_to'             => '转账给',         // 转出方备注前缀(老核心转赠表单仍用)
            'withdraw.transfer_from'           => '收到转账，来自', // 转入方备注前缀(老核心转赠表单仍用)

            // 转赠/兑换流水文案模板(插件版三表单写入明细,用户可见)。占位符 {key}:数据变量 + 词库key(见 NounHelper::render)。
            //  数据变量:{nickname}对方昵称 {id}对方ID {amount}转赠额 {fee}手续费 {gain}兑得积分 {used}使用余额;词库key:{integral} {balance}
            'withdraw.balance_transfer_out_content' => '转账给：{nickname}(ID:{id})',            // 余额转赠·转出方明细
            'withdraw.balance_transfer_in_content'  => '收到转账，来自：{nickname}(ID:{id})',    // 余额转赠·收款方明细
            'withdraw.transfer_refund_title'        => '转账失败退款',                            // 转赠失败退回·标题
            'withdraw.transfer_refund_content'      => '转账失败，资金退回。收款方用户ID：{id}', // 转赠失败退回·明细
            'withdraw.integral_transfer_out_content' => '转赠{integral}给用户(ID:{id})，转赠:{amount}，手续费:{fee}', // 积分转赠·转出方明细
            'withdraw.integral_transfer_in_content'  => '收到来自用户(ID:{id})的{integral}转赠', // 积分转赠·收款方明细
            'withdraw.b2i_out_content'               => '兑换{gain}{integral}',                   // 余额转积分·扣余额明细
            'withdraw.b2i_in_content'                => '{balance}兑换{integral}，使用{balance}：{used}', // 余额转积分·加积分明细
        ];
    }

    /**
     * 词条元数据（规范化：type + 用途 label + 占位符说明）。
     * 带占位符的流水文案归「文案」(template);无占位符的标题/词由 NounHelper 启发式兜底。
     * 展示示例由后端用默认值自动 render,无需在此声明。
     */
    public function getNounMeta(): array
    {
        return [
            'withdraw.balance_transfer_out_content' => [
                'type' => 'template', 'label' => '余额转赠 · 转出方明细',
                'placeholders' => ['nickname' => '对方昵称', 'id' => '对方用户ID'],
            ],
            'withdraw.balance_transfer_in_content' => [
                'type' => 'template', 'label' => '余额转赠 · 收款方明细',
                'placeholders' => ['nickname' => '对方昵称', 'id' => '对方用户ID'],
            ],
            'withdraw.transfer_refund_title' => [
                'label' => '转赠失败退回 · 标题', // 无占位符,类型走启发式(title)
            ],
            'withdraw.transfer_refund_content' => [
                'type' => 'template', 'label' => '转赠失败退回 · 明细',
                'placeholders' => ['id' => '收款方用户ID'],
            ],
            'withdraw.integral_transfer_out_content' => [
                'type' => 'template', 'label' => '积分转赠 · 转出方明细',
                'placeholders' => ['id' => '对方用户ID', 'amount' => '转赠积分数', 'fee' => '手续费'],
            ],
            'withdraw.integral_transfer_in_content' => [
                'type' => 'template', 'label' => '积分转赠 · 收款方明细',
                'placeholders' => ['id' => '对方用户ID'],
            ],
            'withdraw.b2i_out_content' => [
                'type' => 'template', 'label' => '余额转积分 · 扣余额明细',
                'placeholders' => ['gain' => '兑得积分数'],
            ],
            'withdraw.b2i_in_content' => [
                'type' => 'template', 'label' => '余额转积分 · 加积分明细',
                'placeholders' => ['used' => '使用的余额'],
            ],
        ];
    }
}
