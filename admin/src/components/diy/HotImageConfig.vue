<template>
  <div class="hot-image-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 样式设置 -->
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

        <el-form-item label="背景图片">
          <single-image-selector v-model="form.pic_url"></single-image-selector>
          <div class="tip">建议宽度 750px，上传后点击下方按钮编辑热区</div>
        </el-form-item>

        <el-button type="primary" plain size="small" icon="el-icon-edit" :disabled="!form.pic_url"
          @click="openEditor" style="width:100%; margin-bottom: 12px;">
          编辑热区
        </el-button>
      </div>

      <!-- 热区链接设置 -->
      <div class="config-section" v-if="form.list && form.list.length > 0">
        <div class="section-title">热区链接配置 ({{ form.list.length }} 个热区)</div>

        <div v-for="(item, index) in form.list" :key="index" class="link-item">
          <div class="area-name">
            <i class="el-icon-place"></i> 热区 {{ index + 1 }}: {{ item.name }}
          </div>
          <div class="area-link">
            <link-picker v-model="item.url"></link-picker>
          </div>
        </div>
      </div>

      <div v-else class="empty-tip">
        <template v-if="form.pic_url">上传了背景图，点击「编辑热区」开始画区域</template>
        <template v-else>请先上传背景图片</template>
      </div>

    </el-form>

    <!-- 热区编辑弹窗（内嵌 OperationFloor） -->
    <el-dialog
      title="编辑热区"
      :visible.sync="editorVisible"
      width="800px"
      top="5vh"
      append-to-body
      :close-on-click-modal="false"
      @opened="onEditorOpened"
    >
      <div class="editor-tip">在图片上鼠标框选创建热区，拖拽移动，右下角缩放，双击编辑名称</div>
      <div class="editor-canvas-wrap">
        <OperationFloor v-if="editorVisible" ref="floor" v-model="editorData" />
      </div>
      <span slot="footer">
        <el-button size="small" @click="editorVisible = false">取消</el-button>
        <el-button size="small" type="primary" @click="confirmEditor">确认</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import OperationFloor from '@/components/diy/HotImage/OperationFloor'

export default {
  name: 'HotImageConfig',
  components: { OperationFloor },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        hot_image_id: '',
        name: '',
        pic_url: '',
        list: []
      },
      // 热区编辑器
      editorVisible: false,
      editorData: { pic_url: '', data: [] }
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
    // === 内嵌编辑器 ===
    openEditor() {
      // 将当前 form 的数据映射到 OperationFloor 的 v-model 格式
      this.editorData = {
        pic_url: this.form.pic_url,
        data: (this.form.list || []).map((item, i) => ({
          id: item.id || `area_${Date.now()}_${i}`,
          name: item.name || `热区 ${i + 1}`,
          starX: item.starX || 0,
          starY: item.starY || 0,
          areaWidth: item.areaWidth || 200,
          areaHeight: item.areaHeight || 200,
        }))
      };
      this.editorVisible = true;
    },
    onEditorOpened() {
      // 弹窗打开后让 OperationFloor 重新计算容器尺寸
      this.$nextTick(() => {
        if (this.$refs.floor) {
          this.$refs.floor.updateContainerRect();
        }
      });
    },
    confirmEditor() {
      // 将编辑结果同步回 form，保留已有的 url 配置
      const oldMap = {};
      (this.form.list || []).forEach(item => {
        if (item.id) oldMap[item.id] = item.url || '';
      });

      this.form.list = (this.editorData.data || []).map(area => ({
        id: area.id,
        name: area.name,
        starX: area.starX,
        starY: area.starY,
        areaWidth: area.areaWidth,
        areaHeight: area.areaHeight,
        url: oldMap[area.id] !== undefined ? oldMap[area.id] : ''
      }));

      this.editorVisible = false;
    }
  }
}
</script>

<style lang="scss" scoped>
.hot-image-config {
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

.tip {
  font-size: 12px;
  color: #999;
  margin-top: 5px;
}

.link-item {
  background: #f8f9fa;
  border: 1px solid #eee;
  padding: 10px;
  margin-bottom: 10px;
  border-radius: 4px;

  .area-name {
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #606266;
    i { margin-right: 5px; color: var(--admin-accent-text); }
  }
}

.empty-tip {
  text-align: center;
  color: #909399;
  font-size: 12px;
  padding: 20px 0;
  background: #f4f6f8;
  border-radius: 4px;
}

.editor-tip {
  font-size: 12px;
  color: #909399;
  margin-bottom: 12px;
  line-height: 1.5;
}

.editor-canvas-wrap {
  width: 375px;
  margin: 0 auto;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  overflow: hidden;
  min-height: 200px;
}
</style>
