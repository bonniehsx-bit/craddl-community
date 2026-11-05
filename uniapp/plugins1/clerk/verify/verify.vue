<template>
	<!-- title/useCustomNav 必须显式传:两者都空时 tui-page 的 showCustomNav 为 false,
	     自绘导航栏整个不渲染;而 pages.json 这页是 navigationStyle:custom,原生栏也没有,
	     结果顶部一片空白、连返回按钮都没有。 -->
	<tui-page title="核销工作台" :useCustomNav="true" :transparentNav="false"
		titleColor="#ffffff" :navBarBgColor="themeColor" :backgroundColor="'#F5F6FA'">
		<view class="container" :style="themeStyle">

			<view class="header-section">
				<view class="bg-circle"></view>
				<view class="header-content">
					<view class="title">自营核销台</view>
					<view class="subtitle">
						<tui-icon name="shield" :size="14" color="#fff" style="margin-right: 6rpx; opacity: 0.8;"></tui-icon>
						<text>支持 商城订单 / 卡券 / 自提商品</text>
					</view>
				</view>
			</view>

			<view class="action-card" v-if="isClerk">
				
				<view class="scan-area" @tap="handleScan">
					<view class="scan-btn-wrapper">
						<view class="pulse-layer" :style="{ borderColor: themeColor }"></view>
						<view class="pulse-layer delay" :style="{ borderColor: themeColor }"></view>
						
						<view class="scan-btn" :style="{ background: themeColor, boxShadow: getBtnShadow }">
							<tui-icon name="scan" :size="44" color="#fff"></tui-icon>
						</view>
					</view>
					<text class="scan-text">扫码核销</text>
				</view>

				<view class="divider">
					<text>或手动输入核销码</text>
				</view>

				<view class="input-area">
					<view class="input-box">
						<tui-icon name="edit" :size="18" color="#999" class="input-icon"></tui-icon>
						<input 
							type="text" 
							v-model="inputCode" 
							placeholder="请输入核销码" 
							placeholder-class="ph-style"
							confirm-type="search" 
							@confirm="handleQuery" 
						/>
						<view 
							class="verify-btn" 
							:style="{ color: inputCode ? themeColor : '#ccc', opacity: inputCode ? 1 : 0.6 }"
							@tap="handleQuery">
							查询
						</view>
					</view>
				</view>
			</view>

			<view class="action-card" v-if="authChecked && !isClerk">
				<view style="text-align:center; padding: 50rpx 20rpx;">
					<tui-icon name="warning" :size="48" color="#f43f5e"></tui-icon>
					<view style="font-size:30rpx; font-weight:600; color:#333; margin-top:24rpx;">无核销权限</view>
					<view style="font-size:24rpx; color:#999; margin-top:12rpx; line-height:1.6;">您的账号还不是核销员，无法使用核销台。如需开通请联系平台管理员。</view>
				</view>
			</view>

			<view class="footer-tips safe-area-inset-bottom">
				<tui-icon name="about" :size="14" color="#ccc"></tui-icon>
				<text>仅平台核销员可使用本页面</text>
			</view>

			<tui-bottom-popup :show="showModal" @close="closeModal" :radius="30">
				<view class="verify-modal safe-area-inset-bottom">
					<view class="modal-header">
						<text class="modal-title">核销确认</text>
						<view class="close-btn" @tap="closeModal">
							<tui-icon name="close-fill" color="#ddd" :size="24"></tui-icon>
						</view>
					</view>

					<scroll-view scroll-y class="modal-scroll-content" v-if="verifyData">
						
						<view class="status-banner" :class="verifyData.is_allow ? 'status-success' : 'status-fail'">
							<view class="status-icon">
								<tui-icon :name="verifyData.is_allow ? 'check' : 'close'" :size="28" color="#fff"></tui-icon>
							</view>
							<view class="status-text">
								<text class="main-msg">{{ verifyData.msg }}</text>
								<text class="sub-msg" v-if="!verifyData.is_allow && verifyData.type !== 'normal'">该核销码已过期或状态异常。若已与顾客核实无误，可勾选下方选项强制核销</text>
								<text class="sub-msg" v-if="!verifyData.is_allow && verifyData.type === 'normal'">该订单当前不可核销（未支付 / 已核销 / 已取消）</text>
							</view>
						</view>

						<view class="info-grid">
							<view class="grid-item">
								<text class="label">订单类型</text>
								<text class="value highlight">{{ getTypeName(verifyData.type) }}</text>
							</view>
							<view class="grid-item">
								<text class="label">顾客</text>
								<text class="value">{{ verifyData.data.user_name || '匿名' }}</text>
							</view>
							<view class="grid-row">
								<text class="label">订单号</text>
								<view class="value-row" @tap="copyText(verifyData.data.order_no)">
									<text>{{ verifyData.data.order_no }}</text>
									<tui-icon name="document" :size="14" color="#999" style="margin-left: 8rpx;"></tui-icon>
								</view>
							</view>
						</view>

						<view class="goods-list-wrapper" v-if="verifyData.data.products && verifyData.data.products.length > 0">
							<view class="section-head">商品明细</view>
							<view class="goods-item" v-for="(item, idx) in verifyData.data.products" :key="idx">
								<image :src="item.image || '/static/image/avatar.png'" class="g-img" mode="aspectFill"></image>
								<view class="g-content">
									<view class="g-name">{{ item.name }}</view>
									<view class="g-bottom">
										<text class="g-spec">{{ item.spec || '默认规格' }}</text>
										<text class="g-num">x{{ item.num || 1 }}</text>
									</view>
								</view>
							</view>
						</view>

						<view class="force-verify-box" v-if="!verifyData.is_allow && verifyData.type !== 'normal'">
							<label class="force-label" @tap="isForce = !isForce">
								<checkbox :checked="isForce" :color="themeColor" style="transform:scale(0.7)" />
								<text style="font-size: 24rpx; color: #ff5722;">已与顾客核实，确认强制核销</text>
							</label>
						</view>

					</scroll-view>

					<view class="modal-actions">
						<button class="action-btn cancel" @tap="closeModal">取消</button>
						<button 
							class="action-btn confirm" 
							:class="{ disabled: (!(verifyData && verifyData.is_allow) && !isForce) }"
							:style="{ background: ((verifyData && verifyData.is_allow) || isForce) ? themeColor : '#e0e0e0' }"
							:disabled="(!(verifyData && verifyData.is_allow) && !isForce) || isSubmitting"
							@tap="doVerify">
							{{ isSubmitting ? '核销中...' : '确认核销' }}
						</button>
					</view>
				</view>
			</tui-bottom-popup>

		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				inputCode: '',
				showModal: false,
				isSubmitting: false,
				verifyData: null,
				isForce: false, // 强制核销开关
				// ⚠ 这里原来写死了 themeColor:'#0A73F7'。data 和全局 mixin 的同名 computed
				// 撞车时 Vue 取 data,商城后台配的主题色被整页屏蔽掉,永远是那个蓝。
				// 主题色一律走 mixin + themeStyle,页面不再自持(规范 §14.2.1)。
				isClerk: false, // 是否平台核销员(进页校验)
				authChecked: false // 身份是否已校验完成,防止未校验时闪现台面
			}
		},
		onLoad() {
			this.checkAuth();
		},
		computed: {
			getBtnShadow() {
				return `0 16rpx 32rpx -8rpx ${this.themeColor}55`; 
			}
		},
		methods: {
			async checkAuth() {
				try {
					const res = await this.$request({ url: '/plugin/clerk/api/verify/auth', method: 'GET' });
					this.isClerk = res && res.code === 0 && res.data && res.data.is_clerk === 1;
				} catch (e) {
					this.isClerk = false;
				} finally {
					this.authChecked = true;
				}
			},

			async handleScan() {
				// 统一扫码(微信H5走公众号JS-SDK,其余原生),见 utils/scanCode.js
				try {
					const raw = await this.$scan();
					const code = this.parseCode(raw);
					if (code) {
						this.inputCode = code;
						this.queryDetail(code);
					} else {
						this.$util.toast('无法识别这个二维码,请换一个或手动输入核销码');
					}
				} catch (e) {
					if (!e || !e.cancel) this.$util.toast((e && e.msg) || '扫码不可用,请手动输入核销码');
				}
			},

			handleQuery() {
				if (!this.inputCode) return this.$util.toast('请先输入核销码');
				this.queryDetail(this.inputCode);
			},

			async queryDetail(code) {
				uni.showLoading({ title: '查询中...', mask: true });
				this.isForce = false;
				try {
					const res = await this.$request({
						url: '/plugin/clerk/api/verify/search',
						method: 'GET',
						data: { code: code }
					});
					if (res.code === 0) {
						this.verifyData = this.normalizeVerifyData(res.data);
						this.showModal = true;
						if (!this.verifyData.is_allow) uni.vibrateLong();
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					console.error(e);
				} finally {
					uni.hideLoading();
				}
			},

			normalizeVerifyData(apiData) {
				// 如果是 normal 模式直接映射
				if (apiData.type === 'normal') {
					return {
						type: 'normal',
						is_allow: apiData.is_allow,
						msg: apiData.msg,
						data: {
							user_name: apiData.data.user_name || '未留名顾客',
							order_no: apiData.data.order_no || this.inputCode,
							products: apiData.data.products || []
						}
					};
				}

				// 插件返回结构目前有两套,这里都要认 —— 只认一套的后果是"后端说能核销、页面却说不能":
				//   ① 主流(integral / yuda / voucher / brand / group / otv 六家):
				//      { is_allow, msg, data: { user_name, order_no, products: [{name, cover_pic, num, price}] } }
				//   ② hotel 一家:
				//      { can_verify, status_text, user: {name}, order: {order_no}, goods: {name, specs, cover_pic} }
				// 本方法原先只按 ② 写,于是那六家插件一律读到 undefined:
				// is_allow 恒 false(明明可核销却要人勾"强制核销")、商品明细空白、
				// 顾客与单号退化成兜底值。判定字段以 ① 优先、② 兜底。
				const d = apiData.data || {};
				const allow = (apiData.is_allow !== undefined) ? !!apiData.is_allow : !!apiData.can_verify;
				const msg = apiData.msg || apiData.status_text || (allow ? '可以核销' : '当前状态不可核销');

				let products = [];
				if (Array.isArray(d.products) && d.products.length) {
					// ① 主流:cover_pic→image;price 常是"总计积分抵扣: 10.00"这类说明,
					//    没有规格时拿它占规格位,总比空着强
					products = d.products.map(function(it) {
						return {
							name: it.name || '未命名商品',
							spec: it.spec || it.price || '',
							num: it.num || 1,
							image: it.image || it.cover_pic || ''
						};
					});
				} else if (apiData.goods) {
					// ② hotel
					products.push({
						name: apiData.goods.name || '未命名商品',
						spec: apiData.goods.specs || '',
						num: 1,
						image: apiData.goods.cover_pic || ''
					});
				}

				return {
					type: apiData.type || 'plugin',
					is_allow: allow,
					msg: msg,
					data: {
						user_name: d.user_name || (apiData.user && apiData.user.name) || '未留名顾客',
						order_no: d.order_no || (apiData.order && apiData.order.order_no) || apiData.code || this.inputCode,
						products: products
					}
				};
			},

			async doVerify() {
				if (this.isSubmitting || (!(this.verifyData && this.verifyData.is_allow) && !this.isForce)) return;
				this.isSubmitting = true;
				try {
					const res = await this.$request({
						url: '/plugin/clerk/api/verify/commit',
						method: 'POST',
						data: { 
							code: this.inputCode,
							is_manual_force: this.isForce ? 1 : 0
						}
					});
					if (res.code === 0) {
						uni.showToast({ title: '收录完成', icon: 'success' });
						this.closeModal();
						this.inputCode = '';
						uni.vibrateShort();
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {} finally {
					this.isSubmitting = false;
				}
			},

			closeModal() {
				this.showModal = false;
				setTimeout(() => { this.verifyData = null; }, 200);
			},
			
			copyText(text) {
				uni.setClipboardData({ data: text });
			},

			parseCode(result) {
				if (/^\d+$/.test(result)) return result;
				try {
					if (result.includes('?')) {
						const arr = result.split('?')[1].split('&');
						for(let i=0; i<arr.length; i++) {
							const pair = arr[i].split('=');
							if(pair[0] === 'code' || pair[0] === 'scene') return pair[1];
						}
					}
				} catch (e) {}
				return result;
			},

			getTypeName(type) {
				if (type === 'normal') return '自提实物';
				if (type === 'plugin') return '插件延伸商品';
				return type || '系统级别验证';
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 全局盒模型修正 */
	view, text, image, input, scroll-view, button {
		box-sizing: border-box;
	}

	.container {
		min-height: 100vh;
		position: relative;
		overflow: hidden;
		display: flex;
		flex-direction: column;
	}

	.header-section {
		height: 320rpx;
		background: #333; /* 兜底色 */
		/* 渐变终点原来写死 #17325F(深蓝),那是配之前硬编码蓝主题的。主题色一旦跟商城走,
		   任何非蓝的主题色配深蓝都很脏 —— 改用 --theme-color-light(主题色 70% 透明),
		   渐变始终在同一色相内(规范 §14.2.1)。 */
		background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light) 100%);
		position: relative;
		padding: 60rpx 40rpx 0;
		border-bottom-left-radius: 40rpx;
		border-bottom-right-radius: 40rpx;
		
		.bg-circle {
			position: absolute;
			top: -50rpx; right: -50rpx;
			width: 300rpx; height: 300rpx;
			border-radius: 50%;
			background: rgba(255,255,255,0.1);
		}

		.header-content {
			position: relative; z-index: 2;
			.title {
				font-size: 44rpx; font-weight: bold; color: #fff; margin-bottom: 12rpx;
			}
			.subtitle {
				display: flex; align-items: center; color: rgba(255,255,255,0.85); font-size: 24rpx;
			}
		}
	}

	.action-card {
		margin: -100rpx 30rpx 0;
		background: #fff;
		border-radius: 24rpx;
		padding: 50rpx 30rpx;
		box-shadow: 0 10rpx 30rpx rgba(0,0,0,0.05);
		position: relative; z-index: 3;
		display: flex; flex-direction: column; align-items: center;
	}

	.scan-area {
		display: flex; flex-direction: column; align-items: center; width: 100%;
		
		.scan-btn-wrapper {
			position: relative;
			width: 200rpx; height: 200rpx;
			display: flex; align-items: center; justify-content: center;
			margin-bottom: 30rpx;
		}

		.scan-btn {
			width: 160rpx; height: 160rpx;
			border-radius: 50%;
			display: flex; align-items: center; justify-content: center;
			position: relative; z-index: 10;
			transition: transform 0.2s;
			&:active { transform: scale(0.95); }
		}

		.pulse-layer {
			position: absolute;
			width: 100%; height: 100%;
			border-radius: 50%;
			border: 2rpx solid;
			opacity: 0;
			animation: pulse 2.5s infinite;
			z-index: 1;
			
			&.delay { animation-delay: 0.8s; }
		}

		.scan-text {
			font-size: 30rpx; font-weight: 600; color: #333;
		}
	}

	@keyframes pulse {
		0% { transform: scale(0.8); opacity: 0.5; }
		100% { transform: scale(1.5); opacity: 0; }
	}

	.divider {
		width: 100%; margin: 50rpx 0 40rpx;
		display: flex; align-items: center; justify-content: center;
		position: relative;
		
		&::before {
			content: ''; position: absolute; left: 0; right: 0; top: 50%;
			height: 1rpx; background: #eee; z-index: 1;
		}
		text {
			background: #fff; padding: 0 20rpx; font-size: 24rpx; color: #999;
			position: relative; z-index: 2;
		}
	}

	.input-area { width: 100%; }
	.input-box {
		background: #F7F8FA;
		height: 96rpx;
		border-radius: 48rpx;
		display: flex; align-items: center;
		padding: 0 10rpx 0 30rpx;
		border: 2rpx solid transparent;
		transition: all 0.3s;

		&:focus-within {
			border-color: var(--theme-color);
			background: #fff;
		}

		.input-icon { margin-right: 16rpx; }

		input {
			flex: 1; height: 100%; font-size: 30rpx; color: #333;
		}
		
		.ph-style { color: #bbb; }

		.verify-btn {
			padding: 0 30rpx;
			height: 76rpx;
			line-height: 76rpx;
			font-weight: bold;
			font-size: 28rpx;
		}
	}

	.footer-tips {
		margin-top: auto; padding: 40rpx 0 20rpx;
		display: flex; align-items: center; justify-content: center;
		font-size: 24rpx; color: #ccc; gap: 8rpx;
	}

	.verify-modal {
		background: #fff;
		display: flex; flex-direction: column;
		max-height: 85vh;
		width: 100%;
		border-radius: 30rpx 30rpx 0 0;
		overflow: hidden;

		.modal-header {
			padding: 30rpx 40rpx;
			display: flex; justify-content: space-between; align-items: center;
			border-bottom: 1rpx solid #f5f5f5;
			
			.modal-title { font-size: 32rpx; font-weight: bold; color: #333; }
			.close-btn { padding: 10rpx; margin: -10rpx; }
		}

		.modal-scroll-content {
			padding: 30rpx 40rpx;
			width: 100%;
			max-height: 60vh; 
		}

		.status-banner {
			display: flex; align-items: center;
			padding: 30rpx; border-radius: 16rpx;
			margin-bottom: 30rpx;
			
			.status-icon {
				width: 60rpx; height: 60rpx; border-radius: 50%;
				display: flex; align-items: center; justify-content: center;
				margin-right: 24rpx; flex-shrink: 0;
			}

			.status-text {
				display: flex; flex-direction: column;
				.main-msg { font-size: 30rpx; font-weight: bold; color: #333; }
				.sub-msg { font-size: 22rpx; color: #666; margin-top: 4rpx; }
			}

			&.status-success {
				background: #eff6ff;
				.status-icon { background: #3b82f6; }
				.main-msg { color: #1e3a8a; }
			}
			&.status-fail {
				background: #fff1f2;
				.status-icon { background: #f43f5e; }
				.main-msg { color: #e11d48; }
			}
		}

		.info-grid {
			background: #f8f9fa;
			border-radius: 16rpx;
			padding: 24rpx;
			margin-bottom: 30rpx;
			display: flex; flex-wrap: wrap;

			.grid-item {
				width: 50%; margin-bottom: 20rpx;
				display: flex; flex-direction: column;
			}
			.grid-row {
				width: 100%; display: flex; flex-direction: column;
			}

			.label { font-size: 22rpx; color: #999; margin-bottom: 6rpx; }
			.value { font-size: 28rpx; color: #333; font-weight: 500; }
			.highlight { color: var(--theme-color); }
			
			.value-row {
				display: flex; align-items: center;
				font-size: 28rpx; color: #333; font-weight: 500;
			}
		}

		.goods-list-wrapper {
			margin-bottom: 30rpx;
			.section-head {
				font-size: 28rpx; font-weight: bold; color: #333;
				margin-bottom: 20rpx;
				padding-left: 16rpx; border-left: 6rpx solid var(--theme-color);
				line-height: 1;
			}

			.goods-item {
				display: flex; padding: 20rpx 0;
				border-bottom: 1rpx solid #f5f5f5;
				&:last-child { border-bottom: none; }

				.g-img {
					width: 100rpx; height: 100rpx; border-radius: 12rpx;
					background: #eee; margin-right: 20rpx; flex-shrink: 0;
				}
				.g-content {
					flex: 1; display: flex; flex-direction: column; justify-content: space-between;
					
					.g-name { 
						font-size: 28rpx; color: #333; line-height: 1.4;
						overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
					}
					.g-bottom {
						display: flex; justify-content: space-between; align-items: center;
						.g-spec { font-size: 22rpx; color: #999; background: #f5f5f5; padding: 2rpx 8rpx; border-radius: 4rpx; }
						.g-num { font-size: 26rpx; color: #666; font-weight: bold; }
					}
				}
			}
		}
		
		.force-verify-box {
			background: #fff5f5;
			border: 1px dashed #ffccc7;
			padding: 20rpx;
			border-radius: 12rpx;
			margin-bottom: 20rpx;
			.force-label {
				display: flex;
				align-items: center;
			}
		}

		.modal-actions {
			padding: 20rpx 40rpx 40rpx;
			display: flex; gap: 24rpx;
			background: #fff;
			box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.03);

			.action-btn {
				flex: 1; height: 80rpx; line-height: 80rpx;
				border-radius: 40rpx; font-size: 28rpx; margin: 0; font-weight: bold;
				
				&.cancel { background: #f5f5f5; color: #666; }
				&.confirm { color: #fff; }
				&.disabled { background: #ddd !important; color: #fff; opacity: 1; }
			}
		}
	}
</style>
