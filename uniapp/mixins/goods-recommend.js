import { getMallId } from '@/utils/request.js';

/** 个人中心各主题及商品详情共用推荐请求；个性化结果只保存在当前组件。 */
export default {
	data() {
		return { page: 1, list: [], is_no_more: false, loadding: false, pullUpOn: true, recommendError: false };
	},
	computed: {
		recommendIdentity() {
			const user = this.$store.state.user;
			return getMallId() + ':' + (user.token ? Number((user.userInfo || {}).id) || -1 : 0);
		}
	},
	created() {
		this.getRecommend(true);
	},
	watch: {
		recommendIdentity() {
			// 换号/退出立即清理，旧请求回包也不能覆盖新用户结果。
			this._recommendGeneration = (this._recommendGeneration || 0) + 1;
			this.loadding = false;
			this.list = [];
			this.getRecommend(true);
		}
	},
	methods: {
		getRecommend(isRefresh = false) {
			if (this.loadding || (this.is_no_more && !isRefresh)) return Promise.resolve();
			if (isRefresh) {
				this.page = 1;
				this.is_no_more = false;
				this.pullUpOn = true;
			}
			const page = this.page;
			const identity = this.recommendIdentity;
			const generation = this._recommendGeneration || 0;
			this.loadding = true;
			this.recommendError = false;
			return this.$request({
				url: '/api/goods/recommend-list',
				data: { page, limit: 10, exclude_id: Number(this.excludeId) || 0 }
			}).then(res => {
				if (identity !== this.recommendIdentity || generation !== (this._recommendGeneration || 0)) return;
				if (res.code !== 0) { this.recommendError = true; return; }
				const incoming = res.data.list || [];
				// 浏览顺序会随用户打开商品变化，追加时仍须按商品ID去重。
				const seen = new Set();
				this.list = (page === 1 ? incoming : this.list.concat(incoming)).filter(item => {
					if (seen.has(item.id)) return false;
					seen.add(item.id);
					return true;
				});
				this.is_no_more = page >= res.data.pagination.page_count;
				this.pullUpOn = !this.is_no_more;
				if (!this.is_no_more) this.page = page + 1;
			}).catch(() => {
				if (generation === (this._recommendGeneration || 0)) this.recommendError = true;
			}).finally(() => {
				if (generation === (this._recommendGeneration || 0)) this.loadding = false;
			});
		}
	}
};
