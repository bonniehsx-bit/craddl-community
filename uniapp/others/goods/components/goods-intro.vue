<template>
	<view>
		<!-- 补贴条 -->
		<view v-if="goods && goods.expected_rewards && goods.expected_rewards.first_order_discount > 0"
			style="width: 100%;background-color: #fff;">
			<view class="subsidy-bar">
				<view class="subsidy-left">
					<text class="subsidy-label">首单补贴：</text>
					<text class="subsidy-value">{{ goods.expected_rewards.first_order_discount }}元</text>
				</view>
				<view class="subsidy-right">
					<text>剩余：</text>
					<text class="subsidy-stock"
						:style="{color: themeColor}">{{ goods.expected_rewards.first_order_discount_total_stock }}</text>
				</view>
			</view>
		</view>

		<view class="info-module" v-if="goods">

			<!-- 价格行:¥ 小、整数大、小数中,划线价只在真比现价高时才显示 -->
			<view class="price-section">
				<view class="price-left">
					<text class="price-symbol" :style="{color: themeColor}">¥</text>
					<text class="price-main" :style="{color: themeColor}">{{ priceInt }}</text>
					<text class="price-dec" :style="{color: themeColor}">{{ priceDec }}</text>

					<view class="member-tag" v-if="isLevelPrice == 1">
						<text class="tag-text">会员价</text>
					</view>

					<!-- 送积分:原来单独一行「每件预计获得 3 积分」,占地方;收成价格后面一个小红包标。
					     「每件」「预计」两个限定词不能丢(随件数翻倍、实付受优惠影响),点标签弹出完整说明 -->
					<view class="reward-pill" v-if="rewardIntegral > 0" @click="showRewardTip">
						<tui-icon name="redpacket-fill" :size="12" color="#ff7a00"></tui-icon>
						<text class="reward-text">+{{ rewardIntegral }}{{ $noun('integral') }}</text>
					</view>

					<text class="price-original" v-if="showOrigin">¥{{ goods.origin_price }}</text>
				</view>
				<view class="sales-count">已售 {{ goods.virtual_sales || 0 }} {{ goods.unit || '件' }}</view>
			</view>

			<!-- 标题 + 商品海报入口 -->
			<view class="title-section-row">
				<view class="title-text-wrapper">
					<text class="goods-title-rich-text">
						<text class="tag self-operated-tag" v-if="goods.mch_id == 0"
							:style="{ backgroundColor: themeColorLight, color: themeColor }">自营</text>
						{{ goods.name }}
					</text>
					<view class="goods-subtitle" v-if="goods.subtitle">{{ goods.subtitle }}</view>
				</view>

				<!-- 商品码:横排小胶囊,高度 = 标题一行。原来「图标叠文字」竖排 41px 高,
				     标题只有一行(24px)时下面空出一大截 -->
				<view class="title-action-btn" @click="sharePoster">
					<tui-icon name="qrcode" :size="14" color="#666"></tui-icon>
					<text class="action-text">商品码</text>
				</view>
			</view>

			<view class="tags-section" v-if="hasTags">
				<view class="tag theme-tag" v-if="goods.is_integral == 1 && goods.integral_price > 0">
					可用{{ $noun('integral') }}抵 {{ goods.integral_price }} 元
				</view>
				<view class="tag theme-tag" v-if="goods.is_limit == 1 && goods.limit_num > 0">
					每人限购 {{ goods.limit_num }} {{ goods.unit || '件' }}
				</view>
			</view>
		</view>

		<!-- 服务保障:上传商品时勾的服务标签,一个都没勾就整块不显示 -->
		<view class="service-module" v-if="goods && serviceList.length > 0">
			<view class="service-item" v-for="(item, index) in serviceList" :key="index">
				<view class="service-dot" :style="{ backgroundColor: themeColorLight, color: themeColor }">✓</view>
				<text class="service-text">{{ item }}</text>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "goods-intro",
		props: {
			goods: {
				type: Object,
				default: null
			},
			currentSku: {
				type: Object,
				default: null
			},
			isLevelPrice: {
				type: [Number, String],
				default: 0
			}
		},
		computed: {
			// 选了规格就跟着规格价走
			showPrice() {
				const price = this.currentSku ? this.currentSku.price : (this.goods && this.goods.price);
				return Number(price || 0).toFixed(2);
			},
			priceInt() {
				return this.showPrice.split('.')[0];
			},
			priceDec() {
				return '.' + this.showPrice.split('.')[1];
			},
			// 划线价只在真比现价高时显示:原来没填原价(0.00)也画一条划线的「¥0.00」,像标错价
			showOrigin() {
				if (!this.goods) return false;
				return Number(this.goods.origin_price || 0) > Number(this.showPrice);
			},
			hasTags() {
				if (!this.goods) return false;
				return (this.goods.is_integral == 1 && this.goods.integral_price > 0)
					|| (this.goods.is_limit == 1 && this.goods.limit_num > 0);
			},
			rewardIntegral() {
				const rewards = this.goods && this.goods.expected_rewards;
				return rewards && rewards.integral > 0 ? rewards.integral : 0;
			},
			// 服务标签:后端按商品勾选的标签给好文字(app\models\GoodsServiceTag),这里只负责显示。
			// 原来是前端自己算的(运费按 express_type 推、退款看开关、库存看剩余数):
			// 运营想加「正品保障」这类话术一个都加不了,而且前端的运费算法和结算那边差一点,
			// 运费模板商品标着「全国包邮」、结算却收运费。现在标签在后台勾,算法只剩一处(结算)。
			serviceList() {
				const tags = this.goods && this.goods.service_tags;
				if (!Array.isArray(tags)) return [];
				return tags.filter(item => typeof item === 'string' && item.length > 0);
			}
		},
		methods: {
			sharePoster() {
				this.$emit('sharePoster');
			},
			/** 「每件」不能省:按比例算的是明细实付总额,固定值算的是 send_integral×件数,都随件数翻倍,
			 *  详情页只按 1 件预估;「预计」也不能省:实付受优惠券影响,到账可能比这少 */
			showRewardTip() {
				const noun = this.$noun('integral');
				uni.showModal({
					title: '购买送' + noun,
					content: '每件预计获得 ' + this.rewardIntegral + ' ' + noun + ',买几件就翻几倍;使用优惠后实付变少,到账' + noun + '可能略少,以实际到账为准。',
					showCancel: false,
					confirmText: '知道了',
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	$uni-spacing-col: 24rpx;

	.subsidy-bar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 16rpx 24rpx;
		margin: -16rpx 24rpx 0;
		border-radius: 12rpx;
		position: relative;
		z-index: 10;
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.06);
		border: 1rpx solid #ffe5b3;
		background: linear-gradient(to right, #fff9f0, #fff7e8);

		.subsidy-left {
			font-size: 28rpx;
			color: #D97843;
			font-weight: 500;

			.subsidy-value {
				font-size: 32rpx;
				font-weight: bold;
			}
		}

		.subsidy-right {
			font-size: 26rpx;
			color: #999;
			background-color: #fff;
			padding: 4rpx 16rpx;
			border-radius: 20rpx;

			.subsidy-stock {
				font-weight: bold;
			}
		}
	}

	.info-module {
		padding: 24rpx $uni-spacing-col 20rpx;
		background-color: #fff;

		.title-section-row {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			margin-top: 14rpx;

			.title-text-wrapper {
				flex: 1;
				min-width: 0;
				padding-right: 16rpx;
			}

			// 横排小胶囊,高 48rpx = 标题行高,和标题第一行对齐
			.title-action-btn {
				flex-shrink: 0;
				height: 48rpx;
				padding: 0 16rpx;
				border-radius: 24rpx;
				background-color: #f5f6f8;
				display: flex;
				align-items: center;
				gap: 6rpx;

				.action-text {
					font-size: 22rpx;
					color: #666;
					white-space: nowrap;
				}
			}
		}
	}

	.goods-title-rich-text {
		font-size: 34rpx;
		line-height: 48rpx;
		font-weight: 600;
		color: #1f2229;
	}

	.self-operated-tag {
		font-size: 22rpx;
		font-weight: 600;
		padding: 2rpx 10rpx;
		border-radius: 8rpx;
		margin-right: 10rpx;
	}

	.goods-subtitle {
		margin-top: 10rpx;
		font-size: 26rpx;
		line-height: 38rpx;
		color: #8a8f9b;
	}

	/* ==================== 价格 ==================== */
	.price-section {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}

	.price-left {
		display: flex;
		align-items: baseline;
		min-width: 0;
		flex: 1;
	}

	.price-symbol {
		font-size: 32rpx;
		font-weight: 700;
	}

	.price-main {
		font-size: 60rpx;
		font-weight: 800;
		line-height: 1;
		margin-left: 2rpx;
	}

	.price-dec {
		font-size: 34rpx;
		font-weight: 800;
	}

	.member-tag {
		margin-left: 14rpx;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		background: linear-gradient(90deg, #fff0d0, #ffe1ae);

		.tag-text {
			font-size: 20rpx;
			font-weight: 600;
			color: #b4761a;
		}
	}

	// 送积分小红包标:跟在价格后面,一眼看得出,不占一整行
	.reward-pill {
		margin-left: 14rpx;
		padding: 2rpx 12rpx 2rpx 8rpx;
		border-radius: 999rpx;
		background: #fff3e6;
		display: flex;
		align-items: center;
		gap: 4rpx;

		.reward-text {
			font-size: 20rpx;
			font-weight: 600;
			color: #ff7a00;
			white-space: nowrap;
		}
	}

	.price-original {
		margin-left: 14rpx;
		font-size: 26rpx;
		color: #a8aeba;
		text-decoration: line-through;
	}

	.sales-count {
		flex-shrink: 0;
		padding-left: 16rpx;
		font-size: 24rpx;
		color: #9aa0ac;
	}

	/* ==================== 标签 ==================== */
	.tags-section {
		display: flex;
		flex-wrap: wrap;
		margin-top: 18rpx;
	}

	.tag {
		font-size: 22rpx;
		line-height: 40rpx;
		height: 40rpx;
		padding: 0 14rpx;
		border-radius: 8rpx;
		margin-right: 12rpx;
		margin-bottom: 8rpx;
	}

	.theme-tag {
		color: var(--theme-color);
		background-color: var(--theme-color-bg);
	}

	/* ==================== 服务保障 ==================== */
	.service-module {
		display: flex;
		flex-wrap: wrap;
		padding: 20rpx 24rpx 12rpx;
		background-color: #fff;
		border-top: 1rpx solid #f5f6f8;
	}

	.service-item {
		display: flex;
		align-items: center;
		margin-right: 26rpx;
		margin-bottom: 8rpx;
	}

	.service-dot {
		width: 26rpx;
		height: 26rpx;
		border-radius: 50%;
		font-size: 18rpx;
		line-height: 26rpx;
		text-align: center;
		margin-right: 8rpx;
	}

	.service-text {
		font-size: 24rpx;
		color: #5a5f6b;
	}
</style>
