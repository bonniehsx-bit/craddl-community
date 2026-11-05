<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>优惠券管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="couponEdit(null)">添加优惠券</el-button>
      </div>
      <div>
        <el-table size="mini" :data="list" style="width: 100%" v-loading="is_loading" border>
          <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>

          <el-table-column prop="name" label="优惠券名称" min-width="150"></el-table-column>

          <el-table-column label="类型" width="100" align="center">
            <template slot-scope="scope">
              <!-- 商户端只有满减券 -->
              <el-tag size="small">满减券</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="优惠内容" min-width="180">
            <template slot-scope="scope">
              <div>
                <span>满 </span>
                <span class="price-text">{{ Number(scope.row.min_price) > 0 ? scope.row.min_price : '0' }}</span>
                <span> 元, 减 </span>
                <span class="price-text">{{ scope.row.sub_price }}</span>
                <span> 元</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="有效期" width="220">
            <template slot-scope="scope">
              <!-- 商户端只能是 time_type == 0 -->
              <div>
                领取后 {{ scope.row.day || scope.row.expire_day }} 天内有效
              </div>
            </template>
          </el-table-column>

          <el-table-column label="发放/剩余" width="120" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.total_count == -1">不限制</div>
              <div v-else>
                <div>总: {{ scope.row.total_count }}</div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="每人限领" width="100" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.limit_per_user == 0">不限制</span>
              <span v-else>{{ scope.row.limit_per_user }} 张</span>
            </template>
          </el-table-column>

          <!-- 隐藏 admin 中商户端未实现的 is_transferable 判断 -->

          <el-table-column label="使用范围" width="100" align="center">
            <template slot-scope="scope">
              <span v-if="!scope.row.appoint_type || scope.row.appoint_type === 'all' || scope.row.appoint_type === 3">全场通用</span>
              <span v-else>指定商品</span>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="80" align="center">
            <template slot-scope="scope">
              <el-switch
                v-model="scope.row.is_enabled"
                :active-value="1"
                :inactive-value="0"
                disabled
              >
              </el-switch>
            </template>
          </el-table-column>

          <el-table-column label="操作" fixed="right" width="180" align="center">
            <template slot-scope="scope">
              <el-button type="text" size="mini" icon="el-icon-edit" @click="couponEdit(scope.row.id)">编辑</el-button>
              <el-button type="text" size="small" @click="sendCoupon(scope.row)">发放</el-button>
              <el-popconfirm
                confirm-button-text="确定"
                cancel-button-text="取消"
                icon="el-icon-info"
                icon-color="red"
                title="确定删除该优惠券吗？"
                @confirm="couponDelete(scope.row.id)"
              >
                <el-button type="text" size="small" slot="reference" style="color: #F56C6C; margin-left: 10px;">删除
                </el-button>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <div style="text-align: right; margin-top: 20px;">
          <el-pagination
            v-if="total > 0"
            background
            :current-page.sync="search.page"
            :page-size="search.limit"
            layout="total, prev, pager, next, jumper"
            :total="total"
            @current-change="pageChange"
          ></el-pagination>
        </div>
      </div>
    </el-card>

    <coupon-edit :visible.sync="editDrawerVisible" :coupon-id="currentEditCouponId" @success="getList" />

    <!-- 发放优惠券弹窗组件 -->
    <el-drawer title="发放优惠券给客户" :visible.sync="sendDialogVisible" size="45%" destroy-on-close>
        <coupon-send :coupon-id="currentCouponId" @close="sendDialogVisible = false" @success="getList" />
    </el-drawer>
  </div>
</template>

<script>
import CouponEdit from './edit.vue';
import CouponSend from './send.vue';

export default {
  name: 'MchCouponList',
  components: { CouponEdit, CouponSend },
  data() {
    return {
      is_loading: false,
      list: [],
      search: {
        page: 1,
        limit: 20,
        keyword: ''
      },
      total: 0,
      
      // 发送相关
      sendDialogVisible: false,
      currentCouponId: 0,
      
      // 编辑相关
      editDrawerVisible: false,
      currentEditCouponId: null,
    }
  },
  created() {
    this.getList()
  },
  methods: {
    pageChange(page) {
      this.search.page = page
      this.getList()
    },
    couponEdit(id) {
      this.currentEditCouponId = id;
      this.editDrawerVisible = true;
    },
    sendCoupon(row) {
      this.currentCouponId = row.id;
      this.sendDialogVisible = true;
    },
    getList() {
      this.is_loading = true;
      this.$request({
        url: '/plugin/mch/admin/coupon/index',
        method: 'get',
        data: this.search
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          this.list = res.data.list || [];
          if (res.data.pagination) {
            this.total = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
          }
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.is_loading = false;
      });
    },
    couponDelete(id) {
      this.is_loading = true;
      this.$request({
        url: '/plugin/mch/admin/coupon/delete',
        method: 'post',
        data: { id: id }
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          this.$message.success('删除成功');
          
          // 如果想立刻检测当前页是否被删空，其实再调用 getList 也是最稳的
          let idx = this.list.findIndex(item => item.id == id);
          if (idx !== -1) {
             this.list.splice(idx, 1);
          }

          if (this.list.length === 0 && this.search.page > 1) {
            this.search.page--;
            this.getList();
          } else {
            this.getList();
          }
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.is_loading = false;
      });
    }
  }
}
</script>

<style scoped>
.price-text {
  color: #F56C6C;
  font-weight: bold;
}
</style>
