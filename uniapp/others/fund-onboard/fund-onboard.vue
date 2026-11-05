<template>
	<tui-page>
		<!-- 
			[核心修复] 显式绑定 --theme-color 变量 
			确保小程序内 slot 内容能读取到主题色
		-->
		<view class="page-container" v-if="!pageLoading" :style="{ '--theme-color': themeColor }">

			<view class="form-card">
				<!-- 遍历后端返回的字段配置 -->
				<view v-for="(field, index) in fields" :key="field.key" class="form-group"
					:class="{'no-border': index === fields.length - 1}">

					<view class="label" :class="{'required': field.required}">{{ field.label }}</view>
					<view class="field-wrapper">

						<!-- 1. 普通文本/数字/身份证输入框 -->
						<input v-if="isTextInput(field.type)" class="input" :type="getInputType(field.type)"
							:value="formData[field.key]" @input="onFormChange($event, field.key)"
							:placeholder="field.placeholder || `请输入${field.label}`" placeholder-class="placeholder"
							:disabled="isFormDisabled || field.readonly" :class="{'input-disabled': field.readonly}" />

						<!-- 2. 图片上传 -->
						<view v-else-if="field.type === 'image'" class="image-uploader-wrapper">
							<tui-upload-single-image v-model="formData[field.key]"></tui-upload-single-image>

						</view>

						<!-- 3. 开户银行搜索选择器 -->
						<view v-else-if="field.type === 'bank_picker'" class="picker-wrapper"
							@click="openSearchPopup('bank')">
							<text class="input" :class="{'placeholder': !formData.bank_name}">
								{{ formData.bank_name || field.placeholder }}
							</text>
							<tui-icon name="arrowright" :size="20" color="#C0C4CC"></tui-icon>
						</view>


						<!-- 4. 联行号(支行)搜索选择器 -->
						<view v-else-if="field.type === 'bank_branch_picker'" class="picker-wrapper"
							@click="openSearchPopup('branch')">
							<text class="input" :class="{'placeholder': !formData.bank_branch_no}">
								<!-- 优先显示名称，没有名称显示号码 -->
								{{ formData.bank_branch_name || formData.bank_branch_no || field.placeholder }}
							</text>
							<tui-icon name="arrowright" :size="20" color="#C0C4CC"></tui-icon>
						</view>

						<!-- 5. 普通下拉选择 (如账户类型) -->
						<picker style="width: 100%;" v-else-if="field.type === 'select'"
							:value="getPickerIndex(field.key)" :range="field.options" range-key="label"
							@change="onPickerChange($event, field.key)" :disabled="isFormDisabled">
							<view class="picker-wrapper">
								<text class="input" :class="{'placeholder': !formData[field.key]}">
									{{ getPickerLabel(field.key) || field.placeholder }}
								</text>
								<tui-icon name="arrowright" :size="20" color="#C0C4CC"></tui-icon>
							</view>
						</picker>

					</view>
					<!-- 字段备注 -->
					<view v-if="field.note" class="field-note">{{ field.note }}</view>
				</view>

				<!-- 空状态提示 -->
				<view v-if="fields.length === 0 && !pageLoading" class="empty-tip">
					当前无需填写信息，或平台暂未配置该渠道。
				</view>
			</view>

			<!-- 认证状态展示卡片 -->
			<view class="status-card" v-if="currentStatus && currentStatus.status !== 'none'">
				<view class="status-header">
					<!-- [核心] 图标颜色跟随主题 -->
					<tui-icon name="version" :size="20" :color="themeColor"></tui-icon>
					<text class="status-title">认证状态</text>
				</view>
				<!-- 审核中 -->
				<view class="status-item"
					v-if="currentStatus.status === 'pending' || currentStatus.status === 'pending_verify'">
					<view class="status-label">当前状态</view>
					<view class="status-value warning">审核中</view>
					<view class="status-remark">{{ currentStatus.message }}</view>
				</view>
				<!-- 失败 -->
				<view class="status-item column-layout" v-if="currentStatus.status === 'failed'">
					<view class="status-label">当前状态</view>
					<view class="status-value error">认证失败</view>
					<view class="status-remark error">{{ currentStatus.message }}</view>
				</view>
				<!-- 成功 -->
				<view class="status-item" v-if="currentStatus.status === 'success'">
					<view class="status-label">当前状态</view>
					<view class="status-value success">已认证</view>
					<view class="status-remark" v-if="currentStatus.message">{{ currentStatus.message }}</view>
				</view>
			</view>

			<!-- 短信验证码输入框 (特定状态下显示) -->
			<view class="form-card" v-if="showSmsInput">
				<view class="form-group no-border">
					<view class="label required">短信验证码</view>
					<view class="field-wrapper verify-form">
						<input class="input" v-model="smsCode" placeholder="请输入短信验证码" placeholder-class="placeholder" />
						<button class="action-btn small" @click="submitVerification"
							:loading="isVerifying">确认提交</button>
					</view>
				</view>
			</view>

			<!-- 底部提交按钮 -->
			<view class="submit-btn-wrapper" v-if="!isFormDisabled">
				<button class="submit-btn" @click="saveInfo" :loading="isSaving">{{ getButtonText() }}</button>
			</view>
			<!-- 成功后的提示 -->
			<view class="success-tip" v-if="isFormDisabled">
				您的账户已认证成功，可以正常{{ $noun('withdraw') }}。如需修改，请联系客服。
			</view>

		</view>
		<!-- 全局 Loading -->
		<tui-loading v-if="pageLoading" text="加载中..." :is-fixed="true"></tui-loading>

		<!-- 通用搜索弹窗 (银行/联行号) -->
		<uni-popup ref="searchPopup" type="bottom" background-color="#fff">
			<view class="popup-container">
				<view class="popup-header">
					<text>{{ popupTitle }}</text>
					<tui-icon name="close" :size="18" color="#999" @click="closeSearchPopup"></tui-icon>
				</view>
				<view class="search-bar">
					<tui-icon name="search" :size="16" color="#999" style="margin-right:12rpx;"></tui-icon>
					<input
						class="search-input"
						v-model="searchKeyword"
						:placeholder="searchPlaceholder"
						placeholder-class="placeholder"
						:adjust-position="true"
						:cursor-spacing="20"
						confirm-type="search"
						@input="onSearchInput"
						@confirm="handleSearch" />
					<view v-if="searchKeyword" class="clear-btn" @click="clearKeyword">
						<tui-icon name="close" :size="14" color="#fff"></tui-icon>
					</view>
				</view>
				<scroll-view scroll-y="true" class="search-results">
					<view v-if="searchLoading" class="empty-search">搜索中...</view>
					<view v-else-if="searchResults.length === 0" class="empty-search">
						{{ emptySearchTip }}
					</view>

					<!-- 搜索结果列表 -->
					<block v-for="(item, index) in searchResults" :key="index">

						<!-- 1. 银行搜索结果模板 -->
						<view v-if="searchType === 'bank'" class="result-item" @click="handleSelectResult(item)">
							{{ item.bankName }}
						</view>

						<!-- 2. 支行/联行号搜索结果模板 -->
						<view v-if="searchType === 'branch'" class="result-item" @click="handleSelectResult(item)">
							<view class="branch-name">{{ item.bankBranchName }}</view>
							<view class="branch-code">联行号: {{ item.bankBranchCode }}</view>
						</view>

					</block>
				</scroll-view>
			</view>
		</uni-popup>

	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import {
		mapState
	} from 'vuex';

	export default {
		data() {
			return {
				pageLoading: true,
				isSaving: false,
				isVerifying: false,
				fields: [], // 动态表单字段定义
				formData: {}, // 表单填写的数据
				onboardingStatus: {}, // 进件状态数据
				smsCode: '',

				// --- 渠道控制 ---
				currentChannel: 'bank', // 默认为 bank (银行卡)——仅用于页面 UX 语义(标题/银行卡专属校验)
				// 精确网关类名(如 app\funds\FastPayFlexibleLaborFund)。提现渠道按 fund_class 区分,
				// destination 可能都是 bank;传它才能让进件落到正确的网关、状态按类隔离(不被银行卡的 success 锁死)。
				fundClass: '',
				pageTitle: '结算认证',

				// --- 搜索弹窗相关状态 ---
				searchType: 'bank', // 当前搜索类型: 'bank' (银行) | 'branch' (支行/联行号)
				searchLoading: false,
				searchKeyword: '',
				searchResults: [],
			}
		},
		computed: {
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),

			// 计算当前的进件状态 (pending/success/failed)
			currentStatus() {
				const data = this.onboardingStatus;
				if (!data) return {
					status: 'none',
					message: ''
				};

				// 【情况1】如果数据本身直接包含 status 字段（即你提供的报错数据结构）
				if (data.status && typeof data.status === 'string') {
					return data;
				}

				// 【情况2】如果数据是对象集合（你需要遍历子对象）
				// 例如：{ step1: {status:'success'}, step2: {status:'failed'} }
				const values = Object.values(data);

				// 过滤掉 null 或非对象的数据，防止报错
				const validItems = values.filter(item => item && typeof item === 'object' && item.status);

				// 优先显示 处理中 或 失败
				const pendingOrFailed = validItems.find(s => [
					'pending',
					'pending_verify',
					'failed'
				].includes(s.status));
				if (pendingOrFailed) return pendingOrFailed;

				// 其次显示 成功
				const success = validItems.find(s => s.status === 'success');
				if (success) return success;

				return {
					status: 'none',
					message: ''
				};
			},
			// 认证成功后禁用表单
			isFormDisabled() {
				return this.currentStatus.status === 'success';
			},
			// 是否显示短信验证码输入框
			showSmsInput() {
				return this.currentStatus.status === 'pending_verify';
			},
			// 弹窗标题动态计算
			popupTitle() {
				return this.searchType === 'bank' ? '搜索开户银行' : '搜索支行/联行号';
			},
			// 搜索框提示语动态计算
			searchPlaceholder() {
				return this.searchType === 'bank' ? '请输入银行名称关键字(如:工商)' : '请输入支行名称关键字(如:珠海分行)';
			},

			/**
			 * 结果为空时的文案。
			 * ⚠ 原来银行渠道在无关键字时写死「加载中...」,而那时后端对空关键字直接返错、
			 * 列表永远为空 —— 用户看到一个永不消失的「加载中…」,像卡死。
			 * 另外原支行示例写的是「佛山招商」,但 branch_name 是「银行名+地区+支行」的顺序,
			 * 单串 LIKE 要求顺序一致,这个示例实测 0 命中 —— 照着提示输反而搜不到。
			 */
			emptySearchTip() {
				if (this.searchKeyword) {
					return this.searchType === 'branch' && this.formData.bank_name
						? `在「${this.formData.bank_name}」下未找到相关支行，换个关键字试试`
						: '未搜索到相关信息，换个关键字试试';
				}
				if (this.searchType === 'bank') {
					return '暂无可选银行，请输入关键字搜索';
				}
				return this.formData.bank_name
					? `请输入支行所在地或名称（如：顺德、天河），将在「${this.formData.bank_name}」下搜索`
					: '请先返回选择开户银行，再搜索支行';
			}
		},
		onLoad(options) {
			// 1. 接收 channel 参数(语义:bank/alipay/wechat)
			if (options.channel) {
				this.currentChannel = options.channel;
			}
			// 1.1 精确网关类名(提现渠道传入),决定进件真正落地的 fund_class
			if (options.fund_class) {
				this.fundClass = decodeURIComponent(options.fund_class);
			}

			// 2. 动态设置页面标题:优先用调用方传入的渠道名(如"灵工"),否则按 channel 语义兜底
			const titleMap = {
				'alipay': '支付宝设置',
				'bank': '银行卡设置',
				'wechat': '微信' + this.$noun('withdraw') + '设置'
			};
			const passedTitle = options.title ? decodeURIComponent(options.title) : '';
			if (passedTitle || titleMap[this.currentChannel]) {
				this.pageTitle = passedTitle || titleMap[this.currentChannel];
				uni.setNavigationBarTitle({
					title: this.pageTitle
				});
			}

			this.loadInitialData();
		},
		methods: {
			// --- 表单基础交互 ---

			// 文本输入变化
			onFormChange(event, key) {
				this.$set(this.formData, key, event.target.value);
			},
			// Picker 选择变化
			onPickerChange(event, key) {
				const field = this.fields.find(f => f.key === key);
				if (field && field.options) {
					const selectedOption = field.options[event.detail.value];
					if (selectedOption) {
						this.$set(this.formData, key, selectedOption.value);
					}
				}
			},
			// 获取 Picker 当前选中索引
			getPickerIndex(key) {
				const field = this.fields.find(f => f.key === key);
				if (field && field.options && this.formData[key]) {
					return field.options.findIndex(opt => opt.value === this.formData[key]);
				}
				return 0;
			},
			// 获取 Picker 当前显示的文字
			getPickerLabel(key) {
				const field = this.fields.find(f => f.key === key);
				if (field && field.options && this.formData[key]) {
					const option = field.options.find(opt => opt.value === this.formData[key]);
					return option ? option.label : '';
				}
				return '';
			},
			// 判断是否为文本类输入
			isTextInput(type) {
				return ['text', 'id_card', 'number'].includes(type);
			},
			// 转换 input type
			getInputType(type) {
				if (type === 'id_card') return 'idcard';
				if (type === 'number') return 'number';
				return 'text';
			},

			// --- 数据加载与提交 ---

			// 加载初始表单配置和已有数据
			async loadInitialData() {
				this.pageLoading = true;
				try {
					const res = await this.$request({
						url: '/api/fund/onboarding-form',
						// 传参：channel 语义兜底 + fund_class 精确指定网关(提现渠道必带)
						data: {
							channel: this.currentChannel,
							fund_class: this.fundClass
						}
					});
					if (res.code === 0 && res.data) {
						this.fields = res.data.fields || [];
						this.onboardingStatus = res.data.onboarding_status || {};

						const info = res.data.info || {};
						let settlementData = {};

						// 兼容性处理：如果后端返回了老格式的 settlement_data
						if (info.settlement_data) {
							if (typeof info.settlement_data === 'string') {
								try {
									settlementData = JSON.parse(info.settlement_data);
								} catch (e) {}
							} else if (typeof info.settlement_data === 'object') {
								settlementData = info.settlement_data;
							}
						}

						// 合并数据到 formData
						const initialForm = {};
						this.fields.forEach(field => {
							const key = field.key;
							// 优先取 info[key]，其次取 settlementData[key]
							initialForm[key] = info[key] !== undefined && info[key] !== null ? info[key] : (
								settlementData[key] || '');
						});

						this.formData = initialForm;
					} else {
						this.$util.toast(res.msg || '加载信息失败');
					}
				} catch (e) {
					console.error("Load initial data error:", e);
					this.$util.toast("网络错误，加载失败");
				} finally {
					this.pageLoading = false;
				}
			},

			// 表单校验
			validate() {
				for (const field of this.fields) {
					// 必填校验
					if (field.required && (!this.formData[field.key] || String(this.formData[field.key]).trim() === '')) {
						this.$util.toast(field.placeholder || `请输入${field.label}`);
						return false;
					}
				}

				// ── 动态校验：银行卡专属 ──────────────────────────────
				if (this.currentChannel === 'bank') {
					// 银行卡号长度（12-19 位数字，覆盖储蓄卡/借记卡/对公账号）
					if (this.formData.bank_card_no && !/^\d{12,19}$/.test(this.formData.bank_card_no)) {
						this.$util.toast('银行卡号格式不正确（应为 12-19 位数字）');
						return false;
					}

					// 联行号格式（如有填写，必须 12 位数字）
					if (this.formData.bank_branch_no && !/^\d{12}$/.test(this.formData.bank_branch_no)) {
						this.$util.toast('联行号格式不正确（应为 12 位数字）');
						return false;
					}

					// 对公账户专属校验
					if (this.formData.account_type === 'PUBLIC') {
						if (!this.formData.identity_no) {
							this.$util.toast('对公账户必须填写法人身份证号');
							return false;
						}
						if (!this.formData.bank_branch_name && !this.formData.bank_branch_no) {
							this.$util.toast('对公账户必须选择开户支行');
							return false;
						}
						if (!this.formData.use_desc) {
							this.$util.toast('对公账户必须选择收款用途');
							return false;
						}
					}
				}

				// 身份证格式校验（如有填写）
				if (this.formData.identity_no &&
					!/^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[\dX]$/i.test(this.formData.identity_no)) {
					this.$util.toast('请输入有效的身份证号码');
					return false;
				}

				// 手机号校验（如有填写）
				if (this.formData.mobile && !/^1\d{10}$/.test(this.formData.mobile)) {
					this.$util.toast('请输入有效的手机号码');
					return false;
				}
				return true;
			},

			// 提交保存
			async saveInfo() {
				if (!this.validate()) return;
				this.isSaving = true;
				try {
					// 传参：合并 channel + fund_class 到 postData 中(fund_class 决定落到哪个网关、状态记在哪个类下)
					const postData = {
						...this.formData,
						channel: this.currentChannel,
						fund_class: this.fundClass
					};

					const res = await this.$request({
						url: '/api/fund/settlement',
						method: 'POST',
						data: postData
					});
					this.$util.toast(res.msg || (res.code === 0 ? '保存成功' : '保存失败'));
					if (res.code === 0) {
						await this.loadInitialData(); // 刷新状态
						// 已完全认证成功（无需短信验证）才自动返回，审核中/待验证/失败则停留本页让用户看到状态
						if (this.currentStatus.status === 'success') {
							setTimeout(() => uni.navigateBack(), 1500);
						}
					}
				} catch (e) {
					this.$util.toast("网络错误，保存失败");
				} finally {
					this.isSaving = false;
				}
			},

			// 提交短信验证码 (若需要)
			async submitVerification() {
				if (!this.smsCode) {
					this.$util.toast("请输入短信验证码");
					return;
				}
				this.isVerifying = true;
				try {
					const res = await this.$request({
						url: '/api/fund/settlement-verify',
						method: 'POST',
						data: {
							sms_code: this.smsCode,
							channel: this.currentChannel, // 语义渠道
							fund_class: this.fundClass    // 精确网关,多渠道待验证时定位
						}
					});
					this.$util.toast(res.msg || (res.code === 0 ? '验证成功' : '验证失败'));
					if (res.code === 0) {
						this.smsCode = '';
						await this.loadInitialData();
					}
				} catch (e) {
					this.$util.toast("网络错误，验证失败");
				} finally {
					this.isVerifying = false;
				}
			},

			// 获取提交按钮文案
			getButtonText() {
				if (this.currentStatus.status === 'failed') {
					return '重新提交认证';
				}
				return '保存并提交认证';
			},

			// --- 搜索弹窗逻辑 (银行 & 支行) ---

			// 1. 打开弹窗，设置模式
			//    - 'bank'：进入弹窗即拉取默认银行列表，用户可直接挑选；输入时实时过滤
			//    - 'branch'：进弹窗时列表为空，需用户输入关键字才搜索
			openSearchPopup(type) {
				if (this.isFormDisabled) return;

				this.searchType = type;
				this.searchKeyword = '';
				this.searchResults = [];

				// 支行搜索现在是「按已选银行过滤」的,没选银行就会退化成全库匹配、
				// 结果横跨所有银行且被 limit 截断 —— 提示语要说清后果,不能只是"建议"
				if (type === 'branch' && !this.formData.bank_name) {
					this.$util.toast('请先选择开户银行，否则支行结果会混入其他银行');
				}

				this.$refs.searchPopup.open();

				// 银行渠道：打开弹窗立即拉取默认列表（后端关键字为空时返回前 30 个）
				if (type === 'bank') {
					this.$nextTick(() => this.handleSearch());
				}
			},

			// 2. 关闭弹窗
			closeSearchPopup() {
				if (this._searchTimer) clearTimeout(this._searchTimer);
				this.$refs.searchPopup.close();
			},

			// 输入框清空按钮
			clearKeyword() {
				this.searchKeyword = '';
				this.onSearchInput();
			},

			// 输入触发实时搜索（300ms 防抖）
			onSearchInput() {
				if (this._searchTimer) clearTimeout(this._searchTimer);
				this._searchTimer = setTimeout(() => {
					this.handleSearch();
				}, 300);
			},

			// 3. 执行搜索（支持空关键字：bank 渠道空时拉默认列表；branch 渠道空时清空结果）
			async handleSearch() {
				const keyword = String(this.searchKeyword || '').trim();

				// 支行渠道必须有关键字才搜（避免一次返回 10w+ 数据）
				if (this.searchType === 'branch' && keyword === '') {
					this.searchResults = [];
					return;
				}

				this.searchLoading = true;
				try {
					const url = this.searchType === 'bank' ?
						'/api/fund/query-bank-code' :
						'/api/fund/query-bank-info-branch';

					// 支行搜索必须把已选开户银行一起带上,否则结果横跨所有银行:
					// 实测「选招商 + 输顺德」命中 154 条,招商那条排第 119 位、被后端 limit(100) 截掉,
					// 用户根本翻不到自己的支行。银行搜索本身不需要这个参数,后端 andFilterWhere 会忽略空值。
					const res = await this.$request({
						url: url,
						method: 'GET',
						data: {
							bankName: keyword,
							bank_name: this.searchType === 'branch' ? (this.formData.bank_name || '') : ''
						}
					});

					if (res.code === 0) {
						this.searchResults = res.data.list || res.data.branchList || [];
					} else {
						this.searchResults = [];
						// 仅在用户主动确认时弹错误提示，实时输入失败静默处理
					}
				} catch (e) {
					console.error('Search error:', e);
					this.searchResults = [];
				} finally {
					this.searchLoading = false;
				}
			},

			// 4. 选中结果处理
			handleSelectResult(item) {
				if (this.searchType === 'bank') {
					// --- 选中银行 ---
					this.$set(this.formData, 'bank_name', item.bankName);
					this.$set(this.formData, 'bank_code', item.bankCode);

					// 切换银行时，为了数据准确，清空已选的支行信息
					this.$set(this.formData, 'bank_branch_no', '');
					this.$set(this.formData, 'bank_branch_name', '');

				} else {
					// --- 选中支行/联行号 ---
					// item 结构: { bankBranchCode, bankBranchName }
					this.$set(this.formData, 'bank_branch_no', item.bankBranchCode);
					this.$set(this.formData, 'bank_branch_name', item.bankBranchName);
				}
				this.closeSearchPopup();
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 将 SCSS 变量指向 tui-page 注入的 CSS 变量 */
	$theme-color: var(--theme-color);

	/* 页面容器 */
	.page-container {
		padding: 24rpx;
		padding-bottom: 160rpx;
	}

	/* 表单卡片 */
	.form-card {
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		padding: 0 32rpx;
	}

	.form-group {
		padding: 32rpx 0;
		border-bottom: 1rpx solid #EBEDF0;
	}

	.form-group:last-child,
	.form-group.no-border {
		border-bottom: none;
	}

	.label {
		font-size: 28rpx;
		color: #333;
		margin-bottom: 20rpx;
		font-weight: 500;
	}

	.label.required::before {
		content: '*';
		color: #E43D3D;
		margin-right: 4rpx;
	}

	.field-wrapper {
		display: flex;
		align-items: center;
		background-color: #F7F8FA;
		border-radius: 8rpx;
		min-height: 88rpx;
		box-sizing: border-box;
		padding: 0 24rpx;
	}

	.input {
		flex: 1;
		font-size: 28rpx;
		height: 88rpx;
		line-height: 88rpx;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.input-disabled {
		color: #999;
		background-color: #f0f0f0;
	}

	.placeholder {
		color: #C0C4CC;
	}

	.field-note {
		font-size: 24rpx;
		color: #999;
		margin-top: 12rpx;
	}

	/* Picker 样式 */
	.picker-wrapper {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		height: 88rpx;
	}

	.picker-wrapper .input {
		height: 88rpx;
		line-height: 88rpx;
		width: 100%;
	}

	/* 图片上传 */
	.image-uploader-wrapper {
		padding: 20rpx 0;
	}



	/* 底部按钮 */
	.submit-btn-wrapper {
		padding: 20rpx 0;
		margin-top: 20rpx;
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background-color: #F5F6FA;
		box-sizing: border-box;
		padding: 20rpx 24rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		z-index: 10;
	}

	.submit-btn {
		/* [核心] 按钮背景色 */
		background: $theme-color;
		color: #fff;
		height: 96rpx;
		line-height: 96rpx;
		border-radius: 48rpx;
		font-size: 32rpx;
		border: none;

		&:after {
			border: none;
		}
	}

	.success-tip {
		text-align: center;
		font-size: 26rpx;
		color: #999;
		padding: 40rpx 0;
	}

	.empty-tip {
		text-align: center;
		color: #999;
		font-size: 28rpx;
		padding: 60rpx 0;
	}

	/* 状态卡片 */
	.status-card {
		background-color: #fff;
		border-radius: 16rpx;
		padding: 32rpx;
		margin-bottom: 24rpx;
	}

	.status-header {
		display: flex;
		align-items: center;
		padding-bottom: 24rpx;
		border-bottom: 1rpx solid #EBEDF0;
		margin-bottom: 24rpx;
	}

	.status-title {
		font-size: 30rpx;
		font-weight: bold;
		color: #333;
		margin-left: 16rpx;
	}

	.status-item {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		font-size: 28rpx;
		margin-bottom: 20rpx;
		flex-wrap: wrap;
	}

	.status-item:last-child {
		margin-bottom: 0;
	}

	.status-item.column-layout {
		flex-direction: column;
		align-items: flex-start;

		.status-value {
			margin-top: 8rpx;
		}
	}

	.status-label {
		color: #666;
		flex-shrink: 0;
		margin-right: 20rpx;
	}

	.status-value {
		font-weight: 500;
		text-align: right;

		&.success {
			color: #07c160;
		}

		&.error {
			color: #fa5151;
		}

		&.warning {
			color: #ff9900;
		}
	}

	.status-remark {
		font-size: 24rpx;
		color: #999;
		width: 100%;
		margin-top: 8rpx;
		word-break: break-all;

		&.error {
			color: #fa5151;
		}
	}

	/* 验证码输入框 */
	.verify-form {
		padding: 0 !important;
		display: flex;
		align-items: center;
		background-color: transparent;
	}

	.verify-form .input {
		background-color: #F7F8FA;
		border-radius: 8rpx;
		padding: 0 24rpx;
		box-sizing: border-box;
	}

	.action-btn.small {
		/* [核心] 验证码按钮背景色 */
		background-color: $theme-color !important;
		color: #fff !important;
		height: 88rpx;
		line-height: 88rpx;
		font-size: 28rpx;
		padding: 0 30rpx;
		margin-left: 20rpx;
		border-radius: 8rpx;
		flex-shrink: 0;
		border: none;

		&:after {
			border: none;
		}
	}

	/* --- 搜索弹窗样式 --- */
	.popup-container {
		border-radius: 16rpx 16rpx 0 0;
		padding: 32rpx;
		box-sizing: border-box;
		background-color: #fff;
	}

	.popup-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		margin-bottom: 30rpx;
	}

	.search-bar {
		display: flex;
		align-items: center;
		margin-bottom: 30rpx;
		background-color: #f5f5f5;
		border-radius: 40rpx;
		padding: 0 24rpx;
		height: 80rpx;
	}

	.search-input {
		flex: 1;
		height: 80rpx;
		font-size: 28rpx;
		background-color: transparent;
		border: none;
	}

	.search-input .placeholder {
		color: #C0C4CC;
		font-size: 26rpx;
	}

	.clear-btn {
		width: 40rpx;
		height: 40rpx;
		border-radius: 50%;
		background: #C0C4CC;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-left: 12rpx;
		flex-shrink: 0;
	}
	.clear-btn:active {
		opacity: 0.7;
	}

	.search-results {
		height: 50vh;
		/* 限制最大高度 */
	}

	.empty-search {
		text-align: center;
		color: #999;
		padding-top: 100rpx;
	}

	.result-item {
		padding: 24rpx 0;
		font-size: 30rpx;
		color: #333;
		border-bottom: 1rpx solid #f0f0f0;
	}

	.result-item:last-child {
		border-bottom: none;
	}

	/* 支行/联行号专属样式 */
	.branch-name {
		font-size: 30rpx;
		color: #333;
		margin-bottom: 6rpx;
		font-weight: 500;
	}

	.branch-code {
		font-size: 24rpx;
		color: #999;
	}
</style>