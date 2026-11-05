<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="card-header-flex">
        <span>营销活动</span>
        <el-button type="primary" size="mini" icon="el-icon-plus" @click="openEdit()">新建活动</el-button>
      </div>

      <el-form :inline="true" size="small" class="filter-form shrink-0" @submit.native.prevent="search">
        <el-form-item label="类型">
          <el-select v-model="searchForm.type" clearable placeholder="全部" @change="search" style="width:120px">
            <el-option label="触发型" :value="1"></el-option>
            <el-option label="群发型" :value="2"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" clearable placeholder="全部" @change="search" style="width:120px">
            <el-option label="草稿" :value="0"></el-option>
            <el-option label="进行中" :value="1"></el-option>
            <el-option label="已暂停" :value="2"></el-option>
            <el-option label="已结束" :value="3"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="searchForm.keyword" placeholder="活动名称" clearable @keyup.enter.native="search"></el-input>
        </el-form-item>
        <el-form-item><el-button type="primary" @click="search">搜索</el-button></el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table :data="list" v-loading="loading" border size="small" height="100%" style="width:100%" @row-dblclick="openEdit">
            <el-table-column prop="id" label="ID" width="60" align="center"></el-table-column>
            <el-table-column prop="name" label="活动名称" min-width="160" show-overflow-tooltip></el-table-column>
            <el-table-column label="类型" width="90" align="center">
              <template slot-scope="s"><el-tag size="mini" :type="s.row.type===1?'warning':'primary'">{{ s.row.type_text }}</el-tag></template>
            </el-table-column>
            <el-table-column label="状态" width="90" align="center">
              <template slot-scope="s"><el-tag size="mini" :type="statusType(s.row.status)">{{ s.row.status_text }}</el-tag></template>
            </el-table-column>
            <el-table-column label="预算/已发" min-width="150" align="center">
              <template slot-scope="s">
                <span v-if="s.row.budget_type===0">不限 · 已发 {{ s.row.grant_count }} 张</span>
                <span v-else-if="s.row.budget_type===1">{{ s.row.grant_count }} / {{ s.row.budget_total }} 张</span>
                <span v-else>¥{{ s.row.grant_amount }} / ¥{{ s.row.budget_total }}</span>
                <div v-if="s.row.budget_integral > 0 || s.row.grant_integral > 0" class="time-cell">
                  {{ $noun('integral') }} {{ s.row.grant_integral }}{{ s.row.budget_integral > 0 ? ' / ' + s.row.budget_integral : '' }}
                </div>
                <div v-if="s.row.budget_balance > 0 || s.row.grant_balance > 0" class="time-cell">
                  {{ $noun('balance') }} ¥{{ s.row.grant_balance }}{{ s.row.budget_balance > 0 ? ' / ¥' + s.row.budget_balance : '' }}
                </div>
              </template>
            </el-table-column>
            <el-table-column label="活动时段" min-width="150" align="center">
              <template slot-scope="s"><div class="time-cell">{{ s.row.start_at }} ~ {{ s.row.end_at }}</div></template>
            </el-table-column>
            <el-table-column prop="created_time" label="创建时间" width="150" align="center"></el-table-column>
            <el-table-column label="操作" width="200" align="center" fixed="right">
              <template slot-scope="s">
                <el-button type="text" size="mini" @click="openEdit(s.row)">编辑</el-button>
                <el-button v-if="s.row.status!==1" type="text" size="mini" style="color:#67C23A" @click="changeStatus(s.row,1)">启用</el-button>
                <el-button v-if="s.row.status===1" type="text" size="mini" style="color:#E6A23C" @click="changeStatus(s.row,2)">暂停</el-button>
                <el-button v-if="s.row.status===1||s.row.status===2" type="text" size="mini" style="color:#909399" @click="changeStatus(s.row,3)">结束</el-button>
                <el-button type="text" size="mini" style="color:#F56C6C" @click="remove(s.row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination v-if="pagination" background @current-change="pageChange"
          :current-page="searchForm.page" :page-size="searchForm.limit"
          layout="total, prev, pager, next, jumper" :total="pagination.total_count"></el-pagination>
      </div>
    </el-card>

    <!-- 编辑 Drawer -->
    <el-drawer :visible.sync="editVisible" size="640px" :wrapper-closable="true" custom-class="campaign-drawer">
      <div class="drawer-header" slot="title">
        <span class="title-main">{{ form.id ? '编辑营销活动' : '新建营销活动' }}</span>
        <span class="title-sub" v-if="form.id">ID: {{ form.id }}</span>
      </div>
      
      <div class="drawer-body">
        <el-form :model="form" label-width="100px" size="small" label-position="left">
          <el-tabs v-model="activeTab" type="card" class="form-tabs">
            
            <!-- Tab 1: 基本与规则 -->
            <el-tab-pane label="基本设置" name="basic">
              <div class="tab-pane-content">
                <el-form-item label="活动名称" required>
                  <el-input v-model="form.name" maxlength="60" placeholder="如: 新人礼包 / 618满额返券"></el-input>
                </el-form-item>
                
                <el-form-item label="活动类型" required>
                  <el-radio-group v-model="form.type">
                    <el-radio :label="1">触发型(自动发)</el-radio>
                    <el-radio :label="2">群发型(定时群发)</el-radio>
                    <el-radio :label="3">领取型(用户主动领)</el-radio>
                  </el-radio-group>
                </el-form-item>
                
                <el-form-item label="活动时段">
                  <el-date-picker v-model="form.dateRange" type="datetimerange" range-separator="至"
                    start-placeholder="开始时刻(留空不限)" end-placeholder="结束时刻(留空不限)" value-format="timestamp" style="width:100%"></el-date-picker>
                </el-form-item>

                <!-- 触发型: 触发规则 -->
                <div v-if="form.type === 1" class="rules-section">
                  <div class="section-title">触发规则配置</div>
                  <el-form-item label="触发事件" required>
                    <el-select v-model="form.trigger.event" placeholder="选择触发事件" style="width:100%">
                      <el-option label="新用户注册 (即时到账)" value="register"></el-option>
                      <el-option label="订单支付 (首单/满额/复购)" value="order_paid"></el-option>
                      <el-option label="累计下单 (按单数/金额阶梯)" value="order_cumulative"></el-option>
                      <el-option label="每满循环 (每满 N 单/元/件奖一次)" value="order_repeat"></el-option>
                      <el-option label="新客复购 (首单后 N 天内下第二单)" value="new_customer_rebuy"></el-option>
                      <el-option label="邀请首单 (被邀请人首单完成奖励)" value="invite_first_order"></el-option>
                      <el-option label="会员升级" value="level_up"></el-option>
                      <el-option label="会员生日" value="birthday"></el-option>
                      <el-option label="沉睡召回" value="inactive"></el-option>
                    </el-select>
                  </el-form-item>
                  
                  <el-form-item v-if="isOrderEvent" label="计入订单类型">
                    <el-checkbox-group v-model="form.trigger.order_types">
                      <el-checkbox v-for="ot in orderTypeOptions" :key="ot.key" :label="ot.key">{{ ot.name }}</el-checkbox>
                    </el-checkbox-group>
                    <div class="tip-txt" style="margin: 0;">只有勾选类型的订单才计入 / 触发奖励;都不勾选表示全部类型(含充值、会员卡等,不建议)</div>
                  </el-form-item>

                  <el-form-item v-if="form.trigger.event==='order_paid'" label="订单条件">
                    <div style="display: flex; align-items: center; gap: 10px;">
                      <el-select v-model="form.trigger.order_type" style="width:130px">
                        <el-option label="任意支付" value="any"></el-option>
                        <el-option label="仅首单" value="first"></el-option>
                        <el-option label="实付满额" value="amount"></el-option>
                      </el-select>
                      <template v-if="form.trigger.order_type==='amount'">
                        <span class="sub-txt">实付满</span>
                        <el-input-number v-model="form.trigger.min_amount" :min="0" :controls="false" style="width:100px"></el-input-number>
                        <span class="sub-txt">元</span>
                      </template>
                    </div>
                  </el-form-item>
                  
                  <template v-if="isCumulative || isRepeat">
                    <el-form-item label="统计口径">
                      <el-select v-model="form.trigger.metric" style="width:100%">
                        <el-option label="有效订单数" value="order_count"></el-option>
                        <el-option label="实付金额" value="order_amount"></el-option>
                        <el-option label="指定商品件数" value="goods_qty"></el-option>
                        <el-option label="指定商品实付金额" value="goods_amount"></el-option>
                      </el-select>
                    </el-form-item>
                    <el-form-item v-if="isGoodsMetric" label="指定商品" required>
                      <div class="goods-box">
                        <div v-for="(g, gi) in form.trigger.goods_list" :key="g.id" class="goods-item">
                          <el-image :src="g.cover_pic" class="goods-img" fit="cover"></el-image>
                          <span class="goods-name" :title="g.name">{{ g.name }}</span>
                          <el-button type="text" size="mini" class="goods-del" @click="form.trigger.goods_list.splice(gi, 1)">删除</el-button>
                        </div>
                        <goods-picker @selected="selectedGoods" :limit="50">
                          <el-button type="primary" plain size="mini" icon="el-icon-plus">选择商品 ({{ form.trigger.goods_list.length }}/50)</el-button>
                        </goods-picker>
                      </div>
                    </el-form-item>
                    <el-form-item v-if="isRepeat" label="循环规则" required>
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="sub-txt">每满</span>
                        <el-input-number v-model="form.trigger.step" :min="0" :precision="isIntMetric ? 0 : 2" :controls="false" style="width:100px"></el-input-number>
                        <span class="sub-txt">{{ metricUnit }}奖励一次;每个周期最多</span>
                        <el-input-number v-model="form.trigger.max_times" :min="0" :precision="0" :controls="false" style="width:80px"></el-input-number>
                        <span class="sub-txt">次</span>
                        <span class="tip-txt">(0 代表不限)</span>
                      </div>
                    </el-form-item>
                    <el-form-item label="统计周期">
                      <el-select v-model="form.trigger.period" style="width:100%">
                        <el-option label="活动期内" value="campaign"></el-option>
                        <el-option label="每自然月 (每月重新累计)" value="month"></el-option>
                        <el-option label="每自然周 (每周重新累计)" value="week"></el-option>
                        <el-option label="终身累计 (含活动前的历史订单)" value="lifetime"></el-option>
                      </el-select>
                    </el-form-item>
                    <div class="tip-line">订单完成结算后才计入;售后退款导致不再达标时,已发奖励自动扣回。奖励在「奖励与预算」页配置。</div>
                  </template>

                  <template v-if="form.trigger.event==='new_customer_rebuy'">
                    <el-form-item label="复购条件">
                      <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="sub-txt">首单付款后</span>
                        <el-input-number v-model="form.trigger.rebuy_days" :min="1" :precision="0" :controls="false" style="width:80px"></el-input-number>
                        <span class="sub-txt">天内完成第二单</span>
                      </div>
                    </el-form-item>
                    <div class="tip-line">第二单完成结算时发放;若首单或第二单退款导致不再满足,自动扣回。</div>
                  </template>

                  <template v-if="form.trigger.event==='invite_first_order'">
                    <el-form-item label="邀请上限">
                      <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="sub-txt">每个邀请人最多奖励</span>
                        <el-input-number v-model="form.trigger.max_per_inviter" :min="0" :precision="0" :controls="false" style="width:80px"></el-input-number>
                        <span class="sub-txt">人</span>
                        <span class="tip-txt">(0 代表不限)</span>
                      </div>
                    </el-form-item>
                    <el-form-item label="绑定要求">
                      <div style="display: flex; align-items: center; gap: 12px;">
                        <el-switch v-model="form.trigger.require_bind_before"></el-switch>
                        <span class="tip-txt" style="margin: 0;">只认下单前就已绑定的邀请关系(防先下单后补绑)</span>
                      </div>
                    </el-form-item>
                    <div class="tip-line">被邀请人第一笔订单完成结算时发放;被邀请人没有任何有效订单后自动扣回。人群定向对邀请人生效。</div>
                  </template>

                  <el-form-item v-if="form.trigger.event==='level_up'" label="目标等级">
                    <el-input v-model="form.trigger.target_level_ids_text" placeholder="等级ID，多个以英文逗号分隔；留空代表任意等级"></el-input>
                  </el-form-item>
                  
                  <el-form-item v-if="form.trigger.event==='birthday'" label="生日口径">
                    <el-radio-group v-model="form.trigger.mode">
                      <el-radio label="day">生日当天</el-radio>
                      <el-radio label="month">生日当月</el-radio>
                    </el-radio-group>
                  </el-form-item>
                  
                  <el-form-item v-if="form.trigger.event==='inactive'" label="召回天数">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <span class="sub-txt">末次下单</span>
                      <el-input-number v-model="form.trigger.days" :min="1" :controls="false" style="width:80px"></el-input-number>
                      <span class="sub-txt">天前 ；冷却</span>
                      <el-input-number v-model="form.trigger.cooldown_days" :min="1" :controls="false" style="width:80px"></el-input-number>
                      <span class="sub-txt">天 (避免重复赠送)</span>
                    </div>
                  </el-form-item>
                </div>

                <!-- 群发型: 计划时间 -->
                <div v-if="form.type === 2" class="rules-section">
                  <div class="section-title">群发计划配置</div>
                  <el-form-item label="发放时刻">
                    <el-date-picker v-model="form.plan_at" type="datetime" placeholder="留空默认立即群发" value-format="timestamp" style="width:100%"></el-date-picker>
                  </el-form-item>
                  <div class="tip-line">群发开始后再修改保存不会重新群发,同一活动每人只发一次;需要再发一轮请新建活动。</div>
                </div>

                <!-- 领取型: 每人限领 -->
                <div v-if="form.type === 3" class="rules-section">
                  <div class="section-title">主动领取规则</div>
                  <el-form-item label="每人限领">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <el-input-number v-model="form.per_user_limit" :min="0" :controls="false" style="width:100px"></el-input-number>
                      <span class="sub-txt">次</span>
                      <span class="tip-txt">(0 代表不限;发{{ $noun('integral') }}/{{ $noun('balance') }}时必须设置。配置后可在 C 端领券中心展示)</span>
                    </div>
                  </el-form-item>
                </div>
              </div>
            </el-tab-pane>

            <!-- Tab 2: 奖励 & 预算 -->
            <el-tab-pane label="奖励与预算" name="coupons">
              <div class="tab-pane-content">
                <div class="coupon-section">
                  <template v-if="isCumulative">
                    <div class="section-header">
                      <span class="title-txt">阶梯档位与奖励</span>
                      <el-button type="primary" size="mini" plain icon="el-icon-plus" @click="addTier">添加档位</el-button>
                    </div>
                    <div class="tip-line">达到哪档发哪档,一单跨多档会一起发;保存时按门槛从小到大自动排序。进行中的活动不建议修改档位。</div>
                    <div class="coupon-list">
                      <div v-for="(t, i) in form.trigger.tiers" :key="i" class="tier-card">
                        <div class="tier-head">
                          <span class="tier-name">档位 {{ i + 1 }}</span>
                          <span class="sub-txt">累计满</span>
                          <el-input-number v-model="t.threshold" :min="0" :precision="isIntMetric ? 0 : 2" :controls="false" size="small" style="width:110px"></el-input-number>
                          <span class="sub-txt">{{ metricUnit }}</span>
                          <el-button type="text" size="mini" class="tier-del" @click="form.trigger.tiers.splice(i, 1)">删除档位</el-button>
                        </div>
                        <reward-editor v-model="t.rewards" :coupon-options="couponOptions"></reward-editor>
                      </div>
                      <div v-if="form.trigger.tiers.length === 0" class="coupon-empty-tips">暂无档位,请点击上方"添加档位"。</div>
                    </div>
                  </template>
                  <template v-else-if="isInvite">
                    <div class="section-header">
                      <span class="title-txt">邀请人奖励</span>
                    </div>
                    <reward-editor v-model="form.rewards" :coupon-options="couponOptions"></reward-editor>
                    <div class="section-header" style="margin-top: 20px;">
                      <span class="title-txt">被邀请人奖励 (选填)</span>
                    </div>
                    <reward-editor v-model="form.invitee_rewards" :coupon-options="couponOptions"></reward-editor>
                  </template>
                  <template v-else>
                    <div class="section-header">
                      <span class="title-txt">发放奖励</span>
                    </div>
                    <reward-editor v-model="form.rewards" :coupon-options="couponOptions"></reward-editor>
                  </template>
                </div>

                <div class="budget-section">
                  <div class="section-title">预算控制配置</div>
                  <el-form-item :label="$noun('coupon') + '预算'">
                    <el-select v-model="form.budget_type" style="width:100%">
                      <el-option label="无预算限制 (不限)" :value="0"></el-option>
                      <el-option label="按发放张数控制" :value="1"></el-option>
                      <el-option label="按发放总面值控制 (元)" :value="2"></el-option>
                    </el-select>
                  </el-form-item>
                  
                  <el-form-item v-if="form.budget_type!==0" label="预算上限">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <el-input-number v-model="form.budget_total" :min="0" :controls="false" style="width:160px"></el-input-number>
                      <span class="sub-txt">{{ form.budget_type===1?'张':'元' }}</span>
                      <span class="tip-txt">(达到上限后活动将自动标记为结束)</span>
                    </div>
                  </el-form-item>

                  <el-form-item :label="$noun('integral') + '预算'">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <el-input-number v-model="form.budget_integral" :min="0" :precision="0" :controls="false" style="width:160px"></el-input-number>
                      <span class="tip-txt">(0 代表不限;不够发一整份时该份不发)</span>
                    </div>
                  </el-form-item>

                  <el-form-item :label="$noun('balance') + '预算'">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <el-input-number v-model="form.budget_balance" :min="0" :precision="2" :controls="false" style="width:160px"></el-input-number>
                      <span class="sub-txt">元</span>
                      <span class="tip-txt">(0 代表不限)</span>
                    </div>
                  </el-form-item>

                  <el-form-item label="活动备注">
                    <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="活动备忘录（选填）" maxlength="255"></el-input>
                  </el-form-item>
                </div>
              </div>
            </el-tab-pane>

            <!-- Tab 3: 受众人群 -->
            <el-tab-pane label="定向受众" name="audience">
              <div class="tab-pane-content">
                <div class="audience-tips">
                  <i class="el-icon-info"></i>
                  <span>以下过滤条件均可选填；留空默认针对全体合规会员，多个条件间为“且”的关系。</span>
                </div>
                
                <el-form-item label="会员等级">
                  <el-input v-model="form.audience.level_ids_text" placeholder="等级ID，多个以英文逗号分隔；留空代表不限等级"></el-input>
                </el-form-item>
                
                <el-form-item label="累计消费金额">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <el-input-number v-model="form.audience.consume_min" :min="0" :controls="false" placeholder="最小值" style="width:130px"></el-input-number>
                    <span class="sub-txt">至</span>
                    <el-input-number v-model="form.audience.consume_max" :min="0" :controls="false" placeholder="最大值" style="width:130px"></el-input-number>
                    <span class="sub-txt">元</span>
                  </div>
                </el-form-item>
                
                <el-form-item label="仅首单人群">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <el-switch v-model="form.audience.only_first" :active-value="1" :inactive-value="0"></el-switch>
                    <span class="tip-txt" style="margin: 0;">开启后，本活动将仅筛选从未下过单的新客用户</span>
                  </div>
                </el-form-item>
                
                <el-form-item label="沉睡召回天数">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="sub-txt">末次下单</span>
                    <el-input-number v-model="form.audience.last_order_days" :min="0" :controls="false" style="width:100px"></el-input-number>
                    <span class="sub-txt">天前</span>
                    <span class="tip-txt">(0 代表不限制沉睡天数)</span>
                  </div>
                </el-form-item>
              </div>
            </el-tab-pane>
          </el-tabs>
        </el-form>
      </div>
      
      <div class="drawer-footer">
        <el-button size="small" @click="editVisible=false">取消</el-button>
        <el-button size="small" type="primary" :loading="saving" @click="save">保存</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script>
import RewardEditor from './RewardEditor.vue';

export default {
  name: 'marketing-campaign',
  components: { RewardEditor },
  data() {
    return {
      loading: false, list: [], pagination: null,
      searchForm: { page: 1, limit: 10, type: '', status: '', keyword: '' },
      editVisible: false, saving: false, couponOptions: [], orderTypeOptions: [],
      form: this.emptyForm(),
      activeTab: 'basic',
    };
  },
  computed: {
    isCumulative() {
      return this.form.type === 1 && this.form.trigger.event === 'order_cumulative';
    },
    isRepeat() {
      return this.form.type === 1 && this.form.trigger.event === 'order_repeat';
    },
    isInvite() {
      return this.form.type === 1 && this.form.trigger.event === 'invite_first_order';
    },
    isOrderEvent() {
      return this.form.type === 1
        && ['order_paid', 'order_cumulative', 'order_repeat', 'new_customer_rebuy', 'invite_first_order'].includes(this.form.trigger.event);
    },
    isGoodsMetric() {
      return ['goods_qty', 'goods_amount'].includes(this.form.trigger.metric);
    },
    isIntMetric() {
      return ['order_count', 'goods_qty'].includes(this.form.trigger.metric);
    },
    metricUnit() {
      return { order_count: '单', goods_qty: '件' }[this.form.trigger.metric] || '元';
    },
  },
  created() { this.getList(); this.loadCouponOptions(); this.loadOrderTypes(); },
  methods: {
    emptyReward() {
      return { reward_type: 'coupon', coupon_id: '', amount: 0, num: 1 };
    },
    emptyForm() {
      return {
        id: 0, name: '', type: 1, dateRange: [], plan_at: '',
        trigger: {
          event: 'order_paid', order_type: 'any', min_amount: 0, target_level_ids_text: '', mode: 'day', days: 30, cooldown_days: 30,
          metric: 'order_count', period: 'campaign', tiers: [], goods_list: [], step: 0, max_times: 0,
          rebuy_days: 7, max_per_inviter: 0, require_bind_before: true, order_types: ['mall'],
        },
        rewards: [this.emptyReward()],
        invitee_rewards: [],
        audience: { level_ids_text: '', consume_min: 0, consume_max: 0, only_first: 0, last_order_days: 0 },
        budget_type: 0, budget_total: 0, budget_integral: 0, budget_balance: 0, per_user_limit: 1, remark: '',
      };
    },
    selectedGoods(selection) {
      selection.forEach(g => {
        if (!this.form.trigger.goods_list.some(x => x.id === g.id)) {
          this.form.trigger.goods_list.push({ id: g.id, name: g.name, cover_pic: g.cover_pic });
        }
      });
    },
    addTier() {
      this.form.trigger.tiers.push({ threshold: 0, rewards: [this.emptyReward()] });
    },
    mapRewards(list) {
      return (list || []).map(r => ({ reward_type: r.reward_type, coupon_id: r.coupon_id || '', amount: r.amount || 0, num: r.num || 1 }));
    },
    // 去掉未选券的空行;积分/余额数额必须大于 0。返回 null 表示有非法项
    cleanRewards(list) {
      const out = [];
      for (const r of list || []) {
        if (r.reward_type === 'coupon') {
          if (r.coupon_id) out.push({ reward_type: 'coupon', coupon_id: r.coupon_id, num: r.num });
        } else if (Number(r.amount) > 0) {
          out.push({ reward_type: r.reward_type, amount: r.amount });
        } else {
          return null;
        }
      }
      return out;
    },
    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/plugin/marketing/mall/campaign/index', data: this.searchForm });
        if (res.code === 0) { this.list = res.data.list; this.pagination = res.data.pagination; } else this.$message.error(res.msg);
      } finally { this.loading = false; }
    },
    async loadOrderTypes() {
      const res = await this.$request({ url: '/plugin/marketing/mall/campaign/order-types', data: {} });
      if (res.code === 0) this.orderTypeOptions = res.data.list;
    },
    async loadCouponOptions() {
      const res = await this.$request({ url: '/plugin/marketing/mall/campaign/coupon-options', data: {} });
      if (res.code === 0) this.couponOptions = res.data.list;
    },
    search() { this.searchForm.page = 1; this.getList(); },
    pageChange(p) { this.searchForm.page = p; this.getList(); },
    statusType(s) { return s === 1 ? 'success' : (s === 3 ? 'info' : (s === 2 ? 'warning' : '')); },
    async openEdit(row) {
      this.form = this.emptyForm();
      this.activeTab = 'basic';
      if (row) {
        const res = await this.$request({ url: '/plugin/marketing/mall/campaign/detail', data: { id: row.id } });
        if (res.code !== 0) { this.$message.error(res.msg); return; }
        const d = res.data;
        const t = d.trigger || {};
        this.form = {
          id: d.id, name: d.name, type: d.type,
          dateRange: (d.start_at || d.end_at) ? [d.start_at * 1000, d.end_at * 1000] : [],
          plan_at: '',
          trigger: {
            event: t.event || 'order_paid', order_type: t.order_type || 'any', min_amount: t.min_amount || 0,
            target_level_ids_text: (t.target_level_ids || []).join(','), mode: t.mode || 'day',
            days: t.days || 30, cooldown_days: t.cooldown_days || 30,
            metric: t.metric || 'order_count', period: t.period || 'campaign',
            tiers: (t.tiers || []).map(x => ({ threshold: x.threshold, rewards: this.mapRewards(x.rewards) })),
            goods_list: t.goods_list || [], step: t.step || 0, max_times: t.max_times || 0,
            rebuy_days: t.event === 'new_customer_rebuy' ? (t.days || 7) : 7,
            max_per_inviter: t.max_per_inviter || 0, require_bind_before: t.require_bind_before !== 0,
            order_types: t.order_types || [],
          },
          rewards: d.rewards && d.rewards.length ? this.mapRewards(d.rewards) : [this.emptyReward()],
          invitee_rewards: this.mapRewards(d.invitee_rewards),
          audience: {
            level_ids_text: (d.audience.level_ids || []).join(','),
            consume_min: d.audience.consume_min || 0, consume_max: d.audience.consume_max || 0,
            only_first: d.audience.only_first || 0, last_order_days: d.audience.last_order_days || 0,
          },
          budget_type: d.budget_type, budget_total: d.budget_total,
          budget_integral: d.budget_integral || 0, budget_balance: d.budget_balance || 0,
          per_user_limit: d.per_user_limit != null ? d.per_user_limit : 1, remark: d.remark,
        };
      }
      this.editVisible = true;
    },
    parseIds(text) {
      return String(text || '').split(',').map(s => parseInt(s.trim())).filter(n => n > 0);
    },
    save() {
      if (!this.form.name.trim()) { this.$message.warning('请填写活动名称'); return; }
      if ((this.isCumulative || this.isRepeat) && this.isGoodsMetric && !this.form.trigger.goods_list.length) {
        this.$message.warning('按指定商品统计时,请至少选择一个商品'); this.activeTab = 'basic'; return;
      }
      if (this.isRepeat && !(Number(this.form.trigger.step) > 0)) {
        this.$message.warning('请填写「每满」的数值'); this.activeTab = 'basic'; return;
      }
      let rewards = [];
      let tiers = [];
      let inviteeRewards = [];
      if (this.isCumulative) {
        if (!this.form.trigger.tiers.length) { this.$message.warning('请至少添加一个档位'); this.activeTab = 'coupons'; return; }
        for (let i = 0; i < this.form.trigger.tiers.length; i++) {
          const t = this.form.trigger.tiers[i];
          const list = this.cleanRewards(t.rewards);
          if (!(Number(t.threshold) > 0)) { this.$message.warning(`档位 ${i + 1} 的门槛必须大于 0`); this.activeTab = 'coupons'; return; }
          if (list === null) { this.$message.warning(`档位 ${i + 1} 的${this.$noun('integral')}/${this.$noun('balance')}数额必须大于 0`); this.activeTab = 'coupons'; return; }
          if (!list.length) { this.$message.warning(`档位 ${i + 1} 至少配置一项奖励`); this.activeTab = 'coupons'; return; }
          tiers.push({ threshold: t.threshold, rewards: list });
        }
      } else {
        rewards = this.cleanRewards(this.form.rewards);
        if (rewards === null) { this.$message.warning(`${this.$noun('integral')}/${this.$noun('balance')}数额必须大于 0`); this.activeTab = 'coupons'; return; }
        if (!rewards.length) { this.$message.warning(this.isInvite ? '请至少配置一项邀请人奖励' : '请至少配置一项奖励'); this.activeTab = 'coupons'; return; }
        if (this.form.type === 3 && !(Number(this.form.per_user_limit) > 0) && rewards.some(r => r.reward_type !== 'coupon')) {
          this.$message.warning(`领取型活动发放${this.$noun('integral')}或${this.$noun('balance')}时,必须设置每人限领次数`); this.activeTab = 'basic'; return;
        }
        if (this.isInvite) {
          inviteeRewards = this.cleanRewards(this.form.invitee_rewards);
          if (inviteeRewards === null) { this.$message.warning(`被邀请人奖励的${this.$noun('integral')}/${this.$noun('balance')}数额必须大于 0`); this.activeTab = 'coupons'; return; }
        }
      }
      const dr = this.form.dateRange || [];
      const payload = {
        id: this.form.id, name: this.form.name.trim(), type: this.form.type,
        start_at: dr[0] ? Math.floor(dr[0] / 1000) : 0,
        end_at: dr[1] ? Math.floor(dr[1] / 1000) : 0,
        budget_type: this.form.budget_type, budget_total: this.form.budget_total,
        budget_integral: this.form.budget_integral, budget_balance: this.form.budget_balance,
        per_user_limit: this.form.per_user_limit, remark: this.form.remark,
        rewards,
        invitee_rewards: inviteeRewards,
        audience: {
          level_ids: this.parseIds(this.form.audience.level_ids_text),
          consume_min: this.form.audience.consume_min, consume_max: this.form.audience.consume_max,
          only_first: this.form.audience.only_first, last_order_days: this.form.audience.last_order_days,
        },
      };
      if (this.form.type === 1) {
        const t = this.form.trigger;
        payload.trigger = {
          event: t.event, order_type: t.order_type, min_amount: t.min_amount,
          target_level_ids: this.parseIds(t.target_level_ids_text), mode: t.mode,
          days: t.event === 'new_customer_rebuy' ? t.rebuy_days : t.days, cooldown_days: t.cooldown_days,
          metric: t.metric, period: t.period, tiers,
          goods_ids: t.goods_list.map(g => g.id), step: t.step, max_times: t.max_times,
          max_per_inviter: t.max_per_inviter, require_bind_before: t.require_bind_before ? 1 : 0,
          order_types: t.order_types,
        };
      } else if (this.form.type === 2) {
        payload.batch = { plan_at: this.form.plan_at ? Math.floor(this.form.plan_at / 1000) : 0 };
      }
      this.saving = true;
      this.$request({ url: '/plugin/marketing/mall/campaign/save', method: 'post', data: payload })
        .then(res => {
          if (res.code === 0) { this.$message.success('保存成功'); this.editVisible = false; this.getList(); }
          else this.$message.error(res.msg);
        }).finally(() => { this.saving = false; });
    },
    changeStatus(row, status) {
      const txt = { 1: '启用', 2: '暂停', 3: '结束' }[status];
      this.$confirm(`确认${txt}活动「${row.name}」?`, '提示', { type: 'warning' })
        .then(() => this.$request({ url: '/plugin/marketing/mall/campaign/status', method: 'post', data: { id: row.id, status } })
          .then(res => { res.code === 0 ? (this.$message.success('操作成功'), this.getList()) : this.$message.error(res.msg); }))
        .catch(() => {});
    },
    remove(row) {
      this.$confirm(`确认删除活动「${row.name}」?`, '提示', { type: 'warning' })
        .then(() => this.$request({ url: '/plugin/marketing/mall/campaign/delete', method: 'post', data: { id: row.id } })
          .then(res => { res.code === 0 ? (this.$message.success('已删除'), this.getList()) : this.$message.error(res.msg); }))
        .catch(() => {});
    },
  },
};
</script>

<style lang="scss" scoped>
.app-container { height: calc(100vh - 85px); display: flex; flex-direction: column; overflow: hidden; padding: 15px; box-sizing: border-box; background: #f0f2f5; }
.box-card { flex: 1; display: flex; flex-direction: column; border: none; }
.box-card ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.filter-form { margin-bottom: 10px; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }
.time-cell { font-size: 12px; color: #606266; }

/* Custom Drawer Layout */
.campaign-drawer {
  ::v-deep .el-drawer__header {
    margin-bottom: 12px;
    padding: 20px 24px 0;
    font-size: 18px;
    color: #303133;
  }
}
.drawer-header {
  display: flex;
  align-items: center;
  gap: 12px;

  .title-main {
    font-weight: bold;
    font-size: 16px;
    color: #2c3e50;
  }
  .title-sub {
    font-size: 12px;
    color: #909399;
    background: #f4f4f5;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #e9e9eb;
  }
}
.drawer-body {
  padding: 0 24px 24px;
  overflow-y: auto;
  height: calc(100vh - 130px);
}
.drawer-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 24px;
  border-top: 1px solid #ebedf0;
  background: #fff;
  text-align: right;
  z-index: 10;
}

/* Tabs & Panes Styling */
.form-tabs {
  border: none;
  
  ::v-deep .el-tabs__header {
    margin: 0 0 24px 0;
    border-bottom: 2px solid #f2f6fc;
    background: transparent;
  }
  ::v-deep .el-tabs__item {
    font-weight: bold;
    font-size: 14px;
    color: #606266;
    height: 40px;
    line-height: 40px;
    
    &.is-active {
      color: var(--admin-accent-text);
    }
  }
}
.tab-pane-content {
  padding-bottom: 20px;
}

/* Config Subsections */
.rules-section, .budget-section, .coupon-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px dashed #e4e7ed;

  .section-title {
    font-size: 14px;
    font-weight: bold;
    color: #303133;
    margin-bottom: 20px;
    position: relative;
    padding-left: 10px;

    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 2px;
      width: 4px;
      height: 14px;
      background: var(--admin-primary);
      border-radius: 2px;
    }
  }
}

.sub-txt {
  font-size: 13px;
  color: #606266;
  margin: 0 4px;
}
.tip-txt {
  font-size: 12px;
  color: #909399;
  margin-left: 8px;
}

/* Coupon List Layout */
.coupon-section {
  border-top: none;
  padding-top: 0;

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;

    .title-txt {
      font-size: 14px;
      font-weight: bold;
      color: #303133;
    }
  }

  .coupon-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .coupon-item-card {
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 20px;
    position: relative;
    transition: all 0.2s;

    &:hover {
      border-color: #cbd5e1;
      background: #f1f5f9;
      
      .delete-btn {
        opacity: 1;
      }
    }

    .coupon-select-col {
      flex: 1;
      margin-right: 16px;
    }

    .coupon-qty-col {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-right: 12px;

      .qty-lbl {
        font-size: 13px;
        color: #475569;
      }
      .qty-unit {
        font-size: 13px;
        color: #475569;
        font-weight: bold;
      }
    }

    .delete-btn {
      opacity: 0.8;
      transition: opacity 0.2s;
    }
  }

  .coupon-empty-tips {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    font-size: 13px;
    border: 2px dashed #e2e8f0;
    border-radius: 12px;
    background: #fafafa;
  }
}

.tip-line {
  font-size: 12px;
  color: #909399;
  line-height: 1.6;
  margin: -8px 0 16px;
}
.goods-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;

  .goods-item {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    padding: 4px 8px;
  }
  .goods-img {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    flex-shrink: 0;
  }
  .goods-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
  }
  .goods-del {
    color: #F56C6C;
  }
}
.tier-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
  background: #fff;

  .tier-head {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
  }
  .tier-name {
    font-weight: bold;
    color: #303133;
    margin-right: 8px;
  }
  .tier-del {
    margin-left: auto;
    color: #F56C6C;
  }
}

/* Audience Tab Style */
.audience-tips {
  display: flex;
  align-items: flex-start;
  background: #fdf6ec;
  border: 1px solid #f5dab1;
  color: #e6a23c;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.5;
  margin-bottom: 24px;
  gap: 8px;

  i {
    font-size: 14px;
    margin-top: 2px;
  }
}
</style>
