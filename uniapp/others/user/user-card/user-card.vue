<template>
	<view class="card-theme" :style="colorVars">

		<!-- A. 色头(主题色色块) -->
		<view class="hero" :style="{ paddingTop: (top + 50) + 'px' }">
			<view class="deco deco-1"></view>
			<view class="deco deco-2"></view>
			<view class="hero-user" @click="!isLogin ? goToLogin() : ''">
				<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill" class="avatar"
					@click.stop="$T.go('/others/setting/setting')" />
				<view v-else class="avatar placeholder">
					<tui-icon name="people" size="36" color="#ffffff"></tui-icon>
				</view>
				<view class="info">
					<view class="name-row">
						<text class="name">{{ isLogin ? userInfo.nickname : '登录 / 注册' }}</text>
						<view class="level" v-if="isLogin && userInfo.level_name"><text>{{ userInfo.level_name }}</text></view>
					</view>
					<view class="uid" v-if="isLogin" @click.stop="copyId(userInfo.id)">
						<text>ID {{ userInfo.id }}</text>
						<tui-icon name="copy" size="11" color="rgba(255,255,255,0.7)" style="margin-left:8rpx;"></tui-icon>
						<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:22rpx;">· {{ userInfo.mobile }}</text>
					</view>
					<view class="uid" v-else><text>点击登录,体验完整功能</text></view>
				</view>
				<view class="sign" style="display: none;" v-if="isLogin" @click.stop="$T.go('/plugins/integral/sign')"><text>签到</text></view>
			</view>

			<view class="hero-stats" v-if="hasHeaderStats">
				<view class="stat" v-if="formattedPageSetting.header.goods.is_show == 1" @click="$T.go('/others/collect/collect')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.goods.name }}</text>
				</view>
				<view class="stat" v-if="formattedPageSetting.header.footmark.is_show == 1" @click="$T.go('/others/footmark/footmark')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.footmark.name }}</text>
				</view>
				<view class="stat" v-if="formattedPageSetting.header.order && formattedPageSetting.header.order.is_show == 1" @click="$T.go('/others/order/order?status=-1')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.order.name }}</text>
				</view>
			</view>
		</view>

		<!-- B. 资产卡(叠在色头上) -->
		<view class="panel assets" v-if="formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">
			<view class="panel-head" @click="onFinanceClick">
				<text class="t">{{ formattedPageSetting.finance.name }}</text>
				<view class="more"><text>资金管理</text><tui-icon name="arrowright" size="12" color="#c8c9cc"></tui-icon></view>
			</view>
			<view class="assets-row">
				<view class="a-item balance" v-if="formattedPageSetting.finance.balance.is_show == 1" @click="isLogin && goAsset('balance','/plugins2/withdraw/balance')">
					<text class="v hl" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
					<text class="l">{{ formattedPageSetting.finance.balance.name }}</text>
				</view>
				<view class="a-item" v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1" @click="isLogin && goAsset('price','/plugins2/withdraw/price')">
					<text class="v" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
					<text class="l">{{ formattedPageSetting.finance.price.name }}</text>
				</view>
				<view class="a-item" v-if="formattedPageSetting.finance.integral.is_show == 1" @click="isLogin && goAsset('integral','/plugins2/withdraw/integral')">
					<text class="v" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
					<text class="l">{{ formattedPageSetting.finance.integral.name }}</text>
				</view>
				<view class="a-item" v-if="formattedPageSetting.finance.coupon.is_show == 1" @tap="goAsset('coupon','/others/coupon/coupon?status=1')">
					<text class="v" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
					<text class="l">{{ formattedPageSetting.finance.coupon.name }}</text>
				</view>
			</view>
		</view>

		<!-- C. 订单卡 -->
		<view class="panel" v-if="formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
			<view class="panel-head" @click="$T.go('/others/order/order?status=-1')">
				<text class="t">{{ formattedPageSetting.order.title }}</text>
				<view class="more"><text>{{ formattedPageSetting.order.sub_title }}</text><tui-icon name="arrowright" size="12" color="#c8c9cc"></tui-icon></view>
			</view>
			<view class="order-grid">
				<view class="o-item" v-for="key in orderKeys" :key="key"
					v-if="formattedPageSetting.order[key] && formattedPageSetting.order[key].is_show == 1"
					@tap="$T.go(formattedPageSetting.order[key].url)">
					<view class="o-icon">
						<image :src="formattedPageSetting.order[key].icon" class="img"></image>
						<view class="badge" v-if="isLogin && formattedPageSetting.order[key].num > 0">{{ formattedPageSetting.order[key].num }}</view>
					</view>
					<text class="o-t">{{ formattedPageSetting.order[key].name }}</text>
				</view>
			</view>
		</view>

		<!-- D. 工具栏1/2 -->
		<view class="panel" v-for="block in ['menus','menus2']" :key="block"
			v-if="formattedPageSetting[block] && formattedPageSetting[block].is_show == 1">
			<view class="panel-head"><text class="t">{{ formattedPageSetting[block].name }}</text></view>
			<view class="tool-grid" v-if="formattedPageSetting[block].type == 0">
				<view class="tool" v-for="(tool, idx) in formattedPageSetting[block].list" :key="idx" @click="$T.go(tool.url)">
					<view class="tool-bg" :style="{ background: getMenuColor(idx) }"><image :src="tool.icon" class="tool-img"></image></view>
					<text class="tool-t">{{ tool.name }}</text>
				</view>
			</view>
			<view class="tool-list" v-else>
				<view class="row" v-for="(item, index) in formattedPageSetting[block].list" :key="index" @click="$T.go(item.url)">
					<view class="left"><image :src="item.icon || '/static/icons/placeholder-icon.png'" class="ri"></image><text>{{ item.name }}</text></view>
					<tui-icon name="arrowright" size="16" color="#c8c9cc"></tui-icon>
				</view>
			</view>
		</view>

		<!-- E. 推荐好物 -->
		<view class="recommend" v-if="list.length">
			<view class="rec-title"><text class="bar"></text><text>推荐好物</text></view>
			<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
				<block v-for="(item, index) in list" :key="index">
					<uni-grid-item>
						<view :style="{ padding: index % 2 == 0 ? '0 10rpx 20rpx 0rpx' : '0 0rpx 20rpx 10rpx' }">
							<tui-goods-item :goods="item"></tui-goods-item>
						</view>
					</uni-grid-item>
				</block>
			</uni-grid>
			<tui-loadmore v-if="loadding" :index="3" type="primary"></tui-loadmore>
		</view>

		<!-- F. 版权 -->
		<view class="footer" v-if="is_no_more && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1 && formattedPageSetting.copyright.text">
			<text>{{ formattedPageSetting.copyright.text }}</text>
		</view>
		<view style="height: 40rpx;"></view>

		<tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess" @close="showProfileModal = false" />
	</view>
</template>

<script>
import userTheme from '../mixins/userTheme.js';

	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
export default {
		components: { TuiUserAvatar },
	name: 'user-card',
	mixins: [userTheme],
	data() {
		return {
			orderKeys: ['status_0', 'status_1', 'status_2', 'status_3', 'status_4']
		};
	}
};
</script>

<style lang="scss" scoped>
.card-theme {
	min-height: 100vh;
	background: #f5f6f8;
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 色头 */
.hero {
	position: relative;
	overflow: hidden;
	background: linear-gradient(135deg, var(--user-color-from, #5B6CF0), var(--user-color-to, #8A7CF0));
	padding: 0 32rpx 130rpx;
	border-radius: 0 0 44rpx 44rpx;

	.deco {
		position: absolute;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.12);
	}

	.deco-1 {
		top: -130rpx;
		right: -90rpx;
		width: 340rpx;
		height: 340rpx;
	}

	.deco-2 {
		bottom: 10rpx;
		left: -110rpx;
		width: 240rpx;
		height: 240rpx;
		background: rgba(255, 255, 255, 0.08);
	}
}

.hero-user {
	position: relative;
	z-index: 1;
	display: flex;
	align-items: center;
	padding-top: 20rpx;

	.avatar {
		width: 108rpx;
		height: 108rpx;
		border-radius: 50%;
		border: 4rpx solid rgba(255, 255, 255, 0.4);
		background: rgba(255, 255, 255, 0.2);

		&.placeholder {
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}

	.info {
		flex: 1;
		margin-left: 24rpx;
		min-width: 0;

		.name-row {
			display: flex;
			align-items: center;

			.name {
				font-size: 34rpx;
				font-weight: 600;
				color: #ffffff;
				max-width: 320rpx;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			.level {
				margin-left: 14rpx;
				padding: 2rpx 14rpx;
				border-radius: 18rpx;
				background: rgba(255, 255, 255, 0.25);

				text {
					font-size: 20rpx;
					color: #ffffff;
				}
			}
		}

		.uid {
			margin-top: 8rpx;
			display: flex;
			align-items: center;

			text {
				font-size: 22rpx;
				color: rgba(255, 255, 255, 0.8);
			}
		}
	}

	.sign {
		padding: 12rpx 28rpx;
		background: #ffffff;
		border-radius: 28rpx;

		text {
			font-size: 24rpx;
			font-weight: 500;
			color: var(--user-color-from, #5B6CF0);
		}
	}
}

.hero-stats {
	position: relative;
	z-index: 1;
	display: flex;
	margin-top: 40rpx;

	.stat {
		flex: 1;
		text-align: center;

		.num {
			display: block;
			font-size: 32rpx;
			font-weight: 600;
			color: #ffffff;
		}

		.lbl {
			font-size: 22rpx;
			color: rgba(255, 255, 255, 0.8);
		}
	}
}

/* 白卡片 */
.panel {
	position: relative;
	margin: 24rpx 24rpx 0;
	background: #ffffff;
	border-radius: 28rpx;
	padding: 30rpx 28rpx 10rpx;
	box-shadow: 0 12rpx 36rpx rgba(0, 0, 0, 0.05);

	&.assets {
		margin-top: -96rpx;
		box-shadow: 0 16rpx 44rpx rgba(0, 0, 0, 0.08);
	}

	.panel-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 24rpx;

		.t {
			font-size: 28rpx;
			font-weight: 600;
			color: #1a1a1a;
		}

		.more {
			display: flex;
			align-items: center;

			text {
				font-size: 22rpx;
				color: #b0b3b8;
				margin-right: 4rpx;
			}
		}
	}
}

.assets-row {
	display: flex;
	padding-bottom: 24rpx;

	.a-item {
		position: relative;
		flex: 1;
		text-align: center;


		.v {
			position: relative;
			display: block;
			font-size: 40rpx;
			font-weight: 700;
			color: #1a1a1a;
			letter-spacing: -0.5rpx;

			&.hl {
				color: var(--user-color-from, #5B6CF0);
			}
		}

		.l {
			position: relative;
			font-size: 22rpx;
			color: #9aa0a6;
			margin-top: 8rpx;
		}
	}
}

.order-grid {
	display: flex;
	padding-bottom: 20rpx;

	.o-item {
		flex: 1;
		text-align: center;

		.o-icon {
			position: relative;
			display: inline-block;

			.img {
				width: 48rpx;
				height: 48rpx;
			}

			.badge {
				position: absolute;
				top: -8rpx;
				right: -16rpx;
				min-width: 26rpx;
				height: 26rpx;
				line-height: 26rpx;
				padding: 0 6rpx;
				border-radius: 13rpx;
				background: var(--user-color-from, #5B6CF0);
				color: #ffffff;
				font-size: 18rpx;
				text-align: center;
			}
		}

		.o-t {
			display: block;
			font-size: 22rpx;
			color: #5a5a5a;
			margin-top: 10rpx;
		}
	}
}

.tool-grid {
	display: flex;
	flex-wrap: wrap;
	padding-bottom: 12rpx;

	.tool {
		width: 25%;
		text-align: center;
		margin-bottom: 24rpx;

		.tool-bg {
			width: 84rpx;
			height: 84rpx;
			border-radius: 24rpx;
			margin: 0 auto 12rpx;
			display: flex;
			align-items: center;
			justify-content: center;

			.tool-img {
				width: 48rpx;
				height: 48rpx;
			}
		}

		.tool-t {
			font-size: 22rpx;
			color: #5a5a5a;
		}
	}
}

.tool-list {
	.row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f3f4f6;

		&:last-child {
			border-bottom: none;
		}

		.left {
			display: flex;
			align-items: center;

			.ri {
				width: 44rpx;
				height: 44rpx;
				margin-right: 16rpx;
			}

			text {
				font-size: 26rpx;
				color: #333333;
			}
		}
	}
}

/* 推荐 */
.recommend {
	margin: 28rpx 24rpx 0;

	.rec-title {
		display: flex;
		align-items: center;
		margin-bottom: 16rpx;

		.bar {
			width: 8rpx;
			height: 28rpx;
			border-radius: 4rpx;
			background: var(--user-color-from, #5B6CF0);
			margin-right: 12rpx;
		}

		text {
			font-size: 28rpx;
			font-weight: 600;
			color: #1a1a1a;
		}
	}
}

.footer {
	text-align: center;
	padding: 40rpx 0 20rpx;

	text {
		font-size: 22rpx;
		color: #b0b3b8;
	}
}
</style>
