<template>
  <el-dialog title="订单发货" :visible.sync="dialogVisible" width="60%" @close="handleClose" @open="handleOpen" :close-on-click-modal="false" append-to-body>
    <div v-loading="loading">
      <el-form v-if="order" ref="sendForm" :model="form" label-width="120px" size="small">
        <el-form-item label="发货方式">
          <el-radio-group v-model="form.send_type">
            <el-radio :label="0">整单发货 (快递)</el-radio>
            <el-radio :label="1">分包发货 (快递)</el-radio>
            <el-radio :label="2">无需物流</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="voidedNames.length" label="">
          <el-alert type="warning" :closable="false" show-icon
            :title="'这几件已退款,不用发货:' + voidedNames.join('、')"></el-alert>
        </el-form-item>

        <template v-if="form.send_type !== 2">
          <el-form-item label="收货人信息">
            <div class="receiver-info-text">{{ order.name }} | {{ order.mobile }}</div>
          </el-form-item>
          <el-form-item label="收货地址">
            <div class="receiver-info-text">{{ order.address }}</div>
          </el-form-item>
          <el-form-item label="快递公司" prop="express_name" :rules="{ required: true, message: '请选择快递公司', trigger: 'change' }">
            <el-select v-model="form.express_name" placeholder="请选择快递公司" filterable style="width: 100%;" @change="expressSelect">
              <el-option v-for="item in expressList" :key="item.id" :label="item.name" :value="item.name"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="快递单号" prop="express_no" :rules="{ required: true, message: '请输入快递单号', trigger: 'blur' }">
            <el-input v-model="form.express_no" placeholder="请输入快递单号"></el-input>
          </el-form-item>
        </template>

        <template v-if="form.send_type === 2">
          <el-form-item label="无需物流备注">
            <el-input
              type="textarea"
              :rows="3"
              v-model="form.no_express_remark"
              maxlength="500"
              show-word-limit
              placeholder="选填。例如：虚拟商品已线下交付 / 服务已提供 / 到店自取等。此备注独立记录，不影响买家备注。">
            </el-input>
          </el-form-item>
        </template>

        <template v-if="form.send_type === 1">
          <el-form-item label="选择发货商品">
            <el-table :data="orderDetailList" style="width: 100%" ref="selectionTable" @selection-change="handleSelectionChange" height="250px">
              <el-table-column type="selection" width="55" :selectable="checkSelectable"></el-table-column>
              <el-table-column label="商品信息">
                <template slot-scope='scope'>
                  <div style="display: flex; align-items: center;">
                    <el-image :src='scope.row.goods.cover_pic' style="width: 60px; height: 60px; border-radius: 4px; flex-shrink: 0;"></el-image>
                    <div style="margin-left: 10px; font-size: 13px;">{{scope.row.goods.name}}</div>
                  </div>
                </template>
              </el-table-column>
              <el-table-column prop="num" label="总数量" width="80" align="center"></el-table-column>
              <el-table-column label="状态" width="100" align="center">
                <template slot-scope='scope'>
                  <el-tag v-if="Number(scope.row.status) === 2" type="danger" size="mini">已退款</el-tag>
                  <el-tag v-else :type="scope.row.is_send ? 'success' : 'info'" size="mini">
                    {{ scope.row.is_send ? '已发货' : '未发货' }}
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>
          </el-form-item>
        </template>
      </el-form>
      <div v-else style="min-height: 200px; display: flex; align-items: center; justify-content: center;">
        <span v-if="!loading">请填写发货信息</span>
      </div>
    </div>
    <span slot="footer" class="dialog-footer">
			<el-button @click="handleClose" size="small">取 消</el-button>
			<el-button type="primary" @click="submit" :loading="is_submiting" size="small">确 定发货</el-button>
		</span>
  </el-dialog>
</template>

<script>
export default {
  name: 'OrderSend',
  props: {
    visible: {
      type: Boolean,
      required: true
    },
    orderId: {
      type: [Number, String],
      default: null
    }
  },
  computed: {
    // 已退款(确认打款后作废,status=2)的商品:整单发货 / 无需物流时在弹窗里点名,免得照单全发
    voidedNames() {
      return (this.orderDetailList || []).filter(d => Number(d.status) === 2).map(d => (d.goods && d.goods.name) || ('#' + d.id))
    }
  },
  data() {
    return {
      loading: false,
      is_submiting: false,
      dialogVisible: false,
      expressList: [],
      order: null,
      orderDetailList: [],
      // 将表单数据集中管理
      form: {
        send_type: 0,
        express_no: '',
        express_name: '',
        express_code: '',
        send_list: [],
        no_express_remark: '',
      }
    }
  },
  watch: {
    visible(newVal) {
      this.dialogVisible = newVal;
    }
  },
  created() {
    this.getExpressList();
  },
  methods: {
    async handleOpen() {
      this.resetForm();
      if (this.orderId) {
        await this.getDetail();
      }
    },
    handleClose() {
      this.$emit('update:visible', false);
    },

    resetForm() {
      this.form = {
        send_type: 0,
        express_no: '',
        express_name: '',
        express_code: '',
        send_list: [],
        no_express_remark: '',
      };
      if (this.$refs.selectionTable) {
        this.$refs.selectionTable.clearSelection();
      }
    },

    async getDetail() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/mall/order/detail',
          data: { order_id: this.orderId }
        });
        if (res.code === 0) {
          this.order = res.data.order;
          this.orderDetailList = res.data.list;
        } else {
          this.$message.error('加载订单数据失败: ' + res.msg);
          this.handleClose();
        }
      } catch(e) {
        console.error(e);
        this.$message.error('加载订单数据失败，请检查网络');
        this.handleClose();
      } finally {
        this.loading = false;
      }
    },

    async getExpressList() {
      try {
        const res = await this.$request({ url: '/common/express/express' });
        if (res.code === 0) {
          this.expressList = res.data.list;
        }
      } catch (error) {
        console.error("Failed to fetch express list", error);
      }
    },

    expressSelect(name) {
      const selected = this.expressList.find(item => item.name === name);
      if(selected) {
        this.form.express_name = selected.name;
        this.form.express_code = selected.key; // 假设 key 是 code
      }
    },

    handleSelectionChange(selection) {
      this.form.send_list = selection.map(item => item.id);
    },

    // 已发过的、已退款的(明细作废,status=2)不能再选;部分退款后只发剩下的
    checkSelectable(row) {
      return row.is_send !== 1 && Number(row.status) !== 2;
    },

    async submit() {
      if (this.form.send_type === 1 && this.form.send_list.length === 0) {
        return this.$message.warning('分包发货模式下，请至少选择一个未发货的商品');
      }
      if (this.form.send_type !== 2) {
        if (!this.form.express_name) return this.$message.warning('请选择快递公司');
        if (!this.form.express_no.trim()) return this.$message.warning('请输入快递单号');
      }

      this.is_submiting = true;
      try {
        const res = await this.$request({
          url: '/mall/order/send',
          method: 'post',
          data: {
            order_id: this.orderId,
            ...this.form
          }
        });
        if (res.code === 0) {
          this.$message.success('发货成功');
          this.$emit('refresh');
          this.handleClose();
        }
      } finally {
        this.is_submiting = false;
      }
    }
  }
}
</script>

<style scoped>
.receiver-info-text {
  font-size: 13px;
  color: #606266;
}
</style>
