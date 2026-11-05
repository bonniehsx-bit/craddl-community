<template>
  <div class="article-list-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">基础设置</div>

        <el-form-item label="板块标题">
          <el-input v-model="form.title" placeholder="请输入标题"></el-input>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">组件外观</div>

        <el-form-item label="列表样式">
          <el-radio-group v-model="form.style" size="mini">
            <el-radio-button label="list">左文右图</el-radio-button>
            <el-radio-button label="big">大图</el-radio-button>
            <el-radio-button label="text">纯文字</el-radio-button>
          </el-radio-group>
          <div class="style-tip">
            <span v-if="form.style === 'big'">封面大图在上、标题在下，一篇一卡片，适合主推内容。</span>
            <span v-else-if="form.style === 'text'">不显示封面，只排标题与摘要，适合公告类资讯。</span>
            <span v-else>左侧标题摘要、右侧小封面。没有封面的文章会自动占满整行。</span>
          </div>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-switch v-model="form.is_padding" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="圆角设置">
          <el-switch v-model="form.is_circle" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="背景颜色">
          <el-color-picker v-model="form.background" show-alpha></el-color-picker>
        </el-form-item>

      </div>

      <div class="config-section">
        <div class="section-title">
          <span>文章列表 ({{ form.list.length }})</span>
          <span class="tip">拖拽可排序</span>
        </div>

        <!-- 已选文章列表 -->
        <draggable v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(item, index) in form.list" :key="index" class="article-edit-item">
            <div class="item-info">
              <div class="drag-handle"><i class="el-icon-s-operation"></i></div>
              <img :src="item.cover_pic" class="article-pic">
              <div class="article-title">{{ item.title }}</div>
            </div>
            <div class="delete-btn" @click="handleDelete(index)">
              <i class="el-icon-delete"></i>
            </div>
          </div>
        </draggable>

        <!-- 添加文章按钮 -->
        <div style="margin-top: 10px; text-align: center">
          <el-button type="primary" plain icon="el-icon-plus" style="width: 100%" @click="showPicker">
            添加文章 ({{ form.list.length }}/50)
          </el-button>
        </div>
      </div>

    </el-form>

    <!-- 文章选择弹窗 -->
    <el-dialog
      title="选择文章"
      :visible.sync="dialogVisible"
      width="800px"
      append-to-body
      :close-on-click-modal="false"
    >
      <div v-loading="loading">
        <el-table
          :data="articleList"
          border stripe size="small"
          style="width: 100%"
          @selection-change="handleSelectionChange"
          ref="table"
        >
          <el-table-column type="selection" width="55" align="center"></el-table-column>
          <el-table-column prop="id" label="ID" width="60" align="center"></el-table-column>
          <el-table-column label="封面" width="80" align="center">
            <template slot-scope="scope">
              <el-image :src="scope.row.cover_pic" style="width: 40px; height: 40px" fit="cover"></el-image>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" show-overflow-tooltip></el-table-column>
          <el-table-column prop="sub_title" label="摘要" show-overflow-tooltip></el-table-column>
        </el-table>

        <div class="pagination-box">
          <el-pagination
            background
            layout="prev, pager, next"
            :total="pagination.total_count"
            :page-size="pagination.pageSize || 20"
            :current-page.sync="page"
            @current-change="getList"
          >
          </el-pagination>
        </div>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="confirmSelect" size="small">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import draggable from 'vuedraggable'

export default {
  name: 'ArticleListConfig',
  components: { draggable },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        title: '最新资讯',
        style: 'list', // list=左文右图 / big=大图 / text=纯文字
        is_padding: 0,
        is_circle: 0,
        background: '#ffffff',
        list: []
      },
      // 弹窗相关
      dialogVisible: false,
      loading: false,
      articleList: [], // 弹窗列表数据
      page: 1,
      pagination: { total_count: 0 },
      multipleSelection: [] // 弹窗中选中的行
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
          return;
        }
        // 存量页面存的 page_data 里没有 style,补默认值，否则单选框是空选中态、
        // 运营不点一下就存不进去，C 端也只能靠兜底判断
        if (!val.style) {
          this.$emit('input', Object.assign({}, val, { style: 'list' }));
        }
      },
      immediate: true
    }
  },
  methods: {
    handleDelete(index) {
      this.form.list.splice(index, 1);
    },
    showPicker() {
      this.dialogVisible = true;
      this.page = 1;
      this.getList();
    },
    getList() {
      this.loading = true;
      this.$request({
        url: "/plugin/article/mall/article/list",
        data: {
          page: this.page
        }
      }).then(res => {
        this.loading = false;
        if (res.code == 0) {
          this.articleList = res.data.list;
          this.pagination = res.data.pagination || { total_count: 0 };
        }
      }).catch(() => {
        this.loading = false;
      });
    },
    handleSelectionChange(val) {
      this.multipleSelection = val;
    },
    confirmSelect() {
      if (this.multipleSelection.length > 0) {
        const newList = [...this.form.list];

        this.multipleSelection.forEach(item => {
          // 简单去重
          const exists = newList.find(i => i.id === item.id);
          if (!exists) {
            // 字段名以 Article 表为准:摘要是 sub_title、浏览量是 views。
            // 原先写的 info / click_count 表里根本没有,导致摘要从不显示、浏览量恒为 0。
            newList.push({
              id: item.id,
              title: item.title,
              sub_title: item.sub_title || '',
              cover_pic: item.cover_pic,
              views: Number(item.views) || 0
            });
          }
        });

        this.form.list = newList;
      }
      this.dialogVisible = false;
      this.$refs.table.clearSelection(); // 清空选中状态
    }
  }
}
</script>

<style lang="scss" scoped>
.article-list-config {
  .style-tip {
    font-size: 12px;
    color: #999;
    line-height: 1.5;
    margin-top: 6px;
  }

  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
      display: flex; justify-content: space-between;
      .tip { font-weight: normal; color: #999; font-size: 12px; }
    }
  }
}

.article-edit-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8f9fa;
  border: 1px solid #eee;
  padding: 8px;
  margin-bottom: 8px;
  border-radius: 4px;

  .item-info {
    display: flex;
    align-items: center;
    flex: 1;
    overflow: hidden;

    .drag-handle { cursor: move; color: #909399; margin-right: 8px; }
    .article-pic { width: 36px; height: 36px; object-fit: cover; border-radius: 2px; margin-right: 8px; flex-shrink: 0; }
    .article-title { font-size: 12px; color: #333; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 10px; }
  }

  .delete-btn {
    cursor: pointer; color: #F56C6C; padding: 4px;
    &:hover { background: #fef0f0; border-radius: 4px; }
  }
}

.pagination-box {
  text-align: right;
  margin-top: 20px;
}
</style>
