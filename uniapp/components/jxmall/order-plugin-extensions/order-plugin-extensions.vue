<template>
	<view v-if="extensions && extensions.length > 0" class="plugin-ext-wrap">
		<view v-for="(item, index) in extensions" :key="index">

			<!-- type: switch -->
			<view v-if="item.type === 'switch'" class="ext-row">
				<view class="ext-left">
					<text class="ext-label">{{ item.label }}</text>
					<text class="ext-desc" v-if="item.desc">{{ item.desc }}</text>
				</view>
				<switch
					:checked="!!innerValues[item.plugin + '__' + item.key]"
					:color="themeColor"
					style="transform:scale(0.7); margin-right: -10rpx;"
					@change="onSwitchChange(item, $event)"
				/>
			</view>

			<!-- 未来可在此扩展 type: input / radio / select 等 -->

		</view>
	</view>
</template>

<script>
	import { mapState } from 'vuex';

	export default {
		name: 'OrderPluginExtensions',

		props: {
			/**
			 * 后端 preview 接口返回的 plugin_extensions 数组
			 * 每项结构：{ plugin, key, type, label, desc, default }
			 */
			extensions: {
				type: Array,
				default: () => []
			}
		},

		data() {
			return {
				// 内部状态：key 格式为 "plugin__key"，值为 0/1 或具体值
				innerValues: {}
			};
		},

		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},

		watch: {
			// extensions 变化时（切换配送方式重新 preview）重新初始化默认值
			extensions: {
				immediate: true,
				handler(list) {
					if (!list || !list.length) return;
					const init = {};
					list.forEach(item => {
						const k = item.plugin + '__' + item.key;
						// 保留已有值，避免用户已操作过的开关被重置
						if (!(k in this.innerValues)) {
							init[k] = item.default ?? 0;
						}
					});
					if (Object.keys(init).length) {
						this.innerValues = Object.assign({}, this.innerValues, init);
					}
				}
			}
		},

		methods: {
			onSwitchChange(item, e) {
				const k = item.plugin + '__' + item.key;
				this.$set(this.innerValues, k, e.detail.value ? 1 : 0);
				this.emitParams();
			},

			/**
			 * 将内部 innerValues 转换为结构化的 plugin_params 并向父组件 emit
			 *
			 * 输出格式：{ brand: { is_cloud_storage: 1 }, other_plugin: { key: val } }
			 */
			emitParams() {
				const params = {};
				Object.entries(this.innerValues).forEach(([k, v]) => {
					const [plugin, key] = k.split('__');
					if (!params[plugin]) params[plugin] = {};
					params[plugin][key] = v;
				});
				this.$emit('change', params);
			}
		}
	};
</script>

<style lang="scss" scoped>
	.plugin-ext-wrap {
		background: #fff;
		border-radius: 16rpx;
		margin: 16rpx 24rpx 0;
		padding: 0 28rpx;
	}

	.ext-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #F5F5F5;

		&:last-child {
			border-bottom: none;
		}
	}

	.ext-left {
		display: flex;
		flex-direction: column;
		flex: 1;
	}

	.ext-label {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
	}

	.ext-desc {
		font-size: 22rpx;
		color: #999;
		margin-top: 6rpx;
	}
</style>
