<template>
  <el-dialog :visible.sync="visible" width="1040px" top="5vh" append-to-body custom-class="source-license-dialog" @opened="resetScroll">
    <div slot="title" class="license-window-title">
      <i class="el-icon-document-checked" aria-hidden="true" />
      <span>Craddl · 软件授权</span>
      <span class="license-window-caption">使用条款与权利说明</span>
    </div>
    <div v-loading="loading" class="license-body">
      <div v-if="error" class="license-error" role="alert">
        <i class="el-icon-warning-outline" aria-hidden="true" />
        <p>{{ error }}</p>
        <el-button size="small" @click="load">重新加载</el-button>
      </div>
      <div v-else class="license-reader">
        <nav class="license-navigation" aria-label="协议章节">
          <div class="license-navigation-label">协议目录</div>
          <button v-for="(section, index) in agreement.sections" :key="index" type="button" class="license-nav-item" :class="{ 'is-active': activeSection === index }" :aria-current="activeSection === index ? 'location' : null" @click="goToSection(index)">
            <span class="license-nav-number">{{ String(index + 1).padStart(2, '0') }}</span>
            <span>{{ section.title.replace(/^[一二三四五六七八九十]+、/, '') }}</span>
          </button>
          <div class="license-navigation-note">
            <i class="el-icon-lock" aria-hidden="true" />
            <span>尊重知识产权<br>依约使用源码</span>
          </div>
        </nav>
        <article ref="content" class="license-content" tabindex="0" aria-label="协议正文" @scroll.passive="onScroll">
          <header class="license-heading">
            <div class="license-eyebrow">Craddl 软件 · 社区版开源许可</div>
            <h1>{{ agreement.title }}</h1>
            <p v-if="agreement.version" class="license-version">{{ agreement.version }}</p>
          </header>
          <div v-if="agreement.intro.length" class="license-parties">
            <p v-for="(paragraph, index) in agreement.intro" :key="index">{{ paragraph }}</p>
          </div>
          <section v-for="(section, index) in agreement.sections" :key="index" ref="sections" class="license-section" :aria-labelledby="'license-section-' + index">
            <h2 :id="'license-section-' + index">
              <span class="license-section-number" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
              {{ section.title }}
            </h2>
            <template v-for="(block, blockIndex) in section.blocks">
              <ol v-if="block.items" :key="blockIndex" class="license-clauses" :start="block.start">
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
              </ol>
              <p v-else :key="blockIndex">{{ block.text }}</p>
            </template>
          </section>
          <div v-if="agreement.copyright" class="license-signature">{{ agreement.copyright }}</div>
        </article>
      </div>
    </div>
    <div slot="footer" class="license-footer">
      <span><i class="el-icon-info" aria-hidden="true" /> 查阅本协议不代表签署或同意</span>
      <el-button size="small" @click="visible = false">关闭</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'SourceLicense',
  data() {
    return { visible: false, loading: false, error: '', content: '', activeSection: 0 }
  },
  computed: {
    // 只解析展示结构，协议正文仍以 API 交付文件为唯一来源。
    agreement() {
      const result = { title: '', version: '', intro: [], sections: [], copyright: '' }
      const paragraphs = this.content.replace(/\r\n/g, '\n').split(/\n\s*\n/).map(text => text.trim()).filter(Boolean)
      paragraphs.forEach(text => {
        if (/^# /.test(text)) {
          result.title = text.replace(/^# /, '')
        } else if (/^## /.test(text)) {
          result.sections.push({ title: text.replace(/^## /, ''), blocks: [] })
        } else if (/^Copyright \(c\)/.test(text)) {
          result.copyright = text
        } else if (result.sections.length) {
          const lines = text.split('\n')
          const isList = lines.every(line => /^\d+\.\s/.test(line))
          result.sections[result.sections.length - 1].blocks.push(isList
            ? { start: Number(lines[0].match(/^\d+/)[0]), items: lines.map(line => line.replace(/^\d+\.\s/, '')) }
            : { text })
        } else if (/^协议版本：/.test(text)) {
          result.version = text
        } else {
          result.intro.push(text)
        }
      })
      return result
    }
  },
  methods: {
    show() {
      this.visible = true
      this.content = ''
      this.activeSection = 0
      this.load()
    },
    resetScroll() {
      if (this.$refs.content) this.$refs.content.scrollTop = 0
      this.activeSection = 0
    },
    goToSection(index) {
      const reader = this.$refs.content
      const section = (this.$refs.sections || [])[index]
      if (!reader || !section) return
      const heading = section.querySelector('h2') || section
      reader.scrollTop += heading.getBoundingClientRect().top - reader.getBoundingClientRect().top - 24
      this.onScroll()
    },
    onScroll() {
      const reader = this.$refs.content
      const sections = this.$refs.sections || []
      if (!reader || !sections.length) return
      const top = reader.getBoundingClientRect().top + 40
      let current = 0
      sections.forEach((section, index) => {
        const heading = section.querySelector('h2') || section
        if (heading.getBoundingClientRect().top <= top) current = index
      })
      if (reader.scrollTop + reader.clientHeight >= reader.scrollHeight - 2) current = sections.length - 1
      this.activeSection = current
    },
    async load() {
      if (this.loading) return
      this.loading = true
      this.error = ''
      try {
        const res = await this.$request({ url: 'admin/site/source-license', method: 'get', data: {}})
        if (res.code !== 0 || !res.data || !res.data.content) {
          this.error = res.msg || '协议暂时无法加载，请稍后重试'
          return
        }
        this.content = res.data.content
        this.$nextTick(this.resetScroll)
      } catch (_) {
        this.error = '协议加载失败，请检查网络后重试'
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style lang="scss">
.source-license-dialog {
  max-width: calc(100vw - 32px);
  overflow: hidden;
  border-radius: 10px;
  color: #303133;

  .el-dialog__header { padding: 21px 28px; border-bottom: 1px solid #e8ebef; }
  .el-dialog__headerbtn { top: 23px; right: 26px; }
  .el-dialog__body { padding: 0; }
  .el-dialog__footer { padding: 16px 28px; border-top: 1px solid #e8ebef; }
  .license-window-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; }
  .license-window-title > i { font-size: 20px; color: #606266; }
  .license-window-caption { margin-left: 6px; padding-left: 16px; border-left: 1px solid #dcdfe6; font-size: 12px; color: #909399; font-weight: 400; }
  .license-body { min-height: 240px; }
  .license-reader { display: flex; height: 69vh; max-height: 760px; }
  .license-navigation { flex: 0 0 218px; padding: 30px 16px; background: #f6f7f9; border-right: 1px solid #e8ebef; overflow-y: auto; }
  .license-navigation-label { padding: 0 12px 16px; color: #909399; font-size: 12px; letter-spacing: 2px; }
  .license-nav-item {
    display: flex; align-items: baseline; gap: 10px; width: 100%; margin: 3px 0; padding: 11px 12px;
    border: 0; border-radius: 5px; background: transparent; color: #606266; font: inherit;
    font-size: 12px; line-height: 1.6; text-align: left; cursor: pointer;
    &:hover { background: #eceff3; }
    &.is-active { background: #e7ebf1; color: #303133; font-weight: 600; }
    &:focus-visible { outline: 2px solid #909399; outline-offset: -2px; }
  }
  .license-nav-number { flex: 0 0 19px; color: #909399; font-size: 11px; font-variant-numeric: tabular-nums; }
  .license-navigation-note { display: flex; gap: 10px; margin: 30px 12px 0; padding-top: 20px; border-top: 1px solid #e1e5eb; color: #909399; font-size: 12px; line-height: 1.8; }
  .license-navigation-note i { padding-top: 4px; font-size: 15px; }
  .license-content { flex: 1; min-width: 0; overflow-y: auto; padding: 32px 44px 36px; color: #606266; font-size: 14px; line-height: 1.95; overflow-wrap: anywhere; }
  .license-heading { padding-bottom: 24px; border-bottom: 2px solid #303b4b; }
  .license-eyebrow { color: #909399; font-size: 11px; letter-spacing: 2px; }
  .license-heading h1 { margin: 10px 0 12px; color: #273343; font-size: 28px; line-height: 1.4; font-weight: 600; letter-spacing: 1px; }
  .license-heading .license-version { margin: 0; color: #909399; font-size: 12px; }
  .license-parties { padding: 18px 0; border-bottom: 1px solid #e8ebef; font-size: 13px; }
  .license-parties p { margin: 0 0 8px; }
  .license-parties p:last-child { margin: 0; color: #909399; font-size: 12px; }
  .license-section { padding-top: 28px; }
  .license-section h2 { display: flex; align-items: baseline; gap: 12px; margin: 0 0 15px; color: #303b4b; font-size: 16px; line-height: 1.65; font-weight: 600; }
  .license-section-number { color: #a4adb9; font-size: 12px; font-weight: 400; font-variant-numeric: tabular-nums; }
  .license-section p { margin: 0 0 14px; white-space: pre-line; text-align: justify; }
  .license-clauses { margin: 0 0 18px; padding-left: 22px; }
  .license-clauses li { padding-left: 6px; margin-bottom: 12px; text-align: justify; }
  .license-clauses li::marker { color: #606266; font-weight: 600; }
  .license-signature { margin-top: 26px; padding-top: 20px; border-top: 1px solid #e8ebef; color: #909399; font-size: 12px; }
  .license-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
  .license-footer > span { color: #909399; font-size: 12px; text-align: left; }
  .license-footer > span i { margin-right: 5px; }
  .license-error { padding: 60px 24px; text-align: center; color: #909399; }
  .license-error > i { font-size: 28px; }

  @media (max-width: 720px) {
    .el-dialog__header { padding: 18px 20px; }
    .el-dialog__footer { padding: 14px 20px; }
    .license-window-caption, .license-navigation { display: none; }
    .license-content { padding: 24px; }
    .license-heading h1 { font-size: 23px; }
    .license-reader { height: 68vh; }
    .license-footer > span { max-width: 200px; line-height: 1.6; }
  }
}
</style>
