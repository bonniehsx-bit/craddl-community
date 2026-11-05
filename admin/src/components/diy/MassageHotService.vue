<template>
  <div class="diy-massage-hot-service" :style="outerStyle">
   <div class="inner-card">
    <div class="header" v-if="safeData.title || safeData.subtitle">
      <div class="title-wrap">
        <div class="title">{{ safeData.title || '热门服务' }}</div>
        <div class="subtitle" v-if="safeData.subtitle">{{ safeData.subtitle }}</div>
      </div>
      <div class="more" v-if="safeData.show_more == 1">查看更多 <i class="el-icon-arrow-right"></i></div>
    </div>

    <!-- 网格 2 列 -->
    <div v-if="safeData.style === 'grid'" class="grid-layout">
      <div class="svc-card" v-for="i in safeData.limit" :key="'grid-' + i">
        <div class="cover">
          <i class="el-icon-picture-outline"></i>
          <span class="hot-badge" v-if="i === 1">🔥 热卖</span>
        </div>
        <div class="card-body">
          <div class="name">艾灸养生套餐{{ i }}</div>
          <div class="meta">60 分钟 · 高级技师</div>
          <div class="bottom">
            <div class="price" v-if="safeData.show_price == 1">
              <span class="symbol">¥</span><span class="num">{{ 199 - i * 10 }}</span>
            </div>
            <div class="sales" v-if="safeData.show_sales == 1">已售 {{ 200 - i * 30 }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 横向滚动 -->
    <div v-else-if="safeData.style === 'scroll'" class="scroll-layout">
      <div class="scroll-track">
        <div class="svc-card scroll-card" v-for="i in safeData.limit" :key="'scroll-' + i">
          <div class="cover">
            <i class="el-icon-picture-outline"></i>
          </div>
          <div class="card-body">
            <div class="name">艾灸养生套餐{{ i }}</div>
            <div class="bottom">
              <div class="price" v-if="safeData.show_price == 1">
                <span class="symbol">¥</span><span class="num">{{ 199 - i * 10 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 列表 -->
    <div v-else class="list-layout">
      <div class="svc-row" v-for="i in safeData.limit" :key="'list-' + i">
        <div class="cover">
          <i class="el-icon-picture-outline"></i>
        </div>
        <div class="info">
          <div class="name">艾灸养生套餐{{ i }}</div>
          <div class="meta">
            <span class="duration">60 分钟</span>
            <span class="sales" v-if="safeData.show_sales == 1">· 已售 {{ 200 - i * 30 }}</span>
          </div>
          <div class="bottom">
            <div class="price" v-if="safeData.show_price == 1">
              <span class="symbol">¥</span><span class="num">{{ 199 - i * 10 }}</span>
            </div>
            <div class="book-btn">立即预约</div>
          </div>
        </div>
      </div>
    </div>

    <div class="placeholder-tip">
      <i class="el-icon-info"></i>
      预览以示例占位，实际渲染按销量降序展示真实服务
    </div>
   </div>
  </div>
</template>

<script>
export default {
  name: 'MassageHotService',
  props: { data: { type: Object, default: () => ({}) } },
  computed: {
    safeData() {
      const conf = Object.assign({
        title: '热门服务',
        subtitle: '本周人气推荐',
        limit: 4,
        show_sales: 1,
        show_price: 1,
        show_more: 1,
        style: 'grid',
        is_padding: 1,
      }, this.data || {})
      conf.limit = Math.min(4, conf.limit)
      return conf
    },
    outerStyle() {
      return { padding: this.safeData.is_padding == 1 ? '0 10px' : '0' }
    },
  },
}
</script>

<style lang="scss" scoped>
.diy-massage-hot-service {
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
  .more { font-size: 12px; color: #909399; margin-left: 12px; flex-shrink: 0; }
}

/* 网格 */
.grid-layout {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  .svc-card {
    width: calc(50% - 4px);
    background: #fafafa;
    border-radius: 8px;
    overflow: hidden;

    .cover {
      height: 110px;
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      display: flex; align-items: center; justify-content: center;
      position: relative;
      i { font-size: 38px; color: #d97706; }
      .hot-badge {
        position: absolute;
        top: 6px; left: 6px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        padding: 1px 6px;
        border-radius: 3px;
      }
    }
    .card-body { padding: 8px; }
    .name {
      font-size: 13px; font-weight: 600; color: #303133;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .meta { font-size: 11px; color: #909399; margin: 4px 0; }
    .bottom {
      display: flex; align-items: center; justify-content: space-between; margin-top: 4px;
      .price { color: #ef4444; }
      .symbol { font-size: 11px; font-weight: 600; }
      .num { font-size: 16px; font-weight: 800; }
      .sales { font-size: 10px; color: #c0c4cc; }
    }
  }
}

/* 横滑 */
.scroll-layout {
  overflow-x: auto;
  &::-webkit-scrollbar { display: none; }
  .scroll-track {
    display: flex;
    gap: 8px;
    padding-bottom: 4px;
  }
  .scroll-card {
    width: 120px;
    flex-shrink: 0;
    background: #fafafa;
    border-radius: 8px;
    overflow: hidden;
    .cover {
      height: 90px;
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      display: flex; align-items: center; justify-content: center;
      i { font-size: 32px; color: #d97706; }
    }
    .card-body { padding: 6px 8px; }
    .name {
      font-size: 12px; font-weight: 600; color: #303133;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .bottom { margin-top: 4px; }
    .price { color: #ef4444; }
    .symbol { font-size: 10px; font-weight: 600; }
    .num { font-size: 14px; font-weight: 800; }
  }
}

/* 列表 */
.list-layout {
  .svc-row {
    display: flex;
    padding: 10px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 8px;
    &:last-child { margin-bottom: 0; }

    .cover {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      i { font-size: 30px; color: #d97706; }
    }
    .info {
      flex: 1; min-width: 0;
      margin-left: 12px;
      display: flex; flex-direction: column; justify-content: space-between;
    }
    .name {
      font-size: 14px; font-weight: 600; color: #303133;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .meta { font-size: 12px; color: #909399; }
    .bottom {
      display: flex; justify-content: space-between; align-items: center;
      .price { color: #ef4444; }
      .symbol { font-size: 12px; font-weight: 600; }
      .num { font-size: 17px; font-weight: 800; }
      .book-btn {
        background: linear-gradient(135deg, #f5b14a 0%, #e29028 100%);
        color: #fff;
        font-size: 12px;
        padding: 4px 12px;
        border-radius: 20px;
      }
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
