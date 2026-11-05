<template>
  <div>
    <div v-if="file_url" class="file-display-box" flex="dir:left cross:center">
      <i class="el-icon-document file-icon"></i>
      <div class="file-name" :title="file_name">
         {{ file_name || '已选文件' }}
      </div>
      <el-link type="primary" :href="file_url" target="_blank" :underline="false" class="file-action">下载</el-link>
      <span class="file-del flex-x-center flex-y-center" @click="deleteFile" title="移除文件">
        <i class="el-icon-close"></i>
      </span>
    </div>
    <file-picker type="doc" :multiple='false' :max="1" @selected="chooseFile" v-if="!file_url">
      <el-button size="small" type="primary" icon="el-icon-upload2" plain>上传或选择文件</el-button>
      <div class="file-tip" v-if="size">{{ size }}</div>
    </file-picker>
  </div>
</template>

<script>
export default {
  name: 'SingleFileSelector',
  model: {
    prop: 'url',
    event: 'input'
  },
  props: {
    url: {
      type: String,
      default: ''
    },
    name: {
      type: String,
      default: ''
    },
    size: {
      type: String,
      default: '支持 PDF, Word, Excel 等通用文档格式'
    }
  },
  data() {
    return {
      file_url: '',
      file_name: ''
    }
  },
  mounted() {
    this.file_url = this.url;
    this.file_name = this.name || (this.url ? this.url.split('/').pop() : '');
  },
  watch: {
    url(newVal, oldVal) {
      this.file_url = newVal;
      if (newVal && !this.file_name) {
          this.file_name = newVal.split('/').pop();
      }
    },
    name(newVal, oldVal) {
      this.file_name = newVal;
    },
    file_url(newVal, oldVal) {
      if (newVal !== oldVal) {
        this.file_url = newVal;
      }
      this.$emit('input', newVal);
      this.$emit('update:name', this.file_name); // 允许父组件同步文件名
    },
  },

  methods: {
    deleteFile() {
      this.file_url = '';
      this.file_name = '';
    },
    chooseFile(e) {
      if (e && e[0]) {
        this.file_url = e[0].url;
        this.file_name = e[0].name;
      }
    },
  }
}
</script>

<style scoped>
.file-display-box {
  border: 1px solid #dcdfe6;
  background-color: #f7f9fa;
  padding: 8px 15px;
  border-radius: 4px;
  width: 320px;
  position: relative;
  display: flex;
  align-items: center;
}

.file-icon {
  font-size: 24px;
  color: var(--admin-accent-text);
  margin-right: 10px;
}

.file-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14px;
  color: #606266;
}

.file-action {
  margin: 0 15px;
  font-size: 13px;
}

.file-del {
  cursor: pointer;
  color: #909399;
  font-size: 16px;
  transition: color 0.2s;
}

.file-del:hover {
  color: #F56C6C;
}

.file-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 6px;
  line-height: 1.2;
}
</style>
