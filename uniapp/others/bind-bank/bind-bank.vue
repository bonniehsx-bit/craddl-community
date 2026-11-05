<template>
	<view class="" style="background-color: #fff;padding: 20rpx;box-sizing: border-box;">
		<uni-forms :modelValue="form">
			<uni-forms-item label="真实姓名" name="payer_name">
				<uni-easyinput type="text" v-model="form.payer_name" placeholder="请输入真实姓名" />
			</uni-forms-item>
			<uni-forms-item label="手机号码" name="mobile_no">
				<uni-easyinput type="text" v-model="form.mobile_no" placeholder="请输入手机号" />
			</uni-forms-item>
			<uni-forms-item label="身份证号" name="id_no">
				<uni-easyinput type="text" v-model="form.id_no" placeholder="请输入身份证号" />
			</uni-forms-item>
			<uni-forms-item label="银行卡号" name="bank_card_no">
				<uni-easyinput type="text" v-model="form.bank_card_no" placeholder="请输入银行卡号" />
			</uni-forms-item>
			<uni-forms-item label="验证码" name="sms_code">
				<uni-easyinput type="text" v-model="form.sms_code" placeholder="请输入短信验证码" />
			</uni-forms-item>
		</uni-forms> 
		<view class="flex-x-center">
			<tui-button v-if="is_send==false" width="350rpx" size="20" height="70rpx" type="danger"
				@click="getSmsCode">获取银行短信</tui-button>
			<tui-button v-if="is_send" width="350rpx" size="20" height="70rpx" type="danger"
				@click="submit">签约</tui-button>
		</view>

		<view style="padding: 30rpx;box-sizing: border-box;margin-top: 30rpx;font-size: 9pt;color: #7f7f7f;">
			<view style="background-color: #f0f0f0;border-radius: 10rpx;padding: 20rpx;">
				<view> 温馨提示：仅支持以下银行卡</view>
				<view>
					工商银行
				</view>
				<view>
					中信银行
				</view>
				<view>
					上海银行
				</view>
				<view>
					光大银行
				</view>
				<view>
					民生银行
				</view>
				<view>
					交通银行
				</view>
				<view>
					广发银行
				</view>
				<view>
					建设银行
				</view>
				<view>
					浦发银行
				</view>
				<view>
					邮储银行
				</view>
				<view>
					广州农商银行
				</view>
			</view>

		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				is_loading: false,
				is_send: false,
				form: {
					mobile_no: '',
					payer_name: '',
					bank_card_no: '',
					id_no: '',
					sms_code: '',
				}
			}
		},
		methods: {
			submit() {
				if (this.form.sms_code == '') {
					uni.showModal({
						title: '提示',
						content: '请输入短信验证码'
					})
					return;
				}
				if (this.is_loading) {
					return;
				}
				this.is_loading = true;
				uni.showLoading({
					title: '正在签约'
				})
				this.$request({
					url: "/api/join/sms-sign",
					data: this.form,
					method: 'post'
				}).then(res => {
					this.is_loading = false;
					uni.hideLoading();
					if (res.code == 0) {
						uni.showModal({
							title: '提示',
							content: res.msg,
							success: (e) => {
								uni.navigateBack();
							}
						})
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg
						})
					}
				})
			},
			getSmsCode() {
				let title = '';
				if (this.form.mobile_no == '') {
					title = '请填写手机号码';
				}
				if (this.form.payer_name == '') {
					title = '请填写真实姓名';
				}
				if (this.form.bank_card_no == '') {
					title = '请填写银行卡号';
				}
				if (this.form.id_no == '') {
					title = '请填写身份证号';
				}
				if (title != '') {
					uni.showModal({
						title: '提示',
						content: title
					})
					return;
				}
				if (this.is_loading) {
					return;
				}
				this.is_loading = true;
				uni.showLoading({
					title: '正在获取签约短信'
				})
				this.$request({
					url: "/api/join/sign-sms",
					data: this.form,
					method: 'post'
				}).then(res => {
					this.is_loading = false;
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({
							title: res.msg
						})
						this.is_send = true;
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg
						})
					}
				})
			},
		}
	}
</script>

<style>

</style>