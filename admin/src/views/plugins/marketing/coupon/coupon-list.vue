<template>
  <div>
    <el-card class="box-card" shadow="never">
      <div slot="header" class="clearfix">
        <span>优惠券管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="couponEdit(null)">添加优惠券</el-button>
      </div>
      <el-form :inline="true" size="small" class="search-bar" @submit.native.prevent>
        <el-form-item>
          <el-input v-model="search.keyword" :placeholder="$noun('coupon') + '名称 / ID'" clearable style="width: 200px"
            @keyup.enter.native="toSearch" @clear="toSearch"></el-input>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.type" placeholder="类型" clearable style="width: 110px" @change="toSearch">
            <el-option label="满减券" :value="0"></el-option>
            <el-option label="折扣券" :value="1"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.owner" placeholder="所属" clearable style="width: 110px" @change="toSearch">
            <el-option label="平台自营" value="platform"></el-option>
            <el-option label="商家券" value="mch"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.is_enabled" placeholder="启用状态" clearable style="width: 110px" @change="toSearch">
            <el-option label="已启用" :value="1"></el-option>
            <el-option label="已停用" :value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.can_be_claimed" placeholder="领券中心" clearable style="width: 130px" @change="toSearch">
            <el-option label="可在领券中心领" :value="1"></el-option>
            <el-option label="不可领" :value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.validity" placeholder="有效期" clearable style="width: 110px" @change="toSearch">
            <el-option label="未过期" value="valid"></el-option>
            <el-option label="已过期" value="expired"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="toSearch">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
      <div>
        <el-table size="mini" :data="list" style="width: 100%" v-loading="is_loading" border>
          <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>

          <el-table-column prop="name" label="优惠券名称" min-width="150"></el-table-column>

          <el-table-column label="类型" width="100" align="center">
            <template slot-scope="scope">
              <el-tag size="small" v-if="scope.row.type == 0">满减券</el-tag>
              <el-tag size="small" type="warning" v-if="scope.row.type == 1">折扣券</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="优惠内容" min-width="180">
            <template slot-scope="scope">
              <div v-if="scope.row.type == 0">
                <span>满 </span>
                <span class="price-text">{{ Number(scope.row.min_price) > 0 ? scope.row.min_price : '0' }}</span>
                <span> 元, 减 </span>
                <span class="price-text">{{ scope.row.sub_price }}</span>
                <span> 元</span>
              </div>
              <div v-if="scope.row.type == 1">
                <span>满 </span>
                <span class="price-text">{{ Number(scope.row.min_price) > 0 ? scope.row.min_price : '0' }}</span>
                <span> 元, 享 </span>
                <span class="price-text">{{ scope.row.discount }}</span>
                <span> 折</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="有效期" width="220">
            <template slot-scope="scope">
              <div v-if="scope.row.time_type == 1">
                {{ scope.row.start_at_text || scope.row.start_at }} <br>至<br>
                {{ scope.row.end_at_text || scope.row.end_at }}
              </div>
              <div v-else>
                领取后 {{ scope.row.day }} 天内有效
              </div>
            </template>
          </el-table-column>

          <el-table-column label="所属商家" width="120" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.mch">{{ scope.row.mch.mch_name }}</span>
              <el-tag type="success" size="mini" v-else>平台自营</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="发放/剩余" width="120" align="center">
            <template slot-scope="scope">
              <div v-if="scope.row.total_count == -1">不限制</div>
              <div v-else>
                <div>总: {{ scope.row.total_count }}</div>
                <!-- 假设后端返回了剩余数量 remain_count，如果没有可以不显示 -->
                <!-- <div style="color:#909399; font-size:12px;">余: {{ scope.row.remain_count }}</div> -->
              </div>
            </template>
          </el-table-column>

          <el-table-column label="每人限领" width="100" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.limit_per_user == 0">不限制</span>
              <span v-else>{{ scope.row.limit_per_user }} 张</span>
            </template>
          </el-table-column>

          <el-table-column label="允许转赠" width="80" align="center">
            <template slot-scope="scope">
              <el-tag type="success" size="mini" v-if="scope.row.is_transferable == 1">是</el-tag>
              <el-tag type="info" size="mini" v-else>否</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="使用范围" width="100" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.appoint_type === 'all'">全场通用</span>
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
                @confirm="couponDelete(scope.row.id, scope.$index)"
              >
                <el-button type="text" size="small" slot="reference" style="color: #F56C6C; margin-left: 10px;">删除
                </el-button>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <div style="text-align: right; margin-top: 20px;">
          <el-pagination
            v-if="pagination"
            background
            :current-page.sync="page"
            :page-size="pagination.limit"
            layout="total, prev, pager, next, jumper"
            :total="pagination.total_count"
            @current-change="pageChange"
          ></el-pagination>
        </div>
      </div>
    </el-card>

    <!-- 发放优惠券弹窗组件 -->
    <coupon-send
      :visible.sync="sendDialogVisible"
      :coupon-id="currentCouponId"
      :coupon-name="currentCouponName"
      @saved="getList"
    />
    <coupon-edit-drawer
      :visible.sync="editDrawerVisible"
      :coupon-id="currentEditCouponId"
      @success="getList"
    />
  </div>
</template>

<script>
// 引入发放组件
import CouponSend from './CouponSend.vue';
import CouponEditDrawer from './CouponEditDrawer.vue';
const emptySearch = () => ({ keyword: '', type: '', owner: '', is_enabled: '', can_be_claimed: '', validity: '' });

export default {
  name: 'MarketingCouponList',
  components: { CouponSend, CouponEditDrawer },
  data() {
    return {
      is_loading: false,
      page: 1,
      pagination: null,
      list: [],

      // 发放弹窗相关
      sendDialogVisible: false,
      currentCouponId: 0,
      currentCouponName: '',
      // 【新增】抽屉编辑相关
      editDrawerVisible: false,
      currentEditCouponId: null,

      search: emptySearch(),
    }
  },
  created() {
    this.getList()
  },
  methods: {
    pageChange(page) {
      this.page = page
      this.getList()
    },
    toSearch() {
      this.page = 1
      this.getList()
    },
    resetSearch() {
      this.search = emptySearch()
      this.toSearch()
    },
    couponEdit(id) {
      this.currentEditCouponId = id;
      this.editDrawerVisible = true;
    },

    // 打开发放弹窗
    sendCoupon(row) {
      this.currentCouponId = row.id;
      this.currentCouponName = row.name;
      this.sendDialogVisible = true;
    },

    // 获取列表
    getList() {
      this.is_loading = true
      this.$request({
        url: '/plugin/marketing/mall/coupon/list',
        data: {
          ...this.search,
          page: this.page
        }
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

    // 删除优惠券
    couponDelete(id, index) {
      this.is_loading = true
      this.$request({
        url: '/plugin/marketing/mall/coupon/delete',
        data: {id: id},
        method: 'post'
      }).then(res => {
        this.is_loading = false
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.list.splice(index, 1)
          // 翻页处理
          if (this.list.length === 0 && this.page > 1) {
            this.page--
            this.getList()
          }
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {
        this.is_loading = false
      })
    },

    // 开关状态切换 (可选)
    statusChange(row) {
      this.$request({
        url: '/plugin/marketing/mall/coupon/status', // 预留:后端暂无该接口(开关当前禁用)
        method: 'post',
        data: {id: row.id, status: row.is_enabled}
      }).then(res => {
        if (res.code === 0) {
          this.$message.success('操作成功');
        } else {
          this.$message.error(res.msg);
          row.is_enabled = row.is_enabled === 1 ? 0 : 1; // 恢复
        }
      });
    }
  }
}
</script>

<style scoped>
.search-bar { margin-bottom: 4px; }
.price-text {
  color: #F56C6C;
  font-weight: bold;
}
</style>
