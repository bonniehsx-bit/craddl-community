<template>
  <div class="custom-goods-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 样式设置 -->
      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="显示模式">
          <el-radio-group v-model="form.type">
            <el-radio :label="0">网格</el-radio>
            <el-radio :label="1">滚动</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="每行数量">
          <el-radio-group v-model="form.columns">
            <el-radio :label="1">1个</el-radio>
            <el-radio :label="2">2个</el-radio>
            <el-radio :label="3">3个</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="图片高度">
          <el-slider v-model="form.cover_pic_height" :min="100" :max="800" show-input input-size="mini"></el-slider>
        </el-form-item>
      </div>

      <!-- 内容显示设置 -->
      <div class="config-section">
        <div class="section-title">显示设置</div>

        <el-form-item label="显示价格">
          <el-switch v-model="form.show_price" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="价格颜色" v-if="form.show_price == 1">
          <el-color-picker v-model="form.price_color"></el-color-picker>
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
              <el-input v-model="form.cart_text" placeholder="例如: 购买"></el-input>
            </el-form-item>
            <el-form-item label="按钮圆角">
              <el-radio-group v-model="form.is_circle">
                <el-radio :label="0">直角</el-radio>
                <el-radio :label="1">圆角</el-radio>
              </el-radio-group>
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

      <!-- 商品列表 -->
      <div class="config-section">
        <div class="section-title">
          <span>自定义商品 ({{ form.list.length }})</span>
          <span class="tip">拖拽可排序</span>
        </div>

        <draggable v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(item, index) in form.list" :key="index" class="custom-goods-item">
            <div class="item-head">
              <div class="drag-handle"><i class="el-icon-s-operation"></i> 商品 {{ index + 1 }}</div>
              <div class="delete-btn" @click="handleDelete(index)"><i class="el-icon-delete"></i></div>
            </div>

            <div class="item-body">
              <div class="flex-row" style="margin-bottom: 10px;">
                <div class="img-upload">
                  <single-image-selector v-model="item.cover_pic" width="60px" height="60px"></single-image-selector>
                </div>
                <div class="info-input">
                  <el-input v-model="item.name" placeholder="商品名称" size="mini" style="margin-bottom: 5px;"></el-input>
                  <el-input v-model="item.price" placeholder="商品价格" size="mini">
                    <template slot="prepend">￥</template>
                  </el-input>
                </div>
              </div>

              <el-form-item label="跳转链接" label-width="60px" style="margin-bottom: 0;">
                <link-picker v-model="item.url"></link-picker>
              </el-form-item>
            </div>
          </div>
        </draggable>

        <el-button type="primary" plain icon="el-icon-plus" style="width: 100%; margin-top: 10px;" @click="handleAdd">添加自定义商品</el-button>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
// 确保 single-image-selector 和 link-picker 已注册

export default {
  name: 'CustomGoodsConfig',
  components: { draggable },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        cover_pic_height: 375,
        type: 0,
        columns: 2,
        show_price: 1,
        price_color: '#FF3C29',
        show_buy_btn: 1,
        cart_type: 0, // 0-文字, 1-图标
        cart_text: '购买',
        cart_color: '#FF3C29',
        cart_pic: '',
        is_circle: 0,
        list: [
          { cover_pic: '', name: '示例商品', price: '99.00', url: '' }
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
        }
      },
      immediate: true
    }
  },
  methods: {
    handleAdd() {
      this.form.list.push({
        cover_pic: '',
        name: '新商品',
        price: '',
        url: ''
      });
    },
    handleDelete(index) {
      this.form.list.splice(index, 1);
    }
  }
}
</script>

<style lang="scss" scoped>
.custom-goods-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
      display: flex; justify-content: space-between;
      .tip { font-weight: normal; color: #999; font-size: 12px; }
    }
  }
}

.custom-goods-item {
  background: #f8f9fa;
  border: 1px solid #eee;
  border-radius: 4px;
  margin-bottom: 10px;

  .item-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 10px; background: #fff; border-bottom: 1px solid #eee; border-radius: 4px 4px 0 0;

    .drag-handle { cursor: move; color: #606266; font-size: 12px; i { margin-right: 5px; } }
    .delete-btn { cursor: pointer; color: #F56C6C; &:hover { color: #ff0000; } }
  }

  .item-body {
    padding: 10px;

    .flex-row {
      display: flex;
      align-items: flex-start;
      .img-upload { margin-right: 10px; }
      .info-input { flex: 1; }
    }
  }
}
</style>
