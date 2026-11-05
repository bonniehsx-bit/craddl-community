<template>
	<tui-page title="商品发布" pageBgColor="#F5F6FA">
		<view class="page-container" :style="themeStyle">
				<!-- 平台商品提示 -->
				<view class="platform-good-tip" v-if="form.platform_goods_id > 0">
					<tui-icon name="info-circle" color="#007aff" :size="16"></tui-icon>
					<text>该商品为平台采购商品，部分信息不可修改。</text>
				</view>

				<!-- 基本信息 -->
				<view class="form-card">
					<view class="card-title">基本信息</view>
					<view class="form-item">
						<text class="spec-label"><text class="req-star">*</text>商品分类</text>
						<picker class="input align-right" style="text-align: end;" mode="multiSelector"
							:range="multiCategoryArray" range-key="name" @change="onCategoryChange"
							@columnchange="onCategoryColumnChange" :value="multiCategoryIndex">
							<view class="picker-display-wrapper">
								<view class="picker-display" :class="{ 'placeholder': !form.cat_id }">
									{{ selectedCategoryName || '请选择商品分类(必填)' }}
								</view>
								<tui-icon name="arrowright" color="#ccc" :size="20"></tui-icon>
							</view>
						</picker>
					</view>
					<view class="form-item" @click="showMchCatPopup = true" v-if="!form.platform_goods_id">
						<text class="spec-label">店铺分类</text>
						<view class="input align-right picker-display-wrapper" style="justify-content: flex-end;">
							<view class="picker-display" :class="{ 'placeholder': !form.mch_cats || form.mch_cats.length === 0 }">
								{{ selectedMchCatNames || '请选择店铺分类(可选)' }}
							</view>
							<tui-icon name="arrowright" color="#ccc" :size="20"></tui-icon>
						</view>
					</view>
					<view class="form-item">
						<text class="spec-label"><text class="req-star">*</text>名称</text>
						<input class="input align-right" v-model="form.name" placeholder="名称"
							placeholder-class="placeholder" />
					</view>
					<view class="form-item">
						<text class="spec-label">单位</text>
						<input class="input align-right" v-model="form.unit" placeholder="单位"
							placeholder-class="placeholder" />
					</view>

					<view class="form-item">
						<text class="spec-label"><text class="req-star" v-if="form.is_attr != 1">*</text>价格</text>
						<input class="input align-right" type="digit" v-model="form.price"
							:placeholder="form.is_attr == 1 ? '多规格以下方规格价为准' : '商品价格'"
							placeholder-class="placeholder"
							:disabled="form.is_attr == 1 || form.platform_goods_id > 0" />
					</view>
					<view class="form-item">
						<text class="spec-label">原价</text>
						<input class="input align-right" type="digit" v-model="form.origin_price" placeholder="商品原价"
							placeholder-class="placeholder" />
					</view>
					<view class="form-item">
						<text class="spec-label">成本价格</text>
						<input class="input align-right" type="digit" v-model="form.cost_price" placeholder="成本价格"
							placeholder-class="placeholder" />
					</view>
					<view class="form-item">
						<text class="spec-label"><text class="req-star">*</text>库存</text>
						<input class="input align-right" type="number" v-model="form.stock" placeholder="所有规格库存之和"
							placeholder-class="placeholder"
							:disabled="form.is_attr == 1 || form.platform_goods_id > 0" />
					</view>
				</view>

				<!-- 分润配置已移除 -->

				<!-- 媒体资料 -->
				<view class="form-card">
					<view class="card-title">媒体资料</view>
					<view class="upload-group">
						<view class="upload-label">
							<text class="req-star">*</text>商品封面图
							<text class="label-tip">(列表页展示的主图)</text>
						</view>
						<tui-upload-single-image v-model="form.cover_pic" width="200rpx" height="200rpx">
							<view class="upload-placeholder">
								<tui-icon name="camera" color="#ccc" :size="32"></tui-icon>
								<view class="upload-text">上传</view>
							</view>
						</tui-upload-single-image>
					</view>
					<view class="upload-group">
						<view class="upload-label">
							<text class="req-star">*</text>商品轮播图
							<text class="label-tip">(详情页轮播，可传多张)</text>
						</view>
						<tui-upload v-model="form.pic_list" :limit="9" width="200rpx" height="200rpx"
							:is-upload-remote="true">
							<template v-slot:add>
								<view class="upload-placeholder">
									<tui-icon name="camera" color="#ccc" :size="32"></tui-icon>
									<view class="upload-text">上传</view>
								</view>
							</template>
						</tui-upload>
					</view>
					<view class="upload-group">
						<view class="upload-label">
							商品详情图
							<text class="label-tip">(可传多张，将按顺序显示)</text>
						</view>
						<tui-upload v-model="form.detail_pics" :limit="20" width="200rpx" height="200rpx"
							:is-upload-remote="true">
							<template v-slot:add>
								<view class="upload-placeholder">
									<tui-icon name="camera" color="#ccc" :size="32"></tui-icon>
									<view class="upload-text">上传</view>
								</view>
							</template>
						</tui-upload>
					</view>
				</view>
				<!-- ... -->
				<view class="form-card">
					<view class="card-title">物流设置</view>
					<view class="form-item">
						<text class="spec-label">运费设置</text>
						<radio-group class="radio-group" @change="onExpressTypeChange">
							<label class="radio-item">
								<radio value="0" :checked="form.express_type == 0" color="var(--theme-color)"
									style="transform:scale(0.8)" /> 包邮
							</label>
							<label class="radio-item">
								<radio value="1" :checked="form.express_type == 1" color="var(--theme-color)"
									style="transform:scale(0.8)" /> 统一运费
							</label>
							<label class="radio-item">
								<radio value="2" :checked="form.express_type == 2" color="var(--theme-color)"
									style="transform:scale(0.8)" /> 运费模板
							</label>
						</radio-group>
					</view>

					<view class="form-item" v-if="form.express_type == 1">
						<text class="spec-label">运费金额</text>
						<input class="input align-right" type="digit" v-model="form.freight_price" placeholder="0.00"
							placeholder-class="placeholder" />
						<text class="unit-suffix">元</text>
					</view>

					<view class="form-item" v-if="form.express_type == 2">
						<text class="spec-label">选择模板</text>
						<picker v-if="freightTplList.length" class="input align-right" @change="onFreightTplChange" :value="freightTplIndex"
							:range="freightTplList" range-key="name">
							<view class="picker-display" :class="{ 'placeholder': !form.freight_id }">
								{{ (freightTplList[freightTplIndex] && freightTplList[freightTplIndex].name) ||
								'请选择运费模板' }}
							</view>
						</picker>
						<!-- 商家还没配过运费模板:给个去配置的入口,别让它成死胡同 -->
						<view v-else class="input align-right freight-empty" @click="goFreightSetting">去配置运费模板 ›</view>
					</view>

				</view>
				<!-- 规格设置 -->
				<view class="form-card">
					<view class="card-title">规格设置</view>
					<view class="form-item">
						<text class="spec-label">是否多规格</text>
						<radio-group class="radio-group" @change="onSpecTypeChange"
							:disabled="form.platform_goods_id > 0">
							<label class="radio-item" :class="{ 'disabled': form.platform_goods_id > 0 }">
								<radio value="0" :checked="form.is_attr == 0" color="var(--theme-color)"
									style="transform: scale(0.8);" :disabled="form.platform_goods_id > 0" />
								<text>否</text>
							</label>
							<label class="radio-item" :class="{ 'disabled': form.platform_goods_id > 0 }">
								<radio value="1" :checked="form.is_attr == 1" color="var(--theme-color)"
									style="transform: scale(0.8);" :disabled="form.platform_goods_id > 0" />
								<text>是</text>
							</label>
						</radio-group>
					</view>

					<view v-if="form.is_attr == 1">
						<view v-for="(group, groupIndex) in form.specs_data.spec_groups" :key="group.id"
							class="spec-group">
							<view class="form-item spec-group-header">
								<input class="input" v-model="group.name" placeholder="请输入规格名, 如颜色"
									placeholder-class="placeholder" :disabled="form.platform_goods_id > 0" />
								<view class="delete-spec-btn" @click="deleteSpecGroup(groupIndex)"
									v-if="!form.platform_goods_id > 0">
									<tui-icon name="close" color="#ff0000" :size="20"></tui-icon>
								</view>
							</view>
							<view class="spec-values">
								<view class="spec-value-item" v-for="(value, valueIndex) in group.values"
									:key="value.id">
									{{ value.name }}
									<view class="delete-value-btn" @click="deleteSpecValue(groupIndex, valueIndex)"
										v-if="!form.platform_goods_id > 0">×
									</view>
								</view>
								<input class="add-value-input" v-if="group.inputVisible && !form.platform_goods_id > 0"
									:id="'spec_input_' + groupIndex" v-model="group.inputValue"
									@blur="handleInputConfirm(groupIndex)" @confirm="handleInputConfirm(groupIndex)" />
								<view class="add-value-btn" v-else-if="!form.platform_goods_id > 0"
									@click="showInput(groupIndex)">+
									添加规格值</view>
							</view>
						</view>

						<view class="add-spec-btn" @click="addSpecGroup" v-if="!form.platform_goods_id > 0">
							<tui-icon name="plus" color="var(--theme-color)" :size="16"></tui-icon>
							<text>添加规格项</text>
						</view>

						<view class="spec-table" v-if="form.specs_data.sku_list.length > 0">
							<view class="spec-thead">
								<view class="th" v-for="group in form.specs_data.spec_groups" :key="group.id">
									{{ group.name }}
								</view>
								<view class="th"><text class="req-star">*</text>价格</view>
								<view class="th"><text class="req-star">*</text>库存</view>
							</view>
							<view class="spec-tbody">
								<view class="spec-tr" v-for="(sku, skuIndex) in form.specs_data.sku_list"
									:key="skuIndex">
									<view class="td" v-for="spec in sku.specs" :key="spec.group">{{ spec.value }}</view>
									<view class="td"><input class="spec-input" type="digit" v-model="sku.price"
											@input="updateTotalStock" placeholder="价格" /></view>
									<view class="td"><input class="spec-input" type="number" v-model="sku.stock"
											@input="updateTotalStock" placeholder="库存"
											:disabled="form.platform_goods_id > 0" />
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>

			<view class="footer-bar">
				<button class="submit-btn" @click="submit">确定发布</button>
			</view>
		</view>

		<!-- 店铺分类弹窗 -->
		<tui-bottom-popup :show="showMchCatPopup" @close="showMchCatPopup = false">
			<view class="popup-header">
				<text class="popup-title">选择店铺分类</text>
				<view class="popup-close" @click="showMchCatPopup = false">完成</view>
			</view>
			<scroll-view class="popup-scroll" scroll-y>
				<checkbox-group @change="onMchCatChange">
					<view class="mch-cat-list" v-if="flatMchCategoryList.length > 0">
						<label class="mch-cat-item" v-for="cat in flatMchCategoryList" :key="cat.id">
							<checkbox :value="cat.id.toString()" :checked="form.mch_cats && form.mch_cats.includes(cat.id)" color="var(--theme-color)" style="transform: scale(0.8);" />
							<text class="mch-cat-name">{{ cat.display_name }}</text>
						</label>
					</view>
					<view v-else class="empty-tip" @click="goToCatList">
						暂无店铺分类，点此去创建 ›
					</view>
				</checkbox-group>
			</scroll-view>
		</tui-bottom-popup>
	</tui-page>
</template>

<script>
export default {
	data() {
		return {

			loading: false,
			form: this.getCleanForm(),
			showMchCatPopup: false,
			mchCategoryList: [],
			flatMchCategoryList: [], // 扁平化展示用
			categoryList: [],
			multiCategoryArray: [
				[],
				[]
			],
			multiCategoryIndex: [0, 0],
			mch_setting: null,
			minRates: {
				min_platform_profit_rate: 0,
				min_commission_rate: 0,
			},
			// --- 物流相关 ---
			mchType: 0, // 1=供应商, 2=商家
			freightTplList: [],
			freightTplIndex: 0,
		};
	},
	computed: {
		selectedCategoryName() {
			if (!this.form.cat_id || this.categoryList.length === 0) return '';
			let catName = '';
			const level1Index = this.multiCategoryIndex[0];
			const level2Index = this.multiCategoryIndex[1];
			const level1Cat = this.multiCategoryArray[0][level1Index];
			if (level1Cat) {
				catName = level1Cat.name;
				const level2Cat = this.multiCategoryArray[1][level2Index];
				if (level2Cat) {
					catName += ` / ${level2Cat.name}`;
				}
			}
			return catName;
		},
		selectedMchCatNames() {
			if (!this.form.mch_cats || this.form.mch_cats.length === 0) return '';
			const names = [];
			this.form.mch_cats.forEach(id => {
				const found = this.flatMchCategoryList.find(c => c.id == id);
				if (found) names.push(found.name);
			});
			return names.join('，');
		}
	},
	onLoad(options) {
		this.loadInitialData(options.id);
	},
	onShow() {
		// 从「去配置运费模板」返回:重新拉取模板列表,让刚建的模板立刻能选
		if (this._freightNeedsRefresh) {
			this._freightNeedsRefresh = false;
			this.refreshFreightList();
		}
	},
	methods: {
		// 跳去运费配置页;标记返回后需刷新模板列表
		goFreightSetting() {
			this._freightNeedsRefresh = true;
			uni.navigateTo({ url: '/plugins1/mch/admin/freight' });
		},
		// 只刷新运费模板列表(用于从配置页返回后),商家版最多一个模板,建好即默认选中
		async refreshFreightList() {
			try {
				const res = await this.$request({ url: '/plugin/mch/admin/freight/list' });
				if (res.code === 0 && Array.isArray(res.data.list)) {
					this.freightTplList = res.data.list;
					const idx = this.freightTplList.findIndex(item => item.id === this.form.freight_id);
					if (idx !== -1) {
						this.freightTplIndex = idx;
					} else if (this.freightTplList.length) {
						// 之前没选过:默认选中唯一/第一个模板,避免"显示了名字却没写 freight_id"
						this.freightTplIndex = 0;
						this.form.freight_id = this.freightTplList[0].id;
					}
				}
			} catch (e) {
				console.error(e);
			}
		},
		async loadInitialData(goodsId) {
			uni.showLoading({
				title: '加载中...'
			});
			try {
				const requests = [
					this.$request({ // 获取平台分类
						url: '/plugin/mch/admin/platform/cat-list'
					}),
					this.$request({ // 获取商家通用设置
						url: '/plugin/mch/admin/goods/setting'
					}),
					this.$request({ // 获取商家的运费模板列表
						url: '/plugin/mch/admin/freight/list'
					}),
					this.$request({ // [新增] 获取商家私人分类
						url: '/plugin/mch/admin/goods-cat/index'
					}),
				];

				let goodsResIndex = -1;
				if (goodsId) {
					requests.push(this.$request({
						url: '/plugin/mch/admin/goods/edit',
						data: {
							id: goodsId
						}
					}));
					goodsResIndex = requests.length - 1;
				}

				const responses = await Promise.all(requests);
				const catRes = responses[0];
				const settingRes = responses[1];
				const freightRes = responses[2];
				const mchCatRes = responses[3];
				const goodsRes = goodsResIndex !== -1 ? responses[goodsResIndex] : null;

				if (catRes.code === 0) {
					this.categoryList = catRes.data.list;
				}

				if (mchCatRes && mchCatRes.code === 0) {
					this.mchCategoryList = mchCatRes.data.list || [];
					this.flatMchCategoryList = this.flattenMchCats(this.mchCategoryList);
				}

				if (settingRes.code === 0) {

					this.mch_setting = settingRes.data.mch_setting;
				}

				if (freightRes.code === 0 && Array.isArray(freightRes.data.list)) {
					this.freightTplList = freightRes.data.list;
				}

				if (goodsRes && goodsRes.code === 0) {
					this.form = this.mergeWithDefaultForm(goodsRes.data.detail);

					this.form.detail_pics = this.parseDetailToPics(this.form.detail);
					this.form.specs_data = this.form.specs_data || {
						spec_groups: [],
						sku_list: []
					};
					this.form.specs_data.spec_groups.forEach(g => {
						this.$set(g, 'inputVisible', false);
						this.$set(g, 'inputValue', '');
					});
					// 处理运费模板回显
					this.freightTplIndex = this.freightTplList.findIndex(item => item.id === this.form.freight_id);
					if (this.freightTplIndex === -1) this.freightTplIndex = 0;
				}

				const mchInfo = this.$store.state.mch.currentMch;
				this.mchType = (mchInfo && mchInfo.type) || 0;
				this.initMultiCategoryPicker();
				if (this.form.id && this.form.cat_id) {
					this.onCategoryChange({
						detail: {
							value: this.multiCategoryIndex
						}
					}, true);
				}

			} catch (e) {
				console.error("加载初始数据失败", e);
			} finally {
				uni.hideLoading();
			}
		},
		getCleanForm() {
			return {
				id: null,
				name: '',
				unit: '件',
				cat_id: null,
				mch_cats: [],
				cover_pic: '',
				pic_list: [],
				detail_pics: [],
				is_attr: 0,
				price: '',
				cost_price: '',
				origin_price: '',
				stock: '',
				virtual_sales: 0,
				platform_goods_id: 0,
				express_type: 0,
				freight_id: 0,
				freight_price: '',
				specs_data: {
					spec_groups: [{
						id: Date.now(),
						name: '规格',
						inputValue: '',
						inputVisible: false,
						values: []
					}],
					sku_list: []
				}
			};
		},
		mergeWithDefaultForm(backendForm) {
			const defaultForm = this.getCleanForm();
			Object.keys(defaultForm).forEach(key => {
				if (backendForm[key] === undefined || backendForm[key] === null) {
					backendForm[key] = defaultForm[key];
				} else if (typeof backendForm[key] === 'object' && !Array.isArray(backendForm[key])) {
					backendForm[key] = {
						...defaultForm[key],
						...backendForm[key]
					};
				}
			});

			return backendForm;
		},

		parseDetailToPics(detailHtml) {
			if (!detailHtml) return [];
			const imgRegex = /<img.*?src="(.*?)"/g;
			let match;
			const pics = [];
			while ((match = imgRegex.exec(detailHtml)) !== null) {
				pics.push(match[1]);
			}
			return pics;
		},
		initMultiCategoryPicker() {
			if (this.categoryList.length === 0) {
				this.multiCategoryArray = [
					[{
						name: '暂无分类'
					}],
					[]
				];
				return;
			}
			this.multiCategoryArray[0] = this.categoryList;
			let level1Index = 0;
			let level2Index = 0;
			if (this.form.id && this.form.cat_id) {
				let found = false;
				for (let i = 0; i < this.categoryList.length; i++) {
					const level1Cat = this.categoryList[i];
					if (level1Cat.children && level1Cat.children.length > 0) {
						for (let j = 0; j < level1Cat.children.length; j++) {
							if (level1Cat.children[j].id === this.form.cat_id) {
								level1Index = i;
								level2Index = j;
								found = true;
								break;
							}
						}
					}
					if (found) break;
				}
			}
			const children = this.categoryList[level1Index]?.children || [];
			this.$set(this.multiCategoryArray, 1, children);
			this.multiCategoryIndex = [level1Index, level2Index];
		},
		flattenMchCats(tree, prefix = '') {
			let flat = [];
			tree.forEach(node => {
				flat.push({
					id: node.id,
					name: node.name,
					display_name: prefix + node.name
				});
				if (node.children && node.children.length > 0) {
					flat = flat.concat(this.flattenMchCats(node.children, prefix + '— '));
				}
			});
			return flat;
		},
		onMchCatChange(e) {
			const values = e.detail.value || [];
			this.form.mch_cats = values.map(v => parseInt(v));
		},
		goToCatList() {
			this.showMchCatPopup = false;
			uni.navigateTo({ url: '/plugins1/mch/admin/cat-list' });
		},
		onCategoryChange(e, isInitialLoad = false) {
			if (!isInitialLoad) {
				const [level1Index, level2Index] = e.detail.value;
				this.multiCategoryIndex = [level1Index, level2Index];
				const level1Cat = this.multiCategoryArray[0][level1Index];
				const level2Cat = this.multiCategoryArray[1][level2Index];
				this.form.cat_id = level2Cat ? level2Cat.id : null;
			}
			this.minRates = {
				min_platform_profit_rate: 0,
				min_commission_rate: 0,
			};
			if (!this.form.cat_id) return;

			let selectedCat = null;
			const level1 = this.multiCategoryArray[0][this.multiCategoryIndex[0]];
			if (level1 && level1.children) {
				selectedCat = level1.children.find(c => c.id === this.form.cat_id);
			}
			if (selectedCat) {
				this.minRates.min_platform_profit_rate = selectedCat.min_platform_profit_rate || 0;
				this.minRates.min_commission_rate = selectedCat.min_commission_rate || 0;
			}
		},
		onCategoryColumnChange(e) {
			if (e.detail.column === 0) {
				const children = this.categoryList[e.detail.value]?.children || [];
				this.$set(this.multiCategoryArray, 1, children);
				this.multiCategoryIndex = [e.detail.value, 0];
			}
		},

		onExpressTypeChange(e) {
			this.form.express_type = Number(e.detail.value);
		},
		onFreightTplChange(e) {
			this.freightTplIndex = Number(e.detail.value);
			this.form.freight_id = this.freightTplList[this.freightTplIndex].id;
		},
		onSpecTypeChange(e) {
			this.form.is_attr = Number(e.detail.value);
			if (this.form.is_attr === 1) this.updateTotalStock();
		},
		addSpecGroup() {
			this.form.specs_data.spec_groups.push({
				id: Date.now(),
				name: '',
				inputValue: '',
				inputVisible: false,
				values: []
			});
			this.updateSkuList();
		},
		deleteSpecGroup(groupIndex) {
			if (this.form.specs_data.spec_groups.length <= 1) return this.$util.toast('至少需要一个规格组');
			this.form.specs_data.spec_groups.splice(groupIndex, 1);
			this.updateSkuList();
		},
		showInput(groupIndex) {
			this.form.specs_data.spec_groups[groupIndex].inputVisible = true;
		},
		handleInputConfirm(groupIndex) {
			const group = this.form.specs_data.spec_groups[groupIndex];
			const inputValue = group.inputValue;
			if (inputValue && !group.values.find(v => v.name === inputValue)) {
				group.values.push({
					id: Date.now(),
					name: inputValue
				});
			}
			group.inputVisible = false;
			group.inputValue = '';
			this.updateSkuList();
		},
		deleteSpecValue(groupIndex, valueIndex) {
			this.form.specs_data.spec_groups[groupIndex].values.splice(valueIndex, 1);
			this.updateSkuList();
		},
		updateSkuList() {
			const specGroups = this.form.specs_data.spec_groups.filter(g => g.values.length > 0 && g.name);
			if (specGroups.length === 0) {
				this.form.specs_data.sku_list = [];
				this.updateTotalStock();
				return;
			}
			const cartesianProduct = (...a) => a.reduce((a, b) => a.flatMap(d => b.map(e => [d, e].flat())));
			const valueArrays = specGroups.map(g => g.values);
			const skuCombinations = cartesianProduct(...valueArrays);
			this.form.specs_data.sku_list = skuCombinations.map(combination => {
				const specs = (Array.isArray(combination) ? combination : [combination]).map((value,
					index) => ({
						group: specGroups[index].name,
						value: value.name
					}));
				const existingSku = this.form.specs_data.sku_list.find(sku => JSON.stringify(sku
					.specs) === JSON.stringify(specs));
				return existingSku || {
					price: '',
					stock: '',
					specs
				};
			});
			this.updateTotalStock();
		},
		updateTotalStock() {
			if (this.form.is_attr == 1) {
				this.form.stock = this.form.specs_data.sku_list.reduce((total, sku) => total + (Number(sku
					.stock) || 0), 0);
			}
		},
		buildDetailHtml() {
			if (!this.form.detail_pics || this.form.detail_pics.length === 0) return '';
			return this.form.detail_pics.map(url => `<p><img src="${url}" style="max-width: 100%;"></p>`)
				.join('');
		},
		validate() {
			if (!this.form.name) return this.$util.toast('请输入商品名称');
			if (!this.form.cat_id) return this.$util.toast('请选择一个二级商品分类');
			// 多规格价以各 SKU 为准(顶层价被后端忽略),只有单规格才校验顶层价
			if (this.form.is_attr != 1 && (!this.form.price || parseFloat(this.form.price) <= 0)) return this.$util.toast('请输入正确的列表价格');
			if (this.form.stock === '' || parseInt(this.form.stock) < 0) return this.$util.toast('请输入正确的总库存');
			if (!this.form.cover_pic) return this.$util.toast('请上传商品封面图');
			if (!this.form.pic_list || this.form.pic_list.length === 0) return this.$util.toast('请上传商品轮播图');
			if (this.form.is_attr == 1) {
				if (this.form.specs_data.spec_groups.length === 0) return this.$util.toast('请添加规格项');
				for (const group of this.form.specs_data.spec_groups) {
					if (!group.name) return this.$util.toast('请输入规格名');
					if (group.values.length === 0) return this.$util.toast(`请为"${group.name}"添加规格值`);
				}
				if (this.form.specs_data.sku_list.length === 0) return this.$util.toast(
					'请为规格添加规格值以生成SKU列表');
				for (const sku of this.form.specs_data.sku_list) {
					if (!sku.price || parseFloat(sku.price) <= 0) return this.$util.toast('请输入正确的SKU价格');
					if (sku.stock === '' || parseInt(sku.stock) < 0) return this.$util.toast('请输入正确的SKU库存');
				}
			}

			// 运费:选了统一运费/运费模板却没填金额/没选模板,别静默变包邮
			if (this.form.express_type == 1 && (!this.form.freight_price || parseFloat(this.form.freight_price) <= 0)) {
				return this.$util.toast('请输入统一运费金额,或改选包邮');
			}
			if (this.form.express_type == 2 && !this.form.freight_id) {
				return this.$util.toast('请选择运费模板,没有就先去配置');
			}

			return true;
		},
		async submit() {
			if (!this.validate()) return;
			const postData = JSON.parse(JSON.stringify(this.form));
			postData.detail = this.buildDetailHtml();

			delete postData.detail_pics;

			uni.showLoading({
				title: '发布中...'
			});
			this.loading = true;
			try {
				const res = await this.$request({
					url: '/plugin/mch/admin/goods/edit',
					method: 'POST',
					data: postData
				});
				if (res.code === 0) {
					this.$util.toast(res.msg, 'success');
					setTimeout(() => uni.navigateBack(), 1000);
				} else {
					this.$util.toast(res.msg);
				}
			} finally {
				this.loading = false;
				uni.hideLoading();
			}
		}
	}
}
</script>

<style lang="scss" scoped>
.page-container {
	background-color: #F5F6FA;
	/* 原生页面滚动:footer 为 fixed,底部留出按钮高度+安全区,避免最后一张卡被遮挡 */
	padding-bottom: calc(160rpx + env(safe-area-inset-bottom));
}

.platform-good-tip {
	display: flex;
	align-items: center;
	background-color: #ecf5ff;
	color: #409eff;
	padding: 16rpx 24rpx;
	font-size: 24rpx;
	margin: 24rpx 24rpx 0;
	border-radius: 8rpx;
	gap: 12rpx;
}

.switch-control {
	margin-left: auto;
	transform: scale(0.8);
}

.form-card {
	background-color: #fff;
	margin: 24rpx;
	padding: 0 24rpx;
	border-radius: 16rpx;
}

.card-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
	padding: 24rpx 0;
	border-bottom: 1rpx solid #f0f0f0;
}

.form-item {
	display: flex;
	align-items: center;
	padding: 28rpx 0;
	border-bottom: 1rpx solid #f0f0f0;
	font-size: 28rpx;

	&:last-child {
		border-bottom: none;
	}
}

.form-hint {
	font-size: 24rpx;
	color: #999;
	padding: 0 0 24rpx;
}

.unit-suffix {
	font-size: 28rpx;
	color: #333;
	margin-left: 10rpx;
}

.picker-display-wrapper {
	display: flex;
	align-items: center;
	width: 100%;
}

.input {
	flex: 1;
	text-align: left;
	color: #333;

	&.align-right {
		text-align: right;
	}
}

.placeholder {
	color: #C0C4CC;
	font-size: 14px;
}

.picker-display {
	flex: 1;
	color: #333;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* 无运费模板时的「去配置」入口:主题色可点(页面根已绑 themeStyle) */
.freight-empty {
	color: var(--theme-color);
}

.upload-group {
	padding: 24rpx 0;
}

.upload-label {
	font-size: 28rpx;
	color: #333;
	margin-bottom: 20rpx;
}

.label-tip {
	font-size: 24rpx;
	color: #999;
	margin-left: 10rpx;
}

.upload-placeholder {
	width: 100%;
	height: 100%;
	background-color: #F7F8FA;
	border: 1rpx dashed #DCDFE6;
	border-radius: 8rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
}

.upload-text {
	font-size: 24rpx;
	color: #999;
	margin-top: 8rpx;
}

.spec-label {
	color: #333;
	flex-shrink: 0;
	margin-right: 20rpx;
}

/* 必填项小红星 */
.req-star {
	color: #ff4141;
	margin-right: 4rpx;
	font-size: 26rpx;
}

.radio-group {

	.disabled {
		color: #c0c4cc;
	}
}

.radio-item {
	margin-left: 20rpx;
}

.add-spec-btn {
	display: flex;
	align-items: center;
	color: var(--theme-color);
	font-size: 28rpx;
	padding: 24rpx 0;

	text {
		margin-left: 8rpx;
	}
}

.spec-group {
	border-bottom: 1rpx dashed #eee;
	padding-bottom: 20rpx;
	margin-bottom: 20rpx;
}

.spec-group-header .input {
	text-align: left;
}

.spec-values {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	padding: 10rpx 0;
}

.spec-value-item {
	background-color: #f2f2f2;
	color: #333;
	padding: 10rpx 20rpx;
	border-radius: 20rpx;
	margin-right: 20rpx;
	margin-bottom: 20rpx;
	position: relative;
}

.delete-value-btn {
	position: absolute;
	top: -10rpx;
	right: -10rpx;
	width: 30rpx;
	height: 30rpx;
	background-color: rgba(0, 0, 0, 0.5);
	color: #fff;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 20rpx;
	line-height: 1;
}

.add-value-btn,
.add-value-input {
	font-size: 26rpx;
	color: var(--theme-color);
	padding: 10rpx 20rpx;
}

.add-value-input {
	width: 150rpx;
	border: 1rpx solid var(--theme-color);
	border-radius: 20rpx;
}

.spec-table {
	width: 100%;
	font-size: 26rpx;
	color: #666;
}

.spec-thead,
.spec-tr {
	display: flex;
	align-items: center;
	border-bottom: 1rpx solid #f0f0f0;
}

.spec-thead {
	color: #333;
	font-weight: bold;
}

.th,
.td {
	flex: 2;
	padding: 20rpx 10rpx;
	text-align: center;
}

.th:first-child,
.td:first-child {
	flex: 3;
}

.spec-thead .th,
.spec-tbody .td {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.spec-input {
	width: 100%;
	font-size: 26rpx;
	text-align: center;
	background-color: #F7F8FA;
	border-radius: 8rpx;
	height: 60rpx;
}

.delete-spec-btn {
	margin-left: auto;
}

.footer-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background-color: #fff;
	padding: 20rpx 24rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
	z-index: 20;
}

.submit-btn {
	background-color: var(--theme-color);
	color: #fff;
	height: 88rpx;
	line-height: 88rpx;
	border-radius: 44rpx;
	font-size: 32rpx;
	border: none;

	&::after {
		border: none;
	}
}

/* 店铺分类弹窗样式 */
.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 30rpx 40rpx;
	border-bottom: 1rpx solid #f5f5f5;
}
.popup-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #333;
}
.popup-close {
	font-size: 28rpx;
	color: var(--theme-color);
}
.popup-scroll {
	max-height: 60vh;
	min-height: 40vh;
	padding: 20rpx 0;
	background-color: #f9f9f9;
}
.mch-cat-list {
	display: flex;
	flex-direction: column;
	padding: 0 40rpx;
}
.mch-cat-item {
	display: flex;
	align-items: center;
	padding: 24rpx 0;
	border-bottom: 1rpx solid #efefef;
}
.mch-cat-name {
	font-size: 28rpx;
	color: #444;
	margin-left: 20rpx;
}
.empty-tip {
	text-align: center;
	padding: 100rpx 0;
	color: #999;
	font-size: 28rpx;
}
</style>