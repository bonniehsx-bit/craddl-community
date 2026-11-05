<template>
	<view>
		<tui-modal-container :show="localVisible" width="100%" height="100%" @close="closeModal">
			<view class="popup-wrapper" v-if="value">

				<image class="popup-bg" :src="value.bg.url" mode="scaleToFill" :style="{
					top: value.bg.top + '%',
					height: value.bg.height + '%',
					width: value.bg.width + '%',
					borderRadius: value.bg.radius + 'rpx'
				}" v-if="value.bg.url"></image>

				<view class="btn-container flex-y-center flex-x-center" :style="{ top: value.btn.top + '%' }">
					<view class="text-btn flex-y-center flex-x-center" v-if="value.btn.style == 0" :style="{
						width: value.btn.width + '%',
						height: value.btn.height + 'rpx',
						borderRadius: value.btn.radius + 'rpx',
						backgroundColor: value.btn.bgColor,
						color: value.btn.color,
						fontSize: value.btn.fontSize + 'rpx',
						border: `1rpx solid ${value.btn.borderColor || 'transparent'}`
					}" @click.stop="btnClick">
						{{ value.btn.text }}
					</view>

					<image class="img-btn" v-if="value.btn.style == 1" :src="value.btn.btnImg" mode="scaleToFill"
						:style="{
							width: value.btn.width + '%',
							height: value.btn.height + 'rpx',
							borderRadius: value.btn.radius + 'rpx'
						}" @click.stop="btnClick"></image>
				</view>

			</view>
		</tui-modal-container>
	</view>
</template>

<script>
export default {
	name: 'MallAlertAdv',
	props: {
		show: {
			type: Boolean,
			default: true // [修复] 默认设为 true 兼容外部 v-if 控制渲染的用法
		},
		value: {
			type: Object,
			default: () => {
				return {
					bg: { top: '10', height: '70', width: '80', url: '', radius: 30 },
					btn: { type: 0, top: '60', width: '60', text: '关闭', radius: '60', height: '100', color: '#000', bgColor: '#fff', borderColor: '#fff', url: '', fontSize: '35', btnImg: '', style: 1 }
				}
			}
		}
	},
	data() {
		return {
			// 承接父组件传进来的 prop
			localVisible: this.show,
		}
	},
	watch: {
		// 监听外部传值的变化
		show(newVal) {
			this.localVisible = newVal;
		}
	},
	methods: {
		// 统一处理关闭逻辑
		closeModal() {
			this.localVisible = false;
			// 支持外部使用 .sync 语法： <mall-alert-adv :show.sync="showPopup" />
			this.$emit('update:show', false);
			this.$emit('close');
		},

		btnClick() {
			// 无论如何，点完按钮先关弹窗
			this.closeModal();

			// 如果是跳转类型 (1) 并且配了链接，执行跳转
			if (this.value.btn.type == 1 && this.value.btn.url) {
				uni.navigateTo({
					url: this.value.btn.url,
					fail: (err) => {
						console.error("跳转失败:", err);
						// 兜底方案：如果是 tabbar 页面 navigateTo 会报错，可以考虑在这 switchTab
					}
				})
			}
		}
	}
}
</script>

<style scoped>
/* 将内联样式抽取到 CSS 中，保持模板整洁 */
.popup-wrapper {
	width: 100%;
	height: 100%;
	position: relative;
}

.popup-bg {
	position: absolute;
	left: 50%;
	transform: translateX(-50%);
	overflow: hidden;
	/* 加上一个极其微小的阴影增加立体的品质感 */
	box-shadow: 0 10rpx 30rpx rgba(0, 0, 0, 0.1);
}

.btn-container {
	position: absolute;
	width: 100%;
	left: 0;
	z-index: 100;
	/* 不需要 999999 那么夸张，100 够用了 */
}

.text-btn {
	box-sizing: border-box;
	/* 确保 border 不撑大盒子 */
	overflow: hidden;
	/* 按钮点击时的视觉反馈(可选) */
	transition: opacity 0.2s;
}

.text-btn:active,
.img-btn:active {
	opacity: 0.8;
}

/* 兼容外部全局类 flex-y-center flex-x-center, 如果没有请保留以下注释代码 */
/*
	.flex-y-center { display: flex; align-items: center; }
	.flex-x-center { display: flex; justify-content: center; }
	*/
</style>