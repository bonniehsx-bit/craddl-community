<template>
  <tui-page class="container">

    <d-subscribe></d-subscribe>

    <!-- 主题就绪(知道这个商城用哪套首页)才渲染主题,避免先闪 DIY 再切到客户主题;没就绪时由下面的骨架屏垫着 -->
    <block v-if="themeReady">
      <!-- 默认主题（兜底）：DIY 装修引擎;模块全部画完会发 rendered,撤掉骨架屏 -->
      <DiyTheme
        ref="theme"
        :pageData="pageData"
        :pageParams="pageParams"
        @rendered="hideSkeleton" />
    </block>

    <!-- 骨架屏:模块画出来之前先垫一层,画完淡出。底栏每次切回首页都会重建页面,不垫就是一下白屏。
         只用 view + 样式、不用组件,页面第一帧就能画出来 -->
    <view v-if="showSkeleton" :class="['home-skeleton', skeletonLeaving ? 'is-leaving' : '']" :style="skeletonStyle">
      <view class="sk-head"><view class="sk-head-line"></view></view>
      <view class="sk-search"></view>
      <view class="sk-banner"></view>
      <view class="sk-grid">
        <view class="sk-grid-item"><view class="sk-icon"></view><view class="sk-label"></view></view>
        <view class="sk-grid-item"><view class="sk-icon"></view><view class="sk-label"></view></view>
        <view class="sk-grid-item"><view class="sk-icon"></view><view class="sk-label"></view></view>
        <view class="sk-grid-item"><view class="sk-icon"></view><view class="sk-label"></view></view>
        <view class="sk-grid-item"><view class="sk-icon"></view><view class="sk-label"></view></view>
      </view>
      <view class="sk-title"></view>
      <view class="sk-goods">
        <view class="sk-card"><view class="sk-img"></view><view class="sk-line"></view><view class="sk-line sk-short"></view></view>
        <view class="sk-card"><view class="sk-img"></view><view class="sk-line"></view><view class="sk-line sk-short"></view></view>
        <view class="sk-card"><view class="sk-img"></view><view class="sk-line"></view><view class="sk-line sk-short"></view></view>
        <view class="sk-card"><view class="sk-img"></view><view class="sk-line"></view><view class="sk-line sk-short"></view></view>
      </view>
    </view>

    <!-- #ifdef H5 || APP-PLUS -->
    <view class="craddl-attribution">
      <!-- #ifdef H5 -->
      <a href="https://www.craddl.cn/" target="_blank" rel="noopener noreferrer">Powered by Craddl · www.craddl.cn</a>
      <!-- #endif -->
      <!-- #ifdef APP-PLUS -->
      <text @tap="openCraddlWebsite">Powered by Craddl · www.craddl.cn</text>
      <!-- #endif -->
      <text>Copyright © 2026 佛山今日鼎盛信息科技有限公司</text>
    </view>
    <!-- #endif -->

    <!-- ════════════════ 横切关注点（所有主题共享） ════════════════ -->

    <!-- 后台「开启首页登录」:自己的登录弹窗(小程序里直接手机号一键登录,不用跳页) -->
    <jx-login-sheet :show="showLoginSheet" @close="showLoginSheet = false" @success="onSheetLogin" />
    <!-- 弹窗登录成功后接着看要不要「完善资料」(规则同个人中心:后台「完善头像信息」= 强制完善,且还没完善过) -->
    <tui-user-avatar :visible="showProfileModal" :userData="profileUser" @save="onProfileSaved"
      @close="showProfileModal = false" />

    <view class="privacy-mask" v-if="showPrivacy" :style="themeStyle" @touchmove.stop.prevent>
      <view class="privacy-wrap">
        <view class="privacy-icon-box">
          <view class="privacy-shield"></view>
        </view>

        <view class="privacy-title">用户隐私保护提示</view>

        <view class="privacy-content">
          <view class="privacy-text">
            感谢您信任并使用我们的服务。我们非常重视您的数据安全。在使用前，请您仔细阅读
            <text class="privacy-link" @click="openPrivacy">{{ privacyContractName }}</text>
            以了解我们如何保护您的个人信息。
          </view>
          <view class="privacy-text sub-text">
            点击“同意并继续”即表示您已阅读并同意全部条款。
          </view>
        </view>

        <view class="privacy-actions">
          <button class="btn-cancel" @click="closePrivacy">先逛逛</button>
          <button id="agree-btn" open-type="agreePrivacyAuthorization"
                  @agreePrivacyAuthorization="handleAgreePrivacy" @click="handleAgreePrivacy" class="btn-agree">
            同意并继续
          </button>
        </view>
      </view>
    </view>
    <tui-modal :show="showNetworkModal" title="网络连接失败" content='检测到网络权限可能未开启，您可以在”设置“中检查网络'
               @click="showNetworkModal = false" @cancel="showNetworkModal = false"></tui-modal>
  </tui-page>
</template>

<script>
// ──── 主题分发区 ────────────────────────────────────────────────────
// 主题组件统一放置主包 themes/home/{plugin}/HomeTheme.vue，命名跟随插件走。
// 后端事件挂载机制（HomeThemeEvent）不变，由各插件 listener 注册到下拉框。
// 物理放主包是因为：微信小程序不允许主包引用子包组件（即使 uniapp 兼容也会有运行时报错）。
import DiyTheme from '@/themes/home/DiyTheme.vue';
// ────────────────────────────────────────────────────────────────

const HOME_THEME_CACHE_KEY = 'CACHED_HOME_THEME';

// 骨架屏顶部留出状态栏,首行高度对齐胶囊那一行(和装修顶部搜索条 style-kit immersiveTop 同一套算法),单位 px。
// 尺寸整个运行期不变,算一次记住:底栏每次切回首页都会重建页面,不必每次都同步调系统接口
let skeletonStyleCache = '';
function skeletonStyleOf() {
  if (!skeletonStyleCache) {
    let status = 0;
    let bar = 44;
    // #ifndef H5
    status = 20;
    try {
      const si = uni.getSystemInfoSync();
      if (si.statusBarHeight) status = si.statusBarHeight;
    } catch (e) {}
    // #endif
    // #ifdef MP-WEIXIN || MP-QQ || MP-BAIDU || MP-TOUTIAO
    try {
      const m = uni.getMenuButtonBoundingClientRect();
      if (m && m.top) {
        let margin = m.top - status;
        if (margin < 0) margin = 4;
        bar = margin * 2 + m.height;
        if (bar < 32) bar = 44;
      }
    } catch (e) {}
    // #endif
    skeletonStyleCache = 'padding-top:' + status + 'px;--sk-bar:' + bar + 'px;';
  }
  return skeletonStyleCache;
}

// 本次打开只提示一次「去登录」(模块级:切 tab 页面实例不销毁,但冷启动会重置)
let homeLoginPrompted = false;

export default {
  components: {
    DiyTheme,
  },
  data() {
    // 启动瞬间读缓存：
    //  - 命中（老用户）：themeReady=true，直接渲染对应主题，0 闪烁
    //  - 未命中（首次安装）：themeReady=false，先渲染骨架屏，
    //    等接口返回 home_theme 后再渲染目标主题，永远不会先闪 DIY
    const cachedTheme = uni.getStorageSync(HOME_THEME_CACHE_KEY) || '';
    return {
      showNetworkModal: false,
      pageData: null,
      pageParams: null,
      themeReady: !!cachedTheme,
      // 骨架屏:DIY 首页(或还不知道用哪套主题时)先垫着,模块画完淡出;其他主题自己管加载,不垫
      showSkeleton: true,
      skeletonLeaving: false,
      skeletonStyle: skeletonStyleOf(),
      // 隐私
      showPrivacy: false,
      // 小程序隐私弹窗检查是异步的:检查完、用户点了同意,才能去登录页(否则隐私弹窗被丢在后面)
      privacyReady: true,
      showLoginSheet: false,
      showProfileModal: false,
      profileUser: {},
      privacyContractName: '《用户隐私保护指引》',
      pageBgColor: '#FFF',
    };
  },
  // 商城配置不挂成 computed(小程序会把 computed 整个 setData 给视图层,整份配置约 6KB),
  // 下面直接监听 store 里要用的两个字段
  watch: {
    // 后台「开启首页登录」:商城配置可能在 onShow 之后才拉回来,拉回来时再判一次
    '$store.state.mall.setting.is_home_login'() {
      this.checkHomeLogin();
    },
    // 监听 Vuex 中商城配置的 home_theme 变化（一次拉取后即刻生效）
    '$store.state.mall.setting.home_theme': {
      immediate: true,
      handler(val) {
        // val 可能为空（接口未回）；为空时不要把 themeName 设为 'diy'，
        // 否则首次访问会先闪一下 DIY 再切到目标主题
        if (val === undefined || val === null || val === '') return;
        this.applyTheme();
      },
    },
  },
  onLoad() {
    this.initData();
    this.reportVisit();
    // #ifdef MP-WEIXIN
    this.checkPrivacySetting();
    // #endif
    this.checkNetworkStatus();
    // 兜底:3 秒还没撤(比如没收到模块画完的通知),有内容就撤掉,别让骨架屏一直盖着页面
    setTimeout(() => {
      if (this.pageData) this.hideSkeleton();
    }, 3000);
  },
  onShow() {
    this.checkHomeLogin();
    // 主题层 onActivate 由壳层主动调用（component 不支持 onShow）
    if (this.$refs.theme && typeof this.$refs.theme.onActivate === 'function') {
      this.$refs.theme.onActivate();
    }
  },
  onHide() {
    if (this.$refs.theme && typeof this.$refs.theme.onDeactivate === 'function') {
      this.$refs.theme.onDeactivate();
    }
  },
  onPullDownRefresh() {
    Promise.all([
      this.$store.dispatch('mall/fetchMallConfig', true),
      this.getIndexData(),
    ]).finally(() => {
      uni.stopPullDownRefresh();
    });
  },
  methods: {
    // #ifdef APP-PLUS
    openCraddlWebsite() {
      plus.runtime.openURL('https://www.craddl.cn/');
    },
    // #endif
    initData() {
      this.$store.dispatch('mall/fetchMallConfig');

      // 读缓存、赋值放到页面第一帧之后:onLoad 里同步做的事会推迟第一帧(骨架屏)出来,
      // 这份缓存约 70KB,读出来再转成响应式数据要十几到几十毫秒
      setTimeout(() => {
        const cachedIndexData = uni.getStorageSync('INDEX_DATA');
        if (cachedIndexData) {
          this.pageData = cachedIndexData.page_data || cachedIndexData;
          this.pageParams = cachedIndexData.page_params || null;
          // 记下当前显示内容的原文(不放 data,免得跟着 setData 走),接口回来没变就不重画,见 getIndexData
          this.indexDataRaw = JSON.stringify(cachedIndexData);
        }
        this.getIndexData();
      }, 0);
    },

    // 定下首页主题,并记进本地缓存。和当前值一样也要记:原来只在「变了」时才写,
    // 默认主题 diy 和初始值相同永远写不进去,底栏每次切回首页都会重建页面,于是每次都先白屏转圈等接口
    applyTheme() {
      this.themeReady = true;
      uni.setStorageSync(HOME_THEME_CACHE_KEY, 'diy');
    },

    // 撤骨架屏:先淡出再拿掉,淡出时就不挡点击
    hideSkeleton() {
      if (!this.showSkeleton || this.skeletonLeaving) return;
      this.skeletonLeaving = true;
      setTimeout(() => {
        this.showSkeleton = false;
      }, 160);
    },

    reportVisit() {
      let touristId = uni.getStorageSync('H5_TOURIST_V_ID');
      if (!touristId) {
        touristId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
          var r = Math.random() * 16 | 0,
              v = c == 'x' ? r : (r & 0x3 | 0x8);
          return v.toString(16);
        });
        uni.setStorageSync('H5_TOURIST_V_ID', touristId);
      }

      this.$request({
        url: '/api/index/visit-report',
        method: 'POST',
        data: { tourist_id: touristId },
      }).catch(e => {
        console.error('统计接口上报失败', e);
      });
    },

    getIndexData() {
      return this.$request({ url: '/api/index/index' }).then(res => {
        if (res.code == 0) {
          // 和正在显示的一样就不重新赋值、不重写缓存:重新赋值会让每层组件把整页数据再深拷贝比对一遍,
          // 写缓存又是一次约 70KB 的同步写,都压在首页刚画出来的那一刻
          const raw = JSON.stringify(res.data);
          if (raw !== this.indexDataRaw) {
            this.indexDataRaw = raw;
            this.pageData = res.data.page_data;
            this.pageParams = res.data.page_params || null;
            uni.setStorageSync('INDEX_DATA', res.data);
          }
          this.pageBgColor = res.data.title_color || '#FFF';
          // 主题码兜底：当商城配置的 home_theme watch 还没把 themeReady 打开时，从首页接口取
          if (res.data.home_theme) {
            this.applyTheme();
          } else if (!this.themeReady) {
            // 后端没返回时落到默认 diy（不再阻塞）
            this.applyTheme();
          }
          uni.setNavigationBarTitle({ title: res.data.name });
        } else {
          // 接口失败也不要永远卡在骨架屏
          if (!this.themeReady) {
            this.themeReady = true;
          }
          if (!this.pageData) this.hideSkeleton();
          uni.showToast({ title: res.msg, icon: 'none' });
        }
      }).catch(err => {
        console.error('首页数据加载失败:', err);
        if (!this.themeReady) {
          this.themeReady = true;
        }
        if (!this.pageData) this.hideSkeleton();
      });
    },

    // #ifdef MP-WEIXIN
    checkPrivacySetting() {
      if (wx.getPrivacySetting) {
        this.privacyReady = false;
        wx.getPrivacySetting({
          success: res => {
            if (res.needAuthorization) {
              this.showPrivacy = true;
              this.privacyContractName = res.privacyContractName;
            }
          },
          complete: () => {
            this.privacyReady = true;
            this.checkHomeLogin();
          },
        });
      }
    },
    openPrivacy() {
      wx.openPrivacyContract({
        fail: () => uni.showToast({ title: '打开协议失败', icon: 'none' }),
      });
    },
    handleAgreePrivacy() {
      this.showPrivacy = false;
      this.checkHomeLogin();
    },
    closePrivacy() {
      this.showPrivacy = false;
      this.checkHomeLogin();
    },
    // #endif

    /**
     * 后台「商城设置 → 登录注册 → 开启首页登录」。
     * 这个开关后台一直能存、接口也一直下发,但前端从没读过 —— 开了等于没开。
     * 开着且没登录:弹一次登录弹窗(jx-login-sheet),可以「先随便逛逛」(不强制跳登录页 ——
     * 用户还不知道里面有什么就被拦在登录页外,用户定的)。每次打开 App/小程序只弹一次,
     * 切 tab 回首页不再反复弹。审核版本后端会把它下发成 '0',这里不用管。
     */
    checkHomeLogin() {
      const s = this.$setting || {};
      if (String(s.is_home_login) !== '1' || this.$http.isLogin()) return;
      if (!this.privacyReady || this.showPrivacy || homeLoginPrompted) return;
      // 只在首页自己在前台时弹:配置从别的页面拉回来也会触发 watch
      const pages = getCurrentPages();
      const cur = pages.length ? pages[pages.length - 1] : null;
      if (!cur || cur.route !== 'pages/index/index') return;
      homeLoginPrompted = true;
      this.showLoginSheet = true;
    },

    /** 弹窗里登录成功:首页有和用户相关的内容(券、会员价),重新拉一次 */
    onSheetLogin() {
      this.getIndexData();
      this.checkProfileAfterLogin();
    },

    /**
     * 首页弹窗刚登录 / 注册完,接着问一次要不要完善资料 —— 这时候用户正在「开通账号」,填写率最高。
     * 只在这一刻检查,不在每次打开首页时查:首页还有隐私、登录、弹窗广告,不再叠弹窗。
     * 从别的入口登录的,进个人中心时照旧会弹(兜底)。
     */
    checkProfileAfterLogin() {
      this.$store.dispatch('user/fetchUserInfo')
        .catch(() => this.$store.getters['user/userInfo'])
        .then(user => {
          user = user || {};
          if (user.is_avatar == 1) return;
          return this.$request({ url: '/api/user/check-avatar' }).then(res => {
            if (res.code != 0 || !res.data || res.data.is_need_bind != 1) return;
            this.profileUser = user;
            // 等登录弹窗收起、「登录成功」提示过去再弹,不叠在一起
            setTimeout(() => { this.showProfileModal = true; }, 800);
          });
        })
        .catch(() => {});
    },

    onProfileSaved() {
      this.showProfileModal = false;
      this.$store.dispatch('user/fetchUserInfo').catch(() => {});
    },

    checkNetworkStatus() {
      uni.getNetworkType({
        success: res => {
          if (res.networkType === 'none') {
            this.showNetworkModal = true;
            uni.onNetworkStatusChange((e) => {
              if (e.isConnected) {
                this.showNetworkModal = false;
                uni.reLaunch({ url: '/pages/index/index' });
              }
            });
          }
        },
      });
    },
  },
};
</script>

<style lang="scss">

.craddl-attribution {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8rpx;
  padding: 24rpx 20rpx;
  background: #fff;
  color: #3c4043;
  font-size: 24rpx;
  line-height: 1.5;
  text-align: center;

  a { color: inherit; text-decoration: underline; }
}
/* 首页骨架屏:通用商城首页轮廓(顶栏 / 搜索 / 轮播 / 宫格 / 商品),各家装修不同,只求「在加载」不求一模一样。
   盖在模块上面(DIY 模块最高 z-index 999、音乐按钮 998),在底栏 / 隐私弹窗(9999)、登录弹窗(10000)下面。
   类名都套在 .home-skeleton 下:组件开了全局样式,页面里的裸类名会串到模块里 */
.home-skeleton {
  position: fixed; left: 0; right: 0; top: 0; bottom: 0; z-index: 1000;
  background: #fff; overflow: hidden; box-sizing: border-box;
  transition: opacity .16s ease;
  &.is-leaving { opacity: 0; pointer-events: none; }
  /* 扫光:只动 transform */
  &::after {
    content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 50%;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .7) 50%, rgba(255, 255, 255, 0) 100%);
    transform: translateX(-100%);
    animation: home-sk-shine 1.3s ease-in-out infinite;
  }
  .sk-head { height: var(--sk-bar); display: flex; align-items: center; padding: 0 24rpx; }
  .sk-head-line, .sk-search, .sk-banner, .sk-icon, .sk-label, .sk-title, .sk-img, .sk-line { background: #eef0f3; }
  .sk-head-line { width: 220rpx; height: 32rpx; border-radius: 8rpx; }
  .sk-search { margin: 8rpx 24rpx 0; height: 68rpx; border-radius: 34rpx; }
  .sk-banner { margin: 24rpx; height: 300rpx; border-radius: 20rpx; }
  .sk-grid { display: flex; justify-content: space-between; padding: 0 32rpx; }
  .sk-grid-item { display: flex; flex-direction: column; align-items: center; }
  .sk-icon { width: 88rpx; height: 88rpx; border-radius: 50%; }
  .sk-label { margin-top: 14rpx; width: 72rpx; height: 22rpx; border-radius: 6rpx; }
  .sk-title { margin: 40rpx 24rpx 24rpx; width: 200rpx; height: 34rpx; border-radius: 8rpx; }
  .sk-goods { display: flex; flex-wrap: wrap; justify-content: space-between; padding: 0 24rpx; }
  .sk-card { width: 339rpx; margin-bottom: 24rpx; }
  .sk-img { height: 339rpx; border-radius: 16rpx; }
  .sk-line { margin-top: 16rpx; height: 26rpx; border-radius: 6rpx; }
  .sk-short { width: 50%; }
}
@keyframes home-sk-shine { to { transform: translateX(300%); } }

button::after { border: none; }
button {
  position: relative; display: block; margin-left: auto; margin-right: auto; padding: 0;
  box-sizing: border-box; text-align: center; text-decoration: none;
  -webkit-tap-highlight-color: transparent; overflow: hidden; color: #000; background-color: #fff;
}

/* 隐私弹窗（保持原样） */
.privacy-mask {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.6); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.3s ease-out forwards;
  backdrop-filter: blur(2px);
}
.privacy-wrap {
  width: 620rpx; background: #ffffff; border-radius: 32rpx;
  padding: 50rpx 40rpx; box-sizing: border-box; position: relative;
  display: flex; flex-direction: column; align-items: center;
  box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.15);
  animation: popIn 0.5s cubic-bezier(0.19, 1, 0.22, 1) forwards;
  opacity: 0; transform: scale(0.8);
}
.privacy-icon-box {
  width: 100rpx; height: 100rpx;
  background: var(--theme-color-bg);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  margin-bottom: 30rpx;
}
.privacy-shield {
  width: 40rpx; height: 48rpx; background: var(--theme-color);
  clip-path: polygon(50% 0%, 100% 20%, 100% 100%, 50% 100%, 0% 100%, 0% 20%);
  border-radius: 0 0 50% 50%; position: relative;
}
.privacy-shield::after {
  content: ''; position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%); width: 16rpx; height: 16rpx;
  border-radius: 50%; background: #fff;
}
.privacy-title { font-size: 36rpx; font-weight: 700; color: #1a1a1a; margin-bottom: 30rpx; text-align: center; }
.privacy-content { width: 100%; margin-bottom: 50rpx; }
.privacy-text { font-size: 28rpx; color: #555; line-height: 1.7; text-align: justify; }
.sub-text { margin-top: 15rpx; font-size: 24rpx; color: #999; }
.privacy-link { color: var(--theme-color); font-weight: 500; display: inline; padding: 0 4rpx; }
.privacy-actions { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 30rpx; }
.btn-cancel {
  flex: 1; height: 88rpx; line-height: 88rpx; border-radius: 44rpx;
  background: #f5f5f5; color: #666; font-size: 30rpx; font-weight: 500;
  transition: all 0.2s;
}
.btn-cancel:active { background: #e8e8e8; transform: scale(0.98); }
.btn-agree {
  flex: 1.5; height: 88rpx; line-height: 88rpx; border-radius: 44rpx;
  background: var(--theme-color);
  color: #fff; font-size: 30rpx; font-weight: 600;
  box-shadow: 0 8rpx 20rpx var(--theme-shadow);
  transition: all 0.2s;
}
.btn-agree:active { opacity: 0.9; transform: scale(0.98); box-shadow: 0 4rpx 10rpx var(--theme-shadow); }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes popIn {
  0% { opacity: 0; transform: scale(0.8); }
  60% { transform: scale(1.05); }
  100% { opacity: 1; transform: scale(1); }
}

.flex-y-center { display: flex; align-items: center; }
.flex-x-center { display: flex; justify-content: center; }
</style>
