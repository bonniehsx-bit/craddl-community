<template>
	<view class="magic-cube-root" :style="{ padding: value.is_padding == 1 ? '0 20rpx' : '0' }">
		<view class="magic-cube-wrapper" :style="{ borderRadius: value.is_circle == 1 ? '20rpx' : '0' }">

			<view class="magic-cube-container" :style="{ height: value.height + 'rpx' }"
				v-if="value.list && value.list.length > 0">

				<block v-if="value.type == 1">
					<image :src="value.list[0].pic_url" mode="aspectFill" class="image-item"
						@click="itemClick(value.list[0])"></image>
				</block>

				<block v-if="value.type == 2">
					<view class="flex-row layout-type-2">
						<view class="column-2">
							<image :src="value.list[0].pic_url" mode="aspectFill" class="image-item"
								@click="itemClick(value.list[0])"></image>
						</view>
						<view class="column-2" v-if="value.list[1]">
							<image :src="value.list[1].pic_url" mode="aspectFill" class="image-item"
								@click="itemClick(value.list[1])"></image>
						</view>
					</view>
				</block>

				<block v-if="value.type == 3">
					<view class="flex-row layout-type-3">
						<view class="column-2">
							<image :src="value.list[0].pic_url" mode="aspectFill" class="image-item"
								@click="itemClick(value.list[0])"></image>
						</view>
						<view class="column-2 flex-column">
							<view class="row-2" v-if="value.list[1]">
								<image :src="value.list[1].pic_url" mode="aspectFill" class="image-item"
									@click="itemClick(value.list[1])"></image>
							</view>
							<view class="row-2" v-if="value.list[2]">
								<image :src="value.list[2].pic_url" mode="aspectFill" class="image-item"
									@click="itemClick(value.list[2])"></image>
							</view>
						</view>
					</view>
				</block>

				<block v-if="value.type == 4">
					<view class="flex-row layout-type-4">
						<view class="column-3" v-for="(item, index) in value.list" :key="index">
							<image :src="item.pic_url" mode="aspectFill" class="image-item" @click="itemClick(item)">
							</image>
						</view>
					</view>
				</block>

			</view>
		</view>

	</view>
</template>

<script>
export default {
	props: {
		value: {
			type: Object,
			default: () => {
				// [BUG修复] 默认值不能为 null，否则会引发JS错误
				return {
					is_padding: 0,
					is_circle: 0,
					height: 300, // 给一个默认高度
					type: 1,
					list: []
				}
			}
		}
	},
	data() {
		return {}
	},
	methods: {
		itemClick(item) {
			if (item.is_preview == 1) {
				uni.previewImage({
					current: item.pic_url,
					urls: [item.pic_url],
					fail: (e) => {
						console.log(e);
					}
				})
			} else {
				this.$T.go(item.url)
			}
		}

	}
}
</script>

<style lang="scss" scoped>
.magic-cube-root {
	// 根节点，由 props.is_padding 控制 padding
}

.magic-cube-wrapper {
	overflow: hidden;
	box-sizing: border-box;
	position: relative;
}

.magic-cube-container {
	width: 100%;
	overflow: hidden;
}

// 基础布局
.flex-row {
	display: flex;
	flex-direction: row;
	height: 100%;
	width: 100%;
}

.flex-column {
	display: flex;
	flex-direction: column;
	height: 100%;
	width: 100%;
}

// 基础图片样式
.image-item {
	width: 100%;
	height: 100%;
	display: block; // 移除图片底部的空白
}

// 布局样式
.column-2 {
	width: 50%;
	height: 100%;
}

.row-2 {
	width: 100%;
	height: 50%;
}

.column-3 {
	width: 33.33333%;
	height: 100%;
}
</style>