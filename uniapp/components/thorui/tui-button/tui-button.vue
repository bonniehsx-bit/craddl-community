<template>
	<button class="tui-btn" :class="[
			/* 如果传了 background (主题色)，就不使用默认的 type 类名，防止样式冲突 */
			background ? '' : (plain ? 'tui-' + type + '-outline' : 'tui-btn-' + (type || '')),
			
			getDisabledClass(disabled, type, plain),
			getShapeClass(shape, plain),
			getShadowClass(type, shadow, plain),
			bold ? 'tui-text-bold' : '',
			link ? 'tui-btn__link' : '',
			
			/* 标记类：当有自定义背景时，用于在CSS中隐藏默认的 ::after 边框 */
			background ? 'tui-btn-custom' : ''
		]" 
		:hover-class="getHoverClass(disabled, type, plain)"
		:style="{
			width: width,
			height: height,
			fontSize: size + 'rpx',
			/* --- 核心样式逻辑 --- */
			/* 1. 背景色：如果是镂空(plain)，则背景透明；否则使用传入的 background */
			background: plain ? 'transparent' : (background || ''),
			
			/* 2. 文字颜色：如果有 color 属性优先；如果是镂空且有背景色，字色=背景色；如果是实心背景，字色=白色 */
			color: color ? color : (plain && background ? background : (background ? '#fff' : '')),
			
			/* 3. 边框颜色：优先 borderColor，其次 background */
			borderColor: borderColor ? borderColor : (background || ''),
			
			/* 4. 边框宽度：如果有自定义颜色或镂空模式，则显示边框 */
			borderWidth: (background || borderColor || plain) ? '1rpx' : '0',
			borderStyle: 'solid',
			
			/* 5. 圆角：直接内联处理，避免伪元素圆角不一致 */
			borderRadius: shape === 'circle' ? '500px' : (shape === 'rightAngle' ? '10rpx' : '6rpx')
		}"
		:loading="loading" :form-type="formType" :open-type="openType" @getuserinfo="bindgetuserinfo"
		@getphonenumber="bindgetphonenumber" @contact="bindcontact" @error="binderror" :disabled="disabled"
		@agreeprivacyauthorization="bindagreePrivacyAuthorization" @tap="handleClick">
		<slot></slot>
	</button>
</template>

<script>
	export default {
		name: 'tuiButton',
		// 启用小程序原生 form-field 行为
		behaviors: ['wx://form-field-button'],
		props: {
			// 按钮类型：primary, danger, warning, green, blue, white, black, gray 等
			type: { type: String, default: '' },
			// 是否显示阴影
			shadow: { type: Boolean, default: true },
			// 宽度
			width: { type: String, default: '100%' },
			// 高度
			height: { type: String, default: '96rpx' },
			// 字体大小 (rpx)
			size: { type: [Number, String], default: 32 },
			// 字体加粗
			bold: { type: Boolean, default: false },
			// 形状：square(默认圆角), circle(全圆角), rightAngle(直角)
			shape: { type: String, default: 'square' },
			// 是否镂空
			plain: { type: Boolean, default: false },
			// 链接模式 (无边框背景)
			link: { type: Boolean, default: false },
			// 禁用
			disabled: { type: Boolean, default: false },
			// 禁用时是否灰色背景
			disabledGray: { type: Boolean, default: false },
			// 加载中
			loading: { type: Boolean, default: false },
			formType: { type: String, default: '' },
			openType: { type: String, default: '' },
			index: { type: [Number, String], default: 0 },
			// 防止重复点击 (200ms)
			preventClick: { type: Boolean, default: false },
			
			/* --- 自定义样式 Props --- */
			// 背景颜色 (支持传入 themeColor)
			background: { type: String, default: '' },
			// 文字颜色
			color: { type: String, default: '' },
			// 边框颜色
			borderColor: { type: String, default: '' }
		},
		data() {
			return { time: 0 };
		},
		methods: {
			handleClick() {
				if (this.disabled) return;
				if (this.preventClick) {
					if (new Date().getTime() - this.time <= 200) return;
					this.time = new Date().getTime();
					setTimeout(() => { this.time = 0; }, 200);
				}
				this.$emit('click', { index: Number(this.index) });
				// #ifdef MP-WEIXIN
				if (this.$setting && this.$setting.mpwx_tpl_ids) {
					uni.requestSubscribeMessage({
						tmplIds: this.$setting.mpwx_tpl_ids,
						success: () => {},
						fail: () => {},
					})
				}
				// #endif
			},
			bindgetuserinfo({ detail = {} } = {}) { this.$emit('getuserinfo', detail); },
			bindcontact({ detail = {} } = {}) { this.$emit('contact', detail); },
			bindagreePrivacyAuthorization({ detail = {} } = {}) { this.$emit('agreePrivacyAuthorization', detail); },
			bindgetphonenumber({ detail = {} } = {}) { this.$emit('getphonenumber', detail); },
			binderror({ detail = {} } = {}) { this.$emit('error', detail); },
			
			// --- Class 计算辅助方法 ---
			getShadowClass(type, shadow, plain) {
				if (shadow && type && type !== 'white' && !plain) {
					return 'tui-shadow-' + type;
				}
				return '';
			},
			getDisabledClass(disabled, type, plain) {
				if (disabled && type && type !== 'white' && type.indexOf('-') === -1) {
					return plain ? 'tui-dark-disabled-outline' : (this.disabledGray ? 'tui-gray-disabled' : 'tui-dark-disabled');
				}
				return '';
			},
			getShapeClass(shape, plain) {
				if (shape === 'circle') return plain ? 'tui-outline-fillet' : 'tui-fillet';
				if (shape === 'rightAngle') return plain ? 'tui-outline-rightAngle' : 'tui-rightAngle';
				return '';
			},
			getHoverClass(disabled, type, plain) {
				if (!disabled) {
					return plain ? 'tui-outline-hover' : 'tui-' + (type) + '-hover';
				}
				return '';
			}
		}
	};
</script>

<style lang="scss" scoped>
	/* --- 核心修复样式 --- */
	.tui-btn {
		width: 100%;
		position: relative;
		/* 移除 !important，允许内联样式覆盖边框 */
		border: 0; 
		/* 默认圆角，会被内联样式覆盖 */
		border-radius: 6rpx; 
		/* 移除默认 margin */
		margin: 0; 
		padding: 0;
		/* 允许 overflow，防止阴影被切 */
		overflow: visible;
		/* [重要] 使用 Flex 布局居中，防止高度变形 */
		display: flex;
		align-items: center;
		justify-content: center;
		/* [重要] 包含边框和内边距，防止尺寸撑大 */
		box-sizing: border-box;
	}

	/* 默认的微边框 (hairline) */
	.tui-btn::after {
		content: '';
		position: absolute;
		width: 200%;
		height: 200%;
		transform-origin: 0 0;
		transform: scale(0.5, 0.5) translateZ(0);
		box-sizing: border-box;
		left: 0;
		top: 0;
		border-radius: 12rpx;
		border: 0;
	}

	/* [修复] 当有自定义背景时，隐藏 ::after 的边框，防止双重边框冲突 */
	.tui-btn.tui-btn-custom::after {
		border: 0 !important;
	}

	.tui-text-bold { font-weight: bold; }

	/* --- 原有预设样式 (保持不变) --- */
	.tui-btn-white { background: #fff !important; color: #333 !important; }
	.tui-btn-white::after { border: 1px solid #CCC; }
	.tui-white-hover { background: #e5e5e5 !important; color: #2e2e2e !important; }
	
	.tui-dark-disabled { opacity: 0.6 !important; color: #fafbfc !important; }
	.tui-dark-disabled-outline { opacity: 0.5 !important; }
	.tui-gray-disabled { background: #F7F7F7 !important; color: #919191 !important; box-shadow: none; }
	.tui-outline-hover { opacity: 0.5; }
	
	.tui-primary-hover { background: $uni-color-primary !important; color: #e5e5e5 !important; }
	.tui-primary-outline { color: $uni-color-primary !important; background: transparent; }
	.tui-primary-outline::after { border: 1px solid $uni-color-primary !important; }
	
	.tui-danger-hover { background: $uni-color-danger !important; color: #e5e5e5 !important; }
	.tui-danger-outline { color: $uni-color-danger !important; background: transparent; }
	.tui-danger-outline::after { border: 1px solid $uni-color-danger !important; }
	
	.tui-warning-hover { background: #d67326 !important; color: #e5e5e5 !important; }
	.tui-warning-outline { color: #fc872d !important; background: transparent; }
	.tui-warning-outline::after { border: 1px solid #fc872d !important; }
	
	.tui-green-hover { background: #06ad56 !important; color: #e5e5e5 !important; }
	.tui-green-outline { color: #07c160 !important; background: transparent; }
	.tui-green-outline::after { border: 1px solid #07c160 !important; }
	
	.tui-blue-hover { background: #0062cc !important; color: #e5e5e5 !important; }
	.tui-blue-outline { color: #007aff !important; background: transparent; }
	.tui-blue-outline::after { border: 1px solid #007aff !important; }
	
	/* #ifndef APP-NVUE */
	.tui-btn-gradual { background: linear-gradient(90deg, rgb(255, 89, 38), rgb(240, 14, 44)) !important; color: #fff !important; }
	.tui-shadow-gradual { box-shadow: 0 10rpx 14rpx 0 rgba(235, 9, 9, 0.15); }
	.tui-gradual-hover { background: linear-gradient(90deg, #d74620, #cd1225) !important; color: #fff !important; }
	/* #endif */
	
	.tui-gray-hover { background: #a3a3a3 !important; color: #898989; }
	.tui-gray-outline { color: #CCC !important; background: transparent !important; }
	.tui-gray-outline::after { border: 1px solid #ccc !important; }
	
	.tui-white-outline { color: #fff !important; background: transparent !important; }
	.tui-white-outline::after { border: 1px solid #fff !important; }
	
	.tui-black-outline { background: transparent !important; color: #333 !important; }
	.tui-black-outline::after { border: 1px solid #333 !important; }
	
	.tui-brown-outline { color: #ac9157 !important; background: transparent; }
	.tui-brown-outline::after { border: 1px solid #ac9157 !important; }
	
	/* 形状 */
	.tui-fillet { border-radius: 50rpx; }
	.tui-btn-white.tui-fillet::after { border-radius: 98rpx; }
	.tui-outline-fillet::after { border-radius: 98rpx; }
	
	.tui-rightAngle { border-radius: 10rpx; }
	.tui-btn-white.tui-rightAngle::after { border-radius: 10rpx; }
	.tui-outline-rightAngle::after { border-radius: 10rpx; }
	
	.tui-btn.tui-btn__link:not([class*='-outline'])::after { border: 0 !important; }
	
	/* 颜色预设 */
	.tui-btn-primary { background: $uni-color-primary !important; color: #fff; }
	.tui-shadow-primary { box-shadow: 0 10rpx 14rpx 0 rgba(86, 119, 252, 0.2); }
	
	.tui-btn-danger { background: $uni-color-danger !important; color: #fff; }
	.tui-shadow-danger { box-shadow: 0 10rpx 14rpx 0 rgba(235, 9, 9, 0.2); }
	
	.tui-btn-warning { background: $uni-color-warning !important; color: #fff; }
	.tui-shadow-warning { box-shadow: 0 10rpx 14rpx 0 rgba(252, 135, 45, 0.2); }
	
	.tui-btn-green { background: $uni-color-success !important; color: #fff; }
	.tui-shadow-green { box-shadow: 0 10rpx 14rpx 0 rgba(7, 193, 96, 0.2); }
	
	.tui-btn-blue { background: #007aff !important; color: #fff; }
	.tui-shadow-blue { box-shadow: 0 10rpx 14rpx 0 rgba(0, 122, 255, 0.2); }
	
	.tui-btn-gray { background: #CCC !important; color: #999 !important; }
	.tui-btn-black { background: #333 !important; color: #fff !important; }
	.tui-btn-brown { background: #ac9157 !important; color: #fff !important; }
	
	.tui-shadow-gray { box-shadow: 0 10rpx 14rpx 0 rgba(191, 191, 191, 0.2); }
	.tui-hover-gray { background: #f7f7f9 !important; }
	.tui-black-hover { background: #555 !important; color: #e5e5e5 !important; }
	.tui-brown-hover { background: #A37F49 !important; color: #e5e5e5 !important; }
</style>