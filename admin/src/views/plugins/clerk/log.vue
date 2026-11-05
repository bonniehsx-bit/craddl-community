<template>
    <el-card shadow="never" style="border:0" body-style="background-color: #f3f3f3;padding: 10px 0 0;">
        <div slot="header">
            <div class="card-header-flex">
                <span>平台核销大盘</span>
                <div>
                    <el-button type="primary" size="small" @click="exportData">导出报表</el-button>
                </div>
            </div>
        </div>
        <div class="table-body">
            <div class="search-box">
                <el-form :inline="true" :model="search" size="small">
                    <el-form-item label="核销时间">
                        <el-date-picker
                                v-model="dateRange"
                                type="daterange"
                                value-format="yyyy-MM-dd"
                                range-separator="至"
                                start-placeholder="开始日期"
                                end-placeholder="结束日期"
                                @change="onDateChange"
                                clearable>
                        </el-date-picker>
                    </el-form-item>
                    <el-form-item>
                        <el-input placeholder="核销流转单号/操作人" v-model="search.keyword" clearable @clear="getList" @keyup.enter.native="getList">
                            <el-button slot="append" icon="el-icon-search" @click="getList"></el-button>
                        </el-input>
                    </el-form-item>
                </el-form>
            </div>
            
            <el-table v-loading="listLoading" :data="list" border style="width: 100%">
                <el-table-column prop="verify_time_text" label="核销时间" width="160"></el-table-column>
                <el-table-column prop="clerk_name" label="操作人" width="150"></el-table-column>
                <el-table-column prop="verify_type_name" label="业务类型" width="150">
                    <template slot-scope="scope">
                        <el-tag size="small" :type="scope.row.verify_type === 'normal' ? 'success' : 'primary'">
                            {{scope.row.verify_type_name}}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="order_no" label="业务单号/券码" width="220"></el-table-column>
                <el-table-column label="商品快照明细" min-width="250">
                    <template slot-scope="scope">
                        <div v-for="(item, index) in scope.row.products_snapshot" :key="index" style="display: flex;align-items: center;margin-bottom: 5px;">
                            <el-image style="width: 40px; height: 40px; margin-right: 10px; border-radius: 4px;" :src="item.image" fit="cover"></el-image>
                            <div style="flex:1;min-width:0;">
                                <div style="display: block;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">{{item.name}}</div>
                                <div style="font-size: 12px;color: #999;display: block;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;" v-if="item.spec">{{item.spec}} x{{item.num}}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="remark" label="流转系统日志" min-width="180">
                    <template slot-scope="scope">
                        <div style="font-size: 12px;color: #666;line-height:1.5;">{{scope.row.remark}}</div>
                    </template>
                </el-table-column>
            </el-table>

            <div style="text-align: right;margin: 20px 0;">
                <el-pagination @current-change="pagination" background layout="prev, pager, next"
                               :page-count="pageCount" :current-page="current_page"></el-pagination>
            </div>
        </div>
    </el-card>
</template>

<script>
    export default {
        name: "index",
        data() {
            return {
                list: [],
                listLoading: false,
                page: 1,
                pageCount: 0,
                current_page: 1,
                dateRange: [],
                search: {
                    keyword: '',
                    start_date: '',
                    end_date: '',
                },
            };
        },
        mounted() {
            this.getList();
        },
        methods: {
            onDateChange(val) {
                if (val) {
                    this.search.start_date = val[0];
                    this.search.end_date = val[1];
                } else {
                    this.search.start_date = '';
                    this.search.end_date = '';
                }
                this.page = 1;
                this.getList();
            },
            pagination(currentPage) {
                this.page = currentPage;
                this.getList();
            },
            getList() {
                this.listLoading = true;
                this.$request({
                    url: 'plugin/clerk/mall/log/index',
                    data: {
                        page: this.page,
                        ...this.search
                    },
                    method: 'get'
                }).then(e => {
                    if (e.code === 0) {
                        this.list = e.data.list;
                        this.pageCount = e.data.pagination.page_count;
                        this.current_page = e.data.pagination.current_page;
                    } else {
                        this.$message.error(e.msg);
                    }
                    this.listLoading = false;
                }).catch(e => {
                    this.listLoading = false;
                });
            },
            exportData() {
                this.$message.info('报表导出功能开发中...');
            }
        }
    }
</script>

<style scoped>
    .table-body {
        padding: 20px;
        background-color: #fff;
    }
    .search-box {
        margin-bottom: 20px;
    }
</style>
