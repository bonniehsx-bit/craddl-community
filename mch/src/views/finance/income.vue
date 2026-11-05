<template>
    <div class="app-container">
        <el-card shadow="never">
            <!-- 选项卡与筛选 -->
            <div slot="header" class="clearfix">
                <span style="font-weight: bold; font-size: 16px;">账户余额明细</span>
            </div>

            <!-- 财务明细列表 -->
            <el-table :data="list" v-loading="loading" style="width: 100%" border size="small">
                <el-table-column type="index" label="序号" width="60" align="center"></el-table-column>

                <el-table-column prop="created_time" label="变动时间" width="180" align="center">
                    <template slot-scope="scope">
                        <i class="el-icon-time"></i> {{ scope.row.created_time }}
                    </template>
                </el-table-column>

                <el-table-column prop="log_type" label="类型" width="100" align="center">
                    <template slot-scope="scope">
                        <el-tag :type="scope.row.log_type === 'income' ? 'success' : 'danger'" size="small">
                            {{ scope.row.log_type === 'income' ? '收入' : '支出' }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="amount" label="变动金额" width="150" align="center">
                    <template slot-scope="scope">
                        <span
                            :style="{ color: scope.row.log_type === 'income' ? '#67C23A' : '#F56C6C', fontWeight: 'bold' }">
                            {{ scope.row.amount }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column prop="remark" label="变动说明" min-width="250">
                    <template slot-scope="scope">
                        <span style="color: #606266;">{{ scope.row.remark }}</span>
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
    name: 'FinanceIncome',
    data() {
        return {
            loading: false,
            list: [],
            total: 0,
            search: {
                page: 1,
                limit: 15
            }
        }
    },
    created() {
        this.loadData();
    },
    methods: {
        loadData() {
            this.loading = true;
            const url = '/plugin/mch/admin/mch/finance-dashboard';

            this.$request({
                url: url,
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
