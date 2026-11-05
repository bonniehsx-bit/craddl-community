<template>
	<tui-bottom-popup :show="show" @close="close">
		<view class="tui-popup-box" v-if="goods">
			<!-- 关闭按钮 -->
			<view class="tui-right" @click="close">
				<tui-icon name="close-fill" color="#ccc" :size="24"></tui-icon>
			</view>

			<!-- 商品头部信息 -->
			<view class="tui-product-box">
				<image :src="currentSku && currentSku.cover_pic ? currentSku.cover_pic : goods.cover_pic"
					mode="aspectFill" class="tui-popup-img"></image>
				<view class="tui-popup-info">
					<view class="tui-popup-price" v-if="!goods.is_negotiable">
						<view class="price-row">
							<text class="currency-symbol" :style="{color: themeColor}">¥</text>
							<text class="tui-amount tui-bold" :style="{color: themeColor}">{{ priceInt }}</text>
							<text class="tui-amount-dec tui-bold" :style="{color: themeColor}">{{ priceDec }}</text>
							<view v-if="isLevelPrice == 1" class="member-tag">会员价</view>
						</view>

						<view class="stock-row">
							<text class="tui-number">库存 {{ currentSku ? currentSku.stock : goods.stock }} {{ goods.unit || '件' }}</text>
							<text class="tui-number" v-if="currentSku && currentSku.no">编号 {{ currentSku.no }}</text>
						</view>
					</view>
					<view class="tui-popup-price" v-else>
						<view class="tui-amount tui-bold" :style="{color: themeColor}">面议</view>
					</view>
					
					<!-- 已选提示 -->
					<view class="selected-text" v-if="goods.is_attr == 1 && showSpecGroups">
						<text v-if="currentSku">已选「{{ specString }}」</text>
						<text v-else style="color: #ff7900">请选择规格</text>
					</view>
				</view>
			</view>

			<!-- 滚动区域 -->
			<scroll-view scroll-y class="tui-popup-scroll">
				<view class="tui-scrollview-box">
					<!-- 规格列表 -->
					<view v-if="goods.is_attr == 1 && showSpecGroups" v-for="(group, groupIndex) in specGroups" :key="groupIndex" class="attr-group">
						<view class="tui-bold tui-attr-title">{{ group.name }}</view>
						<view class="tui-attr-box">
							<view class="tui-attr-item"
								:class="{ 'tui-attr-active': isValueSelected(group.name, value.name), 'tui-disabled': !isValueOptional(group.name, value.name) }"
								:style="{ 
									color: isValueSelected(group.name, value.name) ? themeColor : '#333',
									backgroundColor: isValueSelected(group.name, value.name) ? themeColorLight : '#f7f7f7',
									borderColor: isValueSelected(group.name, value.name) ? themeColor : 'transparent'
								}"
								@click="handleSelectSpec(groupIndex, valueIndex)"
								v-for="(value, valueIndex) in group.values" :key="valueIndex">
								{{ value.name }}
							</view>
						</view>
					</view>

					<!-- 无规格 / 只有一个「默认」占位规格:画一颗选中态的「默认」,让规格区不至于空着 -->
					<view v-if="goods.is_attr == 0 || !showSpecGroups" class="attr-group">
						<view class="tui-bold tui-attr-title">规格</view>
						<view class="tui-attr-box">
							<view class="tui-attr-item tui-attr-active"
								:style="{ color: themeColor, backgroundColor: themeColorLight, borderColor: themeColor }">
								默认
							</view>
						</view>
					</view>

					<!-- 数量选择 -->
					<view class="tui-number-box">
						<view class="tui-attr-title tui-bold">购买数量</view>
						<tui-numberbox :max="maxStock" :min="1" :value="num" @change="numChange"
							:iconColor="themeColor"></tui-numberbox>
					</view>
				</view>
			</scroll-view>

			<!-- 底部按钮 -->
			<view class="popup-buttons-container" :style="{paddingBottom: isIos ? '40rpx' : '24rpx'}"
				v-if="!goods.is_negotiable">
				<block v-if="isShowAddCart == 1">
					<view class="popup-action-btn add-cart-btn-custom"
						:style="{ backgroundColor: hexToRgba(themeColor, 0.12), color: themeColor }"
						@click="emitAction('addCart')">加入购物车</view>
					<view class="popup-action-btn buy-now-btn-custom"
						:style="{ background: 'linear-gradient(90deg,' + hexToRgba(themeColor, 0.75) + ',' + themeColor + ')', boxShadow: '0 4rpx 10rpx ' + hexToRgba(themeColor, 0.25) }"
						@click="emitAction('buy')">立即购买</view>
				</block>
				<block v-else>
					<view class="popup-action-btn buy-now-btn-custom single"
						:style="{ background: 'linear-gradient(90deg,' + hexToRgba(themeColor, 0.75) + ',' + themeColor + ')', boxShadow: '0 4rpx 10rpx ' + hexToRgba(themeColor, 0.25) }"
						@click="emitAction('buy')">立即购买</view>
				</block>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "goods-sku-popup",
		props: {
			show: {
				type: Boolean,
				default: false
			},
			goods: {
				type: Object,
				default: null
			},
			specGroups: {
				type: Array,
				default: () => []
			},
			skuList: {
				type: Array,
				default: () => []
			},
			isLevelPrice: {
				type: [Number, String],
				default: 0
			},
			isShowAddCart: {
				type: [Number, String],
				default: 1
			},
			isIos: {
				type: Boolean,
				default: false
			}
		},
		data() {
			return {
				num: 1,
				selectedSpecs: {},
				currentSku: null,
			}
		},
		computed: {
			maxStock() {
				if (!this.goods) return 1;
				if (this.goods.is_attr == 0) return this.goods.stock;
				return this.currentSku ? this.currentSku.stock : 99;
			},
			// 辅助计算属性，用于显示已选规格字符串
			specString() {
				if (!this.selectedSpecs) return '';
				return Object.values(this.selectedSpecs).filter(val => val).join(' / ');
			},
			/**
			 * 只有一个「规格 / 默认」时不画规格选择区。
			 * 后台给无规格商品也会生成这么一组模板,画出来就是一颗永远选中的「默认」按钮,
			 * 用户点不点都一样。这时走下面「无规格」那一块的展示即可。
			 */
			showSpecGroups() {
				const groups = this.specGroups || [];
				if (groups.length !== 1) return groups.length > 0;
				const values = groups[0].values || [];
				if (values.length > 1) return true;
				const only = values.length === 1 ? String(values[0].name || '').trim() : '';
				return !(only === '' || only === '默认');
			},
			// 价格拆成整数和小数两截,和详情页、购物车一个写法
			showPrice() {
				const price = this.currentSku ? this.currentSku.price : (this.goods && this.goods.price);
				return Number(price || 0).toFixed(2);
			},
			priceInt() {
				return this.showPrice.split('.')[0];
			},
			priceDec() {
				return '.' + this.showPrice.split('.')[1];
			}
		},
		watch: {
			specGroups: {
				handler(val) {
					if (val && val.length > 0) {
						this.initSpecs();
					}
				},
				immediate: true
			}
		},
		methods: {
			close() {
				this.$emit('close');
			},
			initSpecs() {
				this.selectedSpecs = {};
				this.specGroups.forEach(group => {
					this.$set(this.selectedSpecs, group.name, '');
				});
				if (this.skuList.length === 1) {
					this.skuList[0].specs.forEach(spec => {
						this.selectedSpecs[spec.group] = spec.value;
					});
					this.updateCurrentSku();
				}
			},
			handleSelectSpec(groupIndex, valueIndex) {
				const group = this.specGroups[groupIndex];
				const value = group.values[valueIndex];
				if (!this.isValueOptional(group.name, value.name)) return;

				if (this.selectedSpecs[group.name] === value.name) {
					this.$set(this.selectedSpecs, group.name, '');
				} else {
					this.$set(this.selectedSpecs, group.name, value.name);
				}
				this.updateCurrentSku();
			},
			isValueSelected(groupName, valueName) {
				return this.selectedSpecs[groupName] === valueName;
			},
			isValueOptional(groupName, valueName) {
				if (!this.skuList || this.skuList.length === 0) return true;
				const otherSelectedSpecs = [];
				for (const key in this.selectedSpecs) {
					if (key !== groupName && this.selectedSpecs[key]) {
						otherSelectedSpecs.push({
							group: key,
							value: this.selectedSpecs[key]
						});
					}
				}
				if (otherSelectedSpecs.length === 0) {
					return this.skuList.some(sku => sku.specs.some(spec => spec.group === groupName && String(spec
						.value) === String(valueName)));
				}
				for (const sku of this.skuList) {
					let hasCurrentValue = sku.specs.some(spec => spec.group === groupName && String(spec.value) === String(
						valueName));
					if (hasCurrentValue) {
						const satisfiesOthers = otherSelectedSpecs.every(selected => {
							return sku.specs.some(spec => spec.group === selected.group && String(spec.value) ===
								String(selected.value));
						});
						if (satisfiesOthers) return true;
					}
				}
				return false;
			},
			updateCurrentSku() {
				const allSelected = this.specGroups.every(group => this.selectedSpecs[group.name]);
				if (allSelected) {
					const selectedValues = this.specGroups.map(g => this.selectedSpecs[g.name]).sort();
					const sign = selectedValues.join(';');
					const foundSku = this.skuList.find(sku => {
						const skuSign = sku.specs.map(s => s.value).sort().join(';');
						return skuSign === sign;
					});
					this.currentSku = foundSku || null;
				} else {
					this.currentSku = null;
				}
				if (this.num > this.maxStock && this.maxStock > 0) {
					this.num = this.maxStock;
				} else if (this.maxStock === 0 && this.num > 1) {
					this.num = 1;
				}
				this.$emit('change', {
					currentSku: this.currentSku,
					selectedSpecs: this.selectedSpecs,
					num: this.num
				});
			},
			numChange(e) {
				this.num = e.value;
				this.$emit('change', {
					currentSku: this.currentSku,
					selectedSpecs: this.selectedSpecs,
					num: this.num
				});
			},
			emitAction(action) {
				this.$emit(action, {
					num: this.num,
					currentSku: this.currentSku
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心样式优化] */
	$uni-spacing-col: 30rpx;

	.tui-popup-box {
		position: relative;
		background-color: #fff;
		border-radius: 24rpx 24rpx 0 0;
		overflow: hidden;
	}

	/* 关闭按钮优化 */
	.tui-right {
		position: absolute;
		right: 30rpx;
		top: 30rpx;
		z-index: 10;
		/* 增加点击区域 */
		padding: 10rpx;
		margin: -10rpx;
	}

	/* 商品信息头部 */
	.tui-product-box {
		display: flex;
		align-items: flex-start;
		padding: 30rpx;
		position: relative;
		border-bottom: 1rpx solid #f5f5f5;
	}

	.tui-popup-img {
		height: 200rpx;
		width: 200rpx;
		border-radius: 12rpx;
		display: block;
		background-color: #f7f7f7;
		flex-shrink: 0;
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
	}

	.tui-popup-info {
		flex: 1;
		padding-left: 24rpx;
		display: flex;
		flex-direction: column;
		justify-content: flex-end;
		min-height: 200rpx; // 与图片等高
	}

	.tui-popup-price {
		margin-top: 10rpx;
	}
	
	.price-row {
		display: flex;
		align-items: baseline;
	}

	.currency-symbol {
		font-size: 28rpx;
		font-weight: bold;
	}

	.tui-amount {
		font-size: 52rpx;
		line-height: 1;
	}

	.tui-amount-dec {
		font-size: 32rpx;
		line-height: 1;
	}

	/* 会员价标签与详情页一致:金色底,不再和主题色抢眼 */
	.member-tag {
		font-size: 20rpx;
		font-weight: 600;
		color: #b4761a;
		background: linear-gradient(90deg, #fff0d0, #ffe1ae);
		margin-left: 12rpx;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		transform: translateY(-4rpx);
	}

	.stock-row {
		margin-top: 12rpx;
		display: flex;
		align-items: center;
		flex-wrap: wrap;

		.tui-number + .tui-number {
			margin-left: 20rpx;
		}
	}

	.tui-number {
		font-size: 24rpx;
		line-height: 1.5;
		color: #999;
		max-width: 100%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	
	.selected-text {
		font-size: 26rpx;
		color: #333;
		margin-top: 12rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/* 滚动区域 */
	.tui-popup-scroll {
		max-height: 60vh;
	}

	.tui-scrollview-box {
		padding: 10rpx 30rpx 10rpx 30rpx;
		box-sizing: border-box;
	}
	
	.attr-group {
		margin-bottom: 24rpx;
	}

	.tui-attr-title {
		padding: 24rpx 0 16rpx;
		color: #333;
		font-size: 28rpx;
	}

	.tui-attr-box {
		font-size: 0;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
	}

	.tui-attr-item {
		max-width: 100%;
		min-width: 120rpx;
		height: 60rpx;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: #f7f7f7;
		padding: 0 24rpx;
		box-sizing: border-box;
		border-radius: 30rpx;
		margin-right: 20rpx;
		margin-bottom: 20rpx;
		font-size: 26rpx;
		border: 1rpx solid transparent; // 预留边框位置防止抖动
		transition: all 0.2s;
	}

	/* 选中态样式由style动态控制，但保留基础结构 */
	.tui-attr-active {
		font-weight: bold;
	}

	.tui-attr-item.tui-disabled {
		color: #ccc !important;
		background: #fcfcfc !important;
		border-color: #f0f0f0 !important;
		text-decoration: line-through;
	}

	/* 数量选择 */
	.tui-number-box {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 26rpx 0 10rpx;
		box-sizing: border-box;
		border-top: 1rpx solid #f5f5f5; // 增加分割线
		margin-top: 20rpx;
	}

	/* 底部按钮 */
	.popup-buttons-container {
		width: 100%;
		display: flex;
		align-items: center;
		gap: 24rpx;
		padding: 20rpx 30rpx;
		box-sizing: border-box;
		background-color: #fff;
		box-shadow: 0 -2rpx 10rpx rgba(0,0,0,0.03);

		.popup-action-btn {
			flex: 1;
			height: 80rpx;
			border-radius: 40rpx;
			font-size: 28rpx;
			font-weight: bold;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: opacity 0.2s;

			&:active {
				opacity: 0.9;
			}

			&.add-cart-btn-custom {
				/* 次级按钮:淡填充 + 主题色文字,与底部栏 goods-bottom-bar 的 cart-btn 完全一致。
				   原来这里是硬编码的橙黄渐变 + 白字 —— 既跟外面那颗对不上,
				   在弹窗里还要和旁边跟主题色的「立即购买」并排,一橙一主题色自己先打架。
				   背景与文字色改由 :style 跟随主题色。 */
			}

			&.buy-now-btn-custom {
				/* 主按钮:实心主题色 + 白字。背景与外阴影由 :style 跟随主题色控制
				   (原硬编码红色阴影会与非红主题不匹配) */
				color: #fff;
			}

			&.single {
				flex: unset;
				width: 100%;
			}
		}
	}
</style>