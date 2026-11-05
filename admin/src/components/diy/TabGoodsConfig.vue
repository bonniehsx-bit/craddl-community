<template>
  <div class="tab-goods-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 样式设置 -->
      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="卡片风格">
          <el-radio-group v-model="form.card_style">
            <el-radio-button label="default">默认</el-radio-button>
            <el-radio-button label="minimal">简约</el-radio-button>
          </el-radio-group>
          <div class="tip">简约:商品名小一号、价格用窄体数字、淡阴影无描边,适合精品 / 礼品类商城。</div>
        </el-form-item>
        <el-form-item label="背景颜色">
          <el-color-picker v-model="form.background"></el-color-picker>
        </el-form-item>

        <el-form-item label="选中颜色">
          <el-color-picker v-model="form.active_color"></el-color-picker>
        </el-form-item>

        <el-form-item label="未选颜色">
          <el-color-picker v-model="form.inactive_color"></el-color-picker>
        </el-form-item>

        <el-form-item label="价格颜色">
          <el-color-picker v-model="form.price_color"></el-color-picker>
        </el-form-item>

        <el-form-item label="展示风格">
          <el-radio-group v-model="form.goods_style">
            <el-radio :label="0">网格</el-radio>
            <el-radio :label="1">滚动</el-radio>
            <el-radio :label="2">列表</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="圆角设置">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="显示销量">
          <el-switch v-model="form.show_sales" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="销量前缀" v-if="form.show_sales == 1">
          <el-input v-model="form.sales_text" placeholder="例如：已售"></el-input>
        </el-form-item>

        <el-divider></el-divider>

        <el-form-item label="购买按钮">
          <el-switch v-model="form.show_buy_btn" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <template v-if="form.show_buy_btn == 1">
          <el-form-item label="按钮样式">
            <el-radio-group v-model="form.cart_type">
              <el-radio :label="0">文字按钮</el-radio>
              <el-radio :label="1">图标按钮</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="按钮文案" v-if="form.cart_type == 0">
            <el-input v-model="form.cart_text" placeholder="例如：购买"></el-input>
          </el-form-item>

          <el-form-item label="按钮颜色" v-if="form.cart_type == 0">
            <el-color-picker v-model="form.cart_color"></el-color-picker>
          </el-form-item>

          <el-form-item label="按钮图标" v-if="form.cart_type == 1">
            <single-image-selector v-model="form.cart_pic"></single-image-selector>
          </el-form-item>
        </template>
      </div>

      <!-- 标签管理 -->
      <div class="config-section">
        <div class="section-title">
          <span>标签管理 ({{ form.list.length }})</span>
        </div>

        <draggable v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(tab, index) in form.list" :key="index" class="tab-edit-item">
            <div class="item-header">
              <div class="drag-handle"><i class="el-icon-s-operation"></i> 标签 {{ index + 1 }}</div>
              <div class="delete-btn" @click="handleDeleteTab(index)" v-if="form.list.length > 1">
                <i class="el-icon-delete"></i>
              </div>
            </div>

            <div class="item-body">
              <el-form-item label="标签名称" label-width="60px">
                <el-input v-model="tab.name" placeholder="请输入名称"></el-input>
              </el-form-item>

              <el-form-item label="来源" label-width="60px">
                <source-switch v-model="tab.source" size="mini" @change="refreshTab(tab)"></source-switch>
              </el-form-item>

              <template v-if="tab.source === 'cat'">
                <el-form-item label="分类" label-width="60px">
                  <cat-select v-model="tab.cat_id" @change="c => onTabCat(tab, c)"></cat-select>
                </el-form-item>
                <el-form-item label="件数" label-width="60px">
                  <el-input-number v-model="tab.cat_num" :min="1" :max="20" size="mini" @change="refreshTab(tab)"></el-input-number>
                </el-form-item>
                <el-form-item label="排序" label-width="60px" style="margin-bottom: 0;">
                  <el-select v-model="tab.cat_sort" size="mini" @change="refreshTab(tab)">
                    <el-option label="销量从高到低" value="sales"></el-option>
                    <el-option label="最新上架" value="new"></el-option>
                    <el-option label="价格从低到高" value="price_asc"></el-option>
                    <el-option label="价格从高到低" value="price_desc"></el-option>
                  </el-select>
                </el-form-item>
              </template>

              <el-form-item v-if="tab.source !== 'cat'" label="商品" label-width="60px" style="margin-bottom: 0;">
                <div class="goods-count-info">
                  已选 <span style="color: var(--admin-accent-text); font-weight: bold;">{{ tab.goods_list ? tab.goods_list.length : 0
                    }}</span> 件商品
                </div>
                <!-- 商品选择器触发按钮 -->
                <el-button type="text" size="mini" @click="openGoodsPicker(index)">添加商品</el-button>
              </el-form-item>

              <!-- 已选商品列表预览与删除 -->
              <el-form-item label="列表" label-width="60px" v-if="tab.source !== 'cat' && tab.goods_list && tab.goods_list.length > 0"
                style="margin-top: 10px;">
                <draggable v-model="tab.goods_list" :options="{ animation: 200 }">
                  <div v-for="(goods, gIndex) in tab.goods_list" :key="gIndex"
                    style="display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 5px; border: 1px solid #eee; margin-bottom: 5px; border-radius: 4px;">
                    <div style="display: flex; align-items: center; overflow: hidden;">
                      <img :src="goods.cover_pic"
                        style="width: 30px; height: 30px; object-fit: cover; margin-right: 10px; border-radius: 2px;">
                      <span
                        style="font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">{{
                          goods.name }}</span>
                    </div>
                    <i class="el-icon-delete" style="cursor: pointer; color: #F56C6C;"
                      @click="tab.goods_list.splice(gIndex, 1)"></i>
                  </div>
                </draggable>
              </el-form-item>
            </div>
          </div>
        </draggable>

        <el-button type="primary" plain icon="el-icon-plus" style="width: 100%; margin-top: 10px;"
          @click="handleAddTab">添加标签</el-button>
      </div>

      <!-- 隐藏的商品选择器 (通过 ref 或 v-if 控制) -->
      <!-- 这是一个逻辑组件，用于弹窗选择商品 -->
      <goods-picker ref="goodsPicker" :multiple="true" :max="20" @selected="handleGoodsSelected"></goods-picker>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import CatSelect from './style-kit/CatSelect'
import SourceSwitch from './style-kit/SourceSwitch'
// 确保 goods-picker 已全局注册

export default {
  name: 'TabGoodsConfig',
  components: { draggable, CatSelect, SourceSwitch },
  props: {
    value: Object
  },
  data() {
    return {
      currentEditIndex: -1, // 当前正在编辑哪个标签的商品
      defaultConfig: {
        card_style: 'default',
        background: '#f5f7fa',
        active_color: '#ff4544',
        inactive_color: '#333333',
        price_color: '#ff4544',
        goods_style: 0,
        is_padding: '0',
        is_circle: '0',
        show_sales: 1,
        sales_text: '已售',
        show_buy_btn: 1,
        cart_type: 0,
        cart_text: '购买',
        cart_color: '#ff4544',
        cart_pic: '',
        list: [
          { name: '热销推荐', goods_list: [] },
          { name: '新品上市', goods_list: [] }
        ]
      }
    }
  },
  computed: {
    form: {
      get() {
        return this.value || JSON.parse(JSON.stringify(this.defaultConfig));
      },
      set(val) {
        this.$emit('input', val);
      }
    }
  },
  watch: {
    value: {
      handler(val) {
        if (!val) {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)));
          return;
        }
        // 老标签没有「来源」这几个字段:先用 $set 补齐成响应式,radio 才会正确切换
        // (直接给对象加新属性 Vue 侦测不到,单选框会出现两个同时选中)
        (val.list || []).forEach(tab => this.normalizeTab(tab));
        if (!('card_style' in val)) this.$set(val, 'card_style', 'default');
      },
      immediate: true
    }
  },
  methods: {
    handleAddTab() {
      this.form.list.push({ name: '新标签', goods_list: [], source: 'manual', cat_id: 0, cat_num: 6, cat_sort: 'sales' });
    },
    normalizeTab(tab) {
      const def = { source: 'manual', cat_id: 0, cat_num: 6, cat_sort: 'sales' };
      Object.keys(def).forEach(k => {
        if (!(k in tab)) this.$set(tab, k, def[k]);
      });
      if (!Array.isArray(tab.goods_list)) this.$set(tab, 'goods_list', []);
    },
    onTabCat(tab, cat) {
      // 标签名还是默认的,就顺手用分类名
      if (cat && (!tab.name || tab.name === '新标签')) tab.name = cat.name;
      this.refreshTab(tab);
    },
    /** 「按分类自动」的标签:按手机端同一套规则取商品,马上刷新左边预览 */
    refreshTab(tab) {
      if (tab.source !== 'cat' || !tab.cat_id) return;
      this.$request({
        url: '/mall/mall/diy-cat-goods',
        data: { cat_id: tab.cat_id, num: tab.cat_num, sort: tab.cat_sort }
      }).then(res => {
        if (res.code === 0) tab.goods_list = res.data.list;
      });
    },
    handleDeleteTab(index) {
      this.form.list.splice(index, 1);
    },
    // 打开商品选择器
    openGoodsPicker(index) {
      this.currentEditIndex = index;
      if (this.$refs.goodsPicker) {
        this.$refs.goodsPicker.dialogVisible = true;
      } else {
        this.$message.warning('商品选择器未加载');
      }
    },
    // 商品选择回调
    handleGoodsSelected(goods) {
      if (this.currentEditIndex === -1) return;

      const list = Array.isArray(goods) ? goods : [goods];

      // 更新对应标签的商品列表
      // 这里选择直接替换，还是追加？通常是追加或替换，看业务
      // 这里做简单的去重追加
      const currentTab = this.form.list[this.currentEditIndex];
      if (!currentTab.goods_list) this.$set(currentTab, 'goods_list', []);

      list.forEach(item => {
        // 简单判重
        const exists = currentTab.goods_list.find(g => g.id === item.id);
        if (!exists) {
          currentTab.goods_list.push({
            id: item.id,
            name: item.name,
            cover_pic: item.cover_pic,
            price: item.price,
            origin_price: item.origin_price,
            virtual_sales: item.virtual_sales
          });
        }
      });

      // 重置索引
      // this.currentEditIndex = -1; // 此时 picker 可能还没关，不要立即重置，除非 picker 是一次性的
    }
  }
}
</script>

<style lang="scss" scoped>
.tab-goods-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
    }
  }
}

.tab-edit-item {
  background: #f8f9fa;
  border: 1px solid #eee;
  border-radius: 4px;
  margin-bottom: 10px;

  .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 10px;
    background: #fff;
    border-bottom: 1px solid #eee;
    border-radius: 4px 4px 0 0;

    .drag-handle {
      cursor: move;
      color: #606266;
      font-size: 12px;

      i {
        margin-right: 5px;
      }
    }

    .delete-btn {
      cursor: pointer;
      color: #F56C6C;

      &:hover {
        color: #ff0000;
      }
    }
  }

  .item-body {
    padding: 10px;
  }
}

.goods-count-info {
  display: inline-block;
  margin-right: 10px;
  font-size: 12px;
  color: #666;
}
</style>
