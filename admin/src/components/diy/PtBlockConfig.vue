<template>
  <div class="pt-block-config">
    <el-form label-width="90px" size="small" label-position="left">

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

      <!-- 内容显示设置 -->
      <div class="config-section">
        <div class="section-title">内容设置</div>

        <el-form-item label="显示价格">
          <el-switch v-model="form.show_price" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="价格颜色" v-if="form.show_price == 1">
          <el-color-picker v-model="form.price_color"></el-color-picker>
        </el-form-item>

        <el-form-item label="成团人数">
          <el-switch v-model="form.show_success_num" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="人数颜色" v-if="form.show_success_num == 1">
          <el-color-picker v-model="form.success_num_color"></el-color-picker>
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
              <el-input v-model="form.cart_text" placeholder="例如: 开团"></el-input>
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
          <span>拼团商品 ({{ form.list.length }})</span>
          <span class="tip">拖拽可排序</span>
        </div>

        <draggable v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(goods, index) in form.list" :key="index" class="goods-edit-item">
            <div class="goods-info">
              <div class="drag-handle"><i class="el-icon-s-operation"></i></div>
              <img :src="goods.cover_pic" class="goods-pic">
              <div class="goods-name">{{ goods.name }}</div>
              <div class="goods-price">￥{{ goods.price }}</div>
            </div>
            <div class="delete-btn" @click="handleDelete(index)">
              <i class="el-icon-delete"></i>
            </div>
          </div>
        </draggable>

        <div style="margin-top: 10px; text-align: center">
          <!-- 注意：这里用的是 pt-goods-picker -->
          <pt-goods-picker @selected="handleGoodsSelected">
            <el-button type="primary" plain icon="el-icon-plus" style="width: 100%">添加拼团商品 ({{ form.list.length }}/50)</el-button>
          </pt-goods-picker>
        </div>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
// 确保 pt-goods-picker, single-image-selector 已注册

export default {
  name: 'PtBlockConfig',
  components: { draggable },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        type: 0,
        columns: 2,
        is_padding: '0',
        is_circle: '0',
        show_price: 1,
        price_color: '#FF3C29',
        show_success_num: 1,
        success_num_color: '#B2B2B2',
        show_buy_btn: 1,
        cart_type: 0,
        cart_text: '开团',
        cart_color: '#FF3C29',
        cart_pic: '',
        list: []
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
    handleDelete(index) {
      this.form.list.splice(index, 1);
    },
    handleGoodsSelected(goodsList) {
      if(!goodsList) return;
      const list = Array.isArray(goodsList) ? goodsList : [goodsList];

      list.forEach(item => {
        // 这里添加拼团特有字段
        this.form.list.push({
          id: item.id,
          name: item.name,
          cover_pic: item.cover_pic,
          price: item.price,
          pt_price: item.price, // 拼团价，通常接口会返回
          success_num: item.success_num || 0
        });
      });

      if(this.form.list.length > 50) {
        this.form.list = this.form.list.slice(0, 50);
        this.$message.warning('最多添加50件商品');
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.pt-block-config {
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

.goods-edit-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8f9fa;
  border: 1px solid #eee;
  padding: 8px;
  margin-bottom: 8px;
  border-radius: 4px;

  .goods-info {
    display: flex;
    align-items: center;
    flex: 1;
    overflow: hidden;

    .drag-handle { cursor: move; color: #909399; margin-right: 8px; }
    .goods-pic { width: 36px; height: 36px; object-fit: cover; border-radius: 2px; margin-right: 8px; flex-shrink: 0; }
    .goods-name { font-size: 12px; color: #333; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 10px; }
    .goods-price { font-size: 12px; font-weight: bold; color: #333; margin-right: 10px; }
  }

  .delete-btn {
    cursor: pointer; color: #F56C6C; padding: 4px;
    &:hover { background: #fef0f0; border-radius: 4px; }
  }
}
</style>
