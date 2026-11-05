<template>
	<view class="seckill-panel">
		<view class="head">
			<text class="title">限时秒杀</text>
			<text class="act" v-if="data.activity_name">{{ data.activity_name }}</text>
		</view>

		<view class="rows">
			<view class="row">
				<text class="k">秒杀价</text>
				<view class="v-box">
					<text class="v-main">¥{{ data.price }}</text>
					<text class="v-old" v-if="showOrigin">¥{{ data.origin_price }}</text>
				</view>
			</view>
			<!-- 「省了多少」是秒杀订单上用户最想看到的一行，
			     商品卡只显示成交价，省了多少要自己拿原价去减 -->
			<view class="row" v-if="showSaved">
				<text class="k">已省</text>
				<text class="v-save">¥{{ data.saved }}</text>
			</view>
			<view class="row" v-if="data.num">
				<text class="k">抢购数量</text>
				<text class="v">{{ data.num }} 件</text>
			</view>
			<view class="row" v-if="data.grab_time">
				<text class="k">抢购时间</text>
				<text class="v">{{ data.grab_time }}</text>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'SeckillInfoPanel',
	props: {
		data: { type: Object, required: true },
		order: { type: Object, default: () => ({}) },
		list: { type: Array, default: () => [] }
	},
	computed: {
		showOrigin() {
			return Number(this.data.origin_price) > Number(this.data.price);
		},
		showSaved() {
			return Number(this.data.saved) > 0;
		}
	}
};
</script>

<style lang="scss" scoped>
	.seckill-panel {
		background: #fff;
		border-radius: 16rpx;
		padding: 24rpx;
	}

	.head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 8rpx;
	}

	.title {
		font-size: 30rpx;
		font-weight: 600;
		color: #222;
	}

	.act {
		font-size: 24rpx;
		color: #999;
		max-width: 380rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.rows {
		margin-top: 8rpx;
	}

	.row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 10rpx 0;
	}

	.k {
		font-size: 26rpx;
		color: #888;
	}

	.v {
		font-size: 26rpx;
		color: #333;
	}

	.v-box {
		display: flex;
		align-items: baseline;
	}

	.v-main {
		font-size: 28rpx;
		font-weight: 700;
		color: #ff5b5b;
	}

	.v-old {
		margin-left: 12rpx;
		font-size: 24rpx;
		color: #bbb;
		text-decoration: line-through;
	}

	.v-save {
		font-size: 28rpx;
		font-weight: 700;
		color: #ff5b5b;
	}
</style>
