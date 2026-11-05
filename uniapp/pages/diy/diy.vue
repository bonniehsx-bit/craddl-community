<template>
  <tui-page class="container" :useCustomNav="true" :transparent-nav="true" :showBackBtn="true" :pageQueryParams="id ? { id: id } : null">
    <tui-diy-view :value="pageData" :pageParams="pageParams" :bgColor="bgColor"></tui-diy-view>
  </tui-page>
</template>

<script>
export default {
  data() {
    return {
      id: '',
      title: '',
      pageData: '',
      pageParams: null,
      bgColor: '',
    }
  },
  onLoad(e) {
    this.$util.showLoading();
    if (e.id) {
      this.id = e.id;
      this.getDiyData();
    } else {
      uni.showModal({
        title: '提示',
        content: '参数不正确！',
        showCancel: false,
        success: () => {
          uni.navigateBack();
        }
      })
    }
  },
  onShareAppMessage() {
    // #ifdef MP-WEIXIN
    let path = '/pages/diy/diy?id=' + this.id;
    const user = uni.getStorageSync('USER');
    if (user && user.id) {
      path += "&pid=" + user.id;
    }
    return {
      title: this.title,
      path: path
    }
    // #endif
  },
  methods: {
    getDiyData() {
      this.$request({
        url: "/api/index/diy-page",
        data: {
          id: this.id
        }
      }).then(res => {
        this.$util.hideLoading();
        if (res.code == 0) {
          this.title = res.data.name;
          this.pageData = res.data.page_data;
          this.pageParams = res.data.page_params || null;
          this.bgColor = res.data.bg_color || '';
          this.applyPageTitle();
        }
      }).catch(() => {
        this.$util.hideLoading();
      })
    },

    // 用后台给这个自定义页配的名称当页面标题。
    // 本页导航是沉浸式透明(useCustomNav + transparentNav),导航条上本就不显示标题,
    // 所以这里设的是**系统层面**的标题,它决定:
    //   H5     → 浏览器标签页与历史记录的 document.title
    //   小程序 → 任务管理器标题,以及未自定义时的默认分享标题
    //   App    → titleNView 已关、原生标题栏不存在,调用无效但无害
    // 注:setNavigationBarTitle 与 setNavigationBarColor 不同,它不会把原生导航唤回来
    //    (见规范 §1.6 红线②),custom 页面可以照常调。
    applyPageTitle() {
      const t = (this.title || '').trim();
      if (!t) return;   // 后台没配名称就保留 pages.json 的默认值,别设成空白
      uni.setNavigationBarTitle({ title: t });
    },
  }
}
</script>

<style lang="scss" scoped>
.container {
  background-color: #f7f7f7;
  min-height: 100vh;
}
</style>
