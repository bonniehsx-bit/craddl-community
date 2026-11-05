<template>
  <div class="file-upload" @click="handleClick" :class="{ disabled: disabled || loading }">
    <slot :file="file" :loading="loading">
      <div v-if="!file && !loading" class="upload-placeholder">
        <i class="el-icon-upload"></i>
        <span>点击上传 PDF/Word 等电子纸件</span>
      </div>
      <div v-else-if="file" class="upload-preview" @click.stop>
        <div class="file-info">
          <i class="el-icon-document" style="font-size: 24px; color: var(--admin-accent-text); margin-right: 15px;"></i>
          <a :href="file.url" target="_blank" style="color: var(--admin-accent-text); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;">
             {{ file.name || '查看已归档合同附件' }}
          </a>
        </div>
        <el-button
          v-if="!loading && !disabled"
          size="mini"
          type="text"
          class="delete-btn"
          @click.stop="handleRemove">
          <i class="el-icon-delete"></i> 移除重传
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
    // v-model绑定的文件URL
    value: [String, Number],
    disabled: { type: Boolean, default: false },
    accept: { type: String, default: '.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar' },
    params: {
      type: Object,
      default: () => ({})
    },
    uploadUrl: {
      type: String,
      default: '/common/file/upload'
    }
  },
  data() {
    return {
      loading: false,
      file: null
    }
  },
  watch: {
    value: {
      immediate: true,
      handler(newVal) {
        if (!newVal) {
          this.file = null;
        } else {
          this.file = { url: newVal, name: this.extractFilename(newVal) };
        }
      }
    }
  },
  methods: {
    extractFilename(url) {
        if(!url) return '已上传文件附件';
        const parts = url.split('/');
        return parts[parts.length - 1];
    },
    handleClick() {
      if (this.disabled || this.loading) return
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
      try {
        const formData = new FormData()
        Object.entries({...this.params}).forEach(([key, val]) => {
          formData.append(key, val)
        })
        formData.append('file', rawFile, rawFile.name)
        const mallId = getMallId() || 0
        const response = await axios.post(this.uploadUrl, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
            'x-mall-id': mallId,
            'x-admin-token': getToken()
          }
        })
        if (response.data.code === 0) {
          const fileUrl = response.data.data.url;
          this.$emit('update:value', fileUrl);
          this.file = { url: fileUrl, name: rawFile.name };
          this.$message.success('附件归档成功');
        } else {
          throw new Error(response.data.msg || '归档失败')
        }
      } catch (error) {
        this.$message.error(error.message || '网络或接口故障，上传失败')
      } finally {
        this.loading = false
      }
    },
    handleRemove() {
      if (this.disabled || this.loading) return
      this.$emit('update:value', '')
      this.file = null
      this.$emit('remove')
    }
  }
}
</script>

<style lang="scss" scoped>
.file-upload {
  display: flex;
  align-items: center;
  position: relative;
  width: 100%;
  min-height: 50px;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.3s;
  padding: 10px;

  &:hover:not(.disabled):not(.loading) {
    border-color: var(--admin-accent-text);
    .upload-placeholder { color: var(--admin-accent-text); }
  }
  &.disabled, &.loading {
    cursor: not-allowed;
    opacity: 0.7;
    background: #f5f7fa;
  }
  .upload-placeholder {
    display: flex;
    flex-direction: row;
    align-items: center;
    color: #909399;
    i { font-size: 20px; margin-right: 8px; }
  }
  .upload-preview {
    display: flex;
    align-items: center;
    width: 100%;
    justify-content: space-between;
    .file-info {
      display: flex;
      align-items: center;
      flex: 1;
    }
    .delete-btn {
      color: #909399;
      margin-left: 10px;
      &:hover { color: #f56c6c; }
    }
  }
}
</style>
