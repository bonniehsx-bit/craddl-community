<template>
  <div class="login-container">
    <canvas id="canvas-bg" ref="canvasBg"></canvas>

    <div class="bg-overlay"></div>

    <div class="content-wrapper" @mousemove="handleMouseMove" @mouseleave="resetTilt" ref="wrapper">
      <div class="login-card glass-card" :style="tiltStyle">
        
        <!-- 左侧：品牌展示区 -->
        <div class="card-left">
          <div class="brand-content">
            <div class="logo-animate-box">
              <img v-if="siteLoginPic" :src="siteLoginPic" class="brand-img" alt="Logo" onerror="this.style.display='none'"/>
              <img v-else src="@/assets/logo/logo.png" class="brand-img default-logo" alt="Craddl"/>
            </div>
            <div class="text-box">
              <h2 v-if="siteLoginPic" class="welcome-text">WELCOME</h2>
              <p class="sub-text" :class="{ 'brand-slogan-en': !siteLoginPic }">{{ siteSlogan || (siteLoginPic ? siteSubName : '') || 'Business, on your terms.' }}</p>
              <p v-if="!siteLoginPic && !siteSlogan" class="brand-slogan-cn">生意，由你定义。</p>
            </div>
          </div>
          <div class="decor-glow glow-1"></div>
          <div class="decor-glow glow-2"></div>
        </div>

        <!-- 右侧：登录表单区 -->
        <div class="card-right">
          <div class="form-header">
            <h3 class="sys-title">商家登录</h3>
            <span class="sys-subtitle">Sign in to your merchant account</span>
          </div>

          <el-form
            ref="loginForm"
            :model="loginForm"
            :rules="loginRules"
            class="custom-login-form"
            auto-complete="on"
          >
            <!-- 账号 -->
            <el-form-item prop="username">
              <el-input
                ref="username"
                v-model="loginForm.username"
                placeholder="请输入手机号"
                name="username"
                type="text"
                tabindex="1"
                auto-complete="on"
                prefix-icon="el-icon-user"
              />
            </el-form-item>

            <!-- 密码 -->
            <el-form-item prop="password">
              <el-input
                :key="passwordType"
                ref="password"
                v-model="loginForm.password"
                :type="passwordType"
                placeholder="请输入密码"
                name="password"
                tabindex="2"
                auto-complete="on"
                prefix-icon="el-icon-lock"
                @keyup.enter.native="handleLogin"
              >
                <i slot="suffix" :class="passwordType === 'password' ? 'el-icon-view' : 'el-icon-c-scale-to-original'" class="pointer" @click="showPwd"></i>
              </el-input>
            </el-form-item>

            <!-- 验证码 -->
            <el-form-item prop="captcha">
              <div class="captcha-row">
                <el-input
                  ref="captcha"
                  v-model="loginForm.captcha"
                  placeholder="验证码"
                  name="captcha"
                  type="text"
                  tabindex="3"
                  auto-complete="off"
                  prefix-icon="el-icon-key"
                  @keyup.enter.native="handleLogin"
                  class="captcha-input"
                />
                <div class="captcha-img-box" @click="getCaptcha" title="点击刷新">
                  <img v-if="captchaImg" :src="captchaImg" alt="验证码"/>
                  <div v-else class="loading-text"><i class="el-icon-loading"></i></div>
                </div>
              </div>
            </el-form-item>

            <!-- 登录按钮 -->
            <el-button
              :loading="loading"
              type="primary"
              class="login-btn"
              @click.native.prevent="handleLogin"
            >
              <span v-if="!loading">立 即 登 录</span>
              <span v-else>鉴 权 中...</span>
            </el-button>
            
          </el-form>
        </div>
      </div>
    </div>

    <!-- [核心增量] 多店铺选择弹窗 (暗黑适配版) -->
    <el-dialog
      title="请选择要进入的店铺"
      :visible.sync="showMallDialog"
      width="420px"
      append-to-body
      :close-on-click-modal="false"
      custom-class="mall-select-dialog glass-dialog"
    >
      <div class="mall-list-container">
        <div class="mall-item" v-for="mall in mallList" :key="mall.mch_id" @click="selectMall(mall)">
          <img :src="mall.logo || 'https://cube.elemecdn.com/9/c2/f0ee8a3c7c9638a54940382568c9dpng.png'" class="mall-avatar" :alt="mall.mall_name"/>
          <div class="mall-info">
            <h4 class="m-name">{{ mall.mall_name }}</h4>
            <p class="m-role">
              {{ mall.mch_name }} 
              <span :class="['role-tag', mall.is_clerk ? 'clerk' : 'manager']">
                {{ mall.is_clerk ? '店员' : '店长' }}
              </span>
            </p>
          </div>
          <i class="el-icon-right list-arrow"></i>
        </div>
      </div>
    </el-dialog>

    <div class="login-footer">
      <div class="craddl-attribution">
        <a href="https://www.craddl.cn/" target="_blank" rel="noopener noreferrer">Powered by Craddl · www.craddl.cn</a>
        <span>Copyright © 2026 佛山今日鼎盛信息科技有限公司</span>
        <a href="licenses/CPAL-1.0.txt" target="_blank" rel="noopener">CPAL-1.0</a>
      </div>
      <div v-if="siteCopyright || siteRecord">
        <span v-if="siteCopyright" v-html="siteCopyright"></span>
        <span v-if="siteCopyright && siteRecord" class="divider">|</span>
        <a v-if="siteRecord" href="https://beian.miit.gov.cn/" target="_blank">{{ siteRecord }}</a>
      </div>
      <div v-else>
        Copyright © {{ year }} Craddl All Rights Reserved.
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import {validUsername} from '@/utils/validate'
import {mchLogin, getMchCaptcha} from '@/api/mch' // 引入新写的API
import {setMallId} from '@/utils/mall' // 引入设值方法
import {setCurrentMchId} from '@/utils/auth' // 同城多店：选店后需固化 mch_id

export default {
  name: 'Login',
  data() {
    const validateUsername = (rule, value, callback) => {
      if (!validUsername(value) && !/^1[3-9]\d{9}$/.test(value)) {
        callback(new Error('请输入正确的手机号'))
      } else {
        callback()
      }
    }
    const validatePassword = (rule, value, callback) => {
      if (value.length < 6) {
        callback(new Error('密码不能少于6位'))
      } else {
        callback()
      }
    }
    return {
      loginForm: {
        username: '',
        password: '',
        captcha: '',
        captcha_key: '' // [核心] 存储后端返回的key
      },
      loginRules: {
        username: [{required: true, trigger: 'blur', validator: validateUsername}],
        password: [{required: true, trigger: 'blur', validator: validatePassword}],
        captcha: [{required: true, trigger: 'blur', message: '请输入验证码'}]
      },
      loading: false,
      passwordType: 'password',
      redirect: undefined,
      captchaImg: '', // base64图片
      showMallDialog: false, // 控制选择弹窗
      mallList: [], // 暂存的商城列表
      year: new Date().getFullYear(),
      tiltStyle: {},
      // Canvas 相关
      canvas: null,
      ctx: null,
      particles: [],
      animationFrame: null
    }
  },
  computed: {
    ...mapState({
      siteTitle: state => state.site.setting.title,
      siteLogo: state => state.site.setting.logo,
      siteSubName: state => state.site.setting.slogan || state.site.setting.sub_name,
      siteSlogan: state => state.site.setting.slogan,
      siteLoginPic: state => state.site.setting.login_pic || state.site.setting.login_logo || state.site.setting.logo,
      siteCopyright: state => state.site.setting.copyright,
      siteRecord: state => state.site.setting.record
    })
  },
  watch: {
    $route: {
      handler: function (route) {
        this.redirect = route.query && route.query.redirect
      },
      immediate: true
    }
  },
  created() {
    this.getCaptcha()
  },
  mounted() {
    // 拉取全局站点配置
    if(this.$store.dispatch) this.$store.dispatch('getSiteSetting').catch(() => {})

    this.initCanvas()
    window.addEventListener('resize', this.resizeCanvas)
  },
  beforeDestroy() {
    cancelAnimationFrame(this.animationFrame)
    window.removeEventListener('resize', this.resizeCanvas)
  },
  methods: {
    // === Canvas 粒子动画 (颜色使用品牌橙/白色) ===
    initCanvas() {
      this.canvas = this.$refs.canvasBg
      this.ctx = this.canvas.getContext('2d')
      this.resizeCanvas()
      this.createParticles()
      this.animate()
    },
    resizeCanvas() {
      this.canvas.width = window.innerWidth
      this.canvas.height = window.innerHeight
    },
    createParticles() {
      this.particles = []
      const particleCount = window.innerWidth > 1000 ? 80 : 40
      for (let i = 0; i < particleCount; i++) {
        this.particles.push({
          x: Math.random() * this.canvas.width,
          y: Math.random() * this.canvas.height,
          vx: (Math.random() - 0.5) * 0.8, // 速度放慢一点，更优雅
          vy: (Math.random() - 0.5) * 0.8,
          size: Math.random() * 2,
          opacity: Math.random() * 0.5 + 0.1
        })
      }
    },
    animate() {
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height)

      this.particles.forEach((p, index) => {
        p.x += p.vx
        p.y += p.vy

        if (p.x < 0 || p.x > this.canvas.width) p.vx *= -1
        if (p.y < 0 || p.y > this.canvas.height) p.vy *= -1

        // 绘制点 (青白色)
        this.ctx.beginPath()
        this.ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2)
        this.ctx.fillStyle = `rgba(247, 147, 30, ${p.opacity})` // 品牌橙高光
        this.ctx.fill()

        // 连线
        for (let j = index + 1; j < this.particles.length; j++) {
          const p2 = this.particles[j]
          const dist = Math.sqrt((p.x - p2.x) ** 2 + (p.y - p2.y) ** 2)
          if (dist < 150) {
            this.ctx.beginPath()
            // 线条颜色：半透明青色
            this.ctx.strokeStyle = `rgba(247, 147, 30, ${0.15 * (1 - dist / 150)})`
            this.ctx.lineWidth = 0.5
            this.ctx.moveTo(p.x, p.y)
            this.ctx.lineTo(p2.x, p2.y)
            this.ctx.stroke()
          }
        }
      })
      this.animationFrame = requestAnimationFrame(this.animate)
    },

    // === 3D 卡片倾斜 (参数微调) ===
    handleMouseMove(e) {
      if (window.innerWidth < 800) return
      const wrapper = this.$refs.wrapper
      const { left, top, width, height } = wrapper.getBoundingClientRect()
      // 计算鼠标相对于中心的偏移量 (-1 到 1)
      const x = (e.clientX - left - width / 2) / (width / 2)
      const y = (e.clientY - top - height / 2) / (height / 2)

      // 旋转角度
      const rotateX = -y * 8  // 增加一点角度
      const rotateY = x * 8

      this.tiltStyle = {
        transform: `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`
      }
    },
    resetTilt() {
      this.tiltStyle = {
        transform: 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)'
      }
    },

    showPwd() {
      this.passwordType = this.passwordType === 'password' ? '' : 'password'
      this.$nextTick(() => {
        this.$refs.password.focus()
      })
    },

    // 获取验证码 (对接 PassportController::actionCaptcha)
    getCaptcha() {
      getMchCaptcha().then(res => {
        if (res.code === 0) {
          this.captchaImg = res.data.img
          this.loginForm.captcha_key = res.data.captcha_key
          this.loginForm.captcha = '' // 刷新后清空输入
        }
      }).catch(err => {
        console.error('获取验证码失败', err)
      })
    },
    handleLogin() {
      this.$refs.loginForm.validate(valid => {
        if (valid) {
          this.loading = true

          // 直接 dispatch Vuex 的 login action
          this.$store.dispatch('user/login', this.loginForm)
            .then((res) => {
              this.loading = false
              
              // [核心干预] 如果由于跨店情况，后端返回了 code=2
              if (res && res.code === 2) {
                this.mallList = res.data.malls || []
                this.showMallDialog = true
                return
              }

              this.$message.success('登录成功')
              // 跳转
              this.$router.push({path: this.redirect || '/'})
            })
            .catch((err) => {
              // 登录失败 (密码错、验证码错等)
              this.loading = false
              // 如果后端返回错误消息，通常 request.js 拦截器会处理，或者在这里显示
              if (err.msg) {
                this.$message.error(err.msg)
              }
              this.getCaptcha() // 刷新验证码
            })
        } else {
          console.log('校验失败')
          return false
        }
      })
    },

    // 用户在弹窗中选中了某家店铺
    selectMall(mall) {
      if (!mall || !mall.mch_id) return

      this.showMallDialog = false
      this.loading = true

      // 1. 固化上下文 Cookie：mch_id 用于同城多店精确定位，mall_id 兼容保留
      //    (request.js 会自动带上 X-Mch-Id 与 x-mall-id header)
      setMallId(mall.mall_id)
      setCurrentMchId(mall.mch_id)

      // 2. 原封不动地再触发一次登录（此时 header 里带了 mch_id，后端精确命中该店铺）
      this.$store.dispatch('user/login', this.loginForm)
        .then((res) => {
          this.loading = false
          if (res && res.code === 2) {
             // 理论上不可能走到这里，防御性编程
             this.$message.error('登录状态异常，请重试')
             return
          }
          this.$message.success('登录成功')
          this.$router.push({path: this.redirect || '/'})
        })
        .catch(err => {
          this.loading = false
          if (err.msg) this.$message.error(err.msg)
          this.getCaptcha()
        })
    }
  }
}
</script>

<style lang="scss" scoped>
/* ================= 核心变量定义 ================= */
$primary-color: #F7931E; /* Craddl 品牌橙 */
$bg-color-1: #111317;    /* Craddl 炭黑 */
$bg-color-2: #26211c;    /* 暖调深灰 */
$glass-bg: rgba(255, 255, 255, 0.03);
$glass-border: rgba(255, 255, 255, 0.1);
$text-white: #ffffff;
$text-gray: #aaa8a5;

.login-container {
  position: relative;
  width: 100%;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  /* 深空渐变背景 */
  background: radial-gradient(ellipse at bottom, $bg-color-2 0%, $bg-color-1 100%);
  overflow: hidden;
  font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

#canvas-bg {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  z-index: 0;
}

.bg-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  /* 添加一层暗色晕影 */
  background: radial-gradient(transparent 60%, rgba(0,0,0,0.8) 100%);
  pointer-events: none;
  z-index: 1;
}

.content-wrapper {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  width: 100%;
  box-sizing: border-box;
  z-index: 10;
  padding: 20px;
  perspective: 1200px; /* 增加透视深度 */
}

/* ================= 毛玻璃卡片 ================= */
.glass-card {
  display: flex;
  width: 960px;
  height: 540px;
  border-radius: 20px;
  /* 毛玻璃核心 */
  background: $glass-bg;
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border: 1px solid $glass-border;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);

  overflow: hidden;
  transition: transform 0.1s ease-out;
  animation: slideUpFade 280ms cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

@keyframes slideUpFade {
  from { opacity: 0; transform: translateY(16px) scale(0.99); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

/* === 左侧品牌区 === */
.card-left {
  position: relative;
  width: 45%;
  /* 稍微深一点的透明黑，用于突出右侧亮色 */
  background: rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #fff;
  border-right: 1px solid $glass-border;

  .brand-content {
    z-index: 2;
    text-align: center;
    padding: 30px;

    .logo-animate-box {
      margin-bottom: 30px;
      .brand-img {
        width: 160px;
        /* 发光 LOGO */
        filter: drop-shadow(0 0 15px rgba(247, 147, 30, 0.3));
        animation: float 6s ease-in-out infinite;
      }
      .default-logo {
        width: 112px;
        border-radius: 22px;
        filter: none;
      }
    }

    .text-box {
      .welcome-text {
        font-size: 36px;
        font-weight: 800;
        margin: 0 0 10px 0;
        letter-spacing: 2px;
        background: linear-gradient(135deg, #fff 0%, $primary-color 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
      }
      .sub-text {
        font-size: 14px;
        color: $text-gray;
        letter-spacing: 1px;
      }
      .brand-slogan-en {
        margin: 0;
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        font-weight: 500;
        line-height: 1.6;
        letter-spacing: 0.4px;
      }
      .brand-slogan-cn {
        margin: 10px 0 0;
        color: rgba(255, 255, 255, 0.56);
        font-size: 14px;
        line-height: 1.7;
        letter-spacing: 2px;
      }
    }
  }

  /* 背景装饰光晕 */
  .decor-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    z-index: 1;
    opacity: 0.4;
  }
  .glow-1 { width: 200px; height: 200px; background: #a66b32; top: -50px; left: -50px; }
  .glow-2 { width: 150px; height: 150px; background: #f7931e; bottom: -20px; right: -20px; }
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-12px); }
}

/* === 右侧表单区 === */
.card-right {
  width: 55%;
  padding: 60px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  /* 极淡的白色叠加 */
  background: rgba(255, 255, 255, 0.01);

  .form-header {
    margin-bottom: 40px;
    .sys-title {
      font-size: 28px;
      color: $text-white;
      font-weight: 600;
      margin: 0 0 5px 0;
    }
    .sys-subtitle {
      font-size: 14px;
      color: $text-gray;
    }
  }
}

/* === Element UI 深度定制 (适配暗黑玻璃风) === */
.custom-login-form {
  ::v-deep .el-form-item {
    margin-bottom: 25px;
  }

  /* 输入框改造 */
  ::v-deep .el-input__inner {
    border: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0;
    padding-left: 35px;
    height: 45px;
    line-height: 45px;
    background: transparent; /* 透明背景 */
    color: #fff;
    transition: all 0.3s;

    &::placeholder { color: rgba(255, 255, 255, 0.3); }

    &:focus {
      border-bottom-color: $primary-color;
      box-shadow: 0 10px 20px -10px rgba(247, 147, 30, 0.2); /* 底部发光 */
    }
    /* 自动填充背景色去除 (Chrome quirk) */
    &:-webkit-autofill {
      -webkit-box-shadow: 0 0 0px 1000px #1c1c1e inset !important;
      -webkit-text-fill-color: #fff !important;
    }
  }

  /* 图标颜色 */
  ::v-deep .el-input__prefix {
    left: 5px;
  }
  ::v-deep .el-input__suffix {
    right: 5px;
  }

  ::v-deep .el-input__prefix i, ::v-deep .el-input__suffix i {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.5);
    line-height: 45px;
    transition: color 0.3s;
  }

  ::v-deep .el-input__suffix i:hover {
    color: $primary-color;
    cursor: pointer;
  }

  ::v-deep .el-input__inner:focus + .el-input__prefix i {
    color: $primary-color;
  }
}

.captcha-row {
  display: flex;
  align-items: center;
  .captcha-input { flex: 1; margin-right: 15px; }
  .captcha-img-box {
    width: 120px;
    height: 42px;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    cursor: pointer;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.05);
    &:hover { border-color: $primary-color; }
    img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; mix-blend-mode: screen; }
    .loading-text { color: #fff; }
  }
}

/* 品牌按钮 */
.login-btn {
  width: 100%;
  height: 50px;
  font-size: 16px;
  font-weight: bold;
  letter-spacing: 4px;
  border: none;
  border-radius: 4px;
  /* 品牌橙渐变 */
  background: linear-gradient(110deg, #ffad47, #f7931e);
  color: #191b20;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
  z-index: 1;
  margin-top: 20px;

  &::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    transition: 0.5s;
    z-index: -1;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 20px rgba(247, 147, 30, 0.28); /* 柔和的品牌光晕 */
    &::before { left: 100%; }
  }
}

/* === 多店选择弹窗暗黑玻璃风格定制 === */
::v-deep .glass-dialog {
  background: rgba(25, 27, 32, 0.9) !important;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;

  .el-dialog__header {
    background: transparent;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    .el-dialog__title {
      color: $text-white;
      font-weight: 600;
      font-size: 18px;
    }
    .el-dialog__close {
      color: $text-gray;
      &:hover { color: $primary-color; }
    }
  }

  .el-dialog__body {
    padding: 0;
  }
}

.mall-list-container {
  max-height: 400px;
  overflow-y: auto;
  
  /* 滚动条暗黑风 */
  &::-webkit-scrollbar {
    width: 6px;
  }
  &::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    &:hover { background: rgba(0,243,255,0.3); }
  }

  .mall-item {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    background: transparent;

    &:hover {
      background: rgba(247, 147, 30, 0.05);
      border-left: 3px solid $primary-color;
      padding-left: 21px; /* 减掉 border-left 的宽度避免抖动 */

      .list-arrow {
        transform: translateX(5px);
        color: $primary-color;
      }
      .m-name {
        color: $primary-color;
      }
    }

    &:last-child {
      border-bottom: none;
    }

    .mall-avatar {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      object-fit: cover;
      margin-right: 16px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }

    .mall-info {
      flex: 1;
      overflow: hidden;

      .m-name {
        margin: 0 0 6px 0;
        font-size: 15px;
        color: $text-white;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 0.3s;
      }

      .m-role {
        margin: 0;
        font-size: 13px;
        color: $text-gray;
        display: flex;
        align-items: center;
        gap: 8px;

        .role-tag {
          font-size: 11px;
          padding: 2px 8px;
          border-radius: 10px;
          font-weight: bold;
          
          &.clerk {
            background: rgba(169, 172, 179, 0.2);
            color: #aab2bd;
            border: 1px solid rgba(169, 172, 179, 0.3);
          }
          &.manager {
            background: rgba(247, 147, 30, 0.1);
            color: $primary-color;
            border: 1px solid rgba(247, 147, 30, 0.3);
          }
        }
      }
    }

    .list-arrow {
      font-size: 18px;
      color: rgba(255,255,255,0.3);
      transition: all 0.3s;
    }
  }
}

/* === 底部 === */
.login-footer {
  position: relative;
  padding-bottom: 20px;
  width: 100%;
  text-align: center;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.3);
  z-index: 5;
}

.craddl-attribution {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 4px 12px;
  padding: 0 12px;
  margin-bottom: 4px;
  color: #fff;

  a { color: inherit; text-decoration: underline; }
}

.pointer { cursor: pointer; }

/* 响应式 */
@media screen and (max-width: 960px) {
  .glass-card { width: 90%; height: auto; flex-direction: column; }
  .card-left { width: 100%; min-height: 200px; border-right: none; border-bottom: 1px solid $glass-border; }
  .card-left .brand-content { padding: 24px 18px; display: flex; flex-direction: column; align-items: center; }
  .card-left .brand-content .logo-animate-box { margin: 0 0 18px; }
  .card-left .brand-content .logo-animate-box .brand-img { width: 70px; }
  .card-left .brand-content .logo-animate-box .default-logo { border-radius: 14px; }
  .card-left .welcome-text { font-size: 24px; }

  .card-right { width: 100%; padding: 40px 20px; }
}
</style>
