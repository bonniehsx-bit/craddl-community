<template>
  <div class="diy-massage-new-therapist" :style="outerStyle">
   <div class="inner-card">
    <div class="header" v-if="safeData.title || safeData.subtitle">
      <div class="title-wrap">
        <div class="title">{{ safeData.title || '新晋技师' }}</div>
        <div class="subtitle" v-if="safeData.subtitle">{{ safeData.subtitle }}</div>
      </div>
      <div class="more" v-if="safeData.show_more == 1">查看更多 <i class="el-icon-arrow-right"></i></div>
    </div>

    <!-- 横滑卡片 -->
    <div v-if="safeData.style === 'scroll'" class="scroll-layout">
      <div class="scroll-track">
        <div class="th-card scroll-card" v-for="i in safeData.limit" :key="'scroll-' + i">
          <div class="avatar-wrap">
            <div class="avatar">
              <i class="el-icon-user"></i>
            </div>
            <span class="new-tag" v-if="i <= 2">NEW</span>
          </div>
          <div class="name">技师{{ i }}</div>
          <div class="cert">中医康复理疗</div>
          <div class="meta" v-if="safeData.show_rating == 1">
            <i class="el-icon-star-on"></i> 5.0
          </div>
          <div class="orders" v-if="safeData.show_orders == 1">已服务 {{ i * 18 }} 单</div>
        </div>
      </div>
    </div>

    <!-- 网格 -->
    <div v-else class="grid-layout">
      <div class="th-card" v-for="i in safeData.limit" :key="'grid-' + i">
        <div class="avatar-wrap">
          <div class="avatar">
            <i class="el-icon-user"></i>
          </div>
          <span class="new-tag" v-if="i <= 2">NEW</span>
        </div>
        <div class="info">
          <div class="name-row">
            <span class="name">技师{{ i }}</span>
            <span class="cert-tag">证</span>
          </div>
          <div class="cert">中医康复理疗</div>
          <div class="bottom">
            <span class="rating" v-if="safeData.show_rating == 1">
              <i class="el-icon-star-on"></i> 5.0
            </span>
            <span class="orders" v-if="safeData.show_orders == 1">{{ i * 18 }} 单</span>
          </div>
        </div>
      </div>
    </div>

    <div class="placeholder-tip">
      <i class="el-icon-info"></i>
      预览以示例占位，实际按入驻时间倒序展示真实技师
    </div>
   </div>
  </div>
</template>

<script>
export default {
  name: 'MassageNewTherapist',
  props: { data: { type: Object, default: () => ({}) } },
  computed: {
    safeData() {
      const conf = Object.assign({
        title: '新晋技师',
        subtitle: '认证通过 · 持证上岗',
        limit: 4,
        show_rating: 1,
        show_orders: 1,
        show_more: 1,
        style: 'scroll',
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
.diy-massage-new-therapist {
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

/* 横滑 */
.scroll-layout {
  overflow-x: auto;
  &::-webkit-scrollbar { display: none; }
  .scroll-track { display: flex; gap: 10px; padding: 4px 0; }
  .scroll-card {
    width: 110px;
    flex-shrink: 0;
    text-align: center;
    background: #fafafa;
    border-radius: 8px;
    padding: 12px 6px;

    .avatar-wrap {
      position: relative;
      width: 60px;
      height: 60px;
      margin: 0 auto 8px;
    }
    .avatar {
      width: 100%; height: 100%;
      background: linear-gradient(135deg, #c7d2fe 0%, #818cf8 100%);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      i { font-size: 32px; color: #fff; }
    }
    .new-tag {
      position: absolute;
      top: -2px; right: -4px;
      background: #ef4444;
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 8px;
      border: 1.5px solid #fff;
    }
    .name { font-size: 13px; font-weight: 600; color: #303133; }
    .cert {
      font-size: 10px;
      color: #818cf8;
      background: #eef2ff;
      padding: 1px 6px;
      border-radius: 4px;
      display: inline-block;
      margin-top: 4px;
    }
    .meta {
      font-size: 11px; color: #909399; margin-top: 4px;
      i { color: #f5b14a; }
    }
    .orders { font-size: 10px; color: #c0c4cc; margin-top: 2px; }
  }
}

/* 网格 */
.grid-layout {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  .th-card {
    width: calc(50% - 4px);
    display: flex;
    background: #fafafa;
    border-radius: 8px;
    padding: 8px;

    .avatar-wrap {
      position: relative;
      width: 50px;
      height: 50px;
      flex-shrink: 0;
    }
    .avatar {
      width: 100%; height: 100%;
      background: linear-gradient(135deg, #c7d2fe 0%, #818cf8 100%);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      i { font-size: 24px; color: #fff; }
    }
    .new-tag {
      position: absolute;
      top: -2px; right: -2px;
      background: #ef4444;
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 8px;
      border: 1.5px solid #fff;
    }
    .info { flex: 1; min-width: 0; margin-left: 8px; }
    .name-row {
      display: flex; align-items: center; gap: 4px;
      .name { font-size: 13px; font-weight: 600; color: #303133; }
      .cert-tag {
        font-size: 10px;
        background: #818cf8;
        color: #fff;
        padding: 0 4px;
        border-radius: 3px;
      }
    }
    .cert {
      font-size: 10px;
      color: #818cf8;
      background: #eef2ff;
      padding: 1px 6px;
      border-radius: 4px;
      display: inline-block;
      margin-top: 2px;
    }
    .bottom {
      display: flex; gap: 8px;
      font-size: 11px;
      color: #909399;
      margin-top: 4px;
      .rating i { color: #f5b14a; }
      .orders { color: #c0c4cc; }
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
