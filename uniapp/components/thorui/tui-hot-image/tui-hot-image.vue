<template>
	<view :style="{ padding: value.is_padding == 1 ? '0 20rpx' : '0' }">
		<view style="overflow: hidden;overflow: hidden;box-sizing: border-box;position: relative;"
			:style="{ borderRadius: value.is_circle == 1 ? '20rpx' : '0' }">
			<image :src="value.pic_url" mode="widthFix" style="display: block;width: 100%;overflow: hidden;"></image>
			<block v-if="hotspotList.length" v-for="(item, index) in hotspotList" :key="index">
				<view @click="pageTo(item.url)" :style="item.styleStr">

				</view>
			</block>
		</view>
	</view>
</template>
<script>
export default {
	props: {
		value: {
			type: Object,
			default: () => {
				return {
					id: '',
					list: [],
					pic_url: '',
					name: ''
				};
			}
		}
	},
	data() {
		return {

		}
	},
	computed: {
		// 计算后的热区列表，包含样式
		hotspotList() {
			if (!this.value || !this.value.list) return [];

			// 核心修复：如果开启了左右边距(is_padding=1)，图片宽度会从 750rpx 变为 730rpx
			// 此时需要将热区坐标也等比缩放 (730/750)，否则会错位
			let ratio = 1;
			if (this.value.is_padding == 1) {
				ratio = 730 / 750;
			}

			return this.value.list.map(item => {
				const style = {
					left: (Number(item.starX) * ratio) + 'rpx',
					top: (Number(item.starY) * ratio) + 'rpx',
					width: (Number(item.areaWidth) * ratio) + 'rpx',
					height: (Number(item.areaHeight) * ratio) + 'rpx',
					position: 'absolute'
				};
				// 将对象转换为字符串，兼容小程序
				let styleStr = '';
				for (let key in style) {
					styleStr += `${key}:${style[key]};`;
				}

				return {
					...item,
					styleStr: styleStr
				};
			});
		}
	},
	mounted() { },
	methods: {
		pageTo(url) {
			console.log(url);
			this.$T.go(url)
		}
	}
}
</script>

<style></style>