<template>
<div class="app-container">
	<el-card class="box-card">
			<div slot="header" class="clearfix">
				<span>推广设置</span>
				<el-button style="float: right; padding: 3px 0" type="primary" size="small" :loading="is_loading" @click="save">保存设置</el-button>
			</div>

			<el-row>
				<el-col :span="16">
					<el-form ref="form" :model="form" label-width="140px" v-loading="is_loading && !is_saving">
						<el-divider content-position="left">基础分销设置</el-divider>

						<el-alert type="info" :closable="false" style="margin: 16px 0 24px; line-height: 1.8;">
							<div slot="title" style="font-weight: bold; margin-bottom: 6px;">推荐关系说明</div>
							<div>
								<p style="margin: 0 0 6px;">
									用户 A 分享链接/二维码 → 用户 B 首次打开 → 系统记录「B 的意向推荐人是 A」→ 达到下方设定的条件后，B 正式成为 A 的下线，此后不可更改。
								</p>
								<p style="margin: 0 0 6px;">
									⚠️ <strong>只有已获得分销资格的用户才能作为推荐人</strong>（见下方「分销资格设置」）。普通用户分享的链接不会产生推荐关系。
								</p>
								<p style="margin: 0;">
									⚠️ 推荐人一旦绑定即<strong>永久生效、不可修改</strong>。如需调整请联系技术人员直接操作数据库。
								</p>
							</div>
						</el-alert>

						<el-form-item label="成为下线条件" style="margin-top: 20px;">
							<el-radio-group v-model="form.child_type">
								<el-radio label="0">首次点击链接</el-radio>
								<el-radio label="1">首次下单</el-radio>
								<el-radio label="2">首次付款</el-radio>
							</el-radio-group>
							<div class="form-tip">
								用户通过分享链接/二维码进入商城后，系统先记录推荐意向，达到此条件才正式绑定推荐关系。<br>
								<strong>首次点击链接</strong>：进入商城即绑定，转化率最高但容易误绑（如用户只是随手点开）。<br>
								<strong>首次下单</strong>：用户提交订单时绑定，无论是否付款。<br>
								<strong>首次付款</strong>：用户完成支付后绑定，最严格，适合对分销质量要求高的场景。
							</div>
						</el-form-item>

						<el-divider content-position="left" style="margin-top: 40px;">分销资格与购物结算</el-divider>
						<el-form-item label="分销资格设置" style="margin-top: 20px;">
							<el-radio-group v-model="form.invite_type">
								<el-radio label="0">无条件</el-radio>
								<el-radio label="1">购买指定商品</el-radio>
								<el-radio label="3">消费满一定金额</el-radio>
							</el-radio-group>
							<div class="form-tip">
								决定普通用户如何获得「分销资格」。只有获得资格的用户，其分享链接才会产生推荐关系，才能拿到佣金。<br>
								<strong>无条件</strong>：所有用户注册即拥有资格。<br>
								<strong>购买指定商品</strong>：需购买下方配置的商品后才获得资格（常用于付费加盟模式）。<br>
								<strong>消费满一定金额</strong>：累计消费达到设定金额后获得资格。
							</div>
						</el-form-item>
						<el-form-item label="消费满" v-if="form.invite_type==3">
							<el-input v-model="form.price" type="number" min="0" style="width: 200px;">
								<template slot="append">元</template>
							</el-input>
						</el-form-item>

						<el-form-item label="选择商品" v-if="form.invite_type==1">
							<el-table :data="form.list" style="width: 100%" border size="small">
								<el-table-column label="商品">
									<template slot-scope="scope">
										<div class="flex-row" style="align-items: center;">
											<el-image :src="scope.row.cover_pic" style="width: 50px;height: 50px; border-radius: 4px;" fit="cover">
												<div slot="error" class="image-slot">
													<i class="el-icon-picture-outline"></i>
												</div>
											</el-image>
											<div style="padding: 0 10px; flex: 1; line-height: 1.4;">
												{{scope.row.name}}
											</div>
										</div>
									</template>
								</el-table-column>
								<el-table-column label="价格" width="100" align="center">
									<template slot-scope="scope">
										<span style="font-weight: bold;color: #F56C6C;">￥{{scope.row.price}}</span>
									</template>
								</el-table-column>
								<el-table-column label="操作" width="80" align="center">
									<template slot-scope="scope">
										<el-button size="mini" type="text" style="color: #F56C6C;" @click="goodsDelete(scope.$index)">
											移除
										</el-button>
									</template>
								</el-table-column>
							</el-table>
							<goods-picker @selected="selectedGoods" style="margin-top: 10px; display: inline-block;">
								<el-button type="primary" plain size="small" icon="el-icon-plus">添加指定商品 ({{form.list.length}}/50)</el-button>
							</goods-picker>
						</el-form-item>

						<el-form-item label="购物结算方式" v-if="form.invite_type==1">
							<el-radio-group v-model="form.buy_type">
								<el-radio label="0">订单付款后</el-radio>
								<el-radio label="1">订单完成后</el-radio>
							</el-radio-group>
						</el-form-item>
						
						<el-divider content-position="left" style="margin-top: 40px;">文案说明</el-divider>
						<el-form-item label="推广页面说明" style="margin-top: 20px;">
							<el-input type="textarea" v-model="form.detail" :rows="5" placeholder="可填写邀请规则、分销政策等说明，将展示在用户端申请推广员页面"></el-input>
						</el-form-item>
					</el-form>
				</el-col>
			</el-row>
		</el-card>

</div>
</template>

<script>
	export default {
	  name:'user-setting',
		data() {
			return {
				is_loading: false,
				is_saving: false, // 专门用于保存按钮的 loading 状态区分

				form: {
					price:'',
					buy_type: "0",
					child_type:"0",
					invite_type: "0",
					detail: '',
					list: [],
					goods_ids: [],
				}
			}
		},
		created() {
			this.getSetting();
		},
		methods: {
			selectedGoods(e) {
				if (!e || e.length === 0) return;
				
				if (!this.form.list || this.form.list.length === 0) {
					this.form.list = [...e];
				} else {
					// 【修复 Bug】之前使用 concat 但没有赋值回 this.form.list，导致商品无法追加
					this.form.list = this.form.list.concat(e);
				}
				
				// 重新计算商品 ID 集合
				this.form.goods_ids = this.form.list.map(item => item.id);
				this.$forceUpdate();
			},
			goodsDelete(index) {
				this.form.list.splice(index, 1);
				this.form.goods_ids = this.form.list.map(item => item.id);
			},
			getSetting() {
				this.is_loading = true;
				this.$request({
					url: '/mall/user/setting',
				}).then(res => {
					this.is_loading = false;
					if (res.code == 0 && res.data && res.data.setting) {
						this.form = Object.assign(this.form, res.data.setting);
						if (!this.form.goods_ids) {
							this.form.goods_ids = [];
							this.form.list = [];
							this.form.buy_type = '0';
						}
					}
				}).catch(() => {
					this.is_loading = false;
				})
			},
			save() {
				if (this.form.invite_type == 1) {
					if (!this.form.goods_ids || this.form.goods_ids.length == 0) {
						this.$message.error('请选择商品！');
						return;
					}
				}
				
				if (this.form.invite_type == 3 && (!this.form.price || this.form.price <= 0)) {
					this.$message.error('请输入有效的消费满金额！');
					return;
				}

				this.is_loading = true;
				this.is_saving = true;
				this.$request({
					url: '/mall/user/setting',
					data: this.form,
					method: 'post',
				}).then(res => {
					this.is_loading = false;
					this.is_saving = false;
					if (res.code == 0) {
						this.$message.success('保存成功');
					} else {
						this.$message.error(res.msg || '保存失败');
					}
				}).catch(() => {
					this.is_loading = false;
					this.is_saving = false;
				})
			}
		}


	}
</script>

<style scoped>
.form-tip {
	font-size: 13px;
	color: #909399;
	margin-top: 4px;
	line-height: 1.4;
}
</style>
