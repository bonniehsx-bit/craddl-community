<template>
	<view class="t-icon" :style="boxStyle" @tap="handleTap">
		<image v-if="src && !failed" :key="src" class="t-icon__image" :src="src" :style="boxStyle"
			mode="aspectFit" :lazy-load="lazyLoad" @load="handleLoad" @error="handleError" />
		<view v-if="failed || !src" class="t-icon__fallback" :style="fallbackStyle">!</view>
	</view>
</template>

<script>
import { tablerIconUrl } from '@/utils/tabler-icons.js';

export default {
	name: 't-icon',
	props: {
		name: { type: String, default: '' },
		color: { type: String, default: '' },
		size: { type: [Number, String], default: 24 },
		unit: { type: String, default: 'px' },
		variant: { type: String, default: 'outline' },
		lazyLoad: { type: Boolean, default: true }
	},
	data() { return { failed: false, retry: 0 }; },
	computed: {
		resolvedColor() { return this.color || this.themeColor; },
		resourceUrl() { return tablerIconUrl(this.name, this.resolvedColor, this.variant); },
		src() { return this.resourceUrl ? this.resourceUrl + (this.retry ? '&retry=' + this.retry : '') : ''; },
		boxStyle() {
			const size = Math.max(1, Math.min(512, Number(this.size) || 24));
			const unit = this.unit === 'rpx' ? 'rpx' : 'px';
			return 'width:' + size + unit + ';height:' + size + unit + ';';
		},
		fallbackStyle() { return 'color:' + (/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test(this.resolvedColor) ? this.resolvedColor : '#909399'); }
	},
	watch: {
		resourceUrl() { this.failed = false; this.retry = 0; }
	},
	methods: {
		handleLoad(e) { this.failed = false; this.$emit('load', e); },
		handleError(e) { this.failed = true; this.$emit('error', e); },
		handleTap(e) {
			if (this.failed) { this.failed = false; this.retry = Date.now(); }
			this.$emit('click', e);
		}
	}
};
</script>

<style scoped>
.t-icon { display:inline-flex; position:relative; flex-shrink:0; vertical-align:middle; align-items:center; justify-content:center; line-height:1; }
.t-icon__image { display:block; flex-shrink:0; }
.t-icon__fallback { width:70%; height:70%; display:flex; align-items:center; justify-content:center; border:1px solid currentColor; border-radius:4px; font-size:12px; }
</style>
