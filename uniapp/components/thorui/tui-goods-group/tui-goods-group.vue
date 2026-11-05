<template>
	<!-- 内边距与背景色都取后台配置,口径与后台预览(admin GoodsGroup.vue)保持一致。
	     见 computed.rootStyle 的注释。
	     ⚠ 价格行 / 购买按钮 / 原价在五种布局里写法完全相同(小程序模板没法抽片段),改一处要五处一起改。 -->
	<view class="goods-list-root" :style="rootStyle">
		<view class="goods-list-wrapper" :class="{ 'is-circle': value.is_circle == 1, 'card-minimal': value.card_style === 'minimal' }" :style="innerStyle">

			<block v-if="value.type == 1">
				<scroll-view scroll-x="true" class="scroll-x-view">
					<view class="goods-item scroll-item" :class="['columns-' + value.columns, compact ? 'is-compact' : '']" :style="cardStyle"
						v-for="(goods, index) in value.list" :key="index" @click="pageTo(goods)">
						<view class="image-wrapper" :style="imageStyle">
							<image :src="thumbSrc(goods.cover_pic)" mode="aspectFill" class="goods-image" @error="thumbFail(goods.cover_pic)"></image>
							<view class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</view>
						</view>
						<view class="info-wrapper">
							<text class="title">{{ goods.name }}</text>
							<view class="bottom-row">
								<view class="price-line">
									<view class="price" :style="priceStyle">
										<block v-if="goods.is_negotiable == 1">面议</block>
										<block v-else><text class="cur">¥</text><text class="int">{{ priceInt(goods.price) }}</text><text class="dec">{{ priceDec(goods.price) }}</text></block>
									</view>
									<text class="origin origin-inline" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
									<text class="sold" v-if="value.show_sales == 1">{{ value.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
								<view class="buy-btn" v-if="value.show_buy_btn == 1">
									<view v-if="value.cart_type == 2" class="buy-btn-round" :style="roundBtnStyle">
										<tui-icon name="cart" :size="roundIconSize" color="#ffffff"></tui-icon>
									</view>
									<image v-else-if="value.cart_type == 1" :src="value.cart_pic" class="buy-btn-icon"></image>
									<view v-else class="buy-btn-text" :style="btnStyle">{{ value.cart_text }}</view>
								</view>
							</view>
							<text class="origin origin-below" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
						</view>
					</view>
				</scroll-view>
			</block>

			<block v-else-if="value.type == 2">
				<view class="goods-item list-layout" :style="cardStyle" v-for="(goods, index) in value.list" :key="index"
					@click="pageTo(goods)">
					<view class="image-wrapper">
						<image :src="thumbSrc(goods.cover_pic)" mode="aspectFill" class="goods-image" @error="thumbFail(goods.cover_pic)"></image>
						<view class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</view>
					</view>
					<view class="info-wrapper">
						<text class="title">{{ goods.name }}</text>
						<view class="bottom-row">
							<view class="price-line">
								<view class="price" :style="priceStyle">
									<block v-if="goods.is_negotiable == 1">面议</block>
									<block v-else><text class="cur">¥</text><text class="int">{{ priceInt(goods.price) }}</text><text class="dec">{{ priceDec(goods.price) }}</text></block>
								</view>
								<text class="origin origin-inline" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
								<text class="sold" v-if="value.show_sales == 1">{{ value.sales_text }} {{ goods.virtual_sales }}</text>
							</view>
							<view class="buy-btn" v-if="value.show_buy_btn == 1">
								<view v-if="value.cart_type == 2" class="buy-btn-round" :style="roundBtnStyle">
									<tui-icon name="cart" :size="roundIconSize" color="#ffffff"></tui-icon>
								</view>
								<image v-else-if="value.cart_type == 1" :src="value.cart_pic" class="buy-btn-icon"></image>
								<view v-else class="buy-btn-text" :style="btnStyle">{{ value.cart_text }}</view>
							</view>
						</view>
					</view>
				</view>
			</block>

			<block v-else>
				<block v-if="value.columns === 0">
					<view class="goods-item list-layout large-image-layout" :style="cardStyle" v-for="(goods, index) in value.list"
						:key="index" @click.stop.prevent="pageTo(goods)">
						<view class="image-wrapper">
							<image :src="thumbSrc(goods.cover_pic)" mode="aspectFill" class="goods-image" @error="thumbFail(goods.cover_pic)"></image>
							<view class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</view>
						</view>
						<view class="info-wrapper">
							<view class="title-large">{{ goods.name }}</view>
							<view class="bottom-row">
								<view class="price-line">
									<view class="price" :style="priceStyle">
										<block v-if="goods.is_negotiable == 1">面议</block>
										<block v-else><text class="cur">¥</text><text class="int">{{ priceInt(goods.price) }}</text><text class="dec">{{ priceDec(goods.price) }}</text></block>
									</view>
									<text class="origin origin-inline" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
									<text class="sold" v-if="value.show_sales == 1">{{ value.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
								<view class="buy-btn" v-if="value.show_buy_btn == 1">
									<view v-if="value.cart_type == 2" class="buy-btn-round" :style="roundBtnStyle">
										<tui-icon name="cart" :size="roundIconSize" color="#ffffff"></tui-icon>
									</view>
									<image v-else-if="value.cart_type == 1" :src="value.cart_pic" class="buy-btn-icon"></image>
									<view v-else class="buy-btn-text" :style="btnStyle">{{ value.cart_text }}</view>
								</view>
							</view>
						</view>
					</view>
				</block>

				<view v-if="value.columns == 1">
					<view class="goods-item card-layout" :style="cardStyle" v-for="(goods, index) in value.list" :key="index"
						@click="pageTo(goods)">
						<view class="image-wrapper" :style="imageStyle">
							<image :src="thumbSrc(goods.cover_pic)" mode="aspectFill" class="goods-image" @error="thumbFail(goods.cover_pic)"></image>
							<view class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</view>
						</view>
						<view class="info-wrapper">
							<text class="title">{{ goods.name }}</text>
							<view class="bottom-row">
								<view class="price-line">
									<view class="price" :style="priceStyle">
										<block v-if="goods.is_negotiable == 1">面议</block>
										<block v-else><text class="cur">¥</text><text class="int">{{ priceInt(goods.price) }}</text><text class="dec">{{ priceDec(goods.price) }}</text></block>
									</view>
									<text class="origin origin-inline" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
									<text class="sold" v-if="value.show_sales == 1">{{ value.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
								<view class="buy-btn" v-if="value.show_buy_btn == 1">
									<view v-if="value.cart_type == 2" class="buy-btn-round" :style="roundBtnStyle">
										<tui-icon name="cart" :size="roundIconSize" color="#ffffff"></tui-icon>
									</view>
									<image v-else-if="value.cart_type == 1" :src="value.cart_pic" class="buy-btn-icon"></image>
									<view v-else class="buy-btn-text" :style="btnStyle">{{ value.cart_text }}</view>
								</view>
							</view>
						</view>
					</view>
				</view>

				<view v-if="value.columns > 1" class="grid-layout" :class="'columns-' + value.columns">
					<view class="goods-item card-layout" :class="compact ? 'is-compact' : ''" :style="cardStyle" v-for="(goods, index) in value.list" :key="index"
						@click="pageTo(goods)">
						<view class="image-wrapper" :style="imageStyle">
							<image :src="thumbSrc(goods.cover_pic)" mode="aspectFill" class="goods-image" @error="thumbFail(goods.cover_pic)"></image>
							<view class="goods-badge" :class="'badge-' + badgeStyle" :style="badgeStyle === 'bar' ? badgeBarStyle : badgeSolidStyle" v-if="badgeText(goods)">{{ badgeText(goods) }}</view>
						</view>
						<view class="info-wrapper">
							<text class="title">{{ goods.name }}</text>
							<view class="bottom-row">
								<view class="price-line">
									<view class="price" :style="priceStyle">
										<block v-if="goods.is_negotiable == 1">面议</block>
										<block v-else><text class="cur">¥</text><text class="int">{{ priceInt(goods.price) }}</text><text class="dec">{{ priceDec(goods.price) }}</text></block>
									</view>
									<text class="origin origin-inline" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
									<text class="sold" v-if="value.show_sales == 1">{{ value.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
								<view class="buy-btn" v-if="value.show_buy_btn == 1">
									<view v-if="value.cart_type == 2" class="buy-btn-round" :style="roundBtnStyle">
										<tui-icon name="cart" :size="roundIconSize" color="#ffffff"></tui-icon>
									</view>
									<image v-else-if="value.cart_type == 1" :src="value.cart_pic" class="buy-btn-icon"></image>
									<view v-else class="buy-btn-text" :style="btnStyle">{{ value.cart_text }}</view>
								</view>
							</view>
							<text class="origin origin-below" v-if="hasOrigin(goods)">¥{{ originText(goods) }}</text>
						</view>
					</view>
				</view>
			</block>

		</view>
	</view>
</template>

<script>
import { thumbMixin } from '@/utils/thumb.js';

export default {
	mixins: [thumbMixin],
	props: {
		value: {
			type: Object,
			default: () => {
				return {}
			}
		},
	},
	data() {
		return {
			// JS部分保持不变
		}
	},
	computed: {
		/**
		 * 根容器样式:内边距 + 背景色,两者都由后台配置决定。
		 *
		 * 以前这两项都写死在 style 里(padding: 10rpx 10rpx 0; background: #f5f5f5),
		 * 于是后台的「左右边距」开关和「背景颜色」配了都不生效,
		 * 而且后台预览显示贴边、真机却恒有边距,两边对不上。
		 *
		 * 口径对齐 admin/src/components/diy/GoodsGroup.vue 的 outerStyle:
		 *   padding: is_padding == 1 ? '0 10px' : '0'
		 * 后台预览按 375px 宽渲染,1px = 2rpx,所以这里是 '0 20rpx'
		 * —— 与 banner / img-group / video-block 等其它 diy 组件的既有约定一致。
		 *
		 * background 兜底 #f5f5f5 与配置默认值相同,老配置没有这个键时行为不变。
		 */
		rootStyle() {
			return `padding:${this.value.is_padding == 1 ? '0 20rpx' : '0'};`;
		},
		/**
		 * 内层:背景色 + 圆角。
		 *
		 * 背景色放内层而不是外层,是为了和后台预览的分层保持一致 ——
		 * 开了「左右边距」时,那圈 padding 应该透出页面底色,而不是被背景色填满。
		 * 放外层的话开关一开就能看出两边不一样。
		 *
		 * 兜底用配置声明的默认值 #f5f5f5(不是后台那边写的 transparent):
		 * 老配置没有 background 这个键时,它当年的实际观感就是写死的 #f5f5f5,
		 * 兜到 transparent 会让这些页面凭空变样。后台预览侧一并改齐。
		 */
		innerStyle() {
			// 上下留白必须加在**内层**:CSS 的 background 会铺满自己的 padding 区,
			// 加在外层的话那 10rpx 是透明的、透出页面底色 —— 商品看起来仍然贴着背景色顶边。
			// (改动前 padding 和 background 同在根节点,所以顶部那 10rpx 本来就是背景色。)
			//
			// 上下一律由这里统一给,各布局「最后一个元素的下边距」要归零或抵消,
			// 否则底部会叠成两截(网格模式原本是顶 10rpx / 底 20rpx,肉眼能看出来)。
			return `background-color:${this.bgColor};padding:10rpx 0;`;
		},

		/** 背景色,统一从这里取,免得各处各写一遍兜底 */
		bgColor() {
			return this.value.background || '#f5f5f5';
		},

		/**
		 * 背景色的感知亮度(0~255)。
		 * 用 ITU-R BT.601 的加权(人眼对绿最敏感、蓝最钝),比直接平均 RGB 准得多。
		 * 解析不了的颜色(渐变、颜色名等)一律当浅色处理 —— 浅色是默认值,保守。
		 */
		bgLuminance() {
			const c = String(this.bgColor).trim();
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

		/**
		 * 卡片样式:圆角固定,但「靠什么把卡片从背景里立起来」要跟着背景色走。
		 *
		 * 阴影是黑色半透明的,而背景色运营可以随便配 —— 写死一种阴影必然有配色下失效:
		 *   · 深色底:黑影完全看不见,白加;深浅交界还会显脏 → 干脆不给阴影,
		 *            白卡片本身和深底的对比已经足够强
		 *   · 接近纯白:卡片也是白的,阴影太淡撑不住边界 → 补一条极淡描边
		 *   · 其余(含默认 #f5f5f5):正常给柔和阴影,效果最好
		 */
		cardStyle() {
			// 简约卡片:不要描边,只留一层很淡的暖色阴影(深色背景上照旧不加阴影)
			if (this.value.card_style === 'minimal' && this.bgLuminance >= 140) {
				return 'box-shadow:0 4rpx 16rpx rgba(42,37,34,0.08);border:none;';
			}
			if (this.bgLuminance < 140) {
				return 'box-shadow:none;';
			}
			if (this.bgLuminance > 245) {
				return 'box-shadow:0 1rpx 4rpx rgba(28,28,30,0.04);border:1rpx solid #eceef0;';
			}
			return 'box-shadow:0 2rpx 4rpx rgba(28,28,30,0.05),0 8rpx 24rpx rgba(28,28,30,0.05);';
		},

		/**
		 * 购买按钮:改成实心胶囊。
		 * 原来是 1rpx 描边 + 4rpx 内边距,在手机上触控目标太小(远低于 44px 的经验值),
		 * 而且描边按钮在花哨的商品图旁边存在感太弱。
		 * 五个渲染分支原本各写一遍相同的内联样式,统一收到这里。
		 */
		/**
		 * 封面高度。后台有个 100~800 的滑块,但移动端一直写死 1:1 正方形、
		 * 这个配置配了完全不生效(后台预览却一直在用,所以两边看着不一样)。
		 *
		 * 换算:后台预览按 375px 宽渲染(=750rpx),它用的是 `高度 / 2 + 'px'`,
		 * 默认 370 → 185px。185px 在 750rpx 的尺度下正好是 370rpx,
		 * 所以移动端直接把配置值当 rpx 用,两端观感一致。
		 *
		 * padding-top 必须显式归零 —— 基础样式用的是 `height:0 + padding-top:100%`
		 * 的比例盒写法,不清掉的话会在固定高度之外再叠一个正方形的高度。
		 *
		 * 只用于纵向卡片(滚动/单列/网格)。列表模式后台压根不显示这个配置项,
		 * 大图模式是横向布局、图片尺寸由布局定,两者都不该受它影响。
		 */
		imageStyle() {
			const h = parseInt(this.value.cover_pic_height) || 370;
			return `height:${h}rpx;padding-top:0;`;
		},

		/** 角标样式(pill/block/ribbon/dot/bar),老配置没这个键时给个默认 */
		badgeStyle() {
			const allow = ['pill', 'block', 'ribbon', 'dot', 'bar'];
			const v = this.value.badge_style;
			return allow.indexOf(v) > -1 ? v : 'pill';
		},
		/** 角标底色:留空则跟随按钮色,两者都没有再兜到默认红 */
		badgeColor() {
			return this.value.badge_color || this.value.cart_color || '#FF3C29';
		},
		/** 实心角标(胶囊/直角块/旗形/圆形)—— 纯色底 + 白字 */
		badgeSolidStyle() {
			return `background-color:${this.badgeColor};color:#ffffff;`;
		},
		/**
		 * 底部条:用同色渐变收尾,右侧淡出。
		 * 纯色横条会把封面下缘整条切断,商品图看着像被裁了一截。
		 */
		badgeBarStyle() {
			const c = this.badgeColor;
			return `background:linear-gradient(to right, ${c}, ${this.fade(c, 0.72)});color:#ffffff;`;
		},

		/**
		 * 三列(网格或横滑)卡片只有约 115px 宽,和两列共用字号时标题两行就截断、
		 * 四位数价格和按钮挤不下。三列单独走一套紧凑尺寸(.is-compact),销量也不显示。
		 */
		compact() {
			return this.value.columns == 3;
		},
		priceStyle() {
			return `color:${this.value.price_color || '#FF3C29'};`;
		},
		/** 圆形购物车按钮(cart_type=2):按钮色做底 + 同色淡投影,白色图标 */
		roundBtnStyle() {
			const c = this.value.cart_color || '#FF3C29';
			return `background-color:${c};box-shadow:0 4rpx 12rpx ${this.fade(c, 0.28)};`;
		},
		/** tui-icon 的 size 单位是 px */
		roundIconSize() {
			return this.compact ? 11 : 13;
		},

		btnStyle() {
			const c = this.value.cart_color || '#FF3C29';
			const r = this.value.is_circle == 1 ? '26rpx' : '8rpx';
			return `background-color:${c};color:#ffffff;border-color:${c};border-radius:${r};`;
		}
	},
	methods: {
		/**
		 * 角标文字:单品角标(item_badges[商品id],后台在商品列表里逐件填)优先;
		 * 没填才用整组角标(badge_show + badge_text)。单品角标填了就显示,不受整组开关影响。
		 */
		badgeText(goods) {
			const own = goods && this.value.item_badges ? this.value.item_badges[goods.id] : '';
			if (own) return own;
			return this.value.badge_show == 1 ? (this.value.badge_text || '') : '';
		},
		/** 把颜色转成指定透明度的 rgba;解析不了就原样返回(至少不会渲染成空) */
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
		},

		/**
		 * 价格拆成整数和小数两截:整数大、小数小,用户一眼先读到「68」。
		 * 后台下发的是 "68.00" 这样的字符串;非数字(理论上不会有)整段放整数位原样显示。
		 */
		priceInt(price) {
			const s = String(price == null ? '' : price);
			const i = s.indexOf('.');
			return i < 0 ? s : s.slice(0, i);
		},
		priceDec(price) {
			const s = String(price == null ? '' : price);
			const i = s.indexOf('.');
			const dec = i < 0 ? '' : s.slice(i);
			// 简约卡片:整数价不带「.00」,读起来更干净
			return this.value.card_style === 'minimal' && /^\.0+$/.test(dec) ? '' : dec;
		},
		/** 有原价且高于现价才显示划线价;原价 0.00 = 没填 */
		hasOrigin(goods) {
			if (goods.is_negotiable == 1) return false;
			const o = parseFloat(goods.origin_price);
			return o > 0 && o > parseFloat(goods.price);
		},
		/** 划线价只看个大概,整数就不带 .00(468.00 → 468) */
		originText(goods) {
			const o = parseFloat(goods.origin_price);
			return o % 1 === 0 ? String(o) : o.toFixed(2);
		},
		pageTo(row) {
			uni.navigateTo({
				url: "/others/goods/goods?id=" + row.id
			})
		}
	}
}
</script>

<style lang="scss" scoped>
// ⚠ padding 和 background 都不要写死在这里 ——
//   后台有「左右边距」开关和「背景颜色」配置项,写死会让它们配了不生效。
//   两者现由 computed.rootStyle 注入,口径与后台预览一致。
.goods-list-root {
	box-sizing: border-box;
}

.goods-list-wrapper {
	overflow: hidden;

	&.is-circle {
		// 20rpx = 后台预览的 10px(预览按 375px 宽渲染,1px = 2rpx)。
		// 原来写 10rpx,真机圆角只有运营在后台看到的一半。
		border-radius: 20rpx;
	}
}

/* ══════════ 商品角标 ══════════
   组件级统一角标:后台配一次文案+颜色+样式,该组件下所有商品都带同一个。
   底色/文字色由 computed 注入(badgeSolidStyle / badgeBarStyle),这里只管形状和位置。
   角标靠 .image-wrapper 的 overflow:hidden 裁切,所以必须放在它内部。 */
.goods-badge {
	position: absolute;
	z-index: 2;
	font-size: 20rpx;
	line-height: 1.5;
	white-space: nowrap;
	pointer-events: none;
}

/* 胶囊:离角有留白,重复出现时不压迫 —— 默认样式 */
.badge-pill {
	left: 12rpx;
	top: 12rpx;
	padding: 4rpx 16rpx;
	border-radius: 20rpx;
}

/* 直角块:贴角,外圆内直,存在感最强 */
.badge-block {
	left: 0;
	top: 0;
	padding: 4rpx 14rpx;
	border-radius: 12rpx 0 12rpx 0;
}

/* 斜角带:左上角 45° 压过去,靠父级 overflow 裁成三角。
   这里刻意不做「内凹尖角」或「右侧旗尖」—— 那两种要用和背景同色的三角去挖,
   而背景是商品图、颜色不固定,补不出来;clip-path 在小程序上支持又不稳。
   斜角带只需一个元素 + rotate,全平台都稳。 */
.badge-ribbon {
	/* 用百分比而不是固定 rpx:图片容器在各模式下尺寸差很多
	   (网格约 335rpx、大图 280rpx、列表只有 200rpx),
	   固定宽度在小图上会占掉一大块。百分比让它跟着图片缩放。 */
	left: -14%;
	top: 7%;
	width: 51%;
	padding: 6rpx 0;
	text-align: center;
	transform: rotate(-45deg);
}

/* 圆形:右上角,只放 1-2 字(后台已限字数) */
.badge-dot {
	right: 12rpx;
	top: 12rpx;
	width: 52rpx;
	height: 52rpx;
	border-radius: 50%;
	text-align: center;
	line-height: 52rpx;
	font-weight: 600;
}

/* 底部条:压在封面下缘,不挡商品主体,能放长文案。
   底色用渐变向右淡出(见 badgeBarStyle)—— 纯色横条会把封面下缘整条切断,
   商品图看着像被裁了一截。 */
.badge-bar {
	left: 0;
	right: 0;
	bottom: 0;
	padding: 6rpx 14rpx;
}

.goods-item {
	background-color: #FFFFFF;
	box-sizing: border-box;
	// 圆角统一在卡片自身,图片顶部不再各自设圆角(overflow 裁切即可)。
	// 20rpx 和外层圆角容器(.is-circle)一致;原来 12rpx,里外两种圆角看着散。
	// 阴影/描边由 computed.cardStyle 按背景色亮度决定,不写死在这里。
	border-radius: 20rpx;
	overflow: hidden;
	.image-wrapper {
		position: relative;
		width: 100%;
		height: 0;
		padding-top: 100%; // 宽高比 1:1
		overflow: hidden;
		.goods-image {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
		}
	}
	.info-wrapper {
		padding: 16rpx 20rpx 20rpx;
		.title,
		.title-large {
			font-size: 28rpx;
			color: #1d1d1f;
			line-height: 40rpx;
			// 固定两行高度:同一排卡片的价格行才能对齐
			height: 80rpx;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		// 价格 + 原价 + 销量一行,购买按钮在右。
		// 销量原来单独占一行、数字还用价格色,和价格抢视线;现在是价格后面的灰色小字,卡片也少一行。
		.bottom-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 14rpx;
		}
		.price-line {
			flex: 1;
			min-width: 0;
			display: flex;
			align-items: baseline;
			white-space: nowrap;
			overflow: hidden;
		}
		.price {
			flex-shrink: 0;
			font-weight: bold;
			line-height: 1;
			// 数字等宽,两张卡的价格竖着能对齐
			font-variant-numeric: tabular-nums;
			// 整数大、货币符号和小数小:用户真正要读的是整数
			.cur,
			.dec {
				font-size: 22rpx;
			}
			.cur {
				margin-right: 2rpx;
			}
			.int {
				font-size: 38rpx;
			}
		}
		.origin {
			font-size: 22rpx;
			color: #b0b0b4;
			text-decoration: line-through;
		}
		.origin-inline {
			flex-shrink: 0;
			margin-left: 8rpx;
		}
		// 只有紧凑模式才用(见 .is-compact)
		.origin-below {
			display: none;
		}
		// 空间不够时先让销量省略,价格和原价不被挤。
		// nowrap 必须写在它自己身上:H5 的 <text> 自带 pre-wrap,父级的 nowrap 管不到,会被挤成一字一行
		.sold {
			min-width: 0;
			white-space: nowrap;
			margin-left: 10rpx;
			font-size: 22rpx;
			color: #9a9a9e;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.buy-btn {
			flex-shrink: 0;
			margin-left: 8rpx;
			display: flex;
			.buy-btn-icon {
				width: 40rpx;
				height: 40rpx;
			}
			// 圆形购物车(cart_type=2),底色/投影由 computed.roundBtnStyle 注入
			.buy-btn-round {
				width: 48rpx;
				height: 48rpx;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			// 实心胶囊。原来是 1rpx 描边 + 4rpx 内边距,高度不到 40rpx,
			// 在手机上触控目标偏小,而且描边按钮挨着花哨的商品图存在感很弱。
			// 底色/文字色/圆角由 computed.btnStyle 注入。
			.buy-btn-text {
				height: 48rpx;
				line-height: 48rpx;
				padding: 0 22rpx;
				font-size: 24rpx;
				border: none;
				text-align: center;
			}
		}
	}
	// 三列紧凑尺寸:卡片只有约 230rpx 宽,整套缩一号;销量不显示,原价挪到价格下面
	&.is-compact .info-wrapper {
		padding: 12rpx 14rpx 16rpx;
		.title {
			font-size: 25rpx;
			line-height: 34rpx;
			height: 68rpx;
		}
		.bottom-row {
			margin-top: 10rpx;
		}
		.price {
			.cur,
			.dec {
				font-size: 20rpx;
			}
			.int {
				font-size: 30rpx;
			}
		}
		.sold,
		.origin-inline {
			display: none;
		}
		.origin-below {
			display: block;
			margin-top: 6rpx;
			font-size: 20rpx;
			line-height: 1;
		}
		.buy-btn {
			margin-left: 4rpx;
			.buy-btn-icon {
				width: 32rpx;
				height: 32rpx;
			}
			.buy-btn-round {
				width: 40rpx;
				height: 40rpx;
			}
			.buy-btn-text {
				height: 40rpx;
				line-height: 40rpx;
				padding: 0 14rpx;
				font-size: 22rpx;
			}
		}
	}
}
// 横向滚动
.scroll-x-view {
	white-space: nowrap;
	// 底部留白由 innerStyle 统一给,这里不再叠加(否则滚动模式底部是别处的两倍)。
	// 卡片阴影现在打在卡片自身上,不需要容器额外留空间。
	padding-bottom: 0;

	.scroll-item {
		display: inline-block;
		vertical-align: top;
		margin-right: 10rpx;

		&:last-child {
			margin-right: 0;
		}

		&.columns-1 {
			width: 75%;
		}

		&.columns-2 {
			width: 45%;
		}

		&.columns-3 {
			width: 31%;
		}
	}
}

// 大图模式
.large-image-layout {
	display: flex;
	margin-bottom: 10rpx;
	background: #fff;

	.image-wrapper {
		width: 280rpx;
		height: 280rpx;
		padding-top: 0; // 大图模式直接给高度
		flex-shrink: 0;
		border-radius: 10rpx;
	}

	.info-wrapper {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		.title-large {
			font-size: 30rpx;
			font-weight: bold;
			height: auto;
			-webkit-line-clamp: 3;
		}
	}
}

// 单列模式
.card-layout {
	margin-bottom: 10rpx;

	// 最后一个不再往下推,底部留白由 innerStyle 统一给
	&:last-child {
		margin-bottom: 0;
	}
}

// 网格模式
// 网格模式
.grid-layout {
	display: flex;
	flex-wrap: wrap;
	// 卡片的 margin-bottom 既是行间距、也会在最后一行多留一截。
	// 行间距要保住(20rpx,和左右 10+10 对称),所以只把最后一行那截抵消掉,
	// 底部留白统一交给 innerStyle 的 padding —— 这样上下才一样。
	// 用 :last-child 不行:两列时最后一行有两个元素,只归零一个会一高一低。
	margin-bottom: -20rpx;

	// [修复] 1. 移除父级的负边距
	// margin: 0 -5rpx; // <-- 移除这一行。calc() 算法不需要它。

	.goods-item {
		// [修复] 2. 这里的 margin 就是你想要的 "间隔"
		// 左右各 10rpx，总间隔就是 20rpx
		margin: 0 10rpx 20rpx 10rpx;

		// [修复] 3. 移除这里冲突的 width 属性
		// width: calc(50% - 0rpx); // <-- 移除这一行

	}

	// [修复] 4. 关键：宽度计算
	// width 必须减去你设置的 "总 margin" (10rpx + 10rpx = 20rpx)
	&.columns-2 .goods-item {
		width: calc(50% - 20rpx);
	}

	&.columns-3 .goods-item {
		width: calc(33.33% - 20rpx);
	}
}

// 图文列表模式
.list-layout {
	display: flex;
	padding: 16rpx 0;
	border-bottom: 1rpx solid #f5f5f5;
	// [优化] list-layout 模式在 .goods-list-root 的灰色背景下
	// 应该有自己的白色背景和圆角
	background-color: #fff;
	padding: 16rpx;
	margin-bottom: 10rpx;
	border-bottom: none; // 覆盖之前的

	&:last-child {
		border-bottom: none;
		// 最后一个不再往下推,底部留白由 innerStyle 统一给(大图模式也带 list-layout 类,一并覆盖)
		margin-bottom: 0;
	}

	.image-wrapper {
		width: 200rpx;
		height: 200rpx;
		padding-top: 0;
		flex-shrink: 0;
		border-radius: 10rpx;
	}

	.info-wrapper {
		flex: 1;
		padding-left: 20rpx;
	}
}

/* ── 简约卡片(后台「卡片风格 → 简约」):商品名小一号、颜色更柔,价格用窄体数字 ──
   写在最后、选择器多一层 .card-minimal,压过默认和紧凑模式的同名规则 */
.card-minimal .goods-item .info-wrapper {
	padding: 16rpx 18rpx 20rpx;
	.title,
	.title-large {
		font-size: 24rpx;
		line-height: 34rpx;
		height: 68rpx;
		color: #3a3532;
		font-weight: 400;
	}
	.price {
		font-family: "Avenir Next Condensed", "DIN Condensed", "DIN Alternate", "Helvetica Neue", sans-serif;
		font-weight: 800;
		.cur,
		.dec {
			font-size: 20rpx;
		}
		.int {
			font-size: 32rpx;
		}
	}
	.sold,
	.origin {
		font-size: 20rpx;
	}
}
.card-minimal .goods-item.is-compact .info-wrapper {
	padding: 12rpx 14rpx 16rpx;
	.title {
		font-size: 22rpx;
		line-height: 32rpx;
		height: 64rpx;
	}
	.price .int {
		font-size: 28rpx;
	}
}
</style>