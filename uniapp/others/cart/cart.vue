<template>
	<tui-page :isFixed="true" backgroundColor="#f5f6f8">
		<view class="cart-page" :class="{ 'has-tabbar': hasTabbar, 'has-bar': list.length > 0 }" :style="themeStyle">

			<!-- 1. 摘要栏:件数 + 管理 -->
			<view class="summary" v-if="list.length > 0">
				<view class="summary-count">共<text class="summary-num">{{ validItemCount }}</text>件商品</view>
				<view class="summary-tools">
					<view class="summary-share" @click="goMyShares">我的分享</view>
					<view class="summary-share" :class="isShare ? 'on' : ''" @click="toggleShare">{{ isShare ? '完成选购' : '分享好物' }}</view>
					<view class="summary-manage" :class="{ on: isEdit }" @click="toggleEdit">{{ isEdit ? '完成' : '管理' }}</view>
				</view>
			</view>
			<view class="share-hint" v-if="isShare">勾选想分享的商品，发给好友看看。</view>

			<!-- 多店铺提示:结算规则提前说清楚,而不是点了全选才弹 toast -->
			<view class="multi-tip" v-if="list.length > 1 && !isEdit">
				<view class="multi-tip-icon">!</view>
				<text>{{ isShare ? '不同店铺的商品需要分开分享' : '不同店铺的商品需要分开结算' }}</text>
			</view>

			<!-- 2. 店铺卡片 -->
			<view class="shop" :class="{ dim: isShopDim(mch) }" v-for="(mch, mchIndex) in list" :key="mch.mch_id">
				<view class="shop-head">
					<view class="ck-hit" @click="toggleShop(mchIndex)">
						<view class="ck" :class="shopCheckClass(mch)"></view>
					</view>
					<view class="shop-name" @click="goShop(mch.mch_id)">
						<text class="shop-self" v-if="mch.mch_id == 0">自营</text>
						<text class="shop-text">{{ mch.mch_name }}</text>
						<view class="shop-arrow" v-if="mch.mch_id != 0"></view>
					</view>
				</view>

				<view class="cell" v-for="(item, itemIndex) in mch.goods_list" :key="item.id">
					<tui-swipe-action :actions="swipeActions" :params="item" @click="onSwipeDelete">
						<template v-slot:content>
							<view class="item">
								<view class="ck-hit item-ck" @click="toggleItem(mchIndex, itemIndex)">
									<view class="ck" :class="item.selected ? 'on' : (isShopDim(mch) ? 'off' : '')"></view>
								</view>
								<image class="pic" :src="item.cover_pic" mode="aspectFill" @click="goGoods(item.goods_id)"></image>
								<view class="info">
									<view class="title" @click="goGoods(item.goods_id)">{{ item.name }}</view>
									<view class="spec" v-if="hasSpec(item)">{{ item.attr_name }}</view>
									<view class="tags" v-if="item.is_member_price == 1 || integralTag(item)">
										<text class="tag tag-member" v-if="item.is_member_price == 1">会员价</text>
										<text class="tag tag-integral" v-if="integralTag(item)">{{ integralTag(item) }}</text>
									</view>
									<view class="row">
										<view class="price">
											<text class="price-sym">¥</text>
											<text class="price-int">{{ priceInt(item.price) }}</text>
											<text class="price-dec">{{ priceDec(item.price) }}</text>
											<text class="price-origin" v-if="item.is_member_price == 1 && item.origin_price > 0">¥{{ item.origin_price }}</text>
										</view>
										<view class="stepper">
											<view class="step-btn" :class="{ dis: item.num <= 1 }" @click="changeNum(item, -1)">−</view>
											<text class="step-num">{{ item.num }}</text>
											<view class="step-btn" :class="{ dis: reachedMax(item) }" @click="changeNum(item, 1)">+</view>
										</view>
									</view>
								</view>
							</view>
						</template>
					</tui-swipe-action>
				</view>
			</view>

			<!-- 3. 失效商品 -->
			<view class="shop invalid" v-if="invalidList.length > 0">
				<view class="invalid-head">
					<text class="invalid-title">失效商品 {{ invalidList.length }} 件</text>
					<text class="invalid-clear" @click="clearInvalid">清空</text>
				</view>
				<view class="item" v-for="item in invalidList" :key="item.id">
					<view class="ck-ghost"></view>
					<view class="pic-wrap">
						<image class="pic" :src="item.cover_pic" mode="aspectFill"></image>
						<view class="pic-mask">已失效</view>
					</view>
					<view class="info">
						<view class="title">{{ item.name }}</view>
						<view class="invalid-reason">{{ item.invalid_reason || '商品已下架或已售罄' }}</view>
					</view>
				</view>
			</view>

			<!-- 4. 空状态(没登录时换成引导登录,关了登录弹窗也能从这里再打开) -->
			<view class="empty" v-if="loaded && list.length === 0 && invalidList.length === 0">
				<view class="empty-icon">
					<tui-icon name="cart" :size="56" :color="themeColor"></tui-icon>
				</view>
				<block v-if="notLogin">
					<view class="empty-title">登录后查看购物车</view>
					<view class="empty-sub">加购的商品会一直为你保留</view>
					<view class="empty-btn" @click="showLoginSheet = true">去登录</view>
				</block>
				<block v-else>
					<view class="empty-title">购物车还是空的</view>
					<view class="empty-sub">看看有什么想买的吧</view>
					<view class="empty-btn" @click="goHome">去逛逛</view>
				</block>
			</view>

			<tui-nomore v-if="!pullUpOn && validItemCount > 8" text="没有更多了" backgroundColor="#f5f6f8"></tui-nomore>

			<!-- 5. 底部结算栏 -->
			<view class="bar" :class="{ 'has-tabbar': hasTabbar }" v-if="list.length > 0">
				<view class="bar-all" @click="toggleAll">
					<view class="ck" :class="allCheckClass"></view>
					<text class="bar-all-text">全选</text>
				</view>

				<block v-if="!isEdit && !isShare">
					<view class="bar-sum">
						<view class="bar-sum-line">
							<text class="bar-sum-label">合计</text>
							<text class="price-sym">¥</text>
							<text class="bar-sum-int">{{ priceInt(totalYuan) }}</text>
							<text class="price-dec">{{ priceDec(totalYuan) }}</text>
						</view>
						<view class="bar-sum-sub">{{ selectedShopName ? selectedShopName + ' · ' : '' }}不含运费</view>
					</view>
					<view class="bar-btn" :class="{ dis: selectedItems.length === 0 }" @click="checkout">结算({{ buyNum }})</view>
				</block>
				<block v-else-if="isShare">
					<view class="bar-sum"><view class="bar-sum-sub">已选 {{ selectedItems.length }} 款 · {{ buyNum }} 件</view></view>
					<view class="bar-btn" :class="selectedItems.length ? '' : 'dis'" @click="openShareSheet">分享给好友</view>
				</block>
				<block v-else>
					<view class="bar-sum">
						<view class="bar-sum-sub">已选 {{ selectedItems.length }} 件</view>
					</view>
					<view class="bar-btn line" @click="deleteSelected">删除</view>
				</block>
			</view>

			<jx-login-sheet :show="showLoginSheet" title="登录后查看你的购物车"
				@close="showLoginSheet = false" @success="onSheetLogin" />
			<goods-share-sheet :show="showShareSheet" :busy="shareBusy" :items="selectedItems" :record="shareRecord"
				@close="showShareSheet = false" @create="createGoodsShare" @preview="previewGoodsShare" @copy="copyGoodsShare" />
		</view>
	</tui-page>
</template>

<script>
	import { mapState } from 'vuex';
	import TuiSwipeAction from '@/others/components/tui-swipe-action/tui-swipe-action.vue'
	import GoodsShareSheet from './components/GoodsShareSheet.vue';
	import goodsShareMixin from './goods-share-mixin.js';

	const PAGE_SIZE = 50;
	// 连点「+/−」时合并成一次请求
	const NUM_SYNC_DELAY = 400;

	export default {
		components: { TuiSwipeAction, GoodsShareSheet },
		mixins: [goodsShareMixin],
		data() {
			return {
				list: [],
				invalidList: [],
				isEdit: false,
				isShare: false,
				showShareSheet: false,
				shareBusy: false,
				page: 1,
				loading: false,
				loaded: false,
				pullUpOn: true,
				notLogin: false,
				showLoginSheet: false,
				swipeActions: [{
					name: '删除',
					color: '#fff',
					fontsize: 28,
					width: 70,
					background: '#ff5757'
				}],
			}
		},
		computed: {
			...mapState('mall', {
				tabbar: state => state.tabbar
			}),
			// 与 tui-page 的 is_show_tabbar 同一判断:购物车页本身在 tabbar 列表里,tui-page 才会渲染贴底的 tui-tabbar,
			// 这时结算栏要坐在它上面。只看「商城配没配 tabbar」不对:从商品详情进来的购物车页没有 tabbar,结算栏会悬空
			hasTabbar() {
				const list = (this.tabbar && this.tabbar.list) || [];
				return list.some(item => {
					const path = String(item.url || '').split('?')[0];
					const full = path.startsWith('/') ? path : '/' + path;
					return '/others/cart/cart'.startsWith(full) && full.length > 1;
				});
			},
			allItems() {
				return this.list.reduce((acc, mch) => acc.concat(mch.goods_list), []);
			},
			// 顶部件数只算能买的,失效商品单独在下面列
			validItemCount() {
				return this.allItems.length;
			},
			selectedItems() {
				return this.allItems.filter(item => item.selected);
			},
			buyNum() {
				return this.selectedItems.reduce((acc, item) => acc + item.num, 0);
			},
			// 按分算再转元,避免 0.1 + 0.2 这类浮点误差
			totalYuan() {
				const cents = this.selectedItems.reduce((acc, item) => acc + Math.round(Number(item.price) * 100) * item.num, 0);
				return (cents / 100).toFixed(2);
			},
			// 结算模式下当前选中的是哪家店(一次只能结算一家)
			selectedShopId() {
				const first = this.selectedItems[0];
				return first ? Number(first.mch_id) : null;
			},
			selectedShopName() {
				if (this.list.length < 2 || this.selectedShopId === null) return '';
				const mch = this.list.find(m => Number(m.mch_id) === this.selectedShopId);
				return mch ? mch.mch_name : '';
			},
			allCheckClass() {
				const items = this.allItems;
				if (items.length === 0) return '';
				// 结算模式下多店铺不能全选:勾选框置灰,点了给出原因
				if (!this.isEdit && this.list.length > 1) return 'off';
				return items.every(item => item.selected) ? 'on' : '';
			}
		},
		onShow() {
			// 从详情页、结算页回来:静默刷新,保留勾选状态,不闪白
			this.reload();
		},
		onPullDownRefresh() {
			this.reload();
		},
		onReachBottom() {
			this.loadMore();
		},
		onHide() {
			this.flushNumSync();
		},
		onUnload() {
			this.flushNumSync();
		},
		methods: {
			toggleShare() {
				this.isEdit = false;
				this.isShare = !this.isShare;
			},
			goMyShares() {
				if (!this.$http.isLogin()) return this.$http.promptLogin('登录后查看你的好物分享');
				uni.navigateTo({ url: '/others/goods-share/mine' });
			},
			openShareSheet() {
				if (!this.selectedItems.length) return this.$util.toast('请先勾选想分享的商品');
				if (this.selectedItems.length > 20) return this.$util.toast('一次最多分享20款商品');
				if (!this.$http.isLogin()) return this.$http.promptLogin('登录后把好物分享给好友');
				this.shareRecord = null;
				this.showShareSheet = true;
			},
			async createGoodsShare(text) {
				if (this.shareBusy) return;
				this.shareBusy = true;
				try {
					const res = await this.$request({ url: '/api/goods-share/create', method: 'post', data: {
						title: text.title, note: text.note, items: this.selectedItems.map(item => ({ cart_id: item.id, num: item.num }))
					} });
					if (res.code === -1) { this.showShareSheet = false; this.$http.promptLogin('登录后继续分享'); return; }
					if (res.code !== 0) return this.$util.toast(res.msg || '分享准备失败');
					this.prepareGoodsShare(res.data);
				} catch (e) { this.$util.toast('网络异常，请重试'); }
				finally { this.shareBusy = false; }
			},
			previewGoodsShare() {
				if (!this.shareRecord) return;
				this.showShareSheet = false;
				uni.navigateTo({ url: '/others/goods-share/detail?token=' + this.shareRecord.token });
			},
			// ==================== 数据 ====================
			async fetchPage(page) {
				const res = await this.$request({
					url: '/api/cart/my-cart',
					data: { page, limit: PAGE_SIZE }
				});
				if (res.code === -1) {
					const err = new Error(res.msg);
					err.notLogin = true;
					throw err;
				}
				if (res.code !== 0) {
					throw new Error(res.msg || '加载失败');
				}
				const pagination = res.data.pagination;
				return {
					groups: res.data.list || [],
					invalid: res.data.invalid_list || [],
					hasMore: !!(pagination && pagination.page_count > page)
				};
			},
			decorate(item, selected) {
				return {
					...item,
					num: Number(item.num) || 1,
					stock: Number(item.stock) || 0,
					selected: !!selected,
					// 最后一次和服务端对齐的数量,改数量失败时回退到这里
					syncedNum: Number(item.num) || 1
				};
			},
			async reload() {
				if (this.loading) return;
				this.loading = true;
				const firstLoad = !this.loaded;
				const prevSelected = {};
				this.allItems.forEach(item => {
					if (item.selected) prevSelected[item.id] = true;
				});
				try {
					const { groups, invalid, hasMore } = await this.fetchPage(1);
					this.list = groups.map(mch => ({
						...mch,
						goods_list: mch.goods_list.map(item => this.decorate(item, prevSelected[item.id]))
					}));
					this.invalidList = invalid;
					this.page = 1;
					this.pullUpOn = hasMore;
					if (firstLoad) {
						this.applyDefaultSelection();
					}
					this.notLogin = false;
					this.loaded = true;
				} catch (e) {
					if (e.notLogin) {
						// 没登录:空购物车 + 登录弹窗(和首页同一个),登录后原地刷新,不用跳页再回来
						this.list = [];
						this.invalidList = [];
						this.notLogin = true;
						this.loaded = true;
						this.showLoginSheet = true;
					} else {
						this.$util.toast(e.message || '加载失败');
					}
				} finally {
					this.loading = false;
					uni.stopPullDownRefresh();
				}
			},
			async loadMore() {
				if (!this.pullUpOn || this.loading) return;
				this.loading = true;
				try {
					const next = this.page + 1;
					const { groups, invalid, hasMore } = await this.fetchPage(next);
					groups.forEach(group => {
						const items = group.goods_list.map(item => this.decorate(item, false));
						const exist = this.list.find(m => m.mch_id === group.mch_id);
						if (exist) {
							exist.goods_list.push(...items);
						} else {
							this.list.push({ ...group, goods_list: items });
						}
					});
					this.invalidList = this.invalidList.concat(invalid);
					this.page = next;
					this.pullUpOn = hasMore;
				} catch (e) {
					this.$util.toast(e.message || '加载失败');
				} finally {
					this.loading = false;
				}
			},
			// 首次进入:只有一家店就全选;多家店默认选中最近加购的那家(接口按加购时间倒序,第一组就是它)
			applyDefaultSelection() {
				if (this.list.length === 0) return;
				this.list[0].goods_list.forEach(item => {
					item.selected = true;
				});
			},

			// ==================== 勾选 ====================
			isShopDim(mch) {
				return !this.isEdit && this.list.length > 1 && this.selectedShopId !== null
					&& Number(mch.mch_id) !== this.selectedShopId;
			},
			shopCheckClass(mch) {
				if (mch.goods_list.length > 0 && mch.goods_list.every(item => item.selected)) return 'on';
				return this.isShopDim(mch) ? 'off' : '';
			},
			// 结算模式下勾了别家店的商品:自动切换到那家店,并告诉用户发生了什么
			switchShopIfNeeded(mch) {
				if (this.isEdit || this.selectedShopId === null || Number(mch.mch_id) === this.selectedShopId) {
					return;
				}
				this.allItems.forEach(item => {
					item.selected = false;
				});
				this.$util.toast(`已切换为结算「${mch.mch_name}」的商品`);
			},
			toggleItem(mchIndex, itemIndex) {
				const mch = this.list[mchIndex];
				const item = mch.goods_list[itemIndex];
				if (!item.selected) {
					this.switchShopIfNeeded(mch);
				}
				item.selected = !item.selected;
			},
			toggleShop(mchIndex) {
				const mch = this.list[mchIndex];
				const allOn = mch.goods_list.every(item => item.selected);
				if (!allOn) {
					this.switchShopIfNeeded(mch);
				}
				mch.goods_list.forEach(item => {
					item.selected = !allOn;
				});
			},
			toggleAll() {
				if (this.allItems.length === 0) return;
				if (!this.isEdit && this.list.length > 1) {
					return this.$util.toast('不同店铺需分开结算，请按店铺勾选');
				}
				const allOn = this.allItems.every(item => item.selected);
				this.allItems.forEach(item => {
					item.selected = !allOn;
				});
			},
			toggleEdit() {
				this.isShare = false;
				// 管理模式里可以跨店铺勾选删除;退出时如果勾着多家店,只保留第一家,回到「一次结算一家」
				if (this.isEdit) {
					const firstShop = this.selectedShopId;
					this.allItems.forEach(item => {
						if (Number(item.mch_id) !== firstShop) item.selected = false;
					});
				}
				this.isEdit = !this.isEdit;
			},

			// ==================== 数量 ====================
			reachedMax(item) {
				return item.stock > 0 && item.num >= item.stock;
			},
			changeNum(item, delta) {
				const next = item.num + delta;
				if (next < 1) return;
				if (delta > 0 && this.reachedMax(item)) {
					return this.$util.toast(`库存仅剩 ${item.stock} 件`);
				}
				item.num = next;
				this.scheduleNumSync(item);
			},
			scheduleNumSync(item) {
				if (!this.numTimers) this.numTimers = {};
				clearTimeout(this.numTimers[item.id]);
				this.numTimers[item.id] = setTimeout(() => {
					delete this.numTimers[item.id];
					this.syncNum(item);
				}, NUM_SYNC_DELAY);
			},
			// 离开页面时把还没发出去的数量改动立刻发掉
			flushNumSync() {
				if (!this.numTimers) return;
				Object.keys(this.numTimers).forEach(id => {
					clearTimeout(this.numTimers[id]);
					const item = this.allItems.find(i => String(i.id) === String(id));
					if (item) this.syncNum(item);
				});
				this.numTimers = {};
			},
			async syncNum(item) {
				const num = item.num;
				if (num === item.syncedNum) return;
				try {
					const res = await this.$request({
						url: '/api/cart/edit',
						method: 'post',
						data: { id: item.id, num }
					});
					if (res.code !== 0) throw new Error(res.msg);
					item.syncedNum = num;
				} catch (e) {
					// 失败回退到服务端的数量,别让界面显示一个没存上的值
					if (item.num === num) item.num = item.syncedNum;
					this.$util.toast((e && e.message) || '数量修改失败');
				}
			},

			// ==================== 删除 ====================
			async deleteItems(ids) {
				if (ids.length === 0) return;
				uni.showLoading({ title: '删除中...' });
				try {
					const res = await this.$request({
						url: '/api/cart/delete',
						method: 'post',
						data: { id: ids.join(',') }
					});
					if (res.code !== 0) throw new Error(res.msg);
					// 本地直接移除,不整页重载
					const removed = {};
					ids.forEach(id => {
						removed[id] = true;
					});
					this.list = this.list
						.map(mch => ({ ...mch, goods_list: mch.goods_list.filter(item => !removed[item.id]) }))
						.filter(mch => mch.goods_list.length > 0);
					this.invalidList = this.invalidList.filter(item => !removed[item.id]);
					if (this.list.length === 0) this.isEdit = false;
				} catch (e) {
					this.$util.toast((e && e.message) || '删除失败');
				} finally {
					uni.hideLoading();
				}
			},
			onSwipeDelete(e) {
				this.deleteItems([e.item.id]);
			},
			deleteSelected() {
				const ids = this.selectedItems.map(item => item.id);
				if (ids.length === 0) {
					return this.$util.toast('请选择要删除的商品');
				}
				uni.showModal({
					title: '确认删除',
					content: `确定要删除选中的 ${ids.length} 件商品吗？`,
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) this.deleteItems(ids);
					}
				});
			},
			clearInvalid() {
				uni.showModal({
					title: '清空失效商品',
					content: '确定要清空所有失效商品吗？',
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) this.deleteItems(this.invalidList.map(item => item.id));
					}
				});
			},

			// ==================== 结算与跳转 ====================
			checkout() {
				const items = this.selectedItems;
				if (items.length === 0) {
					return this.$util.toast('请选择要结算的商品');
				}
				const mchId = items[0].mch_id;
				if (items.some(item => item.mch_id !== mchId)) {
					return this.$util.toast('不同店铺需分开结算');
				}
				// 还在排队的数量改动先发掉,结算页读的是这里传过去的数量,和购物车保持一致
				this.flushNumSync();
				uni.setStorageSync('ORDER_SUBMIT_DATA', {
					mch_id: mchId,
					goods_list: items.map(item => ({
						goods_id: item.goods_id,
						goods_attr_id: item.goods_attr_id,
						num: item.num
					}))
				});
				uni.navigateTo({ url: '/others/order-submit/order-submit' });
			},
			goHome() {
				uni.reLaunch({ url: '/pages/index/index' });
			},
			onSheetLogin() {
				this.showLoginSheet = false;
				this.reload();
			},
			goGoods(id) {
				uni.navigateTo({ url: `/others/goods/goods?id=${id}` });
			},
			goShop(mchId) {
				if (!mchId || mchId == 0) return;
				uni.navigateTo({ url: `/plugins1/mch/index?id=${mchId}` });
			},

			// ==================== 展示 ====================
			hasSpec(item) {
				return !!item.attr_name && item.attr_name !== '默认';
			},
			/**
			 * 赠送积分:按当前数量算出「预计获得 N 积分」,口径和下单发放一致(NormalOrderHandler):
			 *   0=固定:每件积分 × 数量
			 *   1=比例:单价 × 数量 × 比例%,向下取整(先乘数量再取整,不能按 1 件取整再乘)
			 * 「预计」不能省:实付会被优惠券抵扣,到账可能比这少。算出来是 0 就不显示
			 */
			integralTag(item) {
				const rate = Number(item.send_integral) || 0;
				if (rate <= 0) return '';
				let n;
				if (Number(item.send_integral_type) === 1) {
					// 全程整数运算:价格换成分、比例放大 100 倍,避免浮点误差让 8.8 算成 8.79 之类
					const cents = Math.round(Number(item.price) * 100);
					const basis = Math.round(rate * 100);
					n = Math.floor(cents * item.num * basis / 1000000);
				} else {
					n = Math.round(rate * item.num * 100) / 100;
				}
				return n > 0 ? `预计获得 ${n} ${this.$noun('integral')}` : '';
			},
			priceInt(price) {
				return String(Number(price || 0).toFixed(2)).split('.')[0];
			},
			priceDec(price) {
				return '.' + String(Number(price || 0).toFixed(2)).split('.')[1];
			}
		}
	}
</script>

<style lang="scss" scoped>
	$ink: #1f2229;
	$gray: #8a8f9b;
	$light: #a0a5b0;
	$line: #f0f1f4;
	$card: #ffffff;
	$page: #f5f6f8;

	/* ==================== 页面 ==================== */
	.cart-page {
		min-height: 100vh;
		background: $page;
		padding-bottom: 40rpx;

		/* 有结算栏:留出结算栏高度 */
		&.has-bar {
			padding-bottom: 160rpx;
		}

		/* 有 tabbar:tabbar(120rpx) + 结算栏(100rpx) */
		&.has-tabbar.has-bar {
			padding-bottom: 260rpx;
		}

		&.has-tabbar {
			padding-bottom: 160rpx;
		}
	}

	.summary-tools { display:flex;align-items:center;gap:24rpx; }
	.summary-share { font-size:24rpx;color:#1f2229;padding:10rpx 0; }
	.summary-share.on { color:var(--theme-color); }
	.summary-tools .summary-manage { padding-left:0; }
	.share-hint { font-size:24rpx;color:#8a8f9b;padding:0 32rpx 20rpx; }

	/* ==================== 摘要栏 ==================== */
	.summary {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 22rpx 32rpx 14rpx;
	}

	.summary-count {
		font-size: 26rpx;
		color: $gray;
	}

	.summary-num {
		color: $ink;
		font-weight: 600;
		margin: 0 4rpx;
	}

	.summary-manage {
		font-size: 28rpx;
		color: $ink;
		font-weight: 500;
		padding: 6rpx 0 6rpx 24rpx;

		&.on {
			color: var(--theme-color);
		}
	}

	.multi-tip {
		display: flex;
		align-items: center;
		margin: 0 24rpx 16rpx;
		padding: 16rpx 24rpx;
		border-radius: 16rpx;
		background: #fff7e8;
		color: #b26a00;
		font-size: 24rpx;
	}

	.multi-tip-icon {
		width: 30rpx;
		height: 30rpx;
		border-radius: 50%;
		background: #f5a623;
		color: #fff;
		font-size: 22rpx;
		font-weight: 700;
		line-height: 30rpx;
		text-align: center;
		margin-right: 12rpx;
		flex-shrink: 0;
	}

	/* ==================== 勾选框(自绘,不用原生 checkbox) ==================== */
	.ck-hit {
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.ck {
		position: relative;
		width: 40rpx;
		height: 40rpx;
		border-radius: 50%;
		border: 3rpx solid #cdd0d6;
		background: #fff;
		box-sizing: border-box;
		flex-shrink: 0;

		&.on {
			border-color: var(--theme-color);
			background: var(--theme-color);

			&::after {
				content: '';
				position: absolute;
				left: 12rpx;
				top: 5rpx;
				width: 9rpx;
				height: 17rpx;
				border-right: 4rpx solid #fff;
				border-bottom: 4rpx solid #fff;
				transform: rotate(45deg);
			}
		}

		&.off {
			background: #f1f2f4;
			border-color: #e1e3e8;
		}
	}

	/* ==================== 店铺卡片 ==================== */
	.shop {
		margin: 0 24rpx 20rpx;
		padding: 8rpx 0 12rpx;
		background: $card;
		border-radius: 24rpx;
		overflow: hidden;
	}

	.shop-head {
		display: flex;
		align-items: center;
		height: 88rpx;
		padding-left: 28rpx;

		.ck-hit {
			height: 88rpx;
			padding-right: 18rpx;
		}
	}

	.shop-name {
		display: flex;
		align-items: center;
		min-width: 0;
		flex: 1;
		padding-right: 28rpx;
	}

	.shop-self {
		font-size: 20rpx;
		font-weight: 600;
		color: #fff;
		background: var(--theme-color);
		border-radius: 6rpx;
		padding: 2rpx 8rpx;
		margin-right: 10rpx;
		flex-shrink: 0;
	}

	.shop-text {
		font-size: 28rpx;
		font-weight: 600;
		color: $ink;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.shop-arrow {
		width: 12rpx;
		height: 12rpx;
		border-right: 3rpx solid #b4b8c2;
		border-top: 3rpx solid #b4b8c2;
		transform: rotate(45deg);
		margin-left: 10rpx;
		flex-shrink: 0;
	}

	.shop.dim {
		.shop-text {
			color: $gray;
		}
	}

	/* ==================== 商品行 ==================== */
	.cell {
		background: $card;
	}

	.item {
		display: flex;
		align-items: flex-start;
		padding: 20rpx 28rpx;
		background: $card;
	}

	.item-ck {
		height: 176rpx;
		padding-right: 18rpx;
	}

	.pic {
		width: 176rpx;
		height: 176rpx;
		border-radius: 16rpx;
		flex-shrink: 0;
		background: #eceef2;
		display: block;
	}

	.info {
		flex: 1;
		min-width: 0;
		min-height: 176rpx;
		margin-left: 22rpx;
		display: flex;
		flex-direction: column;
	}

	.title {
		font-size: 28rpx;
		line-height: 40rpx;
		color: $ink;
		font-weight: 500;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		word-break: break-all;
	}

	.spec {
		align-self: flex-start;
		max-width: 100%;
		margin-top: 10rpx;
		padding: 0 16rpx;
		height: 40rpx;
		line-height: 40rpx;
		border-radius: 8rpx;
		background: $page;
		color: $gray;
		font-size: 22rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		box-sizing: border-box;
	}

	.tags {
		display: flex;
		flex-wrap: wrap;
		margin-top: 10rpx;
	}

	.tag {
		font-size: 20rpx;
		height: 34rpx;
		line-height: 32rpx;
		padding: 0 10rpx;
		border-radius: 6rpx;
		border: 1rpx solid;
		margin-right: 10rpx;
		box-sizing: border-box;
	}

	.tag-member {
		color: var(--theme-color);
		border-color: var(--theme-color);
	}

	.tag-integral {
		color: #e08a00;
		border-color: #f3c77a;
		background: #fff8ec;
	}

	/* 价格行贴底:信息区高度随标签自适应,不再和价格叠在一起 */
	.row {
		margin-top: auto;
		padding-top: 14rpx;
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}

	.price {
		display: flex;
		align-items: baseline;
		color: var(--theme-color);
		font-weight: 700;
		white-space: nowrap;
		min-width: 0;
	}

	.price-sym {
		font-size: 22rpx;
		font-weight: 700;
		color: var(--theme-color);
	}

	.price-int {
		font-size: 36rpx;
		line-height: 1;
	}

	.price-dec {
		font-size: 24rpx;
		font-weight: 700;
		color: var(--theme-color);
	}

	.price-origin {
		font-size: 22rpx;
		font-weight: 400;
		color: #b4b8c2;
		text-decoration: line-through;
		margin-left: 10rpx;
	}

	.stepper {
		display: flex;
		align-items: center;
		height: 52rpx;
		border-radius: 26rpx;
		background: $page;
		flex-shrink: 0;
	}

	.step-btn {
		width: 52rpx;
		height: 52rpx;
		line-height: 50rpx;
		text-align: center;
		font-size: 32rpx;
		color: $ink;

		&.dis {
			color: #c8cbd2;
		}
	}

	.step-num {
		min-width: 56rpx;
		text-align: center;
		font-size: 26rpx;
		font-weight: 600;
		color: $ink;
	}

	/* ==================== 失效商品 ==================== */
	.invalid-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 88rpx;
		padding: 0 28rpx;
	}

	.invalid-title {
		font-size: 28rpx;
		font-weight: 600;
		color: $gray;
	}

	.invalid-clear {
		font-size: 26rpx;
		color: var(--theme-color);
		padding: 10rpx 0 10rpx 24rpx;
	}

	/* 占住勾选框的位置,和上面的商品对齐 */
	.ck-ghost {
		width: 58rpx;
		flex-shrink: 0;
	}

	.pic-wrap {
		position: relative;
		width: 176rpx;
		height: 176rpx;
		border-radius: 16rpx;
		overflow: hidden;
		flex-shrink: 0;

		.pic {
			filter: grayscale(1);
			opacity: 0.75;
		}
	}

	.pic-mask {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		height: 44rpx;
		line-height: 44rpx;
		text-align: center;
		font-size: 22rpx;
		color: #fff;
		background: rgba(0, 0, 0, 0.45);
	}

	.invalid {
		.title {
			color: $light;
		}
	}

	.invalid-reason {
		margin-top: 10rpx;
		font-size: 24rpx;
		color: $light;
	}

	/* ==================== 空状态 ==================== */
	.empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding-top: 200rpx;
	}

	.empty-icon {
		width: 240rpx;
		height: 240rpx;
		border-radius: 50%;
		background: var(--theme-color-bg);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.empty-title {
		margin-top: 40rpx;
		font-size: 32rpx;
		font-weight: 600;
		color: $ink;
	}

	.empty-sub {
		margin-top: 14rpx;
		font-size: 26rpx;
		color: $light;
	}

	.empty-btn {
		margin-top: 48rpx;
		height: 80rpx;
		line-height: 76rpx;
		padding: 0 64rpx;
		border-radius: 40rpx;
		border: 2rpx solid var(--theme-color);
		color: var(--theme-color);
		font-size: 28rpx;
		font-weight: 600;
		box-sizing: border-box;
	}

	/* ==================== 底部结算栏 ==================== */
	/* 高度和 tui-tabbar / 商品详情底栏一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2) */
	.bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 99;
		height: 120rpx;
		padding: 0 28rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		background: $card;
		box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.04);

		/* 有 tabbar:坐在 tui-tabbar(120rpx)上面,自身 100rpx */
		&.has-tabbar {
			bottom: 120rpx;
			height: 100rpx;
		}
	}

	.bar-all {
		display: flex;
		align-items: center;
		height: 100%;
		padding-right: 20rpx;
	}

	.bar-all-text {
		margin-left: 14rpx;
		font-size: 26rpx;
		color: #5a5f6b;
	}

	.bar-sum {
		flex: 1;
		min-width: 0;
		text-align: right;
		margin-right: 22rpx;
	}

	.bar-sum-line {
		display: flex;
		align-items: baseline;
		justify-content: flex-end;
		white-space: nowrap;
		color: var(--theme-color);
		font-weight: 700;
	}

	.bar-sum-label {
		font-size: 26rpx;
		font-weight: 400;
		color: $ink;
		margin-right: 6rpx;
	}

	.bar-sum-int {
		font-size: 40rpx;
		line-height: 1;
	}

	.bar-sum-sub {
		margin-top: 6rpx;
		font-size: 20rpx;
		color: $light;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.bar-btn {
		height: 80rpx;
		line-height: 80rpx;
		min-width: 216rpx;
		padding: 0 36rpx;
		border-radius: 40rpx;
		text-align: center;
		font-size: 30rpx;
		font-weight: 600;
		color: #fff;
		background: linear-gradient(90deg, var(--theme-color-light), var(--theme-color));
		box-shadow: 0 8rpx 18rpx var(--theme-shadow);
		box-sizing: border-box;
		flex-shrink: 0;

		&.dis {
			opacity: 0.5;
			box-shadow: none;
		}

		&.line {
			line-height: 76rpx;
			background: #fff;
			color: var(--theme-color);
			border: 2rpx solid var(--theme-color);
			box-shadow: none;
		}
	}
</style>
