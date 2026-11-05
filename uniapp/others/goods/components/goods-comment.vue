<template>
	<view class="comment-module" v-if="goods && goods.total_comment_count > 0">
		<view class="card-header" @tap="commentList">
			<view class="header-title">用户评价<text class="header-count" :style="{color: themeColor}">{{ goods.total_comment_count }}</text></view>
			<view class="header-extra">
				<text v-if="goods.good_rating_rate > 0">好评率 {{ goods.good_rating_rate }}%</text>
				<tui-icon name="arrowright" :size="16" color="#c3c7d0"></tui-icon>
			</view>
		</view>

		<block v-if="comment">
			<view class="comment-body">
				<view class="user-info">
					<image class="avatar" :src="comment.avatar_url"></image>
					<view class="nickname">{{ comment.nickname }}</view>
					<view class="rating-stars">
						<tui-rate disabled :current="comment.grade_level" :size="16" :active="themeColor"></tui-rate>
					</view>
				</view>
				<view class="comment-text">{{ comment.content || '此用户没有填写评价内容' }}</view>
				<view class="comment-pics" v-if="comment.pic_list && comment.pic_list.length > 0">
					<image class="comment-pic" v-for="(pic, index) in comment.pic_list.slice(0, 3)" :key="index"
						:src="pic" mode="aspectFill" @tap.stop="previewPic(index)"></image>
				</view>
				<view class="comment-date">{{ comment.created_time }}</view>
			</view>
			<view class="card-footer" @tap="commentList">
				查看全部 {{ goods.total_comment_count }} 条评价
				<tui-icon name="arrowright" :size="14" color="#9aa0ac"></tui-icon>
			</view>
		</block>
		<view class="no-comment" v-else>暂无评价</view>
	</view>
</template>

<script>
	export default {
		name: "goods-comment",
		props: {
			goods: {
				type: Object,
				default: null
			},
			comment: {
				type: Object,
				default: null
			}
		},
		methods: {
			commentList() {
				this.$emit('click');
			},
			previewPic(index) {
				const urls = (this.comment && this.comment.pic_list) || [];
				if (!urls.length) return;
				uni.previewImage({ current: urls[index], urls });
			}
		}
	}
</script>

<style lang="scss" scoped>
	.comment-module {
		padding: 24rpx;
		background-color: #fff;
	}

	.card-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.header-title {
		font-size: 30rpx;
		font-weight: 700;
		color: #1f2229;
	}

	.header-count {
		margin-left: 10rpx;
		font-size: 30rpx;
	}

	.header-extra {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: #9aa0ac;
	}

	.comment-body {
		padding: 22rpx 0 6rpx;
	}

	.user-info {
		display: flex;
		align-items: center;

		.avatar {
			width: 56rpx;
			height: 56rpx;
			border-radius: 50%;
			margin-right: 16rpx;
			background-color: #f0f1f4;
		}

		.nickname {
			font-size: 26rpx;
			color: #5a5f6b;
		}

		.rating-stars {
			margin-left: auto;
		}
	}

	.comment-text {
		margin-top: 14rpx;
		font-size: 27rpx;
		line-height: 40rpx;
		color: #33373f;
	}

	.comment-pics {
		display: flex;
		margin-top: 16rpx;
	}

	.comment-pic {
		width: 128rpx;
		height: 128rpx;
		border-radius: 12rpx;
		margin-right: 12rpx;
		background-color: #f0f1f4;
	}

	.comment-date {
		margin-top: 14rpx;
		font-size: 22rpx;
		color: #b4b8c2;
	}

	.card-footer {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 80rpx;
		margin-top: 8rpx;
		border-top: 1rpx solid #f2f3f5;
		font-size: 26rpx;
		color: #9aa0ac;
	}

	.no-comment {
		text-align: center;
		color: #9aa0ac;
		font-size: 26rpx;
		padding: 40rpx 0;
	}
</style>
