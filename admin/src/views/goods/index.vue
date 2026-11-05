<template>
  <div class="app-container">
    <!-- 使用 flex 布局的卡片，填满容器 -->
    <el-card class="box-card full-height-card" shadow="never">
      <div slot="header" class="flex-x-between">
        <span class="card-title">商品列表</span>
        <!-- 批量上下架原来挤在搜索栏里,把搜索栏撑成两行;它们是对表格的操作,放标题栏 -->
        <div class="header-actions">
          <span class="selected-count" v-if="selectedGoodsIds.length > 0">
            已选 <b>{{ selectedGoodsIds.length }}</b> 项
          </span>
          <el-button size="mini" type="success" plain icon="el-icon-top" @click="handleBatchStatus(1)"
                     :disabled="selectedGoodsIds.length === 0">批量上架</el-button>
          <el-button size="mini" type="warning" plain icon="el-icon-bottom" @click="handleBatchStatus(0)"
                     :disabled="selectedGoodsIds.length === 0">批量下架</el-button>
          <el-button type="primary" size="mini" icon="el-icon-plus" @click="handleAdd">发布新商品</el-button>
        </div>
      </div>

      <!-- 卡片主体内容容器：Flex 纵向布局 -->
      <div class="card-body-container">

        <!-- 1. 顶部固定区域：搜索栏(上架状态原来是一排页签,占一整行,收进搜索栏) -->
        <div class="header-section">
          <div class="filter-container">
            <el-form size="mini" :model="searchForm" ref="searchForm" :inline="true" class="compact-search-form">
              <el-form-item>
                <el-input v-model="searchForm.keyword" placeholder="商品名称/编码" clearable class="filter-item-input">
                  <i slot="prefix" class="el-input__icon el-icon-search"></i>
                </el-input>
              </el-form-item>

              <el-form-item>
                <el-cascader
                  placeholder="商品分类"
                  class="filter-item-input"
                  @change="handleCatChange"
                  :options="cat_level_list"
                  :show-all-levels="false"
                  :props="{ multiple: true, checkStrictly: true, label: 'name', value: 'id' }"
                  clearable
                >
                  <template slot-scope="{ node, data }">
                    <span>{{ data.name }}</span>
                    <span v-if="!node.isLeaf" class="text-muted"> ({{ data.children.length }}) </span>
                  </template>
                </el-cascader>
              </el-form-item>

              <el-form-item>
                <el-select v-model="searchForm.source" placeholder="商品来源" class="filter-item-select" clearable>
                  <el-option label="全部商品" value=""></el-option>
                  <el-option label="平台自营" value="0"></el-option>
                  <el-option label="商家商品" value="1"></el-option>
                </el-select>
              </el-form-item>

              <el-form-item v-if="searchForm.source === '1' || searchForm.source === ''">
                <el-input v-model="searchForm.mch_name" placeholder="商家名称搜索" clearable
                          class="filter-item-input"></el-input>
              </el-form-item>

              <el-form-item>
                <el-select v-model="searchForm.is_check" placeholder="审核状态" class="filter-item-select" clearable>
                  <el-option label="全部审核状态" value="-1"></el-option>
                  <el-option label="待审核" value="0"></el-option>
                  <el-option label="已通过" value="1"></el-option>
                </el-select>
              </el-form-item>

              <!-- 不给清空:后端没收到 status 时默认只查销售中,清空会悄悄变成只看销售中 -->
              <el-form-item>
                <el-select v-model="searchForm.status" placeholder="上架状态" class="filter-item-select">
                  <el-option label="全部" :value="-1"></el-option>
                  <el-option label="销售中" :value="1"></el-option>
                  <el-option label="已下架" :value="0"></el-option>
                </el-select>
              </el-form-item>

              <el-form-item>
                <el-date-picker
                  class="filter-item-date"
                  value-format="yyyy-MM-dd"
                  v-model="searchForm.search_date"
                  type="daterange"
                  range-separator="-"
                  start-placeholder="创建起始"
                  end-placeholder="创建结束"
                >
                </el-date-picker>
              </el-form-item>

              <el-form-item class="form-actions">
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button plain @click="exportExcel" :loading="exportLoading" icon="el-icon-download">导出</el-button>
              </el-form-item>

            </el-form>
          </div>
        </div>

        <!-- 2. 中间自适应表格区域：关键在于 height="100%" -->
        <div class="table-section">
          <el-table
            size="mini"
            :data="list"
            border
            height="100%"
            v-loading="is_loading"
            @selection-change="handleSelectionChange"
            @row-dblclick="handleEdit"
            class="custom-table"
            stripe
            style="cursor:pointer"
            :header-cell-style="{background:'#F5F7FA', color:'#606266', fontWeight:'600'}"
          >
            <el-table-column type="selection" width="45" align="center" fixed></el-table-column>
            <el-table-column prop="id" label="ID" width="70" align="center" fixed></el-table-column>

            <el-table-column label="商品信息" min-width="280" fixed>
              <template slot-scope="scope">
                <div class="goods-info-cell">
                  <image-view :height="48" :width="48" :src="scope.row.cover_pic" class="goods-img"></image-view>
                  <div class="goods-desc">
                    <div class="goods-name" :title="scope.row.name">{{ scope.row.name }}</div>
                    <div class="goods-no text-muted">编码：{{ scope.row.no || '--' }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="商品来源" width="140">
              <template slot-scope="scope">
                <div v-if="scope.row.mch" class="source-cell">
                  <el-tag :type="scope.row.mch.type == 2 ? 'warning' : 'success'" size="mini" effect="plain">
                    {{ scope.row.mch.type == 2 ? '门店' : '供应商' }}
                  </el-tag>
                  <div class="mch-name text-ellipsis" :title="scope.row.mch.mch_name">{{ scope.row.mch.mch_name }}</div>
                </div>
                <el-tag v-else size="mini" effect="dark" type="info">平台自营</el-tag>
              </template>
            </el-table-column>

            <el-table-column prop="cat_name" label="分类" width="120" show-overflow-tooltip>
              <template slot-scope="scope">
                <span v-if="scope.row.cat">{{ scope.row.cat.name }}</span>
                <span v-else class="text-muted">--</span>
              </template>
            </el-table-column>

            <el-table-column label="售价 (元)" width="110" align="right">
              <template slot-scope="scope">
                <span class="price-text">¥{{ scope.row.price }}</span>
              </template>
            </el-table-column>

            <el-table-column prop="stock" label="库存" width="90" align="center"></el-table-column>
            <el-table-column prop="sales" label="销量" width="90" align="center"></el-table-column>
            <el-table-column prop="sort" label="排序" width="70" align="center"></el-table-column>

            <el-table-column label="审核状态" width="100" align="center">
              <template slot-scope="{row,$index}">
                <el-popconfirm
                  confirmButtonText="确认"
                  cancelButtonText="取消"
                  :title="'要切换[' + row.name.substring(0,10) + '...]的审核状态吗？'"
                  @confirm="goodsCheck(row.id, $index)"
                >
                  <el-button
                    slot="reference"
                    size="mini"
                    :type="row.is_check == 1 ? 'success' : 'danger'"
                    :plain="row.is_check != 1"
                    class="status-btn"
                  >
                    {{ row.is_check == 1 ? '已通过' : '待审核' }}
                  </el-button>
                </el-popconfirm>
              </template>
            </el-table-column>

            <el-table-column label="上架状态" width="100" align="center">
              <template slot-scope="scope">
                <el-popconfirm
                  confirmButtonText="确认"
                  cancelButtonText="取消"
                  :title="scope.row.status == 1 ? '确认下架此商品？' : '确认上架此商品？'"
                  @confirm="goodsStatusChange(scope.row.id, scope.row.status == 1 ? 0 : 1)"
                >
                  <el-button
                    slot="reference"
                    size="mini"
                    :type="scope.row.status == 1 ? 'primary' : 'info'"
                    :plain="scope.row.status != 1"
                    class="status-btn"
                  >
                    {{ scope.row.status == 1 ? '销售中' : '已下架' }}
                  </el-button>
                </el-popconfirm>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="180"  align="center">
              <template slot-scope="{row,$index}">
                <div class="action-cell">
                  <el-tooltip effect="dark" content="编辑" placement="top">
                    <el-button type="primary" size="mini" icon="el-icon-edit" circle
                               @click="handleEdit(row)"></el-button>
                  </el-tooltip>

                  <el-tooltip effect="dark" content="发布相似商品" placement="top">
                    <el-button type="success" size="mini" icon="el-icon-document-copy" circle
                               @click="handleSimilar(row)"></el-button>
                  </el-tooltip>

                  <el-tooltip v-if="!row.mch" effect="dark" content="指派给商家" placement="top">
                    <el-button type="warning" size="mini" icon="el-icon-s-shop" circle
                               @click="openAssignDialog(row)"></el-button>
                  </el-tooltip>

                  <el-tooltip effect="dark" content="删除" placement="top">
                    <el-popconfirm confirmButtonText="确定" cancelButtonText="取消" :title="'确定删除该商品吗?'"
                                   @confirm="goodsDelete(row.id, $index)">
                      <el-button type="danger" size="mini" icon="el-icon-delete" circle slot="reference"
                                 class="ml-10"></el-button>
                    </el-popconfirm>
                  </el-tooltip>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 3. 底部固定区域：分页 -->
        <pagination
          :pagination="pagination"
          :page-sizes="[10, 20, 50, 100]"
          :page.sync="searchForm.page"
          :limit.sync="searchForm.limit"
          @pageChange="pageChange"
          @sizeChange="sizeChange"
        ></pagination>
      </div>
    </el-card>

    <!-- 弹窗组件 -->
    <UpDownJob :show="showUpDown" :goods_id="goods_id" @close="closeUpDownJob"></UpDownJob>
    <goods-edit-drawer
      :visible.sync="editVisible"
      :goods-id="currentGoodsId"
      :similar-from="similarFromId"
      @success="getList"
    />

    <!-- 指派商户弹窗 -->
    <el-dialog
      title="指派商品给商家"
      :visible.sync="assignDialog.visible"
      width="550px"
      custom-class="custom-dialog"
      :close-on-click-modal="false"
      append-to-body
      v-if="assignDialog.visible"
    >
      <div class="assign-header" v-if="assignDialog.goods">
        <i class="el-icon-goods"></i> <span>目标商品：{{ assignDialog.goods.name }}</span>
      </div>

      <el-steps :active="assignDialog.step" finish-status="success" simple class="custom-steps">
        <el-step title="选择接收商户"></el-step>
        <el-step title="选择商户分类"></el-step>
      </el-steps>

      <div v-show="assignDialog.step === 0" class="wizard-step">
        <el-form size="mini" label-position="top">
          <el-form-item label="搜索并选择商户" required>
            <el-select
              v-model="assignDialog.form.mch_id"
              filterable remote :remote-method="searchMchForAssign"
              placeholder="请输入商户ID、名称或手机号搜索"
              :loading="assignDialog.mchSearchLoading"
              style="width: 100%;"
              @change="onMchSelectedForAssign"
            >
              <el-option v-for="item in assignDialog.mchList" :key="item.id" :label="item.mch_name" :value="item.id">
                <div class="mch-info-select">
                  <el-avatar shape="square" :size="24" :src="item.logo"></el-avatar>
                  <span class="m-name">{{ item.mch_name }}</span>
                  <span class="m-id text-muted">(ID: {{ item.id }})</span>
                </div>
              </el-option>
            </el-select>
          </el-form-item>
        </el-form>
      </div>

      <div v-show="assignDialog.step === 1" class="wizard-step" v-loading="assignDialog.catLoading">
        <el-form size="mini" label-position="top">
          <el-form-item label="选择该商户下的商品分类" required>
            <el-cascader
              v-model="assignDialog.form.cat_id_path"
              :options="assignDialog.catList"
              :props="{ checkStrictly: true, value: 'id', label: 'name', children: 'children' }"
              placeholder="请选择分类映射"
              clearable
              style="width: 100%;"
            ></el-cascader>
          </el-form-item>
        </el-form>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button @click="assignDialog.step--" v-if="assignDialog.step > 0" size="mini">返回上一步</el-button>
        <el-button type="primary" @click="handleAssignNext" :loading="assignDialog.loading" size="mini">
          {{ assignDialog.step === 0 ? '下一步' : '确认指派' }}
        </el-button>
      </span>
    </el-dialog>
  </div>
</template>


<script>
import UpDownJob from '@/components/goods/UpDownJob'
import GoodsEditDrawer from '@/components/goods/GoodsEditDrawer.vue'

export default {
  name: 'goods-index',
  components: {
    UpDownJob,
    GoodsEditDrawer,

  },
  data() {
    return {
      // 优化后的参数整合，包含分页和业务状态
      searchForm: {
        page: 1,
        limit: 10,
        status: -1,
        keyword: '',
        mch_name: '',
        search_date: [],
        cat_ids: [],
        is_check: '-1',
        source: '',
        // 库存预警筛选。仅由后台首页那张卡片带 ?low_stock=1 进来时置位,
        // 阈值在后端(Goods::LOW_STOCK_THRESHOLD),前端不重复定义
        low_stock: 0
      },

      editVisible: false,
      currentGoodsId: '',
      similarFromId: '', // 发布相似商品:以哪件为模板
      cat_level_list: [],
      showUpDown: false,
      is_loading: false,
      list: [],
      goods_id: '',
      pagination: null,
      selectedGoodsIds: [],

      exportLoading: false,
      assignDialog: {
        visible: false,
        loading: false,
        step: 0,
        goods: null,
        mchSearchLoading: false,
        catLoading: false,
        mchList: [],
        catList: [],
        form: {
          goods_id: null,
          mch_id: null,
          cat_id_path: []
        }
      }
    }
  },
  created() {
    // 后台首页「库存预警」卡片点进来:?low_stock=1
    // 不接这个参数的话,卡片说 140 件、点进来却是全量列表,没法定位那 140 件
    if (this.$route && this.$route.query.low_stock == 1) {
      this.searchForm.low_stock = 1
      this.searchForm.status = 1   // 卡片只统计在售商品,列表要跟它一致
    }
    this.getCatLevel()
  },
  mounted() {
    this.getList()
  },
  methods: {
    // 构造 API 参数
    getApiParams(isExport = 0) {
      const params = JSON.parse(JSON.stringify(this.searchForm))
      if (isExport) {
        params.is_export = 1
      }
      return params
    },

    // 查询列表
    getList() {
      this.is_loading = true
      const params = this.getApiParams()

      this.$request({
        url: '/mall/goods/list',
        data: params // 根据您的原代码逻辑，此处用 data
      }).then(res => {
        this.is_loading = false
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {
        this.is_loading = false
      })
    },

    // 搜索按钮
    handleSearch() {
      this.searchForm.page = 1
      this.getList()
    },

    // 分类筛选变化
    handleCatChange(e) {
      if (e && e.length > 0) {
        // 多选 + checkStrictly 返回的是每个选中项的完整路径 [[一级, 二级], ...],只取末尾那个才是真正选中的分类。
        // 原来把整条路径拍平一起传,一级 id 也在里面;而商品存分类时整条路径都挂(goods_cat 同时有一级和二级),
        // 于是选二级也会筛出一级下的全部商品。选一级本身时末尾就是一级,照样能筛出它下面所有商品。
        const leafIds = e.map(path => (Array.isArray(path) ? path[path.length - 1] : path))
        this.searchForm.cat_ids = [...new Set(leafIds)]
      } else {
        this.searchForm.cat_ids = []
      }
    },

    // 分页：页码改变
    pageChange(e) {
      this.searchForm.page = e
      this.getList()
    },

    // 分页：每页条数改变
    sizeChange(e) {
      this.searchForm.limit = e
      this.searchForm.page = 1
      this.getList()
    },

    // 导出
    async exportExcel() {
      try {
        await this.$confirm('确认导出当前筛选条件下的所有商品记录？', '导出确认')
        this.exportLoading = true
        const params = this.getApiParams(1)

        const res = await this.$request({
          url: '/mall/goods/list',
          method: 'get',
          data: params
        })

        if (res.code === 0 && res.data.list.length > 0) {
          const listToExport = res.data.list.map(item => {
            return {
              id: item.id,
              name: item.name,
              no: item.no,
              cat_name: item.cat ? item.cat.name : '',
              price: item.price,
              origin_price: item.origin_price,
              cost_price: item.cost_price,
              stock: item.stock,
              sales: item.sales,
              status_text: item.status === 1 ? '已上架' : '未上架',
              is_check_text: item.is_check === 1 ? '已审核' : (item.is_check === 0 ? '待审核' : '未知'),
              source_text: item.mch ? '商家商品' : '平台自营',
              mch_name: item.mch ? item.mch.mch_name : '',
              platform_goods_id: item.platform_goods_id > 0 ? item.platform_goods_id : '否'
            }
          })

          const header = [
            'ID', '商品名称', '商品编码', '分类', '价格', '原价', '成本价',
            '库存', '销量', '状态', '审核状态', '商品来源', '商家名称', '平台导入ID'
          ]
          const fields = [
            'id', 'name', 'no', 'cat_name', 'price', 'origin_price', 'cost_price',
            'stock', 'sales', 'status_text', 'is_check_text', 'source_text', 'mch_name', 'platform_goods_id'
          ]

          this.$export(header, fields, listToExport, '商品列表')
        } else {
          this.$message.info('没有可导出的数据')
        }
      } catch (error) {
        if (error !== 'cancel') {
          this.$message.error(error.message || '导出失败')
        }
      } finally {
        this.exportLoading = false
      }
    },

    // 批量上下架
    async handleBatchStatus(status) {
      if (this.selectedGoodsIds.length === 0) {
        this.$message.warning('请至少选择一件商品')
        return
      }
      const actionText = status === 1 ? '上架' : '下架'
      try {
        await this.$confirm(`确定要批量${actionText}选中的 ${this.selectedGoodsIds.length} 件商品吗?`, '提示', {
          type: 'warning'
        })
        this.is_loading = true
        const res = await this.$request({
          url: '/mall/goods/batch-status-change',
          method: 'post',
          data: {
            ids: this.selectedGoodsIds,
            status: status
          }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.getList()
        } else {
          this.$message.error(res.msg)
        }
      } catch (error) {
        if (error === 'cancel') {
          this.$message.info('已取消操作')
        } else {
          console.error(error)
        }
      } finally {
        this.is_loading = false
      }
    },

    // 审核
    goodsCheck(id, index) {
      this.$request({
        url: '/mall/goods/check',
        data: {id: id},
        method: 'post'
      }).then(res => {
        this.$message.success('审核成功')
        this.list[index].is_check = this.list[index].is_check == 1 ? 0 : 1
      })
    },
    getCatLevel() {
      this.$request({url: '/mall/goods/all-cat-level'}).then(res => {
        if (res.code == 0) {
          this.cat_level_list = res.data.list
        }
      })
    },
    closeUpDownJob() {
      this.goods_id = ''
      this.showUpDown = false
    },
    showUpDownModal(id) {
      this.goods_id = id
      this.showUpDown = true
    },
    handleAdd() {
      this.currentGoodsId = '';
      this.similarFromId = '';
      this.editVisible = true;
    },
    handleEdit(row) {
      this.currentGoodsId = row.id;
      this.similarFromId = '';
      this.editVisible = true;
    },
    // 发布相似商品:以这件为模板新建,内容照搬、默认下架,改完保存就是一件新商品
    handleSimilar(row) {
      this.currentGoodsId = '';
      this.similarFromId = row.id;
      this.editVisible = true;
    },
    goodsStatusChange(id, status) {
      this.$request({
        url: '/mall/goods/status-change',
        data: {id: id, status: status},
        method: 'post'
      }).then((res) => {
        this.getList()
        this.$message.success(res.msg)
      })
    },
    goodsDelete(id, index) {
      this.$request({
        url: '/mall/goods/delete',
        data: {id: id},
        method: 'post'
      }).then((res) => {
        this.getList()
        this.$message.success(res.msg)
      })
    },
    handleSelectionChange(selection) {
      this.selectedGoodsIds = selection.map(item => item.id)
    },

    // 指派商户逻辑
    openAssignDialog(row) {
      this.assignDialog = {
        visible: true, loading: false, step: 0, goods: row,
        mchSearchLoading: false, catLoading: false, mchList: [], catList: [],
        form: {goods_id: row.id, mch_id: null, cat_id_path: []}
      }
    },
    async searchMchForAssign(keyword) {
      if (!keyword) return
      this.assignDialog.mchSearchLoading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/index',
          data: {keyword, limit: 50}
        })
        if (res.code === 0) {
          this.assignDialog.mchList = res.data.list
        }
      } finally {
        this.assignDialog.mchSearchLoading = false
      }
    },
    async onMchSelectedForAssign(mchId) {
      if (!mchId) {
        this.assignDialog.catList = []
        this.assignDialog.form.cat_id_path = []
        return
      }
      this.assignDialog.catLoading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/cat/index',
          data: {mch_id: mchId}
        })
        if (res.code === 0) {
          this.assignDialog.catList = res.data.list
        } else {
          this.$message.error('加载商户分类失败: ' + res.msg)
        }
      } finally {
        this.assignDialog.catLoading = false
      }
    },
    async handleAssignNext() {
      if (this.assignDialog.step === 0) {
        if (!this.assignDialog.form.mch_id) {
          return this.$message.warning('请先选择一个商户')
        }
        this.assignDialog.step++
      } else if (this.assignDialog.step === 1) {
        const {goods_id, mch_id, cat_id_path} = this.assignDialog.form
        if (!cat_id_path || cat_id_path.length === 0) {
          return this.$message.warning('请为商品选择一个分类')
        }
        const cat_id = cat_id_path[cat_id_path.length - 1]
        this.assignDialog.loading = true
        try {
          const res = await this.$request({
            url: '/mall/goods/assign-mch',
            method: 'post',
            data: {goods_id, mch_id, cat_id}
          })
          if (res.code === 0) {
            this.$message.success('指派成功！')
            this.assignDialog.visible = false
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        } finally {
          this.assignDialog.loading = false
        }
      }
    }
  }
}
</script>
<style lang="scss" scoped>
/* 1. 核心布局样式 - 锁定页面高度，内部滚动 */
.app-container {
  padding: 10px;
  background-color: #f0f2f5;
  /* 假设顶部导航栏高度为 50px，如果不是请调整 calc */
  height: calc(100vh - 85px);
  display: flex;
  flex-direction: column;
}

.full-height-card {
  flex: 1; /* 撑满高度 */
  display: flex;
  flex-direction: column;
  overflow: hidden; /* 防止卡片本身溢出 */
  border-radius: 4px;
  border: none;
}

/* 深度选择器，强行让 el-card__body 变成 flex 布局以支撑表格 */
::v-deep .el-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden; /* 关键：禁止 body 滚动，交给 table 滚动 */
  padding: 15px;
}

.card-body-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%; /* 确保填满父容器 */
  overflow: hidden;
}

/* 顶部区域 (搜索栏) */
.header-section {
  flex-shrink: 0; /* 禁止压缩 */
}

/* 中间表格区域 */
.table-section {
  flex: 1; /* 占据剩余所有空间 */
  overflow: hidden; /* 必须设置，否则 el-table height="100%" 不生效 */
  margin-top: 10px;
}

/* 底部区域 (分页) */
.pagination-section {
  flex-shrink: 0;
  padding-top: 15px;
  display: flex;
  justify-content: flex-end;
}

/* 2. 细节美化 */
.flex-x-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 16px;
  font-weight: bold;
  color: #303133;
}

.filter-container {
  background: #fcfcfc;
  padding: 10px 12px 0 12px;
  border-radius: 4px;
  border: 1px solid #f0f0f0;
}

// 搜索栏尽量一行排下(紧凑),窄屏照样自动折行
.compact-search-form {
  .el-form-item {
    margin-bottom: 10px;
    margin-right: 8px;
  }

  .filter-item-input {
    width: 140px;
  }

  .filter-item-select {
    width: 108px;
  }

  .filter-item-date {
    width: 220px;
  }
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;

  .el-button + .el-button { margin-left: 0; }

  .selected-count {
    font-size: 13px;
    color: #606266;

    b { color: var(--admin-accent-text); }
  }
}



/* 表格内部样式 */
.goods-info-cell {
  display: flex;
  align-items: center;

  .goods-img {
    flex-shrink: 0;
    border-radius: 4px;
    border: 1px solid #eee;
  }

  .goods-desc {
    margin-left: 10px;
    overflow: hidden;

    .goods-name {
      font-size: 13px;
      color: #333;
      margin-bottom: 4px;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      overflow: hidden;
    }

    .goods-no {
      font-size: 12px;
    }
  }
}

.source-cell .mch-name {
  margin-top: 4px;
  font-size: 12px;
  color: #606266;
}

.price-text {
  color: #F56C6C;
  font-weight: bold;
}

.action-cell .ml-10 {
  margin-left: 10px;
}

/* 弹窗样式 */
.assign-header {
  padding: 10px 15px;
  background-color: #f0f9eb;
  color: #67c23a;
  border-radius: 4px;
  margin-bottom: 20px;
}

.mch-info-select {
  display: flex;
  align-items: center;
  gap: 8px;

  .m-name {
    font-weight: 500;
  }

  .m-id {
    font-size: 12px;
    color: #999;
  }
}

.wizard-step {
  padding: 20px 10px;
  min-height: 150px;
}

/* 通用 */
.text-muted {
  color: #909399;
}

.text-primary {
  color: var(--admin-accent-text);
  font-weight: bold;
}

.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
