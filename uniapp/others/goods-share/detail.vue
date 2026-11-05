<template>
	<tui-page title="好友在看" :isFixed="true" backgroundColor="#f5f6f8">
		<view class="share-page" :style="themeStyle">
			<view class="loading" v-if="loading && !loaded">正在打开好友的分享…</view>
			<view class="empty" v-else-if="error"><view class="empty-title">{{ error }}</view><button class="outline" @click="load">重新加载</button></view>
			<view v-else-if="loaded">
				<view class="hero">
					<view class="sender"><image v-if="sender.avatar" :src="sender.avatar" mode="aspectFill" /><view class="avatar" v-else><tui-icon name="people" :size="18" color="#8a8f9b" /></view><text>{{ sender.nickname }}分享的好物</text><view class="share-icon" @click="showShare = true"><tui-icon name="share" :size="21" :color="themeColor" /></view></view>
					<view class="hero-title">好友在看</view>
					<view class="hero-note" v-if="title && title !== '分享给你的好物'">{{ title }}</view>
					<view class="hero-note" v-if="note">{{ note }}</view>
					<view class="meta">{{ items.length }} 款商品 · {{ totalNum }} 件 · 保留已选规格</view>
				</view>
				<view class="section-title"><text>商品明细</text><text class="muted">可调整勾选和数量</text></view>
				<view class="stock-tip" v-if="invalidCount">{{ invalidCount }} 款商品暂不可购买，已取消勾选</view>
				<view class="shop">
					<view class="shop-head" @click="toggleAll"><view class="ck" :class="allSelected ? 'on' : ''"></view><text class="self" v-if="mchId === 0">自营</text><text>{{ mchName }}</text></view>
					<view class="item" :class="item.is_valid ? '' : 'invalid'" v-for="(item,index) in items" :key="item.index">
						<view class="check-hit" @click="toggle(index)"><view class="ck" :class="item.selected ? 'on' : ''"></view></view>
						<view class="pic-wrap" @click="goGoods(item)"><image class="pic" :src="item.cover_pic" mode="aspectFill" /><view v-if="!item.is_valid" class="sold">{{ item.invalid_reason }}</view></view>
						<view class="info"><view class="name" @click="goGoods(item)">{{ item.name }}</view><view class="spec" v-if="item.attr_name">{{ item.attr_name }}</view><view class="reason" v-if="item.quantityChanged">库存不足，数量已调整为 {{ item.num }} 件</view><view class="row"><view class="price"><text class="sym">¥</text>{{ item.priceText }}</view><view class="stepper" v-if="item.is_valid"><view class="step" :class="item.num <= 1 ? 'dis' : ''" @click="changeNum(index,-1)">−</view><text>{{ item.num }}</text><view class="step" :class="item.num >= item.stock ? 'dis' : ''" @click="changeNum(index,1)">+</view></view><text class="qty" v-else>×{{ item.num }}</text></view></view>
					</view>
				</view>
				<view class="footnote">价格与库存以购买时为准，商品合计不含运费。</view>
				<view class="footer"><view class="total"><text>已选 {{ selectedItems.length }} 款 · {{ selectedNum }} 件</text><view>合计 <text class="price"><text class="sym">¥</text>{{ totalPrice }}</text></view></view><view class="actions"><button class="outline" :disabled="busy || !selectedItems.length" @click="importItems('cart')">加入购物车</button><button class="primary" :loading="busy" :disabled="busy || !selectedItems.length" @click="importItems('checkout')">我也要买</button></view></view>
			</view>
			<goods-share-sheet :show="showShare" :record="shareRecord" @close="showShare = false" @copy="copyGoodsShare" @preview="showShare = false" />
		</view>
	</tui-page>
</template>

<script>
import goodsShareMixin from '../cart/goods-share-mixin.js';
import GoodsShareSheet from '../cart/components/GoodsShareSheet.vue';

export default {
	mixins: [goodsShareMixin], components: { GoodsShareSheet },
	data() { return { token: '', title: '', note: '', sender: { nickname: '好友', avatar: '' }, items: [], loading: false, loaded: false, error: '', busy: false, showShare: false, requestKey: '', requestSignature: '', viewedUser: 0 }; },
	computed: {
		selectedItems() { return this.items.filter(item => item.selected && item.is_valid); },
		selectedNum() { return this.selectedItems.reduce((sum, item) => sum + item.num, 0); },
		totalNum() { return this.items.reduce((sum, item) => sum + item.num, 0); },
		totalPrice() { return (this.selectedItems.reduce((sum, item) => sum + Math.round(Number(item.price) * 100) * item.num, 0) / 100).toFixed(2); },
		invalidCount() { return this.items.filter(item => !item.is_valid).length; },
		allSelected() { const valid = this.items.filter(item => item.is_valid); return valid.length > 0 && valid.every(item => item.selected); },
		mchId() { return this.items.length ? Number(this.items[0].mch_id) : 0; },
		mchName() { return this.items.length ? this.items[0].mch_name : '商城'; }
	},
	onLoad(options) { this.token = options.token || ''; this.load(); },
	onShow() { if (this.loaded) this.load(); },
	methods: {
		async load() {
			if (this.loading) return;
			this.loading = true;
			const previous = {};
			this.items.forEach(item => { previous[item.index] = item; });
			try {
				const res = await this.$request({ url: '/api/goods-share/detail', data: { token: this.token } });
				if (res.code !== 0) throw new Error(res.msg || '分享暂时无法打开');
				const data = res.data;
				this.title = data.title; this.note = data.note; this.sender = data.sender;
				this.items = data.items.map(item => {
					const old = previous[item.index];
					const requested = old ? old.num : Number(item.num);
					const valid = Number(item.is_valid) === 1;
					return { ...item, is_valid: valid, stock: Number(item.stock), num: valid ? Math.min(requested, Number(item.stock)) : requested,
						selected: valid && (!old || old.selected), priceText: Number(item.price).toFixed(2), quantityChanged: valid && requested > Number(item.stock) };
				});
				this.prepareGoodsShare(data);
				this.loaded = true; this.error = '';
				this.recordFootmarks();
			} catch (e) { this.error = e.message || '加载失败，请重试'; }
			finally { this.loading = false; }
		},
		async recordFootmarks() {
			if (!this.$http.isLogin()) return;
			const userId = this.goodsShareUserId() || -1;
			if (this.viewedUser === userId) return;
			try {
				const res = await this.$request({ url: '/api/goods-share/view', method: 'post', data: { token: this.token } });
				if (res.code === 0) this.viewedUser = userId;
			} catch (e) { /* 足迹失败不阻断浏览与购买，下次进入可重试。 */ }
		},
		toggle(index) { const item = this.items[index]; if (item.is_valid) item.selected = !item.selected; },
		toggleAll() { const selected = !this.allSelected; this.items.forEach(item => { item.selected = item.is_valid && selected; }); },
		changeNum(index, delta) { const item = this.items[index]; item.num = Math.max(1, Math.min(item.stock, item.num + delta)); item.quantityChanged = false; },
		goGoods(item) { if (item.can_view) uni.navigateTo({ url: '/others/goods/goods?id=' + item.goods_id }); },
		async importItems(mode) {
			if (this.busy || !this.selectedItems.length) return;
			if (!this.$http.isLogin()) return this.$http.promptLogin('登录后即可加购或购买好友分享的商品');
			const items = this.selectedItems.map(item => ({ index: item.index, num: item.num }));
			const signature = JSON.stringify([mode, items]);
			// 网络异常保留同一请求键；明确成功后才生成下一次操作的键。
			if (!this.requestKey || signature !== this.requestSignature) {
				this.requestKey = Date.now().toString(36) + Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 8);
				this.requestSignature = signature;
			}
			this.busy = true;
			try {
				const res = await this.$request({ url: '/api/goods-share/import', method: 'post', data: { token: this.token, items, mode, request_key: this.requestKey } });
				if (res.code === -1) { this.$http.promptLogin('登录后继续购买好友分享的商品'); return; }
				if (res.code !== 0) { this.$util.toast(res.msg || '操作失败'); await this.load(); return; }
				this.requestKey = ''; this.requestSignature = '';
				if (mode === 'checkout') {
					uni.setStorageSync('ORDER_SUBMIT_DATA', res.data);
					uni.navigateTo({ url: '/others/order-submit/order-submit' });
				} else {
					this.$util.toast('已加入购物车');
				}
			} catch (e) { this.$util.toast('网络异常，请重试'); }
			finally { this.busy = false; }
		}
	}
};
</script>

<style lang="scss" scoped>
.share-page { min-height:100vh;background:#f5f6f8;padding-bottom:220rpx;color:#1f2229; }
.hero { padding:28rpx 32rpx 34rpx; }.sender { display:flex;align-items:center;gap:16rpx;color:#8a8f9b;font-size:24rpx; }.sender image,.avatar { width:54rpx;height:54rpx;border-radius:50%;background:#eceef2; }.avatar { display:flex;align-items:center;justify-content:center; }.share-icon { margin-left:auto;padding:8rpx; }
.hero-title { font-size:44rpx;font-weight:600;line-height:62rpx;margin:24rpx 0 12rpx;word-break:break-all; }.hero-note { font-size:24rpx;color:#8a8f9b;line-height:40rpx;white-space:pre-wrap; }.meta { margin-top:22rpx;color:#a0a5b0;font-size:22rpx; }
.section-title { padding:0 32rpx 22rpx;display:flex;justify-content:space-between;font-size:26rpx; }.muted { font-size:22rpx;color:#8a8f9b; }
.shop { margin:0 24rpx;background:#fff;border-radius:24rpx;overflow:hidden;padding-bottom:12rpx; }.shop-head { display:flex;align-items:center;gap:14rpx;height:88rpx;padding:0 28rpx;font-size:28rpx;font-weight:600; }.self { background:var(--theme-color);color:#fff;border-radius:6rpx;padding:2rpx 8rpx;font-size:20rpx;font-weight:400; }
.ck { width:40rpx;height:40rpx;border:2rpx solid #d8dbe1;border-radius:50%;box-sizing:border-box;position:relative;flex-shrink:0; }.ck.on { border-color:var(--theme-color);background:var(--theme-color); }.ck.on::after { content:'';position:absolute;left:11rpx;top:7rpx;width:12rpx;height:20rpx;border-right:3rpx solid #fff;border-bottom:3rpx solid #fff;transform:rotate(45deg); }
.item { display:flex;padding:24rpx 24rpx 28rpx;gap:18rpx; }.item+.item { border-top:2rpx solid #f5f6f8; }.check-hit { padding:50rpx 0 20rpx; }.pic-wrap { width:176rpx;height:176rpx;position:relative;flex-shrink:0; }.pic { width:100%;height:100%;border-radius:16rpx;background:#eceef2; }.sold { position:absolute;bottom:0;left:0;right:0;font-size:20rpx;background:rgba(31,34,41,.68);color:#fff;text-align:center;padding:8rpx; }.info { flex:1;min-width:0;display:flex;flex-direction:column; }.name { font-size:28rpx;line-height:40rpx;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }.spec { align-self:flex-start;font-size:22rpx;line-height:32rpx;border-radius:8rpx;background:#f5f6f8;color:#8a8f9b;margin:10rpx 0 16rpx;padding:4rpx 12rpx;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.row { display:flex;align-items:center;justify-content:space-between;gap:8rpx;margin-top:auto; }.price { color:var(--theme-color);font-size:36rpx;font-weight:600;white-space:nowrap; }.sym { font-size:22rpx;margin-right:3rpx; }.stepper { display:flex;align-items:center;background:#f5f6f8;border-radius:26rpx;height:48rpx;font-size:24rpx; }.step { width:42rpx;text-align:center;font-size:32rpx; }.stepper text { min-width:34rpx;text-align:center; }.dis { color:#c8cbd2; }.qty { color:#8a8f9b;font-size:24rpx; }.invalid .info,.invalid .pic { opacity:.45; }
.footnote { font-size:22rpx;color:#a0a5b0;line-height:36rpx;padding:22rpx 34rpx; }.stock-tip,.reason { font-size:22rpx;color:#a66a17;line-height:34rpx; }.stock-tip { margin:0 24rpx 20rpx;padding:16rpx 22rpx;border-radius:14rpx;background:#fff7e8; }.reason { margin-bottom:8rpx; }
.footer { position:fixed;bottom:0;left:0;right:0;background:#fff;padding:18rpx 28rpx calc(22rpx + env(safe-area-inset-bottom));z-index:90;box-shadow:0 -4rpx 20rpx rgba(0,0,0,.04); }.total { display:flex;justify-content:space-between;align-items:center;font-size:24rpx;margin-bottom:16rpx; }.total .price { font-size:40rpx; }.actions { display:flex;gap:20rpx; }.primary,.outline { height:82rpx;line-height:82rpx;border-radius:44rpx;font-size:28rpx;margin:0; }.actions button { flex:1; }.primary { background:linear-gradient(90deg,var(--theme-color-light),var(--theme-color));color:#fff; }.outline { border:2rpx solid var(--theme-color);color:var(--theme-color);background:#fff; }.primary::after,.outline::after { border:0; }button[disabled] { opacity:.45; }.loading,.empty { padding:150rpx 40rpx;text-align:center;color:#8a8f9b;font-size:26rpx; }.empty-title { margin-bottom:40rpx; }
</style>
