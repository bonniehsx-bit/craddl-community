<template>
    <div class="app-container">
        <el-card shadow="never">
            <div slot="header" class="clearfix">
                <span>退货地址设置</span>
            </div>

            <el-form :model="form" :rules="rules" ref="form" label-width="150px" v-loading="loading">
                <el-form-item label="收件人姓名" prop="name">
                    <el-input v-model="form.name" placeholder="请输入退货收件人姓名" style="width: 50%"></el-input>
                </el-form-item>

                <el-form-item label="联系电话" prop="mobile">
                    <el-input v-model="form.mobile" placeholder="请输入收件人联系电话" style="width: 50%"></el-input>
                </el-form-item>

                <el-form-item label="详细退货地址" prop="address">
                    <el-input type="textarea" :rows="3" v-model="form.address" placeholder="请输入退件所在的省市区及详细地址"
                        style="width: 50%"></el-input>
                </el-form-item>

                <el-form-item label="买家须知 / 备注" prop="remark">
                    <el-input type="textarea" :rows="3" v-model="form.remark" placeholder="如果有特殊的退货要求请写在此处，会展示给买家"
                        style="width: 50%"></el-input>
                </el-form-item>

                <el-form-item label="开启退货服务" prop="is_return">
                    <el-switch v-model="form.is_return" :active-value="1" :inactive-value="0" active-text="开启"
                        inactive-text="关闭">
                    </el-switch>
                    <div style="font-size: 12px; color: #909399; margin-top: 5px;">如果不开启，买家可能无法自动获取退件地址。</div>
                </el-form-item>

                <el-form-item style="margin-top: 30px;">
                    <el-button type="primary" @click="onSubmit" :loading="submitLoading">保存配置</el-button>
                </el-form-item>
            </el-form>

        </el-card>
    </div>
</template>

<script>
export default {
    name: 'StoreSetting',
    data() {
        return {
            loading: false,
            submitLoading: false,
            form: {
                name: '',
                mobile: '',
                address: '',
                remark: '',
                is_return: 1
            },
            rules: {
                name: [{ required: true, message: '请填写收件人姓名', trigger: 'blur' }],
                mobile: [{ required: true, message: '请填写联系电话', trigger: 'blur' }],
                address: [{ required: true, message: '请填写详细地址', trigger: 'blur' }]
            }
        }
    },
    created() {
        this.getConfig();
    },
    methods: {
        getConfig() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/setting/return-address',
                method: 'get'
            }).then(res => {
                this.loading = false;
                if (res.code === 0 && res.data) {
                    Object.assign(this.form, res.data);
                    this.form.is_return = parseInt(this.form.is_return);
                }
            });
        },
        onSubmit() {
            this.$refs.form.validate((valid) => {
                if (valid) {
                    this.submitLoading = true;
                    this.$request({
                        url: '/plugin/mch/admin/setting/return-address',
                        method: 'post',
                        data: this.form
                    }).then(res => {
                        this.submitLoading = false;
                        if (res.code === 0) {
                            this.$message.success('保存成功');
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
