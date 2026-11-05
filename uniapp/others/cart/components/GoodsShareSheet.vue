<template>
	<!-- 自定义 tabbar 层级为9999，分享弹层与遮罩需要一起覆盖底栏。 -->
	<tui-bottom-popup :show="show" :zIndex="10000" @close="$emit('close')">
		<view class="share-sheet" :style="themeStyle">
			<view class="sheet-head"><text>{{ record ? '好物已选好' : '分享给好友' }}</text><view class="close" @click="$emit('close')">×</view></view>
			<view v-if="!record">
				<view class="covers"><image v-for="item in covers" :key="item.id" :src="item.cover_pic" mode="aspectFill" /><text>{{ items.length }} 款 · {{ totalNum }} 件</text></view>
				<view class="label">分享标题<text>选填</text></view>
				<input class="field" v-model="title" maxlength="60" placeholder="例如：这些好物，想分享给你" />
				<view class="label">给好友留句话<text>选填</text></view>
				<textarea class="field note" v-model="note" maxlength="200" placeholder="说说为什么想推荐给好友" />
				<view class="hint">保留已选规格与数量，购物车中的商品不会移除。</view>
				<button class="primary" :loading="busy" :disabled="busy" @click="submit">准备分享</button>
			</view>
			<view v-else>
				<view class="share-card"><view class="share-title">{{ record.title }}</view><view class="hint">{{ record.items.length }} 款商品 · 分享给好友</view><view class="covers"><image v-for="item in readyCovers" :key="item.index" :src="item.cover_pic" mode="aspectFill" /></view><view class="card-foot">好物分享 · {{ mallName }}</view></view>
				<view class="actions">
					<button class="outline" @click="$emit('preview')">查看分享</button>
					<!-- #ifdef MP-WEIXIN -->
					<button class="primary" open-type="share" @click="$emit('close')">分享给好友</button>
					<!-- #endif -->
					<!-- #ifndef MP-WEIXIN -->
					<button class="primary" @click="$emit('copy')">复制链接分享</button>
					<!-- #endif -->
				</view>
				<view class="hint centered">好友可查看明细，自由选择加购或下单。</view>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
export default {
	props: { show: Boolean, busy: Boolean, record: { type: Object, default: null }, items: { type: Array, default: () => [] } },
	data() { return { title: '', note: '' }; },
	computed: {
		covers() { return this.items.slice(0, 3); },
		readyCovers() { return this.record ? this.record.items.slice(0, 3) : []; },
		totalNum() { return this.items.reduce((sum, item) => sum + item.num, 0); },
		mallName() { return (this.$setting && this.$setting.name) || '商城'; }
	},
	watch: { show(value) { if (value && !this.record) { this.title = ''; this.note = ''; } } }
	, methods: { submit() { this.$emit('create', { title: this.title.trim(), note: this.note.trim() }); } }
};
</script>

<style lang="scss" scoped>
.share-sheet { padding: 32rpx 36rpx calc(36rpx + env(safe-area-inset-bottom)); background: #fff; color: #1f2229; }
.sheet-head { display:flex;align-items:center;justify-content:space-between;font-size:34rpx;font-weight:600;margin-bottom:28rpx; }
.close { color:#a0a5b0;font-size:48rpx;font-weight:400;padding:0 8rpx; }
.covers { display:flex;align-items:center;gap:16rpx;margin:24rpx 0; }
.covers image { width:120rpx;height:120rpx;border-radius:14rpx;background:#f5f6f8; }
.covers text { color:#8a8f9b;font-size:24rpx; }
.label { font-size:26rpx;display:flex;justify-content:space-between;margin:26rpx 0 14rpx; }
.label text { font-size:22rpx;color:#a0a5b0; }
.field { border:2rpx solid #eceef2;background:#fafbfc;border-radius:16rpx;padding:20rpx 24rpx;height:88rpx;font-size:26rpx;box-sizing:border-box;width:100%; }
.note { height:146rpx;line-height:40rpx; }
.hint { color:#959ba6;font-size:22rpx;line-height:36rpx;margin:20rpx 0; }
.primary,.outline { border-radius:44rpx;font-size:28rpx;font-weight:500;height:84rpx;line-height:84rpx;margin:0;padding:0 26rpx; }
.primary { background:linear-gradient(90deg,var(--theme-color-light),var(--theme-color));color:#fff; }
.primary::after,.outline::after { border:0; }
.outline { color:var(--theme-color);background:#fff;border:2rpx solid var(--theme-color); }
.share-card { background:#f8f9fb;border:2rpx solid #eceef2;border-radius:22rpx;padding:28rpx; }
.share-title { font-size:32rpx;font-weight:600;line-height:46rpx; }
.card-foot { border-top:2rpx solid #eceef2;padding-top:18rpx;color:#8a8f9b;font-size:22rpx; }
.actions { display:flex;gap:20rpx;margin-top:30rpx; }.actions button { flex:1; }.centered { text-align:center;margin-bottom:0; }
</style>
