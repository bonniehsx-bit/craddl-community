<template>
	<view class="mch-product-list-wrap" :style="themeStyle">
		<tui-no-data v-if="!list || list.length === 0" :fixed="false">该商家暂未上架商品哦</tui-no-data>
		<view class="content-box" v-else>

			<scroll-view class="category-sidebar" scroll-y :scroll-top="sidebarScrollTop">
				<view class="category-item" v-for="(cat, index) in list" :key="cat.id"
					:class="{ 'active': activeIndex === index }" @click="handleCategoryClick(index)">

					<view class="active-bar" v-if="activeIndex === index" :style="{ background: themeColor }"></view>

					<text class="cat-name" :class="{ 'active-text': activeIndex === index }">
						{{ cat.name }}
					</text>

				</view>
				<view class="sidebar-spacer"></view>
			</scroll-view>

			<scroll-view class="product-main" scroll-y :scroll-into-view="scrollIntoViewId" scroll-with-animation
				@scroll="onMainScroll">

				<view class="product-container">
					<view class="product-group" v-for="(level1Cat, index) in list" :key="level1Cat.id"
						:id="'category-' + index">

						<view class="group-title sticky-title">{{ level1Cat.name }}</view>

						<template v-if="level1Cat.products && level1Cat.products.length > 0">
							<view class="product-item" v-for="product in level1Cat.products" :key="product.id"
								@click="goDetail(product)">
								<image class="prod-img" :src="product.cover_pic" mode="aspectFill"></image>

								<view class="prod-info">
									<view class="name">{{ product.name }}</view>
									<view class="desc" v-if="product.intro">{{ product.intro }}</view>

									<view class="tags-row">
										<text class="sales">月售 {{ product.sales || 0 }}</text>

									</view>

									<view class="price-row">
										<view class="price" :style="{ color: themeColor }">
											<text class="sym">¥</text>{{ product.price }}
										</view>

										<view class="stepper">
											<transition name="fade">
												<view class="stepper-group" v-if="cartMap[product.id] > 0">
													<view class="btn minus" @click.stop="emitAction('minus', product)">
														<tui-icon name="reduce" size="16" color="#333"></tui-icon>
													</view>
													<text class="num">{{ cartMap[product.id] }}</text>
												</view>
											</transition>
											<view class="btn plus" :style="{ background: themeColor }"
												@click.stop="emitAction('plus', product)">
												<tui-icon name="plus" size="16" color="#fff"></tui-icon>
											</view>
										</view>
									</view>
								</view>
							</view>
						</template>

						<template v-if="level1Cat.children && level1Cat.children.length > 0">
							<view class="sub-group" v-for="level2Cat in level1Cat.children" :key="level2Cat.id">
								<view class="sub-title">{{ level2Cat.name }}</view>

								<view class="product-item" v-for="product in level2Cat.products" :key="product.id"
									@click="goDetail(product)">
									<image class="prod-img" :src="product.cover_pic" mode="aspectFill"></image>
									<view class="prod-info">
										<view class="name">{{ product.name }}</view>
										<view class="desc" v-if="product.intro">{{ product.intro }}</view>
										<view class="tags-row">
											<text class="sales">月售 {{ product.sales || 0 }}</text>
										</view>
										<view class="price-row">
											<view class="price" :style="{ color: themeColor }">
												<text class="sym">¥</text>{{ product.price }}
											</view>
											<view class="stepper">
												<view class="stepper-group" v-if="cartMap[product.id] > 0">
													<view class="btn minus" @click.stop="emitAction('minus', product)">
														<tui-icon name="reduce" size="16" color="#333"></tui-icon>
													</view>
													<text class="num">{{ cartMap[product.id] }}</text>
												</view>
												<view class="btn plus" :style="{ background: themeColor }"
													@click.stop="emitAction('plus', product)">
													<tui-icon name="plus" size="16" color="#fff"></tui-icon>
												</view>
											</view>
										</view>
									</view>
								</view>
							</view>
						</template>
					</view>

					<view class="bottom-spacer"></view>
				</view>
			</scroll-view>
		</view>
	</view>
</template>

<script>
export default {
	name: "mch-product-list",
	props: {
		// 商品分类列表 (包含嵌套的 products)
		list: {
			type: Array,
			default: () => []
		},
		// 购物车数量映射表 { goods_id: count }
		cartMap: {
			type: Object,
			default: () => ({})
		}
	},
	data() {
		return {
			activeIndex: 0, // 当前激活的分类索引
			scrollIntoViewId: '', // 右侧滚动目标ID
			sidebarScrollTop: 0, // 左侧滚动位置

			// 滚动联动相关
			categoryTops: [], // 各分类的顶部高度数组
			isClickScroll: false, // 锁：是否是点击分类触发的滚动
			scrollTimer: null,
		};
	},
	watch: {
		// 监听数据变化，重新计算高度锚点
		list: {
			handler() {
				this.$nextTick(() => {
					this.calculateCategoryTops();
				});
			},
			deep: true,
			immediate: true
		}
	},
	methods: {// 点击左侧分类
		handleCategoryClick(index) {
			if (this.activeIndex === index) return;

			this.isClickScroll = true; // 加锁，防止触发 scroll 事件的计算
			this.activeIndex = index;
			this.scrollIntoViewId = `category-${index}`;

			// 500ms 后解锁
			setTimeout(() => {
				this.isClickScroll = false;
			}, 500);
		},

		// 右侧滚动监听
		onMainScroll(e) {
			if (this.isClickScroll) return; // 如果是点击触发的，不计算

			// 节流处理
			if (this.scrollTimer) clearTimeout(this.scrollTimer);
			this.scrollTimer = setTimeout(() => {
				const scrollTop = e.detail.scrollTop;
				let current = 0;

				// 遍历高度数组，找到当前可视区域对应的分类
				// 这里的 10 是一个缓冲值，或者可以是 sticky 标题的高度
				for (let i = this.categoryTops.length - 1; i >= 0; i--) {
					if (scrollTop >= this.categoryTops[i] - 10) {
						current = i;
						break;
					}
				}

				if (this.activeIndex !== current) {
					this.activeIndex = current;
					// 让左侧分类尽量居中显示 (大概每个分类元素高 50px)
					this.sidebarScrollTop = Math.max(0, current * 50 - 150); 
				}
			}, 50);
		},

		// 计算各分类块的高度 Top 值
		calculateCategoryTops() {
			const query = uni.createSelectorQuery().in(this);
			query.selectAll('.product-group').boundingClientRect(data => {
				if (data && data.length > 0) {
					// 第一个元素作为基准点
					const startTop = data[0].top;
					this.categoryTops = data.map(item => item.top - startTop);
				}
			}).exec();
		},

		// 抛出加减购事件 (带震动触感)
		emitAction(type, product) {
			uni.vibrateShort();
			this.$emit(type, product);
		},

		// 跳转详情
		goDetail(product) {
			this.$emit('detail', product);
		}
	},
	emits: ['plus', 'minus', 'detail']
}
</script>

<style lang="scss" scoped>
.mch-product-list-wrap {
	width: 100%;
	height: 100%;
	background: #fff;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.content-box {
	flex: 1;
	min-height: 0;
	display: flex;
	width: 100%;
	background: #f7f8fa;
}

/* 左侧分类侧边栏 */
.category-sidebar {
	width: 180rpx;
	height: 100%;
	background: #f7f8fa;
	flex-shrink: 0;

	.category-item {
		min-height: 100rpx;
		padding: 24rpx 10rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
		box-sizing: border-box;

		&.active {
			background: #fff;
		}

		.cat-name {
			font-size: 26rpx;
			color: #666;
			text-align: center;
			line-height: 1.4;

			&.active-text {
				color: #333;
				font-weight: bold;
				font-size: 28rpx;
			}
		}

		/* 选中指示条 */
		.active-bar {
			position: absolute;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			width: 8rpx;
			height: 36rpx;
			border-radius: 0 6rpx 6rpx 0;
		}
	}

	.sidebar-spacer {
		height: 160rpx;
	}
}

/* 右侧商品主列表 */
.product-main {
	flex: 1;
	height: 100%;
	background: #fff;

	.product-container {
		padding: 0 24rpx;
	}

	.group-title {
		font-size: 26rpx;
		color: #333;
		font-weight: bold;
		margin: 0 -24rpx 20rpx;
		padding: 16rpx 24rpx;
		background: rgba(247, 248, 250, 0.9);
		backdrop-filter: blur(5px);
		line-height: 1.2;

		&.sticky-title {
			position: sticky;
			top: 0;
			z-index: 9;
		}
	}

	.sub-group {
		.sub-title {
			font-size: 24rpx;
			color: #999;
			margin: 12rpx 0;
			padding-left: 12rpx;
			border-left: 4rpx solid #eee;
		}
	}

	.product-item {
		display: flex;
		padding: 24rpx 0;
		// border-bottom: 1rpx solid #f9f9f9;
		position: relative;

		&:not(:last-child) {
			margin-bottom: 12rpx;
		}

		.prod-img {
			width: 180rpx;
			height: 180rpx;
			border-radius: 20rpx;
			margin-right: 24rpx;
			flex-shrink: 0;
			background: #f5f5f5;
			box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.03);
		}

		.prod-info {
			flex: 1;
			min-width: 0;
			display: flex;
			flex-direction: column;
			justify-content: space-between;

			.name {
				font-size: 30rpx;
				font-weight: bold;
				color: #333;
				line-height: 1.4;
				margin-bottom: 6rpx;

				/* 限制2行 */
				display: -webkit-box;
				-webkit-box-orient: vertical;
				-webkit-line-clamp: 2;
				overflow: hidden;
			}

			.desc {
				font-size: 22rpx;
				color: #999;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				margin-bottom: 8rpx;
			}

			.tags-row {
				display: flex;
				align-items: center;
				gap: 10rpx;
				margin-bottom: 12rpx;

				.sales {
					font-size: 22rpx;
					color: #999;
				}


			}

			.price-row {
				display: flex;
				justify-content: space-between;
				align-items: flex-end;

				.price {
					font-size: 32rpx;
					font-weight: bold;
					line-height: 1;

					.sym {
						font-size: 24rpx;
						margin-right: 2rpx;
					}
				}

				.stepper {
					display: flex;
					align-items: center;

					.stepper-group {
						display: flex;
						align-items: center;
					}

					.btn {
						width: 44rpx;
						height: 44rpx;
						border-radius: 50%;
						display: flex;
						align-items: center;
						justify-content: center;

						&.minus {
							border: 1rpx solid #e0e0e0;
							background: #fff;
						}

						&.plus {
							color: #fff;
							box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.1);
						}

						&:active {
							opacity: 0.8;
							transform: scale(0.95);
						}
					}

					.num {
						font-size: 28rpx;
						min-width: 50rpx;
						text-align: center;
						color: #333;
						font-weight: 500;
					}
				}
			}
		}
	}

	.bottom-spacer {
		height: calc(env(safe-area-inset-bottom) + 160rpx);
		width: 100%;
	}
}

/* 减号出现动画 */
.fade-enter-active,
.fade-leave-active {
	transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
	opacity: 0;
	transform: translateX(20rpx) rotate(180deg);
}
</style>