<template>
  <div class="drawer-container">
    <div>
      <div class="setting-drawer-content">
        <div class="setting-drawer-title">
          <h3 class="drawer-title">后台外观</h3>
        </div>
        <p class="setting-note">仅调整当前浏览器的后台外观，不影响商城前台或其他管理员。</p>
        <div class="theme-options" aria-label="后台配色方案">
          <button v-for="item in themeOptions" :key="item.value" type="button"
                  class="theme-option" :class="{ selected: themeMode === item.value }"
                  :aria-pressed="themeMode === item.value" @click="selectTheme(item.value)">
            <span class="theme-sample" :style="{ background: item.color }"><i :style="{ background: item.accent }" /></span>
            <span>{{ item.label }}</span><i v-if="themeMode === item.value" class="el-icon-check" />
          </button>
        </div>
        <div v-if="themeMode === 'custom'" class="drawer-item">
          <span>自定义主色</span>
          <theme-picker style="float: right;height: 28px;" @change="themeChange" />
        </div>
        <p class="setting-note">切换后即时预览，点击“保存配置”后刷新仍保留。</p>
        <h3 class="drawer-title">侧边栏风格</h3>
        <div class="setting-drawer-block-checbox">
          <div class="setting-drawer-block-checbox-item" @click="handleTheme('theme-dark')">
            <img src="@/assets/images/dark.svg" alt="dark">
            <div v-if="sideTheme === 'theme-dark'" class="setting-drawer-block-checbox-selectIcon" style="display: block;">
              <i aria-label="图标: check" class="anticon anticon-check">
                <svg viewBox="64 64 896 896" data-icon="check" width="1em" height="1em" :fill="theme" aria-hidden="true"
                     focusable="false" class="">
                  <path
                    d="M912 190h-69.9c-9.8 0-19.1 4.5-25.1 12.2L404.7 724.5 207 474a32 32 0 0 0-25.1-12.2H112c-6.7 0-10.4 7.7-6.3 12.9l273.9 347c12.8 16.2 37.4 16.2 50.3 0l488.4-618.9c4.1-5.1.4-12.8-6.3-12.8z"/>
                </svg>
              </i>
            </div>
          </div>
          <div class="setting-drawer-block-checbox-item" @click="handleTheme('theme-light')">
            <img src="@/assets/images/light.svg" alt="light">
            <div v-if="sideTheme === 'theme-light'" class="setting-drawer-block-checbox-selectIcon" style="display: block;">
              <i aria-label="图标: check" class="anticon anticon-check">
                <svg viewBox="64 64 896 896" data-icon="check" width="1em" height="1em" :fill="theme" aria-hidden="true"
                     focusable="false" class="">
                  <path
                    d="M912 190h-69.9c-9.8 0-19.1 4.5-25.1 12.2L404.7 724.5 207 474a32 32 0 0 0-25.1-12.2H112c-6.7 0-10.4 7.7-6.3 12.9l273.9 347c12.8 16.2 37.4 16.2 50.3 0l488.4-618.9c4.1-5.1.4-12.8-6.3-12.8z"/>
                </svg>
              </i>
            </div>
          </div>
        </div>

      </div>

      <el-divider/>

      <h3 class="drawer-title">系统布局配置</h3>

    <div class="drawer-item">
        <span>开启双排侧边栏</span>
        <el-switch v-model="splitMenu" class="drawer-switch" />
      </div>

      <div class="drawer-item">
        <span>开启 Tags-Views</span>
        <el-switch v-model="tagsView" class="drawer-switch" />
      </div>

      <div class="drawer-item">
        <span>固定 Header</span>
        <el-switch v-model="fixedHeader" class="drawer-switch" />
      </div>

      <div class="drawer-item">
        <span>显示 Logo</span>
        <el-switch v-model="sidebarLogo" class="drawer-switch" />
      </div>

      <div class="drawer-item">
        <span>动态标题</span>
        <el-switch v-model="dynamicTitle" class="drawer-switch" />
      </div>

      <el-divider/>

      <div class="flex-row">
        <el-button size="mini" type="primary" icon="el-icon-document-add" @click="saveSetting(false)">保存配置</el-button>
        <el-button size="mini" plain icon="el-icon-refresh" @click="resetSetting">重置配置</el-button>
      </div>
    </div>
  </div>
</template>

<script>
import ThemePicker from '@/components/ThemePicker'
import { ADMIN_THEMES } from '@/utils/admin-theme'

export default {
  components: { ThemePicker },
  data() {
    return {
      themeOptions: ADMIN_THEMES
    };
  },
  computed: {
    theme() { return this.$store.state.settings.theme },
    themeMode() { return this.$store.state.settings.themeMode },
    sideTheme() { return this.$store.state.settings.sideTheme },
    fixedHeader: {
      get() {
        return this.$store.state.settings.fixedHeader
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'fixedHeader',
          value: val
        })
      }
    },
    splitMenu: {
      get() {
        return this.$store.state.settings.splitMenu
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'splitMenu',
          value: val
        })
        if (!val) {
          this.$store.dispatch('app/toggleSideBarHide', false);
          this.$store.commit("SET_SIDEBAR_ROUTERS", this.$store.state.permission.defaultRoutes);
        }
        this.saveSetting(true);
      }
    },
    tagsView: {
      get() {
        return this.$store.state.settings.tagsView
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'tagsView',
          value: val
        })
      }
    },
    sidebarLogo: {
      get() {
        return this.$store.state.settings.sidebarLogo
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'sidebarLogo',
          value: val
        })
      }
    },
    dynamicTitle: {
      get() {
        return this.$store.state.settings.dynamicTitle
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'dynamicTitle',
          value: val
        })
      }
    },
  },
  methods: {
    selectTheme(mode) {
      const item = this.themeOptions.find(option => option.value === mode)
      if (!item) return
      this.$store.dispatch('settings/changeSetting', { key: 'themeMode', value: mode })
      if (mode !== 'custom') {
        this.$store.dispatch('settings/changeSetting', { key: 'theme', value: item.color })
      }
    },
    themeChange(val) {
      this.$store.dispatch('settings/changeSetting', {
        key: 'theme',
        value: val
      })
    },
    handleTheme(val) {
      this.$store.dispatch('settings/changeSetting', {
        key: 'sideTheme',
        value: val
      })
      this.saveSetting(true);
    },
    saveSetting(silent = false) {
      try {
        const setting = {}
        const keys = ['splitMenu', 'topNav', 'tagsView', 'fixedHeader', 'sidebarLogo', 'dynamicTitle', 'sideTheme', 'theme', 'themeMode']
        keys.forEach(key => { setting[key] = this.$store.state.settings[key] })
        this.$cache.local.setJSON('layout-setting', setting)
        if (!silent) this.$message.success('已保存当前浏览器的后台外观')
      } catch (error) {
        this.$message.error('浏览器未能保存配置，请检查存储权限')
      }
    },
    resetSetting() {
      this.$confirm('恢复 Craddl 默认配色及默认布局？仅影响当前浏览器。', '重置后台外观', { type: 'warning', zIndex: 100000 }).then(() => {
        try {
          this.$cache.local.remove('layout-setting')
          window.location.reload()
        } catch (error) {
          this.$message.error('浏览器未能清除配置，请检查存储权限')
        }
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
  .setting-note { color: #6B6E75; font-size: 12px; line-height: 1.7; margin: 10px 0 18px; }
  .flex-row { position: sticky; bottom: -24px; background: #fff; padding: 14px 0; border-top: 1px solid #EBEEF5; z-index: 1; }
  .theme-options { display: grid; gap: 8px; }
  .theme-option { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px; border: 1px solid #DCDFE6; border-radius: 7px; background: #fff; color: #303133; cursor: pointer; text-align: left; }
  .theme-option.selected { border-color: var(--admin-primary); background: var(--admin-accent-soft); }
  .theme-option:focus-visible { outline: 2px solid var(--admin-primary); outline-offset: 2px; }
  .theme-option > .el-icon-check { margin-left: auto; }
  .theme-sample { width: 32px; height: 24px; border-radius: 4px; display: flex; align-items: flex-end; padding: 4px; }
  .theme-sample i { display: block; width: 14px; height: 3px; border: 1px solid rgba(255, 255, 255, .5); }
  .setting-drawer-content {
    .setting-drawer-title {
      margin-bottom: 12px;
      color: rgba(0, 0, 0, .85);
      font-size: 14px;
      line-height: 22px;
      font-weight: bold;
    }

    .setting-drawer-block-checbox {
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-top: 10px;
      margin-bottom: 20px;

      .setting-drawer-block-checbox-item {
        position: relative;
        margin-right: 16px;
        border-radius: 2px;
        cursor: pointer;

        img {
          width: 48px;
          height: 48px;
        }

        .setting-drawer-block-checbox-selectIcon {
          position: absolute;
          top: 0;
          right: 0;
          width: 100%;
          height: 100%;
          padding-top: 15px;
          padding-left: 24px;
          color: var(--admin-accent-text);
          font-weight: 700;
          font-size: 14px;
        }
      }
    }
  }

  .drawer-container {
    padding: 24px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;

    .drawer-title {
      margin-bottom: 12px;
      color: rgba(0, 0, 0, .85);
      font-size: 14px;
      line-height: 22px;
    }

    .drawer-item {
      color: rgba(0, 0, 0, .65);
      font-size: 14px;
      padding: 12px 0;
    }

    .drawer-switch {
      float: right
    }
  }
</style>
