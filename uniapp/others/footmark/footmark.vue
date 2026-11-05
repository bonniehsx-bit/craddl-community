<template>
	<!-- --theme-color 由 tui-page 根节点注入(它就是包住 slot 的那层),
	     本页不用再传 :style —— 之前那个局部 themeStyle 只提供 1 个变量,
	     反而把全局 mixin 的 4 个变量遮蔽掉了 -->
	<tui-page>
		<view class="fm" v-if="list.length > 0">
			<!-- 按浏览时间分组。足迹页和普通商品列表的区别全在这里:
			     光一个商品网格是看不出"这是我看过的东西"的 -->
			<view class="fm-group" v-for="(group, gi) in groups" :key="gi">
				<view class="fm-gh">
					<view class="fm-bar"></view>
					<text class="fm-gt">{{ group.title }}</text>
					<text class="fm-gc">{{ group.list.length }} 件</text>
				</view>
				<view class="fm-grid">
					<!-- ⚠ :key 只能是纯变量,拼字符串会把引号漏进 data-event-opts,小程序上传报 -80054 -->
					<view class="fm-cell" v-for="(item, ii) in group.list" :key="ii">
						<tui-goods-item :goods="item"></tui-goods-item>
						<view class="fm-views">浏览 {{ item.view_count || 1 }} 次</view>
					</view>
				</view>
			</view>

			<tui-loadmore v-if="loading" :index="3" type="red"></tui-loadmore>
			<tui-nomore v-else-if="is_no_more" backgroundColor="#f7f7f7"></tui-nomore>
			<view class="fm-safe"></view>
		</view>

		<!-- loading 判空:首屏请求还没回来时不要先闪一下"暂无足迹" -->
		<tui-no-data v-if="list.length == 0 && !loading">暂无足迹</tui-no-data>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				page: 1,
				is_no_more: false,
				loading: false,
				list: [],
			}
		},
		computed: {
			/**
			 * 把列表切成「今天 / 昨天 / 最近 7 天 / 更早」四段。
			 *
			 * footmark_time 是接口新加的字段(最后浏览时间)。老版本后端不返回这个字段时
			 * 全部落进「更早」—— 页面照样能用,只是分不出组,不会白屏。
			 */
			groups() {
				const now = new Date()
				// 今天零点。用本地时区,别拿 UTC 算,不然跨零点分组会错一天
				const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000
				const yesterdayStart = todayStart - 86400
				const weekStart = todayStart - 6 * 86400

				const buckets = [
					{ title: '今天', list: [] },
					{ title: '昨天', list: [] },
					{ title: '最近 7 天', list: [] },
					{ title: '更早', list: [] }
				]

				this.list.forEach(item => {
					// footmark_time 是 INT 时间戳,不是日期字符串
					const ts = parseInt(item.footmark_time) || 0
					let i = 3
					if (ts >= todayStart) i = 0
					else if (ts >= yesterdayStart) i = 1
					else if (ts >= weekStart) i = 2
					buckets[i].list.push(item)
				})

				return buckets.filter(b => b.list.length > 0)
			}
		},
		onShow: function() {
			// 从商品详情返回时必须重新拉第一页:刚看过的那件商品现在应该排到最前面。
			// 之前这里直接 getList() 且不重置 page,返回一次就接着加载下一页,
			// 越返回列表越长,而刚看的商品始终不出现。
			this.refresh()
		},
		onPullDownRefresh: function() {
			this.refresh(true)
		},
		onReachBottom: function() {
			this.getList()
		},
		methods: {
			refresh(fromPull) {
				this.page = 1
				this.is_no_more = false
				this.getList(fromPull, true)
			},
			/**
			 * @param {Boolean} fromPull 是否来自下拉刷新(需要收起刷新动画)
			 * @param {Boolean} isReset  是否整表替换(刷新),否则追加(翻页)
			 */
			getList(fromPull, isReset) {
				if (this.is_no_more && !isReset) {
					if (fromPull) uni.stopPullDownRefresh()
					return
				}
				this.loading = true
				this.$request({
					url: '/api/user/goods-footmark',
					data: {
						page: this.page,
					}
				}).then(res => {
					this.loading = false
					if (fromPull) uni.stopPullDownRefresh()
					if (res.code != 0) return

					if (isReset) {
						this.list = res.data.list
					} else {
						this.list = this.list.concat(res.data.list)
					}

					if (this.page >= res.data.pagination.page_count) {
						this.is_no_more = true
					} else {
						this.page++
					}
				}).catch(() => {
					// 请求失败也要收掉 loading,否则骨架/加载条会一直挂着
					this.loading = false
					if (fromPull) uni.stopPullDownRefresh()
				})
			},
		}
	}
</script>

<style>
	page {
		background-color: #f7f7f7;
	}

	/* 两端留白 20rpx = 容器 10rpx + 卡片格子各 10rpx,列间距同样是 20rpx,
	   和 others/goods-list 的网格保持一致 */
	.fm {
		padding: 0 10rpx;
	}

	.fm-group {
		margin-bottom: 10rpx;
	}

	/* 分组标题 */
	.fm-gh {
		display: flex;
		align-items: center;
		padding: 28rpx 10rpx 16rpx;
	}

	.fm-bar {
		width: 8rpx;
		height: 28rpx;
		border-radius: 4rpx;
		background: var(--theme-color);
		margin-right: 14rpx;
	}

	.fm-gt {
		font-size: 28rpx;
		color: #333;
		font-weight: 600;
	}

	.fm-gc {
		font-size: 22rpx;
		color: #bbb;
		margin-left: 14rpx;
	}

	.fm-grid {
		display: flex;
		flex-wrap: wrap;
	}

	.fm-cell {
		position: relative;
		width: 50%;
		padding: 0 10rpx;
		box-sizing: border-box;
		/* flex/百分比子项不加这个,长标题会把格子撑宽,两列就不等宽了 */
		min-width: 0;
	}
	.fm-views {
		position: absolute;
		top: 16rpx;
		right: 26rpx;
		padding: 6rpx 14rpx;
		border-radius: 24rpx;
		background: rgba(0, 0, 0, 0.55);
		color: #fff;
		font-size: 22rpx;
	}

	.fm-safe {
		height: env(safe-area-inset-bottom);
	}
</style>
