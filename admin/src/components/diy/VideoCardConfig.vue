<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">视频</div>
        <el-form-item label="视频">
          <!-- 文件库选 / 当场上传,或直接粘贴外部链接 -->
          <video-picker v-model="form.video_url" size="small" />
        </el-form-item>
        <el-form-item label="封面">
          <single-image-selector v-model="form.cover_pic" size="750*420" />
          <div class="tip">先显示封面和播放按钮,点了才加载视频,不拖慢首页。</div>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" maxlength="16" show-word-limit />
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" maxlength="24" show-word-limit />
        </el-form-item>
        <el-form-item label="高度">
          <el-slider v-model="form.height" :min="280" :max="560" :step="10" style="padding: 0 8px" />
        </el-form-item>
      </div>
      <div class="config-section">
        <div class="section-title">配色与版式</div>
        <color-fields :value="form" :fields="colorFields" />
        <el-form-item label="左右边距" style="margin-top: 12px">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>
    </el-form>
  </div>
</template>

<script>
import configMixin from './style-kit/configMixin'
import ColorFields from './style-kit/ColorFields'

export default {
  name: 'VideoCardConfig',
  components: { ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'text_color', label: '文字' }, { key: 'accent_color', label: '播放键' }, { key: 'bg_color', label: '暗角' }]
    }
  },
  methods: {
    defaults() {
      return {
        video_url: '', cover_pic: '', title: '品牌故事', subtitle: '看看我们是怎么挑选每一件好物的', height: 380,
        text_color: '#ffffff', accent_color: '#c8102e', bg_color: 'rgba(14,26,43,0.85)',
        is_padding: '1', is_circle: '1'
      }
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
