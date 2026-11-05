<template>
	<tui-page>
		<view class="page-container" v-if="!loading" :style="themeStyle">

			<!-- 主体类型(后台开放哪几类就显示哪几类)-->
			<view class="form-card" v-if="entityOptions.length">
				<view class="form-group no-border">
					<view class="label required">主体类型</view>
					<radio-group class="entity-radio-group" @change="onTypeChange">
						<label class="entity-item" v-for="opt in entityOptions" :key="opt.value"
							:class="{ active: form.type === opt.value }">
							<radio :value="String(opt.value)" :checked="form.type === opt.value" :color="themeColor" />
							<view class="entity-text">
								<text class="entity-label">{{ opt.label }}</text>
								<text class="entity-desc">{{ opt.desc }}</text>
							</view>
						</label>
					</radio-group>
				</view>
			</view>

			<!-- 动态字段 + 证照(按后台配置渲染)-->
			<view class="form-card">
				<block v-for="field in activeFields" :key="field.field_key">
					<view class="form-group">
						<view class="label" :class="{ required: field.is_required }">{{ field.label }}</view>

						<!-- 文本 / 手机号 -->
						<view v-if="field.component === 'text' || field.component === 'phone'" class="field-wrapper">
							<input class="input" :type="field.component === 'phone' ? 'number' : 'text'"
								:value="form[field.field_key]" :placeholder="field.placeholder"
								placeholder-class="placeholder" @input="onFieldInput(field.field_key, $event)" />
						</view>

						<!-- 多行文本 -->
						<view v-else-if="field.component === 'textarea'" class="field-wrapper textarea-wrapper">
							<textarea class="textarea-input" :value="form[field.field_key]" :placeholder="field.placeholder"
								placeholder-class="placeholder" auto-height @input="onFieldInput(field.field_key, $event)" />
						</view>

						<!-- 商家分类 -->
						<picker v-else-if="field.component === 'category'" range-key="cat_name" :value="catIndex"
							:range="catList" @change="onCatChange">
							<view class="field-wrapper">
								<view class="picker-display" :class="{ placeholder: catIndex === -1 }">
									{{ catList[catIndex] ? catList[catIndex].cat_name : (field.placeholder || '请选择') }}
								</view>
								<tui-icon name="arrowdown" :size="16" color="#999"></tui-icon>
							</view>
						</picker>

						<!-- 省市区 -->
						<tui-address-picker v-else-if="field.component === 'region'" @selected="onAddressConfirm">
							<view class="field-wrapper">
								<view class="picker-display" :class="{ placeholder: !form.area }">{{ form.area || field.placeholder || '选择省市区' }}</view>
								<tui-icon name="arrowdown" :size="16" color="#999"></tui-icon>
							</view>
						</tui-address-picker>

						<!-- 街道/乡镇 -->
						<picker v-else-if="field.component === 'town'" range-key="name" :value="townIndex"
							:range="townList" @change="onTownChange">
							<view class="field-wrapper">
								<view class="picker-display" :class="{ placeholder: townIndex === -1 }">
									{{ townList[townIndex] ? townList[townIndex].name : (field.placeholder || '请选择街道/乡镇') }}
								</view>
								<tui-icon name="arrowdown" :size="16" color="#999"></tui-icon>
							</view>
						</picker>

						<!-- 地图位置 -->
						<view v-else-if="field.component === 'location'" class="field-wrapper" @click="chooseLocation">
							<view class="location-display" :class="{ placeholder: !form.location_address }">
								{{ form.location_address || field.placeholder || '点击选择门店地图位置' }}
							</view>
							<view class="location-switch">
								<tui-icon name="position" :size="16" :color="themeColor"></tui-icon>
								<text class="switch-text" :style="'color:' + themeColor">切换</text>
							</view>
						</view>

						<!-- 营业时间 -->
						<view v-else-if="field.component === 'timerange'" class="time-picker-wrapper flex-y-center">
							<picker class="time-picker-item" mode="time" @change="onTimeChange($event, 'start')">
								<view class="time-picker-display">{{ form.start_time || '开始时间' }}</view>
							</picker>
							~
							<picker class="time-picker-item" mode="time" @change="onTimeChange($event, 'end')">
								<view class="time-picker-display">{{ form.end_time || '结束时间' }}</view>
							</picker>
						</view>

						<!-- 单图 -->
						<tui-upload-single-image v-else-if="field.component === 'image'" :value="form[field.field_key]"
							width="160rpx" height="160rpx" @input="onFieldVal(field.field_key, $event)"></tui-upload-single-image>

						<!-- 多图 -->
						<tui-upload v-else-if="field.component === 'images'" :value="form[field.field_key]"
							:limit="field.limit || 5" @input="onFieldVal(field.field_key, $event)"></tui-upload>
					</view>
				</block>

				<!-- 证照库(后台可增减,可带有效期)。事件传 idx 查表(小程序 data-event-opts 不能用拼接key/复杂表达式) -->
				<block v-for="(cert, idx) in activeCerts" :key="cert.id">
					<view class="form-group" :class="{ 'no-border': idx === activeCerts.length - 1 }">
						<view class="label" :class="{ required: cert.is_required }">{{ cert.name }}</view>
						<tui-upload-single-image :value="form.certs[cert.id].image" width="160rpx" height="160rpx"
							@input="onCertImage(idx, $event)"></tui-upload-single-image>

						<view v-if="cert.need_validity" class="validity-box">
							<checkbox-group @change="onLongTerm(idx, $event)">
								<label class="long-term-label">
									<checkbox value="1" :checked="form.certs[cert.id].is_long_term === 1"
										:color="themeColor" style="transform:scale(0.7)" />
									<text>长期有效</text>
								</label>
							</checkbox-group>
							<view v-if="form.certs[cert.id].is_long_term !== 1" class="date-range flex-y-center">
								<picker class="date-item" mode="date" @change="onCertDate(idx, 'valid_start', $event)">
									<view class="date-display" :class="{ placeholder: !form.certs[cert.id].valid_start }">
										{{ form.certs[cert.id].valid_start || '生效日期' }}
									</view>
								</picker>
								<text class="date-sep">至</text>
								<picker class="date-item" mode="date" @change="onCertDate(idx, 'valid_end', $event)">
									<view class="date-display" :class="{ placeholder: !form.certs[cert.id].valid_end }">
										{{ form.certs[cert.id].valid_end || '到期日期' }}
									</view>
								</picker>
							</view>
						</view>
					</view>
				</block>
			</view>

			<view class="shuoming-card" v-if="instructionsText">
				<view class="label">{{ instructionsTitle }}</view>
				<view class="desc-text">{{ instructionsText }}</view>
			</view>

			<view class="agreement-section" v-if="agreementContent">
				<checkbox-group @change="onAgreementChange">
					<label>
						<checkbox value="agree" style="transform:scale(0.8)" :color="themeColor" />
						<text>阅读并同意</text>
						<text class="link" :style="'color:' + themeColor" @click.stop="show = true">《{{ agreementTitle }}》</text>
					</label>
				</checkbox-group>
			</view>

			<view class="submit-btn-wrapper">
				<button class="submit-btn" :style="isAgreed ? ('background-color:' + themeColor) : ''" @click="submit"
					:disabled="!isAgreed">提交入驻申请</button>
			</view>
		</view>

		<tui-bottom-popup :show="show" @close="close" :show-close="true">
			<view style="height: 70vh;padding: 30rpx;">
				<view class="flex-x-center" style="font-weight: bold;">{{ agreementTitle }}</view>
				<view style="margin-top: 10rpx;">
					<tui-rich-text :value="agreementContent"></tui-rich-text>
				</view>
			</view>
		</tui-bottom-popup>

		<tui-loading v-if="loading" text="配置加载中..." :is-fixed="true"></tui-loading>
	</tui-page>
</template>

<script>
	import { chooseLocationWithTip } from "@/utils/permissionTip.js";
import { mapState } from 'vuex'

// 主体类型枚举（与后端 Mch::TYPE_* 一致）
const TYPE_UP_STORE   = 1
const TYPE_DOWN_STORE = 2
const TYPE_INDIVIDUAL = 4

export default {
	data() {
		return {
			show: false,
			loading: true,
			form: this.getCleanForm(),
			// 后端下发的逐类型配置：{ "1": { fields:[...], certs:[...] }, ... }
			applyConfig: {},
			mchTypes: [],
			townList: [],
			townIndex: -1,
			catList: [],
			catIndex: -1,
			instructionsTitle: '入驻说明',
			instructionsText: '',
			agreementTitle: '店铺入驻协议',
			agreementContent: '',
			isAgreed: false,
		}
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D6201F',
		}),
		// 类型选项：后端开放列表 + 本地描述字典
		entityOptions() {
			const descMap = {
				[TYPE_UP_STORE]:   '纯线上经营，发布商品、在线下单',
				[TYPE_DOWN_STORE]: '有线下营业地址，支持到店核销 / 同城配送',
				[TYPE_INDIVIDUAL]: '自然人主体，凭身份证即可入驻',
			}
			return (this.mchTypes || []).map(t => {
				const value = Number(t.value)
				return { value, label: t.label, desc: descMap[value] || '' }
			})
		},
		// 当前类型的「启用字段」：街道仅在该区县有乡镇时显示
		activeFields() {
			const cfg = this.applyConfig[String(this.form.type)]
			if (!cfg || !cfg.fields) return []
			return cfg.fields.filter(f => {
				if (f.field_key === 'town') return this.townList.length > 0
				return true
			})
		},
		// 当前类型的证照定义
		activeCerts() {
			const cfg = this.applyConfig[String(this.form.type)]
			return (cfg && cfg.certs) ? cfg.certs : []
		},
	},
	onLoad() {
		this.setNavBarColor()
		this.loadPrepareData()
	},
	watch: {
		themeColor(val) { if (val) this.setNavBarColor() },
	},
	methods: {
		setNavBarColor() {
			uni.setNavigationBarColor({
				frontColor: '#000000',
				backgroundColor: '#F5F6FA',
				animation: { duration: 300, timingFunc: 'easeIn' },
			})
		},
		close() { this.show = false },
		getCleanForm() {
			return {
				type: TYPE_UP_STORE,
				mch_name: '',
				cat_id: null,
				province_code: '',
				city_code: '',
				county_code: '',
				town_code: '',
				area: '',
				address: '',
				location_address: '',
				latitude: '',
				longitude: '',
				legal_person_name: '',
				mobile: '',
				start_time: '',
				end_time: '',
				opening_hours: '',
				logo: '',
				banner_images: [],
				description: '',
				id_card_front: '',
				id_card_back: '',
				certs: {}, // { [cert_def_id]: { image, valid_start, valid_end, is_long_term } }
			}
		},
		async loadPrepareData() {
			this.loading = true
			try {
				const res = await this.$request({ url: '/plugin/mch/api/mch/apply-data', method: 'GET' })
				if (res.code === 0) {
					const { setting, mch } = res.data
					this.applyConfig = setting.apply_config || {}
					this.mchTypes = Array.isArray(setting.mch_types) ? setting.mch_types : []
					this.catList = setting.categories || []
					if (setting.instructions_title)   this.instructionsTitle = setting.instructions_title
					if (setting.instructions_content) this.instructionsText  = setting.instructions_content
					this.agreementTitle   = setting.entry_agreement_title || this.agreementTitle
					this.agreementContent = setting.entry_agreement_content || ''

					const allowed = this.mchTypes.map(t => Number(t.value))
					this.form.type = allowed[0] || TYPE_UP_STORE

					if (mch) {
						// 回显已有申请
						this.fillFromMch(mch, allowed)
						if (mch.status === 2) {
							uni.showModal({
								title: '申请被驳回',
								content: '原因：' + (mch.remarks || '未提供具体原因'),
								showCancel: false,
								confirmText: '我知道了',
							})
						}
					} else {
						this.buildCerts([])
					}
				} else {
					this.$util.toast(res.msg)
				}
			} catch (e) {
				console.error('获取入驻配置失败', e)
				this.$util.toast('配置加载失败，请重试')
			} finally {
				this.loading = false
			}
		},
		fillFromMch(mch, allowed) {
			const f = Object.assign(this.getCleanForm(), mch)
			f.type = Number(mch.type) || allowed[0] || TYPE_UP_STORE
			if (f.type === 3 || !allowed.includes(f.type)) f.type = allowed[0] || TYPE_UP_STORE
			f.banner_images = Array.isArray(mch.banner_images) ? mch.banner_images : []
			f.certs = {}
			if (mch.opening_hours && mch.opening_hours.includes('-')) {
				const [s, e] = mch.opening_hours.split('-')
				f.start_time = (s || '').trim()
				f.end_time   = (e || '').trim()
			}
			this.form = f
			this.catIndex = this.catList.findIndex(c => c.id == this.form.cat_id)
			this.buildCerts(mch.certificates || [])
			if (this.form.county_code) this.getTownList(this.form.county_code)
		},
		// 重建当前类型的证照填写态(合并已提交值)
		buildCerts(existing) {
			const exMap = {}
			;(existing || []).forEach(e => { exMap[e.cert_def_id] = e })
			const certs = {}
			this.activeCerts.forEach(c => {
				const e = exMap[c.id] || {}
				certs[c.id] = {
					image: e.image || '',
					valid_start: e.valid_start || '',
					valid_end: e.valid_end || '',
					is_long_term: Number(e.is_long_term) || 0,
				}
			})
			this.form.certs = certs
		},
		onTypeChange(e) {
			this.form.type = Number(e.detail.value)
			// 清掉切换后不再适用的字段值
			const keys = {}
			this.activeFields.forEach(f => { keys[f.field_key] = true })
			if (!keys.cat_id) { this.form.cat_id = null; this.catIndex = -1 }
			if (!keys.logo) this.form.logo = ''
			if (!keys.banner_images) this.form.banner_images = []
			if (!keys.description) this.form.description = ''
			if (!keys.location) { this.form.location_address = ''; this.form.latitude = ''; this.form.longitude = '' }
			if (!keys.opening_hours) { this.form.opening_hours = ''; this.form.start_time = ''; this.form.end_time = '' }
			this.buildCerts(this.collectCerts())
		},
		// 把当前 form.certs 收集成 [{cert_def_id,...}],供切换类型时尽量保留已填
		collectCerts() {
			return Object.keys(this.form.certs || {}).map(id => Object.assign({ cert_def_id: Number(id) }, this.form.certs[id]))
		},
		onFieldInput(key, e) { this.form[key] = e.detail.value },
		onFieldVal(key, val) { this.form[key] = val },
		onCertImage(idx, val) { const c = this.activeCerts[idx]; if (c && this.form.certs[c.id]) this.form.certs[c.id].image = val },
		onCertDate(idx, key, e) { const c = this.activeCerts[idx]; if (c && this.form.certs[c.id]) this.form.certs[c.id][key] = e.detail.value },
		onLongTerm(idx, e) {
			const c = this.activeCerts[idx]
			if (c && this.form.certs[c.id]) this.form.certs[c.id].is_long_term = (e.detail.value || []).includes('1') ? 1 : 0
		},
		onCatChange(e) {
			this.catIndex = Number(e.detail.value)
			this.form.cat_id = (this.catList[this.catIndex] || {}).id || null
		},
		onAddressConfirm(e) {
			this.form.province_code = e.province_code
			this.form.city_code     = e.city_code
			this.form.county_code   = e.county_code
			this.form.area          = e.area
			this.form.town_code     = ''
			this.townIndex = -1
			this.townList  = []
			if (e.county_code) this.getTownList(e.county_code)
		},
		async getTownList(county_code) {
			uni.showLoading({ title: '加载街道...', mask: true })
			try {
				const res = await this.$request({ url: '/common/address/town-list', data: { county_code } })
				if (res.code === 0 && res.data.list) {
					this.townList = res.data.list
					// 回填:按已选 town_code 匹配 index;新选:不默认选第一个,留空让用户主动选(validate 会要求)
					const idx = this.townList.findIndex(t => String(t.code) === String(this.form.town_code))
					this.townIndex = idx
					if (idx < 0) this.form.town_code = ''
				}
			} catch (e) {
				console.error('获取乡镇列表失败', e)
			} finally {
				uni.hideLoading()
			}
		},
		onTownChange(e) {
			this.townIndex = Number(e.detail.value)
			this.form.town_code = (this.townList[this.townIndex] || {}).code || ''
		},
		chooseLocation() {
			chooseLocationWithTip({
				success: (res) => {
					this.form.location_address = res.address
					this.form.latitude = res.latitude
					this.form.longitude = res.longitude
				},
				fail: (err) => {
					if ((err.errMsg || '').indexOf('cancel') >= 0) return
					console.log(err)
				},
			})
		},
		onTimeChange(e, type) {
			if (type === 'start') this.form.start_time = e.detail.value
			else                  this.form.end_time   = e.detail.value
			if (this.form.start_time && this.form.end_time) {
				this.form.opening_hours = this.form.start_time + '-' + this.form.end_time
			}
		},
		onAgreementChange(e) { this.isAgreed = e.detail.value.includes('agree') },
		fieldFilled(key) {
			switch (key) {
				case 'region':        return !!(this.form.province_code && this.form.city_code && this.form.county_code)
				case 'location':      return !!(this.form.latitude && this.form.longitude)
				case 'cat_id':        return !!this.form.cat_id
				case 'banner_images': return Array.isArray(this.form.banner_images) && this.form.banner_images.length > 0
				case 'opening_hours': return !!this.form.opening_hours
				default:              return !!this.form[key]
			}
		},
		validate() {
			for (const f of this.activeFields) {
				if (!f.is_required) continue
				if (f.field_key === 'town') {
					if (this.townList.length > 0 && !this.form.town_code) { this.$util.toast('请选择街道/乡镇'); return false }
					continue
				}
				if (!this.fieldFilled(f.field_key)) { this.$util.toast('请完善' + f.label); return false }
			}
			if (this.form.mobile && !/^1[3-9]\d{9}$/.test(this.form.mobile)) {
				this.$util.toast('请填写正确的手机号码'); return false
			}
			for (const c of this.activeCerts) {
				const cv = this.form.certs[c.id] || {}
				if (c.is_required && !cv.image) { this.$util.toast('请上传' + c.name); return false }
				if (cv.image && c.need_validity && cv.is_long_term !== 1) {
					if (!cv.valid_start || !cv.valid_end) { this.$util.toast('请填写' + c.name + '的有效期'); return false }
				}
			}
			return true
		},
		buildPayload() {
			const certificates = this.activeCerts
				.map(c => {
					const cv = this.form.certs[c.id] || {}
					return {
						cert_def_id: c.id,
						image: cv.image || '',
						valid_start: cv.valid_start || '',
						valid_end: cv.valid_end || '',
						is_long_term: cv.is_long_term || 0,
					}
				})
				.filter(x => x.image)
			return {
				type: this.form.type,
				mch_name: this.form.mch_name,
				cat_id: this.form.cat_id,
				province_code: this.form.province_code,
				city_code: this.form.city_code,
				county_code: this.form.county_code,
				town_code: this.form.town_code,
				area: this.form.area,
				address: this.form.address,
				location_address: this.form.location_address,
				latitude: this.form.latitude,
				longitude: this.form.longitude,
				legal_person_name: this.form.legal_person_name,
				mobile: this.form.mobile,
				opening_hours: this.form.opening_hours,
				logo: this.form.logo,
				banner_images: this.form.banner_images,
				description: this.form.description,
				id_card_front: this.form.id_card_front,
				id_card_back: this.form.id_card_back,
				certificates,
			}
		},
		async submit() {
			if (!this.isAgreed) return
			if (!this.validate()) return
			uni.showLoading({ title: '正在提交...', mask: true })
			try {
				const res = await this.$request({
					url: '/plugin/mch/api/mch/apply',
					method: 'POST',
					data: this.buildPayload(),
				})
				uni.hideLoading()
				uni.showModal({
					title: '提示',
					content: res.msg,
					showCancel: false,
					success: () => { if (res.code === 0) uni.navigateBack() },
				})
			} catch (e) {
				uni.hideLoading()
				console.error('提交申请失败', e)
				this.$util.toast('提交失败，请重试')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.page-container { padding: 24rpx; }

/* 主体类型 radio：卡片式 */
.entity-radio-group { display: flex; flex-direction: column; gap: 16rpx; padding: 10rpx 0; }
.entity-item {
	display: flex; align-items: center;
	padding: 24rpx 20rpx;
	border: 2rpx solid #EBEDF0;
	border-radius: 16rpx;
	background: #FAFAFA;
	transition: all 0.2s;
	&.active {
		border-color: var(--theme-color);
		background: var(--theme-color-bg);
	}
	radio { transform: scale(0.8); margin-right: 8rpx; }
}
.entity-text { display: flex; flex-direction: column; gap: 4rpx; flex: 1; }
.entity-label { font-size: 28rpx; font-weight: 700; color: #333; }
.entity-desc  { font-size: 22rpx; color: #94A3B8; line-height: 1.4; }

.form-card, .shuoming-card {
	background-color: #fff;
	border-radius: 16rpx;
	margin-bottom: 24rpx;
	padding: 0 32rpx;
}
.form-group {
	padding: 32rpx 0;
	border-bottom: 1rpx solid #EBEDF0;
	&:last-child, &.no-border { border-bottom: none; }
}
.label { font-size: 28rpx; color: #333; margin-bottom: 20rpx; font-weight: 500; }
.label.required::before { content: '*'; color: var(--theme-color); margin-right: 4rpx; }

.field-wrapper {
	display: flex; align-items: center;
	border: 1rpx solid #EBEDF0; border-radius: 8rpx;
	padding: 0 24rpx; height: 88rpx; box-sizing: border-box;
}
.input { flex: 1; font-size: 28rpx; height: 100%; }
.placeholder { color: #C0C4CC; }
.picker-display, .location-display { flex: 1; font-size: 28rpx; color: #333; }
.picker-display.placeholder, .location-display.placeholder { color: #C0C4CC; }
.location-display { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.location-switch { display: flex; align-items: center; }
.switch-text { font-size: 28rpx; margin-left: 8rpx; }

.time-picker-wrapper { display: flex; justify-content: space-between; align-items: center; }
.time-picker-item { width: 48%; }
.time-picker-display {
	border: 1rpx solid #EBEDF0; border-radius: 8rpx;
	height: 88rpx;
	display: flex; align-items: center; justify-content: center;
	font-size: 28rpx;
}

.textarea-wrapper { height: auto; padding: 20rpx 24rpx; }
.textarea-input { width: 100%; font-size: 28rpx; line-height: 1.5; min-height: 80rpx; }

/* 证照有效期 */
.validity-box { margin-top: 20rpx; }
.long-term-label { display: inline-flex; align-items: center; font-size: 26rpx; color: #666; }
.date-range { margin-top: 16rpx; }
.date-item { flex: 1; }
.date-sep { margin: 0 16rpx; color: #999; }
.date-display {
	border: 1rpx solid #EBEDF0; border-radius: 8rpx;
	height: 80rpx; display: flex; align-items: center; justify-content: center;
	font-size: 26rpx; color: #333;
}
.date-display.placeholder { color: #C0C4CC; }

.shuoming-card { padding: 32rpx; }
.desc-text { font-size: 26rpx; color: #666; line-height: 1.6; margin-top: 20rpx; white-space: pre-wrap; }

.agreement-section { padding: 20rpx 10rpx; font-size: 26rpx; color: #666; }

.submit-btn-wrapper { padding: 40rpx 0; }
.submit-btn {
	color: #fff;
	height: 96rpx; line-height: 96rpx;
	border-radius: 48rpx;
	font-size: 32rpx;
	background-color: #ccc;
	&[disabled] { background-color: #ccc !important; color: #f7f7f7; }
}
</style>
