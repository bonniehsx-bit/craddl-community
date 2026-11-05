<template>
  <el-drawer
    title="应用插件中心"
    :visible.sync="dialogVisible"
    size="1100px"
    direction="rtl"
    append-to-body
    :wrapper-closable="true"
    @open="getList"
  >
    <div class="drawer-wrap">
      <div class="drawer-body" v-loading="loading">
      <!-- 搜索 / 分组 工具栏 -->
      <div class="plugin-toolbar">
        <el-input
          v-model="keyword"
          placeholder="搜索插件名称 / 标识 / 描述"
          prefix-icon="el-icon-search"
          clearable
          size="small"
          style="width: 260px;"
        ></el-input>
        <el-radio-group v-model="groupFilter" size="small" style="margin-left: 12px;">
          <el-radio-button label="all">全部</el-radio-button>
          <el-radio-button label="分润类">分润类</el-radio-button>
          <el-radio-button label="营销类">营销类</el-radio-button>
          <el-radio-button label="工具类">工具类</el-radio-button>
          <el-radio-button label="客户定制类">客户定制类</el-radio-button>
        </el-radio-group>
        <el-radio-group v-model="statusFilter" size="small" style="margin-left: 12px;">
          <el-radio-button label="all">不限</el-radio-button>
          <el-radio-button label="1">已安装</el-radio-button>
          <el-radio-button label="0">未安装</el-radio-button>
        </el-radio-group>
        <span class="plugin-count">共 {{ filteredList.length }} 个</span>
        <el-button
          type="primary"
          size="small"
          icon="el-icon-download"
          :loading="batchInstalling"
          :disabled="uninstalledCount === 0"
          style="margin-left: 12px;"
          @click="installAll"
        >
          一键安装全部<span v-if="uninstalledCount > 0">（{{ uninstalledCount }}）</span>
        </el-button>
      </div>

      <!-- 列表 -->
      <el-table :data="filteredList" border style="width: 100%" height="calc(100vh - 240px)">
        <template slot="empty">
          <span style="color: #909399;">没有匹配的插件</span>
        </template>

        <el-table-column label="图标" width="100" align="center">
          <template slot-scope="scope">
            <div class="flex-column-center">
              <el-avatar :size="50" :src="scope.row.logo || defaultLogo" shape="square"></el-avatar>
              <!-- 更换Logo: 仅已安装的插件可更换 -->
              <file-picker
                v-if="scope.row.status == 1"
                :multiple="false"
                @selected="(e) => handleLogoSelected(e, scope.row)"
              >
                <el-button type="text" size="mini" style="margin-top: 5px;">更换图标</el-button>
              </file-picker>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="label" label="插件名称" width="180" show-overflow-tooltip>
          <template slot-scope="scope">
            <span style="font-weight: bold; font-size: 15px;">{{ scope.row.label }}</span>
            <div style="font-size: 12px; color: #909399;">{{ scope.row.name }}</div>
          </template>
        </el-table-column>

        <el-table-column label="分类" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="mini" :type="groupTagType(scope.row.group_name)" effect="plain">
              {{ scope.row.group_name || '其他' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="版本" width="120" align="center">
          <template slot-scope="scope">
            <span>{{ scope.row.local_version || scope.row.version || '-' }}</span>
          </template>
        </el-table-column>

        <el-table-column prop="dsc" label="功能描述" min-width="250" show-overflow-tooltip></el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag type="success" v-if="scope.row.status == 1">已安装</el-tag>
            <el-tag type="info" v-if="scope.row.status == 0">未安装</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="170" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button
              type="primary"
              size="mini"
              plain
              v-if="scope.row.status == 0"
              @click="install(scope.row)"
            >安装</el-button>

            <!-- 未安装(从未安装/已卸载)的插件可彻底删除目录文件 -->
            <el-button
              type="danger"
              size="mini"
              icon="el-icon-delete"
              v-if="scope.row.status == 0"
              @click="remove(scope.row)"
            >删除</el-button>

            <el-button
              type="danger"
              size="mini"
              plain
              v-if="scope.row.status == 1"
              @click="uninstall(scope.row)"
            >卸载</el-button>
          </template>
        </el-table-column>
      </el-table>
      </div>
      <div class="drawer-footer">
        <el-button size="small" type="primary" @click="dialogVisible = false">确 定</el-button>
      </div>
    </div>
  </el-drawer>
</template>

<script>
import defaultLogo from '@/assets/logo/logo.png' // 请确保有默认logo

export default {
  name: "PluginManager",
  props: {
    // 通过 value 控制弹窗显示 (v-model)
    value: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      loading: false,
      list: [],
      keyword: '',
      groupFilter: 'all',
      statusFilter: 'all',
      defaultLogo: defaultLogo,
      batchInstalling: false
    };
  },
  computed: {
    dialogVisible: {
      get() {
        return this.value;
      },
      set(val) {
        this.$emit('input', val);
      }
    },
    // 未安装数量（用于一键安装按钮的徽标和禁用判断）
    uninstalledCount() {
      return this.list.filter(p => String(p.status) === '0').length;
    },
    // 客户端过滤：关键词 + 分组 + 安装状态
    filteredList() {
      const kw = this.keyword.trim().toLowerCase();
      return this.list.filter(p => {
        if (this.groupFilter !== 'all' && p.group_name !== this.groupFilter) return false;
        if (this.statusFilter !== 'all' && String(p.status) !== this.statusFilter) return false;
        if (kw) {
          const hay = ((p.label || '') + ' ' + (p.name || '') + ' ' + (p.dsc || '')).toLowerCase();
          if (hay.indexOf(kw) === -1) return false;
        }
        return true;
      });
    }
  },
  methods: {
    // 获取插件列表（后端一次性返回全部，过滤在前端完成）
    getList() {
      this.loading = true;
      this.$request({
        url: '/admin/plugin/list',
        method: 'get'
      }).then(res => {
        if (res.code == 0) {
          this.list = res.data.list || [];
        }
        this.loading = false;
      }).catch(() => {
        this.loading = false;
      });
    },

    // 分类标签颜色
    groupTagType(groupName) {
      return {
        '分润类': 'warning',
        '营销类': 'success',
        '工具类': 'info',
        '客户定制类': 'danger'
      }[groupName] || '';
    },

    // 一键安装全部未安装插件（顺序执行，避免后端事件/迁移并发冲突）
    installAll() {
      const targets = this.list.filter(p => String(p.status) === '0');
      if (targets.length === 0) {
        this.$message.info('当前没有可安装的插件');
        return;
      }
      this.$confirm(
        `即将批量安装 ${targets.length} 个插件，过程中请勿关闭窗口。是否继续？`,
        '一键安装全部',
        { type: 'warning', confirmButtonText: '开始安装' }
      ).then(async () => {
        this.batchInstalling = true;
        const loading = this.$loading({
          text: `正在安装 0/${targets.length}…`,
          spinner: 'el-icon-loading'
        });
        let okCount = 0;
        const failed = [];
        for (let i = 0; i < targets.length; i++) {
          const row = targets[i];
          loading.text = `正在安装 ${i + 1}/${targets.length}：${row.label}`;
          try {
            const res = await this.$request({
              url: '/admin/plugin/install',
              method: 'post',
              data: { name: row.name }
            });
            if (res.code == 0) {
              okCount++;
            } else {
              failed.push({ label: row.label, msg: res.msg || '未知错误' });
            }
          } catch (e) {
            failed.push({ label: row.label, msg: '请求异常' });
          }
        }
        loading.close();
        this.batchInstalling = false;
        // 刷新列表 + 通知父组件
        this.getList();
        this.$emit('plugin-changed');
        // 结果汇总
        if (failed.length === 0) {
          this.$message.success(`已成功安装 ${okCount} 个插件 🎉`);
        } else {
          const detail = failed.map(f => `• ${f.label}：${f.msg}`).join('<br>');
          this.$alert(
            `<div>成功 ${okCount} 个，失败 ${failed.length} 个：</div><div style="margin-top:8px; color:#f56c6c; font-size:13px;">${detail}</div>`,
            '安装结果',
            { dangerouslyUseHTMLString: true, confirmButtonText: '知道了' }
          );
        }
      }).catch(() => {});
    },

    // 安装插件
    install(row) {
      const loading = this.$loading({ text: '正在安装插件...', spinner: 'el-icon-loading' });
      this.$request({
        url: "/admin/plugin/install",
        method: 'post',
        data: {
          name: row.name
        }
      }).then(res => {
        loading.close();
        if (res.code == 0) {
          this.$message.success(res.msg);
          this.getList();
          // 触发父组件更新（比如更新创建商城时的插件列表）
          this.$emit('plugin-changed');
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        loading.close();
      });
    },

    // 卸载插件
    uninstall(row) {
      this.$confirm(`确定要卸载插件 [${row.label}] 吗？卸载后相关功能将不可用。`, '警告', {
        type: 'warning',
        confirmButtonText: '确定卸载',
        confirmButtonClass: 'el-button--danger'
      }).then(() => {
        const loading = this.$loading({ text: '正在卸载...', spinner: 'el-icon-loading' });
        return this.$request({
          url: "/admin/plugin/uninstall",
          method: 'post',
          data: {
            name: row.name
          }
        }).then(res => {
          loading.close();
          if (res.code == 0) {
            this.$message.success(res.msg);
            this.getList();
            this.$emit('plugin-changed');
          } else {
            this.$message.error(res.msg);
          }
        });
      }).catch(() => {});
    },

    // 彻底删除插件目录文件（危险操作，仅未安装插件可删）
    // 采用「输入插件标识」二次确认，避免误点导致不可恢复的文件删除
    remove(row) {
      this.$prompt(
        `<div style="line-height:1.75;">
           <p style="color:#f56c6c;font-weight:bold;font-size:14px;">⚠ 危险操作 · 不可恢复</p>
           <p>此操作将<b>彻底删除该插件目录下的所有文件</b>：</p>
           <p><code style="background:#f5f5f5;padding:2px 6px;border-radius:3px;">api/plugins/${row.name}/</code></p>
           <p style="color:#f56c6c;margin-top:6px;">若该插件文件仍被系统引用，删除后<b>可能导致系统不正常运行、甚至无法运行</b>。请确认此插件确实不再需要。</p>
           <p style="margin-top:8px;">如确认删除，请输入插件标识 <b style="color:#f56c6c;">${row.name}</b> ：</p>
         </div>`,
        '删除插件文件',
        {
          dangerouslyUseHTMLString: true,
          confirmButtonText: '彻底删除',
          cancelButtonText: '取消',
          confirmButtonClass: 'el-button--danger',
          inputPlaceholder: `请输入 ${row.name}`,
          inputValidator: (val) => (val === row.name ? true : '输入的插件标识不匹配')
        }
      ).then(({ value }) => {
        if (value !== row.name) return;
        const loading = this.$loading({ text: '正在删除插件文件...', spinner: 'el-icon-loading' });
        this.$request({
          url: '/admin/plugin/delete',
          method: 'post',
          data: { name: row.name }
        }).then(res => {
          loading.close();
          if (res.code == 0) {
            this.$message.success(res.msg);
            this.getList();
            this.$emit('plugin-changed');
          } else {
            this.$message.error(res.msg);
          }
        }).catch(() => {
          loading.close();
        });
      }).catch(() => {});
    },

    // 更改Logo
    handleLogoSelected(e, row) {
      if (e.length == 0) return;

      this.$request({
        url: "/admin/plugin/logo",
        method: 'post',
        data: {
          name: row.name,
          logo: e[0].url
        },
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg);
          row.logo = e[0].url; // 前端直接更新
        } else {
          this.$message.error(res.msg);
        }
      });
    }
  }
};
</script>

<style scoped>
.flex-column-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.plugin-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 0;
  margin-bottom: 14px;
}
.plugin-count {
  margin-left: auto;
  font-size: 13px;
  color: #909399;
}

/* el-drawer__body 默认 overflow:auto 会把 footer 一起卷走，关掉外层滚动让内部 .drawer-body 负责 */
::v-deep .el-drawer__body {
  padding: 0;
  overflow: hidden;
}

/* drawer 三段式 */
.drawer-wrap { display: flex; flex-direction: column; height: 100%; }
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}
.drawer-body::-webkit-scrollbar { width: 6px; }
.drawer-body::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }
.drawer-footer {
  flex-shrink: 0;
  padding: 12px 24px;
  border-top: 1px solid #EBEDF0;
  text-align: right;
  background: #fff;
}
</style>
