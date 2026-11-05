<template>
	<view class="page-container" :style="themeStyle">
		<mch-skeleton v-if="!pageLoaded" />

		<template v-else>
			<mch-header 
				:merchant="merchant" 
				:isFavorite="merchant.is_favorite" 
				:statusBarHeight="statusBarHeight" 
				@back="handleBack" 
				@favorite="toggleFavorite" 

				@location="openLocation"
				@call="makePhoneCall"
			/>

			<view class="main-tabs" id="main-tabs">
				<view class="tab-item" 
					:class="{'active': activeTab === index}" 
					v-for="(tab, index) in mainTabs"
					:key="index" 
					@click="switchTab(index)">
					{{ tab }}
					<view class="tab-line" v-if="activeTab === index" :style="{background: themeColor}"></view>
				</view>
			</view>

			<view class="content-wrapper">
				<swiper class="swiper-container" :current="activeTab"
					@change="onSwiperChange">

					<swiper-item class="swiper-item">
						<mch-product-list 
							:list="productCategories" 
							:cartMap="cartCountMap"
							@plus="handleAddToCart" 
							@minus="handleMinusFromCart" 
							@detail="goToProductDetail" 
						/>
					</swiper-item>

					<swiper-item class="swiper-item">
						<mch-reviews 
							:merchant="merchant"
							:list="reviews"
							:loadStatus="reviewLoadStatus"
						/>
					</swiper-item>

					<swiper-item class="swiper-item">
						<mch-info 
							:merchant="merchant"
							@location="openLocation"
							@call="makePhoneCall"
							@license="previewLicense"
							@qrcode="showQrcode"
						/>
					</swiper-item>

					<swiper-item class="swiper-item">
						<mch-coupon 
							:list="coupons"
							@claim="handleReceiveCoupon"
						/>
					</swiper-item>
				</swiper>
			</view>

			<mch-cart 
				v-if="activeTab === 0"
				:cartItems="cartItems" 
				:safeAreaBottom="safeAreaBottom"
				@update="updateCartItem"
				@clear="clearCart"
				@checkout="checkout"
			/>

			<mch-spec-popup 
				:show="isSpecShow" 
				:product="currentProduct" 
				@close="isSpecShow = false" 
				@confirm="confirmAddSpecToCart" 
			/>
		</template>
	</view>
</template>

<script>
	import { mapState } from 'vuex';
	
	// 引入所有子组件
	import MchHeader from './components/mch-header.vue';
	import MchProductList from './components/mch-product-list.vue';
	import MchCart from './components/mch-cart.vue';
	import MchSpecPopup from './components/mch-spec-popup.vue';
	import MchReviews from './components/mch-reviews.vue';
	import MchInfo from './components/mch-info.vue';
	import MchCoupon from './components/mch-coupon.vue';
	import MchSkeleton from './components/mch-skeleton.vue';
	// 可选：如果没封装骨架屏组件，可以用之前的代码封装一个 mch-skeleton.vue
	// 这里假设你已经有了或者直接在 template 里写简单的 v-if 占位

	export default {
		components: {
			MchHeader,
			MchProductList,
			MchCart,
			MchSpecPopup,
			MchReviews,
			MchInfo,
			MchCoupon,
			MchSkeleton
		},
		data() {
			return {
				// --- 页面状态 ---
				mch_id: null,
				pageLoaded: false,
				from_scan: '',
				statusBarHeight: 20,
				safeAreaBottom: 0,

				// --- 布局相关 ---
				activeTab: 0,
				mainTabs: ['点单', '评价', '商家', '领券'],

				// --- 数据源 ---
				merchant: {},
				productCategories: [],
				cartItems: [],
				reviews: [],
				coupons: [],
				
				// --- 交互状态 ---
				isSpecShow: false,
				currentProduct: {}, // 当前操作的商品
				
				// --- 评价分页 ---
				reviewPage: 1,
				reviewLoadStatus: 'loadmore',
			};
		},
		computed: {
			// 购物车数量映射表 { goods_id: count }，传递给商品列表用于显示减号
			cartCountMap() {
				const map = {};
				this.cartItems.forEach(item => {
					if (!map[item.id]) map[item.id] = 0;
					map[item.id] += item.count;
				});
				return map;
			}
		},
		onLoad(options) {
			const sys = uni.getSystemInfoSync();
			this.statusBarHeight = sys.statusBarHeight || 20;
			this.safeAreaBottom = sys.safeAreaInsets ? sys.safeAreaInsets.bottom : 0;

			if (options.scene) {
				const sceneParams = this.$util.getScene(options.scene);
				options = { ...options, ...sceneParams };
				if (options.from_scan) this.from_scan = 'scan';
			}
			
			if (!options.id) {
				this.$util.toast('商户不存在', () => uni.navigateBack());
				return;
			}
			
			this.mch_id = options.id;
			this.loadAllData();
		},
		onReady() {
		},
		onReachBottom() {
			if (this.activeTab === 1) {
				this.loadReviewList();
			}
		},
		methods: {
			// --- 1. 数据初始化 ---
			async loadAllData() {
				this.pageLoaded = false;
				uni.showLoading({ title: '加载中...', mask: true });
				
				try {
					// 并行请求：商家信息 + 商品列表
					const [infoRes, goodsRes] = await Promise.all([
						this.$request({
							url: '/plugin/mch/api/index/info',
							data: { id: this.mch_id, from_scan: this.from_scan }
						}),
						this.$request({
							url: '/plugin/mch/api/index/mch-goods-list',
							data: { id: this.mch_id }
						})
					]);

					if (infoRes.code === 0) {
						this.merchant = infoRes.data.info;
						this.coupons = infoRes.data.coupons || [];
					}
					
					if (goodsRes.code === 0) {
						this.productCategories = goodsRes.data.list;
					}

					// 单独加载购物车，不阻塞页面显示
					await this.refreshCart();

				} catch (e) {
					console.error(e);
				} finally {
					this.pageLoaded = true;
					uni.hideLoading();
				}
			},

			// --- 2. 购物车逻辑 ---
			async refreshCart() {
				try {
					const res = await this.$request({
						url: '/api/cart/list',
						data: { mch_id: this.mch_id }
					});
					
					if (res.code === 0 && res.data && Array.isArray(res.data.list)) {
						this.cartItems = res.data.list.map(item => ({
							cart_id: item.id,
							id: item.goods_id,
							sku_id: item.goods_attr_id,
							count: parseInt(item.num),
							name: item.name,
							sku_name: item.attr_name,
							price: parseFloat(item.price),
							image: item.cover_pic,
							uniqueId: `${item.goods_id}_${item.goods_attr_id}`,
						}));
					} else {
						this.cartItems = [];
					}
				} catch (e) {
					this.cartItems = [];
				}
			},

			// 列表点击加号
			handleAddToCart(product) {
				if (product.is_attr == 1) {
					// 多规格 -> 打开弹窗
					this.currentProduct = product;
					this.isSpecShow = true;
				} else {
					// 单规格 -> 直接调用 API 加购
					this.callAddCartApi(product.id, 0, 1);
				}
			},

			// 列表点击减号
			handleMinusFromCart(product) {
				if (product.is_attr == 1) {
					// 多规格 -> 无法确定减哪个 SKU，提示用户去购物车详情操作
					// 或者直接打开购物车详情弹窗
					this.$util.toast('多规格商品请在购物车中修改');
				} else {
					// 单规格 -> 找到对应购物车项减 1
					const item = this.cartItems.find(c => c.id === product.id && !c.sku_id);
					if (item) {
						this.updateCartItem(item, -1);
					}
				}
			},

			// 规格弹窗确认加购
			confirmAddSpecToCart(result) {
				// result: { id, sku_id, num, ... }
				this.callAddCartApi(result.id, result.sku_id, result.num);
				this.isSpecShow = false;
			},

			// 购物车组件触发更新 (加/减)
			updateCartItem(cartItem, change) {
				const newNum = cartItem.count + change;
				if (newNum <= 0) {
					this.callDeleteCartApi(cartItem.cart_id);
				} else {
					this.callUpdateCartApi(cartItem.cart_id, newNum);
				}
			},

			// 购物车组件触发清空
			clearCart() {
				uni.showModal({
					title: '提示',
					content: '确认清空购物车？',
					success: (res) => {
						if (res.confirm) {
							this.$request({
								url: "/api/cart/clear",
								data: { mch_id: this.mch_id }
							}).then(res => {
								if (res.code == 0) this.refreshCart();
							});
						}
					}
				});
			},

			// 去结算
			checkout() {
				if (!this.cartItems.length) return;
				
				const orderData = {
					mch_id: this.mch_id,
					goods_list: this.cartItems.map(item => ({
						goods_id: item.id,
						num: item.count,
						goods_attr_id: item.sku_id || 0
					}))
				};
				uni.setStorageSync('ORDER_SUBMIT_DATA', orderData);
				uni.navigateTo({
					url: '/others/order-submit/order-submit?from_cart=1'
				});
			},

			// --- API 封装 ---
			async callAddCartApi(goodsId, skuId, num) {
				uni.showLoading({ mask: true });
				const res = await this.$request({
					url: '/api/cart/add-cart',
					method: 'POST',
					data: { goods_id: goodsId, goods_attr_id: skuId || 0, num: num }
				});
				uni.hideLoading();
				if (res.code === 0) {
					this.refreshCart();
				} else {
					this.$util.toast(res.msg);
				}
			},

			async callUpdateCartApi(cartId, num) {
				uni.showLoading({ mask: true });
				const res = await this.$request({
					url: '/api/cart/edit',
					method: 'POST',
					data: { id: cartId, num: num }
				});
				uni.hideLoading();
				if (res.code === 0) this.refreshCart();
			},

			async callDeleteCartApi(cartId) {
				uni.showLoading({ mask: true });
				const res = await this.$request({
					url: '/api/cart/delete',
					method: 'POST',
					data: { id: cartId }
				});
				uni.hideLoading();
				if (res.code === 0) this.refreshCart();
			},

			// --- 3. 评价逻辑 ---
			async loadReviewList() {
				if (this.reviewLoadStatus === 'nomore' || this.reviewLoadStatus === 'loading') return;
				this.reviewLoadStatus = 'loading';
				
				try {
					const res = await this.$request({
						url: '/plugin/mch/api/comment/list',
						data: { mch_id: this.mch_id, page: this.reviewPage }
					});
					if (res.code === 0) {
						const list = res.data.list || [];
						this.reviews = this.reviewPage === 1 ? list : this.reviews.concat(list);
						
						if (this.reviewPage >= res.data.pagination.page_count) {
							this.reviewLoadStatus = 'nomore';
						} else {
							this.reviewPage++;
							this.reviewLoadStatus = 'loadmore';
						}
					}
				} catch(e) {
					this.reviewLoadStatus = 'loadmore';
				}
			},

			// --- 4. 优惠券领取 ---
			async handleReceiveCoupon(coupon) {
				uni.showLoading();
				const res = await this.$request({
					url: '/api/coupon/coupon-receive',
					method: 'post',
					data: { coupon_id: coupon.id }
				});
				uni.hideLoading();
				if (res.code === 0) {
					this.$util.toast('领取成功');
					// 更新本地状态
					const idx = this.coupons.findIndex(c => c.id === coupon.id);
					if (idx !== -1) {
						this.coupons[idx].can_claim_more = false; // 假设只领一次
					}
				} else {
					this.$util.toast(res.msg);
				}
			},

			// --- 5. 基础交互 ---
			onSwiperChange(e) {
				this.switchTab(e.detail.current);
			},
			switchTab(index) {
				if (this.activeTab === index) return;
				this.activeTab = index;
				// 懒加载评价
				if (index === 1 && this.reviews.length === 0) {
					this.loadReviewList();
				}
			},
			
			// 路由跳转
			goToProductDetail(product) {
				this.$T.go(`/others/goods/goods?id=${product.id}`);
			},

			handleBack() {
				uni.navigateBack();
			},
			
			// 商家信息交互
			toggleFavorite() {
				this.$request({
					url: "/plugin/mch/api/user/favorite",
					data: { mch_id: this.mch_id },
					method: 'post'
				}).then(res => {
					if (res.code == 0) {
						this.merchant.is_favorite = !this.merchant.is_favorite;
						this.$util.toast(this.merchant.is_favorite ? '已收藏' : '已取消');
					}
				});
			},
			makePhoneCall() {
				const phone = this.merchant.mobile || this.merchant.tel;
				if(phone) uni.makePhoneCall({ phoneNumber: phone });
			},
			openLocation() {
				this.$util.openLocation(
					Number(this.merchant.longitude), 
					Number(this.merchant.latitude), 
					this.merchant.mch_name,
					this.merchant.address
				);
			},
			previewLicense() {
				if(this.merchant.business_license) {
					uni.previewImage({ urls: [this.merchant.business_license] });
				}
			},
			showQrcode() {
				// 获取二维码逻辑...
				this.$util.toast('二维码查看逻辑');
			}
		}
	}
</script>

<style lang="scss">
	/* 微信小程序端 page 必须放在非 scoped 中才能生效 */
	page {
		height: 100%;
		background-color: #f7f8fa;
		overflow: hidden;
	}
</style>

<style lang="scss" scoped>
	.page-container {
		height: 100vh;
		display: flex;
		flex-direction: column;
	}

	/* 吸顶 Tab 样式 (放在父级控制) */
	.main-tabs {
		display: flex;
		justify-content: space-around;
		background: #fff;
		border-bottom: 1rpx solid #f5f5f5;
		border-radius: 20rpx 20rpx 0 0;
		height: 100rpx;
		margin-top: 30rpx;
		position: sticky;
		top: 0;
		z-index: 9;
		flex-shrink: 0;

		.tab-item {
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 28rpx;
			color: #666;
			position: relative;
			flex: 1;
			transition: all 0.2s;

			&.active {
				color: #333;
				font-weight: bold;
				font-size: 30rpx;
			}

			.tab-line {
				position: absolute;
				bottom: 0;
				left: 50%;
				transform: translateX(-50%);
				width: 40rpx;
				height: 6rpx;
				border-radius: 6rpx 6rpx 0 0;
			}
		}
	}

	.content-wrapper {
		flex: 1;
		min-height: 0;
	}

	.swiper-container, .swiper-item {
		height: 100%;
	}
</style>