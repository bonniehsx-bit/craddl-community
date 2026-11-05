<template>
    <div class="app-container">
        <el-card shadow="always" :body-style="{ padding: '20px' }">
            <div slot="header">
                <span>飞鹅打印机</span>
                <el-button style="float: right; padding: 3px 0" type="text" @click="dialogVisible = true">新建</el-button>
            </div>
            <div v-loading="is_loading">
                <el-table size="mini"  :data="list" border style="width: 100%">
                    <el-table-column prop="id" label="ID">
                    </el-table-column>
                    <el-table-column prop="name" label="名称">
                    </el-table-column>
                    <el-table-column prop="account" label="账号">
                    </el-table-column>
                    <el-table-column prop="printer_sn" label="设备号">
                    </el-table-column>
                    <el-table-column prop="type" label="类型">
                        <template slot-scope='scope'>
                            <el-tag :type="scope.row.type == 'ticket' ? 'success' : 'primary'" size="mini"
                                effect="dark">
                                {{ scope.row.type == 'ticket' ? '小票' : '标签' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_time" label="创建时间">
                    </el-table-column>
                    <el-table-column label="操作">
                        <template slot-scope="scope">
                            <el-button type="text" @click="edit(scope.row)">编辑</el-button>
                            <el-button type="text" @click="deletePrinter(scope.row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div style="text-align: right;margin: 20px 0;">
                    <pagination v-model="page" :pagination="pagination" @pageChage="getList">
                    </pagination>
                </div>
            </div>
        </el-card>
        <el-dialog title="提示" :visible.sync="dialogVisible" width="40%" :before-close="handleClose">
           <el-form size="mini" :model="form" ref="form" label-width="80px" :inline="false" >
                <el-form-item label="打印机名称">
                    <el-input v-model="form.name"></el-input>
                </el-form-item>

                <el-form-item label="飞鹅账号">
                    <el-input v-model="form.account">

                    </el-input>
                </el-form-item>

                <el-form-item label="UKEY">
                    <el-input v-model="form.fe_ukey">
                        <template slot="append">
                            在飞鹅开放平台获取
                        </template>
                    </el-input>
                </el-form-item>

                <el-form-item label="小票机设备编号">
                    <el-input v-model="form.printer_sn">
                    </el-input>
                </el-form-item>
                <el-form-item label="打印机类型">
                    <el-radio v-model="form.type" label="ticket">小票打印机</el-radio>
                    <el-radio v-model="form.type" label="label">标签打印机</el-radio>
                </el-form-item>

            </el-form>

            <span slot="footer" class="dialog-footer">
                <el-button @click="dialogVisible = false">取 消</el-button>
                <el-button type="primary" @click="save">确 定</el-button>
            </span>
        </el-dialog>
    </div>

</template>
<script>
export default {
    name: 'mall-fei-printer',
    data() {
        return {
            is_loading: false,
            dialogVisible: false,
            page: 1,
            pagination: null,
            list: [],
            form: {
                id: '',
                printer_sn: '',
                account: '',
                fe_ukey: '',
                type: 'ticket',
                name: '',
            }
        }
    },
    created() {
        this.getList();
    },
    methods: {
        edit(row) {
            this.form = Object.assign(this.form, row);
            this.dialogVisible = true;

        },
        deletePrinter(row) {
            this.$confirm('确定删除:' + row.name, '提示').then(e => {
                this.$request({
                    url: '/mall/feie-printer/delete',
                    data: row,
                    method: 'post'
                }).then(res => {
                    if (res.code == 0) {
                        this.$message.success(res.msg);
                        this.getList();
                    }
                })
            })

        },
        handleClose(e) {
            this.dialogVisible = false;
            this.form = {
                id: '',
                name: '',
                printer_sn: '',
                account: '',
                fe_ukey: '',
                type: 'ticket',
            }
        },
        getList() {
            this.is_loading = true;
            this.$request({
                url: '/mall/feie-printer/printer-list',
                data: {
                    page: this.page,
                }
            }).then(res => {
                this.is_loading = false;
                if (res.code == 0) {
                    this.list = res.data.list
                    this.pagination = res.data.pagination
                }
            })
        },
        save() {
            if (this.is_loading) {
                return;
            }
            this.is_loading = true;
            this.$request({
                url: '/mall/feie-printer/printer',
                data: this.form,
                method: 'post'
            }).then(res => {
                this.is_loading = false;
                if (res.code == 0) {
                    this.$message.success(res.msg);
                    this.handleClose();
                    this.getList();
                }
            }).catch(e => {
                this.is_loading = false;
            })

        },
    },
}
</script>
<style scoped>
</style>
