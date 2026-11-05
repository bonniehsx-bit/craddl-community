<template>
  <el-drawer title="商户审核" :visible.sync="visible" size="600px" @close="$emit('close')" direction="rtl">
    <div v-if="mch" v-loading="loading" style="padding: 20px; height: calc(100vh - 120px); overflow-y: auto;">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="商户ID">{{ mch.id }}</el-descriptions-item>
        <el-descriptions-item label="商户名称">{{ mch.mch_name }}</el-descriptions-item>

        <!-- ======================= [核心新增] 开始: 商家类型显示 ======================= -->
        <el-descriptions-item label="商家类型">
          <el-tag :type="mch.type === 1 ? 'primary' : 'success'" size="small">
            {{ getTypeText(mch.type) }}
          </el-tag>
        </el-descriptions-item>
        <!-- ======================= [核心新增] 结束 ======================= -->

        <el-descriptions-item label="店长昵称">{{ mch.user_info ? mch.user_info.nickname : '-' }} (ID: {{mch.user_id}})</el-descriptions-item>
        <el-descriptions-item :label="mch.type === 4 ? '本人姓名' : '法人姓名'">{{ mch.legal_person_name }}</el-descriptions-item>
        <el-descriptions-item label="联系手机">{{ mch.mobile }}</el-descriptions-item>

        <!-- 门店和个人租户都展示地址 -->
        <template v-if="mch.type === 2 || mch.type === 4">
          <el-descriptions-item v-if="mch.type === 2" label="联系电话">{{ mch.tel || '-' }}</el-descriptions-item>
          <el-descriptions-item label="详细地址" :span="2">{{ mch.address || '-' }}</el-descriptions-item>
        </template>

        <el-descriptions-item :label="mch.type === 4 ? '本人身份证' : '法人身份证'">
          <template v-if="mch.id_card_front || mch.id_card_back">
            <el-image v-if="mch.id_card_front" :src="mch.id_card_front" style="width: 80px; height: 80px; margin-right: 10px;" :preview-src-list="[mch.id_card_front]"></el-image>
            <el-image v-if="mch.id_card_back" :src="mch.id_card_back" style="width: 80px; height: 80px" :preview-src-list="[mch.id_card_back]"></el-image>
          </template>
          <span v-else>-</span>
        </el-descriptions-item>
      </el-descriptions>

      <!-- 证照(动态:营业执照及后台配置的各类许可证,含有效期)-->
      <div class="cert-section" v-if="certList.length">
        <div class="cert-title">证照</div>
        <div class="cert-grid">
          <div class="cert-item" v-for="(c, i) in certList" :key="i">
            <el-image :src="c.image" fit="cover" class="cert-img" :preview-src-list="[c.image]"></el-image>
            <div class="cert-name" :title="c.name">{{ c.name }}</div>
            <div class="cert-valid" :class="{ expired: isExpired(c) }">{{ certValidText(c) }}</div>
          </div>
        </div>
      </div>

      <el-form style="margin-top: 20px;" label-width="80px" size="mini">
        <el-form-item label="审核备注">
          <el-input type="textarea" v-model="form.remarks" placeholder="仅在驳回时需要填写"></el-input>
        </el-form-item>
      </el-form>
    </div>
    <div style="padding: 10px 20px; text-align: right; border-top: 1px solid #EBEEF5; background: #fff; width: 100%; box-sizing: border-box;">
      <el-button @click="$emit('close')" size="small">取 消</el-button>
      <el-button @click="handleReview(2)" :loading="loading" size="small">驳 回</el-button>
      <el-button type="primary" @click="handleReview(1)" :loading="loading" size="small">通 过</el-button>
    </div>
  </el-drawer>
</template>

<script>
export default {
  name: 'MchReviewDrawer',
  props: {
    visible: Boolean,
    mch: Object,
  },
  data() {
    return {
      loading: false,
      form: { remarks: '' },
    }
  },
  computed: {
    // 证照:优先用提交记录;老商户(无记录)回退 business_license 列,保证旧数据仍可见
    certList() {
      if (!this.mch) return []
      const list = Array.isArray(this.mch.certificates) ? this.mch.certificates : []
      if (list.length) return list
      if (this.mch.business_license) {
        return [{ name: '营业执照', image: this.mch.business_license, is_long_term: 0, valid_start: '', valid_end: '' }]
      }
      return []
    }
  },
  watch: {
    mch(newVal) {
      if (newVal) {
        // 当 mch 对象更新时，同步更新表单中的备注信息
        // 这里的 newVal.remarks 是指商家申请时可能被驳回过的历史备注
        this.form.remarks = newVal.remarks || '';
      }
    }
  },
  methods: {
    /**
     * [核心新增]
     * 获取商家类型对应的文案
     * @param {number} type - 商家类型值 (1: 供应商, 2: 门店)
     * @returns {string} 对应的文本
     */
    getTypeText(type) {
      const map = {
        1: '线上商家',
        2: '线下门店',
        4: '个人租户',
      };
      return map[type] || '未知类型';
    },

    // 证照有效期文案
    certValidText(c) {
      if (Number(c.is_long_term) === 1) return '长期有效';
      if (c.valid_start && c.valid_end) return `${c.valid_start} 至 ${c.valid_end}`;
      if (c.valid_end) return `至 ${c.valid_end}`;
      return '';
    },
    // 是否已过期(标红提示审核员)
    isExpired(c) {
      if (Number(c.is_long_term) === 1 || !c.valid_end) return false;
      return c.valid_end < new Date().toISOString().slice(0, 10);
    },

    /**
     * 处理审核操作（通过或驳回）
     * @param {number} status - 目标状态 (1: 通过, 2: 驳回)
     */
    async handleReview(status) {
      if (status === 2 && !this.form.remarks) {
        this.$message.warning('请填写驳回理由');
        return;
      }

      this.loading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/update-status',
          method: 'post',
          data: {
            id: this.mch.id,
            status,
            remarks: this.form.remarks
          }
        });

        if (res.code === 0) {
          this.$message.success('操作成功');
          this.$emit('close'); // 关闭弹窗
          this.$emit('finish'); // 触发父组件刷新列表
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style scoped>
.cert-section { margin-top: 20px; }
.cert-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 12px; }
.cert-grid { display: flex; flex-wrap: wrap; gap: 16px; }
.cert-item { width: 100px; text-align: center; }
.cert-img { width: 100px; height: 100px; border-radius: 4px; border: 1px solid #ebeef5; }
.cert-name { font-size: 12px; color: #303133; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cert-valid { font-size: 12px; color: #909399; margin-top: 2px; }
.cert-valid.expired { color: #F56C6C; }
</style>
