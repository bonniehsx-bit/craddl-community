<template>
  <div class="diy-page-container">
    <div class="diy-header">
      <div class="header-left">
        <div class="page-title">用户中心装修</div>
        <div class="page-desc">实时预览 · 拖拽排序 · 个性化定制</div>
      </div>
      <div class="header-right">
        <el-button size="small" @click="getSetting">重置配置</el-button>
        <el-button type="primary" size="small" :loading="is_loading" @click="save">保存并发布</el-button>
      </div>
    </div>

    <div class="diy-body">
      <div class="preview-wrap">
        <div class="preview-inner">
          <div class="mobile-shell">
            <div class="mobile-notch">
              <div class="camera" />
              <div class="speaker" />
            </div>
            <div class="mobile-screen">
              <div
                class="status-bar"
                :class="{ 'light': ['premium', 'christmas', 'classic'].includes(form.style_type) }"
              >
                <span>12:00</span>
                <div class="icons">
                  <i class="el-icon-signal" />
                  <i class="el-icon-full-screen" />
                  <i class="el-icon-loading" />
                </div>
              </div>

              <!-- 全局顶部导航 (模拟 H5/小程序 头部) -->
              <div
                class="global-nav-bar"
                :class="{ 'light': ['premium', 'christmas', 'classic', 'aurora', 'dream', 'silver'].includes(form.style_type) }"
              >
                <div class="nav-left" />
                <div class="nav-title" />
                <div class="nav-right">
                  <div class="icon-btn-mock">
                    <i class="el-icon-chat-dot-round" />
                    <div class="nav-badge" />
                  </div>
                  <div class="icon-btn-mock">
                    <i class="el-icon-setting" />
                  </div>
                </div>
              </div>

              <div class="component-container">
                <preview-aurora
                  v-if="form.style_type === 'aurora'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-classic
                  v-if="form.style_type === 'classic'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-premium
                  v-if="form.style_type === 'premium'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-minimalist
                  v-if="form.style_type === 'minimalist'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-christmas
                  v-if="form.style_type === 'christmas'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-silver
                  v-if="form.style_type === 'silver'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-dream
                  v-if="form.style_type === 'dream'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-card
                  v-if="form.style_type === 'card'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-airy
                  v-if="form.style_type === 'airy'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
                <preview-night
                  v-if="form.style_type === 'night'"
                  :data="form"
                  :active-block="currentSelectBlock"
                  @edit="chooseBlock"
                />
              </div>
            </div>
          </div>
          <div class="preview-tips">
            <i class="el-icon-touch" /> 点击手机内模块可快速定位配置项
          </div>
        </div>
      </div>

      <div class="setting-wrap">

        <div class="setting-sidebar">
          <div class="sidebar-group">
            <div class="group-title">界面风格</div>
            <div class="style-select-box">
              <div
                v-for="(style, index) in styleList"
                :key="index"
                class="style-item"
                :class="{ active: form.style_type === style.value }"
                @click="form.style_type = style.value"
              >
                <div class="style-preview">
                  <div class="sp-header" :class="{ 'sp-header--light': style.lightHeader }" :style="{ background: style.background }">
                    <div class="sp-avatar" />
                    <div class="sp-lines"><span /><span /></div>
                  </div>
                  <div class="sp-body" :class="{ 'sp-body--dark': style.dark }">
                    <div class="sp-card" />
                    <div class="sp-grid"><i /><i /><i /><i /></div>
                  </div>
                  <div v-if="form.style_type === style.value" class="sp-check">
                    <i class="el-icon-check" />
                  </div>
                </div>
                <span>{{ style.label }}</span>
              </div>
            </div>
          </div>

          <div class="sidebar-group">
            <div class="group-title">色调配置</div>
            <div class="color-theme-box">
              <div class="color-theme-tip">留空则使用当前风格的默认配色</div>
              <div class="color-theme-row">
                <span class="color-label">主色调</span>
                <el-color-picker
                  v-model="form.colors.header_from"
                  size="mini"
                  :predefine="['#a18cd1','#E41F19','#2a2a2a','#D32F2F','#A0C4FF','#E0EAFF','#5C6BC0','#00897B','#F4511E','#8E24AA']"
                />
                <el-button v-if="form.colors.header_from" type="text" size="mini" @click="form.colors.header_from = ''">重置</el-button>
              </div>
              <div class="color-theme-row">
                <span class="color-label">辅色调</span>
                <el-color-picker
                  v-model="form.colors.header_to"
                  size="mini"
                  :predefine="['#fad0c4','#c0392b','#121212','#8E0000','#FFC6FF','#F3E5F5','#3949AB','#00695C','#BF360C','#6A1B9A']"
                />
                <el-button v-if="form.colors.header_to" type="text" size="mini" @click="form.colors.header_to = ''">重置</el-button>
              </div>
            </div>
          </div>

          <div class="sidebar-group">
            <div class="group-title">悬浮客服</div>
            <div class="service-config-box">
              <div class="service-row">
                <span class="service-label">启用客服按钮</span>
                <el-switch v-model="form.customer_service.is_show" :active-value="1" :inactive-value="0" />
              </div>
              <div class="service-tip">悬浮客服按钮，对所有风格模板统一生效。</div>
              <template v-if="form.customer_service.is_show == 1">
                <div class="service-row service-icon-row">
                  <span class="service-label">按钮图标</span>
                  <single-image-selector v-model="form.customer_service.icon" width="44px" height="44px" />
                </div>
                <div class="service-tip">留空则使用系统默认客服图标，建议 80×80 透明底 PNG。</div>

                <div class="service-field">
                  <span class="service-field-label">客服标题</span>
                  <el-input v-model="form.customer_service.title" placeholder="留空则不显示，如：在线客服" size="mini" />
                  <div class="service-tip">填写后显示在客服图标下方；留空则只显示图标。</div>
                </div>

                <div class="service-divider" />

                <div class="service-field">
                  <span class="service-field-label">客服方式</span>
                  <el-radio-group v-model="form.customer_service.mode" size="mini" class="service-radio-group">
                    <el-radio-button label="im">在线客服(IM)</el-radio-button>
                    <el-radio-button label="wechat">微信客服会话</el-radio-button>
                    <el-radio-button label="custom">自定义电话</el-radio-button>
                  </el-radio-group>
                  <div class="service-tip">「在线客服(IM)」全端走系统 IM 客服（需 IM 插件已启用客服；微信端未开 IM 时自动降级为自带会话）。「微信客服会话」仅小程序进入自带会话。「自定义电话」弹出工作时间与电话。H5 / App 无微信自带会话：选「微信客服会话」时自动按电话弹窗处理。</div>
                </div>

                <div class="service-field">
                  <span class="service-field-label">工作时间</span>
                  <el-input v-model="form.customer_service.work_time" placeholder="如：早上10:00-20:00" size="mini" />
                </div>

                <div class="service-field">
                  <span class="service-field-label">客服电话</span>
                  <div v-for="(p, i) in form.customer_service.phones" :key="i" class="phone-item">
                    <el-input v-model="form.customer_service.phones[i]" placeholder="客服电话" size="mini" class="phone-input" />
                    <el-button type="danger" icon="el-icon-delete" circle size="mini" @click="form.customer_service.phones.splice(i, 1)" />
                  </div>
                  <el-button type="primary" size="mini" icon="el-icon-plus" plain class="phone-add-btn" @click="form.customer_service.phones.push('')">添加电话</el-button>
                  <div class="service-tip">「自定义」方式及 H5 / App 端使用：点击客服按钮弹出工作时间与电话，可一键拨打。</div>
                </div>
              </template>
            </div>
          </div>

          <div class="sidebar-group">
            <div class="group-title">模块编辑</div>
            <div class="module-menu">
              <div
                v-for="nav in navList"
                :key="nav.key"
                class="menu-item"
                :class="{ active: currentSelectBlock === nav.key, disabled: !checkIsShow(nav.key) }"
                @click="chooseBlock(nav.key)"
              >
                <span class="menu-name">{{ nav.name }}</span>
                <i v-if="checkIsShow(nav.key)" class="el-icon-success is-show-icon" />
                <i v-else class="el-icon-error is-hide-icon" />
              </div>
            </div>
          </div>
        </div>

        <div class="setting-form-panel">
          <div class="panel-header">
            <div class="panel-title">{{ getBlockName(currentSelectBlock) }} 配置</div>
            <div class="panel-desc">您可以在此调整该模块的详细属性与数据</div>
          </div>

          <div class="form-area">
            <template v-if="currentSelectBlock === 'bg'">
              <el-form label-position="top" size="small">
                <el-row :gutter="20">
                  <el-col :span="24">
                    <el-form-item label="默认头像">
                      <single-image-selector v-model="form.header.avatar" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="24">
                    <el-form-item label="显示用户手机号">
                      <el-switch v-model="form.header.show_mobile" :active-value="1" :inactive-value="0" />
                      <span class="color-theme-tip" style="margin-left:12px;">开启后，个人中心头部展示当前登录用户的手机号。</span>
                    </el-form-item>
                  </el-col>
                </el-row>

                <div class="sub-card">
                  <div class="sub-title">头部统计数据</div>
                  <div class="stat-config-item">
                    <span class="label">商品收藏</span>
                    <div class="inputs">
                      <el-input v-model="form.header.goods.name" placeholder="显示名称" size="mini" />
                      <el-switch v-model="form.header.goods.is_show" :active-value="1" :inactive-value="0" />
                    </div>
                  </div>
                  <div class="stat-config-item">
                    <span class="label">浏览足迹</span>
                    <div class="inputs">
                      <el-input v-model="form.header.footmark.name" placeholder="显示名称" size="mini" />
                      <el-switch
                        v-model="form.header.footmark.is_show"
                        :active-value="1"
                        :inactive-value="0"
                      />
                    </div>
                  </div>
                  <div class="stat-config-item">
                    <span class="label">成交订单</span>
                    <div class="inputs">
                      <el-input v-model="form.header.order.name" placeholder="显示名称" size="mini" />
                      <el-switch v-model="form.header.order.is_show" :active-value="1" :inactive-value="0" />
                    </div>
                  </div>
                </div>
              </el-form>
            </template>

            <template v-if="currentSelectBlock === 'finance'">
              <el-form label-position="top" size="small">
                <el-alert
                  type="warning"
                  :closable="false"
                  show-icon
                  class="cfg-tip"
                >
                  <template slot="title">提现相关已迁移至「提现中心」插件</template>
                  配置跳转链接时请优先选择「提现中心」的链接（提现 / 提现记录 / 各状态直达）。链接留空则走原默认提现页面。
                </el-alert>

                <div class="flex-row-between mb-20">
                  <el-input v-model="form.finance.name" placeholder="模块标题 (如: 我的资产)" style="width: 250px;" />
                  <el-switch
                    v-model="form.finance.is_show"
                    :active-value="1"
                    :inactive-value="0"
                    active-text="开启模块"
                  />
                </div>

                <div class="finance-grid">
                  <div v-for="fk in [{key:'balance',label:'余额'},{key:'price',label:'佣金'},{key:'integral',label:'积分'},{key:'coupon',label:'优惠券'}]" :key="fk.key" class="finance-item">
                    <div class="f-header">
                      <span class="label">{{ fk.label }}</span>
                      <el-switch
                        v-model="form.finance[fk.key].is_show"
                        :active-value="1"
                        :inactive-value="0"
                        size="mini"
                      />
                    </div>
                    <el-input v-model="form.finance[fk.key].name" size="mini" placeholder="自定义名称" />
                    <div class="f-color">
                      <span class="color-label">数值颜色</span>
                      <el-color-picker
                        v-model="form.finance[fk.key].color"
                        size="mini"
                        show-alpha
                        :predefine="['#ff4d4f','#ff9f43','#52c41a','#1890ff','#722ed1','#5e5ce6','#ff75c3','#333333']"
                      />
                      <el-button v-if="form.finance[fk.key].color" type="text" size="mini" style="margin-left:4px;" @click="form.finance[fk.key].color = ''">重置</el-button>
                    </div>
                    <div class="f-link">
                      <link-picker v-model="form.finance[fk.key].url" :text="form.finance[fk.key].url || '跳转链接(留空走默认)'" />
                    </div>
                  </div>
                </div>
              </el-form>
            </template>

            <template v-if="currentSelectBlock === 'order'">
              <el-form label-position="top" size="small">
                <div class="flex-row-between mb-20">
                  <span style="font-size:13px;color:#303133;font-weight:600;">订单模块总开关</span>
                  <el-switch
                    v-model="form.order.is_show"
                    :active-value="1"
                    :inactive-value="0"
                    active-text="开启模块"
                  />
                </div>
                <el-row :gutter="20">
                  <el-col :span="12">
                    <el-form-item label="主标题">
                      <el-input v-model="form.order.title" placeholder="如: 我的订单" />
                    </el-form-item>
                  </el-col>
                  <el-col :span="12">
                    <el-form-item label="副标题">
                      <el-input v-model="form.order.sub_title" placeholder="如: 全部订单" />
                    </el-form-item>
                  </el-col>
                </el-row>

                <div class="draggable-list">
                  <div v-for="(key, index) in orderStatusList" :key="key" class="drag-item">
                    <div class="item-icon">
                      <single-image-selector
                        v-model="form.order[key].icon"
                        width="36px"
                        height="36px"
                      />
                    </div>
                    <div class="item-info">
                      <div class="row-1">
                        <el-input
                          v-model="form.order[key].name"
                          size="mini"
                          class="name-input"
                          placeholder="状态名称"
                        />
                        <el-switch
                          v-model="form.order[key].is_show"
                          :active-value="1"
                          :inactive-value="0"
                          size="mini"
                        />
                      </div>
                      <div class="row-2">
                        <link-picker
                          v-model="form.order[key].url"
                          :text="form.order[key].url || '默认跳转链接'"
                        />
                      </div>
                    </div>
                    <div class="item-handle"><i class="el-icon-s-operation" /></div>
                  </div>
                </div>
              </el-form>
            </template>

            <template v-if="currentSelectBlock === 'menus' || currentSelectBlock === 'menus2'">
              <el-form label-position="top" size="small">
                <div class="flex-row-between mb-10">
                  <el-input
                    v-model="form[currentSelectBlock].name"
                    placeholder="模块标题 (如: 常用服务)"
                    style="width: 250px;"
                  />
                  <el-switch
                    v-model="form[currentSelectBlock].is_show"
                    :active-value="1"
                    :inactive-value="0"
                    active-text="开启模块"
                  />
                </div>

                <el-form-item label="布局样式">
                  <el-radio-group v-model="form[currentSelectBlock].type" size="small">
                    <el-radio-button :label="0">网格布局 (宫格)</el-radio-button>
                    <el-radio-button :label="1">列表布局 (纵向)</el-radio-button>
                  </el-radio-group>
                </el-form-item>

                <div class="drag-header">
                  <span>菜单项 ({{ form[currentSelectBlock].list.length }} 个，可拖拽排序)</span>
                </div>

                <draggable v-model="form[currentSelectBlock].list" handle=".drag-handle">
                  <div
                    v-for="(item, index) in form[currentSelectBlock].list"
                    :key="index"
                    class="compact-wrap"
                  >
                    <!-- 紧凑行 -->
                    <div
                      class="drag-item compact-item"
                      :class="{ 'is-active': activeMenuEdit === currentSelectBlock + '_' + index }"
                    >
                      <div class="drag-handle"><i class="el-icon-rank" /></div>
                      <div class="compact-icon">
                        <img v-if="item.icon" :src="item.icon" class="icon-thumb">
                        <i v-else class="el-icon-picture-outline icon-placeholder" />
                      </div>
                      <div class="compact-name">{{ item.name || '未命名菜单' }}</div>
                      <div class="compact-actions">
                        <span
                          class="action-btn edit-btn"
                          @click.stop="toggleMenuEdit(currentSelectBlock, index)"
                        >
                          <i :class="activeMenuEdit === currentSelectBlock + '_' + index ? 'el-icon-arrow-up' : 'el-icon-edit'" />
                          {{ activeMenuEdit === currentSelectBlock + '_' + index ? '收起' : '编辑' }}
                        </span>
                        <span
                          class="action-btn del-btn"
                          @click.stop="menuItemDelete(currentSelectBlock, index)"
                        >
                          <i class="el-icon-delete" />
                        </span>
                      </div>
                    </div>
                    <!-- 展开编辑区 -->
                    <div
                      v-if="activeMenuEdit === currentSelectBlock + '_' + index"
                      class="item-edit-panel"
                    >
                      <div class="edit-panel-row">
                        <span class="edit-label">图标</span>
                        <single-image-selector v-model="item.icon" width="44px" height="44px" :allow-icons="true" :icon-scale="75" />
                      </div>
                      <div class="edit-panel-row">
                        <span class="edit-label">名称</span>
                        <el-input
                          v-model="item.name"
                          placeholder="菜单名称"
                          size="small"
                          style="flex:1"
                        />
                      </div>
                      <div class="edit-panel-row">
                        <span class="edit-label">链接</span>
                        <div style="flex:1">
                          <link-picker v-model="item.url" :text="item.url || '选择跳转链接'" />
                        </div>
                      </div>
                    </div>
                  </div>
                </draggable>

                <div class="add-item-row" @click="addMenuItem(currentSelectBlock)">
                  <i class="el-icon-plus" />
                  <span>添加菜单项</span>
                </div>
              </el-form>
            </template>

            <template v-if="currentSelectBlock === 'copyright'">
              <el-form label-position="top" size="small">
                <el-form-item label="版权开关">
                  <el-switch v-model="form.copyright.is_show" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="版权文字信息">
                  <el-input
                    v-model="form.copyright.text"
                    type="textarea"
                    :rows="4"
                    placeholder="例如: © 2025 Mall Power by YourCompany"
                  />
                </el-form-item>
              </el-form>
            </template>

            <template v-if="currentSelectBlock === 'tabbar'">
              <el-form label-position="top" size="small">
                <el-form-item label="选中颜色">
                  <el-color-picker v-model="form.tabbar.color" size="small" />
                </el-form-item>

                <div class="drag-header">
                  <span>导航按钮 ({{ form.tabbar.list.length }}/5，可拖拽排序)</span>
                </div>

                <draggable v-model="form.tabbar.list" handle=".drag-handle">
                  <div
                    v-for="(item, index) in form.tabbar.list"
                    :key="index"
                    class="compact-wrap"
                  >
                    <!-- 紧凑行 -->
                    <div
                      class="drag-item compact-item"
                      :class="{ 'is-active': activeTabbarEdit === index }"
                    >
                      <div class="drag-handle"><i class="el-icon-rank" /></div>
                      <div class="compact-icon">
                        <img
                          v-if="item.active_icon || item.icon"
                          :src="item.active_icon || item.icon"
                          class="icon-thumb"
                        >
                        <i v-else class="el-icon-picture-outline icon-placeholder" />
                      </div>
                      <div class="compact-name">{{ item.name || '未命名导航' }}</div>
                      <div class="compact-actions">
                        <span class="action-btn edit-btn" @click.stop="toggleTabbarEdit(index)">
                          <i :class="activeTabbarEdit === index ? 'el-icon-arrow-up' : 'el-icon-edit'" />
                          {{ activeTabbarEdit === index ? '收起' : '编辑' }}
                        </span>
                        <span class="action-btn del-btn" @click.stop="itemDelete(index)">
                          <i class="el-icon-delete" />
                        </span>
                      </div>
                    </div>
                    <!-- 展开编辑区 -->
                    <div v-if="activeTabbarEdit === index" class="item-edit-panel">
                      <div class="edit-panel-row">
                        <span class="edit-label">选中图标</span>
                        <single-image-selector
                          v-model="item.active_icon"
                          width="44px"
                          height="44px"
                        />
                      </div>
                      <div class="edit-panel-row">
                        <span class="edit-label">默认图标</span>
                        <single-image-selector
                          v-model="item.icon"
                          width="44px"
                          height="44px"
                        />
                      </div>
                      <div class="edit-panel-row">
                        <span class="edit-label">名称</span>
                        <el-input
                          v-model="item.name"
                          placeholder="导航名称"
                          size="small"
                          style="flex:1"
                        />
                      </div>
                      <div class="edit-panel-row">
                        <span class="edit-label">链接</span>
                        <div style="flex:1">
                          <link-picker v-model="item.url" :text="item.url || '跳转链接'" />
                        </div>
                      </div>
                    </div>
                  </div>
                </draggable>

                <div
                  class="add-item-row"
                  :class="{ 'is-disabled': form.tabbar.list.length >= 5 }"
                  @click="addItem"
                >
                  <i class="el-icon-plus" />
                  <span>{{ form.tabbar.list.length >= 5 ? '已达上限（最多5个）' : '添加导航项' }}</span>
                </div>
              </el-form>
            </template>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PreviewAurora from './components/PreviewAurora'
import PreviewClassic from './components/PreviewClassic'
import PreviewPremium from './components/PreviewPremium'
import PreviewMinimalist from './components/PreviewMinimalist'
import PreviewChristmas from './components/PreviewChristmas'
import PreviewSilver from './components/PreviewSilver'
import PreviewDream from './components/PreviewDream'
import PreviewCard from './components/PreviewCard'
import PreviewAiry from './components/PreviewAiry'
import PreviewNight from './components/PreviewNight'
import draggable from 'vuedraggable'

export default {
  name: 'MallUserDecoration',
  components: {
    PreviewAurora, PreviewClassic, PreviewPremium, PreviewMinimalist,
    PreviewChristmas, PreviewSilver, PreviewDream, PreviewCard, PreviewAiry, PreviewNight, draggable
  },
  data() {
    return {
      is_loading: false,
      currentSelectBlock: 'bg',
      activeMenuEdit: null, // 格式: 'menus_0' / 'menus2_2'
      activeTabbarEdit: null, // tabbar 当前展开项的 index
      orderStatusList: ['status_0', 'status_1', 'status_2', 'status_3', 'status_4', 'status_5'],
      navList: [
        { name: '个人信息', key: 'bg' },
        { name: '资产栏', key: 'finance' },
        { name: '我的订单', key: 'order' },
        { name: '工具栏1', key: 'menus' },
        { name: '工具栏2', key: 'menus2' },
        { name: '底部版权', key: 'copyright' },
        { name: '底部导航', key: 'tabbar' }
      ],
      styleList: [
        { label: '极光微光', value: 'aurora', class: 'style-aurora', background: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)' },
        { label: '经典商务', value: 'classic', class: 'style-classic', background: 'linear-gradient(to bottom, #d63031 50%, #f5f6fa 50%)' },
        { label: '高端黑金', value: 'premium', class: 'style-premium', background: 'linear-gradient(135deg, #2d3436 0%, #000000 100%)', dark: true },
        { label: '极简白', value: 'minimalist', class: 'style-minimalist', background: '#FFFFFF', lightHeader: true },
        { label: '圣诞红', value: 'christmas', class: 'style-christmas', background: 'linear-gradient(135deg, #D32F2F 0%, #C62828 100%)' },
        { label: '银光质感', value: 'silver', class: 'style-silver', background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)', lightHeader: true },
        { label: '梦幻弥散', value: 'dream', class: 'style-dream', background: 'linear-gradient(to top, #ff9a9e 0%, #fecfef 99%, #fecfef 100%)' },
        { label: '卡片色头', value: 'card', class: 'style-card', background: 'linear-gradient(135deg, #5B6CF0 0%, #8A7CF0 100%)' },
        { label: '极简留白', value: 'airy', class: 'style-airy', background: 'linear-gradient(135deg, #ffffff 0%, #eef0f3 100%)', lightHeader: true },
        { label: '暗色极简', value: 'night', class: 'style-night', background: 'linear-gradient(135deg, #2a2c31 0%, #131417 100%)', dark: true }
      ],
      form: {
        style_type: 'aurora',
        header: {
          user_bg: '',
          avatar: '',
          goods: { is_show: 1, name: '商品收藏' },
          order: { is_show: 1, name: '成交订单' },
          footmark: { is_show: 1, name: '足迹' },
          show_mobile: 0
        },
        finance: {
          name: '我的资产',
          is_show: 1,
          integral: { name: '积分', is_show: 1, color: '', url: '' },
          balance: { name: '余额', is_show: 1, color: '', url: '' },
          price: { name: '佣金', is_show: 1, color: '', url: '' },
          coupon: { name: '优惠券', is_show: 1, color: '', url: '' }
        },
        order: {
          is_show: 1,
          title: '我的订单',
          sub_title: '全部订单',
          status_0: { name: '待付款', icon: '', is_show: 1, url: '' },
          status_1: { name: '待发货', icon: '', is_show: 1, url: '' },
          status_2: { name: '待收货', icon: '', is_show: 1, url: '' },
          status_3: { name: '待评价', is_show: 1, icon: '', url: '' },
          status_4: { name: '已完成', is_show: 1, icon: '', url: '' },
          status_5: { name: '取消', is_show: 1, icon: '', url: '' }
        },
        menus: { type: 0, is_show: 1, name: '常用工具', list: [] },
        menus2: { type: 0, is_show: 0, name: '常用工具', list: [] },
        copyright: { is_show: 1, text: 'Simplicity & Aurora UI' },
        tabbar: { color: '#E41F19', list: [] },
        colors: { header_from: '', header_to: '' },
        // 悬浮客服（全局统一，对所有风格模板生效）
        // mode: 'wechat'=微信原生客服会话(仅小程序) / 'custom'=弹窗展示工作时间+电话；H5/App 恒用 custom
        customer_service: { is_show: 0, icon: '', mode: 'wechat', work_time: '', phones: [], title: '' }
      }
    }
  },
  created() {
    this.getSetting()
  },
  methods: {
    chooseBlock(block) {
      this.currentSelectBlock = block
    },
    getBlockName(key) {
      const target = this.navList.find(item => item.key === key)
      return target ? target.name : '配置'
    },
    checkIsShow(key) {
      if (['bg', 'tabbar'].includes(key)) return true
      if (key === 'order') return !this.form.order || this.form.order.is_show != 0
      if (this.form[key] && typeof this.form[key].is_show !== 'undefined') {
        return this.form[key].is_show == 1
      }
      return true
    },
    getSetting() {
      this.is_loading = true
      this.$request({ url: '/mall/mall/user-center' }).then(res => {
        this.is_loading = false
        if (res.code == 0) {
          this.form = Object.assign({}, this.form, res.data.page_data)
          if (!this.form.style_type) this.$set(this.form, 'style_type', 'aurora')
          if (!this.form.copyright) this.$set(this.form, 'copyright', { is_show: 1, text: 'Simplicity & Aurora UI' })
          // 补全客服标题字段（老数据可能没有）
          if (!this.form.customer_service) this.$set(this.form, 'customer_service', { is_show: 0, icon: '', mode: 'wechat', work_time: '', phones: [], title: '' })
          else if (this.form.customer_service.title === undefined) this.$set(this.form.customer_service, 'title', '')
          if (!this.form.header.order) this.$set(this.form.header, 'order', { is_show: 1, name: '成交订单' })
          if (!this.form.header.footmark) this.$set(this.form.header, 'footmark', { is_show: 1, name: '足迹' })
          if (this.form.header.show_mobile === undefined) this.$set(this.form.header, 'show_mobile', 0)
          if (!this.form.finance.price) this.$set(this.form.finance, 'price', { is_show: 1, name: '佣金' })
          // 补全资产项 color / url 字段（老数据可能没有）
          ;['balance', 'integral', 'price', 'coupon'].forEach(key => {
            if (this.form.finance[key] && this.form.finance[key].color === undefined) {
              this.$set(this.form.finance[key], 'color', '')
            }
            if (this.form.finance[key] && this.form.finance[key].url === undefined) {
              this.$set(this.form.finance[key], 'url', '')
            }
          })
          // 补全订单模块总开关（老数据无此字段时默认开启，避免线上订单块消失）
          if (this.form.order.is_show === undefined) this.$set(this.form.order, 'is_show', 1)
          // 补全 order status 缺失字段（老数据可能没有 icon/url）
          const defaultStatus = { name: '', icon: '', is_show: 1, url: '' }
          this.orderStatusList.forEach(key => {
            if (!this.form.order[key]) {
              this.$set(this.form.order, key, { ...defaultStatus })
            } else {
              this.form.order[key] = Object.assign({}, defaultStatus, this.form.order[key])
            }
          })
          // 补全 menus list 缺失字段
          ;['menus', 'menus2'].forEach(block => {
            if (this.form[block] && Array.isArray(this.form[block].list)) {
              this.form[block].list = this.form[block].list.map(item => Object.assign({ icon: '', name: '', url: '' }, item))
            }
          })
          if (res.data.page_data.tabbar) {
            this.form.tabbar = res.data.page_data.tabbar
          }
          // 补全色调配置字段（老数据没有此字段时初始化为空）
          if (!this.form.colors) {
            this.$set(this.form, 'colors', { header_from: '', header_to: '' })
          } else {
            if (this.form.colors.header_from === undefined) this.$set(this.form.colors, 'header_from', '')
            if (this.form.colors.header_to === undefined) this.$set(this.form.colors, 'header_to', '')
          }
          // 补全悬浮客服配置（老数据没有此字段时默认关闭；mode 默认 wechat 保持存量小程序行为不变）
          if (!this.form.customer_service) {
            this.$set(this.form, 'customer_service', { is_show: 0, icon: '', mode: 'wechat', work_time: '', phones: [] })
          } else {
            if (this.form.customer_service.is_show === undefined) this.$set(this.form.customer_service, 'is_show', 0)
            if (this.form.customer_service.icon === undefined) this.$set(this.form.customer_service, 'icon', '')
            if (this.form.customer_service.mode === undefined) this.$set(this.form.customer_service, 'mode', 'wechat')
            if (this.form.customer_service.work_time === undefined) this.$set(this.form.customer_service, 'work_time', '')
            if (!Array.isArray(this.form.customer_service.phones)) this.$set(this.form.customer_service, 'phones', [])
          }
        }
      })
    },
    toggleMenuEdit(block, index) {
      const key = `${block}_${index}`
      this.activeMenuEdit = this.activeMenuEdit === key ? null : key
    },
    toggleTabbarEdit(index) {
      this.activeTabbarEdit = this.activeTabbarEdit === index ? null : index
    },
    addItem() {
      if (this.form.tabbar.list.length >= 5) return
      this.form.tabbar.list.push({ icon: '', active_icon: '', name: '', url: '' })
      // 新增后自动展开编辑区
      this.activeTabbarEdit = this.form.tabbar.list.length - 1
    },
    itemDelete(index) {
      this.form.tabbar.list.splice(index, 1)
      if (this.activeTabbarEdit === index) this.activeTabbarEdit = null
      else if (this.activeTabbarEdit > index) this.activeTabbarEdit--
    },
    addMenuItem(type) {
      this.form[type].list.push({ icon: '', name: '', url: '' })
      // 新增后自动展开编辑区
      const newIndex = this.form[type].list.length - 1
      this.activeMenuEdit = `${type}_${newIndex}`
    },
    menuItemDelete(type, index) {
      const key = `${type}_${index}`
      this.form[type].list.splice(index, 1)
      if (this.activeMenuEdit === key) this.activeMenuEdit = null
    },
    save() {
      this.is_loading = true
      this.$request({
        url: '/mall/mall/user-center',
        method: 'post',
        data: { page_data: JSON.stringify(this.form) }
      }).then(res => {
        this.is_loading = false
        if (res.code == 0) {
          this.$message.success('保存成功')
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
/* 变量定义 */
$bg-color: #f0f2f5;
$primary-color: #409EFF;
$border-color: #e4e7ed;
$text-main: #303133;
$text-sub: #909399;

.color-theme-box {
  padding: 8px 4px;
  .color-theme-tip {
    font-size: 11px;
    color: #909399;
    margin-bottom: 10px;
    line-height: 1.4;
  }
  .color-theme-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    gap: 8px;
    .color-label {
      font-size: 12px;
      color: #606266;
      width: 42px;
      flex-shrink: 0;
    }
  }
}

.service-config-box {
  padding: 8px 4px;
  .service-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    .service-label {
      font-size: 12px;
      color: #606266;
    }
  }
  .service-icon-row {
    margin-top: 4px;
  }
  .service-tip {
    font-size: 11px;
    color: #909399;
    line-height: 1.5;
    margin-bottom: 8px;
  }
  .service-divider {
    height: 1px;
    background: #ebeef5;
    margin: 12px 0;
  }
  // 宽控件竖排字段：标签在上、控件占满整行，避免窄侧栏横向拥挤
  .service-field {
    margin-bottom: 14px;
    .service-field-label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: #606266;
      margin-bottom: 6px;
    }
    .service-tip {
      margin-top: 6px;
      margin-bottom: 0;
    }
    ::v-deep .el-input {
      width: 100%;
    }
  }
  .service-radio-group {
    display: flex;
    width: 100%;
    ::v-deep .el-radio-button {
      flex: 1;
    }
    ::v-deep .el-radio-button__inner {
      width: 100%;
      padding-left: 0;
      padding-right: 0;
    }
  }
  .phone-item {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    .phone-input {
      flex: 1;
      margin-right: 6px;
    }
  }
  .phone-add-btn {
    width: 100%;
    margin-top: 2px;
  }
}

.diy-page-container {
  height: calc(100vh - 50px - var(--tags-view-height, 34px));
  display: flex;
  flex-direction: column;
  background-color: $bg-color;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  overflow: hidden;
}

/* 顶部栏 */
.diy-header {
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #dcdfe6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);

  .header-left {
    .page-title {
      font-size: 16px;
      font-weight: 700;
      color: $text-main;
    }

    .page-desc {
      font-size: 12px;
      color: $text-sub;
      margin-top: 2px;
    }
  }
}

.diy-body {
  flex: 1;
  display: flex;
  overflow: hidden;
  /* 防止溢出到整体页面 */
}

/* --- 左侧预览区 --- */
.preview-wrap {
  width: 420px;
  background: #eef0f4;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  flex-shrink: 0;

  .preview-inner {
    transform: scale(0.9);
    transform-origin: center center;
  }

  .mobile-shell {
    width: 375px;
    height: 712px;
    background: #fff;
    border-radius: 40px;
    border: 10px solid #2c3e50;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;

    .mobile-notch {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 160px;
      height: 32px;
      background: #2c3e50;
      border-bottom-left-radius: 16px;
      border-bottom-right-radius: 16px;
      z-index: 100;
      display: flex;
      justify-content: center;
      align-items: center;

      .camera {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #1a2633;
        margin-right: 10px;
      }

      .speaker {
        width: 50px;
        height: 4px;
        background: #1a2633;
        border-radius: 2px;
      }
    }

    .mobile-screen {
      width: 100%;
      height: 100%;
      background: #F5F7FA;
      display: flex;
      flex-direction: column;

      .status-bar {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 44px;
        padding: 0 18px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        padding-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
        z-index: 90;
        color: #333;
        background: transparent;

        &.light {
          color: #fff;
        }

        .icons i {
          margin-left: 5px;
        }
      }

      .global-nav-bar {
        position: absolute;
        top: 44px;
        left: 0;
        width: 100%;
        height: 44px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 15px;
        z-index: 90;
        color: #333;
        background: transparent;
        pointer-events: none;
        /* 让点击穿透，避免遮挡子组件内容点击 */

        &.light {
          color: #fff;
        }

        .nav-left {
          width: 60px;
        }

        .nav-title {
          flex: 1;
          text-align: center;
          font-size: 16px;
          font-weight: bold;
        }

        .nav-right {
          width: 60px;
          display: flex;
          justify-content: flex-end;
          gap: 12px;

          .icon-btn-mock {
            position: relative;
            font-size: 20px;
            display: flex;
            align-items: center;

            .nav-badge {
              position: absolute;
              top: 0px;
              right: -4px;
              width: 8px;
              height: 8px;
              background: #FF4d4f;
              border-radius: 50%;
              border: 1px solid #fff;
            }
          }
        }
      }

      .component-container {
        flex: 1;
        overflow-y: auto;

        &::-webkit-scrollbar {
          display: none;
        }
      }
    }
  }

  .preview-tips {
    text-align: center;
    margin-top: 20px;
    color: #909399;
    font-size: 13px;
  }
}

/* ================= 右侧双栏配置区 ================= */
.setting-wrap {
  flex: 1;
  display: flex;
  background: #fff;
  border-left: 1px solid $border-color;
  overflow: hidden;
  /* 外层禁止滚动 */
}

/* 栏目1：左侧固定宽度导航 */
.setting-sidebar {
  width: 350px;
  background: #fafbfc;
  border-right: 1px solid $border-color;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  padding: 20px 15px;
  flex-shrink: 0;

  &::-webkit-scrollbar {
    width: 4px;
  }

  &::-webkit-scrollbar-thumb {
    background: #dcdfe6;
    border-radius: 2px;
  }

  .sidebar-group {
    margin-bottom: 25px;

    .group-title {
      font-size: 13px;
      font-weight: bold;
      color: #909399;
      margin-bottom: 12px;
      padding-left: 5px;
    }
  }
}

/* 风格选择器 (双列网格) */
.style-select-box {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;

  .style-item {
    cursor: pointer;
    text-align: center;
    font-size: 12px;
    color: #606266;

    // 迷你手机页面缩略图：头部渐变(反映主题色) + 头像/骨架线 + 浅/深 body 的资产卡与宫格点
    .style-preview {
      border-radius: 8px;
      background: #fff;
      border: 2px solid #ebeef5;
      margin-bottom: 6px;
      position: relative;
      overflow: hidden;
      transition: all 0.2s;

      .sp-header {
        height: 34px;
        padding: 0 8px;
        display: flex;
        align-items: center;
        gap: 6px;

        .sp-avatar {
          width: 14px;
          height: 14px;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.9);
          flex-shrink: 0;
        }
        .sp-lines {
          display: flex;
          flex-direction: column;
          gap: 3px;
          span {
            display: block;
            height: 4px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.9);
          }
          span:first-child { width: 36px; }
          span:last-child { width: 22px; background: rgba(255, 255, 255, 0.55); }
        }

        // 浅色头部(极简白/银光/留白)：白色骨架会隐没，改用深色骨架
        &.sp-header--light {
          .sp-avatar { background: rgba(0, 0, 0, 0.22); }
          .sp-lines span { background: rgba(0, 0, 0, 0.22); }
          .sp-lines span:last-child { background: rgba(0, 0, 0, 0.12); }
        }
      }

      .sp-body {
        padding: 6px 8px 8px;
        background: #fff;

        .sp-card {
          height: 12px;
          border-radius: 4px;
          background: #eef1f5;
          margin-bottom: 6px;
        }
        .sp-grid {
          display: flex;
          gap: 5px;
          i {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            background: #e4e7ed;
          }
        }
        &.sp-body--dark {
          background: #23252a;
          .sp-card { background: #34373d; }
          .sp-grid i { background: #3a3d44; }
        }
      }

      .sp-check {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--admin-primary);
        color: var(--admin-on-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: bold;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
      }
    }

    &:hover .style-preview {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }

    &.active {
      color: var(--admin-accent-text);
      font-weight: 600;

      .style-preview {
        border-color: var(--admin-accent-text);
      }
    }
  }
}

/* 模块菜单 (纵向列表) */
.module-menu {
  display: flex;
  flex-direction: column;
  gap: 6px;

  .menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #EBEEF5;
    border-radius: 6px;
    font-size: 13px;
    color: $text-main;
    cursor: pointer;
    transition: all 0.2s;

    .is-show-icon {
      color: #67C23A;
      font-size: 14px;
    }

    .is-hide-icon {
      color: #C0C4CC;
      font-size: 14px;
    }

    &:hover {
      border-color: var(--admin-accent-border);
      background: var(--admin-accent-soft);
    }

    &.active {
      background: var(--admin-primary);
      border-color: var(--admin-accent-text);
      color: var(--admin-on-primary);
      font-weight: bold;
      box-shadow: 0 2px 6px rgba(var(--admin-accent-rgb), 0.3);

      .is-show-icon,
      .is-hide-icon {
        color: var(--admin-on-primary);
        opacity: 0.9;
      }
    }

    &.disabled:not(.active) {
      color: #909399;
      background: #f5f7fa;
    }
  }
}

/* 栏目2：右侧自适应表单区 */
.setting-form-panel {
  flex: 1;
  padding: 20px 30px;
  overflow-y: auto;
  /* 只有当这个特定表单内容极多时才会出现滚动条 */
  background: #fff;

  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-thumb {
    background: #dcdfe6;
    border-radius: 3px;
  }

  .panel-header {
    border-bottom: 1px solid #EBEEF5;
    padding-bottom: 15px;
    margin-bottom: 20px;

    .panel-title {
      font-size: 16px;
      font-weight: 700;
      color: $text-main;
      margin-bottom: 6px;
    }

    .panel-desc {
      font-size: 12px;
      color: #909399;
    }
  }
}

/* 风格CSS模拟 */
.style-aurora {
  background: linear-gradient(135deg, #a18cd1, #fbc2eb);
}

.style-classic {
  background: linear-gradient(to bottom, #E41F19 30%, #eee 30%);
}

.style-premium {
  background: #2b2b2b;
}

.style-minimalist {
  background: #fff;
  border: 1px solid #eee !important;
}

.style-christmas {
  background: #c0392b;
}

.style-silver {
  background: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
}

.style-dream {
  background: linear-gradient(45deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
}

/* 表单区域通用样式 */
.form-area {
  max-width: 600px;
  /* 限制最大宽度，防止大屏下表单拉伸过长 */

  .mb-20 {
    margin-bottom: 20px;
  }

  .mb-10 {
    margin-bottom: 10px;
  }

  .mb-5 {
    margin-bottom: 5px;
  }

  .flex-row-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  /* 子卡片 */
  .sub-card {
    background: #F9FAFC;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #EBEEF5;

    .sub-title {
      font-size: 13px;
      font-weight: 600;
      color: $text-main;
      margin-bottom: 12px;
    }

    .stat-config-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;

      &:last-child {
        margin-bottom: 0;
      }

      .label {
        font-size: 12px;
        color: #606266;
        width: 60px;
      }

      .inputs {
        flex: 1;
        display: flex;
        gap: 10px;
        align-items: center;
      }
    }
  }
}

/* 资产宫格 */
.finance-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;

  .finance-item {
    background: #F9FAFC;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid #EBEEF5;

    .f-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 12px;
      font-weight: 600;
    }

    .f-color {
      display: flex;
      align-items: center;
      margin-top: 8px;

      .color-label {
        font-size: 11px;
        color: #909399;
        margin-right: 6px;
        white-space: nowrap;
      }
    }

    .f-link {
      margin-top: 8px;
    }
  }
}

/* 迁移提示 */
.cfg-tip {
  margin-bottom: 16px;
  align-items: flex-start;

  ::v-deep .el-alert__description {
    font-size: 12px;
    line-height: 1.6;
    margin: 4px 0 0;
  }
}

/* 我的订单 — 状态拖拽列表（保留原始展开样式） */
.draggable-list {
  .drag-item {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid #EBEEF5;
    padding: 10px;
    margin-bottom: 8px;
    border-radius: 6px;
    transition: all 0.2s;

    &:hover {
      box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05);
      border-color: #dcdfe6;
    }

    .drag-handle {
      cursor: move;
      color: #C0C4CC;
      padding: 4px;
      margin-right: 8px;
    }

    .item-icon {
      margin-right: 12px;
      flex-shrink: 0;
    }

    .item-info {
      flex: 1;
      min-width: 0;

      .row-1 {
        display: flex;
        align-items: center;
        margin-bottom: 6px;

        .name-input {
          width: 120px;
          margin-right: 10px;
        }
      }
    }

    .item-del {
      cursor: pointer;
      color: #F56C6C;
      margin-left: 10px;
      padding: 5px;

      &:hover {
        background: #fef0f0;
        border-radius: 4px;
      }
    }
  }
}

/* 工具栏/底部导航 — 紧凑模式 + 点击展开 */
.compact-wrap {
  margin-bottom: 4px;
}

.compact-item {
  display: flex;
  align-items: center;
  background: #fff;
  border: 1px solid #EBEEF5;
  padding: 8px 12px;
  border-radius: 6px;
  transition: all 0.2s;

  &:hover {
    border-color: #dcdfe6;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  }

  &.is-active {
    border-color: var(--admin-accent-text);
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    background: var(--admin-accent-soft);
  }

  .drag-handle {
    cursor: move;
    color: #C0C4CC;
    padding: 4px;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .compact-icon {
    width: 32px;
    height: 32px;
    margin-right: 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;

    .icon-thumb {
      width: 32px;
      height: 32px;
      object-fit: cover;
      border-radius: 4px;
    }

    .icon-placeholder {
      font-size: 22px;
      color: #C0C4CC;
    }
  }

  .compact-name {
    flex: 1;
    font-size: 13px;
    color: #303133;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .compact-actions {
    display: flex;
    gap: 2px;
    align-items: center;
    flex-shrink: 0;

    .action-btn {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 4px;
      cursor: pointer;
      transition: all 0.15s;
      user-select: none;

      &.edit-btn {
        color: var(--admin-accent-text);
        &:hover { background: var(--admin-accent-soft); }
      }

      &.del-btn {
        color: #F56C6C;
        &:hover { background: #fef0f0; }
      }
    }
  }
}

/* 展开的编辑面板 */
.item-edit-panel {
  background: #f5f7fa;
  border: 1px solid var(--admin-accent-text);
  border-top: none;
  border-radius: 0 0 6px 6px;
  padding: 14px 16px 10px;
  margin-bottom: 0;

  .edit-panel-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;

    &:last-child { margin-bottom: 0; }

    .edit-label {
      font-size: 12px;
      color: #606266;
      width: 62px;
      flex-shrink: 0;
      text-align: right;
    }
  }
}

.drag-header {
  font-size: 13px;
  color: #303133;
  font-weight: bold;
  margin: 15px 0 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* 底部添加行 */
.add-item-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 44px;
  margin-top: 8px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  color: #909399;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: var(--admin-accent-text);
    color: var(--admin-accent-text);
    background: var(--admin-accent-soft);
  }

  &.is-disabled {
    cursor: not-allowed;
    opacity: 0.5;
    pointer-events: none;
  }
}
</style>
