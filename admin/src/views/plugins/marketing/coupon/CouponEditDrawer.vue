<template>
  <el-drawer
    :title="couponId ? '编辑优惠券' : '新建优惠券'"
    :visible.sync="drawerVisible"
    size="700px"
    :before-close="handleClose"
    :destroy-on-close="true"
    custom-class="coupon-edit-drawer"
    :wrapperClosable="true"
  >
    <div class="drawer-content" v-loading="is_loading">
      <el-form size="mini" ref="form" :model="form" :rules="rules" label-width="130px" class="custom-form">

        <el-form-item label="所属商家" prop="mch_id">
          <el-select
            v-model="form.mch_id"
            filterable
            remote
            clearable
            placeholder="请输入商家名称搜索"
            :remote-method="searchMch"
            :loading="mchLoading"
            :disabled="!!form.id"
            style="width: 100%; max-width: 350px;"
          >
            <el-option
              v-for="item in mchOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id">
            </el-option>
          </el-select>
          <div class="form-tip">留空则表示创建【平台自营】优惠券；编辑模式下不可修改所属商家。</div>
        </el-form-item>

        <el-form-item v-if="!form.mch_id" label="可用于商家商品">
          <el-switch v-model="form.apply_to_mch" :active-value="1" :inactive-value="0"></el-switch>
          <div class="form-tip">仅平台券可设。开启后此券可用于本商城所有商家的商品，优惠金额由<b>平台承担</b>（商家收入不受影响）；关闭则仅限平台自营商品。</div>
        </el-form-item>

        <el-form-item label="优惠券名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入优惠券名称" style="max-width: 350px;"></el-input>
        </el-form-item>

        <el-form-item label="启用状态">
          <el-switch v-model="form.is_enabled" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="允许转赠" prop="is_transferable">
          <el-switch v-model="form.is_transferable" :active-value="1" :inactive-value="0"></el-switch>
          <div class="form-tip">开启后，用户可以将此优惠券转赠给好友</div>
        </el-form-item>

        <el-form-item label="优惠券类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio :label="0">满减券</el-radio>
            <el-radio :label="1" style="display: none">折扣券</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="金额设置" prop="sub_price">
          <div v-if="form.type == 0" class="flex-y-center amount-row">
            <span>消费满</span>
            <el-input v-model="form.min_price" class="amount-input" type="number" min="0">
              <template slot="append">元</template>
            </el-input>
            <span>减</span>
            <el-input v-model="form.sub_price" class="amount-input" type="number" min="0">
              <template slot="append">元</template>
            </el-input>
          </div>
          <div v-if="form.type == 1" class="flex-y-center amount-row">
            <span>消费满</span>
            <el-input v-model="form.min_price" class="amount-input" type="number" min="0">
              <template slot="append">元</template>
            </el-input>
            <span>打</span>
            <el-input v-model="form.discount" class="amount-input" type="number" min="0.1" max="10">
              <template slot="append">折</template>
            </el-input>
          </div>
        </el-form-item>

        <el-form-item label="发放总量" prop="total_count">
          <el-input v-model.number="form.total_count" style="max-width: 350px;" type="number" min="-1" placeholder="-1 为不限制">
            <template slot="append">张</template>
          </el-input>
          <div class="form-tip">填写-1或留空，表示不限制发放总数</div>
        </el-form-item>

        <el-form-item label="有效期" prop="time_type">
          <el-radio-group v-model="form.time_type" style="margin-bottom: 10px;">
            <el-radio :label="0">领取后生效</el-radio>
            <el-radio :label="1">固定日期</el-radio>
          </el-radio-group>
          <div v-if="form.time_type == 0">
            <el-input v-model.number="form.day" style="max-width: 350px;" type="number" min="0">
              <template slot="prepend">领取后</template>
              <template slot="append">天内有效</template>
            </el-input>
          </div>
          <div v-if="form.time_type == 1">
            <el-date-picker
              v-model="form.date_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="max-width: 350px;"
            >
            </el-date-picker>
          </div>
        </el-form-item>

        <el-form-item label="加入到领券中心">
          <el-switch v-model="form.can_be_claimed" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="每人限领数量" prop="limit_per_user">
          <el-input v-model.number="form.limit_per_user" style="max-width: 350px;" type="number" min="0" placeholder="0 为不限制">
            <template slot="append">张</template>
          </el-input>
          <div class="form-tip">填写0或留空，表示不限制每人领取张数</div>
        </el-form-item>

        <el-form-item label="使用范围" prop="appoint_type">
          <el-radio-group v-model="form.appoint_type" style="margin-bottom: 10px;">
            <el-radio label="all">全场通用</el-radio>
            <el-radio label="goods">指定商品</el-radio>
          </el-radio-group>

          <div v-if="form.appoint_type === 'goods'" class="goods-table-wrapper">
            <el-table :data="form.appoint_ids" style="width: 100%" border size="mini">
              <el-table-column label="商品" min-width="250">
                <template slot-scope="scope">
                  <div class="flex-row goods-cell">
                    <el-image :src="scope.row.cover_pic" class="goods-img" fit="cover"></el-image>
                    <div class="goods-name" :title="scope.row.name">{{ scope.row.name }}</div>
                  </div>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="60" align="center">
                <template slot-scope="scope">
                  <el-button size="mini" type="text" class="text-danger" @click="goodsDelete(scope.$index)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div class="goods-picker-btn">
              <goods-picker @selected="selectedGoods" :limit="50">
                <el-button type="primary" plain size="mini" icon="el-icon-plus">添加指定商品 ({{ (form.appoint_ids || []).length }}/50)</el-button>
              </goods-picker>
            </div>
          </div>
        </el-form-item>
      </el-form>
    </div>

    <div class="drawer-footer">
      <el-button size="small" @click="handleClose">取消</el-button>
      <el-button type="primary" size="small" @click="save" :loading="is_saving">保存优惠券</el-button>
    </div>
  </el-drawer>
</template>

<script>
// 假设您有引入 GoodsPicker 组件
import GoodsPicker from '@/components/common/GoodsPicker'

// 提供干净的默认表单，防止抽屉二次打开时数据残留
const getDefaultForm = () => ({
  id: null,
  mch_id: '',
  apply_to_mch: 0,
  name: '',
  is_transferable: 0,
  is_enabled: 1,
  type: 0,
  min_price: '',
  sub_price: '',
  discount: '',
  time_type: 0,
  day: 1,
  date_range: [],
  total_count: -1,
  can_be_claimed: 1,
  limit_per_user: 0,
  appoint_type: 'all',
  appoint_ids: []
});

export default {
  name: 'CouponEditDrawer',
  components: { GoodsPicker },
  props: {
    // 控制抽屉显示隐藏
    visible: {
      type: Boolean,
      default: false
    },
    // 如果有 id 传过来代表编辑，null或空代表新建
    couponId: {
      type: [String, Number],
      default: null
    }
  },
  computed: {
    drawerVisible: {
      get() {
        return this.visible;
      },
      set(val) {
        this.$emit('update:visible', val);
      }
    }
  },
  data() {
    return {
      is_loading: false,
      is_saving: false,

      // 商户搜索相关
      mchLoading: false,
      mchOptions: [],

      form: getDefaultForm(),

      rules: {
        name: [{required: true, message: '请输入优惠券名称', trigger: 'blur'}],
        sub_price: [{
          validator: (rule, value, callback) => {
            if (this.form.type === 0 && (!value || value <= 0)) {
              callback(new Error('满减金额必须大于0'))
            } else {
              callback()
            }
          },
          trigger: 'blur'
        }]
      }
    }
  },
  watch: {
    visible(newVal) {
      if (newVal) {
        this.initData();
      } else {
        this.resetData();
      }
    }
  },
  methods: {
    initData() {
      // 每次打开抽屉时预先触发一次空搜，获取初始商户列表
      this.searchMch('');
      if (this.couponId) {
        this.form.id = this.couponId;
        this.getCoupon();
      }
    },
    resetData() {
      // 抽屉关闭时，还原所有状态
      this.form = getDefaultForm();
      this.mchOptions = [];
      // 如果 form 有验证规则，清空验证状态
      if (this.$refs.form) {
        this.$refs.form.clearValidate();
      }
    },
    handleClose() {
      if (this.is_saving) return;
      this.drawerVisible = false;
    },

    // 搜索商户
    searchMch(query) {
      // 允许查询为空，获取默认列表
      this.mchLoading = true;
      this.$request({
        url: '/mall/mch/search',
        method: 'get',
        data: {
          keyword: query
        }
      }).then(res => {
        this.mchLoading = false;
        if (res.code === 0) {
          this.mchOptions = res.data.list.map(item => ({
            id: item.id,
            name: item.mch_name || item.name
          }));
        }
      }).catch(() => {
        this.mchLoading = false;
      });
    },

    getCoupon() {
      this.is_loading = true;
      this.$request({
        url: '/plugin/marketing/mall/coupon/detail',
        data: { id: this.form.id }
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          this.form = {
            ...this.form,
            ...res.data.coupon
          };

          // 回显商户名称
          if (this.form.mch_id && this.form.mch_id > 0) {
            this.mchOptions = [{
              id: this.form.mch_id,
              name: res.data.coupon.mch_name
            }];
          }

        } else {
          this.$message.error(res.msg);
          this.drawerVisible = false; // 加载失败直接关掉抽屉
        }
      }).catch(() => {
        this.is_loading = false;
      });
    },

    save() {
      this.$refs.form.validate((valid) => {
        if (!valid) return;

        this.is_saving = true;

        this.$request({
          url: '/plugin/marketing/mall/coupon/save',
          data: this.form,
          method: 'post'
        }).then(res => {
          this.is_saving = false;
          if (res.code === 0) {
            this.$message.success(res.msg);
            this.drawerVisible = false;
            // 通知父级列表刷新
            this.$emit('success');
          } else {
            this.$message.error(res.msg);
          }
        }).catch(() => {
          this.is_saving = false;
        });
      });
    },

    goodsDelete(index) {
      this.form.appoint_ids.splice(index, 1);
    },

    selectedGoods(selection) {
      if (!this.form.appoint_ids) {
        this.form.appoint_ids = [];
      }
      selection.forEach(newItem => {
        if (!this.form.appoint_ids.some(oldItem => oldItem.id === newItem.id)) {
          this.form.appoint_ids.push(newItem);
        }
      });
    }
  }
}
</script>

<style lang="scss">
/* 覆盖抽屉的默认结构，固定头部和尾部，中间自适应滚动 */
.coupon-edit-drawer {
  display: flex;
  flex-direction: column;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ebeef5;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
  }

  .el-drawer__body {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
}
</style>

<style scoped lang="scss">
/* 抽屉内容区可滚动 */
.drawer-content {
  flex: 1;
  overflow-y: auto;
  padding: 20px 30px;
  background-color: #fff;
}

/* 抽屉底部固定操作栏 */
.drawer-footer {
  flex-shrink: 0;
  padding: 12px 20px;
  border-top: 1px solid #ebeef5;
  background-color: #fff;
  display: flex;
  justify-content: flex-end;
  box-shadow: 0 -2px 10px rgba(0,0,0,0.02);
}

.custom-form {
  padding-right: 20px; /* 防止滚动条贴边 */
}

.flex-y-center {
  display: flex;
  align-items: center;
}

.flex-row {
  display: flex;
  align-items: center;
}

/* 金额设置行紧凑化 */
.amount-row {
  gap: 10px;
  .amount-input {
    width: 140px;
  }
}

.form-tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.4;
  margin-top: 5px;
}

.text-danger {
  color: #F56C6C;
}

/* 隐藏输入框原生的上下微调箭头 */
::v-deep input[type=number]::-webkit-inner-spin-button,
::v-deep input[type=number]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* 商品表格区域优化 */
.goods-table-wrapper {
  background: #f8f9fa;
  padding: 10px;
  border-radius: 4px;
}

.goods-cell {
  .goods-img {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    border: 1px solid #eee;
    flex-shrink: 0;
  }
  .goods-name {
    padding-left: 10px;
    line-height: 1.2;
    font-size: 12px;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }
}

.goods-picker-btn {
  margin-top: 10px;
  text-align: center;
}
</style>
