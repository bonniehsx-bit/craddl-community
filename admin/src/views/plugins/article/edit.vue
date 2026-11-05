<template>
  <el-drawer :visible.sync="drawerVisible" title="文章编辑" size="50%" :before-close="handleClose" :append-to-body="true">
    <div style="padding: 20px; padding-bottom: 80px;">
      <el-form ref="form" :model="form" label-width="120px" size="mini">
        <el-form-item label="文章分类">
          <el-select v-model="form.cat_id" placeholder="请选择">
            <el-option v-for="item in cat_list" :key="item.id" :label="item.name" :value="item.id">
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="文章标题">
          <el-input v-model="form.title"></el-input>
        </el-form-item>
        <el-form-item label="小标题">
          <el-input v-model="form.sub_title"></el-input>
        </el-form-item>
        <el-form-item label="作者">
          <el-input v-model="form.created_by"></el-input>
        </el-form-item>
        <el-form-item label="阅读量">
          <el-input v-model="form.views"></el-input>
        </el-form-item>
        <el-form-item label="是否发布">
          <el-radio v-model="form.is_published" :label="0">否</el-radio>
          <el-radio v-model="form.is_published" :label="1">是</el-radio>
        </el-form-item>
        <el-form-item label="封面图">
          <single-image-selector :url="form.cover_pic" v-model="form.cover_pic">
          </single-image-selector>
        </el-form-item>
        <el-form-item label="视频">
          <template v-if="form.video">
            <div class="flex-row">
              <div flex="main:center cross:center"
                   class="add-image-btn flex-x-center flex-y-center">
                <video :src="form.video" alt="" style="width: 100%;height: 100%;"/>
                <div class="flex-x-center flex-y-center pic-del" @click="form.video = ''">
                  <i class="el-icon-close"></i>
                </div>
              </div>
            </div>
          </template>
          <file-picker type="video" :max="1" :multiple="false" v-model="form.video"
                       v-if="form.video == '' || !form.video">
            <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center">
              <i class="el-icon-upload"></i>
            </div>
          </file-picker>
        </el-form-item>
        <el-form-item label="详情">
          <rich-text v-model="form.detail"></rich-text>
        </el-form-item>
        <el-form-item label="挂商品">
          <div class="goods-box">
            <draggable v-model="goodsList" handle=".g-drag" animation="150">
              <div v-for="(g, i) in goodsList" :key="g.id" class="g-row" :class="{ off: g.offline }">
                <i class="el-icon-rank g-drag" title="拖动调整顺序"></i>
                <image-view :src="g.cover_pic" class="g-img"></image-view>
                <div class="g-name">
                  <div class="g-title" :title="g.name">{{ g.name }}</div>
                  <div class="g-price">¥{{ g.price }}<el-tag v-if="g.offline" size="mini" type="info" style="margin-left:6px">已下架 · 手机端不显示</el-tag></div>
                </div>
                <el-input v-model="g.recommend" maxlength="60" size="mini" class="g-rec" placeholder="一句推荐语(选填,显示在商品卡片上)"></el-input>
                <el-radio v-model="mainId" :label="g.id" class="g-main">底栏主推</el-radio>
                <el-button type="text" size="mini" style="color:#f56c6c" @click="goodsList.splice(i, 1)">移除</el-button>
              </div>
            </draggable>
            <goods-picker @selected="onPickGoods" :max="GOODS_MAX">
              <el-button type="primary" plain size="mini" icon="el-icon-plus" :disabled="goodsList.length >= GOODS_MAX">添加商品 ({{ goodsList.length }}/{{ GOODS_MAX }})</el-button>
            </goods-picker>
            <div class="g-tip">文章里会显示「文中好物」商品卡,手机端底部有常驻购买条,显示「底栏主推」那件;下架或没库存的商品手机端自动不显示。</div>
          </div>
        </el-form-item>
      </el-form>
    </div>

    <div class="drawer-footer">
      <el-button @click="handleClose" size="small">取 消</el-button>
      <el-button type="primary" @click="save" size="small">保 存</el-button>
    </div>
  </el-drawer>
</template>
<script>
import RichText from '@/components/common/RichText'
import draggable from 'vuedraggable'

export default {
  name: 'article-edit',
  components: {
    RichText,
    draggable,
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    articleId: {
      type: [String, Number],
      default: ''
    }
  },
  data() {
    return {
      drawerVisible: false,
      GOODS_MAX: 6,
      goodsList: [], // [{id,name,cover_pic,price,recommend,is_main,offline}]
      mainId: 0,
      cat_list: [],
      form: {
        cat_id: '',
        id: '',
        video: '',
        title: '',
        cover_pic: '',
        detail: '',
        created_by: '',
        views: 0,
        is_published: 1,
        sub_title: '',
      },
    }
  },
  watch: {
    visible(val) {
      this.drawerVisible = val;
      if (val) {
        this.getCatList();
        if (this.articleId) {
          this.form.id = this.articleId;
          this.getArticle();
        } else {
          this.resetForm();
        }
      }
    },
    drawerVisible(val) {
      if (!val) {
        this.$emit('update:visible', false);
      }
    }
  },
  methods: {
    handleClose() {
      this.drawerVisible = false;
    },
    resetForm() {
      this.goodsList = [];
      this.mainId = 0;
      this.form = {
        cat_id: '',
        id: '',
        video: '',
        title: '',
        cover_pic: '',
        detail: '',
        created_by: '',
        views: 0,
        is_published: 1,
        sub_title: '',
      };
    },
    detailChange(e) {
      this.form.detail = e.content
    },
    getCatList() {
      this.$request({
        url: '/plugin/article/mall/article/all-cat',
      }).then(res => {
        if (res.code == 0) {
          this.cat_list = res.data.list
        }
      })
    },
    getArticle() {
      this.$request({
        url: '/plugin/article/mall/article/article',
        data: {id: this.form.id},

      }).then(res => {
        if (res.code == 0) {
          if (res.data.article) {
            // 没分类的文章(cat_id 为空)也要能打开编辑
            this.form = Object.assign({}, this.form, res.data.article, { cat_id: res.data.article.cat_id || '' })
          }
          this.goodsList = (res.data.goods_list || []).map(g => Object.assign({}, g))
          const main = this.goodsList.find(g => g.is_main)
          this.mainId = main ? main.id : (this.goodsList[0] ? this.goodsList[0].id : 0)
        }
      })
    },
    onPickGoods(selection) {
      selection.forEach(g => {
        if (this.goodsList.length >= this.GOODS_MAX) return
        if (!this.goodsList.some(x => x.id === g.id)) {
          this.goodsList.push({ id: g.id, name: g.name, cover_pic: g.cover_pic, price: g.price, recommend: '', is_main: 0, offline: 0 })
        }
      })
      if (!this.mainId && this.goodsList.length) this.mainId = this.goodsList[0].id
    },
    save() {
      // 顺序即排序;主推只有一个
      const goods_list = this.goodsList.map(g => ({ goods_id: g.id, recommend: g.recommend || '', is_main: g.id === this.mainId ? 1 : 0 }))
      this.$request({
        url: '/plugin/article/mall/article/article',
        data: Object.assign({}, this.form, { goods_list: JSON.stringify(goods_list) }),
        method: 'post',
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg);
          this.handleClose();
          this.$emit('success');
        } else {
          this.$message.error(res.msg);
        }
      })

    },
  },
}
</script>

<style scoped>
.goods-box { width: 100%; }
.g-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; margin-bottom: 8px; border: 1px solid #ebeef5; border-radius: 6px; background: #fafafa; }
.g-row.off { opacity: .6; }
.g-drag { cursor: move; color: #c0c4cc; font-size: 16px; }
.g-img { width: 44px; height: 44px; flex: none; border-radius: 4px; }
.g-name { width: 180px; flex: none; min-width: 0; }
.g-title { font-size: 13px; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.g-price { font-size: 12px; color: #f56c6c; margin-top: 2px; }
.g-rec { flex: 1; min-width: 0; }
.g-main { margin-right: 0; flex: none; }
.g-tip { margin-top: 8px; font-size: 12px; color: #909399; line-height: 1.6; }
.drawer-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 10px 20px;
  background-color: #fff;
  border-top: 1px solid #e8e8e8;
  text-align: right;
  z-index: 100;
}
</style>
