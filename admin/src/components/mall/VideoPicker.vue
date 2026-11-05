<template>
  <!-- 视频选择:和 link-picker 一个样子 —— 前面输入框(可直接粘贴链接),后面「选择视频」打开文件库 -->
  <div class="input-with-video-picker">
    <el-input
      :value="value"
      :placeholder="placeholder"
      :size="size"
      :disabled="disabled"
      clearable
      @input="v => $emit('input', v)"
    >
      <file-picker slot="append" type="video" :multiple="false" :max="1" @selected="choose">
        <el-button :size="size" :disabled="disabled">选择视频</el-button>
      </file-picker>
    </el-input>
  </div>
</template>

<script>
/**
 * 选一个视频地址。比 single-video-selector 省地方:不放大块预览,
 * 文件库里选(file-picker type=video,可当场上传)或直接粘贴外部链接都行,最后都是一个 url 字符串。
 *   <video-picker v-model="form.video_url" />
 */
export default {
  name: 'VideoPicker',
  props: {
    value: { type: String, default: '' },
    placeholder: { type: String, default: '选择视频或粘贴视频链接(mp4)' },
    size: { type: String, default: 'mini' },
    disabled: { type: Boolean, default: false }
  },
  methods: {
    choose(files) {
      if (files && files[0] && files[0].url) this.$emit('input', files[0].url)
    }
  }
}
</script>

<style lang="scss" scoped>
.input-with-video-picker {
  width: 100%;

  // file-picker 的触发层默认 inline-block,放进 append 里去掉行高差,按钮和输入框齐平
  ::v-deep .el-input-group__append {
    padding: 0;

    > div {
      display: block;
    }

    .el-button {
      margin: 0;
      border: none;
      border-radius: 0;
    }
  }
}
</style>
