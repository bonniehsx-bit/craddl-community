<template>
  <div>
    <template v-if="allowIcons">
      <div class="optional-selector">
        <div class="optional-thumb" :style="{ width: width, height: height }">
          <el-image v-if="pic_url" :src="pic_url" fit="contain" :preview-src-list="[pic_url]" class="optional-preview" />
          <i v-else class="el-icon-picture-outline optional-empty" />
          <el-button v-if="pic_url" class="optional-clear" type="text" icon="el-icon-close" aria-label="清除图标或图片" @click="deleteImage" />
        </div>
        <div class="optional-actions">
          <file-picker :multiple="false" :max="1" @selected="chooseImage">
            <el-button type="text" size="mini">{{ pic_url ? '更换图片' : '选择图片' }}</el-button>
          </file-picker>
          <el-button type="text" size="mini" @click="openIcons">图标库</el-button>
        </div>
      </div>
      <tabler-icon-picker v-if="iconPickerReady" :visible.sync="iconPickerVisible" :value="pic_url"
        :default-color="iconColor" :default-scale="iconScale" @input="chooseIcon" />
    </template>
    <template v-else>
    <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center" v-if="pic_url" style="position: relative;">
      <el-image :src="pic_url" fit="cover" :preview-src-list="[pic_url]" style="width: 100%;height: 100%;"></el-image>
      <span class="flex-x-center flex-y-center pic-del" @click="deleteImage">
        <i class="el-icon-close"></i></span>
    </div>
    <file-picker :multiple='false' :max="1" @selected="chooseImage" v-if="pic_url == ''||!pic_url">
      <div flex="main:center cross:center" style="position: relative;" class="add-image-btn flex-x-center flex-y-center">
        <i class="el-icon-upload"></i>
        <span style="position: absolute;top: 0px;right: 1px;font-size: 5px;color: #909399;">{{ size }}</span>
      </div>
    </file-picker>
    </template>
  </div>
</template>

<script>
export default {
  components: { TablerIconPicker: () => import('@/components/TablerIconPicker') },
  model: {
    prop: 'url',
    event: 'input'
  },
  props: {
    allowIcons: { type: Boolean, default: false },
    width: { type: String, default: '44px' },
    height: { type: String, default: '44px' },
    iconColor: { type: String, default: '#303133' },
    iconScale: { type: Number, default: 75 },
    url: {
      type: String,
      default: ''
    },
    size: {
      type: String,
      default: '768*768'
    }
  },
  data() {
    return {
      pic_url: '',
      iconPickerReady: false,
      iconPickerVisible: false,
    }
  },
  mounted() {
    this.pic_url = this.url;
  },
  watch: {
    url(newVal, oldVal) {
      this.pic_url = newVal;
    },
    pic_url(newVal, oldVal) {
      if (newVal != oldVal) {
        this.pic_url = newVal;
      }

      this.$emit('input', newVal);
    },
  },

  methods: {
    openIcons() {
      this.iconPickerReady = true;
      this.$nextTick(() => { this.iconPickerVisible = true; });
    },
    chooseIcon(url) {
      this.pic_url = url;
    },
    deleteImage() {
      this.pic_url = '';

    },
    chooseImage(e) {
      this.pic_url = e[0].url;

    },
  }
}
</script>

<style scoped>
.optional-selector { display:inline-flex; align-items:center; gap:12px; }
.optional-thumb { position:relative; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid #e4e7ed; border-radius:6px; background:#fff; }
.optional-preview { width:100%; height:100%; }
.optional-empty { font-size:22px; color:#c0c4cc; }
.optional-clear { position:absolute; top:-8px; right:-8px; padding:3px; border-radius:50%; background:#fff; border:1px solid #e4e7ed; color:#909399; font-size:10px; line-height:1; }
.optional-actions { display:flex; gap:12px; align-items:center; }

/* 仅利用 absolute 定位将删除按钮移到图片内部右上角 */
.pic-del {
  position: absolute;
  top: 0;
  right: 0;
  width: 20px;
  height: 20px;
  background-color: rgba(0, 0, 0, 0.5); /* 半透明黑底，防止图片是白色时看不清叉号 */
  color: #fff; /* 叉号颜色变白 */
  cursor: pointer;
  z-index: 2;
  border-bottom-left-radius: 4px; /* 让内部小角落稍微圆润一点 */
}
</style>
