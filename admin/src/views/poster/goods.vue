<template>
  <div class="app-container">
    <el-card v-loading="loading">
      <div slot="header" class="card-header-flex">
        <span style="font-size: 16px; font-weight: bold;">商品海报设置</span>
        <div>
          <el-button type="primary" size="small" :loading="btnLoading" @click="save">保存设置</el-button>
        </div>
      </div>

      <div class="editor-container">
        <!-- ================== 左侧:实时预览 ================== -->
        <div class="preview-area">
          <div class="poster-frame">
            <goods-poster-preview
              :template="form.template"
              :theme-color="form.theme_color"
              :main-title="form.main_title"
              :sub-title="form.sub_title"
              :recommend="form.recommend"
              :mall="mall"
              :sample-goods="sampleGoods"
              :scale="0.5"
            />
          </div>
          <div class="preview-tip">
            {{ sampleGoods ? '预览用的是最近上架的一件商品' : '商城里还没有带主图的在售商品，用示例数据预览' }}；头像、昵称、小程序码为示意
          </div>
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
                    <goods-poster-preview
                      :template="key"
                      :theme-color="form.theme_color"
                      :main-title="form.main_title"
                      :sub-title="form.sub_title"
                      :recommend="form.recommend"
                      :mall="mall"
                      :sample-goods="sampleGoods"
                      :scale="0.1"
                    />
                  </div>
                  <div class="tpl-name">{{ name }}</div>
                </div>
              </div>
              <div class="form-tip">{{ templateTips[form.template] }}</div>
            </el-form-item>

            <el-form-item v-if="form.template !== 'blackgold'" label="主题色">
              <el-color-picker v-model="form.theme_color" :predefine="predefineColors" />
              <div class="form-tip">价格和强调色用这个颜色，默认跟商城主题色一致</div>
            </el-form-item>

            <el-form-item v-if="usesMainTitle" label="主标题">
              <el-input v-model="form.main_title" :maxlength="limits.main_title" show-word-limit placeholder="例如：好物推荐" />
              <div class="form-tip">{{ form.template === 'promo' ? '显示在顶部大字' : '显示在商品名上方的小标题' }}</div>
            </el-form-item>

            <el-form-item v-if="form.template === 'promo'" label="副标题">
              <el-input v-model="form.sub_title" :maxlength="limits.sub_title" show-word-limit placeholder="可不填，例如：精选好物，值得拥有" />
            </el-form-item>

            <el-form-item v-if="form.template !== 'blackgold'" label="推荐语">
              <el-input v-model="form.recommend" :maxlength="limits.recommend" show-word-limit placeholder="例如：为你推荐一件好物" />
              <div class="form-tip">显示在分享人昵称下面</div>
            </el-form-item>

            <div class="auto-tip">
              商品名称、售价、划线价、商品主图、销量都自动读商品数据；价格或主图改了，下次生成就是新的。
            </div>
          </el-form>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
import GoodsPosterPreview from './components/GoodsPosterPreview'

export default {
  name: 'PosterGoods',
  components: { GoodsPosterPreview },

  data() {
    return {
      loading: false,
      btnLoading: false,
      mall: { name: '', logo: '' },
      sampleGoods: null,
      limits: { main_title: 8, sub_title: 20, recommend: 12 },
      // 后端 GoodsPosterRenderer::TEMPLATES 下发,这里是接口没回来之前的兜底
      templates: {
        classic: '经典白卡',
        promo: '主题大促',
        magazine: '大图杂志',
        blackgold: '黑金臻选'
      },
      templateTips: {
        classic: '白色卡片，信息最完整（含销量），通用百搭',
        promo: '主题色撑满顶部，价格做成角标，促销氛围强',
        magazine: '商品图占大半张，适合主图好看的商品',
        blackgold: '黑底金框，适合高客单、礼品、酒水茶叶；配色固定为黑金'
      },
      predefineColors: ['#FF4B3E', '#FF7A1A', '#E6A23C', '#12B76A', '#3D7BFF', '#7A5AF8', '#1F2229'],
      form: {
        template: 'classic',
        theme_color: '#FF4B3E',
        main_title: '好物推荐',
        sub_title: '精选好物，值得拥有',
        recommend: '为你推荐一件好物'
      }
    }
  },

  computed: {
    usesMainTitle() {
      return this.form.template === 'promo' || this.form.template === 'magazine'
    }
  },

  created() {
    this.getSetting()
  },

  methods: {
    getSetting() {
      this.loading = true
      this.$request({
        url: '/mall/poster/goods',
        method: 'get'
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.form = Object.assign({}, this.form, res.data.setting || {})
          this.mall = Object.assign({ name: '', logo: '' }, res.data.mall || {})
          this.sampleGoods = res.data.sample_goods || null
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
      if (!(this.form.recommend || '').trim()) {
        this.$message.error('请填写推荐语')
        return
      }
      this.btnLoading = true
      this.$request({
        url: '/mall/poster/goods',
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
  gap: 14px;
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

.form-tip {
  font-size: 12px;
  color: #999;
  line-height: 1.6;
  margin-top: 6px;
}

.auto-tip {
  margin-top: 8px;
  padding: 10px 12px;
  border-radius: 4px;
  background: #f4f4f5;
  font-size: 12px;
  line-height: 1.6;
  color: #909399;
}
</style>
