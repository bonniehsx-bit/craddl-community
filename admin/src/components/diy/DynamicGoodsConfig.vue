<template>
  <div class="dynamic-goods-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 数据来源 -->
      <div class="config-section">
        <div class="section-title">数据来源</div>

        <el-form-item label="显示数量">
          <el-input-number v-model="form.show_num" :min="1" :max="20" @change="fetchGoods"></el-input-number>
        </el-form-item>

        <el-form-item label="筛选规则">
          <el-checkbox-group v-model="form.property" @change="fetchGoods">
            <el-checkbox
              v-for="(item, index) in goods_property"
              :key="index"
              :label="item"
              style="margin-right: 10px;"
            >{{ item }}</el-checkbox>
          </el-checkbox-group>
          <div class="tip" v-if="goods_property.length === 0">暂无筛选标签</div>
        </el-form-item>
      </div>

      <!-- 样式设置 -->
      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="显示模式">
          <el-radio-group v-model="form.type">
            <el-radio :label="0">网格</el-radio>
            <el-radio :label="1">滚动</el-radio>
            <el-radio :label="2">列表</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="每行数量" v-if="form.type !== 2">
          <el-radio-group v-model="form.columns">
            <el-radio :label="1">1个</el-radio>
            <el-radio :label="2">2个</el-radio>
            <el-radio :label="3">3个</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="图片高度" v-if="form.type !== 2">
          <el-slider v-model="form.cover_pic_height" :min="100" :max="800" show-input input-size="mini"></el-slider>
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
      </div>

      <!-- 内容显示 -->
      <div class="config-section">
        <div class="section-title">内容显示</div>

        <el-form-item label="显示价格">
          <el-switch v-model="form.show_price" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="价格颜色" v-if="form.show_price == 1">
          <el-color-picker v-model="form.price_color"></el-color-picker>
        </el-form-item>

        <el-form-item label="显示销量">
          <el-switch v-model="form.show_sales" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="销量文字" v-if="form.show_sales == 1">
          <el-input v-model="form.sales_text"></el-input>
        </el-form-item>

        <el-form-item label="显示按钮">
          <el-switch v-model="form.show_buy_btn" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <div v-if="form.show_buy_btn == 1">
          <el-form-item label="按钮类型">
            <el-radio-group v-model="form.cart_type">
              <el-radio :label="0">文字</el-radio>
              <el-radio :label="1">图标</el-radio>
            </el-radio-group>
          </el-form-item>

          <template v-if="form.cart_type == 0">
            <el-form-item label="按钮文字">
              <el-input v-model="form.cart_text"></el-input>
            </el-form-item>
            <el-form-item label="按钮颜色">
              <el-color-picker v-model="form.cart_color"></el-color-picker>
            </el-form-item>
          </template>

          <template v-else>
            <el-form-item label="按钮图标">
              <single-image-selector v-model="form.cart_pic"></single-image-selector>
            </el-form-item>
          </template>
        </div>
      </div>

    </el-form>
  </div>
</template>

<script>
// 确保 single-image-selector 已注册

export default {
  name: 'DynamicGoodsConfig',
  props: {
    value: Object
  },
  data() {
    return {
      goods_property: [], // 存储后端返回的筛选标签
      defaultConfig: {
        show_num: 4,
        property: [], // 选中的筛选标签

        type: 0,
        columns: 2,
        cover_pic_height: 370,
        is_padding: '0',
        is_circle: '0',

        show_price: 1,
        price_color: '#FF3C29',
        show_sales: 0,
        sales_text: '销量',

        show_buy_btn: 1,
        cart_type: 0,
        cart_text: '购买',
        cart_color: '#FF3C29',
        cart_pic: '',

        list: [] // 动态获取的商品列表
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
        } else {
          // 如果列表为空，尝试获取一次
          if (!val.list || val.list.length === 0) {
            this.fetchGoods();
          }
        }
      },
      immediate: true
    }
  },
  created() {
    this.getGoodsProperty();
  },
  methods: {
    // 获取商品属性标签 (如: 最新, 推荐)
    getGoodsProperty() {
      this.$request({
        url: "/mall/goods/goods-property"
      }).then(res => {
        if (res.code == 0) {
          this.goods_property = res.data.property || [];
        }
      });
    },
    // 根据配置拉取商品列表预览
    fetchGoods() {
      // 防抖或简单的请求逻辑
      this.$request({
        url: "/mall/goods/list",
        data: {
          page: 1,
          limit: this.form.show_num || 4,
          property: this.form.property || []
        }
      }).then(res => {
        if (res.code == 0) {
          this.form.list = res.data.list || [];
        }
      });
    }
  }
}
</script>

<style lang="scss" scoped>
.dynamic-goods-config {
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

  .tip { font-size: 12px; color: #999; margin-top: 5px; }
}
</style>
