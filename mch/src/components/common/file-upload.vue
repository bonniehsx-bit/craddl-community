<template>
  <div class="file-upload" flex="main:center cross:center" @click="handleClick">
    <slot />
    <input
      ref="input"
      type="file"
      :accept="accept"
      :multiple="multiple"
      style="display: none"
      @change="handleChange"
    >
  </div>
</template>

<script>
import axios from 'axios'
import { getToken } from '@/utils/auth'
import { getMallId } from '@/utils/mall'

export default {
  name: 'file-upload',
  props: {
    disabled: Boolean,
    multiple: Boolean,
    max: Number,
    accept: String,
    params: Object,
    fields: Object,
    is_site: {
      type: Number,
      default: 0
    },
    // [核心优化] 接收外部传入的上传接口地址
    // 默认为系统通用上传，FilePicker 传 isMch=true 时会覆盖此值
    action: {
      type: String,
      default: '/common/file/upload'
    }
  },
  data() {
    return {
      files: []
    }
  },
  methods: {
    handleClick() {
      if (this.disabled) return
      this.$refs.input.value = null
      this.$refs.input.click()
    },

    handleChange(e) {
      if (!e.target.files) return
      this.uploadFiles(e.target.files)
    },

    uploadFiles(rawFiles) {
      if (this.max && rawFiles.length > this.max) {
        this.$message.error('最多一次只能上传' + this.max + '个文件。')
        return
      }
      this.files = []
      // 初始化文件状态
      for (let i = 0; i < rawFiles.length; i++) {
        const file = {
          complete: false,
          response: null,
          rawFile: rawFiles[i]
        }
        this.files.push(file)
      }

      this.$emit('start', this.files)

      // 并发上传
      for (const i in this.files) {
        this.upload(this.files[i])
      }
    },

    upload(file) {
      const formData = new FormData()

      // 组装参数
      if (this.params && this.params.group_id) {
        formData.append('group_id', this.params.group_id)
      }
      formData.append('is_site', this.is_site)
      formData.append('file', file.rawFile, file.rawFile.name)

      // 组装 Header
      let mallId = getMallId()
      mallId = mallId ? mallId : 0

      const headers = {
        'Content-Type': 'multipart/form-data',
        'x-mall-id': mallId,
        'x-admin-token': getToken() // 保持与您原代码一致的 token key
      }

      let uploadUrl = this.action

      axios.post(uploadUrl, formData, { headers })
        .then(res => {
          if (res.data.code === 0) {
            file.response = res.data
            file.complete = true
            this.onSuccess(file)
          } else {
            this.$message.error(res.data.msg || '上传失败')
            this.onFail(file)
          }
        })
        .catch(e => {
          console.error('上传异常', e)
          this.$message.error('网络请求失败')
          this.onFail(file)
        })
    },

    onSuccess(file) {
      this.$emit('success', file)
      this.checkAllComplete()
    },

    onFail(file) {
      file.complete = true
      // 触发外部的 fail 事件，用于关闭 loading 状态
      this.$emit('fail', file)
      this.checkAllComplete()
    },

    checkAllComplete() {
      let allComplete = true
      for (let i in this.files) {
        if (!this.files[i].complete) {
          allComplete = false
          break
        }
      }
      if (allComplete) {
        this.$emit('complete', this.files)
      }
    }
  }
}
</script>

<style lang="scss" scoped>
/* 样式保持简洁，布局由父组件控制 */
.file-upload {
  width: 100%;
  height: 100%;
  cursor: pointer;
}
</style>
