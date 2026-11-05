<template>
	<!-- 提现规则:放在余额 / 佣金 / 积分页提现按钮下面,直接展示(不藏在点开的弹窗里)。
	     小程序审核要求涉及提现的页面能清楚看到:可提现额度、每日提现次数、提现时间、到账时间。
	     四项直接列出,手续费 / 审核 / 须知等放「完整规则」弹窗。数字全部取后台配置(见 ../rules.js)。 -->
	<view class="withdraw-notice" :class="plain ? 'plain' : ''" v-if="loaded">
		<view class="rule-head">
			<view class="rule-title">
				<uni-icons type="info" size="14" color="#666"></uni-icons>
				<text class="rule-title-text">{{ noun }}规则</text>
			</view>
			<view class="rule-more" @click="show = true">
				<text>完整规则</text>
				<uni-icons type="right" size="12" color="#999"></uni-icons>
			</view>
		</view>
		<view class="rule-row" v-for="(r, i) in keyRules" :key="i">
			<text class="rule-k">{{ r.k }}</text>
			<text class="rule-v">{{ r.v }}</text>
		</view>

		<tui-bottom-popup :show="show" @close="show = false">
			<view class="notice-popup">
				<view class="popup-header">
					<text class="popup-title">{{ noun }}规则</text>
					<view class="popup-close" @click="show = false">
						<uni-icons type="closeempty" size="20" color="#999"></uni-icons>
					</view>
				</view>
				<scroll-view scroll-y class="popup-body">
					<view class="rule-item" v-for="(r, i) in rules" :key="i">
						<text class="rule-index">{{ i + 1 }}.</text>
						<text class="rule-text"><text v-if="r.k" class="rule-text-k">{{ r.k }}：</text>{{ r.v }}</text>
					</view>
					<!-- 后台「提现须知」(提现插件设置),没配就不显示 -->
					<view class="notice-block" v-if="notice">
						<text class="notice-text">{{ notice }}</text>
					</view>
				</scroll-view>
				<button class="popup-btn" @click="show = false">我知道了</button>
			</view>
		</tui-bottom-popup>
	</view>
</template>

<script>
	import { buildWithdrawRules } from '../rules.js';

	export default {
		name: 'withdrawNotice',
		props: {
			// 资金来源 balance / integral / price:取这个来源下的提现方式与额度
			asset: {
				type: String,
				default: ''
			},
			// 页面上已经拿到的余额:提现方式还没配(接口不返回可提现额)时,额度照样能写出「当前可提现 ¥x」
			available: {
				type: [String, Number],
				default: null
			},
			// 放在白色卡片里用:去掉自带的灰底和内边距,规则按「名称在上、内容在下」两列排,更紧凑
			plain: {
				type: Boolean,
				default: false
			}
		},
		data() {
			return {
				show: false,
				loaded: false,
				notice: '',
				channels: [],
				assetAvailable: null,
				dayLimit: 0
			};
		},
		computed: {
			noun() {
				return this.$noun('withdraw');
			},
			rules() {
				return buildWithdrawRules({
					noun: this.noun,
					channels: this.channels,
					dayLimit: this.dayLimit,
					available: this.assetAvailable !== null ? this.assetAvailable : this.available,
					money: v => this.money(v)
				});
			},
			// 卡片上直接列的四项:额度 / 每日次数 / 提现时间 / 到账时间(审核点名要的)
			keyRules() {
				return this.rules.slice(0, 4);
			}
		},
		created() {
			this.load();
		},
		methods: {
			money(v) {
				return this.asset === 'integral'
					? Number(v || 0).toFixed(0) + this.$noun('integral')
					: '¥' + Number(v || 0).toFixed(2);
			},
			// 取这个来源下的提现方式;取不到也照样展示(额度写「以提现页所选方式为准」)
			async load() {
				try {
					const res = await this.$request({
						url: '/plugin/withdraw/api/withdraw/prepare',
						method: 'get',
						data: this.asset ? { asset_type: this.asset } : {}
					});
					if (res.code === 0 && res.data) {
						const a = (res.data.assets || []).find(x => x.key === this.asset) || (res.data.assets || [])[0];
						this.channels = (a && a.channels) || [];
						this.assetAvailable = a ? a.available : null;
						this.notice = res.data.notice || '';
						this.dayLimit = Number(res.data.day_limit_count) || 0;
					}
				} catch (e) {}
				this.loaded = true;
			}
		}
	};
</script>

<style lang="scss" scoped>
	.withdraw-notice {
		margin-top: 24rpx;
		padding: 20rpx 24rpx 12rpx;
		border-radius: 16rpx;
		background-color: #f7f8fa;
	}

	.withdraw-notice.plain {
		display: flex;
		flex-wrap: wrap;
		margin-top: 0;
		padding: 0;
		background-color: transparent;

		.rule-head {
			width: 100%;
			margin-bottom: 18rpx;
		}

		.rule-title-text {
			font-size: 28rpx;
		}

		/* 四项规则排成两列:名称在上、内容在下 */
		.rule-row {
			width: 50%;
			box-sizing: border-box;
			flex-direction: column;
			padding: 0 16rpx 20rpx 0;
		}

		.rule-k {
			width: auto;
			font-size: 22rpx;
			color: #a8a8a8;
		}

		.rule-v {
			font-size: 25rpx;
			color: #444;
		}
	}

	.rule-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 10rpx;
	}

	.rule-title {
		display: flex;
		align-items: center;
	}

	.rule-title-text {
		margin-left: 8rpx;
		font-size: 26rpx;
		font-weight: 600;
		color: #333;
	}

	.rule-more {
		display: flex;
		align-items: center;
		font-size: 23rpx;
		color: #999;
	}

	.rule-row {
		display: flex;
		padding: 6rpx 0;
	}

	.rule-k {
		flex-shrink: 0;
		width: 168rpx;
		font-size: 23rpx;
		color: #999;
		line-height: 1.6;
	}

	.rule-v {
		flex: 1;
		min-width: 0;
		font-size: 23rpx;
		color: #555;
		line-height: 1.6;
	}

	.notice-popup {
		padding: 30rpx 30rpx calc(30rpx + env(safe-area-inset-bottom));
		background-color: #fff;
		border-radius: 24rpx 24rpx 0 0;
	}

	.popup-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 24rpx;
	}

	.popup-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
	}

	.popup-close {
		padding: 6rpx;
	}

	.popup-body {
		max-height: 60vh;
	}

	.notice-block {
		background-color: #f7f8fa;
		border-radius: 12rpx;
		padding: 20rpx 24rpx;
		margin-top: 8rpx;
	}

	.notice-text {
		font-size: 26rpx;
		color: #666;
		line-height: 1.7;
		white-space: pre-wrap;
	}

	.rule-item {
		display: flex;
		margin-bottom: 16rpx;
	}

	.rule-index {
		flex-shrink: 0;
		width: 36rpx;
		font-size: 26rpx;
		color: #999;
		line-height: 1.7;
	}

	.rule-text {
		flex: 1;
		font-size: 26rpx;
		color: #666;
		line-height: 1.7;
	}

	.rule-text-k {
		color: #333;
		font-weight: 600;
	}

	.popup-btn {
		margin-top: 30rpx;
		height: 80rpx;
		line-height: 80rpx;
		border-radius: 40rpx;
		font-size: 28rpx;
		color: #fff;
		background-color: var(--theme-color);

		&::after {
			border: none;
		}
	}
</style>
