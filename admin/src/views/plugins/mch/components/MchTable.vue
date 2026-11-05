<template>
  <div class="mch-table-container">
    <el-table
      :data="list"
      v-loading="loading"
      size="medium"
      border
      :header-cell-style="{ background: '#fafafa', color: '#606266' }"
      style="width: 100%"
    >
      <!-- ID -->
      <el-table-column prop="id" label="商户ID" width="80" align="center"></el-table-column>

      <!-- 商户信息 -->
      <el-table-column label="商户信息" min-width="250">
        <template slot-scope="scope">
          <div class="mch-info">
            <el-avatar shape="square" :size="50" :src="scope.row.logo" icon="el-icon-picture-outline"></el-avatar>
            <div class="mch-details">
              <div class="mch-name">{{ scope.row.mch_name }}</div>
              <div class="mch-contact">电话: {{ scope.row.tel || '-' }}</div>
            </div>
          </div>
        </template>
      </el-table-column>

      <!-- 店长信息 -->
      <el-table-column label="店长信息" width="200">
        <template slot-scope="scope">
          <div v-if="scope.row.user_info" class="user-info">
            <el-avatar :size="36" :src="scope.row.user_info.avatar_url" icon="el-icon-user-solid"></el-avatar>
            <div class="user-detail">
              <div class="nickname text-ellipsis">{{ scope.row.user_info.nickname }}</div>
              <div class="user-id">ID: {{ scope.row.user_id }}</div>
            </div>
          </div>
          <span v-else class="text-gray">未绑定</span>
        </template>
      </el-table-column>

      <!-- 商家类型 -->
      <!-- 注：type=3(酒店/民宿) 已废弃，新流程不再创建；
           列表仍展示存量数据，但酒店业务字段(星级/结算模式等)由 hotel 插件后台维护，
           不在此处暴露。需要查看/编辑酒店业务请进入「酒店插件 → 商户管理」。 -->
      <el-table-column label="商家类型" align="center" width="120">
        <template slot-scope="scope">
          <el-tag :type="getTypeTag(scope.row.type)" size="small" effect="plain">
            {{ getTypeText(scope.row.type) }}
          </el-tag>
          <div v-if="scope.row.type === 3" class="legacy-tip">存量数据</div>
        </template>
      </el-table-column>

      <!-- 审核状态 -->
      <el-table-column label="审核状态" align="center" width="100">
        <template slot-scope="scope">
          <el-tag :type="getStatusTagType(scope.row.status)" size="small">
            {{ getStatusText(scope.row.status) }}
          </el-tag>
          <el-popover
            v-if="scope.row.status === 2 && scope.row.remarks"
            placement="top"
            width="200"
            trigger="hover"
            :content="scope.row.remarks"
          >
            <i slot="reference" class="el-icon-warning-outline text-danger" style="margin-left: 5px; cursor: pointer;"></i>
          </el-popover>
        </template>
      </el-table-column>

      <!-- 入驻时间 -->
      <el-table-column label="入驻时间" width="160" align="center">
        <template slot-scope="scope">
          <div class="time-cell">{{ formatDate(scope.row.created_at) }}</div>
        </template>
      </el-table-column>

      <!-- 操作栏：mch 仅做平台级商户管理（编辑基础资料 / 审核 / 启用禁用 / 删除）
           业务专属配置(酒店/团购/技师等)由对应插件后台单独管理 -->
      <el-table-column label="操作" fixed="right" width="260" align="center">
        <template slot-scope="scope">
          <div class="action-buttons">
            <!-- 通用编辑 -->
            <el-button type="text" @click="$emit('edit', scope.row)">编辑</el-button>

            <el-button type="text" @click="$emit('dashboard', scope.row)">数据</el-button>

            <!-- 待审核状态 -->
            <el-button v-if="scope.row.status === 0" type="text" @click="$emit('review', scope.row)">审核</el-button>

            <!-- 正常状态 -> 禁用 -->
            <el-button
              v-if="scope.row.status === 1"
              type="text"
              class="text-warning"
              @click="$emit('change-status', scope.row, 3)"
            >禁用</el-button>

            <!-- 禁用状态 -> 启用 -->
            <el-button
              v-if="scope.row.status === 3"
              type="text"
              class="text-success"
              @click="$emit('change-status', scope.row, 1)"
            >启用</el-button>

            <!-- 删除 -->
            <el-button type="text" class="text-danger" @click="$emit('delete', scope.row)">删除</el-button>
          </div>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
export default {
  name: 'MchTable',
  props: {
    list: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  methods: {
    formatDate(timestamp) {
      if (!timestamp) return '-';
      const date = new Date(timestamp * 1000);
      return date.toLocaleString('zh-CN', { hour12: false });
    },
    getTypeText(type) {
      // 与其它地方文案保持一致（MchFilter / apply.vue / center.vue 同步）：
      //   1=线上商家, 2=线下门店, 3=酒店/民宿(已废弃，存量数据), 4=个人租户
      const map = { 1: '线上商家', 2: '线下门店', 3: '酒店/民宿', 4: '个人租户' };
      return map[type] || '未知';
    },
    getTypeTag(type) {
      // 与 center.vue 头部 type-chip 配色对齐：线上=蓝(info) / 门店=绿(success) / 酒店=橙(warning) / 个人=紫(primary)
      const map = { 1: 'info', 2: 'success', 3: 'warning', 4: 'primary' };
      return map[type] || '';
    },
    getStatusText(status) {
      const map = { 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已禁用' };
      return map[status] || '未知';
    },
    getStatusTagType(status) {
      const map = { 0: 'warning', 1: 'success', 2: 'danger', 3: 'info' };
      return map[status] || '';
    }
  }
}
</script>

<style scoped lang="scss">
.mch-info {
  display: flex;
  align-items: center;

  .mch-details {
    margin-left: 10px;
    flex: 1;
    overflow: hidden;

    .mch-name {
      font-weight: bold;
      font-size: 14px;
      color: #303133;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mch-contact {
      font-size: 12px;
      color: #909399;
    }
  }
}

.user-info {
  display: flex;
  align-items: center;

  .user-detail {
    margin-left: 10px;
    flex: 1;
    overflow: hidden;

    .nickname {
      font-size: 13px;
      color: #606266;
    }

    .user-id {
      font-size: 12px;
      color: #909399;
      margin-top: 2px;
    }
  }
}

.legacy-tip {
  margin-top: 4px;
  font-size: 11px;
  color: #C0C4CC;
}

.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.text-gray { color: #C0C4CC; font-size: 12px; }
.text-warning { color: #E6A23C; }
.text-success { color: #67C23A; }
.text-danger { color: #F56C6C; }
.text-primary { color: var(--admin-accent-text); }
.text-info { color: #909399; }

.action-buttons {
  .el-button {
    margin-left: 5px;
    margin-right: 5px;
  }
}
</style>
