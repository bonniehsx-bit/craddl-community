<template>
  <!-- 外层控制左右边距 -->
  <div class="diy-img-group" :style="outerStyle">
    <!-- 内层控制圆角和高度 -->
    <div class="img-group-inner" :style="innerStyle">

      <!-- 空状态提示 -->
      <div v-if="!list || list.length === 0" class="empty-tip">
        <i class="el-icon-picture"></i>
        <span>请配置图片魔方</span>
      </div>

      <!-- 样式 1: 单图模式 -->
      <div v-else-if="type === 1" class="layout-one">
        <el-image :src="getImage(0)" fit="cover" class="img-full">
          <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
        </el-image>
      </div>

      <!-- 样式 2: 双图并排 (50% + 50%) -->
      <div v-else-if="type === 2" class="layout-two">
        <div class="img-box">
          <el-image :src="getImage(0)" fit="cover" class="img-full">
            <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
          </el-image>
        </div>
        <div class="img-box">
          <el-image :src="getImage(1)" fit="cover" class="img-full">
            <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
          </el-image>
        </div>
      </div>

      <!-- 样式 3: 左一右二 (左50%, 右上下各50%) -->
      <div v-else-if="type === 3" class="layout-three">
        <div class="left-col">
          <el-image :src="getImage(0)" fit="cover" class="img-full">
            <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
          </el-image>
        </div>
        <div class="right-col">
          <div class="right-top">
            <el-image :src="getImage(1)" fit="cover" class="img-full">
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
          </div>
          <div class="right-bottom">
            <el-image :src="getImage(2)" fit="cover" class="img-full">
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'ImgGroup',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    list() {
      return this.safeData.list || [];
    },
    type() {
      return parseInt(this.safeData.type) || 1;
    },
    // 外层样式：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent'
      };
    },
    // 内层样式：控制高度和圆角
    innerStyle() {
      const height = parseInt(this.safeData.height) || 375;
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        height: height / 2 + 'px', // 预览时高度除以2 (rpx -> px)
        borderRadius: radius,
        overflow: 'hidden'
      };
    }
  },
  methods: {
    // 安全获取图片地址
    getImage(index) {
      if (this.list && this.list[index]) {
        return this.list[index].pic_url;
      }
      return '';
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-img-group {
  overflow: hidden;
}

.img-group-inner {
  position: relative;
  width: 100%;
}

.img-full {
  width: 100%;
  height: 100%;
  display: block;
}

.image-slot {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  color: #909399;
  font-size: 20px;
}

.empty-tip {
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  background: #f0f2f5;
  color: #909399;
  font-size: 12px;

  i {
    font-size: 24px;
    margin-bottom: 5px;
  }
}

/* 布局样式 */
.layout-one {
  width: 100%;
  height: 100%;
}

.layout-two {
  display: flex;
  height: 100%;

  .img-box {
    width: 50%;
    height: 100%;
  }
}

.layout-three {
  display: flex;
  height: 100%;

  .left-col {
    width: 50%;
    height: 100%;
  }

  .right-col {
    width: 50%;
    height: 100%;
    display: flex;
    flex-direction: column;

    .right-top,
    .right-bottom {
      height: 50%;
      width: 100%;
    }
  }
}
</style>
