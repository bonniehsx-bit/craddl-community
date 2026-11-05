import { withPid } from '@/utils/share-path.js';
import { fitShareImage } from '@/utils/shareImage.js';
import { getMallId } from '@/utils/request.js';
// #ifdef H5
import { buildShareUrl } from '@/utils/wechatJsSdk.js';
// #endif

/** 购物车和好友落地页共用同一个转发入口，不在链接中放商品列表或用户隐私。 */
export default {
	data() {
		return { shareRecord: null, shareCover: '' };
	},
	onShareAppMessage() {
		if (!this.shareRecord) return this._getShareConfig();
		return {
			title: this.shareRecord.title || '分享给你的好物',
			path: this.goodsSharePath(),
			imageUrl: this.shareCover || ((this.shareRecord.items || [])[0] || {}).cover_pic || ''
		};
	},
	onShareTimeline() {
		if (!this.shareRecord) return this._getShareConfig();
		return { title: this.shareRecord.title, query: this.goodsSharePath().split('?')[1], imageUrl: this.shareCover || '' };
	},
	methods: {
		goodsShareUserId() {
			if (!this.$http.isLogin()) return 0;
			const user = this.$store.state.user.userInfo || uni.getStorageSync('USER_INFO') || {};
			return Number(user.id) || 0;
		},
		goodsSharePath() {
			// 新登录流程写 USER_INFO，不能用旧 USER 缓存误带上一位用户的 pid。
			return withPid('/others/goods-share/detail?token=' + this.shareRecord.token + '&mall_id=' + getMallId() + '&pid=' + this.goodsShareUserId());
		},
		prepareGoodsShare(record) {
			this.shareRecord = record;
			this.shareCover = '';
			const token = record.token;
			const cover = ((record.items || [])[0] || {}).cover_pic || '';
			fitShareImage(cover).then(path => {
				if (this.shareRecord && this.shareRecord.token === token) this.shareCover = path;
			});
			// #ifdef H5
			if (this.$wechatSdk) {
				this.$wechatSdk.share({ route: this.goodsSharePath(), pid: this.goodsShareUserId() }, { share_title: record.title, share_desc: record.note || '选了这些好物，分享给你看看。', share_pic: cover });
			}
			// #endif
		},
		copyGoodsShare() {
			if (!this.shareRecord) return;
			let url = '';
			// #ifdef H5
			url = buildShareUrl({ href: window.location.href, curRoute: this.goodsSharePath(), mallId: getMallId(), pid: this.goodsShareUserId() });
			// #endif
			// #ifdef APP-PLUS
			url = this.$setting && this.$setting.h5_url ? this.$setting.h5_url.split('#')[0] + '#' + this.goodsSharePath() : '';
			// #endif
			if (!url) return this.$util.toast('请通过微信小程序分享给好友');
			uni.setClipboardData({ data: url, success: () => this.$util.toast('链接已复制，发给好友吧') });
		}
	}
};
