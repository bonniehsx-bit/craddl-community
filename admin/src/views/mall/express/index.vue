<template>
    <div class="app-container">
        <el-card class="box-card">
            <div slot="header" class="clearfix">
                <span>快递公司管理</span>
                <el-button style="float: right; padding: 3px 0" type="text" @click="editEorder(null)">添加快递</el-button>
                <el-button style="float: right; padding: 3px 0; margin-right: 16px;" type="text" icon="el-icon-refresh"
                    :loading="syncLoading" @click="syncSystem">同步系统数据</el-button>
            </div>

            <!-- Search Area -->
            <div style="margin-bottom: 20px;">
                <el-input size="small" v-model="searchData.keyword" placeholder="搜索快递名称" style="width: 200px" clearable
                    @clear="search" @keyup.enter.native="search">
                    <el-button slot="append" icon="el-icon-search" @click="search"></el-button>
                </el-input>
            </div>

            <div>
                <el-table v-loading="loading" size="mini" :data="list" style="width: 100%" border>
                    <el-table-column prop="id" label="ID" width="80px"></el-table-column>
                    <el-table-column prop="name" label="快递名称"></el-table-column>
                    <el-table-column prop="code" label="快递鸟编码"></el-table-column>
                    <el-table-column prop="key" label="原核心Key"></el-table-column>
                    <el-table-column prop="ali_key" label="阿里菜鸟Key"></el-table-column>
                    <el-table-column prop="sort" label="排序" width="80px"></el-table-column>

                    <el-table-column label="操作" width="180px">
                        <template slot-scope="scope">
                            <el-button type='text' style="margin-right: 10px;"
                                @click="editEorder(scope.row)">编辑</el-button>
                            <el-tooltip class="btn" effect="dark" content="删除" placement="top">
                                <el-popconfirm confirmButtonText='确定' cancelButtonText='取消' icon="el-icon-info"
                                    iconColor="red" title="确定删除该快递？" @confirm="deleteEorder(scope.row.id)">
                                    <el-button type="text" size="mini" slot="reference">删除</el-button>
                                </el-popconfirm>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>
                <pagination :pagination="pagination" @pageChange="getList" v-model="page"></pagination>
            </div>
        </el-card>

        <!-- Edit Dialog -->
        <el-dialog :title="dialogTitle" :visible.sync="dialogVisible" width="500px">
            <el-form :model="form" :rules="rules" ref="form" label-width="120px" size="small">
                <el-form-item label="快递公司名称" prop="name">
                    <el-input v-model="form.name" placeholder="请输入快递名称, 如: 顺丰"></el-input>
                </el-form-item>
                <el-form-item label="快递鸟编码" prop="code">
                    <el-input v-model="form.code" placeholder="如: shunfeng (拼音)"></el-input>
                </el-form-item>
                <el-form-item label="核心Key" prop="key">
                    <el-input v-model="form.key" placeholder="如: SF"></el-input>
                </el-form-item>
                <el-form-item label="阿里菜鸟Key" prop="ali_key">
                    <el-input v-model="form.ali_key" placeholder="如: SF"></el-input>
                </el-form-item>
                <el-form-item label="排序" prop="sort">
                    <el-input-number v-model="form.sort" :min="1" :max="9999"></el-input-number>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button @click="dialogVisible = false" size="small">取 消</el-button>
                <el-button type="primary" @click="save" size="small" :loading="submitLoading">确 定</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    name: 'mall-express',
    data() {
        return {
            page: 1,
            pagination: null,
            list: [],
            loading: false,
            syncLoading: false,
            searchData: {
                keyword: ''
            },

            dialogVisible: false,
            dialogTitle: '添加快递',
            submitLoading: false,
            form: {
                id: null,
                name: '',
                code: '',
                key: '',
                ali_key: '',
                sort: 100
            },
            rules: {
                name: [
                    { required: true, message: '请输入快递公司名称', trigger: 'blur' }
                ]
            }
        }
    },
    created() {
        this.getList()
    },
    methods: {
        search() {
            this.page = 1;
            this.getList();
        },
        // 同步系统标准快递公司数据(幂等补齐;解决某些站点迁移未灌数据导致快递列表为空)
        syncSystem() {
            this.$confirm('将把系统标准快递公司(顺丰、圆通、中通等)补齐到本站。已存在的不会重复添加，可放心执行。', '同步系统数据', {
                confirmButtonText: '确定同步',
                cancelButtonText: '取消',
                type: 'info'
            }).then(() => {
                this.syncLoading = true;
                this.$request({
                    url: '/mall/mall/express-sync',
                    method: 'post'
                }).then(res => {
                    this.syncLoading = false;
                    if (res.code == 0) {
                        this.$message.success(res.msg);
                        this.getList();
                    } else {
                        this.$message.error(res.msg);
                    }
                }).catch(() => {
                    this.syncLoading = false;
                });
            }).catch(() => { });
        },
        getList() {
            this.loading = true;
            this.$request({
                url: '/mall/mall/express-list',
                data: Object.assign({ page: this.page }, this.searchData)
            }).then(res => {
                this.loading = false;
                if (res.code == 0) {
                    this.list = res.data.list;
                    this.pagination = res.data.pagination;
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.loading = false;
            })
        },
        deleteEorder(id) {
            this.$request({
                url: "/mall/mall/express-destroy",
                data: { id: id },
                method: 'post',
            }).then(res => {
                if (res.code == 0) {
                    this.$message.success(res.msg)
                    this.getList();
                } else {
                    this.$message.error(res.msg);
                }
            })
        },
        editEorder(row) {
            if (row) {
                this.dialogTitle = '编辑快递';
                this.form = Object.assign({}, row);
            } else {
                this.dialogTitle = '添加快递';
                this.form = {
                    id: null,
                    name: '',
                    code: '',
                    key: '',
                    ali_key: '',
                    sort: 100
                };
            }
            this.dialogVisible = true;
            this.$nextTick(() => {
                if (this.$refs.form) {
                    this.$refs.form.clearValidate();
                }
            });
        },
        save() {
            this.$refs.form.validate((valid) => {
                if (valid) {
                    this.submitLoading = true;
                    this.$request({
                        url: "/mall/mall/express-edit",
                        data: {
                            form: this.form
                        },
                        method: 'post',
                    }).then(res => {
                        this.submitLoading = false;
                        if (res.code == 0) {
                            this.$message.success(res.msg);
                            this.dialogVisible = false;
                            this.getList();
                        } else {
                            this.$message.error(res.msg);
                        }
                    }).catch(() => {
                        this.submitLoading = false;
                    });
                }
            });
        }
    }
}
</script>

<style scoped></style>
