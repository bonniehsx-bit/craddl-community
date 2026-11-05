<template>
    <view class="classic-container">

        <view class="header-section" :style="{ background: headerColor, paddingTop: (top + 44) + 'px' }">
            <view class="header-bg-glass"></view>

            <view class="user-info-row" @click="!isLogin ? goToLogin() : ''">
                <view class="avatar-box">
                    <image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill" class="user-avatar"
                        @click.stop="$T.go('/others/setting/setting')"
                        style="width:120rpx;height:120rpx;border-radius:50%;" />
                    <view v-else class="avatar-placeholder">
                        <tui-icon name="people" size="40" color="#fff"></tui-icon>
                    </view>
                </view>

                <view class="info-content">
                    <view class="name-line">
                        <text class="nickname">{{ isLogin ? userInfo.nickname : '点击登录/注册' }}</text>
                        <view class="level-badge" v-if="isLogin && userInfo.level_name">
                            <tui-icon name="star-fill" size="10" color="#6D4C00"></tui-icon>
                            <text class="level-txt">{{ userInfo.level_name }}</text>
                        </view>
                    </view>
                    <view class="id-capsule" v-if="isLogin" @click.stop="copyId(userInfo.id)">
                        <text>UID: {{ userInfo.id }}</text>
                        <view class="copy-icon-wrap">
                            <tui-icon name="copy" size="10" color="#fff"></tui-icon>
                        </view>
                    	<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:22rpx;">· {{ userInfo.mobile }}</text>
                    </view>
                    <view class="id-capsule empty" v-else>
                        <text>登录享受更多专属权益</text>
                    </view>
                </view>

                <view class="checkin-btn" style="display: none;" v-if="isLogin"
                    @click.stop="$T.go('/plugins/check_in/index')" hover-class="checkin-hover">
                    <tui-icon name="calendar" size="14" color="#fff" style="margin-right: 6rpx;"></tui-icon>
                    <text>签到</text>
                </view>
            </view>
        </view>

        <view class="main-content">

            <view class="white-card assets-card"
                v-if="formattedPageSetting && ((formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1) || (formattedPageSetting.header && formattedPageSetting.header.goods.is_show == 1))">

                <view class="asset-item" v-if="formattedPageSetting.finance.balance.is_show == 1"
                    @click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')" hover-class="item-hover">
                    <text class="num" :style="{ color: formattedPageSetting.finance.balance.color || headerColor }">{{ isLogin ? userInfo.money : '0.00' }}</text>
                    <text class="label">{{ formattedPageSetting.finance.balance.name }}</text>
                </view>

                <view class="asset-item"
                    v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
                    @click="isLogin && goAsset('price', '/plugins2/withdraw/price')" hover-class="item-hover">
                    <text class="num" :style="{ color: formattedPageSetting.finance.price.color || headerColor }">{{ isLogin ? userInfo.price : '0.00' }}</text>
                    <text class="label">{{ formattedPageSetting.finance.price.name }}</text>
                </view>

                <view class="asset-item" v-if="formattedPageSetting.finance.integral.is_show == 1"
                    @click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')" hover-class="item-hover">
                    <text class="num" :style="{ color: formattedPageSetting.finance.integral.color || headerColor }">{{ isLogin ? userInfo.integral : 0 }}</text>
                    <text class="label">{{ formattedPageSetting.finance.integral.name }}</text>
                </view>

                <view class="asset-item" v-if="formattedPageSetting.finance.coupon.is_show == 1"
                    @tap="goAsset('coupon', '/others/coupon/coupon?status=1')" hover-class="item-hover">
                    <text class="num" :style="{ color: formattedPageSetting.finance.coupon.color || headerColor }">{{ isLogin ? userInfo.coupon : 0 }}</text>
                    <text class="label">{{ formattedPageSetting.finance.coupon.name }}</text>
                </view>

                <view class="asset-item"
                    v-if="formattedPageSetting.header && formattedPageSetting.header.goods.is_show == 1"
                    @click="$T.go('/others/collect/collect')" hover-class="item-hover">
                    <text class="num" :style="{ color: headerColor }">{{ isLogin ?
                        formattedPageSetting.header.goods.value : 0 }}</text>
                    <text class="label">{{ formattedPageSetting.header.goods.name }}</text>
                </view>
            </view>

            <view class="white-card order-card" v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
                <view class="card-header" @click="$T.go('/others/order/order?status=-1')" hover-class="item-hover">
                    <text class="title">{{ formattedPageSetting.order.title }}</text>
                    <view class="more">
                        <text>{{ formattedPageSetting.order.sub_title }}</text>
                        <tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
                    </view>
                </view>

                <view class="classic-row-box">
                    <view class="classic-col-item" v-if="formattedPageSetting.order.status_0.is_show == 1"
                        @tap="$T.go(formattedPageSetting.order.status_0.url)" hover-class="icon-hover">
                        <view class="classic-icon-wrap">
                            <image :src="formattedPageSetting.order.status_0.icon" class="item-icon-img"></image>
                            <view class="badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0"
                                :style="{ background: headerColor }">
                                {{ formattedPageSetting.order.status_0.num }}
                            </view>
                        </view>
                        <text class="item-name">{{ formattedPageSetting.order.status_0.name }}</text>
                    </view>

                    <view class="classic-col-item" v-if="formattedPageSetting.order.status_1.is_show == 1"
                        @tap="$T.go(formattedPageSetting.order.status_1.url)" hover-class="icon-hover">
                        <view class="classic-icon-wrap">
                            <image :src="formattedPageSetting.order.status_1.icon" class="item-icon-img"></image>
                            <view class="badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0"
                                :style="{ background: headerColor }">
                                {{ formattedPageSetting.order.status_1.num }}
                            </view>
                        </view>
                        <text class="item-name">{{ formattedPageSetting.order.status_1.name }}</text>
                    </view>

                    <view class="classic-col-item" v-if="formattedPageSetting.order.status_2.is_show == 1"
                        @tap="$T.go(formattedPageSetting.order.status_2.url)" hover-class="icon-hover">
                        <view class="classic-icon-wrap">
                            <image :src="formattedPageSetting.order.status_2.icon" class="item-icon-img"></image>
                            <view class="badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0"
                                :style="{ background: headerColor }">
                                {{ formattedPageSetting.order.status_2.num }}
                            </view>
                        </view>
                        <text class="item-name">{{ formattedPageSetting.order.status_2.name }}</text>
                    </view>

                    <view class="classic-col-item" v-if="formattedPageSetting.order.status_3.is_show == 1"
                        @tap="$T.go(formattedPageSetting.order.status_3.url)" hover-class="icon-hover">
                        <view class="classic-icon-wrap">
                            <image :src="formattedPageSetting.order.status_3.icon" class="item-icon-img"></image>
                            <view class="badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0"
                                :style="{ background: headerColor }">
                                {{ formattedPageSetting.order.status_3.num }}
                            </view>
                        </view>
                        <text class="item-name">{{ formattedPageSetting.order.status_3.name }}</text>
                    </view>

                    <view class="classic-col-item" v-if="formattedPageSetting.order.status_4.is_show == 1"
                        @tap="$T.go(formattedPageSetting.order.status_4.url)" hover-class="icon-hover">
                        <view class="classic-icon-wrap">
                            <image :src="formattedPageSetting.order.status_4.icon" class="item-icon-img"></image>
                            <view class="badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0"
                                :style="{ background: headerColor }">
                                {{ formattedPageSetting.order.status_4.num }}
                            </view>
                        </view>
                        <text class="item-name">{{ formattedPageSetting.order.status_4.name }}</text>
                    </view>
                </view>
            </view>

            <view class="white-card menus-card"
                v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
                <view class="card-header">
                    <text class="title">{{ formattedPageSetting.menus.name }}</text>
                </view>

                <view class="classic-row-box cols-4" v-if="formattedPageSetting.menus.type == 0">
                    <view class="classic-col-item" hover-class="icon-hover"
                        v-for="(item, index) in formattedPageSetting.menus.list" :key="index" @click="$T.go(item.url)">
                        <view class="classic-icon-wrap">
                            <image :src="item.icon || '/static/icons/placeholder-icon.png'" class="item-icon-img"></image>
                        </view>
                        <text class="item-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
                    </view>
                </view>

                <view class="classic-list-box" v-else>
                    <view class="list-row-item" hover-class="item-hover"
                        v-for="(item, index) in formattedPageSetting.menus.list" :key="index" @click="$T.go(item.url)">
                        <view class="left-part">
                            <image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon-img"></image>
                            <text class="list-txt">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
                        </view>
                        <tui-icon name="arrowright" size="16" color="#d1d1d1"></tui-icon>
                    </view>
                </view>
            </view>

            <view class="white-card menus-card"
                v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
                <view class="card-header">
                    <text class="title">{{ formattedPageSetting.menus2.name }}</text>
                </view>

                <view class="classic-row-box cols-4" v-if="formattedPageSetting.menus2.type == 0">
                    <view class="classic-col-item" hover-class="icon-hover"
                        v-for="(item, index) in formattedPageSetting.menus2.list" :key="index" @click="$T.go(item.url)">
                        <view class="classic-icon-wrap">
                            <image :src="item.icon || '/static/icons/placeholder-icon.png'" class="item-icon-img"></image>
                        </view>
                        <text class="item-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
                    </view>
                </view>

                <view class="classic-list-box" v-else>
                    <view class="list-row-item" hover-class="item-hover"
                        v-for="(item, index) in formattedPageSetting.menus2.list" :key="index" @click="$T.go(item.url)">
                        <view class="left-part">
                            <image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon-img"></image>
                            <text class="list-txt">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
                        </view>
                        <tui-icon name="arrowright" size="16" color="#d1d1d1"></tui-icon>
                    </view>
                </view>
            </view>

            <view class="recommend-section" v-if="list.length">
                <view class="divider-title">
                    <view class="line-grad left"></view>
                    <view class="dot"></view>
                    <text class="text">推荐好物</text>
                    <view class="dot"></view>
                    <view class="line-grad right"></view>
                </view>

                <view class="product-list">
                    <uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
                        <block v-for="(item, index) in list" :key="index">
                            <uni-grid-item>
                                <view class="goods-item-wrap"
                                    :style="{ padding: index % 2 == 0 ? '0 10rpx 20rpx 0rpx' : '0 0rpx 20rpx 10rpx' }">
                                    <tui-goods-item :goods="item"></tui-goods-item>
                                </view>
                            </uni-grid-item>
                        </block>
                    </uni-grid>
                </view>

                <tui-loadmore v-if="loadding" :index="3" type="primary"></tui-loadmore>
            </view>

            <view class="footer-copyright"
                v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
                <text>{{ formattedPageSetting.copyright.text }}</text>
            </view>

            <view style="height: 40rpx;"></view>
        </view>

        <tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess"
            @close="showProfileModal = false" />

    </view>
</template>

<script>
	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
		components: { TuiUserAvatar },
    name: "user-classic",
    // 1. 接收父组件数据
    props: {
        userInfo: {
            type: Object,
            default: () => ({})
        },
        pageSetting: {
            type: Object,
            default: () => null
        },
        isLogin: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            top: 0,
            navRightGap: 0,
            showProfileModal: false,

            // 商品列表数据
        }
    },
    computed: {
        // 2. 包装后的配置数据
        formattedPageSetting() {
            let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
            if (!data.copyright) {
                data.copyright = {
                    is_show: 1,
                    text: 'Simplicity & Aurora UI'
                };
            }
            // 确保 financial 对象存在，防止报错
            if (!data.finance) {
                data.finance = {
                    balance: {},
                    integral: {},
                    coupon: {},
                    price: {}
                };
            }
            return data;
        },
        // 动态头部背景色：优先读后台色调配置，其次 tabbar.color，最后兜底默认红
        headerColor() {
            const colors = this.formattedPageSetting && this.formattedPageSetting.colors;
            if (colors && colors.header_from) {
                return colors.header_to
                    ? `linear-gradient(135deg, ${colors.header_from}, ${colors.header_to})`
                    : colors.header_from;
            }
            if (this.formattedPageSetting && this.formattedPageSetting.tabbar && this.formattedPageSetting.tabbar.color) {
                return this.formattedPageSetting.tabbar.color;
            }
            return '#E41F19';
        }
    },
    created() {
        this.initNav();

        // 推荐商品由共享mixin按当前登录用户加载。
        if (this.isLogin && this.userInfo.is_avatar != 1) {
            this.checkAvatar();
        }
    },
    watch: {
        isLogin(val) {
            if (val && this.userInfo.is_avatar != 1) {
                this.checkAvatar();
            }
        }
    },
    methods: {
            goAsset(key, defaultUrl) {
                const f = this.formattedPageSetting && this.formattedPageSetting.finance;
                const cfg = f && f[key];
                this.$T.go(cfg && cfg.url ? cfg.url : defaultUrl);
            },
        // 父组件调用的下拉刷新
        refreshLocalData() {
            this.page = 1;
            this.is_no_more = false;
            this.pullUpOn = true;
            this.getRecommend(true);
        },

        // 父组件调用的触底加载
        handleReachBottom() {
            if (!this.pullUpOn || this.loadding || this.is_no_more) return;
            this.getRecommend();
        },

        initNav() {
            const sys = uni.getSystemInfoSync();
            this.top = sys.statusBarHeight || 20;

            // #ifdef MP-WEIXIN
            let menuButton = uni.getMenuButtonBoundingClientRect();
            if (menuButton) {
                this.navRightGap = (sys.screenWidth - menuButton.left) + 10;
            }
            // #endif

            // #ifndef MP-WEIXIN
            this.navRightGap = 15;
            // #endif
        },

        copyId(id) {
            uni.setClipboardData({
                data: String(id),
                success: () => {
                    uni.showToast({
                        title: 'ID已复制',
                        icon: 'none'
                    })
                }
            });
        },
        goToLogin() {
            this.$T.go('/others/login/login')
        },
        handleSaveSuccess() {
            uni.redirectTo({
                url: "/others/user/user"
            })
        },
        checkAvatar() {
            this.$request({
                url: "/api/user/check-avatar"
            }).then(res => {
                if (res.code == 0 && res.data.is_need_bind == 1) {
                    this.showProfileModal = true;
                }
            })
        }
    }
}
</script>

<style lang="scss" scoped>
/* 变量体系 */
$bg-gray: #F7F8FA;
$text-main: #222222;
$text-sub: #888888;
$card-radius: 24rpx; /* 大圆角更现代 */

.classic-container {
    min-height: 100vh;
    background-color: $bg-gray;
    position: relative;
    font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
    padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 1. 头部背景区 (质感提升) */
.header-section {
    width: 100%;
    height: 460rpx; /* 稍微加高，留出更多呼吸空间 */
    border-bottom-left-radius: 60rpx;
    border-bottom-right-radius: 60rpx;
    position: relative;
    padding: 0 40rpx;
    box-sizing: border-box;
    transition: background 0.4s ease;
    overflow: hidden;

    /* 增加高光渐变遮罩，使背景不单调 */
    .header-bg-glass {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%);
        pointer-events: none;
    }

    .user-info-row {
        display: flex;
        align-items: center;
        margin-top: 20rpx;
        position: relative;
        z-index: 2;

        .avatar-box {
            width: 120rpx;
            height: 120rpx;
            border-radius: 50%;
            border: 4rpx solid rgba(255, 255, 255, 0.4); /* 柔和边框 */
            box-shadow: 0 8rpx 16rpx rgba(0,0,0,0.1); /* 头像阴影 */
            margin-right: 30rpx;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;

            .user-avatar {
                width: 100%;
                height: 100%;
                border-radius: 50%;
            }

            .avatar-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .info-content {
            flex: 1;
            color: #fff;
            min-width: 0;

            .name-line {
                display: flex;
                align-items: center;
                margin-bottom: 16rpx;

                .nickname {
                    font-size: 40rpx;
                    font-weight: 800;
                    margin-right: 16rpx;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 320rpx;
                    text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.1); /* 提升文字清晰度 */
                }

                .level-badge {
                    display: flex;
                    align-items: center;
                    background: linear-gradient(135deg, #FFE8B5 0%, #F5C563 100%); /* 渐变金 */
                    color: #6D4C00;
                    padding: 4rpx 14rpx;
                    border-radius: 20rpx;
                    flex-shrink: 0;
                    box-shadow: 0 4rpx 8rpx rgba(0,0,0,0.1);

                    .level-txt {
                        font-size: 20rpx;
                        font-weight: 900;
                        margin-left: 6rpx;
                    }
                }
            }

            /* 胶囊状 ID 标签 */
            .id-capsule {
                display: inline-flex;
                align-items: center;
                background: rgba(0, 0, 0, 0.15);
                padding: 6rpx 20rpx;
                border-radius: 30rpx;
                font-size: 22rpx;
                font-family: monospace; /* 数字更清晰 */
                backdrop-filter: blur(4px);

                &.empty {
                    font-family: inherit;
                    font-size: 24rpx;
                }

                .copy-icon-wrap {
                    margin-left: 12rpx;
                    opacity: 0.8;
                }
            }
        }

        .checkin-btn {
            background: linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border: 1rpx solid rgba(255, 255, 255, 0.5);
            color: #fff;
            font-size: 24rpx;
            font-weight: bold;
            padding: 10rpx 28rpx;
            border-radius: 30rpx;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            backdrop-filter: blur(4px);
        }
    }
}

/* 2. 悬浮内容区 */
.main-content {
    padding: 0 24rpx;
    margin-top: -100rpx; /* 增加悬浮跨度 */
    position: relative;
    z-index: 10;
}

/* 通用白卡 (软阴影 + 大圆角) */
.white-card {
    background: #ffffff;
    border-radius: $card-radius;
    margin-bottom: 24rpx;
    box-shadow: 0 10rpx 30rpx rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

/* 2.1 资产卡片排版优化 */
.assets-card {
    display: flex;
    padding: 36rpx 0 16rpx;

    /* ⛔ 这里**不能**写死宽度。资产栏最多 5 项(余额/佣金/积分/优惠券/收藏),
       但每一项后台都能单独关(is_show) —— 原来写死 width:20% 是按"一定有 5 项"算的,
       关掉两项就只占 60%,左边挤成一堆、右边空一大块。
       flex:1 按**实际渲染出来的项数**均分:3 项各 1/3、4 项各 1/4，都自动铺满。

       flex-wrap 也一并去掉:5 项以内本就该在一行,留着 wrap 反而会在窄屏或
       字体放大时把最后一项挤到第二行,看起来像少了一项。 */
    .asset-item {
        flex: 1;
        min-width: 0;   /* 不加这句,长数字(如余额 12345.67)会把 flex 项撑开、重新挤歪 */
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 20rpx;
        box-sizing: border-box;

        .num {
            font-size: 38rpx; /* 加大数字 */
            font-weight: 900; /* 加粗 */
            margin-bottom: 10rpx;
            font-family: 'DIN Alternate', 'Helvetica Neue', sans-serif; /* 采用数字优美的字体 */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 90%;
        }

        .label {
            font-size: 24rpx;
            color: $text-sub;
            font-weight: 500;
        }
    }
}

/* 通用卡片标题栏 */
.card-header {
    padding: 30rpx 30rpx 20rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;

    .title {
        font-size: 32rpx;
        font-weight: 800;
        color: $text-main;
    }

    .more {
        display: flex;
        align-items: center;
        font-size: 24rpx;
        color: #999;

        text {
            margin-right: 4rpx;
        }
    }
}

/* 网格布局 (订单/工具) */
.classic-row-box {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    padding: 20rpx 0 16rpx;
    box-sizing: border-box;
    justify-content: flex-start;

    .classic-col-item {
        width: 20%;
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 24rpx;
        box-sizing: border-box;

        .classic-icon-wrap {
            position: relative;
            width: 68rpx; /* 适当放大外框 */
            height: 68rpx;
            margin-bottom: 16rpx;
            display: flex;
            align-items: center;
            justify-content: center;

            .item-icon-img {
                width: 56rpx;
                height: 56rpx;
                display: block;
            }

            /* 现代化红点角标 */
            .badge {
                position: absolute;
                top: -8rpx;
                right: -16rpx;
                color: #fff;
                font-size: 20rpx;
                font-weight: bold;
                font-family: 'DIN Alternate', sans-serif;
                min-width: 32rpx;
                height: 32rpx;
                padding: 0 8rpx;
                border-radius: 32rpx;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 3rpx solid #fff; /* 白边让角标更立体 */
                z-index: 2;
                box-shadow: 0 4rpx 8rpx rgba(0,0,0,0.1);
            }
        }

        .item-name {
            font-size: 24rpx;
            color: #444;
            font-weight: 500;
            text-align: center;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    &.cols-4 .classic-col-item {
        width: 25%;
    }
}

/* 列表布局 (菜单) */
.classic-list-box {
    width: 100%;
    padding: 0 10rpx; /* 内缩一点更精致 */

    .list-row-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 28rpx 20rpx;
        border-bottom: 1rpx solid #f2f2f2; /* 更柔和的线 */

        &:last-child {
            border-bottom: none;
        }

        .left-part {
            display: flex;
            align-items: center;
            flex: 1;

            .list-icon-img {
                width: 40rpx;
                height: 40rpx;
                margin-right: 24rpx;
                flex-shrink: 0;
            }

            .list-txt {
                font-size: 28rpx;
                color: $text-main;
                font-weight: 500;
            }
        }
    }
}

/* 2.5 推荐好物 (设计感分割线) */
.recommend-section {
    margin-top: 40rpx;

    .divider-title {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30rpx;

        /* 渐变修饰线 */
        .line-grad {
            width: 80rpx;
            height: 2rpx;
            &.left { background: linear-gradient(to right, transparent, #ccc); }
            &.right { background: linear-gradient(to left, transparent, #ccc); }
        }

        .dot {
            width: 6rpx;
            height: 6rpx;
            background: #ccc;
            border-radius: 50%;
            margin: 0 10rpx;
            transform: rotate(45deg);
        }

        .text {
            font-size: 30rpx;
            color: $text-main;
            font-weight: 800;
            letter-spacing: 2rpx;
            margin: 0 16rpx;
        }
    }

    .goods-item-wrap {
        box-sizing: border-box;
        height: 100%;
    }

    ::v-deep .uni-grid-item {
        padding: 0 !important;
    }
}

/* 版权信息 */
.footer-copyright {
    text-align: center;
    color: #c0c0c0;
    font-size: 22rpx;
    padding: 30rpx 0;
    letter-spacing: 1rpx;
}

/* ================== 交互动效 ================== */
.checkin-hover {
    opacity: 0.7;
    transform: scale(0.95);
}

.item-hover {
    background-color: #FAFAFA !important;
    opacity: 0.8;
}

/* 图标点击微缩放 */
.icon-hover {
    transform: scale(0.92);
    transition: transform 0.1s;
    opacity: 0.8;
}
</style>