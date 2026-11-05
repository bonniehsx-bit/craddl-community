<template>
  <div class="banner-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">基础样式</div>

        <el-form-item label="轮播高度">
          <div class="flex-row">
            <el-slider
              v-model="form.height"
              :min="100"
              :max="1500"
              show-input
              input-size="mini"
              style="flex: 1"
            ></el-slider>
          </div>
          <div class="tip">单位：rpx (基于750px宽度设计稿)</div>
          <div class="tip">例如：设置 750rpx 为正方形，375rpx 为 2:1 比例</div>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button :label="0">无边距</el-radio-button>
            <el-radio-button :label="1">有边距</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="图片圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button :label="0">直角</el-radio-button>
            <el-radio-button :label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="自动播放">
          <el-switch v-model="form.autoPlay"></el-switch>
        </el-form-item>

        <el-form-item label="指示点">
          <el-switch v-model="form.dots"></el-switch>
          <div class="tip">轮播图底部的小圆点;只有一张图时不显示</div>
        </el-form-item>

        <template v-if="form.dots">
          <el-form-item label="点的样式">
            <el-radio-group v-model="form.dot_style">
              <el-radio-button label="pill">胶囊</el-radio-button>
              <el-radio-button label="dot">圆点</el-radio-button>
              <el-radio-button label="num">数字</el-radio-button>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="点的位置">
            <el-radio-group v-model="form.dot_position">
              <el-radio-button label="center">底部居中</el-radio-button>
              <el-radio-button label="right">右下角</el-radio-button>
            </el-radio-group>
          </el-form-item>
        </template>

        <el-form-item label="轮播间隔">
          <el-input-number
            v-model="form.interval"
            :min="1000"
            :step="500"
            controls-position="right"
            style="width: 100%"
            :disabled="!form.autoPlay"
          ></el-input-number>
          <div class="tip">单位：毫秒 (ms)</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">
          <span>图片内容 ({{ form.list.length }})</span>
          <span class="tip">拖拽可排序</span>
        </div>

        <draggable
          v-model="form.list"
          :options="{ animation: 200, handle: '.drag-handle' }"
        >
          <div v-for="(item, index) in form.list" :key="index" class="img-card">
            <div class="card-header">
              <div class="drag-handle">
                <i class="el-icon-s-operation"></i> 图片 {{ index + 1 }}
              </div>
              <div class="delete-btn" @click="handleDelete(index)">
                <i class="el-icon-delete"></i>
              </div>
            </div>

            <div class="card-body">
              <el-form-item label="图片" label-width="50px" style="margin-bottom: 12px;">
                <single-image-selector v-model="item.pic_url"></single-image-selector>
              </el-form-item>

              <el-form-item label="标题" label-width="50px" style="margin-bottom: 12px;">
                <el-input v-model="item.title" placeholder="请输入标题 (可选)"></el-input>
              </el-form-item>

              <el-form-item label="链接" label-width="50px" style="margin-bottom: 0;">
                <link-picker v-model="item.url"></link-picker>
              </el-form-item>
            </div>
          </div>
        </draggable>

        <el-button
          type="primary"
          plain
          icon="el-icon-plus"
          style="width: 100%; margin-top: 10px;"
          @click="handleAdd"
        >添加轮播图片</el-button>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'

export default {
  name: 'BannerConfig',
  components: {
    draggable
  },
  props: {
    value: {
      type: Object,
      default: () => null
    }
  },
  data() {
    return {
      defaultConfig: {
        height: 350, // 默认 rpx
        is_padding: 0,
        is_circle: 0,
        autoPlay: true, // [新增] 默认自动播放
        interval: 3000,
        dots: true, // 是否显示指示点
        dot_style: 'pill', // pill 胶囊 / dot 圆点 / num 数字
        dot_position: 'center', // center 底部居中 / right 右下角

        list: [
          { pic_url: '', url: '', title: '' }
        ]
      }
    }
  },
  computed: {
    form: {
      get() {
        return this.value || JSON.parse(JSON.stringify(this.defaultConfig))
      },
      set(val) {
        this.$emit('input', val)
      }
    }
  },
  watch: {
    value: {
      handler(val) {
        // 如果传入的是空值，或者缺少新字段，进行补全
        if (!val) {
          const initData = JSON.parse(JSON.stringify(this.defaultConfig))
          this.$emit('input', initData)
        } else {
          // 兼容旧数据：缺少字段时按原来的默认行为补全(自动播放、显示指示点)
          if (val.autoPlay === undefined) this.$set(this.form, 'autoPlay', true)
          if (val.dots === undefined) this.$set(this.form, 'dots', true)
          if (!val.dot_style) this.$set(this.form, 'dot_style', 'pill')
          if (!val.dot_position) this.$set(this.form, 'dot_position', 'center')
        }
      },
      immediate: true
    }
  },
  methods: {
    handleAdd() {
      this.form.list.push({ pic_url: '', url: '', title: '' })
    },
    handleDelete(index) {
      this.form.list.splice(index, 1)
    }
  }
}
</script>

<style lang="scss" scoped>
.banner-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;

    &:last-child { border-bottom: none; }

    .section-title {
      font-weight: bold;
      font-size: 13px;
      color: #333;
      margin-bottom: 15px;
      display: flex; justify-content: space-between; align-items: center;
      .tip { font-weight: normal; font-size: 12px; color: #909399; }
    }

    .tip { font-size: 12px; color: #999; line-height: 1.2; margin-top: 5px; }
  }
}

.img-card {
  background: #f7f8fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  margin-bottom: 12px;
  overflow: hidden;
  transition: all 0.2s;

  &:hover { border-color: #c0c4cc; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }

  .card-header {
    height: 32px; background: #fff; border-bottom: 1px solid #e4e7ed;
    display: flex; align-items: center; justify-content: space-between; padding: 0 10px;

    .drag-handle { cursor: move; color: #606266; font-size: 12px; display: flex; align-items: center; i { margin-right: 6px; font-size: 14px; } }
    .delete-btn { cursor: pointer; color: #f56c6c; padding: 4px; &:hover { background: #fef0f0; border-radius: 4px; } }
  }

  .card-body { padding: 12px; }
}

.flex-row { display: flex; align-items: center; }
</style>
