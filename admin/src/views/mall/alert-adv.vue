<template>
	<div class="app-container">
		<el-card>
			<div slot="header" class="clearfix">
				<span>活动弹窗配置</span>
				<el-button style="float: right; padding: 3px 0" type="primary" size="small"
					@click="save">保存设置</el-button>
			</div>

			<div class="container flex-row" style="display: flex; gap: 40px; align-items: flex-start; padding: 20px;">

				<div class="diy-component-preview">
					<div class="iphone-frame">
						<div class="iphone-notch">
							<div class="camera"></div>
							<div class="speaker"></div>
						</div>
						<div class="iphone-screen">
							<div class="screen-mask">
								<div class="img-container">
									<div style="position: relative; text-align: center; width: 100%; height: 100%;">
										<el-image :src="data.bg.url"
											style="margin: 0 auto; overflow: hidden; position: absolute; left: 50%; transform: translateX(-50%);"
											:style="{ top: data.bg.top + '%', height: data.bg.height + '%', borderRadius: data.bg.radius + 'px', width: data.bg.width + '%' }"
											fit="fill">
											<div slot="error" class="image-slot flex-y-center flex-x-center"
												style="height: 100%; background: #f5f7fa; color: #909399;">
												暂无弹窗图片
											</div>
										</el-image>
									</div>
								</div>

								<div style="z-index: 999; position: absolute; width: 100%; left: 0;"
									:style="{ top: data.btn.top + '%' }" class="flex-y-center flex-x-center">

									<div style="overflow: hidden; border: solid 1px;"
										:style="{ borderColor: data.btn.borderColor || '#f0f0f0', width: data.btn.width + '%', borderRadius: data.btn.radius + 'px', backgroundColor: data.btn.bgColor, color: data.btn.color, fontSize: (data.btn.fontSize / 2) + 'px', height: (data.btn.height / 2) + 'px', lineHeight: (data.btn.height / 2) + 'px', textAlign: 'center' }"
										v-if="data.btn.style == 0" @click="btnClick">
										{{ data.btn.text || '按钮文字' }}
									</div>

									<el-image :src="data.btn.btnImg"
										:style="{ width: data.btn.width + '%', borderRadius: data.btn.radius + 'px', height: (data.btn.height / 2) + 'px' }"
										fit="fill" v-if="data.btn.style == 1" @click="btnClick">
										<div slot="error" class="image-slot"
											style="height: 100%; background: #f5f7fa; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #909399;">
											按钮图片
										</div>
									</el-image>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="diy-component-edit" style="flex: 1; min-width: 500px;">
					<el-tabs v-model="activeTab" type="border-card">

						<el-tab-pane label="图片设置" name="image">
							<el-form label-width="120px" size="small">
								<el-form-item label="背景图片">
									<div class="add-image-btn" v-if="data.bg.url">
										<img :src="data.bg.url" alt=""
											style="width: 100%;height: 100%; object-fit: cover;">
										<span class="pic-del" @click="data.bg.url = ''"><i
												class="el-icon-close"></i></span>
									</div>
									<file-picker v-if="!data.bg.url" v-model="data.bg.url" width="88" height="88">
										<div class="add-image-btn upload-btn flex-x-center flex-y-center">
											<i class="el-icon-plus" style="font-size: 24px; color: #8c939d;"></i>
										</div>
									</file-picker>
									<div style="color: #909399; font-size: 12px; margin-top: 8px; line-height: 1.4;">
										建议上传比例为 3:4 或 4:5 的竖版图片（透明底更佳），例如 600×800 像素，以获得原生 App 般的高级展现效果。
									</div>
								</el-form-item>

								<el-form-item label="图片顶部位置">
									<el-slider show-input v-model="data.bg.top" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="图片高度">
									<el-slider show-input v-model="data.bg.height" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="图片宽度">
									<el-slider show-input v-model="data.bg.width" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="图片圆角">
									<el-slider show-input v-model="data.bg.radius" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="点击背景跳转">
									<el-switch v-model="data.bg.canClick" :active-value="1"
										:inactive-value="0"></el-switch>
								</el-form-item>
								<el-form-item label="背景图链接" v-if="data.bg.canClick == 1">
									<link-picker v-model="data.bg.link"></link-picker>
								</el-form-item>
							</el-form>
						</el-tab-pane>

						<el-tab-pane label="按钮设置" name="button">
							<el-form label-width="120px" size="small">
								<el-form-item label="按钮动作">
									<el-radio-group v-model="data.btn.type">
										<el-radio :label="0">关闭弹窗</el-radio>
										<el-radio :label="1">跳转链接</el-radio>
									</el-radio-group>
								</el-form-item>

								<el-form-item label="选择链接" v-if="data.btn.type == 1">
									<link-picker v-model="data.btn.url"></link-picker>
								</el-form-item>

								<el-form-item label="按钮样式">
									<el-radio-group v-model="data.btn.style">
										<el-radio :label="0">纯文字</el-radio>
										<el-radio :label="1">自定义图片</el-radio>
									</el-radio-group>
								</el-form-item>

								<template v-if="data.btn.style == 0">
									<el-form-item label="按钮文字">
										<el-input v-model="data.btn.text" placeholder="请输入按钮文字"
											style="width: 250px;"></el-input>
									</el-form-item>
									<el-form-item label="文字颜色">
										<el-color-picker v-model="data.btn.color"></el-color-picker>
									</el-form-item>
									<el-form-item label="按钮背景色">
										<el-color-picker v-model="data.btn.bgColor"></el-color-picker>
									</el-form-item>
									<el-form-item label="按钮边框颜色">
										<el-color-picker v-model="data.btn.borderColor"></el-color-picker>
									</el-form-item>
									<el-form-item label="按钮字体大小">
										<el-slider show-input v-model="data.btn.fontSize" :max="100"></el-slider>
									</el-form-item>
								</template>

								<template v-if="data.btn.style == 1">
									<el-form-item label="按钮图片">
										<div class="add-image-btn" v-if="data.btn.btnImg">
											<img :src="data.btn.btnImg" alt=""
												style="width: 100%;height: 100%; object-fit: cover;">
											<span class="pic-del" @click="data.btn.btnImg = ''"><i
													class="el-icon-close"></i></span>
										</div>
										<file-picker v-if="!data.btn.btnImg" v-model="data.btn.btnImg" width="88"
											height="88">
											<div class="add-image-btn upload-btn flex-x-center flex-y-center">
												<i class="el-icon-plus" style="font-size: 24px; color: #8c939d;"></i>
											</div>
										</file-picker>
									</el-form-item>
								</template>

								<el-form-item label="按钮顶部位置">
									<el-slider show-input v-model="data.btn.top" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="按钮高度">
									<el-slider show-input v-model="data.btn.height" :max="200"></el-slider>
								</el-form-item>
								<el-form-item label="按钮宽度">
									<el-slider show-input v-model="data.btn.width" :max="100"></el-slider>
								</el-form-item>
								<el-form-item label="按钮圆角">
									<el-slider show-input v-model="data.btn.radius" :max="100"></el-slider>
								</el-form-item>
							</el-form>
						</el-tab-pane>

						<el-tab-pane label="弹出设置" name="popup">
							<el-form label-width="120px" size="small">
								<el-form-item label="启用弹窗">
									<el-switch v-model="data.status" :active-value="1" :inactive-value="0"></el-switch>
								</el-form-item>
								<el-form-item label="弹出时机">
									<el-radio-group v-model="data.show_time">
										<el-radio :label="0">每天弹一次</el-radio>
										<el-radio :label="1">每次打开均弹</el-radio>
									</el-radio-group>
								</el-form-item>
								<el-form-item label="入场动画">
									<el-radio-group v-model="data.animation">
										<el-radio label="scale">果冻缩放</el-radio>
										<el-radio label="fade">渐显出现</el-radio>
									</el-radio-group>
								</el-form-item>
							</el-form>
						</el-tab-pane>

					</el-tabs>
				</div>
			</div>
		</el-card>
	</div>
</template>

<script>
export default {
	name: 'mall-alert-adv',
	data() {
		return {
			activeTab: 'image', // 默认选中的 Tab
			visible: false,
			showLink: false,
			data: {
				status: 0,
				show_time: 0,
				show: true,
				animation: 'scale',
				bg: {
					top: 15,
					height: 60,
					width: 80,
					url: '',
					radius: 15,
					canClick: 0,
					link: ''
				},
				btn: {
					type: 0,
					top: 80,
					width: 40,
					text: '我知道了',
					radius: 30,
					height: 80,
					color: '#000000',
					bgColor: '#FFFFFF',
					borderColor: '#FFFFFF',
					url: '',
					fontSize: 30,
					btnImg: '',
					style: 0,
				},
			},
		}
	},
	created() {
		this.getData();
	},
	methods: {
		getData() {
			this.$request({
				url: '/mall/mall/alert-adv',
			}).then(res => {
				if (res.code == 0) {
					if (res.data.alert_data) {
						this.data = res.data.alert_data
					}
				}
			})
		},
		save() {
			this.visible = false;
			this.$request({
				url: '/mall/mall/alert-adv',
				data: {
					data: JSON.stringify(this.data)
				},
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					this.$message.success(res.msg)
				}
			})
		},
		btnClick(e) { },
	}
}
</script>

<style lang="scss" scoped>
/* 辅助 Flex 布局类 */
.flex-row {
	display: flex;
	flex-direction: row;
}

.flex-x-center {
	display: flex;
	justify-content: center;
}

.flex-y-center {
	display: flex;
	align-items: center;
}

/* === iPhone 13 Pro 手机壳 UI === */
.iphone-frame {
	width: 312px;
	/* 390 的 80% */
	height: 675px;
	/* 844 的 80%，完美还原 13 Pro 修长比例 */
	border: 12px solid #333;
	border-radius: 45px;
	/* 13 Pro 的圆角更大更圆润 */
	position: relative;
	background-color: #ffffff;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
	box-sizing: content-box;
}

/* 窄刘海设计 */
.iphone-notch {
	position: absolute;
	top: 0;
	left: 50%;
	transform: translateX(-50%);
	width: 120px;
	height: 24px;
	background-color: #333;
	border-bottom-left-radius: 16px;
	border-bottom-right-radius: 16px;
	z-index: 1000;
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 8px;
}

/* 刘海里的听筒和摄像头小细节，让质感提升 */
.iphone-notch .speaker {
	width: 40px;
	height: 4px;
	background: #111;
	border-radius: 2px;
}

.iphone-notch .camera {
	width: 10px;
	height: 10px;
	background: #111;
	border-radius: 50%;
	box-shadow: inset 0 0 2px rgba(255, 255, 255, 0.2);
}

.iphone-screen {
	width: 100%;
	height: 100%;
	border-radius: 32px;
	/* 配合外壳的大圆角 */
	overflow: hidden;
	position: relative;
	background-color: #f5f5f5;
	/* 模拟底层页面背景 */
}

/* 模拟弹窗出现时的半透明黑底 */
.screen-mask {
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.65);
	position: relative;
}

.img-container {
	width: 100%;
	position: absolute;
	left: 0;
	top: 0;
	height: 100%;
}

/* === 上传图片框 UI 优化 === */
.add-image-btn {
	width: 88px;
	height: 88px;
	border-radius: 4px;
	position: relative;
	overflow: hidden;
	cursor: pointer;
	box-sizing: border-box;

	&.upload-btn {
		border: 1px dashed #d9d9d9;
		background-color: var(--admin-canvas);

		&:hover {
			border-color: var(--admin-accent-text);
		}
	}

	.pic-del {
		position: absolute;
		top: 0;
		right: 0;
		width: 20px;
		height: 20px;
		background: rgba(0, 0, 0, 0.5);
		color: #fff;
		display: flex;
		justify-content: center;
		align-items: center;
		border-bottom-left-radius: 4px;
		cursor: pointer;
	}
}
</style>