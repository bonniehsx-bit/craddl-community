<template>
  <div class="video-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="圆角设置">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.background"></el-color-picker>
            <span class="color-text">{{ form.background }}</span>
          </div>
        </el-form-item>

        <el-form-item label="视频高度">
          <el-slider v-model="form.height" :min="100" :max="800" show-input input-size="mini"></el-slider>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容设置</div>

        <el-form-item label="视频标题">
          <el-input v-model="form.title" placeholder="请输入标题"></el-input>
        </el-form-item>

        <el-form-item label="标题颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.title_color"></el-color-picker>
            <span class="color-text">{{ form.title_color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="封面图片">
          <single-image-selector v-model="form.cover_pic"></single-image-selector>
        </el-form-item>

        <el-form-item label="视频文件">
          <!-- 视频预览区域 (如果有视频链接) -->
          <div class="video-preview-box" v-if="form.video_url">
            <video :src="form.video_url" controls style="width: 100%; height: 150px; background: #000;"></video>
            <i class="el-icon-error delete-video" @click="form.video_url = ''"></i>
          </div>

          <!-- 上传组件 -->
          <file-picker
            v-if="!form.video_url"
            type="video"
            :multiple="false"
            :max="1"
            @selected="handleVideoSelected"
          >
            <!-- 保持和你原有代码一致的触发按钮样式 -->
            <div class="upload-btn">
              <i class="el-icon-upload"></i>
              <span>点击上传视频</span>
            </div>
          </file-picker>
          <div class="tip">建议上传 MP4 格式视频</div>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
// 确保 file-picker 和 single-image-selector 已注册
// import FilePicker from '@/components/common/FilePicker'

export default {
  name: 'VideoBlockConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        background: '#ffffff',
        title_color: '#000000',
        title: '视频标题',
        video_url: '',
        cover_pic: '',
        height: 200
      }
    }
  },
  computed: {
    form: {
      get() {
        return this.value || JSON.parse(JSON.stringify(this.defaultConfig));
      },
      set(val) {
        this.$emit('input', val);
      }
    }
  },
  watch: {
    value: {
      handler(val) {
        if (!val) {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)));
        }
      },
      immediate: true
    }
  },
  methods: {
    // 处理 file-picker 选择视频后的回调
    handleVideoSelected(files) {
      if (files && files.length > 0) {
        // file-picker 通常返回数组对象 [{url: '...'}, ...]
        this.form.video_url = files[0].url;
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.video-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
    }
  }
}

.flex-row {
  display: flex;
  align-items: center;
}

.color-text {
  margin-left: 10px; font-size: 12px; color: #999;
}

.tip { font-size: 12px; color: #999; margin-top: 5px; }

.video-preview-box {
  position: relative;
  margin-bottom: 10px;

  .delete-video {
    position: absolute;
    top: -8px; right: -8px;
    color: #F56C6C;
    background: #fff;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    z-index: 2;
  }
}

.upload-btn {
  width: 100px; height: 100px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  display: flex; flex-direction: column; justify-content: center; align-items: center;
  color: #8c939d;
  background: var(--admin-canvas);

  &:hover { border-color: var(--admin-accent-text); color: var(--admin-accent-text); }
  i { font-size: 28px; margin-bottom: 5px; }
  span { font-size: 12px; }
}
</style>
