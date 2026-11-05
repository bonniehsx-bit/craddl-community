<template>
	<div class="app-container hot-image-container">
		<div class="diy-header">
			<div class="header-left">
				<span class="title">热图编辑 / {{ form.name || '未命名' }}</span>
			</div>
			<div class="header-right">
				<el-button size="small" @click="$go.back()">返回</el-button>
				<el-button size="small" type="primary" :loading="is_loading" @click="save">保存</el-button>
			</div>
		</div>

		<div class="diy-main">
			<!-- 中间：手机预览区 -->
			<div class="diy-center">
				<div class="phone-shell">
					<!-- 顶部刘海 (模拟) -->
					<div class="notch"></div>

					<!-- 顶部状态栏 (沉浸式) -->
					<div class="phone-status-bar">
						<div class="time">12:00</div>
						<div class="status-icons">
							<i class="el-icon-connection"></i>
							<i class="el-icon-s-opportunity"></i>
						</div>
					</div>

					<!-- 内容区域 -->
					<div class="phone-body">
						<!-- 核心：OperationFloor (画布) -->
						<!-- 
                       注意：OperationFloor 内部是 750px，
                       我们需要在这里或者 OperationFloor 内部进行缩放适配 
                    -->
						<div class="canvas-wrapper">
							<OperationFloor v-model="form" />
						</div>
					</div>

					<!-- 底部 Home Indicator -->
					<div class="home-indicator"></div>
				</div>
			</div>

			<!-- 右侧：属性配置区 -->
			<div class="diy-right">
				<div class="config-panel">
					<div class="panel-title">热图设置</div>
					<div class="panel-content">
						<el-form label-width="70px" size="small" label-position="top">
							<el-form-item label="名称">
								<el-input v-model="form.name" placeholder="请输入热图名称"></el-input>
							</el-form-item>
							<el-form-item label="背景图 (宽度750px)">
								<single-image-selector v-model="form.pic_url"></single-image-selector>

							</el-form-item>
							<div class="tip">提示：上传背景图后，在左侧手机屏幕上框选区域即可添加热区。</div>
						</el-form>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
import OperationFloor from '@/components/diy/HotImage/OperationFloor'
// 假设 file-picker 是全局注册或需要引入，原代码中有使用
// import FilePicker from '@/components/file-picker' 

export default {
	name: 'hot-image',
	components: {
		OperationFloor
	},
	data() {
		return {
			is_loading: false,
			form: {
				id: '',
				name: '',
				data: [],
				pic_url: '',
			}
		}
	},
	created() {
		if (this.$route.query.id) {
			this.form.id = this.$route.query.id;
			this.search();
		}
	},
	methods: {
		handleSelectImage() {
			// 触发 file-picker 的选择
			// 这里需要确认 file-picker 的用法，原代码是用 slot
		},
		save() {
			if (!this.form.name) return this.$message.warning('请输入名称');
			if (!this.form.pic_url) return this.$message.warning('请上传背景图');

			this.is_loading = true;
			this.$request({
				url: "/mall/mall/hot-image",
				data: this.form,
				method: 'post'
			}).then(res => {
				this.is_loading = false;
				if (res.code == 0) {
					this.$message.success(res.msg);
					this.$go.back();
				}
			}).catch(e => {
				this.is_loading = false;
			})
		},
		search() {
			this.is_loading = true;
			this.$request({
				url: "/mall/mall/hot-image",
				data: { id: this.form.id }, // Fix: search usually needs id
			}).then(res => {
				this.is_loading = false;
				if (res.code == 0) {
					// 合并数据
					this.form = Object.assign({}, this.form, res.data.info);
				}
			}).catch(e => {
				this.is_loading = false;
			})
		},
	}
}
</script>

<style scoped lang="scss">
.hot-image-container {
	height: calc(100vh - 50px - var(--tags-view-height, 34px));
	display: flex;
	flex-direction: column;
	padding: 0 !important;
	background-color: #f0f2f5;
	overflow: hidden;
}

.diy-header {
	height: 56px;
	background: #fff;
	border-bottom: 1px solid #e0e0e0;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 0 20px;
	flex-shrink: 0;
	z-index: 100;

	.title {
		font-weight: 600;
		color: #333;
	}
}

.diy-main {
	flex: 1;
	display: flex;
	overflow: hidden;
}

/* 中间手机 shell */
.diy-center {
	flex: 1;
	background: #f0f2f5;
	display: flex;
	justify-content: center;
	padding: 40px 0;
	overflow-y: auto;

	.phone-shell {
		width: 375px;
		height: 760px;
		background: #fff;
		border-radius: 40px;
		border: 10px solid #2c3e50;
		box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
		position: relative;
		display: flex;
		flex-direction: column;
		margin-bottom: 50px;
		overflow: hidden;
		/* 缩放适配小屏 */
		transform: scale(0.85);
		transform-origin: top center;
		margin-bottom: -100px;

		/* 刘海 */
		.notch {
			position: absolute;
			top: 0;
			left: 50%;
			transform: translateX(-50%);
			width: 150px;
			height: 30px;
			background: #2c3e50;
			border-bottom-left-radius: 16px;
			border-bottom-right-radius: 16px;
			z-index: 100;
		}

		.phone-status-bar {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 44px;
			padding: 0 20px;
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
			padding-bottom: 8px;
			z-index: 90;
			color: #333;
			background: transparent;
			pointer-events: none;

			.time {
				font-weight: 600;
				font-size: 14px;
			}

			.status-icons {
				display: flex;
				gap: 6px;

				i {
					font-size: 16px;
					font-weight: bold;
				}
			}
		}

		.phone-body {
			flex: 1;
			background: #F5F7FA;
			position: relative;
			overflow-y: auto;

			&::-webkit-scrollbar {
				display: none;
			}

			-ms-overflow-style: none;
			scrollbar-width: none;

			.canvas-wrapper {
				min-height: 100%;
				/* 为 OperationFloor 提供容器，OperationFloor 内部需要缩放 */
			}
		}

		.home-indicator {
			position: absolute;
			bottom: 8px;
			left: 0;
			width: 100%;
			height: 20px;
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 100;
			pointer-events: none;

			&::after {
				content: '';
				width: 130px;
				height: 5px;
				background: #000;
				border-radius: 3px;
				opacity: 0.2;
			}
		}
	}
}

/* 右侧配置 */
.diy-right {
	width: 340px;
	background: #fff;
	border-left: 1px solid #e0e0e0;
	display: flex;
	flex-direction: column;
	z-index: 10;

	.config-panel {
		height: 100%;
		display: flex;
		flex-direction: column;

		.panel-title {
			height: 50px;
			line-height: 50px;
			padding: 0 20px;
			font-weight: bold;
			background: #f9f9fa;
			border-bottom: 1px solid #eee;
		}

		.panel-content {
			padding: 20px;
			flex: 1;
			overflow-y: auto;
		}
	}
}

.bg-uploader {
	width: 100%;
	height: 150px;
	border: 1px dashed #d9d9d9;
	border-radius: 6px;
	cursor: pointer;
	position: relative;
	overflow: hidden;

	&:hover {
		border-color: var(--admin-accent-text);
	}

	.uploader-icon {
		font-size: 28px;
		color: #8c939d;
		width: 100%;
		height: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.bg-img {
		width: 100%;
		height: 100%;
		object-fit: contain;
		background: #f5f7fa;
	}
}

.re-upload-btn {
	font-size: 12px;
	color: #F56C6C;
	margin-top: 5px;
	cursor: pointer;
	text-align: right;
}

.tip {
	margin-top: 20px;
	font-size: 12px;
	color: #909399;
	line-height: 1.5;
}
</style>
