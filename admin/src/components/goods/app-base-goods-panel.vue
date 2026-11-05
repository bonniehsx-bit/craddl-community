<template>
  <div class="app-base-goods-panel" v-loading="is_loading">
    <el-form size="mini" ref="form" :model="form" label-width="150px" class="goods-form">
      <el-tabs v-model="activeName" class="custom-tabs"
               :style="isDrawer ? 'position: sticky; top: -20px; z-index: 10;' : ''">

        <el-tab-pane label="基础信息" name="basic" v-if="!hiddenTabs.includes('basic')">
          <tab-basic :form="form" :hidden-fields="hiddenFields" :require-mch="requireMch"></tab-basic>
        </el-tab-pane>

        <!-- 专门留给各个插件使用的插槽！把 form 暴露出去 -->
        <slot name="extra-tabs" :form="form"></slot>

        <el-tab-pane label="价格配置" name="sku" v-if="!hiddenTabs.includes('sku')">
          <tab-sku :form="form" :disable-multi-spec="disableMultiSpec"></tab-sku>
        </el-tab-pane>

        <el-tab-pane label="开关" name="status_setting" v-if="!hiddenTabs.includes('status')">
          <tab-status :form="form"></tab-status>
        </el-tab-pane>

        <el-tab-pane label="商品属性" name="property" v-if="!hiddenTabs.includes('property')">
          <el-form-item label="选择属性" v-if="form.property">
            <el-button type="text" v-if="goods_property.length === 0">暂未设置商品属性</el-button>
            <el-checkbox-group v-model="form.property">
              <el-checkbox v-if="goods_property.length > 0" v-for="item in goods_property" :label="item" :key="item">
              </el-checkbox>
            </el-checkbox-group>
          </el-form-item>
        </el-tab-pane>

        <el-tab-pane label="商品详情" name="detail" v-if="!hiddenTabs.includes('detail')">
          <el-form-item label="商品详情" class="rich-text-item">
            <rich-text v-model="form.detail"></rich-text>
          </el-form-item>
        </el-tab-pane>

        <el-tab-pane label="积分营销" name="integral" v-if="!hiddenTabs.includes('integral')">
          <tab-integral :form="form"></tab-integral>
        </el-tab-pane>

        <el-tab-pane label="会员价" name="member_price" v-if="!hiddenTabs.includes('member_price')">
          <tab-member-price :form="form" :level-list="level_list"></tab-member-price>
        </el-tab-pane>

        <el-tab-pane label="权限设置" name="permission" v-if="!hiddenTabs.includes('permission')">
          <tab-permission :form="form" :level-list="level_list"></tab-permission>
        </el-tab-pane>

        <el-tab-pane label="运费设置" name="express" v-if="!hiddenTabs.includes('express')">
          <tab-express :form="form" :freight-list="freight_list"></tab-express>
        </el-tab-pane>

        <el-tab-pane v-for="plugin in plugins_list" :key="plugin.key" :label="plugin.label" :name="plugin.key">
          <component :is="plugin.component_name" v-model="form.plugins_data[plugin.key]" :goods-id="form.id">
          </component>
        </el-tab-pane>

      </el-tabs>
    </el-form>

    <div :class="['panel-footer', isDrawer ? 'drawer-footer' : 'page-footer']">
      <el-button size="small" @click="$emit('cancel')">取 消</el-button>
      <el-button size="small" type="primary" @click="submit" :loading="is_submiting">保存商品</el-button>
    </div>
  </div>
</template>

<script>
// === 1. 组件引入 ===
import TabBasic from './TabBasic'
import TabNumber from './TabNumber'
import TabStatus from './TabStatus'
import TabIntegral from './TabIntegral'
import TabMemberPrice from './TabMemberPrice'
import TabPermission from './TabPermission'
import TabExpress from './TabExpress'
import TabSku from './TabSku'
import RichText from '@/components/common/RichText'
import PluginDistributionGoodsSetting from '@/components/goods/plugins/plugin-distribution-goods-setting.vue'
import PluginMchRecruitGoodsSetting from '@/components/goods/plugins/plugin-mch-recruit-goods-setting.vue'
// PluginMchGoodsSetting 已删除：mch_picker 现在内置在 TabBasic 顶部，
// 通过 form.mch_id 直接绑定，不再走 plugins_data.mch 间接层。

const defaultForm = () => ({
  id: '',
  name: '',
  short_name: '', // 商品简称(客户定制:仅订单导出用)
  mch_id: 0,
  factory: '',
  subtitle: '',
  service_tags: [], // 详情页服务标签:标签 ID 数组
  no: '',
  cover_pic: '',
  pic_list: [],
  detail: '',
  unit: '件',
  weight: '',
  sort: 0,
  cat: {id: '', name: ''},
  cat_ids: [],
  property: [],
  origin_price: '',
  price: '',
  cost_price: '',
  purchase_price: '',
  concession_rate: '',
  sales: 0,
  virtual_sales: 0,
  stock: 0,
  total_stock: 0,
  warning_stock: 0,
  limit_num: 0,
  is_attr: 0,
  specs_data: {spec_groups: [], sku_list: []},
  attr_list: [],
  freight_id: '0',
  freight_price: '0.00',
  express_type: 0,
  freight: null,
  is_integral: 0,
  use_integral_type: 0,
  use_integral: 0,
  integral_price: 0,
  send_integral: '',
  status: 1,
  is_recommend: 0,
  is_refund: 1,
  is_purchase: 0,
  is_limit: 0,
  is_negotiable: 0,
  is_special_price: 0,
  is_member_price: 0,
  is_auto_complete: 0,
  is_check: 0,
  level_price: [],
  permission: {explode_is_level: 0, explode_level_ids: [], buy_is_level: 0, buy_level_ids: []},
  plugins_data: {},
  share_title: '',
  type: '' // 核心扩展点！用于强制指定底层类型
});

export default {
  name: 'AppBaseGoodsPanel',
  components: {
    TabBasic, TabNumber, TabStatus, TabIntegral, TabMemberPrice, TabPermission, TabExpress, TabSku, RichText,
    'plugin-distribution-goods-setting': PluginDistributionGoodsSetting,
    'plugin-mch-recruit-goods-setting': PluginMchRecruitGoodsSetting,
  },
  props: {
    goodsId: {
      type: [String, Number],
      default: ''
    },
    // 「发布相似商品」:以这个商品为模板新建(后端下发去掉了 id / 货号 / 销量、默认下架的拷贝),保存走新建
    similarFrom: {
      type: [String, Number],
      default: ''
    },
    hiddenTabs: {
      type: Array,
      default: () => []
    },
    hiddenFields: {
      type: Array,
      default: () => []
    },
    apiUrl: {
      type: String,
      default: '/mall/goods/edit' // 默认为主商城接口
    },
    goodsType: {
      type: String,
      default: '' // 如果设为 bs，则强制携带覆盖后端
    },
    isDrawer: {
      type: Boolean,
      default: false
    },
    disableMultiSpec: {
      type: Boolean,
      default: false
    },
    requireMch: {
      type: Boolean,
      default: false
    },
    initialLoad: {
      type: Boolean,
      default: true // 是否在 mounted 后自动触发 getGoods
    }
  },
  data() {
    return {
      is_loading: false,
      is_submiting: false,
      activeName: 'basic',

      freight_list: [],
      level_list: [],
      goods_property: [],
      plugins_list: [],

      form: defaultForm()
    }
  },
  watch: {
    goodsId(newVal, oldVal) {
      if (newVal !== oldVal) {
        this.refresh();
      }
    },
    similarFrom(newVal, oldVal) {
      if (newVal !== oldVal) {
        this.refresh();
      }
    }
  },
  created() {
    this.getGoodsProperty();
    this.getFreight();
    this.getLevelList();
  },
  mounted() {
    if (this.initialLoad) {
      this.refresh();
    }
  },
  methods: {
    refresh() {
      this.activeName = 'basic';
      if (this.goodsId) {
        this.form.id = this.goodsId;
        this.getGoods();
      } else if (this.similarFrom) {
        this.form = defaultForm();
        this.getGoods();
      } else {
        this.form = defaultForm();
        if (this.goodsType) this.form.type = this.goodsType;
        this.getGoods(); // 新建商品也要请求后端接口，拿默认插件配置
      }
    },
    getGoods() {
      this.is_loading = true;
      this.$request({
        url: this.apiUrl, // 【动态灵活的请求接口】
        data: this.similarFrom && !this.form.id ? {id: this.similarFrom, similar: 1} : {id: this.form.id || 0}
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          let backendPluginList = res.data.info.plugins_data || [];
          delete res.data.info.plugins_data;

          this.form = Object.assign({}, defaultForm(), res.data.info);
          if (this.goodsType) this.form.type = this.goodsType;

          // 根据 sort 升序排序外接模块 (过滤掉明确没有自带前端组件的后端下发数据，这种数据交由具体插槽接管)
          this.plugins_list = backendPluginList
            .filter(p => p.component_name && !this.hiddenTabs.includes(p.key))
            .map(p => ({
              key: p.key, label: p.label, component_name: p.component_name, sort: p.sort || 100
            })).sort((a, b) => a.sort - b.sort);

          this.$set(this.form, 'plugins_data', {});
          backendPluginList.forEach(p => {
            this.$set(this.form.plugins_data, p.key, p.data);
          });

          if (!this.form.specs_data) this.form.specs_data = {spec_groups: [], sku_list: []};
          if (!this.form.property) this.form.property = [];
        } else {
          this.$message.error(res.msg);
          this.$emit('cancel'); // 如果加载失败，通知外层阻断
        }
      }).catch(() => {
        this.is_loading = false;
      })
    },
    submit() {
      // 强制商户校验：mch-picker 在 TabBasic 顶部，要求必须选具体商户（不可为平台自营）
      if (this.requireMch && (!this.form.mch_id || this.form.mch_id <= 0)) {
        this.$message.error('该商品必须归属至具体门店，请在"基础信息"顶部选择「所属商家」');
        this.activeName = 'basic';
        return;
      }

      // 验证通用参数
      if (!this.hiddenTabs.includes('basic') && !this.hiddenFields.includes('cats') && (!this.form.cat || !this.form.cat.id)) {
        this.$message.error('请选择分类');
        this.activeName = 'basic';
        return;
      }
      if (!this.hiddenTabs.includes('express') && this.form.express_type === 2) {
        if (!this.form.freight_id || this.form.freight_id === 0) {
          this.$message.error('请选择运费模板');
          this.activeName = 'express';
          return;
        }
      }

      // 如果内部逻辑无缝，向上抛允许前置校验
      this.$emit('before-submit', this.form, (isOk) => {
        if (isOk !== false) {
          this.doRequestSave();
        }
      });
      // 默认只要没绑定拦断回调，那就直接保存
      if (!this.$listeners['before-submit']) {
        this.doRequestSave();
      }
    },
    doRequestSave() {
      // 强制防呆保底：如果是已被锁定单规格的插件业务（禁用了 SKU 生成器）
      // 则强行将顶部外露的表单基础值同步到底层真正的单级 sku_list[0] 中，防止数据断层
      if (this.disableMultiSpec && this.form.specs_data && this.form.specs_data.sku_list && this.form.specs_data.sku_list.length > 0) {
        this.form.specs_data.sku_list[0].price = this.form.price;
        this.form.specs_data.sku_list[0].origin_price = this.form.origin_price;
        this.form.specs_data.sku_list[0].cost_price = this.form.cost_price;
        this.form.specs_data.sku_list[0].purchase_price = this.form.purchase_price;
        this.form.specs_data.sku_list[0].stock = this.form.stock;
      }

      this.is_submiting = true;
      this.$request({
        url: this.apiUrl, // 【动态灵活的提交通道】
        data: this.form,
        method: 'post'
      }).then(res => {
        this.is_submiting = false;
        if (res.code === 0) {
          this.$message.success(res.msg);
          this.$emit('success', res.data); // 执行成果返回外壳
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.is_submiting = false;
      })
    },
    // 原有的拉取字典等通用 API
    getGoodsProperty() {
      this.$request({url: '/mall/goods/goods-property'}).then(res => {
        if (res.code === 0) {
          this.goods_property = res.data.property;
        }
      })
    },
    getLevelList() {
      this.$request({url: '/mall/user/level'}).then(res => {
        if (res.code === 0) {
          res.data.list.forEach(item => {
            item.id = item.id.toString();
          })
          this.level_list = [{id: '0', level: -1, name: '普通用户'}].concat(res.data.list);
        }
      })
    },
    getFreight() {
      this.$request({url: '/mall/mall/freight-list', data: {enabled: 1}}).then(res => {
        if (res.code === 0) {
          this.freight_list = res.data.list;
        }
      })
    }
  }
}
</script>

<style scoped lang="scss">
.app-base-goods-panel {
  width: 100%;
  display: flex;
  flex-direction: column;
  flex: 1;
  padding-left: 10px;

  /* 修复：限制最大高度，触发内部弹性盒子的独立滚动，防止在对话框/抽屉内撑爆屏幕导致无法滚动 */
  min-height: 0;
  height: 100%;

  .goods-form {
    flex: 1;
    padding-bottom: 20px;
    overflow-y: auto; /* 关键修复：允许内部内容独立上下滚动 */
    overflow-x: auto;

    // 美化一下内边距防止滚动条贴脸
    padding-right: 5px;

    ::v-deep .el-tabs__header {
      // tab 切换栏 sticky 固定在滚动容器顶部，滚多远都能切换 tab
      position: sticky;
      top: 0;
      z-index: 10;
      background-color: #fff;
    }
  }

  .rich-text-item {
    flex: 1;

    margin-right: 20px;
  }

  .panel-footer {
    flex-shrink: 0;
    display: flex;
    justify-content: flex-end;
  }

  .page-footer {
    padding: 15px 0;
    border-top: 1px solid #ebeef5;
    margin-top: 20px;
  }

  .drawer-footer {
    padding: 15px 20px;
    border-top: 1px solid #ebeef5;
    background-color: #fff;
    position: sticky;
    bottom: 0;
    z-index: 20;

    .el-button {
      margin-left: 12px;
      min-width: 90px;
    }
  }
}
</style>
