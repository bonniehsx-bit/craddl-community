<template>
  <div class="goods-group-config">
    <el-form label-width="90px" size="small" label-position="left">

      <el-divider>布局样式</el-divider>

      <el-form-item label="卡片风格">
        <el-radio-group v-model="form.card_style">
          <el-radio-button label="default">默认</el-radio-button>
          <el-radio-button label="minimal">简约</el-radio-button>
        </el-radio-group>
        <div class="tip">简约:商品名小一号、价格用窄体数字、淡阴影无描边,适合精品 / 礼品类商城。</div>
      </el-form-item>
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

      <el-form-item label="封面高度" v-if="form.type !== 2">
        <el-slider v-model="form.cover_pic_height" :min="100" :max="800" show-input input-size="mini"></el-slider>
        <div class="tip">
          单位是 rpx（手机屏宽固定为 750rpx）。默认 370 约占屏宽一半，比卡片略高一点、成竖图。
          <b>列表模式不使用此项</b>，它的封面尺寸由布局决定。
        </div>
      </el-form-item>

      <el-divider>组件样式</el-divider>

      <el-form-item label="左右边距">
        <el-switch v-model="form.is_padding" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="圆角设置">
        <el-switch v-model="form.is_circle" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="背景颜色">
        <el-color-picker v-model="form.background" show-alpha></el-color-picker>
      </el-form-item>

      <el-divider>内容设置</el-divider>

      <el-form-item label="商品角标">
        <el-switch v-model="form.badge_show" :active-value="1" :inactive-value="0"></el-switch>
        <span class="tip-inline">在商品封面上加一个标签，如「热卖」「新品」</span>
      </el-form-item>

      <template v-if="form.badge_show == 1">
        <el-form-item label="角标样式">
          <el-radio-group v-model="form.badge_style" size="mini">
            <el-radio-button label="pill">胶囊</el-radio-button>
            <el-radio-button label="block">直角块</el-radio-button>
            <el-radio-button label="ribbon">斜角</el-radio-button>
            <el-radio-button label="dot">圆形</el-radio-button>
            <el-radio-button label="bar">底部条</el-radio-button>
          </el-radio-group>
          <div class="tip">
            胶囊 / 直角块 / 斜角在<b>左上角</b>，圆形在<b>右上角</b>，底部条压在封面下缘。
          </div>
        </el-form-item>

        <el-form-item label="角标文案">
          <el-input v-model="form.badge_text" :maxlength="form.badge_style === 'dot' ? 2 : 8"
            show-word-limit placeholder="如：热卖" style="width: 220px"></el-input>
          <div class="tip" v-if="form.badge_style === 'dot'">
            圆形角标位置有限，<b>最多 2 个字</b>，多了会被挤变形。
          </div>
        </el-form-item>

        <el-form-item label="角标颜色">
          <el-color-picker v-model="form.badge_color"></el-color-picker>
          <span class="tip-inline">留空则跟随「按钮颜色」</span>
        </el-form-item>
      </template>

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
        <el-input v-model="form.sales_text" placeholder="例如: 已售"></el-input>
      </el-form-item>

      <el-divider>购买按钮</el-divider>

      <el-form-item label="显示按钮">
        <el-switch v-model="form.show_buy_btn" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <div v-if="form.show_buy_btn == 1">
        <el-form-item label="按钮类型">
          <el-radio-group v-model="form.cart_type" @change="onCartTypeChange">
            <el-radio :label="0">文字按钮</el-radio>
            <el-radio :label="1">图标按钮</el-radio>
            <el-radio :label="2">圆形按钮</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 圆形按钮:按钮色做底 + 白色购物车图标,不用上传图 -->
        <el-form-item label="按钮颜色" v-if="form.cart_type == 2">
          <el-color-picker v-model="form.cart_color"></el-color-picker>
        </el-form-item>

        <!-- 文字按钮设置 -->
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

        <!-- 图标按钮设置 -->
        <template v-else-if="form.cart_type == 1">
          <el-form-item label="上传图标">
            <!-- 调用 file-picker 或 single-image-selector -->
            <single-image-selector v-model="form.cart_pic"></single-image-selector>
            <div class="tip">建议尺寸 40x40 像素</div>
          </el-form-item>
        </template>
      </div>

      <el-divider>商品列表</el-divider>

      <el-form-item label="商品来源">
        <source-switch v-model="form.source" @change="refreshCatPreview"></source-switch>
      </el-form-item>

      <template v-if="form.source === 'cat'">
        <el-form-item label="分类">
          <cat-select v-model="form.cat_id" @change="refreshCatPreview"></cat-select>
        </el-form-item>
        <el-form-item label="取几件">
          <el-input-number v-model="form.cat_num" :min="1" :max="20" size="small" @change="refreshCatPreview"></el-input-number>
        </el-form-item>
        <el-form-item label="排序">
          <el-select v-model="form.cat_sort" style="width: 100%" @change="refreshCatPreview">
            <el-option label="销量从高到低" value="sales"></el-option>
            <el-option label="最新上架" value="new"></el-option>
            <el-option label="价格从低到高" value="price_asc"></el-option>
            <el-option label="价格从高到低" value="price_desc"></el-option>
          </el-select>
          <div class="tip">打开页面时按分类实时取在售商品(含子分类),新上架的商品自动出现,不用回来改装修。</div>
        </el-form-item>
      </template>

      <!-- 已选商品列表 -->
      <draggable v-if="form.source !== 'cat'" v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
        <div v-for="(goods, index) in form.list" :key="index" class="goods-edit-item">
          <div class="goods-info">
            <div class="drag-handle"><i class="el-icon-s-operation"></i></div>
            <img :src="goods.cover_pic" class="goods-pic">
            <div class="goods-name">{{ goods.name }}</div>
            <div class="goods-price">￥{{ goods.price }}</div>
          </div>
          <el-input :value="(form.item_badges || {})[goods.id]" size="mini" placeholder="角标" maxlength="6"
                    class="item-badge" title="这件商品单独的角标,如 新品 / 截单前寄出;填了不受上面「商品角标」开关影响"
                    @input="v => setItemBadge(goods.id, v)"></el-input>
          <div class="delete-btn" @click="handleDelete(index)">
            <i class="el-icon-delete"></i>
          </div>
        </div>
      </draggable>

      <!-- 添加商品按钮 -->
      <div v-if="form.source !== 'cat'" style="margin-top: 10px; text-align: center">
        <!-- 调用你的 goods-picker -->
        <goods-picker @selected="handleGoodsSelected">
          <el-button type="primary" plain icon="el-icon-plus" style="width: 100%">添加商品 ({{ form.list.length
          }}/50)</el-button>
        </goods-picker>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import CatSelect from './style-kit/CatSelect'
import SourceSwitch from './style-kit/SourceSwitch'
// 确保 GoodsPicker, SingleImageSelector 已注册

export default {
  name: 'GoodsGroupConfig',
  components: { draggable, CatSelect, SourceSwitch },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        type: 0,
        columns: 2,
        cover_pic_height: 370,
        show_price: 1,
        price_color: '#FF3C29',
        show_sales: 0,
        sales_text: '销量',
        show_buy_btn: 1,
        cart_type: 0,
        cart_text: '购买',
        cart_color: '#FF3C29',
        cart_pic: '',
        is_circle: 0,
        is_padding: 0,
        background: '#f5f5f5',
        // 角标默认关 —— 老页面不受影响
        badge_show: 0,
        badge_style: 'pill',
        badge_text: '热卖',
        badge_color: '',
        // 商品来源:manual 手动挑 / cat 按分类自动取(DiyHydrateHelper 打开页面时实时查)
        // 卡片风格:default 原样 / minimal 简约
        card_style: 'default',
        source: 'manual',
        cat_id: 0,
        cat_num: 6,
        cat_sort: 'sales',
        // 单品角标 { 商品id: '文字' },优先于整组角标
        item_badges: {},
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
          return;
        }
        // 老商品组没有来源 / 单品角标字段:用 $set 补成响应式,单选和角标输入才会正常更新
        ['source', 'cat_id', 'cat_num', 'cat_sort', 'item_badges', 'card_style'].forEach(k => {
          if (!(k in val)) this.$set(val, k, JSON.parse(JSON.stringify(this.defaultConfig[k])));
        });
      },
      immediate: true
    }
  },
  methods: {
    /**
     * 切到「圆形按钮」时,按钮颜色还是默认亮红就改成跟价格色一致。
     * 图标按钮模式下不显示「按钮颜色」,老组件的 cart_color 多半是没人动过的默认值,
     * 圆形按钮拿它做底色,就会和配过价格色的其它组件一深一浅对不上。
     * 用 @change 而不是 watch:只在运营手动切换时改,选中组件加载配置时不动。
     */
    onCartTypeChange(type) {
      const DEFAULT_COLOR = '#FF3C29';
      if (type !== 2) return;
      const cur = String(this.form.cart_color || '').toUpperCase();
      if ((!cur || cur === DEFAULT_COLOR) && this.form.price_color) {
        this.form.cart_color = this.form.price_color;
      }
    },
    /** 「按分类自动」:按手机端同一套规则取商品,马上刷新左边预览(保存时只存来源和分类,不存这些商品) */
    refreshCatPreview() {
      if (this.form.source !== 'cat' || !this.form.cat_id) return
      this.$request({
        url: '/mall/mall/diy-cat-goods',
        data: { cat_id: this.form.cat_id, num: this.form.cat_num, sort: this.form.cat_sort }
      }).then(res => {
        if (res.code === 0) this.form.list = res.data.list
      })
    },
    setItemBadge(id, text) {
      const map = Object.assign({}, this.form.item_badges || {})
      if (text) map[id] = text
      else delete map[id]
      this.$set(this.form, 'item_badges', map)
    },
    // 删除商品
    handleDelete(index) {
      this.form.list.splice(index, 1);
    },
    // 选中商品回调
    handleGoodsSelected(goodsList) {
      if (!goodsList) return;
      // 兼容单选和多选
      const list = Array.isArray(goodsList) ? goodsList : [goodsList];

      // 追加到现有列表
      list.forEach(item => {
        // 这里可以做去重判断，或者只提取需要的字段
        this.form.list.push({
          id: item.id,
          name: item.name,
          cover_pic: item.cover_pic, // 假设 goods-picker 返回的字段里有这个
          price: item.price,
          is_negotiable: item.is_negotiable
        });
      });

      // 限制最大数量
      if (this.form.list.length > 50) {
        this.form.list = this.form.list.slice(0, 50);
        this.$message.warning('最多添加50件商品');
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.tip {
  font-size: 12px;
  color: #999;
  margin-top: 5px;
}

/* 跟在开关/取色器右边的一句说明 */
.tip-inline {
  font-size: 12px;
  color: #999;
  margin-left: 10px;
}

.item-badge {
  width: 72px;
  flex-shrink: 0;
  margin: 0 6px;
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

    .drag-handle {
      cursor: move;
      color: #909399;
      margin-right: 8px;
    }

    .goods-pic {
      width: 36px;
      height: 36px;
      object-fit: cover;
      border-radius: 2px;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .goods-name {
      font-size: 12px;
      color: #333;
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-right: 10px;
    }

    .goods-price {
      font-size: 12px;
      font-weight: bold;
      color: #333;
      margin-right: 10px;
    }
  }

  .delete-btn {
    cursor: pointer;
    color: #F56C6C;
    padding: 4px;

    &:hover {
      background: #fef0f0;
      border-radius: 4px;
    }
  }
}
</style>
