<template>
	<tui-bottom-popup :show="show" @close="close">
		<view class="picker-container">
			<view class="picker-header">
				<view class="title">选择在售门店</view>
				<tui-icon name="close-fill" color="#999" :size="20" @click="close"></tui-icon>
			</view>

			<scroll-view scroll-y class="mch-scroll-view">
				<view class="mch-list">
					<view class="mch-item" v-for="item in mchList" :key="item.id" @click="onSelect(item)">
						<image class="logo" :src="item.logo" mode="aspectFill"></image>
						<view class="mch-info">
							<view class="name">{{ item.mch_name }}</view>
							<view class="desc">{{ item.address }}</view>
							<view class="distance">{{ item.distance_text }}</view>
						</view>
						<view class="checkbox">
							<tui-icon name="arrowright" color="#ccc"
								:size="22"></tui-icon>
						</view>
					</view>
				</view>
			</scroll-view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "mch-picker",
		props: {
			// 从父组件传入所有可选的门店列表
			list: {
				type: Array,
				default: () => []
			},
			// 从父组件传入当前选中的门店ID，用于高亮显示
			initialId: {
				type: [Number, String],
				default: 0
			}
		},
		data() {
			return {
				show: false,
				mchList: [],
				selectedId: 0
			};
		},
		methods: {
			open() {
				// 每次打开时，都从父组件同步最新的列表和选中ID
				this.mchList = this.list;
				this.selectedId = this.initialId;
				this.show = true;
			},
			close() {
				this.show = false;
			},
			onSelect(mch) {
				this.$T.go('/plugins1/mch/index?id=' + mch.id)
				this.$emit('confirm', mch);
				this.close();
			}
		}
	}
</script>

<style lang="scss" scoped>
	.picker-container {
		background-color: #fff;
		border-top-left-radius: 20rpx;
		border-top-right-radius: 20rpx;
		padding: 30rpx 30rpx 0 30rpx;
	}

	.picker-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-bottom: 20rpx;

		.title {
			font-size: 32rpx;
			font-weight: bold;
			color: #333;
		}
	}

	.mch-scroll-view {
		/* 计算一个合适的高度，例如屏幕高度的60% */
		height: 60vh;
	}

	.mch-item {
		display: flex;
		align-items: center;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f0f0f0;

		&:last-child {
			border-bottom: none;
		}

		.logo {
			width: 100rpx;
			height: 100rpx;
			border-radius: 8rpx;
			margin-right: 24rpx;
			flex-shrink: 0;
		}

		.mch-info {
			flex: 1;
			overflow: hidden;

			.name {
				font-size: 30rpx;
				color: #333;
				font-weight: 500;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.desc,
			.distance {
				font-size: 24rpx;
				color: #999;
				margin-top: 8rpx;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.distance {
				color: #ff5733;
			}
		}

		.checkbox {
			margin-left: 24rpx;
		}
	}
</style>