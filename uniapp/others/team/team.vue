<template>
	<tui-page title="我的团队" :navBarBgColor="themeColor" titleColor="#ffffff" :is-pulldown="true" @pulldown="onPullDownRefresh" @reach-bottom="onReachBottom">
		<!-- 主题色必须用字符串 :style,对象写法在小程序里会丢 CSS 变量 -->
		<view class="team-page" :style="'--theme-color:' + themeColor + ';'">

			<!-- 头部:总人数 + 直推/间推切换 -->
			<view class="hero">
				<view class="hero-c1"></view>
				<view class="hero-c2"></view>
				<view class="sum">
					<view>
						<view class="sum-lab">团队总人数</view>
						<view class="sum-num">{{ totalCount === null ? '-' : totalCount }}<text class="sum-unit">人</text></view>
					</view>
					<view class="sum-split">
						<view>直推 <text class="sum-b">{{ counts[1] === null ? '-' : counts[1] }}</text> 人</view>
						<view>间推 <text class="sum-b">{{ counts[2] === null ? '-' : counts[2] }}</text> 人</view>
					</view>
				</view>
				<view class="seg">
					<view class="seg-item" :class="{ on: filters.level === 1 }" @click="levelClick(1)">直推成员</view>
					<view class="seg-item" :class="{ on: filters.level === 2 }" @click="levelClick(2)">间推成员</view>
				</view>
			</view>

			<!-- 白色底板:搜索 + 列表 -->
			<view class="sheet">
				<view class="search">
					<uni-icons type="search" size="16" color="#aab0b9"></uni-icons>
					<input
						class="input"
						type="text"
						v-model="filters.keyword"
						placeholder="输入昵称 / ID 搜索"
						placeholder-style="color:#aab0b9"
						confirm-type="search"
						@confirm="search"
					/>
					<text v-if="filters.keyword" class="search-go" @click="search">搜索</text>
				</view>

				<view class="ltitle">
					<text class="ltitle-main">{{ levelLabel }}</text>
					<text v-if="counts[filters.level] !== null">共 {{ counts[filters.level] }} 人</text>
				</view>

				<view v-if="loading && list.length === 0">
					<view class="sk-row" v-for="i in 5" :key="i">
						<view class="sk-avatar"></view>
						<view class="sk-info">
							<view class="sk-line w-40"></view>
							<view class="sk-line w-70"></view>
						</view>
					</view>
				</view>

				<view v-else>
					<view v-for="(item, index) in list" :key="item.id || index" class="row">
						<image v-if="item.avatar_url" :src="item.avatar_url" class="avatar" mode="aspectFill"></image>
						<view v-else class="avatar" :class="avatarTone(item)">{{ initial(item) }}</view>

						<view class="info">
							<view class="nm">
								<text class="nickname">{{ item.nickname || '未设置昵称' }}</text>
								<text class="chip" :class="{ gold: isUpLevel(item) }" v-if="item.level_name">{{ item.level_name }}</text>
							</view>
							<view class="meta">
								<text>ID {{ item.id }}</text>
								<text class="dot">·</text>
								<text>{{ joinDate(item) }} 加入</text>
							</view>
							<view class="mobile" v-if="item.mobile">{{ maskMobile(item.mobile) }}</view>
						</view>

						<view class="call" v-if="item.mobile" @click.stop="$util.call(item.mobile)">
							<uni-icons type="phone-filled" size="20" :color="themeColor"></uni-icons>
						</view>
					</view>
				</view>

				<tui-no-data v-if="!loading && list.length === 0" :fixed="false" marginTop="60">
					{{ filters.keyword ? '没有找到相关成员' : '暂无团队成员' }}
				</tui-no-data>
				<uni-load-more v-if="list.length > 0" :status="loadStatus" />
			</view>
		</view>
	</tui-page>
</template>

<script>
	// 引入 Vuex 获取主题色
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				list: [],
				loading: false,
				page: 1,
				loadStatus: 'loadmore',
				filters: {
					level: 1, // 1:直推 2:间推(不含直推)
					keyword: '',
				},
				// 两个分栏各自的人数,顶部统计块用;null=还没取到
				counts: { 1: null, 2: null }
			}
		},
		computed: {
			// 团队总人数 = 直推 + 间推;两个分栏的人数都取到才显示
			totalCount() {
				const { 1: a, 2: b } = this.counts;
				return a === null || b === null ? null : a + b;
			},
			levelLabel() {
				return this.filters.level === 1 ? '直推成员' : '间推成员';
			},
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad() {
			this.loadData(true);
			this.loadCounts();
		},
		methods: {
			// 没有头像时用昵称首字 + 按 id 取色的圆底
			initial(item) {
				const n = (item.nickname || '').trim();
				return n ? Array.from(n)[0].toUpperCase() : '客';
			},
			// 手机号中间四位打码,拨号仍用完整号码
			maskMobile(m) {
				return String(m).replace(/^(\d{3})\d{4}(\d+)$/, '$1****$2');
			},
			// 当年的只显示月-日,往年的显示完整日期
			joinDate(item) {
				const t = String(item.created_at_text || item.created_at || '');
				if (!t) return '未知';
				return t.slice(0, 4) === String(new Date().getFullYear()) ? t.slice(5, 10) : t.slice(0, 10);
			},
			// 非默认等级用金色标签区分
			isUpLevel(item) {
				return !!item.level_id && item.level_name !== '默认等级';
			},
			avatarTone(item) {
				return 'tone-' + (Number(item.id) || 0) % 5;
			},

			// 只取总数:每个分栏拉 1 条,读 pagination.total_count
			loadCounts() {
				[1, 2].forEach(async level => {
					try {
						const res = await this.$request({ url: '/api/manage/team', data: { page: 1, limit: 1, level } });
						if (res.code == 0 && res.data.pagination) {
							this.$set(this.counts, level, res.data.pagination.total_count || 0);
						}
					} catch (e) {}
				});
			},

			async loadData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.list = [];
					this.loadStatus = 'loadmore';
				}
				
				if (this.loadStatus === 'nomore' || this.loadStatus === 'loading') return;
				
				this.loadStatus = 'loading';
				this.loading = true;

				try {
					const res = await this.$request({
						url: '/api/manage/team',
						data: {
							page: this.page,
							...this.filters
						}
					});
					
					if (res.code == 0) {
						const newList = res.data.list || [];
						this.list.push(...newList);
						
						if (!res.data.pagination || res.data.pagination.current_page >= res.data.pagination.page_count) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.loadStatus = 'nomore';
					}
				} catch(e) {
					console.error("Error:", e);
					this.loadStatus = 'nomore';
				} finally {
					this.loading = false;
					uni.stopPullDownRefresh();
				}
			},
			levelClick(level) {
				if (this.filters.level === level) return;
				this.filters.level = level;
				this.loadData(true);
			},
			search() {
				this.loadData(true);
			},
			onPullDownRefresh() {
				this.loadData(true);
				this.loadCounts();
			},
			onReachBottom() {
				if (this.loadStatus === 'loadmore') {
					this.loadData();
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	$theme-color: var(--theme-color);

	.team-page {
		min-height: 100vh;
		background: #fff;
	}

	/* 头部 */
	.hero {
		position: relative;
		overflow: hidden;
		padding: 12rpx 44rpx 150rpx;
		color: #fff;
		background: $theme-color;
	}
	.hero-c1 {
		position: absolute;
		top: -100rpx;
		right: -140rpx;
		width: 440rpx;
		height: 440rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.12);
	}
	.hero-c2 {
		position: absolute;
		bottom: -170rpx;
		left: -120rpx;
		width: 360rpx;
		height: 360rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.08);
	}

	.sum {
		position: relative;
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}
	.sum-lab {
		font-size: 26rpx;
		letter-spacing: 2rpx;
		opacity: 0.85;
	}
	.sum-num {
		margin-top: 4rpx;
		font-size: 92rpx;
		font-weight: 700;
		line-height: 1.1;
		font-family: Arial, sans-serif;
	}
	.sum-unit {
		margin-left: 8rpx;
		font-size: 30rpx;
		font-weight: 400;
	}
	.sum-split {
		padding-bottom: 12rpx;
		text-align: right;
		font-size: 26rpx;
		line-height: 1.8;
		opacity: 0.92;
	}
	.sum-b {
		margin: 0 4rpx;
		font-size: 32rpx;
		font-weight: 700;
		font-family: Arial, sans-serif;
	}

	.seg {
		position: relative;
		display: flex;
		margin-top: 40rpx;
		padding: 8rpx;
		border-radius: 44rpx;
		background: rgba(255, 255, 255, 0.2);
	}
	.seg-item {
		flex: 1;
		height: 72rpx;
		line-height: 72rpx;
		text-align: center;
		border-radius: 36rpx;
		font-size: 28rpx;
		color: rgba(255, 255, 255, 0.92);
		transition: all 0.25s;

		&.on {
			color: $theme-color;
			font-weight: 700;
			background: #fff;
			box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.14);
		}
	}

	/* 白色底板 */
	.sheet {
		position: relative;
		z-index: 2;
		min-height: 900rpx;
		margin-top: -80rpx;
		padding: 36rpx 36rpx 40rpx;
		background: #fff;
		border-radius: 56rpx 56rpx 0 0;
	}

	.search {
		display: flex;
		align-items: center;
		height: 88rpx;
		padding: 0 32rpx;
		border-radius: 44rpx;
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

	.ltitle {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		margin: 40rpx 8rpx 8rpx;
		font-size: 26rpx;
		color: #86909c;
	}
	.ltitle-main {
		font-size: 30rpx;
		font-weight: 700;
		color: #1f2329;
	}

	/* 成员行 */
	.row {
		display: flex;
		align-items: center;
		padding: 30rpx 8rpx;
		border-bottom: 1rpx solid #eef0f3;

		&:last-child { border-bottom: 0; }
	}

	.avatar {
		flex-shrink: 0;
		width: 100rpx;
		height: 100rpx;
		margin-right: 28rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 38rpx;
		font-weight: 700;
		color: #fff;
		background: #eee;

		&.tone-0 { background: linear-gradient(135deg, #f7a35c, #e8693a); }
		&.tone-1 { background: linear-gradient(135deg, #6fb7ff, #4a7cf0); }
		&.tone-2 { background: linear-gradient(135deg, #7bd49a, #2fa66a); }
		&.tone-3 { background: linear-gradient(135deg, #c79bff, #8a5ce6); }
		&.tone-4 { background: linear-gradient(135deg, #ff8fa8, #e5486b); }
	}

	.info {
		flex: 1;
		min-width: 0;
	}
	.nm {
		display: flex;
		align-items: center;
	}
	.nickname {
		min-width: 0;
		max-width: 300rpx;
		margin-right: 16rpx;
		font-size: 32rpx;
		font-weight: 700;
		color: #1f2329;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	/* 等级标签:淡底 + 主题色字;淡底用伪元素叠透明度 */
	.chip {
		position: relative;
		flex-shrink: 0;
		padding: 2rpx 16rpx;
		font-size: 22rpx;
		line-height: 1.5;
		color: $theme-color;
		border-radius: 20rpx;
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background: $theme-color;
			opacity: 0.1;
		}
	}
	.chip.gold {
		color: #b7791f;

		&::before { background: #f5b942; opacity: 0.18; }
	}
	.meta {
		margin-top: 10rpx;
		font-size: 25rpx;
		color: #86909c;

		.dot { margin: 0 12rpx; color: #d0d4da; }
	}
	.mobile {
		margin-top: 6rpx;
		font-size: 26rpx;
		color: #4e5969;
		font-family: Arial, sans-serif;
	}

	.call {
		position: relative;
		flex-shrink: 0;
		width: 80rpx;
		height: 80rpx;
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

	/* 骨架屏 */
	.sk-row {
		display: flex;
		align-items: center;
		padding: 30rpx 8rpx;

		.sk-avatar {
			width: 100rpx;
			height: 100rpx;
			margin-right: 28rpx;
			border-radius: 50%;
			background: #f2f3f5;
		}
		.sk-info { flex: 1; }
		.sk-line {
			height: 26rpx;
			margin-bottom: 16rpx;
			border-radius: 8rpx;
			background: #f2f3f5;

			&.w-40 { width: 40%; }
			&.w-70 { width: 70%; }
		}
	}
</style>
