<template>
  <div class="mch-picker">
    <el-select
      v-model="selectedMchId"
      filterable
      remote
      clearable
      placeholder="请输入商户名称搜索"
      :remote-method="searchMch"
      :loading="loading"
      @change="handleChange"
      @clear="handleClear"
      size="small"
      style="width: 100%"
    >
      <!-- 默认选项：平台自营 -->
      <el-option label="平台自营" :value="0">
        <span style="float: left">平台自营</span>
        <span style="float: right; color: #8492a6; font-size: 13px">官方</span>
      </el-option>

      <!-- 远程搜索结果 -->
      <el-option
        v-for="item in mchList"
        :key="item.id"
        :label="item.name"
        :value="item.id"
      >
        <span style="float: left">{{ item.name }}</span>
        <span style="float: right; color: #8492a6; font-size: 13px">ID:{{ item.id }}</span>
      </el-option>
    </el-select>
  </div>
</template>

<script>
export default {
  name: 'MchPicker',
  props: {
    // 接收父组件传入的 mch_id
    value: {
      type: [Number, String],
      default: 0
    },
    initialList: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      selectedMchId: 0,
      loading: false,
      mchList: []
    }
  },
  watch: {
    // 监听初始列表，如果携带了回显数据，直接注入，免除接口慢查询！
    initialList: {
      immediate: true,
      handler(val) {
        if (val && val.length > 0) {
          val.forEach(item => {
            if (!this.mchList.some(m => m.id === item.id)) {
              this.mchList.push(item);
            }
          });
        }
      }
    },
    // 监听父组件传入的值变化（用于编辑时的回显）
    value: {
      immediate: true,
      handler(val) {
        this.selectedMchId = val ? parseInt(val) : 0
        // 如果有ID且列表中没有这个ID（说明是初始化），需要去后台查一下这个商户的名字用于显示
        if (this.selectedMchId > 0 && !this.mchList.some(m => m.id === this.selectedMchId)) {
          this.fetchDetail(this.selectedMchId)
        }
      }
    }
  },
  mounted() {
    // 初始化加载一部分列表
    this.searchMch('')
  },
  methods: {
    // 远程搜索方法
    searchMch(keyword) {
      this.loading = true
      this.$request({
        url: '/plugin/mch/mall/mch/search', // 对应后端控制器的 actionSearch
        data: {
          keyword: keyword
        },
        method: 'get'
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.mchList = res.data.list
        }
      }).catch(() => {
        this.loading = false
      })
    },

    // 专门用于回显：根据ID查单个商户详情
    fetchDetail(id) {
      // 检查当前列表是否已经包含该ID，如果包含就不查了
      const exists = this.mchList.some(item => item.id === id)
      if (exists) return

      this.$request({
        url: '/plugin/mch/mall/mch/search', // 对应后端控制器的 actionSearch

        method: 'get'
      }).then(res => {
        if (res.code === 0 && res.data.list.length > 0) {
          // 将查到的这个商户加入到下拉选项列表中，确保能正确显示名称
          const mch = res.data.list[0]
          // 避免重复添加
          if (!this.mchList.some(item => item.id === mch.id)) {
            this.mchList.push(mch)
          }
        }
      })
    },

    handleChange(val) {
      // 传回给父组件
      this.$emit('input', val)
      // 同时也可以传回整个对象，如果父组件需要名字
      const selectedItem = this.mchList.find(item => item.id === val)
      this.$emit('select', selectedItem)
    },

    handleClear() {
      this.selectedMchId = 0
      this.$emit('input', 0)
    }
  }
}
</script>

<style scoped>
.mch-picker {
  display: inline-block;
  width: 100%;
}
</style>
