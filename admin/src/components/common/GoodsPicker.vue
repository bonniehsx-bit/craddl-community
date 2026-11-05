<template>
  <div style="text-align: start;">
    <el-dialog append-to-body :title="multiple ? '选择商品' : '选择商品（单选）'" :visible.sync="dialogVisible" width="min(960px, 92vw)" :before-close="handleClose">
      <div>
        <el-row>
          <el-col :span="12">
            <div class="flex-row">
              <el-input v-model="keyword" placeholder="输入商品名称进行搜索"></el-input>
              <el-button @click="getList" type="primary" style="margin: 0 10px;">搜索</el-button>
            </div>
          </el-col>
        </el-row>
        <el-scrollbar style="height: 450px;">
          <el-table ref="goodsTable" :data="list" style="width: 100%;" v-loading='is_loading'>
            <el-table-column label="商品">
              <template slot-scope="scope">
                <div class="flex-row">
                  <image-view :src="scope.row.cover_pic"></image-view>
                  <div style="padding:0 10px;">
                    {{ scope.row.name }}
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="price" label="价格" width="120">
            </el-table-column>
            <el-table-column prop="stock" label="库存" width="120">
            </el-table-column>
            <el-table-column label="操作" width="100" align="center">
              <template slot-scope="scope">
                <el-button size="mini" v-if="scope.row.selected == 1" type="primary"
                  @click="selectRow(scope.row, scope.$index)">
                  已选
                </el-button>
                <el-button size="mini" v-if="scope.row.selected !== 1" @click="selectRow(scope.row, scope.$index)"
                  plain>
                  选择
                </el-button>
              </template>
            </el-table-column>
          </el-table>

        </el-scrollbar>
        <pagination :pagination="pagination" :page-sizes="[10, 20, 50, 100]" @pageChange="getList"
          @sizeChange="onSizeChange" v-model="page"></pagination>
      </div>

      <span slot="footer" class="dialog-footer">
        <span class="picked-tip" v-if="selected_rows.length">已选 {{ selected_rows.length }} 件<template v-if="maxCount"> / 最多 {{ maxCount }} 件</template></span>
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirm">确 定</el-button>
      </span>
    </el-dialog>
    <div style="line-height: normal;" :style="'display:' + (display ? display : 'inline-block')"
      @click="open">
      <slot />
    </div>
  </div>
</template>

<script>
const PICKER_LIMIT_KEY = 'goods_picker_limit'

function readPickerLimit() {
  try {
    const v = Number(localStorage.getItem(PICKER_LIMIT_KEY))
    return [10, 20, 50, 100].includes(v) ? v : 10
  } catch (e) {
    return 10
  }
}

export default {
  name: 'GoodsPicker',
  props: {
    status: {
      type: Number,
      default: 1
    },
    // 商品类型过滤：缺省(undefined)→仅 normal；'all'→所有类型；具体值→按该 type 过滤
    type: {
      type: String,
      default: undefined
    },
    // 是否多选:砍价 / 秒杀这类一次只配一个商品的传 false,点别的商品直接替换
    multiple: {
      type: Boolean,
      default: true
    },
    // 最多选几件(0 = 不限);历史上有页面传 limit,两个都认
    max: {
      type: Number,
      default: 0
    },
    limit: {
      type: Number,
      default: 0
    },
    // v-model:打开时带入调用方已有的商品(在原基础上增删);不用 v-model 的每次打开都从空开始
    value: {
      type: Array,
      default: null
    }
  },
  data() {
    return {
      list: [],
      page: 1,
      is_loading: false,
      pagination: null,
      selected_rows: [],
      limit_size: readPickerLimit(),
      dialogVisible: false,
      display: '',
      keyword: ''
    }
  },
  computed: {
    maxCount() {
      return this.multiple ? (this.max || this.limit || 0) : 1
    }
  },
  created() {
    this.getList();
  },
  methods: {
    // 每次打开都重置选择:原来上次选过(甚至点了取消)的会一直留着,下次点确定又被带回去
    open() {
      this.selected_rows = Array.isArray(this.value) ? this.value.slice() : []
      this.markSelected()
      this.dialogVisible = true
    },
    isPicked(row) {
      return this.selected_rows.some(item => item.id == row.id)
    },
    // 按 selected_rows 刷新当前页每行的「已选」(翻页 / 重开后状态才对得上)
    markSelected() {
      this.list = (this.list || []).map(item => Object.assign({}, item, { selected: this.isPicked(item) ? 1 : 0 }))
    },
    getList() {
      this.is_loading = true;
      const data = {
        page: this.page,
        limit: this.limit_size,
        keyword: this.keyword,
        status: this.status
      };
      if (this.type) data.type = this.type;
      this.$request({
        url: '/mall/goods/list',
        data
      }).then(res => {
        this.is_loading = false;
        this.list = res.data.list;
        this.markSelected();
        this.pagination = res.data.pagination
      })
    },
    // 每页条数:回到第 1 页重新取;选过的条数记在本机,下次打开(任何页面的选择器)沿用
    onSizeChange(size) {
      this.limit_size = size
      this.page = 1
      try { localStorage.setItem(PICKER_LIMIT_KEY, String(size)) } catch (e) {}
      this.getList()
    },
    handleClose() {
      this.dialogVisible = false;
    },
    selectRow(row) {
      if (this.isPicked(row)) {
        // 取消选择:原来写成 filter(id == row.id),反而把其它已选的全清了、只剩要取消的这个
        this.selected_rows = this.selected_rows.filter(item => item.id != row.id)
      } else if (!this.multiple) {
        this.selected_rows = [row]
      } else {
        if (this.maxCount && this.selected_rows.length >= this.maxCount) {
          this.$message.warning('最多只能选 ' + this.maxCount + ' 件')
          return
        }
        this.selected_rows.push(row)
      }
      this.markSelected()
    },
    confirm() {
      const rows = this.selected_rows.slice()
      this.$emit('selected', rows)
      this.$emit('input', rows)
      this.handleClose();
    }
  }
}
</script>

<style scoped>
.picked-tip {
  margin-right: 12px;
  font-size: 12px;
  color: #909399;
}
</style>
