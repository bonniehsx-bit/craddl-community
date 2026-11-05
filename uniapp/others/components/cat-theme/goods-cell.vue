<template>
	<!-- 事件名不用 tap:自定义组件上的 @tap 和原生 tap 同名,小程序端可能既走自定义
	     事件又走原生冒泡,导致点一次跳两次。全项目也没有第二处这么写。 -->
	<view class="g-cell" :class="['g-cell--' + mode, { pressed: pressed }]" @tap="$emit('select', goods)"
		@touchstart="pressed = true" @touchend="pressed = false" @touchcancel="pressed = false">
		<view class="g-pic" :class="'g-pic--' + mode">
			<image class="g-img" :class="{ loaded: loaded }" :src="goods.cover_pic" mode="aspectFill" lazy-load
				@load="loaded = true" @error="loaded = true" />
			<!-- 折扣角标压在图片左上角:比堆在标题里更容易扫到,也不占标题的两行 -->
			<view v-if="discount" class="g-discount">{{ discount }}折</view>
		</view>

		<view class="g-body">
			<view class="g-name" :class="'g-name--' + mode">
				<text v-if="tag" class="g-tag">{{ tag }}</text>{{ goods.name }}
			</view>

			<view v-if="mode === 'trend' && goods.subtitle" class="g-sub">
				<text class="g-subtitle">{{ goods.subtitle }}</text>
			</view>

			<view class="g-foot" :class="'g-foot--' + mode">
				<view class="g-price-wrap">
					<block v-if="goods.is_negotiable == 1">
						<text class="g-negotiable">价格面议</text>
					</block>
					<block v-else>
						<text class="g-cur">¥</text>
						<text class="g-price" :class="'g-price--' + mode">{{ priceMain }}</text>
						<text v-if="priceCent" class="g-cent">.{{ priceCent }}</text>
						<text v-if="showOrigin" class="g-origin">¥{{ goods.origin_price }}</text>
					</block>
				</view>
				<text class="g-sales">已售 {{ salesText }}</text>
			</view>
		</view>
	</view>
</template>

<script>
	/**
	 * 分类主题页的商品卡。三种形态(list / grid / trend)共用一个组件 ——
	 * 四套版式里商品卡的差别只是尺寸和行数,内容结构完全一致,拆成四个组件会让
	 * 「销量口径」「面议判断」这类逻辑散成四份,改一处漏三处。
	 */
	export default {
		name: 'goods-cell',
		props: {
			goods: { type: Object, default: () => ({}) },
			// list=横向列表 / grid=宫格(2列3列共用,靠外层网格控制列数) / trend=大卡
			mode: { type: String, default: 'list' }
		},
		data() {
			return {
				pressed: false,
				loaded: false
			}
		},
		computed: {
			/**
			 * 角标只用商品自己的真实标记,按「会员价 > 特价 > 推荐」取一个。
			 * 不叠多个:卡片就那么点地方,两三个彩色标签糊上去反而看不清商品名。
			 */
			tag() {
				if (this.goods.is_member_price == 1) return '会员价'
				if (this.goods.is_special_price == 1) return '特价'
				if (this.goods.is_recommend == 1) return '推荐'
				return ''
			},
			/** 整数部分和小数部分分开排版:小数用小字号,价格看起来更利落 */
			priceMain() {
				const p = String(this.goods.price == null ? '' : this.goods.price)
				const i = p.indexOf('.')
				return i > -1 ? p.slice(0, i) : p
			},
			priceCent() {
				const p = String(this.goods.price == null ? '' : this.goods.price)
				const i = p.indexOf('.')
				if (i < 0) return ''
				const c = p.slice(i + 1).replace(/0+$/, '')   // 去掉无意义的尾零
				return c
			},
			/** 划线价只在确实更高时才展示,否则会出现「¥99 划掉 ¥99」 */
			showOrigin() {
				const o = parseFloat(this.goods.origin_price)
				const p = parseFloat(this.goods.price)
				return o > 0 && o > p
			},
			/** 折扣角标。9.5 折以内才显示 —— 9.9 折标出来像在凑数,反而显廉价 */
			discount() {
				if (!this.showOrigin) return ''
				const o = parseFloat(this.goods.origin_price)
				const p = parseFloat(this.goods.price)
				const d = p / o * 10
				if (d >= 9.5 || d <= 0) return ''
				return d < 1 ? d.toFixed(1) : (Math.round(d * 10) / 10).toFixed(1).replace(/\.0$/, '')
			},
			/**
			 * 销量口径:优先虚拟销量(运营在商品编辑页填的),没填才回退真实销量。
			 * 后端两个字段都下发,不在服务端做替换 —— 否则会改掉所有列表接口的语义。
			 */
			salesText() {
				const v = parseInt(this.goods.virtual_sales) || 0
				const r = parseInt(this.goods.sales) || 0
				const n = v > 0 ? v : r
				return n >= 10000 ? (n / 10000).toFixed(1) + '万' : n
			}
		}
	}
</script>

<style scoped lang="scss">
	.g-cell {
		background: #fff;
		overflow: hidden;
		transition: opacity .15s;

		&.pressed {
			opacity: .6;
		}
	}

	/* ---------- 横向列表 ---------- */
	.g-cell--list {
		display: flex;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f4f4f4;
	}

	.g-pic--list {
		width: 190rpx;
		height: 190rpx;
		flex-shrink: 0;
		border-radius: 14rpx;
		margin-right: 22rpx;
	}

	.g-cell--list .g-body {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 6rpx 0 4rpx;
	}

	/* ---------- 宫格 ---------- */
	.g-cell--grid {
		border-radius: 14rpx;
		border: 1rpx solid #f0f0f0;
	}

	.g-pic--grid {
		width: 100%;
		height: 0;
		padding-bottom: 100%;   /* 正方形:图片高度随列宽自适应,三列时不会被压扁 */
		border-radius: 0;
	}

	.g-cell--grid .g-body {
		padding: 14rpx 14rpx 16rpx;
	}

	/* ---------- 大卡 ---------- */
	.g-cell--trend {
		display: flex;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f4f4f4;
	}

	.g-pic--trend {
		width: 240rpx;
		height: 240rpx;
		flex-shrink: 0;
		border-radius: 16rpx;
		margin-right: 24rpx;
	}

	.g-cell--trend .g-body {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	/* ---------- 图片 ---------- */
	.g-pic {
		position: relative;
		background: #f7f7f7;
		overflow: hidden;
	}

	.g-img {
		width: 100%;
		height: 100%;
		display: block;
		opacity: 0;
		transition: opacity .25s;
	}

	/* 宫格用 padding-bottom 撑高,图片得绝对定位才填得满 */
	.g-pic--grid .g-img {
		position: absolute;
		left: 0;
		top: 0;
		height: 100%;
	}

	.g-img.loaded {
		opacity: 1;
	}

	.g-discount {
		position: absolute;
		left: 0;
		top: 0;
		background: var(--theme-color);
		color: #fff;
		font-size: 20rpx;
		line-height: 1;
		padding: 6rpx 10rpx;
		border-radius: 0 0 10rpx 0;
	}

	/* ---------- 文字 ---------- */
	.g-name {
		font-size: 27rpx;
		color: #262626;
		line-height: 1.45;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.g-name--grid {
		font-size: 25rpx;
		min-height: 72rpx;
	}

	.g-name--trend {
		font-size: 29rpx;
	}

	/* 角标做成内联,跟标题同一段文字流:标题短的时候不会在上面留一条空行 */
	.g-tag {
		display: inline-block;
		font-size: 19rpx;
		line-height: 28rpx;
		height: 28rpx;
		padding: 0 8rpx;
		margin-right: 8rpx;
		border-radius: 6rpx;
		background: var(--theme-color-bg);
		color: var(--theme-color);
		vertical-align: 2rpx;
	}

	.g-sub {
		margin-top: 10rpx;
	}

	.g-subtitle {
		font-size: 23rpx;
		color: #9a9a9a;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 1;
		-webkit-box-orient: vertical;
	}

	.g-foot {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		margin-top: 12rpx;
	}

	.g-foot--grid {
		flex-direction: column;
		align-items: flex-start;
		margin-top: 10rpx;
	}

	/* 三列时每格可用宽只有 180rpx 上下,「¥39800 ¥49800」这种会把卡片撑破。
	   裁掉超出部分(先裁划线价)比撑破布局好看 */
	.g-price-wrap {
		display: flex;
		align-items: baseline;
		min-width: 0;
		max-width: 100%;
		overflow: hidden;
	}

	.g-cur {
		font-size: 22rpx;
		color: var(--theme-color);
		margin-right: 2rpx;
	}

	/* 价格本体不许被压缩 —— 宽度不够时该让划线价先消失,而不是把价格数字挤掉 */
	.g-price {
		font-size: 36rpx;
		font-weight: 600;
		color: var(--theme-color);
		line-height: 1.1;
		flex-shrink: 0;
	}

	.g-price--grid {
		font-size: 32rpx;
	}

	.g-price--trend {
		font-size: 40rpx;
	}

	.g-cent {
		font-size: 24rpx;
		font-weight: 600;
		color: var(--theme-color);
	}

	.g-origin {
		font-size: 22rpx;
		color: #c0c0c0;
		text-decoration: line-through;
		margin-left: 10rpx;
	}

	.g-negotiable {
		font-size: 28rpx;
		color: var(--theme-color);
	}

	.g-sales {
		font-size: 22rpx;
		color: #9a9a9a;
		flex-shrink: 0;
	}

	.g-foot--grid .g-sales {
		margin-top: 6rpx;
	}
</style>
