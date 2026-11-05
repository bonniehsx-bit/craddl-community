<template>
    <el-card shadow="never" style="border:0" body-style="background-color: #f3f3f3;padding: 10px 0 0;">
        <div slot="header">
            <div class="card-header-flex">
                <span>平台核销员管理</span>
                <div>
                    <el-button type="primary" @click="editClerk" size="small">添加核销员</el-button>
                </div>
            </div>
        </div>
        <div class="table-body">
            <div class="search-box">
                <el-input size="small" v-model="search.keyword" placeholder="核销员姓名/昵称/手机号" clearable @clear="getList" @keyup.enter.native="getList">
                    <el-button slot="append" icon="el-icon-search" @click="getList"></el-button>
                </el-input>
            </div>
            <el-table v-loading="listLoading" :data="list" border style="width: 100%">
                <el-table-column prop="id" label="ID" width="100"></el-table-column>
                <el-table-column label="核销员信息">
                    <template slot-scope="scope">
                        <div style="display: flex;align-items: center">
                            <el-avatar size="small" :src="scope.row.user.avatar_url"></el-avatar>
                            <span style="margin-left: 10px;">{{scope.row.user.nickname}}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="user.mobile" label="手机号码" width="150"></el-table-column>
                <el-table-column prop="name" label="姓名/备注"></el-table-column>
                <el-table-column label="状态" width="120">
                    <template slot-scope="scope">
                        <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0" @change="switchStatus(scope.row)"></el-switch>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150">
                    <template slot-scope="scope">
                        <el-button type="text" size="small" icon="el-icon-edit" @click="editClerk(scope.row)">编辑</el-button>
                        <el-button type="text" size="small" icon="el-icon-delete" style="color:#F56C6C" @click="deleteClerk(scope.row.id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div style="text-align: right;margin: 20px 0;">
                <el-pagination @current-change="pagination" background layout="prev, pager, next"
                               :page-count="pageCount" :current-page="current_page"></el-pagination>
            </div>
        </div>

        <el-dialog :title="dialogForm.id ? '编辑核销员' : '添加核销员'" :visible.sync="dialogVisible" width="30%">
            <el-form @submit.native.prevent :model="dialogForm" :rules="rules" ref="dialogForm" label-width="100px">
                <el-form-item label="人员选择" prop="user_id" v-if="!dialogForm.id">
                    <el-autocomplete v-model="dialogForm.nickname" value-key="nickname" :fetch-suggestions="querySearchAsync"
                                     placeholder="输入昵称 / 手机号 / 用户ID 搜索" @select="handleSelect">
                        <template slot-scope="{ item }">
                            <div style="display: flex; align-items: center; justify-content: space-between">
                                <div style="display: flex;align-items: center">
                                    <img :src="item.avatar_url" style="width: 30px;height: 30px;border-radius: 50%;margin-right: 10px;">
                                    <span>{{ item.nickname }}</span>
                                    <span style="color:#909399;font-size:12px;margin-left:8px;">ID {{ item.id }}</span>
                                </div>
                                <span>{{ item.mobile }}</span>
                            </div>
                        </template>
                    </el-autocomplete>
                </el-form-item>
                <el-form-item label="姓名/备注" prop="name">
                    <el-input v-model="dialogForm.name" placeholder="请输入真实姓名或备注信息"></el-input>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitLoading" @click="onSubmit">确定</el-button>
            </span>
        </el-dialog>
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
                search: {
                    keyword: '',
                },
                dialogVisible: false,
                submitLoading: false,
                dialogForm: {
                    id: 0,
                    user_id: 0,
                    nickname: '',
                    name: '',
                    status: 1
                },
                rules: {
                    user_id: [
                        {required: true, message: '请选择核销员', trigger: 'change'}
                    ],
                    name: [
                        {required: true, message: '请输入姓名/备注', trigger: 'blur'}
                    ]
                }
            };
        },
        mounted() {
            this.getList();
        },
        methods: {
            pagination(currentPage) {
                this.page = currentPage;
                this.getList();
            },
            getList() {
                this.listLoading = true;
                this.$request({
                    url: 'plugin/clerk/mall/user/index',
                    data: {
                        page: this.page,
                        keyword: this.search.keyword,
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
            editClerk(row) {
                if (row && row.id) {
                    this.dialogForm = {
                        id: row.id,
                        user_id: row.user_id,
                        nickname: row.user ? row.user.nickname : '',
                        name: row.name,
                        status: row.status
                    };
                } else {
                    this.dialogForm = {
                        id: 0,
                        user_id: 0,
                        nickname: '',
                        name: '',
                        status: 1
                    };
                }
                this.dialogVisible = true;
            },
            deleteClerk(id) {
                this.$confirm('确定解绑该核销员吗?', '提示', {
                    confirmButtonText: '确定',
                    cancelButtonText: '取消',
                    type: 'warning'
                }).then(() => {
                    this.$request({
                        url: 'plugin/clerk/mall/user/delete',
                        data: {
                            id: id
                        },
                        method: 'post'
                    }).then(e => {
                        if (e.code === 0) {
                            this.$message.success(e.msg);
                            this.getList();
                        } else {
                            this.$message.error(e.msg);
                        }
                    });
                }).catch(() => {
                });
            },
            switchStatus(row) {
                this.$request({
                    url: 'plugin/clerk/mall/user/edit',
                    data: {
                        form: row
                    },
                    method: 'post'
                }).then(e => {
                    if (e.code === 0) {
                        this.$message.success('更新成功');
                    } else {
                        this.$message.error(e.msg);
                        this.getList();
                    }
                });
            },
            onSubmit() {
                this.$refs.dialogForm.validate((valid) => {
                    if (valid) {
                        this.submitLoading = true;
                        this.$request({
                            url: 'plugin/clerk/mall/user/edit',
                            data: {
                                form: this.dialogForm
                            },
                            method: 'post'
                        }).then(e => {
                            this.submitLoading = false;
                            if (e.code === 0) {
                                this.$message.success(e.msg);
                                this.dialogVisible = false;
                                this.getList();
                            } else {
                                this.$message.error(e.msg);
                            }
                        }).catch(e => {
                            this.submitLoading = false;
                        });
                    }
                });
            },
            querySearchAsync(queryString, cb) {
                if (queryString) {
                    this.$request({
                        // 核心用户搜索接口是 user-search-list(keyword 为数字时同时匹配 ID / 手机号 / 昵称)
                        url: 'mall/user/user-search-list',
                        data: {
                            keyword: queryString,
                            limit: 20
                        },
                        method: 'get'
                    }).then(e => {
                        if (e.code === 0) {
                            cb(e.data.list);
                        } else {
                            cb([]);
                        }
                    });
                } else {
                    cb([]);
                }
            },
            handleSelect(item) {
                this.dialogForm.user_id = item.id;
                this.dialogForm.nickname = item.nickname;
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
        width: 300px;
    }
</style>
