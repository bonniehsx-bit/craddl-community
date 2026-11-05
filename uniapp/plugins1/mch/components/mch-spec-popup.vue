<template>
	<tui-bottom-popup :show="show" :zIndex="1010" @close="handleClose">
		<view class="spec-popup-content" :style="themeStyle">
			
			<view class="product-header">
				<image class="img" :src="selectedSku.cover_pic || product.cover_pic" mode="aspectFill" @click="previewImage"></image>
				
				<view class="info">
					<view class="price-box">
						<text class="sym" :style="{color: themeColor}">¥</text>
						<text class="num" :style="{color: themeColor}">{{ currentPrice }}</text>
					</view>
					<view class="stock">库存: {{ currentStock }}</view>
					<view class="selected-text">
						已选: <text :style="{color: '#333', fontWeight: 'bold'}">{{ selectedSpecText }}</text>
					</view>
				</view>
				
				<view class="close-btn" @click="handleClose">
					<tui-icon name="close-fill" size="20" color="#ccc"></tui-icon>
				</view>
			</view>

			<scroll-view scroll-y class="spec-scroll">
				<view class="spec-group" v-for="(group, gIndex) in specGroups" :key="gIndex">
					<view class="group-title">{{ group.name }}</view>
					<view class="values-list">
						<view class="value-item" 
							v-for="(val, vIndex) in group.values" 
							:key="vIndex"
							:class="selectedSpec[group.name] === val.name ? 'active' : ''"
							:style="selectedSpec[group.name] === val.name ? activeStyleStr : ''"
							@click="selectSpec(group.name, val.name)">
							{{ val.name }}
						</view>
					</view>
				</view>
				
				<view class="count-row">
					<view class="label">购买数量</view>
					<tui-numberbox :value="buyNum" :min="1" :max="currentStock" @change="onNumChange"></tui-numberbox>
				</view>
			</scroll-view>

			<view class="spec-footer" :style="{paddingBottom: safeAreaBottom + 'px'}">
				<button class="confirm-btn" 
					:style="{background: currentStock > 0 ? themeColor : '#ccc'}"
					:disabled="currentStock <= 0"
					@click="handleConfirm">
					{{ currentStock > 0 ? '加入购物车' : '暂时缺货' }}
				</button>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "mch-spec-popup",
		props: {
			// 控制显示隐藏
			show: {
				type: Boolean,
				default: false
			},
			// 商品对象
			product: {
				type: Object,
				default: () => ({})
			}
		},
		data() {
			return {
				selectedSpec: {}, // 已选规格映射 { "颜色": "红", "尺码": "L" }
				buyNum: 1, // 购买数量
				safeAreaBottom: 0
			};
		},
		computed: {
			// CSS 变量注入 (返回字符串，修复小程序报错)
			themeStyle() {
				return `--theme-color:${this.themeColor};`;
			},
			
			// 【核心修改】选中态样式字符串
			// 将原来的对象形式改为 CSS 字符串形式
			activeStyleStr() {
				const lightColor = this.hexToRgba(this.themeColor, 0.08);
				// 注意：小程序 style 字符串不需要大括号包裹
				return `background: ${lightColor}; color: ${this.themeColor}; border-color: ${this.themeColor}; font-weight: bold;`;
			},

			// 获取规格组列表
			specGroups() {
				return this.product?.specs_data?.spec_groups || [];
			},
			// 获取 SKU 列表
			skuList() {
				return this.product?.specs_data?.sku_list || [];
			},
			// 计算当前匹配的 SKU
			selectedSku() {
				if (this.skuList.length === 0) return {};
				
				// 简单的全匹配逻辑
				const targetSku = this.skuList.find(sku => {
					// 检查是否每一项规格都匹配
					return sku.specs.every(s => this.selectedSpec[s.group] === s.value);
				});

				return targetSku || {};
			},
			// 当前价格
			currentPrice() {
				return this.selectedSku.price || this.product.price || '0.00';
			},
			// 当前库存
			currentStock() {
				if (!this.selectedSku.id) return this.product.stock || 0;
				return this.selectedSku.stock || 0;
			},
			// 已选文本展示
			selectedSpecText() {
				const textArr = this.specGroups.map(g => this.selectedSpec[g.name]).filter(v => v);
				return textArr.length > 0 ? textArr.join(' / ') : '请选择规格';
			}
		},
		watch: {
			show(val) {
				if (val && this.product.id) {
					this.initDefaultSpec();
					this.buyNum = 1;
					
					// 获取底部安全区
					const sys = uni.getSystemInfoSync();
					this.safeAreaBottom = sys.safeAreaInsets ? sys.safeAreaInsets.bottom : 0;
				}
			}
		},
		methods: {initDefaultSpec() {
				const defaultSel = {};
				this.specGroups.forEach(group => {
					if (group.values && group.values.length > 0) {
						defaultSel[group.name] = group.values[0].name;
					}
				});
				this.selectedSpec = defaultSel;
			},

			selectSpec(groupName, valueName) {
				this.$set(this.selectedSpec, groupName, valueName);
			},

			onNumChange(e) {
				this.buyNum = e.value;
			},

			previewImage() {
				const url = this.selectedSku.cover_pic || this.product.cover_pic;
				if (url) {
					uni.previewImage({
						urls: [url]
					});
				}
			},

			handleClose() {
				this.$emit('close');
			},

			handleConfirm() {
				const isComplete = this.specGroups.every(g => this.selectedSpec[g.name]);
				if (!isComplete) {
					uni.showToast({ title: '请选择完整规格', icon: 'none' });
					return;
				}

				if (this.currentStock <= 0) {
					uni.showToast({ title: '该规格暂时缺货', icon: 'none' });
					return;
				}

				const result = {
					id: this.product.id,
					sku_id: this.selectedSku.id, 
					num: this.buyNum,
					price: this.currentPrice,
					specs: this.selectedSpecText
				};

				this.$emit('confirm', result);
			}
		},
		emits: ['close', 'confirm']
	}
</script>

<style lang="scss" scoped>
	.spec-popup-content {
		background: #fff;
		border-radius: 24rpx 24rpx 0 0;
		overflow: hidden;
		position: relative;
		z-index: 1011;
		display: flex;
		flex-direction: column;
		max-height: 80vh; 
	}

	/* 1. 头部区域 */
	.product-header {
		padding: 30rpx;
		display: flex;
		position: relative;
		border-bottom: 1rpx solid #f5f5f5;

		.img {
			width: 180rpx;
			height: 180rpx;
			border-radius: 12rpx;
			margin-right: 24rpx;
			background: #f7f7f7;
			flex-shrink: 0;
		}

		.info {
			flex: 1;
			display: flex;
			flex-direction: column;
			justify-content: flex-end;
			padding-bottom: 4rpx;

			.price-box {
				display: flex;
				align-items: baseline;
				margin-bottom: 12rpx;
				
				.sym { font-size: 28rpx; font-weight: bold; margin-right: 4rpx; }
				.num { font-size: 48rpx; font-weight: bold; line-height: 1; font-family: DIN; }
			}

			.stock {
				font-size: 24rpx;
				color: #999;
				margin-bottom: 8rpx;
			}

			.selected-text {
				font-size: 26rpx;
				color: #666;
				display: -webkit-box;
				-webkit-box-orient: vertical;
				-webkit-line-clamp: 2;
				overflow: hidden;
			}
		}

		.close-btn {
			position: absolute;
			top: 24rpx;
			right: 24rpx;
			padding: 10rpx;
			opacity: 0.6;
			
			&:active { opacity: 1; }
		}
	}

	/* 2. 滚动区域 */
	.spec-scroll {
		flex: 1;
		min-height: 300rpx;
		overflow-y: auto;
		padding: 10rpx 30rpx 30rpx;
		box-sizing: border-box;

		.spec-group {
			margin-top: 30rpx;

			.group-title {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
				margin-bottom: 20rpx;
			}

			.values-list {
				display: flex;
				flex-wrap: wrap;
				gap: 20rpx;

				.value-item {
					padding: 12rpx 30rpx;
					background: #f5f6fa;
					border-radius: 8rpx;
					font-size: 26rpx;
					color: #333;
					border: 1rpx solid transparent; 
					transition: all 0.2s;
				}
			}
		}
		
		.count-row {
			margin-top: 40rpx;
			margin-bottom: 20rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;
			
			.label {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
			}
		}
	}

	/* 3. 底部按钮 */
	.spec-footer {
		padding: 20rpx 30rpx;
		border-top: 1rpx solid #f5f5f5;
		background: #fff;

		.confirm-btn {
			width: 100%;
			height: 88rpx;
			line-height: 88rpx;
			border-radius: 44rpx;
			color: #fff;
			font-size: 32rpx;
			font-weight: bold;
			border: none;
			
			&::after { border: none; }
			
			&:active { opacity: 0.9; }
			
			&[disabled] {
				opacity: 0.6;
				background: #ccc !important;
			}
		}
	}
</style>