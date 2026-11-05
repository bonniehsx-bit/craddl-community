<template>
	<view class="list-action-bar" :style="themeStyle" v-if="actions.length" @tap.stop>
		<view class="btn-group">
			<button
				v-for="(btn, i) in actions"
				:key="i"
				class="btn"
				:class="btn.style === 'primary' ? 'primary' : 'plain'"
				:style="btn.style === 'primary' ? primaryStyle : ''"
				@tap="onClick(btn)">
				{{ btn.label }}
			</button>
		</view>
	</view>
</template>

<script>
import { resolveActionData } from './utils.js';

export default {
	name: 'ListActionBar',
	props: {
		order: { type: Object, required: true },
		actions: { type: Array, default: () => [] }
		// themeColor / hexToRgba 来自全局 mixin
	},
	computed: {
		primaryStyle() {
			return {
				background: this.themeColor,
				color: '#fff',
			};
		}
	},
	methods: {
		onClick(btn) {
			const data = resolveActionData(btn, { order: this.order });
			// rawAction 透传整个按钮配置 —— 插件订单（如 massage）可通过
			// cancel_url / cancel_payload 等字段告诉前端调用自己的取消接口，
			// 与 OrderFooterBar.vue 的 emit 形状保持一致
			this.$emit('action', { type: btn.type, data, order: this.order, rawAction: btn });
		}
	}
};
</script>

<style scoped lang="scss">
.list-action-bar {
	padding: 16rpx 24rpx;
	border-top: 1rpx solid #f5f5f5;
}
.btn-group {
	display: flex;
	justify-content: flex-end;
	gap: 14rpx;
}
.btn {
	margin: 0;
	padding: 0 28rpx;
	height: 56rpx;
	line-height: 54rpx;
	font-size: 24rpx;
	border-radius: 28rpx;
	font-weight: 500;
	min-width: 150rpx;

	&::after { border: none; }
	&:active { transform: scale(0.96); opacity: 0.85; }

	/* 次按钮:白底 + 主题色描边/文字(描边式);无主题时优雅降级为灰。
	   --theme-color-light=主题色70%(软描边)  --theme-color=本色(文字)。
	   想要"浅色块"风可把 background 换成 var(--theme-color-bg)(主题色10%浅底)。 */
	&.plain {
		background: #fff;
		border: 1rpx solid var(--theme-color-light, #dcdfe6);
		color: var(--theme-color, #333);
	}
	/* 主按钮:背景已是主题色,外阴影用主题色25%(--theme-shadow)做同色系柔光,而非灰阴影 */
	&.primary {
		border: none;
		box-shadow: 0 6rpx 16rpx var(--theme-shadow, rgba(0, 0, 0, 0.12));
	}
}
</style>
