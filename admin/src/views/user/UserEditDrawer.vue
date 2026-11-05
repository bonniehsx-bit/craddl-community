<template>
  <el-drawer :title="user ? ('用户编辑 · ' + (user.nickname || ('#' + user.id))) : '用户编辑'" :visible.sync="drawerVisible"
    :size="drawerSize" :destroy-on-close="true" append-to-body
    modal-append-to-body custom-class="user-edit-drawer">
    <div class="drawer-content" v-loading="loading">
      <div v-if="user">
        <!-- 顶部用户信息头 -->
        <div class="user-header">
          <!-- 头像支持点击即时修改 -->
          <single-image-selector v-model="user.avatar_url" @input="handleAvatarChange" width="64px" height="64px"
            class="header-avatar"></single-image-selector>

          <div class="header-info">
            <div class="header-row">
              <span class="user-name" :class="{ 'editable-trigger': !isDeleted }"
                :title="isDeleted ? '' : '点击修改昵称'" @click="!isDeleted && editInfo('nickname')">
                {{ user.nickname }} <i v-if="!isDeleted" class="el-icon-edit-outline"></i>
              </span>
              <el-tag size="mini" effect="dark" class="level-tag">{{ user.level_name }}</el-tag>
              <el-tag size="mini" :type="statusInfo.type" effect="plain">{{ statusInfo.text }}</el-tag>
              <el-tag v-if="user.is_inviter == 1" size="mini" type="warning" effect="plain">推广员</el-tag>
            </div>
            <div class="header-row secondary">
              <span>ID: {{ user.id }}</span>
              <span v-if="displayMobile"><i class="el-icon-phone-outline"></i> {{ displayMobile }}</span>
              <span><i class="el-icon-time"></i> 注册 {{ user.created_time }}</span>
            </div>
          </div>
        </div>

        <!-- 标签页 -->
        <el-tabs v-model="activeName" class="user-tabs">
          <!-- Tab 1: 基础信息 -->
          <el-tab-pane label="基础信息" name="basic">
            <el-form label-width="100px" size="small" class="basic-form">
              <div class="form-section-title">个人资料</div>
              <el-row :gutter="40">
                <el-col :span="12">
                  <el-form-item label="昵称">
                    <div v-if="isEditing.nickname" class="inline-edit-box">
                      <el-input v-model="tempInfo.nickname" size="mini" ref="nicknameInput"
                        @blur="saveTextInput('nickname')" @keyup.enter.native="$event.target.blur()"
                        @keyup.esc.native="cancelEdit('nickname')"></el-input>
                    </div>
                    <div v-else class="info-display">
                      <span>{{ user.nickname || '-' }}</span>
                      <i v-if="!isDeleted" class="el-icon-edit-outline icon-edit" @click="editInfo('nickname')"></i>
                    </div>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="手机号码">
                    <div v-if="isEditing.mobile" class="inline-edit-box">
                      <el-input v-model="tempInfo.mobile" size="mini" ref="mobileInput" @blur="saveTextInput('mobile')"
                        @keyup.enter.native="$event.target.blur()" @keyup.esc.native="cancelEdit('mobile')"></el-input>
                    </div>
                    <div v-else class="info-display">
                      <span>{{ displayMobile || '-' }}</span>
                      <i v-if="!isDeleted" class="el-icon-edit-outline icon-edit" @click="editInfo('mobile')"></i>
                    </div>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="真实姓名">
                    <div v-if="isEditing.name" class="inline-edit-box">
                      <el-input v-model="tempInfo.name" size="mini" ref="nameInput"
                        @blur="saveTextInput('name')" @keyup.enter.native="$event.target.blur()"
                        @keyup.esc.native="cancelEdit('name')" maxlength="45" show-word-limit></el-input>
                    </div>
                    <div v-else class="info-display">
                      <span>{{ user.name || '-' }}</span>
                      <i v-if="!isDeleted" class="el-icon-edit-outline icon-edit" @click="editInfo('name')"></i>
                    </div>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="性别">
                    <el-select v-if="!isDeleted" v-model="user.gender" size="mini" style="width: 120px"
                      @change="saveInfo('gender', $event)">
                      <el-option label="未知" :value="0"></el-option>
                      <el-option label="男" :value="1"></el-option>
                      <el-option label="女" :value="2"></el-option>
                    </el-select>
                    <span v-else>{{ genderText }}</span>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <!-- 生日必须存 Y-m-d：后端 User::beforeSave 按它拆出 year/month/day 三列，
                       生日券的自动发放读的是那三列，不是这个字符串。value-format 别改。 -->
                  <el-form-item label="生日">
                    <el-date-picker v-if="!isDeleted" v-model="user.birthday" type="date" size="mini"
                      style="width: 160px" placeholder="选择生日" value-format="yyyy-MM-dd" format="yyyy-MM-dd"
                      @change="saveBirthday"></el-date-picker>
                    <span v-else>{{ user.birthday || '-' }}</span>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="微信号">
                    {{ user.wechat_no || '-' }}
                  </el-form-item>
                </el-col>
                <el-col :span="24">
                  <el-form-item label="个人简介">
                    <div v-if="isEditing.detail" class="inline-edit-box">
                      <el-input v-model="tempInfo.detail" size="mini" type="textarea" :rows="2" ref="detailInput"
                        @blur="saveTextInput('detail')" @keyup.esc.native="cancelEdit('detail')"
                        maxlength="255" show-word-limit></el-input>
                    </div>
                    <div v-else class="info-display">
                      <span>{{ user.detail || '-' }}</span>
                      <i v-if="!isDeleted" class="el-icon-edit-outline icon-edit" @click="editInfo('detail')"></i>
                    </div>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="登录密码">
                    <el-button type="text" icon="el-icon-edit" :disabled="isDeleted" @click="openDialog('password')">修改密码</el-button>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="支付密码">
                    <el-button type="text" icon="el-icon-edit" :disabled="isDeleted" @click="openDialog('payPassword')">修改支付密码</el-button>
                  </el-form-item>
                </el-col>
              </el-row>

              <div class="form-section-title">关系与等级</div>
              <el-row :gutter="40">
                <el-col :span="12">
                  <el-form-item label="会员等级">
                    <span class="mr-10">{{ user.level_name }}</span>
                    <el-button type="text" icon="el-icon-edit" :disabled="isDeleted" @click="openDialog('level')">修改</el-button>
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item label="推荐人">
                    <div class="parent-info">
                      <template v-if="user.parent_id == -1 || user.parent_id > 0">
                        <el-avatar size="small" :src="user.parent_avatar_url"></el-avatar>
                        <span class="parent-name">{{ user.parent_nickname || '平台' }}</span>
                      </template>
                      <template v-else>
                        <span style="color: #909399">未绑定推荐人</span>
                      </template>
                      <div class="actions">
                        <el-button type="text" :disabled="isDeleted" @click="dialogs.userPickerVisible = true">修改</el-button>
                        <el-button type="text" :disabled="isDeleted" @click="bindParent(-1)">设为平台</el-button>
                        <el-button type="text" class="text-danger" :disabled="isDeleted" @click="bindParent(0)">解绑</el-button>
                      </div>
                    </div>
                  </el-form-item>
                </el-col>
              </el-row>

              <div class="form-section-title">其他信息</div>
              <el-form-item label="后台备注">
                <div class="remarks-box">
                  <div class="remarks-content">{{ user.remarks || '暂无备注' }}</div>
                  <el-button type="text" icon="el-icon-edit" @click="openDialog('remarks')">修改备注</el-button>
                </div>
              </el-form-item>
            </el-form>
          </el-tab-pane>

          <!-- Tab 2: 资产财务 -->
          <el-tab-pane label="资产财务" name="assets">
            <div class="asset-section">
              <div class="section-subtitle">我的资产</div>
              <el-row :gutter="20">
                <el-col :span="8">
                  <div class="asset-card-new integral">
                    <div class="label">积分</div>
                    <div class="value">{{ user.integral }}</div>
                    <div class="actions">
                      <el-button type="text" size="mini" :disabled="isDeleted" @click="openDialog('integral')">变更</el-button>
                      <el-button type="text" size="mini" @click="openLogDialog('integral')">明细</el-button>
                    </div>
                  </div>
                </el-col>
                <el-col :span="8">
                  <div class="asset-card-new balance">
                    <div class="label">余额</div>
                    <div class="value">¥{{ user.money }}</div>
                    <div class="actions">
                      <el-button type="text" size="mini" :disabled="isDeleted" @click="openDialog('balance')">变更</el-button>
                      <el-button type="text" size="mini" @click="openLogDialog('balance')">明细</el-button>
                    </div>
                  </div>
                </el-col>
                <el-col :span="8">
                  <div class="asset-card-new commission">
                    <div class="label">佣金</div>
                    <div class="value">¥{{ user.price }}</div>
                    <div class="actions">
                      <el-button type="text" size="mini" :disabled="isDeleted" @click="openDialog('price')">变更</el-button>
                      <el-button type="text" size="mini" @click="openLogDialog('price')">明细</el-button>
                    </div>
                  </div>
                </el-col>
              </el-row>
            </div>

            <div class="asset-section mt-20" v-if="wallets.length">
              <div class="section-subtitle">扩展钱包</div>
              <el-row :gutter="20">
                <el-col :span="8" v-for="w in wallets" :key="w.key">
                  <div class="asset-card-new wallet">
                    <div class="label">{{ w.name }}</div>
                    <div class="value">{{ w.balance }}</div>
                    <div class="actions">
                      <el-button type="text" size="mini" :disabled="isDeleted" @click="openWalletDialog(w)">变更</el-button>
                      <el-button type="text" size="mini" @click="openLogDialog('wallet', w)">明细</el-button>
                    </div>
                  </div>
                </el-col>
              </el-row>
            </div>

            <div class="asset-section mt-20">
              <div class="section-subtitle">交易统计</div>
              <el-row :gutter="20">
                <el-col :span="12">
                  <div class="trade-stat-card">
                    <div class="stat-icon success"><i class="el-icon-s-order"></i></div>
                    <div class="stat-info">
                      <div class="stat-label">累计成交订单</div>
                      <div class="stat-num">{{ user.success_order_count || 0 }} 笔</div>
                      <div class="stat-amount">总金额: ¥{{ user.success_order_price || '0.00' }}</div>
                    </div>
                  </div>
                </el-col>
                <el-col :span="12">
                  <div class="trade-stat-card">
                    <div class="stat-icon refund"><i class="el-icon-refresh-left"></i></div>
                    <div class="stat-info">
                      <div class="stat-label">累计退款订单</div>
                      <div class="stat-num">{{ user.success_refund_count || 0 }} 笔</div>
                      <div class="stat-amount">总金额: ¥{{ user.success_refund_price || '0.00' }}</div>
                    </div>
                  </div>
                </el-col>
              </el-row>
            </div>
          </el-tab-pane>

          <!-- Tab 3: 账号权限 -->
          <el-tab-pane label="账号权限" name="permission">
            <el-form label-width="120px" size="small" class="permission-form">
              <!-- 封禁状态:正常 ↔ 拉黑,可来回切。账号已注销时整组禁用(注销号谈不上封禁) -->
              <el-form-item label="封禁状态">
                <div class="status-block">
                  <!-- 用 :value + @input 而非 v-model:后端确认前不改选中态,取消操作不会误切 -->
                  <el-radio-group :value="Number(user.status)" size="small" @input="onStatusChange"
                    :disabled="Number(user.status) === 2">
                    <el-radio-button :label="0">正常</el-radio-button>
                    <el-radio-button :label="1">拉黑</el-radio-button>
                  </el-radio-group>

                  <el-alert v-if="Number(user.status) === 1" type="error" :closable="false" show-icon
                    class="status-alert">
                    <template slot="title">
                      账号已拉黑<template v-if="user.ban_reason">：{{ user.ban_reason }}</template>
                    </template>
                  </el-alert>
                </div>
                <div class="form-tip" v-if="Number(user.status) === 2">
                  账号已注销，封禁状态不适用。如需拉黑，请先恢复账号。
                </div>
                <div class="form-tip" v-else>拉黑需填写原因；拉黑与解封均写入审计日志。</div>
              </el-form-item>

              <!-- 账号生命周期:注销/恢复是危险的单向动作,不与封禁混在同一个 radio 里 -->
              <el-form-item label="账号生命周期">
                <div class="status-block">
                  <template v-if="Number(user.status) !== 2">
                    <el-button type="danger" plain size="small" @click="deleteAccount">注销账号</el-button>
                    <div class="form-tip">
                      注销后手机号释放为占位号、用户全端强制下线。数据不会删除，可由后台恢复。
                    </div>
                  </template>

                  <template v-else>
                    <el-alert type="info" :closable="false" show-icon class="status-alert">
                      <template slot="title">
                        账号已注销<template v-if="user.account_del_time">（{{ user.account_del_time }}）</template>，手机号已释放为占位号。
                      </template>
                    </el-alert>
                    <el-button type="primary" plain size="small" class="restore-btn" @click="restoreAccount">
                      恢复账号
                    </el-button>
                    <div class="form-tip">
                      恢复将还原原手机号。若该号已被其他账号注册占用，恢复会失败，需先处理占用账号。
                    </div>
                  </template>
                </div>
              </el-form-item>

              <el-form-item label="推广员资格">
                <el-switch v-model="user.is_inviter" :active-value="1" :inactive-value="0" :disabled="isDeleted"
                  @change="saveRadioInfo('is_inviter', $event)" active-text="开启" inactive-text="关闭">
                </el-switch>
                <div class="form-tip">开启后用户可生成推广海报发展下级</div>
              </el-form-item>

              <el-form-item label="免推荐人下单">
                <el-switch v-model="user.order_always" :active-value="1" :inactive-value="0" :disabled="isDeleted"
                  @change="saveRadioInfo('order_always', $event)" active-text="开启" inactive-text="关闭">
                </el-switch>
                <div class="form-tip">仅在系统设置“必须绑定推荐人”的模式下生效，开启后该用户无需绑定即可下单。</div>
              </el-form-item>

              <el-form-item label="禁止自动升级">
                <el-switch v-model="user.disable_auto_upgrade" :active-value="1" :inactive-value="0" :disabled="isDeleted"
                  @change="saveRadioInfo('disable_auto_upgrade', $event)" active-text="禁止" inactive-text="允许">
                </el-switch>
                <div class="form-tip">开启后该用户不再参与会员等级的自动升级（满足条件也不升），后台仍可手动调整其等级。</div>
              </el-form-item>

              <el-divider></el-divider>

              <div class="form-section-title">登录日志</div>
              <el-descriptions :column="2" border size="small">
                <el-descriptions-item label="最近登录时间">{{ user.updated_time }}</el-descriptions-item>
                <el-descriptions-item label="最近登录IP">{{ user.login_ip || '未知' }}</el-descriptions-item>
                <el-descriptions-item label="注册时间">{{ user.created_time }}</el-descriptions-item>
              </el-descriptions>

            </el-form>
          </el-tab-pane>
        </el-tabs>

      </div>
    </div>

    <el-dialog :title="dialogs.asset.title" :visible.sync="dialogs.asset.visible" width="500px" append-to-body
      :close-on-click-modal="false">
      <el-form size="small" label-width="100px">
        <el-form-item :label="'当前' + dialogs.asset.unit">
          <span class="current-asset">{{ dialogs.asset.currentValue }}</span>
        </el-form-item>
        <el-form-item label="操作类型">
          <el-radio-group v-model="dialogs.asset.way">
            <el-radio :label="1">增加</el-radio>
            <el-radio :label="2">减少</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="变更数量">
          <el-input-number v-model="dialogs.asset.num" :precision="2" :step="1" :min="0"
            style="width: 200px;"></el-input-number>
        </el-form-item>
        <el-form-item label="备注">
          <el-input type="textarea" v-model="dialogs.asset.remarks" placeholder="请填写变更原因（选填）" :rows="3"></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dialogs.asset.visible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="confirmAssetChange" :loading="dialogs.asset.loading" size="small">确
          定</el-button>
      </span>
    </el-dialog>

    <el-dialog :title="logDialog.title" :visible.sync="logDialog.visible" width="920px" append-to-body>
      <el-table :data="logDialog.list" v-loading="logDialog.loading" size="small" border stripe max-height="460" empty-text="暂无记录">
        <el-table-column label="变动金额" width="110" align="center">
          <template slot-scope="s">
            <span :style="{ color: s.row.type == 1 ? '#67C23A' : '#F56C6C', fontWeight: 'bold' }">{{ s.row.type == 1 ? '+' : '-' }} {{ s.row[logDialog.amountField] }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90" align="center">
          <template slot-scope="s">
            <el-tag :type="s.row.type == 1 ? 'success' : 'warning'" size="small" effect="dark">{{ typeText(s.row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前余额" width="110" align="center">
          <template slot-scope="s">{{ s.row[logDialog.balanceField] }}</template>
        </el-table-column>
        <el-table-column label="标题" prop="title" min-width="140" show-overflow-tooltip></el-table-column>
        <el-table-column label="详情" min-width="180" show-overflow-tooltip>
          <template slot-scope="s">{{ s.row[logDialog.detailField] || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" prop="created_at" width="150" align="center"></el-table-column>
      </el-table>
      <div style="text-align:right; margin-top:12px;" v-if="logDialog.total > logDialog.limit">
        <el-pagination background layout="total, prev, pager, next" :total="logDialog.total" :page-size="logDialog.limit"
          :current-page="logDialog.page" @current-change="fetchLog"></el-pagination>
      </div>
    </el-dialog>

    <el-dialog title="修改密码" :visible.sync="dialogs.passwordVisible" width="400px" append-to-body>
      <el-input v-model="password" placeholder="请输入新密码"></el-input>
      <span slot="footer">
        <el-button @click="dialogs.passwordVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="setPassword" :loading="submitLoading" size="small">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="修改支付密码" :visible.sync="dialogs.payPasswordVisible" width="400px" append-to-body>
      <el-input v-model="payPassword" maxlength="6" show-password placeholder="请输入6位数字支付密码"></el-input>
      <div style="margin-top:8px;color:#909399;font-size:12px;">用于余额支付、转账等场景；仅支持 6 位数字。</div>
      <span slot="footer">
        <el-button @click="dialogs.payPasswordVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="setPayPassword" :loading="submitLoading" size="small">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="拉黑用户" :visible.sync="dialogs.banVisible" width="440px" append-to-body>
      <el-form label-width="90px" size="small">
        <el-form-item label="封禁原因" required>
          <el-input type="textarea" :rows="3" v-model="banForm.reason" maxlength="255" show-word-limit
            placeholder="必填，将写入审计日志"></el-input>
        </el-form-item>
        <el-form-item label="解封时间">
          <el-date-picker v-model="banForm.until" type="datetime" value-format="timestamp" style="width:100%"
            placeholder="留空 = 永久封禁"></el-date-picker>
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dialogs.banVisible = false" size="small">取 消</el-button>
        <el-button type="danger" @click="banUser" :loading="submitLoading" size="small">确认拉黑</el-button>
      </span>
    </el-dialog>

    <el-dialog title="修改等级" :visible.sync="dialogs.levelVisible" width="400px" append-to-body>
      <el-select v-model="selectedLevel" placeholder="请选择会员等级" style="width: 100%;">
        <el-option v-for="item in levelList" :key="item.id" :label="item.name" :value="item.level"></el-option>
      </el-select>
      <span slot="footer">
        <el-button @click="dialogs.levelVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="levelConfirm" :loading="submitLoading" size="small">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="修改备注" :visible.sync="dialogs.remarksVisible" width="400px" append-to-body>
      <el-input type="textarea" v-model="tempRemarks" :rows="4" placeholder="请输入备注信息"></el-input>
      <span slot="footer">
        <el-button @click="dialogs.remarksVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="remarksConfirm" :loading="submitLoading" size="small">确 定</el-button>
      </span>
    </el-dialog>

    <user-picker :show="dialogs.userPickerVisible" @selected="selectUser"
      @close="dialogs.userPickerVisible = false"></user-picker>
  </el-drawer>
</template>

<script>
import UserPicker from '@/components/mall/UserPicker.vue'

export default {
  name: 'UserEditDrawer',
  components: { UserPicker },
  props: {
    // 控制抽屉显示隐藏
    visible: {
      type: Boolean,
      default: false
    },
    // 接收从列表传过来的用户ID
    userId: {
      type: [String, Number],
      default: ''
    }
  },
  computed: {
    // 双向绑定可见性
    drawerVisible: {
      get() {
        return this.visible;
      },
      set(val) {
        this.$emit('update:visible', val);
      }
    },
    // 根据屏幕宽度自动适应抽屉大小
    drawerSize() {
      return window.innerWidth < 1200 ? '90%' : '1000px';
    },
    // 账号状态徽章（0正常 1拉黑 2已注销）—— 在头部一眼可见，无需进「账号权限」Tab
    // 已注销:账号冻结,禁止一切业务变更(与后端 UserController::DENY_ACTIONS_WHEN_DELETED 对齐)
    isDeleted() {
      return this.user ? Number(this.user.status) === 2 : false
    },
    // 注销后 mobile 被改写成 DEL_<id> 占位号(真号进 backup_mobile),不能当真手机号显示
    displayMobile() {
      if (!this.user || !this.user.mobile) return ''
      if (this.isDeleted && /^DEL_/.test(this.user.mobile)) return '已注销（手机号已释放）'
      return this.user.mobile
    },
    statusInfo() {
      const map = {
        0: { text: '正常', type: 'success' },
        1: { text: '已拉黑', type: 'danger' },
        2: { text: '已注销', type: 'info' }
      }
      return map[this.user ? this.user.status : 0] || { text: '正常', type: 'success' }
    },
    // 只用于已注销用户的只读展示（可编辑时走下拉框）
    genderText() {
      const map = { 0: '未知', 1: '男', 2: '女' }
      return map[this.user ? Number(this.user.gender) : 0] || '未知'
    }
  },
  data() {
    return {
      loading: false,
      submitLoading: false,
      user_id: null,
      user: null,
      wallets: [],
      logDialog: { visible: false, loading: false, title: '', source: '', api: '', amountField: 'amount', balanceField: 'current', detailField: 'content', walletKey: '', list: [], page: 1, limit: 10, total: 0 },
      levelList: [],
      activeName: 'basic',

      // 弹窗相关数据
      password: '',
      payPassword: '',
      banForm: { reason: '', until: null },
      tempRemarks: '',
      selectedLevel: null,
      dialogs: {
        passwordVisible: false,
        payPasswordVisible: false,
        banVisible: false,
        levelVisible: false,
        remarksVisible: false,
        userPickerVisible: false,
        asset: {
          visible: false,
          type: '',
          title: '',
          unit: '',
          currentValue: 0,
          way: 1, // 默认增加
          num: 0,
          remarks: '',
          loading: false,
          typeId: 0
        }
      },

      // 编辑状态管理
      isEditing: {
        nickname: false,
        mobile: false,
        name: false,
        detail: false
      },
      // 临时存储编辑中的值
      tempInfo: {
        nickname: '',
        mobile: '',
        name: '',
        detail: ''
      }
    }
  },
  // 所在页面被标签栏缓存(keep-alive):换页面时页面只是停用不销毁,而抽屉和弹窗都挂在 body 上,
  // 不主动关就会一直盖在新页面上。停用时把抽屉连同它弹出的窗口一起关掉
  deactivated() {
    const d = this.dialogs
    d.passwordVisible = d.payPasswordVisible = d.banVisible = d.levelVisible = d.remarksVisible = d.userPickerVisible = false
    d.asset.visible = false
    this.logDialog.visible = false
    if (this.visible) this.drawerVisible = false
  },
  watch: {
    // 监听抽屉打开/关闭，动态获取数据
    visible(newVal) {
      if (newVal) {
        if (this.userId) {
          this.user_id = this.userId;
          this.getUser();
        } else {
          this.$message.error('参数错误：缺少用户ID');
          this.drawerVisible = false;
        }
      } else {
        // 抽屉关闭时清空数据，防止下一次打开闪烁旧数据
        this.user = null;
        this.user_id = null;
        // 编辑态必须一起清。本组件在 index.vue 是常驻挂载(没有 v-if),
        // destroy-on-close 只销毁 el-drawer 的插槽内容,销毁不掉这里的 data ——
        // 不清的话 isEditing/tempInfo 会带着上一个用户的值进入下一个用户的抽屉。
        this.resetEditState();
      }
    }
  },
  methods: {
    // 已注销账号禁止业务变更。<i @click> 这类元素 :disabled 无效,
    // 所以真正的闸在方法层;模板上的 :disabled 只是视觉反馈。
    // 后端 UserController::beforeAction 有同名守卫兜底 —— 前端禁用是可以绕过的。
    guardDeleted() {
      if (this.isDeleted) {
        this.$message.warning('该用户已注销,请先恢复账号后再操作')
        return true
      }
      return false
    },

    // 获取用户详情
    async getUser() {
      // 换用户 = 换一套编辑态,先清干净再拉数据
      this.resetEditState()
      this.loading = true
      try {
        const res = await this.$request({
          url: '/mall/user/info',
          method: 'get',
          data: { user_id: this.user_id }
        })
        if (res.code === 0) {
          this.user = res.data.user
          this.levelList = res.data.level_list
          this._initialAvatarUrl = this.user.avatar_url // 记录初始加载时的头像防止默认触发
          this.loadWallets()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },

    // 拦截头像修改事件，防止 single-image-selector 初始化时立刻保存
    handleAvatarChange(val) {
      // 先做「初始化 emit / 值未变」的静默判断 —— single-image-selector 挂载时会 emit 一次初始值,
      // guard 若抢在前面,打开任何已注销用户都会无故弹一次「已注销」警告。
      if (!this.user || this._initialAvatarUrl === undefined) return;
      if (val === this._initialAvatarUrl) return;
      // 到这里才是运营真的换了头像
      if (this.guardDeleted()) {
        this.user.avatar_url = this._initialAvatarUrl   // 回滚,避免 UI 显示成已改
        return
      }
      this.saveInfo('avatar_url', val);
      this._initialAvatarUrl = val;
    },

    // 开启内联编辑（姓名/手机号）
    editInfo(key) {
      if (this.guardDeleted()) return
      // 1. 初始化临时值
      this.tempInfo[key] = this.user[key]
      // 2. 开启编辑状态
      this.$set(this.isEditing, key, true)
      // 3. 自动聚焦
      this.$nextTick(() => {
        if (this.$refs[key + 'Input']) {
          this.$refs[key + 'Input'].focus()
        }
      })
    },

    // 取消内联编辑
    cancelEdit(key) {
      this.$set(this.isEditing, key, false)
    },

    // 保存文本输入框的修改 (姓名/手机号)
    async saveTextInput(key) {
      // 预防性检查：如果不在编辑状态，直接返回
      if (!this.isEditing[key]) return

      const newValue = this.tempInfo[key]
      const oldValue = this.user[key]

      // 如果值没有变化，直接关闭编辑状态，不请求接口
      if (newValue === oldValue) {
        this.cancelEdit(key)
        return
      }

      await this.saveInfo(key, newValue)
    },

    // 保存单选框的修改 (权限设置)
    async saveRadioInfo(key, value) {
      // el-switch 的 v-model 已经改了本地值,拦截时要回滚,否则 UI 显示成已开启
      if (this.guardDeleted()) {
        this.user[key] = Number(value) === 1 ? 0 : 1
        return
      }
      await this.saveInfo(key, value)
    },

    // 生日保存。
    // 清空时 el-date-picker 给的是 null，直接发过去后端拿到的是字符串 "null"，
    // 会被当成一个解析不了的生日 —— 统一归成空串，让后端把 year/month/day 一起清零。
    async saveBirthday(val) {
      if (this.guardDeleted()) return
      await this.saveInfo('birthday', val || '')
    },

    // 核心保存逻辑
    //
    // ⚠️ 这里的 await 横跨了「抽屉可能被关掉」的窗口:点遮罩会先让输入框 blur
    // 触发保存、再触发关闭,而关闭的 watch 把 this.user 置成 null。
    // 所以请求前要把用户身份留存下来,回来后判活再回填,否则:
    //   · 写 null → TypeError → 被 catch 吞成「保存失败」(后端其实已经存了)
    //   · 异常还会跳过下面清 isEditing 的那行,编辑态泄漏到下一个用户,
    //     再 blur 一次就把上一个人的昵称写到当前这个人头上。
    async saveInfo(key, value) {
      if (!this.user) return
      const uid = this.user.id
      try {
        const res = await this.$request({
          url: '/mall/user/set-user-info',
          method: 'post',
          data: {
            user_id: uid,
            key: key,
            value: value
          }
        })

        if (res.code === 0) {
          // 提示照给 —— 后端确实存下了,哪怕抽屉这会儿已经关了
          this.$message.success(res.msg)
          // 但只有还停在同一个用户上时,才允许回填界面
          if (this.user && this.user.id === uid) {
            this.user[key] = value
            if (this.isEditing.hasOwnProperty(key)) {
              this.isEditing[key] = false
            }
          }
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        // 程序异常必须留痕,不能再伪装成「保存失败」误导运营
        console.error('[UserEditDrawer.saveInfo]', key, e)
        this.$message.error('保存失败')
      }
    },

    // 清空内联编辑态(昵称/手机号/真实姓名/个人简介)。
    // 抽屉关闭时、以及每次加载新用户前都要调。
    resetEditState() {
      Object.keys(this.isEditing).forEach(k => { this.isEditing[k] = false })
      Object.keys(this.tempInfo).forEach(k => { this.tempInfo[k] = '' })
    },

    // 打开各类弹窗
    openDialog(type) {
      // 备注是运营自己的笔记,已注销账号也允许写
      if (type !== 'remarks' && this.guardDeleted()) return
      switch (type) {
        case 'password':
          this.password = ''
          this.dialogs.passwordVisible = true
          break
        case 'payPassword':
          this.payPassword = ''
          this.dialogs.payPasswordVisible = true
          break
        case 'ban':
          this.banForm = { reason: '', until: null }
          this.dialogs.banVisible = true
          break
        case 'level':
          this.selectedLevel = this.user.level
          this.dialogs.levelVisible = true
          break
        case 'remarks':
          this.tempRemarks = this.user.remarks
          this.dialogs.remarksVisible = true
          break
        case 'integral':
        case 'balance':
        case 'price':
          const assetConfig = {
            integral: { title: '可用积分变更', unit: '积分', value: this.user.integral, typeId: 1 },
            balance: { title: '余额变更', unit: '元', value: this.user.money, typeId: 0 },
            price: { title: '佣金变更', unit: '元', value: this.user.price, typeId: 2 }
          }
          const config = assetConfig[type]
          if (!config) return

          this.dialogs.asset = {
            visible: true,
            type: type,
            title: config.title,
            unit: config.unit,
            currentValue: config.value,
            typeId: config.typeId,
            way: 1,
            num: undefined,
            remarks: '',
            loading: false
          }
          break
      }
    },

    // 提交：修改密码
    async setPassword() {
      if (!this.password) {
        this.$message.warning('请输入新密码')
        return
      }
      this.submitLoading = true
      await this.handleSimpleAction('/mall/user/set-password', { password: this.password }, () => {
        this.dialogs.passwordVisible = false
        this.password = ''
      })
      this.submitLoading = false
    },

    // 提交：修改支付密码(后端 UserForm::setPayPassword,与 C 端 set-pay-pwd 同一存储口径)
    async setPayPassword() {
      if (!/^\d{6}$/.test(this.payPassword || '')) {
        this.$message.warning('请输入6位数字支付密码')
        return
      }
      this.submitLoading = true
      await this.handleSimpleAction('/mall/user/set-pay-password', { pay_password: this.payPassword }, () => {
        this.dialogs.payPasswordVisible = false
        this.payPassword = ''
      })
      this.submitLoading = false
    },

    // ---- 封禁状态:正常 ↔ 拉黑 ----
    // 只管封禁这一个维度。注销/恢复是账号生命周期,走各自的独立按钮,
    // 混进同一个 radio 会让「点『正常』」变成「恢复账号」,那是手滑就能触发的危险迁移。
    onStatusChange(target) {
      const cur = Number(this.user.status)
      const next = Number(target)
      if (cur === next) return
      if (cur === 2) return                          // 已注销:radio 已禁用,兜底再拦一次
      if (next === 1) return this.openDialog('ban')  // 正常 → 拉黑
      if (next === 0) return this.unbanUser()        // 拉黑 → 正常
    },

    // 拉黑(必填原因,可选到期;到期留空 = 永久)
    async banUser() {
      const reason = (this.banForm.reason || '').trim()
      if (!reason) {
        this.$message.warning('请填写封禁原因')
        return
      }
      const data = { reason }
      // el-date-picker 的 timestamp 是毫秒,后端按秒存
      if (this.banForm.until) data.banned_until = Math.floor(this.banForm.until / 1000)
      this.submitLoading = true
      await this.handleSimpleAction('/mall/user/ban', data, () => {
        this.dialogs.banVisible = false
        this.getUser()
      })
      this.submitLoading = false
    },

    async unbanUser() {
      try {
        await this.$confirm('确认解除该用户的拉黑状态？', '解除拉黑', { type: 'warning' })
      } catch (e) { return }
      await this.handleSimpleAction('/mall/user/unban', {}, () => this.getUser())
    },

    // 后台代为注销:释放手机号为 DEL_<id> 占位并强制全端下线,可由「恢复账号」还原
    async deleteAccount() {
      try {
        await this.$confirm(
          '注销后：账号标记删除、手机号释放为占位号、用户全端强制下线。余额、佣金、订单与上下级关系均保留，可由后台「恢复账号」还原。确认注销？',
          '注销账号',
          { type: 'warning', confirmButtonText: '确认注销' }
        )
      } catch (e) { return }
      await this.handleSimpleAction('/mall/user/account-del', {}, () => this.getUser())
    },

    // 恢复注销:还原原手机号;若原号已被他人占用,后端会拒绝并提示
    async restoreAccount() {
      try {
        // 不写「一定能还原」:原手机号若已被其他活跃账号注册占用,后端会拒绝(1062)
        await this.$confirm(
          '恢复后将解除删除标记，并把原手机号还原给该账号。若原手机号已被其他账号注册占用，恢复会失败。确认恢复？',
          '恢复账号',
          { type: 'warning', confirmButtonText: '确认恢复' }
        )
      } catch (e) { return }
      await this.handleSimpleAction('/mall/user/account-restore', {}, () => this.getUser())
    },

    // 提交：修改等级
    async levelConfirm() {
      this.submitLoading = true
      await this.handleSimpleAction('/mall/user/level-change', { level: this.selectedLevel }, () => {
        this.dialogs.levelVisible = false
        this.getUser()
      })
      this.submitLoading = false
    },

    // 提交：修改备注
    async remarksConfirm() {
      this.submitLoading = true
      await this.handleSimpleAction('/mall/user/remarks-change', { remarks: this.tempRemarks }, () => {
        this.dialogs.remarksVisible = false
        this.user.remarks = this.tempRemarks
      })
      this.submitLoading = false
    },

    // 提交：资产变更(核心资产走 /charge;扩展钱包带 walletKey 走 /wallet-charge)
    async confirmAssetChange() {
      const { typeId, way, num, remarks, walletKey } = this.dialogs.asset
      if (!num || num <= 0) {
        this.$message.warning('请输入有效的变更数量')
        return
      }
      this.dialogs.asset.loading = true
      try {
        const isWallet = !!walletKey
        const res = await this.$request({
          url: isWallet ? '/mall/user/wallet-charge' : '/mall/user/charge',
          method: 'post',
          data: isWallet
            ? { user_id: this.user_id, wallet_key: walletKey, way: way, num: num, remarks: remarks }
            : { user_id: this.user_id, type: typeId, way: way, num: num, remarks: remarks }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.dialogs.asset.visible = false
          if (isWallet) {
            this.loadWallets()
          } else {
            this.getUser()
          }
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        // err
      } finally {
        this.dialogs.asset.loading = false
      }
    },

    // 加载用户的扩展钱包(注册表驱动;插件声明了才有)
    async loadWallets() {
      if (!this.user_id) return
      try {
        const res = await this.$request({ url: '/mall/user/wallets', method: 'get', data: { user_id: this.user_id } })
        this.wallets = res.code === 0 ? (res.data.list || []) : []
      } catch (e) {
        this.wallets = []
      }
    },

    // 打开扩展钱包调整弹窗(复用资产弹窗,带 walletKey)
    openWalletDialog(w) {
      if (this.guardDeleted()) return
      this.dialogs.asset = {
        visible: true,
        type: 'wallet',
        walletKey: w.key,
        title: w.name + '变更',
        unit: '',
        currentValue: w.balance,
        typeId: null,
        way: 1,
        num: undefined,
        remarks: '',
        loading: false
      }
    },

    // 绑定/解绑推荐人
    async bindParent(parent_id) {
      if (this.guardDeleted()) return
      let confirmText = '确认要修改推荐人吗？'
      if (parent_id === 0) confirmText = '确认要解绑该用户的推荐人吗？'
      if (parent_id === -1) confirmText = '确认要绑定为平台直属吗？'

      this.$confirm(confirmText, '提示', { type: 'warning' }).then(async () => {
        await this.handleSimpleAction('/mall/user/parent-change', { parent_id }, () => {
          this.getUser()
        })
      }).catch(() => { })
    },

    // 选人回调
    selectUser(user) {
      this.dialogs.userPickerVisible = false
      if (user && user.user_id) {
        if (this.user_id == user.user_id) {
          this.$message.warning('推荐人不能选择自己！')
          return
        }
        this.bindParent(user.user_id)
      }
    },

    // 通用简单操作封装
    async handleSimpleAction(url, data = {}, successCallback) {
      try {
        const res = await this.$request({
          url,
          method: 'post',
          data: { user_id: this.user_id, ...data }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          if (successCallback) successCallback()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        this.$message.error('操作失败')
      }
    },

    // 打开明细弹窗(核心资产走 finance 流水接口;扩展钱包走 wallet-log)。统一 type==1 为入账/收入
    openLogDialog(source, w) {
      const map = {
        integral: { api: '/mall/finance/integral-log', amountField: 'integral', balanceField: 'current', detailField: 'content', title: '积分明细' },
        balance: { api: '/mall/finance/balance-log', amountField: 'money', balanceField: 'current', detailField: 'content', title: '余额明细' },
        price: { api: '/mall/finance/price-log', amountField: 'price', balanceField: 'current', detailField: 'remarks', title: '佣金明细' },
        wallet: { api: '/mall/user/wallet-log', amountField: 'amount', balanceField: 'balance_after', detailField: 'remark', title: ((w && w.name) || '钱包') + '明细' }
      }
      const cfg = map[source]
      if (!cfg) return
      this.logDialog = {
        visible: true, loading: false, title: cfg.title, source: source,
        api: cfg.api, amountField: cfg.amountField, balanceField: cfg.balanceField, detailField: cfg.detailField,
        walletKey: source === 'wallet' ? (w && w.key) : '',
        list: [], page: 1, limit: 10, total: 0
      }
      this.fetchLog(1)
    },

    // 类型文案:钱包用入账/扣减,核心资产用收入/支出(统一 type==1 为入)
    typeText(type) {
      if (this.logDialog.source === 'wallet') return type == 1 ? '入账' : '扣减'
      return type == 1 ? '收入' : '支出'
    },

    // 拉取明细(分页)
    async fetchLog(page) {
      this.logDialog.page = page || 1
      this.logDialog.loading = true
      try {
        const data = { user_id: this.user_id, page: this.logDialog.page, limit: this.logDialog.limit }
        if (this.logDialog.source === 'wallet') data.wallet_key = this.logDialog.walletKey
        const res = await this.$request({ url: this.logDialog.api, method: 'get', data: data })
        if (res.code === 0) {
          this.logDialog.list = res.data.list || []
          this.logDialog.total = res.data.pagination ? (res.data.pagination.total_count || 0) : 0
        }
      } catch (e) {
        // ignore
      } finally {
        this.logDialog.loading = false
      }
    }
  }
}
</script>

<style lang="scss">
/* 修改 Drawer 自带头部的样式，使其更协调 */
.user-edit-drawer {
  .el-drawer__header {
    margin-bottom: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ebeef5;
    font-size: 16px;
    font-weight: bold;
    color: #303133;
  }

  .el-drawer__body {
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
}
</style>

<style lang="scss" scoped>
/* 给 Drawer 内部主容器设定高度及滚动 */
.drawer-content {
  flex: 1;
  overflow-y: auto;
  background-color: #f5f7fa;
  /* 浅灰底色更显层次 */
  padding: 0;
}

/* 顶部用户信息栏 */
.user-header {
  background: #fff;
  padding: 20px 30px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #ebeef5;

  .header-avatar {
    flex-shrink: 0;
    border: 2px solid #fff;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
  }

  .header-info {
    margin-left: 20px;

    .header-row {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 8px;

      &:last-child {
        margin-bottom: 0;
      }

      .el-icon-phone-outline,
      .el-icon-time {
        margin-right: 2px;
      }
    }

    .user-name {
      font-size: 18px;
      font-weight: bold;
      color: #303133;
      margin-right: 10px;
    }

    .secondary {
      font-size: 12px;
      color: #909399;
      gap: 18px;
    }
  }
}

/* 标签页样式调整 */
.user-tabs {
  background: #fff;
  margin: 20px;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #ebeef5;
  box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
  min-height: 500px;
}

.form-section-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 20px;
  padding-left: 10px;
  border-left: 4px solid var(--admin-accent-text);
  line-height: 1;
}

.mr-10 {
  margin-right: 10px;
}

.mt-20 {
  margin-top: 20px;
}

.text-danger {
  color: #F56C6C;
}

/* 基础信息表单 */
.basic-form {
  padding: 10px;
}

/* 内联编辑样式优化 */
.info-display {
  display: flex;
  align-items: center;
  height: 32px;

  &:hover .icon-edit {
    color: var(--admin-accent-text);
  }
}

.inline-edit-box {
  width: 100%;
  max-width: 200px;
}

.icon-edit {
  cursor: pointer;
  margin-left: 8px;
  // 一直显示:原来只在悬停时出现,值为空时只有一个「-」可悬停,运营以为改不了手机号
  color: #C0C4CC;
  display: inline-block;
  font-size: 16px;
}

.parent-info {
  display: flex;
  align-items: center;
  gap: 10px;

  .parent-name {
    font-weight: 500;
  }

  .actions {
    margin-left: 10px;

    .el-button {
      margin-left: 5px;
    }
  }
}

.remarks-box {
  display: flex;
  align-items: flex-start;

  .remarks-content {
    background: #f4f4f5;
    padding: 8px 12px;
    border-radius: 4px;
    color: #606266;
    margin-right: 10px;
    max-width: 500px;
    line-height: 1.5;
  }
}

/* 资产卡片新样式 */
.section-subtitle {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 20px;
  color: #303133;
  padding-left: 10px;
  border-left: 4px solid var(--admin-accent-text);
  line-height: 1;
}

.asset-card-new {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;

  &:hover {
    border-color: var(--admin-accent-border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
  }

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
  }

  &.integral::before {
    background: #F56C6C;
  }

  &.balance::before {
    background: #E6A23C;
  }

  &.commission::before {
    background: #67C23A;
  }

  &.wallet::before {
    background: #909399;
  }

  .label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 10px;
  }

  .value {
    font-size: 24px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 15px;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.2px;
  }

  .actions {
    border-top: 1px solid #f2f6fc;
    padding-top: 10px;
    display: flex;
    justify-content: center;
    gap: 10px;
  }
}

/* 交易统计卡片 */
.trade-stat-card {
  display: flex;
  align-items: center;
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #ebeef5;

  .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    color: #fff;
    font-size: 28px;

    &.success {
      background: linear-gradient(135deg, #67C23A, #95d475);
      box-shadow: 0 4px 10px rgba(103, 194, 58, 0.3);
    }

    &.refund {
      background: linear-gradient(135deg, #F56C6C, #f78989);
      box-shadow: 0 4px 10px rgba(245, 108, 108, 0.3);
    }
  }

  .stat-info {
    flex: 1;

    .stat-label {
      font-size: 13px;
      color: #909399;
    }

    .stat-num {
      font-size: 24px;
      font-weight: bold;
      color: #303133;
      margin: 5px 0;
    }

    .stat-amount {
      font-size: 12px;
      color: #606266;
    }
  }
}

/* 权限表单 */
.permission-form {
  padding: 10px 20px;
}

.status-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
}

.status-alert {
  padding: 8px 12px;

  .restore-btn {
  margin-top: 10px;
}

::v-deep .el-alert__title {
    font-size: 12px;
    line-height: 1.6;
    font-weight: normal;
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
  margin-top: 5px;
}

.current-asset {
  font-size: 18px;
  font-weight: bold;
  color: #E6A23C;
}
</style>
