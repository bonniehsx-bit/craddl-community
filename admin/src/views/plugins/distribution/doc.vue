<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-notebook-2"></i>
          <span>分销插件运营手册</span>
        </div>
      </div>

      <div class="doc-body">
        <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 16px;"
                  title="开始配置前必读：本插件没有「开通分销员」这一步">
          <div style="line-height:1.8;">
            很多人以为要先把某个人设成分销员他才能拿佣金 —— <b>本插件不是这样的</b>。<br/>
            只要买家的推荐关系链上有他、层级在配置范围内，<b>他就会拿到佣金</b>，无需任何开通动作。<br/>
            反过来，不想让某个人再拿，要到【分销用户】页对他<b>「取消资格」</b>。
          </div>
        </el-alert>

        <el-tabs v-model="active" tab-position="left" class="doc-tabs">

          <!-- ============ 分润算法 ============ -->
          <el-tab-pane label="分润算法" name="algo">
            <h3>一、一笔订单是怎么分的</h3>
            <p class="lead">
              买家付款后，系统沿着<b>推荐关系链</b>从他的直接上级往上走，
              第 1 个上级算「一级分销」、第 2 个算「二级分销」，以此类推，
              走到你配置的<b>层级数</b>为止。每一层按自己那层的比例算一笔佣金。
            </p>

            <div class="example">
              <div class="example-title">例：层级数 2，一级 10%、二级 5%，商品实付 100 元</div>
              <div class="example-body">
                买家 → 直推上级拿 <b>100 × 10% = 10 元</b> → 再上一级拿 <b>100 × 5% = 5 元</b><br/>
                再往上的人<b>不拿</b>（超出层级数）。
              </div>
            </div>

            <h3>二、关系链上「消失的人」去哪了</h3>
            <p>
              关系链会自动<b>剔除已注销和被拉黑的用户</b>，并且<b>层级自动前移</b>。
            </p>
            <div class="example">
              <div class="example-title">例：A（买家）→ B → C → D，其中 B 已注销</div>
              <div class="example-body">
                实际参与分润的是：<b>C 算一级、D 算二级</b>。<br/>
                所以运营看到「某一层没人拿」，多数不是漏发，而是那个人已经失效了。
              </div>
            </div>
            <p class="warn">
              注意这与「取消资格」不同：<b>取消资格的人是被跳过、但层级不前移</b>
              —— 他那一层的钱不发给任何人，后面的人也不会因此升一级。
              这是有意的：取消资格是惩罚，不该让上级顺势多拿。
            </p>

            <h3>三、自购返佣</h3>
            <p>
              默认<b>买家自己下单不返佣</b>。开启「自购返佣」后，
              <b>买家自己占一级</b>，他的上级依次顺延成二级、三级……
            </p>
            <p class="warn">
              这<b>不是</b>额外多发一份。总发放比例不变，只是把一级那份给了买家本人，
              原本拿一级的直推上级降为二级、拿二级的比例。超出层级数的人自然被挤掉。
            </p>

            <h3>四、算钱用的「基数」可以换</h3>
            <p>同一套比例，算在不同的钱上，结果差很远：</p>
            <p class="warn">
              先说清一个容易误会的点：默认的「商品金额」是<b>单价 × 件数的原价小计</b>，
              <b>不扣优惠券、不扣积分抵扣</b>。商品 199 元、用券后买家实付 149，按默认档仍按 <b>199</b> 计佣。
              这是本系统一贯口径（区域代理、经销商同此），相当于优惠成本由平台承担、不削减推广积极性。
            </p>
            <el-table :data="baseRows" size="mini" border class="doc-table">
              <el-table-column prop="k" label="基数" width="130" />
              <el-table-column prop="v" label="算的是哪笔钱" width="200" />
              <el-table-column prop="d" label="适合什么场景" />
            </el-table>
            <p class="warn">
              选「利润」时，若成本价没维护，利润会等于实付，比例照原样算 ——
              上线前务必确认商品成本价是填过的。
            </p>

            <h3>五、比例可以被两层配置覆盖</h3>
            <p>优先级从高到低：</p>
            <ul class="bullets">
              <li><b>商品级</b>（在商品的分销配置里）：可以给单个商品配自定义比例，或按会员等级配</li>
              <li><b>会员等级级</b>（设置页「按会员等级分润」tab）：不同等级的收款人拿不同比例。
                <b>⚠ 这一层要先打开该 tab 里的「启用按等级分润」开关才生效</b>，关着的话下面配的比例一律不算数</li>
              <li><b>全局</b>（本插件设置页「多级分润比例」）：兜底</li>
            </ul>
            <p>
              覆盖是<b>按层级逐项</b>生效的：某一层留空就回退上一级配置，不会因为配了一层而把其它层清零。
            </p>
          </el-tab-pane>

          <!-- ============ 配置指引 ============ -->
          <el-tab-pane label="配置指引" name="config">
            <h3>第一步：开启并定层级</h3>
            <ol class="steps">
              <li>【分销设置】打开「启用分销」</li>
              <li>设「层级数」——决定往上走几层，比如 2 就是一级+二级</li>
              <li>逐层填比例（百分比）</li>
            </ol>

            <h3>第二步：决定哪些商品参与</h3>
            <p>
              <b>默认全部商品参与</b>，用全局比例。只有在商品的分销配置里
              <b>显式关掉</b>某个商品，它才不参与。
            </p>
            <p class="warn">
              这一点和很多人的预期相反：不是「配了才参与」，而是「不配就参与」。
              有不该返佣的商品（如成本极薄的引流品），必须去<b>主动关掉</b>。
            </p>

            <h3>第三步：决定什么时候把钱发出去</h3>
            <el-table :data="timingRows" size="mini" border class="doc-table">
              <el-table-column prop="k" label="结算节点" width="130" />
              <el-table-column prop="v" label="什么时候到账" width="220" />
              <el-table-column prop="d" label="取舍" />
            </el-table>
            <p class="warn">
              「延迟结算天数」大于 0 时<b>会压过上面的结算节点</b>，统一由到期任务发放。
              想用结算节点，请把延迟天数改回 0。
            </p>

            <h3>第四步：决定钱发到哪个账户</h3>
            <p>
              「佣金落账账户」可选<b>收入</b>（需提现）、<b>余额</b>（可直接消费）、<b>积分</b>。
            </p>
            <p class="warn">
              该账户会随每一笔佣金<b>快照保存</b>。后台改了只影响之后新产生的佣金，
              已经计提的老佣金仍按当初的账户结算和退款追回 —— 不会串账。
            </p>

            <h3>配置项速查</h3>
            <el-table :data="settingRows" size="mini" border class="doc-table">
              <el-table-column prop="k" label="配置项" width="150" />
              <el-table-column prop="v" label="作用" />
            </el-table>
          </el-tab-pane>

          <!-- ============ 结算与退款 ============ -->
          <el-tab-pane label="结算与退款" name="settle">
            <h3>一笔佣金的完整生命周期</h3>
            <div class="flow">
              <span class="node">买家付款</span><span class="arrow">→</span>
              <span class="node">计提（待结算）</span><span class="arrow">→</span>
              <span class="node done">结算（到账）</span>
            </div>
            <div class="flow">
              <span class="arrow">中途退款：</span>
              <span class="node">待结算 → <b>作废</b>，钱本来就没发</span>
              <span class="arrow">/</span>
              <span class="node danger">已结算 → <b>原路追回</b></span>
            </div>

            <h3>退款怎么算</h3>
            <ul class="bullets">
              <li><b>整单退款</b>：这单产生的分销佣金全部回滚</li>
              <li><b>部分退款</b>：只回滚被退的那条商品明细对应的佣金，其它明细的不动</li>
              <li><b>追回从哪扣</b>：按当初发放的账户原路扣回（发到余额就扣余额，发到积分就扣积分）</li>
              <li><b>余额不足</b>：强制扣成负数。这是平台统一口径 —— 钱已经发出去了，不能因为对方花光了就算了</li>
              <li><b>重复触发</b>：先取消又售后这类重复信号不会重复扣，内部有幂等保护</li>
            </ul>

            <h3>手动结算怎么用</h3>
            <p>
              结算节点选「手动结算」后，佣金只计提不发放，需要运营在【分销记录】页
              对每一笔点<b>「结算」</b>才真正到账，或点<b>「作废」</b>不再发放。
            </p>
            <p class="warn">
              <b>已结算的不能作废</b>。钱已经打出去了，靠「作废」收不回来 ——
              要收回必须走订单退款流程，让系统按原路追回，否则账面和实际资产会对不上。
            </p>

            <h3>延迟结算（T+N）</h3>
            <p>
              配了延迟天数后，佣金在计提时只登记一条到期计划，满 N 天由每日任务自动发放。
              期间若订单退款，计划会被<b>自动作废</b>，不会到期后还把钱发出去。
            </p>
            <p class="warn">
              到期任务<b>按小时</b>扫描（不是只在凌晨）。因为到期时刻是精确到点的：
              下午 3 点计提的单子，第 N 天下午 3 点才到期，只在凌晨跑会白拖一天。
            </p>
          </el-tab-pane>

          <!-- ============ 常见问题 ============ -->
          <el-tab-pane label="常见问题" name="faq">
            <h3>排查清单</h3>
            <el-collapse>
              <el-collapse-item v-for="(item, i) in faq" :key="i" :title="item.q">
                <div class="faq-answer" v-html="item.a"></div>
              </el-collapse-item>
            </el-collapse>
          </el-tab-pane>

        </el-tabs>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'distribution-doc',
  data() {
    return {
      active: 'algo',
      baseRows: [
        { k: '商品金额（默认）', v: '单价 × 件数，原价小计，<b>不扣券和积分抵扣</b>', d: '系统一贯口径，优惠成本由平台承担、不影响推广积极性' },
        { k: '利润', v: '商品金额 − 成本（负数按 0）', d: '只分「赚到的那部分」，毛利薄的商品不至于倒贴' },
        { k: '商品成本', v: '成本价 × 件数', d: '按进货价给固定返点的场景' },
        { k: '商品现价', v: '当前售价 × 件数', d: '有大额优惠券时，按标价算而不是按实付算' }
      ],
      timingRows: [
        { k: '订单完成后', v: '买家确认收货 / 自动完成后', d: '最安全，能避开售后期内的退款' },
        { k: '支付完成后', v: '付款成功立刻到账', d: '激励强，但退款要从对方账户追回，平台担风险' },
        { k: '手动结算', v: '只计提，运营逐笔点了才发', d: '最可控，适合金额大或需人工复核的场景' },
        { k: '延迟 N 天', v: '计提满 N 天后自动发', d: '压过上面三档。用来盖住售后期' }
      ],
      settingRows: [
        { k: '启用分销', v: '总开关。关掉后不再产生新佣金，已产生的不受影响' },
        { k: '层级数', v: '往上走几层。超出的人不拿' },
        { k: '多级分润比例', v: '每一层的百分比，全局兜底' },
        { k: '按会员等级覆盖', v: '不同会员等级的收款人拿不同比例，留空回退全局' },
        { k: '佣金计算基数', v: '比例算在哪笔钱上：实付 / 利润 / 成本 / 现价' },
        { k: '自购返佣', v: '开启后买家自己占一级，上级顺延，总比例不变' },
        { k: '佣金落账账户', v: '收入 / 余额 / 积分。随每笔佣金快照，改了不影响老数据' },
        { k: '结算节点', v: '订单完成后 / 支付完成后 / 手动结算' },
        { k: '延迟结算天数', v: '大于 0 时压过结算节点，满 N 天由到期任务发放' }
      ],
      faq: [
        {
          q: '某个人明明在关系链上，为什么没拿到佣金？',
          a: '按这个顺序查：① 他是不是在【分销用户】页被<b>取消资格</b>了；② 他的层级是不是<b>超出了层级数</b>；③ 这个商品是不是被<b>显式关闭</b>了分销；④ 他所在的会员等级是不是配了 0% 的覆盖比例；⑤ 中间有没有人注销/被拉黑导致<b>层级前移</b>，他实际的层数和你数的不一样。'
        },
        {
          q: '要先把用户设成分销员吗？',
          a: '<b>不需要</b>。本插件没有开通动作，关系链上的人默认都能拿。【分销用户】页只有「取消资格 / 恢复资格」两个动作，没有开通 —— 取消后随时可以恢复。'
        },
        {
          q: '为什么直推上级只拿到二级的比例？',
          a: '大概率是开了<b>自购返佣</b>：买家自己占了一级，上级顺延成二级。总发放比例没变，只是分配位置变了。'
        },
        {
          q: '配了「按会员等级分润」，为什么还是按全局比例发？',
          a: '<b>先看该 tab 里的「启用按等级分润」开关是不是关着</b> —— 关闭时下面配的比例一律不生效，全部走全局默认。开关打开后若某一层仍是全局比例，那是正常的：覆盖<b>按层级逐项</b>生效，某一层留空就回退全局，这样只想改一级比例时不必每层重填。'
        },
        {
          q: '选了「利润」做基数，为什么算出来和默认档一样？',
          a: '商品<b>成本价没填</b>。成本为 0 时「商品金额 − 成本」就等于商品金额本身。请到商品里补成本价。'
        },
        {
          q: '配了延迟结算，为什么结算节点点不动了？',
          a: '延迟天数大于 0 时会压过结算节点，统一由到期任务发放。想用结算节点请把延迟天数改回 0。'
        },
        {
          q: '延迟结算到期了，钱却没发出来？',
          a: '先确认服务器上的定时任务同步过了（部署后需要执行 crontab 同步），否则到期任务不会被调度。其次看【分销记录】里那笔是不是已经被退款作废了。'
        },
        {
          q: '已经结算的佣金，能在记录页点「作废」收回吗？',
          a: '<b>不能</b>，系统会拒绝。钱已经打到对方账户了，只改账面状态会造成账实不符。要收回请走订单退款，系统会按原发放账户自动追回。'
        },
        {
          q: '把落账账户从「收入」改成「余额」，以前的佣金会变吗？',
          a: '不会。账户是随每一笔佣金快照保存的，老佣金仍按当初的账户结算和退款追回。只有改动之后新产生的佣金才用新账户。'
        },
        {
          q: '部分退款后，其它商品的佣金也被退了吗？',
          a: '不会。只回滚被退的那条明细对应的佣金，同一订单里其它明细的佣金保持不变。'
        },
        {
          q: '不想让某个人再拿分销佣金了，怎么办？',
          a: '到【分销用户】页对他「取消资格」。之后不再给他计提新佣金；已计提未结算的，在发放时会被作废。注意他那一层的钱不会顺延给上级。想让他重新参与，在同一个页面点「恢复资格」即可。'
        }
      ]
    };
  }
};
</script>

<style scoped lang="scss">
.app-container { height: calc(100vh - 85px); width: 100%; background-color: #f0f2f5; padding: 15px; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; }
.box-card {
  flex: 1; display: flex; flex-direction: column; border: none; border-radius: 6px;
  ::v-deep .el-card__header { padding: 10px 15px; border-bottom: 1px solid #ebeef5; }
  ::v-deep .el-card__body { flex: 1; overflow-y: auto; min-height: 0; padding: 16px 20px; }
}
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #303133; i { color: var(--admin-accent-text); font-size: 18px; } }

.doc-body { font-size: 13px; color: #303133; }
.doc-tabs ::v-deep .el-tabs__content { padding-left: 20px; }

h3 { font-size: 14px; font-weight: 600; margin: 22px 0 10px; padding-left: 9px; border-left: 3px solid var(--admin-accent-text); }
h3:first-child { margin-top: 0; }
p { line-height: 1.9; margin: 8px 0; color: #5a5e66; }
p.lead { color: #303133; }
p.warn { background: #fdf6ec; border-left: 3px solid #E6A23C; padding: 8px 12px; color: #8a6d3b; border-radius: 0 4px 4px 0; }

.doc-table { margin: 10px 0 6px; }
.steps { line-height: 2.1; padding-left: 20px; color: #5a5e66; margin: 8px 0; }
.bullets { line-height: 2.1; padding-left: 20px; color: #5a5e66; margin: 8px 0; }

.example { background: #f5f7fa; border-radius: 4px; padding: 12px 16px; margin: 10px 0; }
.example-title { font-weight: 600; margin-bottom: 8px; color: #303133; }
.example-body { line-height: 2; color: #5a5e66; }

.flow { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
.node { background: #ecf5ff; color: #409EFF; padding: 6px 12px; border-radius: 4px; }
.node.done { background: #f0f9eb; color: #67C23A; }
.node.danger { background: #fef0f0; color: #F56C6C; }
.arrow { color: #c0c4cc; }

.faq-answer { line-height: 2; color: #5a5e66; }
</style>
