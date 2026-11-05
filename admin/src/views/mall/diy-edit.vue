<template>
  <div class="app-container diy-container" :class="{ 'is-embedded': embedded }">
    <!-- 顶部导航栏 -->
    <div class="diy-header">
      <div class="header-left">
        <span class="title">页面装修 / {{ name || '未命名页面' }}</span>
      </div>
      <div class="header-right">
        <el-button size="small" @click="saveOther">另存为</el-button>
        <el-button size="small" type="primary" icon="el-icon-check" :loading="is_loading" @click="save">保存</el-button>
        <el-button v-if="embedded" size="small" icon="el-icon-close" @click="$emit('close')">关闭</el-button>
      </div>
    </div>

    <!-- 主体区域 -->
    <div class="diy-main" v-loading="is_loading">

      <!-- 左侧：组件库（按分组渲染） -->
      <div class="diy-left">
        <div class="panel-title">组件库</div>
        <div class="component-list">
          <div class="component-group" v-for="g in groupedComponents" :key="g.name">
            <div class="group-header">
              <span class="group-name">{{ g.name }}</span>
              <span class="group-count">{{ g.items.length }}</span>
            </div>
            <div class="group-items">
              <div
                class="component-item"
                v-for="(item, idx) in g.items"
                :key="g.name + '-' + idx"
                @click="handleAddComponent(item)"
              >
                <div class="icon-box">
                  <i class="el-icon-s-grid"></i>
                </div>
                <div class="name">{{ item.display_name }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 中间：手机预览区 -->
      <div class="diy-center" @click="selectIndex = -1">
        <!-- CSS 绘制的手机外壳 -->
        <div class="phone-shell" @click.stop>

          <!-- 模拟顶部状态栏 -->
          <div class="phone-status-bar">
            <div class="time">12:00</div>
            <div class="camera"></div> <!-- 摄像头/刘海 -->
            <div class="status-icons">
              <i class="el-icon-connection"></i> <!-- 信号 -->
              <i class="el-icon-s-opportunity"></i> <!-- 电量 -->
            </div>
          </div>

          <!-- 内容显示区域 -->
          <div class="phone-body scrollbar-hide" :style="page_params.bg_color ? { background: page_params.bg_color } : null">
            <div class="canvas-area">
              <draggable
                v-model="components"
                :options="{ animation: 200, group: 'diy', ghostClass: 'ghost' }"
                style="min-height: 600px; padding-bottom: 20px;"
              >
                <div
                  v-for="(item, index) in components"
                  :key="index"
                  class="preview-item"
                  :class="{ active: selectIndex === index }"
                  @click.stop="handleSelect(index)"
                >
                  <!-- 动态渲染预览组件 (View) -->
                  <component :is="item.name" :data="item.data"></component>

                  <!-- 选中时的操作工具栏 (修复版：悬浮在组件内部) -->
                  <!-- 使用 @click.stop 防止点击按钮时触发取消选中 -->
                  <div class="action-bar" v-if="selectIndex === index" @click.stop>
                    <!-- 左上角：组件名称 -->
                    <div class="component-name">{{ getItemTitle(item.name) }}</div>

                    <!-- 右下角：操作按钮组 -->
                    <div class="btn-group">
                      <div class="btn" @click.stop="moveUp(index)" title="上移" :class="{disabled: index === 0}">
                        <i class="el-icon-top"></i>
                      </div>
                      <div class="btn" @click.stop="moveDown(index)" title="下移"
                           :class="{disabled: index === components.length - 1}">
                        <i class="el-icon-bottom"></i>
                      </div>
                      <div class="btn" @click.stop="handleCopy(index)" title="复制">
                        <i class="el-icon-document-copy"></i>
                      </div>
                      <div class="btn delete" @click.stop="handleDelete(index)" title="删除">
                        <i class="el-icon-delete"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </draggable>

              <!-- 空状态提示 -->
              <div v-if="components.length === 0" class="empty-state">
                <i class="el-icon-touch-phone"></i>
                <p>点击左侧组件开始装修</p>
              </div>
            </div>
          </div>

          <!-- 模拟底部 Home 条 -->
          <div class="home-indicator"></div>
        </div>
      </div>

      <!-- 右侧：属性配置区 -->
      <div class="diy-right">

        <!-- 1. 页面全局设置 (未选中组件时显示) -->
        <div v-if="selectIndex === -1" class="config-panel">
          <div class="panel-title">页面设置</div>
          <div class="panel-content">
            <el-form label-width="80px" size="small" label-position="top">
              <el-form-item label="页面名称">
                <el-input v-model="name" placeholder="请输入页面名称"></el-input>
              </el-form-item>
              <el-form-item label="标题栏背景色">
                <div style="display: flex; align-items: center;">
                  <el-color-picker v-model="title_color"></el-color-picker>
                  <span style="margin-left: 10px; color: #999;">{{ title_color }}</span>
                </div>
              </el-form-item>

              <el-form-item label="页面背景色">
                <div class="page-bg-presets">
                  <span
                    v-for="p in pageBgPresets"
                    :key="p.name"
                    class="bg-swatch"
                    :class="{ active: (page_params.bg_color || '') === p.color, empty: !p.color }"
                    :style="p.color ? { background: p.color } : null"
                    :title="p.name"
                    @click="page_params.bg_color = p.color"
                  >{{ p.color ? '' : '默认' }}</span>
                  <el-color-picker v-model="page_params.bg_color" size="small" title="自己选颜色"></el-color-picker>
                </div>
                <div style="font-size:12px;color:#909399;line-height:1.6;margin-top:4px;">
                  组件之间的空隙和内容不满一屏的地方显示这个颜色;选「默认」保持原来的底色。
                </div>
              </el-form-item>

              <el-divider content-position="left">页面行为</el-divider>

              <el-form-item label="单屏模式">
                <el-switch v-model="page_params.single_screen" :active-value="1" :inactive-value="0"></el-switch>
                <div style="font-size:12px;color:#909399;line-height:1.6;margin-top:2px;">
                  开启后隐藏底部「已经到底」提示并去掉底部留白，适合内容只有一屏、不需要下拉的页面。
                </div>
              </el-form-item>

              <!-- 新增：页面专属配置 (悬浮按钮) -->
              <el-divider content-position="left">悬浮按钮设置</el-divider>
              
              <el-form-item label="开启悬浮客服">
                <el-switch v-model="page_params.show_contact" :active-value="1" :inactive-value="0"></el-switch>
              </el-form-item>
              
              <el-form-item label="客服悬浮图标" v-if="page_params.show_contact == 1">
                <single-image-selector size="100*100" v-model="page_params.contact_icon"></single-image-selector>
              </el-form-item>
              
              <el-form-item label="开启一键拨号">
                <el-switch v-model="page_params.show_phone" :active-value="1" :inactive-value="0"></el-switch>
              </el-form-item>
              
              <el-form-item label="拨号悬浮图标" v-if="page_params.show_phone == 1">
                <single-image-selector size="100*100" v-model="page_params.phone_icon"></single-image-selector>
              </el-form-item>
              
              <el-form-item label="联系电话" v-if="page_params.show_phone == 1">
                <el-input v-model="page_params.tel" placeholder="请输入客服电话"></el-input>
              </el-form-item>

              <el-form-item label="开启一键导航">
                <el-switch v-model="page_params.show_nav" :active-value="1" :inactive-value="0"></el-switch>
              </el-form-item>
              
              <el-form-item label="导航悬浮图标" v-if="page_params.show_nav == 1">
                <single-image-selector size="100*100" v-model="page_params.nav_icon"></single-image-selector>
              </el-form-item>
              
              <el-form-item label="导航地址名称" v-if="page_params.show_nav == 1">
                <el-input v-model="page_params.nav_address" placeholder="如：XX总店"></el-input>
              </el-form-item>
              
              <el-form-item label="导航经纬度" v-if="page_params.show_nav == 1">
                <el-input v-model="page_params.nav_location" placeholder="如：39.904,116.407 (纬度,经度)"></el-input>
                <div style="font-size: 12px; color: #999; line-height: 1.2; margin-top: 5px;">请用逗号分隔纬度和经度。建议使用腾讯地图坐标系。</div>
              </el-form-item>

            </el-form>
          </div>
        </div>

        <!-- 2. 组件属性设置 (选中组件时显示) -->
        <div v-else class="config-panel">
          <div class="panel-title">{{ getItemTitle(components[selectIndex].name) }} 设置</div>
          <div class="panel-content">
            <!-- 核心：动态加载 Config 组件 -->
            <component
              :is="components[selectIndex].name + '-config'"
              v-model="components[selectIndex].data"
            ></component>

            <!-- 调试提示 -->
            <div v-if="!hasConfigComponent(components[selectIndex].name)" class="no-config-tip">
              未找到配置组件: {{ components[selectIndex].name }}-config <br>
              请确认文件是否存在并已注册
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- 另存为弹窗 -->
    <el-dialog title="另存为" :visible.sync="showSaveOther" width="400px">
      <el-form size="small">
        <el-form-item label="页面名称">
          <el-input v-model="other_name" placeholder="请输入新名称"></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="showSaveOther = false" size="small">取 消</el-button>
        <el-button type="primary" @click="saveOtherConfirm" size="small">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import { PAGE_BG_PRESETS } from '@/components/diy/style-kit/presets'

/* ================== 预览组件 (View) ================== */
import Blank from '@/components/diy/Blank'
import SearchBar from '@/components/diy/SearchBar'
import Banner from '@/components/diy/Banner'
import TitleBar from '@/components/diy/TitleBar'
import Navbar from '@/components/diy/Navbar'
import GoodsGroup from '@/components/diy/GoodsGroup'
import ImgGroup from '@/components/diy/ImgGroup'
import Notice from '@/components/diy/Notice'
import VideoBlock from '@/components/diy/VideoBlock'
import CustomGoods from '@/components/diy/CustomGoods'
import PtBlock from '@/components/diy/PtBlock'
import HotImage from '@/components/diy/HotImage'
import ArticleList from '@/components/diy/ArticleList'
import DynamicGoods from '@/components/diy/DynamicGoods'
import TabGoods from '@/components/diy/TabGoods'

/* ================== 配置组件 (Config) ================== */
import BlankConfig from '@/components/diy/BlankConfig'
import SearchBarConfig from '@/components/diy/SearchBarConfig'
import BannerConfig from '@/components/diy/BannerConfig'
import TitleBarConfig from '@/components/diy/TitleBarConfig'
import NavbarConfig from '@/components/diy/NavbarConfig'
import GoodsGroupConfig from '@/components/diy/GoodsGroupConfig'
import ImgGroupConfig from '@/components/diy/ImgGroupConfig'
import NoticeConfig from '@/components/diy/NoticeConfig'
import VideoBlockConfig from '@/components/diy/VideoBlockConfig'
import CustomGoodsConfig from '@/components/diy/CustomGoodsConfig'
import PtBlockConfig from '@/components/diy/PtBlockConfig'
import HotImageConfig from '@/components/diy/HotImageConfig'
import ArticleListConfig from '@/components/diy/ArticleListConfig'
import DynamicGoodsConfig from '@/components/diy/DynamicGoodsConfig'
import TabGoodsConfig from '@/components/diy/TabGoodsConfig'
// 风格组件(带配色预设的成品版块)
import BrandSearch from '@/components/diy/BrandSearch'
import BrandSearchConfig from '@/components/diy/BrandSearchConfig'
import HeroCard from '@/components/diy/HeroCard'
import HeroCardConfig from '@/components/diy/HeroCardConfig'
import FeatureStrip from '@/components/diy/FeatureStrip'
import FeatureStripConfig from '@/components/diy/FeatureStripConfig'
import CatGrid from '@/components/diy/CatGrid'
import CatGridConfig from '@/components/diy/CatGridConfig'
import SectionTitle from '@/components/diy/SectionTitle'
import SectionTitleConfig from '@/components/diy/SectionTitleConfig'
import PromoCard from '@/components/diy/PromoCard'
import PromoCardConfig from '@/components/diy/PromoCardConfig'
import VideoCard from '@/components/diy/VideoCard'
import VideoCardConfig from '@/components/diy/VideoCardConfig'
import ContactCard from '@/components/diy/ContactCard'
import ContactCardConfig from '@/components/diy/ContactCardConfig'
import HeaderBanner from '@/components/diy/HeaderBanner'
import HeaderBannerConfig from '@/components/diy/HeaderBannerConfig'

// [新增] 积分商城与线下换购 DIY 预览与配置组件
import IntegralGroup from '@/components/diy/IntegralGroup'
import IntegralGroupConfig from '@/components/diy/IntegralGroupConfig'
import ExchangeGroup from '@/components/diy/ExchangeGroup'
import ExchangeGroupConfig from '@/components/diy/ExchangeGroupConfig'
import LuckyGroup from '@/components/diy/LuckyGroup'
import LuckyGroupConfig from '@/components/diy/LuckyGroupConfig'
// 砍价插件：DIY 砍价专区
import BargainGroup from '@/components/diy/BargainGroup'
import BargainGroupConfig from '@/components/diy/BargainGroupConfig'

// 艾灸到家：DIY 门店列表 / 热门服务 / 新晋技师 组件
import MassageShopList from '@/components/diy/MassageShopList'
import MassageShopListConfig from '@/components/diy/MassageShopListConfig'
import MassageHotService from '@/components/diy/MassageHotService'
import MassageHotServiceConfig from '@/components/diy/MassageHotServiceConfig'
import MassageNewTherapist from '@/components/diy/MassageNewTherapist'
import MassageNewTherapistConfig from '@/components/diy/MassageNewTherapistConfig'

// 多商户：DIY 精选商家（后台勾选要展示的商户橱窗）
import MchSelectShops from '@/components/diy/MchSelectShops'
import MchSelectShopsConfig from '@/components/diy/MchSelectShopsConfig'

export default {
  name: 'DiyEdit',
  components: {
    draggable,

    // 预览组件
    Blank, SearchBar, Banner, TitleBar, Navbar, GoodsGroup, ImgGroup, Notice,
    VideoBlock, CustomGoods, PtBlock, HotImage, ArticleList, DynamicGoods, TabGoods,
    HeaderBanner, IntegralGroup, ExchangeGroup, LuckyGroup,
    BrandSearch, HeroCard, FeatureStrip, CatGrid, SectionTitle, PromoCard, VideoCard, ContactCard,
    'massage-shop-list': MassageShopList,
    'massage-hot-service': MassageHotService,
    'massage-new-therapist': MassageNewTherapist,
    'mch-select-shops': MchSelectShops,
    'bargain-group': BargainGroup,
    // 配置组件
    'blank-config': BlankConfig,
    'search-bar-config': SearchBarConfig,
    'banner-config': BannerConfig,
    'title-bar-config': TitleBarConfig,
    'navbar-config': NavbarConfig,
    'goods-group-config': GoodsGroupConfig,
    'img-group-config': ImgGroupConfig,
    'notice-config': NoticeConfig,
    'video-block-config': VideoBlockConfig,
    'custom-goods-config': CustomGoodsConfig,
    'pt-block-config': PtBlockConfig,
    'hot-image-config': HotImageConfig,
    'article-list-config': ArticleListConfig,
    'dynamic-goods-config': DynamicGoodsConfig,
    'tab-goods-config': TabGoodsConfig,
    'brand-search-config': BrandSearchConfig,
    'hero-card-config': HeroCardConfig,
    'feature-strip-config': FeatureStripConfig,
    'cat-grid-config': CatGridConfig,
    'section-title-config': SectionTitleConfig,
    'promo-card-config': PromoCardConfig,
    'video-card-config': VideoCardConfig,
    'contact-card-config': ContactCardConfig,
    'header-banner-config': HeaderBannerConfig,
    'integral-group-config': IntegralGroupConfig,
    'exchange-group-config': ExchangeGroupConfig,
    'lucky-group-config': LuckyGroupConfig,
    'bargain-group-config': BargainGroupConfig,
    'massage-shop-list-config': MassageShopListConfig,
    'massage-hot-service-config': MassageHotServiceConfig,
    'massage-new-therapist-config': MassageNewTherapistConfig,
    'mch-select-shops-config': MchSelectShopsConfig,

  },
  props: {
    // 抽屉(embedded)模式下由父组件传入页面 id；独立页面模式则从 $route.query.id 取
    pageId: { type: [String, Number], default: null },
    // 是否作为 diy.vue 内嵌抽屉使用（true 时走 prop + emit，不操作路由）
    embedded: { type: Boolean, default: false },
  },
  data() {
    return {
      id: '',
      name: '',
      title_color: '#ffffff',
      is_loading: false,
      showSaveOther: false,
      other_name: '',

      page_params: {
        bg_color: '',
        single_screen: 0,
        show_contact: 0,
        contact_icon: '',
        show_phone: 0,
        phone_icon: '',
        tel: '',
        show_nav: 0,
        nav_icon: '',
        nav_address: '',
        nav_location: ''
      },

      pageBgPresets: PAGE_BG_PRESETS,

      // 左侧组件库列表（本地定义，图标使用 Element 图标类名）
      option_list: [],
      components: [],  // 中间画布组件数据
      selectIndex: -1, // 当前选中的组件索引
    }
  },
  created() {
    this.getComponents()
    // 抽屉模式取 prop.pageId；独立页面模式取路由 query.id
    const id = this.embedded
      ? this.pageId
      : (this.$route && this.$route.query ? this.$route.query.id : null)
    if (id) {
      this.id = id
      this.getPage()
    }
  },
  computed: {
    // 按 group 字段聚合 + 保持后端返回顺序（内置在前、插件按注册顺序追加）
    // 没声明 group 的老组件兜底为"其他扩展"
    groupedComponents() {
      const groups = []
      const indexMap = {}
      const fallback = '其他扩展'
      for (const item of this.option_list || []) {
        const g = item.group || fallback
        if (!(g in indexMap)) {
          indexMap[g] = groups.length
          groups.push({ name: g, items: [] })
        }
        groups[indexMap[g]].items.push(item)
      }
      return groups
    },
  },
  methods: {
    getComponents() {
      // 模拟组件列表，实际请使用你的接口
      this.$request({url: '/mall/mall/components'}).then((res) => {
        if (res.code == 0) {
          this.option_list = res.data.list
        }
      })
    },
    getPage() {
      this.is_loading = true
      this.$request({
        url: '/mall/mall/diy-edit',
        data: {id: this.id}
      }).then(res => {
        this.is_loading = false
        if (res.code == 0) {
          this.name = res.data.page.name
          this.title_color = res.data.page.title_color
          if (res.data.page.page_params) {
            this.page_params = Object.assign(this.page_params, res.data.page.page_params)
          }
          this.components = res.data.page.data || []
        }
      })
    },
    handleAddComponent(item) {
      const newComponent = {
        name: item.name,
        data: null
      }
      if (this.selectIndex !== -1) {
        this.components.splice(this.selectIndex + 1, 0, newComponent)
        this.selectIndex += 1
      } else {
        this.components.push(newComponent)
        this.selectIndex = this.components.length - 1
      }
    },
    handleSelect(index) {
      this.selectIndex = index
    },
    moveUp(index) {
      if (index > 0) {
        const temp = this.components[index]
        this.$set(this.components, index, this.components[index - 1])
        this.$set(this.components, index - 1, temp)
        this.selectIndex = index - 1
      }
    },
    moveDown(index) {
      if (index < this.components.length - 1) {
        const temp = this.components[index]
        this.$set(this.components, index, this.components[index + 1])
        this.$set(this.components, index + 1, temp)
        this.selectIndex = index + 1
      }
    },
    handleCopy(index) {
      const item = JSON.parse(JSON.stringify(this.components[index]))
      this.components.splice(index + 1, 0, item)
      this.selectIndex = index + 1
    },
    handleDelete(index) {
      this.components.splice(index, 1)
      this.selectIndex = -1
    },
    save() {
      if (!this.name) {
        this.$message.warning('请输入页面名称')
        return
      }
      this.is_loading = true

      const isNew = !this.id
      const postData = {
        id: this.id,
        name: this.name,
        title_color: this.title_color,
        page_params: JSON.stringify(this.page_params),
        data: JSON.stringify(this.components),
      }

      this.$request({
        url: '/mall/mall/diy-edit',
        method: 'post',
        data: postData
      }).then((res) => {
        this.is_loading = false
        if (res.code == 0) {
          this.$message.success(res.msg)
          // 关键：新建保存成功后，把后端回传的 id 接住，避免下一次保存又走新建分支重复 INSERT
          if (isNew && res.data && res.data.id) {
            this.id = res.data.id
            // 同步 URL 让用户刷新也不丢上下文（仅独立页面模式；抽屉模式不操作路由）
            if (!this.embedded && this.$route && this.$router) {
              this.$router.replace({ query: { ...this.$route.query, id: this.id } }).catch(() => {})
            }
          }
          // 通知父组件（抽屉模式）刷新列表
          this.$emit('saved', this.id)
        }
      })
    },
    saveOther() {
      this.showSaveOther = true
      this.other_name = this.name + ' 副本'
    },
    saveOtherConfirm() {
      if (!this.other_name) return this.$message.warning('请输入名称')
      this.is_loading = true
      this.$request({
        url: '/mall/mall/diy-edit',
        method: 'post',
        data: {
          // 不传 id 走新建路径
          name: this.other_name,
          title_color: this.title_color,
          page_params: JSON.stringify(this.page_params),
          data: JSON.stringify(this.components),
        }
      }).then((res) => {
        this.is_loading = false
        this.showSaveOther = false
        if (res.code == 0) {
          this.$message.success('另存成功')
          // 切换上下文到新创建的副本：更新 id + name + URL，避免用户后续"保存"误改原页
          if (res.data && res.data.id) {
            this.id = res.data.id
            this.name = this.other_name
            if (!this.embedded && this.$route && this.$router) {
              this.$router.replace({ query: { ...this.$route.query, id: this.id } }).catch(() => {})
            }
          }
          // 通知父组件（抽屉模式）刷新列表
          this.$emit('saved', this.id)
        }
      })
    },
    getItemTitle(name) {
      const item = this.option_list.find(i => i.name === name)
      return item ? item.display_name : name
    },
    hasConfigComponent(name) {
      return this.$options.components[name + '-config']
    }
  }
}
</script>

<style lang="scss" scoped>
/* 整体布局 */
.diy-container {
  /* 50px navbar + 动态 tagsView 高度，跟随尺寸档位自适应 */
  height: calc(100vh - 50px - var(--tags-view-height, 34px));
  display: flex;
  flex-direction: column;
  padding: 0 !important;
  background-color: #f0f2f5;
  overflow: hidden;
}

/* 抽屉(embedded)模式：占满抽屉 body，不再扣 navbar/tagsView */
.diy-container.is-embedded {
  height: 100%;
}

/* Header */
.diy-header {
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #e0e0e0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  flex-shrink: 0;
  z-index: 100;

  .title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
  }
}

.diy-main {
  flex: 1;
  display: flex;
  overflow: hidden;
}

/* 左侧组件库 */
.diy-left {
  width: 280px;
  background: #fff;
  border-right: 1px solid #e0e0e0;
  display: flex;
  flex-direction: column;
  z-index: 10;

  .panel-title {
    padding: 15px 20px;
    font-weight: bold;
    font-size: 14px;
    background: #f9f9fa;
    border-bottom: 1px solid #eee;
  }

  .component-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px 12px 20px;
  }

  /* 分组容器 */
  .component-group {
    margin-bottom: 16px;
    &:last-child { margin-bottom: 0; }
  }

  /* 分组标题 */
  .group-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 4px 10px;
    .group-name {
      font-size: 12px;
      font-weight: 600;
      color: #909399;
      letter-spacing: 0.5px;
      position: relative;
      padding-left: 8px;
      &::before {
        content: '';
        position: absolute;
        left: 0; top: 50%;
        transform: translateY(-50%);
        width: 3px; height: 12px;
        background: var(--admin-accent-text);
        border-radius: 2px;
      }
    }
    .group-count {
      font-size: 11px;
      color: #c0c4cc;
      background: #f4f6f8;
      padding: 0 6px;
      border-radius: 8px;
      line-height: 16px;
      height: 16px;
    }
  }

  .group-items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;

    .component-item {
      width: calc(50% - 4px);
      background: #f4f6f8;
      border: 1px solid transparent;
      border-radius: 6px;
      padding: 14px 5px;
      display: flex;
      flex-direction: column;
      align-items: center;
      cursor: pointer;
      transition: all 0.2s;

      &:hover {
        background: var(--admin-accent-soft);
        border-color: var(--admin-accent-text);
        color: var(--admin-accent-text);
        box-shadow: 0 2px 6px rgba(var(--admin-accent-rgb), 0.1);
      }

      .icon-box {
        width: 30px;
        height: 30px;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        justify-content: center;

        i {
          font-size: 22px;
          color: #606266;
        }
      }

      .name {
        font-size: 12px;
      }
    }
  }
}

/* 中间：手机预览 (CSS 绘制手机) */
.diy-center {
  flex: 1;
  background: #f0f2f5;
  display: flex;
  justify-content: center;
  padding: 40px 0;
  overflow-y: auto;

  /* 手机外壳 (统一风格) */
  .phone-shell {
    width: 375px;
    height: 760px;
    background: #fff;
    border-radius: 40px;
    border: 10px solid #2c3e50; /* 实体边框 */
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    position: relative;
    display: flex;
    flex-direction: column;
    margin-bottom: 50px;
    overflow: hidden;

    /* 核心修复：缩小显示比例，适配屏幕 */
    transform: scale(0.85);
    transform-origin: top center;
    margin-bottom: -100px !important; /* 抵消缩放底部的空白 */

    /* 顶部刘海 (模拟) */
    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 150px;
      height: 30px;
      background: #2c3e50;
      border-bottom-left-radius: 16px;
      border-bottom-right-radius: 16px;
      z-index: 100;
    }

    /* 顶部状态栏 (沉浸式) */
    .phone-status-bar {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 44px;
      padding: 0 20px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end; /* 底部对齐 */
      padding-bottom: 8px;
      z-index: 90;
      color: #333;
      background: transparent; /* 透明背景 */
      pointer-events: none; /* 让点击穿透 */

      .time { font-weight: 600; font-size: 14px; width: 50px; }
      .camera { display: none; } /* 用伪元素模拟了刘海，这里隐藏旧的 */
      
      .status-icons {
        display: flex; justify-content: flex-end; gap: 6px; width: 50px;
        i { font-size: 16px; font-weight: bold; }
      }
    }

    /* 内容区域 */
    .phone-body {
      flex: 1;
      background: #F5F7FA;
      position: relative;
      overflow-y: auto;
      
      /* 隐藏滚动条 */
      &::-webkit-scrollbar { display: none; }
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    /* 底部 Home Indicator (悬浮) */
    .home-indicator {
      position: absolute;
      bottom: 8px;
      left: 0;
      width: 100%;
      height: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 100;
      pointer-events: none;

      &::after {
        content: '';
        width: 130px;
        height: 5px;
        background: #000;
        border-radius: 3px;
        opacity: 0.2;
      }
    }
  }

  .canvas-area {
    min-height: 100%;
    /* 给状态栏 + 刘海留出 44rpx 空间，否则第一个组件顶部会被状态栏盖住一截 */
    padding-top: 44px;
    box-sizing: border-box;
  }

  .preview-item {
    position: relative;
    cursor: pointer;
    outline: 1px solid transparent;
    outline-offset: -1px;

    transition: all 0.2s;

    &.active {
      outline-color: var(--admin-accent-text);
      z-index: 10;
    }

    &:hover:not(.active) {
      outline-style: dashed;
      outline-color: var(--admin-accent-text);
    }

    /* 遮罩层，防止鼠标直接点到组件内部的链接 */
    &::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1;
    }

    /* ==============================================
       核心修复：操作栏样式
       改为在组件边框内部显示，避免被父级 overflow:hidden 裁剪
       ============================================== */
    .action-bar {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 100;
      pointer-events: none; /* 让鼠标事件穿透，除非点到具体的按钮 */

      /* 组件名称标签：左上角 */
      .component-name {
        position: absolute;
        top: 0;
        left: 0;
        background: var(--admin-primary);
        color: var(--admin-on-primary);
        padding: 3px 8px;
        font-size: 12px;
        border-bottom-right-radius: 4px;
        pointer-events: auto;
        box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.2);
      }

      /* 按钮组：右下角，横向排列 */
      .btn-group {
        position: absolute;
        bottom: 0;
        right: 0;
        display: flex;
        flex-direction: row; /* 横向 */
        pointer-events: auto; /* 恢复按钮点击 */
        background: var(--admin-primary); /* 给个背景色统一 */
        border-top-left-radius: 8px;

        .btn {
          width: 32px;
          height: 32px;
          background: transparent; /* 背景透明，利用父级颜色 */
          color: var(--admin-on-primary);
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s;
          border-right: 1px solid rgba(255, 255, 255, 0.2);

          &:last-child {
            border-right: none;
          }

          &:hover {
            background: rgba(0, 0, 0, 0.1);
          }

          &.delete:hover {
            background: #F56C6C;
            color: #fff;
          }

          &.disabled {
            cursor: not-allowed;
            color: var(--admin-on-primary);
            opacity: .5;
          }
        }
      }
    }
  }

  .empty-state {
    padding: 100px 0;
    text-align: center;
    color: #909399;

    i {
      font-size: 48px;
      margin-bottom: 10px;
    }
  }
}

/* 右侧：配置面板 */
.diy-right {
  width: 380px;
  background: #fff;
  border-left: 1px solid #e0e0e0;
  display: flex;
  flex-direction: column;
  z-index: 10;

  .config-panel {
    display: flex;
    flex-direction: column;
    height: 100%;

    .panel-title {
      height: 50px;
      line-height: 50px;
      padding: 0 20px;
      font-weight: bold;
      background: #f9f9fa;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    .panel-content {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }
  }

  .no-config-tip {
    padding: 40px;
    text-align: center;
    color: #F56C6C;
    background: #fef0f0;
    border-radius: 4px;
    font-size: 13px;
    line-height: 1.6;
  }
}

/* 拖拽占位 */
.ghost {
  opacity: 0.5;
  background: var(--admin-accent-soft);
  border: 1px dashed var(--admin-accent-text);
}

/* 页面设置:页面背景色预设色块 */
.page-bg-presets {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;

  .bg-swatch {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    border: 1px solid #dcdfe6;
    box-sizing: border-box;
    cursor: pointer;
    font-size: 10px;
    line-height: 26px;
    text-align: center;
    color: #909399;

    &.empty {
      width: 36px;
      background: #fff;
    }

    &.active {
      border-color: var(--admin-accent-text);
      box-shadow: 0 0 0 2px rgba(var(--admin-accent-rgb), 0.35);
    }
  }
}
</style>
