<template>
  <div class="tab-basic-container">
    <el-row :gutter="24">

      <!-- ===== 左侧：文字信息 ===== -->
      <el-col :span="10" :xs="24" class="left-panel">
        <el-divider content-position="left" class="custom-divider">商品信息</el-divider>

        <el-form-item label="所属商家" prop="mch_id" :required="requireMch" v-if="!hiddenFields.includes('mch_id')">
          <mch-picker v-model="form.mch_id" class="full-width-picker"></mch-picker>
          <div class="form-tip">选择「平台自营」表示商品归属平台，选择具体商户则归属该商户。</div>
        </el-form-item>

        <div v-if="!hiddenFields.includes('cats')">
          <el-form-item label="系统分类" prop="cat">
            <cat-picker v-model="form.cat" class="full-width-picker"></cat-picker>
          </el-form-item>
          <el-form-item label="本店展示分类" prop="mch_cats" v-if="form.mch_id > 0">
            <el-select v-model="form.mch_cats" multiple placeholder="选填, 多选" style="width: 100%;">
              <el-option v-for="cat in mchCatOptions" :key="cat.id" :label="cat.name" :value="cat.id"></el-option>
            </el-select>
          </el-form-item>
        </div>

        <el-form-item label="商品名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入商品名称，建议50个字符以内"></el-input>
        </el-form-item>

        <el-form-item label="商品简称" prop="short_name">
          <el-input v-model="form.short_name" maxlength="255" placeholder="选填，仅用于订单列表导出时输出商品简称"></el-input>
        </el-form-item>

        <el-form-item v-if="form.source_url !== undefined && !hiddenFields.includes('source_url')" label="来源链接" prop="source_url">
          <el-input v-model.trim="form.source_url" maxlength="2048" clearable placeholder="选填，请粘贴以 https:// 或 http:// 开头的商品链接"></el-input>
          <div class="form-tip">记录 1688 等网站的商品来源，仅供后台查阅。</div>
        </el-form-item>

        <el-form-item label="小标题" prop="subtitle">
          <el-input type="textarea" v-model="form.subtitle" :rows="2" placeholder="卖点补充，显示在名称下方 (选填)"></el-input>
        </el-form-item>

        <el-form-item label="服务标签" prop="service_tags">
          <!-- 下拉多选:原来一排带边框的勾选框,标签多了会折成好几行、很乱 -->
          <el-select v-model="form.service_tags" multiple filterable :multiple-limit="serviceTagLimit"
                     :placeholder="serviceTagOptions.length ? '选择服务标签' : '暂无可选标签'"
                     :disabled="!serviceTagOptions.length" style="width: 100%;">
            <el-option v-for="tag in serviceTagOptions" :key="tag.id" :label="tag.name" :value="tag.id">
              <span>{{ tag.name }}</span><span v-if="tag.is_own" class="own-tag">商家自建</span>
            </el-option>
          </el-select>
          <div class="form-tip" v-if="serviceTagOptions.length">
            显示在详情页价格下面那一排，最多选 {{ serviceTagLimit }} 个。标签在「商品管理 → 服务标签」里维护。
            <span class="tip-warn">标签只是展示文字：选了「全国包邮」结算照样按运费设置收钱。</span>
          </div>
          <div class="form-tip" v-else>还没有可选的服务标签，去「商品管理 → 服务标签」加几个。</div>
        </el-form-item>

        <el-form-item label="生产厂家" prop="factory">
          <el-input v-model="form.factory" placeholder="请输入生产厂家名称"></el-input>
        </el-form-item>

        <el-form-item label="分享标题" prop="share_title">
          <el-input v-model="form.share_title" placeholder="用户分享给好友时显示的自定义标题"></el-input>
        </el-form-item>

        <el-form-item label="商品编号" prop="no">
          <el-input v-model="form.no" placeholder="如：SPU-001"></el-input>
        </el-form-item>

        <el-form-item label="计量单位" prop="unit">
          <el-input v-model="form.unit" placeholder="如：件、个、包"></el-input>
        </el-form-item>

        <el-form-item label="展示排序" prop="sort">
          <el-input v-model="form.sort" type="number" placeholder="0">
            <template slot="append">越小越靠前</template>
          </el-input>
        </el-form-item>

        <el-form-item label="是否限购" prop="is_limit">
          <el-switch v-model="form.is_limit" :active-value="1" :inactive-value="0"></el-switch>
          <span class="form-tip" style="margin-left:10px">限制每个用户终身可购买的总数</span>
        </el-form-item>

        <el-form-item label="限购数量" prop="limit_num" v-if="form.is_limit == 1">
          <el-input type="number" v-model="form.limit_num" step="1"></el-input>
        </el-form-item>
      </el-col>

      <!-- ===== 右侧：媒体素材 ===== -->
      <el-col :span="14" :xs="24" class="right-panel">
        <el-divider content-position="left" class="custom-divider">媒体素材</el-divider>

        <el-form-item label="缩略图" prop="cover_pic">
          <single-image-selector size="768*768" v-model="form.cover_pic"></single-image-selector>
          <div class="form-tip">建议尺寸：768*768 像素，商品列表主图。</div>
        </el-form-item>

        <el-form-item label="商品轮播图" prop="pic_list">
          <div class="media-container">
            <images-selector size="768*868" :count="6" :list="form.pic_list" v-model="form.pic_list"></images-selector>
          </div>
          <div class="form-tip">建议尺寸：768*868 像素，最多 6 张，可拖拽调序。</div>
        </el-form-item>
      </el-col>

    </el-row>
  </div>
</template>

<script>
// 引入需要的子组件
import CatPicker from '@/components/goods/CatPicker'
import MchPicker from '@/components/MchPicker/MchPicker.vue'

export default {
  name: "TabBasic",
  components: { CatPicker, MchPicker },
  props: {
    // 接收父组件的 form 对象
    form: {
      type: Object,
      required: true
    },
    hiddenFields: {
      type: Array,
      default: () => []
    },
    // 是否要求必填所属商家（必填时表单显示星号 + 提交校验）
    requireMch: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      mchCatOptions: [],
      serviceTagOptions: [],
      serviceTagLimit: 6
    }
  },
  watch: {
    'form.mch_id': {
      handler(val) {
        if (val > 0) {
          this.getMchCats(val);
        } else {
          this.mchCatOptions = [];
        }
        // 可选标签 = 平台的 + 这个商品所属商家自建的,换了商家要重拉
        this.getServiceTags(val);
      },
      immediate: true
    }
  },
  methods: {
    // 详情页服务标签的可选项。换商家后已勾的标签可能不再可选,顺手剔掉,免得存回去被后端过滤掉、界面却还打着勾
    getServiceTags(mch_id) {
      this.$request({
        url: '/mall/goods/service-tag-selectable',
        method: 'get',
        data: { goods_mch_id: mch_id || 0 }
      }).then(res => {
        if (res.code !== 0) return;
        this.serviceTagOptions = res.data.list || [];
        this.serviceTagLimit = res.data.show_limit || 6;
        const ids = this.serviceTagOptions.map(item => item.id);
        const picked = Array.isArray(this.form.service_tags) ? this.form.service_tags : [];
        this.form.service_tags = picked.filter(id => ids.includes(id));
      });
    },
    getMchCats(mch_id) {
      this.$request({
        url: '/mall/goods/cat-list',
        method: 'get',
        data: { mch_id: mch_id, type: 0 } // 只拿一级分类作为演示或统一获取
      }).then(res => {
        if (res.code === 0) {
          this.mchCatOptions = res.data.list || [];
        }
      });
    }
  }
}
</script>

<style scoped lang="scss">
.tab-basic-container {
  padding-right: 10px;
}

/* 左右分栏 */
.right-panel {
  border-left: 1px dashed #ebeef5;
  padding-left: 24px;
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

/* 统一规范提示文字的风格 */
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 4px;
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
  margin: 10px 0 20px 0;

  ::v-deep .el-divider__text {
    color: #909399;
    font-size: 13px;
    background-color: #fff;
  }
}

/* 隐藏输入框原生的上下微调箭头 */
::v-deep input[type=number]::-webkit-inner-spin-button,
::v-deep input[type=number]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
::v-deep input[type=number] {
  -moz-appearance: textfield;
}

/* 轮播图容器 */
.media-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
