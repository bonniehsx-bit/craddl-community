/**
 * 列表 / 首页用的商品小图。
 *
 * 商品封面原图 1440×1440、一张 800KB 上下,卡片只显示 170 个点宽,小带宽服务器刷一屏要下好几 MB。
 * 后台上传图片时会在原图旁边生成一张最长边 500 的缩略图(api/helpers/ImageThumbHelper.php,同一条规则):
 *
 *   https://域名/uploads/image/original/20260819/abc.jpg  →  .../abc_thumb.jpg
 *
 * 这里只凭原图地址推出缩略图地址,不用改任何接口。缩略图不存在(还没补的老图)时,
 * 图片的 @error 调 markThumbFailed,之后这张就一直用原图 —— 最差和原来一样,不会显示不出来。
 *
 * 只处理本站服务器上的图(域名和接口域名一致):云存储(OSS / COS / 七牛)的图没有缩略图,不去多请求一次。
 *
 *   import { thumb, markThumbFailed } from '@/utils/thumb.js'
 *   <image :src="thumbSrc(goods.cover_pic)" @error="thumbFail(goods.cover_pic)" />
 */
import { getApiRoot } from '@/utils/request.js';

const failed = {};
// 早期上传目录是 /uploads/images/(多一个 s),一并处理
const RULE = /^(.+\/uploads\/images?\/.+?)(\.(jpe?g|png|webp))(\?.*)?$/i;

function hostOf(url) {
	const m = /^(?:https?:)?\/\/([^/?#]+)/i.exec(url || '');
	return m ? m[1].toLowerCase() : '';
}

/**
 * 本站图片才有缩略图,外链 / 云存储 / 别家商城的图一律原样用。
 * 拿不到接口域名(小程序商城配置还没加载完)时也用原图;只有 H5 本地开发走代理没有域名,放开方便调试
 */
function isOwnHost(url) {
	const apiHost = hostOf(getApiRoot());
	if (!apiHost) {
		// #ifdef H5
		return process.env.NODE_ENV === 'development';
		// #endif
		// #ifndef H5
		return false;
		// #endif
	}
	return hostOf(url) === apiHost;
}

export function thumb(url) {
	if (!url || typeof url !== 'string' || failed[url]) return url;
	const m = RULE.exec(url);
	if (!m || /_thumb$/i.test(m[1]) || !isOwnHost(url)) return url;
	return m[1] + '_thumb' + m[2] + (m[4] || '');
}

export function markThumbFailed(url) {
	if (url) failed[url] = true;
}

/**
 * 组件里混入:模板写 thumbSrc(url) / @error="thumbFail(url)"。
 * thumbTick 只用来在退回原图后触发重新渲染。
 */
export const thumbMixin = {
	data() {
		return { thumbTick: 0 };
	},
	methods: {
		thumbSrc(url) {
			return this.thumbTick >= 0 ? thumb(url) : url;
		},
		thumbFail(url) {
			if (url && !failed[url] && thumb(url) !== url) {
				markThumbFailed(url);
				this.thumbTick++;
			}
		},
	},
};
