<template>
	<tui-page :title="$noun('coupon')" :is-pulldown="true" @pulldown="onPullDownRefresh" @reach-bottom="onReachBottom">
		<view class="page-container">
			<!-- 吸顶偏移走 tui-page 的 --tui-nav-height(H5 无自绘栏时自动为 0),不再自算 statusBar+44 -->
			<view class="tabs-container">
				<tui-tabs :tabs="navbar" selectedColor="#E43D3D" sliderBgColor="#E43D3D" :currentTab="currentTab"
					@change="tabChange" itemWidth="33.333%"></tui-tabs>
			</view>

			<view class="coupon-list">
				<view class="coupon-card" :class="{ 'is-disabled': currentTab !== 0 }"
					v-for="(item,index) in list" :key="item.id">
					
					<!-- 左侧：金额/折扣 -->
					<view class="card-left">
						<view class="price-wrapper">
							<block v-if="item.type == 0"> <text class="symbol">¥</text>
								<text class="price">{{ item.sub_price }}</text>
							</block>
							<block v-if="item.type == 1"> <text class="price">{{ item.discount }}</text>
								<text class="symbol">折</text>
							</block>
						</view>
						<view class="condition">{{ item.description }}</view>
					</view>

					<!-- 右侧：详情与操作 -->
					<view class="card-right">
						<view class="details">
							<view class="title-line">
								<view class="tag">{{ item.appoint_type === 'all' ? '全场通用' : '指定可用' }}</view>
								<view class="title">{{ item.name }}</view>
							</view>

							<view class="middle-content">
								<view class="mch-info" v-if="item.mch && item.mch.mch_name">
									<tui-icon name="shop" :size="14" color="#999"></tui-icon>
									<text class="mch-name-text">{{ item.mch.mch_name }}</text>
								</view>

								<view class="date">
									{{ item.start_at_text }} - {{ item.end_at_text }}
								</view>
							</view>
						</view>

						<!-- 操作按钮区 (仅在未使用状态下显示) -->
						<view class="action-btn" v-if="currentTab === 0">
							<button class="btn use" @click="goUse(item)">去使用</button>
							<!-- 新增转赠按钮 -->
							<button class="btn transfer" v-if="item.is_transferable==1" @click="goTransfer(item)">转赠</button>
						</view>
					</view>

					<!-- 状态印章 (已使用/已过期) -->
					<view class="status-stamp" v-if="currentTab === 1"
						:style="{ backgroundImage: 'url(' + icons.used + ')' }"></view>
					<view class="status-stamp" v-if="currentTab === 2"
						:style="{ backgroundImage: 'url(' + icons.failure + ')' }"></view>
				</view>
			</view>

			<uni-load-more v-if="list.length > 0 || loading" :status="loadStatus" />
			<tui-no-data v-if="!list.length && !loading" :fixed="false">暂无{{ $noun('coupon') }}</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	import TuiTabs from '@/others/components/tui-tabs/tui-tabs.vue'
	export default {
		components: { TuiTabs },
		data() {
			return {
				currentTab: 0,
				navbar: [
					{ name: "未使用", status: 0 },
					{ name: "已使用", status: 1 },
					{ name: "已过期", status: 2 }
				],
				loading: false,
				page: 1,
				limit: 15,
				list: [],
				loadStatus: 'loadmore',
				icons: {},
				navBarHeight: 0,
			}
		},
		created() {
			// 与 tui-page 自定义导航栏高度保持一致，供吸顶 tab 偏移使用
			let statusBarHeight = 20;
			let customBarHeight = 44;
			const systemInfo = uni.getSystemInfoSync();
			if (systemInfo.statusBarHeight) {
				statusBarHeight = systemInfo.statusBarHeight;
			}
			// #ifdef H5
			statusBarHeight = 0;
			// #endif
			// #ifndef APP-PLUS || H5 || MP-ALIPAY
			if (typeof uni.getMenuButtonBoundingClientRect === 'function') {
				try {
					const menu = uni.getMenuButtonBoundingClientRect();
					if (menu && menu.top) {
						let margin = menu.top - statusBarHeight;
						if (margin < 0) margin = 4;
						customBarHeight = margin * 2 + menu.height;
						if (customBarHeight < 32) customBarHeight = 44;
					}
				} catch (e) {}
			}
			// #endif
			this.navBarHeight = statusBarHeight + customBarHeight;
		},
		onLoad() {
			this.getSetting();
		},
		onShow() {
			// 转赠/使用返回时刷新列表，避免显示已失效的旧数据
			this.loadData(true);
		},
		onPullDownRefresh() {
			this.loadData(true).finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.loadData();
		},
		methods: {
			async loadData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.list = [];
					this.loadStatus = 'loadmore';
				}

				if (this.loadStatus === 'nomore' || this.loading) {
					return;
				}

				this.loading = true;
				this.loadStatus = 'loading';

				const params = {
					page: this.page,
					limit: this.limit,
					status: this.navbar[this.currentTab].status
				};

				try {
					const res = await this.$request({
						url: '/api/coupon/user-coupon-list',
						data: params
					});

					if (res.code === 0) {
						const newList = res.data.list || [];
						this.list = isRefresh ? newList : this.list.concat(newList);

						const pagination = res.data.pagination;
						if (!pagination || pagination.current_page >= pagination.page_count) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.$http.toast(res.msg);
						this.loadStatus = 'loadmore';
					}
				} catch (e) {
					this.loadStatus = 'loadmore';
				} finally {
					this.loading = false;
				}
			},

			tabChange(e) {
				if (this.currentTab === e.index) return;
				this.currentTab = e.index;
				this.loadData(true);
			},

			goUse(item) {
				if (item.mch) {
					this.$T.redirect(`/plugins1/mch/index?id=${item.mch.id}`);
					return;
				}
				this.$T.reLaunch('/pages/index/index');
			},
			
			// 跳转到转赠页面
			goTransfer(item) {
				// 携带必要参数，避免下一页重复请求详情
				const params = `?id=${item.id}&name=${encodeURIComponent(item.name)}&sub_price=${item.sub_price}&min_price=${item.min_price}`;
				this.$T.go(`/others/coupon/transfer${params}`);
			},

			async getSetting() {
				try {
					const res = await this.$request({
						url: "/api/coupon/coupon-center"
					});
					if (res.code === 0) {
						this.icons = res.data.setting;
					}
				} catch (e) {
					console.error("获取优惠券中心配置失败", e);
				}
			},
		}
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #f7f8fa;
	}

	.page-container {
		display: flex;
		flex-direction: column;
	}

	.tabs-container {
		background-color: #fff;
		position: sticky;
		top: var(--tui-nav-height, 0px);
		z-index: 99;
	}

	.coupon-list {
		box-sizing: border-box;
		padding: 24rpx;
	}

	.coupon-card {
		display: flex;
		height: 200rpx; /* 稍微增加高度以容纳两个按钮 */
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
		position: relative;
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			background-color: #f7f8fa;
			width: 30rpx;
			height: 30rpx;
			border-radius: 50%;
			top: 50%;
			transform: translateY(-50%);
			left: 215rpx;
			z-index: 2;
		}

		&.is-disabled .card-left,
		&.is-disabled .tag {
			color: #999;
			background-color: #f2f2f2;
		}

		&.is-disabled .title,
		&.is-disabled .condition,
		&.is-disabled .mch-info {
			color: #999;
		}
	}

	.card-left {
		width: 230rpx;
		border-right: 1rpx dashed #e0e0e0;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		color: #E43D3D;
		transition: all 0.3s;
		background: #fff;

		.price-wrapper {
			display: flex;
			align-items: baseline;
		}

		.symbol {
			font-size: 32rpx;
			font-weight: bold;
		}

		.price {
			font-size: 64rpx;
			font-weight: bold;
			line-height: 1;
		}

		.condition {
			font-size: 24rpx;
			margin-top: 10rpx;
			color: #666;
			transition: color 0.3s;
		}
	}

	.card-right {
		flex: 1;
		padding: 20rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
		min-width: 0;
		position: relative;
		z-index: 1;
	}

	.details {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		height: 100%;
		overflow: hidden;
		flex: 1;
		padding: 8rpx 0;
	}

	.title-line {
		display: flex;
		align-items: center;
	}

	.tag {
		background-color: #FEF0F0;
		color: #E43D3D;
		font-size: 20rpx;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		margin-right: 10rpx;
		flex-shrink: 0;
		transition: all 0.3s;
	}

	.title {
		font-size: 28rpx;
		font-weight: 500;
		color: #333;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		transition: color 0.3s;
	}

	.mch-info {
		display: flex;
		align-items: center;
		font-size: 22rpx;
		color: #999;
		margin-top: 8rpx;
		margin-bottom: 8rpx;

		.mch-name-text {
			margin-left: 8rpx;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	.date {
		font-size: 22rpx;
		color: #999;
	}

	.action-btn {
		flex-shrink: 0;
		margin-left: 10rpx;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: flex-end;
		height: 100%;

		.btn {
			height: 52rpx;
			line-height: 52rpx;
			font-size: 24rpx;
			border-radius: 26rpx;
			margin: 8rpx 0; /* 增加垂直间距 */
			padding: 0 28rpx;
			border: none;
			min-width: 140rpx;

			&::after {
				border: none;
			}

			&.claim,
			&.use {
				background: linear-gradient(90deg, #FF7043, #E43D3D);
				color: #fff;
				box-shadow: 0 4rpx 10rpx rgba(228, 61, 61, 0.3);
			}
			
			&.transfer {
				background: #fff;
				color: #E43D3D;
				border: 1rpx solid #E43D3D;
				box-shadow: none;
			}

			&.disabled {
				background-color: #ccc;
				color: #fff;
			}
		}
	}

	.status-stamp {
		position: absolute;
		top: -8rpx;
		right: -8rpx;
		width: 120rpx;
		height: 120rpx;
		background-size: contain;
		background-repeat: no-repeat;
		z-index: 0; 
		opacity: 0.8;
	}
</style>