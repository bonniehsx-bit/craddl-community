<template>
    <div class="app-container">
        <el-card shadow="never">
            <!-- 顶部操作栏 -->
            <div slot="header" class="clearfix">
                <el-button type="primary" size="small" icon="el-icon-plus"
                    @click="dialogVisible = true">添加员工</el-button>
                <div style="float: right">
                    <el-input v-model="search.keyword" placeholder="搜员工名称/用户ID" size="small"
                        style="width: 250px; margin-right: 10px;" @keyup.enter.native="searchList" clearable
                        @clear="searchList"></el-input>
                    <el-button type="primary" size="small" @click="searchList">搜索</el-button>
                </div>
            </div>

            <!-- 员工列表 -->
            <el-table :data="list" v-loading="loading" style="width: 100%" border size="small">
                <el-table-column prop="user_id" label="用户ID" width="100" align="center"></el-table-column>

                <el-table-column label="员工信息" min-width="200">
                    <template slot-scope="scope">
                        <div style="display: flex; align-items: center;">
                            <el-avatar :size="40" :src="scope.row.avatar_url" style="margin-right: 15px;"></el-avatar>
                            <div>
                                <div style="font-weight: bold; margin-bottom: 5px;">{{ scope.row.nickname }}</div>
                                <div style="font-size: 12px; color: #909399;"><i class="el-icon-mobile-phone"></i> {{
                                    scope.row.mobile || '未绑定手机号' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="verify_count" label="单月核销数" width="150" align="center">
                    <template slot-scope="scope">
                        <el-tag size="small" type="success">{{ scope.row.verify_count }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="created_time" label="添加时间" width="180" align="center"></el-table-column>

                <el-table-column label="操作" width="150" align="center" fixed="right">
                    <template slot-scope="scope">
                        <el-button type="text" size="small" style="color: #F56C6C;" icon="el-icon-delete"
                            @click="handleDelete(scope.row)">移除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <pagination v-show="total > 0" :total="total" :page.sync="search.page" :limit.sync="search.limit"
                @pagination="loadData" />
        </el-card>

        <!-- 添加员工弹窗 -->
        <el-dialog title="添加员工" :visible.sync="dialogVisible" width="500px" @closed="resetSearchUser">
            <div style="display: flex; margin-bottom: 20px;">
                <el-input v-model="searchUserId" placeholder="请输入精确的用户ID进行搜索" size="small" style="margin-right: 10px;"
                    @keyup.enter.native="handleSearchUser" clearable></el-input>
                <el-button type="primary" size="small" :loading="searching" @click="handleSearchUser">搜索</el-button>
            </div>

            <!-- 搜索结果预览 -->
            <el-card v-if="searchResult" shadow="hover" style="margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center;">
                        <el-avatar :size="50" :src="searchResult.avatar_url" style="margin-right: 15px;"></el-avatar>
                        <div>
                            <div style="font-weight: bold;">{{ searchResult.nickname }}</div>
                            <div style="font-size: 12px; color: #909399;">ID: {{ searchResult.id }} | 手机号: {{
                                searchResult.mobile || '暂无' }}</div>
                        </div>
                    </div>
                    <el-button type="success" size="small" plain @click="handleAddClerk"
                        :loading="adding">确认添加</el-button>
                </div>
            </el-card>

            <div v-else-if="searched && !searchResult" style="text-align: center; color: #909399; padding: 20px 0;">
                未能找到该用户，请确认用户ID是否正确且该用户未加入本店。
            </div>

            <span slot="footer" class="dialog-footer">
                <el-button @click="dialogVisible = false" size="small">关 闭</el-button>
            </span>
        </el-dialog>

    </div>
</template>

<script>
export default {
    name: 'ClerkIndex',
    data() {
        return {
            loading: false,
            list: [],
            total: 0,
            search: {
                keyword: '',
                page: 1,
                limit: 15
            },

            // 弹窗相关
            dialogVisible: false,
            searchUserId: '',
            searching: false,
            searched: false,
            searchResult: null,
            adding: false
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
        loadData() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/clerk/index',
                method: 'get',
                data: this.search
            }).then(res => {
                this.loading = false;
                if (res.code === 0) {
                    this.list = res.data.list;
                    if (res.data.pagination) {
                        this.total = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
                    }
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.loading = false;
            });
        },

        // 移除员工
        handleDelete(row) {
            this.$confirm(`确定要将员工【${row.nickname}】移除吗？`, '移除确认', {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }).then(() => {
                this.$request({
                    url: '/plugin/mch/admin/clerk/delete',
                    method: 'post',
                    data: { id: row.id }
                }).then(res => {
                    if (res.code === 0) {
                        this.$message.success('移除成功');
                        this.loadData();
                    } else {
                        this.$message.error(res.msg);
                    }
                });
            }).catch(() => { });
        },

        // 搜索候选新员工
        handleSearchUser() {
            if (!this.searchUserId) {
                this.$message.warning('请输入搜索的用户ID');
                return;
            }
            this.searching = true;
            this.$request({
                url: '/plugin/mch/admin/clerk/search-user',
                method: 'get',
                data: { keyword: this.searchUserId }
            }).then(res => {
                this.searching = false;
                this.searched = true;
                if (res.code === 0 && res.data.list && res.data.list.length > 0) {
                    this.searchResult = res.data.list[0];
                } else {
                    this.searchResult = null;
                }
            }).catch(() => {
                this.searching = false;
            });
        },

        // 确认添加为员工
        handleAddClerk() {
            if (!this.searchResult) return;
            this.adding = true;
            this.$request({
                url: '/plugin/mch/admin/clerk/add',
                method: 'post',
                data: { user_id: this.searchResult.id }
            }).then(res => {
                this.adding = false;
                if (res.code === 0) {
                    this.$message.success('添加成功');
                    this.dialogVisible = false;
                    this.loadData();
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.adding = false;
            });
        },

        resetSearchUser() {
            this.searchUserId = '';
            this.searchResult = null;
            this.searched = false;
        }
    }
}
</script>
