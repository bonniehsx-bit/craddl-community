<template>
    <div class="app-container">
        <el-card shadow="never">
            <div slot="header" class="clearfix">
                <span style="font-weight: bold;">自定义商品分类</span>
                <div style="float: right;">
                    <el-button type="primary" size="small" icon="el-icon-plus"
                        @click="openDialog('add')">添加分类</el-button>
                </div>
            </div>

            <el-table :data="list" v-loading="loading" style="width: 100%; margin-bottom: 20px;" row-key="id" border
                default-expand-all :tree-props="{ children: 'children', hasChildren: 'hasChildren' }">
                <el-table-column prop="name" label="分类名称" min-width="200"></el-table-column>
                <el-table-column prop="sort" label="排序权重" width="100" align="center"></el-table-column>
                <el-table-column label="操作" width="200" align="center">
                    <template slot-scope="scope">
                        <el-button type="text" size="small" icon="el-icon-plus" @click="openDialog('add', scope.row.id)"
                            v-if="Number(scope.row.parent_id) === 0">添加子分类</el-button>
                        <el-button type="text" size="small" icon="el-icon-edit"
                            @click="openDialog('edit', 0, scope.row)">编辑</el-button>
                        <el-button type="text" size="small" icon="el-icon-delete" style="color: #F56C6C;"
                            @click="handleDelete(scope.row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 编辑/添加分类抽屉 -->
        <el-drawer :title="dialogType === 'add' ? '添加分类' : '编辑分类'" :visible.sync="dialogVisible" size="400px" direction="rtl">
            <div style="padding: 20px; height: calc(100vh - 141px); overflow-y: auto;">
                <el-form :model="form" :rules="rules" ref="catForm" label-width="100px">
                    <el-form-item label="分类名称" prop="name">
                        <el-input v-model="form.name" placeholder="请输入分类名称" maxlength="45"></el-input>
                    </el-form-item>
                    <el-form-item label="上级分类" prop="parent_id">
                        <el-select v-model="form.parent_id" placeholder="请选择上级分类" style="width: 100%;">
                            <el-option label="作为一级分类" :value="0"></el-option>
                            <el-option v-for="item in parentOptions" :key="item.id" :label="item.name"
                                :value="item.id"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item label="排序权重" prop="sort">
                        <el-input-number v-model="form.sort" :min="1" :max="9999"></el-input-number>
                    </el-form-item>
                </el-form>
            </div>
            <div style="padding: 20px; text-align: right; border-top: 1px solid #EBEEF5; background: #fff; width: 100%; box-sizing: border-box;">
                <el-button @click="dialogVisible = false" size="small">取 消</el-button>
                <el-button type="primary" :loading="saving" @click="saveCat" size="small">确 定</el-button>
            </div>
        </el-drawer>
    </div>
</template>

<script>
export default {
    name: 'GoodsCategory',
    data() {
        return {
            loading: false,
            list: [],

            dialogVisible: false,
            dialogType: 'add',
            saving: false,
            form: {
                id: null,
                name: '',
                parent_id: 0,
                sort: 100
            },
            rules: {
                name: [
                    { required: true, message: '请输入分类名称', trigger: 'blur' }
                ]
            }
        }
    },
    computed: {
        parentOptions() {
            // 提取一级分类供选择，不包括自己
            let options = this.list.filter(item => Number(item.parent_id) === 0);
            if (this.dialogType === 'edit' && this.form.id) {
                options = options.filter(item => Number(item.id) !== Number(this.form.id));
            }
            return options;
        }
    },
    created() {
        this.loadData();
    },
    methods: {
        loadData() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/goods-cat/index',
                method: 'get'
            }).then(res => {
                this.loading = false;
                if (res.code === 0) {
                    this.list = res.data.list || [];
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.loading = false;
            });
        },

        openDialog(type, parentId = 0, row = null) {
            this.dialogType = type;
            if (type === 'add') {
                this.form = {
                    id: null,
                    name: '',
                    parent_id: parentId,
                    sort: 100
                };
            } else {
                this.form = {
                    id: row.id,
                    name: row.name,
                    parent_id: row.parent_id,
                    sort: row.sort
                };
            }
            this.dialogVisible = true;
            this.$nextTick(() => {
                this.$refs.catForm.clearValidate();
            });
        },

        saveCat() {
            this.$refs.catForm.validate(valid => {
                if (valid) {
                    this.saving = true;
                    this.$request({
                        url: '/plugin/mch/admin/goods-cat/edit',
                        method: 'post',
                        data: this.form
                    }).then(res => {
                        this.saving = false;
                        if (res.code === 0) {
                            this.$message.success('分类保存成功');
                            this.dialogVisible = false;
                            this.loadData();
                        } else {
                            this.$message.error(res.msg);
                        }
                    }).catch(() => {
                        this.saving = false;
                    });
                }
            });
        },

        handleDelete(row) {
            this.$confirm(`确认删除分类 "${row.name}" 吗？如果分类下有商品则无法删除。`, '提示', {
                type: 'warning'
            }).then(() => {
                this.$request({
                    url: '/plugin/mch/admin/goods-cat/delete',
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
        }
    }
}
</script>
