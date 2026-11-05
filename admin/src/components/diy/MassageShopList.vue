<template>
  <div class="diy-massage-shop-list" :style="outerStyle">
   <div class="inner-card">
    <!-- 标题栏 -->
    <div class="header" v-if="safeData.title || safeData.subtitle">
      <div class="title-wrap">
        <div class="title">{{ safeData.title || '附近艾灸门店' }}</div>
        <div class="subtitle" v-if="safeData.subtitle">{{ safeData.subtitle }}</div>
      </div>
      <div class="more" v-if="safeData.show_more == 1">查看更多 <i class="el-icon-arrow-right"></i></div>
    </div>

    <!-- 列表样式 -->
    <div v-if="safeData.style === 'list'" class="list-layout">
      <div class="shop-item" v-for="i in safeData.limit" :key="'list-' + i">
        <div class="cover">
          <i class="el-icon-shop"></i>
        </div>
        <div class="info">
          <div class="name-row">
            <span class="name">艾灸示例门店{{ i }}号</span>
            <span class="cert-tag" v-if="i === 1">金牌</span>
          </div>
          <div class="meta-row" v-if="safeData.show_rating == 1">
            <i class="el-icon-star-on"></i>
            <span>4.8</span>
            <span class="dot">·</span>
            <span>128 条评价</span>
          </div>
          <div class="addr-row">
            <i class="el-icon-location-outline"></i>
            <span class="addr">广东省佛山市禅城区祖庙路 ××× 号</span>
            <span v-if="safeData.show_distance == 1" class="distance">1.2km</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 网格样式 -->
    <div v-else class="grid-layout">
      <div class="shop-card" v-for="i in safeData.limit" :key="'grid-' + i">
        <div class="cover">
          <i class="el-icon-shop"></i>
          <span v-if="safeData.show_distance == 1" class="distance-tag">1.2km</span>
        </div>
        <div class="card-body">
          <div class="name">艾灸示例门店{{ i }}号</div>
          <div class="meta" v-if="safeData.show_rating == 1">
            <i class="el-icon-star-on"></i> 4.8 · 128
          </div>
        </div>
      </div>
    </div>

    <div class="placeholder-tip">
      <i class="el-icon-info"></i>
      该组件展示真实门店数据，预览中以示例占位
    </div>
   </div>
  </div>
</template>

<script>
export default {
  name: 'MassageShopList',
  props: {
    data: { type: Object, default: () => ({}) },
  },
  computed: {
    safeData() {
      return Object.assign({
        title: '附近艾灸门店',
        subtitle: '认准平台正品门店',
        limit: 3,           // 预览只画 3 个示例，真实运行时按 data.limit
        show_distance: 1,
        show_rating: 1,
        show_more: 1,
        style: 'list',
        is_padding: 1,
      }, this.data || {}, {
        // 预览阶段最多画 3 张（即使配 10），免得右侧栏拖太长
        limit: Math.min(3, (this.data && this.data.limit) || 3),
      })
    },
    outerStyle() {
      return { padding: this.safeData.is_padding == 1 ? '0 10px' : '0' }
    },
  },
}
</script>

<style lang="scss" scoped>
.diy-massage-shop-list {
  margin-bottom: 10px;
}
.inner-card {
  background: #fff;
  padding: 12px;
  border-radius: 8px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f5f5f5;

  .title-wrap { flex: 1; min-width: 0; }
  .title { font-size: 16px; font-weight: bold; color: #303133; line-height: 1.3; }
  .subtitle { font-size: 11px; color: #909399; margin-top: 4px; }
  .more { font-size: 12px; color: #909399; flex-shrink: 0; margin-left: 12px; }
}

/* 列表 */
.list-layout {
  .shop-item {
    display: flex;
    padding: 10px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 8px;
    &:last-child { margin-bottom: 0; }

    .cover {
      width: 80px; height: 80px;
      background: #ecf5ff;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      i { font-size: 32px; color: #409EFF; }
    }
    .info {
      flex: 1; min-width: 0;
      margin-left: 12px;
      display: flex; flex-direction: column; justify-content: space-between;
    }
    .name-row {
      display: flex; align-items: center; gap: 6px;
      .name { font-size: 14px; font-weight: 600; color: #303133; }
      .cert-tag {
        font-size: 10px;
        color: #fff;
        background: linear-gradient(135deg, #f5b14a 0%, #e29028 100%);
        padding: 1px 6px;
        border-radius: 3px;
      }
    }
    .meta-row {
      font-size: 12px; color: #909399;
      i { color: #f5b14a; margin-right: 2px; }
      .dot { margin: 0 4px; }
    }
    .addr-row {
      font-size: 12px; color: #909399;
      display: flex; align-items: center;
      i { margin-right: 2px; flex-shrink: 0; }
      .addr {
        flex: 1; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      }
      .distance {
        flex-shrink: 0; margin-left: 8px;
        color: #409EFF; font-weight: 600;
      }
    }
  }
}

/* 网格 */
.grid-layout {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  .shop-card {
    width: calc(50% - 4px);
    background: #fafafa;
    border-radius: 6px;
    overflow: hidden;

    .cover {
      height: 100px;
      background: #ecf5ff;
      display: flex; align-items: center; justify-content: center;
      position: relative;
      i { font-size: 36px; color: #409EFF; }
      .distance-tag {
        position: absolute;
        top: 6px; right: 6px;
        background: rgba(0,0,0,0.5); color: #fff;
        font-size: 10px; padding: 2px 6px; border-radius: 3px;
      }
    }
    .card-body { padding: 8px; }
    .name {
      font-size: 13px; font-weight: 600; color: #303133;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .meta {
      font-size: 11px; color: #909399; margin-top: 3px;
      i { color: #f5b14a; }
    }
  }
}

.placeholder-tip {
  margin-top: 8px;
  font-size: 11px;
  color: #c0c4cc;
  text-align: center;
  i { margin-right: 4px; }
}
</style>
