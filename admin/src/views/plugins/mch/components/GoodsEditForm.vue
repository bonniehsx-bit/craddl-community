<template>
  <div v-loading="loading" class="goods-edit-form-container">
    <el-form :model="form" :rules="rules" ref="form" label-width="120px" size="mini">
      <el-tabs v-model="activeTab">
        <!-- 1. 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="商品分类" prop="cat_id">
                <el-cascader
                  v-model="cascaderCatId"
                  :options="platformCategoryOptions"
                  :props="{ checkStrictly: false, value: 'id', label: 'name', children: 'children', emitPath: false }"
                  clearable
                  @change="onCategoryChange"
                  placeholder="请选择平台商品分类"
                  style="width: 100%;"
                ></el-cascader>
              </el-form-item>
              <el-form-item label="店铺分类">
                <el-cascader
                  v-model="form.mch_cats"
                  :options="mchCategoryOptions"
                  :props="{ checkStrictly: true, value: 'id', label: 'name', children: 'children', multiple: true, emitPath: false }"
                  clearable
                  placeholder="请选择店铺专属分类(可选跨级)"
                  style="width: 100%;"
                ></el-cascader>
              </el-form-item>
              <el-form-item label="商品名称" prop="name">
                <el-input v-model="form.name" placeholder="请输入商品名称"></el-input>
              </el-form-item>
              <el-form-item label="商品单位" prop="unit">
                <el-input v-model="form.unit" placeholder="例如：件、个、斤、箱"></el-input>
              </el-form-item>
              <el-form-item label="虚拟销量">
                <el-input-number v-model="form.virtual_sales" :min="0" controls-position="right"></el-input-number>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- 2. 价格库存 -->
        <el-tab-pane label="价格库存" name="price">
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="商品售价" prop="price">
                <el-input-number v-model="form.price" :precision="2" :min="0.01"
                                 :disabled="form.is_attr == 1"></el-input-number>
                <span style="margin-left: 5px;">元</span>
              </el-form-item>
              <el-form-item label="市场价(原价)">
                <el-input-number v-model="form.origin_price" :precision="2" :min="0"></el-input-number>
                <span style="margin-left: 5px;">元</span>
              </el-form-item>
              <el-form-item label="商品成本价">
                <el-input-number v-model="form.cost_price" :precision="2" :min="0"
                                 :disabled="form.is_attr == 1"></el-input-number>
                <span style="margin-left: 5px;">元</span>
                <div class="form-tip">单规格时填写，多规格时由SKU成本价决定。</div>
              </el-form-item>
              <el-form-item label="总库存" prop="stock">
                <el-input-number v-model="form.stock" :min="0" controls-position="right"
                                 :disabled="form.is_attr == 1"></el-input-number>
                <div class="form-tip">如使用多规格，总库存由各规格库存之和自动计算。</div>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- 3. 规格信息 -->
        <el-tab-pane label="规格信息" name="specs">
          <el-form-item label="是否多规格">
            <el-radio-group v-model="form.is_attr" @change="onSpecTypeChange">
              <el-radio :label="0">单规格</el-radio>
              <el-radio :label="1">多规格</el-radio>
            </el-radio-group>
          </el-form-item>
          <sku-generator v-if="form.is_attr === 1" v-model="form.specs_data" @change="updateTotalStock"></sku-generator>
        </el-tab-pane>

        <!-- 4. 媒体与详情 -->
        <el-tab-pane label="媒体与详情" name="media">
          <el-form-item label="商品封面图" prop="cover_pic">
            <single-image-selector v-model="form.cover_pic"></single-image-selector>
            <div class="form-tip">建议尺寸：750*750</div>
          </el-form-item>
          <el-form-item label="商品轮播图" prop="pic_list">
            <images-selector v-model="form.pic_list" :limit="9"></images-selector>
            <div class="form-tip">建议尺寸：750*750，最多上传9张</div>
          </el-form-item>
          <el-form-item label="商品详情">
            <rich-text v-model="form.detail"></rich-text>
          </el-form-item>
        </el-tab-pane>

        <!-- 5. 分润配置 -->
        <el-tab-pane label="分润配置" name="profit_sharing">
          <el-row>
            <el-col :span="12">
              <el-form-item label="让利比例">
                <el-input-number v-model="form.concession_rate" :precision="2" :min="0" :max="100"
                                 placeholder="不填则使用商户默认"></el-input-number>
                <span style="margin-left: 5px;">%</span>

              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

      </el-tabs>
    </el-form>
    <div class="dialog-footer">
      <el-button @click="$emit('cancel')" size="small">取 消</el-button>
      <el-button type="primary" @click="submit" :loading="submitLoading" size="small">保 存</el-button>
    </div>
  </div>
</template>

<script>

export default {
  name: 'GoodsEditForm',
  props: {
    mchId: {type: Number, required: true},
    goodsId: {type: Number, default: null},
  },
  data() {
    return {
      loading: false,
      submitLoading: false,
      activeTab: 'basic',
      mchData: null,
      form: this.getCleanForm(),
      rules: {
        cat_id: [{required: true, message: '请选择商品分类', trigger: 'change'}],
        name: [{required: true, message: '商品名称不能为空', trigger: 'blur'}],
        cover_pic: [{required: true, message: '请上传商品封面图', trigger: 'change'}],
        pic_list: [{type: 'array', required: true, min: 1, message: '请至少上传一张商品轮播图', trigger: 'change'}],
        price: [{required: true, message: '商品价格不能为空', trigger: 'blur'}],
        stock: [{required: true, message: '总库存不能为空', trigger: 'blur'}]
      },
      platformCategoryOptions: [], // 存储平台分类
      mchCategoryOptions: [],      // 存储商户专属分类
    }
  },
  computed: {
    cascaderCatId: {
      get() {
        if (!this.form.cat_id) return [];
        let path = [];
        const findPath = (nodes, targetId) => {
          for (const node of nodes) {
            path.push(node.id);
            if (node.id === targetId) return true;
            if (node.children && findPath(node.children, targetId)) return true;
            path.pop();
          }
          return false;
        }
        findPath(this.platformCategoryOptions, this.form.cat_id);
        return path;
      },
      set(val) {
        this.form.cat_id = val && val.length > 0 ? val[val.length - 1] : null;
      }
    }
  },
  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.loadMchData(this.mchId),
        this.loadPlatformCategories(),
        this.loadMchCategories()
      ]);
      if (this.goodsId) {
        await this.loadGoodsDetail();
      } else {
        this.form.mch_id = this.mchId;
      }
    } finally {
      this.loading = false;
    }
  },
  methods: {
    getCleanForm() {
      return {
        id: null, mch_id: null, cat_id: null, name: '', unit: '件', cover_pic: '', pic_list: [],
        price: '', origin_price: '', cost_price: '', stock: 9999, is_attr: 0,
        virtual_sales: 0,
        detail: '',
        concession_rate: '',
        mch_cats: [],
        specs_data: {spec_groups: [], sku_list: []},
      }
    },
    async loadMchData(mchId) {
      try {
        const res = await this.$request({url: '/plugin/mch/mall/mch/edit', data: {id: mchId}});
        if (res.code === 0) {
          this.mchData = res.data.detail;
        }
      } catch (e) {
        console.error("加载商户信息失败", e);
      }
    },
    async loadPlatformCategories() {
      try {
        const res = await this.$request({url: '/mall/goods/cat-tree'}); // 假设这是获取平台分类的接口
        if (res.code === 0) {
          this.platformCategoryOptions = res.data.list;
        }
      } catch (e) {
        console.error("加载平台分类失败", e);
      }
    },
    async loadMchCategories() {
      try {
        const res = await this.$request({url: '/plugin/mch/mall/mch-cat/index', params: {mch_id: this.mchId}});
        if (res.code === 0) {
          this.mchCategoryOptions = res.data.list || [];
        }
      } catch (e) {
        console.error("加载店铺专属分类失败", e);
      }
    },
    async loadGoodsDetail() {
      try {
        const res = await this.$request({url: '/plugin/mch/mall/goods/edit', data: {id: this.goodsId}});
        if (res.code === 0) {
          const detail = res.data.detail;
          detail.specs_data = detail.specs_data || {spec_groups: [], sku_list: []};
          detail.specs_data.spec_groups.forEach(g => {
            this.$set(g, 'inputVisible', false);
            this.$set(g, 'inputValue', '');
          });

          this.form = {...this.getCleanForm(), ...detail};
          // 加载完数据后，手动触发一次分类变更，以获取最低比例
          this.onCategoryChange(this.form.cat_id);
        } else {
          this.$message.error(res.msg);
          this.$emit('cancel');
        }
      } catch (e) {
        console.error("加载商品详情失败", e);
      }
    },

    onSpecTypeChange() {
      this.updateTotalStock();
    },
    updateTotalStock() {
      if (this.form.is_attr == 1) {
        this.form.stock = this.form.specs_data.sku_list.reduce((total, sku) => total + (Number(sku.stock) || 0), 0);
      }
    },
    submit() {
      this.$refs.form.validate(async (valid) => {
        if (valid) {
          if (this.form.is_attr == 1 && this.form.specs_data.spec_groups.length > 0) {
            if (this.form.specs_data.sku_list.length === 0) {
              return this.$message.error('请生成有效的SKU列表');
            }
            for (const sku of this.form.specs_data.sku_list) {
              if (!sku.price || parseFloat(sku.price) <= 0) return this.$message.error('所有SKU的售价都必须大于0');
              if (sku.stock === '' || parseInt(sku.stock) < 0) return this.$message.error('所有SKU的库存都必须大于等于0');
            }
          }
          this.submitLoading = true;
          try {
            const postData = JSON.parse(JSON.stringify(this.form));
            const res = await this.$request({
              url: '/plugin/mch/mall/goods/edit',
              method: 'post',
              data: {form: postData}
            });
            if (res.code === 0) {
              this.$message.success('保存成功！');
              this.$emit('success');
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
.goods-edit-form-container {
  padding: 0 10px;
}

.form-tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
}

.spec-group {
  border: 1px solid #DCDFE6;
  border-radius: 4px;
  padding: 10px;
  margin-bottom: 15px;
}

.spec-group-header {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  gap: 10px;
}

.spec-values > .el-tag {
  margin-right: 10px;
  margin-bottom: 10px;
}

.input-new-tag {
  width: 90px;
  margin-left: 10px;
  vertical-align: bottom;
}

.button-new-tag {
  margin-left: 10px;
  height: 32px;
  line-height: 30px;
  padding-top: 0;
  padding-bottom: 0;
}

.dialog-footer {
  text-align: right;
  margin-top: 20px;
  padding-bottom: 10px;
}

.nested-form-section {
  background-color: #f9f9f9;
  border: 1px solid #eee;
  border-radius: 5px;
  padding: 10px;
  margin-top: 10px;
}
</style>
