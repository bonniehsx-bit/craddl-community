<template>
	<view style="background-color: #fff;padding: 20rpx;overflow: hidden;box-sizing: border-box;">

		<view class="bank-form">
			<view class="form-row"><text class="form-label">身份证号码</text><view class="form-control">
				<uni-easyinput v-model="form.id_card" placeholder="身份证号码"></uni-easyinput>
			</view></view>
			<view class="form-row"><text class="form-label">是否长期有效</text><view class="form-control">
				<radio-group @change="(e) => form.is_validity = Number(e.detail.value)">
					<label style="margin-left:15px;display:inline-flex;align-items:center;">
						<radio :value="0" :checked="form.is_validity === 0" color="#0BDA6B" />
						<text style="font-size:28rpx;">否</text>
					</label>
					<label style="margin-left:15px;display:inline-flex;align-items:center;">
						<radio :value="1" :checked="form.is_validity === 1" color="#0BDA6B" />
						<text style="font-size:28rpx;">是</text>
					</label>
				</radio-group>
			</view></view>
			<view class="form-row"><text class="form-label">有效期开始日期</text><view class="form-control">
				<view @click="showCalendar('begin_date')" style="width: 100%;">
					<uni-easyinput v-model="form.begin_date" placeholder="有效期开始时间"></uni-easyinput>
				</view>

			</view></view>
			<view class="form-row" v-if="form.is_validity==0"><text class="form-label">有效期结束日期</text><view class="form-control">
				<view @click="showCalendar('end_date')" style="width: 100%;">
					<uni-easyinput v-model="form.end_date" placeholder="有效期结束日期"></uni-easyinput>
				</view>
			</view></view>
			<view class="form-row"><text class="form-label">身份证正面</text><view class="form-control">
				<tui-upload-single-image :url="form.id_card_pic1" v-model="form.id_card_pic1">
					<view>上传身份证正面</view>
				</tui-upload-single-image>
			</view></view>
			<view class="form-row"><text class="form-label">身份证背面</text><view class="form-control">
				<tui-upload-single-image :url="form.id_card_pic2" v-model="form.id_card_pic2">
					<view>上传身份证背面</view>
				</tui-upload-single-image>
			</view></view>
			<view class="form-row"><text class="form-label">持卡人</text><view class="form-control">
				<uni-easyinput v-model="form.name" placeholder="持卡人"></uni-easyinput>
			</view></view>
			<view class="form-row"><text class="form-label">银行卡号</text><view class="form-control">
				<uni-easyinput v-model="form.account" placeholder="卡号"></uni-easyinput>
			</view></view>
			<view class="form-row"><text class="form-label">银行卡正面</text><view class="form-control">
				<tui-upload-single-image :url="form.bank_card_pic1" v-model="form.bank_card_pic1">
					<view>上传银行卡正面</view>
				</tui-upload-single-image>
			</view></view>
			<view class="form-row"><text class="form-label">银行卡背面</text><view class="form-control">
				<tui-upload-single-image :url="form.bank_card_pic2" v-model="form.bank_card_pic2">
					<view>上传银行卡背面</view>
				</tui-upload-single-image>
			</view></view>
			<view class="form-row"><text class="form-label">银行预留手机号</text><view class="form-control">
				<uni-easyinput v-model="form.mobile" placeholder="预留手机号"></uni-easyinput>
			</view></view>
			<view class="form-row"><text class="form-label">开户行</text><view class="form-control">
				<uni-data-select v-model="form.bank_code" :localdata="bank_list" v-if="bank_list.length>0"
					style="width:460rpx;" @change="change"></uni-data-select>
			</view></view>
			<view class="form-row"><text class="form-label">支行名称</text><view class="form-control">
				<uni-easyinput v-model="form.bank_branch_name" placeholder="支行名称"></uni-easyinput>
			</view></view>
			<view class="form-row"><text class="form-label">开户行所在地区</text><view class="form-control">
				<tui-address-picker @selected="addrSelected">

					<view v-if="form.province_name">
						{{form.province_name}}-{{form.city_name}}-{{form.area_name}}
					</view>
					<view v-else style="color: #666;">
						请选择开户行所在地区
					</view>
				</tui-address-picker>
			</view></view>

		</view>


		<view class="flex-x-center" style="padding-top: 30rpx;">
			<tui-button @click="submit" type="danger" shape="circle" width="550rpx" height="75rpx">提交</tui-button>
		</view>

		<uni-calendar :insert="false" ref="calendar" :lunar="true" @confirm="confirm" />
	</view>
</template>

<script>
	export default {
		data() {
			return {
				current: '',
				show: false,
				mode: 'single',
				bank_list: [],
				form: {
					name: '',
					account: '',
					bank_name: '',
					bank_branch_name: '',
					bank_code: '',
					id_card: '',
					mobile: '',
					is_validity: 0,
					begin_date: '',
					end_date: '',
					bank_card_pic2: '',
					bank_card_pic1: '',
					id_card_pic1: '',
					id_card_pic2: '',
					provice_code: '',
					city_code: '',
					area_code: '',
					provice_name: '',
					city_name: '',
					area_name: '',
				}
			}
		},
		onLoad() {
			this.getBank();
		},
		methods: {
			addrSelected(e) {
				this.form.province_name = e.province_name;
				this.form.city_name = e.city_name;
				this.form.area_name = e.county_name;
				this.form.province_code = e.province_code;
				this.form.city_code = e.city_code;
				this.form.area_code = e.county_code;

			},
			showCalendar(current) {
				this.$refs.calendar.open();
				this.current = current;

			},
			confirm(e) {
				if (this.current == 'begin_date') {
					this.form.begin_date = e.fulldate;
				} else {
					this.form.end_date = e.fulldate;
				}

				this.show = false;
			},
			change(e) {
				this.form.bank_code = e;
				this.form.bank_name = "";
				this.bank_list.forEach(item => {
					if (item.code == this.form.bank_code) {
						this.form.bank_name = item.name;
						console.log(this.form.bank_name);
					}
				})
			},
			getBank() {
				this.$request({
					url: "/api/bank/bank"
				}).then(res => {
					if (res.data.bank) {

						this.form = res.data.bank;
					}
					this.bank_list = res.data.list;
					this.bank_list.forEach(item => {
						item.text = item.name;
						item.value = item.code;
					})
				})

			},
			submit() {
				let title = "";
				if (!this.form.name) {
					title = '姓名不能为空';
				}
				if (!this.form.id_card) {
					title = '身份证号码不能为空';
				}
				if (!this.form.account) {
					title = "银行卡号不能为空";
				}
				if (title != '') {
					uni.showModal({
						title: '提示',
						content: title
					})
					return;
				}
				this.$request({
					url: "/api/bank/bank",
					data: this.form,
					method: 'post'
				}).then(res => {
					if (res.code == 0) {
						uni.showModal({
							title: '提示',
							content: res.msg,
							showCancel: false
						})
					}
				})
			}

		}
	}
</script>

<style>
.bank-form { width: 100%; }
.form-row {
	display: flex;
	align-items: center;
	padding: 24rpx 0;
	border-bottom: 1rpx solid #f0f0f0;
}
.form-row:last-child { border-bottom: none; }
.form-label {
	width: 220rpx;
	color: #666;
	font-size: 28rpx;
	flex-shrink: 0;
	text-align: right;
	padding-right: 20rpx;
}
.form-control {
	flex: 1;
	min-width: 0;
}
</style>