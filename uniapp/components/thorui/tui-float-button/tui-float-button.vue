<template>
	<view>
		 
	</view>
</template>

</template>

<script>
	export default {
		name: 'tui-float-button',
		props: {
			value: {
				type: Object,
				default: () => {
					return {
						bottom: 100,
						right: 20,
						list: [],
						type: 0, //0展开类型  1 收缩类型
						bgColor: '#ffff',
					};
				}
			}
		},
		data() {
			return {

			}
		},
		created() {
			console.log(this.value);
		},
		computed: {
			buttonStyle() {
				return `bottom:${this.value.bottom}rpx;right:${this.value.right}rpx`;
			},
		},
		methods: {
			onClick(e) {
				const button = this.value.list[e.index];
				if (button.openType == 'nav') {
					this.$T.go(button.value);
				}
				if (button.openType == 'call') {
					this.$util.call(button.value);
				}
				if (button.openType == 'location') {
					this.$util.openLocation(button.value, button.lat, button.lon);
				}
			},
			pageTo() {
				if (this.value.type == 1) {
					this.$T.go('/others/web/web?url=' + this.value.url)
				} else {
					this.$T.go(this.value.url)
				}
			}
		}
	}
</script>

<style>

</style>
