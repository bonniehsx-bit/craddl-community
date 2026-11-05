<template>
  <div class="login-container">
    <!-- 高保真云烟背景：WebGL fragment shader fBm 噪声，通过 three.js 全屏 quad 渲染 -->
    <canvas id="fog-bg" ref="fogBg"></canvas>

    <canvas id="canvas-bg" ref="canvasBg"></canvas>

    <div class="bg-overlay"></div>

    <div class="content-wrapper" @mousemove="handleMouseMove" @mouseleave="resetTilt" ref="wrapper">
      <div class="login-card glass-card" :style="tiltStyle">

        <div class="card-left">
          <div class="brand-content">
            <div class="logo-animate-box">
              <img v-if="siteLoginPic" :src="siteLoginPic" class="brand-img" alt="站点 Logo">
              <img v-else :src="defaultLogo" class="brand-img default-logo" alt="Craddl">
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

        <div class="card-right">
          <div class="form-header">
            <h3 class="sys-title">{{ siteTitle || '用户登录' }}</h3>
            <span class="sys-subtitle">Sign in to your account</span>
          </div>

          <el-form ref="loginForm" :model="loginForm" :rules="loginRules" class="custom-login-form">

            <el-form-item prop="username">
              <el-input
                v-model="loginForm.username"
                type="text"
                placeholder="请输入账号"
                prefix-icon="el-icon-user"
                tabindex="1"
                auto-complete="on"
              ></el-input>
            </el-form-item>

            <el-form-item prop="password">
              <el-input
                v-model="loginForm.password"
                :type="passwordType"
                placeholder="请输入密码"
                prefix-icon="el-icon-lock"
                tabindex="2"
                auto-complete="on"
                @keyup.enter.native="handleLogin"
              >
                <i slot="suffix" class="el-icon-view pointer" @click="showPwd"></i>
              </el-input>
            </el-form-item>

            <el-form-item prop="captcha">
              <div class="captcha-row">
                <el-input
                  v-model="loginForm.captcha"
                  placeholder="验证码"
                  prefix-icon="el-icon-key"
                  tabindex="3"
                  @keyup.enter.native="handleLogin"
                  class="captcha-input"
                ></el-input>
                <div class="captcha-img-box" @click="getCaptcha" title="点击刷新验证码">
                  <img v-if="captchaImg" :src="captchaImg" alt="code" />
                  <div v-else class="loading-text"><i class="el-icon-loading"></i></div>
                </div>
              </div>
            </el-form-item>

            <div class="action-row">
              <el-checkbox v-model="loginForm.rememberMe">记住账号</el-checkbox>
            </div>

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
        Copyright © {{ year }} Craddl 商城 All Rights Reserved.
      </div>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex'
import { getCaptchaCode } from '@/api/login'
import Cookies from 'js-cookie'
import defaultLogo from '@/assets/logo/logo.png'
import { removeMallId } from '@/utils/auth'
import * as THREE from 'three'

export default {
  name: 'Login',
  data() {
    return {
      defaultLogo: defaultLogo,
      captchaImg: '',
      loginForm: {
        username: '',
        password: '',
        rememberMe: false,
        captcha: '',
        captcha_key: '',
        login_type: 0,
        uuid: ''
      },
      loginRules: {
        username: [{ required: true, trigger: 'blur', message: '请输入账号' }],
        password: [{ required: true, trigger: 'blur', message: '请输入密码' }],
        captcha: [{ required: true, trigger: 'blur', message: '请输入验证码' }]
      },
      loading: false,
      passwordType: 'password',
      year: new Date().getFullYear(),
      tiltStyle: {},
      // Canvas 粒子
      canvas: null,
      ctx: null,
      particles: [],
      animationFrame: null,
      // WebGL 云烟 shader
      fog: { renderer: null, scene: null, camera: null, material: null, raf: null, start: 0 }
    }
  },
  computed: {
    ...mapState({
      // 浏览器标签页用 title，没填 fallback name
      siteTitle: state => state.site.setting.title || state.site.setting.name,
      siteLogo: state => state.site.setting.logo,
      // 登录页副标题优先用独立的 slogan 字段，留空时 fallback 到 sub_name
      siteSubName: state => state.site.setting.slogan || state.site.setting.sub_name,
      siteSlogan: state => state.site.setting.slogan,
      siteLoginPic: state => state.site.setting.login_pic || state.site.setting.login_logo || state.site.setting.logo,
      siteCopyright: state => state.site.setting.copyright,
      siteRecord: state => state.site.setting.record
    })
  },
  created() {
    this.getCookie()
    this.getCaptcha()
  },
  mounted() {
    // 假设你有这个 action，如果没有请注释掉
    if(this.$store.dispatch) this.$store.dispatch('getSiteSetting').catch(() => {})
    this.initFog()
    this.initCanvas()
    window.addEventListener('resize', this.onResize)
  },
  beforeDestroy() {
    cancelAnimationFrame(this.animationFrame)
    if (this.fog.raf) cancelAnimationFrame(this.fog.raf)
    if (this.fog.renderer) {
      this.fog.renderer.dispose()
      if (this.fog.material) this.fog.material.dispose()
    }
    window.removeEventListener('resize', this.onResize)
  },
  methods: {
    // === 高保真云烟背景（WebGL fragment shader + fBm 噪声） ===
    initFog() {
      const canvas = this.$refs.fogBg
      if (!canvas) return
      const w = window.innerWidth
      const h = window.innerHeight

      try {
        const renderer = new THREE.WebGLRenderer({ canvas, antialias: false, alpha: true })
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5)) // 控分辨率换性能
        renderer.setSize(w, h, false)

        const scene = new THREE.Scene()
        const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1)

        const material = new THREE.ShaderMaterial({
          uniforms: {
            uTime: { value: 0 },
            uResolution: { value: new THREE.Vector2(w, h) },
            // 柔和星云配色（莫兰迪偏深）：低饱和、不刺眼、显高级
            uColorBg:     { value: new THREE.Color(0x111317) },  // 炭黑底色
            uColorMist1:  { value: new THREE.Color(0x5e3c20) },  // 低饱和琥珀
            uColorMist2:  { value: new THREE.Color(0x8a572d) },  // 暖铜
            uColorMist3:  { value: new THREE.Color(0x40362d) },  // 暖灰
          },
          vertexShader: /* glsl */`
            varying vec2 vUv;
            void main() {
              vUv = uv;
              gl_Position = vec4(position.xy, 0.0, 1.0);
            }
          `,
          fragmentShader: /* glsl */`
            precision highp float;
            varying vec2 vUv;
            uniform float uTime;
            uniform vec2  uResolution;
            uniform vec3  uColorBg;
            uniform vec3  uColorMist1;
            uniform vec3  uColorMist2;
            uniform vec3  uColorMist3;

            // —— 经典 hash + value noise ——
            float hash(vec2 p) {
              p = fract(p * vec2(123.34, 456.21));
              p += dot(p, p + 45.32);
              return fract(p.x * p.y);
            }
            float noise(vec2 p) {
              vec2 i = floor(p);
              vec2 f = fract(p);
              float a = hash(i);
              float b = hash(i + vec2(1.0, 0.0));
              float c = hash(i + vec2(0.0, 1.0));
              float d = hash(i + vec2(1.0, 1.0));
              vec2 u = f * f * (3.0 - 2.0 * f);
              return mix(a, b, u.x) + (c - a) * u.y * (1.0 - u.x) + (d - b) * u.x * u.y;
            }
            // —— fBm 多 octave 累加噪声（5 层）—— 关键的"云烟感"来源
            float fbm(vec2 p) {
              float v = 0.0;
              float a = 0.5;
              mat2 rot = mat2(0.866, -0.5, 0.5, 0.866); // 30° 旋转防 axis-aligned 重复
              for (int i = 0; i < 5; i++) {
                v += a * noise(p);
                p = rot * p * 2.05 + 17.0;
                a *= 0.5;
              }
              return v;
            }
            // —— Domain warping：让噪声"流动" + 不规则形状 ——
            float warpedFbm(vec2 p, float t) {
              vec2 q = vec2(fbm(p + vec2(0.0, t * 0.15)), fbm(p + vec2(5.2, t * 0.13 + 1.3)));
              vec2 r = vec2(fbm(p + 4.0 * q + vec2(1.7 + t * 0.08, 9.2)),
                            fbm(p + 4.0 * q + vec2(8.3, 2.8 - t * 0.07)));
              return fbm(p + 4.0 * r);
            }

            void main() {
              // 归一化坐标，保持纵横比
              vec2 uv = (gl_FragCoord.xy - 0.5 * uResolution) / min(uResolution.x, uResolution.y);
              float t = uTime * 0.05;

              // 主云雾密度图（多次 warp 拼出复杂结构）
              float n = warpedFbm(uv * 1.5 + vec2(t, -t * 0.7), uTime * 0.3);

              // 3 个颜色按 n 与位置混合，再叠加副密度做"云缝"
              // 整体权重调低：避免亮色过曝，保持沉静感
              float n2 = fbm(uv * 3.0 - vec2(t * 0.4));
              vec3 col = uColorBg;
              col = mix(col, uColorMist1, smoothstep(0.40, 0.90, n) * 0.65);
              col = mix(col, uColorMist2, smoothstep(0.50, 0.95, n * n2) * 0.50);
              col = mix(col, uColorMist3, smoothstep(0.60, 1.00, n2 * (1.0 - n)) * 0.25);

              // 中央微高光（径向衰减）— 减弱
              float r = length(uv);
              col += 0.025 * (1.0 - smoothstep(0.0, 0.9, r));

              // 边缘暗角 — 加深
              col *= mix(1.0, 0.45, smoothstep(0.55, 1.15, r));

              gl_FragColor = vec4(col, 1.0);
            }
          `,
        })

        const quad = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), material)
        scene.add(quad)

        this.fog = { renderer, scene, camera, material, raf: null, start: performance.now() }
        const tick = () => {
          const t = (performance.now() - this.fog.start) / 1000
          this.fog.material.uniforms.uTime.value = t
          this.fog.renderer.render(this.fog.scene, this.fog.camera)
          this.fog.raf = requestAnimationFrame(tick)
        }
        tick()
      } catch (e) {
        // WebGL 不支持时静默回退，仅显示 CSS 渐变背景
        console.warn('[login] WebGL fog init failed, fallback to CSS gradient:', e)
      }
    },

    onResize() {
      this.resizeCanvas()
      if (this.fog.renderer && this.fog.material) {
        const w = window.innerWidth
        const h = window.innerHeight
        this.fog.renderer.setSize(w, h, false)
        this.fog.material.uniforms.uResolution.value.set(w, h)
      }
    },

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

    // === 业务逻辑保持不变 ===
    showPwd() {
      this.passwordType = this.passwordType === 'password' ? '' : 'password'
    },
    getCaptcha() {
      getCaptchaCode().then(res => {
        if (res.code === 0) {
          this.captchaImg = res.data.img
          this.loginForm.captcha_key = res.data.captcha_key
          this.loginForm.captcha = ''
        }
      })
    },
    getCookie() {
      const username = Cookies.get('username')
      Cookies.remove('password')
      const rememberMe = Cookies.get('rememberMe')
      this.loginForm = {
        username: username === undefined ? this.loginForm.username : username,
        password: '',
        rememberMe: rememberMe === 'true',
        captcha: '',
        captcha_key: ''
      }
    },
    handleLogin() {
      this.$refs.loginForm.validate(valid => {
        if (valid) {
          this.loading = true
          if (this.loginForm.rememberMe) {
            Cookies.set('username', this.loginForm.username, { expires: 30 })
            Cookies.set('rememberMe', this.loginForm.rememberMe, { expires: 30 })
          } else {
            Cookies.remove('username')
            Cookies.remove('rememberMe')
          }
          // 深度拷贝
          let loginForm = JSON.parse(JSON.stringify(this.loginForm))
          this.$store.dispatch('Login', loginForm).then(() => {
            if (typeof removeMallId === 'function') removeMallId()
            else localStorage.removeItem('Mall-Id')
            this.$router.push({ path: '/mall/entry' }).catch(() => {})
          }).catch(() => {
            this.loading = false
            this.getCaptcha()
          })
        }
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

/* ── 高保真云烟背景（WebGL fragment shader）──
   通过 fBm 噪声 + domain warping + 时间驱动，实现"变换无常"的真实烟雾流动。
   GPU 渲染，与 CSS 动画相比性能/逼真度都更好。
*/
#fog-bg {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  z-index: 0;
  display: block;
}

#canvas-bg {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  z-index: 1;                    /* 粒子叠在云烟之上 */
}

.bg-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  /* 边角暗角，烘托中心星云 */
  background: radial-gradient(transparent 55%, rgba(0,0,0,0.7) 100%);
  pointer-events: none;
  z-index: 2;
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

  /* Checkbox 颜色 */
  ::v-deep .el-checkbox__label { color: $text-gray; }
  ::v-deep .el-checkbox__input.is-checked + .el-checkbox__label { color: $primary-color; }
  ::v-deep .el-checkbox__input.is-checked .el-checkbox__inner {
    background-color: $primary-color;
    border-color: $primary-color;
  }
  ::v-deep .el-checkbox__input.is-checked .el-checkbox__inner::after {
    border-color: #191b20;
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
    img { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
    .loading-text { color: #fff; }
  }
}

.action-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 35px;
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
  /* 深色品牌页面使用暖白主按钮，橙色保留为品牌点缀。 */
  background: #f7f6f2;
  color: #191b20;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
  z-index: 1;

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
    background: #e7e5de;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    &::before { left: 100%; }
  }

  &:active { background: #d8d5cb; }
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

  a { color: rgba(255, 255, 255, 0.5); text-decoration: none; &:hover { color: $primary-color; } }
  .divider { margin: 0 10px; opacity: 0.3; }
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
