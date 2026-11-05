<template>
  <div class="app-container">
    <el-card shadow="never" v-loading="loading">
      <div slot="header">
        <span style="font-size:15px;font-weight:600;">AI 配置</span>
        <span style="font-size:12px;color:#909399;margin-left:12px;">
          全商城共用一份，所有需要 AI 能力的插件都读这里
        </span>
      </div>

      <el-alert type="info" :closable="false" show-icon style="margin-bottom:20px;">
        <div slot="default" style="line-height:1.8;font-size:12px;">
          配好之后，商城内所有用到 AI 的功能（智能问答、内容生成、图片识别、语音转写等）都会走这份配置，
          插件侧无需重复填写密钥。<br>
          主供应商调用失败时会自动切换到降级供应商；两者都未配置时使用 Mock（返回模板内容），
          业务流程不会中断。
        </div>
      </el-alert>

      <el-form label-width="160px" size="small">

        <div class="section-title">主供应商</div>

        <el-form-item label="供应商">
          <el-select v-model="form.ai_provider" style="width:260px;">
            <el-option v-for="p in providers" :key="p.value" :label="p.label" :value="p.value" />
          </el-select>
        </el-form-item>

        <el-form-item label="API Key" v-if="form.ai_provider !== 'mock'">
          <el-input v-model="form.ai_api_key" placeholder="填写 API Key（保存后脱敏展示）" style="width:400px;" />
          <div class="field-hint">保存后此处仅展示脱敏占位，原始值已加密存储；不修改请勿清空。</div>
        </el-form-item>

        <el-form-item label="模型 ID / 名称" v-if="form.ai_provider !== 'mock'">
          <el-input v-model="form.ai_model" :placeholder="modelPlaceholder" style="width:400px;" />
          <div class="field-hint" v-if="form.ai_provider === 'deepseek'">
            常见：<code>deepseek-chat</code>（V3 通用）/ <code>deepseek-reasoner</code>（R1 推理）。留空用 <code>deepseek-chat</code>。
          </div>
          <div class="field-hint" v-if="form.ai_provider === 'doubao'">
            豆包模型 ID 形如 <code>doubao-1-5-pro-32k-250115</code> 或推理接入点 <code>ep-2025xxxx-xxxxx</code>。留空用 lite 模型。
          </div>
          <div class="field-hint" v-if="form.ai_provider === 'claude'">
            常见：<code>claude-3-5-haiku-20241022</code> / <code>claude-3-5-sonnet-20241022</code>。留空用 haiku。
          </div>
          <div class="field-hint" style="color:#E6A23C;">
            ⚠ 需要「图片识别」类功能时，必须选用支持视觉的模型。
            <code>deepseek-chat</code>、豆包 lite 等纯文本模型会导致图片能力不可用。
          </div>
        </el-form-item>

        <el-form-item label="自定义 Base URL" v-if="form.ai_provider !== 'mock'">
          <el-input v-model="form.ai_base_url" placeholder="留空使用官方地址；私有化部署 / 代理时填写" style="width:400px;" />
          <div class="field-hint">
            <span style="color:#E6A23C;">⚠ 只填到根路径，不要带 <code>/chat/completions</code> 等 endpoint！</span><br>
            豆包：<code>https://ark.cn-beijing.volces.com/api/v3</code>
            DeepSeek：<code>https://api.deepseek.com</code>
            Claude：<code>https://api.anthropic.com</code>
          </div>
        </el-form-item>

        <div class="section-title" style="margin-top:24px;">降级供应商（可选）</div>
        <div class="field-hint" style="margin:0 0 12px 160px;">
          主供应商连续失败时自动接管，保证功能不中断。不选则降级为 Mock。
        </div>

        <el-form-item label="降级供应商">
          <el-select v-model="form.ai_fallback_provider" clearable placeholder="不启用（降级为 Mock）" style="width:260px;">
            <el-option v-for="p in providers" :key="p.value" :label="p.label" :value="p.value" />
          </el-select>
        </el-form-item>

        <el-form-item
          label="降级 API Key"
          v-if="form.ai_fallback_provider && form.ai_fallback_provider !== 'mock'"
        >
          <el-input v-model="form.ai_fallback_api_key" placeholder="填写降级供应商的 API Key" style="width:400px;" />
        </el-form-item>

        <div class="section-title" style="margin-top:24px;">语音识别（ASR）</div>
        <div class="field-hint" style="margin:0 0 12px 160px;">
          用于录音转文字。与上面的大模型是<b>两套独立驱动 + 独立密钥</b>，互不影响。
        </div>

        <el-form-item label="驱动">
          <el-select v-model="form.asr_provider" style="width:260px;">
            <el-option v-for="p in asrProviders" :key="p.value" :label="p.label" :value="p.value" />
          </el-select>
        </el-form-item>

        <el-form-item label="SecretId" v-if="form.asr_provider !== 'mock'">
          <el-input v-model="form.asr_secret_id" placeholder="AKIDxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" style="width:400px;" />
          <div class="field-hint" v-if="form.asr_provider === 'tencent'">
            前往
            <el-link type="primary" href="https://console.cloud.tencent.com/asr" target="_blank">腾讯云 ASR 控制台</el-link>
            开通「录音文件识别」服务后，在
            <el-link type="primary" href="https://console.cloud.tencent.com/cam/capi" target="_blank">访问管理 → API 密钥</el-link>
            获取。
          </div>
        </el-form-item>

        <el-form-item label="SecretKey" v-if="form.asr_provider !== 'mock'">
          <el-input v-model="form.asr_secret_key" placeholder="填写 SecretKey（保存后脱敏展示）" style="width:400px;" />
        </el-form-item>

        <div class="section-title" style="margin-top:24px;">文生图（Text-to-Image）</div>
        <div class="field-hint" style="margin:0 0 12px 160px;">
          用于 AI 绘本等需要「按文字生成图片」的场景。同样是<b>独立驱动 + 独立接入点</b> ——
          火山的生图要单独开一个推理接入点，和上面文本用的不是同一个，别填串了。<br>
          <b>不配置等于关闭</b>：调用方会收到明确报错并走失败退款，不会拿占位图冒充成功。
        </div>

        <el-form-item label="生图驱动">
          <el-select v-model="form.image_provider" style="width:260px;">
            <el-option v-for="p in imageProviders" :key="p.value" :label="p.label" :value="p.value" />
          </el-select>
        </el-form-item>

        <el-form-item label="生图 API Key" v-if="form.image_provider !== 'mock'">
          <el-input v-model="form.image_api_key" placeholder="填写 API Key（保存后脱敏展示）" style="width:400px;" />
        </el-form-item>

        <el-form-item label="生图接入点 ID" v-if="form.image_provider !== 'mock'">
          <el-input v-model="form.image_model" placeholder="如：ep-20250101xxxxxx-xxxxx" style="width:400px;" />
          <div class="field-hint" v-if="form.image_provider === 'doubao'">
            火山方舟控制台里<b>生图模型</b>的推理接入点 ID，注意不要填成文本接入点。
          </div>
        </el-form-item>

        <el-form-item label="生图 Base URL" v-if="form.image_provider !== 'mock'">
          <el-input v-model="form.image_base_url" placeholder="留空使用 https://ark.cn-beijing.volces.com/api/v3" style="width:400px;" />
        </el-form-item>

        <div class="section-title" style="margin-top:24px;">图片来源白名单</div>
        <div class="field-hint" style="margin:0 0 12px 160px;">
          图片类 AI 能力只会从受信任的域名下载图片，防止服务器被当作跳板探测内网。<br>
          <b>本站域名默认允许，无需填写</b>；仅当图片托管在 CDN / 对象存储等外部域名时才需要补充。
        </div>

        <el-form-item label="允许的图片域名">
          <el-input
            v-model="form.image_host_whitelist"
            type="textarea"
            :rows="3"
            placeholder="每行一个域名，如：&#10;oss.example.com&#10;*.cdn.example.com"
            style="width:400px;"
          />
          <div class="field-hint">换行或逗号分隔，支持 <code>*.example.com</code> 通配子域。</div>
        </el-form-item>

      </el-form>

      <div style="padding-left:160px;margin-top:16px;">
        <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
        <el-button :loading="testing" @click="handleTest" style="margin-left:10px;">测试连通性</el-button>
      </div>

      <div v-if="testResult" style="margin:16px 0 0 160px;">
        <el-alert
          :type="testResult.ok ? 'success' : 'error'"
          :title="testResult.ok ? '✅ 连通正常' : '❌ 连通失败'"
          :closable="false"
          show-icon
        >
          <div slot="default" style="font-family:monospace;font-size:12px;margin-top:4px;white-space:pre-wrap;">{{ testResult.msg }}</div>
          <div v-if="testResult.ok" slot="default" class="field-hint" style="margin-top:6px;">
            注意：这里只测了文本对话。<b>图片识别和语音识别是否可用需另行验证</b>，
            可在对应插件的联调页实测。
          </div>
        </el-alert>
      </div>

    </el-card>
  </div>
</template>

<script>
const DEFAULT_FORM = () => ({
  ai_provider: 'mock',
  ai_api_key: '',
  ai_base_url: '',
  ai_model: '',
  ai_fallback_provider: '',
  ai_fallback_api_key: '',
  asr_provider: 'tencent',
  asr_secret_id: '',
  asr_secret_key: '',
  image_provider: 'mock',
  image_api_key: '',
  image_base_url: '',
  image_model: '',
  image_host_whitelist: '',
})

export default {
  name: 'MallAiConfig',
  data() {
    return {
      loading: false,
      saving: false,
      testing: false,
      providers: [],
      asrProviders: [],
      imageProviders: [],
      form: DEFAULT_FORM(),
      testResult: null,
    }
  },
  computed: {
    modelPlaceholder() {
      const map = {
        deepseek: '留空使用 deepseek-chat',
        doubao: '留空使用 doubao-1-5-lite-32k',
        claude: '留空使用 claude-3-5-haiku',
      }
      return map[this.form.ai_provider] || '留空使用默认模型'
    },
  },
  created() {
    this.loadConfig()
  },
  methods: {
    loadConfig() {
      this.loading = true
      this.$request({ url: '/mall/ai-config/index' }).then(res => {
        if (res.code === 0) {
          this.form = Object.assign(DEFAULT_FORM(), res.data.setting || {})
          this.providers = res.data.providers || []
          this.asrProviders = res.data.asrProviders || []
          this.imageProviders = res.data.imageProviders || []
        } else {
          this.$message.error(res.msg || '加载配置失败')
        }
      }).finally(() => { this.loading = false })
    },

    handleSave() {
      this.saving = true
      this.testResult = null
      this.$request({
        url: '/mall/ai-config/save',
        method: 'post',
        data: this.form,
      }).then(res => {
        if (res.code === 0) {
          this.$message.success('保存成功')
          this.loadConfig()
        } else {
          this.$message.error(res.msg || '保存失败')
        }
      }).finally(() => { this.saving = false })
    },

    handleTest() {
      this.testing = true
      this.testResult = null
      this.$request({
        url: '/mall/ai-config/test',
        method: 'post',
        data: this.form,
      }).then(res => {
        if (res.code === 0) {
          this.testResult = { ok: true, msg: (res.data && res.data.reply) || '✅ 连通正常' }
        } else {
          this.testResult = { ok: false, msg: res.msg || '未知错误' }
        }
      }).catch(() => {
        this.testResult = { ok: false, msg: '请求失败，请检查网络或后端配置' }
      }).finally(() => { this.testing = false })
    },
  },
}
</script>

<style scoped>
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
  padding-left: 8px;
  border-left: 3px solid var(--admin-accent-text);
}
.field-hint {
  font-size: 12px;
  color: #909399;
  line-height: 1.7;
  margin-top: 4px;
}
.field-hint code {
  background: #f5f7fa;
  padding: 1px 5px;
  border-radius: 3px;
  color: var(--admin-accent-text);
}
</style>
