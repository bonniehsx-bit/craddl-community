<template>
  <div class="diy-goods-group" :style="outerStyle">
    <div class="goods-group-inner" :class="{ 'card-minimal': safeData.card_style === 'minimal' }" :style="innerStyle">

      <!-- 空状态提示 -->
      <div v-if="list.length === 0" class="empty-goods-tip">
        <i class="el-icon-goods"></i>
        <div>请在右侧添加商品</div>
      </div>

      <!-- 1. 滚动模式 (type == 1) -->
      <div v-else-if="safeData.type == 1" class="scroll-wrapper">
        <div class="scroll-content">
          <div class="goods-item" v-for="(goods, index) in list" :key="index" :style="itemStyle">
            <div class="goods-card" :style="cardStyle">
              <div class="img-box">
              <el-image :src="goods.cover_pic" fit="cover" :style="imageStyle" class="goods-img"></el-image>
              <div class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</div>
            </div>
              <div class="goods-info">
                <div class="goods-title">{{ goods.name }}</div>
                <div class="goods-bottom">
                  <div class="price-box" v-if="safeData.show_price == 1" :style="{ color: safeData.price_color }">
                    <span v-if="goods.is_negotiable == 1">面议</span>
                    <span v-else><span class="cur">￥</span>{{ shownPrice(goods.price) }}</span>
                  </div>

                  <!-- 购买按钮 -->
                  <div class="buy-btn-wrapper" v-if="safeData.show_buy_btn == 1">
                    <!-- 图标模式 -->
                    <!-- 圆形购物车:按钮色做底、白色图标(和小程序 tui-goods-group 的 cart_type=2 一致) -->
                    <div v-if="safeData.cart_type == 2" class="round-btn" :style="roundBtnStyle">
                      <i class="el-icon-shopping-cart-2"></i>
                    </div>
                    <div v-else-if="safeData.cart_type == 1" class="icon-btn">
                      <img :src="safeData.cart_pic || defaultCartIcon" alt="">
                    </div>
                    <!-- 按钮模式 -->
                    <div v-else class="text-btn" :style="textBtnStyle">
                      {{ safeData.cart_text || '购买' }}
                    </div>
                  </div>
                </div>

                <div class="sales-box" v-if="safeData.show_sales == 1">
                  {{ safeData.sales_text }} <span :style="{ color: safeData.price_color }">100+</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. 列表模式 (type == 2) -->
      <div v-else-if="safeData.type == 2" class="list-wrapper">
        <div class="list-item" :style="cardStyle" v-for="(goods, index) in list" :key="index">
          <div class="list-img-box">
            <el-image :src="goods.cover_pic" fit="cover" style="width: 100%; height: 100%;"></el-image>
            <div class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</div>
          </div>
          <div class="list-info-box">
            <div class="goods-title">{{ goods.name }}</div>
            <div class="list-bottom">
              <div class="price-row">
                <div class="price-box" v-if="safeData.show_price == 1" :style="{ color: safeData.price_color }">
                  <span class="cur">￥</span>{{ shownPrice(goods.price) }}
                </div>
                <div class="sales-box" v-if="safeData.show_sales == 1">
                  {{ safeData.sales_text }} <span :style="{ color: safeData.price_color }">100+</span>
                </div>
              </div>

              <div class="buy-btn-wrapper" v-if="safeData.show_buy_btn == 1">
                <!-- 圆形购物车:按钮色做底、白色图标(和小程序 tui-goods-group 的 cart_type=2 一致) -->
                <div v-if="safeData.cart_type == 2" class="round-btn" :style="roundBtnStyle">
                  <i class="el-icon-shopping-cart-2"></i>
                </div>
                <div v-else-if="safeData.cart_type == 1" class="icon-btn">
                  <img :src="safeData.cart_pic || defaultCartIcon" alt="">
                </div>
                <div v-else class="text-btn" :style="textBtnStyle">
                  {{ safeData.cart_text || '购买' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. 正常模式 (type == 0, 网格布局) -->
      <div v-else class="grid-wrapper">
        <div class="goods-item" v-for="(goods, index) in list" :key="index" :style="itemStyle">
          <div class="goods-card" :style="cardStyle">
            <div class="img-box">
              <el-image :src="goods.cover_pic" fit="cover" :style="imageStyle" class="goods-img"></el-image>
              <div class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</div>
            </div>
            <div class="goods-info">
              <div class="goods-title">{{ goods.name }}</div>
              <div class="goods-bottom">
                <div class="price-box" v-if="safeData.show_price == 1" :style="{ color: safeData.price_color }">
                  <span v-if="goods.is_negotiable == 1">面议</span>
                  <span v-else><span class="cur">￥</span>{{ shownPrice(goods.price) }}</span>
                </div>

                <div class="buy-btn-wrapper" v-if="safeData.show_buy_btn == 1">
                  <!-- 圆形购物车:按钮色做底、白色图标(和小程序 tui-goods-group 的 cart_type=2 一致) -->
                  <div v-if="safeData.cart_type == 2" class="round-btn" :style="roundBtnStyle">
                    <i class="el-icon-shopping-cart-2"></i>
                  </div>
                  <div v-else-if="safeData.cart_type == 1" class="icon-btn">
                    <img :src="safeData.cart_pic || defaultCartIcon" alt="">
                  </div>
                  <div v-else class="text-btn" :style="textBtnStyle">
                    {{ safeData.cart_text || '购买' }}
                  </div>
                </div>
              </div>
              <div class="sales-box" v-if="safeData.show_sales == 1">
                {{ safeData.sales_text }} <span :style="{ color: safeData.price_color }">100+</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'GoodsGroup',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      defaultCartIcon: require('@/assets/statics/mall/diy/cart_icon.png') // 建议替换为你项目里的默认购物车图标路径
    }
  },
  methods: {
    // 简约卡片:整数价不带「.00」(和手机端同规则)
    shownPrice(price) {
      const s = String(price == null ? '' : price)
      return this.safeData.card_style === 'minimal' ? s.replace(/\.0+$/, '') : s
    },
    // 单品角标优先,没填才用整组角标(和手机端 tui-goods-group 同一口径)
    badgeText(goods) {
      const own = goods && this.safeData.item_badges ? this.safeData.item_badges[goods.id] : ''
      if (own) return own
      return this.safeData.badge_show == 1 ? (this.safeData.badge_text || '') : ''
    },
    /** 把颜色转成指定透明度的 rgba；解析不了就原样返回 */
    fade(color, alpha) {
      const c = String(color).trim();
      const rgba = c.match(/rgba?\(([^)]+)\)/i);
      if (rgba) {
        const p = rgba[1].split(',');
        return `rgba(${parseInt(p[0], 10)},${parseInt(p[1], 10)},${parseInt(p[2], 10)},${alpha})`;
      }
      let h = c.replace('#', '');
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      if (h.length !== 6 || /[^0-9a-f]/i.test(h)) return color;
      const r = parseInt(h.slice(0, 2), 16);
      const g = parseInt(h.slice(2, 4), 16);
      const b = parseInt(h.slice(4, 6), 16);
      return `rgba(${r},${g},${b},${alpha})`;
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    list() {
      return this.safeData.list || [];
    },
    // 外层容器：控制内边距和背景色
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent' // 透明，透出手机壳底色
      };
    },
    // 内层容器：控制圆角和背景色
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        // 顶部留白放在内层，这样它落在背景色范围内；放外层会透出页面底色，
        // 商品看起来仍贴着背景色顶边。5px = uniapp 侧的 10rpx。
        // 上下都给，不然底部会叠上「最后一个商品的 margin」变成两倍（与 uniapp 侧同口径）
      padding: '5px 0',
        // 兜底用配置声明的默认值,不是 transparent —— 老配置没有 background 这个键时,
        // 它当年在真机上的实际观感就是 #f5f5f5,兜到透明会让预览和真机对不上。
        backgroundColor: this.safeData.background || '#f5f5f5',
        overflow: 'hidden'
      };
    },
    // 计算每个商品项的宽度 (仅用于 Grid 和 Scroll 模式)
    itemStyle() {
      const columns = parseInt(this.safeData.columns) || 2;
      const type = parseInt(this.safeData.type);

      // 1. 滚动模式宽度固定
      if (type === 1) {
        if (columns === 1) return { width: '80%', paddingRight: '10px' };
        if (columns === 2) return { width: '45%', paddingRight: '10px' };
        return { width: '35%', paddingRight: '10px' };
      }

      // 2. 正常模式 (Grid) - 核心修复：使用 calc 精确计算间距
      // 假设每列间距为 10px (左右各5px padding)
      // width = (100% / columns)
      const widthPercent = (100 / columns);
      return {
        width: `${widthPercent}%`,
        padding: '5px' // 统一内边距，形成间隙
      };
    },
    // 图片高度
    imageStyle() {
      // 注意：这里我们做个简单的换算，把原来的 px 值缩小一点适应预览区，或者保持原样
      // 原代码：goodsHeight()
      const height = parseInt(this.safeData.cover_pic_height) || 370;
      // 在预览区里如果按 750px 设计稿的高度直接显示可能会太大，通常除以 2 (rpx -> px)
      // 这里假设用户填的是 px，直接使用
      return { height: height / 2 + 'px' };
    },
    /**
     * 卡片「靠什么从背景里立起来」——跟着背景色亮度走，与 uniapp 侧 cardStyle 同一套判定。
     * 阴影是黑色半透明的，而背景色运营可以随便配，写死一种必然有配色下失效：
     *   深底黑影看不见还糊边；近纯白时卡片和背景同色，阴影太淡撑不住边界。
     * 阈值与权重(BT.601)和移动端保持一致，只是单位换成 px。
     */
    bgLuminance() {
      const c = String(this.safeData.background || '#f5f5f5').trim();
      let r, g, b;
      const rgba = c.match(/rgba?\(([^)]+)\)/i);
      if (rgba) {
        const p = rgba[1].split(',');
        r = parseInt(p[0], 10); g = parseInt(p[1], 10); b = parseInt(p[2], 10);
      } else {
        let h = c.replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (h.length !== 6 || /[^0-9a-f]/i.test(h)) return 255;
        r = parseInt(h.slice(0, 2), 16);
        g = parseInt(h.slice(2, 4), 16);
        b = parseInt(h.slice(4, 6), 16);
      }
      if ([r, g, b].some(v => isNaN(v))) return 255;
      return (r * 299 + g * 587 + b * 114) / 1000;
    },
    cardStyle() {
      if (this.bgLuminance < 140) return { boxShadow: 'none' };
      if (this.bgLuminance > 245) {
        return { boxShadow: '0 0.5px 2px rgba(28,28,30,0.04)', border: '1px solid #eceef0' };
      }
      return { boxShadow: '0 1px 2px rgba(28,28,30,0.05), 0 4px 12px rgba(28,28,30,0.05)' };
    },
    /* ── 商品角标：与 uniapp 侧同口径 ── */
    badgeStyle() {
      const allow = ['pill', 'block', 'ribbon', 'dot', 'bar'];
      const v = this.safeData.badge_style;
      return allow.indexOf(v) > -1 ? v : 'pill';
    },
    badgeColor() {
      // 留空则跟随按钮色，两者都没有再兜到默认红
      return this.safeData.badge_color || this.safeData.cart_color || '#FF3C29';
    },
    badgeSolidStyle() {
      return { backgroundColor: this.badgeColor, color: '#fff' };
    },
    badgeBarStyle() {
      // 纯色横条会把封面下缘整条切断，商品图看着像被裁了一截，所以向右淡出
      const c = this.badgeColor;
      return {
        background: `linear-gradient(to right, ${c}, ${this.fade(c, 0.72)})`,
        color: '#fff'
      };
    },
    // 文字按钮样式
    textBtnStyle() {
      const color = this.safeData.cart_color || '#FF3C29';
      const isCircle = this.safeData.is_circle == 1; // 按钮圆角跟随整体圆角设置，或者单独设置
      // 实心胶囊，与 uniapp 侧 btnStyle 同口径：
      // 原来的 1px 描边小方块在真机上触控目标太小，且挨着商品图存在感很弱。
      return {
        backgroundColor: color,
        color: '#fff',
        borderColor: color,
        borderRadius: isCircle ? '13px' : '4px'   // 13px = uniapp 的 26rpx
      };
    },
    roundBtnStyle() {
      return { backgroundColor: this.safeData.cart_color || '#FF3C29' };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-goods-group {
  /* padding 由 style 控制 */
  overflow: hidden;
}

.goods-group-inner {
  /* 圆角、背景、上下留白全部由 innerStyle 控制，这里不要再写 padding，
     否则会和内联的 padding 打架（内联赢，横向那 5px 会静默丢掉）。
     横向间距由 .goods-item / .list-wrapper 各自的 padding 提供。 */
}

.empty-goods-tip {
  height: 100px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  color: #909399;
  font-size: 12px;
  background: #fff;
  border-radius: 4px;

  i {
    font-size: 24px;
    margin-bottom: 5px;
  }
}

/* 1. 滚动模式容器 */
.scroll-wrapper {
  overflow-x: auto;

  .scroll-content {
    display: flex;
    flex-wrap: nowrap;
    /* 底部留白由 innerStyle 统一给，这里不再叠加。
       .goods-item 是网格/滚动共用的类，它那 10px 的 margin-bottom 在滚动模式下
       纯属多余（只有一行），负边距抵掉，否则滚动模式底部是别处的三倍。 */
    padding-bottom: 0;
    margin-bottom: -10px;

    /* 预留滚动条空间 */
    /* 隐藏滚动条 */
    &::-webkit-scrollbar {
      display: none;
    }
  }
}

/* 2. 列表模式容器 */
.list-wrapper {
  padding: 0 5px;

  .list-item {
    display: flex;
    background: #fff;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 6px;

    /* 最后一个不再往下推，底部留白由 innerStyle 统一给 */
    &:last-child {
      margin-bottom: 0;
    }

    .list-img-box {
      width: 100px;
      height: 100px;
      flex-shrink: 0;
      border-radius: 4px;
      overflow: hidden;
      margin-right: 10px;
    }

    .list-info-box {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;

      .goods-title {
        font-size: 14px;
        line-height: 1.4;
        height: 40px;
        overflow: hidden;
        margin-bottom: 5px;
      }

      .list-bottom {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
      }
    }
  }
}

/* 3. 网格模式容器 */
.grid-wrapper {
  display: flex;
  flex-wrap: wrap;
  /* 商品的 margin-bottom 既是行间距、也会在最后一行多留一截。
     行间距要保住，所以只抵消最后一行那截，底部留白交给 innerStyle。
     用 :last-child 不行：多列时最后一行有多个元素，只归零一个会一高一低。 */
  margin-bottom: -10px;
}

/* 通用商品卡片样式 (Grid & Scroll) */
.goods-item {
  box-sizing: border-box;
  padding: 0 5px;
  margin-bottom: 10px;
  flex-shrink: 0; // 防止flex压缩

  /* 图片容器：角标的定位父级，overflow 负责把斜角带裁成三角 */
  .img-box {
    position: relative;
    overflow: hidden;
    line-height: 0;
  }

  .goods-card {
    background: #fff;
    /* 6px = uniapp 侧的 12rpx；阴影/描边由 cardStyle 按背景亮度注入，不写死 */
    border-radius: 6px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;

    .goods-img {
      width: 100%;
      display: block;
    }

    .goods-info {
      padding: 8px;
      flex: 1;
      display: flex;
      flex-direction: column;

      .goods-title {
        font-size: 13px;
        line-height: 1.4;
        height: 36px;
        overflow: hidden;
        margin-bottom: 8px;
        color: #333;
      }

      .goods-bottom {
        margin-top: auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .sales-box {
        font-size: 10px;
        color: #999;
        margin-top: 4px;
      }
    }
  }
}

/* ══════════ 商品角标 ══════════
   与 uniapp 侧 .goods-badge 一一对应，只是单位换成 px（1rpx = 0.5px）。
   底色/文字色由 computed 注入，这里只管形状和位置。 */
.goods-badge {
  position: absolute;
  z-index: 2;
  font-size: 10px;
  line-height: 1.5;
  white-space: nowrap;
  pointer-events: none;
}

.badge-pill   { left: 6px; top: 6px; padding: 2px 8px; border-radius: 10px; }
.badge-block  { left: 0;   top: 0;   padding: 2px 7px; border-radius: 6px 0 6px 0; }

/* 斜角带：左上 45°，靠父级 overflow 裁成三角。
   不做内凹尖角/右旗尖 —— 那要用和背景同色的三角去挖，而背景是商品图、颜色不固定。 */
.badge-ribbon {
  /* 百分比而非固定 px：列表模式图片只有 100px 宽，固定宽度会占掉一大块（同 uniapp 侧） */
  left: -14%; top: 7%; width: 51%;
  padding: 3px 0; text-align: center;
  transform: rotate(-45deg);
}

.badge-dot {
  right: 6px; top: 6px; width: 26px; height: 26px;
  border-radius: 50%; text-align: center; line-height: 26px; font-weight: 600;
}

.badge-bar { left: 0; right: 0; bottom: 0; padding: 3px 7px; }

/* 列表模式的图片容器本来就有 overflow:hidden，直接当定位父级 */
.list-img-box { position: relative; }

/* 通用价格和按钮样式 */
.price-box {
  /* 9px = uniapp 的 18px…这里按预览 375px 宽的实际观感取值，与移动端 36rpx 同观感 */
  font-size: 18px;
  font-weight: bold;
  font-variant-numeric: tabular-nums;

  /* 货币符号比数字小一号：￥ 和数字同大时它太抢视线，用户要读的是数字 */
  .cur {
    font-size: 11px;
    font-weight: 600;
    margin-right: 1px;
  }
}

.buy-btn-wrapper {
  // 24px = uniapp 的 48rpx
  .round-btn {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    color: #fff;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .icon-btn {
    width: 20px;
    height: 20px;

    img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
  }

  /* 实心胶囊，尺寸对齐 uniapp（48rpx 高 → 24px）。
     底色/文字色/圆角由 textBtnStyle 注入。 */
  .text-btn {
    height: 24px;
    line-height: 24px;
    padding: 0 11px;
    font-size: 12px;
    border: none;
    text-align: center;
    white-space: nowrap;
  }
}

/* 简约卡片:和手机端 tui-goods-group 的 .card-minimal 同一口径 */
.card-minimal {
  .goods-card {
    border: none !important;
    box-shadow: 0 2px 8px rgba(42, 37, 34, 0.08) !important;
  }
  .goods-title {
    font-size: 12px;
    line-height: 17px;
    color: #3a3532;
    font-weight: 400;
  }
  .price-box {
    font-family: "Avenir Next Condensed", "DIN Condensed", "DIN Alternate", "Helvetica Neue", sans-serif;
    font-weight: 800;
  }
}
</style>
