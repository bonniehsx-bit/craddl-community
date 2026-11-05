<template>
  <div class="diy-article-list" :style="outerStyle">
    <div class="article-inner" :style="innerStyle">

      <!-- 头部：与 C 端一致，标题为空时仍保留「更多」入口 -->
      <div class="group-header" :class="{ 'no-title': !safeData.title }">
        <span class="header-title" v-if="safeData.title">{{ safeData.title }}</span>
        <span class="more">更多 <i class="el-icon-arrow-right"></i></span>
      </div>

      <div class="article-content">
        <!-- 空状态：编辑器里给出提示；C 端此时整块不渲染 -->
        <div v-if="!list.length" class="empty-tip">
          <i class="el-icon-document"></i>
          <div>请添加文章</div>
        </div>

        <!-- 大图 -->
        <template v-else-if="style === 'big'">
          <div v-for="(item, index) in list" :key="index" class="art-big">
            <el-image v-if="item.cover_pic" :src="item.cover_pic" fit="cover" class="big-cover">
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
            <div class="big-info">
              <div class="art-title">{{ item.title || '文章标题' }}</div>
              <div class="art-desc" v-if="desc(item)">{{ desc(item) }}</div>
              <div class="art-meta">{{ views(item) }} 人浏览</div>
            </div>
          </div>
        </template>

        <!-- 纯文字 -->
        <template v-else-if="style === 'text'">
          <div v-for="(item, index) in list" :key="index" class="art-text">
            <div class="art-title">{{ item.title || '文章标题' }}</div>
            <div class="art-desc" v-if="desc(item)">{{ desc(item) }}</div>
            <div class="art-meta">{{ views(item) }} 人浏览</div>
          </div>
        </template>

        <!-- 左文右图（默认）：无封面时文字占满整行 -->
        <template v-else>
          <div v-for="(item, index) in list" :key="index" class="art-row">
            <div class="row-info">
              <div class="row-text">
                <div class="art-title">{{ item.title || '文章标题' }}</div>
                <div class="art-desc" v-if="desc(item)">{{ desc(item) }}</div>
              </div>
              <div class="art-meta">{{ views(item) }} 人浏览</div>
            </div>
            <div class="row-img" v-if="item.cover_pic">
              <el-image :src="item.cover_pic" fit="cover" style="width:100%;height:100%;display:block;">
                <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
              </el-image>
            </div>
          </div>
        </template>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'ArticleList',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    list() {
      const list = this.safeData.list;
      return Array.isArray(list) ? list : [];
    },
    // 存量页面没有 style 字段，兜底成原有的左文右图
    style() {
      const s = this.safeData.style;
      return (s === 'big' || s === 'text') ? s : 'list';
    },
    outerStyle() {
      return {
        padding: this.safeData.is_padding == 1 ? '0 10px' : '0'
      };
    },
    innerStyle() {
      return {
        borderRadius: this.safeData.is_circle == 1 ? '10px' : '0',
        backgroundColor: this.safeData.background || '#ffffff',
        overflow: 'hidden'
      };
    }
  },
  methods: {
    // 字段名以 Article 表为准（sub_title / views）；info、click_count 是老快照的键
    desc(item) {
      return item.sub_title || item.info || '';
    },
    views(item) {
      return Number(item.views || item.click_count || 0);
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-article-list {
  overflow: hidden;
}

.article-inner {
  padding: 0 15px;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 0 8px;
  border-bottom: 1px solid #f5f5f5;

  &.no-title {
    justify-content: flex-end;
    border-bottom: none;
    padding-bottom: 0;
  }

  .header-title {
    font-size: 16px;
    font-weight: bold;
    color: #333;
  }

  .more {
    font-size: 12px;
    color: #999;
  }
}

.empty-tip {
  padding: 40px 0;
  text-align: center;
  color: #909399;
  font-size: 12px;
  i { font-size: 24px; margin-bottom: 5px; }
}

/* ── 三种布局共用的文字样式 ── */
.art-title {
  font-size: 15px;
  font-weight: bold;
  color: #333;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.art-desc {
  font-size: 12px;
  color: #999;
  line-height: 1.4;
  margin-top: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.art-meta {
  font-size: 11px;
  color: #ccc;
  margin-top: 6px;
}

.image-slot {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 20px;
  background: #f5f7fa;
}

/* ── 左文右图 ── */
.art-row {
  display: flex;
  justify-content: space-between;
  padding: 15px 0;
  border-bottom: 1px solid #f5f5f5;

  &:last-child { border-bottom: none; }

  .row-info {
    flex: 1;
    min-width: 0; // 不加这条，长标题会把右侧封面挤出容器
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .row-img {
    width: 110px;
    height: 80px;
    margin-left: 15px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
    background: #f5f7fa;
  }
}

/* ── 大图 ── */
.art-big {
  padding-bottom: 15px;
  margin-bottom: 15px;
  border-bottom: 1px solid #f5f5f5;

  &:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
  }

  .big-cover {
    width: 100%;
    height: 160px;
    display: block;
    border-radius: 6px;
    background: #f5f7fa;
  }

  .big-info { margin-top: 8px; }
}

/* ── 纯文字 ── */
.art-text {
  padding: 13px 0;
  border-bottom: 1px solid #f5f5f5;

  &:last-child { border-bottom: none; }
}
</style>
