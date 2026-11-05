<template>
	<view :style="'width:100%;height:' + (value.height || 20) + 'rpx;background-color:' + (value.background || 'transparent')">
	</view>
</template>

<script>
	export default {
		props: {
			value: {
				type: Object,
				default: () => {
					return {
						height: 2
					};
				}
			}
		},
		data() {
			return {

			}
		},
		methods: {

		}
	}
</script>

<style>

</style>
