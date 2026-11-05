<template>
  <div class="entry-wrapper">
    <div class="background-mesh">
      <div class="mesh-blob blob-1"></div>
      <div class="mesh-blob blob-2"></div>
      <div class="mesh-blob blob-3"></div>
    </div>

    <div class="entry-header glass-header">
      <div class="header-left">
        <div class="logo-wrapper">
          <img v-if="siteLogo" :src="siteLogo" class="sys-logo" alt="logo" />
        </div>
        <span class="sys-title">{{ siteTitle || 'Craddl' }}</span>
      </div>

      <div class="header-right">
        <div class="admin-actions">
          <el-tooltip v-if="isAdmin" content="使用指引" placement="bottom" effect="light">
            <div class="action-item" @click="guideVisible = true">
              <i class="el-icon-notebook-2"></i>
            </div>
          </el-tooltip>
          <el-tooltip content="使用协议" placement="bottom" effect="light">
            <button type="button" class="action-item license-action" aria-label="使用协议" @click="$refs.sourceLicense.show()">
              <i class="el-icon-document-checked"></i>
            </button>
          </el-tooltip>
          <template v-if="isAdmin">
            <el-tooltip content="站点配置" placement="bottom" effect="light">
              <div class="action-item" @click="openSiteSetting">
                <i class="el-icon-setting"></i>
              </div>
            </el-tooltip>
            <el-tooltip content="菜单管理" placement="bottom" effect="light">
              <div class="action-item" @click="showMenuManager = true">
                <i class="el-icon-s-operation"></i>
              </div>
            </el-tooltip>
            <el-tooltip content="插件中心" placement="bottom" effect="light">
              <div class="action-item" @click="showPluginManager = true">
                <i class="el-icon-menu"></i>
              </div>
            </el-tooltip>
            <el-tooltip content="系统信息" placement="bottom" effect="light">
              <div class="action-item" @click="openSystemInfo">
                <i class="el-icon-cpu"></i>
              </div>
            </el-tooltip>

            <!-- 系统更新 -->
            <el-tooltip content="系统更新" placement="bottom" effect="light">
              <div class="action-item" @click="openUpdate">
                <el-badge is-dot :hidden="!updateAvailable">
                  <i class="el-icon-refresh"></i>
                </el-badge>
              </div>
            </el-tooltip>

            <!-- 失败任务（调度器死信） -->
            <el-tooltip content="失败任务" placement="bottom" effect="light">
              <div class="action-item" @click="openDeadJob">
                <el-badge :value="deadJobCount" :max="99" :hidden="deadJobCount === 0">
                  <i class="el-icon-warning-outline"></i>
                </el-badge>
              </div>
            </el-tooltip>

            <!-- 调度器状态灯（点击进调度中心） -->
            <el-tooltip :content="schedulerTip + ' · 点击查看调度中心'" placement="bottom" effect="light">
              <div class="scheduler-light" @click="openDashboard">
                <span class="light-dot" :class="schedulerAlive ? 'is-on' : 'is-off'"></span>
                <span class="light-text">调度器</span>
              </div>
            </el-tooltip>

          </template>
          <div class="divider-line"></div>
        </div>

        <div class="time-box">
          <span class="time-icon"><i class="el-icon-time"></i></span>
          <span class="time-text">{{ currentTime }}</span>
        </div>

        <div class="divider-line"></div>

        <div class="user-info">
          <div class="avatar-ring">
            <el-avatar :size="34" :src="avatar || defaultAvatar" class="user-avatar"></el-avatar>
          </div>
          <div class="user-meta">
            <span class="user-hello">Hello,</span>
            <span class="user-name">{{ nickname || name }}</span>
          </div>
        </div>

        <div class="logout-btn" @click="logout">
          <svg-icon icon-class="exit-fullscreen" />
          <span>退出</span>
        </div>
      </div>
    </div>

    <!-- 授权到期提醒(云端下发;过期不封,仅提醒续费) -->
    <div v-if="showLicenseNotice" class="license-notice-bar" :class="licenseNotice.level">
      <i class="ln-icon" :class="licenseNotice.level === 'expired' ? 'el-icon-warning' : 'el-icon-warning-outline'"></i>
      <span class="ln-text">
        <strong>{{ licenseNotice.title }}</strong>
        <span>{{ licenseNotice.message }}</span>
        <em v-if="licenseNotice.contact" class="ln-contact">{{ licenseNotice.contact }}</em>
      </span>
      <i v-if="licenseNotice.level === 'warning'" class="el-icon-close ln-close" title="本次折叠" @click="licenseDismissed = true"></i>
    </div>

    <div class="entry-main">
      <div class="content-container">

        <div class="welcome-section fade-in-down">
          <div class="welcome-text">
            <h2>控制台概览</h2>
            <p>选择一个商城进入管理，或创建新的商城实例。
              <span v-if="isAdmin" class="welcome-guide" @click="guideVisible = true">
                <i class="el-icon-notebook-2"></i> 新手必看 · 使用指引
              </span>
            </p>
          </div>
          <div class="action-btn" v-if="isAdmin">
            <el-button type="primary" class="primary-btn-effect" @click="handleAdd">
              <i class="el-icon-plus"></i> 创建新商城
            </el-button>
          </div>
        </div>

        <div class="mall-list-section" v-loading="loading">
          <el-row :gutter="30" v-if="list.length > 0">
            <el-col
              :xs="24" :sm="12" :md="12" :lg="8" :xl="6"
              v-for="(item, index) in list"
              :key="item.id"
              class="mall-col-item fade-in-up"
              :style="{'animation-delay': (index * 0.08) + 's'}"
            >
              <div class="mall-card" @click="handleEnter(item)">
                <div class="shine-effect"></div>

                <div class="status-badge" :class="{
                  'is-stop': item.is_stop == 1,
                  'is-expire': item.is_expire == 1,
                  'is-active': item.is_stop == 0 && item.is_expire == 0
                }">
                  <span class="status-dot"></span>
                  {{ item.is_stop == 1 ? '已停用' : (item.is_expire == 1 ? '已过期' : '运营中') }}
                </div>

                <div class="card-body">
                  <div class="logo-section">
                    <div class="logo-bg-blur" :style="{ backgroundImage: `url(${item.logo || defaultAvatar})` }"></div>
                    <el-avatar :size="76" :src="item.logo || defaultAvatar" shape="square" class="mall-icon"></el-avatar>
                  </div>

                  <div class="info-section">
                    <h3 class="mall-title" :title="item.name">{{ item.name }}</h3>
                    <div class="mall-id-tag" @click.stop="copyMallId(item.id)" title="点击复制 mall_id">
                      <i class="el-icon-key"></i> ID: {{ item.id }}
                    </div>
                    <div class="expire-tag" :class="{'is-danger': item.is_expire == 1}">
                      <i class="el-icon-timer"></i>
                      {{ item.is_has_expire == 0 ? '永久授权' : formatExpire(item.end_at_text) }}
                    </div>
                  </div>
                </div>

                <div class="card-footer">
                  <span class="enter-text">进入管理控制台</span>

                  <div class="footer-actions" v-if="isAdmin" @click.stop>
                    <el-dropdown trigger="click" placement="top">
                      <div class="more-btn">
                        <i class="el-icon-more"></i>
                      </div>
                      <el-dropdown-menu slot="dropdown" class="custom-dropdown">
                        <el-dropdown-item icon="el-icon-setting" @click.native="handleEdit(item)">配置商城</el-dropdown-item>
                        <el-dropdown-item icon="el-icon-s-custom" @click.native="openTransfer(item)">移交商城</el-dropdown-item>
                        <el-dropdown-item icon="el-icon-delete" divided style="color: #F56C6C" @click.native="handleDelete(item)">删除实例</el-dropdown-item>
                      </el-dropdown-menu>
                    </el-dropdown>
                  </div>
                  <div class="arrow-icon" v-else><i class="el-icon-right"></i></div>
                </div>
              </div>
            </el-col>
          </el-row>

          <div v-else class="empty-placeholder fade-in-up">
            <div class="empty-content">
              <div class="empty-icon-box">
                <img src="@/assets/logo/logo.png" class="empty-img" />
                <div class="pulse-ring"></div>
              </div>
              <h3>暂无可用商城</h3>
              <p>您还没有创建任何商城实例，请联系管理员或点击上方按钮创建。</p>
              <el-button v-if="isAdmin" type="primary" round icon="el-icon-plus" @click="handleAdd" style="margin-top: 25px;">立即创建</el-button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="entry-footer glass-footer">
      <div v-if="siteCopyright || siteRecord" class="footer-content">
        <span v-if="siteCopyright" v-html="siteCopyright" style="margin-right: 15px;"></span>
        <a v-if="siteRecord" href="https://beian.miit.gov.cn/" target="_blank" class="record-link">
          <i class="el-icon-s-check"></i> {{ siteRecord }}
        </a>
      </div>
      <div v-else class="footer-content">
        Copyright © {{ year }} Craddl 商城 All Rights Reserved.
      </div>
    </div>

    <source-license ref="sourceLicense" />

    <el-drawer :title="title" :visible.sync="dialogVisible" size="640px" direction="rtl" append-to-body
               :wrapper-closable="true" @close="resetForm">
      <div class="drawer-wrap">
        <div class="drawer-body">
          <el-form ref="form" :model="form" :rules="rules" label-width="100px" size="medium">
            <el-form-item label="商城名称" prop="name">
              <el-input v-model="form.name" placeholder="请输入商城名称" maxlength="45" show-word-limit />
            </el-form-item>
            <el-form-item label="商城Logo">
              <div class="logo-uploader" :class="{ 'has-file': form.logo }"
                   @click="!form.logo && (logoPickerVisible = true)">
                <div v-if="form.logo" class="preview-area">
                  <img :src="form.logo" alt="商城Logo" />
                  <div class="mask"><i class="el-icon-delete" @click.stop="form.logo = ''"></i></div>
                </div>
                <div v-else class="uploader-trigger"><i class="el-icon-plus"></i></div>
              </div>
              <div class="logo-uploader-tip">点击选择 / 上传图片，建议方形（如 200×200）</div>
              <!-- 受控调起:整个框 @click 打开,选完回填 URL;平台级页面无 mall 上下文,走 is_site -->
              <file-picker :multiple="false" :is_site="1" :openDialog="logoPickerVisible"
                           @closed="logoPickerVisible = false" @selected="onLogoSelected" />
            </el-form-item>
            <el-form-item label="有效期设置">
              <el-radio-group v-model="form.is_has_expire">
                <el-radio :label="0">永久有效</el-radio>
                <el-radio :label="1">设置期限</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="到期时间" v-if="form.is_has_expire === 1" prop="end_at">
              <el-date-picker
                v-model="form.end_at"
                type="datetime"
                value-format="timestamp"
                placeholder="选择到期时间"
                style="width: 100%"
                :picker-options="pickerOptions"
              />
            </el-form-item>
            <el-form-item label="状态设置">
              <el-radio-group v-model="form.is_stop">
                <el-radio :label="0">正常运营</el-radio>
                <el-radio :label="1">停止运营</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="授权插件">
              <div class="plugin-tree-toolbar">
                <el-input
                  v-model="pluginFilterKeyword"
                  size="mini"
                  clearable
                  placeholder="按名称 / 标识搜索插件"
                  prefix-icon="el-icon-search"
                  style="width: 200px;"
                />
                <el-button size="mini" type="text" icon="el-icon-circle-check" @click="checkAllPlugins">全选</el-button>
                <el-button size="mini" type="text" icon="el-icon-circle-close" @click="uncheckAllPlugins">全不选</el-button>
                <el-button size="mini" type="text" icon="el-icon-refresh-left" @click="invertCheckPlugins">反 选</el-button>
                <el-button size="mini" type="text" icon="el-icon-arrow-down" @click="toggleExpandPlugins">
                  {{ pluginExpandAll ? '全部折叠' : '全部展开' }}
                </el-button>
                <span class="plugin-tree-count">已选 {{ checkedPluginCount }} 项</span>
              </div>
              <div class="plugin-tree-box">
                <el-tree
                  ref="tree"
                  :data="plugin_map"
                  node-key="name"
                  :props="{ label: 'label', children: 'children' }"
                  show-checkbox
                  :default-checked-keys="form.plugins"
                  :default-expand-all="pluginExpandAll"
                  :filter-node-method="filterPluginNode"
                  @check-change="onPluginCheckChange"
                />
              </div>
            </el-form-item>

            <!-- 商城归属：仅新建时可指定。编辑/移交走后续单独动作 -->
            <el-form-item label="商城归属" v-if="!form.id">
              <el-radio-group v-model="form.owner_mode">
                <el-radio label="self">我自己管理（超管自营）</el-radio>
                <el-radio label="new">交付商户主账号</el-radio>
              </el-radio-group>
              <div style="font-size:12px;color:#909399;margin-top:4px;line-height:1.6;">
                选「交付商户主账号」会新建一个商户主账号并自动配「店长」角色（含本店全部菜单），开店即交付给商户登录管理。
              </div>
            </el-form-item>

            <template v-if="!form.id && form.owner_mode === 'new'">
              <el-form-item label="主账号用户名">
                <el-input v-model="form.owner_username" placeholder="商户登录用户名" maxlength="30" />
              </el-form-item>
              <el-form-item label="主账号密码">
                <el-input v-model="form.owner_password" placeholder="至少 5 位" show-password maxlength="20" />
              </el-form-item>
              <el-form-item label="主账号手机号">
                <el-input v-model="form.owner_mobile" placeholder="商户手机号" maxlength="20" />
              </el-form-item>
              <el-form-item label="主账号昵称">
                <el-input v-model="form.owner_nickname" placeholder="留空默认用用户名" maxlength="30" />
              </el-form-item>
            </template>
          </el-form>
        </div>
        <div class="drawer-footer">
          <el-button size="small" @click="dialogVisible = false">取 消</el-button>
          <el-button size="small" type="primary" :loading="submitLoading" @click="submit">保 存</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 使用指引 -->
    <el-drawer title="商城权限 · 使用指引" :visible.sync="guideVisible" size="660px" direction="rtl"
               append-to-body :wrapper-closable="true">
      <div class="drawer-wrap">
        <div class="drawer-body guide-body">

          <div class="guide-intro">
            一句话：<b>超管管平台 · 店主管本店 · 员工管被分配的活</b>。下面三步看懂整套流程。
          </div>

          <!-- 三级账号 -->
          <div class="guide-section">
            <div class="guide-h"><i class="el-icon-user-solid"></i> 一、三级账号，各管一摊</div>
            <div class="role-card r1">
              <div class="role-top"><span class="role-name">平台超管</span><span class="role-tag">您 / 平台方</span></div>
              <div class="role-desc">全平台所有商城：开店、交付、移交、停用、授权插件、有效期、全局菜单与定时调度。</div>
            </div>
            <div class="role-card r2">
              <div class="role-top"><span class="role-name">商城主账号（店主）</span><span class="role-tag">每个商户老板</span></div>
              <div class="role-desc">自己这一家店的<b>全部</b>：角色、员工、菜单装修、所有业务功能。但<b>只限本店</b>。</div>
            </div>
            <div class="role-card r3">
              <div class="role-top"><span class="role-name">操作员（员工）</span><span class="role-tag">店里的店员/运营</span></div>
              <div class="role-desc">由店主分配角色，<b>按角色授予的功能模块受限</b>，只能做被授权的事。</div>
            </div>
          </div>

          <!-- 流程 -->
          <div class="guide-section">
            <div class="guide-h"><i class="el-icon-set-up"></i> 二、从开通到运营（5 步）</div>
            <div class="step"><span class="step-no">1</span><div><b>开店</b>　点「创建新商城」，勾选授权插件、设有效期。</div></div>
            <div class="step"><span class="step-no">2</span><div><b>交付</b>　建店时选「交付商户主账号」——新建或指定一个店主账号，系统自动配好「店长」角色（含本店全部功能），<b>开店即交付</b>。</div></div>
            <div class="step"><span class="step-no">3</span><div><b>移交</b>　已开的店随时可在商城卡片「⋯ → 移交商城」转给另一个商户主账号。</div></div>
            <div class="step"><span class="step-no">4</span><div><b>店主管店</b>　店主进店后：建员工（员工管理）、定角色（角色管理）、调菜单（菜单装修，仅影响本店）。</div></div>
            <div class="step"><span class="step-no">5</span><div><b>员工干活</b>　员工进店只看得到、只能操作被授权的模块。</div></div>
          </div>

          <!-- 安全保障 -->
          <div class="guide-section">
            <div class="guide-h"><i class="el-icon-lock"></i> 三、三大安全保障</div>
            <div class="safe-item"><i class="el-icon-circle-check"></i><div><b>多租户隔离</b>：各店数据/账号/角色/菜单严格隔离，A 店看不到也碰不到 B 店，更动不了平台。</div></div>
            <div class="safe-item"><i class="el-icon-circle-check"></i><div><b>后端强制鉴权</b>：不是前端藏按钮，后端对每次操作都校验权限，绕过界面直接调接口也拦得住。</div></div>
            <div class="safe-item"><i class="el-icon-circle-check"></i><div><b>越权天花板</b>：店主只能给员工分配自己有的权限，下级永远超不过上级，杜绝提权。</div></div>
          </div>

        </div>
        <div class="drawer-footer">
          <el-button type="primary" size="small" @click="guideVisible = false">我知道了</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 移交商城归属 -->
    <el-dialog title="移交商城归属" :visible.sync="transferVisible" width="520px" append-to-body @open="onTransferOpen">
      <div style="color:#909399;font-size:12px;margin-bottom:16px;line-height:1.6;">
        将商城 <b>{{ transferMallName }}</b> 移交给商户主账号。移交后该主账号进店即为店主、全权管理本店。
      </div>
      <el-form :model="transferForm" label-width="96px" size="small">
        <el-form-item label="移交方式">
          <el-radio-group v-model="transferForm.owner_mode">
            <el-radio label="exist">选择已有主账号</el-radio>
            <el-radio label="new">新建主账号</el-radio>
          </el-radio-group>
        </el-form-item>
        <template v-if="transferForm.owner_mode === 'exist'">
          <el-form-item label="商户主账号">
            <el-select v-model="transferForm.owner_admin_id" filterable placeholder="选择商户主账号"
                       style="width:100%" v-loading="founderLoading">
              <el-option v-for="f in founderList" :key="f.id"
                         :label="(f.nickname || f.username) + ' / ' + f.mobile" :value="f.id" />
            </el-select>
            <div style="font-size:12px;color:#c0c4cc;margin-top:4px;">仅列出商户主账号(FOUNDER)。</div>
          </el-form-item>
        </template>
        <template v-else>
          <el-form-item label="用户名"><el-input v-model="transferForm.owner_username" maxlength="30" placeholder="商户登录用户名" /></el-form-item>
          <el-form-item label="密码"><el-input v-model="transferForm.owner_password" show-password maxlength="20" placeholder="至少 5 位" /></el-form-item>
          <el-form-item label="手机号"><el-input v-model="transferForm.owner_mobile" maxlength="20" placeholder="商户手机号" /></el-form-item>
          <el-form-item label="昵称"><el-input v-model="transferForm.owner_nickname" maxlength="30" placeholder="留空默认用用户名" /></el-form-item>
        </template>
      </el-form>
      <div slot="footer">
        <el-button size="small" @click="transferVisible = false">取 消</el-button>
        <el-button size="small" type="primary" :loading="transferLoading" @click="submitTransfer">确认移交</el-button>
      </div>
    </el-dialog>

    <plugin-manager v-if="isAdmin" v-model="showPluginManager" @plugin-changed="handlePluginChanged" />
    <system-menu-manager v-if="isAdmin" v-model="showMenuManager" />

    <el-drawer title="系统配置信息" :visible.sync="systemInfoVisible" size="760px" direction="rtl" append-to-body
               :wrapper-closable="true">
      <div class="drawer-wrap">
        <div class="drawer-body" v-loading="systemInfoLoading">
          <div v-for="(g, gi) in systemGroups" :key="gi" class="sysinfo-group">
            <div class="sysinfo-group-title">{{ g.title }}</div>
            <div class="sysinfo-item" v-for="(it, ii) in g.items" :key="ii">
              <span class="sysinfo-label">{{ it.label }}</span>
              <span class="sysinfo-value" :class="'st-' + it.status">
                <span v-if="it.status !== 'none'" class="sysinfo-dot"></span>{{ it.value }}
              </span>
            </div>
          </div>
          <div v-if="!systemInfoLoading && systemGroups.length === 0" class="sysinfo-empty">
            暂无数据
          </div>
        </div>
        <div class="drawer-footer">
          <el-button size="small" type="primary" @click="systemInfoVisible = false">确 定</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 系统更新 -->
    <el-dialog title="系统更新" :visible.sync="updateVisible" width="520px" append-to-body @closed="stopUpdatePoll">
      <div v-loading="updateLoading">
        <!-- 检查中:显著加载动画 + 安抚文案(同步等待最长十几秒,避免被误认为卡死)-->
        <div v-if="checking" class="upd-checking">
          <div class="upd-loader"></div>
          <div class="upd-checking-title">正在检查更新…</div>
          <p class="upd-checking-tip">正在联系调度器并向云端核对最新版本,通常几秒、偶尔十几秒即可完成。<b>请耐心等待、不要关闭窗口,这是正常现象,不用担心。</b></p>
        </div>

        <template v-else>
          <div class="upd-row">
            <span class="upd-label">当前版本</span>
            <span class="upd-cur">{{ updateInfo.current_version || '—' }}<em v-if="updateInfo.current_version_code"> (code {{ updateInfo.current_version_code }})</em></span>
          </div>

          <!-- 有新版 -->
          <div v-if="updateInfo.online_upgrade === false" class="upd-nodata">
            <i class="el-icon-info"></i> {{ updateInfo.upgrade_message }}
          </div>
          <div v-else-if="updateInfo.has_update && updateInfo.latest" class="upd-new">
            <div class="upd-row">
              <span class="upd-label">最新版本</span>
              <span class="upd-latest">{{ updateInfo.latest.latest_version }} (code {{ updateInfo.latest.latest_version_code }})</span>
              <el-tag v-if="updateInfo.latest.force_level" size="mini" :type="forceTagType(updateInfo.latest.force_level)" effect="plain" style="margin-left:8px;">{{ forceLabel(updateInfo.latest.force_level) }}</el-tag>
            </div>
            <div class="upd-log-title">更新日志</div>
            <div class="upd-log">{{ updateInfo.latest.changelog || '（无）' }}</div>
          </div>
          <div v-else-if="!updateInfo.latest && !updateRunning" class="upd-nodata">
            <i class="el-icon-warning-outline"></i> 未获取到云端版本信息(需调度器运行并完成一次心跳)
          </div>
          <div v-else-if="!updateRunning" class="upd-uptodate">
            <i class="el-icon-success"></i> 已是最新版本
          </div>

          <!-- 升级进度:科幻控制台面板 -->
          <div v-if="updateRunning || (updateInfo.status && updateInfo.status.state)" class="sf-panel" :class="{ 'sf-fail': upgradeFailed, 'sf-done': upgradeDone, 'sf-paused': upgradeTerminal }">
            <div class="sf-grid"></div>
            <div v-if="!upgradeTerminal" class="sf-scan"></div>
            <div class="sf-core">
              <div class="sf-ring r1"></div>
              <div class="sf-ring r2"></div>
              <div class="sf-ring r3"></div>
              <div class="sf-orbit"></div>
              <div class="sf-pct">
                <b>{{ upgradePercent }}%</b>
                <span>{{ upgradeFailed ? 'FAILED' : (upgradeDone ? 'DONE' : 'Upgrading') }}</span>
              </div>
            </div>
            <div class="sf-stagerow">
              <template v-for="(st, i) in upgradeSteps">
                <div :key="'s' + i" class="sf-step" :class="{ done: i < upgradeStep, active: i === upgradeStep }">
                  <div class="sf-dot"></div><small>{{ st }}</small>
                </div>
                <div v-if="i < upgradeSteps.length - 1" :key="'l' + i" class="sf-line" :class="{ on: i < upgradeStep }"></div>
              </template>
            </div>
            <div class="sf-bar"><div class="sf-fill" :style="{ width: upgradePercent + '%' }"></div></div>
            <div class="sf-state">{{ upgradeStateText }}<span v-if="updateInfo.status && updateInfo.status.message" class="sf-msg"> · {{ updateInfo.status.message }}</span></div>
            <p v-if="updateRunning" class="sf-tip">升级由调度器在后台执行,期间请勿关闭页面或重启服务;整个过程可能持续数十秒到几分钟,耐心等待属正常,不用担心。</p>
          </div>
        </template>
      </div>
      <div slot="footer">
        <el-button v-if="updateInfo.online_upgrade !== false" style="float:left;" icon="el-icon-refresh" :loading="checking" :disabled="updateRunning" @click="refreshCheck">
          {{ checking ? '检查中…' : '检查更新' }}
        </el-button>
        <el-button plain @click="updateVisible = false">关闭</el-button>
        <el-button v-if="upgradeDone" type="success" icon="el-icon-refresh-right" @click="reloadForUpdate">确定并刷新页面</el-button>
        <el-button v-else-if="updateInfo.online_upgrade !== false" type="primary" :loading="upgrading" :disabled="!updateInfo.has_update || updateRunning" @click="doUpgrade">
          {{ updateRunning ? '升级中…' : '立即升级' }}
        </el-button>
      </div>
    </el-dialog>

    <el-drawer title="站点全局配置" :visible.sync="siteSettingVisible" size="960px" direction="rtl" append-to-body
               :wrapper-closable="true">
      <div class="drawer-wrap">
        <div class="drawer-body">
          <site-setting v-if="siteSettingVisible" ref="siteSettingComp" :show-header="false" @success="siteSettingVisible = false" />
        </div>
        <div class="drawer-footer">
          <el-button size="small" @click="siteSettingVisible = false">取 消</el-button>
          <el-button size="small" type="primary" @click="handleSiteSettingSubmit">保 存</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 失败任务（调度器死信） -->
    <el-drawer title="失败任务" :visible.sync="deadJobVisible" size="900px" direction="rtl" append-to-body
               :wrapper-closable="true" @open="getDeadJobList">
      <div class="drawer-wrap">
        <div class="drawer-body">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; gap: 12px;">
            <span style="color: #909399; font-size: 13px;">
              定时/延迟任务重试耗尽后落到这里。点「重试」会重新投递；「忽略」表示不再处理。
            </span>
            <div style="flex-shrink:0; white-space:nowrap;">
              <el-button size="mini" type="primary" plain icon="el-icon-refresh"
                         :disabled="deadJobLoading || !deadJobList.length" @click="retryAllDeadJobs">一键重试</el-button>
              <el-button size="mini" plain icon="el-icon-close"
                         :disabled="deadJobLoading || !deadJobList.length" @click="ignoreAllDeadJobs">一键忽略</el-button>
            </div>
          </div>
          <el-table :data="deadJobList" v-loading="deadJobLoading" border size="mini">
            <el-table-column prop="id" label="ID" width="60" align="center" />
            <el-table-column prop="job_class" label="任务类" min-width="200" :show-overflow-tooltip="true" />
            <el-table-column prop="attempt" label="尝试" width="60" align="center" />
            <el-table-column prop="last_error" label="失败原因" min-width="200" :show-overflow-tooltip="true" />
            <el-table-column prop="failed_at_text" label="失败时间" width="150" align="center" />
            <el-table-column label="操作" width="140" align="center">
              <template slot-scope="{ row }">
                <el-button type="text" @click="retryDeadJob(row)">重试</el-button>
                <el-button type="text" style="color:#909399" @click="ignoreDeadJob(row)">忽略</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div v-if="!deadJobLoading && deadJobList.length === 0" style="text-align:center; color:#c0c4cc; padding:30px 0;">
            暂无失败任务 🎉
          </div>
        </div>
        <div class="drawer-footer">
          <el-button size="small" type="primary" @click="deadJobVisible = false">确 定</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 调度中心 -->
    <el-drawer title="调度中心" :visible.sync="dashboardVisible" size="980px" direction="rtl" append-to-body
               :wrapper-closable="true" @open="onDashboardOpen">
      <div class="drawer-wrap">
        <div class="drawer-body">
      <el-tabs v-model="dashboardTab">
      <el-tab-pane label="概览" name="overview">
      <div v-loading="dashboardLoading">
        <div class="sched-cards">
          <div class="sched-card">
            <div class="sc-label">调度器</div>
            <div class="sc-value" :style="{ color: dashboard.alive ? '#67c23a' : '#f56c6c' }">
              {{ dashboard.alive ? '运行中' : '已离线' }}
            </div>
          </div>
          <div class="sched-card">
            <div class="sc-label">待执行</div>
            <div class="sc-value">{{ dashboard.queue_pending || 0 }}</div>
            <div class="sc-sub" v-if="dashboard.lane_breakdown">
              高{{ dashboard.lane_breakdown.high }} 普{{ dashboard.lane_breakdown.normal }}
              低{{ dashboard.lane_breakdown.low }} 流{{ dashboard.lane_breakdown.throttle }}
            </div>
          </div>
          <div class="sched-card">
            <div class="sc-label">处理中</div>
            <div class="sc-value">{{ dashboard.processing || 0 }}</div>
          </div>
          <div class="sched-card">
            <div class="sc-label">死信待处理</div>
            <div class="sc-value" :style="{ color: (dashboard.dead_pending || 0) > 0 ? '#f56c6c' : '' }">
              {{ dashboard.dead_pending || 0 }}
            </div>
          </div>
          <div class="sched-card">
            <div class="sc-label">今日执行(成/败)</div>
            <div class="sc-value">
              <span style="color:#67c23a">{{ dashboard.today ? dashboard.today.success : 0 }}</span>
              <span style="color:#c0c4cc"> / </span>
              <span style="color:#f56c6c">{{ dashboard.today ? dashboard.today.failed : 0 }}</span>
            </div>
          </div>
        </div>

        <div class="sched-section-title">近期执行</div>
        <el-table :data="dashboard.recent || []" border size="mini" max-height="300">
          <el-table-column prop="job_class" label="任务类" min-width="200" :show-overflow-tooltip="true" />
          <el-table-column prop="source" label="来源" width="70" align="center" />
          <el-table-column label="结果" width="72" align="center">
            <template slot-scope="{ row }">
              <el-tag size="mini" :type="row.success == 1 ? 'success' : 'danger'">
                {{ row.success == 1 ? '成功' : '失败' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="attempt" label="尝试" width="55" align="center" />
          <el-table-column label="耗时" width="85" align="center">
            <template slot-scope="{ row }">{{ row.duration_ms }}ms</template>
          </el-table-column>
          <el-table-column prop="error" label="失败原因" min-width="150" :show-overflow-tooltip="true" />
          <el-table-column prop="executed_at_text" label="执行时间" width="150" align="center" />
        </el-table>

        <div class="sched-section-title">今日慢任务 TOP5</div>
        <el-table :data="dashboard.slow || []" border size="mini">
          <el-table-column prop="job_class" label="任务类" min-width="240" :show-overflow-tooltip="true" />
          <el-table-column prop="source" label="来源" width="70" align="center" />
          <el-table-column label="耗时" width="100" align="center">
            <template slot-scope="{ row }">{{ row.duration_ms }}ms</template>
          </el-table-column>
          <el-table-column prop="executed_at_text" label="时间" width="100" align="center" />
        </el-table>
      </div>
      </el-tab-pane>

      <el-tab-pane label="Cron 管理" name="cron">
        <div style="margin-bottom:10px; color:#909399; font-size:12px;">
          代码定义的定时任务在此可视化启停 / 改表达式；保存后 Go 调度器 10 秒内热加载。表达式留空 = 用代码默认值。
        </div>
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
          <el-input
            v-model="cronKeyword"
            size="mini"
            clearable
            placeholder="搜索任务名 / job_key / cron 表达式"
            prefix-icon="el-icon-search"
            style="width: 320px;" />
          <el-checkbox v-model="cronOnlyOverridden" size="mini">仅看已改</el-checkbox>
          <span style="color:#c0c4cc; font-size:12px;">
            共 {{ cronList.length }} 个，当前显示 {{ filteredCronList.length }} 个
          </span>
        </div>
        <el-table :data="filteredCronList" v-loading="cronLoading" border size="mini" height="calc(100vh - 330px)">
          <el-table-column label="任务" min-width="240" :show-overflow-tooltip="true">
            <template slot-scope="{ row }">
              <div>{{ row.description || row.method }}</div>
              <div style="font-size:11px;color:#c0c4cc;">{{ row.job_key }}</div>
            </template>
          </el-table-column>
          <el-table-column label="cron 表达式" width="210">
            <template slot-scope="{ row }">
              <el-input v-model="row.expression" size="mini" :placeholder="'默认 ' + row.code_expression" />
            </template>
          </el-table-column>
          <el-table-column label="启用" width="80" align="center">
            <template slot-scope="{ row }">
              <el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" />
            </template>
          </el-table-column>
          <el-table-column label="覆盖" width="70" align="center">
            <template slot-scope="{ row }">
              <el-tag v-if="row.overridden" size="mini" type="warning">已改</el-tag>
              <span v-else style="color:#c0c4cc">—</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template slot-scope="{ row }">
              <el-button type="text" @click="saveCronTask(row)">保存</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
      </el-tabs>
        </div>
        <div class="drawer-footer">
          <el-button size="small" type="primary" @click="dashboardVisible = false">确 定</el-button>
        </div>
      </div>
    </el-drawer>

  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { setMallId } from '@/utils/auth'
import defaultAvatar from '@/assets/logo/logo.png'
import PluginManager from '@/components/PluginManager'
import SystemMenuManager from '@/components/SystemMenuManager'
import SiteSetting from '@/components/SiteSetting'
import SourceLicense from '@/components/SourceLicense'

export default {
  name: "MallEntry",
  components: { PluginManager, SystemMenuManager, SiteSetting, SourceLicense },
  data() {
    return {
      loading: false,
      list: [],
      defaultAvatar: defaultAvatar,
      currentTime: '',
      timer: null,
      schedulerAlive: false,
      schedulerAgo: null,
      schedulerTimer: null,
      deadJobVisible: false,
      deadJobLoading: false,
      deadJobList: [],
      deadJobCount: 0,
      dashboardVisible: false,
      dashboardLoading: false,
      dashboard: {},
      dashboardTab: 'overview',
      cronList: [],
      cronLoading: false,
      cronKeyword: '',
      cronOnlyOverridden: false,
      pluginFilterKeyword: '',
      pluginExpandAll: false,
      checkedPluginCount: 0,
      year: new Date().getFullYear(),
      dialogVisible: false,
      guideVisible: false,
      logoPickerVisible: false,
      // 移交商城
      transferVisible: false,
      transferLoading: false,
      transferMallName: '',
      founderList: [],
      founderLoading: false,
      transferForm: { mall_id: null, owner_mode: 'exist', owner_admin_id: null, owner_username: '', owner_password: '', owner_mobile: '', owner_nickname: '' },
      showPluginManager: false,
      showMenuManager: false,
      siteSettingVisible: false,
      systemInfoVisible: false,
      systemInfoLoading: false,
      systemGroups: [],
      // 系统更新
      updateVisible: false,
      updateLoading: false,
      updateAvailable: false, // 头部小红点
      upgrading: false,
      checking: false, // 立即检查更新中
      updateTimer: null,
      updateInfo: { current_version: '', current_version_code: 0, has_update: false, latest: null, status: null },
      licenseNotice: null,      // 授权到期提醒(云端下发:level/title/message/contact)
      licenseDismissed: false,  // 临期黄条本次会话折叠(过期红条不可折叠)
      upgradeSteps: ['排队', '下载', '解压', '迁移'],  // 科幻升级面板阶段步进灯
      submitLoading: false,
      title: '',
      plugin_map: [],
      form: { id: undefined, name: '', logo: '', is_has_expire: 0, end_at: '', is_stop: 0, plugins: [], owner_mode: 'self', owner_username: '', owner_password: '', owner_mobile: '', owner_nickname: '' },
      rules: {
        name: [{ required: true, message: '商城名称不能为空', trigger: 'blur' }],
        end_at: [{ required: true, message: '请选择到期时间', trigger: 'change' }]
      },
      pickerOptions: {
        shortcuts: [{
          text: '1年后',
          onClick(picker) {
            const date = new Date();
            date.setFullYear(date.getFullYear() + 1);
            picker.$emit('pick', date);
          }
        }, {
          text: '3年后',
          onClick(picker) {
            const date = new Date();
            date.setFullYear(date.getFullYear() + 3);
            picker.$emit('pick', date);
          }
        }]
      }
    };
  },
  computed: {
    ...mapGetters(['name', 'avatar']),
    ...mapState({
      nickname: state => state.user.nickname,
      adminType: state => state.user.admin_type,
      // 顶部展示用"站点名称"(name)；浏览器标签页才用 title
      siteTitle: state => state.site.setting.name || state.site.setting.title,
      siteLogo: state => state.site.setting.logo || defaultAvatar,
      siteCopyright: state => state.site.setting.copyright,
      siteRecord: state => state.site.setting.record
    }),
    isAdmin() { return this.adminType === 0; },
    // 授权到期提醒条是否显示:过期红条常驻,临期黄条本次会话可折叠
    showLicenseNotice() {
      const n = this.licenseNotice;
      if (!this.isAdmin || !n) return false;
      if (n.level === 'expired') return true;
      if (n.level === 'warning') return !this.licenseDismissed;
      return false;
    },
    // 升级是否进行中(状态非终态)
    updateRunning() {
      const s = this.updateInfo.status && this.updateInfo.status.state;
      return ['queued', 'downloading', 'extracting', 'migrating'].indexOf(s) !== -1;
    },
    upgradePercent() {
      const map = { queued: 5, downloading: 35, extracting: 60, migrating: 85, done: 100, failed: 100 };
      const s = this.updateInfo.status && this.updateInfo.status.state;
      return map[s] || 0;
    },
    upgradeBarStatus() {
      const s = this.updateInfo.status && this.updateInfo.status.state;
      if (s === 'done') return 'success';
      if (s === 'failed') return 'exception';
      return null;
    },
    upgradeStateText() {
      const s = this.updateInfo.status && this.updateInfo.status.state;
      return { queued: '已排队', downloading: '下载中', extracting: '解压覆盖中', migrating: '数据库迁移中', done: '升级完成', failed: '升级失败' }[s] || '';
    },
    // 科幻升级面板:当前阶段索引(排队0/下载1/解压2/迁移3;done=4 全亮;failed=-1)
    upgradeStep() {
      const s = this.updateInfo.status && this.updateInfo.status.state;
      const m = { queued: 0, downloading: 1, extracting: 2, migrating: 3, done: 4, failed: -1 };
      return s in m ? m[s] : 0;
    },
    upgradeDone() { return (this.updateInfo.status && this.updateInfo.status.state) === 'done'; },
    upgradeFailed() { return (this.updateInfo.status && this.updateInfo.status.state) === 'failed'; },
    upgradeTerminal() { return this.upgradeDone || this.upgradeFailed; },
    filteredCronList() {
      const kw = (this.cronKeyword || '').trim().toLowerCase();
      return this.cronList.filter(row => {
        if (this.cronOnlyOverridden && !row.overridden) return false;
        if (!kw) return true;
        return (
          (row.description || '').toLowerCase().includes(kw) ||
          (row.job_key || '').toLowerCase().includes(kw) ||
          (row.method || '').toLowerCase().includes(kw) ||
          (row.expression || '').toLowerCase().includes(kw) ||
          (row.code_expression || '').toLowerCase().includes(kw)
        );
      });
    },
    schedulerTip() {
      if (this.schedulerAlive) {
        return 'Go 调度器运行中' + (this.schedulerAgo != null ? `（${this.schedulerAgo}s 前心跳）` : '');
      }
      return 'Go 调度器已离线 —— 定时任务可能未执行，点击重新检测';
    }
  },
  created() {
    this.getList();
    this.startClock();
    if(this.isAdmin) {
      this.getPluginMap();
      this.checkScheduler();
      this.getDeadJobList();
      this.checkUpdateBadge();
      this.schedulerTimer = setInterval(this.checkScheduler, 30000);
    }
    this.$store.dispatch('getSiteSetting');
  },
  beforeDestroy() {
    if (this.timer) clearInterval(this.timer);
    if (this.schedulerTimer) clearInterval(this.schedulerTimer);
    if (this.updateTimer) clearInterval(this.updateTimer);
  },
  watch: {
    // 插件搜索关键词 → 触发 el-tree 内置 filter
    pluginFilterKeyword(val) {
      if (this.$refs.tree) this.$refs.tree.filter(val);
    },
    // 打开商城编辑/新建抽屉时，重置插件树工具栏状态
    dialogVisible(val) {
      if (val) {
        this.pluginFilterKeyword = '';
        this.pluginExpandAll = false;
      }
    }
  },
  methods: {
    /* ─── 插件树辅助方法 ─── */
    // 仅叶子节点（实际授权单元）参与全选/全不选，避免父节点 indeterminate 计算混乱
    getLeafKeys(nodes = this.plugin_map) {
      const keys = [];
      const walk = (arr) => {
        (arr || []).forEach(n => {
          if (n.children && n.children.length) {
            walk(n.children);
          } else {
            keys.push(n.name);
          }
        });
      };
      walk(nodes);
      return keys;
    },
    checkAllPlugins() {
      if (!this.$refs.tree) return;
      this.$refs.tree.setCheckedKeys(this.getLeafKeys());
    },
    uncheckAllPlugins() {
      if (!this.$refs.tree) return;
      this.$refs.tree.setCheckedKeys([]);
    },
    invertCheckPlugins() {
      if (!this.$refs.tree) return;
      const all = this.getLeafKeys();
      const checked = new Set(this.$refs.tree.getCheckedKeys(true));
      const next = all.filter(k => !checked.has(k));
      this.$refs.tree.setCheckedKeys(next);
    },
    toggleExpandPlugins() {
      this.pluginExpandAll = !this.pluginExpandAll;
      // el-tree 的 default-expand-all 是初始 prop，运行期改不生效；需要遍历节点 store 手动切换
      const store = this.$refs.tree && this.$refs.tree.store;
      if (!store) return;
      const traverse = (nodes) => {
        (nodes || []).forEach(node => {
          node.expanded = this.pluginExpandAll;
          if (node.childNodes && node.childNodes.length) traverse(node.childNodes);
        });
      };
      traverse(store.root.childNodes);
    },
    filterPluginNode(value, data) {
      if (!value) return true;
      const kw = String(value).toLowerCase();
      return (
        (data.label || '').toLowerCase().includes(kw) ||
        (data.name || '').toLowerCase().includes(kw)
      );
    },
    onPluginCheckChange() {
      if (this.$refs.tree) {
        // 只数叶子节点，避免把分组节点也计入
        this.checkedPluginCount = this.$refs.tree.getCheckedKeys(true).length;
      }
    },

    startClock() {
      this.updateTime();
      this.timer = setInterval(this.updateTime, 1000);
    },
    updateTime() {
      const date = new Date();
      // 使用更现代的数字格式
      const hours = date.getHours().toString().padStart(2, '0');
      const minutes = date.getMinutes().toString().padStart(2, '0');
      const seconds = date.getSeconds().toString().padStart(2, '0');
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      this.currentTime = `${date.getFullYear()}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
    formatExpire(text) {
      if(!text) return '未知';
      return text.replace('到期', '');
    },
    copyMallId(id) {
      const text = String(id);
      const done = () => this.$message.success(`已复制 mall_id：${text}`);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(() => this.fallbackCopy(text, done));
      } else {
        this.fallbackCopy(text, done);
      }
    },
    fallbackCopy(text, done) {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) { this.$message.warning(`mall_id：${text}`); }
      document.body.removeChild(ta);
    },
    logout() {
      this.$confirm('确定注销并退出系统吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$store.dispatch('LogOut').then(() => { location.href = '/admin'; });
      }).catch(() => {});
    },
    getList() {
      this.loading = true;
      this.$request({ url: '/admin/mall/entry-list', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.list = res.data.list || [];

          // 【单行道静默穿透】：如果是普通商户（非超管），且名下只有1个商城，直接强行着陆！
          if (!this.isAdmin && this.list.length === 1) {
            this.handleEnter(this.list[0]);
          }
        } else {
          this.$modal.msgError(res.msg);
        }
        this.loading = false;
      }).catch(() => { this.loading = false; });
    },
    handleEnter(row) {
      if (row.is_stop == 1) return this.$modal.msgError('该商城已停用，无法进入');
      if (row.is_expire == 1) this.$modal.msgWarning('该商城已过期，请及时续费');

      const loading = this.$loading({
        lock: true,
        text: `正在初始化 [${row.name}] 资源...`,
        spinner: 'el-icon-loading',
        background: 'rgba(255, 255, 255, 0.8)' // 使用白色背景loading
      });

      this.$request({ url: '/admin/mall/enter', method: 'post', data: { mall_id: row.id } }).then(res => {
        loading.close();
        if (res.code === 0) {
          setMallId(row.id);
          // 进入商城即同步当前商城 logo（同时写 state 与 localStorage），避免切换商城后侧栏仍显示上一个商城的图标
          this.$store.commit('SET_MALL_LOGO', row.logo || '');
          // 商城名同步存下来，供侧边栏底部账号区常驻显示（和 logo 同一时机，切换商城时一起被覆盖）
          this.$store.commit('SET_MALL_NAME', row.name || '');
          this.$message.success({ message: `欢迎进入 ${row.name}`, offset: 60 });
          // 进入商城即拉取该商城词库,让业务页首屏就有名词(否则显示 xwy.* 原始 key,要去词库页保存才被动加载)
          this.$store.dispatch('noun/loadNouns').catch(()=>{});
          this.$store.dispatch('GenerateRoutes').then((accessRoutes) => {
            // ── 防护：员工账号若后台未分配任何菜单 → accessRoutes 只剩兜底的 {path:'*'} ──
            // 此时若继续跳 defaultHome 会命中通配 → 跳 /404 → /404 的"返回首页"又跳 / → 再次命中通配 → 死循环
            const hasBusinessRoute = (accessRoutes || []).some(r => r.path && r.path !== '*');
            if (!hasBusinessRoute) {
              // 清掉刚才设的 mall_id，避免下次刷新仍卡在这个商城
              setMallId(0);
              this.$confirm(
                '当前账号未被分配任何菜单权限，无法进入商城。请联系管理员为该账号绑定角色与菜单。',
                '无可用权限',
                {
                  confirmButtonText: '退出登录',
                  cancelButtonText: '稍后再说',
                  type: 'warning',
                  closeOnClickModal: false,
                  closeOnPressEscape: false,
                }
              ).then(() => {
                this.$store.dispatch('LogOut').then(() => { location.href = '/admin'; });
              }).catch(() => {
                // 用户选"稍后再说" → 停留在 entry.vue 商城卡片页，至少不会卡死
              });
              return;
            }

            this.$router.addRoutes(accessRoutes);

            // 智能主页推演引擎：已经抽象到了 Vuex permission store
            let defaultPath = this.$store.state.permission.defaultHome;

            setTimeout(() => {
              if (defaultPath.startsWith('http')) {
                window.location.href = defaultPath;
              } else {
                window.location.href = '/admin/#' + defaultPath;
              }
            }, 300);
          });
        } else {
          this.$modal.msgError(res.msg);
        }
      }).catch(() => { loading.close(); });
    },
    getPluginMap() {
      this.$request({ url: '/admin/admin/plugin-map', method: 'get' }).then(res => {
        if (res.code === 0) this.plugin_map = res.data.list || [];
      });
    },
    handlePluginChanged() { this.getPluginMap(); },
    openSystemInfo() {
      this.systemInfoVisible = true;
      this.loadSystemInfo();
    },
    loadSystemInfo() {
      this.systemInfoLoading = true;
      this.$request({ url: '/admin/admin/system-info', method: 'get' }).then(res => {
        this.systemInfoLoading = false;
        if (res.code === 0) {
          this.systemGroups = res.data.groups || [];
        } else {
          this.$modal.msgError(res.msg || '获取系统信息失败');
        }
      }).catch(() => { this.systemInfoLoading = false; });
    },
    // ---- 系统更新 ----
    forceLabel(l) { return { optional: '可选', notify: '建议', force: '强制' }[l] || ''; },
    forceTagType(l) { return { optional: 'info', notify: 'warning', force: 'danger' }[l] || 'info'; },
    openUpdate() {
      this.updateVisible = true;
      this.loadUpdate();
    },
    loadUpdate() {
      this.updateLoading = !this.updateInfo.current_version; // 首次才转圈
      this.$request({ url: '/admin/site/check-update', method: 'get' }).then(res => {
        this.updateLoading = false;
        if (res.code === 0) {
          this.updateInfo = res.data || this.updateInfo;
          this.updateAvailable = !!this.updateInfo.has_update;
          this.pickLicenseNotice(this.updateInfo);
          // 升级进行中 → 持续轮询;终态 → 停止
          if (this.updateRunning) {
            this.startUpdatePoll();
          } else {
            this.stopUpdatePoll();
          }
        }
      }).catch(() => { this.updateLoading = false; });
    },
    // 升级完成后确定 → 整页刷新,让新版本前端(带 hash 的 JS/CSS)全新加载
    reloadForUpdate() {
      this.stopUpdatePoll();
      window.location.reload();
    },
    doUpgrade() {
      const latest = this.updateInfo.latest || {};
      this.$confirm(`确认升级到 ${latest.latest_version}(code ${latest.latest_version_code})？升级由调度器后台执行，期间请勿关闭服务。`, '升级确认', {
        confirmButtonText: '确认升级', cancelButtonText: '取消', type: 'warning'
      }).then(() => {
        this.upgrading = true;
        this.$request({ url: '/admin/site/update', method: 'get', params: { new_version: latest.latest_version_code } }).then(res => {
          this.upgrading = false;
          if (res.code === 0) {
            this.$modal.msgSuccess(res.msg || '升级任务已下发');
            this.loadUpdate();
            this.startUpdatePoll();
          } else {
            this.$modal.msgError(res.msg || '下发失败');
          }
        }).catch(() => { this.upgrading = false; });
      }).catch(() => {});
    },
    startUpdatePoll() {
      if (this.updateTimer) return;
      this.updateTimer = setInterval(this.loadUpdate, 3000);
    },
    stopUpdatePoll() {
      if (this.updateTimer) { clearInterval(this.updateTimer); this.updateTimer = null; }
    },
    // 立即检查:下发 refresh 命令让 scheduler 马上心跳,再轮询几次拿刷新结果
    // 同步:一次请求,PHP 直连 scheduler 当场刷新,无轮询、确定性
    refreshCheck() {
      this.checking = true;
      this.$request({ url: '/admin/site/check-update', method: 'get', params: { refresh: 1 } }).then(res => {
        this.checking = false;
        if (res.code === 0 && res.data) {
          this.updateInfo = res.data;
          this.updateAvailable = !!res.data.has_update;
          this.pickLicenseNotice(res.data);
          if (res.data.scheduler_ok === false) {
            this.$modal.msgError('调度器未运行,请确认其已启动');
          } else {
            this.$modal.msgSuccess('已刷新到最新');
          }
        }
      }).catch(() => { this.checking = false; });
    },
    // 轻量:仅刷新头部小红点 + 授权到期提醒(不开弹窗,页面加载即调)
    checkUpdateBadge() {
      this.$request({ url: '/admin/site/check-update', method: 'get' }).then(res => {
        if (res.code === 0 && res.data) {
          this.updateAvailable = !!res.data.has_update;
          this.pickLicenseNotice(res.data);
        }
      }).catch(() => {});
    },
    // 从 check-update 响应里取出云端下发的到期提醒(挂在 latest.license_notice)
    pickLicenseNotice(data) {
      this.licenseNotice = (data && data.latest && data.latest.license_notice) ? data.latest.license_notice : null;
    },
    handleAdd() {
      this.resetForm();
      this.title = '创建新商城';
      this.dialogVisible = true;
      this.$nextTick(() => {
        if (this.$refs.tree) this.$refs.tree.setCheckedKeys([]);
        this.checkedPluginCount = 0;
      });
    },
    handleEdit(row) {
      this.resetForm();
      this.title = '配置商城';
      this.dialogVisible = true;
      this.$request({ url: '/admin/mall/info', method: 'get', data: { mall_id: row.id } }).then(res => {
        if (res.code === 0) {
          const mall = res.data.mall;
          const plugins = mall.plugins || [];
          this.form = {
            id: mall.id, name: mall.name, logo: mall.logo,
            is_has_expire: parseInt(mall.is_has_expire),
            is_stop: parseInt(mall.is_stop),
            end_at: mall.end_at ? mall.end_at * 1000 : '',
            plugins: plugins
          };
          this.$nextTick(() => {
            if (this.$refs.tree) this.$refs.tree.setCheckedKeys(plugins);
            this.onPluginCheckChange();
          });
        }
      });
    },
    // ── 移交商城 ──
    openTransfer(row) {
      this.transferMallName = row.name;
      this.transferForm = { mall_id: row.id, owner_mode: 'exist', owner_admin_id: null, owner_username: '', owner_password: '', owner_mobile: '', owner_nickname: '' };
      this.transferVisible = true;
    },
    onTransferOpen() { this.getFounders(); },
    getFounders() {
      this.founderLoading = true;
      this.$request({ url: '/admin/mall/founders', method: 'get' }).then(res => {
        if (res.code === 0) this.founderList = res.data.list || [];
      }).finally(() => { this.founderLoading = false; });
    },
    submitTransfer() {
      const f = this.transferForm;
      if (f.owner_mode === 'exist' && !f.owner_admin_id) return this.$modal.msgError('请选择商户主账号');
      if (f.owner_mode === 'new') {
        if (!f.owner_username || !f.owner_password || !f.owner_mobile) return this.$modal.msgError('请完善主账号 用户名 / 密码 / 手机号');
        if (f.owner_password.length < 5) return this.$modal.msgError('主账号密码至少 5 位');
      }
      this.transferLoading = true;
      this.$request({ url: '/admin/mall/transfer', method: 'post', data: f }).then(res => {
        this.transferLoading = false;
        if (res.code === 0) {
          this.$modal.msgSuccess('移交成功');
          this.transferVisible = false;
          this.getList();
        } else {
          this.$modal.msgError(res.msg);
        }
      }).catch(() => { this.transferLoading = false; });
    },
    handleDelete(row) {
      this.$confirm(`此操作将永久删除商城 [${row.name}] 及其所有数据，是否继续？`, '危险操作警告', {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        return this.$request({ url: '/admin/mall/delete', method: 'post', data: { id: row.id } });
      }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess('删除成功');
          this.getList();
        } else {
          this.$modal.msgError(res.msg);
        }
      }).catch(() => {});
    },
    submit() {
      this.$refs.form.validate(valid => {
        if (valid) {
          // 新建 + 交付商户主账号：前端先做必填校验（后端会二次校验兜底）
          if (!this.form.id && this.form.owner_mode === 'new') {
            if (!this.form.owner_username || !this.form.owner_password || !this.form.owner_mobile) {
              return this.$modal.msgError('请完善商城主账号的用户名 / 密码 / 手机号');
            }
            if (this.form.owner_password.length < 5) {
              return this.$modal.msgError('主账号密码至少 5 位');
            }
          }
          this.submitLoading = true;
          const checkedPlugins = this.$refs.tree.getCheckedKeys();
          const params = { ...this.form, plugins: checkedPlugins };
          // el-date-picker value-format="timestamp" 给的是毫秒,且选择器只精确到秒、
          // 内部 Date 的毫秒位不一定为 0 —— 直接 /1000 会得到小数,后端 integer 校验会拒。
          // 必须取整(后端 normalizeEndAt 也兜一层,这里先算对以减少无效往返)。
          if (params.end_at) params.end_at = Math.floor(params.end_at / 1000);
          this.$request({ url: '/admin/mall/edit', method: 'post', data: params }).then(res => {
            this.submitLoading = false;
            if (res.code === 0) {
              this.$modal.msgSuccess(this.form.id ? '配置已更新' : '商城创建成功');
              this.dialogVisible = false;
              this.getList();
            } else {
              this.$modal.msgError(res.msg);
            }
          }).catch(() => { this.submitLoading = false; });
        }
      });
    },
    resetForm() {
      this.form = { id: undefined, name: '', logo: '', is_has_expire: 0, end_at: '', is_stop: 0, plugins: [], owner_mode: 'self', owner_username: '', owner_password: '', owner_mobile: '', owner_nickname: '' };
      if (this.$refs.form) this.$refs.form.resetFields();
    },
    // 图片选择器选定回调:回填 Logo URL（单选取第一个）
    onLogoSelected(files) {
      if (files && files.length) this.form.logo = files[0].url;
      this.logoPickerVisible = false;
    },
    checkScheduler() {
      this.$request({ url: '/admin/site/scheduler-status', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.schedulerAlive = res.data.alive === 1;
          this.schedulerAgo = res.data.seconds_ago;
        } else {
          this.schedulerAlive = false;
        }
      }).catch(() => { this.schedulerAlive = false; });
    },
    openDashboard() { this.dashboardVisible = true; },
    onDashboardOpen() {
      this.dashboardTab = 'overview';
      this.getDashboard();
      this.getCronList();
    },
    getDashboard() {
      this.dashboardLoading = true;
      this.$request({ url: '/admin/admin/scheduler-dashboard', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.dashboard = res.data || {};
          this.schedulerAlive = this.dashboard.alive === 1;
        } else {
          this.$modal.msgError(res.msg);
        }
      }).finally(() => { this.dashboardLoading = false; });
    },
    getCronList() {
      this.cronLoading = true;
      this.$request({ url: '/admin/admin/cron-task-list', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.cronList = res.data.list || [];
        } else {
          this.$modal.msgError(res.msg);
        }
      }).finally(() => { this.cronLoading = false; });
    },
    saveCronTask(row) {
      this.$request({
        url: '/admin/admin/cron-task-save',
        method: 'post',
        data: { job_key: row.job_key, enabled: row.enabled, expression: row.expression || '' }
      }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess(res.msg);
          this.getCronList();
        } else {
          this.$modal.msgError(res.msg);
        }
      });
    },
    openDeadJob() { this.deadJobVisible = true; },
    getDeadJobList() {
      this.deadJobLoading = true;
      this.$request({ url: '/admin/admin/dead-job-list', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.deadJobList = res.data.list || [];
          this.deadJobCount = res.data.pending_count || 0;
        }
      }).finally(() => { this.deadJobLoading = false; });
    },
    retryDeadJob(row) {
      this.$request({ url: '/admin/admin/dead-job-retry', method: 'post', data: { id: row.id } }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess('已重新投递');
          this.getDeadJobList();
        } else {
          this.$modal.msgError(res.msg);
        }
      });
    },
    retryAllDeadJobs() {
      this.$confirm(`确定重投全部 ${this.deadJobList.length} 个失败任务吗？`, '一键重试', {
        confirmButtonText: '确定重投', cancelButtonText: '取消', type: 'warning'
      }).then(() => {
        return this.$request({ url: '/admin/admin/dead-job-retry-all', method: 'post' });
      }).then(res => {
        if (res.code === 0) { this.$modal.msgSuccess(res.msg || '已重投'); this.getDeadJobList(); }
        else this.$modal.msgError(res.msg);
      }).catch(() => {});
    },
    ignoreAllDeadJobs() {
      this.$confirm(`确定忽略全部 ${this.deadJobList.length} 个失败任务吗？`, '一键忽略', {
        confirmButtonText: '确定忽略', cancelButtonText: '取消', type: 'warning'
      }).then(() => {
        return this.$request({ url: '/admin/admin/dead-job-ignore-all', method: 'post' });
      }).then(res => {
        if (res.code === 0) { this.$modal.msgSuccess(res.msg || '已忽略'); this.getDeadJobList(); }
        else this.$modal.msgError(res.msg);
      }).catch(() => {});
    },
    ignoreDeadJob(row) {
      this.$confirm(`确定忽略失败任务 #${row.id}（${row.job_class}）吗？`, '提示', { type: 'warning' }).then(() => {
        return this.$request({ url: '/admin/admin/dead-job-ignore', method: 'post', data: { id: row.id } });
      }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess('已忽略');
          this.getDeadJobList();
        } else {
          this.$modal.msgError(res.msg);
        }
      }).catch(() => {});
    },
    openSiteSetting() { this.siteSettingVisible = true; },
    handleSiteSettingSubmit() { if (this.$refs.siteSettingComp) this.$refs.siteSettingComp.submit(); }
  }
};
</script>

<style lang="scss" scoped>
/* ================= CSS 变量定义 ================= */
$primary-color: var(--admin-accent-text);
$text-main: #303133;
$text-sub: #606266;
$bg-color: #f7f9fc;
$glass-bg: rgba(255, 255, 255, 0.75);
$glass-border: rgba(255, 255, 255, 0.6);

/* ================= 动画关键帧 ================= */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes float {
  0% { transform: translate(0, 0); }
  50% { transform: translate(-20px, 30px); }
  100% { transform: translate(0, 0); }
}
@keyframes sheen {
  0% { transform: translateX(-150%) skewX(-15deg); }
  100% { transform: translateX(200%) skewX(-15deg); }
}
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(var(--admin-accent-rgb), 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(var(--admin-accent-rgb), 0); }
  100% { box-shadow: 0 0 0 0 rgba(var(--admin-accent-rgb), 0); }
}

.fade-in-up { opacity: 0; animation: fadeInUp 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
.fade-in-down { animation: fadeInDown 0.7s ease forwards; }

/* ================= 布局容器 ================= */
.entry-wrapper {
  min-height: 100vh;
  background-color: var(--admin-canvas);
  display: flex;
  flex-direction: column;
  position: relative;
  overflow-x: hidden;
  font-family: 'Helvetica Neue', Helvetica, 'PingFang SC', 'Microsoft YaHei', Arial, sans-serif;
}

/* ================= 动态流体背景 ================= */
.background-mesh {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; overflow: hidden;
  .mesh-blob {
    position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.6;
    animation: float 10s infinite ease-in-out;
  }
  .blob-1 {
    width: 600px; height: 600px; background: var(--admin-decoration); top: -200px; left: -100px;
    animation-delay: 0s;
  }
  .blob-2 {
    width: 500px; height: 500px; background: var(--admin-decoration); bottom: -100px; right: -100px;
    animation-delay: -5s;
  }
  .blob-3 {
    width: 400px; height: 400px; background: var(--admin-decoration); top: 40%; left: 30%;
    animation-delay: -2s; opacity: 0.4;
  }
}

/* ================= 顶部玻璃导航 ================= */
.entry-header {
  height: 72px;
  background: $glass-bg;
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid $glass-border;
  display: flex; justify-content: space-between; align-items: center;
  padding: 0 40px; position: sticky; top: 0; z-index: 100;
  box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);

  .header-left {
    display: flex; align-items: center;
    .logo-wrapper {
      width: 38px; height: 38px; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-right: 12px;
      .sys-logo { width: 28px; height: 28px; object-fit: contain; border-radius: 6px; }
    }
    .sys-title { font-size: 20px; font-weight: 700; color: #1f2d3d; letter-spacing: 0.5px; }
  }

  .header-right {
    display: flex; align-items: center; color: $text-sub;
    .divider-line { height: 20px; width: 1px; background: #e6e6e6; margin: 0 20px; }

    .license-action { border: 0; background: transparent; padding: 0; font: inherit; }
    .admin-actions { display: flex; align-items: center; }
    .action-item {
      cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
      border-radius: 8px; transition: all 0.2s; margin-left: 5px; color: #606266;
      &:hover { background: rgba(var(--admin-accent-rgb), 0.1); color: $primary-color; }
      i { font-size: 18px; }
    }

    /* 调度器状态灯 */
    .scheduler-light {
      cursor: pointer; display: flex; align-items: center;
      height: 36px; padding: 0 10px; margin-left: 5px;
      border-radius: 8px; transition: all 0.2s;
      &:hover { background: rgba(var(--admin-accent-rgb), 0.08); }
      .light-dot {
        width: 8px; height: 8px; border-radius: 50%; margin-right: 6px;
        &.is-on {
          background: #67c23a;
          box-shadow: 0 0 0 0 rgba(103, 194, 58, 0.5);
          animation: light-pulse 1.8s infinite;
        }
        &.is-off { background: #f56c6c; }
      }
      .light-text { font-size: 13px; color: #606266; }
    }
    @keyframes light-pulse {
      0% { box-shadow: 0 0 0 0 rgba(103, 194, 58, 0.5); }
      70% { box-shadow: 0 0 0 6px rgba(103, 194, 58, 0); }
      100% { box-shadow: 0 0 0 0 rgba(103, 194, 58, 0); }
    }

    .time-box {
      display: flex; align-items: center; font-family: 'Roboto Mono', monospace;
      color: #606266; font-size: 15px; font-weight: 500;
      .time-icon { margin-right: 8px; color: #909399; }
    }

    .user-info {
      display: flex; align-items: center;
      .avatar-ring {
        /* Force circular size: (34 + 2*2 + 1*2) = 40px */
        width: 40px; height: 40px;
        padding: 2px; border: 1px solid #dcdfe6; border-radius: 50%; margin-right: 10px;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
      }
      .user-meta {
        display: flex; flex-direction: column; line-height: 1.2;
        .user-hello { font-size: 12px; color: #909399; }
        .user-name { font-size: 14px; font-weight: 600; color: #303133; }
      }
    }

    .logout-btn {
      margin-left: 20px; cursor: pointer; display: flex; align-items: center;
      padding: 6px 16px; border-radius: 20px; background: #f4f4f5; color: #909399;
      font-size: 13px; transition: all 0.3s;
      .svg-icon { margin-right: 4px; }
      &:hover { background: #fef0f0; color: #F56C6C; }
    }
  }
}

/* ================= 主内容 ================= */
/* 授权到期提醒条 */
.license-notice-bar {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 10px;
  margin: 12px 20px 0; padding: 10px 16px;
  border-radius: 10px; font-size: 14px; line-height: 1.5;
  box-shadow: 0 4px 14px rgba(0,0,0,.06);
}
.license-notice-bar.warning { background: #fff8e6; border: 1px solid #ffe08a; color: #8a6100; }
.license-notice-bar.expired { background: #fdecec; border: 1px solid #f7b8b8; color: #b42323; }
.license-notice-bar .ln-icon { font-size: 18px; flex-shrink: 0; }
.license-notice-bar .ln-text { flex: 1; }
.license-notice-bar .ln-text strong { margin-right: 8px; }
.license-notice-bar .ln-contact { display: inline-block; margin-left: 10px; font-style: normal; font-weight: 600; opacity: .95; }
.license-notice-bar .ln-close { cursor: pointer; flex-shrink: 0; opacity: .55; transition: opacity .2s; }
.license-notice-bar .ln-close:hover { opacity: 1; }

.entry-main { flex: 1; padding: 40px 20px; display: flex; justify-content: center; z-index: 1; position: relative; }
.content-container { width: 100%; max-width: 1440px; }

.welcome-section {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;
  .welcome-text h2 { margin: 0 0 6px; font-size: 28px; color: #1a1a1a; font-weight: 700; }
  .welcome-text p { margin: 0; color: #909399; font-size: 15px; }

  .primary-btn-effect {
    // 配色由后台主题统一提供，保留入口原有尺寸和圆角。
    padding: 12px 24px; border-radius: 50px;
    font-size: 14px; font-weight: 600; box-shadow: 0 6px 16px rgba(25,27,32,0.12);
    transition: background-color .2s, border-color .2s, box-shadow .2s;
    &:hover { box-shadow: 0 8px 20px rgba(25,27,32,0.18); }
    &:focus-visible { outline: 2px solid var(--admin-primary); outline-offset: 3px; }
    i { margin-right: 5px; }
  }
}

/* ================= 使用指引 ================= */
.welcome-guide {
  display: inline-flex; align-items: center; gap: 4px;
  margin-left: 10px; color: var(--admin-accent-text); font-size: 13px; cursor: pointer;
  padding: 2px 10px; border-radius: 20px; background: rgba(var(--admin-accent-rgb), 0.08);
  transition: all 0.2s;
  &:hover { background: rgba(var(--admin-accent-rgb), 0.16); }
}

.guide-body {
  .guide-intro {
    background: var(--admin-accent-soft); border: 1px solid var(--admin-accent-border); border-radius: 8px;
    padding: 12px 14px; font-size: 13px; color: #475669; line-height: 1.7; margin-bottom: 22px;
    b { color: var(--admin-accent-text); }
  }
  .guide-section { margin-bottom: 26px; }
  .guide-h {
    font-size: 15px; font-weight: 700; color: #1f2d3d; margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
    i { color: var(--admin-accent-text); font-size: 17px; }
  }

  .role-card {
    border-radius: 10px; padding: 12px 14px; margin-bottom: 10px;
    border: 1px solid #ebeef5; border-left-width: 4px;
    .role-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .role-name { font-size: 14px; font-weight: 700; color: #303133; }
    .role-tag { font-size: 11px; color: #909399; background: #f4f4f5; padding: 1px 8px; border-radius: 10px; }
    .role-desc { font-size: 12.5px; color: #606266; line-height: 1.65; b { color: #303133; } }
  }
  .role-card.r1 { border-left-color: var(--admin-accent); background: var(--admin-accent-soft); }
  .role-card.r2 { border-left-color: #67C23A; background: #f6fcf3; }
  .role-card.r3 { border-left-color: #E6A23C; background: #fefaf3; }

  .step {
    display: flex; gap: 12px; align-items: flex-start; margin-bottom: 12px;
    font-size: 13px; color: #475669; line-height: 1.7;
    b { color: #303133; }
    .step-no {
      flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
      background: var(--admin-primary); color: var(--admin-on-primary);
      font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;
      margin-top: 1px;
    }
  }

  .safe-item {
    display: flex; gap: 9px; align-items: flex-start; margin-bottom: 11px;
    font-size: 13px; color: #475669; line-height: 1.65;
    b { color: #303133; }
    i { color: #67C23A; font-size: 16px; margin-top: 2px; flex-shrink: 0; }
  }
}

/* ================= 极简科技卡片 ================= */
.mall-card {
  background: #fff; border-radius: 16px; position: relative;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(255,255,255,1);
  overflow: hidden; cursor: pointer; height: 100%; margin-bottom: 30px;
  display: flex; flex-direction: column;

  /* 扫光特效 */
  .shine-effect {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%);
    transform: skewX(-15deg) translateX(-150%); z-index: 2; pointer-events: none;
  }

  &:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px -10px rgba(var(--admin-accent-rgb), 0.15);
    border-color: rgba(var(--admin-accent-rgb), 0.2);

    .shine-effect { animation: sheen 0.6s ease-in-out; }
    .card-footer { background-color: var(--admin-accent-soft); border-top-color: var(--admin-accent-border); }
    .enter-text { color: $primary-color; font-weight: 600; }
    .arrow-icon { transform: translateX(5px); color: $primary-color; }
    .mall-icon { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
    .logo-bg-blur { opacity: 0.6; transform: scale(1.1); }
  }

  /* 状态徽标 */
  .status-badge {
    position: absolute; top: 16px; right: 16px; z-index: 3;
    display: flex; align-items: center; padding: 4px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 500; background: #f2f3f5; color: #909399;
    .status-dot { width: 6px; height: 6px; border-radius: 50%; margin-right: 5px; background: #c0c4cc; }

    &.is-active { background: rgba(103, 194, 58, 0.1); color: #67c23a; .status-dot { background: #67c23a; } }
    &.is-stop { background: #f4f4f5; color: #909399; }
    &.is-expire { background: rgba(245, 108, 108, 0.1); color: #f56c6c; .status-dot { background: #f56c6c; } }
  }

  /* 卡片主体 */
  .card-body {
    padding: 35px 25px 25px; flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center;
    position: relative; z-index: 1;
  }

  .logo-section {
    position: relative; margin-bottom: 20px; width: 100%; display: flex; justify-content: center;
    .logo-bg-blur {
      position: absolute; width: 120px; height: 120px; top: 50%; left: 50%;
      transform: translate(-50%, -50%); background-size: cover; filter: blur(35px); opacity: 0.3;
      border-radius: 50%; transition: all 0.5s ease;
    }
    .mall-icon {
      position: relative; background: #fff; padding: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      transition: all 0.4s ease;
    }
  }

  .info-section {
    width: 100%;
    .mall-title {
      font-size: 18px; font-weight: 700; color: #2c3e50; margin: 0 0 8px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .mall-id-tag {
      display: inline-flex; align-items: center; font-size: 12px; color: #909399;
      background: #f2f6fc; padding: 1px 8px; border-radius: 10px; margin-bottom: 8px;
      cursor: pointer; transition: all 0.2s;
      i { margin-right: 3px; font-size: 12px; }
      &:hover { color: var(--admin-accent-text); background: var(--admin-accent-soft); }
    }
    .expire-tag {
      display: inline-flex; align-items: center; font-size: 12px; color: #909399;
      background: #f7f8fa; padding: 2px 10px; border-radius: 12px;
      i { margin-right: 4px; }
      &.is-danger { color: #f56c6c; background: #fef0f0; }
    }
  }

  /* 卡片底部 */
  .card-footer {
    height: 50px; border-top: 1px solid #f2f6fc; padding: 0 20px;
    display: flex; align-items: center; justify-content: space-between;
    transition: all 0.3s;
    .enter-text { font-size: 13px; color: #909399; transition: color 0.3s; }
    .arrow-icon { color: #c0c4cc; transition: transform 0.3s; }

    .more-btn {
      width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center;
      color: #909399; transition: all 0.2s;
      &:hover { background: #eef1f6; color: #303133; }
    }
  }
}

/* 空状态 */
.empty-placeholder {
  min-height: 500px; display: flex; align-items: center; justify-content: center;
  .empty-content {
    text-align: center;
    .empty-icon-box {
      position: relative; width: 100px; height: 100px; margin: 0 auto 25px;
      display: flex; align-items: center; justify-content: center;
      background: #fff; border-radius: 50%; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
      .empty-img { width: 50px; opacity: 0.6; filter: grayscale(100%); z-index: 2; position: relative; }
      .pulse-ring {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%;
        animation: pulse 2s infinite; z-index: 1;
      }
    }
    h3 { font-size: 20px; color: #303133; margin-bottom: 8px; }
    p { color: #909399; font-size: 14px; }
  }
}

/* 底部 */
.entry-footer {
  text-align: center; padding-bottom: 30px; color: #c0c4cc; font-size: 12px; z-index: 1;
  .footer-content { display: flex; align-items: center; justify-content: center; }
  .record-link { color: inherit; text-decoration: none; margin-left: 10px; &:hover { color: #909399; } }
}

.plugin-tree-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 10px;
  margin-bottom: 8px;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;

  .plugin-tree-count {
    margin-left: auto;
    color: #909399;
    font-size: 12px;
  }
}
.plugin-tree-box {
  max-height: 400px; overflow-y: auto; border: 1px solid #e4e7ed; border-radius: 4px; padding: 10px; background: #fafafa;
  &::-webkit-scrollbar { width: 6px; }
  &::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }
}

/* 商城 Logo 选择器：复用站点配置同款交互（预览 + 悬停删除遮罩 + 点击调起图片选择器） */
.logo-uploader {
  position: relative;
  width: 88px;
  height: 88px;
  border: 1px dashed #dcdfe6;
  border-radius: 8px;
  background-color: #fff;
  cursor: pointer;
  overflow: hidden;
  transition: all 0.3s;

  &:hover { border-color: $primary-color; }
  &.has-file { border-style: solid; border-color: #ebeef5; }

  /* 整个 .logo-uploader 框承载 @click,这里只是占满方框并居中图标(纯展示) */
  .uploader-trigger {
    width: 100%; height: 100%;
    display: flex; justify-content: center; align-items: center;
    color: #8c939d;
    i { font-size: 26px; }
  }

  .preview-area {
    width: 100%; height: 100%; position: relative;
    img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .mask {
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.55);
      display: flex; justify-content: center; align-items: center;
      opacity: 0; transition: opacity 0.3s;
      i { color: #fff; font-size: 20px; cursor: pointer; }
    }
    &:hover .mask { opacity: 1; }
  }
}
.logo-uploader-tip {
  font-size: 12px; color: #909399; line-height: 1.6; margin-top: 6px;
}

/* ─── 通用 drawer 三段式布局（body 滚动 / footer 吸底）─── */
/* el-drawer__body 默认 overflow:auto 会把 footer 一起卷走，这里关掉外层滚动，让内部 .drawer-body 独立负责滚动 */
::v-deep .el-drawer__body {
  padding: 0;
  overflow: hidden;
}
.drawer-wrap {
  display: flex;
  flex-direction: column;
  height: 100%;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  &::-webkit-scrollbar { width: 6px; }
  &::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }
}
.drawer-footer {
  flex-shrink: 0;
  padding: 12px 24px;
  border-top: 1px solid #EBEDF0;
  text-align: right;
  background: #fff;
}

/* 系统配置信息面板 */
.sysinfo-group { margin-bottom: 18px;
  &:last-child { margin-bottom: 0; }
}
.sysinfo-group-title {
  font-size: 13px; font-weight: 600; color: #303133; padding: 6px 10px;
  background: #f5f7fa; border-left: 3px solid var(--admin-accent); border-radius: 3px; margin-bottom: 6px;
}
.sysinfo-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 12px; font-size: 13px; border-bottom: 1px dashed #ebeef5;
  &:last-child { border-bottom: none; }
}
.sysinfo-label { color: #909399; flex-shrink: 0; margin-right: 16px; }
.sysinfo-value {
  color: #303133; text-align: right; word-break: break-all;
  display: flex; align-items: center;
  &.st-ok { color: #67C23A; }
  &.st-warn { color: #E6A23C; }
  &.st-error { color: #F56C6C; }
}
.sysinfo-dot {
  display: inline-block; width: 7px; height: 7px; border-radius: 50%;
  margin-right: 6px; background: currentColor; flex-shrink: 0;
}
.sysinfo-empty { text-align: center; color: #c0c4cc; padding: 40px 0; font-size: 13px; }

/* 调度中心 */
.sched-cards {
  display: flex;
  gap: 14px;
  margin-bottom: 18px;
}
.sched-card {
  flex: 1;
  background: #f7f9fc;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 14px 10px;
  text-align: center;
}
.sched-card .sc-label { font-size: 12px; color: #909399; margin-bottom: 8px; }
.sched-card .sc-value { font-size: 22px; font-weight: 700; color: #303133; }
.sched-card .sc-sub { font-size: 11px; color: #c0c4cc; margin-top: 4px; }
.sched-section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 16px 0 8px;
  padding-left: 8px;
  border-left: 3px solid var(--admin-accent);
}

/* 系统更新弹窗 */
.upd-row {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  font-size: 14px;
}
.upd-label {
  width: 76px;
  color: #909399;
  flex-shrink: 0;
}
.upd-cur { color: #303133; font-weight: 500; em { color: #909399; font-style: normal; font-size: 12px; } }
.upd-latest { color: #67c23a; font-weight: 600; }
.upd-new { background: #f6ffed; border: 1px solid #e1f3d8; border-radius: 6px; padding: 12px; margin: 12px 0; }
.upd-log-title { color: #909399; font-size: 12px; margin: 6px 0 4px; }
.upd-log { color: #606266; font-size: 13px; white-space: pre-wrap; line-height: 1.6; }
.upd-uptodate { color: #67c23a; text-align: center; padding: 20px 0; i { margin-right: 6px; } }
.upd-nodata { color: #e6a23c; text-align: center; padding: 20px 0; font-size: 13px; i { margin-right: 6px; } }
.upd-progress { margin-top: 16px; }
.upd-state { margin-top: 8px; color: #606266; font-size: 13px; }
.upd-spin-inline { display: inline-block; width: 13px; height: 13px; margin-right: 7px; vertical-align: -2px; border: 2px solid var(--admin-accent-border); border-top-color: var(--admin-accent-text); border-radius: 50%; animation: upd-spin .7s linear infinite; }
.upd-msg { color: #909399; }
.upd-progress-tip { margin: 10px 0 0; font-size: 12px; color: #909399; line-height: 1.6; i { margin-right: 4px; color: var(--admin-accent-text); } }

/* 科幻升级控制台面板 */
.sf-panel { position: relative; margin-top: 16px; border-radius: 12px; padding: 26px 22px 22px; overflow: hidden;
  background: radial-gradient(120% 80% at 50% 0%, #292C32 0%, #191B20 55%, #111317 100%);
  border: 1px solid rgba(var(--admin-dark-accent-rgb),.22); box-shadow: inset 0 0 0 1px rgba(var(--admin-dark-accent-rgb),.06), 0 8px 26px rgba(4,10,22,.5); }
.sf-panel.sf-done { border-color: rgba(45,212,191,.3); }
.sf-panel.sf-fail { border-color: rgba(248,113,113,.32); }
.sf-panel.sf-paused .sf-ring, .sf-panel.sf-paused .sf-orbit { animation-play-state: paused; }
.sf-grid { position: absolute; inset: 0; opacity: .35; pointer-events: none;
  background-image: linear-gradient(rgba(var(--admin-dark-accent-rgb),.09) 1px, transparent 1px), linear-gradient(90deg, rgba(var(--admin-dark-accent-rgb),.09) 1px, transparent 1px);
  background-size: 26px 26px; -webkit-mask: radial-gradient(80% 60% at 50% 30%, #000, transparent 75%); mask: radial-gradient(80% 60% at 50% 30%, #000, transparent 75%); }
.sf-scan { position: absolute; left: 0; right: 0; height: 70px; pointer-events: none;
  background: linear-gradient(180deg, transparent, rgba(var(--admin-dark-accent-rgb),.14), transparent); animation: sf-scan 3.2s ease-in-out infinite; }
@keyframes sf-scan { 0% { top: -70px; } 100% { top: 100%; } }
.sf-core { position: relative; width: 150px; height: 150px; margin: 2px auto 4px; }
.sf-ring { position: absolute; border-radius: 50%; border-style: solid; }
.sf-ring.r1 { inset: 0; border-width: 3px; border-color: rgba(var(--admin-dark-accent-rgb),.12); border-top-color: var(--admin-dark-accent); border-right-color: var(--admin-dark-accent);
  box-shadow: 0 0 22px rgba(var(--admin-dark-accent-rgb),.4), inset 0 0 22px rgba(var(--admin-dark-accent-rgb),.12); animation: sf-spin 2.8s linear infinite; }
.sf-ring.r2 { inset: 16px; border-width: 2px; border-color: rgba(var(--admin-dark-accent-rgb),.1); border-bottom-color: var(--admin-dark-accent); border-left-color: var(--admin-dark-accent);
  box-shadow: 0 0 16px rgba(var(--admin-dark-accent-rgb),.35); animation: sf-spin 2s linear infinite reverse; }
.sf-ring.r3 { inset: 34px; border-width: 2px; border-color: rgba(var(--admin-dark-accent-rgb),.14); border-top-color: var(--admin-dark-accent);
  box-shadow: 0 0 14px rgba(var(--admin-dark-accent-rgb),.4); animation: sf-spin 1.3s linear infinite; }
.sf-orbit { position: absolute; inset: 0; animation: sf-spin 2.8s linear infinite; }
.sf-orbit::before { content: ""; position: absolute; top: -4px; left: 50%; width: 8px; height: 8px; margin-left: -4px; border-radius: 50%; background: var(--admin-dark-accent); box-shadow: 0 0 12px 3px rgba(var(--admin-dark-accent-rgb),.9); }
.sf-panel.sf-done .sf-ring.r1 { border-top-color: #2dd4bf; border-right-color: #2dd4bf; box-shadow: 0 0 22px rgba(45,212,191,.45); }
.sf-panel.sf-fail .sf-ring.r1 { border-top-color: #f87171; border-right-color: #f87171; box-shadow: 0 0 22px rgba(248,113,113,.45); }
@keyframes sf-spin { to { transform: rotate(360deg); } }
.sf-pct { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.sf-pct b { font-size: 34px; font-weight: 600; color: #F7F6F2; font-family: "SF Mono", Menlo, Consolas, monospace; letter-spacing: 1px; text-shadow: 0 0 18px rgba(var(--admin-dark-accent-rgb),.7); line-height: 1; }
.sf-pct span { margin-top: 4px; font-size: 11px; letter-spacing: 3px; color: var(--admin-dark-accent); text-transform: uppercase; }
.sf-panel.sf-fail .sf-pct span { color: #fca5a5; }
.sf-stagerow { display: flex; align-items: center; justify-content: center; margin: 16px 0 4px; }
.sf-step { display: flex; flex-direction: column; align-items: center; gap: 6px; width: 64px; }
.sf-dot { width: 11px; height: 11px; border-radius: 50%; background: #34373E; border: 1px solid #60646D; }
.sf-step.done .sf-dot { background: var(--admin-dark-accent); border-color: var(--admin-dark-accent); box-shadow: 0 0 8px rgba(var(--admin-dark-accent-rgb),.6); }
.sf-step.active .sf-dot { background: var(--admin-dark-accent); border-color: var(--admin-dark-accent); animation: sf-pulse 1.3s ease-out infinite; }
@keyframes sf-pulse { 0% { box-shadow: 0 0 0 0 rgba(var(--admin-dark-accent-rgb),.6); } 100% { box-shadow: 0 0 0 9px rgba(var(--admin-dark-accent-rgb),0); } }
.sf-step small { font-size: 11px; color: #A8ABB2; }
.sf-step.done small, .sf-step.active small { color: #E7E5DE; }
.sf-line { flex: 1; height: 2px; max-width: 26px; background: #34373E; margin-top: -19px; }
.sf-line.on { background: var(--admin-dark-accent); box-shadow: 0 0 6px rgba(var(--admin-dark-accent-rgb),.5); }
.sf-bar { position: relative; height: 8px; border-radius: 6px; background: #292C32; overflow: hidden; margin: 16px 4px 0; border: 1px solid rgba(var(--admin-dark-accent-rgb),.15); }
.sf-fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 6px; background: var(--admin-dark-accent); box-shadow: 0 0 12px rgba(var(--admin-dark-accent-rgb),.6); transition: width .5s ease; }
.sf-panel.sf-done .sf-fill { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }
.sf-panel.sf-fail .sf-fill { background: linear-gradient(90deg, #ef4444, #f87171); }
.sf-fill::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,.55), transparent); animation: sf-shim 1.4s linear infinite; }
.sf-panel.sf-paused .sf-fill::after { display: none; }
@keyframes sf-shim { 0% { transform: translateX(-100%); } 100% { transform: translateX(280%); } }
.sf-state { margin-top: 12px; text-align: center; color: #E7E5DE; font-size: 13.5px; letter-spacing: .3px; }
.sf-msg { color: #A8ABB2; }
.sf-tip { margin: 8px 2px 0; text-align: center; color: #A8ABB2; font-size: 11.5px; line-height: 1.6; }

/* 检查中:加载动画 + 安抚文案 */
.upd-checking { padding: 30px 12px 24px; text-align: center; }
.upd-loader {
  width: 42px; height: 42px; margin: 0 auto 18px;
  border: 3px solid #ebeef5; border-top-color: var(--admin-accent-text); border-radius: 50%;
  animation: upd-spin .8s linear infinite;
}
@keyframes upd-spin { to { transform: rotate(360deg); } }
.upd-checking-title { font-size: 15px; font-weight: 600; color: #303133; margin-bottom: 10px; }
.upd-checking-tip { margin: 0 auto; max-width: 380px; font-size: 13px; color: #909399; line-height: 1.75; }
.upd-checking-tip b { color: #e6a23c; font-weight: 600; }
</style>
