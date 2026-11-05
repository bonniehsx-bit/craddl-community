<template>
	<view class="tui-search-wrap">
		<view class="tui-searchbox">
			<view class="tui-rolling-search" @click.stop="searchClick"
				:style="{ borderRadius: value.is_circle ? '35rpx' : '10rpx', backgroundColor: value.background }">
				<icon type="search" :size='13' color='#999'></icon>
				<view style="font-size: 10pt;color: #A1A1A1;padding: 0 20rpx;">{{ value.tips }}</view>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	props: {
		value: {
			type: Object,
			default: () => {
				return null

			}
		}
	},
	data() {
		return {

		}
	},
	methods: {
		searchClick() {
			uni.navigateTo({
				url: '/others/search/search'
			})
		},

	}
}
</script>

<style>
.tui-search-wrap {
	padding: 10rpx 24rpx;
	box-sizing: border-box;
}

.tui-searchbox {
	box-sizing: border-box;
}

.tui-rolling-search {
	width: 100%;
	height: 72rpx;
	padding: 0 30rpx;
	box-sizing: border-box;
	background: #fff;
	display: flex;
	align-items: center;
	flex-wrap: nowrap;
	color: #999;
	box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
}
</style>
