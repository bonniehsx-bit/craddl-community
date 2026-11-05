<template>
	<tui-page>
		<view class="container" :style="themeStyle">
			<!--header-->
			<!-- 吸顶层:一层容器搞定,不再套 tui-header-box / tui-header 三层。
			     之前那套是「内联 style + 全局 tui-* + gl-* 覆盖」三份规则打架,
			     白底在第二层、圆角在第二层、padding 在第三层内联、z-index 在第一层,
			     改一处冒一个新问题。这里重写成扁平结构,所有样式集中在 .gl-bar 一族。 -->
			<view class="gl-bar">
				<view class="gl-bar-search" @tap="search">
					<icon type="search" :size="14" color="#999"></icon>
					<text class="gl-bar-ph" v-if="!searchKey">输入内容搜索商品</text>
					<view class="gl-bar-key" v-else>
						<text class="gl-bar-key-txt">{{ searchKey }}</text>
						<tui-icon name="shut" :size="12" color="#fff"></tui-icon>
					</view>
					<view class="gl-bar-btn">搜索</view>
				</view>

				<view class="gl-bar-sort">
					<view class="gl-sort-item" v-for="tab in sortTabs" :key="tab.key"
						:class="{ 'gl-sort-on': sortBy === tab.key }" @tap="switchSort(tab.key)">
						<text class="gl-sort-text">{{ tab.name }}</text>
						<text v-if="tab.key === 'price'" class="gl-sort-arrow">{{ priceArrow }}</text>
					</view>
					<view class="gl-view-toggle" @tap="isList = !isList">
						<tui-icon :name="isList ? 'grid' : 'list'" :size="16" color="#666"></tui-icon>
					</view>
				</view>
			</view>
			<!-- 吸顶层是 fixed、不占位,垫一块等高空白,列表/空态/加载提示才排在它下面 -->
			<view class="gl-bar-holder"></view>
			<!--header-->


			<tui-block v-if="list.length==0&&!loadding">
				<view>
					<tui-no-data :fixed="false">
						暂无相关商品~</tui-no-data>
				</view>
			</tui-block>
			<!--list-->
			<tui-block v-if="list.length>0">
				<view class="tui-product-list gl-grid">
					<view class="tui-product-container">
						<block v-for="(item, index) in list" :key="index" v-if="(index + 1) % 2 != 0 || isList">
						<view class="gl-card" @tap="pageTo(item)">
							<view class="gl-thumb">
								<image :src="thumbSrc(item.cover_pic)" class="gl-img" mode="aspectFill" @error="thumbFail(item.cover_pic)"></image>
								<view class="gl-tag" v-if="item.subtitle">{{ item.subtitle }}</view>
							</view>
							<view class="gl-info">
								<view class="gl-name">{{ item.name }}</view>
								<view class="gl-bottom">
									<view class="gl-price-wrap">
										<view class="gl-price" v-if="item.is_negotiable != 1">
											<text class="gl-sym">¥</text><text class="gl-num">{{ priceInt(item.price) }}</text><text class="gl-dec">{{ priceDec(item.price) }}</text>
										</view>
										<view class="gl-price" v-else><text class="gl-num sm">面议</text></view>
										<text class="gl-origin" v-if="item.is_negotiable != 1 && Number(item.origin_price) > Number(item.price)">¥{{ item.origin_price }}</text>
										<text class="gl-sold">已售 {{ soldOf(item) }}{{ item.unit || '' }}</text>
									</view>
									<view class="gl-go">
										<tui-icon name="cart" :size="13" color="#fff"></tui-icon>
									</view>
								</view>
							</view>
						</view>
						</block>
					</view>
					<view class="tui-product-container" v-if="!isList">
						<block v-for="(item, index) in list" :key="index" v-if="(index + 1) % 2 == 0">
						<view class="gl-card" @tap="pageTo(item)">
							<view class="gl-thumb">
								<image :src="thumbSrc(item.cover_pic)" class="gl-img" mode="aspectFill" @error="thumbFail(item.cover_pic)"></image>
								<view class="gl-tag" v-if="item.subtitle">{{ item.subtitle }}</view>
							</view>
							<view class="gl-info">
								<view class="gl-name">{{ item.name }}</view>
								<view class="gl-bottom">
									<view class="gl-price-wrap">
										<view class="gl-price" v-if="item.is_negotiable != 1">
											<text class="gl-sym">¥</text><text class="gl-num">{{ priceInt(item.price) }}</text><text class="gl-dec">{{ priceDec(item.price) }}</text>
										</view>
										<view class="gl-price" v-else><text class="gl-num sm">面议</text></view>
										<text class="gl-origin" v-if="item.is_negotiable != 1 && Number(item.origin_price) > Number(item.price)">¥{{ item.origin_price }}</text>
										<text class="gl-sold">已售 {{ soldOf(item) }}{{ item.unit || '' }}</text>
									</view>
									<view class="gl-go">
										<tui-icon name="cart" :size="13" color="#fff"></tui-icon>
									</view>
								</view>
							</view>
						</view>
						</block>
					</view>
				</view>
			</tui-block>

			<!--list-->
			<!--加载loadding-->
			<tui-loadmore v-if="loadding" :index="3" type="red"></tui-loadmore>
			<tui-nomore v-if="!pullUpOn && list.length > 0" backgroundColor="#f7f7f7"></tui-nomore>
			<!--加载loadding-->
		</view>

	</tui-page>
</template>

<script>
	import { thumbMixin } from '@/utils/thumb.js';

	export default {
		mixins: [thumbMixin],
		data() {
			return {
				searchKey: '', //搜索关键词
				width: 200, //header宽度
				height: 64, //header高度
				inputTop: 0, //搜索框距离顶部距离
				arrowTop: 0, //箭头距离顶部距离
				dropScreenH: 0, //下拉筛选框距顶部距离
				// ⚠ 下面这批(attrData / attrIndex / dropScreenShow / tabIndex / drawer*
				//    / selectedName / selectH)是一整套**没有 UI 的孤儿状态** ——
				//    模板里早就没有抽屉筛选和属性多选了,但 methods 区还留着一堆
				//    操作它们的方法(btnCloseDrop / changeAttr / 抽屉开关等)。
				//    删 data 会让那些 methods 运行时报错,所以先原样留着,
				//    要清就得连 methods 和对应的 .tui-drawer-* 样式一起清,单独开一次收拾。
				attrData: [],
				attrIndex: -1,
				dropScreenShow: false,
				scrollTop: 0,
				tabIndex: 0, //顶部筛选索引
				isList: false, //是否以列表展示  | 列表或大图
				drawer: false,
				drawerH: 0, //抽屉内部scrollview高度
				selectedName: '综合',
				selectH: 0,

				// ── 本次新增:排序 ──
				// 后端 /api/goods/list 早就支持 sort_by + sort_order
				// (sales / price / 不传=综合),这个页面之前一个都没传。
				sortBy: '',           // '' = 综合
				sortOrder: 'desc',
				sortTabs: [
					{ key: '', name: '综合' },
					{ key: 'sales', name: '销量' },
					{ key: 'price', name: '价格' }
				],

				list: [],
				page: 1,
				cat_id: '',
				is_no_more: false,
				loadding: true,
				pullUpOn: true,
				coupon_id:'',
			};
		},
		onLoad: function(options) {
			this.searchKey = options.keyword || '';
			this.cat_id = options.cat_id || '';
			this.coupon_id=options.coupon_id||'';
			let obj = {};
			// #ifdef MP-WEIXIN
			obj = wx.getMenuButtonBoundingClientRect();
			// #endif
			// #ifdef MP-BAIDU
			obj = swan.getMenuButtonBoundingClientRect();
			// #endif
			// #ifdef MP-ALIPAY
			my.hideAddToDesktopMenu();
			// #endif
			uni.getSystemInfo({
				success: res => {
					this.width = obj.left || res.windowWidth;
					this.height = obj.top ? obj.top + obj.height + 8 : res.statusBarHeight + 44;
					this.inputTop = obj.top ? obj.top + (obj.height - 30) / 2 : res.statusBarHeight + 7;
					this.arrowTop = obj.top ? obj.top + (obj.height - 32) / 2 : res.statusBarHeight + 6;

					//略小，避免误差带来的影响
					this.dropScreenH = (this.height * 750) / res.windowWidth + 186;
					this.drawerH = res.windowHeight - uni.upx2px(100) - this.height;
				}
			});
			this.getList();

		},
		computed: {
			priceArrow() {
				if (this.sortBy !== 'price') return '⇅';
				return this.sortOrder === 'desc' ? '↓' : '↑';
			}
		},
		methods: {
			getList() {

				if (this.is_no_more) {
					uni.showToast({
						title: '没有更多数据'
					})
					return;
				}
				this.$request({
					url: '/api/goods/list',
					data: {
						keyword: this.searchKey,
						page: this.page,
						cat_id: this.cat_id,
						coupon_id: this.coupon_id,
						// 综合排序不传 sort_by,交给后端默认的 is_recommend + sort 规则
						sort_by: this.sortBy,
						sort_order: this.sortOrder
					}
				}).then(res => {
					this.loadding = false;
					if (res.code == 0) {
						if (this.page == 1) {
							this.list = res.data.list
						} else {
							this.list = this.list.concat(res.data.list)
						}
						if (res.data.pagination.page_count > this.page) {
							this.page++;
						} else {
							this.is_no_more = true;
						}
					}

				})
			},

			/**
			 * 切换排序。
			 * 价格档反复点是升↔降来回切,其余档位只有降序一种含义
			 * (销量当然是从高到低,没人要看卖得最差的)。
			 * 换排序必须把 page 归 1、is_no_more 清掉,否则新排序只会从旧页码往后接。
			 */
			switchSort(key) {
				if (key === 'price' && this.sortBy === 'price') {
					this.sortOrder = this.sortOrder === 'desc' ? 'asc' : 'desc';
				} else {
					if (this.sortBy === key) return;   // 同一档重复点,不必重新拉
					this.sortBy = key;
					this.sortOrder = 'desc';
				}
				this.page = 1;
				this.is_no_more = false;
				this.loadding = true;
				this.list = [];
				this.getList();
			},

			px(num) {
				return uni.upx2px(num) + 'px';
			},
			btnDropChange: function(e) {
				let index = e.currentTarget.dataset.index;
				let arr = JSON.parse(JSON.stringify(this.attrArr[index].list));
				if (arr.length === 0) {
					this.btnCloseDrop();
					this.$set(this.attrArr[index], 'isActive', !this.attrArr[index].isActive);
				} else {
					this.attrData = arr;
					this.attrIndex = index;
					this.dropScreenShow = true;
					this.$set(this.attrArr[index], 'isActive', false);
					this.scrollTop = 1;
					this.$nextTick(() => {
						this.scrollTop = 0;
					});
				}
			},
			btnSelected: function(e) {
				let index = e.currentTarget.dataset.index;
				this.$set(this.attrData[index], 'selected', !this.attrData[index].selected);
			},
			reset() {
				let arr = this.attrData;
				for (let item of arr) {
					item.selected = false;
				}
				this.attrData = arr;
			},
			btnCloseDrop() {
				this.scrollTop = 1;
				this.$nextTick(() => {
					this.scrollTop = 0;
				});
				this.dropScreenShow = false;
				this.attrIndex = -1;
			},
			btnSure: function() {
				let index = this.attrIndex;
				let arr = this.attrData;
				let active = false;
				let attrName = '';
				//这里只是为了展示选中效果,并非实际场景
				for (let item of arr) {
					if (item.selected) {
						active = true;
						attrName += attrName ? ';' + item.name : item.name;
					}
				}
				let obj = this.attrArr[index];
				this.btnCloseDrop();
				this.$set(obj, 'isActive', active);
				this.$set(obj, 'selectedName', attrName);
			},
			showDropdownList: function() {
				this.selectH = 246;
				this.tabIndex = 0;
			},
			hideDropdownList: function() {
				this.selectH = 0;
			},
			dropdownItem: function(e) {
				let index = e.currentTarget.dataset.index;
				let arr = this.dropdownList;
				for (let i = 0; i < arr.length; i++) {
					if (i === index) {
						arr[i].selected = true;
					} else {
						arr[i].selected = false;
					}
				}
				this.dropdownList = arr;
				this.selectedName = index == 0 ? '综合' : '价格';
				this.selectH = 0;
			},
			screen: function(e) {
				let index = e.currentTarget.dataset.index;
				this.hideDropdownList();
				this.btnCloseDrop();
				if (index == 0) {
					this.showDropdownList();
				} else if (index == 1) {
					this.tabIndex = 1;
				} else if (index == 2) {
					this.isList = !this.isList;
				} else if (index == 3) {
					this.drawer = true;
				}
			},
			closeDrawer: function() {
				this.drawer = false;
			},
			back: function() {
				if (this.drawer) {
					this.closeDrawer();
				} else {
					uni.navigateBack();
				}
			},
			search: function() {
				uni.redirectTo({
					url: '/others/search/search'
				});
			},
			// 销量口径与公共组件 tui-goods-item 完全一致:虚拟销量优先,
			// 用 == null 判断而不是 || —— 虚拟销量填 0 是运营明确要显示 0,
			// 用 || 会把 0 当"没填"回退到真实销量,反把想藏的真实销量露出来。
			soldOf(g) {
				g = g || {};
				return (g.virtual_sales === undefined || g.virtual_sales === null || g.virtual_sales === '')
					? (g.sales || 0)
					: g.virtual_sales;
			},
			// 价格拆成整数和小数两截:整数大、小数小,一眼先读到「30」(同首页商品组 tui-goods-group)
			priceInt(price) {
				const s = String(price == null ? '' : price);
				const i = s.indexOf('.');
				return i < 0 ? s : s.slice(0, i);
			},
			priceDec(price) {
				const s = String(price == null ? '' : price);
				const i = s.indexOf('.');
				return i < 0 ? '' : s.slice(i);
			},

			pageTo(row) {
				uni.navigateTo({
					url: "/others/goods/goods?id=" + row.id
				})
			}
		},
		onReachBottom: function() {
			if (!this.pullUpOn) return;
			this.loadding = true;
			if (this.is_no_more) {
				this.loadding = false;
				this.pullUpOn = false;
			} else {
				this.getList();
				this.loadding = false;
			}
		}
	};
</script>

<style>
	page {
		background-color: #f7f7f7;
	}

	.container {
		padding-bottom: env(safe-area-inset-bottom);
	}

	/* 隐藏scroll-view滚动条*/

	::-webkit-scrollbar {
		width: 0;
		height: 0;
		color: transparent;
	}

	.tui-header-box {
		width: 100%;
		background: #fff;
		z-index: 99998;
		left: 0;
		top: 0;
	}

	.tui-header {
		display: flex;
		align-items: flex-start;
	}

	.tui-back {
		margin-left: 8rpx;
		height: 32px !important;
		width: 32px !important;
	}

	.tui-searchbox {
		width: 100%;
		height: 30px;

		border-radius: 15px;
		font-size: 12px;
		background: #f7f7f7;
		padding: 3px 10px;
		box-sizing: border-box;
		color: #999;
		display: flex;
		align-items: center;
		overflow: hidden;
	}

	/* #ifdef MP */
	.tui-search-mr {
		margin-right: 20rpx !important;
	}

	/* #endif */

	.tui-search-text {
		padding-left: 16rpx;
	}

	.tui-search-key {
		max-width: 80%;
		height: 100%;
		padding: 0 16rpx;
		margin-left: 12rpx;
		display: flex;
		align-items: center;
		border-radius: 15px;
		background: rgba(0, 0, 0, 0.5);
		color: #fff;
	}

	.tui-key-text {
		box-sizing: border-box;
		padding-right: 12rpx;
		font-size: 12px;
		line-height: 12px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/*screen*/

	.tui-header-screen {
		width: 100%;
		box-sizing: border-box;
		background: #fff;
		position: fixed;
		z-index: 1000;
	}

	.tui-screen-top,
	.tui-screen-bottom {
		display: flex;
		align-items: center;
		justify-content: space-between;
		font-size: 28rpx;
		color: #333;
	}

	.tui-screen-top {
		height: 88rpx;
		position: relative;
		background: #fff;
	}

	.tui-top-item {
		height: 28rpx;
		line-height: 28rpx;
		flex: 1;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.tui-topitem-active {
		color: #D71F1F;
	}

	.tui-screen-bottom {
		height: 100rpx;
		padding: 0 30rpx;
		box-sizing: border-box;
		font-size: 24rpx;
		align-items: center;
		overflow: hidden;
	}

	.tui-bottom-text {
		line-height: 26rpx;
		max-width: 82%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.tui-bottom-item {
		flex: 1;
		width: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0 12rpx;
		box-sizing: border-box;
		background-color: #f7f7f7;
		margin-right: 20rpx;
		white-space: nowrap;
		height: 60rpx;
		border-radius: 40rpx;
	}

	.tui-bottom-item:last-child {
		margin-right: 0;
	}

	.tui-btmItem-active {
		background-color: #fcedea !important;
		color: #D71F1F;
		font-weight: bold;
		position: relative;
	}

	.tui-btmItem-active::after {
		content: '';
		position: absolute;
		border: 1rpx solid #D71F1F;
		width: 100%;
		height: 100%;
		border-radius: 40rpx;
		left: 0;
		top: 0;
	}

	.tui-btmItem-tap {
		position: relative;
		border-bottom-left-radius: 0;
		border-bottom-right-radius: 0;
	}

	.tui-btmItem-tap::after {
		content: '';
		position: absolute;
		width: 100%;
		height: 22rpx;
		background: #f7f7f7;
		left: 0;
		top: 58rpx;
	}

	.tui-bold {
		font-weight: bold;
	}

	.tui-active {
		color: #D71F1F;
	}

	.tui-addr-left {
		padding-left: 6rpx;
	}

	.tui-seizeaseat-20 {
		height: 20rpx;
	}

	.tui-seizeaseat-30 {
		height: 30rpx;
	}

	/*screen*/

	/*顶部下拉选择 属性*/

	.tui-scroll-box {
		width: 100%;
		height: 480rpx;
		box-sizing: border-box;
		position: relative;
		z-index: 99;
		color: #fff;
		font-size: 30rpx;
		word-break: break-all;
	}

	.tui-drop-item {
		color: #333;
		height: 80rpx;
		font-size: 28rpx;
		padding: 20rpx 40rpx 20rpx 40rpx;
		box-sizing: border-box;
		display: inline-block;
		width: 50%;
	}

	.tui-drop-btnbox {
		width: 100%;
		height: 100rpx;
		position: absolute;
		left: 0;
		bottom: 0;
		box-sizing: border-box;
		display: flex;
	}

	.tui-drop-btn {
		width: 50%;
		font-size: 32rpx;
		text-align: center;
		height: 100rpx;
		line-height: 100rpx;
		border: 0;
	}

	.tui-btn-red {
		background: #D71F1F;
		color: #fff;
	}

	.tui-red-hover {
		background: #c51a15 !important;
		color: #e5e5e5;
	}

	.tui-btn-white {
		background: #fff;
		color: #333;
	}

	.tui-white-hover {
		background: #e5e5e5;
		color: #2e2e2e;
	}

	/*顶部下拉选择 属性*/

	/*顶部下拉选择 综合*/

	.tui-dropdownlist {
		width: 100%;
		position: absolute;
		background-color: #fff;
		border-bottom-left-radius: 24rpx;
		border-bottom-right-radius: 24rpx;
		overflow: hidden;
		box-sizing: border-box;
		padding-top: 10rpx;
		padding-bottom: 26rpx;
		left: 0;
		top: 88rpx;
		visibility: hidden;
		transition: all 0.2s ease-in-out;
		z-index: 999;
	}

	.tui-dropdownlist-show {
		visibility: visible;
	}

	.tui-dropdownlist-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: rgba(0, 0, 0, 0.6);
		z-index: -1;
		transition: all 0.2s ease-in-out;
		opacity: 0;
		visibility: hidden;
	}

	.tui-mask-show {
		opacity: 1;
		visibility: visible;
	}

	.tui-dropdownlist-item {
		color: #333;
		height: 70rpx;
		font-size: 28rpx;
		padding: 0 40rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	/*顶部下拉选择 综合*/

	.tui-drawer-box {
		width: 686rpx;
		font-size: 24rpx;
		overflow: hidden;
		position: relative;
		padding-bottom: 100rpx;
	}

	.tui-drawer-title {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 30rpx;
		box-sizing: border-box;
		height: 80rpx;
	}

	.tui-title-bold {
		font-size: 26rpx;
		font-weight: bold;
		flex-shrink: 0;
	}

	.tui-location {
		margin-right: 6rpx;
	}

	.tui-attr-right {
		width: 70%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		text-align: right;
	}

	.tui-all-box {
		width: 90%;
		white-space: nowrap;
		display: flex;
		align-items: center;
		justify-content: flex-end;
	}

	.tui-drawer-content {
		padding: 16rpx 30rpx 30rpx 30rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-sizing: border-box;
	}

	.tui-input {
		border: 0;
		height: 64rpx;
		border-radius: 32rpx;
		width: 45%;
		background-color: #f7f7f7;
		text-align: center;
		font-size: 24rpx;
		color: #333;
	}

	.tui-phcolor {
		text-align: center;
		color: #b2b2b2;
		font-size: 24rpx;
	}

	.tui-flex-attr {
		flex-wrap: wrap;
		justify-content: flex-start;
	}

	.tui-attr-item {
		width: 30%;
		height: 64rpx;
		background-color: #f7f7f7;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0 4rpx;
		box-sizing: border-box;
		border-radius: 32rpx;
		margin-right: 5%;
		margin-bottom: 5%;
	}

	.tui-attr-ellipsis {
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
		width: 96%;
		text-align: center;
	}

	.tui-attr-item:nth-of-type(3n) {
		margin-right: 0%;
	}

	.tui-attr-btnbox {
		width: 100%;
		position: absolute;
		left: 0;
		bottom: 0;
		box-sizing: border-box;
		padding: 0 30rpx;
		background: #fff;
	}

	.tui-attr-safearea {
		height: 100rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding-bottom: env(safe-area-inset-bottom);
	}

	.tui-safearea-bottom {
		width: 100%;
		height: env(safe-area-inset-bottom);
	}

	.tui-attr-btnbox::before {
		content: '';
		position: absolute;
		top: 0;
		right: 0;
		left: 0;
		border-top: 1px solid #eaeef1;
		transform: scaleY(0.5) translateZ(0);
		transform-origin: 0 0;
	}

	.tui-drawer-btn {
		width: 47%;
		text-align: center;
		height: 60rpx;
		border-radius: 30rpx;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		box-sizing: border-box;
	}

	.tui-drawerbtn-black {
		border: 1rpx solid #555;
	}

	.tui-drawerbtn-primary {
		background: #D71F1F;
		color: #fff;
	}

	/* 商品列表*/

	.tui-product-list {
		display: flex;
		justify-content: space-between;
		flex-direction: row;
		flex-wrap: wrap;
		box-sizing: border-box;
	}

	.tui-product-container {
		flex: 1;
		margin-right: 10rpx;
	}

	.tui-product-container:last-child {
		margin-right: 0;
	}

	/* ═══ 吸顶层:搜索 + 排序,一套样式集中在这儿 ═══
	   ⚠ 本页 <style> 没有 scoped,.tui-* 那些类是给别的页面共用的(tui-searchbox
	      有 5 个页面在用),所以这里一律用 gl-bar-* 独立命名,不碰它们。 */
	.gl-bar {
		position: fixed;
		left: 0;
		right: 0;
		/* 小程序/App 编译成 0;H5 的导航栏是页面里画的,top:0 会被它盖住搜索框,这里是导航栏高度 */
		top: var(--window-top);
		z-index: 99;
		/* 实心白底铺满整宽 —— 商品滚上来会被完全挡住,不会浮在排序文字下面 */
		background: #fff;
		box-shadow: 0 2rpx 12rpx rgba(23, 24, 28, .06);
		/* 左右 32rpx:让搜索按钮距屏幕边缘 20px,不贴边 */
		padding: 20rpx 32rpx 0;
		box-sizing: border-box;
	}

	/* 吸顶层占位 = 吸顶层高 189rpx(上内边距 20 + 搜索框 72 + 间距 16 + 排序栏 80 + 分隔线 1)+ 留白 20rpx。
	   ⚠ 改了 .gl-bar 里任何高度/间距,这里要一起改。
	   不要再减导航栏高度:原生导航栏在页面区域之外,吸顶层的 top 和这块占位都从导航栏下沿算起
	   (8/24 那版按「−导航 44px」算成 124rpx,首屏第一排商品被压住 65rpx)。 */
	.gl-bar-holder {
		height: 209rpx;
	}

	/* 搜索框 */
	.gl-bar-search {
		display: flex;
		align-items: center;
		height: 72rpx;
		background: #f4f5f7;
		border-radius: 36rpx;
		padding: 0 8rpx 0 24rpx;
	}

	.gl-bar-ph {
		flex: 1;
		margin-left: 12rpx;
		font-size: 26rpx;
		color: #a3a7b0;
	}

	/* 关键词胶囊:只包住文字,margin-right:auto 把搜索按钮顶到最右 */
	.gl-bar-key {
		display: flex;
		align-items: center;
		max-width: 60%;
		margin-left: 12rpx;
		margin-right: auto;
		padding: 0 16rpx;
		height: 48rpx;
		border-radius: 24rpx;
		background: rgba(0, 0, 0, .45);
	}

	.gl-bar-key-txt {
		font-size: 24rpx;
		color: #fff;
		margin-right: 8rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.gl-bar-btn {
		flex-shrink: 0;
		height: 56rpx;
		line-height: 56rpx;
		padding: 0 28rpx;
		border-radius: 28rpx;
		background: var(--theme-color, #e41f19);
		color: #fff;
		font-size: 26rpx;
		text-align: center;
	}

	/* 排序栏:紧贴搜索框下方,同一块白底里,上方一道细分隔线 */
	.gl-bar-sort {
		display: flex;
		align-items: center;
		height: 80rpx;
		margin-top: 16rpx;
		border-top: 1rpx solid #f2f3f5;
	}

	/* 本页专属的网格留白。
	   ⚠ 不要直接改上面的 .tui-product-list / .tui-product-container ——
	   本页和 others/footmark 的 <style> 都没有 scoped,同名 class 在 H5 下是同一份
	   全局样式,改本体会顺带把足迹页的间距也改掉。所以套一层 .gl-grid 限定作用域。

	   20rpx 不是拍脑袋:顶部搜索框那层 .tui-header 是 width:95% + margin:auto,
	   左右各空出 2.5% ≈ 18.75rpx,取 20rpx 让商品卡跟搜索框对齐在同一条竖线上。 */
	.gl-grid {
		padding: 0 20rpx;
	}



	/* 关键词态:关键词块只包住文字(不撑满,否则深色底铺满整条),
	   用 margin-right:auto 把搜索按钮顶到最右。限定在 .gl-searchbox 内,
	   不动全局 .tui-search-key(footmark 等页也用它)。 */


	/* ── 商品卡(效果图 A 清爽风,自写不用公共组件) ── */
	.gl-card {
		background: #fff;
		/* 20rpx 和首页商品组(tui-goods-group)一致 */
		border-radius: 20rpx;
		overflow: hidden;
		margin-bottom: 20rpx;
		box-shadow: 0 6rpx 20rpx rgba(23,24,28,.08);
	}

	.gl-thumb {
		position: relative;
		width: 100%;
		/* 正方形图 */
		height: 0;
		padding-bottom: 100%;
		background: #f2f3f5;
		overflow: hidden;
		/* 不单独设圆角:卡片 overflow:hidden 已经裁好,改卡片圆角只改一处 */
	}

	.gl-img {
		position: absolute;
		top: 0; left: 0;
		width: 100%;
		height: 100%;
	}

	/* 左上副标题角标 */
	.gl-tag {
		position: absolute;
		top: 12rpx; left: 0;
		max-width: 70%;
		padding: 4rpx 14rpx;
		background: rgba(0,0,0,.5);
		color: #fff;
		font-size: 20rpx;
		border-radius: 0 20rpx 20rpx 0;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.gl-info {
		padding: 16rpx 16rpx 18rpx;
	}

	.gl-name {
		font-size: 26rpx;
		line-height: 1.4;
		color: #17181c;
		/* 两行截断,矮商品名也占两行高,卡片等高 */
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		min-height: 74rpx;
	}

	/* 价格 + 原价 + 已售在左,购物车钮在右,一行搞定。
	   原来价格一行、「已售 + 钮」再一行,卡片四行高。 */
	.gl-bottom {
		margin-top: 14rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.gl-price-wrap {
		flex: 1;
		min-width: 0;
		display: flex;
		align-items: baseline;
		white-space: nowrap;
		overflow: hidden;
	}

	.gl-price {
		flex-shrink: 0;
		color: var(--theme-color, #e8302b);
		display: flex;
		align-items: baseline;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}

	/* 整数大、¥ 和小数小:原来 46rpx 整串一样大,「.00」白占位置 */
	.gl-sym,
	.gl-dec {
		font-size: 22rpx;
	}

	.gl-num {
		font-size: 40rpx;
		line-height: 1;
		font-weight: 700;
	}

	.gl-num.sm {
		font-size: 30rpx;
	}

	.gl-origin {
		flex-shrink: 0;
		font-size: 22rpx;
		color: #a3a7b0;
		text-decoration: line-through;
		margin-left: 8rpx;
	}

	/* 放不下时先省略「已售」,价格和原价不被挤。
	   nowrap 必须写在它自己身上:H5 的 <text> 自带 pre-wrap,父级的 nowrap 管不到,会被挤成一字一行 */
	.gl-sold {
		min-width: 0;
		white-space: nowrap;
		margin-left: 10rpx;
		font-size: 22rpx;
		color: #9a9ea6;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/* 右下圆形购物车钮,主题色 + 同色淡投影,和首页商品组的「圆形按钮」同一套样子。
	   整张卡片可点进详情,这个钮只是入口提示,不单独加购(加购要选规格,在详情页做)。
	   投影色 --theme-shadow 来自根节点绑定的 themeStyle。 */
	.gl-go {
		flex-shrink: 0;
		margin-left: 8rpx;
		width: 48rpx;
		height: 48rpx;
		border-radius: 50%;
		background: var(--theme-color, #e8302b);
		box-shadow: 0 4rpx 12rpx var(--theme-shadow);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* ── 排序栏 ──
	   ⚠ 本页 <style> 没有 scoped,和 others/footmark 共享全局 class。
	      所有新增样式一律带 gl- 前缀,别碰任何 tui-* 本体。 */


	.gl-sort-item {
		flex: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 100%;
	}

	.gl-sort-text {
		font-size: 26rpx;
		color: #666;
	}

	/* 选中态用主题色。这里不能写死颜色 —— 各商城主题色不同 */
	.gl-sort-on .gl-sort-text {
		color: var(--theme-color, #e41f19);
		font-weight: 600;
	}

	.gl-sort-arrow {
		font-size: 22rpx;
		color: #bbb;
		margin-left: 4rpx;
	}

	.gl-sort-on .gl-sort-arrow {
		color: var(--theme-color, #e41f19);
	}

	/* 视图切换钉在最右,不参与 flex:1 的均分 */
	.gl-view-toggle {
		width: 60rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border-left: 1rpx solid #f0f0f0;
	}

	/* 列间距与两端留白取同一个值,横向间距才是均匀的 */
	.gl-grid .tui-product-container {
		margin-right: 20rpx;
		/* flex 子项不加这个,长标题会把某一列撑宽,两列就不等宽了 */
		min-width: 0;
	}

	.gl-grid .tui-product-container:last-child {
		margin-right: 0;
	}

	.tui-pro-item {
		width: 100%;
		margin-bottom: 10rpx;
		background: #fff;
		box-sizing: border-box;
		border-radius: 12rpx;
		overflow: hidden;
		transition: all 0.15s ease-in-out;
	}

	.tui-flex-list {
		display: flex;
		margin-bottom: 1rpx !important;
	}

	.tui-pro-img {
		width: 100%;
		display: block;
		height: 375rpx;
	}

	.tui-proimg-list {
		width: 260rpx;
		height: 260rpx !important;
		flex-shrink: 0;
		border-radius: 12rpx;
	}

	.tui-pro-content {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		box-sizing: border-box;
		padding: 20rpx;
	}

	.tui-pro-tit {
		color: #2e2e2e;
		font-size: 26rpx;
		word-break: break-all;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
	}

	.tui-pro-price {
		padding-top: 18rpx;
	}

	.tui-sale-price {
		font-size: 34rpx;
		font-weight: 500;
		font-weight: bold;
		color: #D71F1F !important;
	}

	.tui-factory-price {
		font-size: 24rpx;
		color: #a0a0a0;
		text-decoration: line-through;
		padding-left: 12rpx;
	}

	.tui-pro-pay {
		padding-top: 10rpx;
		font-size: 24rpx;
		color: #656565;
	}

	/* 商品列表*/
</style>
