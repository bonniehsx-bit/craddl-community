<template>
    <div class="app-container" style="padding: 0 20px 20px;">
        <el-card shadow="never" style="border: none;">

            <div class="filter-container">
                <el-form :inline="true" size="small" @submit.native.prevent>
                    <el-form-item label="搜索客户信息">
                        <el-input v-model="searchKeyword" placeholder="手机号 / 昵称" clearable style="width: 250px;"
                            @keyup.enter.native="searchUser"></el-input>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" icon="el-icon-search" @click="searchUser"
                            :loading="searchLoading">搜索用户</el-button>
                    </el-form-item>
                </el-form>
            </div>

            <el-alert v-if="user" title="已选中用户，请确认发放信息" type="success" :closable="false" style="margin-bottom: 20px;">
            </el-alert>

            <div v-if="user"
                style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center;">
                    <el-avatar :src="user.avatar_url" size="large"></el-avatar>
                    <div style="margin-left: 15px;">
                        <div style="font-weight: bold; font-size: 15px;">{{ user.nickname }}</div>
                        <div style="font-size: 13px; color: #909399; margin-top: 5px;">手机号: {{ user.mobile || '未绑定' }} |
                            ID: {{ user.id }}</div>
                    </div>
                </div>
                <div>
                    <el-button type="primary" @click="confirmSend" :loading="submitLoading">确认发放当前优惠券</el-button>
                </div>
            </div>

            <!-- 当前准备发送的优惠券信息展示 -->
            <div v-if="coupon">
                <h4 style="color: #606266; margin-bottom: 15px;">当前优惠券摘要</h4>
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item label="名称">{{ coupon.name }}</el-descriptions-item>
                    <el-descriptions-item label="类型">{{ coupon.type == 1 ? '满减券' : '折扣券' }}</el-descriptions-item>
                    <el-descriptions-item label="最低消费">¥{{ coupon.min_price }}</el-descriptions-item>
                    <el-descriptions-item label="优惠档位" v-if="coupon.type == 1">减 ¥{{ coupon.sub_price
                    }}</el-descriptions-item>
                    <el-descriptions-item label="优惠档位" v-if="coupon.type == 2">{{ coupon.discount }}
                        打折</el-descriptions-item>
                    <el-descriptions-item label="状态" :span="2">可发数量: {{ coupon.total_count == -1 ? '无限' :
                        coupon.total_count }}</el-descriptions-item>
                </el-descriptions>
            </div>

        </el-card>
    </div>
</template>

<script>
export default {
    name: 'MchCouponSend',
    props: {
        couponId: {
            type: Number,
            default: 0
        }
    },
    data() {
        return {
            searchLoading: false,
            submitLoading: false,
            searchKeyword: '',
            user: null,    // 搜索到的用户
            coupon: null,  // 当前携带的优惠券详情
            coupon_id: 0
        }
    },
    watch: {
        couponId: {
            immediate: true,
            handler(val) {
                this.coupon_id = val;
                this.user = null;
                this.coupon = null;
                this.searchKeyword = '';

                if (val > 0) {
                    this.getCouponDetail();
                }
            }
        }
    },
    methods: {
        getCouponDetail() {
            this.$request({
                url: '/plugin/mch/admin/coupon/detail',
                method: 'get',
                data: { id: this.coupon_id }
            }).then(res => {
                if (res.code === 0) {
                    this.coupon = res.data.detail;
                }
            });
        },
        searchUser() {
            if (!this.searchKeyword) {
                this.$message.warning("请输入搜索关键词");
                return;
            }
            this.searchLoading = true;
            this.user = null; // reset
            this.$request({
                url: '/plugin/mch/admin/coupon/search-user',
                method: 'get',
                data: { keyword: this.searchKeyword }
            }).then(res => {
                this.searchLoading = false;
                if (res.code === 0 && res.data.user) {
                    this.user = res.data.user; // 返回单个最佳匹配用户
                } else {
                    this.$message.warning(res.msg || '未搜索到匹配的用户');
                }
            }).catch(err => {
                this.searchLoading = false;
            });
        },
        confirmSend() {
            if (!this.user || !this.coupon_id) return;
            this.$confirm(`确认将【${this.coupon.name}】发放给用户【${this.user.nickname}】吗?`, '发放提示', {
                type: 'warning'
            }).then(() => {
                this.submitLoading = true;
                this.$request({
                    url: '/plugin/mch/admin/coupon/send',
                    method: 'post',
                    data: {
                        coupon_id: this.coupon_id,
                        user_id: this.user.id
                    }
                }).then(res => {
                    this.submitLoading = false;
                    if (res.code === 0) {
                        this.$message.success('发放成功');
                        this.$emit('success');
                        this.$emit('close');
                    } else {
                        this.$message.error(res.msg);
                    }
                }).catch(e => {
                    this.submitLoading = false;
                });
            }).catch(() => { });
        }
    }
}
</script>
