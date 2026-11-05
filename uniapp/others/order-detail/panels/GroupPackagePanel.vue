<template>
	<view class="group-package-panel">
		<view class="card-title">
			<text>套餐内容</text>
			<text class="expire" v-if="data.expire_text">{{ data.expire_text }}</text>
		</view>

		<!-- 套餐项分组 -->
		<block v-if="packageGroups.length">
			<view class="pkg-group" v-for="(group, gi) in packageGroups" :key="gi">
				<view class="group-name" v-if="group.group_name">{{ group.group_name }}</view>
				<view class="group-items">
					<view class="pkg-item" v-for="(item, ii) in (group.items || [])" :key="ii">
						<view class="item-main">
							<text class="item-name">{{ item.name || item.title || '' }}</text>
							<text class="item-num" v-if="item.num || item.count">x{{ item.num || item.count }}</text>
						</view>
						<text v-if="item.desc || item.remark" class="item-desc">{{ item.desc || item.remark }}</text>
					</view>
				</view>
			</view>
		</block>

		<!-- 使用规则 -->
		<view class="rules" v-if="Array.isArray(data.rule_list) && data.rule_list.length">
			<view class="rule-title">使用须知</view>
			<view class="rule-row" v-for="(rule, ri) in data.rule_list" :key="ri">
				<text class="dot">·</text>
				<text class="rule-text">{{ rule.desc || rule.text || rule }}</text>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'GroupPackagePanel',
	props: {
		data: { type: Object, required: true },
		order: { type: Object, default: () => ({}) }
	},
	computed: {
		/**
		 * 规范化 package 数据为 [{group_name, items:[{name, num}]}]
		 */
		packageGroups() {
			const list = this.data.package_list;
			if (!Array.isArray(list)) return [];
			// 后端已经规范化过，但前端再兜一道
			if (list.length > 0 && !list[0].items) {
				return [{ group_name: '', items: list }];
			}
			return list;
		}
	}
};
</script>

<style scoped lang="scss">
.group-package-panel {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 28rpx 30rpx;
}

.card-title {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 30rpx;
	font-weight: 600;
	color: #1A1A1A;
	margin-bottom: 20rpx;

	.expire {
		font-size: 22rpx;
		color: #FF8917;
		background: #FFF6E8;
		padding: 4rpx 12rpx;
		border-radius: 16rpx;
		font-weight: normal;
	}
}

.pkg-group {
	margin-bottom: 18rpx;

	.group-name {
		font-size: 26rpx;
		color: #666;
		margin-bottom: 10rpx;
		font-weight: 500;
	}
	.group-items {
		background: #FAFBFC;
		border-radius: 10rpx;
		padding: 14rpx 18rpx;
	}
	.pkg-item {
		padding: 8rpx 0;

		&:not(:last-child) {
			border-bottom: 1rpx dashed #eee;
		}

		.item-main {
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 26rpx;
			color: #333;

			.item-name { flex: 1; }
			.item-num {
				color: #999;
				margin-left: 14rpx;
				font-family: 'DIN Alternate', sans-serif;
			}
		}
		.item-desc {
			display: block;
			margin-top: 4rpx;
			font-size: 22rpx;
			color: #999;
		}
	}
}

.rules {
	margin-top: 20rpx;
	padding-top: 16rpx;
	border-top: 1rpx solid #f0f0f0;

	.rule-title {
		font-size: 26rpx;
		color: #666;
		margin-bottom: 10rpx;
		font-weight: 500;
	}
	.rule-row {
		display: flex;
		font-size: 24rpx;
		color: #666;
		line-height: 1.6;

		.dot {
			margin-right: 10rpx;
		}
		.rule-text { flex: 1; }
	}
}
</style>
