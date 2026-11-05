<template>
	<tui-page title="Tabler 图标库" pageBgColor="#f5f6f8">
		<view class="icon-page" :style="themeStyle">
			<view class="intro"><view><text class="headline">为页面，挑一个好图标。</text><text class="subtitle">{{ libraryCount || '—' }} 枚图标 · 自有服务器按需加载</text></view><text class="version">TABLER {{ version }}</text></view>
			<view class="preview-card">
				<view class="preview-top"><view class="preview-icon"><t-icon :name="selectedName" :variant="selectedVariant" :color="iconColor" :size="iconSize" :lazyLoad="false" /></view><view class="preview-info"><text class="selected-name">{{ selectedName }}</text><text class="preview-caption">{{ selectedVariant === 'filled' ? '实心' : '线性' }} · {{ iconSize }} px · 点选下方图标即可预览</text></view><button class="copy-btn" @tap="copyUsage">复制用法</button></view>
				<view class="controls"><view class="colors"><view v-for="item in palette" :key="item.key" class="color-choice" :class="colorChoice === item.key ? 'color-choice--active' : ''" :style="item.style" @tap="setColor(item.key)"><text v-if="colorChoice === item.key" class="color-check">✓</text></view></view><view class="sizes"><text v-for="value in sizes" :key="value" class="size-choice" :class="iconSize === value ? 'size-choice--active' : ''" @tap="iconSize = value">{{ value }}</text></view></view>
				<view class="usage" @tap="copyUsage">{{ usage }}</view>
			</view>
			<view class="library-tools">
				<view class="search-bar"><tui-icon name="search" :size="18" color="#9a9faa" /><input v-model="keyword" class="search-input" maxlength="80" placeholder="搜索名称或关键词，如：好友、home" confirm-type="search" @input="onSearchInput" @confirm="searchNow" /><text v-if="keyword" class="clear-search" @tap="clearSearch">×</text></view>
				<view class="filters"><view class="variants"><view class="variant" :class="variant === 'outline' ? 'variant--active' : ''" @tap="setVariant('outline')">线性</view><view class="variant" :class="variant === 'filled' ? 'variant--active' : ''" @tap="setVariant('filled')">实心</view></view><picker class="category-picker" :range="categoryOptions" range-key="text" :value="categoryIndex" @change="setCategory"><view class="category-value">{{ categoryLabel }}<tui-icon name="arrowdown" :size="12" color="#8c929d" /></view></picker></view>
				<view class="results-meta"><text>{{ totalCount }} 枚{{ keyword ? '匹配图标' : '图标' }}</text><text v-if="loading">加载中…</text><text v-else>第 {{ page }} / {{ totalPages }} 页</text></view>
			</view>
			<scroll-view class="library-scroll" scroll-y :scroll-top="scrollTop">
				<view v-if="error" class="empty"><text>{{ error }}</text><button class="retry-btn" @tap="fetchIcons">重新加载</button></view>
				<view v-else-if="!loading && !list.length" class="empty"><text>没有找到图标</text><text class="empty-hint">试试其他关键词，或切换图标风格。</text></view>
				<view v-else class="icon-grid"><view v-for="item in list" :key="item.name" class="icon-cell" :class="selectedName === item.name && selectedVariant === item.variant ? 'icon-cell--active' : ''" @tap="selectIcon(item)"><t-icon :name="item.name" :variant="item.variant" :color="iconColor" :size="26" /><text class="icon-name">{{ item.name }}</text></view></view>
				<view class="license-note">Tabler Icons · MIT License · 3.50.0</view>
			</scroll-view>
			<view class="pagination"><button class="page-btn" :disabled="page <= 1 || loading" @tap="changePage(-1)">上一页</button><text class="page-summary">{{ page }} / {{ totalPages }}</text><button class="page-btn page-btn--next" :disabled="!hasMore || loading" @tap="changePage(1)">下一页</button></view>
		</view>
	</tui-page>
</template>

<script>
import { TABLER_VERSION } from '@/utils/tabler-icons.js';

export default {
	data() {
		return {
			version: TABLER_VERSION, libraryCount: 0, keyword: '', variant: 'outline', category: '',
			categoryOptions: [{ value: '', text: '全部分类' }], categoryIndex: 0,
			list: [], page: 1, limit: 40, totalCount: 0, hasMore: false, loading: false, error: '',
			selectedName: 'home', selectedVariant: 'outline', colorChoice: 'theme', iconSize: 28,
			sizes: [20, 24, 28, 36], scrollTop: 0
		};
	},
	computed: {
		iconColor() { return this.colorChoice === 'theme' ? this.themeColor : this.colorChoice; },
		palette() {
			return ['theme', '#303133', '#409eff', '#36a77c', '#9672d4'].map(key => ({ key, style: 'background-color:' + (key === 'theme' ? this.themeColor : key) }));
		},
		categoryLabel() { return this.categoryOptions[this.categoryIndex] ? this.categoryOptions[this.categoryIndex].text : '全部分类'; },
		totalPages() { return Math.max(1, Math.ceil(this.totalCount / this.limit)); },
		usage() {
			return '<t-icon name="' + this.selectedName + '"' + (this.selectedVariant === 'filled' ? ' variant="filled"' : '') +
				(this.colorChoice === 'theme' ? '' : ' color="' + this.iconColor + '"') + ' size="' + this.iconSize + '" />';
		}
	},
	onLoad() { this._requestId = 0; this.fetchIcons(); },
	onUnload() { this._requestId++; clearTimeout(this._searchTimer); },
	methods: {
		async fetchIcons() {
			const requestId = ++this._requestId;
			this.loading = true; this.error = '';
			try {
				const res = await this.$request({ url: 'common/icon/list', data: { keyword: this.keyword.trim(), variant: this.variant, category: this.category, page: this.page, limit: this.limit } });
				if (requestId !== this._requestId) return;
				if (res.code === -1) { this.$http.promptLogin('登录后继续查看图标'); return; }
				if (res.code !== 0) throw new Error(res.msg || '图标目录读取失败');
				const data = res.data;
				this.list = data.list; this.totalCount = data.total_count; this.libraryCount = data.library_count;
				this.hasMore = data.has_more;
				this.categoryOptions = [{ value: '', text: '全部分类' }].concat(data.categories.map(c => ({ value: c.value, text: c.label + ' · ' + c.count })));
				this.categoryIndex = Math.max(0, this.categoryOptions.findIndex(c => c.value === this.category));
				this.scrollTop = this.scrollTop === 0 ? 1 : 0;
			} catch (e) {
				if (requestId === this._requestId) { this.list = []; this.error = e.message || '网络暂时不可用，请重试'; }
			} finally { if (requestId === this._requestId) this.loading = false; }
		},
		onSearchInput() { clearTimeout(this._searchTimer); this._searchTimer = setTimeout(() => this.searchNow(), 350); },
		searchNow() { clearTimeout(this._searchTimer); this.page = 1; this.fetchIcons(); },
		clearSearch() { this.keyword = ''; this.searchNow(); },
		setVariant(value) { if (this.variant === value) return; this.variant = value; this.category = ''; this.categoryIndex = 0; this.searchNow(); },
		setCategory(e) { this.categoryIndex = Number(e.detail.value); this.category = this.categoryOptions[this.categoryIndex].value; this.searchNow(); },
		changePage(delta) { if (this.loading || (delta < 0 && this.page <= 1) || (delta > 0 && !this.hasMore)) return; this.page += delta; this.fetchIcons(); },
		selectIcon(item) { this.selectedName = item.name; this.selectedVariant = item.variant; },
		setColor(color) { this.colorChoice = color; },
		copyUsage() { uni.setClipboardData({ data: this.usage }); }
	}
};
</script>

<style lang="scss" scoped>
.icon-page { height:calc(100vh - var(--tui-nav-height)); display:flex; flex-direction:column; max-width:900px; margin:0 auto; padding:18px 16px 0; box-sizing:border-box; color:#303133; }
.intro { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-shrink:0; }.headline { display:block; font-size:19px; font-weight:600; letter-spacing:-.4px; }.subtitle { display:block; font-size:11px; color:#949aa5; margin-top:7px; }.version { font-size:9px; color:#a0a6af; letter-spacing:1px; white-space:nowrap; }
.preview-card { background:#fff; border:1px solid #ebeef2; border-radius:14px; padding:16px; flex-shrink:0; }.preview-top { display:flex; align-items:center; gap:12px; }.preview-icon { width:54px; height:54px; display:flex; align-items:center; justify-content:center; background:var(--theme-color-bg); border-radius:12px; flex-shrink:0; }.preview-info { flex:1; min-width:0; }.selected-name { font-size:15px; font-weight:600; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.preview-caption { display:block; font-size:10px; color:#9a9faa; margin-top:6px; }.copy-btn { margin:0; padding:0 10px; height:29px; line-height:29px; border-radius:6px; font-size:11px; color:var(--theme-color); background:var(--theme-color-bg); flex-shrink:0; }.copy-btn::after,.page-btn::after,.retry-btn::after { border:0; }
.controls { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; }.colors { display:flex; align-items:center; gap:10px; }.color-choice { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid #fff; box-sizing:border-box; }.color-choice--active { box-shadow:0 0 0 1px #b2b7c0; }.color-check { color:#fff; font-size:12px; }.sizes { display:flex; gap:3px; background:#f5f6f8; border-radius:5px; padding:2px; }.size-choice { font-size:10px; padding:4px 6px; color:#969ca6; border-radius:3px; }.size-choice--active { background:#fff; color:#303133; }.usage { margin-top:15px; padding-top:12px; border-top:1px solid #f0f1f4; font-size:10px; color:#8e95a0; font-family:monospace; line-height:1.7; word-break:break-all; }
.library-tools { margin-top:18px; flex-shrink:0; }.search-bar { display:flex; align-items:center; gap:9px; background:#fff; border:1px solid #e9ecf1; height:40px; padding:0 12px; border-radius:8px; }.search-input { flex:1; min-width:0; font-size:12px; height:38px; }.clear-search { font-size:22px; color:#9a9faa; padding:0 3px; }.filters { display:flex; justify-content:space-between; align-items:center; margin-top:14px; gap:14px; }.variants { display:flex; background:#e9ecf1; padding:3px; border-radius:7px; }.variant { font-size:12px; padding:6px 20px; color:#8c939e; border-radius:5px; }.variant--active { color:#303133; background:#fff; font-weight:500; }.category-picker { min-width:100px; max-width:55%; }.category-value { display:flex; align-items:center; justify-content:flex-end; gap:8px; font-size:12px; color:#767e8b; }.results-meta { display:flex; justify-content:space-between; font-size:10px; color:#a0a6b0; padding:15px 2px 11px; }
.library-scroll { flex:1; height:0; min-height:100px; }.icon-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; padding:1px; }.icon-cell { height:82px; background:#fff; border:1px solid #edf0f4; border-radius:9px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:11px; min-width:0; }.icon-cell--active { background:var(--theme-color-bg); border-color:var(--theme-color); }.icon-name { font-size:9px; color:#868e9b; width:90%; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.empty { display:flex; flex-direction:column; align-items:center; padding:45px 10px; color:#9098a4; font-size:13px; gap:12px; }.empty-hint { font-size:11px; }.retry-btn { font-size:12px; color:var(--theme-color); background:var(--theme-color-bg); }.license-note { text-align:center; color:#b2b7c0; font-size:9px; padding:20px 5px; }
.pagination { display:flex; align-items:center; justify-content:space-between; gap:15px; padding:13px 0 calc(13px + env(safe-area-inset-bottom)); flex-shrink:0; border-top:1px solid #e9edf2; }.page-btn { margin:0; padding:0 18px; height:34px; line-height:34px; border-radius:7px; font-size:12px; background:#fff; color:#6c7583; }.page-btn--next { background:var(--theme-color); color:#fff; }.page-btn[disabled] { background:#e9edf2; color:#b6bdc7; }.page-summary { color:#969eaa; font-size:11px; }
@media (min-width:768px) { .icon-grid { grid-template-columns:repeat(8,minmax(0,1fr)); }.intro { margin-top:6px; }.preview-caption { font-size:12px; }.usage { font-size:12px; }.icon-cell { height:94px; }.icon-name { font-size:11px; } }
</style>
