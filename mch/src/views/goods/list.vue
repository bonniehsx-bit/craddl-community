<template>
    <div class="app-container">
        <el-card class="box-card" shadow="never">
            <div slot="header" class="page-header">
                <span>商品管理</span>
                <el-button type="success" size="mini" icon="el-icon-plus" @click="handleAdd">添加商品</el-button>
            </div>

            <!-- Filters -->
            <el-form :inline="true" :model="search" size="mini" class="filter-form shrink-0"
                @submit.native.prevent>
                <el-form-item label="商品名称">
                    <el-input v-model="search.keyword" placeholder="请输入商品名称" clearable style="width: 180px;"
                        @keyup.enter.native="searchList"></el-input>
                </el-form-item>

                <el-form-item label="上架状态">
                    <el-select v-model="search.status" placeholder="全部状态" style="width: 120px;" @change="searchList">
                        <el-option label="全部" :value="-1"></el-option>
                        <el-option label="销售中" :value="1"></el-option>
                        <el-option label="已下架" :value="0"></el-option>
                    </el-select>
                </el-form-item>

                <el-form-item label="审核状态">
                    <el-select v-model="search.is_check" placeholder="全部" style="width: 120px;" @change="searchList">
                        <el-option label="全部" :value="-1"></el-option>
                        <el-option label="待审核" :value="0"></el-option>
                        <el-option label="已通过" :value="1"></el-option>
                        <el-option label="被驳回" :value="2"></el-option>
                    </el-select>
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" icon="el-icon-search" @click="searchList">搜索</el-button>
                    <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
                </el-form-item>

                <!-- 批量操作 -->
                <el-form-item>
                    <span v-if="selectedIds.length > 0" class="selected-count">
                        已选 <b>{{ selectedIds.length }}</b> 项
                    </span>
                    <el-button type="success" plain icon="el-icon-top" :disabled="selectedIds.length === 0"
                        @click="handleBatchStatus(1)">批量上架</el-button>
                    <el-button type="warning" plain icon="el-icon-bottom" :disabled="selectedIds.length === 0"
                        @click="handleBatchStatus(0)">批量下架</el-button>
                </el-form-item>
            </el-form>

            <!-- Data Table -->
            <div class="table-wrapper flex-1">
                <div class="absolute-table-container">
                    <el-table :data="list" v-loading="loading" border height="100%" size="small"
                        @selection-change="handleSelectionChange" @row-dblclick="handleEdit"
                        style="width: 100%; cursor: pointer;"
                        :header-cell-style="{ background: '#f5f7fa', color: '#606266' }">
                        <el-table-column type="selection" width="45" align="center" fixed></el-table-column>
                        <el-table-column prop="id" label="ID" width="70" align="center" fixed></el-table-column>

                        <el-table-column label="商品信息" min-width="250" fixed show-overflow-tooltip>
                            <template slot-scope="scope">
                                <div style="display: flex; align-items: center;">
                                    <el-image
                                        style="width: 50px; height: 50px; border-radius: 4px; border: 1px solid #eee; margin-right: 10px; flex-shrink: 0;"
                                        :src="scope.row.cover_pic" :preview-src-list="[scope.row.cover_pic]">
                                    </el-image>
                                    <span class="text-ellipsis-2">{{ scope.row.name }}</span>
                                </div>
                            </template>
                        </el-table-column>

                        <el-table-column label="价格(元)" width="110" align="center">
                            <template slot-scope="scope">
                                <span style="color: #f56c6c; font-weight: bold;">¥{{ scope.row.price }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column prop="stock" label="库存" width="90" align="center"></el-table-column>
                        <el-table-column prop="sales" label="实际销量" width="90" align="center"></el-table-column>

                        <el-table-column label="审核状态" width="100" align="center">
                            <template slot-scope="scope">
                                <el-tag size="mini" type="info" v-if="scope.row.is_check == 0">待审核</el-tag>
                                <el-tag size="mini" type="success" v-else-if="scope.row.is_check == 1">已通过</el-tag>
                                <el-tag size="mini" type="danger" v-else-if="scope.row.is_check == 2">已被驳回</el-tag>
                                <el-tag size="mini" type="info" v-else>未知</el-tag>
                            </template>
                        </el-table-column>

                        <el-table-column label="上架状态" width="100" align="center">
                            <template slot-scope="scope">
                                <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0"
                                    active-color="#13ce66" inactive-color="#ff4949"
                                    @change="handleStatusChange(scope.row)">
                                </el-switch>
                            </template>
                        </el-table-column>

                        <el-table-column label="创建时间" width="150" align="center">
                            <template slot-scope="scope">
                                <span>{{ formatTime(scope.row.created_at) }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column label="操作" width="140" align="center" fixed="right">
                            <template slot-scope="scope">
                                <el-button type="text" size="small" icon="el-icon-edit"
                                    @click="handleEdit(scope.row)">编辑</el-button>
                                <el-button type="text" size="small" icon="el-icon-delete" style="color: #f56c6c;"
                                    @click="handleDelete(scope.row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination-container shrink-0">
                <pagination v-show="total > 0" :total="total" :page.sync="search.page" :limit.sync="search.limit"
                    @pagination="loadData" />
            </div>

        </el-card>

        <!-- 编辑 / 新增抽屉 -->
        <goods-edit-drawer :goods-id="currentEditId" :visible.sync="editVisible"
            :hidden-tabs="['integral', 'member_price', 'permission', 'status']" api-url="/plugin/mch/admin/goods/edit"
            @success="handleEditSuccess"></goods-edit-drawer>
    </div>
</template>

<script>
import GoodsEditDrawer from '@/components/goods/GoodsEditDrawer'

export default {
    name: 'MchGoodsList',
    components: { GoodsEditDrawer },
    data() {
        return {
            loading: false,
            list: [],
            search: {
                page: 1,
                limit: 20,
                keyword: '',
                status: -1,
                is_check: -1
            },
            total: 0,
            editVisible: false,
            currentEditId: 0,
            selectedIds: []
        }
    },
    created() {
        this.loadData();
    },
    methods: {
        searchList() {
            this.search.page = 1;
            this.loadData();
        },
        resetSearch() {
            this.search = {
                page: 1,
                limit: 20,
                keyword: '',
                status: -1,
                is_check: -1
            };
            this.loadData();
        },
        loadData() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/goods/index',
                method: 'get',
                data: this.search
            }).then(res => {
                this.loading = false;
                if (res.code === 0) {
                    this.list = res.data.list || [];
                    if (res.data.pagination) {
                        this.total = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
                    }
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(err => {
                this.loading = false;
            });
        },
        handleSelectionChange(selection) {
            this.selectedIds = selection.map(item => item.id);
        },
        handleBatchStatus(status) {
            if (this.selectedIds.length === 0) {
                this.$message.warning('请至少选择一件商品');
                return;
            }
            const actionText = status === 1 ? '上架' : '下架';
            this.$confirm(`确定要批量${actionText}选中的 ${this.selectedIds.length} 件商品吗?`, '提示', {
                type: 'warning'
            }).then(() => {
                this.loading = true;
                this.$request({
                    url: '/plugin/mch/admin/goods/batch-status-change',
                    method: 'post',
                    data: { ids: this.selectedIds, status: status }
                }).then(res => {
                    this.loading = false;
                    if (res.code === 0) {
                        this.$message.success(res.msg);
                        this.selectedIds = [];
                        this.loadData();
                    } else {
                        this.$message.error(res.msg);
                    }
                }).catch(() => {
                    this.loading = false;
                });
            }).catch(() => { });
        },
        handleAdd() {
            this.currentEditId = 0;
            this.editVisible = true;
        },
        handleEdit(row) {
            this.currentEditId = row.id;
            this.editVisible = true;
        },
        handleEditSuccess() {
            this.editVisible = false;
            this.loadData();
        },
        handleStatusChange(row) {
            this.$request({
                url: '/plugin/mch/admin/goods/update-status',
                method: 'post',
                data: {
                    id: row.id,
                    status: row.status
                }
            }).then(res => {
                if (res.code === 0) {
                    this.$message.success('状态更新成功');
                } else {
                    this.$message.error(res.msg);
                    // 状态回滚
                    row.status = row.status === 1 ? 0 : 1;
                }
            }).catch(err => {
                row.status = row.status === 1 ? 0 : 1;
            });
        },
        handleDelete(row) {
            this.$confirm('此操作将永久删除该商品, 是否继续?', '提示', {
                type: 'warning'
            }).then(() => {
                this.$request({
                    url: '/plugin/mch/admin/goods/delete',
                    method: 'post',
                    data: { id: row.id }
                }).then(res => {
                    if (res.code === 0) {
                        this.$message.success('删除成功');
                        this.loadData();
                    } else {
                        this.$message.error(res.msg);
                    }
                });
            }).catch(() => { });
        },
        formatTime(timestamp) {
            if (!timestamp) return '';
            // 如果是 10 位时间戳（秒），则乘以 1000 转换为毫秒
            let time = timestamp.toString().length === 10 ? parseInt(timestamp) * 1000 : parseInt(timestamp);
            let date = new Date(time);
            if (isNaN(date.getTime())) return timestamp; // 备用方案，万一本来就是字符串
            let y = date.getFullYear();
            let m = (date.getMonth() + 1).toString().padStart(2, '0');
            let d = date.getDate().toString().padStart(2, '0');
            let h = date.getHours().toString().padStart(2, '0');
            let min = date.getMinutes().toString().padStart(2, '0');
            let s = date.getSeconds().toString().padStart(2, '0');
            return `${y}-${m}-${d} ${h}:${min}:${s}`;
        }
    }
}
</script>

<style scoped lang="scss">
/* §14.1.1 全屏 Flex + 表格内部滚动 */
.app-container {
    height: calc(100vh - 85px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 15px;
    box-sizing: border-box;
    background: #f0f2f5;
}

.box-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    border: none;
}

/* 把 card body 变成 flex 容器，表格才能内部滚动 */
.box-card ::v-deep .el-card__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filter-form {
    padding-top: 4px;
}

.shrink-0 {
    flex-shrink: 0;
}

.flex-1 {
    flex: 1;
}

/* absolute 定位破解 el-table 在 flex 下的高度计算 bug */
.table-wrapper {
    flex: 1;
    position: relative;
    min-height: 0;
}

.absolute-table-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

.pagination-container {
    padding-top: 10px;
    text-align: right;
}

.selected-count {
    margin-right: 10px;
    font-size: 13px;
    color: #606266;

    b {
        color: #409EFF;
    }
}

.text-ellipsis-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
    font-size: 13px;
    line-height: 1.5;
}
</style>
