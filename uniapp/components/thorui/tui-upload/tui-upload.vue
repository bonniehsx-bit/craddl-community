<template>
	<view class="tui-container">
		<view class="tui-upload-box">
			<view v-for="(item,index) in imageList" :key="item"
				:class="{'is-dragging': isDragging && index === dragIndex}" 
				class="tui-image-item" 
				:style="{width: width, height: height}" 
				@tap.stop="previewImage(index)"
				@touchstart="onTouchStart($event, index)"
				@touchmove.stop.prevent="onTouchMove"
				@touchend="onTouchEnd"
				@touchcancel="onTouchEnd">
				
				<view v-if="isDragging && index === placeholderIndex" class="tui-placeholder-item" :style="{width: width, height: height}"></view>

				<image :src="item" class="tui-item-img" :style="{width: width, height: height}" mode="aspectFill"></image>
				
				<view v-if="!forbidDel" class="tui-img-del" @tap.stop="delImage(index)">
					<view class="del-icon"></view>
				</view>
				
				<view v-if="statusArr[index]!=1" class="tui-upload-mask">
					<view class="tui-upload-loading" v-if="statusArr[index]==2"></view>
					<text class="tui-tips">{{statusArr[index]==2?'上传中...':'上传失败'}}</text>
					<view class="tui-mask-btn" v-if="statusArr[index]==3" @tap.stop="reUpLoad(index)"
						hover-class="tui-btn-hover" :hover-stay-time="150">重新上传</view>
				</view>
			</view>
			
			<view v-if="isShowAdd" class="tui-upload-add" :style="{width: width, height: height}" @tap="chooseImage">
				<slot name="add">
					<view class="default-add-btn">
						<view class="plus-line-h"></view>
						<view class="plus-line-v"></view>
					</view>
				</slot>
			</view>
		</view>

		<image v-if="isDragging" class="tui-ghost-item" 
			:style="{width: draggedItemInfo.width, height: draggedItemInfo.height, top: draggedItemInfo.top, left: draggedItemInfo.left}"
			:src="draggedItemInfo.src"
			mode="aspectFill">
		</image>

		<yk-authpup ref="authpup" type="top" @changeAuth="changeAuth" permissionID="CAMERA"></yk-authpup>
	</view>
</template>

<script>
	import ykAuthpup from "@/components/yk-authpup/yk-authpup";
	import { chooseImageWithTip } from '@/utils/permissionTip.js';
	export default {
		components: {
			ykAuthpup
		},
		name: 'tuiUpload',
		props: {
			// 组件的宽度
			width: {
				type: String,
				default: '180rpx'
			},
			// 组件的高度
			height: {
				type: String,
				default: '180rpx'
			},
			// 组件的唯一标识
			index: {
				type: Number,
				default: -1,
			},
			// v-model绑定的图片路径数组或字符串
			value: {
				type: [Array, String],
				default () {
					return []
				}
			},
			// 禁用删除
			forbidDel: {
				type: Boolean,
				default: false
			},
			// 禁用添加
			forbidAdd: {
				type: Boolean,
				default: false
			},
			// 服务器上传地址
			serverUrl: {
				type: String,
				default: "/api/upload/upload"
			},
			// 最大上传数量
			limit: {
				type: Number,
				default: 9
			},
			// 图片压缩类型
			sizeType: {
				type: Array,
				default () {
					return ['original', 'compressed']
				}
			},
			// 图片来源
			sourceType: {
				type: Array,
				default () {
					return ['album', 'camera']
				}
			},
			// 允许的图片格式
			imageFormat: {
				type: Array,
				default () {
					return []
				}
			},
			// 单张图片大小限制 (MB)
			size: {
				type: Number,
				default: 4
			},
			// 上传文件对应的 key
			fileKeyName: {
				type: String,
				default: "file"
			},
			// HTTP 请求 Header
			header: {
				type: Object,
				default () {
					return {}
				}
			},
			// HTTP 请求中其他额外的 form data
			formData: {
				type: Object,
				default () {
					return {}
				}
			}
		},
		data() {
			return {
				imageList: [],
				statusArr: [],
				// --- 拖拽排序所需的状态 ---
				isDragging: false,
				dragIndex: -1,
				placeholderIndex: -1,
				draggedItemInfo: {},
				gridItemRects: [],
				initialTouch: {},
				longPressTimer: null, // 用于模拟长按的计时器
				hasMoved: false,      // 用于判断触摸期间是否发生过移动
			}
		},
		watch: {
			value(val) {
				// 避免在拖拽过程中因父组件v-model更新导致列表重置
				if (!this.isDragging) {
					this.initImages(val);
				}
			}
		},
		created() {
			this.initImages(this.value);
		},
		computed: {
			isShowAdd() {
				if (this.forbidAdd || (this.limit && this.imageList.length >= this.limit)) {
					return false;
				}
				return true;
			}
		},
		methods: {
			initImages(val) {
				val = val || [];
				let images = Array.isArray(val) ? val : (val ? [val] : []);
				this.imageList = [...images];
				this.statusArr = this.imageList.map(() => "1");
				// 列表变化后，在下一帧重新计算所有格子的位置信息
				this.$nextTick(() => {
					this._calculateGridPositions();
				});
			},

			// --- [核心] 拖拽排序事件处理模型 ---

			_calculateGridPositions() {
				const query = uni.createSelectorQuery().in(this);
				query.selectAll('.tui-image-item').boundingClientRect(data => {
					if (data) {
						this.gridItemRects = data;
					}
				}).exec();
			},
			
			onTouchStart(e, index) {
				if (this.forbidDel) return;

				this.hasMoved = false;
				const touch = e.touches[0] || e.changedTouches[0];
				this.initialTouch = { x: touch.clientX, y: touch.clientY };

				// 启动长按计时器
				this.longPressTimer = setTimeout(() => {
					if (!this.hasMoved) {
						this.isDragging = true;
						this.dragIndex = index;
						this.placeholderIndex = index;
						
						// #ifndef H5
						uni.vibrateShort();
						// #endif
						
						const currentRect = this.gridItemRects[index];
						this.draggedItemInfo = {
							src: this.imageList[index],
							width: currentRect.width + 'px',
							height: currentRect.height + 'px',
							top: currentRect.top + 'px',
							left: currentRect.left + 'px',
						};
					}
				}, 350);
			},
			
			onTouchMove(e) {
				const touch = e.touches[0] || e.changedTouches[0];
				
				const deltaX = Math.abs(touch.clientX - this.initialTouch.x);
				const deltaY = Math.abs(touch.clientY - this.initialTouch.y);
				
				if (deltaX > 5 || deltaY > 5) {
					this.hasMoved = true;
					if (this.longPressTimer) {
						clearTimeout(this.longPressTimer);
						this.longPressTimer = null;
					}
				}
				
				if (!this.isDragging) return;
				
				const moveX = touch.clientX - this.initialTouch.x;
				const moveY = touch.clientY - this.initialTouch.y;
				const initialRect = this.gridItemRects[this.dragIndex];
				
				this.draggedItemInfo.left = (initialRect.left + moveX) + 'px';
				this.draggedItemInfo.top = (initialRect.top + moveY) + 'px';
				
				let targetIndex = -1;
				for (let i = 0; i < this.gridItemRects.length; i++) {
					const rect = this.gridItemRects[i];
					if (touch.clientX > rect.left && touch.clientX < rect.right &&
						touch.clientY > rect.top && touch.clientY < rect.bottom) {
						targetIndex = i;
						break;
					}
				}

				if (targetIndex > -1 && targetIndex !== this.placeholderIndex) {
					this.placeholderIndex = targetIndex;
				}
			},

			onTouchEnd(e) {
				if (this.longPressTimer) {
					clearTimeout(this.longPressTimer);
					this.longPressTimer = null;
				}
				
				if (!this.isDragging) return;
				
				if (this.placeholderIndex !== -1 && this.placeholderIndex !== this.dragIndex) {
					const draggedItem = this.imageList.splice(this.dragIndex, 1)[0];
					this.imageList.splice(this.placeholderIndex, 0, draggedItem);
					
					const draggedStatus = this.statusArr.splice(this.dragIndex, 1)[0];
					this.statusArr.splice(this.placeholderIndex, 0, draggedStatus);

					this.change();
				}
				
				setTimeout(() => {
					this.isDragging = false;
					this.dragIndex = -1;
					this.placeholderIndex = -1;
					this.draggedItemInfo = {};
					this.$nextTick(() => this._calculateGridPositions());
				}, 50);
			},
			
			// --- 原有方法 ---
			
			openAuth() {
				this.$refs['authpup'].open();
			},
			
			changeAuth() {
				console.log('权限已授权');
			},
			
			reUpLoad(index) {
				this.$set(this.statusArr, index, "2");
				this.change();
				this.uploadImage(index, this.imageList[index]).then(() => {
					this.change();
				}).catch(() => {
					this.change();
				});
			},
			
			change() {
				let status = ~this.statusArr.indexOf("2") ? 2 : 1;
				if (status != 2 && ~this.statusArr.indexOf("3")) {
					status = 3;
				}
				this.$emit('input', this.imageList);
				this.$emit('complete', {
					index: this.index,
					status: status,
					imgArr: this.imageList
				});
			},
			
			toast(text) {
				text && uni.showToast({
					title: text,
					icon: "none"
				});
			},
			
			chooseImage: function() {
				let _this = this;
				// 走 chooseImageWithTip:按实际来源弹对应的权限说明(华为审核7.18)。
				// 本组件 sourceType 默认 ['album','camera'],封装会先让用户选来源,
				// 避免"弹相册说明、却去申请相机权限"的不一致。
				chooseImageWithTip({
					count: _this.limit - _this.imageList.length,
					sizeType: _this.sizeType,
					sourceType: _this.sourceType,
					success: function(e) {
						let imageArr = [];
						for (let i = 0; i < e.tempFiles.length; i++) {
							let len = _this.imageList.length;
							if (len >= _this.limit) {
								_this.toast(`最多可上传${_this.limit}张图片`);
								break;
							}
							let path = e.tempFiles[i].path;
							if (_this.imageFormat.length > 0) {
								let format = "";
								// #ifdef H5
								let type = e.tempFiles[i].type;
								format = type.split('/')[1]
								// #endif
								// #ifndef H5
								format = path.split(".")[(path.split(".")).length - 1];
								// #endif
								if (_this.imageFormat.indexOf(format) == -1) {
									let text = `只能上传 ${_this.imageFormat.join(',')} 格式图片！`
									_this.toast(text);
									continue;
								}
							}
							let size = e.tempFiles[i].size;
							if (_this.size * 1024 * 1024 < size) {
								let err = `单张图片大小不能超过：${_this.size}MB`
								_this.toast(err);
								continue;
							}
							imageArr.push(path);
							_this.imageList.push(path);
							_this.statusArr.push("2");
						}
						_this.change();
						
						_this.$nextTick(() => _this._calculateGridPositions());

						let start = _this.imageList.length - imageArr.length;
						for (let j = 0; j < imageArr.length; j++) {
							let index = start + j;
							if (_this.serverUrl) {
								_this.uploadImage(index, imageArr[j]).then(() => {
									_this.change();
								}).catch(() => {
									_this.change();
								});
							} else {
								_this.$set(_this.statusArr, index, "1");
								_this.change();
							}
						}
					}
				});
			},

			uploadImage: function(index, url) {
				let _this = this;
				let apiRoot = this.$http.getApiRoot();
				this.header = Object.assign(this.header, {
					'x-mall-id': this.$http.getMallId(),
					'x-access-token': this.$http.getToken()
				});
				return new Promise((resolve, reject) => {
					uni.uploadFile({
						url: apiRoot + this.serverUrl,
						name: this.fileKeyName,
						header: this.header,
						formData: this.formData,
						filePath: url,
						success: function(res) {
							if (res.statusCode == 200) {
								let d = JSON.parse(res.data.replace(/\ufeff/g, "") || "{}");
								if (d.code === 0) {
									if (d.data.url) {
										_this.imageList[index] = d.data.url;
									}
									_this.$set(_this.statusArr, index, d.data.url ? "1" : "3");
								} else {
									_this.$set(_this.statusArr, index, "3");
								}
								resolve(index);
							} else {
								_this.$set(_this.statusArr, index, "3");
								reject(index);
							}
						},
						fail: function(res) {
							_this.$set(_this.statusArr, index, "3");
							reject(index);
						}
					});
				});
			},
			
			delImage: function(index) {
				let delUrl = this.imageList.splice(index, 1);
				this.statusArr.splice(index, 1);
				this.$emit("remove", {
					url: delUrl[0],
					index: index
				});
				this.change();
				this.$nextTick(() => this._calculateGridPositions());
			},
			
			previewImage: function(index) {
				if (!this.imageList.length) return;
				uni.previewImage({
					current: this.imageList[index],
					loop: true,
					urls: this.imageList
				});
			}
		}
	}
</script>

<style scoped>
	.tui-container {
		width: 100%;
	}

	.tui-upload-box {
		width: 100%;
		display: flex;
		flex-wrap: wrap;
		gap: 20rpx; /* 使用 gap 代替 margin，布局更优雅 */
		position: relative;
	}

	/* 添加按钮默认样式（更现代的虚线框设计） */
	.tui-upload-add {
		/* #ifdef H5 */
		cursor: pointer;
		/* #endif */
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0;
		box-sizing: border-box;
		border: 2rpx dashed #DCDFE6;
		background-color: #FAFAFA;
		border-radius: 12rpx;
		transition: all 0.2s;
	}

	.tui-upload-add:active {
		background-color: #F0F2F5;
		border-color: #C0C4CC;
	}

	/* 默认十字图标 */
	.default-add-btn {
		position: relative;
		width: 48rpx;
		height: 48rpx;
	}

	.plus-line-h, .plus-line-v {
		position: absolute;
		background-color: #BDBDBD;
		border-radius: 4rpx;
	}

	.plus-line-h {
		width: 100%;
		height: 4rpx;
		top: 50%;
		left: 0;
		transform: translateY(-50%);
	}

	.plus-line-v {
		height: 100%;
		width: 4rpx;
		left: 50%;
		top: 0;
		transform: translateX(-50%);
	}

	/* 图片项容器 */
	.tui-image-item {
		position: relative;
		border-radius: 12rpx; /* 增加高级圆角 */
		overflow: hidden;
		transition: transform 0.2s ease-in-out;
		box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.05); /* 淡淡的投影 */
	}

	.tui-item-img {
		display: block;
		width: 100%;
		height: 100%;
	}

	/* 优化后的删除按钮（右上角半透明黑底白叉） */
	.tui-img-del {
		width: 40rpx;
		height: 40rpx;
		position: absolute;
		right: 0;
		top: 0;
		background-color: rgba(0, 0, 0, 0.5);
		border-bottom-left-radius: 12rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 10;
		backdrop-filter: blur(4px); /* 毛玻璃效果 */
	}

	.del-icon {
		position: relative;
		width: 20rpx;
		height: 20rpx;
	}

	.del-icon::before, .del-icon::after {
		content: '';
		position: absolute;
		top: 50%;
		left: 0;
		width: 100%;
		height: 2rpx;
		background-color: #FFFFFF;
		border-radius: 2rpx;
	}

	.del-icon::before {
		transform: translateY(-50%) rotate(45deg);
	}

	.del-icon::after {
		transform: translateY(-50%) rotate(-45deg);
	}

	/* 上传状态蒙层优化 */
	.tui-upload-mask {
		width: 100%;
		height: 100%;
		position: absolute;
		left: 0;
		top: 0;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center; /* 改为居中，不那么散 */
		box-sizing: border-box;
		background-color: rgba(0, 0, 0, 0.45); /* 降低黑度 */
		backdrop-filter: blur(2px);
		z-index: 9;
	}

	.tui-upload-loading {
		width: 32rpx;
		height: 32rpx;
		border-radius: 50%;
		border: 3rpx solid transparent;
		border-top-color: #FFFFFF;
		border-right-color: #FFFFFF;
		border-bottom-color: #FFFFFF;
		animation: tui-rotate 0.8s linear infinite;
		margin-bottom: 12rpx;
	}

	@keyframes tui-rotate {
		0% { transform: rotate(0); }
		100% { transform: rotate(360deg); }
	}

	.tui-tips {
		font-size: 24rpx;
		color: #fff;
		margin-bottom: 8rpx;
	}

	.tui-mask-btn {
		padding: 6rpx 20rpx;
		border-radius: 30rpx;
		text-align: center;
		font-size: 22rpx;
		color: #fff;
		background-color: rgba(255, 255, 255, 0.2);
		border: 1rpx solid rgba(255, 255, 255, 0.6);
		margin-top: 10rpx;
	}

	.tui-btn-hover {
		background-color: rgba(255, 255, 255, 0.3);
	}
	
	/* --- 拖拽相关的样式 --- */
	.tui-image-item.is-dragging {
		opacity: 0;
		transition: opacity 0.2s ease-in-out;
	}

	.tui-ghost-item {
		position: fixed;
		z-index: 999;
		box-shadow: 0 8rpx 24rpx rgba(0,0,0,0.15);
		border-radius: 12rpx;
		transform: scale(1.05); /* 稍微缩小放大比例，更平滑 */
		pointer-events: none;
		transition: transform 0.1s ease;
	}

	.tui-placeholder-item {
		position: absolute;
		top: 0;
		left: 0;
		background-color: #F5F7FA;
		border: 2rpx dashed #C0C4CC;
		border-radius: 12rpx;
		box-sizing: border-box;
	}
</style>