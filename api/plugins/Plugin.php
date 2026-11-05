<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:57
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

namespace app\plugins;


abstract class Plugin
{
    const TYPE_COMMISSION = 0; // 分润类
    const TYPE_MARKET = 1;     // 营销类
    const TYPE_TOOL = 2;       // 工具类
    const TYPE_CUSTOM = 3;     // 客户定制类(面向特定客户的定制功能,与通用插件区分)

    abstract public function getLabel();

    abstract public function getName();

    /**
     * 插件默认 Logo，统一使用 Craddl C 品牌图标。
     */
    public function getLogo()
    {
        return \app\helpers\PluginHelper::getDefaultLogoUrl();
    }

    abstract public function getType();

    abstract public function getVersion();

    abstract public function getHistoryVersion();

    abstract public function getDsc();

    /**
     * 获取移动端链接
     * @return array
     */
    abstract public function getLinks();

    /**
     * 声明本插件在【商家后台(mch)】的菜单/路由。
     *
     * - 默认空数组 = 本插件不向商家后台注入任何菜单。
     * - 由 mch MenuController 按【当前商城已开通插件】聚合下发，前端据此动态注册路由 + 渲染菜单。
     * - 节点结构(与 vue-router 对齐，component 为字符串，由前端 loadView 映射真实组件)：
     *     [
     *       'path'      => '/group',                 // 路由路径
     *       'name'      => 'GroupBuy',               // 路由名(全局唯一)
     *       'component' => 'Layout',                 // 'Layout'=后台外壳 | 'ParentView'=分组透传 | 其它=@/views/{值}.vue
     *       'redirect'  => '/group/index',           // 可选
     *       'hidden'    => false,                    // 可选，true=不在菜单显示但路由可达
     *       'meta'      => ['title'=>'商家团购','icon'=>'el-icon-s-shop','roles'=>['manager']], // roles 可选
     *       'children'  => [ ...同结构... ],
     *     ]
     * - ⚠️ 组件本身仍需存在于 mch 前端(mch/src/views/...)；本方法只声明结构/顺序/层级，不搬运组件。
     *
     * @return array 顶层菜单节点数组
     */
    public function getMchMenus(): array
    {
        return [];
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 16:59
     * @Note:获取事件监听器
     */
    /**
     * 声明本插件在【商家端移动工作台】的快捷入口。
     *
     * - 默认空数组 = 不注入任何入口
     * - 由 mch 的 MchActionsForm 按【当前商城已开通插件】聚合下发,与内置入口同一套过滤规则
     * - 节点结构与内置入口一致:
     *     ['name'=>'代客下单', 'icon'=>'order', 'color'=>'#FF6B35', 'bgColor'=>'#FFF0EA',
     *      'url'=>'/plugins3/dining/agent', 'types'=>[2], 'managerOnly'=>false]
     *   · types       适用商户主体类型(1线上/2门店/4个人租户),空=全主体
     *   · managerOnly true=仅店长可见
     * - ⚠️ 页面本身仍需存在于 uniapp;本方法只声明入口,不搬运页面
     *
     * @return array
     */
    public function getMchWorkbenchActions(): array
    {
        return [];
    }

    abstract public function getListeners();

    /**
     * 声明本插件依赖的其它插件（按 plugin name 列出）。
     *
     * - 默认空数组表示无依赖
     * - 用于：
     *   1) 卸载校验：被依赖的插件被卸载前提示存在依赖关系
     *   2) 启用顺序：依赖项先初始化
     *   3) 文档化：让"插件之间谁依赖谁"在源码层有正式声明，避免"反向 use"
     *
     * 约定：
     *   - mch 是基础设施，所有商户业务插件都依赖它
     *   - mch 自身不应该依赖任何业务插件（如出现 mch 依赖 hotel 即为架构污染）
     *
     * @return string[] 例如 ['mch']
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * 插件图标（element-ui 图标类名，如 'el-icon-share'）。
     *
     * 用于「应用中心」列表展示。相比托管 PNG logo，矢量图标不依赖文件/host，
     * 不会 404 / 跨域 / 混合内容，且无需设计师出图，换肤可上色。
     * 默认空串表示未声明，前端回退到「真实上传 logo → 首字母彩色占位」。
     *
     * @return string 例如 'el-icon-share'
     */
    public function getIcon(): string
    {
        return '';
    }

    /**
     * 声明本插件"不可清空"的表（用于 data/clear-db 维护命令的白名单）。
     *
     * 默认策略：未声明的本插件表 = 业务/流水 = 可被清空。
     * 仅当某张表丢失会导致"商家要重新配置/系统功能失常"时，才在此处声明。
     *
     * - 表名不含 jxmall_ 前缀（如 'plugin_mch_cat'，不是 'jxmall_plugin_mch_cat'）
     * - DataController::actionClearDb() 会扫描所有插件汇总此列表
     * - 加新表时只改这里，不再需要改 DataController
     *
     * @return string[] 例如 ['plugin_mch', 'plugin_mch_cat']
     */
    public function getProtectedTables(): array
    {
        return [];
    }

    /**
     * 声明本插件的"品牌词库" key => 默认词。
     *
     * - 默认空数组表示本插件无自定义名词
     * - key 必须带插件名前缀（点号分隔），如 'aicare.point'，
     *   避免与核心词库 / 其它插件的 key 冲突
     * - 商户可在后台「词库配置」把这些词改成自有叫法
     * - uniapp 端通过 $noun('aicare.point') 读取，默认词由后端下发
     *
     * @return array 例如 ['aicare.point' => '积分', 'aicare.reward' => '奖励']
     */
    public function getNouns(): array
    {
        return [];
    }

    /**
     * 声明本插件词条的展示元数据（可选）—— 供后台「词库配置」规范化展示。
     * 每个 key 一组数据：
     *  - 'type'         类型：'noun'名词 / 'phrase'短语 / 'title'标题 / 'template'文案(带占位符)
     *                   缺省由 NounHelper 按 key 启发式兜底（含 title→标题，否则短语）
     *  - 'label'        用途说明（文案模板必备，默认值是占位符串、不加说明看不懂场景）
     *  - 'placeholders' 占位符说明 token=>释义（如 ['id'=>'对方用户ID']），后台编辑时提示
     * 「展示示例」无需声明：后端用默认值 + 样例数据自动 render 得出。
     *
     * @return array 例如 [
     *   'withdraw.xxx_content' => ['type'=>'template', 'label'=>'…·明细文案', 'placeholders'=>['id'=>'对方ID']],
     *   'withdraw.title_balance' => ['type'=>'title'],
     * ]
     */
    public function getNounMeta(): array
    {
        return [];
    }

    public static function syncGoods($id)
    {
    }

    /**
     * 1. 获取核销码详情 (扫码查询阶段)
     * 商户扫码后调用此方法，展示券/订单的详细信息供商户确认
     *
     * @param string $code 核销码
     * @param int $mch_id 当前操作的商户ID (用于鉴权，非本店券应返回不可用或不返回)
     * @return array|false
     *      - 成功返回标准结构数组:
     *        [
     *           'type' => 'plugin_name', // 插件标识
     *           'is_allow' => true/false, // 是否允许核销 (例如：过期了就是false)
     *           'msg' => '可核销', // 提示文案
     *           'data' => [
     *               'name' => '商品名称',
     *               'image' => '图片url',
     *               'info' => '规格/日期等',
     *               'user_name' => '持券人',
     *               'order_no' => '订单号'
     *           ]
     *        ]
     *      - 失败/未找到返回 false
     */
    /**
     * 下单预览扩展钩子
     *
     * 当前插件需要在下单页注入额外 UI 组件时重写此方法。
     * 返回值会合并到 preview 响应的 plugin_extensions 字段，由前端负责渲染。
     *
     * @param array $goodsList 购物车商品列表 [['goods_id'=>..., 'num'=>...], ...]
     * @param int   $mallId    商城 ID
     * @param int   $userId    当前用户 ID
     * @return array  空数组 = 无扩展；非空 = 扩展项列表
     */
    public function getOrderExtensions(array $goodsList, int $mallId, int $userId): array
    {
        return [];
    }

    /**
     * 声明本插件订单类型的「行为特征」,供核心与底座据此决策,取代散落各处的插件名硬编码。
     *
     * key = 订单类型(`Order.type`,与插件名同名);商品级特征按 `Goods.type` 索引,二者同名。
     *
     * 订单级特征:
     *   · self_settled              true = 本类订单自主推进 settlement_status,核心 OrderSettleTask 不碰
     *   · block_confirm             true = 禁止走基础订单管线手动确认收货(线下核销/退房前)
     *   · block_ship                true = 禁止走基础物流体系发货(专有后台/专门设备处理)
     *   · mch_settlement_exempt     true = 商家货款不由 mch 结算(收益另有归属),mch 的结算监听/兜底任务跳过
     *   · exportable                true = 后台订单导出未指定类型时,默认把本类订单也导出去
     *   · express_income_to_mch     true = 商家单的 express_price(运费/附加费)不论配送方式一律归商家。
     *                               核心默认只有快递、同城的运费归商家,自提归平台;点单的餐位费/打包费
     *                               塞在 express_price 里且订单都是自提,不声明就会被下单口径记成平台收入
     *   · no_user_after_sale        true = C 端不走通用「申请售后」(有自己的申诉 / 取消退款流程):
     *                               订单详情不显示申请售后按钮,核心 RefundService::apply 也直接拒绝
     *   · fulfillment_text          真实履约推送文案(无商品摘要时)
     *   · fulfillment_text_with_summary  同上,含 {summary} 占位符,有商品摘要时优先使用
     *
     * 商品级特征:
     *   · goods_protected           true = 该类商品不可被编辑/删除/上下架(如收银台的默认收银商品)
     *   · auto_off_shelf            true = 本类商品参与「售罄(stock<=0)自动下架」(core/cron/GoodsDownTask)
     *                               默认不参与 —— 核心只自动下架自己的 normal 商品。插件商品的库存
     *                               语义由插件自定,占位商品(账单缴费/账户充值/兑换……)库存恒为 0,
     *                               默认纳管等于把它们静默下架、功能瘫痪还查不出原因(真出过事)。
     *                               只有「库存卖光了就该从货架消失」的真实商品才声明,例如 yuda 套餐。
     *                               漏声明的代价很轻:商品继续挂着,用户下单时被库存校验拦住。
     *   · virtual_goods             true = 无库存概念(与上面互斥,不必同时声明)。
     *                               ⚠ 自 2026-08 起本标记与 GoodsDownTask **无关** —— 该任务已改白名单,
     *                               不声明 auto_off_shelf 本就不会被下架。此标记仅余语义登记用途。
     *   · standalone                true = 专属商品:只能走插件自己的入口下单。商城通用详情页会跳走、
     *                               加购/普通下单被拒、通用商品列表不展示(核心 StandaloneGoodsGuard)。
     *                               凡是「用商品编辑器建、但不该被当普通商品买」的插件商品都要声明
     *                               (来信、充值、缴费、收银台占位……)。方案见 docs/方案-插件专属商品类型隔离.md
     *   · entry_path                专属商品的 C 端入口,如 '/plugins3/aidb/letter/order?goods_id={goods_id}'。
     *                               推广选品、商品海报、通用详情页的旧链接都改指向它;不对外售卖的占位商品不写
     *   · offline_payment           true = 当面付/线下收款,商家看板里计入「扫码收款」而非「线上成交」
     *
     * 汇总见 app\services\order\OrderBehaviorRegistry。默认空数组 = 本插件订单无特殊行为。
     *
     * @return array 例如 ['cashier' => ['self_settled' => true, 'goods_protected' => true]]
     */
    public function getOrderBehaviors(): array
    {
        return [];
    }

    /**
     * 声明本插件产生的「订单类型」,用于 C 端订单列表的「按类型可配置展示」。
     *
     * - 默认空数组 = 本插件不产生独立订单类型(不会出现在「订单列表配置」可选项里)
     * - 每项结构:['key'=>唯一键, 'name'=>默认中文名, 'plugins'=>[匹配的 order.plugin 值...], 'icon'=>'']
     *   · key   全局唯一,一般用插件名
     *   · name  默认展示名,商户可在后台「订单列表配置」改名
     *   · plugins 后端按 order.plugin 过滤用(一个类型可匹配多个 plugin 值);省略则默认 [插件名]
     * - 汇总见 app\services\order\OrderTypeRegistry
     *
     * @return array 例如 [['key'=>'group','name'=>'同城团购订单','plugins'=>['group'],'icon'=>'']]
     */
    public function getOrderTypes(): array
    {
        return [];
    }

    /**
     * 声明本插件提供的「可提现钱包(用户账户)」,由 withdraw 插件的通用钱包底座统一承载。
     *
     * - 默认空数组 = 本插件不提供独立钱包
     * - 每项结构:['key'=>唯一键, 'name'=>展示名, 'withdrawable'=>true]
     *   · key 全局唯一,约定带插件前缀(如 'xwy.channel'),防跨插件冲突
     *   · 可选 'noun':品牌词库 key(对齐「插件.字段」约定);省略默认=key。如货款 key='mch.funds'、noun='mch.mch.funds'
     *   · 余额存核心 user_wallet 表,经核心 app\services\WalletService 原子存取
     *     (底座在核心,声明钱包不需依赖 withdraw;withdraw 只是消费方,负责把它接进提现链路)
     * - 汇总见 app\services\WalletRegistry,提现链路经 AssetService 自动打通
     *
     * @return array 例如 [['key'=>'xwy.channel','name'=>'渠道专户','withdrawable'=>true]]
     */
    public function getWallets(): array
    {
        return [];
    }

    /**
     * 声明本插件提供的 AI 驱动(供应商)。
     *
     * - 默认空数组 = 本插件不扩展 AI 驱动
     * - 核心已内置 mock / deepseek / doubao / claude,通用大模型不需要在此重复声明
     * - 仅当要接入核心没有的供应商(如自研模型、行业专用模型)时才声明
     * - class 必须实现 app\services\ai\AiProviderInterface
     * - key 建议带插件前缀防跨插件重名;与内置 key 冲突时以内置为准
     * - 汇总见 app\services\ai\AiProviderRegistry;配置入口统一在
     *   后台「商城管理 → AI 配置」,插件无需自建配置页
     *
     * @return array 例如 [['key'=>'xxx.qwen','label'=>'通义千问','class'=>QwenProvider::class]]
     */
    public function getAiProviders(): array
    {
        return [];
    }

    /**
     * 声明本插件提供的文生图驱动。
     *
     * 结构与 getAiProviders() 一致：
     *   [['key' => 'xxx.sd', 'label' => 'Stable Diffusion', 'class' => SdProvider::class]]
     *
     * class 必须实现 app\services\ai\ImageProviderInterface，否则注册表会拒绝并告警。
     * key 建议带插件前缀（xxx.sd），避免跨插件重名互相覆盖。
     */
    public function getImageProviders(): array
    {
        return [];
    }

    /**
     * 声明本插件提供的 ASR(语音识别)驱动。
     *
     * - 默认空数组 = 本插件不扩展 ASR 驱动
     * - 核心已内置 tencent(腾讯云录音文件识别) / mock,通用场景不需要在此重复声明
     * - 仅当要接入核心没有的语音识别供应商时才声明
     * - class 必须实现 app\services\ai\AsrProviderInterface(提交/查询两段式异步接口,
     *   不能用阻塞轮询——详见该接口的注释,涉及 Go 调度器 FastCGI 派发的硬超时约束)
     * - key 建议带插件前缀防跨插件重名;与内置 key 冲突时以内置为准
     * - 汇总见 app\services\ai\AsrProviderRegistry;配置入口统一在
     *   后台「商城管理 → AI 配置」,插件无需自建配置页
     *
     * @return array 例如 [['key'=>'xxx.baidu','label'=>'百度语音识别','class'=>BaiduAsrProvider::class]]
     */
    public function getAsrProviders(): array
    {
        return [];
    }

    /**
     * 声明本插件的定时任务(Task 子类的完整类名列表)。
     *
     * - 默认空数组 = 本插件无定时任务
     * - 取代"扫描 plugins/{name}/cron 目录":任务改为在此显式登记,由 Manager::getJobs()
     *   统一聚合(核心任务见 app\cron\CronRegister)。仅【已安装插件】声明的任务会被加载。
     * - 与 getListeners() 同构。
     *
     * @return string[] 例如 [\app\plugins\xwy\cron\XwySettleDueTask::class]
     */
    public function getCrons(): array
    {
        return [];
    }

    /**
     * 声明本插件接管的「结算目标处理器」(payout handler)。
     *
     * - 返回 ['payoutType' => 实现类全名],实现类须 implements app\services\contracts\PayoutHandlerInterface
     * - 由 app\services\PayoutHandlerRegistry 聚合;核心 CommissionService::settle() 命中已注册的
     *   payoutType 时委托给对应 handler,使核心对插件私有的结算实现保持零引用
     * - 默认空数组 = 本插件不接管任何结算目标(仍可使用核心内置的 balance/integral/price/扩展钱包)
     * - 与 getWallets()/getCrons() 同构,仅【已安装插件】的声明会生效
     *
     * @return array<string,string> 例如 ['split' => app\plugins\cashier\services\ExternalSplitPayoutHandler::class]
     */
    public function getPayoutHandlers(): array
    {
        return [];
    }

    /**
     * 钱包「逐用户可见性」钩子:某钱包是否对某用户在 C 端展示/可选(总览、提现源列表等通用面统一遵守)。
     * 默认全可见;声明钱包的插件如需按用户控制(如 xwy.channel 走后台逐用户开关 show_channel_entry),覆写本方法。
     * ⚠ 只影响「是否显示/可选」,不影响钱包能否被入账(入账由业务侧决定)。出错应尽量返回 true,避免误藏。
     *
     * @param string $walletKey 钱包 key(如 xwy.channel)
     * @param int    $userId    用户 id
     */
    public function isWalletVisible(string $walletKey, int $userId): bool
    {
        return true;
    }

    public function getVerifyCodeDetail($code, $mch_id)
    {
        return false;
    }

    /**
     * 2. 执行核销 (确认核销阶段)
     * 商户在前端点击“确认”后调用
     *
     * @param string $code 核销码
     * @param int $mch_id 商户ID
     * @param int $clerk_id 操作员ID (核销员)
     * @return bool
     */
    public function verifyCode($code, $mch_id, $clerk_id)
    {
        // 默认抛出异常，强制子类实现（如果子类支持核销的话）
        return false;
    }
}
