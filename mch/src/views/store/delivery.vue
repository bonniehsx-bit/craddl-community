<template>
    <div class="app-container">
        <el-card shadow="never" class="box-card">
            <div slot="header" class="clearfix">
                <span style="font-weight: bold; font-size: 15px;">平台级配送总线配置</span>
            </div>

            <el-form :model="form" ref="form" label-width="180px" v-loading="loading" style="max-width: 800px; padding: 20px 0;">
                
                <el-form-item label="开启的配送方式">
                    <el-checkbox-group v-model="form.delivery_options">
                        <el-checkbox 
                           v-for="item in availableOptions" 
                           :key="item.value" 
                           :label="item.value">
                           {{ item.name }}
                        </el-checkbox>
                    </el-checkbox-group>
                    <div v-if="availableOptions.length === 0" style="color: #E6A23C; font-size: 13px; margin-top: 5px;">
                        <i class="el-icon-warning-outline"></i> 当前商家类型无需配置配送方式
                    </div>
                </el-form-item>

                <!-- 上门自提配置区域 -->
                <div v-if="form.delivery_options.includes('pickup')" class="delivery-section">
                    <div class="section-title">
                        <i class="el-icon-shopping-bag-2"></i> 上门自提配置
                    </div>
                    
                    <el-form-item label="自提优惠折扣 (%)" prop="pickup_discount_rate">
                        <el-input-number v-model="form.pickup_discount_rate" :min="0" :max="100" :step="1"></el-input-number>
                        <div class="form-tip">例：填 5 表示打 95 折，0 为不打折</div>
                    </el-form-item>
                    
                    <el-form-item label="最高折扣上限 (元)" prop="pickup_discount_max_amount">
                        <el-input-number v-model="form.pickup_discount_max_amount" :min="0" :precision="2" :step="1"></el-input-number>
                        <div class="form-tip">自提优惠减免的最高金额，0 或为空表示不封顶</div>
                    </el-form-item>
                </div>

                <!-- 同城配送配置区域 -->
                <div v-if="form.delivery_options.includes('city')" class="delivery-section city-section">
                    <div class="section-title">
                        <i class="el-icon-bicycle"></i> 同城配送生态配置
                    </div>

                    <el-form-item label="最大接单配送半径(km)">
                        <el-input-number v-model="form.city_config.delivery_radius" :min="0" :precision="1" :step="1"></el-input-number>
                    </el-form-item>
                    
                    <el-form-item label="最低起送消费金额(元)">
                        <el-input-number v-model="form.city_config.order_amount_limit.min_order_amount" :min="0" :precision="2" :step="1"></el-input-number>
                        <div class="form-tip">订单支付金额需要达到此起送门槛才能呼叫配送，0 为无门槛限制</div>
                    </el-form-item>

                    <el-form-item label="计费阶梯 - 起步距离(km)">
                        <el-input-number v-model="form.city_config.charge_rules.start_distance" :min="0" :precision="1" :step="1"></el-input-number>
                    </el-form-item>

                    <el-form-item label="计费阶梯 - 起步价(元)">
                        <el-input-number v-model="form.city_config.charge_rules.start_fee" :min="0" :precision="2" :step="1"></el-input-number>
                    </el-form-item>

                    <el-form-item label="计费阶梯 - 超出按公里加价(元/km)">
                        <el-input-number v-model="form.city_config.charge_rules.extra_fee_per_km" :min="0" :precision="2" :step="1"></el-input-number>
                        <div class="form-tip">当配送距离大于起步距离时，每超出 1 公里累计的追加跑腿费</div>
                    </el-form-item>
                </div>

                <el-form-item style="margin-top: 40px;">
                    <el-button type="primary" @click="onSubmit" :loading="submitLoading" size="medium" icon="el-icon-check">保存配送总线策略</el-button>
                </el-form-item>
            </el-form>

        </el-card>
    </div>
</template>

<script>
export default {
    name: 'StoreDelivery',
    data() {
        return {
            loading: false,
            submitLoading: false,
            form: {
                mch_type: 0,
                delivery_options: [],
                pickup_discount_rate: 0,
                pickup_discount_max_amount: 0,
                city_config: {
                    delivery_radius: 5,
                    order_amount_limit: { min_order_amount: 0 },
                    charge_rules: {
                        start_distance: 3,
                        start_fee: 5,
                        extra_fee_per_km: 2
                    }
                }
            }
        }
    },
    computed: {
        availableOptions() {
            if (this.form.mch_type == 1) { // 供应商
                return [ { name: '快递物流体系', value: 'express' } ];
            }
            if (this.form.mch_type == 2) { // 门店
                return [
                    { name: '快递物流发包', value: 'express' },
                    { name: '上门提货 (自提)', value: 'pickup' },
                    { name: '同城跑腿发包 (同城配送)', value: 'city' }
                ];
            }
            return [];
        }
    },
    created() {
        this.getConfig();
    },
    methods: {
        getConfig() {
            this.loading = true;
            this.$request({
                url: '/plugin/mch/admin/mch/delivery-config',
                method: 'get'
            }).then(res => {
                if (res.code === 0 && res.data) {
                    let d = res.data;
                    this.form.mch_type = d.mch_type;
                    this.form.delivery_options = d.delivery_options || [];
                    this.form.pickup_discount_rate = parseFloat(d.pickup_discount_rate || 0);
                    this.form.pickup_discount_max_amount = parseFloat(d.pickup_discount_max_amount || 0);

                    if(d.city_config) {
                        this.form.city_config.delivery_radius = parseFloat(d.city_config.delivery_radius || 5);
                        
                        if(d.city_config.order_amount_limit) {    
                            this.form.city_config.order_amount_limit.min_order_amount = parseFloat(d.city_config.order_amount_limit.min_order_amount || 0);
                        }
                        
                        if(d.city_config.charge_rules) {
                            this.form.city_config.charge_rules.start_distance = parseFloat(d.city_config.charge_rules.start_distance || 3);
                            this.form.city_config.charge_rules.start_fee = parseFloat(d.city_config.charge_rules.start_fee || 5);
                            this.form.city_config.charge_rules.extra_fee_per_km = parseFloat(d.city_config.charge_rules.extra_fee_per_km || 2);
                        }
                    }
                    
                    this.loading = false;
                } else {
                    this.loading = false;
                }
            }).catch(() => {
                this.loading = false;
            });
        },
        onSubmit() {
            this.submitLoading = true;
            this.$request({
                url: '/plugin/mch/admin/mch/delivery-config',
                method: 'post',
                data: this.form
            }).then(res => {
                this.submitLoading = false;
                if (res.code === 0) {
                    this.$message.success('配送总线状态流转完成');
                } else {
                    this.$message.error(res.msg);
                }
            }).catch(() => {
                this.submitLoading = false;
            });
        }
    }
}
</script>

<style scoped>
.form-tip {
    font-size: 13px;
    color: #909399;
    line-height: 1.4;
    margin-top: 6px;
}
.delivery-section {
    margin-top: 35px;
    padding: 25px 20px 5px 0;
    background: #FAFAFA;
    border-radius: 8px;
    border: 1px solid #EBEDF0;
}
.city-section {
    background: #F4F8FE;
    border-color: #E4EDF8;
}
.section-title {
    font-size: 14px;
    font-weight: bold;
    color: #303133;
    margin-bottom: 25px;
    margin-left: 20px;
    border-left: 3px solid #409EFF;
    padding-left: 10px;
}
</style>
