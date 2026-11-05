<template>
  <div class="flex-row" style="flex-wrap: wrap;">
    <template v-if="pic_list.length">
      <div class="flex-row" style="flex-wrap: wrap;">
        <div
          v-for="(item, index) in pic_list"
          :key="index"
          flex="main:center cross:center"
          class="add-image-btn flex-x-center flex-y-center"
          style="position: relative;"
        >
          <el-image
            :src="item"
            fit="cover"
            :preview-src-list="pic_list"
            style="width: 100%; height: 100%"
          ></el-image>
          <div class="flex-x-center flex-y-center pic-del" @click.stop="deletePic(index)">
            <i class="el-icon-close"></i>
          </div>
        </div>
      </div>
    </template>

    <file-picker
      v-if="pic_list.length < count"
      :max="count"
      :multiple="true"
      @selected="chooseImage"
    >
      <div flex="main:center cross:center" style="position: relative;"
           class="add-image-btn flex-x-center flex-y-center">
        <i class="el-icon-upload"></i>
        <span
          style="position: absolute; top: 0px; right: 1px; font-size: 5px; color: #909399;"
        >{{ size }}</span>
      </div>
    </file-picker>
  </div>
</template>

<script>
export default {
  name: "ImageUploader",

  model: {
    prop: 'list',
    event: 'input'
  },

  props: {
    count: {
      type: Number,
      default: 6
    },
    size: {
      type: String,
      default: '768*768'
    },
    list: {
      type: Array,
      default: () => []
    }
  },

  data() {
    return {
      pic_list: []
    };
  },

  watch: {
    list: {
      immediate: true,
      handler(val) {
        // 确保 pic_list 永远是数组
        this.pic_list = Array.isArray(val) ? [...val] : [];
      }
    }
  },

  methods: {
    deletePic(index) {
      this.pic_list.splice(index, 1);
      this.$emit('input', [...this.pic_list]);
    },

    chooseImage(files) {
      const remain = this.count - this.pic_list.length;
      const addList = files.slice(0, remain).map(item => item.url);
      this.pic_list.push(...addList);
      this.$emit('input', [...this.pic_list]);
    }
  }
};
</script>

<style scoped>
/* 仅利用 absolute 定位将删除按钮移到图片内部右上角，和之前的单图组件保持统一风格 */
.pic-del {
  position: absolute;
  top: 0;
  right: 0;
  width: 20px;
  height: 20px;
  background-color: rgba(0, 0, 0, 0.5); /* 半透明黑底，防止白色图片看不清叉号 */
  color: #fff;
  cursor: pointer;
  z-index: 10;
  border-bottom-left-radius: 4px; /* 让内部小角落稍微圆润一点 */
}

/* 给图片框增加间距，避免多张图片时紧贴在一起 */
.add-image-btn {
  margin-right: 10px;
  margin-bottom: 10px;
}
</style>
