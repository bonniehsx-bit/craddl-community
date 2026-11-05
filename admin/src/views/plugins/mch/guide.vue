<template>
  <div class="app-container guide-page">
    <el-page-header :show-back="false">
      <template slot="content">
        <span style="font-weight:600;">多商户 · 商家运营手册</span>
        <el-tag size="mini" type="info" style="margin-left:10px;font-family:monospace;">订单资金流 / 配置说明 / 运作逻辑 / FAQ</el-tag>
      </template>
    </el-page-header>

    <el-tabs v-model="activeTab" tab-position="left" style="min-height:600px;margin-top:16px;">

      <!-- ① 运作逻辑 -->
      <el-tab-pane label="运作逻辑" name="logic">
        <div class="guide-section">
          <h2>商家订单 · 运作逻辑</h2>
          <el-alert type="info" :closable="false" show-icon style="margin-bottom:20px;"
            title="一句话:钱在「下单」时按商品分润配置算死存快照,商家履约完(确认送达)进待结算,售后期满后系统自动把「货款 + 运费」打进商家账户,平台留成自动留在平台。" />

          <h3>三个角色</h3>
          <el-table :data="roles" border size="small" style="margin-bottom:24px;">
            <el-table-column label="角色" prop="role" width="120" />
            <el-table-column label="职责" prop="desc" />
            <el-table-column label="拿什么钱" prop="money" width="260" />
          </el-table>

          <h3>订单生命周期(同城配送/快递自发货)</h3>
          <el-steps direction="vertical" :active="99" style="margin:16px 0 24px;">
            <el-step title="① 买家下单支付" description="买家付「实付金额 pay_price」。此刻系统按商品分润配置算好:每件商品的商家应得货款 mch_income_price、整单 mch_income、平台留成 platform_income、运费归属 express_income_mch_id —— 全部存进 DB 当快照,后续不再重算。" />
            <el-step title="② 商家开始配送 / 发货" description="商家在商家端点「开始配送」(同城,order/ship)或录快递单「发货」(快递,express-ship)。订单进「待收货」,delivery_status=已发货。" />
            <el-step title="③ 商家确认送达 / 买家确认收货" description="同城单商家点「确认送达」(order/complete),或买家确认收货。订单进「已完成」,置 settlement_status=待结算 + finish_at=当前时间。" />
            <el-step title="④ 售后期满 → 自动结算" description="售后期(mall_setting.order_settle_day 天)满后,核心 OrderSettleTask 定时任务自动结算,触发 ORDER_SETTLED 事件。" />
            <el-step title="⑤ 打款到商家账户" description="mch 结算监听器收到 ORDER_SETTLED,把「货款(快照)+ 运费(全额)」打进商家配置的账户;平台留成自动留在平台。MchSettlementTask 兜底任务补漏。" />
          </el-steps>

          <h3>订单状态机(与代码 enum 一致)</h3>
          <el-table :data="orderStates" border size="small">
            <el-table-column label="status" prop="val" width="70" />
            <el-table-column label="名称" prop="name" width="120" />
            <el-table-column label="说明" prop="desc" />
          </el-table>
        </div>
      </el-tab-pane>

      <!-- ② 资金流与结算 -->
      <el-tab-pane label="资金流与结算" name="fund">
        <div class="guide-section">
          <h2>订单资金流 · 时序图</h2>
          <el-alert type="warning" :closable="false" show-icon style="margin-bottom:20px;"
            title="核心原则:算一次(下单快照)、结一次(确认后打款),中间不重算。这样即使后台改了分润配置,历史订单也不会被算错。" />

          <!-- 时序资金图 (自包含 SVG) -->
          <div class="fund-svg-wrap">
            <svg viewBox="0 0 900 430" width="100%" preserveAspectRatio="xMidYMid meet" font-family="-apple-system,Helvetica,Arial,sans-serif">
              <!-- 三条泳道 -->
              <text x="90"  y="28" text-anchor="middle" font-size="15" font-weight="600" fill="#409EFF">买家</text>
              <text x="450" y="28" text-anchor="middle" font-size="15" font-weight="600" fill="#E6A23C">平台账户</text>
              <text x="810" y="28" text-anchor="middle" font-size="15" font-weight="600" fill="#67C23A">商家账户</text>
              <line x1="90"  y1="40" x2="90"  y2="410" stroke="#409EFF" stroke-width="2" stroke-dasharray="4 4" opacity="0.5"/>
              <line x1="450" y1="40" x2="450" y2="410" stroke="#E6A23C" stroke-width="2" stroke-dasharray="4 4" opacity="0.5"/>
              <line x1="810" y1="40" x2="810" y2="410" stroke="#67C23A" stroke-width="2" stroke-dasharray="4 4" opacity="0.5"/>

              <!-- 定义箭头 -->
              <defs>
                <marker id="arr" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth">
                  <path d="M0,0 L8,3 L0,6 Z" fill="#606266"/>
                </marker>
              </defs>

              <!-- ① 支付 -->
              <text x="270" y="70" text-anchor="middle" font-size="13" fill="#303133">① 支付 实付 pay_price</text>
              <line x1="90" y1="80" x2="445" y2="80" stroke="#606266" stroke-width="1.5" marker-end="url(#arr)"/>
              <text x="90" y="100" text-anchor="middle" font-size="11" fill="#909399">下单即算好快照:</text>
              <text x="90" y="116" text-anchor="middle" font-size="11" fill="#909399">mch_income / platform_income</text>

              <!-- ② 履约 -->
              <rect x="330" y="140" width="240" height="34" rx="6" fill="#FDF6EC" stroke="#F5DAB1"/>
              <text x="450" y="162" text-anchor="middle" font-size="12" fill="#E6A23C">② 履约:开始配送 → 确认送达</text>

              <!-- ③ 售后期满结算 -->
              <rect x="330" y="192" width="240" height="34" rx="6" fill="#ECF5FF" stroke="#B3D8FF"/>
              <text x="450" y="214" text-anchor="middle" font-size="12" fill="#409EFF">③ 售后期满 → 自动结算</text>

              <!-- ④ 打款货款 -->
              <text x="630" y="268" text-anchor="middle" font-size="13" fill="#303133">④ 货款 mch_income_price</text>
              <line x1="450" y1="278" x2="805" y2="278" stroke="#67C23A" stroke-width="1.6" marker-end="url(#arr)"/>

              <!-- ⑤ 打款运费 -->
              <text x="630" y="316" text-anchor="middle" font-size="13" fill="#303133">⑤ 运费 express_price(全额)</text>
              <line x1="450" y1="326" x2="805" y2="326" stroke="#67C23A" stroke-width="1.6" marker-end="url(#arr)"/>

              <!-- 平台留成 -->
              <rect x="360" y="356" width="180" height="46" rx="6" fill="#F0F9EB" stroke="#C2E7B0"/>
              <text x="450" y="376" text-anchor="middle" font-size="12" fill="#67C23A">平台留成 platform_income</text>
              <text x="450" y="393" text-anchor="middle" font-size="11" fill="#909399">= pay_price − mch_income(留平台)</text>
            </svg>
          </div>

          <h3>一笔订单的钱怎么分</h3>
          <div class="formula-box">
            <div class="formula-line">买家实付 <strong>pay_price</strong> <em>= 商品优惠后金额 + 运费 express_price</em></div>
            <div class="formula-line">├─ 商家货款 <strong>mch_income</strong> <em>= Σ 各明细 mch_income_price(已扣平台抽佣的商家实得)</em></div>
            <div class="formula-line">├─ 运费 <strong>express_price</strong> <em>= 全额给商家(不抽佣),归属看 express_income_mch_id</em></div>
            <div class="formula-line">└─ 平台留成 <strong>platform_income</strong> <em>= pay_price − mch_income(平台抽佣部分,留平台)</em></div>
            <div class="formula-tip"><strong>运费归属:</strong>同城配送 / 快递 → 商家自己;上门自提 / 无需物流 → 归平台(通常运费本就是 0)。</div>
          </div>

          <h3>两条结算轨(别混)</h3>
          <el-table :data="tracks" border size="small" style="margin-bottom:20px;">
            <el-table-column label="结算轨" prop="track" width="130" />
            <el-table-column label="钱先到哪" prop="where" width="200" />
            <el-table-column label="怎么结算" prop="how" />
          </el-table>

          <h3>落到哪个账户(per-mall 可配)</h3>
          <el-table :data="settleTargets" border size="small" style="margin-bottom:20px;">
            <el-table-column label="mch_settle_target" prop="key" width="160" />
            <el-table-column label="落到" prop="acc" width="200" />
            <el-table-column label="说明" prop="desc" />
          </el-table>

          <el-alert type="info" :closable="false" show-icon
            title="退款重算:部分退款时 calculateFinalIncome 按剩余有效明细重算商家收入;全部退完时运费也不再计入(重置 is_express_settled)。货款/运费两个打款标志(is_mch_settled / is_express_paid)相互独立,避免运费永不打款的资损。" />
        </div>
      </el-tab-pane>

      <!-- ③ 配置说明 -->
      <el-tab-pane label="配置说明" name="config">
        <div class="guide-section">
          <h2>关键配置说明</h2>
          <el-alert type="info" :closable="false" show-icon style="margin-bottom:20px;"
            title="以下配置在【多商户 · 设置】里。改配置只影响之后的新订单,不影响已下单的历史订单(历史单用下单时的快照)。" />

          <el-table :data="configs" border size="small">
            <el-table-column label="配置项" prop="name" width="180" />
            <el-table-column label="在哪配" prop="where" width="160" />
            <el-table-column label="作用" prop="desc" />
          </el-table>
        </div>
      </el-tab-pane>

      <!-- ④ 常见疑问 -->
      <el-tab-pane label="常见疑问 FAQ" name="faq">
        <div class="guide-section">
          <h2>常见疑问</h2>
          <el-collapse v-model="activeFaq">
            <el-collapse-item v-for="(f, i) in faqs" :key="i" :title="f.q" :name="String(i)">
              <div class="faq-a" v-html="f.a"></div>
            </el-collapse-item>
          </el-collapse>
        </div>
      </el-tab-pane>

    </el-tabs>
  </div>
</template>

<script>
export default {
  name: 'mch-guide',
  data() {
    return {
      activeTab: 'logic',
      activeFaq: ['0'],
      roles: [
        { role: '平台', desc: '收订单钱、按分润配置抽佣、售后期满后给商家打款', money: '平台留成 platform_income(抽佣部分)' },
        { role: '商家', desc: '上架商品、履约(开始配送/发货 → 确认送达)', money: '货款 mch_income_price + 运费 express_price(全额)' },
        { role: '买家', desc: '下单、支付、收货', money: '付实付金额 pay_price' },
      ],
      orderStates: [
        { val: 0, name: '待付款', desc: '下单未支付' },
        { val: 20, name: '待发货 / 待配送', desc: '已支付,等商家发货 / 开始配送' },
        { val: 30, name: '待收货 / 配送中', desc: '商家已发货 / 已开始配送' },
        { val: 40, name: '已完成', desc: '确认送达 / 确认收货,进入待结算 → 售后期满后结算打款' },
        { val: 10, name: '已关闭', desc: '取消 / 退款关闭' },
      ],
      tracks: [
        { track: '普通订单', where: '买家付款进【平台账户】', how: '售后期满后,平台把「货款 + 运费」打给商家,自己留 platform_income。走 MchSettlementService。' },
        { track: '收银台订单', where: '买家付款直进【商家自己的支付网关】(商户号)', how: '走分账,把平台抽成反向分出来。由 CashierSettlementService 结算,豁免 mch 结算兜底任务。' },
      ],
      settleTargets: [
        { key: 'commission(默认)', acc: '佣金账户(User.price)', desc: '需走提现才能到银行卡;未配置的商城默认走这个,保证零行为变化。' },
        { key: 'balance', acc: '余额账户(User.money)', desc: '可直接在平台内消费。' },
        { key: 'mch.funds', acc: '独立货款账户', desc: '与佣金分开记账,提现规则可单独配置(货款渠道)。' },
      ],
      configs: [
        { name: '商家结算账户 mch_settle_target', where: '设置 → 货款结算', desc: '商家货款 + 运费结算落到哪个账户(佣金 / 余额 / 独立货款),默认佣金账户。' },
        { name: '货款结算渠道 / 提现规则', where: '设置 → 货款结算', desc: '商家把货款提现到银行卡 / 支付宝 / 余额的渠道与限额。' },
        { name: '平台抽点 concession_rate', where: '商品编辑「平台抽点」/ 商户全局默认', desc: '平台从商家订单抽成的服务费率(模型旧名「让利比例」,实为平台抽点)。下单时:平台服务费 = 结算基数 × 该率 → 存 concession_amount;商家货款 mch_income_price = 结算基数 − 平台服务费。商品级优先,留空继承商户全局费率。' },
        { name: '商家入驻与审核', where: '设置 → 入驻与审核', desc: '是否开启入驻、允许的商家类型、审核方式(人工 / 自动)、入驻协议与说明。' },
        { name: '入驻字段', where: '设置 → 入驻字段', desc: '按商家类型配置入驻表单要填哪些字段、哪些必填;证照走证照库。' },
        { name: '首单优惠', where: '商家编辑店铺', desc: '商家给新客的首单补贴,补贴金额由平台/商家承担逻辑决定,体现在订单优惠明细。' },
        { name: '名词词库', where: '商城 → 名词配置', desc: '「余额/积分/佣金/货款」等叫法后台可改,前端一律走词库显示,商家/买家看到的是配置后的叫法。' },
        { name: '地址选择地理位置(平台侧)', where: '商城设置 → 功能开关 → 地址选择地理位置', desc: '是否允许买家在收货地址页点地图选定位、获取经纬度。默认关。同城配送靠经纬度算距离与运费,做同城配送的商城【必须开启】;不做同城可关掉以简化地址填写。' },
        { name: '同城配送范围与计费(商家侧)', where: '商家端 → 配送设置', desc: '商家自设:配送半径 delivery_radius(超出拒单)、起步价 / 起步距离 / 每公里加价、起送金额 min_order_amount(未达拒单)。下单时按买家地址到商家的直线距离算运费,运费全额归商家。' },
      ],
      faqs: [
        { q: '1. 运费到底给谁?', a: '同城配送、快递自发货 → <b>运费全额给商家自己</b>(不抽佣);上门自提、无需物流 → 归平台(这类通常运费本就是 0)。归属记录在 <code>order.express_income_mch_id</code>,下单时定死。' },
        { q: '2. 为什么金额用下单快照、结算时不重算?', a: '商家货款 <code>mch_income_price</code> 在<b>下单时</b>按当时的商品分润配置算好、存进 DB。结算时<b>只读快照、绝不重算</b>——这样即使后台之后改了抽佣比例,历史订单也不会被算错,避免资损与对账混乱。' },
        { q: '3. mch_settle_target 三个账户有啥区别、怎么提现?', a: '<code>commission</code>(默认)→ 佣金账户(User.price),需走提现;<code>balance</code> → 余额(可直接消费);<code>mch.funds</code> → 独立货款账户(与佣金分开,提现规则可单配)。在【设置 → 货款结算】切换,只影响之后的新结算。' },
        { q: '4. 部分退款 / 全退后商家的钱怎么变?', a: '部分退款:<code>calculateFinalIncome</code> 按剩余有效明细重算商家应得;全部退完:运费也不再计入(重置 <code>is_express_settled</code>)。已结算的会按规则冲正。' },
        { q: '5. 收银台订单为什么不走这条结算轨?', a: '收银台的钱<b>直接进商家自己的支付网关</b>(商户号),不经过平台账户。所以它走<b>分账</b>(把平台抽成反向分出),由 CashierSettlementService 结算,并豁免 mch 的结算兜底任务。' },
        { q: '6. concession_rate(让利比例 / 平台抽点)是干嘛的?谁能改?', a: '它是<b>平台从商家订单抽成的服务费率</b>(模型旧名「让利比例」,实为平台抽点)。下单时:平台服务费 = 结算基数 × 该率(记入 <code>concession_amount</code>),商家货款 <code>mch_income_price</code> = 结算基数 − 平台服务费。<b>只有平台后台能设</b>:商户级「通用让利比例」在【商户编辑】,商品级可在【商品编辑】单独覆盖(留空继承商户级)。<b>商家端(移动端/PC/后端)都无法设置</b>,不是商家想填多少就多少。此前设置页的「让利比例区间限制(min/max)」是死配置(它想约束的“商家自设”入口根本不存在),已移除。' },
        { q: '7. is_mch_settled / is_express_paid 为什么分成两个标志?', a: '货款打款(is_mch_settled)和运费打款(is_express_paid)<b>相互独立</b>。历史上混用一个标志曾导致「财务汇总已置位、运费却永不打款」的资损,现已彻底解耦。' },
        { q: '8. 确认送达后多久到账?', a: '确认送达后订单进「待结算」并记 finish_at。<b>售后期(mall_setting.order_settle_day 天)满后</b>,核心 OrderSettleTask 定时任务自动结算并打款;MchSettlementTask 兜底任务补漏。' },
        { q: '9. 同城配送怎么才能正常用?为什么有的买家选了同城会被拦?', a: '同城配送要<b>两处开关配合,缺一不可</b>:<br>① <b>平台侧</b>——【商城设置 → 功能开关 → 地址选择地理位置】要<b>开启</b>,买家才能在收货地址里点地图选定位、拿到经纬度;<br>② <b>商家侧</b>——商家在配送设置里开启同城配送,并设好<b>配送半径 / 起步价 / 起送金额</b>。<br>下单时系统按买家地址经纬度到商家的距离算运费,并做三道校验,任一不过就拦下不让下单:<b>超出配送半径 → 拒</b>、<b>未达起送金额 → 拒</b>、<b>地址没有定位坐标 → 拒</b>(否则会以运费0、不校验范围静默成单,等于商家白送货)。<br><b>最常见的坑:</b>商家开了同城配送,平台却忘了开①的地理位置开关 → 买家地址没经纬度 → 一选同城就被拦。现在下单页会提示原因并给一个「去设置定位」按钮,买家点了可就地补定位(即便①没开也能补),补完即可正常下单,不会再卡死。<b>结论:做同城配送的商城,请务必先把①打开</b>,让买家日常存地址时就能顺手选好定位。' },
      ],
    };
  },
};
</script>

<style scoped>
.guide-page { padding: 20px; }
.guide-section { padding: 0 8px 24px; max-width: 960px; }
.guide-section h2 { font-size: 20px; color: #303133; margin: 8px 0 16px; }
.guide-section h3 { font-size: 15px; color: #303133; margin: 24px 0 12px; padding-left: 10px; border-left: 3px solid var(--admin-accent-text); }

.fund-svg-wrap {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 8px;
}

.formula-box {
  background: #fafafa;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 16px 20px;
  margin-bottom: 8px;
  font-family: Menlo, Consolas, monospace;
}
.formula-line { line-height: 2; color: #303133; font-size: 13px; }
.formula-line em { color: #909399; font-style: normal; font-size: 12px; margin-left: 8px; font-family: -apple-system, Arial, sans-serif; }
.formula-line strong { color: var(--admin-accent-text); }
.formula-tip { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #dcdfe6; color: #606266; font-size: 13px; font-family: -apple-system, Arial, sans-serif; }
.formula-tip strong { color: #E6A23C; }

.faq-a { color: #606266; font-size: 13px; line-height: 1.9; }
.faq-a code { background: #f4f4f5; color: #c7254e; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
</style>
