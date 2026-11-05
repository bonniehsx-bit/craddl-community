<template>
	<tui-page title="我的分享" :isFixed="true" backgroundColor="#f5f6f8">
		<view class="mine" :style="themeStyle">
			<view class="hint">选好的好物，随时再发给好友。</view>
			<view class="card" v-for="item in list" :key="item.token" @click="open(item.token)"><view class="title">{{ item.title }}<text>›</text></view><view class="meta">{{ item.dateText }} · {{ item.count }} 款 · {{ item.num }} 件</view><view class="covers"><image v-for="(cover,index) in item.covers" :key="index" :src="cover" mode="aspectFill" /></view><view class="bottom">查看并分享给好友<tui-icon name="arrowright" :size="13" color="#a0a5b0" /></view></view>
			<view class="empty" v-if="loaded && !list.length">{{ error || '还没有分享过好物' }}<view class="link" @click="goCart">去购物车选好物</view></view>
			<view class="more" v-if="hasMore && !loading" @click="load(false)">加载更多</view><view class="more" v-if="loading">加载中…</view>
		</view>
	</tui-page>
</template>
<script>
export default {
	data() { return { list: [], page: 1, hasMore: false, loading: false, loaded: false, error: '' }; },
	onShow() { this.load(true); },
	onReachBottom() { if (this.hasMore && !this.loading) this.load(false); },
	methods: {
		async load(reset) {
			if (this.loading) return;
			this.loading = true;
			try {
				const page = reset ? 1 : this.page + 1;
				const res = await this.$request({ url: '/api/goods-share/mine', data: { page } });
				if (res.code === -1) { this.$http.promptLogin({ content: '登录后查看你的好物分享', pageUnusable: !this.loaded }); return; }
				if (res.code !== 0) throw new Error(res.msg || '加载失败');
				const items = res.data.list.map(item => { const d = new Date(item.created_at * 1000); return { ...item, dateText: d.getFullYear() + '.' + (d.getMonth() + 1) + '.' + d.getDate() }; });
				this.list = reset ? items : this.list.concat(items); this.page = page; this.hasMore = res.data.has_more; this.loaded = true; this.error = '';
			} catch (e) { this.error = e.message || '加载失败'; this.loaded = true; this.$util.toast(this.error); }
			finally { this.loading = false; }
		},
		open(token) { uni.navigateTo({ url: '/others/goods-share/detail?token=' + token }); },
		goCart() { uni.navigateTo({ url: '/others/cart/cart' }); }
	}
};
</script>
<style lang="scss" scoped>
.mine { min-height:100vh;background:#f5f6f8;padding:28rpx 24rpx 60rpx;color:#1f2229; }.hint { font-size:24rpx;color:#8a8f9b;margin:0 8rpx 24rpx; }.card { background:#fff;border-radius:24rpx;padding:28rpx;margin-bottom:24rpx; }.title { font-size:30rpx;font-weight:600;line-height:44rpx;display:flex;justify-content:space-between; }.title text { color:#a0a5b0;margin-left:20rpx; }.meta { font-size:22rpx;color:#a0a5b0;margin-top:12rpx; }.covers { display:flex;gap:18rpx;margin:26rpx 0; }.covers image { width:150rpx;height:150rpx;border-radius:14rpx;background:#f5f6f8; }.bottom { display:flex;justify-content:space-between;border-top:2rpx solid #f0f1f4;padding-top:22rpx;font-size:24rpx;color:#8a8f9b; }.empty { padding:150rpx 20rpx;text-align:center;color:#8a8f9b;font-size:26rpx; }.link { margin-top:30rpx;color:var(--theme-color); }.more { text-align:center;font-size:24rpx;color:#a0a5b0;padding:24rpx; }
</style>
