<template>
	<tui-page>
		<view style="height: 100vh;background-color: #fff;padding: 20rpx;">
			<textarea placeholder="请输入地址" v-model="text" cols="30" rows="10"
				style="width: 100%;padding: 20rpx;border: solid #f0f0f0 1rpx;border-radius: 10rpx;"></textarea>
			<view class="flex-y-center flex-x-center" style="margin-top: 30rpx;">
				<tui-button type="danger" width="500rpx" height="80rpx" :shadow="true" shape="circle"
					@click="analyse">识别内容</tui-button>
			</view>
			<view style="margin-top: 30rpx;" v-if="is_ok">
				<view class="flex-y-center" style="border-bottom: solid #f0f0f0 1rpx;height: 100rpx;">
					<view style="width:200rpx;">联系人</view>
					<view>{{form?form.person:''}}</view>
				</view>
				<view class="flex-y-center" style="border-bottom: solid #f0f0f0 1rpx;height: 100rpx;">
					<view style="width: 200rpx;">联系电话</view>
					<view>{{form?form.phonenum:''}}</view>
				</view>
				<view class="flex-y-center" style="border-bottom: solid #f0f0f0 1rpx;height: 100rpx;">
					<view style="width: 200rpx;">地址</view>
					<view>{{form?form.province+' '+form.city+' '+form.county+' '+form.town:''}}</view>
				</view>
				<view class="flex-y-center" style="margin-top: 10rpx;">
					<view style="width: 200rpx;">详细地址</view>
					<view>
						<textarea v-model="form.detail" cols="30" rows="3"
							style="width: 500rpx;border: solid #f0f0f0 1rpx;border-radius: 10rpx;height: 200rpx;padding: 10rpx;font-size: 10pt;color: #636363;"></textarea>
					</view>
				</view>
				<view class="flex-x-between" style="width: 70%;padding-top: 30rpx;margin: 0 auto;">
					<tui-button type="gray" width="200rpx" height="70rpx" shape="circle"
						@click="reset">重新编辑</tui-button>
					<tui-button type="danger" width="200rpx" height="70rpx" shape="circle"
						@click="save">确定保存</tui-button>
				</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				is_loading: false,
				text: '',
				is_ok: false,
				form: {
					city: "",
					city_code: "",
					county: "",
					county_code: "",
					detail: "",
					person: "",
					phonenum: "",
					province: "",
					province_code: "",
					text: "",
					town: "",
					town_code: "",
				}
			}
		},
		methods: {
			reset() {
				this.is_ok = false;
				this.text = '';
			},
			save() {
				if (!this.is_ok) {
					uni.showToast({
						title: '清先识别内容'
					})
					return;
				}
				if (this.is_loading) {
					return;
				}
				this.is_loading = true;
				this.$request({
					url: "/api/user/address-analyse-save",
					data: {
						content: JSON.stringify(this.form)
					},
					method: 'post'
				}).then(res => {
					this.is_loading=false;
					if (res.code == 0) {
						uni.navigateBack();
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg
						})
					}
				})



			},
			analyse() {
				if (!this.text) {
					uni.showToast({
						title: '请输入要识别的内容'
					})
					return;
				}
				if (this.is_loading) {
					return;
				}
				this.is_loading = true;

				this.$request({
					url: "/api/user/address-analyse",
					data: {
						text: this.text
					},
					method: 'post'
				}).then(res => {
					this.is_loading = false;
					if (res.code == 0) {
						this.form = res.data.result;
						this.is_ok = true;
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