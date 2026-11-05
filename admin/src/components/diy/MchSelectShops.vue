<template>
  <div class="diy-mch-select-shops" :style="outerStyle">
    <div class="inner-card" :style="cardStyle">
      <div class="header" v-if="safeData.title || safeData.subtitle">
        <div class="title-wrap">
          <div class="title" :style="titleStyle">{{ safeData.title || '精选商家' }}</div>
          <div class="subtitle" v-if="safeData.subtitle">{{ safeData.subtitle }}</div>
        </div>
        <div class="more" v-if="safeData.show_more == 1">查看更多 <i class="el-icon-arrow-right"></i></div>
      </div>

      <!-- 网格 2 列 -->
      <div v-if="safeData.style === 'grid'" class="grid-layout">
        <div class="shop-card" v-for="i in previewCount" :key="'grid-' + i" :style="itemStyle">
          <div class="cover"><i class="el-icon-shop"></i></div>
          <div class="card-body">
            <div class="name">商家名称{{ i }}</div>
            <div class="meta" v-if="safeData.show_rating == 1"><i class="el-icon-star-on star"></i> 4.{{ 9 - (i % 3) }}</div>
            <div class="addr" v-if="safeData.show_address == 1"><i class="el-icon-location-outline"></i> 示例地址·{{ i }}号</div>
          </div>
        </div>
      </div>

      <!-- 横向滚动 -->
      <div v-else-if="safeData.style === 'scroll'" class="scroll-layout">
        <div class="scroll-track">
          <div class="shop-card scroll-card" v-for="i in previewCount" :key="'scroll-' + i" :style="itemStyle">
            <div class="cover"><i class="el-icon-shop"></i></div>
            <div class="card-body">
              <div class="name">商家名称{{ i }}</div>
              <div class="meta" v-if="safeData.show_rating == 1"><i class="el-icon-star-on star"></i> 4.{{ 9 - (i % 3) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 列表 -->
      <div v-else class="list-layout">
        <div class="shop-row" v-for="i in previewCount" :key="'list-' + i" :style="itemStyle">
          <div class="cover"><i class="el-icon-shop"></i></div>
          <div class="info">
            <div class="name">商家名称{{ i }}</div>
            <div class="meta" v-if="safeData.show_rating == 1"><i class="el-icon-star-on star"></i> 4.{{ 9 - (i % 3) }} 分</div>
            <div class="addr" v-if="safeData.show_address == 1"><i class="el-icon-location-outline"></i> 示例地址·{{ i }}号</div>
          </div>
          <div class="enter-btn">进店</div>
        </div>
      </div>

      <div class="placeholder-tip">
        <i class="el-icon-info"></i>
        <template v-if="selectedCount > 0">预览以示例占位，实际展示你在右侧勾选的 {{ selectedCount }} 个商家（按勾选顺序）</template>
        <template v-else>请在右侧「选择商家」里勾选要展示的商户</template>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MchSelectShops',
  props: { data: { type: Object, default: () => ({}) } },
  computed: {
    safeData() {
      return Object.assign({
        title: '精选商家',
        subtitle: '为您甄选优质商户',
        mch_ids: [],
        show_rating: 1,
        show_address: 1,
        show_more: 0,
        style: 'grid',
        is_padding: 1,
        bg_color: '',
        title_color: '',
        item_bg_color: '',
      }, this.data || {})
    },
    selectedCount() {
      return Array.isArray(this.safeData.mch_ids) ? this.safeData.mch_ids.length : 0
    },
    // 预览卡片数量：勾了就按勾的数（最多 4 个占位即可示意），没勾给 3 个示意
    previewCount() {
      return this.selectedCount > 0 ? Math.min(4, this.selectedCount) : 3
    },
    outerStyle() {
      return { padding: this.safeData.is_padding == 1 ? '0 10px' : '0' }
    },
    cardStyle() {
      return this.safeData.bg_color ? { background: this.safeData.bg_color } : {}
    },
    titleStyle() {
      return this.safeData.title_color ? { color: this.safeData.title_color } : {}
    },
    itemStyle() {
      return this.safeData.item_bg_color ? { background: this.safeData.item_bg_color } : {}
    },
  },
}
</script>

<style lang="scss" scoped>
.diy-mch-select-shops { margin-bottom: 10px; }
.inner-card { background: #fff; padding: 12px; border-radius: 8px; }

.header {
  display: flex; justify-content: space-between; align-items: flex-end;
  margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f5f5f5;
  .title-wrap { flex: 1; min-width: 0; }
  .title { font-size: 16px; font-weight: bold; color: #303133; line-height: 1.3; }
  .subtitle { font-size: 11px; color: #909399; margin-top: 4px; }
  .more { font-size: 12px; color: #909399; margin-left: 12px; flex-shrink: 0; }
}

/* 网格 */
.grid-layout {
  display: flex; flex-wrap: wrap; gap: 8px;
  .shop-card {
    width: calc(50% - 4px); background: #f7f9fc; border-radius: 8px; overflow: hidden;
    .cover {
      height: 92px; background: linear-gradient(135deg, #e0ecfc 0%, #c7e0ff 100%);
      display: flex; align-items: center; justify-content: center;
      i { font-size: 34px; color: #5b8def; }
    }
    .card-body { padding: 8px; }
    .name { font-size: 13px; font-weight: 600; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .meta { font-size: 11px; color: #f59e0b; margin-top: 4px; .star { color: #f59e0b; } }
    .addr { font-size: 11px; color: #909399; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  }
}

/* 横滑 */
.scroll-layout {
  overflow-x: auto;
  &::-webkit-scrollbar { display: none; }
  .scroll-track { display: flex; gap: 8px; padding-bottom: 4px; }
  .scroll-card {
    width: 120px; flex-shrink: 0; background: #f7f9fc; border-radius: 8px; overflow: hidden;
    .cover {
      height: 80px; background: linear-gradient(135deg, #e0ecfc 0%, #c7e0ff 100%);
      display: flex; align-items: center; justify-content: center;
      i { font-size: 30px; color: #5b8def; }
    }
    .card-body { padding: 6px 8px; }
    .name { font-size: 12px; font-weight: 600; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .meta { font-size: 11px; color: #f59e0b; margin-top: 3px; .star { color: #f59e0b; } }
  }
}

/* 列表 */
.list-layout {
  .shop-row {
    display: flex; align-items: center; padding: 10px; background: #f7f9fc; border-radius: 8px; margin-bottom: 8px;
    &:last-child { margin-bottom: 0; }
    .cover {
      width: 64px; height: 64px; flex-shrink: 0; border-radius: 6px;
      background: linear-gradient(135deg, #e0ecfc 0%, #c7e0ff 100%);
      display: flex; align-items: center; justify-content: center;
      i { font-size: 28px; color: #5b8def; }
    }
    .info { flex: 1; min-width: 0; margin-left: 12px; }
    .name { font-size: 14px; font-weight: 600; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .meta { font-size: 12px; color: #f59e0b; margin-top: 4px; .star { color: #f59e0b; } }
    .addr { font-size: 12px; color: #909399; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .enter-btn {
      flex-shrink: 0; margin-left: 10px; background: linear-gradient(135deg, #5b8def 0%, #3a6fd8 100%);
      color: #fff; font-size: 12px; padding: 5px 14px; border-radius: 20px;
    }
  }
}

.placeholder-tip {
  margin-top: 8px; font-size: 11px; color: #c0c4cc; text-align: center;
  i { margin-right: 4px; }
}
</style>
