<!--
  完善头像/昵称弹窗(后台开启「强制完善头像」时,个人中心各主题经 userTheme.checkAvatar 唤起)。
  用居中 tui-modal 而非 tui-bottom-popup:个人中心是 tabbar 页,小程序原生 tabBar 覆盖在页面之上,
  底部弹层的按钮会被 tabbar 遮住,居中弹窗天然规避。
-->
<template>
	<tui-modal :show="showModal" :custom="true" :maskClosable="true" @cancel="cancel"
		width="620rpx" padding="0" radius="32rpx" backgroundColor="#ffffff">
		<view class="uav" :style="themeStyle">

			<view class="uav-hd">
				<view class="uav-avatar">
					<!-- 微信小程序:官方头像选择 -->
					<!-- #ifdef MP-WEIXIN -->
					<button class="uav-avatar-btn" open-type="chooseAvatar" @chooseavatar="chooseavatar">
						<image :src="userInfo.avatar_url || fallbackAvatar" mode="aspectFill" class="uav-avatar-img" />
						<view class="uav-cam"><tui-icon name="camera-fill" :size="13" color="#ffffff"></tui-icon></view>
					</button>
					<!-- #endif -->
					<!-- 其它端:相册选图后上传 -->
					<!-- #ifndef MP-WEIXIN -->
					<view class="uav-avatar-btn" @click="chooseFromAlbum">
						<image :src="userInfo.avatar_url || fallbackAvatar" mode="aspectFill" class="uav-avatar-img" />
						<view class="uav-cam"><tui-icon name="camera-fill" :size="13" color="#ffffff"></tui-icon></view>
					</view>
					<!-- #endif -->
				</view>
				<view class="uav-title">完善个人资料</view>
				<view class="uav-sub">设置头像和昵称，获得更好的使用体验</view>
			</view>

			<view class="uav-field">
				<!-- #ifdef MP-WEIXIN -->
				<input class="uav-input" type="nickname" v-model="userInfo.nickname" placeholder="请输入昵称"
					placeholder-class="uav-ph" @blur="nicknameChange" />
				<!-- #endif -->
				<!-- #ifndef MP-WEIXIN -->
				<input class="uav-input" v-model="userInfo.nickname" placeholder="请输入昵称"
					placeholder-class="uav-ph" />
				<!-- #endif -->
			</view>

			<!-- 性别(采集开启才显示;已选过且后台设为「锁定」时整项隐藏,交由后台/客服改) -->
			<view class="uav-field uav-row" v-if="showGender">
				<text class="uav-label">性别</text>
				<view class="uav-radios">
					<view class="uav-radio" :class="{ on: userInfo.gender == 1 }" @click="userInfo.gender = 1">男</view>
					<view class="uav-radio" :class="{ on: userInfo.gender == 2 }" @click="userInfo.gender = 2">女</view>
				</view>
			</view>

			<!-- 生日(采集开启才显示;已填过且后台设为「锁定」时整项隐藏,交由后台/客服改) -->
			<view class="uav-field uav-row" v-if="showBirthday">
				<text class="uav-label">生日</text>
				<picker mode="date" :value="userInfo.birthday || defaultBirthday" :start="'1900-01-01'"
					:end="today" @change="onBirthdayChange">
					<view class="uav-picker">{{ userInfo.birthday || '请选择生日(选填)' }}</view>
				</picker>
			</view>
			<!-- 仅在「首次填写 + 后台设为锁定」时提示:提交后就改不了了 -->
			<view class="uav-tip" v-if="showBirthday && !birthdayEditable && !lockedBirthday">生日提交后不可自行修改,请谨慎填写</view>

			<view class="uav-actions">
				<view class="uav-btn ghost" hover-class="uav-btn-hover" @click="cancel">稍后设置</view>
				<view class="uav-btn primary" :class="{ disabled: saving }" hover-class="uav-btn-hover"
					@click="saveUserInfo">{{ saving ? '保存中...' : '保 存' }}</view>
			</view>

		</view>
	</tui-modal>
</template>

<script>
	import { chooseImageWithTip } from '@/utils/permissionTip.js';
	export default {
		name: 'UserProfileModal',
		props: {
			// 控制模态框显示/隐藏
			visible: {
				type: Boolean,
				default: false
			},
			// 用户信息对象
			userData: {
				type: Object,
				default: () => ({
					avatar_url: '',
					nickname: ''
				})
			},
			// 默认头像(不传就按 fallbackAvatar 的顺序兜底)
			defaultAvatar: {
				type: String,
				default: ''
			},

		},
		data() {
			return {
				// 内部状态，用于双向绑定
				userInfo: {
					avatar_url: '',
					nickname: '',
					gender: 0,
					birthday: ''
				},
				// 打开弹窗时的快照:记录此前【已采集到】的值,配合后台「可否自改」决定该项是否展示
				lockedBirthday: '',
				lockedGender: 0,
				showModal: this.visible,
				saving: false, // 保存中状态
				errorMsg: '' // 错误信息
			}
		},
		computed: {
			/**
			 * 用户还没头像时显示的图:调用方传的 → 后台「商城设置 > 其他设置 > 默认用户头像」→ 包里自带的。
			 * 原来兜底写死的是外站 CDN 上的示例头像,对方一删就成空白,也和商城配的默认头像对不上。
			 */
			fallbackAvatar() {
				return this.defaultAvatar || this.$util.getSetting('avatar_url', '') || '/static/icons/avatar.png';
			},
			// 是否采集生日/性别:商城设置开关(per-mall,默认关)
			collectBirthday() {
				return String(this.$util.getSetting('collect_birthday', '0')) === '1';
			},
			collectGender() {
				return String(this.$util.getSetting('collect_gender', '0')) === '1';
			},
			// 采集到之后能否由用户自行修改(per-mall 设置;默认沿用历史行为:生日锁、性别可改)
			birthdayEditable() {
				return String(this.$util.getSetting('birthday_editable', '0')) === '1';
			},
			genderEditable() {
				return String(this.$util.getSetting('gender_editable', '1')) === '1';
			},
			// 是否展示该项 = 开启采集 且 (此前没填过 或 后台允许自改)。
			// 「已填 + 锁定」直接整项隐藏(而非只读展示),改由后台/客服调整,与后端 UserHelper 拦截一致。
			showBirthday() {
				return this.collectBirthday && (!this.lockedBirthday || this.birthdayEditable);
			},
			showGender() {
				return this.collectGender && (!this.lockedGender || this.genderEditable);
			},
			today() {
				const d = new Date();
				const p = n => (n < 10 ? '0' + n : n);
				return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
			},
			defaultBirthday() {
				return '2000-01-01';
			}
		},
		watch: {
			// 监听外部传入的visible变化
			visible(val) {
				this.showModal = val;
				// 重置用户信息
				if (val) {
					this.resetUserInfo();
				}
			}
		},
		created() {
			this.resetUserInfo();
		},
		methods: {
			// 微信 nickname 输入框失焦：同步昵称到服务端(v-model 已更新 userInfo.nickname)
			nicknameChange() {
				this.setAttribute('nickname', this.userInfo.nickname);
			},

			// 重置用户信息
			resetUserInfo() {
				this.userInfo = {
					avatar_url: this.userData.avatar_url || '',
					nickname: this.userData.nickname || '',
					gender: this.userData.gender || 0,
					birthday: this.userData.birthday || ''
				};
				// 记录进入弹窗时【已采集到】的值 → 配合后台「可否自改」决定该项是否还展示
				this.lockedBirthday = this.userData.birthday || '';
				this.lockedGender = Number(this.userData.gender) || 0;
				this.errorMsg = '';
			},

			// 选择生日
			onBirthdayChange(e) {
				this.userInfo.birthday = e.detail.value;
			},

			// 微信小程序:官方头像选择回调
			chooseavatar(e) {
				this.uploadAvatar(e.detail.avatarUrl);
			},

			// 其它端:取图换头像。走 chooseImageWithTip —— 它会先让用户选「拍照/从相册」,
			// 再弹与该来源**对应**的权限说明(华为审核7.18)。
			// 原实现固定弹"相册说明"却又允许点「拍摄」去申请相机权限,正是被驳回的点。
			chooseFromAlbum() {
				chooseImageWithTip({
					count: 1,
					sizeType: ['compressed'],
					purpose: '更换头像',   // 让权限说明贴合当前场景,而不是通用的"拍照或扫码"
					success: (res) => {
						this.uploadAvatar(res.tempFilePaths[0]);
					}
				});
			},

			// 统一的头像上传(两端共用)
			uploadAvatar(filePath) {
				if (!filePath) return;
				const _this = this;
				const apiRoot = this.$http.getApiRoot();
				const header = {
					'x-mall-id': this.$http.getMallId(),
					'x-access-token': this.$http.getToken()
				};
				uni.showLoading({ title: '上传中...', mask: true });
				uni.uploadFile({
					url: apiRoot + '/api/upload/upload',
					name: 'file',
					header: header,
					filePath: filePath,
					success: function(res) {
						if (res.statusCode == 200) {
							const d = JSON.parse(res.data.replace(/\ufeff/g, "") || "{}");
							if (d.code === 0) {
								_this.userInfo.avatar_url = d.data.url;
							} else {
								uni.showToast({ title: d.msg || '头像上传失败', icon: 'none' });
							}
						} else {
							uni.showToast({ title: '头像上传失败', icon: 'none' });
						}
					},
					fail: function() {
						uni.showToast({ title: '头像上传异常', icon: 'none' });
					},
					complete: function() {
						uni.hideLoading();
					}
				});
			},

			setAttribute(key, value) {
				this.$request({
					url: "/api/user/set-user-info",
					data: {
						key: key,
						value: value,
					},
					method: 'post'
				}).then(e => {
					if (e.code == 1) {
						uni.showModal({
							title: '提示',
							content: e.msg
						})
					}
				})

			},

			// 取消设置
			cancel() {
				this.showModal = false;
				this.$emit('close');
			},

			// 保存用户信息
			saveUserInfo() {
				if (this.saving) return;
				// 表单验证
				if (!this.userInfo.nickname) {
					this.errorMsg = '请设置昵称';
					uni.showToast({
						title: '请设置昵称',
						icon: 'none'
					});
					return;
				}

				// 显示加载状态
				this.saving = true;
				// 性别/生日走通用资料接口(后端已做字段白名单 + 可否自改拦截);均为选填,失败不阻断主保存。
				// 只提交本次「展示出来的」项:被后台锁定而隐藏的项不提交,避免白发一次注定被拒的请求。
				if (this.showGender && this.userInfo.gender) {
					this.setAttribute('gender', this.userInfo.gender);
				}
				if (this.showBirthday && this.userInfo.birthday && this.userInfo.birthday !== this.lockedBirthday) {
					this.setAttribute('birthday', this.userInfo.birthday);
				}
				this.$request({
					url: "/api/user/set-avatar",
					data: {
						avatar_url: this.userInfo.avatar_url,
						nickname: this.userInfo.nickname
					},
					method: 'post'
				}).then(res => {
					this.saving = false;
					this.showModal = false;
					if (res.code == 0) {
						this.$emit('save', this.userInfo);
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg
						})
					}
				}).catch(() => {
					this.saving = false;
				})
			}
		}
	}
</script>

<style scoped>
	.uav {
		padding: 56rpx 40rpx 40rpx;
		box-sizing: border-box;
	}

	/* 头部：头像 + 标题 */
	.uav-hd {
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.uav-avatar {
		position: relative;
	}

	.uav-avatar-btn {
		width: 160rpx;
		height: 160rpx;
		padding: 0;
		margin: 0;
		border: none;
		background-color: transparent;
		position: relative;
		line-height: normal;
	}

	.uav-avatar-btn::after {
		border: none;
	}

	.uav-avatar-img {
		width: 160rpx;
		height: 160rpx;
		border-radius: 50%;
		background-color: #f5f6f8;
		border: 4rpx solid var(--theme-color-bg, #f2f2f2);
		box-shadow: 0 8rpx 24rpx var(--theme-shadow, rgba(0, 0, 0, 0.08));
	}

	/* 相机徽标：提示头像可点击更换 */
	.uav-cam {
		position: absolute;
		right: 0;
		bottom: 4rpx;
		width: 44rpx;
		height: 44rpx;
		border-radius: 50%;
		background-color: var(--theme-color, #D71F1F);
		border: 4rpx solid #ffffff;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.uav-title {
		font-size: 34rpx;
		font-weight: 600;
		color: #1a1a1a;
		margin-top: 28rpx;
	}

	.uav-sub {
		font-size: 24rpx;
		color: #9aa0a6;
		margin-top: 10rpx;
		text-align: center;
	}

	/* 昵称输入 */
	.uav-field {
		margin-top: 44rpx;
		height: 96rpx;
		background-color: #f7f8fa;
		border-radius: 20rpx;
		padding: 0 28rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		box-sizing: border-box;
	}

	/* 性别 / 生日行:左标签 + 右内容 */
	.uav-row {
		margin-top: 20rpx;
		justify-content: space-between;
	}

	.uav-label {
		font-size: 28rpx;
		color: #333;
		flex-shrink: 0;
	}

	.uav-radios {
		display: flex;
	}

	.uav-radio {
		min-width: 110rpx;
		height: 60rpx;
		line-height: 60rpx;
		text-align: center;
		border-radius: 12rpx;
		background: #fff;
		border: 1rpx solid #e5e5e5;
		font-size: 26rpx;
		color: #666;
		margin-left: 16rpx;
	}

	.uav-radio.on {
		border-color: var(--theme-color, #D71F1F);
		color: var(--theme-color, #D71F1F);
		background: var(--theme-color-bg, rgba(215, 31, 31, 0.08));
	}

	.uav-picker {
		font-size: 28rpx;
		color: #333;
		text-align: right;
		min-width: 260rpx;
	}

	.uav-locked {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
	}

	.uav-locked-val {
		font-size: 28rpx;
		color: #333;
	}

	.uav-locked-tip {
		font-size: 20rpx;
		color: #999;
		margin-top: 2rpx;
	}

	.uav-tip {
		font-size: 22rpx;
		color: #999;
		margin-top: 10rpx;
		padding-left: 8rpx;
	}

	/* 昵称居中,与上方头像/标题保持同一中轴 */
	.uav-input {
		flex: 1;
		height: 100%;
		font-size: 28rpx;
		color: #333333;
		text-align: center;
	}

	.uav-ph {
		color: #c0c4cc;
		text-align: center;
	}

	/* 按钮组 */
	.uav-actions {
		display: flex;
		margin-top: 44rpx;
	}

	.uav-btn {
		flex: 1;
		height: 88rpx;
		line-height: 88rpx;
		text-align: center;
		border-radius: 44rpx;
		font-size: 28rpx;
		font-weight: 500;
	}

	.uav-btn.ghost {
		background-color: #f2f3f5;
		color: #606266;
		margin-right: 24rpx;
	}

	.uav-btn.primary {
		color: #ffffff;
		background: linear-gradient(135deg, var(--theme-color, #D71F1F), var(--theme-color-light, #D71F1F));
		box-shadow: 0 10rpx 24rpx var(--theme-shadow, rgba(0, 0, 0, 0.15));
	}

	.uav-btn.primary.disabled {
		opacity: 0.6;
	}

	.uav-btn-hover {
		opacity: 0.88;
	}
</style>
