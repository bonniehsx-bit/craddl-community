<template>
	<view class="tui-container">
		<view class="tui-upload-box">
			<!-- 已上传的图片展示 -->
			<view class="tui-image-item" :style="{width: width, height: height}" v-if="imageUrl"
				@tap.stop="handleImageClick">
				<image :src="imageUrl" class="tui-item-img" :style="{width: width, height: height}" mode="aspectFill">
				</image>
				<view v-if="!forbidDel" class="tui-img-del" @tap.stop="delImage"></view>
				<!-- 上传状态遮罩 -->
				<view v-if="status!=1" class="tui-upload-mask">
					<view class="tui-upload-loading" v-if="status==2"></view>
					<text class="tui-tips">{{status==2?'上传中...':'上传失败'}}</text>
					<view class="tui-mask-btn" v-if="status==3" @tap.stop="reUpLoad" hover-class="tui-btn-hover"
						:hover-stay-time="150">重新上传</view>
				</view>
			</view>

			<!-- 添加图片的按钮 -->
			<view class="tui-upload-add" :style="{width: width, height: height}" v-if="!imageUrl && !forbidAdd"
				@tap="chooseImage">
				<slot>
					<view class="default-add-content">
						<tui-icon name="picture" size="20"></tui-icon>
						<view class="default-add-text">上传图片</view>
					</view>
				</slot>
			</view>
		</view>
		
		<!-- 权限申请与引导组件 -->
		<yk-authpup ref="authpup" type="top" @changeAuth="changeAuth" permissionID="CAMERA"></yk-authpup>
	</view>
</template>

<script>
	import ykAuthpup from "@/components/yk-authpup/yk-authpup";
	export default {
		components: {
			ykAuthpup
		},
		name: 'tuiUploadSingleImage',
		props: {
			// ... (Props部分与上一版相同，此处省略以保持简洁) ...
			width: { type: String, default: '160rpx' },
			height: { type: String, default: '160rpx' },
			value: { type: String, default: '' },
			is_show_preview: { type: Boolean, default: true },
			forbidDel: { type: Boolean, default: false },
			forbidAdd: { type: Boolean, default: false },
			serverUrl: { type: String, default: "/api/upload/upload" },
			sizeType: { type: Array, default: () => ['original', 'compressed']},
			size: { type: Number, default: 4 },
			fileKeyName: { type: String, default: "file" },
			header: { type: Object, default: () => ({}) },
			formData: { type: Object, default: () => ({}) }
		},
		data() {
			return {
				imageUrl: '',
				status: 1, // 1:正常, 2:上传中, 3:上传失败
				tempFilePath: '',
				currentSourceType: null // 暂存用户选择的图片来源, 用于权限申请后的回调
			}
		},
		watch: {
			value(val) {
				this.imageUrl = val;
			}
		},
		created() {
			this.imageUrl = this.value;
		},
		methods: {
			/**
			 * 点击已上传图片的操作菜单
			 */
			handleImageClick() {
				// ... (此方法与上一版相同，无需修改) ...
				if (!this.imageUrl) return;
				let itemList = [];
				if (this.is_show_preview) itemList.push('预览图片');
				if (!this.forbidAdd && !this.forbidDel) itemList.push('更换图片');
				if (itemList.length === 0) return;
				uni.showActionSheet({
					itemList,
					success: (res) => {
						const tappedItem = itemList[res.tapIndex];
						if (tappedItem === '预览图片') this.previewImage();
						else if (tappedItem === '更换图片') this.chooseImage();
					}
				});
			},

			/**
			 * 步骤1: 用户点击上传，弹出选择菜单
			 */
			chooseImage() {
				uni.showActionSheet({
					itemList: ['拍照', '从相册选择'],
					success: (res) => {
						let sourceType = res.tapIndex === 0 ? ['camera'] : ['album'];
						// 进入权限处理和图片选择的流程
						this._handleImageSelection(sourceType);
					}
				});
			},
			
			/**
			 * 【核心改造】
			 * 步骤2: 处理图片选择，包括权限检查和申请
			 * @param {Array} sourceType 图片来源, ['camera'] 或 ['album']
			 */
			_handleImageSelection(sourceType) {
				// 暂存 sourceType，以便在权限申请成功的回调中知道该执行拍照还是选相册
				this.currentSourceType = sourceType;

				// #ifdef APP-PLUS
				if (plus.os.name == 'Android') {
					// Android：先用自定义弹窗解释权限用途（合规要求），再申请系统权限
					const permissionMap = {
						'camera': 'CAMERA',
						'album': 'WRITE_EXTERNAL_STORAGE'
					};
					const permissionKey = sourceType.includes('camera') ? 'camera' : 'album';
					const permissionID = permissionMap[permissionKey];
					const permissionString = 'android.permission.' + permissionID;
					plus.android.checkPermission(permissionString, (e) => {
						if (e.checkResult === 1) {
							this._executeChooseImage(sourceType);
						} else {
							this.$refs.authpup.permissionID = permissionID;
							this.$refs.authpup.open();
						}
					}, (err) => {
						console.error('Check permission failed: ' + JSON.stringify(err));
						this.toast('权限检查失败');
					});
				} else {
					// iOS：必须直接调用 uni.chooseImage，由系统接管权限询问。
					// ⚠️ 切勿在此之前主动检测权限并跳转「设置」——否则首次用户（notDetermined 状态）
					// 在系统原生权限弹窗出现之前就被强制跳设置，会触发 App Store 5.1.1(iv) 拒审。
					// Info.plist 里已配 NSCameraUsageDescription / NSPhotoLibraryUsageDescription 说明文案，
					// 系统弹窗会自动展示这些文案，无需自定义弹窗。
					this._executeChooseImage(sourceType);
				}
				// #endif

				// #ifndef APP-PLUS
				// 对于非App平台（H5、小程序），没有复杂的权限问题，直接执行
				this._executeChooseImage(sourceType);
				// #endif
			},
			
			/**
			 * 【核心改造】
			 * 步骤3: 在 yk-authpup 组件授权成功后，此方法被回调
			 */
			changeAuth() {
				// 权限申请成功了，现在可以安全地执行图片选择了
				if (this.currentSourceType) {
					this._executeChooseImage(this.currentSourceType);
				}
			},

			/**
			 * 【封装】
			 * 步骤4: 真正执行 uni.chooseImage 的方法
			 * @param {Array} sourceType 图片来源
			 */
			_executeChooseImage(sourceType) {
				uni.chooseImage({
					count: 1,
					sizeType: this.sizeType,
					sourceType: sourceType,
					success: (res) => {
						const path = res.tempFiles[0].path;
						this.tempFilePath = path;

						if (this.serverUrl) {
							this.status = 2;
							this.uploadImage(path).then(url => {
								this.imageUrl = url; this.status = 1;
								this.$emit('input', this.imageUrl); this.$emit('change', this.imageUrl);
							}).catch(() => { this.status = 3; })
						} else {
							this.imageUrl = path; this.status = 1;
							this.$emit('input', this.imageUrl); this.$emit('change', this.imageUrl);
						}
					},
					fail: (err) => {
						console.log(err);
						const msg = (err && err.errMsg) ? err.errMsg : '';
						// 用户主动取消：静默，不打扰
						if (msg.indexOf('cancel') !== -1) {
							return;
						}
						// #ifdef APP-PLUS
						// iOS/Android：走到这且非取消，多半是权限被拒（系统弹窗已出现过、用户点了"不允许"）。
						// 此时引导去设置是合规的 —— 因为发生在用户操作之后、系统已询问过之后，
						// 不是抢在原生弹窗之前。
						uni.showModal({
							title: '无法访问相机/相册',
							content: '请在系统设置中允许本应用访问相机与相册后重试。',
							confirmText: '去设置',
							cancelText: '取消',
							success: (r) => {
								if (r.confirm && this.$refs.authpup && typeof this.$refs.authpup.goSetting === 'function') {
									this.$refs.authpup.goSetting();
								}
							}
						});
						return;
						// #endif
						// 非 App 平台（H5/小程序）：普通失败提示
						this.toast('选择图片失败');
					}
				});
			},

			// ... (uploadImage, delImage, previewImage, toast, reUpLoad 等方法与上一版相同，无需修改) ...
			uploadImage(filePath) {
				return new Promise((resolve, reject) => {
					let apiRoot = this.$http.getApiRoot();
					let fullHeader = Object.assign({}, this.header, {
						'x-mall-id': this.$http.getMallId(),
						'x-access-token': this.$http.getToken()
					});
					uni.uploadFile({
						url: apiRoot + this.serverUrl,
						name: this.fileKeyName,
						header: fullHeader,
						formData: this.formData,
						filePath,
						success: (res) => {
							if (res.statusCode == 200) {
								let d = JSON.parse(res.data.replace(/\ufeff/g, "") || "{}");
								if (d.code === 0 && d.data && d.data.url) {
									resolve(d.data.url);
								} else {
									this.toast(d.msg || '上传失败'); reject();
								}
							} else {
								this.toast('服务器响应异常'); reject();
							}
						},
						fail: (err) => {
							this.toast('网络请求失败'); reject(err);
						}
					})
				})
			},
			delImage() {
				this.imageUrl = ''; this.$emit("input", '');
				this.$emit("change", ''); this.$emit("remove");
			},
			previewImage() {
				uni.previewImage({ current: this.imageUrl, urls: [this.imageUrl] });
			},
			toast(text) {
				text && uni.showToast({ title: text, icon: "none" });
			},
			reUpLoad() {
				this.status = 2;
				this.uploadImage(this.tempFilePath).then(url => {
					this.imageUrl = url; this.status = 1;
					this.$emit('input', this.imageUrl); this.$emit('change', this.imageUrl);
				}).catch(() => { this.status = 3; })
			},
		}
	}
</script>

<style scoped>
	/* 样式部分未做修改，保持原样 */
	.tui-upload-box { width: 100%; display: flex; flex-wrap: wrap; }
	.tui-upload-add { /* #ifdef H5 */ cursor: pointer; /* #endif */ font-weight: 100; color: #888; background-color: #F7F7F7; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 8rpx; }
	.default-add-content { text-align: center; }
	.default-add-text { font-size: 9pt; }
	.tui-image-item { position: relative; margin-right: 20rpx; margin-bottom: 20rpx; }
	.tui-item-img { display: block; border-radius: 8rpx; }
	.tui-img-del { width: 36rpx; height: 36rpx; position: absolute; right: -18rpx; top: -18rpx; background-color: #EB0909; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; z-index: 5; }
	.tui-img-del::before { content: ''; width: 20rpx; height: 2rpx; position: absolute; background-color: #fff; transform: rotate(45deg); }
	.tui-img-del::after { content: ''; width: 20rpx; height: 2rpx; position: absolute; background-color: #fff; transform: rotate(-45deg); }
	.tui-upload-mask { width: 100%; height: 100%; position: absolute; left: 0; top: 0; display: flex; flex-direction: column; align-items: center; justify-content: space-around; padding: 40rpx 0; box-sizing: border-box; background-color: rgba(0, 0, 0, 0.6); border-radius: 8rpx; z-index: 3; }
	.tui-upload-loading { width: 28rpx; height: 28rpx; border-radius: 50%; border: 2px solid; border-color: #B2B2B2 #B2B2B2 #B2B2B2 #fff; animation: tui-rotate 0.7s linear infinite; }
	@keyframes tui-rotate { 0% { transform: rotate(0); } 100% { transform: rotate(360deg); } }
	.tui-tips { font-size: 26rpx; color: #fff; }
	.tui-mask-btn { padding: 4rpx 16rpx; border-radius: 40rpx; text-align: center; font-size: 24rpx; color: #fff; border: 1rpx solid #fff; }
</style>