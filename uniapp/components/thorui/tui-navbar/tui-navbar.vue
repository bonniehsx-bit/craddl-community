<template>
	<view class="premium-navbar-container" :style="outerStyle">
		<view class="navbar-inner" :style="innerStyle">
			<view class="nav-grid" :style="gridStyle">
				<block v-for="(item, index) in xc.list" :key="index">
					<view class="nav-item" :style="'width:' + itemWidth" @click="navClick(item)">
						<!-- 图片模式 -->
						<view class="icon-wrap" v-if="xc.style == 0" :style="iconStyle">
							<image :src="item.pic_url" mode="aspectFill" class="icon"></image>
						</view>
						<!-- 文字部分 -->
						<text class="nav-name" :style="textStyle">{{ item.name }}</text>
					</view>
				</block>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'tui-navbar',
	props: {
		value: {
			type: Object,
			default: () => ({})
		}
	},
	computed: {
		xc() {
			let v = this.value || {};
			return {
				list: v.list || [],
				style: v.style !== undefined ? v.style : 0,
				columns: v.columns || 4,
				is_padding: v.is_padding !== undefined ? v.is_padding : '0',
				is_circle: v.is_circle !== undefined ? v.is_circle : '0',
				background: v.background || '#ffffff',
				color: v.color || '#333333',
				font_size: v.font_size || 14,
				icon_size: v.icon_size || 44
			};
		},
		outerStyle() {
			const pad = this.xc.is_padding == '1' ? '0 24rpx' : '0';
			return `padding:${pad};width:100%;box-sizing:border-box;background-color:transparent`;
		},
		innerStyle() {
			const radius = this.xc.is_circle == '1' ? '20rpx' : '0';
			return `background-color:${this.xc.background};border-radius:${radius};overflow:hidden`;
		},
		gridStyle() {
			return 'display:flex;flex-wrap:wrap;padding:24rpx 0 0;width:100%';
		},
		itemWidth() {
			const cols = this.xc.columns || 4;
			return (100 / cols).toFixed(4) + '%';
		},
		iconStyle() {
			let size = this.xc.icon_size * 2;
			return `width:${size}rpx;height:${size}rpx`;
		},
		textStyle() {
			return `font-size:${this.xc.font_size * 2}rpx;color:${this.xc.color}`;
		}
	},
	methods: {
		navClick(e) {
			if (!e.url) return;
			if (!e.type || e.type == 'navigate') {
				uni.navigateTo({ url: e.url });
			} else if (e.type == 'redirect') {
				uni.redirectTo({ url: e.url });
			} else if (e.type == 'reLaunch') {
				uni.reLaunch({ url: e.url });
			} else {
				this.$T.go(e.url);
			}
		}
	}
}
</script>

<style scoped>
.premium-navbar-container {
	width: 100%;
}
.navbar-inner {
	width: 100%;
}
.nav-grid {
	box-sizing: border-box;
}
.nav-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	margin-bottom: 20rpx;
	box-sizing: border-box;
}
.icon-wrap {
	display: flex;
	justify-content: center;
	align-items: center;
	margin-bottom: 12rpx;
}
.icon {
	width: 100%;
	height: 100%;
	display: block;
}
.nav-name {
	display: block;
	text-align: center;
	font-weight: 400;
	line-height: 1.2;
	max-width: 90%;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
</style>
