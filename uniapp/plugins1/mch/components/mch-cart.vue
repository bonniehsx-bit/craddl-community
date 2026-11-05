<template>
	<view class="mch-cart-wrapper">

		<view class="cart-bar-container" :style="{ paddingBottom: safeAreaBottom + 'px' }">
			<view class="cart-bar blur-glass">
				<view class="cart-icon-box" :class="{'bump-ani': cartAni}" :style="{background: totalCount > 0 ? themeColor : '#333'}"
					@click="togglePopup">
					<tui-icon name="cart-fill" color="#fff" size="24"></tui-icon>
					<view class="badge" v-if="totalCount > 0" :style="{color: themeColor}">{{ totalCount }}</view>
				</view>

				<view class="price-box" @click="togglePopup">
					<block v-if="totalCount > 0">
						<view class="total" :class="{'bump-price': priceAni}">
							<text class="symbol">¥</text>{{ totalPrice.toFixed(2) }}
						</view>
						<view class="desc">免配送费</view>
					</block>
					<block v-else>
						<view class="empty-text">未选购商品</view>
						<view class="desc">另需配送费 ¥0</view>
					</block>
				</view>

				<button class="checkout-btn" :style="{background: totalCount > 0 ? themeColor : '#555'}"
					@click="handleCheckout">
					{{ totalCount > 0 ? '去结算' : '¥0起送' }}
				</button>
			</view>
		</view>

		<tui-bottom-popup :show="showPopup" @close="closePopup">
			<view class="cart-popup-content" :style="{paddingBottom: (safeAreaBottom + 60) + 'px'}">
				<view class="popup-header">
					<view class="left">
						<text class="title">已选商品</text>
						<text class="count">({{ totalCount }}件)</text>
					</view>
					<view class="clear-btn" @click="handleClear">
						<tui-icon name="delete" size="14" color="#999"></tui-icon>
						<text>清空</text>
					</view>
				</view>

				<scroll-view scroll-y class="popup-scroll">
					<view class="cart-item" v-for="(item, index) in cartItems">
						<image class="item-img" :src="item.image" mode="aspectFill"></image>
						<view class="item-info">
							<view class="name">{{ item.name }}</view>
							<view class="sku" v-if="item.sku_name">{{ item.sku_name }}</view>
							<view class="price-row">
								<view class="price" :style="{color: themeColor}">
									<text class="sym">¥</text>{{ item.price }}
								</view>

								<view class="stepper">
									<view class="stepper-btn minus" @click.stop="updateItem(item, -1)">
										<tui-icon name="reduce" size="14" color="#333"></tui-icon>
									</view>
									<text class="count">{{ item.count }}</text>
									<view class="stepper-btn plus" :style="{background: themeColor}"
										@click.stop="updateItem(item, 1)">
										<tui-icon name="plus" size="14" color="#fff"></tui-icon>
									</view>
								</view>
							</view>
						</view>
					</view>
				</scroll-view>
			</view>
		</tui-bottom-popup>

		<view class="mask-click-area" v-if="showPopup" @click="closePopup"></view>
	</view>
</template>

<script>
	export default {
		name: "mch-cart",
		props: {
			// 购物车商品列表
			cartItems: {
				type: Array,
				default: () => []
			},
			// 底部安全区高度 (从父组件传入或自行获取)
			safeAreaBottom: {
				type: Number,
				default: 0
			}
		},
		data() {
			return {
				showPopup: false,
				cartAni: false,
				priceAni: false
			};
		},
		computed: {
			// 计算总数量
			totalCount() {
				return this.cartItems.reduce((sum, item) => sum + item.count, 0);
			},
			// 计算总金额
			totalPrice() {
				return this.cartItems.reduce((sum, item) => sum + (item.count * item.price), 0);
			}
		},
		methods: {
			// 切换弹窗显示
			togglePopup() {
				if (this.totalCount > 0) {
					this.showPopup = !this.showPopup;
				}
			},
			closePopup() {
				this.showPopup = false;
			},
			// 更新数量（加/减）
			updateItem(item, change) {
				// 抛出事件给父组件处理 API 请求
				this.$emit('update', item, change);
			},
			// 清空购物车
			handleClear() {
				this.$emit('clear');
				this.showPopup = false;
			},
			// 结算
			handleCheckout() {
				if (this.totalCount === 0) return;
				this.$emit('checkout');
			}
		},
		// 监听购物车为空时自动关闭弹窗及触发动画
		watch: {
			totalCount(newVal, oldVal) {
				if (newVal === 0) {
					this.showPopup = false;
				}
				if (newVal > (oldVal || 0)) {
					this.cartAni = true;
					setTimeout(() => { this.cartAni = false; }, 300);
				}
			},
			totalPrice(newVal, oldVal) {
				if (newVal > (oldVal || 0)) {
					this.priceAni = true;
					setTimeout(() => { this.priceAni = false; }, 300);
				}
			}
		},
		emits: ['update', 'clear', 'checkout']
	}
</script>

<style lang="scss" scoped>
	.mch-cart-wrapper {
		position: relative;
		z-index: 999;
	}

	/* --- 悬浮购物车栏 --- */
	.cart-bar-container {
		position: fixed;
		bottom: 40rpx;
		left: 24rpx;
		right: 24rpx;
		z-index: 1000;
		/* 必须高于弹窗遮罩 */
	}

	.cart-bar {
		height: 100rpx;
		background: rgba(40, 40, 40, 0.85);
		border-radius: 50rpx;
		display: flex;
		align-items: center;
		padding: 0 24rpx;
		box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.25);
		position: relative;

		&.blur-glass {
			/* 毛玻璃效果支持 */
			backdrop-filter: blur(15px);
			border: 1rpx solid rgba(255,255,255,0.1);
		}

		.cart-icon-box {
			width: 96rpx;
			height: 96rpx;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			position: relative;
			top: -16rpx;
			border: 6rpx solid #f7f8fa;
			/* 制造悬浮缺口 */
			box-shadow: 0 -4rpx 10rpx rgba(0, 0, 0, 0.1);
			transition: background 0.3s;

			.badge {
				position: absolute;
				top: -6rpx;
				right: -6rpx;
				background: #fff;
				font-size: 20rpx;
				min-width: 36rpx;
				height: 36rpx;
				line-height: 36rpx;
				text-align: center;
				border-radius: 18rpx;
				padding: 0 6rpx;
				font-weight: bold;
				box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.2);
			}
		}

		.price-box {
			flex: 1;
			margin-left: 24rpx;
			display: flex;
			flex-direction: column;
			justify-content: center;

			.total {
				color: #fff;
				font-size: 36rpx;
				font-weight: bold;
				line-height: 1.2;

				.symbol {
					font-size: 24rpx;
					margin-right: 4rpx;
				}
			}

			.empty-text {
				color: #999;
				font-size: 28rpx;
			}

			.desc {
				color: #999;
				font-size: 20rpx;
			}
		}

		.checkout-btn {
			width: 200rpx;
			height: 100%;
			margin-right: -24rpx;
			border-radius: 0 50rpx 50rpx 0;
			font-size: 30rpx;
			font-weight: bold;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #fff;
			border: none;
			transition: background 0.3s;

			&::after {
				border: none;
			}

			&:active {
				opacity: 0.9;
			}
		}
	}

	/* --- 购物车详情弹窗 --- */
	.cart-popup-content {
		background: #fff;
		border-radius: 24rpx 24rpx 0 0;
		max-height: 70vh;
		display: flex;
		flex-direction: column;
		overflow: hidden;

		.popup-header {
			padding: 24rpx 30rpx;
			background: #f9f9f9;
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 26rpx;
			color: #666;
			flex-shrink: 0;

			.left {
				.title {
					font-size: 28rpx;
					font-weight: bold;
					color: #333;
				}

				.count {
					margin-left: 8rpx;
				}
			}

			.clear-btn {
				display: flex;
				align-items: center;
				gap: 8rpx;
			}
		}

		.popup-scroll {
			flex: 1;
			overflow-y: auto;
			min-height: 200rpx;
		}

		.cart-item {
			display: flex;
			padding: 24rpx 30rpx;
			border-bottom: 1rpx solid #f5f5f5;

			.item-img {
				width: 110rpx;
				height: 110rpx;
				border-radius: 8rpx;
				margin-right: 20rpx;
				flex-shrink: 0;
				background: #f5f5f5;
			}

			.item-info {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;

				.name {
					font-size: 28rpx;
					color: #333;
					margin-bottom: 4rpx;
					font-weight: 500;
				}

				.sku {
					font-size: 22rpx;
					color: #999;
					margin-bottom: 8rpx;
				}

				.price-row {
					display: flex;
					justify-content: space-between;
					align-items: center;

					.price {
						font-size: 32rpx;
						font-weight: bold;

						.sym {
							font-size: 22rpx;
							margin-right: 2rpx;
						}
					}

					.stepper {
						display: flex;
						align-items: center;
						gap: 20rpx;

						.stepper-btn {
							width: 44rpx;
							height: 44rpx;
							border-radius: 50%;
							display: flex;
							align-items: center;
							justify-content: center;

							&.minus {
								border: 1rpx solid #ddd;
								background: #fff;
							}

							&.plus {
								color: #fff;
							}
						}

						.count {
							font-size: 28rpx;
							min-width: 40rpx;
							text-align: center;
							color: #333;
						}
					}
				}
			}
		}
	}

	/* 防止点击穿透 */

	/* 加购碰撞动画 */
	@keyframes bump {
		0% { transform: scale(1); }
		50% { transform: scale(1.15); }
		100% { transform: scale(1); }
	}
	.bump-ani {
		animation: bump 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
	}

	@keyframes price-bump {
		0% { transform: translateY(0); color: #fff; }
		50% { transform: translateY(-4rpx); color: #FFC107; }
		100% { transform: translateY(0); color: #fff; }
	}
	.bump-price {
		animation: price-bump 0.3s ease-out;
		display: inline-block;
	}
</style>