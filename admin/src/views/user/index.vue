<template>
  <div class="app-container" :class="{ 'is-component': isComponent }">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="header-flex" v-if="!isComponent">
        <span class="card-title">用户管理</span>
        <div>
          <el-button size="mini" icon="el-icon-s-custom" @click="openBatchDialog">批量创建</el-button>
          <el-button type="primary" size="mini" icon="el-icon-plus" @click="openCreateUserDialog">新建用户</el-button>
        </div>
      </div>

      <el-tabs v-model="activeTabName" @tab-click="tabClick" class="custom-tabs shrink-0" v-if="!isComponent">
        <el-tab-pane label="全部用户" name="all"></el-tab-pane>
        <el-tab-pane label="正常" name="0"></el-tab-pane>
        <el-tab-pane label="拉黑" name="1"></el-tab-pane>
        <el-tab-pane label="注销" name="2"></el-tab-pane>
      </el-tabs>

      <div class="filter-container shrink-0" v-if="!isComponent">
        <el-form :inline="true" :model="searchParams" size="mini" class="compact-form">
          <el-form-item>
            <el-input v-model="searchParams.keyword" placeholder="昵称/手机号" clearable @keyup.enter.native="search"
                      class="filter-input">
              <i slot="prefix" class="el-input__icon el-icon-search"></i>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="searchParams.user_id" placeholder="用户ID" clearable
                      @keyup.enter.native="search" class="filter-input-short"></el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="searchParams.parent_id" placeholder="推荐人ID" clearable
                      @keyup.enter.native="search" class="filter-input-short"></el-input>
          </el-form-item>
          <el-form-item>
            <el-input v-model="searchParams.top_parent_id" placeholder="团队长ID" clearable
                      @keyup.enter.native="search" class="filter-input-short"></el-input>
          </el-form-item>
          <el-form-item>
            <el-select v-model="searchParams.level_id" placeholder="会员等级" clearable class="filter-input">
              <el-option v-for="item in levelOptions" :key="item.id" :label="item.name" :value="item.id"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-date-picker v-model="searchParams.search_date" type="daterange" value-format="yyyy-MM-dd"
                            range-separator="-" start-placeholder="注册起" end-placeholder="注册止" class="filter-date">
            </el-date-picker>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
            <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
            <el-button type="warning" icon="el-icon-download" plain @click="openExportDialog">导出</el-button>
          </el-form-item>
        </el-form>
      </div>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table ref="dataTable" :data="list" v-loading="loading" style="width: 100%" height="100%" size="small"
                    border stripe
                    :header-cell-style="{ background: '#F5F7FA', color: '#606266', padding: '8px 0', fontSize: '13px' }"
                    :cell-style="{ padding: '6px 0', fontSize: '13px' }"
                    :default-sort="{ prop: 'created_at', order: 'descending' }"
                    @sort-change="onSortChange"
                    @row-dblclick="onRowDblclick">
            <el-table-column label="ID" width="70" align="center" fixed>
              <template slot-scope="scope">
                <el-button type="text" size="mini" style="padding: 0;" @click="copyText(scope.row.id, '用户ID')"
                           title="点击复制ID">{{ scope.row.id }}
                </el-button>
              </template>
            </el-table-column>

            <el-table-column label="用户信息" min-width="200" fixed show-overflow-tooltip>
              <template slot-scope="scope">
                <div class="user-info-row">
                  <el-avatar :size="36" :src="scope.row.avatar_url" shape="square" class="avatar-shadow"></el-avatar>
                  <div class="info-col">
                    <div class="nickname" :title="scope.row.nickname">{{ scope.row.nickname }}</div>
                    <div class="meta flex-row" style="display: flex; align-items: center;">
                      <span class="mr-10" style="display: inline-flex; align-items: center;">
                        <i class="el-icon-mobile-phone" style="margin-right: 3px;"></i>
                        <el-button v-if="scope.row.mobile" type="text" size="mini" style="padding: 0; height: auto;"
                                   @click="copyText(scope.row.mobile, '手机号')" title="点击复制">{{
                            scope.row.mobile
                          }}</el-button>
                        <span v-else>无</span>
                      </span>
                      <el-tag size="mini" type="info" effect="plain" class="mini-tag">{{ scope.row.level }}</el-tag>
                    </div>
                  </div>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="推荐关系" min-width="140" show-overflow-tooltip>
              <template slot-scope="scope">
                <div class="parent-info">
                  <div v-if="scope.row.parent_id > 0">
                    <div class="p-val">
                      <el-avatar :size="18" :src="scope.row.parent_avatar" class="mini-avatar"></el-avatar>
                      <span class="p-name">{{ scope.row.parent_name }}</span>
                      <span class="p-id" style="margin-left: 2px;">(ID:
                        <el-button type="text" size="mini"
                                   style="padding: 0; height: auto; font-size: 11px; color: #999;"
                                   @click="copyText(scope.row.parent_id, '推荐人ID')"
                                   title="点击复制">{{ scope.row.parent_id }}</el-button>)
                      </span>
                    </div>
                    <div v-if="scope.row.parent_at_fmt" class="text-muted" style="font-size: 11px; margin-top: 4px;">
                      绑定: {{ scope.row.parent_at_fmt }}
                    </div>
                  </div>
                  <div v-else class="p-val text-muted">无绑定推荐人</div>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="资产账户" min-width="160">
              <template slot-scope="scope">
                <div class="asset-grid">
                  <div class="asset-item"><span class="lbl">余额</span> <span class="val text-danger">¥{{
                      scope.row.money
                    }}</span></div>
                  <div class="asset-item"><span class="lbl">佣金</span> <span class="val text-danger">¥{{
                      scope.row.price
                    }}</span></div>
                  <div class="asset-item"><span class="lbl">积分</span> <span class="val text-primary">{{
                      scope.row.integral
                    }}</span></div>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="消费统计" min-width="140" align="center">
              <template slot-scope="scope">
                <div class="stat-box">
                  <div>订单: <b>{{ scope.row.order_num }}</b></div>
                  <div>消费: <b>¥{{ scope.row.total_order_price }}</b></div>
                </div>
              </template>
            </el-table-column>

            <el-table-column prop="created_at" label="注册时间" min-width="150" align="center"
                             sortable="custom"></el-table-column>

            <el-table-column label="状态" min-width="80" align="center">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.status == 0" type="success" size="mini" effect="plain"
                        class="mini-tag">正常
                </el-tag>
                <el-tag v-else-if="scope.row.status == 1" type="danger" size="mini" effect="plain"
                        class="mini-tag">拉黑
                </el-tag>
                <el-tag v-else type="info" size="mini" effect="plain" class="mini-tag">注销</el-tag>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="300" align="center" fixed="right">
              <template slot-scope="scope">
                <div class="action-cell">
                  <el-button type="text" size="mini" icon="el-icon-edit"
                             @click="openEditDrawer(scope.row.id)">编辑
                  </el-button>
                  <el-button type="text" size="mini" icon="el-icon-s-order" @click="order(scope.row.id)">订单
                  </el-button>
                  <el-button type="text" size="mini" icon="el-icon-connection"
                             @click="openChildDialog(scope.row.id)">直推
                  </el-button>
                  <el-button type="text" size="mini" icon="el-icon-top"
                             @click="triggerLevelCheck(scope.row)">升级检测
                  </el-button>
                  <!-- 行内封禁/解封快捷操作 -->
                  <el-button
                    v-if="scope.row.status == 0"
                    type="text"
                    size="mini"
                    icon="el-icon-remove-outline"
                    style="color: #F56C6C;"
                    @click="openBanDialog(scope.row, 'ban')"
                  >拉黑
                  </el-button>
                  <el-button
                    v-else-if="scope.row.status == 1"
                    type="text"
                    size="mini"
                    icon="el-icon-circle-check"
                    style="color: #67C23A;"
                    @click="openBanDialog(scope.row, 'unban')"
                  >解封
                  </el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination background layout="total, sizes, prev, pager, next, jumper"
                       :total="pagination ? pagination.total_count : 0" :page-sizes="[10, 20, 50, 100]"
                       :page-size="searchParams.limit" :current-page.sync="searchParams.page"
                       @size-change="handleSizeChange"
                       @current-change="pageChange">
        </el-pagination>
      </div>
    </el-card>

    <el-dialog title="新增用户" :visible.sync="userDialogVisible" width="450px" custom-class="round-dialog"
               :close-on-click-modal="false">
      <el-form size="mini" ref="form" :model="form" label-width="90px" class="dialog-form">
        <el-form-item label="头像">
          <single-image-selector v-model="form.avatar_url"></single-image-selector>
        </el-form-item>
        <el-form-item label="用户昵称" required>
          <el-input v-model="form.nickname" placeholder="请输入昵称"></el-input>
        </el-form-item>
        <el-form-item label="手机号码">
          <el-input v-model="form.mobile" placeholder="请输入手机号"></el-input>
        </el-form-item>
        <el-form-item label="登录密码">
          <el-input v-model="form.password" show-password placeholder="不填则无法通过密码登录"></el-input>
        </el-form-item>
        <el-form-item label="推荐人ID">
          <el-input v-model="form.parent_id" placeholder="选填"></el-input>
        </el-form-item>
        <el-form-item label="备注信息">
          <el-input type="textarea" v-model="form.remarks" :rows="2"></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="userDialogVisible = false" size="mini">取消</el-button>
        <el-button type="primary" @click="submit" :loading="submitLoading" size="mini">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="批量创建用户" :visible.sync="batchDialogVisible" width="680px" top="6vh"
               custom-class="round-dialog batch-dialog" :close-on-click-modal="false">
      <el-alert type="warning" show-icon :closable="false" class="batch-alert"
                title="测试用途：批量生成手机号自增的用户，遇到已存在的号会自动跳过"></el-alert>

      <el-form size="mini" :model="batchForm" label-width="104px" class="dialog-form batch-form">
        <!-- 短字段两列排,长字段独占一行 —— 竖着堆七八个 form-item 会把对话框拉得很高 -->
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="起始手机号" required>
              <el-input v-model="batchForm.mobile_start" placeholder="如 19900000001" maxlength="11"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="创建数量" required>
              <el-input-number v-model="batchForm.count" :min="1" :max="batchMaxCount" :step="10"
                               controls-position="right" style="width: 100%;"></el-input-number>
            </el-form-item>
          </el-col>
        </el-row>
        <div class="batch-limit-tip" v-if="batchForm.build_chain">
          <i class="el-icon-info"></i>
          开了关系链，一次最多 100 个——建链每加一个人都要回溯更新它全部上级，开销随人数平方增长。
        </div>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="昵称前缀">
              <el-input v-model="batchForm.nickname_prefix" placeholder="测试用户"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="登录密码">
              <el-input v-model="batchForm.password" placeholder="123456"></el-input>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="备注标记">
          <el-input v-model="batchForm.remarks" placeholder="用于事后筛选清理"></el-input>
        </el-form-item>

        <div class="batch-section">
          可选项<span class="sec-hint">全部不开 = 行为与以前完全一致</span>
        </div>

        <el-form-item label="推广资格">
          <div class="switch-line">
            <el-switch v-model="batchForm.is_inviter" :active-value="1" :inactive-value="0"></el-switch>
            <span class="switch-desc">即「推广员」，同用户详情里的那个开关</span>
          </div>
        </el-form-item>

        <el-form-item label="关系链">
          <div class="switch-line">
            <el-switch v-model="batchForm.build_chain" :active-value="1" :inactive-value="0"></el-switch>
            <span class="switch-desc">按顺序串成一条完整关系链：起始上级 → 第1个 → 第2个 → …</span>
          </div>
          <div class="form-tip">每一环都走正规绑定，<b>user_path、团队统计、绑定事件</b> 一个不少。</div>
          <div class="form-tip is-warn" v-if="batchForm.build_chain && !batchForm.is_inviter">
            <i class="el-icon-warning-outline"></i>
            这批人没有推广资格。链能建出来，但生产里没资格的人拉不到下级，数据会偏离真实场景。
          </div>
        </el-form-item>

        <el-form-item label="起始上级" v-if="batchForm.build_chain">
          <el-input v-model="batchForm.chain_parent"
                    placeholder="手机号或用户ID；留空 = 整批自成一条新链"></el-input>
        </el-form-item>

        <!-- 三项各占一行:680px 里横排三个输入框会被挤到塌行(实测),
             而且做成独立 form-item 才能用左侧标签,比输入框内嵌前缀好读 -->
        <el-form-item label="初始余额">
          <el-input v-model="batchForm.init_money" placeholder="0 或留空 = 不发" class="asset-input">
            <template slot="append">元</template>
          </el-input>
        </el-form-item>
        <el-form-item label="初始积分">
          <el-input v-model="batchForm.init_integral" placeholder="0 或留空 = 不发" class="asset-input">
            <template slot="append">分</template>
          </el-input>
        </el-form-item>
        <el-form-item label="初始佣金">
          <el-input v-model="batchForm.init_price" placeholder="0 或留空 = 不发" class="asset-input">
            <template slot="append">元</template>
          </el-input>
          <div class="form-tip">
            三项每人固定发放。走后台<b>资产变更</b>同一条路，会正常产生流水与操作日志，不是直接改余额字段。
          </div>
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="batchDialogVisible = false" size="mini">取消</el-button>
        <el-button type="primary" @click="submitBatch" :loading="batchLoading" size="mini">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="自定义导出数据" :visible.sync="exportDialogVisible" width="500px" custom-class="round-dialog"
               append-to-body>
      <div class="export-container">
        <el-alert title="勾选需要的字段并使用箭头调整Excel列顺序" type="info" show-icon :closable="false"
                  style="margin-bottom: 10px; padding: 5px 10px;"></el-alert>
        <div class="field-list-wrapper custom-scrollbar">
          <div v-for="(field, index) in exportFields" :key="field.key" class="field-item">
            <el-checkbox v-model="field.selected" size="mini">
              <span class="field-label">{{ field.label }}</span>
            </el-checkbox>
            <div class="field-action">
              <el-button type="text" icon="el-icon-top" size="mini" @click="moveField(index, -1)"
                         :disabled="index === 0"></el-button>
              <el-button type="text" icon="el-icon-bottom" size="mini" @click="moveField(index, 1)"
                         :disabled="index === exportFields.length - 1"></el-button>
            </div>
          </div>
        </div>
      </div>
      <span slot="footer">
        <el-button @click="exportDialogVisible = false" size="mini">取消</el-button>
        <el-button type="primary" @click="confirmExport" size="mini">开始异步导出</el-button>
      </span>
    </el-dialog>

    <!-- 升级检测结果:同步试算,直接展示逐等级、逐条件的达标明细(卡在哪、还差多少) -->
    <el-dialog title="升级检测结果" :visible.sync="levelCheckVisible" width="620px" custom-class="round-dialog"
      :close-on-click-modal="false">
      <div v-loading="levelCheckLoading" style="min-height: 120px;">
        <template v-if="levelCheckData">
          <div style="margin-bottom: 12px;">
            <span style="color:#909399;">当前等级：</span>
            <el-tag size="mini" type="info">{{ levelCheckData.current_level }}</el-tag>
            <el-tag size="mini" :type="levelCheckData.can_upgrade ? 'success' : 'warning'" style="margin-left:8px;">
              {{ levelCheckData.can_upgrade ? ('可升级 → ' + levelCheckData.target_level) : '暂不满足升级条件' }}
            </el-tag>
          </div>
          <el-alert v-if="levelCheckData.msg" :title="levelCheckData.msg" type="info" :closable="false"
            style="margin-bottom:12px;"></el-alert>

          <div v-for="lv in levelCheckData.levels" :key="lv.level_id" class="lv-check-block">
            <div class="lv-check-head">
              <i :class="lv.pass ? 'el-icon-success' : 'el-icon-error'"
                :style="{ color: lv.pass ? '#67C23A' : '#F56C6C', marginRight: '6px' }"></i>
              <strong>{{ lv.name }}</strong>
              <span class="lv-check-logic">{{ lv.logic === 1 ? '（满足任一条件即可）' : '（需全部满足）' }}</span>
            </div>
            <div v-if="lv.reason" class="lv-check-reason">{{ lv.reason }}</div>
            <ul class="lv-check-items">
              <li v-for="(it, idx) in lv.items" :key="idx" :class="it.pass ? 'ok' : 'no'">
                <i :class="it.pass ? 'el-icon-check' : 'el-icon-close'"></i> {{ it.desc }}
              </li>
              <li v-if="!lv.items || !lv.items.length" class="none">{{ lv.reason || '无可判定条件' }}</li>
            </ul>
          </div>
        </template>
        <!-- 试算未返回明细(降级为异步检测)时的兜底,避免空白弹窗 -->
        <el-alert v-else-if="!levelCheckLoading" title="已触发升级检测，后台异步执行，稍后刷新查看等级"
          type="info" :closable="false"></el-alert>
      </div>
      <span slot="footer">
        <el-button type="primary" @click="levelCheckVisible = false" size="mini">知道了</el-button>
      </span>
    </el-dialog>

    <user-edit-drawer :visible.sync="editDrawerVisible" :user-id="currentEditUserId" @success="getList"/>
    <user-order-drawer :visible.sync="orderDrawerVisible" :user-id="currentOrderUserId"/>
    <user-friends-drawer :visible.sync="friendsDrawerVisible" :parent-id="currentFriendsParentId"/>
    <ban-dialog
      :visible.sync="banDialogVisible"
      :mode="banDialogMode"
      :user="currentBanTarget"
      @success="getList"
    />
  </div>
</template>

<script>
import UserEditDrawer from './UserEditDrawer.vue'
import UserOrderDrawer from '@/components/mall/UserOrderDrawer.vue'
import BanDialog from './BanDialog.vue'

export default {
  name: 'user-index',
  components: {
    UserEditDrawer,
    UserOrderDrawer,
    BanDialog,
    UserFriendsDrawer: () => import('@/components/mall/UserFriendsDrawer.vue')
  },
  props: {
    isComponent: {type: Boolean, default: false},
    parentId: {type: [String, Number], default: ''}
  },
  data() {
    return {
      list: [],
      loading: false,
      submitLoading: false,
      pagination: null,
      activeTabName: 'all',
      editDrawerVisible: false,
      currentEditUserId: '',
      orderDrawerVisible: false,
      currentOrderUserId: '',
      searchParams: {
        page: 1,
        limit: 10,
        status: '',
        user_id: '',
        keyword: '',
        parent_id: '',
        top_parent_id: '',
        level_id: '',
        search_date: null,
        // 列表默认最新注册在前,和表头箭头的初始状态一致
        sort_field: 'created_at',
        sort_order: 'desc'
      },
      levelOptions: [],
      userDialogVisible: false,
      form: this.getCleanForm(),
      batchDialogVisible: false,
      batchLoading: false,
      batchForm: {
        mobile_start: '', count: 10, nickname_prefix: '测试用户',
        password: '123456', remarks: '',
        is_inviter: 0, build_chain: 0, chain_parent: '',
        init_money: '', init_integral: '', init_price: ''
      },
      friendsDrawerVisible: false,
      currentFriendsParentId: '',
      banDialogVisible: false,
      banDialogMode: 'ban',
      currentBanTarget: null,
      exportDialogVisible: false,
      levelCheckVisible: false,   // 升级检测结果弹窗
      levelCheckLoading: false,
      levelCheckData: null,
      exportTimer: null,
      exportFields: [
        {key: 'id', label: '用户ID', selected: true},
        {key: 'nickname', label: '用户昵称', selected: true},
        {key: 'mobile', label: '手机号', selected: true},
        {key: 'level_name', label: '会员等级', selected: true},
        {key: 'parent_name', label: '推荐人', selected: true},
        {key: 'money', label: '余额', selected: true},
        {key: 'integral', label: '积分', selected: true},
        {key: 'total_order_price', label: '累计消费', selected: true},
        {key: 'order_num', label: '订单数', selected: true},
        {key: 'created_at', label: '注册时间', selected: true},
        {key: 'status_text', label: '账号状态', selected: true},
        {key: 'platform_text', label: '注册平台', selected: false},
        {key: 'remarks', label: '后台备注', selected: false}
      ]
    }
  },
  computed: {
    // 数量上限两档，必须和后端 UserBatchCreateForm 的 MAX_COUNT / MAX_COUNT_CHAIN 一致——
    // 前端只是提前拦一道，真正的守门人是后端（改了那边记得同步这里，
    // 否则用户会先填满数字再被驳回）。
    batchMaxCount() {
      return this.batchForm.build_chain ? 100 : 200
    }
  },
  watch: {
    // 上限是动态的，开关一变，已经填在框里的数字可能就超了。
    // 不夹回来的话，用户点确定才被后端驳回，白填一遍。
    batchMaxCount(max) {
      if (this.batchForm.count > max) this.batchForm.count = max
    },
    parentId(val) {
      if (this.isComponent) {
        this.searchParams.parent_id = val;
        this.search();
      }
    }
  },
  created() {
    if (this.isComponent && this.parentId) {
      this.searchParams.parent_id = this.parentId;
    }
    this.getList()
    this.loadFieldPreference()
    this.getLevels()
  },
  mounted() {
    window.addEventListener('resize', this.fixTableLayout);
  },
  activated() {
    this.fixTableLayout();
  },
  beforeDestroy() {
    if (this.exportTimer) clearInterval(this.exportTimer);
    window.removeEventListener('resize', this.fixTableLayout);
  },
  methods: {
    fixTableLayout() {
      this.$nextTick(() => {
        if (this.$refs.dataTable) {
          this.$refs.dataTable.doLayout();
        }
      });
    },
    tabClick() {
      this.searchParams.status = this.activeTabName === 'all' ? '' : this.activeTabName;
      this.search();
    },
    openEditDrawer(id) {
      this.currentEditUserId = id;
      this.editDrawerVisible = true;
    },
    // 双击整行 → 打开编辑抽屉 (和点行内"编辑"按钮等价)
    onRowDblclick(row) {
      if (row && row.id) this.openEditDrawer(row.id);
    },
    copyText(text, label) {
      if (!text) return;
      const input = document.createElement('textarea');
      input.value = text;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      try {
        if (document.execCommand('copy')) {
          this.$message.success(`${label} ${text} 已成功复制到剪贴板`);
        }
      } catch (err) {
        this.$message.error('浏览器不支持快捷复制，请手动复制');
      }
      document.body.removeChild(input);
    },
    getLevels() {
      this.$request({
        url: '/mall/user/level'
      }).then(res => {
        if (res.code === 0) {
          this.levelOptions = res.data.list
        }
      })
    },
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/mall/user/index',
          method: 'get',
          data: this.searchParams
        })
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
          this.fixTableLayout()
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    search() {
      this.searchParams.page = 1
      this.getList()
    },
    // 点表头排序:交给后端排全表,不是只排当前这一页(前端排序只会把本页 10 条换个顺序,是假排序)
    onSortChange({ prop, order }) {
      if (!order) { // 第三次点击取消排序,回到默认的最新在前
        this.searchParams.sort_field = 'created_at'
        this.searchParams.sort_order = 'desc'
      } else {
        this.searchParams.sort_field = prop
        this.searchParams.sort_order = order === 'ascending' ? 'asc' : 'desc'
      }
      this.searchParams.page = 1 // 换了排序,当前页码没意义了
      this.getList()
    },
    resetSearch() {
      this.searchParams = {
        page: 1, limit: 10, status: '', user_id: '', keyword: '',
        parent_id: this.isComponent ? this.parentId : '', top_parent_id: '', level_id: '', search_date: null,
        sort_field: 'created_at', sort_order: 'desc'
      }
      if (this.$refs.dataTable) {
        this.$refs.dataTable.sort('created_at', 'descending')
      }
      this.activeTabName = 'all';
      this.getList()
    },
    pageChange(page) {
      this.searchParams.page = page
      this.getList()
    },
    handleSizeChange(val) {
      this.searchParams.limit = val
      this.getList()
    },
    order(id) {
      this.currentOrderUserId = id;
      this.orderDrawerVisible = true;
    },
    triggerLevelCheck(row) {
      this.$confirm(`对「${row.nickname || ('用户' + row.id)}」触发一次会员等级升级检测？满足条件将自动升级。`, '提示', { type: 'info' })
        .then(() => {
          // 后端同步试算(纯只读),直接返回逐等级、逐条件的达标明细;达标才会异步执行真正的升级
          this.levelCheckData = null;
          this.levelCheckLoading = true;
          this.levelCheckVisible = true;
          this.$request({
            url: '/mall/user/trigger-level-check',
            method: 'post',
            data: { user_id: row.id }
          }).then(res => {
            this.levelCheckLoading = false;
            if (res.code === 0) {
              this.levelCheckData = res.data || null;
              this.$message.success(res.msg);
              // 已触发升级 → 稍后刷新列表以显示新等级
              if (res.data && res.data.can_upgrade) setTimeout(() => this.getList(), 2000);
            } else {
              this.levelCheckVisible = false;
              this.$message.warning(res.msg);
            }
          }).catch(() => {
            this.levelCheckLoading = false;
            this.levelCheckVisible = false;
          });
        }).catch(() => {});
    },
    getCleanForm() {
      return {
        avatar_url: '', nickname: '', is_inviter: 1, mobile: '',
        wechat_no: '', remarks: '', platform: 'MPWX', openid: '',
        password: '', parent_mobile: '', parent_id: ''
      }
    },
    openCreateUserDialog() {
      this.form = this.getCleanForm()
      this.userDialogVisible = true
    },
    handleUserClose() {
      this.userDialogVisible = false
    },
    openBatchDialog() {
      const d = new Date()
      const ymd = d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0')
      this.batchForm = {
        mobile_start: '', count: 10, nickname_prefix: '测试用户',
        password: '123456', remarks: '批量创建-' + ymd,
        is_inviter: 0, build_chain: 0, chain_parent: '',
        init_money: '', init_integral: '', init_price: ''
      }
      this.batchDialogVisible = true
    },
    async submitBatch() {
      const f = this.batchForm
      if (!/^1[3-9]\d{9}$/.test(f.mobile_start)) {
        this.$message.error('请填写正确的起始手机号')
        return
      }
      // 二次确认：一点就是几百条数据，点错了清理起来很花时间。
      // 开了关系链/资产之后更要说清楚——那两样会真的发钱、真的改关系结构，
      // 不像单纯建号那样删掉用户就当无事发生。
      const extra = []
      if (f.is_inviter) extra.push('全部授予推广资格')
      if (f.build_chain) {
        extra.push(f.chain_parent
          ? `按顺序挂成一条链（起始上级 ${f.chain_parent}）`
          : '按顺序串成一条新链（第1个人不挂上级）')
      }
      const assets = []
      if (Number(f.init_money) > 0) assets.push(`余额 ${f.init_money}`)
      if (Number(f.init_integral) > 0) assets.push(`积分 ${f.init_integral}`)
      if (Number(f.init_price) > 0) assets.push(`佣金 ${f.init_price}`)
      if (assets.length) extra.push(`每人发放 ${assets.join('、')}（会产生流水）`)

      try {
        await this.$confirm(
          `将从 ${f.mobile_start} 开始创建 ${f.count} 个用户，密码 ${f.password || '123456'}。遇到已存在的号会自动跳过。` +
          (extra.length ? `<br/><br/>另外：<br/>· ${extra.join('<br/>· ')}` : ''),
          '确认批量创建', {
            type: 'warning', confirmButtonText: '开始创建', cancelButtonText: '取消',
            dangerouslyUseHTMLString: true
          }
        )
      } catch (e) {
        return
      }
      this.batchLoading = true
      try {
        const res = await this.$request({
          url: '/mall/user/batch-create',
          method: 'post',
          data: f
        })
        if (res.code === 0) {
          this.batchDialogVisible = false
          this.getList()
          const d = res.data || {}
          // 跳过的号、挂链失败、发资产失败都要单独说清楚：
          // 跳过——否则用户会疑惑「我要 10 个，怎么号不连续」；
          // 后两者——号是建成了的，但关系链断了 / 钱没到账，混在一句「成功」里最容易漏。
          const parts = []
          if (d.skipped_count > 0) {
            parts.push(`跳过已存在号码 ${d.skipped_count} 个：<br/>` +
              `<span style="color:#999;font-size:12px;">${(d.skipped || []).join('、')}</span>`)
          }
          if (d.chain_failed_count > 0) {
            parts.push(`<span style="color:#F56C6C;">挂上级失败 ${d.chain_failed_count} 个</span>（号已建成，但没接进关系链）：<br/>` +
              `<span style="color:#999;font-size:12px;">${(d.chain_failed || []).map(x => `${x.mobile}（${x.reason}）`).join('<br/>')}</span>`)
          }
          if (d.asset_failed_count > 0) {
            parts.push(`<span style="color:#F56C6C;">资产发放失败 ${d.asset_failed_count} 笔</span>：<br/>` +
              `<span style="color:#999;font-size:12px;">${(d.asset_failed || []).map(x => `${x.mobile} ${x.asset}（${x.reason}）`).join('<br/>')}</span>`)
          }
          if (parts.length) {
            this.$alert(`成功创建 ${d.created_count} 个。<br/><br/>` + parts.join('<br/><br/>'),
              '创建完成', {dangerouslyUseHTMLString: true})
          } else {
            this.$message.success(res.msg)
          }
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.batchLoading = false
      }
    },
    async submit() {
      this.submitLoading = true
      try {
        const res = await this.$request({
          url: '/mall/user/create',
          method: 'post',
          data: this.form
        })
        if (res.code === 0) {
          this.handleUserClose()
          this.getList()
          this.$message.success(res.msg)
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.submitLoading = false
      }
    },
    openChildDialog(id) {
      this.currentFriendsParentId = id;
      this.friendsDrawerVisible = true;
    },
    openBanDialog(row, mode) {
      this.currentBanTarget = {
        id: row.id,
        nickname: row.nickname,
        avatar_url: row.avatar_url,
        ban_reason: row.ban_reason || ''
      };
      this.banDialogMode = mode;
      this.banDialogVisible = true;
    },
    openExportDialog() {
      this.exportDialogVisible = true;
    },
    moveField(index, direction) {
      const targetIndex = index + direction
      if (targetIndex < 0 || targetIndex >= this.exportFields.length) return
      const temp = this.exportFields[index]
      this.$set(this.exportFields, index, this.exportFields[targetIndex])
      this.$set(this.exportFields, targetIndex, temp)
      this.saveFieldPreference()
    },
    saveFieldPreference() {
      localStorage.setItem('user_export_fields', JSON.stringify(this.exportFields))
    },
    loadFieldPreference() {
      const saved = localStorage.getItem('user_export_fields')
      if (saved) {
        try {
          const savedArr = JSON.parse(saved)
          const newFields = []
          savedArr.forEach(s => {
            const found = this.exportFields.find(f => f.key === s.key)
            if (found) newFields.push({...found, selected: s.selected})
          })
          this.exportFields.forEach(f => {
            if (!newFields.find(nf => nf.key === f.key)) newFields.push(f)
          })
          this.exportFields = newFields
        } catch (e) {
        }
      }
    },
    confirmExport() {
      const selectedFields = this.exportFields.filter(f => f.selected).map(f => f.key)
      if (selectedFields.length === 0) {
        this.$message.warning('请至少选择一个字段')
        return
      }
      this.exportDialogVisible = false
      this.saveFieldPreference()
      this.handleAsyncExport(selectedFields)
    },
    handleAsyncExport(fields) {
      this.$confirm('数据生成可能需要较长时间，确认进行【异步导出】？', '导出确认', {type: 'info'}).then(() => {
        const loadingInstance = this.$loading({
          lock: true, text: '创建任务中...', spinner: 'el-icon-loading', background: 'rgba(0,0,0,0.7)'
        })

        let params = {...this.searchParams, export_fields: JSON.stringify(fields)}

        this.$request({
          url: '/mall/user/async-export',
          method: 'get',
          data: params
        }).then(res => {
          if (res.code === 0) {
            this.pollExportStatus(res.data.token, loadingInstance)
          } else {
            loadingInstance.close()
            this.$message.error(res.msg)
          }
        }).catch(() => loadingInstance.close())
      }).catch(() => {
      })
    },
    pollExportStatus(token, loadingInstance) {
      if (this.exportTimer) clearInterval(this.exportTimer)
      this.exportTimer = setInterval(() => {
        this.$request({
          url: '/mall/user/export-status',
          method: 'get',
          data: {token: token}
        }).then(res => {
          if (res.code === 0) {
            const status = res.data.status
            if (status === 'processing' || status === 'waiting') {
              loadingInstance.text = `正在生成Excel (${res.data.percentage || 0}%)...`
            } else if (status === 'success') {
              clearInterval(this.exportTimer)
              loadingInstance.close()
              this.$message.success('导出成功，开始下载')
              window.location.href = res.data.url
            } else {
              clearInterval(this.exportTimer)
              loadingInstance.close()
              this.$message.error(res.data.msg || '导出失败')
            }
          } else {
            clearInterval(this.exportTimer)
            loadingInstance.close()
            this.$message.error(res.msg)
          }
        }).catch(() => {
          clearInterval(this.exportTimer)
          loadingInstance.close()
        })
      }, 1500)
    }
  }
}
</script>

<style lang="scss" scoped>
// 表单项下方的灰色说明文字,与 admin 其他页面同一套写法
.form-tip {
  font-size: 12px;
  color: #999;
  line-height: 1.5;
  margin-top: 3px;

  b { color: #666; font-weight: 600; }

  &.is-warn {
    color: #E6A23C;
    background: #FDF6EC;
    border-radius: 4px;
    padding: 5px 8px;
    margin-top: 6px;

    i { margin-right: 3px; }
  }
}

// ── 批量创建对话框 ──
// 字段多,竖着堆会顶到屏幕外,所以:短字段两列排 + 内容区限高滚动 + top 提到 6vh。
.batch-dialog {
  ::v-deep .el-dialog__body {
    max-height: 74vh;
    overflow-y: auto;
    padding: 16px 24px 4px;
  }
}

.batch-alert {
  margin-bottom: 16px;
  padding: 6px 10px;
}

.batch-form {
  // 两列布局下 form-item 的默认下边距偏大,收一点让整体更紧凑
  ::v-deep .el-form-item { margin-bottom: 16px; }

  // 三个资产输入框不占满整行 —— 金额框拉到 560px 宽既没必要也难看
  .asset-input {
    width: 220px;

    ::v-deep .el-input-group__append {
      padding: 0 10px;
      background: #FAFAFA;
      color: #909399;
      font-size: 12px;
    }
  }
}

// 分组小标题 —— 比 el-divider 轻,不会在中间拉出一条横线抢视线
.batch-section {
  display: flex;
  align-items: baseline;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin: 4px 0 14px;
  padding-left: 8px;
  border-left: 3px solid var(--admin-accent-text);

  .sec-hint {
    margin-left: 8px;
    font-size: 12px;
    font-weight: normal;
    color: #C0C4CC;
  }
}

// 开关和它的说明文字同一行,省一行高度,也比 el-switch 自带的 active-text 好读
.switch-line {
  display: flex;
  align-items: center;
  gap: 10px;

  .switch-desc {
    font-size: 12px;
    color: #909399;
    line-height: 1.4;
  }
}

// 数量上限提示挂在两列那一行下面,缩进对齐到输入框左边
.batch-limit-tip {
  margin: -10px 0 14px 104px;
  font-size: 12px;
  color: #E6A23C;

  i { margin-right: 3px; }
}
$primary: #409EFF;
$success: #67C23A;
$danger: #F56C6C;
$info: #909399;

.app-container {
  height: calc(100vh - 85px);
  width: 100%;
  background-color: #f0f2f5;
  padding: 15px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  &.is-component {
    height: 100% !important;
    padding: 0 !important;
    background-color: transparent !important;
  }
}

/* 改造 el-card 成为 Flex 容器 */
.box-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: none;
  border-radius: 6px;

  /* 重写 card header */
  ::v-deep .el-card__header {
    padding: 10px 15px;
    border-bottom: 1px solid #ebeef5;
  }

  /* 重写 card body 极其重要：实现表格自适应高度滚动 */
  ::v-deep .el-card__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 10px 15px;
    min-height: 0;
    /* 修复 Flexbox 下高度溢出的魔法属性 */
  }
}

.shrink-0 {
  flex-shrink: 0;
}

.flex-1 {
  flex: 1;
}

/* 头部 */
.header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .card-title {
    font-size: 15px;
    font-weight: bold;
    color: #303133;
    border-left: 3px solid var(--admin-accent-text);
    padding-left: 8px;
  }
}

/* Tabs */
.custom-tabs {
  margin-top: -5px;

  ::v-deep .el-tabs__header {
    margin: 0 0 10px 0;
  }

  ::v-deep .el-tabs__item {
    font-size: 13px;
    height: 36px;
    line-height: 36px;
  }
}

/* 搜索栏 */
.filter-container {
  background: #fafafa;
  padding: 10px 10px 0 10px;
  border-radius: 4px;
  border: 1px solid #ebeef5;
  margin-bottom: 10px;
}

.compact-form {
  .el-form-item {
    margin-bottom: 10px;
    margin-right: 10px;
  }

  .el-form-item__label {
    padding-right: 6px;
    font-size: 12px;
  }

  .filter-input {
    width: 140px;
  }

  .filter-input-short {
    width: 100px;
  }

  .filter-date {
    width: 210px;
  }
}

/* 【修复核心】表格容器包裹器，采用 absolute 定位彻底解决 Element UI 高度 Bug */
.table-wrapper {
  flex: 1;
  position: relative;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  min-height: 0;
}

.absolute-table-container {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

/* 表格内部元素 */
.user-info-row {
  display: flex;
  align-items: center;

  .avatar-shadow {
    margin-right: 8px;
    border: 1px solid #eee;
    flex-shrink: 0;
  }

  .info-col {
    flex: 1;
    overflow: hidden;
    line-height: 1.2;

    .nickname {
      font-weight: 500;
      font-size: 14px;
      color: #333;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .meta {
      font-size: 12px;
      color: #999;
    }
  }
}

.parent-info {
  font-size: 13px;

  .p-val {
    display: flex;
    align-items: center;
    color: #333;
  }

  .p-name {
    display: inline-block;
    max-width: 65px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0 4px;
  }

  .p-id {
    color: #999;
    font-size: 12px;
  }

  .mini-avatar {
    flex-shrink: 0;
  }
}

.asset-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 3px;

  .asset-item {
    font-size: 12px;
    display: flex;
    justify-content: space-between;

    .lbl {
      color: #999;
    }

    .val {
      font-weight: bold;
      font-family: Arial, sans-serif;
    }

    .text-danger {
      color: $danger;
    }

    .text-primary {
      color: $primary;
    }
  }
}

.stat-box {
  font-size: 12px;
  color: #666;
  text-align: left;
  display: inline-block;
  line-height: 1.4;

  div {
    margin-bottom: 3px;
  }

  b {
    color: #333;
    font-family: Arial, sans-serif;
  }
}

.action-cell {
  display: flex;
  justify-content: center;
  align-items: center;

  .el-button {
    padding: 0 4px;
    margin: 0;
  }
}

/* 分页 */
.pagination-container {
  padding-top: 10px;
  text-align: right;
  background: #fff;
}

.pagination-footer {
  text-align: right;
  padding-top: 10px;
}

/* 通用微调 */
.mini-tag {
  transform: scale(0.9);
  transform-origin: left center;
}

.flex-row {
  display: flex;
  align-items: center;
}

.mr-10 {
  margin-right: 10px;
}

.text-muted {
  color: #bbb;
}

.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* 弹窗及导出相关样式 */
.fake-upload {
  width: 60px;
  height: 60px;
  border: 1px dashed #d9d9d9;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #999;
  cursor: pointer;
  background: #fafafa;
}

.export-container {
  .field-list-wrapper {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid #f0f0f0;
    border-radius: 4px;
    padding: 4px;

    &::-webkit-scrollbar {
      width: 5px;
    }

    &::-webkit-scrollbar-thumb {
      background: #e0e0e0;
      border-radius: 3px;
    }
  }

  .field-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 10px;
    border-bottom: 1px solid #fafafa;

    &:hover {
      background: var(--admin-accent-soft);
    }

    .field-label {
      font-size: 12px;
      margin-left: 6px;
    }
  }
}

.mini-user-row {
  display: flex;
  align-items: center;

  .name {
    margin-left: 8px;
    font-size: 12px;
    color: #333;
  }
}

/* 升级检测结果弹窗 */
.lv-check-block {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 10px 12px;
  margin-bottom: 10px;
  background: #fafafa;

  .lv-check-head {
    display: flex;
    align-items: center;
    font-size: 13px;
    color: #303133;
  }
  .lv-check-logic {
    margin-left: 6px;
    font-size: 12px;
    color: #909399;
    font-weight: normal;
  }
  .lv-check-reason {
    margin: 6px 0 0 20px;
    font-size: 12px;
    color: #67C23A;
  }
  .lv-check-items {
    margin: 6px 0 0;
    padding-left: 20px;
    list-style: none;

    li {
      font-size: 12px;
      line-height: 1.9;
      word-break: break-all;

      i { margin-right: 4px; }
      &.ok { color: #67C23A; }
      &.no { color: #F56C6C; }
      &.none { color: #909399; }
    }
  }
}
</style>
