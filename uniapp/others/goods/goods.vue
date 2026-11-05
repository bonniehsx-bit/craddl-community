<template>
	<!-- 底色恒定 #f7f7f7:原来骨架屏期间是 #fff、加载完切 #f7f7f7,整页底色会跳一下。
	     骨架屏那边同步改成灰底承接(见 skeleton-view.vue),它本来就有 20rpx 的卡片间隙,
	     灰底一露出来反而更像真实布局 -->
	<tui-page ref="page" backgroundColor="#f7f7f7">

		<view class="goods-detail-container" :style="themeStyle">

			<!-- 1. 骨架屏组件 -->
			<skeleton-view v-if="is_skeleton"></skeleton-view>

			<block v-else>
				<view class="content-wrapper">
					<!-- 2. 轮播图组件 -->
					<goods-banner :goodsInfo="goods_info" :scrollH="scrollH" :collected="collected"
						@collecting="collecting">
					</goods-banner>

					<!-- 3. 商品基础信息组件 (价格、标题、补贴) -->
					<goods-intro :goods="goods" :currentSku="currentSku" :isLevelPrice="is_member_price"
						@sharePoster="sharePoster">
					</goods-intro>

					<!-- 4. 商家信息组件 -->
					<view class="module-gap" v-if="recommendedMch"></view>
					<goods-store :recommendedMch="recommendedMch" @goMch="goToMchPage" @locate="requestLocationSort">
					</goods-store>


					<!-- 5. 规格选择条组件 -->
					<goods-spec-bar v-if="showSpecBar" :goods="goods" :currentSku="currentSku" :selectedSpecText="selectedSpecText"
						:unselectedSpecText="unselectedSpecText" @showPopup="showPopup('all')">
					</goods-spec-bar>
					<view class="module-gap"></view>

					<!-- 6. 评论组件 -->
					<goods-comment :goods="goods" :comment="comment" @click="commentList">
					</goods-comment>
					<view class="module-gap" v-if="goods && goods.total_comment_count > 0"></view>

					<!-- 7. 详情富文本组件 -->
					<goods-detail-rich :goodsInfo="goods_info"></goods-detail-rich>
					<goods-recommend ref="recommendGoods" :excludeId="id"></goods-recommend>

					<!-- 底部安全区占位 -->
					<view class="safe-area-bottom"></view>
				</view>
			</block>

			<!-- 8. 底部操作栏组件 -->
			<goods-bottom-bar v-if="goods && !is_skeleton" :goods="goods" :currentUrl="'/others/goods/goods?id=' + id"
				:cartCount="cart_count"
				:isShowAddCart="$util.getSetting('is_show_add_cart')" @action="handleBottomAction"
				@showPopup="showPopup">
			</goods-bottom-bar>

			<!-- 9. SKU 弹窗组件 -->
			<goods-sku-popup :show="popupShow" :goods="goods" :specGroups="spec_groups" :skuList="sku_list"
				:isLevelPrice="is_member_price" :isShowAddCart="$util.getSetting('is_show_add_cart')" :isIos="is_ios"
				@close="hidePopup" @change="handleSkuChange" @addCart="handleAddCart" @buy="handleBuy">
			</goods-sku-popup>

			<!-- 其他全局组件 -->
			<tui-modal-poster :url="poster_url" :showPoster="showPosterModal"
				@closePoster="closePoster"></tui-modal-poster>
		</view>
	</tui-page>
</template>

<script>
	import { getLocationWithTip } from "@/utils/permissionTip.js";
// 引入组件
import SkeletonView from './components/skeleton-view.vue';
import GoodsBanner from './components/goods-banner.vue';
import GoodsIntro from './components/goods-intro.vue';
import GoodsStore from './components/goods-store.vue';
import GoodsSpecBar from './components/goods-spec-bar.vue';
import GoodsComment from './components/goods-comment.vue';
import GoodsDetailRich from './components/goods-detail-rich.vue';
import GoodsBottomBar from './components/goods-bottom-bar.vue';
import GoodsSkuPopup from './components/goods-sku-popup.vue';
import GoodsRecommend from './components/goods-recommend.vue';

import { mapState } from 'vuex';
import { fitShareImage } from '@/utils/shareImage.js';

export default {
	components: {
		SkeletonView,
		GoodsBanner,
		GoodsIntro,
		GoodsStore,
		GoodsSpecBar,
		GoodsComment,
		GoodsDetailRich,
		GoodsBottomBar,
		GoodsSkuPopup,
		GoodsRecommend
	},
	data() {
		return {
			id: 0,
			is_member_price: 0,
			scrollH: 0,
			goods: null,
			goods_info: null,
			shareCover: '', // 转成 5:4 的分享卡片图(见 onShareAppMessage)
			poster_url: '',
			showPosterModal: false,
			popupShow: false,
			collected: false,
			cart_count: 0,
			comment: null,
			is_ios: false,
			spec_groups: [],
			sku_list: [],
			// [状态同步] 从子组件同步回来的选中状态
			currentSku: null,
			selectedSpecs: {},
			// 骨架屏状态
			is_skeleton: true,
			recommendedMch: null,
			userLocation: null,
		};
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D71F1F'
		}),
		/**
		 * 规格行只在真有可选规格时显示。
		 * 后台给无规格商品也会自动生成一个「规格 / 默认」的模板,照 is_attr 判断的话,
		 * 详情页会出现一行「规格 已选: 默认」—— 点进去只有一个选项,纯占位。
		 */
		showSpecBar() {
			if (!this.goods || this.goods.is_attr != 1) return false;
			if (this.sku_list.length > 1) return true;
			return this.spec_groups.some(group => (group.values || []).length > 1);
		},
		// themeStyle 改用全局 mixin 的字符串版(含 --theme-color/-light/-shadow/-color-bg,MP 安全)。
		// 删掉原本地对象版:它只注 --theme-color 且对象格式在 MP 端会丢键 → 阴影/浅底/渐变变量全取不到。
		themeColorLight() {
			return this.hexToRgba(this.themeColor, 0.1);
		},
		// 用于 SpecBar 显示
		selectedSpecText() {
			if (!this.currentSku) return '';
			return this.spec_groups.map(g => this.selectedSpecs[g.name]).join(' / ');
		},
		// 用于 SpecBar 显示
		unselectedSpecText() {
			if (!this.goods || this.goods.is_attr == 0 || !this.spec_groups) return '';
			const unselected = this.spec_groups
				.map(g => g.name)
				.filter(name => !this.selectedSpecs[name]);
			return unselected.join(' ');
		}
	},
	onLoad: function (options) {
		uni.getSystemInfo({
			success: res => {
				this.scrollH = res.windowWidth;
				if (res.platform == 'ios' || res.platform == 'devtools') {
					this.is_ios = true;
				}
			}
		});

		if (options.scene) {
			options = this.$util.getScene(options.scene)
		}
		this.id = options.id
		this.pid = options.pid || '';
		this.getGoods();
		this.getCartCount();
		this.loadCachedLocation();
	},
	onShow() {
		if (this.$refs.recommendGoods) this.$refs.recommendGoods.getRecommend(true);
	},
	onReachBottom() {
		if (this.$refs.recommendGoods) this.$refs.recommendGoods.getRecommend();
	},
	onShareAppMessage: function (e) {
		let user_info = uni.getStorageSync('USER');
		let path = `/others/goods/goods?id=${this.id}`;
		if (user_info) {
			path += `&pid=${user_info.id}`;
		}
		// 好友卡片固定 5:4,正方形主图直接给会被裁掉上下 —— 用转好的 5:4 图(商品完整、两边留白)。
		// 还没转好就用微信的 promise 转发(基础库 2.12+)等它,3 秒没好微信自动用原图这份
		const share = {
			title: this.goods.share_title || this.goods.name,
			path: path,
			imageUrl: this.shareCover || this.goods.cover_pic
		};
		if (!this.shareCover && this._sharePromise) {
			const later = this._sharePromise.then(p => Object.assign({}, share, { imageUrl: p || this.goods.cover_pic }));
			return Object.assign({}, share, { promise: later });
		}
		return share;
	},
	methods: {
		hexToRgba(hex, opacity) {
			if (!hex) return '';
			let c;
			if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
				c = hex.substring(1).split('');
				if (c.length === 3) {
					c = [c[0], c[0], c[1], c[1], c[2], c[2]];
				}
				c = '0x' + c.join('');
				return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + opacity + ')';
			}
			return hex;
		},
		// [新] 处理底部栏动作
		handleBottomAction(type) {
			if (type === 'home') {
				this.$T.reLaunch('/pages/index/index')
			} else if (type === 'cart') {
				uni.navigateTo({
					url: '/others/cart/cart'
				});
			}
		},
		// [新] 处理 SKU 弹窗状态变化，同步数据
		handleSkuChange(e) {
			this.currentSku = e.currentSku;
			this.selectedSpecs = e.selectedSpecs;
			// 注意：num 状态在父组件其实不需要实时跟踪，只有提交时需要，
			// 但为了保持数据流清晰，也可以存一下，或者直接在 addCart/buy 事件中接收
		},

		// [核心逻辑] 获取商品详情
		async getGoods() {
			try {
				const res = await this.$request({
					url: '/api/goods/goods',
					data: { id: this.id }
				});
				if (res.code == 0) {
					this.is_member_price = res.data.is_member_price;
					this.goods = res.data.goods;
					this.goods_info = res.data.info;
					// 提前把分享图转成 5:4,用户点转发时直接用
					this.shareCover = '';
					this._sharePromise = fitShareImage(this.goods.cover_pic).then(p => (this.shareCover = p));
					this.collected = res.data.is_favorite == 1;
					this.comment = res.data.comment;

					// 规格数据处理
					if (this.goods.is_attr == 1) {
						this.spec_groups = res.data.specs_data.spec_groups || [];
						const raw_sku_list = res.data.specs_data.sku_list || [];
						// 规格组修正逻辑保留
						this.sku_list = raw_sku_list.map(sku => {
							const correctedSpecs = sku.specs.map(specItem => {
								let correctGroupName = '';
								for (const group of this.spec_groups) {
									if (group.values.some(v => v.name === specItem.value)) {
										correctGroupName = group.name;
										break;
									}
								}
								return {
									group: correctGroupName || specItem.group,
									value: specItem.value
								};
							});
							return { ...sku, specs: correctedSpecs };
						});
					}

					//#ifdef H5
					if (this.$wechatSdk.isWechat()) {
						this.$wechatSdk.share(null, {
							share_title: this.goods.share_title ? this.goods.share_title : this.goods.name,
							share_desc: this.goods.share_desc ? this.goods.share_desc : this.goods.name,
							share_pic: this.goods.cover_pic
						});
					}
					//#endif
					// 平台商品(mch_id=0)：展示多门店 + 推荐门店
					// 商家商品(mch_id>0)：展示该商家信息，后端自动隐藏"更多门店"
					this.getAvailableMch();

					this.$nextTick(() => {
						this.is_skeleton = false;
					});

				} else if (res.data && res.data.entry_path) {
					// 插件专属商品(来信、充值……)不在通用详情页售卖,后端给出专属入口:直接换过去,带上推荐人
					const sep = res.data.entry_path.indexOf('?') === -1 ? '?' : '&';
					const pid = this.pid ? sep + 'pid=' + this.pid : '';
					uni.redirectTo({ url: res.data.entry_path + pid });
				} else {
					this.is_skeleton = false;
					this.showGoodsGone(res.msg);
				}
			} catch (e) {
				console.error(e);
				this.is_skeleton = false;
				// 网络异常也别把用户干晾在骨架屏上
				this.showGoodsGone('');
			}
		},
		/**
		 * 商品打不开时的统一出口(已删除 / 已下架 / 链接坏了 / 网络异常)。
		 *
		 * 原来是 showModal(res.msg) + navigateBack()。两个毛病:
		 *  ① res.msg 原样透传 —— 后端校验失败时会弹出「商品ID必须是整数」这种技术黑话;
		 *  ② 从分享链接、扫码、外部跳转进来时页面栈只有这一页,navigateBack() 直接失败,
		 *     用户点完确定卡在一个空白的商品页上,退不出去。
		 * 现在按页面栈决定去向:能退就退,退不了就回首页。
		 */
		showGoodsGone(msg) {
			const text = msg && msg.indexOf('必须') === -1 ? msg : '该商品不存在或已下架';
			const canBack = getCurrentPages().length > 1;
			uni.showModal({
				title: '提示',
				content: text,
				confirmText: canBack ? '返回' : '去逛逛',
				showCancel: false,
				success: () => {
					if (canBack) {
						uni.navigateBack();
					} else {
						uni.switchTab({
							url: '/pages/index/index',
							// 首页未必在 tabbar 里(商城可自定义 tabbar),switchTab 会失败,兜一层
							fail: () => uni.reLaunch({ url: '/pages/index/index' })
						});
					}
				}
			});
		},
		// 进页面只读取历史缓存的定位(不弹权限窗):
		// 应用市场审核禁止「一进页面就无理由索取定位」,故这里绝不主动调用 uni.getLocation。
		// 有缓存则用于距离排序;没有也不影响门店列表展示(后端不强制经纬度),
		// 需要按距离排序时由用户在门店卡片上主动点击「查看距离」触发定位。
		loadCachedLocation() {
			let location = uni.getStorageSync('location');
			if (location && location.latitude && location.longitude) {
				this.userLocation = { latitude: location.latitude, longitude: location.longitude };
			}
			// 不在此处调 getAvailableMch:getGoods 成功后会统一调用,届时已带上缓存定位
		},
		// 用户主动点击「查看距离」时才申请定位授权 —— 由用户行为触发,符合审核要求
		requestLocationSort() {
			getLocationWithTip({
				type: 'gcj02',
				success: (res) => {
					uni.setStorageSync('location', { latitude: res.latitude, longitude: res.longitude });
					this.userLocation = { latitude: res.latitude, longitude: res.longitude };
					this.getAvailableMch(); // 拿到定位后刷新门店,带上距离排序
				},
				fail: (err) => {
					console.warn('获取用户位置失败', err);
					uni.showToast({ title: '未能获取位置,请在系统设置中开启定位权限', icon: 'none' });
				}
			});
		},
		async getAvailableMch() {
			if (!this.id) return;
			let params = { goods_id: this.id };
			if (this.userLocation) {
				params.latitude = this.userLocation.latitude;
				params.longitude = this.userLocation.longitude;
			}
			try {
				const res = await this.$request({
					url: '/plugin/mch/api/index/available-mch',
					data: params
				});
				if (res.code === 0 && res.data) {
					this.recommendedMch = res.data.recommended;
				}
			} catch (e) {
				console.error('获取在售门店列表失败', e);
			}
		},
		showPopup(action = 'all') {
			this.popupShow = true;
		},
		hidePopup() {
			this.popupShow = false;
		},
		goToMchPage(mchId) {
			if (!mchId) return;
			uni.navigateTo({ url: `/plugins1/mch/index?id=${mchId}` });
		},
		// [新] 处理加入购物车 (接收子组件传递的数据)
		handleAddCart(e) {
			const { num, currentSku } = e;

			if (this.goods.is_attr == 1 && !currentSku) {
				uni.showToast({ title: '请选择完整的规格', icon: 'none' });
				return;
			}
			const stock = currentSku ? currentSku.stock : this.goods.stock;
			if (num > stock) {
				uni.showToast({ title: '库存不足', icon: 'none' });
				return;
			}
			const goods_attr_id = currentSku ? currentSku.id : 0;
			this.$request({
				url: '/api/cart/add-cart',
				data: {
					goods_id: this.id,
					goods_attr_id: goods_attr_id,
					num: num
				},
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					uni.showToast({ title: '添加成功' });
					this.getCartCount();
					this.hidePopup();
				} else if (res.code === -1) {
					// 未登录:和订单提交页同一套,给「返回 / 去登录」两个出口,登录完回到这个商品页
					this.$http.promptLogin('登录后才能加入购物车，现在去登录吗？');
				} else {
					uni.showToast({ title: res.msg, icon: 'none' });
				}
			});
		},
		// [新] 处理立即购买
		handleBuy(e) {
			const { num, currentSku } = e;

			if (this.goods.is_attr == 1 && !currentSku) {
				uni.showToast({ title: '请选择完整的规格', icon: 'none' });
				return;
			}
			const stock = currentSku ? currentSku.stock : this.goods.stock;
			if (num > stock) {
				uni.showToast({ title: '库存不足', icon: 'none' });
				return;
			}
			const goods_attr_id = currentSku ? currentSku.id : 0;
			let specListForDisplay = [];
			if (this.goods.is_attr == 1 && currentSku) {
				specListForDisplay = currentSku.specs.map(spec => ({
					group_name: spec.group.trim(),
					spec_name: spec.value
				}));
			} else if (this.goods.is_attr == 0) {
				specListForDisplay = [{ group_name: "规格", spec_name: "默认规格" }];
			}
			const order_submit_data = {
				mch_id: this.goods.mch_id,
				goods_list: [{
					goods_id: this.id,
					num: num,
					goods_attr_id: goods_attr_id,
					name: this.goods.name,
					cover_pic: currentSku && currentSku.cover_pic ? currentSku.cover_pic : this.goods.cover_pic,
					price: currentSku ? currentSku.price : this.goods.price,
					specs: specListForDisplay
				}]
			};
			uni.setStorageSync('ORDER_SUBMIT_DATA', order_submit_data);
			uni.navigateTo({ url: '/others/order-submit/order-submit' });
		},
		collecting() {
			this.$request({
				url: '/api/goods/favorite',
				data: { goods_id: this.id },
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					this.collected = !this.collected;
				} else if (res.code === -1) {
					// 未登录:原来点了毫无反应,改成和加入购物车一样引导去登录
					this.$http.promptLogin('登录后才能收藏商品，现在去登录吗？');
				} else {
					uni.showToast({ title: res.msg, icon: 'none' });
				}
			})
		},
		getCartCount() {
			this.$request({ url: '/api/cart/count' }).then(res => {
				if (res.code == 0) this.cart_count = res.data.count;
			})
		},
		commentList() {
			uni.navigateTo({ url: `/others/comment-list/comment-list?goods_id=${this.id}` })
		},
		closePoster() {
			this.showPosterModal = false;
		},
		sharePoster() {
			uni.showLoading({ title: '正在生成海报....' });
			if (this.poster_url) {
				uni.hideLoading();
				this.showPosterModal = true;
				return;
			}
			this.$request({
				url: '/api/poster/goods-poster',
				data: { goods_id: this.id },
				method: 'get'
			}).then(res => {
				uni.hideLoading();
				if (res.code == 0) {
					this.poster_url = res.data.url;
					this.showPosterModal = true;
				} else if (res.code === -1) {
					// 未登录:海报上印着分享人头像昵称、二维码带分享人 id,必须登录;和加入购物车一样引导去登录
					this.$http.promptLogin('登录后才能生成商品码，现在去登录吗？');
				} else {
					uni.showToast({ title: res.msg, icon: 'none' });
				}
			});
		},
	}
};
</script>

<style lang="scss" scoped>
/* [核心] 将 SCSS 变量指向 CSS 变量 */
$theme-color: var(--theme-color);
$uni-bg-color: #f7f7f7;

page {
	background-color: $uni-bg-color;
}

.content-wrapper {
	width: 100%;
	animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
	from {
		opacity: 0.5;
	}

	to {
		opacity: 1;
	}
}

.module-gap {
	height: 20rpx;
	background-color: $uni-bg-color;
}

.safe-area-bottom {
	height: 140rpx;
	background-color: $uni-bg-color;
}
</style>
