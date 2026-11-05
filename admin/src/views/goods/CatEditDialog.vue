<template>
  <el-dialog
    :title="form.id ? '编辑分类' : '添加分类'"
    :visible.sync="dialogVisible"
    width="50%"
    @close="onClose"
    @opened="onOpened"
    v-loading="loading">

    <el-form :model="form" :rules="rules" ref="formRef" label-width="150px">
      <el-form-item label="上级分类" prop="parent_id">
        <el-select v-model="form.parent_id" placeholder="不选择则为一级分类" clearable>
          <el-option
            v-for="item in topLevelCategories"
            :key="item.id"
            :label="item.name"
            :value="item.id">
          </el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="分类名称" prop="name">
        <el-input v-model="form.name"></el-input>
      </el-form-item>
      <el-form-item label="排序" prop="sort">
        <el-input v-model.number="form.sort" type="number"></el-input>
        <div class="tip-text">数字越大，越靠前</div>
      </el-form-item>
      <el-divider>其他设置</el-divider>

      <el-form-item label="缩略图">
        <single-image-selector v-model="form.cover_pic"></single-image-selector>
      </el-form-item>
      <el-form-item label="广告图">
        <single-image-selector v-model="form.adv_pic"></single-image-selector>
      </el-form-item>
      <el-form-item label="广告链接">
        <link-picker v-model="form.link"></link-picker>
      </el-form-item>
      <el-form-item label="是否显示">
        <el-switch v-model="form.is_show" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>
    </el-form>

    <span slot="footer" class="dialog-footer">
      <el-button @click="dialogVisible = false">取 消</el-button>
      <el-button type="primary" @click="submit" :loading="submitLoading">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>

export default {
  name: 'CatEditDialog',

  props: {
    visible: { type: Boolean, default: false },
    catId: { type: [Number, String], default: null }
  },
  data() {
    return {
      loading: false,
      submitLoading: false,
      form: this.getCleanForm(),
      rules: {
        name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
        sort: [{ required: true, message: '请输入排序号', trigger: 'blur' }],
      },
      topLevelCategories: [],
    };
  },
  computed: {
    dialogVisible: {
      get() { return this.visible; },
      set(val) { this.$emit('update:visible', val); }
    }
  },
  methods: {
    getCleanForm() {
      return {
        id: null,
        name: '',
        cover_pic: '',
        adv_pic: '',
        is_show: 1,
        sort: 100,
        parent_id: '',
        link: null,

      };
    },
    async onOpened() {
      this.loading = true;
      try {
        await this.getTopLevelCats();
        if (this.catId) {
          await this.getCatDetail();
        }
      } finally {
        this.loading = false;
      }
    },
    onClose() {
      this.$refs.formRef.resetFields();
      this.form = this.getCleanForm();
      this.dialogVisible = false;
      this.$emit('closed');
    },
    async getTopLevelCats() {
      try {
        const res = await this.$request({
          url: '/mall/goods/cat-level',
          data: { cat_id: this.catId }
        });
        if (res.code === 0) {
          this.topLevelCategories = res.data.list;
        }
      } catch (e) {
        console.error("Failed to get top level categories", e);
      }
    },
    async getCatDetail() {
      try {
        const res = await this.$request({
          url: '/mall/goods/cat-edit',
          data: { id: this.catId }
        });
        if (res.code === 0) {
          this.form = res.data.cat;
        } else {
          this.$message.error(res.msg);
          this.onClose();
        }
      } catch (e) {
        console.error("Failed to get category detail", e);
        this.onClose();
      }
    },
    submit() {
      this.$refs.formRef.validate(async (valid) => {
        if (valid) {
          this.submitLoading = true;
          try {
            const res = await this.$request({
              url: '/mall/goods/cat-edit',
              method: 'post',
              data: this.form
            });
            if (res.code === 0) {
              this.$message.success('保存成功');
              this.$emit('saved');
              this.onClose();
            } else {
              this.$message.error(res.msg);
            }
          } finally {
            this.submitLoading = false;
          }
        }
      });
    }
  }
}
</script>

<style scoped>
.el-select { width: 100%; }
.tip-text { font-size: 12px; color: #C1C1C1; line-height: 1.5; }
</style>
