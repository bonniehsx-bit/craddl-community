<template>
	<tui-bottom-popup :show="popupShow" @close="close" :radius="true">
		<view class="action-drawer-box" :style="{ '--theme-color': themeColor || '#143A94' }">
			<!-- 标题与头部导航区 -->
			<view class="drawer-header">
				<view class="back-btn" v-if="viewMode !== 'base'" @click="goBack">
					<tui-icon name="arrowleft" size="24" color="#666"></tui-icon>
				</view>
				<view class="title">{{ currentTitle }}</view>
				<view class="close-btn" @click="close">
					<tui-icon name="close-fill" size="20" color="#999"></tui-icon>
				</view>
			</view>

			<scroll-view scroll-y class="drawer-scroll-body">
				<view class="scroll-inner">
					
					<!-- 第一层：操作导航台 (Base View) -->
					<view v-if="viewMode === 'base'" class="base-view">
						<!-- 订单简化概览 -->
						<view class="order-summary-card">
							<view class="o-head">
								<text class="o-no">订单号：{{ order.order_no || '--' }}</text>
								<text class="o-amount">¥{{ order.total_pay_price || order.pay_price || '0.00' }}</text>
							</view>
							<view class="o-user" v-if="order.name">收件人：{{ order.name }} {{ order.mobile }}</view>
						</view>

						<!-- 插件定制提示 (Custom Tip) -->
						<view class="custom-tip-banner" v-if="order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.custom_tip">
							<uni-icons type="info-filled" color="#e6a23c" size="18"></uni-icons>
							<text class="tip-text">{{ order.plugin_data.action_config.custom_tip }}</text>
						</view>

						<!-- 插件特殊面板展示 (Display Panels) -->
						<view class="plugin-panel-card" v-if="order.plugin_data && order.plugin_data.display_panels && order.plugin_data.display_panels.length > 0">
							<view class="panel-section" v-for="(panel, pIdx) in order.plugin_data.display_panels" :key="pIdx">
								<view class="panel-title"><view class="v-line"></view>{{ panel.title }}</view>
								<view class="panel-items">
									<view class="panel-item" v-for="(item, iIdx) in panel.items" :key="iIdx">
										<text class="p-label">{{ item.label }}</text>
										<text class="p-value">{{ item.value }}</text>
									</view>
								</view>
							</view>
						</view>

						<view class="action-grid">
							<view class="action-item" v-if="canShip" @click="switchMode('ship')">
								<view class="icon-circle bg-blue"><uni-icons type="paperplane-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">去发货</text>
							</view>
							
							<!-- 同城配送:商家开始配送 -->
							<view class="action-item" v-if="canSend" @click="handleSend">
								<view class="icon-circle bg-orange"><uni-icons type="paperplane-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">开始配送</text>
							</view>
							
							<!-- 核销机制 -->
							<view class="action-item" v-if="canVerify" @click="handleVerify">
								<view class="icon-circle bg-green"><uni-icons type="checkbox-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">核销订单</text>
							</view>
							
							<!-- 修改地址 -->
							<view class="action-item" v-if="canAddress" @click="switchMode('address')">
								<view class="icon-circle bg-cyan"><uni-icons type="map-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">修改地址</text>
							</view>

							<!-- 商家备注 -->
							<view class="action-item" v-if="canRemark" @click="switchMode('remark')">
								<view class="icon-circle bg-purple"><uni-icons type="chat-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">写备注</text>
							</view>

							<!-- 售后:到售后单页处理(同意 / 拒绝 / 打款 / 完成换货都在那边) -->
							<view class="action-item" v-if="canRefundAction" @click="goRefunds">
								<view class="icon-circle bg-red"><uni-icons type="info-filled" color="#fff" size="24"></uni-icons></view>
								<text class="label">处理售后</text>
							</view>

							<!-- 确认完成/收货 -->
							<view class="action-item" v-if="canComplete" @click="handleComplete">
								<view class="icon-circle bg-success"><uni-icons type="checkbox" color="#fff" size="24"></uni-icons></view>
								<text class="label">确认送达</text>
							</view>

							<!-- 店长取消 -->
							<view class="action-item" v-if="canCancel" @click="switchMode('cancel')">
								<view class="icon-circle bg-gray"><uni-icons type="closeempty" color="#fff" size="24"></uni-icons></view>
								<text class="label">取消订单</text>
							</view>
							
							<!-- 兜底提示 -->
							<view v-if="!hasAnyAction" class="no-actions">
								订单当前状态暂无可执行操作
							</view>
						</view>
					</view>

					<!-- 第二层：表单系列 (Forms) -->
					
					<!-- 1. 发货表单 -->
					<view v-if="viewMode === 'ship'" class="form-view">
						<view class="form-row">
							<view class="f-label">快递公司</view>
							<picker @change="onExpressChange" :value="forms.ship.expressIndex" :range="expressList" range-key="name" class="f-input-picker">
								<view class="picker-txt" :class="{'placeholder': forms.ship.expressIndex === -1}">
									{{ forms.ship.expressIndex === -1 ? '点击选择快递公司' : expressList[forms.ship.expressIndex].name }}
								</view>
							</picker>
							<tui-icon name="arrowright" size="18" color="#999"></tui-icon>
						</view>
						<view class="form-row">
							<view class="f-label">快递单号</view>
							<input class="f-input" v-model="forms.ship.express_no" placeholder="请录入真实有效的快递单号" />
						</view>
						<view class="form-row">
							<view class="f-label">商家留言</view>
							<input class="f-input" v-model="forms.ship.merchant_remark" placeholder="非必填，展示给买家看" />
						</view>
					</view>

					<!-- 2. 修改地址表单 -->
					<view v-if="viewMode === 'address'" class="form-view">
						<view class="alert-tip">注意：订单发货后可能无法再次修改地址，请谨慎操作。</view>
						<view class="form-row">
							<view class="f-label">收件人</view>
							<input class="f-input" v-model="forms.address.name" placeholder="姓名" />
						</view>
						<view class="form-row">
							<view class="f-label">手机号</view>
							<input class="f-input" v-model="forms.address.mobile" type="number" placeholder="联系电话" />
						</view>
						<view class="form-row col-layout">
							<view class="f-label">详细地址</view>
							<textarea class="f-textarea" v-model="forms.address.address" placeholder="省市区路及门牌号" :auto-height="true" maxlength="120" />
						</view>
					</view>

					<!-- 3. 写备注表单 -->
					<view v-if="viewMode === 'remark'" class="form-view">
						<view class="form-row col-layout has-bg">
							<textarea class="f-textarea" v-model="forms.remark.remark" placeholder="请输入商家仅见的内部备注 (如: 客服小花答应补发布娃娃)" maxlength="200" style="min-height: 160rpx;" />
						</view>
					</view>

				</view>
			</scroll-view>

			<!-- 底部执行推杆 (只在 Form Mode 下展示) -->
			<view class="drawer-footer" v-if="viewMode !== 'base'">
				<button class="submit-btn" @click="submitSubForm">确认并提交</button>
			</view>
			<!-- 底部安全区补足 -->
			<view class="safe-area-bottom"></view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	import { mapState } from 'vuex';
	
	export default {
		name: "action-drawer",
		computed: {
			...mapState('mall', ['themeColor']),
			currentTitle() {
				const map = {
					'base': '订单操作聚合舱',
					'ship': '录入快递单发货',
					'address': '修改收件人信息',
					'remark': '编辑商家备忘录',
					'cancel': '取消此单'
				};
				return map[this.viewMode] || '操作';
			},
			// 发货 / 配送 / 核销 / 取消:有售后还在处理时不能做(after_sale_block_reason,与后端拦截同一个判断);
			// 售后处理完了照常 —— 部分退款后剩下的能发,也能取消(只退剩下的)。原来只要有过售后就一律藏起
			canShip() {
				if (!this.order || this.order.status != 20 || this.order.deliver_type !== 'express') return false;
				if (this.order.after_sale_block_reason) return false;
				if (this.order.plugin_data && this.order.plugin_data.action_config && this.order.plugin_data.action_config.show_ship === false) return false;
				return true;
			},
			canSend() {
				if (!this.order || this.order.status != 20 || this.order.deliver_type !== 'city') return false;
				if (this.order.after_sale_block_reason) return false;
				return true;
			},
			canVerify() {
				if (!this.order || this.order.status != 20) return false;
				if (this.order.after_sale_block_reason) return false;
				const ac = this.order.plugin_data && this.order.plugin_data.action_config;
				if (ac) {
					// 与 PC 端对齐：show_force_verify=false 时，仍检查 show_mch_force_verify 是否放行
					if (ac.show_force_verify === false && !ac.show_mch_force_verify) return false;
				} else {
					// 无插件配置时，只有自提订单可核销
					if (this.order.deliver_type !== 'pickup') return false;
				}
				return this.order.deliver_type === 'pickup' || (ac && ac.show_mch_force_verify === true);
			},
			canAddress() {
				if (!this.order || this.order.status != 20 || this.order.refund_status > 0 || this.order.deliver_type === 'pickup') return false;
				return true;
			},
			canRemark() {
				return !!this.order; // Always available
			},
			// 有售后申请待审(10)或售后处理中(20)
			canRefundAction() {
				return this.order && (this.order.refund_status == 10 || this.order.refund_status == 20);
			},
			canComplete() {
				return this.order && this.order.status == 30; // 配送中的可以通过干预手段完结
			},
			canCancel() {
				if (!this.order || this.order.status >= 30 || this.order.status == 0 || this.order.after_sale_block_reason) return false;
				return true;
			},
			hasAnyAction() {
				return this.canShip || this.canSend || this.canVerify || this.canAddress || this.canRemark || this.canRefundAction || this.canComplete || this.canCancel;
			}
		},
		data() {
			return {
				popupShow: false,
				order: {},
				viewMode: 'base', // 'base', 'ship', 'address', 'remark', 'cancel'
				expressList: [],
				
				// 多张子表单的数据源隔离存储区
				forms: {
					ship: { expressIndex: -1, express_name: '', express_code: '', express_no: '', merchant_remark: '' },
					address: { name: '', mobile: '', address: '' },
					remark: { remark: '' },
				}
			};
		},
		created() {
			this.loadExpressList();
		},
		methods: {
			// 一次性查出快递常量字典缓存
			async loadExpressList() {
				const res = await this.$request({ url: '/common/express/express' });
				if (res.code === 0) {
					this.expressList = res.data.list;
				}
			},

			async open(orderData) {
				// 乐观更新：先用外部传入的最简数据兜底骨架
				this.order = JSON.parse(JSON.stringify(orderData)); 
				this.viewMode = 'base';
				this.popupShow = true;
				
				uni.showLoading({ title: '拉取最新大盘状态', mask: true });
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/detail',
						data: { order_id: orderData.id || orderData.order_id }
					});
					if (res.code === 0) {
						let detailData = res.data.order || res.data.detail;
						// 把同级别的 plugin_data 合并注入到核心实体中 (向后兼容所有拦截器)
						if (res.data.plugin_data) {
							detailData.plugin_data = res.data.plugin_data;
						}
						// 全量覆盖为包含最全属性和商品项的安全实体
						this.order = detailData;
					} else {
						this.$tui.toast(res.msg);
					}
				} finally {
					uni.hideLoading();
					this.resetForms(); // 重新用新数据初始化深层表单的数据模型
				}
			},
			close() {
				this.popupShow = false;
			},
			goBack() {
				this.viewMode = 'base';
			},
			switchMode(mode) {
				this.viewMode = mode;
			},
			resetForms() {
				this.forms.ship = { expressIndex: -1, express_name: '', express_code: '', express_no: '', merchant_remark: '' };
				
				// 修改地址默认带入目前数据
				this.forms.address = { 
					name: this.order.name || '', 
					mobile: this.order.mobile || '', 
					address: this.order.address || '' 
				};
				// 备注如果原有，也带入
				this.forms.remark = { remark: this.order.remarks || '' };
			},

			// 表单组件内特定的 change 交互
			onExpressChange(e) {
				let idx = e.detail.value;
				this.forms.ship.expressIndex = idx;
				let obj = this.expressList[idx];
				this.forms.ship.express_name = obj.name;
				this.forms.ship.express_code = obj.key;
			},

			// =======================
			// 极简直连交互 (不切子表单的动作)
			// =======================
			handleVerify() {
				if (!this.order.pickup_code) {
					return uni.showToast({ title: '该订单没有核销码', icon: 'none' });
				}
				uni.showModal({
					title: '确认核销',
					content: '确认要核销该订单吗？核销后将立即完成订单并结算给商家。',
					success: (res) => {
						if (res.confirm) {
							this.executeApi('/plugin/mch/admin/order/verify', { code: this.order.pickup_code }, '核销成功');
						}
					}
				});
			},
			handleSend() {
				uni.showModal({
					title: '开始配送',
					content: '确认由本店开始配送此订单吗？',
					success: (res) => {
						if (res.confirm) this.executeApi('/plugin/mch/admin/order/ship', { order_id: this.order.id }, '已开始配送');
					}
				});
			},
			handleComplete() {
				uni.showModal({
					title: '人工干预提醒验证',
					content: '强制确认收货将终止流转将款项释出，确实妥投？',
					success: (res) => {
						if (res.confirm) this.executeApi('/plugin/mch/admin/order/complete', { order_id: this.order.id }, '订单强制完结');
					}
				});
			},
			// 售后在「售后单」页处理:这里的订单数据里没有售后单号,原来的内联审批 / 打款传不出正确参数,点了必定失败。
			// 按订单号只看这一单的售后
			goRefunds() {
				this.close();
				uni.navigateTo({ url: '/plugins1/mch/refund-orders?status=-1&keyword=' + encodeURIComponent(this.order.order_no || '') });
			},

			// =======================
			// 子面单统筹推入执行
			// =======================
			submitSubForm() {
				let mode = this.viewMode;
				let url = '';
				let payload = {};

				if (mode === 'ship') {
					if (this.forms.ship.expressIndex === -1) return uni.showToast({ title: '没有选择运力方', icon: 'none' });
					if (!this.forms.ship.express_no) return uni.showToast({ title: '无法识别空单号', icon: 'none' });
					
					url = '/plugin/mch/admin/order/express-ship';
					payload = {
						order_id: this.order.id,
						express_name: this.forms.ship.express_name,
						express_code: this.forms.ship.express_code,
						express_no: this.forms.ship.express_no,
						merchant_remark: this.forms.ship.merchant_remark
					};
				} 
				else if (mode === 'address') {
					if (!this.forms.address.name || !this.forms.address.mobile || !this.forms.address.address) {
						return uni.showToast({ title: '这三项缺一不可', icon: 'none' });
					}
					url = '/plugin/mch/admin/order/address-modify';
					payload = { order_id: this.order.id, ...this.forms.address };
				}
				else if (mode === 'remark') {
					url = '/plugin/mch/admin/order/remarks';
					payload = { order_id: this.order.id, remark: this.forms.remark.remark }; // 注意后端参数可能是 remark还是remarks
					// 这里 PC端调的 admin/order/remarks，并且参数也是 remark
				}
				else if (mode === 'cancel') {
					url = '/plugin/mch/admin/order/cancel';
					payload = { order_id: this.order.id };
				}

				if (url) {
					this.executeApi(url, payload, mode === 'cancel' ? '不可逆：款项已流转' : '推入成功');
				}
			},

			// 通用执行外设
			async executeApi(url, data, successMsg) {
				uni.showLoading({ title: '网关阻击通讯中...' });
				try {
					let res = await this.$request({ url, method: 'POST', data });
					if (res.code === 0) {
						uni.showToast({ title: successMsg, icon: 'success' });
						this.close();
						this.$emit('success');
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.action-drawer-box {
		width: 100vw;
		background: #F8F9FA;
		border-radius: 32rpx 32rpx 0 0;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		/* 划定黄金75%视区 */
		height: 75vh; 
	}

	.drawer-header {
		height: 100rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
		background: #fff;
		flex-shrink: 0;

		.title {
			font-size: 32rpx;
			font-weight: 600;
			color: #333;
		}

		.back-btn {
			position: absolute;
			left: 20rpx;
			top: 50%;
			transform: translateY(-50%);
			padding: 20rpx;
		}

		.close-btn {
			position: absolute;
			right: 20rpx;
			top: 50%;
			transform: translateY(-50%);
			padding: 20rpx;
		}
	}

	.drawer-scroll-body {
		flex: 1;
		min-height: 0; 
	}
	
	.scroll-inner {
		padding: 30rpx;
	}

	/* Base View */
	.order-summary-card {
		background: #fff;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 30rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.03);
		
		.o-head {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 12rpx;
			.o-no { font-size: 26rpx; color: #666; font-family: monospace; }
			.o-amount { font-size: 32rpx; font-weight: bold; color: #fa3534; font-family: DIN; }
		}
		.o-user {
			font-size: 26rpx; color: #999;
		}
	}

	.custom-tip-banner {
		display: flex;
		align-items: center;
		background: rgba(230, 162, 60, 0.1);
		padding: 20rpx 24rpx;
		border-radius: 12rpx;
		margin-bottom: 30rpx;
		border-left: 6rpx solid #e6a23c;
		
		.tip-text {
			font-size: 26rpx;
			color: #e6a23c;
			margin-left: 12rpx;
			line-height: 1.4;
			flex: 1;
		}
	}

	.plugin-panel-card {
		background: #fff;
		border-radius: 16rpx;
		padding: 30rpx 24rpx 10rpx;
		margin-bottom: 30rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.03);

		.panel-section {
			margin-bottom: 20rpx;
			
			.panel-title {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
				margin-bottom: 20rpx;
				display: flex;
				align-items: center;
				
				.v-line {
					width: 6rpx;
					height: 26rpx;
					background: var(--theme-color);
					border-radius: 4rpx;
					margin-right: 12rpx;
				}
			}
			
			.panel-item {
				display: flex;
				margin-bottom: 16rpx;
				font-size: 26rpx;
				line-height: 1.5;
				
				.p-label {
					color: #909399;
					width: 160rpx;
					flex-shrink: 0;
					margin-right: 16rpx;
				}
				.p-value {
					color: #303133;
					font-weight: 500;
					flex: 1;
					word-break: break-all;
				}
			}
		}
	}

	.action-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 20rpx;
		
		.action-item {
			width: calc(25% - 15rpx);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			background: #fff;
			padding: 24rpx 0;
			border-radius: 16rpx;
			box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.02);
			transition: opacity 0.2s;
			
			&:active { opacity: 0.7; }
			
			.icon-circle {
				width: 80rpx;
				height: 80rpx;
				border-radius: 40rpx;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 16rpx;
				
				&.bg-blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
				&.bg-orange { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
				&.bg-green { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
				&.bg-cyan { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
				&.bg-purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
				&.bg-red { background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); }
				&.bg-yellow { background: linear-gradient(135deg, #fac004 0%, #fba609 100%); }
				&.bg-primary { background: var(--theme-color); }
				&.bg-success { background: #19be6b; }
				&.bg-gray { background: #c0c4cc; }
			}
			
			.label { font-size: 24rpx; color: #333; }
		}
		
		.no-actions {
			width: 100%;
			text-align: center;
			padding: 60rpx 0;
			color: #C0C4CC;
			font-size: 26rpx;
		}
	}

	/* Form View */
	.form-view {
		background: #fff;
		border-radius: 16rpx;
		padding: 10rpx 24rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.03);

		.form-row {
			display: flex;
			align-items: center;
			padding: 30rpx 0;
			border-bottom: 1rpx solid #F0F2F5;
			
			&.no-border { border-bottom: none; }
			&.col-layout { flex-direction: column; align-items: flex-start; }
			&.has-bg { background: #F8F9FA; border-radius: 12rpx; padding: 24rpx; margin-bottom: 20rpx; border: none; }

			.f-label {
				width: 160rpx;
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
				flex-shrink: 0;
			}
			
			.f-input {
				flex: 1;
				font-size: 28rpx;
				color: #333;
				text-align: right;
			}
			
			.f-input-picker {
				flex: 1;
				display: flex;
				justify-content: flex-end;
				
				.picker-txt {
					font-size: 28rpx;
					color: #333;
					&.placeholder { color: #ccc; }
				}
			}
			
			.f-textarea {
				width: 100%;
				height: 120rpx;
				font-size: 28rpx;
				line-height: 1.5;
				color: #333;
				margin-top: 10rpx;
			}
		}

		.alert-tip {
			background: #eef5fe;
			color: #3E80FB;
			padding: 20rpx;
			border-radius: 10rpx;
			font-size: 24rpx;
			margin: 20rpx 0;
			&.alert-danger {
				background: #fef0f0;
				color: #f56c6c;
			}
		}

		.radio-vertical-group {
			display: flex;
			flex-direction: column;
			gap: 32rpx;
			width: 100%;
			
			.radio-item {
				display: flex;
				align-items: center;
				font-size: 28rpx;
				font-weight: 500;
				
				&.text-primary { color: var(--theme-color); }
				&.text-success { color: #19be6b; }
				&.text-danger  { color: #fa3534; }
			}
		}

		&.center-align {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 80rpx 40rpx;
			
			.money-show { font-size: 64rpx; font-weight: bold; color: #fa3534; font-family: DIN; margin-bottom: 20rpx; }
			.sec-tip { font-size: 26rpx; color: #999; text-align: center; line-height: 1.5; }
		}
	}

	.drawer-footer {
		padding: 24rpx 30rpx;
		background: #fff;
		box-shadow: 0 -4px 12px rgba(0,0,0,0.03);
		
		.submit-btn {
			background: var(--theme-color);
			color: #fff;
			border-radius: 44rpx;
			height: 88rpx;
			line-height: 88rpx;
			font-size: 30rpx;
			font-weight: 500;
			border: none;
			outline: none;
			
			&::after { display: none; }
			&:active { opacity: 0.9; }
		}
	}

	.safe-area-bottom {
		height: env(safe-area-inset-bottom);
		background: #fff;
	}
</style>
