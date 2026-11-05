<template>
	<view class="goods-recommend" v-if="list.length || loadding || recommendError" :style="themeStyle">
		<view class="recommend-heading"><view class="recommend-bar"></view><text>推荐好物</text></view>
		<view class="recommend-grid">
			<view class="recommend-cell" v-for="(item, index) in list" :key="index">
				<tui-goods-item :goods="item"></tui-goods-item>
			</view>
		</view>
		<tui-loadmore v-if="loadding" :index="3"></tui-loadmore>
		<view v-else-if="recommendError" class="recommend-retry" @click="getRecommend()">加载失败，点击重试</view>
	</view>
</template>

<script>
import goodsRecommend from '@/mixins/goods-recommend.js';
export default {
	name: 'GoodsRecommend',
	mixins: [goodsRecommend],
	props: { excludeId: { type: [Number, String], default: 0 } }
};
</script>

<style scoped>
.goods-recommend { padding: 20rpx 10rpx 0; }
.recommend-heading { display: flex; align-items: center; padding: 12rpx 10rpx 24rpx; color: #333; font-size: 30rpx; font-weight: 600; }
.recommend-bar { width: 6rpx; height: 28rpx; border-radius: 4rpx; margin-right: 14rpx; background: var(--theme-color); }
.recommend-grid { display: flex; flex-wrap: wrap; }
.recommend-cell { width: 50%; min-width: 0; box-sizing: border-box; padding: 0 10rpx 10rpx; }
.recommend-retry { padding: 28rpx; text-align: center; color: #888; font-size: 24rpx; }
</style>
