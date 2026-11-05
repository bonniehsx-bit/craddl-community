<template>
  <div class="sku-generator-wrap">
    <div class="spec-section">
      <div class="spec-header">
        <span class="title"><i class="el-icon-setting"></i> 规格属性设置</span>
        <div class="actions">
          <el-button type="primary" size="mini" icon="el-icon-plus" @click="addGroup" v-if="spec_groups.length < 3"
                     plain>
            添加规格项目
          </el-button>
          <el-button type="danger" size="mini" icon="el-icon-refresh-left" @click="resetSpecifications"
                     v-if="spec_groups.length > 0" plain>
            重置全部规格
          </el-button>
        </div>
      </div>

      <div class="spec-body" v-if="spec_groups.length > 0">
        <div v-for="(group, groupIndex) in spec_groups" :key="group.id || groupIndex" class="spec-group-card">
          <div class="group-row">
            <div class="row-label">规格名：</div>
            <div class="row-content">
              <el-input v-model="group.name" placeholder="请输入规格名 (如: 颜色、尺码)" size="small"
                        class="name-input"></el-input>
              <el-button type="text" icon="el-icon-delete" class="text-danger ml-15" @click="deleteGroup(groupIndex)">
                删除此项目
              </el-button>
            </div>
          </div>
          <div class="group-row mt-15">
            <div class="row-label mt-5">规格值：</div>
            <div class="row-content tags-content">
              <el-tag
                v-for="(value, valueIndex) in group.values"
                :key="value.id || valueIndex"
                closable
                effect="plain"
                class="spec-tag"
                @close="deleteValue(groupIndex, valueIndex)"
              >
                {{ value.name }}
              </el-tag>

              <el-input
                class="input-new-tag"
                v-if="group.inputVisible"
                v-model="group.inputValue"
                :ref="'saveTagInput' + groupIndex"
                size="small"
                placeholder="按回车确认"
                @keyup.enter.native="handleInputConfirm(groupIndex)"
                @blur="handleInputConfirm(groupIndex)"
              >
              </el-input>
              <el-button v-else class="button-new-tag" size="small" icon="el-icon-circle-plus-outline"
                         @click="showInput(groupIndex)">
                添加规格值
              </el-button>
            </div>
          </div>
        </div>
        <div class="spec-tip" v-if="spec_groups.length >= 3">
          <i class="el-icon-info"></i> 最多允许添加 3 个规格项目
        </div>
      </div>
      <div class="empty-spec" v-else>
        <span class="empty-text"><i class="el-icon-info"></i> 暂无规格，请点击右上角添加</span>
      </div>
    </div>

    <div class="sku-section" v-if="sku_list.length > 0">
      <div class="sku-header">
        <span class="title"><i class="el-icon-s-grid"></i> 商品 SKU 明细</span>
      </div>

      <div class="batch-toolbar">
        <span class="batch-label">批量设置：</span>
        <el-input-number v-model="batch.price" :min="0" :precision="2" controls-position="right" size="small"
                         placeholder="价格(元)" class="batch-input"></el-input-number>
        <el-input-number v-model="batch.cost_price" :min="0" :precision="2" controls-position="right" size="small"
                         placeholder="成本价(元)" class="batch-input"></el-input-number>
        <el-input-number v-model="batch.purchase_price" :min="0" :precision="2" controls-position="right" size="small"
                         placeholder="采购价(元)" class="batch-input"></el-input-number>
        <el-input-number v-model="batch.stock" :min="0" :precision="0" controls-position="right" size="small"
                         placeholder="库存(件)" class="batch-input"></el-input-number>
        <el-input-number v-model="batch.weight" :min="0" :precision="2" controls-position="right" size="small"
                         placeholder="重量(kg)" class="batch-input"></el-input-number>

        <div style="display: inline-block; margin: 0 10px; vertical-align: middle;">
          <single-image-selector v-model="batch.cover_pic" size="32px"></single-image-selector>
        </div>

        <el-button type="primary" size="small" @click="applyBatch" plain>批量应用</el-button>
      </div>

      <el-table :data="sku_list" border style="width: 100%" size="small" :max-height="skuTableMaxHeight"
                :header-cell-style="{background:'#F5F7FA', color:'#606266', fontWeight:'600'}">
        <el-table-column
          v-if="spec_groups.length > 0"
          v-for="group in spec_groups"
          :key="group.name"
          :label="group.name"
          align="center"
          min-width="100"
        >
          <template slot-scope="scope">
            {{ getSpecValue(scope.row, group.name) }}
          </template>
        </el-table-column>

        <el-table-column label="价格 (元)" prop="price" width="130" align="center">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.price" :min="0" :precision="2" :controls="false" size="mini"
                             style="width: 100%;"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="成本价 (元)" prop="cost_price" width="130" align="center">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.cost_price" :min="0" :precision="2" :controls="false" size="mini"
                             style="width: 100%;"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="采购价 (元)" prop="purchase_price" width="130" align="center">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.purchase_price" :min="0" :precision="2" :controls="false" size="mini"
                             style="width: 100%;"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="库存" prop="stock" width="120" align="center">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.stock" :min="0" :precision="0" :controls="false" size="mini"
                             style="width: 100%;"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="重量 (kg)" prop="weight" width="120" align="center">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.weight" :min="0" :precision="2" :controls="false" size="mini"
                             style="width: 100%;"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="商品编码" prop="no" width="180" align="center">
          <template slot-scope="scope">
            <el-input v-model="scope.row.no" size="mini" placeholder="选填编码"></el-input>
          </template>
        </el-table-column>
        <el-table-column label="SKU预览图" prop="cover_pic" width="120" align="center">
          <template slot-scope="scope">
            <div style="display: flex; justify-content: center;">
              <single-image-selector v-model="scope.row.cover_pic" size="40px"></single-image-selector>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SkuGenerator',
  props: {
    value: {
      type: Object,
      default: () => ({
        spec_groups: [],
        sku_list: []
      })
    }
  },
  data() {
    return {
      spec_groups: [],
      sku_list: [],
      batch: {
        cost_price: null,
        purchase_price: null,
        price: null,
        stock: null,
        weight: null,
        cover_pic: ''
      },
      isInitialLoad: true,
      windowHeight: 800
    }
  },
  computed: {
    /**
     * SKU 表格的 max-height 动态计算：
     * 视口高度 - 抽屉 header(60) - tab 切换栏(50) - 规格设置区(~180) - 批量工具栏(50) - footer(60)
     * 最小 300，保证至少能看到几行
     */
    skuTableMaxHeight() {
      return Math.max(300, this.windowHeight - 400)
    }
  },
  mounted() {
    this.windowHeight = window.innerHeight
    this._resizeHandler = () => { this.windowHeight = window.innerHeight }
    window.addEventListener('resize', this._resizeHandler)
  },
  beforeDestroy() {
    if (this._resizeHandler) {
      window.removeEventListener('resize', this._resizeHandler)
    }
  },
  watch: {
    value: {
      handler(newValue) {
        const internalStateString = JSON.stringify({
          spec_groups: this.spec_groups,
          sku_list: this.sku_list
        })
        if (JSON.stringify(newValue) === internalStateString) {
          return
        }

        this.isInitialLoad = true

        this.spec_groups = JSON.parse(JSON.stringify(newValue.spec_groups || []))
        this.sku_list = JSON.parse(JSON.stringify(newValue.sku_list || []))
        this.addDynamicProperties()

        this.$nextTick(() => {
          this.isInitialLoad = false
        })
      },
      immediate: true,
      deep: true
    },
    spec_groups: {
      handler() {
        if (!this.isInitialLoad) {
          this.generateSkus()
        }
      },
      deep: true
    },
    sku_list: {
      handler(newVal) {
        this.$emit('input', {
          spec_groups: this.spec_groups,
          sku_list: newVal
        })
      },
      deep: true
    }
  },
  methods: {
    resetSpecifications() {
      this.$confirm('此操作将清空所有已添加的规格组和规格值，并重置下方的SKU表格，是否继续？', '确认重置', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.isInitialLoad = false
        this.spec_groups = []
        this.$message.success('规格已重置')
      }).catch(() => {
        this.$message.info('已取消操作')
      })
    },

    getSpecValue(sku, groupName) {
      if (!sku || !Array.isArray(sku.specs)) {
        return ''
      }
      const spec = sku.specs.find(s => s.group === groupName)
      return spec ? spec.value : 'N/A'
    },

    addDynamicProperties() {
      if (!this.spec_groups) this.spec_groups = []
      this.spec_groups.forEach(group => {
        this.$set(group, 'inputVisible', false)
        this.$set(group, 'inputValue', '')
      })
    },

    addGroup() {
      this.isInitialLoad = false
      if (this.spec_groups.length >= 3) {
        this.$message.warning('最多添加3个规格组')
        return
      }
      this.spec_groups.push({
        id: 'temp_' + Date.now(),
        name: '',
        values: [],
        inputVisible: false,
        inputValue: ''
      })
    },
    deleteGroup(groupIndex) {
      this.$confirm('确定要删除此规格组吗？', '提示').then(() => {
        this.isInitialLoad = false
        this.spec_groups.splice(groupIndex, 1)
      }).catch(() => {
      })
    },
    showInput(groupIndex) {
      this.spec_groups[groupIndex].inputVisible = true
      this.$nextTick(() => {
        this.$refs['saveTagInput' + groupIndex][0].$refs.input.focus()
      })
    },
    handleInputConfirm(groupIndex) {
      this.isInitialLoad = false
      const group = this.spec_groups[groupIndex]
      let inputValue = group.inputValue
      if (inputValue) {
        if (group.values.some(v => v.name === inputValue)) {
          this.$message.error('规格值已存在')
        } else {
          group.values.push({
            id: 'temp_' + Date.now(),
            name: inputValue
          })
        }
      }
      group.inputVisible = false
      group.inputValue = ''
    },
    deleteValue(groupIndex, valueIndex) {
      this.isInitialLoad = false
      this.spec_groups[groupIndex].values.splice(valueIndex, 1)
    },
    generateSkus() {
      const validSpecGroups = this.spec_groups.filter(g => g.name && g.values.length > 0)
      if (validSpecGroups.length === 0) {
        this.sku_list = []
        return
      }
      const cartesianProduct = (arrays) => {
        return arrays.reduce((acc, curr) => {
          return acc.flatMap(c => curr.map(n => [].concat(c, n)))
        }, [[]])
      }
      const valueArrays = validSpecGroups.map(g => g.values)
      const combinations = cartesianProduct(valueArrays)
      const oldSkuMap = new Map()
      this.sku_list.forEach(sku => {
        const key = (sku.specs || []).map(s => `${s.group}:${s.value}`).sort().join(';')
        if (key) {
          oldSkuMap.set(key, sku)
        }
      })
      const newSkuList = combinations.map(combo => {
        if (!Array.isArray(combo) || combo.length === 0) return null
        const specs = combo.map((value, index) => ({
          group: validSpecGroups[index].name,
          value: value.name
        }))
        const key = specs.map(s => `${s.group}:${s.value}`).sort().join(';')
        const oldSku = oldSkuMap.get(key)
        if (oldSku) {
          const orderedSpecs = validSpecGroups.map(group => {
            return specs.find(s => s.group === group.name) || {group: group.name, value: ''}
          })
          return {...oldSku, specs: orderedSpecs}
        }
        return {
          specs: specs,
          price: 0,
          cost_price: 0,
          purchase_price: 0,
          stock: 0,
          weight: 0,
          no: '',
          cover_pic: ''
        }
      }).filter(Boolean)

      this.sku_list = newSkuList
    },
    applyBatch() {
      if (
        this.batch.price === null &&
        this.batch.stock === null &&
        this.batch.cost_price === null &&
        this.batch.purchase_price === null &&
        this.batch.weight === null &&
        !this.batch.cover_pic
      ) {
        this.$message.warning('请输入要批量设置的内容')
        return
      }
      this.sku_list.forEach(sku => {
        if (this.batch.price !== null && this.batch.price >= 0) {
          sku.price = this.batch.price
        }
        if (this.batch.cost_price !== null && this.batch.cost_price >= 0) {
          sku.cost_price = this.batch.cost_price
        }
        if (this.batch.purchase_price !== null && this.batch.purchase_price >= 0) {
          sku.purchase_price = this.batch.purchase_price
        }
        if (this.batch.stock !== null && this.batch.stock >= 0) {
          sku.stock = this.batch.stock
        }
        if (this.batch.weight !== null && this.batch.weight >= 0) {
          sku.weight = this.batch.weight
        }
        if (this.batch.cover_pic) {
          sku.cover_pic = this.batch.cover_pic
        }
      })
      this.$message.success('批量设置成功')
    }
  }
}
</script>
<style scoped lang="scss">
.sku-generator-wrap {
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background-color: #fff;
  overflow: hidden;
}

/* 共用头部样式 */
.spec-header, .sku-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background-color: #f8f9fb;
  border-bottom: 1px solid #ebeef5;

  .title {
    font-size: 14px;
    font-weight: bold;
    color: #303133;

    i {
      margin-right: 5px;
      color: #909399;
    }
  }
}

/* 规格组区域 */
.spec-section {
  border-bottom: 8px solid #f5f7fa;
}

.spec-body {
  padding: 16px;
  background-color: #fff;
}

.empty-spec {
  padding: 30px 0;
  text-align: center;
  background-color: #fff;

  .empty-text {
    font-size: 13px;
    color: #909399;
  }
}

.spec-group-card {
  padding: 16px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  margin-bottom: 16px;
  background-color: #fafbfc;
  transition: all 0.3s;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    border-color: #dcdfe6;
  }

  &:last-child {
    margin-bottom: 0;
  }
}

.group-row {
  display: flex;
  align-items: flex-start;

  .row-label {
    width: 65px;
    font-size: 13px;
    color: #606266;
    line-height: 32px;
  }

  .row-content {
    flex: 1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;

    .name-input {
      width: 200px;
    }
  }

  .tags-content {
    gap: 10px;
  }
}

.spec-tag {
  border-radius: 4px;
}

.input-new-tag {
  width: 110px;
}

.spec-tip {
  margin-top: 10px;
  font-size: 12px;
  color: #909399;
}

/* SKU 明细区域 */
.sku-section {
  background-color: #fff;
}

.batch-toolbar {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background-color: #fdfdfd;
  border-bottom: 1px solid #ebeef5;
  gap: 12px;
  /* 批量工具栏固定在滚动容器顶部，滚 SKU 列表时始终可见 */
  position: sticky;
  top: 0;
  z-index: 5;

  .batch-label {
    font-size: 13px;
    color: #606266;
  }

  .batch-input {
    width: 130px;
  }
}

.text-danger {
  color: #F56C6C;
}

.ml-15 {
  margin-left: 15px;
}

.mt-15 {
  margin-top: 15px;
}

.mt-10 {
  margin-top: 10px;
}

.mt-5 {
  margin-top: 5px;
}

/* 覆盖表格内输入框默认样式 */
::v-deep .el-table {
  .el-input-number.is-without-controls .el-input__inner {
    text-align: left;
    padding-left: 10px;
    padding-right: 10px;
  }
}
</style>
