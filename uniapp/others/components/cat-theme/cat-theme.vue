<template>
	<view class="cat-theme" :style="themeStyle">
		<!-- 搜索栏:四套版式共用,统一白底。
		     原来按主题给它铺一层 --theme-color-bg 的浅色,实际看着像没加载完的色块,
		     跟下面的白色内容区也接不上,不如干净留白 -->
		<view class="ct-search">
			<view class="ct-search-box" @tap="toSearch">
				<icon type="search" :size="13" color="#999"></icon>
				<text class="ct-search-tip">搜索商品</text>
			</view>
		</view>

		<!-- 潮流卡片:没有侧栏,一级分类走顶部横滑 -->
		<scroll-view v-if="!spec.sidebar" scroll-x class="ct-top-cats" :scroll-into-view="topScrollId">
			<view v-for="(c, i) in cats" :key="c.id" :id="'tc_' + i" class="ct-top-cat"
				:class="{ on: i === catIndex }" @tap="pickCat(i)">{{ c.name }}</view>
		</scroll-view>

		<view class="ct-body" :style="{ height: bodyHeight + 'px' }">
			<!-- 左侧一级分类 -->
			<scroll-view v-if="spec.sidebar" scroll-y class="ct-side" :class="{ wide: spec.sidebarIcon }"
				:scroll-into-view="sideScrollId">
				<view v-for="(c, i) in cats" :key="c.id" :id="'sc_' + i" class="ct-side-item"
					:class="{ on: i === catIndex, tall: spec.sidebarIcon }" @tap="pickCat(i)">
					<view v-if="i === catIndex" class="ct-side-bar"></view>
					<!-- 没配图就用分类名首字兜底。库里 20 个分类只有 1 个有图,
					     不兜底的话这一列全是空灰块,比不放图还难看 -->
					<view v-if="spec.sidebarIcon" class="ct-side-pic" :class="{ on: i === catIndex }">
						<image v-if="c.cover_pic" class="ct-side-img" :src="c.cover_pic" mode="aspectFill" />
						<text v-else class="ct-side-initial">{{ initial(c.name) }}</text>
					</view>
					<text class="ct-side-name">{{ c.name }}</text>
				</view>
			</scroll-view>

			<view class="ct-main">
				<!-- 二级分类:四种形态。
				     只有「全部」一项时整块不渲染 —— 没有可选项,留着只是占地方,
				     九宫格形态下更是一个 25% 宽的小格子孤零零杵在左边 -->
				<scroll-view v-if="hasSubs && spec.subStyle === 'underline'" scroll-x class="ct-sub-line">
					<view v-for="(s, i) in subs" :key="i" class="ct-sub-u" :class="{ on: i === subIndex }"
						@tap="pickSub(i)">{{ s.name }}</view>
				</scroll-view>

				<scroll-view v-else-if="hasSubs && spec.subStyle === 'circle'" scroll-x class="ct-sub-circle">
					<view v-for="(s, i) in subs" :key="i" class="ct-sub-c" @tap="pickSub(i)">
						<view class="ct-sub-c-pic" :class="{ on: i === subIndex }">
							<image v-if="s.cover_pic" class="ct-sub-c-img" :src="s.cover_pic" mode="aspectFill" />
							<text v-else class="ct-sub-c-initial" :class="{ on: i === subIndex }">{{ initial(s.name) }}</text>
						</view>
						<text class="ct-sub-c-name" :class="{ on: i === subIndex }">{{ s.name }}</text>
					</view>
				</scroll-view>

				<scroll-view v-else-if="hasSubs && spec.subStyle === 'chip'" scroll-x class="ct-sub-chip">
					<view v-for="(s, i) in subs" :key="i" class="ct-sub-p" :class="{ on: i === subIndex }"
						@tap="pickSub(i)">{{ s.name }}</view>
				</scroll-view>

				<view v-else-if="hasSubs" class="ct-sub-grid">
					<view v-for="(s, i) in subs" :key="i" class="ct-sub-g" :class="{ on: i === subIndex }"
						@tap="pickSub(i)">{{ s.name }}</view>
				</view>

				<!-- 商品区 -->
				<scroll-view scroll-y class="ct-goods" :style="{ height: goodsHeight + 'px' }"
					:scroll-top="goodsScrollTop" @scrolltolower="loadMore">
					<!-- 宫格用 flex + 百分比宽度而不是 CSS grid:微信小程序早期基础库对
					     display:grid 支持不全,会整片塌成一列,而它不报错、只是布局悄悄错掉 -->
					<block v-if="spec.cols > 1">
						<view class="ct-grid">
							<view v-for="g in goods" :key="g.id" class="ct-cell" :style="{ width: cellWidth }">
								<goods-cell :goods="g" :mode="spec.goodsMode" @select="toDetail" />
							</view>
						</view>
					</block>
					<!-- 列表/大卡直接铺,**不能**套 .ct-cell:它的左右 padding 会让每项比容器窄,
					     商品卡自带的下边框跟着缩进,分割线两头出现缺口 -->
					<block v-else>
						<view class="ct-flow">
							<goods-cell v-for="g in goods" :key="g.id" :goods="g" :mode="spec.goodsMode"
								@select="toDetail" />
						</view>
					</block>

					<!-- 首屏/切分类时用骨架占位。只放一行"加载中"的话,切分类瞬间整片变白再弹回来,
					     那一下的闪比慢一点更让人觉得卡 -->
					<view v-if="loading && !goods.length" :class="spec.cols > 1 ? 'ct-grid' : 'ct-flow'">
						<!-- style 必须绑一个**字符串** computed:模板里写 `cols>1 ? {width:x} : {}`
						     会被原样编译进 wxml 的 {{}},小程序把对象字面量的 } 当成表达式结束符,
						     直接报 Bad attr `style` unexpected token `}` —— 编译期不报,上传时才炸 -->
						<!-- :key 不能写表达式(同上)。n 是 1..N 的数字,本身就唯一 -->
						<view v-for="n in skeletonCount" :key="n" class="ct-sk"
							:class="[spec.cols > 1 ? 'ct-cell' : 'ct-sk-row']"
							:style="skeletonCellStyle">
							<view class="ct-sk-pic" :class="'ct-sk-pic--' + spec.goodsMode"></view>
							<view class="ct-sk-body">
								<view class="ct-sk-line"></view>
								<view class="ct-sk-line short"></view>
								<view class="ct-sk-line price"></view>
							</view>
						</view>
					</view>

					<view v-else-if="!loading && !goods.length" class="ct-empty">
						<view class="ct-empty-icon">
							<tui-icon name="bag" :size="56" unit="rpx" :color="themeColor"></tui-icon>
						</view>
						<text class="ct-empty-t">该分类下暂无商品</text>
						<text class="ct-empty-d">{{ emptyHint }}</text>
					</view>

					<view v-if="loading && goods.length" class="ct-tip">加载中…</view>
					<view v-else-if="noMore && goods.length" class="ct-tip">— 没有更多了 —</view>
					<view class="ct-safe"></view>
				</scroll-view>
			</view>
		</view>
	</view>
</template>

<script>
	import goodsCell from './goods-cell.vue'

	/**
	 * 分类页主题版式容器。
	 *
	 * 四套版式换的是**骨架**不是颜色 —— 配色一律走全局 themeStyle 注入的
	 * --theme-color / --theme-color-bg / --theme-shadow,不在这里硬编码任何色值,
	 * 否则商城改了主题色分类页会对不上(规范 §14.2.1)。
	 *
	 * 版式差异全部收在 SPECS 里:模板只写一遍,靠 spec 决定「有没有侧栏 / 二级分类
	 * 长什么样 / 商品几列」。四套各写一份模板的话,改一个商品卡要动四处。
	 */
	const SPECS = {
		classic: { sidebar: true, sidebarIcon: false, subStyle: 'underline', goodsMode: 'list', cols: 1 },
		market: { sidebar: true, sidebarIcon: true, subStyle: 'circle', goodsMode: 'grid', cols: 2 },
		trend: { sidebar: false, sidebarIcon: false, subStyle: 'chip', goodsMode: 'trend', cols: 1 },
		dense: { sidebar: true, sidebarIcon: false, subStyle: 'grid', goodsMode: 'grid', cols: 3 }
	}

	export default {
		name: 'cat-theme',
		components: { goodsCell },
		props: {
			theme: { type: String, default: 'classic' }
		},
		data() {
			return {
				cats: [],
				subs: [],
				catIndex: 0,
				subIndex: 0,
				goods: [],
				page: 1,
				limit: 10,
				loading: false,
				noMore: false,
				seq: 0,
				goodsScrollTop: 0,
				winWidth: 375,
				bodyHeight: 0,
				sideScrollId: '',
				topScrollId: ''
			}
		},
		computed: {
			spec() {
				return SPECS[this.theme] || SPECS.classic
			},
			/** 宫格列宽。间距由 .ct-cell 的左右 padding 吃掉(box-sizing: border-box),
			 *  所以这里就是等分百分比,不用再减 gap —— 减了会算重、右边多出一条缝。 */
			cellWidth() {
				return (100 / this.spec.cols).toFixed(4) + '%'
			},
			/**
			 * 二级分类区要不要出现。subs 恒定带一个「全部」,所以 length<=1 意味着
			 * 这个一级分类**没有子分类** —— 库里大多数分类都是这样。
			 * 只剩一个没得选的「全部」,留着纯占地方。
			 */
			hasSubs() {
				return this.subs.length > 1
			},
			/** 商品滚动区高度 = 主体高度 - 二级分类占位 */
			goodsHeight() {
				// 占位值跟样式里的 rpx 一一对应(下划线 80 / 圆标 168 / 胶囊 88 / 九宫格每行 60)。
				// 改样式必须同步改这里,否则商品区比实际可用空间高一截、最后一行滚不到。
				if (!this.hasSubs) {
					return Math.max(this.bodyHeight, 200)   // 整块不渲染,高度全给商品
				}
				let sub
				if (this.spec.subStyle === 'grid') {
					sub = this.rpx(Math.ceil(this.subs.length / 4) * 60 + 18)
				} else {
					sub = this.rpx({ underline: 80, circle: 168, chip: 88 }[this.spec.subStyle] || 80)
				}
				return Math.max(this.bodyHeight - sub, 200)
			},
			/** 骨架卡数量:铺满一屏即可,多了纯属浪费 */
			skeletonCount() {
				return this.spec.cols > 1 ? this.spec.cols * 3 : 4
			},
			/** 骨架卡宽度。返回字符串而不是对象 —— 见模板处注释,对象字面量进 wxml 会让上传失败 */
			skeletonCellStyle() {
				return this.spec.cols > 1 ? 'width:' + this.cellWidth : ''
			},
			/**
			 * 空态提示要说清是「没挂商品」还是「真没有」——
			 * 二级分类下无商品,多半是商品只挂了一级分类没挂二级(库里绝大多数商品都是这样),
			 * 直接说"暂无商品"运营会以为是 bug,一路查到接口。
			 */
			emptyHint() {
				if (this.subIndex > 0) {
					return '该二级分类下还没有商品,可在商品编辑页把商品挂到这个分类'
				}
				return '换个分类看看'
			}
		},
		watch: {
			// 有没有侧栏会影响可用高度(潮流版多一条顶部横滑)。theme 变了但组件没重建时
			// 只有 spec 会更新,bodyHeight 还是旧的 —— 补算一次。
			theme() {
				this.calcHeight()
			}
		},
		mounted() {
			this.calcHeight()
			this.loadCats()
		},
		methods: {
			/**
			 * 分类没配图时的兜底字:中文取首字,英文取前两个字母(单个拉丁字母太空)。
			 * 用 Array.from 按码点切而不是 slice —— emoji 是代理对,slice(0,1) 会劈成半个,
			 * 渲染出来是个乱码方块。分类名带 emoji 不常见,但出一次就很难看。
			 */
			initial(name) {
				const s = String(name || '').trim()
				if (!s) return '?'
				const chars = Array.from(s)
				return /^[\x00-\x7F]/.test(s) ? chars.slice(0, 2).join('').toUpperCase() : chars[0]
			},
			/** rpx → px。样式里全是 rpx,高度却要算 px,直接写死换算值在大屏机型上会算少 */
			rpx(v) {
				return v * (this.winWidth || 375) / 750
			},
			calcHeight() {
				const info = uni.getWindowInfo ? uni.getWindowInfo() : uni.getSystemInfoSync()
				this.winWidth = info.windowWidth || 375

				let h = info.windowHeight
				h -= this.rpx(92)                       // 搜索栏
				if (!this.spec.sidebar) h -= this.rpx(80)   // 潮流版顶部一级分类横滑

				// 分类页通常挂在 tabbar 上,不扣掉的话最后一行商品会被 tabbar 压住。
				// 旧版是写死判断 url == '/pages/cat2/cat2' —— 页面早就搬到 others/ 了,
				// 那个判断其实一直没生效过。这里改成拿当前真实路由去比对。
				if (this.isInTabbar()) h -= 50

				this.bodyHeight = Math.max(h, 200)
			},
			isInTabbar() {
				try {
					const pages = getCurrentPages()
					if (!pages.length) return false
					const route = String(pages[pages.length - 1].route || '').replace(/^\//, '')
					const tabbar = uni.getStorageSync('TABBAR')
					const list = (tabbar && tabbar.list) || []
					return list.some(i => String(i.url || '').replace(/^\//, '') === route)
				} catch (e) {
					return false
				}
			},
			loadCats() {
				this.$request({ url: '/api/goods/cat2' }).then(res => {
					if (res.code != 0) return
					this.cats = res.data.list || []
					if (this.cats.length) this.applyCat(0)
				})
			},
			pickCat(i) {
				if (i === this.catIndex) return
				this.applyCat(i)
				// 长列表时把选中项带进可视区,否则点到底部分类会"跳回顶部"的错觉
				this.sideScrollId = 'sc_' + Math.max(i - 2, 0)
				this.topScrollId = 'tc_' + Math.max(i - 1, 0)
			},
			applyCat(i) {
				this.catIndex = i
				const children = (this.cats[i] && this.cats[i].children) || []
				// 「全部」永远排第一:大部分商品只挂了一级分类,没有这一项二级 tab 会全空
				this.subs = [{ id: 'all', name: '全部' }].concat(children)
				this.subIndex = 0
				this.reload()
			},
			pickSub(i) {
				if (i === this.subIndex) return
				this.subIndex = i
				this.reload()
			},
			/**
			 * 切换分类。seq 自增作废掉所有在飞的请求 ——
			 * 不这么做的话快速点两个分类会同时踩两个坑:后一次 reload 被 `if (this.loading)`
			 * 直接吞掉(新分类永远不加载),而先发出的旧请求回来时 page 还是 1,
			 * 会把**上一个分类的商品**填进当前分类的列表里。
			 */
			reload() {
				this.seq++
				this.page = 1
				this.goods = []
				this.noMore = false
				this.loading = false
				// scroll-top 得有变化才生效,所以在 0 / 0.01 之间来回切。
				// 不重置的话从长列表切到短列表,会停在旧的滚动位置、看上去整页空白。
				this.goodsScrollTop = this.goodsScrollTop === 0 ? 0.01 : 0
				this.loadGoods()
			},
			loadGoods() {
				if (this.loading || this.noMore) return
				const cat = this.cats[this.catIndex]
				if (!cat) return

				const mySeq = this.seq
				this.loading = true

				const sub = this.subs[this.subIndex]
				this.$request({
					url: '/api/goods/list',
					data: {
						cat_id: cat.id,
						sub_cat_id: sub && sub.id !== 'all' ? sub.id : 'all',
						page: this.page,
						limit: this.limit
					}
				}).then(res => {
					if (mySeq !== this.seq) return   // 已经切走了,这次的结果作废
					this.loading = false
					if (res.code != 0) return
					const list = (res.data && res.data.list) || []
					this.goods = this.page === 1 ? list : this.goods.concat(list)
					if (list.length < this.limit) this.noMore = true
					else this.page++
				}).catch(() => {
					if (mySeq !== this.seq) return
					this.loading = false
				})
			},
			loadMore() {
				this.loadGoods()
			},
			toDetail(g) {
				// 路径和参数名都跟全项目其余 6 处保持一致(/others/goods/goods?id=)。
				// goods-detail 那个目录压根不存在,写错了点上去是静默无反应 —— 不报错、
				// 也不跳转,最难查。
				uni.navigateTo({ url: '/others/goods/goods?id=' + g.id })
			},
			toSearch() {
				uni.navigateTo({ url: '/others/search/search' })
			}
		}
	}
</script>

<style scoped lang="scss">
	.cat-theme {
		background: #fff;
	}

	/* ---------- 搜索栏 ---------- */
	.ct-search {
		height: 92rpx;
		padding: 0 24rpx;
		display: flex;
		align-items: center;
		box-sizing: border-box;
		background: #fff;
		border-bottom: 1rpx solid #f0f0f0;
	}

	.ct-search-box {
		flex: 1;
		height: 60rpx;
		background: #f5f6f7;
		border-radius: 30rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0 24rpx;
	}

	.ct-search-icon {
		font-size: 30rpx;
		color: #999;
		margin-right: 10rpx;
	}

	.ct-search-tip {
		font-size: 26rpx;
		color: #999;
	}

	/* ---------- 顶部一级分类(潮流版) ---------- */
	.ct-top-cats {
		white-space: nowrap;
		padding: 0 24rpx;
		background: #fff;
		height: 80rpx;
	}

	.ct-top-cat {
		display: inline-block;
		font-size: 28rpx;
		color: #999;
		padding: 14rpx 0 10rpx;
		margin-right: 34rpx;
		border-bottom: 5rpx solid transparent;

		&.on {
			color: #222;
			font-size: 30rpx;
			font-weight: 500;
			border-bottom-color: var(--theme-color);
		}
	}

	/* ---------- 主体 ---------- */
	.ct-body {
		display: flex;
	}

	.ct-side {
		width: 168rpx;
		flex-shrink: 0;
		box-sizing: border-box;
		background: #f7f8fa;
		/* 侧栏和主区之间给一条细线。原来纯靠底色深浅区分,选中项是白底、
		   跟右侧主区连成一片,看不出边界在哪 */
		border-right: 1rpx solid #f0f0f0;

		&.wide {
			width: 190rpx;
		}
	}

	.ct-side-item {
		position: relative;
		height: 96rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		font-size: 25rpx;
		color: #666;

		/* 图标 54 + 间距 6 + 两行文字约 63 = 123rpx,给到 136 留点余量。
		   卡在 124 的话字号或行高稍有偏差就溢出,而侧栏是滚动区、加高不影响商品区高度计算 */
		&.tall {
			height: 136rpx;
		}

		&.on {
			background: #fff;
			color: var(--theme-color);
			font-weight: 500;
		}
	}

	.ct-side-bar {
		position: absolute;
		left: 0;
		top: 26rpx;
		bottom: 26rpx;
		width: 6rpx;
		border-radius: 0 4rpx 4rpx 0;
		background: var(--theme-color);
	}

	.ct-side-pic {
		width: 54rpx;
		height: 54rpx;
		border-radius: 14rpx;
		background: #fff;
		margin-bottom: 6rpx;
		overflow: hidden;

		&.on {
			background: var(--theme-color-bg);
		}
	}

	.ct-side-img {
		width: 100%;
		height: 100%;
	}

	/* 兜底字要横竖都居中。原来只给了 line-height(竖向),横向没管 ——
	   字就贴在圆/方块左边,比不放还难看 */
	.ct-side-initial {
		display: block;
		width: 100%;
		height: 100%;
		font-size: 26rpx;
		color: #b0b0b0;
		line-height: 54rpx;
		text-align: center;
	}

	.ct-side-pic.on .ct-side-initial {
		color: var(--theme-color);
	}

	/* 分类名可能很长,不截断会把侧栏撑破/换行错位 */
	.ct-side-name {
		line-height: 1.25;
		width: 100%;
		padding: 0 8rpx;
		box-sizing: border-box;
		text-align: center;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.ct-main {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	/* ---------- 二级分类:下划线 ---------- */
	/* 二级分类和商品区之间加一条淡线,不然两块内容糊在一起分不出层次 */
	.ct-sub-line,
	.ct-sub-circle,
	.ct-sub-chip,
	.ct-sub-grid {
		border-bottom: 1rpx solid #f4f4f4;
		box-sizing: border-box;
	}

	.ct-sub-line {
		white-space: nowrap;
		padding: 0 20rpx;
		height: 80rpx;
	}

	.ct-sub-u {
		display: inline-block;
		font-size: 26rpx;
		color: #666;
		padding: 16rpx 0 10rpx;
		margin-right: 30rpx;
		border-bottom: 4rpx solid transparent;

		&.on {
			color: var(--theme-color);
			font-weight: 500;
			border-bottom-color: var(--theme-color);
		}
	}

	/* ---------- 二级分类:圆标 ---------- */
	.ct-sub-circle {
		white-space: nowrap;
		padding: 16rpx 20rpx 10rpx;
		height: 168rpx;
		box-sizing: border-box;
	}

	.ct-sub-c {
		display: inline-block;
		width: 96rpx;
		text-align: center;
		margin-right: 18rpx;
		vertical-align: top;
	}

	.ct-sub-c-pic {
		width: 84rpx;
		height: 84rpx;
		border-radius: 50%;
		background: #f2f2f2;
		margin: 0 auto 8rpx;
		overflow: hidden;
		border: 4rpx solid transparent;
		box-sizing: border-box;

		&.on {
			border-color: var(--theme-color);
		}
	}

	.ct-sub-c-img {
		width: 100%;
		height: 100%;
	}

	.ct-sub-c-initial {
		display: block;
		width: 100%;
		height: 100%;
		font-size: 28rpx;
		color: #b0b0b0;
		/* 圆是 84rpx,但有 4rpx 边框且 border-box,内容区只有 76rpx */
		line-height: 76rpx;
		text-align: center;

		&.on {
			color: var(--theme-color);
		}
	}

	.ct-sub-c-name {
		font-size: 23rpx;
		color: #666;
		display: block;
		width: 100%;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;

		&.on {
			color: var(--theme-color);
			font-weight: 500;
		}
	}

	/* ---------- 二级分类:胶囊 ---------- */
	.ct-sub-chip {
		white-space: nowrap;
		padding: 14rpx 24rpx 10rpx;
		height: 88rpx;
		box-sizing: border-box;
	}

	.ct-sub-p {
		display: inline-block;
		font-size: 25rpx;
		color: #666;
		background: #f5f5f5;
		border-radius: 28rpx;
		padding: 8rpx 26rpx;
		margin-right: 14rpx;

		&.on {
			background: var(--theme-color);
			color: #fff;
		}
	}

	/* ---------- 二级分类:九宫格 ---------- */
	/* 这个容器没有显式 height,高度由内容撑;box-sizing 管不到 border,
	   1rpx 的下边框是**额外**加上去的。padding-bottom 少给 1rpx 抵掉,
	   让实际占位精确等于 goodsHeight 里算的 rows*60+18 */
	.ct-sub-grid {
		display: flex;
		flex-wrap: wrap;
		padding: 14rpx 12rpx 3rpx;
	}

	/* 必须单行省略:这一格是固定宽度的,分类名一长就换行 → 整块被撑高,
	   而 goodsHeight 是按「每行 60rpx」算出来的,一撑高商品区就跟着错位。
	   横滑的那几种形态不会有这问题(它们在 nowrap 容器里横着排) */
	.ct-sub-g {
		width: calc(25% - 12rpx);
		margin: 0 6rpx 12rpx;
		text-align: center;
		font-size: 23rpx;
		color: #666;
		background: #f5f5f5;
		border-radius: 8rpx;
		padding: 10rpx 8rpx;
		box-sizing: border-box;
		height: 48rpx;
		line-height: 28rpx;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;

		&.on {
			background: var(--theme-color-bg);
			color: var(--theme-color);
			font-weight: 500;
		}
	}

	/* ---------- 商品区 ---------- */
	.ct-goods {
		flex: 1;
	}

	.ct-flow {
		padding: 0 20rpx;
	}

	/* 负 margin + cell 内 padding:最后一行不满时保持左对齐。
	   用 space-between 的话「3 列只剩 2 个」会被拉到两端,中间裂个大口子。 */
	.ct-grid {
		display: flex;
		flex-wrap: wrap;
		padding: 14rpx 9rpx 0;
	}

	.ct-cell {
		padding: 0 7rpx;
		margin-bottom: 14rpx;
		box-sizing: border-box;
	}

	.ct-tip {
		text-align: center;
		font-size: 24rpx;
		color: #c0c0c0;
		padding: 30rpx 0;
	}

	/* ---------- 骨架屏 ---------- */
	.ct-sk-row {
		display: flex;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f4f4f4;
	}

	.ct-sk-pic {
		background: #f0f0f0;
		border-radius: 14rpx;
		animation: ct-sk-pulse 1.2s ease-in-out infinite;
	}

	.ct-sk-pic--list {
		width: 190rpx;
		height: 190rpx;
		flex-shrink: 0;
		margin-right: 22rpx;
	}

	.ct-sk-pic--trend {
		width: 240rpx;
		height: 240rpx;
		flex-shrink: 0;
		margin-right: 24rpx;
	}

	.ct-sk-pic--grid {
		width: 100%;
		height: 0;
		padding-bottom: 100%;
		border-radius: 14rpx 14rpx 0 0;
	}

	.ct-sk-row .ct-sk-body {
		flex: 1;
		min-width: 0;
		padding: 8rpx 0;
	}

	.ct-cell.ct-sk .ct-sk-body {
		padding: 14rpx;
	}

	.ct-sk-line {
		height: 24rpx;
		background: #f0f0f0;
		border-radius: 6rpx;
		margin-bottom: 14rpx;
		animation: ct-sk-pulse 1.2s ease-in-out infinite;

		&.short {
			width: 55%;
		}

		&.price {
			width: 35%;
			height: 30rpx;
			margin-bottom: 0;
		}
	}

	@keyframes ct-sk-pulse {
		0%, 100% { opacity: 1; }
		50% { opacity: .5; }
	}

	/* ---------- 空态 ---------- */
	.ct-empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 130rpx 50rpx;
	}

	/* 不引图片资源:图标走 tui-icon 的内嵌字体(base64,零网络请求),
	   底衬一个淡色圆盘,两者都跟着主题色走。
	   之所以不用全站通用的 tui-no-data:那张 img_nodata.png 要从后端
	   /statics/app/ 现拉,而这里是分类页切 tab 的高频路径,空态一闪而过,
	   拉图往往还没到就切走了;而且它是固定配色,压不住这四套版式的主题色。 */
	.ct-empty-icon {
		width: 120rpx;
		height: 120rpx;
		border-radius: 50%;
		background: var(--theme-color-bg);
		margin-bottom: 28rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.ct-empty-t {
		font-size: 28rpx;
		color: #8a8a8a;
	}

	.ct-empty-d {
		font-size: 23rpx;
		color: #bbb;
		margin-top: 14rpx;
		text-align: center;
		line-height: 1.6;
	}

	.ct-safe {
		height: env(safe-area-inset-bottom);
	}
</style>
