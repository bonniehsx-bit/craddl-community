<template>
	<!-- 图片用原生 image、视频用原生 video,其余文字段落交给原生 rich-text。
	     原来套的是 feng-parse,每个 HTML 节点建一个自定义组件(一段详情动辄几十上百个),小程序里打开页面慢 -->
	<view class="tui-rich-text">
		<view v-for="(seg, i) in segments" :key="i">
			<image v-if="seg.type === 'img'" class="rt-img" :src="seg.src" mode="widthFix" lazy-load
				@tap="preview(i)"></image>
			<video v-else-if="seg.type === 'video'" class="rt-video" :src="seg.src" controls></video>
			<rich-text v-else :nodes="seg.html"></rich-text>
		</view>
	</view>
</template>
<script>
	import wxDiscode from '@/components/feng-parse/libs/wxDiscode';

	// 图片和视频单独拆出来,中间的文字段落原样交给 rich-text
	const MEDIA_RE = /<img\b[^>]*>|<video\b[^>]*>[\s\S]*?<\/video>|<video\b[^>]*\/?>/gi;

	function attrOf(tag, name) {
		const m = tag.match(new RegExp('\\s' + name + '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s>]+))', 'i'));
		return m ? (m[1] || m[2] || m[3] || '') : '';
	}

	// 文字段落:去 class / style(原来 feng-parse 也全去掉)、去掉标签之间的排版空白(原来只有空白的文字节点直接跳过),
	// pre / code 换成普通块:不少商品详情是淘宝源码贴进代码块的,留着会按等宽字体、保留空格排
	function cleanHtml(chunk) {
		return chunk
			// 整段还原转义符后,文字里原本转义的 < 和 & 也成了裸字符,原生 rich-text 解析会出错(feng-parse 自带的解析器扛得住):
			// 不是标签开头的 < 和不是实体的 & 再转义回去。只对文字段落做,图片 / 视频地址里的 & 不能动
			.replace(/<(?![a-zA-Z\/!])/g, '&lt;')
			.replace(/&(?![a-zA-Z]+;|#\d+;|#x[0-9a-fA-F]+;)/g, '&amp;')
			.replace(/<(\/?)pre\b[^>]*>/gi, '<$1div>')
			.replace(/<(\/?)code\b[^>]*>/gi, '<$1span>')
			.replace(/\s(?:class|style)\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
			// 只去普通空格和换行,别把 nbsp 这类有意的空格也吃掉
			.replace(/>[ \t\r\n]+</g, '><')
			// 段首落单的结束标签(图片把一个段落从中间切开时留下的)
			.replace(/^(?:\s*<\/[a-z0-9]+>)+/i, '')
			.trim();
	}

	function hasText(chunk) {
		return chunk.replace(/<[^>]*>/g, '').replace(/\s+/g, '') !== '';
	}

	/**
	 * HTML → [{type:'html'|'img'|'video'}]。前置处理照搬 feng-parse(components/feng-parse/libs/html2json.js):
	 * 去注释 / 脚本 / 样式,整段还原转义符(淘宝源码转义后贴进来的详情,还原后才是真正的 HTML),图片地址 // 开头补 https
	 */
	function buildSegments(html) {
		let s = String(html || '')
			.replace(/<!--[\s\S]*?-->/g, '')
			.replace(/<script[\s\S]*?<\/script>/gi, '')
			.replace(/<style[\s\S]*?<\/style>/gi, '')
			// 空格类实体先换成真的 Unicode 空格:strDiscode 会把它们换成「装着空格的 span」,下面去排版空白时会被一起去掉
			.replace(/&nbsp;|&#160;|&#xa0;/gi, '\u00a0')
			.replace(/&ensp;|&#8194;|&#x2002;/gi, '\u2002')
			.replace(/&emsp;|&#8195;|&#x2003;/gi, '\u2003');
		s = wxDiscode.strDiscode(s);
		const segs = [];
		const pushHtml = chunk => {
			const h = cleanHtml(chunk);
			// pre-wrap:原来对文字用的就是 pre-wrap,在文字里敲的换行要保留
			if (h && hasText(h)) segs.push({ type: 'html', html: '<div style="white-space:pre-wrap;">' + h + '</div>' });
		};
		let last = 0;
		let m;
		MEDIA_RE.lastIndex = 0;
		while ((m = MEDIA_RE.exec(s))) {
			pushHtml(s.slice(last, m.index));
			const tag = m[0];
			if (/^<img/i.test(tag)) {
				const src = wxDiscode.urlToHttpUrl(attrOf(tag, 'src') || attrOf(tag, 'data-src'), '');
				if (src) segs.push({ type: 'img', src });
			} else {
				const source = tag.match(/<source\b[^>]*>/i);
				const src = attrOf(tag, 'src') || (source ? attrOf(source[0], 'src') : '');
				if (src) segs.push({ type: 'video', src: wxDiscode.urlToHttpUrl(src, '') });
			}
			last = m.index + tag.length;
		}
		pushHtml(s.slice(last));
		return segs;
	}

	export default {
		name: 'tui-rich-text',
		props: {
			value: {
				type: String,
				default: ''
			}
		},
		computed: {
			segments() {
				return buildSegments(this.value);
			}
		},
		methods: {
			// 点图看大图,能左右翻这段内容里的所有图(和原来 feng-parse 的预览一样)
			preview(i) {
				const urls = this.segments.filter(s => s.type === 'img').map(s => s.src);
				const cur = this.segments[i];
				if (!cur || !urls.length) return;
				uni.previewImage({ current: cur.src, urls, loop: false, indicator: 'number', longPressActions: false });
			}
		}
	}
</script>
<style scoped>
	.tui-rich-text {
		font-size: 28rpx;
	}

	/* 和原来 feng-parse 的图片一样:铺满宽度、按宽度算高、块级(图片之间不留缝) */
	.rt-img {
		width: 100%;
		display: block;
	}

	.rt-video {
		width: 100%;
	}
</style>
