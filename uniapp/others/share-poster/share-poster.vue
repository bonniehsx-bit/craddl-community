<template>
	<!-- 标题栏走 tui-page 自绘(pages.json 里这页是 navigationStyle: custom),主题色和头部连成一片 -->
	<tui-page ref="page" title="推广海报" :navBarBgColor="themeColor" titleColor="#ffffff" :is-pulldown="true"
		@pulldown="onPullDownRefresh" @reach-bottom="onReachBottom">

		<!-- 主题色必须用字符串 :style,对象写法在小程序里会丢 CSS 变量 -->
		<view class="promotion-page" :style="'--theme-color:' + themeColor + ';'">

			<!-- 头部:一句话说明这页干什么 -->
			<view class="hero">
				<view class="hero-c1"></view>
				<view class="hero-c2"></view>
				<view class="hero-tip">
					<view class="hero-title">邀请好友，一起逛逛</view>
					<view class="hero-sub">保存海报发给朋友，扫码即可加入</view>
				</view>
			</view>

			<!-- 海报(后台生成的整张图):悬浮在头部上,是页面主角 -->
			<view class="poster-wrap">
				<view v-if="posterLoading" class="poster-state">
					<view class="loading-indicator"></view>
					<text class="state-text">正在生成海报...</text>
				</view>

				<view v-else-if="poster_url" class="poster-card">
					<image :src="poster_url" class="poster-image" mode="widthFix" @click="previewPoster"></image>
				</view>

				<view v-else class="poster-state">
					<text class="state-text">海报加载失败</text>
				</view>
			</view>

			<!-- 操作区:小程序能存相册;H5 只能长按,给一条提示而不是假按钮 -->
			<view class="acts" v-if="poster_url && !posterLoading">
				<!-- #ifndef H5 -->
				<view class="btn primary" @click="savePoster">保存至相册</view>
				<view class="btn ghost" @click="previewPoster">查看大图</view>
				<!-- #endif -->
				<!-- #ifdef H5 -->
				<view class="hintbar">长按上方海报即可保存</view>
				<!-- #endif -->
			</view>

			<view class="sheet">
				<!-- 我的邀请人 -->
				<view class="inviter" v-if="inviter && inviter.name">
					<image v-if="inviter.avatar" :src="inviter.avatar" class="avatar" mode="aspectFill"></image>
					<view v-else class="avatar" :class="tone(inviter.id)">{{ initial(inviter.name) }}</view>
					<view class="info">
						<view class="inviter-lab">我的邀请人</view>
						<view class="inviter-name">{{ inviter.name }}</view>
						<view class="inviter-id">ID {{ inviter.id }}</view>
					</view>
					<view class="chat" v-if="inviter.mobile" @click="$util.call(inviter.mobile)">
						<uni-icons type="phone-filled" size="14" :color="themeColor"></uni-icons>
						<text>联系 TA</text>
					</view>
				</view>

				<!-- 我的客户 -->
				<view class="ltitle">
					<text class="ltitle-main">我的客户</text>
					<text v-if="total !== null" class="ltitle-sub">共 {{ total }} 人</text>
				</view>

				<view class="search">
					<uni-icons type="search" size="16" color="#aab0b9"></uni-icons>
					<input class="input" type="text" v-model="keyword" placeholder="输入昵称 / ID 搜索"
						placeholder-style="color:#aab0b9" confirm-type="search" @confirm="onFilterChange" />
					<text v-if="keyword" class="search-go" @click="onFilterChange">搜索</text>
				</view>

				<view class="cus" v-for="customer in customerList" :key="customer.id">
					<view class="cus-top">
						<image v-if="customer.avatar" :src="customer.avatar" class="avatar" mode="aspectFill"></image>
						<view v-else class="avatar" :class="tone(customer.id)">{{ initial(customer.name) }}</view>
						<view class="info">
							<view class="cus-name">{{ customer.name || '未设置昵称' }}</view>
							<view class="cus-meta">ID {{ customer.id }}<text class="dot">·</text>{{ joinDate(customer.registration_time) }} 加入</view>
						</view>
						<view class="call" v-if="customer.mobile" @click="$util.call(customer.mobile)">
							<uni-icons type="phone-filled" size="18" :color="themeColor"></uni-icons>
						</view>
					</view>
					<view class="stats">
						<view class="stat"><text class="stat-num">{{ customer.order_count }}</text><text class="stat-lab">订单数</text></view>
						<view class="stat"><text class="stat-num">¥{{ customer.total_consumption }}</text><text class="stat-lab">消费金额</text></view>
						<view class="stat"><text class="stat-num">{{ customer.team_count }}</text><text class="stat-lab">推荐人数</text></view>
					</view>
				</view>

				<tui-nomore v-if="noMore && customerList.length > 0" text="没有更多客户了"></tui-nomore>
				<tui-no-data v-if="!customerList.length && !customerLoading && !firstLoad" :fixed="false">
					{{ keyword ? '没有找到相关客户' : '暂无客户记录' }}
				</tui-no-data>
				<tui-loadmore v-if="customerLoading"></tui-loadmore>
			</view>
		</view>
	</tui-page>
</template>

<script>
	import { saveImageWithTip } from "@/utils/permissionTip.js";
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				// 海报相关
				posterLoading: true,
				poster_url: '',
				// 客户列表相关
				inviter: {},
				customerList: [],
				total: null, // 客户总数(分页的 total_count),标题旁的「共 N 人」
				customerLoading: false,
				page: 1,
				limit: 10,
				noMore: false,
				keyword: '',
				firstLoad: true,
			};
		},
		computed: {
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad() {
			this.loadPoster();
			this.loadCustomerData(true);
		},
		methods: {
			// 没有头像时用昵称首字 + 按 id 取色的圆底
			initial(name) {
				const n = String(name || '').trim();
				return n ? Array.from(n)[0].toUpperCase() : '客';
			},
			tone(id) {
				return 'tone-' + (Number(id) || 0) % 5;
			},
			// 当年的只显示月-日,往年的显示完整日期
			joinDate(t) {
				t = String(t || '');
				if (!t) return '未知';
				return t.slice(0, 4) === String(new Date().getFullYear()) ? t.slice(5, 10) : t.slice(0, 10);
			},

			// --- 海报相关方法 ---
			previewPoster() {
				if (!this.poster_url) return;
				uni.previewImage({
					urls: [this.poster_url],
					current: this.poster_url
				})
			},
			async loadPoster() {
				this.posterLoading = true;
				try {
					const res = await this.$request({
						url: '/api/poster/share-poster',
						method: 'get'
					});
					if (res.code == -1) {
						this.$refs.page.showLoginModalWindow();
						return;
					}
					if (res.code == 0) {
						this.poster_url = res.data.url;
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg || '获取海报失败'
						});
					}
				} catch (e) {
					console.error("loadPoster failed", e);
					uni.showToast({
						title: '获取海报失败',
						icon: 'none'
					});
				} finally {
					this.posterLoading = false;
				}
			},
			savePoster() {
				if (!this.poster_url) {
					return uni.showToast({
						title: '海报还未生成',
						icon: 'none'
					});
				}
				uni.showLoading({
					title: '保存中...'
				});
				uni.downloadFile({
					url: this.poster_url,
					success: (res) => {
						if (res.statusCode === 200) {
							saveImageWithTip({
								filePath: res.tempFilePath,
								success: () => {
									uni.showToast({
										title: '已保存到相册',
										icon: 'success'
									});
								},
								fail: (err) => {
									if (err.errMsg && err.errMsg.includes('auth deny')) {
										uni.showModal({
											title: '提示',
											content: '需要您授权保存相册才能使用该功能',
											showCancel: false,
											success: () => uni.openSetting()
										});
									} else {
										uni.showToast({
											title: '保存失败',
											icon: 'none'
										});
									}
								},
								complete: () => {
									uni.hideLoading();
								}
							});
						} else {
							uni.hideLoading();
							uni.showToast({
								title: '图片下载失败',
								icon: 'none'
							});
						}
					},
					fail: () => {
						uni.hideLoading();
						uni.showToast({
							title: '图片下载失败',
							icon: 'none'
						});
					}
				});
			},

			// --- 客户列表相关方法 ---
			onPullDownRefresh() {
				this.loadPoster();
				this.loadCustomerData(true);
			},
			onReachBottom() {
				this.loadCustomerData(false);
			},
			async loadCustomerData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.noMore = false;
					this.customerList = [];
				}

				if (this.noMore || this.customerLoading) {
					if (isRefresh) uni.stopPullDownRefresh();
					return;
				}

				this.customerLoading = true;
				this.firstLoad = false;

				try {
					const res = await this.$request({
						url: '/api/user/customers',
						data: {
							page: this.page,
							limit: this.limit,
							keyword: this.keyword,
						}
					});

					if (res.code === 0) {
						const {
							inviter,
							list,
							pagination
						} = res.data;

						if (this.page === 1 && isRefresh) {
							this.inviter = inviter || {};
							this.total = pagination && pagination.total_count !== undefined ? pagination.total_count : null;
						}

						this.customerList = this.customerList.concat(list);

						if (!list || list.length < this.limit || pagination.current_page >= pagination.page_count) {
							this.noMore = true;
						} else {
							this.page++;
						}
					} else {
						this.noMore = true;
						uni.showToast({
							title: res.msg,
							icon: 'none'
						});
					}
				} catch (e) {
					console.error("loadCustomerData failed", e);
					this.noMore = true;
				} finally {
					this.customerLoading = false;
					if (isRefresh) {
						uni.stopPullDownRefresh();
					}
				}
			},
			onFilterChange() {
				this.loadCustomerData(true);
			},
			clearKeywordAndSearch() {
				this.keyword = '';
				this.onFilterChange();
			},
		}
	}
</script>

<style lang="scss" scoped>
	$theme-color: var(--theme-color);

	.promotion-page {
		min-height: 100vh;
		background: #fff;
	}

	/* 头部 */
	.hero {
		position: relative;
		overflow: hidden;
		height: 400rpx;
		background: $theme-color;
	}
	.hero-c1 {
		position: absolute;
		top: -120rpx;
		right: -160rpx;
		width: 480rpx;
		height: 480rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.12);
	}
	.hero-c2 {
		position: absolute;
		bottom: -180rpx;
		left: -140rpx;
		width: 400rpx;
		height: 400rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.08);
	}
	.hero-tip {
		position: relative;
		padding: 12rpx 48rpx 0;
		color: #fff;
	}
	.hero-title {
		font-size: 42rpx;
		font-weight: 700;
		letter-spacing: 2rpx;
	}
	.hero-sub {
		margin-top: 12rpx;
		font-size: 26rpx;
		opacity: 0.88;
	}

	/* 海报:压在头部上 */
	.poster-wrap {
		position: relative;
		z-index: 3;
		margin: -190rpx 44rpx 0;
	}
	.poster-card {
		border-radius: 40rpx;
		overflow: hidden;
		background: #fff;
		box-shadow: 0 36rpx 88rpx rgba(60, 10, 10, 0.28);
		display: flex;
	}
	.poster-image {
		width: 100%;
		height: auto;
		display: block;
	}
	.poster-state {
		min-height: 560rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		border-radius: 40rpx;
		background: #fff;
		box-shadow: 0 36rpx 88rpx rgba(60, 10, 10, 0.18);
	}
	.state-text {
		font-size: 26rpx;
		color: #999;
	}
	.loading-indicator {
		width: 60rpx;
		height: 60rpx;
		margin-bottom: 20rpx;
		border-radius: 50%;
		border: 4rpx solid #e0e0e0;
		border-top-color: $theme-color;
		animation: spin 1s linear infinite;
	}
	@keyframes spin {
		to { transform: rotate(360deg); }
	}

	/* 操作区 */
	.acts {
		position: relative;
		z-index: 3;
		display: flex;
		margin: 36rpx 44rpx 0;

		.btn {
			flex: 1;
			height: 92rpx;
			line-height: 92rpx;
			text-align: center;
			border-radius: 46rpx;
			font-size: 30rpx;
			font-weight: 600;

			& + .btn { margin-left: 24rpx; }
			&:active { opacity: 0.85; }
		}
		.primary {
			color: #fff;
			background: $theme-color;
		}
		/* 淡底用伪元素叠透明度,小程序里不用 color-mix */
		.ghost {
			position: relative;
			color: $theme-color;
			overflow: hidden;

			&::before {
				content: '';
				position: absolute;
				inset: 0;
				background: $theme-color;
				opacity: 0.1;
			}
		}
		.hintbar {
			position: relative;
			flex: 1;
			height: 92rpx;
			line-height: 92rpx;
			text-align: center;
			border-radius: 46rpx;
			font-size: 28rpx;
			color: $theme-color;
			overflow: hidden;

			&::before {
				content: '';
				position: absolute;
				inset: 0;
				background: $theme-color;
				opacity: 0.1;
			}
		}
	}

	/* 白色底板 */
	.sheet {
		position: relative;
		z-index: 2;
		padding: 44rpx 36rpx 40rpx;
		background: #fff;
	}

	/* 通用头像 + 按昵称取色 */
	.avatar {
		flex-shrink: 0;
		width: 92rpx;
		height: 92rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 36rpx;
		font-weight: 700;
		color: #fff;
		background: #eee;

		&.tone-0 { background: linear-gradient(135deg, #f7a35c, #e8693a); }
		&.tone-1 { background: linear-gradient(135deg, #6fb7ff, #4a7cf0); }
		&.tone-2 { background: linear-gradient(135deg, #7bd49a, #2fa66a); }
		&.tone-3 { background: linear-gradient(135deg, #c79bff, #8a5ce6); }
		&.tone-4 { background: linear-gradient(135deg, #ff8fa8, #e5486b); }
	}

	/* 邀请人 */
	.inviter {
		display: flex;
		align-items: center;
		padding: 28rpx 32rpx;
		border-radius: 32rpx;
		background: #f7f8fa;

		.avatar { width: 88rpx; height: 88rpx; margin-right: 24rpx; font-size: 34rpx; }
		.info { flex: 1; min-width: 0; }
	}
	.inviter-lab {
		font-size: 22rpx;
		color: #86909c;
	}
	.inviter-name {
		margin-top: 4rpx;
		font-size: 30rpx;
		font-weight: 700;
		color: #1f2329;
	}
	.inviter-id {
		margin-top: 4rpx;
		font-size: 24rpx;
		color: #86909c;
	}
	.chat {
		position: relative;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		height: 64rpx;
		padding: 0 28rpx;
		border-radius: 32rpx;
		font-size: 26rpx;
		font-weight: 600;
		color: $theme-color;
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background: $theme-color;
			opacity: 0.1;
		}
		text { margin-left: 8rpx; position: relative; }
	}

	/* 客户 */
	.ltitle {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		margin: 52rpx 4rpx 24rpx;
	}
	.ltitle-main {
		font-size: 34rpx;
		font-weight: 700;
		color: #1f2329;
	}
	.ltitle-sub {
		font-size: 26rpx;
		color: #86909c;
	}

	.search {
		display: flex;
		align-items: center;
		height: 84rpx;
		padding: 0 32rpx;
		border-radius: 42rpx;
		background: #f3f4f6;

		.input {
			flex: 1;
			min-width: 0;
			margin-left: 20rpx;
			font-size: 28rpx;
			color: #1f2329;
		}
	}
	.search-go {
		flex-shrink: 0;
		margin-left: 16rpx;
		font-size: 28rpx;
		font-weight: 600;
		color: $theme-color;
	}

	.cus {
		padding: 32rpx 4rpx;
		border-bottom: 1rpx solid #eef0f3;
	}
	.cus-top {
		display: flex;
		align-items: center;

		.avatar { margin-right: 24rpx; }
		.info { flex: 1; min-width: 0; }
	}
	.cus-name {
		font-size: 32rpx;
		font-weight: 700;
		color: #1f2329;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.cus-meta {
		margin-top: 6rpx;
		font-size: 24rpx;
		color: #86909c;

		.dot { margin: 0 14rpx; color: #d0d4da; }
	}
	.call {
		position: relative;
		flex-shrink: 0;
		width: 76rpx;
		height: 76rpx;
		margin-left: 16rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			border-radius: 50%;
			background: $theme-color;
			opacity: 0.1;
		}
		&:active::before { opacity: 0.2; }
	}

	.stats {
		display: flex;
		margin-top: 24rpx;
		padding: 20rpx 0;
		border-radius: 24rpx;
		background: #f7f8fa;
	}
	.stat {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;

		& + .stat { border-left: 1rpx solid #e9ebef; }
	}
	.stat-num {
		font-size: 32rpx;
		font-weight: 700;
		color: #1f2329;
		font-family: Arial, sans-serif;
	}
	.stat-lab {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: #86909c;
	}

	::v-deep .tui-loadmore-line,
	::v-deep .tui-nomore-line {
		margin-top: 30rpx;
		margin-bottom: 30rpx;
	}

	::v-deep .tui-no-data {
		padding-top: 80rpx;
		padding-bottom: 80rpx;
	}
</style>
