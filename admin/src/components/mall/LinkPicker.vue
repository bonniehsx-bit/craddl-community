<template>
  <div class="input-with-link-picker">
    <el-input
      :value="inputValue"
      @input="handleInput"
      :placeholder="placeholder"
      :size="size"
      :disabled="disabled"
      clearable
    >
      <template slot="append">
        <el-button :size="size" @click="openLinkDialog" :disabled="disabled">选择链接</el-button>
      </template>
    </el-input>

    <el-dialog title="选择链接" :visible.sync="dialogVisible" :before-close="handleDialogCancel" width="1080px"
               append-to-body
    >
      <el-tabs v-model="activeTabName" @tab-click="handleTabClickInDialog" v-loading="isLoadingInDialog">
        <!-- 商城页面 -->
        <el-tab-pane label="商城页面" name="mall">
          <el-input v-model="groupKeyword" placeholder="搜索链接名称、分组或路径" clearable size="small"
                    prefix-icon="el-icon-search" style="margin-bottom: 12px;"
          >
            <template slot="append">{{ groupLinkCount }} 条</template>
          </el-input>
          <el-scrollbar style="height: 410px;">
            <div v-if="activeTabName === 'mall' && filteredLinkGroups.length > 0">
              <div style="margin-bottom: 20px;" v-for="(group, groupIndex) in filteredLinkGroups" :key="`group-${groupIndex}`">
                <div class="link-group-title">{{ group.name }}</div>
                <div class="links">
                  <div
                    class="flex-y-center flex-x-center link-item"
                    :class="{ active: dialogSelectedUrl === link.url }"
                    v-for="(link, linkIndex) in group.links"
                    :key="`link-${groupIndex}-${linkIndex}`"
                    @click="selectSimpleLinkInDialog(link)"
                    :title="link.url"
                  >
                    <span>{{ link.name }}</span>
                  </div>
                </div>
              </div>
            </div>
            <el-empty v-else-if="activeTabName === 'mall' && !isLoadingInDialog"
                      :description="groupKeyword ? `没有匹配「${groupKeyword}」的链接` : '暂无数据'"
            ></el-empty>
          </el-scrollbar>
        </el-tab-pane>

        <!-- 商品 -->
        <el-tab-pane label="商品" name="goods">
          <el-row style="margin-bottom: 15px;">
            <el-col :span="16">
              <div class="flex-row">
                <el-input v-model="searchKeywordInDialog" placeholder="输入商品名称进行搜索" clearable
                          @clear="searchItemsInDialog" @keyup.enter.native="searchItemsInDialog"
                ></el-input>
                <el-button @click="searchItemsInDialog" type="primary" style="margin: 0 10px;">搜索</el-button>
              </div>
            </el-col>
          </el-row>
          <el-table size="mini" :data="itemListInDialog" style="width: 100%">
            <el-table-column label="商品" width="350px">
              <template slot-scope="scope">
                <div class="flex-row" style="align-items: center;">
                  <el-image :src="scope.row.cover_pic" style="width: 40px;height: 40px; flex-shrink: 0;">
                    <div slot="error" class="image-slot flex-y-center flex-x-center"
                         style="width: 40px;height: 40px; background: #f5f7fa;"
                    >
                      <i class="el-icon-picture-outline" style="font-size: 20px;"></i>
                    </div>
                  </el-image>
                  <div style="margin-left: 10px; white-space: normal;">{{ scope.row.name }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="price" label="价格" width="100"></el-table-column>
            <el-table-column prop="stock" label="库存" width="100"></el-table-column>
            <el-table-column label="操作" width="100">
              <template slot-scope="scope">
                <el-button
                  size="mini"
                  :type="isRowSelectedInDialog(scope.row) ? 'primary' : 'default'"
                  @click="selectItemRowInDialog(scope.row)"
                  :plain="!isRowSelectedInDialog(scope.row)"
                >{{ isRowSelectedInDialog(scope.row) ? '已选' : '选择' }}
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <pagination v-if="paginationInDialog && paginationInDialog.total_count > 0" :pagination="paginationInDialog"
                      @pageChange="handlePageChangeInDialog" v-model="currentPageInDialog"
          ></pagination>
          <el-empty v-else-if="!isLoadingInDialog && !itemListInDialog.length" description="暂无数据"></el-empty>
        </el-tab-pane>

        <!-- 自定义页面 (DIY) -->
        <el-tab-pane label="自定义页面" name="diy">
          <el-table size="mini" :data="itemListInDialog" style="width: 100%">
            <el-table-column prop="id" label="ID" width="80"></el-table-column>
            <el-table-column prop="name" label="名称"></el-table-column>
            <el-table-column label="页面链接">
              <template slot-scope="scope">{{ getItemUrlInDialog(scope.row) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template slot-scope="scope">
                <el-button size="mini" :type="isRowSelectedInDialog(scope.row) ? 'primary' : 'default'"
                           @click="selectItemRowInDialog(scope.row)" :plain="!isRowSelectedInDialog(scope.row)"
                >
                  {{ isRowSelectedInDialog(scope.row) ? '已选' : '选择' }}
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <pagination v-if="paginationInDialog && paginationInDialog.total_count > 0" :pagination="paginationInDialog"
                      @pageChange="handlePageChangeInDialog" v-model="currentPageInDialog"
          ></pagination>
          <el-empty v-else-if="!isLoadingInDialog && !itemListInDialog.length" description="暂无数据"></el-empty>
        </el-tab-pane>

        <!-- 分类 (cat) -->
        <el-tab-pane label="分类" name="cat">
          <el-row style="margin-bottom: 15px;">
            <el-col :span="16">
              <div class="flex-row">
                <el-input v-model="searchKeywordInDialog" placeholder="输入分类名称进行搜索" clearable
                          @clear="searchItemsInDialog" @keyup.enter.native="searchItemsInDialog"
                ></el-input>
                <el-button @click="searchItemsInDialog" type="primary" style="margin: 0 10px;">搜索</el-button>
              </div>
            </el-col>
          </el-row>
          <el-table size="mini" :data="itemListInDialog" style="width: 100%">
            <el-table-column prop="id" label="ID" width="80"></el-table-column>
            <el-table-column prop="name" label="名称"></el-table-column>
            <el-table-column label="页面链接">
              <template slot-scope="scope">{{ getItemUrlInDialog(scope.row) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template slot-scope="scope">
                <el-button size="mini" :type="isRowSelectedInDialog(scope.row) ? 'primary' : 'default'"
                           @click="selectItemRowInDialog(scope.row)" :plain="!isRowSelectedInDialog(scope.row)"
                >
                  {{ isRowSelectedInDialog(scope.row) ? '已选' : '选择' }}
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <pagination v-if="paginationInDialog && paginationInDialog.total_count > 0" :pagination="paginationInDialog"
                      @pageChange="handlePageChangeInDialog" v-model="currentPageInDialog"
          ></pagination>
          <el-empty v-else-if="!isLoadingInDialog && !itemListInDialog.length" description="暂无数据"></el-empty>
        </el-tab-pane>

        <!-- 插件 (plugin) -->
        <el-tab-pane label="插件" name="plugin">
          <el-input v-model="groupKeyword" placeholder="搜索链接名称、插件名或路径" clearable size="small"
                    prefix-icon="el-icon-search" style="margin-bottom: 12px;"
          >
            <template slot="append">{{ groupLinkCount }} 条</template>
          </el-input>
          <el-scrollbar style="height: 410px;">
            <div v-if="activeTabName === 'plugin' && filteredLinkGroups.length > 0">
              <div style="margin-bottom: 20px;" v-for="(group, groupIndex) in filteredLinkGroups"
                   :key="`plugingroup-${groupIndex}`"
              >
                <div class="link-group-title">{{ group.name }}</div>
                <div class="links">
                  <div class="flex-y-center flex-x-center link-item" :class="{ active: dialogSelectedUrl === link.url }"
                       v-for="(link, linkIndex) in group.links" :key="`pluginlink-${groupIndex}-${linkIndex}`"
                       @click="selectSimpleLinkInDialog(link)"
                       :title="link.url"
                  >
                    <span>{{ link.name }}</span>
                  </div>
                </div>
              </div>
            </div>
            <el-empty v-else-if="activeTabName === 'plugin' && !isLoadingInDialog"
                      :description="groupKeyword ? `没有匹配「${groupKeyword}」的链接` : '暂无数据'"
            ></el-empty>
          </el-scrollbar>
        </el-tab-pane>

        <!-- 外部链接 (outside) -->
        <el-tab-pane label="外部链接" name="outside">
          <div v-if="isOutsideLinkManualInput" style="margin-bottom: 15px;">
            <el-input v-model="manualOutsideLinkUrlInDialog"
                      placeholder="请输入外部链接地址 (例：https://www.example.com)"
            >
              <template slot="prepend">链接地址</template>
            </el-input>
          </div>
          <div v-else>
            <el-row style="margin-bottom: 15px;">
              <el-col :span="16">
                <div class="flex-row">
                  <el-input v-model="searchKeywordInDialog" placeholder="输入链接名称搜索" clearable
                            @clear="searchItemsInDialog" @keyup.enter.native="searchItemsInDialog"
                  ></el-input>
                  <el-button @click="searchItemsInDialog" type="primary" style="margin: 0 10px;">搜索</el-button>
                </div>
              </el-col>
            </el-row>
            <el-table size="mini" :data="itemListInDialog" style="width: 100%">
              <el-table-column prop="id" label="ID" width="80"></el-table-column>
              <el-table-column prop="name" label="名称"></el-table-column>
              <el-table-column label="链接地址">
                <template slot-scope="scope">{{ getItemUrlInDialog(scope.row) }}</template>
              </el-table-column>
              <el-table-column label="操作" width="100">
                <template slot-scope="scope">
                  <el-button size="mini" :type="isRowSelectedInDialog(scope.row) ? 'primary' : 'default'"
                             @click="selectItemRowInDialog(scope.row)" :plain="!isRowSelectedInDialog(scope.row)"
                  >
                    {{ isRowSelectedInDialog(scope.row) ? '已选' : '选择' }}
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
            <pagination v-if="paginationInDialog && paginationInDialog.total_count > 0" :pagination="paginationInDialog"
                        @pageChange="handlePageChangeInDialog" v-model="currentPageInDialog"
            ></pagination>
            <el-empty v-else-if="!isLoadingInDialog && !itemListInDialog.length" description="暂无数据"></el-empty>
          </div>
        </el-tab-pane>
      </el-tabs>
      <span slot="footer" class="dialog-footer">
        <el-button @click="handleDialogCancel">取 消</el-button>
        <el-button type="primary" @click="handleDialogConfirm" :disabled="!canDialogConfirm">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
// 假设 Pagination 组件已全局注册或在此引入
// import Pagination from '@/components/common/Pagination.vue';

export default {
  name: 'InputWithLinkPicker',
  // components: { Pagination },
  props: {
    value: { type: String, default: '' },
    placeholder: { type: String, default: '点击选择或输入链接' },
    size: { type: String, default: 'mini' },
    disabled: { type: Boolean, default: false },
    isOutsideLinkManualInput: { type: Boolean, default: false }
  },
  data() {
    return {
      inputValue: this.value,
      dialogVisible: false,
      activeTabName: 'mall',
      isLoadingInDialog: false,
      linkGroups: [],
      // 商城页面 / 插件两个 tab 是整批下发、前端渲染的，用它做客户端过滤。
      // 与 searchKeywordInDialog(其余 tab 的服务端搜索)分开，避免切 tab 时互相干扰。
      groupKeyword: '',
      itemListInDialog: [],
      searchKeywordInDialog: '',
      currentPageInDialog: 1,
      paginationInDialog: null,
      dialogSelectedUrl: null,
      dialogSelectedName: null,
      dialogSelectedItemId: null,
      manualOutsideLinkUrlInDialog: ''
    }
  },
  watch: {
    value(newVal) {
      if (newVal !== this.inputValue) {
        this.inputValue = newVal
      }
    }
  },
  computed: {
    // 名称、分组名、URL 三者任一命中即保留；分组名命中时整组保留。
    // 插件 tab 动辄上百条链接（withdraw 一家就 16 条），不给搜索只能靠滚。
    filteredLinkGroups() {
      const kw = String(this.groupKeyword || '').trim().toLowerCase()
      const groups = this.linkGroups || []
      if (!kw) return groups
      const hit = (s) => String(s || '').toLowerCase().indexOf(kw) !== -1
      return groups.reduce((acc, g) => {
        if (hit(g.name)) {
          acc.push(g)
          return acc
        }
        const links = (g.links || []).filter(l => hit(l.name) || hit(l.url))
        if (links.length) acc.push({ ...g, links })
        return acc
      }, [])
    },
    groupLinkCount() {
      return this.filteredLinkGroups.reduce((n, g) => n + ((g.links || []).length), 0)
    },
    canDialogConfirm() {
      if (this.activeTabName === 'outside' && this.isOutsideLinkManualInput) {
        return !!this.manualOutsideLinkUrlInDialog
      }
      return !!this.dialogSelectedUrl
    }
  },
  methods: {
    handleInput(value) {
      this.inputValue = value
      this.$emit('input', value)
    },
    openLinkDialog() {
      if (this.disabled) return
      // 关掉再打开时搜索词要清掉：数据可能已缓存不重新拉，残留的关键词会让人以为链接丢了
      this.groupKeyword = ''
      this.dialogSelectedUrl = this.inputValue // 用当前输入框的值初始化弹窗选择
      if (this.isOutsideLinkManualInput && this.activeTabName === 'outside') {
        this.manualOutsideLinkUrlInDialog = this.inputValue || ''
      }
      this.dialogVisible = true
      // 优化：只有当数据为空或者Tab切换时才加载，或者强制刷新
      // 这里简化为每次打开都判断是否需要加载，可以根据需求调整
      const dataNotLoaded = (this.activeTabName === 'mall' || this.activeTabName === 'plugin') ?
        !this.linkGroups.length : !this.itemListInDialog.length
      if (dataNotLoaded) {
        this.fetchDataForDialogTab()
      } else {
        // 数据已存在，尝试高亮
        this.$nextTick(() => {
          this.tryToSetInitialDialogSelection()
        })
      }
    },
    tryToSetInitialDialogSelection() {
      if (!this.dialogSelectedUrl) {
        this.dialogSelectedItemId = null
        this.dialogSelectedName = null
        return
      }
      const currentList = (this.activeTabName === 'mall' || this.activeTabName === 'plugin') ?
        (this.linkGroups || []).flatMap(g => g.links) : // 添加空数组保护
        (this.itemListInDialog || []) // 添加空数组保护

      const foundItem = currentList.find(item => this.getItemUrlInDialog(item) === this.dialogSelectedUrl)

      if (foundItem) {
        this.dialogSelectedItemId = foundItem.id || null
        this.dialogSelectedName = foundItem.name
      } else {
        this.dialogSelectedItemId = null
        // 如果是手动输入的外部链接，且当前tab是outside，则name可以保留，否则清空
        if (!(this.activeTabName === 'outside' && this.isOutsideLinkManualInput)) {
          this.dialogSelectedName = null
        }
      }
    },
    resetDialogState() { // isSwitchingTab = false
      this.itemListInDialog = []
      this.linkGroups = []
      // 搜索和分页重置，但保留当前选中的dialogSelectedUrl等，除非是切换tab
      //   if (!isSwitchingTab) {
      this.groupKeyword = ''
      this.searchKeywordInDialog = ''
      this.currentPageInDialog = 1
      this.paginationInDialog = null
      //   }
    },
    handleTabClickInDialog() { // tab参数可以省略，因为this.activeTabName已更新
      this.resetDialogState()
      this.dialogSelectedUrl = this.inputValue // 切换tab时，弹窗内选择重置为当前输入框的值
      if (this.isOutsideLinkManualInput && this.activeTabName === 'outside') {
        this.manualOutsideLinkUrlInDialog = this.inputValue || ''
      }
      this.fetchDataForDialogTab()
    },
    async fetchDataForDialogTab() {
      this.isLoadingInDialog = true
      let requestConfig = { url: '', params: {}, method: 'get' } // 假设默认为GET
      let isGroupedLink = false

      const baseParams = {
        page: this.currentPageInDialog,
        keyword: this.searchKeywordInDialog
      }

      switch (this.activeTabName) {
        case 'mall':
        case 'plugin':
          requestConfig.url = '/mall/link/list' // 你的接口地址
          requestConfig.params = { type: this.activeTabName }
          isGroupedLink = true
          break
        case 'goods':
          requestConfig.url = '/mall/goods/list' // 你的接口地址
          requestConfig.params = { ...baseParams, status: 1 }
          break
        case 'diy':
          requestConfig.url = '/mall/mall/diy' // 你的接口地址
          requestConfig.params = { page: this.currentPageInDialog } // DIY可能没有keyword
          break
        case 'cat':
          requestConfig.url = '/mall/goods/cat' // 你的接口地址
          requestConfig.params = { ...baseParams }
          break
        case 'outside':
          if (this.isOutsideLinkManualInput) {
            this.isLoadingInDialog = false
            // 手动输入模式，尝试根据dialogSelectedUrl(即inputValue)设置名称
            this.$nextTick(() => this.tryToSetInitialDialogSelection())
            return
          }
          requestConfig.url = '/mall/mall/outside-link-list' // 你的接口地址
          requestConfig.params = { ...baseParams }
          break
        default:
          this.isLoadingInDialog = false
          return
      }

      try {
        // **实际请求**
        // 确保你的 this.$request 能正确处理 params (GET请求通常在url后，POST在body)
        // 如果 this.$request 自动区分 GET/POST，或者你需要显式指定:
        // const res = await this.$request({ url: requestConfig.url, method: requestConfig.method, data: requestConfig.params }); // for POST
        const res = await this.$request({ url: requestConfig.url, data: requestConfig.params }) // for GET

        if (res && res.code === 0) { // 确保res存在
          const data = res.data || {} // 防止res.data为null
          if (isGroupedLink) {
            this.linkGroups = data.list || []
          } else {
            this.itemListInDialog = data.list || []
            this.paginationInDialog = data.pagination || null
          }
          // 数据加载成功后，尝试根据 dialogSelectedUrl (即打开弹窗时的inputValue) 设置高亮
          this.$nextTick(() => {
            this.tryToSetInitialDialogSelection()
          })
        } else {
          this.$message.error(res.msg || `加载${this.activeTabName}数据失败`)
          this.linkGroups = []
          this.itemListInDialog = []
          this.paginationInDialog = null
        }
      } catch (error) {
        console.error(`Error fetching data for tab ${this.activeTabName}:`, error)
        this.$message.error('网络请求失败，请稍后重试')
        this.linkGroups = []
        this.itemListInDialog = []
        this.paginationInDialog = null
      } finally {
        this.isLoadingInDialog = false
      }
    },
    searchItemsInDialog() {
      this.currentPageInDialog = 1 // 搜索时重置到第一页
      this.paginationInDialog = null // 清空旧的分页信息
      this.fetchDataForDialogTab()
    },
    handlePageChangeInDialog(newPage) {
      this.currentPageInDialog = newPage
      this.fetchDataForDialogTab()
    },
    getItemUrlInDialog(item) {
      if (!item) return ''
      switch (this.activeTabName) {
        case 'goods':
          return `/others/goods/goods?id=${item.id}`
        case 'diy':
          return `/pages/diy/diy?id=${item.id}`
        case 'cat':
          return `/others/goods-list/goods-list?cat_id=${item.id}`
        case 'outside':
          return `/others/outside/outside?id=${item.id}`
        case 'mall':
        case 'plugin':
          return item.url || ''
        default:
          return ''
      }
    },
    selectSimpleLinkInDialog(link) {
      const linkUrl = link.url || ''
      if (this.dialogSelectedUrl === linkUrl) {
        this.dialogSelectedUrl = null
        this.dialogSelectedName = null
      } else {
        this.dialogSelectedUrl = linkUrl
        this.dialogSelectedName = link.name
        this.dialogSelectedItemId = null
      }
    },
    selectItemRowInDialog(row) {
      const itemUrl = this.getItemUrlInDialog(row)
      if (this.dialogSelectedUrl === itemUrl && this.dialogSelectedItemId === row.id) {
        this.dialogSelectedUrl = null
        this.dialogSelectedName = null
        this.dialogSelectedItemId = null
      } else {
        this.dialogSelectedUrl = itemUrl
        this.dialogSelectedName = row.name
        this.dialogSelectedItemId = row.id
      }
    },
    isRowSelectedInDialog(row) {
      const itemUrl = this.getItemUrlInDialog(row)
      if (this.activeTabName === 'mall' || this.activeTabName === 'plugin') {
        return this.dialogSelectedUrl === itemUrl && itemUrl !== '' // 确保空URL不被意外选中
      }
      return this.dialogSelectedItemId === row.id && this.dialogSelectedUrl === itemUrl && itemUrl !== ''
    },
    handleDialogConfirm() {
      let finalUrl = null
      if (this.activeTabName === 'outside' && this.isOutsideLinkManualInput) {
        finalUrl = this.manualOutsideLinkUrlInDialog || null
      } else {
        finalUrl = this.dialogSelectedUrl
      }

      if (finalUrl !== null) {
        this.inputValue = finalUrl
        this.$emit('input', finalUrl)
      } else if (this.inputValue !== '') { // 如果用户清空了选择，且之前输入框有值，也同步清空
        this.inputValue = ''
        this.$emit('input', '')
      }
      this.dialogVisible = false
    },
    handleDialogCancel() {
      this.dialogVisible = false
    }
  }
}
</script>

<style lang="scss" scoped>
/* 样式与之前保持一致 */
.input-with-link-picker {
}

.image-slot {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  color: #909399;
  font-size: 18px;
}

.flex-row {
  display: flex;
  flex-direction: row;
  align-items: center;
}

.flex-y-center {
  display: flex;
  align-items: center;
}

.flex-x-center {
  display: flex;
  justify-content: center;
}

.link-group-title {
  font-weight: bold;
  color: #333;
  height: 40px;
  line-height: 40px;
  font-size: 14px;
}

.active {
  background-color: var(--admin-primary) !important;
  color: var(--admin-on-primary) !important;
  border-color: var(--admin-primary) !important;
}

.el-scrollbar__wrap {
  overflow-x: hidden;
}

/* 用 grid 自适应列数：容器越宽塞得越多，不用手算百分比。
   原来是 flex + width:calc(25% - 15px) + margin-right:20px，
   百分比和固定外边距相加超过 100%，窄一点就掉行、每行实际只有 3 个。 */
.links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

.link-item {
  box-sizing: border-box;
  border: 1px solid #F3F3F3;
  height: 40px;
  line-height: 38px;
  text-align: center;
  font-size: 13px;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease-in-out;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 0 5px;

  &:hover {
    border-color: var(--admin-accent-text);
    color: var(--admin-accent-text);
  }
}

.el-dialog__body {
  padding: 15px 20px;
}

.el-tabs__nav-wrap::after {
  background-color: #e4e7ed !important;
}

/* 确保弹窗在最上层 */
::v-deep .el-dialog__wrapper {
  z-index: 2001 !important;
}

::v-deep .el-dialog {
  z-index: 2002 !important;
}
</style>
