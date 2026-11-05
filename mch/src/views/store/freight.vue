<template>
    <div class="app-container">
        <el-card shadow="never">
            <div slot="header" class="clearfix">
                <span>运费规则设置</span>
            </div>

            <el-form :model="form" :rules="rules" ref="freightForm" label-width="150px"
                style="max-width: 800px; margin-top: 20px;" v-loading="loading">
                <el-alert title="商家默认使用全国统一运费模板。请根据您的发货习惯设置计费方式和具体的首续费金额。" type="info" :closable="false"
                    style="margin-bottom: 30px;">
                </el-alert>

                <el-form-item label="模板名称" prop="name">
                    <el-input v-model="form.name" placeholder="例如：全国统一运费模板" maxlength="45"></el-input>
                </el-form-item>

                <el-form-item label="是否启用该模板" prop="enabled">
                    <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-color="#13ce66"
                        inactive-color="#ff4949"></el-switch>
                    <div style="font-size: 12px; color: #909399; margin-top: 5px;">关闭后，所有商品（除非包邮）将无法结算此运费规则。</div>
                </el-form-item>

                <el-form-item label="计费方式" prop="price_type">
                    <el-radio-group v-model="form.price_type">
                        <el-radio :label="1">按件数计费</el-radio>
                        <el-radio :label="2">按重量计费</el-radio>
                        <el-radio :label="3">按体积计费</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-card shadow="never" style="background-color: #f8f9fa; border: 1px dashed #dcdfe6;">
                    <el-row :gutter="20">
                        <el-col :span="12">
                            <el-form-item :label="firstUnitLabel" prop="first_num" label-width="120px">
                                <el-input-number v-model="form.first_num" :min="1" :step="1"
                                    style="width: 100%;"></el-input-number>
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="首运费金额 (元)" prop="first_price" label-width="130px">
                                <el-input-number v-model="form.first_price" :min="0" :precision="2" :step="1"
                                    style="width: 100%;"></el-input-number>
                            </el-form-item>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" style="margin-top: 20px;">
                        <el-col :span="12">
                            <el-form-item :label="otherUnitLabel" prop="other_num" label-width="120px">
                                <el-input-number v-model="form.other_num" :min="1" :step="1"
                                    style="width: 100%;"></el-input-number>
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="续运费金额 (元)" prop="other_price" label-width="130px">
                                <el-input-number v-model="form.other_price" :min="0" :precision="2" :step="1"
                                    style="width: 100%;"></el-input-number>
                            </el-form-item>
                        </el-col>
                    </el-row>
                </el-card>

                <el-form-item style="margin-top: 40px;">
                    <el-button type="primary" @click="submitApply" :loading="submitting">保存运费规则</el-button>
                </el-form-item>
            </el-form>

        </el-card>
    </div>
</template>

<script>
export default {
    name: 'StoreFreight',
    data() {
        return {
            loading: true,
            submitting: false,
            form: {
                name: '商家默认模板',
                enabled: 1,
                price_type: 1, // 1:按件, 2:按重量, 3:按体积
                first_num: 1,
                first_price: 0,
                other_num: 1,
                other_price: 0
            },
            rules: {
                name: [
                    { required: true, message: '请输入运费模板名称', trigger: 'blur' }
                ],
                first_num: [
                    { required: true, message: '请设定首件/首重/首体积数量', trigger: 'blur' }
                ],
                other_num: [
                    { required: true, message: '请设定续件/续重/续体积数量', trigger: 'blur' }
                ]
            }
        }
    },
    computed: {
        firstUnitLabel() {
            if (this.form.price_type === 1) return '首件 (件)';
            if (this.form.price_type === 2) return '首重 (克)';
            if (this.form.price_type === 3) return '首体积 (m³)';
            return '首件 (件)';
        },
        otherUnitLabel() {
            if (this.form.price_type === 1) return '续件 (件)';
            if (this.form.price_type === 2) return '续重 (克)';
            if (this.form.price_type === 3) return '续体积 (m³)';
            return '续件 (件)';
        }
    },
    created() {
        this.getPrepareInfo();
    },
    methods: {
        getPrepareInfo() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/freight/setting',
                method: 'get'
            }).then(res => {
                this.loading = false;
                if (res.code === 0 && res.data) {
                    const data = res.data;
                    this.form.name = data.name || '默认模版';
                    this.form.enabled = data.enabled !== undefined ? parseInt(data.enabled) : 1;
                    this.form.price_type = data.price_type ? parseInt(data.price_type) || 1 : 1;
                    this.form.first_num = parseInt(data.first_num || 1);
                    this.form.first_price = parseFloat(data.first_price || 0);
                    this.form.other_num = parseInt(data.other_num || 1);
                    this.form.other_price = parseFloat(data.other_price || 0);
                } else if (res.code !== 0) {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.loading = false;
            });
        },

        submitApply() {
            this.$refs.freightForm.validate(valid => {
                if (valid) {
                    this.submitting = true;
                    this.$request({
                        url: '/plugin/mch/admin/freight/setting',
                        method: 'post',
                        data: this.form
                    }).then(res => {
                        this.submitting = false;
                        if (res.code === 0) {
                            this.$message.success('运费规则保存成功');
                            this.getPrepareInfo();
                        } else {
                            this.$message.error(res.msg);
                        }
                    }).catch(() => {
                        this.submitting = false;
                    });
                }
            });
        }
    }
}
</script>
