<template>
  <div class="file-upload" @click="handleClick" :class="{ disabled: disabled || loading }">
    <slot :file="file" :loading="loading">
      <div v-if="!file && !loading" class="upload-placeholder">
        <i class="el-icon-upload"></i>
        <span>点击上传文件</span>
      </div>
      <div v-else-if="file" class="upload-preview">
        <div class="file-info">
          <el-image :src="file.url" style="width: 100px;height: 100px" :preview-src-list="[file.url]"></el-image>
        </div>
        <el-button
          v-if="!loading"
          size="mini"
          type="text"
          class="delete-btn"
          @click.stop="handleRemove">
          <i class="el-icon-delete"></i>
        </el-button>
      </div>
      <el-loading v-if="loading" class="upload-loading" :fullscreen="false" :lock="true"></el-loading>
    </slot>
    <input ref="input" type="file" :accept="accept" style="display: none" @change="handleChange">
  </div>
</template>

<script>
import axios from 'axios'
import {getToken} from '@/utils/auth'
import {getMallId} from '@/utils/mall'

export default {
  model: {
    prop: 'value',
    event: 'update:value'
  },
  props: {
    // v-model绑定的文件ID或URL
    value: [String, Number],
    disabled: Boolean,
    accept: String,
    params: {
      type: Object,
      default: () => ({})
    },
    uploadUrl: {
      type: String,
      default: '/common/file/upload'
    },
    // 预览文件的URL前缀
    previewUrlPrefix: {
      type: String,
      default: ''
    },
    // 是否自动显示上传成功的文件
    autoPreview: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      loading: false,
      file: null,
      uploadError: null
    }
  },
  watch: {
    value: {
      immediate: true,
      handler(newVal) {
        this.updateFilePreview(newVal)
      }
    }
  },
  methods: {
    // 更新文件预览信息
    updateFilePreview(fileId) {
      if (!fileId) {
        this.file = null
        return
      }

      // 如果是对象格式，直接使用
      if (typeof fileId === 'object') {
        this.file = {
          id: fileId.id || fileId.key,
          name: fileId.name || fileId.filename,
          url: fileId.url || (this.previewUrlPrefix + (fileId.id || fileId.key))
        }
        return
      }

      // 如果是字符串/数字格式，构建文件对象
      this.file = {
        id: fileId,
        name: `file_${fileId}`,
        url: this.previewUrlPrefix + fileId
      }
    },

    handleClick() {
      if (this.disabled || this.loading) return

      // 重置input以允许重复选择同一文件
      this.$refs.input.value = null
      this.$refs.input.click()
    },

    handleChange(e) {
      if (!e.target.files || e.target.files.length === 0) return

      const selectedFile = e.target.files[0]
      this.uploadFile(selectedFile)
    },

    async uploadFile(rawFile) {
      this.loading = true
      this.uploadError = null

      try {
        const formData = new FormData()

        // 添加额外参数
        Object.entries({...this.params}).forEach(([key, value]) => {
          formData.append(key, value)
        })

        // 添加文件
        formData.append('file', rawFile, rawFile.name)

        // 获取商城ID
        const mallId = getMallId() || 0

        // 上传请求
        const response = await axios.post(this.uploadUrl, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
            'x-mall-id': mallId,
            'x-admin-token': getToken()
          },
          onUploadProgress: progressEvent => {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            this.$emit('progress', percentCompleted)
          }
        })

        if (response.data.code === 0) {
          const fileId = response.data.data.id || response.data.data.key || response.data.data.url

          // 更新v-model绑定值
          this.$emit('update:value', response.data.data.url)

          // 更新文件预览
          this.file = response.data.data;


          // 触发成功事件
          this.$emit('success', {
            id: fileId,
            url: response.data.data.url,
            name: rawFile.name,
            size: rawFile.size,
            type: rawFile.type,
            response: response.data
          })

          this.$message.success('上传成功')
        } else {
          throw new Error(response.data.msg || '上传失败')
        }
      } catch (error) {
        this.uploadError = error.message
        this.$message.error(error.message || '上传失败')
        this.$emit('error', error)
      } finally {
        this.loading = false
      }
    },

    handleRemove() {
      if (this.disabled || this.loading) return

      this.$emit('update:value', null)
      this.file = null
      this.$emit('remove')
    }
  }
}
</script>

<style lang="scss" scoped>
.file-upload {
  display: inline-block;
  position: relative;
  width: 100%;
  min-height: 80px;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.3s;
  padding: 10px;

  &:hover:not(.disabled):not(.loading) {
    border-color: var(--admin-accent-text);

    .upload-placeholder {
      color: var(--admin-accent-text);
    }
  }

  &.disabled, &.loading {
    cursor: not-allowed;
    opacity: 0.7;
  }

  .upload-placeholder {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 80px;
    color: #909399;

    i {
      font-size: 28px;
      margin-bottom: 8px;
    }
  }

  .upload-preview {
    display: flex;
    align-items: center;
    padding: 10px 15px;

    .file-info {
      flex: 1;
      display: flex;
      align-items: center;
      overflow: hidden;

      i {
        margin-right: 8px;
        color: #606266;
      }

      span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }

    .delete-btn {
      color: #909399;

      &:hover {
        color: #f56c6c;
      }
    }
  }

  .upload-loading {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }
}
</style>
