<template>
	<view :style="rootStyle">
		<!-- 骨架屏 Loading 状态 -->
		<view v-if="loading">
			<tui-skeleton :width="'100%'" :height="'300rpx'" :radius="'0rpx'" backgroundColor="#fff" model="list"
				:rows="0" :avatarSize="'0rpx'" :rowHeight="0" :rowWidth="'100%'" :marginTop="'0rpx'">
			</tui-skeleton>
			<tui-skeleton backgroundColor="#fff" model="list" :rows="3" :avatarSize="'80rpx'" :rowHeight="30"
				:rowWidth="'100%'" :marginTop="'20rpx'">
			</tui-skeleton>
			<tui-skeleton backgroundColor="#fff" model="list" :rows="3" :avatarSize="'80rpx'" :rowHeight="30"
				:rowWidth="'100%'" :marginTop="'20rpx'">
			</tui-skeleton>
		</view>

		<!-- 正常内容渲染 -->
		<block v-else-if="value.length>0">
			<!-- 每块用 diy-block 统一包裹：底色（wrapStyles）。不强加间距，保留各模块原始间距;不做入场动画,模块直接显示 -->
			<view class="diy-block" v-for="(item, index) in value" :key="index" :style="wrapStyles[index]">

					<!-- 【新增】头部轮播组件 -->
					<block v-if="item.name=='header-banner'">
						<!-- 监听 click-location 事件，向上传递 -->
						<tui-header-banner :value="item.data" @refresh="onLocationUpdated">
						</tui-header-banner>
					</block>

					<block v-if="item.name=='blank'">
						<tui-blank :value="item.data">
						</tui-blank>
					</block>
					<block v-if="item.name=='custom-goods'">
						<tui-custom-goods :value="item.data">
						</tui-custom-goods>
					</block>
					<block v-if="item.name=='video-block'">
						<tui-video-block :value="item.data">
						</tui-video-block>
					</block>
					<block v-if="item.name=='img-group'">
						<tui-img-group :value="item.data">
						</tui-img-group>
					</block>
					<block v-if="item.name=='search-bar'">
						<tui-search-bar :value="item.data">
						</tui-search-bar>
					</block>
					<block v-if="item.name=='banner'">
						<tui-banner :value="item.data">
						</tui-banner>
					</block>
					<block v-if="item.name=='notice'">
						<tui-notice :value="item.data">
						</tui-notice>
					</block>
					<block v-if="item.name=='title-bar'">
						<tui-title-bar :value="item.data">
						</tui-title-bar>
					</block>
					<!-- 风格组件(带配色预设的成品版块) -->
					<block v-if="item.name=='brand-search'">
						<tui-brand-search :value="item.data" :first="index == 0"></tui-brand-search>
					</block>
					<block v-if="item.name=='hero-card'">
						<tui-hero-card :value="item.data"></tui-hero-card>
					</block>
					<block v-if="item.name=='feature-strip'">
						<tui-feature-strip :value="item.data"></tui-feature-strip>
					</block>
					<block v-if="item.name=='cat-grid'">
						<tui-cat-grid :value="item.data"></tui-cat-grid>
					</block>
					<block v-if="item.name=='section-title'">
						<tui-section-title :value="item.data"></tui-section-title>
					</block>
					<block v-if="item.name=='promo-card'">
						<tui-promo-card :value="item.data"></tui-promo-card>
					</block>
					<block v-if="item.name=='video-card'">
						<tui-video-card :value="item.data"></tui-video-card>
					</block>
					<block v-if="item.name=='contact-card'">
						<tui-contact-card :value="item.data"></tui-contact-card>
					</block>
					<block v-if="item.name=='goods-group'">
						<tui-goods-group :value="item.data">
						</tui-goods-group>
					</block>
					<block v-if="item.name=='navbar'">
						<tui-navbar :value="item.data">
						</tui-navbar>
					</block>
			 
					<block v-if="item.name=='hot-image'">
						<tui-hot-image :value="item.data">
						</tui-hot-image>
					</block>
					<block v-if="item.name=='float-button'">
						<tui-float-button :value="item.data">
						</tui-float-button>
					</block>
					<block v-if="item.name=='article-list'">
						<tui-article-list :value="item.data">
						</tui-article-list>
					</block>
					<block v-if="item.name=='dynamic-goods'">
						<tui-dynamic-goods :value="item.data">
						</tui-dynamic-goods>
					</block>
					<block v-if="item.name=='notice-order'">
						<tui-notice-order :value="item.data">
						</tui-notice-order>
					</block>
					<block v-if="item.name=='tab-goods'">
						<tui-tab-goods :value="item.data">
						</tui-tab-goods>
					</block>
					<block v-if="item.name=='integral-group'">
						<tui-integral-group :value="item.data"></tui-integral-group>
					</block>
					
				 

					<block v-if="item.name=='lucky-group'">
						<tui-lucky-group :value="item.data"></tui-lucky-group>
					</block>

					<block v-if="item.name=='bargain-group'">
						<tui-bargain-group :value="item.data"></tui-bargain-group>
					</block>

					<block v-if="item.name=='massage-shop-list'">
						<tui-massage-shop-list :value="item.data"></tui-massage-shop-list>
					</block>

					<block v-if="item.name=='massage-hot-service'">
						<tui-massage-hot-service :value="item.data"></tui-massage-hot-service>
					</block>

					<block v-if="item.name=='massage-new-therapist'">
						<tui-massage-new-therapist :value="item.data"></tui-massage-new-therapist>
					</block>

					<block v-if="item.name=='mch-select-shops'">
						<tui-mch-select-shops :value="item.data"></tui-mch-select-shops>
					</block>
			</view>
		</block>

		<!-- 空状态：非加载中且无任何配置块，避免白屏 -->
		<view v-else-if="!loading" class="diy-empty">
			<tui-icon name="shop" :size="64" color="#dcdfe6"></tui-icon>
			<text class="diy-empty-text">这里还没有内容</text>
		</view>

		<view class="diy-footer" v-if="show_bottom && !loading && value.length>0 && !singleScreen">
			<text class="diy-footer-line"></text>
			<text class="diy-footer-text">已经到底啦</text>
			<text class="diy-footer-line"></text>
		</view>
			
		<!-- 全局悬浮按钮组 (受页面自带的 pageParams 控制，无配置则降格到商城全局配置) -->
		<view v-if="floatingSettings && (floatingSettings.show_contact == 1 || floatingSettings.show_phone == 1 || floatingSettings.show_nav == 1)" class="button-group">
			<view class="floating-btn-item" v-if="floatingSettings && floatingSettings.show_contact == 1">
				<tui-cs-wrapper>
					<view class="floating-btn flex-y-center flex-x-center">
						<image v-if="floatingSettings.contact_icon && !iconErr.contact" :src="floatingSettings.contact_icon" mode="aspectFill" class="floating-btn-icon" @error="onIconErr('contact')"></image>
						<tui-icon v-else name="service" :size="40" :color="themeColor"></tui-icon>
					</view>
				</tui-cs-wrapper>
			</view>
			<view class="floating-btn-item" v-if="floatingSettings && floatingSettings.show_phone == 1">
				<button class="floating-btn flex-y-center flex-x-center" @click="$util.call(floatingSettings.tel)">
					<image v-if="floatingSettings.phone_icon && !iconErr.phone" :src="floatingSettings.phone_icon" mode="aspectFill" class="floating-btn-icon" @error="onIconErr('phone')"></image>
					<tui-icon v-else name="voipphone" :size="40" :color="themeColor"></tui-icon>
				</button>
			</view>
			<view class="floating-btn-item" v-if="floatingSettings && floatingSettings.show_nav == 1">
				<button class="floating-btn flex-y-center flex-x-center" @click="handleNav(floatingSettings)">
					<image v-if="floatingSettings.nav_icon && !iconErr.nav" :src="floatingSettings.nav_icon" mode="aspectFill" class="floating-btn-icon" @error="onIconErr('nav')"></image>
					<tui-icon v-else name="location" :size="40" :color="themeColor"></tui-icon>
				</button>
			</view>
		</view>
	</view>
</template>

<script>
	// 基础组件
	import TuiSkeleton from '@/components/thorui/tui-skeleton/tui-skeleton.vue'

	// DIY 组件
	// 【新增】引入头部轮播组件 (请确保路径正确，如果是放在 components 下)
	import TuiHeaderBanner from '@/components/thorui/tui-header-banner/tui-header-banner.vue'

	import TuiBlank from '@/components/thorui/tui-blank/tui-blank'
	import TuiBanner from '@/components/thorui/tui-banner/tui-banner'
	import TuiImgGroup from '@/components/thorui/tui-img-group/tui-img-group'
	import TuiSearchBar from '@/components/thorui/tui-search-bar/tui-search-bar'
	import TuiNotice from '@/components/thorui/tui-notice/tui-notice'
	import TuiNoticeOrder from '@/components/thorui/tui-notice-order/tui-notice-order'
	import TuiTitleBar from '@/components/thorui/tui-title-bar/tui-title-bar'
	import TuiGoodsGroup from '@/components/thorui/tui-goods-group/tui-goods-group'
	import TuiNavbar from '@/components/thorui/tui-navbar/tui-navbar'
	import TuiVideoBlock from '@/components/thorui/tui-video-block/tui-video-block'
 
	import TuiCustomGoods from '@/components/thorui/tui-custom-goods/tui-custom-goods'
	import TuiHotImage from '@/components/thorui/tui-hot-image/tui-hot-image'
	import TuiFloatButton from '@/components/thorui/tui-float-button/tui-float-button'
	import TuiArticleList from '@/components/thorui/tui-article-list/tui-article-list'
	import TuiDynamicGoods from '@/components/thorui/tui-dynamic-goods/tui-dynamic-goods'
	import TuiTabGoods from '@/components/thorui/tui-tab-goods/tui-tab-goods'
	// 风格组件
	import TuiBrandSearch from '@/components/thorui/tui-brand-search/tui-brand-search.vue'
	import TuiHeroCard from '@/components/thorui/tui-hero-card/tui-hero-card.vue'
	import TuiFeatureStrip from '@/components/thorui/tui-feature-strip/tui-feature-strip.vue'
	import TuiCatGrid from '@/components/thorui/tui-cat-grid/tui-cat-grid.vue'
	import TuiSectionTitle from '@/components/thorui/tui-section-title/tui-section-title.vue'
	import TuiPromoCard from '@/components/thorui/tui-promo-card/tui-promo-card.vue'
	import TuiVideoCard from '@/components/thorui/tui-video-card/tui-video-card.vue'
	import TuiContactCard from '@/components/thorui/tui-contact-card/tui-contact-card.vue'
	import TuiIntegralGroup from '@/components/thorui/tui-integral-group/tui-integral-group.vue'
	import TuiLuckyGroup from '@/components/thorui/tui-lucky-group/tui-lucky-group.vue'
	import TuiBargainGroup from '@/components/thorui/tui-bargain-group/tui-bargain-group.vue'
	import TuiMassageShopList from '@/components/thorui/tui-massage-shop-list/tui-massage-shop-list.vue'
	import TuiMassageHotService from '@/components/thorui/tui-massage-hot-service/tui-massage-hot-service.vue'
	import TuiMassageNewTherapist from '@/components/thorui/tui-massage-new-therapist/tui-massage-new-therapist.vue'
	import TuiMchSelectShops from '@/components/thorui/tui-mch-select-shops/tui-mch-select-shops.vue'
	import TuiCsWrapper from '@/components/thorui/tui-cs-wrapper/tui-cs-wrapper.vue'
	import TuiIcon from '@/components/thorui/tui-icon/tui-icon.vue'

	import { mapState } from 'vuex';

	export default {
		name: "DiyPageRenderer",
		components: {
			TuiSkeleton,
			// 【新增】注册组件
			TuiHeaderBanner,
			TuiIntegralGroup,
			TuiLuckyGroup,
			TuiBargainGroup,
			TuiMassageShopList,
			TuiMassageHotService,
			TuiMassageNewTherapist,
			TuiMchSelectShops,

			TuiTabGoods,
			TuiBrandSearch,
			TuiHeroCard,
			TuiFeatureStrip,
			TuiCatGrid,
			TuiSectionTitle,
			TuiPromoCard,
			TuiVideoCard,
			TuiContactCard,
			TuiDynamicGoods,
			TuiNoticeOrder,
			TuiBlank,
			TuiBanner,
			TuiImgGroup,
			TuiSearchBar,
			TuiNotice,
			TuiTitleBar,
			TuiGoodsGroup,
			TuiNavbar,
			TuiVideoBlock,
 
			TuiCustomGoods,
			TuiHotImage,
			TuiFloatButton,
			TuiArticleList,
			TuiCsWrapper,
			TuiIcon
		},

		props: {
			value: {
				type: Array,
				default: () => {
					return []
				}
			},
			loading: {
				type: Boolean,
				default: false
			},
			pageParams: {
				type: Object,
				default: () => null
			},
			// 页面背景色:自定义页由接口单独下发;首页在 pageParams.bg_color 里。都没配 = 不铺,保持原来的底色
			bgColor: {
				type: String,
				default: ''
			}
		},
		computed: {
			...mapState({
				themeColor: state => state.mall.themeColor || '#D71F1F'
			}),
			// 单屏模式（后台 DIY 页配置 page_params.single_screen）：隐藏「到底」提示 + 去掉底部留白，
			// 适合内容只有一屏、不需要下拉的页面。默认关，向后兼容。
			singleScreen() {
				return !!(this.pageParams && this.pageParams.single_screen == 1);
			},
			// 根容器底部留白：单屏模式置 0；否则维持原有 tabbar 避让（缺省 100rpx）
			rootStyle() {
				let s = 'padding-bottom:' + (this.singleScreen ? '0' : (this.tabbarHeight || '100rpx')) + ';';
				// 配了页面背景色:铺满整屏,内容不足一屏时下面也是这个颜色
				if (this.pageBg) s += 'background:' + this.pageBg + ';min-height:100vh;box-sizing:border-box;';
				return s;
			},
			pageBg() {
				return this.bgColor || (this.pageParams && this.pageParams.bg_color) || '';
			},
			// 预算每个块的包裹样式：自定义底色（不含 margin-top），和 value 按下标一一对应。
			// 字符串预算在 computed，规避 mp-weixin 的 :style 不支持模板内带参表达式。
			// 只算样式字符串、模板直接遍历 value:原来的 blocks 把每块复制一份再加样式,
			// 小程序会把 computed 整个 setData 给视图层,等于整页 DIY 数据(首页约 69KB)多传一遍。
			// 原来还给每块加错峰入场(第 i 块晚 i×60ms 淡入上移),模块一块块冒出来像在加载,已去掉
			wrapStyles() {
				// 不强加 margin-top：原 tui-block 本就不加，间距由各模块自身样式/后台决定，
				// 否则会把客户要的零间距模块也撑开。
				return (this.value || []).map(item => (item && item.bgColor) ? 'background:' + item.bgColor + ';' : '');
			},
			floatingSettings() {
				// 如果当前 DIY 页面单独配置了浮动按钮（哪怕里面的数据为空，只要 pageParams 对象存在就是开启了本页控制）
				if (this.pageParams) {
					return this.pageParams;
				}
				// 回退到全局设置
				if (this.$store.state.mall && this.$store.state.mall.setting) {
					return this.$store.state.mall.setting;
				}
				return null;
			}
		},
		data() {
			return {
				show_bottom: false,
				// 悬浮按钮图标加载失败标记 → 回退矢量图标，杜绝裂图
				iconErr: { contact: false, phone: false, nav: false },
			}
		},
		watch: {
			// 下拉回弹露出的原生页面底色也换成同一个颜色(H5 没有这个接口,失败忽略)
			pageBg: {
				immediate: true,
				handler(c) {
					if (!c || !uni.setBackgroundColor) return;
					uni.setBackgroundColor({ backgroundColor: c, backgroundColorTop: c, backgroundColorBottom: c, fail() {} });
				}
			}
		},
		created() {
			// 简单的防抖显示底部，体验更好
			setTimeout(() => {
				this.show_bottom = true;
			}, 800)
		},
		mounted() {
			// 模块都画出来了(小程序里组件的 mounted 要等子组件都 ready 才触发),通知页面撤掉骨架屏
			this.$emit('rendered');
		},
		methods: {
			// 悬浮按钮图标加载失败 → 切回矢量图标
			onIconErr(key) {
				this.iconErr[key] = true;
			},
			onLocationUpdated(data) {
				console.log("新的定位数据：", data);

				// 假设你父组件有商品列表的 ref 或者刷新方法
				// this.loadGoodsList(true); // 重新加载商品

				// 或者抛出给更上层
				this.$emit('refresh-goods');
			},
			// 处理一键导航
			handleNav(settings) {
				if (!settings.nav_location) {
					uni.showToast({ title: '尚未配置导航坐标', icon: 'none' });
					return;
				}
				const loc = settings.nav_location.split(',');
				if (loc.length !== 2) {
					uni.showToast({ title: '导航坐标格式错误', icon: 'none' });
					return;
				}
				
				const latitude = parseFloat(loc[0].trim());
				const longitude = parseFloat(loc[1].trim());
				
				uni.openLocation({
					latitude: latitude,
					longitude: longitude,
					name: settings.nav_address || '目的地',
					address: settings.nav_address || '目的地',
					scale: 18
				});
			}
		}
	}
</script>

<style scoped>
	/* 每个 DIY 块统一包裹：底色由内联 _wrapStyle 控制，这里负责裁切（不加间距、不做入场动画）*/
	.diy-block {
		overflow: hidden;
	}

	/* 空状态 */
	.diy-empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 160rpx 0;
	}

	.diy-empty-text {
		margin-top: 24rpx;
		font-size: 26rpx;
		color: #b2b2b2;
	}

	/* 到底 footer：细线分隔，替代原硬编码文案 */
	.diy-footer {
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 48rpx 0 24rpx;
	}

	.diy-footer-line {
		width: 48rpx;
		height: 1rpx;
		background: #e5e5e5;
		margin: 0 16rpx;
	}

	.diy-footer-text {
		font-size: 22rpx;
		color: #c0c4cc;
		letter-spacing: 2rpx;
	}

	/* --- 悬浮按钮组 --- */
	.button-group {
		position: fixed;
		bottom: 200rpx;
		right: 20rpx;
		width: 100rpx;
		z-index: 999;
	}

	.floating-btn-item {
		margin-bottom: 20rpx;
	}

	.floating-btn {
		width: 84rpx;
		height: 84rpx;
		border-radius: 50%;
		/* 毛玻璃半透明白底 + 柔和投影，给图标层次（图片图标会铺满覆盖，矢量兜底时露出底色） */
		background: rgba(255, 255, 255, 0.88);
		backdrop-filter: blur(10rpx);
		box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.14);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.floating-btn-icon {
		width: 100%;
		height: 100%;
		border-radius: 50%;
	}
	
	/* 清楚button默认样式 */
	button::after {
		border: none;
	}

	button {
		position: relative;
		display: block;
		margin-left: auto;
		margin-right: auto;
		padding: 0;
		box-sizing: border-box;
		text-align: center;
		text-decoration: none;
		-webkit-tap-highlight-color: transparent;
		overflow: hidden;
		color: #000;
		background-color: #fff;
	}
</style>
