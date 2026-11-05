<template>
	<tui-page :useCustomNav="true" :transparentNav="true" :showBackBtn="true" :title="$noun('withdraw.title_withdraw')">
		<view class="container" :style="themeStyle + ';--nav-top:' + navTop + 'px'">

			<!-- 1. 主题色头部:钱包选择条 + 可提现金额 + 提现记录入口 -->
			<view class="hero" :style="'padding-top:' + navTop + 'px'">
				<view class="hero-deco"></view>

				<!-- 钱包选择条:替代原来的顶部 Tab。2~3 个钱包铺满一行;更多就横向滑动,最右边「全部」打开列表抽屉 -->
				<view class="ws-wrap" v-if="assets.length > 1">
					<scroll-view class="ws" scroll-x :show-scrollbar="false" :scroll-into-view="'w' + activeIndex"
						:scroll-with-animation="true">
						<view class="ws-in" :class="assets.length <= 3 ? 'fit' : ''">
							<view class="w" v-for="(a, i) in assets" :key="a.key" :id="'w' + i"
								:class="i === activeIndex ? 'on' : ''" @click="switchAsset(i)">
								<text class="w-name">{{ a.label }}</text>
								<text class="w-val">{{ fmtWallet(a) }}</text>
							</view>
						</view>
					</scroll-view>
					<view class="ws-all" v-if="assets.length > 3" @click="showAssets = true">
						<text>全部</text>
						<uni-icons type="right" size="12" color="#ffffff"></uni-icons>
					</view>
				</view>

				<view class="hero-top" v-if="activeAsset">
					<text class="hero-label">可{{ $noun('withdraw') }}{{ activeAsset.label }}{{ activeAsset.key === 'integral' ? '' : '(元)' }}</text>
					<view class="hero-link" @click="goRecords">
						<text>{{ $noun('withdraw') }}记录</text>
						<uni-icons type="right" size="12" color="rgba(255,255,255,0.9)"></uni-icons>
					</view>
				</view>
				<view class="hero-amount" v-if="activeAsset">{{ fmt(activeAsset.available) }}</view>
				<view class="hero-top" v-if="!activeAsset && !loading">
					<text class="hero-label">{{ $noun('withdraw') }}</text>
					<view class="hero-link" @click="goRecords">
						<text>{{ $noun('withdraw') }}记录</text>
						<uni-icons type="right" size="12" color="rgba(255,255,255,0.9)"></uni-icons>
					</view>
				</view>
			</view>

			<block v-if="activeAsset">
				<!-- 2. 金额 -->
				<view class="card">
					<view class="card-title">{{ $noun('withdraw') }}金额</view>
					<view class="input-row">
						<text class="unit">{{ activeAsset.key === 'integral' ? '' : '¥' }}</text>
						<input class="input" type="digit" v-model="amount" placeholder-style="font-size:30rpx;color:#c0c0c0;" :placeholder="`请输入${$noun('withdraw')}金额`" />
						<text class="all-btn" :style="{ color: themeColor }" @click="withdrawAll">全部{{ $noun('withdraw') }}</text>
					</view>
					<!-- 快捷金额:100 / 200 / 500,超过可提现额、低于起提额、不是倍数的自动不显示 -->
					<view class="quick" v-if="quickAmounts.length">
						<text class="q-chip" :class="Number(amount) === q ? 'on' : ''" v-for="q in quickAmounts" :key="q" @click="amount = String(q)">{{ activeAsset.key === 'integral' ? q : '¥' + q }}</text>
					</view>
					<view class="fee-row" v-if="activeChannel">
						<text class="fee">手续费 {{ feePreview }}</text>
						<text class="fee" v-if="taxPreview !== '0.00'">税费 {{ taxPreview }}</text>
					</view>
					<view class="hint" v-if="activeChannel && activeChannel.min_amount > 0">
						最低{{ fmt(activeChannel.min_amount) }}起提
						<text v-if="activeChannel.multiple > 0">，须为{{ fmt(activeChannel.multiple) }}的整数倍</text>
					</view>
				</view>

				<!-- 3. 提现方式(渠道) -->
				<view class="card">
					<view class="card-title">{{ $noun('withdraw') }}到</view>
					<view class="empty" v-if="activeAsset.channels.length === 0">暂无可用{{ $noun('withdraw') }}方式</view>
					<view class="ch" :class="selectedChannelId === c.id ? 'on' : ''" v-for="c in activeAsset.channels" :key="c.id" @click="selectChannel(c)">
						<view class="ch-ico" :style="c.color ? ('color:' + c.color) : ''">
							<!-- 渠道图标:后台上传的是图片地址;填的是图标名就走字体图标 -->
							<image v-if="isImg(c.icon)" class="ch-img" :src="c.icon" mode="aspectFit"></image>
							<tui-icon v-else :name="c.icon || 'cardticket'" :color="c.color || themeColor" size="22"></tui-icon>
						</view>
						<view class="ch-info">
							<view class="ch-line">
								<text class="ch-name">{{ c.name }}</text>
								<text class="ch-tag" v-if="c.arrival_desc">{{ c.arrival_desc }}</text>
							</view>
							<text class="ch-sub" v-if="c.subtitle">{{ c.subtitle }}</text>
						</view>
						<view class="manage need" v-if="needAccount(c) && !c.onboarding_ready" :style="'color:' + themeColor" @click.stop="goOnboard(c)">
							去完善资料
							<tui-icon name="arrowright" :color="themeColor" size="13"></tui-icon>
						</view>
						<view class="manage" v-else-if="needAccount(c)" @click.stop="goOnboard(c)">
							收款账户
							<tui-icon name="arrowright" color="#c0c0c0" size="13"></tui-icon>
						</view>
						<view class="radio" :class="selectedChannelId === c.id ? 'on' : ''"></view>
					</view>
				</view>

				<!-- 提现须知(后台「提现设置」配置) -->
				<view class="notice-card" v-if="notice">
					<view class="notice-title">
						<tui-icon name="explain" :color="themeColor" size="15"></tui-icon>
						<text>{{ $noun('withdraw') }}须知</text>
					</view>
					<text class="notice-text">{{ notice }}</text>
				</view>
			</block>

			<view class="empty-page" v-if="!activeAsset && !loading">
				<text>暂无可用的{{ $noun('withdraw') }}方式</text>
			</view>

			<!-- 4. 提现规则:始终展示(小程序审核要求)。额度 / 每日次数 / 提现时间 / 到账时间排两列,其余往下列;没有可用方式时也照样展示 -->
			<view class="rules-card">
				<view class="rules-title">
					<tui-icon name="explain" :color="themeColor" size="15"></tui-icon>
					<text>{{ $noun('withdraw') }}规则</text>
				</view>
				<view class="rules-grid">
					<view class="rg-cell" v-for="(r, i) in rulesKey" :key="i">
						<text class="rg-k">{{ r.k }}</text>
						<text class="rg-v">{{ r.v }}</text>
					</view>
				</view>
				<view class="rule-item" v-for="(r, i) in rulesMore" :key="'m' + i">
					<text class="rule-dot" :style="{ background: themeColor }"></text>
					<text class="rule-text"><text v-if="r.k" class="rule-k">{{ r.k }}：</text>{{ r.v }}</text>
				</view>
			</view>

			<!-- 底部固定栏:预计到账 + 确认提现 -->
			<view class="bar" v-if="activeAsset">
				<view class="bar-inner">
					<view class="bar-left">
						<text class="bar-label">预计到账</text>
						<text class="bar-val">{{ activeAsset.key === 'integral' ? '' : '¥' }}{{ actualPreview }}</text>
					</view>
					<button class="bar-btn" :class="submitting ? 'disabled' : ''" :loading="submitting" :disabled="submitting" @click="submit">确认{{ $noun('withdraw') }}</button>
				</view>
			</view>

			<!-- 全部钱包 -->
			<tui-bottom-popup :show="showAssets" @close="showAssets = false">
				<view class="pop">
					<view class="pop-head">
						<text class="pop-title">选择{{ $noun('withdraw') }}的钱包</text>
						<view class="pop-close" @click="showAssets = false">×</view>
					</view>
					<scroll-view scroll-y class="pop-list">
						<view class="pop-item" :class="i === activeIndex ? 'on' : ''" v-for="(a, i) in assets" :key="a.key" @click="pickAsset(i)">
							<view class="pop-name">{{ a.label }}</view>
							<text class="pop-val">{{ fmtWallet(a) }}</text>
							<view class="radio" :class="i === activeIndex ? 'on' : ''"></view>
						</view>
					</scroll-view>
				</view>
			</tui-bottom-popup>
		</view>
	</tui-page>
</template>

<script>
	import {
		mapState
	} from 'vuex';
	import { buildWithdrawRules } from './rules.js';

	export default {
		data() {
			return {
				loading: false,
				submitting: false,
				assets: [],
				activeIndex: 0,
				amount: '',
				navTop: 0,
				showAssets: false, // 「全部」钱包抽屉
				selectedChannelId: 0,
				notice: '',
				dayLimit: 0 // 全局每日提现次数(0=不限)
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			// 快捷金额:固定 100 / 200 / 500;超过可提现额、低于该方式起提额、不是倍数要求的整数倍的不显示
			quickAmounts() {
				const a = this.activeAsset;
				if (!a) return [];
				const c = this.activeChannel;
				const avail = Number(a.available) || 0;
				return [100, 200, 500].filter(v => {
					if (v > avail) return false;
					if (c && Number(c.min_amount) > 0 && v < Number(c.min_amount)) return false;
					if (c && Number(c.max_amount) > 0 && v > Number(c.max_amount)) return false;
					if (c && Number(c.multiple) > 0 && v % Number(c.multiple) !== 0) return false;
					return true;
				});
			},
			// 规则:前四项(额度 / 次数 / 提现时间 / 到账时间)排两列,其余往下列
			rulesKey() {
				return this.withdrawRules.slice(0, 4);
			},
			rulesMore() {
				return this.withdrawRules.slice(4);
			},
			activeAsset() {
				return this.assets[this.activeIndex] || null;
			},
			activeChannel() {
				if (!this.activeAsset) return null;
				return this.activeAsset.channels.find(c => c.id === this.selectedChannelId) || null;
			},
			feePreview() {
				return this.calcPreview().fee;
			},
			taxPreview() {
				return this.calcPreview().tax;
			},
			actualPreview() {
				return this.calcPreview().actual;
			},
			// 提现规则:额度 / 每日次数 / 提现时间 / 到账时间 / 手续费 / 审核 始终展示(小程序审核要求),
			// 数字取当前所选方式的后台配置;后台写的自定义规则追加在后面(见 rules.js)
			withdrawRules() {
				return buildWithdrawRules({
					noun: this.$noun('withdraw'),
					channels: this.activeChannel ? [this.activeChannel] : [],
					dayLimit: this.dayLimit,
					available: this.activeAsset ? this.activeAsset.available : null,
					money: v => this.money(v)
				});
			}
		},
		onLoad(options) {
			this.navTop = uni.getSystemInfoSync().statusBarHeight + 44;
			// 从余额/奖励/积分中心跳来时，预选对应资金来源
			this._presetAsset = options && options.asset ? options.asset : '';
			this._firstShow = true;
			// 标题走后台可配完整词库(pages.json 静态标题取不了词)
			uni.setNavigationBarTitle({ title: this.$noun('withdraw.title_withdraw') });
			this.loadPrepare();
		},
		onShow() {
			// 首次进入由 onLoad 加载，跳过；之后每次返回本页都刷新可提现
			// （提现成功跳记录页返回、收款资料页返回等场景，避免可提现金额显示旧值）
			if (this._firstShow) {
				this._firstShow = false;
				return;
			}
			this.loadPrepare();
		},
		methods: {
			fmt(v) {
				const p = this.activeAsset && this.activeAsset.key === 'integral' ? 0 : 2;
				return Number(v || 0).toFixed(p);
			},
			// 钱包卡 / 抽屉里显示的可提现额:积分不带小数,其余带 ¥
			fmtWallet(a) {
				const v = Number(a.available || 0);
				return a.key === 'integral' ? String(Math.floor(v)) : '¥' + v.toFixed(2);
			},
			isImg(v) {
				return /^(https?:)?\/\//.test(String(v || '')) || String(v || '').indexOf('/') === 0;
			},
			pickAsset(i) {
				this.showAssets = false;
				this.switchAsset(i);
			},
			// 带单位的金额:积分写「100积分」,其余写「¥10.00」
			money(v) {
				return this.activeAsset && this.activeAsset.key === 'integral'
					? this.fmt(v) + this.$noun('integral')
					: '¥' + this.fmt(v);
			},
			// 仅用于展示预估，最终金额以后端 bc 计算为准
			calcPreview() {
				const amt = parseFloat(this.amount) || 0;
				const c = this.activeChannel;
				if (!c || amt <= 0) return {
					fee: '0.00',
					tax: '0.00',
					actual: '0.00'
				};
				const fee = amt * Number(c.fee_rate || 0);
				const tax = amt * Number(c.tax_rate || 0);
				let actual = amt - fee - tax;
				if (actual < 0) actual = 0;
				return {
					fee: fee.toFixed(2),
					tax: tax.toFixed(2),
					actual: actual.toFixed(2)
				};
			},
			needAccount(c) {
				return ['bank', 'alipay', 'wechat'].includes(c.destination);
			},
			async loadPrepare() {
				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/withdraw/api/withdraw/prepare',
						method: 'get'
					});
					if (res.code === 0) {
						this.assets = res.data.assets || [];
						this.notice = res.data.notice || '';
						this.dayLimit = Number(res.data.day_limit_count) || 0;
						// 预选 ?asset= 指定的资金来源（中心页跳来），找不到则默认第一个
						const idx = this._presetAsset ? this.assets.findIndex(a => a.key === this._presetAsset) : -1;
						this.activeIndex = idx >= 0 ? idx : 0;
						this.autoSelectChannel();
					} else {
						this.toast(res.msg);
					}
				} catch (e) {
					// 网络异常静默，避免空指针
				} finally {
					this.loading = false;
				}
			},
			autoSelectChannel() {
				this.amount = '';
				const a = this.activeAsset;
				this.selectedChannelId = a && a.channels.length ? a.channels[0].id : 0;
			},
			switchAsset(i) {
				this.activeIndex = i;
				this.autoSelectChannel();
			},
			selectChannel(c) {
				this.selectedChannelId = c.id;
			},
			withdrawAll() {
				if (this.activeAsset) {
					this.amount = this.fmt(this.activeAsset.available);
				}
			},
			goOnboard(c) {
				// 复用既有收款资料页（写 UserSettlementInfo，后端打款时读取）
				// 必须带 fund_class:提现渠道按 fund_class 区分(灵工/对公/银行卡 destination 可能都是 bank),
				// 只传 destination 会落到 BankFund 被"已认证"锁死,灵工的签约永远进不去。
				// 再带 title=渠道名,避免灵工页头显示"银行卡设置"。
				this._needReload = true;
				const fc = encodeURIComponent(c.fund_class || '');
				const title = encodeURIComponent(c.name || '');
				uni.navigateTo({
					url: `/others/fund-onboard/fund-onboard?channel=${c.destination}&fund_class=${fc}&title=${title}`
				});
			},
			goRecords() {
				uni.navigateTo({
					url: '/plugins2/withdraw/records'
				});
			},
			async submit() {
				if (this.submitting) return;
				const amt = parseFloat(this.amount);
				if (!amt || amt <= 0) return this.toast('请输入正确的金额');
				const c = this.activeChannel;
				if (!c) return this.toast('请选择提现方式');

				// 内部转余额无需资料；其它渠道提交前先做资格/资料校验
				if (c.destination !== 'balance') {
					const ok = await this.ensureEligible(c);
					if (!ok) return; // 不满足时已弹"去设置"并跳转
				}
				this.doApply();
			},
			// 资料/资格校验：满足 true；不满足时弹窗→跳收款资料页，返回 false
			async ensureEligible(c) {
				try {
					const res = await this.$request({
						url: '/plugin/withdraw/api/withdraw/check-eligibility',
						method: 'get',
						data: {
							channel_id: c.id
						}
					});
					if (res.code === 0 && res.data && res.data.is_eligible) return true;
					const data = (res && res.data) || {};
					const canJump = !!data.redirect_url;
					uni.showModal({
						title: '无法' + this.$noun('withdraw'),
						content: data.message || '请先完善收款资料',
						showCancel: canJump,
						confirmText: canJump ? '去设置' : '确定',
						success: (m) => {
							if (m.confirm && canJump) {
								this._needReload = true;
								const sep = data.redirect_url.includes('?') ? '&' : '?';
								// 同 goOnboard:带上 fund_class 精确进件路由 + title 渠道名
								const fc = encodeURIComponent(data.fund_class || c.fund_class || '');
								const title = encodeURIComponent(c.name || '');
								uni.navigateTo({
									url: `${data.redirect_url}${sep}channel=${data.channel || c.destination}&fund_class=${fc}&title=${title}`
								});
							}
						}
					});
					return false;
				} catch (e) {
					this.toast('资格校验失败，请稍后再试');
					return false;
				}
			},
			async doApply() {
				this.submitting = true;
				try {
					const res = await this.$request({
						url: '/plugin/withdraw/api/withdraw/apply',
						method: 'post',
						data: {
							channel_id: this.activeChannel.id,
							asset_type: this.activeAsset.key,
							amount: this.amount
						}
					});
					if (res.code === 0) {
						uni.showToast({
							title: '申请已提交',
							icon: 'success'
						});
						this.amount = '';
						setTimeout(() => this.goRecords(), 800);
					} else {
						this.toast(res.msg);
					}
				} catch (e) {
					this.toast('提交失败，请重试');
				} finally {
					// 防重提交窗口：成功提交后稍延迟再解锁，配合后端短窗口防重
					setTimeout(() => {
						this.submitting = false;
					}, 1200);
				}
			},
			toast(msg) {
				uni.showToast({
					title: msg || '操作失败',
					icon: 'none'
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	.container {
		min-height: 100vh;
		background: #f5f6f8;
		padding-bottom: calc(150rpx + env(safe-area-inset-bottom) * 0.3);
		box-sizing: border-box;
	}

	/* 1. 头部 */
	.hero {
		position: relative;
		overflow: hidden;
		padding: 0 28rpx 44rpx;
		background: var(--theme-color);
		border-bottom-left-radius: 44rpx;
		border-bottom-right-radius: 44rpx;

		&::before {
			content: '';
			position: absolute;
			left: 0;
			right: 0;
			top: 0;
			bottom: 0;
			background: linear-gradient(160deg, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 45%, rgba(0, 0, 0, 0.14) 100%);
			pointer-events: none;
		}
	}

	.hero-deco {
		position: absolute;
		top: -90rpx;
		right: -70rpx;
		width: 340rpx;
		height: 340rpx;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.1);
		pointer-events: none;
	}

	/* 钱包选择条 */
	.ws-wrap {
		position: relative;
		display: flex;
		align-items: stretch;
		margin: 20rpx -28rpx 0;
		padding-left: 28rpx;
	}

	.ws {
		flex: 1;
		min-width: 0;
		white-space: nowrap;
	}

	/* H5 里横向 scroll-view 会露出系统滚动条 */
	/* #ifdef H5 */
	.ws ::v-deep .uni-scroll-view::-webkit-scrollbar,
	.ws ::v-deep .uni-scroll-view-content::-webkit-scrollbar {
		display: none;
		width: 0;
		height: 0;
	}

	.ws ::v-deep .uni-scroll-view {
		scrollbar-width: none;
	}

	/* #endif */

	.ws-in {
		display: inline-flex;
		padding: 4rpx 4rpx 8rpx 0;

		&.fit {
			display: flex;
			padding-right: 28rpx;
		}
	}

	.w {
		display: flex;
		flex-direction: column;
		flex-shrink: 0;
		min-width: 196rpx;
		margin-right: 16rpx;
		padding: 18rpx 24rpx;
		box-sizing: border-box;
		border-radius: 26rpx;
		background: rgba(255, 255, 255, 0.16);
		border: 1rpx solid rgba(255, 255, 255, 0.22);
		color: #fff;
	}

	.ws-in.fit .w {
		flex: 1;
		min-width: 0;
	}

	.ws-in.fit .w:last-child {
		margin-right: 0;
	}

	.w-name {
		font-size: 23rpx;
		color: rgba(255, 255, 255, 0.85);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.w-val {
		margin-top: 4rpx;
		font-size: 31rpx;
		font-weight: bold;
		font-family: DINAlternate-Bold, Helvetica, Arial, sans-serif;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.w.on {
		background: #fff;
		border-color: #fff;
		box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.18);

		.w-name {
			color: #9a9a9a;
		}

		.w-val {
			color: var(--theme-color);
		}
	}

	.ws-all {
		flex-shrink: 0;
		width: 92rpx;
		margin: 0 28rpx 8rpx 8rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		border-radius: 26rpx;
		background: rgba(255, 255, 255, 0.16);
		border: 1rpx solid rgba(255, 255, 255, 0.22);
		font-size: 22rpx;
		color: #fff;
	}

	.hero-top {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 30rpx;
	}

	.hero-label {
		font-size: 26rpx;
		color: rgba(255, 255, 255, 0.88);
	}

	.hero-link {
		display: flex;
		align-items: center;
		padding: 6rpx 8rpx 6rpx 20rpx;
		border-radius: 30rpx;
		background: rgba(255, 255, 255, 0.18);
		font-size: 23rpx;
		color: #fff;
	}

	.hero-amount {
		position: relative;
		margin-top: 14rpx;
		font-size: 92rpx;
		font-weight: bold;
		color: #fff;
		line-height: 1.05;
		font-family: DINAlternate-Bold, Helvetica, Arial, sans-serif;
		text-shadow: 0 6rpx 18rpx rgba(0, 0, 0, 0.14);
	}

	/* 2. 卡片 */
	.card {
		position: relative;
		margin: 24rpx 24rpx 0;
		padding: 30rpx 30rpx 26rpx;
		background: #fff;
		border-radius: 28rpx;
		box-shadow: 0 8rpx 26rpx rgba(0, 0, 0, 0.05);
	}

	.card-title {
		margin-bottom: 18rpx;
		font-size: 29rpx;
		font-weight: 600;
		color: #222;
	}

	.input-row {
		display: flex;
		align-items: center;
		padding-bottom: 18rpx;
		border-bottom: 1rpx solid #f0f0f0;
	}

	.unit {
		margin-right: 12rpx;
		font-size: 52rpx;
		font-weight: bold;
		color: #222;
	}

	.input {
		flex: 1;
		height: 72rpx;
		font-size: 44rpx;
		font-weight: bold;
		color: #222;
	}

	.all-btn {
		flex-shrink: 0;
		margin-left: 16rpx;
		font-size: 26rpx;
		font-weight: 600;
	}

	.quick {
		display: flex;
		margin-top: 20rpx;
	}

	.q-chip {
		margin-right: 16rpx;
		padding: 8rpx 28rpx;
		border-radius: 30rpx;
		background: #f5f6f8;
		border: 1rpx solid transparent;
		font-size: 24rpx;
		color: #555;

		&.on {
			background: var(--theme-color-bg);
			border-color: var(--theme-color);
			color: var(--theme-color);
		}
	}

	.fee-row {
		display: flex;
		margin-top: 18rpx;
	}

	.fee {
		margin-right: 28rpx;
		font-size: 24rpx;
		color: #999;
	}

	.hint {
		margin-top: 10rpx;
		font-size: 22rpx;
		color: #aaa;
	}

	/* 提现方式 */
	.empty,
	.empty-page {
		padding: 40rpx 0;
		text-align: center;
		font-size: 26rpx;
		color: #aaa;
	}

	.empty-page {
		padding: 100rpx 0 40rpx;
	}

	.ch {
		display: flex;
		align-items: center;
		margin-bottom: 16rpx;
		padding: 22rpx 22rpx;
		border-radius: 22rpx;
		border: 3rpx solid #f0f0f0;

		&:last-child {
			margin-bottom: 0;
		}

		&.on {
			border-color: var(--theme-color);
			background: var(--theme-color-bg);
		}
	}

	.ch-ico {
		width: 72rpx;
		height: 72rpx;
		margin-right: 20rpx;
		border-radius: 22rpx;
		background: var(--theme-color-bg);
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.ch-img {
		width: 44rpx;
		height: 44rpx;
	}

	.ch-info {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.ch-line {
		display: flex;
		align-items: center;
	}

	.ch-name {
		font-size: 29rpx;
		font-weight: 600;
		color: #222;
	}

	.ch-tag {
		margin-left: 12rpx;
		padding: 2rpx 14rpx;
		border-radius: 16rpx;
		background: rgba(24, 181, 102, 0.12);
		color: #18a05c;
		font-size: 20rpx;
		font-weight: 600;
		white-space: nowrap;
	}

	.ch-sub {
		margin-top: 6rpx;
		font-size: 22rpx;
		color: #9a9a9a;
	}

	.manage {
		display: flex;
		align-items: center;
		flex-shrink: 0;
		margin: 0 16rpx;
		font-size: 22rpx;
		color: #999;
	}

	.radio {
		flex-shrink: 0;
		width: 36rpx;
		height: 36rpx;
		box-sizing: border-box;
		border-radius: 50%;
		border: 3rpx solid #ccc;

		&.on {
			border: 10rpx solid var(--theme-color);
		}
	}

	/* 须知 / 规则 */
	.notice-card,
	.rules-card {
		margin: 24rpx 24rpx 0;
		padding: 28rpx 30rpx 22rpx;
		background: #fff;
		border-radius: 28rpx;
		box-shadow: 0 8rpx 26rpx rgba(0, 0, 0, 0.04);
	}

	.notice-title,
	.rules-title {
		display: flex;
		align-items: center;
		margin-bottom: 16rpx;
		font-size: 28rpx;
		font-weight: 600;
		color: #333;

		text {
			margin-left: 8rpx;
		}
	}

	.notice-text {
		font-size: 24rpx;
		color: #777;
		line-height: 1.7;
		white-space: pre-wrap;
	}

	.rules-grid {
		display: flex;
		flex-wrap: wrap;
	}

	.rg-cell {
		width: 50%;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		padding: 0 20rpx 20rpx 0;
	}

	.rg-k {
		font-size: 22rpx;
		color: #a8a8a8;
	}

	.rg-v {
		margin-top: 4rpx;
		font-size: 25rpx;
		color: #444;
		line-height: 1.55;
	}

	.rule-item {
		display: flex;
		align-items: flex-start;
		margin-bottom: 14rpx;
	}

	.rule-dot {
		flex-shrink: 0;
		width: 10rpx;
		height: 10rpx;
		margin: 16rpx 14rpx 0 0;
		border-radius: 50%;
	}

	.rule-text {
		flex: 1;
		font-size: 24rpx;
		color: #777;
		line-height: 1.7;
	}

	.rule-k {
		color: #333;
		font-weight: 600;
	}

	/* 底部固定栏:内容区 100rpx,iOS 安全区只补三成 */
	.bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 90;
		padding: 0 28rpx;
		padding-bottom: calc(env(safe-area-inset-bottom) * 0.3);
		background: rgba(255, 255, 255, 0.96);
		backdrop-filter: blur(12px);
		-webkit-backdrop-filter: blur(12px);
		border-top: 1rpx solid #eeeae2;
		box-shadow: 0 -6rpx 24rpx rgba(40, 30, 10, 0.05);
	}

	.bar-inner {
		height: 100rpx;
		display: flex;
		align-items: center;
	}

	.bar-left {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.bar-label {
		font-size: 22rpx;
		color: #999;
	}

	.bar-val {
		margin-top: 2rpx;
		font-size: 40rpx;
		font-weight: bold;
		color: var(--theme-color);
		font-family: DINAlternate-Bold, Helvetica, Arial, sans-serif;
		line-height: 1.1;
	}

	.bar-btn {
		flex-shrink: 0;
		margin: 0;
		padding: 0 70rpx;
		height: 76rpx;
		line-height: 76rpx;
		border-radius: 38rpx;
		background: var(--theme-color);
		color: #fff;
		font-size: 29rpx;
		font-weight: 600;
		letter-spacing: 2rpx;
		box-shadow: 0 8rpx 20rpx var(--theme-shadow);

		&::after {
			border: none;
		}

		&.disabled {
			opacity: 0.6;
		}
	}

	/* 全部钱包抽屉 */
	.pop {
		padding: 28rpx 28rpx calc(24rpx + env(safe-area-inset-bottom) * 0.3);
		background: #fff;
		border-radius: 36rpx 36rpx 0 0;
	}

	.pop-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 6rpx;
	}

	.pop-title {
		font-size: 32rpx;
		font-weight: 700;
		color: #222;
	}

	.pop-close {
		width: 56rpx;
		height: 56rpx;
		line-height: 52rpx;
		text-align: center;
		border-radius: 50%;
		background: #f3f1ec;
		color: #888;
		font-size: 40rpx;
	}

	.pop-list {
		max-height: 760rpx;
	}

	.pop-item {
		display: flex;
		align-items: center;
		padding: 28rpx 0;
		border-bottom: 1rpx solid #f4f1ec;
	}

	.pop-name {
		flex: 1;
		min-width: 0;
		font-size: 29rpx;
		color: #222;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.pop-val {
		margin: 0 22rpx;
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		font-family: DINAlternate-Bold, Helvetica, Arial, sans-serif;
	}

	.pop-item.on .pop-val {
		color: var(--theme-color);
	}
</style>
