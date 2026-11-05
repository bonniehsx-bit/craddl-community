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

        <el-form-item label="优惠券名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入优惠券名称" style="max-width: 350px;"></el-input>
        </el-form-item>

        <el-form-item label="启用状态">
          <el-switch v-model="form.is_enabled" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="优惠券类型" prop="type">
          <!-- 商户强制满减模式 -->
          <el-radio-group v-model="form.type" disabled>
            <el-radio :label="0">满减券</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="金额设置" prop="sub_price">
          <div class="flex-y-center amount-row">
            <span>消费满</span>
            <el-input v-model="form.min_price" class="amount-input" type="number" min="0">
              <template slot="append">元</template>
            </el-input>
            <span>减</span>
            <el-input v-model="form.sub_price" class="amount-input" type="number" min="0">
              <template slot="append">元</template>
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
          <el-radio-group v-model="form.time_type" style="margin-bottom: 10px;" disabled>
            <el-radio :label="0">领取后生效</el-radio>
          </el-radio-group>
          <div>
            <el-input v-model.number="form.day" style="max-width: 350px;" type="number" min="1">
              <template slot="prepend">领取后</template>
              <template slot="append">天内有效</template>
            </el-input>
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
          <!-- 商户目前强制全场通用 -->
          <el-radio-group v-model="form.appoint_type" disabled style="margin-bottom: 10px;">
            <el-radio label="all">全场通用</el-radio>
          </el-radio-group>
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
// 提供干净的默认表单，防止抽屉二次打开时数据残留
const getDefaultForm = () => ({
  id: null,
  name: '',
  is_enabled: 1,
  type: 0,
  min_price: '',
  sub_price: '',
  time_type: 0,
  day: 1,
  total_count: -1,
  can_be_claimed: 1,
  limit_per_user: 0,
  appoint_type: 'all'
});

export default {
  name: 'MchCouponEdit',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
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

      form: getDefaultForm(),

      rules: {
        name: [{required: true, message: '请输入优惠券名称', trigger: 'blur'}],
        sub_price: [{
          validator: (rule, value, callback) => {
            if (!value || value <= 0) {
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
      if (this.couponId) {
        this.form.id = this.couponId;
        this.getCoupon();
      }
    },
    resetData() {
      this.form = getDefaultForm();
      if (this.$refs.form) {
        this.$refs.form.clearValidate();
      }
    },
    handleClose() {
      if (this.is_saving) return;
      this.drawerVisible = false;
    },

    getCoupon() {
      this.is_loading = true;
      this.$request({
        url: '/plugin/mch/admin/coupon/detail',
        data: { id: this.form.id },
        method: 'get'
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          let c = res.data.detail;
          Object.assign(this.form, {
            name: c.name,
            is_enabled: c.is_enabled !== undefined ? c.is_enabled : 1,
            min_price: c.min_price,
            sub_price: c.sub_price,
            day: c.day || c.expire_day || 1, // Fallback gracefully
            total_count: c.total_count !== undefined ? c.total_count : -1,
            can_be_claimed: c.can_be_claimed !== undefined ? c.can_be_claimed : (c.is_join !== undefined ? c.is_join : 1), // 兼容老版本is_join字段
            limit_per_user: c.limit_per_user || 0,
            appoint_type: 'all' // force visually
          });
        } else {
          this.$message.error(res.msg);
          this.drawerVisible = false;
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
          url: '/plugin/mch/admin/coupon/edit',
          data: this.form,
          method: 'post'
        }).then(res => {
          this.is_saving = false;
          if (res.code === 0) {
            this.$message.success('保存成功');
            this.drawerVisible = false;
            this.$emit('success');
          } else {
            this.$message.error(res.msg);
          }
        }).catch(() => {
          this.is_saving = false;
        });
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
</style>
