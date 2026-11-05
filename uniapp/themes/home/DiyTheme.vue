<template>
	<!-- 兼容历史结构：page_data 可能直接是数组，也可能含 .data 字段。
	     直接绑 pageData 上的路径、不经 computed:小程序会把 computed 整个 setData 给视图层,等于整页 DIY 数据多传一份 -->
	<tui-diy-view v-if="pageData && pageData.data" :value="pageData.data" :pageParams="pageParams" @rendered="$emit('rendered')"></tui-diy-view>
	<tui-diy-view v-else-if="pageData" :value="pageData" :pageParams="pageParams" @rendered="$emit('rendered')"></tui-diy-view>
</template>

<script>
/**
 * 默认首页主题 —— DIY 装修引擎渲染
 *
 * 行为与改造前 pages/index/index.vue 直接渲染 <tui-diy-view> 完全一致，
 * 所有现有商家拖拽配置零变动。
 *
 * 数据来源：壳层（pages/index/index.vue）通过 :pageData / :pageParams 传入
 */
export default {
	name: 'DiyTheme',
	props: {
		pageData: {
			type: [Object, Array],
			default: null,
		},
		pageParams: {
			type: Object,
			default: null,
		},
	},
}
</script>
