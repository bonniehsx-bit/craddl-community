<template>
  <div class="app-container">
    <el-card v-loading="loading">
      <div slot="header" class="card-header-flex">
        <span style="font-size: 16px; font-weight: bold;">推广海报设置</span>
        <div>
          <el-button type="primary" size="small" :loading="btnLoading" @click="save">保存设置</el-button>
        </div>
      </div>

      <div class="editor-container">
        <!-- ================== 左侧:实时预览 ================== -->
        <div class="preview-area">
          <div class="poster-frame">
            <invite-poster-preview
              :template="form.template"
              :theme-color="form.theme_color"
              :main-title="form.main_title"
              :sub-title="form.sub_title"
              :bg-image="usesBg ? form.bg_image : ''"
              :mall="mall"
              :scale="0.5"
            />
          </div>
          <div class="preview-tip">头像、昵称、小程序码为示意，生成时换成邀请人自己的</div>
        </div>

        <!-- ================== 右侧:配置 ================== -->
        <div class="config-area">
          <el-form label-position="top" size="small">
            <el-form-item label="海报模板">
              <div class="tpl-list">
                <div
                  v-for="(name, key) in templates"
                  :key="key"
                  class="tpl-item"
                  :class="{ active: form.template === key }"
                  @click="form.template = key"
                >
                  <div class="tpl-thumb">
                    <invite-poster-preview
                      :template="key"
                      :theme-color="form.theme_color"
                      :main-title="form.main_title"
                      :sub-title="form.sub_title"
                      :mall="mall"
                      :scale="0.09"
                    />
                  </div>
                  <div class="tpl-name">{{ name }}</div>
                </div>
              </div>
              <div class="form-tip">{{ templateTips[form.template] }}</div>
            </el-form-item>

            <el-form-item v-if="usesTheme" label="主题色">
              <el-color-picker v-model="form.theme_color" :predefine="predefineColors" />
              <div class="form-tip">{{ form.template === 'magazine' ? '用在「专属邀请」小标题；没配背景图时也用来铺底色' : '背景、按钮底框、高亮文字都从这个颜色推出来' }}</div>
            </el-form-item>

            <el-form-item label="主标题">
              <el-input v-model="form.main_title" :maxlength="limits.main_title" show-word-limit placeholder="例如：诚邀加入" />
              <div class="presets">
                <span class="presets-label">常用：</span>
                <el-tag
                  v-for="text in mainTitlePresets"
                  :key="text"
                  size="small"
                  :effect="form.main_title === text ? 'dark' : 'plain'"
                  @click="form.main_title = text"
                >{{ text }}</el-tag>
              </div>
              <div class="form-tip">「邀请有礼」这类说法会让人以为有奖励，商城没有邀请奖励时建议别用</div>
            </el-form-item>

            <el-form-item label="副标题">
              <el-input v-model="form.sub_title" :maxlength="limits.sub_title" show-word-limit placeholder="可不填，例如：诚挚邀请您加入" />
              <div class="presets">
                <span class="presets-label">常用：</span>
                <el-tag
                  v-for="text in subTitlePresets"
                  :key="text"
                  size="small"
                  :effect="form.sub_title === text ? 'dark' : 'plain'"
                  @click="form.sub_title = text"
                >{{ text }}</el-tag>
              </div>
            </el-form-item>

            <el-form-item v-if="usesBg" label="背景图">
              <single-image-selector v-model="form.bg_image" />
              <div class="form-tip">{{ bgTips[form.template] }}</div>
            </el-form-item>
          </el-form>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
import InvitePosterPreview from './components/InvitePosterPreview'

export default {
  name: 'PosterInviter',
  components: { InvitePosterPreview },

  data() {
    return {
      loading: false,
      btnLoading: false,
      mall: { name: '', logo: '' },
      limits: { main_title: 10, sub_title: 24 },
      // 后端 InvitePosterRenderer::TEMPLATES 下发,这里是接口没回来之前的兜底
      templates: {
        ticket: '邀请票券',
        simple: '简约白',
        redpacket: '喜庆红包',
        magazine: '杂志大图',
        blackgold: '黑金尊享'
      },
      templateTips: {
        ticket: '主题色渐变配白色票券卡片，通用百搭',
        simple: '浅色留白，干净有品牌感，适合日常分享',
        redpacket: '红包造型，福利感强，适合节日和拉新活动；配色固定为红金',
        magazine: '整张铺满背景图，适合有好看商品图、门店图的商家',
        blackgold: '黑底金边，适合会员、代理、高客单品类；配色固定为黑金'
      },
      bgTips: {
        ticket: '可不配。配了会铺满海报上半部分并自动压暗，建议宽 750、高 760 以上',
        simple: '可不配，不配时显示礼盒图案。配了会铺满顶部卡片，建议宽 670、高 820 以上',
        magazine: '建议配。图会铺满整张海报，下半部分自动压暗放文字，建议 750×1334 竖图，主体放在画面上半部分'
      },
      // 预设文案:点一下填入输入框,填完还能再改
      mainTitlePresets: ['诚邀加入', '好友邀请', '专属邀请函', '一起来逛逛', '邀你同行', '邀请有礼'],
      subTitlePresets: ['诚挚邀请您加入,好物好价一起享', '发现好物,和我一起逛逛', '好东西要和好朋友分享', '扫码进店,精选好物等你来'],
      predefineColors: ['#FF4B3E', '#FF7A1A', '#E6A23C', '#12B76A', '#3D7BFF', '#7A5AF8', '#1F2229'],
      form: {
        template: 'ticket',
        theme_color: '#FF4B3E',
        main_title: '诚邀加入',
        sub_title: '诚挚邀请您加入,好物好价一起享',
        bg_image: ''
      }
    }
  },

  computed: {
    // 红包、黑金是固定配色,不用主题色和背景图
    usesTheme() {
      return ['ticket', 'simple', 'magazine'].indexOf(this.form.template) !== -1
    },
    usesBg() {
      return ['ticket', 'simple', 'magazine'].indexOf(this.form.template) !== -1
    }
  },

  created() {
    this.getSetting()
  },

  methods: {
    getSetting() {
      this.loading = true
      this.$request({
        url: '/mall/poster/inviter',
        method: 'get'
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.form = Object.assign({}, this.form, res.data.setting || {})
          this.mall = Object.assign({ name: '', logo: '' }, res.data.mall || {})
          if (res.data.limits) this.limits = res.data.limits
          if (res.data.templates) this.templates = res.data.templates
        }
      }).catch(() => {
        this.loading = false
      })
    },

    save() {
      if (!/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.test(this.form.theme_color || '')) {
        this.$message.error('请选择主题色')
        return
      }
      if (!(this.form.main_title || '').trim()) {
        this.$message.error('请填写主标题')
        return
      }
      this.btnLoading = true
      this.$request({
        url: '/mall/poster/inviter',
        method: 'post',
        data: { data: JSON.stringify(this.form) }
      }).then(res => {
        this.btnLoading = false
        if (res.code === 0) {
          this.$message.success('保存成功')
        } else {
          this.$message.error(res.msg || '保存失败')
        }
      }).catch(err => {
        this.btnLoading = false
        this.$message.error((err && err.msg) || '网络错误')
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.editor-container {
  display: flex;
  min-height: 760px;
  background-color: #f7f8fa;
  border: 1px solid #e0e0e0;
}

.preview-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px;
  border-right: 1px solid #e0e0e0;
  background-color: #f0f2f5;
}

.preview-tip {
  margin-top: 14px;
  color: #999;
  font-size: 12px;
}

.poster-frame {
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.config-area {
  width: 460px;
  padding: 24px;
  background: #fff;
  overflow-y: auto;
}

.tpl-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.tpl-item {
  cursor: pointer;
  text-align: center;

  .tpl-thumb {
    border: 2px solid transparent;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
    transition: border-color 0.2s, transform 0.2s;
  }

  .tpl-name {
    margin-top: 4px;
    font-size: 12px;
    line-height: 18px;
    color: #606266;
  }

  &:hover .tpl-thumb {
    transform: translateY(-2px);
  }

  &.active {
    .tpl-thumb { border-color: var(--admin-accent-text); }
    .tpl-name { color: var(--admin-accent-text); font-weight: 600; }
  }
}

.presets {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 8px;

  .presets-label {
    font-size: 12px;
    color: #909399;
  }

  .el-tag {
    cursor: pointer;
  }
}

.form-tip {
  font-size: 12px;
  color: #999;
  line-height: 1.6;
  margin-top: 6px;
}
</style>
