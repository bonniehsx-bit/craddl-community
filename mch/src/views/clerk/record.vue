<template>
    <div class="app-container">
        <el-card shadow="never">
            <!-- 顶部操作栏 -->
            <div slot="header" class="clearfix">
                <span style="font-weight: bold; margin-right: 20px;">核销记录追踪</span>

                <el-select v-model="search.clerk_id" placeholder="全部核销员" size="small" clearable @change="searchList"
                    style="width: 150px; margin-right: 10px;">
                    <el-option v-for="item in clerkList" :key="item.id" :label="item.name" :value="item.id">
                    </el-option>
                </el-select>

                <el-date-picker v-model="search.month" type="month" value-format="yyyy-MM" placeholder="按月份筛选"
                    size="small" style="width: 150px;" @change="searchList" clearable>
                </el-date-picker>

                <el-button type="primary" size="small" @click="searchList" style="margin-left: 10px;">查询</el-button>
                <el-button size="small" @click="resetSearch">重置</el-button>
            </div>

            <!-- 记录列表 -->
            <el-table :data="list" v-loading="loading" style="width: 100%" border size="small">
                <el-table-column type="index" label="序号" width="60" align="center"></el-table-column>

                <el-table-column prop="order_no" label="凭证条码" min-width="200">
                    <template slot-scope="scope">
                        <div style="font-family: Consolas, monospace; font-weight: bold; color: #409EFF;">
                            {{ scope.row.order_no }}
                        </div>
                        <el-tag size="mini" type="success" effect="dark" v-if="scope.row.verify_type_name" style="margin-top: 5px;">
                            {{ scope.row.verify_type_name }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="核销商品摘要" min-width="200">
                    <template slot-scope="scope">
                        <div v-if="scope.row.products_snapshot && scope.row.products_snapshot.length > 0">
                            {{ scope.row.products_snapshot[0].name }}
                        </div>
                        <div v-else style="color: #909399; font-size: 13px;">未知或无挂载商品</div>
                    </template>
                </el-table-column>

                <el-table-column prop="remark" label="操作摘要" min-width="200">
                    <template slot-scope="scope">
                        <div style="color: #E6A23C; font-size: 13px;" v-if="scope.row.remark">
                            {{ scope.row.remark }}
                        </div>
                        <div v-else style="color: #909399;">-</div>
                    </template>
                </el-table-column>

                <el-table-column prop="clerk_name" label="核销专员" width="120" align="center">
                    <template slot-scope="scope">
                        <el-tag size="small" effect="plain" type="info">{{ scope.row.clerk_name }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="verify_time" label="核销时间" width="160" align="center">
                    <template slot-scope="scope">
                        <span style="font-size: 13px; color: #606266;">
                            <i class="el-icon-time"></i> {{ scope.row.verify_time }}
                        </span>
                    </template>
                </el-table-column>
            </el-table>

            <pagination v-show="total > 0" :total="total" :page.sync="search.page" :limit.sync="search.limit"
                @pagination="loadData" />
        </el-card>

    </div>
</template>

<script>
export default {
    name: 'ClerkRecord',
    data() {
        return {
            loading: false,
            list: [],
            total: 0,
            search: {
                clerk_id: '',
                month: '',
                page: 1,
                limit: 15
            },
            clerkList: [] // 下拉框所有核销员
        }
    },
    created() {
        this.getAllClerks();
        this.loadData();
    },
    methods: {
        // 获取核销员下拉列表
        getAllClerks() {
            this.$request({
                url: '/plugin/mch/admin/clerk/all-clerks',
                method: 'get'
            }).then(res => {
                if (res.code === 0) {
                    this.clerkList = res.data.list;
                }
            });
        },

        searchList() {
            this.search.page = 1;
            this.loadData();
        },

        resetSearch() {
            this.search.clerk_id = '';
            this.search.month = '';
            this.searchList();
        },

        loadData() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/clerk/records',
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
        }
    }
}
</script>
