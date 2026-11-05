<template>
  <div class="app-container">
    <el-card v-loading="is_loading" class="box-card">
      <div slot="header" class="clearfix">
        <span>推广中心</span>
        <div style="float: right;">
          <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
        </div>
      </div>
      <div class="config-container">
        <div class="flex-row">

          <div class="mobile-preview new-layout">
            <div class="preview-main">
              <!-- [新增] 手机刘海装饰 -->
              <div class="phone-notch" />

              <!-- 1. 头部区域 -->
              <div class="preview-header-gradient">
                <!-- 装饰圆圈 -->
                <div class="bg-circle-1" />
                <div class="bg-circle-2" />

                <div class="preview-user-info flex-x-between flex-y-center">
                  <div class="left-part flex-y-center">
                    <el-avatar icon="el-icon-user-solid" :size="55" class="user-avatar" />
                    <div class="user-info-details">
                      <div class="user-info-name">用户昵称</div>
                      <div class="user-info-parent">
                        <span class="label">推荐人</span>
                        <span class="divider">|</span>
                        <span class="val">无</span>
                      </div>
                    </div>
                  </div>
                  <div class="right-part">
                    <div class="qr-btn">
                      <i class="el-icon-full-screen" />
                      <span class="text">推广码</span>
                    </div>
                  </div>
                </div>

                <!-- 头部统计数据（可配置跳转） -->
                <div
                  class="preview-header-stats flex-x-around selectable"
                  :class="{ active: currentSelectBlock == 'header' }"
                  @click="chooseBlock('header')"
                >
                  <div class="stat-item">
                    <div class="num">0.00</div>
                    <div class="txt">{{ form.header.balance.name }}</div>
                  </div>
                  <div class="line" />
                  <div class="stat-item">
                    <div class="num">0.00</div>
                    <div class="txt">{{ form.header.price.name }}</div>
                  </div>
                  <div class="line" />
                  <div class="stat-item">
                    <div class="num">0</div>
                    <div class="txt">{{ form.header.integral.name }}</div>
                  </div>
                </div>
              </div>

              <!-- 2. 内容区域 -->
              <div class="preview-card-area">
                <div class="preview-card-wrapper">

                  <!-- 资产卡片 -->
                  <div
                    class="preview-card finance-card"
                    :class="{ active: currentSelectBlock == 'finance' }"
                    @click="chooseBlock('finance')"
                  >
                    <div class="preview-card-header flex-x-between">
                      <div class="card-title">{{ form.finance.name }}</div>
                      <div class="more">查看明细 <i class="el-icon-arrow-right" /></div>
                    </div>
                    <div class="preview-card-body finance-body">
                      <!-- 可提现主区 + 提现按钮（对齐移动端 withdraw-row） -->
                      <div class="withdraw-row">
                        <div class="wd-left">
                          <div class="wd-label">{{ form.finance.price.name }}</div>
                          <div class="wd-val">0.00</div>
                        </div>
                        <div class="wd-btn"><span>提现</span><i class="el-icon-arrow-right" /></div>
                      </div>
                      <!-- 累计 / 提现中 / 已提现 三栏 -->
                      <div class="finance-sub flex-x-between">
                        <div class="finance-item">
                          <div class="finance-item-value highlight">0.00</div>
                          <div class="finance-item-label">{{ form.finance.total.name }}</div>
                        </div>
                        <div class="finance-item">
                          <div class="finance-item-value">0.00</div>
                          <div class="finance-item-label">{{ form.finance.cashing.name }}</div>
                        </div>
                        <div class="finance-item">
                          <div class="finance-item-value">0.00</div>
                          <div class="finance-item-label">{{ form.finance.cash.name }}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 菜单卡片 -->
                  <div
                    class="preview-card menus-card"
                    :class="{ active: currentSelectBlock == 'menus' }"
                    @click="chooseBlock('menus')"
                  >
                    <div class="preview-card-header flex-x-between">
                      <div class="card-title">{{ form.menus.name }}</div>
                    </div>
                    <div class="preview-card-body menus-body">
                      <!-- 确保即使没有数据也能显示布局 -->
                      <div
                        v-for="(item, i) in form.menus.list"
                        v-if="form.menus.list.length"
                        :key="i"
                        class="menu-item"
                      >
                        <div class="menu-item-icon" :style="{ backgroundColor: item.iconBgColor || '#F3F6FF' }">
                          <el-image :src="item.icon" class="menu-item-image" fit="contain" />
                        </div>
                        <div class="menu-item-label">{{ item.name }}</div>
                      </div>
                      <!-- 空状态占位 -->
                      <div v-if="!form.menus.list.length" class="menu-item placeholder">
                        <div class="menu-item-icon" style="background:#f4f4f5"><i class="el-icon-plus" /></div>
                        <div class="menu-item-label">添加菜单</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="config-forms">
            <div class="cfg-panel-head">
              <i :class="blockIcon" />
              <span class="title">{{ blockTitle }}</span>
              <small class="hint">点击左侧预览区切换编辑模块</small>
            </div>

            <!-- 头部数据 -->
            <el-form v-if="currentSelectBlock == 'header'" size="mini" label-width="72px" class="cfg-form">
              <el-alert type="warning" :closable="false" show-icon class="cfg-tip">
                <template slot="title">提现相关已迁移至「提现中心」插件</template>
                头部「余额/收益/积分」的跳转请选择「提现中心」对应的中心页；<b>留空则点击不跳转</b>。
              </el-alert>
              <div class="cfg-card">
                <div class="cfg-card-tag">余额</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.header.balance.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.header.balance.url">选择链接(留空不跳转)</link-picker>
                </el-form-item>
              </div>
              <div class="cfg-card">
                <div class="cfg-card-tag">收益</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.header.price.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.header.price.url">选择链接(留空不跳转)</link-picker>
                </el-form-item>
              </div>
              <div class="cfg-card">
                <div class="cfg-card-tag">积分</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.header.integral.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.header.integral.url">选择链接(留空不跳转)</link-picker>
                </el-form-item>
              </div>
            </el-form>

            <!-- 资产卡片 -->
            <el-form v-if="currentSelectBlock == 'finance'" size="mini" label-width="72px" class="cfg-form">
              <el-alert
                type="warning"
                :closable="false"
                show-icon
                class="cfg-tip"
              >
                <template slot="title">
                  提现相关已迁移至「提现中心」插件
                </template>
                配置跳转链接时请优先选择「提现中心」的链接（提现 / 提现记录 / 各状态直达）。链接留空则走原默认提现页面。
              </el-alert>

              <div class="cfg-card">
                <div class="cfg-card-tag">板块设置</div>
                <el-form-item label="板块标题">
                  <el-input v-model="form.finance.name" placeholder="如：我的资产" />
                </el-form-item>
                <el-form-item label="查看明细">
                  <link-picker v-model="form.finance.more_url">查看明细跳转(留空不跳转)</link-picker>
                </el-form-item>
              </div>

              <div class="cfg-card">
                <div class="cfg-card-tag">累计奖励</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.finance.total.name" placeholder="名称" />
                </el-form-item>
              </div>

              <div class="cfg-card">
                <div class="cfg-card-tag">可提奖励<span class="tag-badge">提现入口</span></div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.finance.price.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.finance.price.url">提现跳转(留空走默认)</link-picker>
                </el-form-item>
              </div>

              <div class="cfg-card">
                <div class="cfg-card-tag">提现中</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.finance.cashing.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.finance.cashing.url">跳转(留空走默认)</link-picker>
                </el-form-item>
              </div>

              <div class="cfg-card">
                <div class="cfg-card-tag">已提现</div>
                <el-form-item label="显示名称">
                  <el-input v-model="form.finance.cash.name" placeholder="名称" />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <link-picker v-model="form.finance.cash.url">跳转(留空走默认)</link-picker>
                </el-form-item>
              </div>
            </el-form>

            <!-- 菜单工具 -->
            <el-form v-if="currentSelectBlock == 'menus'" size="mini" label-width="72px" class="cfg-form">
              <div class="cfg-card">
                <div class="cfg-card-tag">板块设置</div>
                <el-form-item label="板块标题">
                  <el-input v-model="form.menus.name" placeholder="如：常用工具" />
                </el-form-item>
              </div>

              <div class="cfg-list-head">
                <span>菜单项</span>
                <small><i class="el-icon-rank" /> 拖动排序</small>
              </div>

              <draggable v-if="form.menus.list" v-model="form.menus.list" handle=".drag-handle" animation="180">
                <div v-for="(item, index) in form.menus.list" :key="index" class="menu-cfg-card">
                  <i class="drag-handle el-icon-rank" />
                  <el-button
                    type="danger"
                    icon="el-icon-close"
                    circle
                    size="mini"
                    class="menu-del-btn"
                    @click="menuItemDelete(index)"
                  />
                  <div class="menu-cfg-body">
                    <single-image-selector
                      v-model="item.icon"
                      width="44px"
                      height="44px"
                      :allow-icons="true"
                      :icon-scale="75"
                      :icon-color="item.iconColor || '#303133'"
                      @input="syncMenuIconColor(item, $event)"
                    />
                    <div class="menu-cfg-fields">
                      <el-input v-model="item.name" placeholder="菜单名称" />
                      <div class="color-row">
                        <span class="color-label">背景</span>
                        <el-color-picker v-model="item.iconBgColor" size="mini" />
                        <span class="color-label">图标</span>
                        <el-color-picker v-model="item.iconColor" size="mini" @change="changeMenuIconColor(item, $event)" />
                      </div>
                      <link-picker v-model="item.url">选择链接</link-picker>
                    </div>
                  </div>
                </div>
              </draggable>

              <el-button class="add-menu-btn" icon="el-icon-plus" @click="addMenuItem">添加菜单</el-button>
            </el-form>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import { parseTablerAsset } from '@/utils/tabler-icons'

export default {
  name: 'MallManage',
  components: {
    draggable
  },

  data() {
    return {
      showMenuLink: false,
      currentMenuEditNavIndex: 0,

      currentSelectBlock: '',
      form: {
        header: {
          balance: { name: '余额', url: '' },
          price: { name: '收益', url: '' },
          integral: { name: '积分', url: '' }
        },
        menus: {
          type: 0,
          name: '常用工具',
          list: []
        },
        finance: {
          name: '我的资产',
          more_url: '',
          total: { name: '积分' },
          price: { name: '佣金', url: '' },
          cashing: { name: '佣金', url: '' },
          cash: { name: '成功提现', url: '' }
        }
      },
      is_loading: false,

      // 【新增】定义一组默认颜色
      defaultColors: [
        { iconBgColor: '#F3F6FF', iconColor: '#409EFF' },
        { iconBgColor: '#FDF4F2', iconColor: '#E6A23C' },
        { iconBgColor: '#F2FCF8', iconColor: '#19be6b' },
        { iconBgColor: '#FFF3F3', iconColor: '#FF4544' },
        { iconBgColor: '#F4F4F5', iconColor: '#909399' }
      ]
    }
  },
  computed: {
    blockTitle() {
      return { header: '头部数据', finance: '资产卡片', menus: '菜单工具' }[this.currentSelectBlock] || ''
    },
    blockIcon() {
      return { header: 'el-icon-data-line', finance: 'el-icon-bank-card', menus: 'el-icon-menu' }[this.currentSelectBlock] || ''
    }
  },
  created() {
    this.getSetting()
    this.currentSelectBlock = 'finance'
  },
  methods: {
    syncMenuIconColor(item, url) {
      const icon = parseTablerAsset(url)
      if (icon) this.$set(item, 'iconColor', icon.color)
    },
    changeMenuIconColor(item, color) {
      if (!parseTablerAsset(item.icon)) return
      const nextColor = /^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test(color || '') ? color : '#303133'
      const url = new URL(item.icon)
      url.searchParams.set('color', nextColor)
      item.icon = url.toString()
      this.$set(item, 'iconColor', nextColor)
    },
    chooseBlock(block) {
      this.currentSelectBlock = block
    },
    getSetting() {
      this.is_loading = true
      this.$request({
        url: '/mall/mall/user-manage'
      }).then(res => {
        this.is_loading = false
        if (res.code == 0 && res.data.page_data) {
          Object.keys(this.form).forEach(key => {
            if (res.data.page_data[key]) {
              Object.assign(this.form[key], res.data.page_data[key])
            }
          })
          if (!this.form.menus.list) {
            this.form.menus.list = []
          }
          // 补全财务项 url 字段（老数据 Object.assign 后可能丢失）
          ['price', 'cashing', 'cash'].forEach(k => {
            if (this.form.finance[k] && this.form.finance[k].url === undefined) {
              this.$set(this.form.finance[k], 'url', '')
            }
          })
          if (this.form.finance.more_url === undefined) {
            this.$set(this.form.finance, 'more_url', '')
          }
          // 补全头部各项 name/url（老数据可能只有 name，甚至缺对象）
          ['balance', 'price', 'integral'].forEach(k => {
            if (!this.form.header[k]) {
              this.$set(this.form.header, k, { name: '', url: '' })
            } else if (this.form.header[k].url === undefined) {
              this.$set(this.form.header[k], 'url', '')
            }
          })

          // 【新增】为已保存的旧数据补充默认颜色
          this.form.menus.list.forEach((item, index) => {
            const defaultColor = this.defaultColors[index % this.defaultColors.length]
            if (!item.iconBgColor) {
              this.$set(item, 'iconBgColor', defaultColor.iconBgColor)
            }
            if (!item.iconColor) {
              this.$set(item, 'iconColor', defaultColor.iconColor)
            }
          })
        }
      }).catch(e => {
        this.is_loading = false
      })
    },

    menuLinkSelected(e) {
      if (!this.form.menus.list[this.currentMenuEditNavIndex].name || this.form.menus.list[this
        .currentMenuEditNavIndex].name ==
        '') {
        this.form.menus.list[this.currentMenuEditNavIndex].name = e.name
      }
      this.showMenuLink = false
      this.form.menus.list[this.currentMenuEditNavIndex].url = e.url
      this.$forceUpdate()
    },

    deleteIcon(row) {
      row.icon = ''
    },
    menuItemDelete(index) {
      this.form.menus.list.splice(index, 1)
    },
    addMenuItem() {
      // 【修改】添加新项目时，自动带上默认颜色
      const newIndex = this.form.menus.list.length
      const defaultColor = this.defaultColors[newIndex % this.defaultColors.length]

      this.form.menus.list.push({
        icon: '',
        name: '',
        url: '',
        open_type: '',
        iconBgColor: defaultColor.iconBgColor, // 默认背景
        iconColor: defaultColor.iconColor // 默认图标颜色
      })
    },

    selectMenuLinkClick(index) {
      this.showMenuLink = true
      this.currentMenuEditNavIndex = index
    },

    save() {
      this.is_loading = true
      this.$request({
        url: '/mall/mall/user-manage',
        method: 'post',
        data: {
          page_data: JSON.stringify(this.form)
        }
      }).then(res => {
        this.is_loading = false
        if (res.code == 0) {
          this.$message.success('保存成功')
        } else {
          this.$message.error(res.msg)
        }
      }).catch(e => {
        this.is_loading = false
      })
    }
  }
}
</script>

<style lang="scss" scoped>
/* 容器 */
.config-container {
  padding-left: 50px;
}

/* 1. 左侧手机预览 */
.mobile-preview {
  width: 750px;
  height: 1600px;
  /* 稍微减小高度以适应 */
  background-color: #F3F3F3;
  zoom: .5;
  /* 缩放 */
  border-radius: 90px;
  /* iPhone X 大圆角 */
  box-sizing: content-box;
  /* 边框在外部 */
  border: 16px solid #1a1a1a;
  /* 更窄的深色机身边框，贴近全面屏 */
  overflow: hidden;
  font-size: 24px;
  position: relative;
  box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
  /* 阴影 */
  margin-left: 20px;
  /* 留出阴影空间 */
}

.mobile-preview.new-layout {
  height: 1624px;
  /* iPhone X 比例 375:812 → 750:1624（瘦高，告别矮胖） */
  background-color: #f5f6fa;

  .phone-notch {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 320px;
    height: 54px;
    background: #1a1a1a;
    border-bottom-left-radius: 36px;
    border-bottom-right-radius: 36px;
    z-index: 100;
  }

  .preview-header-gradient {
    /* [核心] 使用 App 默认主题红 */
    background: linear-gradient(135deg, #FF6B6B 0%, #D71F1F 100%);
    padding: 60px 30px 140px 30px;
    /* 调整内边距 */
    height: auto;
    /* 让高度自适应内容 */
    min-height: 380px;
    box-sizing: border-box;
    position: relative;
    border-bottom-left-radius: 40px;
    /* 圆弧底部 */
    border-bottom-right-radius: 40px;
    overflow: hidden;

    /* 装饰圆圈 */
    .bg-circle-1 {
      position: absolute;
      top: -60px;
      right: -60px;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
    }

    .bg-circle-2 {
      position: absolute;
      bottom: 40px;
      left: -40px;
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.05);
    }
  }

  .preview-user-info {
    color: #fff;
    position: relative;
    z-index: 2;
    margin-bottom: 40px;

    .left-part {
      .user-avatar {
        border: 4px solid rgba(255, 255, 255, 0.3);
        margin-right: 20px;
        flex-shrink: 0;
      }

      .user-info-name {
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 10px;
      }

      .user-info-parent {
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 16px;
        border-radius: 30px;
        font-size: 22px;
        display: inline-flex;
        align-items: center;

        .label {
          opacity: 0.9;
        }

        .divider {
          margin: 0 10px;
          opacity: 0.5;
        }

        .val {
          font-weight: bold;
        }
      }
    }

    .right-part {
      .qr-btn {
        width: 80px;
        height: 80px;
        background: #fff;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #D71F1F;
        /* 主题色 */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);

        i {
          font-size: 32px;
          margin-bottom: 4px;
        }

        .text {
          font-size: 18px;
          font-weight: bold;
        }
      }
    }
  }

  .preview-header-stats {
    position: relative;
    z-index: 2;
    color: #fff;
    margin-bottom: 20px;
    padding: 6px 4px;
    border-radius: 12px;
    transition: box-shadow .2s;

    &.selectable { cursor: pointer; }
    &.active { box-shadow: 0 0 0 3px rgba(var(--admin-accent-rgb), 0.95); }

    .stat-item {
      text-align: center;

      .num {
        font-size: 36px;
        font-weight: bold;
        margin-bottom: 8px;
        font-family: sans-serif;
      }

      .txt {
        font-size: 24px;
        opacity: 0.9;
      }
    }

    .line {
      width: 1px;
      height: 40px;
      background: rgba(255, 255, 255, 0.3);
      margin-top: 10px;
    }
  }

  .preview-card-area {
    margin-top: -80px;
    /* 上浮覆盖 */
    position: relative;
    z-index: 10;
    padding: 0 24px;
    box-sizing: border-box;
  }

  .preview-card-wrapper {
    width: 100%;
  }
}

.preview-main {
  height: 100%;
  width: 100%;
}

.preview-card {
  background-color: #FFFFFF;
  width: 100%;
  border-radius: 20px;
  /* 圆角增加 */
  margin-top: 24px;
  padding: 30px;
  box-sizing: border-box;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
  border: 2px solid transparent;
  /* 选中预留 */

  &.active {
    border-color: var(--admin-accent-text);
  }
}

.finance-card {
  /* height: 200px; */
  /* 自适应高度 */
}

.preview-card-header {
  height: 60px;
  line-height: 60px;
  border-bottom: 1px solid #f5f5f5;
  margin-bottom: 20px;

  .card-title {
    font-size: 30px;
    font-weight: 600;
    color: #333;
    position: relative;
    padding-left: 20px;

    /* 装饰条 */
    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 8px;
      height: 32px;
      background: #D71F1F;
      border-radius: 4px;
    }
  }

  .more {
    font-size: 24px;
    color: #999;
  }
}

.preview-card-body {
  text-align: center;
}

.finance-body {
  text-align: left;
}

/* 可提现主区 + 提现按钮（对齐移动端 withdraw-row） */
.withdraw-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 4px 32px 4px;
  border-bottom: 1px solid #f2f2f2;

  .wd-left {
    .wd-label {
      font-size: 24px;
      color: #888;
      margin-bottom: 10px;
    }

    .wd-val {
      font-size: 56px;
      font-weight: bold;
      color: #D71F1F;
      line-height: 1;
    }
  }

  .wd-btn {
    display: flex;
    align-items: center;
    background: #D71F1F;
    color: #fff;
    font-size: 28px;
    font-weight: 500;
    padding: 16px 36px;
    border-radius: 40px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);

    span { margin-right: 4px; }
  }
}

/* 累计 / 提现中 / 已提现 三栏 */
.finance-sub {
  padding-top: 32px;
}

.finance-item {
  text-align: center;
  width: 33.333%;
}

.finance-item-value {
  font-size: 32px;
  font-weight: bold;
  color: #333;
  margin-bottom: 10px;

  &.highlight {
    color: #D71F1F;
    font-size: 36px;
  }
}

.finance-item-label {
  font-size: 24px;
  color: #999;
}

.menus-card {
  min-height: 200px;
}

.menus-body {
  display: flex;
  flex-flow: row wrap;
  justify-content: flex-start;
  align-items: flex-start;
}

.menu-item {
  width: 25%;
  text-align: center;
  margin-bottom: 30px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.menu-item-icon {
  height: 80px;
  width: 80px;
  margin: 0 auto 12px auto;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-item-image {
  width: 40px;
  height: 40px;
}

.menu-item-label {
  font-size: 24px;
  color: #333;
}

/* 2. 右侧配置表单 */
.config-forms {
  width: 460px;
  margin-left: 40px;
  padding: 20px;
  background: #f7f8fa;
  border-radius: 12px;
  box-sizing: border-box;
  align-self: flex-start;
}

/* 面板头 */
.cfg-panel-head {
  display: flex;
  align-items: center;
  padding-bottom: 14px;
  margin-bottom: 18px;
  border-bottom: 1px solid #ebeef5;

  i {
    font-size: 18px;
    color: var(--admin-accent-text);
    margin-right: 8px;
  }

  .title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }

  .hint {
    margin-left: auto;
    font-size: 12px;
    color: #c0c4cc;
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

/* 配置卡片 */
.cfg-card {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 16px 16px 6px;
  margin-bottom: 14px;
  transition: box-shadow .2s, border-color .2s;

  &:hover {
    border-color: var(--admin-accent-border);
    box-shadow: 0 2px 12px rgba(var(--admin-accent-rgb), 0.08);
  }

  .cfg-card-tag {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 14px;
    padding-left: 9px;
    position: relative;

    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 1px;
      width: 3px;
      height: 14px;
      background: var(--admin-primary);
      border-radius: 2px;
    }

    .tag-badge {
      display: inline-block;
      margin-left: 8px;
      padding: 1px 8px;
      font-size: 11px;
      font-weight: normal;
      color: #f56c6c;
      background: #fef0f0;
      border-radius: 10px;
    }
  }

  .el-form-item {
    margin-bottom: 12px;
  }
}

/* 菜单列表小标题 */
.cfg-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 18px 2px 12px;
  font-size: 13px;
  font-weight: 600;
  color: #303133;

  small {
    font-weight: normal;
    color: #909399;

    i { margin-right: 2px; }
  }
}

/* 菜单配置卡片 */
.menu-cfg-card {
  position: relative;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  transition: box-shadow .2s, border-color .2s;

  &:hover {
    border-color: var(--admin-accent-border);
    box-shadow: 0 2px 12px rgba(var(--admin-accent-rgb), 0.08);
  }

  .drag-handle {
    position: absolute;
    top: 10px;
    left: 10px;
    color: #c0c4cc;
    cursor: move;
    font-size: 16px;

    &:hover { color: var(--admin-accent-text); }
  }

  .menu-del-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 2;
  }

  .menu-cfg-body {
    display: flex;
    padding-top: 14px;
  }

  .menu-cfg-fields {
    flex: 1;
    margin-left: 14px;
    min-width: 0;

    .el-input { margin-bottom: 10px; }
  }

  .color-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;

    .color-label {
      font-size: 12px;
      color: #909399;
      margin: 0 6px 0 0;
    }

    .el-color-picker { margin-right: 16px; }
  }
}

/* 添加菜单按钮 */
.add-menu-btn {
  width: 100%;
  border-style: dashed;
  color: var(--admin-accent-text);
}

.add-image-btn {
  width: 88px;
  height: 88px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  color: #8c939d;
  background-color: #f9f9f9;

  &:hover {
    border-color: var(--admin-accent-text);
  }

  i {
    font-size: 28px;
  }

  .preview-image {
    width: 100%;
    height: 100%;
  }
}

.pic-del {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 20px;
  height: 20px;
  background: #f56c6c;
  border-radius: 50%;
  color: #fff;
  cursor: pointer;

  i {
    font-size: 12px;
  }
}

/* 辅助 Flex 类 */
.flex-row {
  display: flex;
  flex-direction: row;
}

.flex-y-center {
  align-items: center;
}

.flex-x-between {
  justify-content: space-between;
}

.flex-x-center {
  justify-content: center;
}

.flex-x-around {
  display: flex;
  justify-content: space-around;
  align-items: center;
}
</style>
