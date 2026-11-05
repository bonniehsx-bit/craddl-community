<template>
  <div class="tab-basic-container">

    <el-row :gutter="24" v-if="!hiddenFields.includes('cats')">
      <el-col :span="12" :xs="24">
        <el-form-item label="系统分类" prop="cat">
          <el-button size="small" @click="showSystemCatPicker = true">{{ sysCatName || '请选择系统分类' }}</el-button>
          <cat-picker :showCatPicker="showSystemCatPicker" url="/plugin/mch/admin/goods-cat/cat-list" @closed="showSystemCatPicker = false" @selected="onSystemCatSelected" :mch_id="0"></cat-picker>
        </el-form-item>
      </el-col>
      <el-col :span="12" :xs="24">
        <el-form-item label="本店展示分类" prop="mch_cats">
          <el-button size="small" type="success" @click="showMchCatPicker = true">选择本店私有分类</el-button>
          <div v-if="mchCatNames.length > 0" style="margin-top: 10px;">
              <el-tag v-for="(name, idx) in mchCatNames" :key="idx" size="small" closable @close="removeMchCat(idx)" style="margin-right: 5px;">{{ name }}</el-tag>
          </div>
          <cat-picker :showCatPicker="showMchCatPicker" url="/plugin/mch/admin/goods-cat/cat-list" @closed="showMchCatPicker = false" @selected="onMchCatSelected" :mch_id="1"></cat-picker>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="24">
      <el-col :span="24">
        <el-form-item label="商品名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入商品名称，建议50个字符以内"></el-input>
        </el-form-item>
      </el-col>

      <el-col :span="24">
        <el-form-item label="小标题" prop="subtitle">
          <el-input type="textarea" v-model="form.subtitle" :rows="2" placeholder="常用于商品卖点补充，将显示在商品名称下方 (选填)"></el-input>
        </el-form-item>
      </el-col>

      <el-col :span="12" :xs="24">
        <el-form-item label="服务标签" prop="service_tags">
          <!-- 下拉多选:原来一排带边框的勾选框,标签多了会折成好几行、很乱(与平台后台一致) -->
          <el-select v-model="form.service_tags" multiple filterable :multiple-limit="serviceTagLimit"
                     :placeholder="serviceTagOptions.length ? '选择服务标签' : '暂无可选标签'"
                     :disabled="!serviceTagOptions.length" style="width: 100%;">
            <el-option v-for="tag in serviceTagOptions" :key="tag.id" :label="tag.name" :value="tag.id">
              <span>{{ tag.name }}</span><span v-if="tag.is_own" class="own-tag">本店</span>
            </el-option>
          </el-select>
          <div class="form-tip" v-if="serviceTagOptions.length">
            显示在商品详情页价格下面那一排，最多选 {{ serviceTagLimit }} 个。本店自己的标签在「商品管理 → 服务标签」里加。
            <span class="tip-warn">标签只是展示文字：选了「全国包邮」结算照样按运费设置收钱。</span>
          </div>
          <div class="form-tip" v-else>还没有可选的服务标签，去「商品管理 → 服务标签」加几个。</div>
        </el-form-item>

        <el-form-item label="生产厂家" prop="factory">
          <el-input v-model="form.factory" placeholder="请输入生产厂家名称"></el-input>
        </el-form-item>
      </el-col>
      <el-col :span="12" :xs="24">
        <el-form-item label="分享标题" prop="share_title">
          <el-input v-model="form.share_title" placeholder="用户分享给好友时显示的自定义标题"></el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="24">
      <el-col :span="8" :xs="24" :sm="12">
        <el-form-item label="商品编号" prop="no">
          <el-input v-model="form.no" placeholder="如：SPU-001"></el-input>
        </el-form-item>
      </el-col>

      <el-col :span="8" :xs="24" :sm="12">
        <el-form-item label="计量单位" prop="unit">
          <el-input v-model="form.unit" placeholder="如：件、个、包"></el-input>
        </el-form-item>
      </el-col>

      <el-col :span="8" :xs="24" :sm="12">
        <el-form-item label="展示排序" prop="sort">
          <el-input v-model="form.sort" type="number" placeholder="0">
            <template slot="append">越小越靠前</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="24">
      <el-col :span="12" :xs="24">
        <el-form-item label="是否限购" prop="is_limit">
          <el-switch v-model="form.is_limit" :active-value="1" :inactive-value="0"></el-switch>
          <div class="form-tip mt-5">限制每个用户终身可购买的总数</div>
        </el-form-item>
      </el-col>

      <el-col :span="12" :xs="24" v-if="form.is_limit == 1">
        <el-form-item label="限购数量" prop="limit_num">
          <el-input type="number" v-model="form.limit_num" step="1"></el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-divider content-position="left" class="custom-divider">媒体素材</el-divider>

    <el-row :gutter="24">
      <el-col :span="24">
        <el-form-item label="缩略图" prop="cover_pic">
          <single-image-selector size="768*768" v-model="form.cover_pic"></single-image-selector>
          <div class="form-tip mt-5">建议尺寸：768*768 像素，该图片将作为商品列表页的主图展示。</div>
        </el-form-item>
      </el-col>

      <el-col :span="24">
        <el-form-item label="商品轮播图" prop="pic_list">
          <div class="media-container">
            <images-selector size="768*868" :count="6" :list="form.pic_list" v-model="form.pic_list"></images-selector>
          </div>
          <div class="form-tip mt-5">建议尺寸：768*868 像素，最多允许上传 6 张，可拖拽图片调整显示顺序。</div>
        </el-form-item>
      </el-col>
    </el-row>

  </div>
</template>

<script>
// 引入需要的子组件
import CatPicker from '@/components/mall/goods/CatPicker'

export default {
  name: "TabBasic",
  components: { CatPicker },
  props: {
    // 接收父组件的 form 对象
    form: {
      type: Object,
      required: true
    },
    hiddenFields: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      showSystemCatPicker: false,
      sysCatName: '',
      showMchCatPicker: false,
      mchCatNames: [],
      serviceTagOptions: [],
      serviceTagLimit: 6
    }
  },
  created() {
    this.getServiceTags();
  },
  watch: {
    // 回显系统分类名字
    'form.cat': {
      handler(val) {
        if (val && val.name) {
          this.sysCatName = val.name;
        } else if (val && val.id) {
          this.sysCatName = '已选 (ID: ' + val.id + ')';
        } else {
          this.sysCatName = '';
        }
      },
      immediate: true
    },
    // 监听表单获取回显的 mch_cats 数据，如果只有 ID 数组，尝试恢复显示。
    // 但是由于 mch_cats 只有 ID，没有 name，最完美的是后端提供 mch_cats 的 name。
    // 如果没有，我们就通过一次接口拿到所有分类来 map 名字。
    'form.mch_cats': {
      handler(val) {
        if (val && val.length > 0 && this.mchCatNames.length !== val.length) {
           this.resolveMchCatNames(val);
        } else if (!val || val.length === 0) {
           this.mchCatNames = [];
        }
      },
      immediate: true
    }
  },
  methods: {
    // 详情页服务标签的可选项:平台铺的 + 本店自建的(后端按登录商户圈定,不认前端传的商户)
    getServiceTags() {
      this.$request({
        url: '/plugin/mch/admin/goods/service-tag-selectable',
        method: 'get',
        data: {}
      }).then(res => {
        if (res.code !== 0) return;
        this.serviceTagOptions = res.data.list || [];
        this.serviceTagLimit = res.data.show_limit || 6;
      });
    },
    onSystemCatSelected(cats) {
      if (cats && cats.length > 0) {
        const selected = cats[cats.length - 1]; // 取最底层的分类
        this.$set(this.form, 'cat', { id: selected.id, name: selected.name });
        this.$set(this.form, 'cat_id', selected.id); // 兼容 Mch 后端验证所需
        this.sysCatName = selected.name;
      } else {
        this.$set(this.form, 'cat', { id: '', name: '' });
        this.$set(this.form, 'cat_id', '');
        this.sysCatName = '';
      }
    },
    onMchCatSelected(cats) {
      if (cats && cats.length > 0) {
        // Vue form.mch_cats reactivity
        this.$set(this.form, 'mch_cats', cats.map(c => c.id));
        this.mchCatNames = cats.map(c => c.name);
      } else {
        this.$set(this.form, 'mch_cats', []);
        this.mchCatNames = [];
      }
    },
    removeMchCat(idx) {
      this.mchCatNames.splice(idx, 1);
      this.form.mch_cats.splice(idx, 1);
    },
    resolveMchCatNames(ids) {
      // 通过一次拉取全量树，或者直接展示 "已选ID" 退化处理
      this.$request({
        url: '/plugin/mch/admin/goods-cat/cat-list',
        method: 'get',
        data: { mch_id: 1, is_tree: 1 } 
      }).then(res => {
        if (res.code === 0) {
           let names = [];
           const findName = (list) => {
              for (let i = 0; i < list.length; i++) {
                 if (ids.includes(list[i].id)) names.push(list[i].name);
                 if (list[i].children) findName(list[i].children);
                 if (list[i].child) findName(list[i].child);
              }
           };
           findName(res.data.list);
           this.mchCatNames = names;
        }
      });
    }
  }
}
</script>

<style scoped lang="scss">
.tab-basic-container {
  padding-right: 20px;
}

/* 统一规范提示文字的风格，取代杂乱的 inline style */
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 6px;
}

.mt-5 {
  margin-top: 5px;
}

/* 保证自定义选择器在栅格中占满整列 */
::v-deep .full-width-picker {
  width: 100%;
}
::v-deep .full-width-picker .el-input {
  width: 100%;
}

/* 视觉分割线 */
.custom-divider {
  margin: 30px 0 20px 0;

  ::v-deep .el-divider__text {
    color: #909399;
    font-size: 13px;
    background-color: #fdfdfd; /* 如果抽屉底色是白色，这里写 #fff 即可 */
  }
}

/* 隐藏输入框原生的上下微调箭头，保持极简 */
::v-deep input[type=number]::-webkit-inner-spin-button,
::v-deep input[type=number]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
::v-deep input[type=number] {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* 轮播图容器包裹，防止多图换行时紧贴着过于局促 */
.media-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px; /* 如果内部组件没有 margin，用 gap 会非常好用 */
}

.own-tag {
  margin-left: 6px;
  padding: 0 4px;
  font-size: 12px;
  color: #909399;
  background: #f4f4f5;
  border-radius: 2px;
}

.tip-warn {
  color: #E6A23C;
}
</style>
