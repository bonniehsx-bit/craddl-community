<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-shopping-cart-full"></i>
          <span>充值商品</span>
        </div>
        <el-button type="primary" size="small" icon="el-icon-plus" @click="handleCreate">新建充值商品</el-button>
      </div>

      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px;">
        <template slot="title">
          充值现在是<b>一个正经商品</b>：一个商品对应一种到账账户，里面配若干充值档位。
        </template>
        <div style="font-size: 12px; line-height: 1.9; margin-top: 4px;">
          · 要同时支持充余额和充积分，就<b>建两个商品</b>（还能各自配不同的分销比例）<br>
          · 编辑商品时的「<b>分销设置</b>」tab 决定要不要给充值分润 —— 不开就不分，开了才分<br>
          · 充值商品不参与优惠券 / 积分抵扣，C 端走专属下单页
        </div>
      </el-alert>

      <el-form :inline="true" size="small" :model="searchForm" @submit.native.prevent class="filter-form">
        <el-form-item label="商品名称">
          <el-input v-model="searchForm.keyword" placeholder="商品名称" clearable style="width: 180px;"
                    @clear="search" @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;" @change="search">
            <el-option label="上架" :value="1" />
            <el-option label="下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
          <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" border size="small">
        <el-table-column label="商品" min-width="220">
          <template slot-scope="{ row }">
            <div class="goods-cell">
              <el-image v-if="row.cover_pic" :src="row.cover_pic" fit="cover" class="goods-pic" />
              <div v-else class="goods-pic goods-pic--empty">无图</div>
              <span class="goods-name">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="到账账户" prop="asset_label" width="120" align="center">
          <template slot-scope="{ row }">
            <el-tag v-if="row.asset_type" size="mini">{{ row.asset_label }}</el-tag>
            <el-tag v-else type="danger" size="mini">未配置</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="在架档位" width="100" align="center">
          <template slot-scope="{ row }">
            <span :class="row.plan_count ? '' : 'muted'">{{ row.plan_count }} 档</span>
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort" width="70" align="center" />
        <el-table-column label="状态" width="80" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="mini">
              {{ row.status === 1 ? '上架' : '下架' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" width="140" align="center" />
        <el-table-column label="操作" width="90" align="center">
          <template slot-scope="{ row }">
            <el-button type="text" size="mini" @click="handleEdit(row)">编辑</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="count > 0"
        style="margin-top: 16px; text-align: right;"
        background layout="total, prev, pager, next"
        :total="count" :page-size="limit" :current-page.sync="page" @current-change="load" />
    </el-card>

    <!--
      复用核心的商品编辑抽屉：
        goods-type       告诉核心把 Goods.type 存成 withdraw_recharge
        disable-multi-spec 关掉多规格 —— 档位走插件自己的表，不占用核心 SKU
        hidden-tabs      充值是虚拟的，没有物流/参数/商户
      #extra-tabs 是插槽，所以「充值设置」完全由本页渲染，不用改任何核心组件。
    -->
    <goods-edit-drawer
      :visible.sync="drawerVisible"
      :goods-id="editGoodsId"
      goods-type="withdraw_recharge"
      api-url="/mall/goods/edit"
      :disable-multi-spec="true"
      size="90%"
      :hidden-tabs="['express', 'property', 'mch', 'sku']"
      @success="handleEditSuccess">

      <template #extra-tabs="{ form }">
        <el-tab-pane label="充值设置" name="withdraw_recharge"
                     v-if="form.plugins_data && form.plugins_data.withdraw_recharge">
          <div class="rc-block">
            <el-form-item label="到账账户" required>
              <el-select v-model="form.plugins_data.withdraw_recharge.asset_type" style="width: 260px;">
                <el-option
                  v-for="a in (form.plugins_data.withdraw_recharge.assetOptions || [])"
                  :key="a.key || a.value" :label="a.label" :value="a.key || a.value" />
              </el-select>
              <div class="tip-text">
                充值成功后额度进入该账户。<b>一个商品只对应一个账户</b> ——
                要同时支持充余额和充积分，请建两个商品。
              </div>
            </el-form-item>

            <el-form-item label="充值说明">
              <el-input v-model="form.plugins_data.withdraw_recharge.remark" type="textarea" :rows="2"
                        placeholder="展示在 C 端充值页的说明文字，选填" style="width: 520px;" />
            </el-form-item>
          </div>

          <div class="rc-block">
            <div class="rc-title">充值档位</div>
            <div class="tip-text" style="margin-bottom: 12px;">
              至少配一档，否则 C 端点不动。<b>到账总量 = 到账数量 + 赠送</b>，
              赠送额与本金进同一个账户。<br>
              「赠送」是<b>充值当场一次性到账</b>的；要做<b>分期送券 / 分期送赠金</b>（天猫那种「分3期」），
              点右侧的<b>分期赠品</b>。两者可以同时用。
            </div>

            <el-table :data="form.plugins_data.withdraw_recharge.plans" border size="mini">
              <el-table-column label="档位名称" min-width="150">
                <template slot-scope="{ row }">
                  <el-input v-model="row.title" size="mini" placeholder="如：充100送10" />
                </template>
              </el-table-column>
              <el-table-column label="实付(元)" width="120" align="center">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.pay_amount" :min="0.01" :precision="2" :controls="false"
                                   size="mini" style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column label="到账数量" width="120" align="center">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.gain_amount" :min="0" :precision="2" :controls="false"
                                   size="mini" style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column label="赠送" width="110" align="center">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.gift_amount" :min="0" :precision="2" :controls="false"
                                   size="mini" style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column label="可购买身份" width="180" align="center">
                <template slot-scope="{ row }">
                  <el-checkbox-group v-model="row.user_types" size="mini">
                    <el-checkbox label="user">普通</el-checkbox>
                    <el-checkbox label="merchant">商户</el-checkbox>
                  </el-checkbox-group>
                </template>
              </el-table-column>
              <el-table-column label="每日限购" width="110" align="center">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.day_limit" :min="0" :controls="false" size="mini"
                                   style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column width="120" align="center">
                <template slot="header">
                  累计限购
                  <el-tooltip placement="top">
                    <div slot="content" style="line-height:1.6;max-width:300px;">
                      每人<b>一辈子</b>最多买几次，买完就永远没有了。<br>
                      与「每日限购」是两个维度，配了就都要满足：<br>
                      · 每日限购管节奏，次日 0 点重置<br>
                      · 累计限购管总量，不会恢复<br>
                      像「新人首充 1 元得 10 元」这种亏本引流档只能靠它挡住——<br>
                      光配每日限购=1 的话，用户每天都能再来一次。<br>
                      <b>0 = 不限制</b>
                    </div>
                    <i class="el-icon-question" style="color:#909399;"></i>
                  </el-tooltip>
                </template>
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.total_limit" :min="0" :controls="false" size="mini"
                                   style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column label="排序" width="80" align="center">
                <template slot-scope="{ row }">
                  <el-input-number v-model="row.sort" :min="0" :controls="false" size="mini"
                                   style="width: 100%;" />
                </template>
              </el-table-column>
              <el-table-column label="上架" width="70" align="center">
                <template slot-scope="{ row }">
                  <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
                </template>
              </el-table-column>
              <el-table-column label="分期赠品" width="120" align="center">
                <template slot-scope="{ row }">
                  <el-button type="text" size="mini" @click="openGift(row, form)">
                    <span v-if="row.gifts && row.gifts.length" style="color:#67C23A;">
                      已配 {{ row.gifts.length }} 期
                    </span>
                    <span v-else style="color:#909399;">未配置</span>
                  </el-button>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="70" align="center">
                <template slot-scope="{ $index }">
                  <el-button type="text" size="mini" style="color:#f56c6c;"
                             @click="form.plugins_data.withdraw_recharge.plans.splice($index, 1)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <el-button type="primary" plain icon="el-icon-plus" size="mini" style="margin-top: 12px;"
                       @click="addPlan(form)">添加档位</el-button>
          </div>
        </el-tab-pane>
      </template>
    </goods-edit-drawer>

    <gift-plan-dialog :visible.sync="giftDialog.visible" :plan="giftDialog.plan"
                      :asset-label="giftDialog.assetLabel" @confirm="onGiftConfirm" />
  </div>
</template>

<script>
import GoodsEditDrawer from '@/components/goods/GoodsEditDrawer.vue'

import GiftPlanDialog from './GiftPlanDialog.vue'

export default {
  name: 'WithdrawRechargeGoods',
  // ⚠ 两个组件必须写在**同一个** components 里。
  //   写成两个 components 键的话,JS 对象字面量后者静默覆盖前者,
  //   组件不会注册、模板渲染成空标签、点了没反应 —— 而构建完全不报错。
  components: { GoodsEditDrawer, GiftPlanDialog },
  data() {
    return {
      // 分期赠品弹窗。plan 直接指向档位对象,确认后把 gifts 写回去
      giftDialog: { visible: false, plan: null, assetLabel: '元' },
      loading: false,
      list: [],
      count: 0,
      page: 1,
      limit: 20,
      searchForm: { keyword: '', status: '' },
      drawerVisible: false,
      editGoodsId: ''
    }
  },
  created() {
    this.load()
  },
  methods: {
    // ⚠ form 是 extra-tabs 插槽的作用域变量,页面 this 上没有 —— 由模板把它传进来
    openGift(row, form) {
      if (!row.gifts) this.$set(row, 'gifts', [])
      this.giftDialog.plan = row
      // 赠金那一期的单位跟着商品的到账账户走,不能写死"元"(可能是积分)
      this.giftDialog.assetLabel = this.assetLabelOf(form)
      this.giftDialog.visible = true
    },
    assetLabelOf(form) {
      const d = (form && form.plugins_data && form.plugins_data.withdraw_recharge) || {}
      const opt = (d.assetOptions || []).find(a => (a.key || a.value) === d.asset_type)
      return (opt && opt.label) || '元'
    },
    onGiftConfirm(gifts) {
      if (this.giftDialog.plan) this.$set(this.giftDialog.plan, 'gifts', gifts)
    },
    load() {
      this.loading = true
      this.$request({
        url: '/plugin/withdraw/admin/goods/index',
        method: 'get',
        data: Object.assign({ page: this.page, limit: this.limit }, this.searchForm)
      }).then(res => {
        if (res.code !== 0) return this.$message.error(res.msg || '加载失败')
        this.list = res.data.list || []
        this.count = res.data.count || 0
      }).finally(() => { this.loading = false })
    },
    search() {
      this.page = 1
      this.load()
    },
    resetSearch() {
      this.searchForm = { keyword: '', status: '' }
      this.search()
    },
    handleCreate() {
      this.editGoodsId = ''
      this.drawerVisible = true
    },
    handleEdit(row) {
      this.editGoodsId = row.id
      this.drawerVisible = true
    },
    handleEditSuccess() {
      this.drawerVisible = false
      this.load()
    },
    addPlan(form) {
      form.plugins_data.withdraw_recharge.plans.push({
        id: 0, title: '', pay_amount: 100, gain_amount: 100, gift_amount: 0,
        user_types: ['user'], day_limit: 0, total_limit: 0, sort: 0, status: 1,
        gifts: []   // 分期赠品明细,由「分期赠品」弹窗写入
      })
    }
  }
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 15px; font-weight: 600; }
.page-title i { margin-right: 6px; }
.goods-cell { display: flex; align-items: center; }
.goods-pic { width: 38px; height: 38px; border-radius: 4px; margin-right: 10px; flex-shrink: 0; }
.goods-pic--empty {
  background: #f5f7fa; color: #c0c4cc; font-size: 12px;
  display: flex; align-items: center; justify-content: center;
}
.goods-name { font-size: 13px; }
.muted { color: #c0c4cc; }
.rc-block { margin-bottom: 24px; }
.rc-title { font-weight: 600; font-size: 14px; margin-bottom: 10px; }
.tip-text { font-size: 12px; color: #909399; line-height: 1.7; margin-top: 4px; }
</style>
