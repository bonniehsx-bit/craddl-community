<template>
	<tui-page title="商品分类管理" :is-pulldown="true" @pulldown="onPullDownRefresh">
		<!-- ⚠ :style="themeStyle" 不能删:本页样式用了 var(--theme-color),该变量只由 themeStyle
		     注入。去掉后「添加一级分类」是白字+无背景 = 按钮肉眼消失(规范§14.2.1)。 -->
		<view class="page-container" :style="themeStyle">
			<!-- 分类列表 -->
			<scroll-view class="scroll-view" scroll-y>
				<view class="category-list" v-if="categoryList.length > 0">
					<view class="category-card" v-for="(cat, index) in categoryList" :key="cat.id">
						<!-- 一级分类行 -->
						<view class="level-1-row">
							<view class="level-1-main" @click="toggleExpand(cat)">
								<text class="level-1-name">{{ cat.name }}</text>
								<text class="sub-count">{{ (cat.children && cat.children.length) || 0 }} 个子类</text>
								<text class="sort-tip">排序 {{ cat.sort }}</text>
							</view>
							<view class="actions">
								<view class="action-item" @click="toggleExpand(cat)">
									<tui-icon :name="cat.isExpanded ? 'arrowup' : 'arrowdown'" color="#666" :size="16"></tui-icon>
									<text>{{ cat.isExpanded ? '收起' : '展开' }}</text>
								</view>
								<view class="action-item" @click="editCategory(cat)">
									<tui-icon name="edit" color="#666" :size="16"></tui-icon>
									<text>编辑</text>
								</view>
								<view class="action-item" @click="deleteCategory(cat)">
									<tui-icon name="delete" color="#666" :size="16"></tui-icon>
									<text>删除</text>
								</view>
							</view>
						</view>

						<!-- 二级分类列表 (展开时显示) -->
						<view class="level-2-wrapper" v-if="cat.isExpanded">
							<view class="empty-sub" v-if="!cat.children || !cat.children.length">该分类下还没有二级分类</view>
							<view class="level-2-item" v-for="(subCat, subIndex) in cat.children" :key="subCat.id">
								<text class="level-2-name">{{ subCat.name }}</text>
								<text class="sort-tip">排序 {{ subCat.sort }}</text>
								<view class="actions">
									<view class="action-item" @click="editCategory(subCat)">
										<tui-icon name="edit" color="#666" :size="16"></tui-icon>
									</view>
									<view class="action-item" @click="deleteCategory(subCat)">
										<tui-icon name="delete" color="#666" :size="16"></tui-icon>
									</view>
								</view>
							</view>
							<!-- 添加二级分类按钮 -->
							<view class="add-level-2-btn" @click="addCategory(2, cat)">
								<tui-icon name="plus" :color="themeColor" :size="16"></tui-icon>
								<text>添加二级分类</text>
							</view>
						</view>
					</view>
				</view>
				
				<tui-no-data v-if="!categoryList.length && !loading" :fixed="false">暂无分类，快去添加吧</tui-no-data>
				
			</scroll-view>

			<!-- 底部操作栏 -->
			<view class="footer-bar">
				<button class="add-level-1-btn" @click="addCategory(1)">
					<tui-icon name="plus" color="#fff" :size="18"></tui-icon>
					<text>添加一级分类</text>
				</button>
			</view>
			
			<!-- 【关键修正】: 新增/编辑弹窗，使用插槽自定义内容 -->
			<tui-modal :show="modal.show" @cancel="modal.show=false" :custom="true">
				<view class="modal-container">
					<view class="modal-title">{{ modal.title }}</view>
					<view class="modal-input-wrapper">
						<input class="modal-input" v-model="modal.name" :maxlength="45" placeholder="请输入分类名称" />
					</view>
					<view class="modal-input-wrapper" style="padding-top: 0;">
						<input class="modal-input" type="number" v-model="modal.sort" placeholder="排序权重 (默认100)" />
					</view>
					<view class="modal-actions">
						<view class="modal-btn cancel" @click="modal.show=false">取消</view>
						<view class="modal-btn confirm" @click="handleModalConfirm">确定</view>
					</view>
				</view>
			</tui-modal>

		</view>
	</tui-page>
</template>

<script>
	// 【说明】: 这里的 JS 逻辑与您提供的一模一样，未做任何修改
	export default {
		data() {
			return {
				loading: false,
				categoryList: [],
				modal: {
					show: false,
					title: '',
					name: '',
					sort: 100,
					isEdit: false,
					id: null,
					parent_id: 0
				}
			};
		},
		onLoad() {
			this.getCategoryList();
		},
		onPullDownRefresh() {
			this.getCategoryList().finally(() => uni.stopPullDownRefresh());
		},
		methods: {
			async getCategoryList() {
				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/goods-cat/index',
					});
					if (res.code === 0) {
						// 保留用户当前的展开/收起状态:加完一个二级分类就把人家手动收起的全弹开,
						// 分类多的商家每次都要重新收一遍。首次加载时按"有子分类就展开"。
						const prev = {};
						this.categoryList.forEach(c => { prev[c.id] = c.isExpanded; });
						this.categoryList = res.data.list.map(cat => ({
							...cat,
							isExpanded: prev[cat.id] !== undefined
								? prev[cat.id]
								: !!(cat.children && cat.children.length > 0),
						}));
					} else {
						this.$util.toast(res.msg);
					}
				} finally {
					this.loading = false;
				}
			},
			toggleExpand(category) {
				this.$set(category, 'isExpanded', !category.isExpanded);
			},
			addCategory(level, parentCategory = null) {
				this.modal = {
					show: true,
					title: level === 1 ? '添加一级分类' : `在「${parentCategory.name}」下添加二级分类`,
					name: '',
					sort: 100,
					isEdit: false,
					id: null,
					parent_id: parentCategory ? parentCategory.id : 0
				};
			},
			editCategory(category) {
				this.modal = {
					show: true,
					title: `编辑分类`,
					name: category.name,
					sort: category.sort || 100,
					isEdit: true,
					id: category.id,
					parent_id: category.parent_id
				};
			},
			async handleModalConfirm() {
				if (!this.modal.name.trim()) {
					return this.$util.toast('分类名称不能为空');
				}
				
				uni.showLoading({ title: '保存中...' });
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/goods-cat/edit',
						method: 'POST',
						data: {
							id: this.modal.id,
							name: this.modal.name,
							sort: this.modal.sort || 100,
							parent_id: this.modal.parent_id
						}
					});
					
					this.$util.toast(res.msg);
					if (res.code === 0) {
						// 刚在某个一级分类下新增了二级 → 强制展开父级。
						// 否则上面"保留展开状态"会沿用它原来的收起状态,把刚加的那条藏起来,
						// 商家会以为没添加成功、反复重加。
						if (!this.modal.isEdit && this.modal.parent_id) {
							const parent = this.categoryList.find(c => c.id === this.modal.parent_id);
							if (parent) this.$set(parent, 'isExpanded', true);
						}
						this.modal.show = false;
						this.getCategoryList(); // 成功后刷新列表
					}
				} finally {
					uni.hideLoading();
				}
			},
			deleteCategory(category) {
				// [安全校验] 如果有子分类，不允许删除
				if (category.children && category.children.length > 0) {
					return this.$util.toast('请先删除该分类下的所有二级分类');
				}

				uni.showModal({
					title: '确认删除',
					content: `删除后不可恢复，确认删除分类 "${category.name}" 吗？`,
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({ title: '删除中...' });
							try {
								const apiRes = await this.$request({
									url: '/plugin/mch/admin/goods-cat/delete',
									method: 'POST',
									data: { id: category.id }
								});
								this.$util.toast(apiRes.msg);
								if (apiRes.code === 0) {
									this.getCategoryList(); // 成功后刷新列表
								}
							} finally {
								uni.hideLoading();
							}
						}
					}
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 【说明】: 这里的样式是为您的HTML和JS逻辑量身定制的 */
	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f5f6fa;
	}

	.scroll-view {
		flex: 1;
		height: 0;
		padding-bottom: 160rpx;
	}

	.category-list {
		padding: 24rpx;
	}
	
	.category-card {
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.05);
	}

	.level-1-row {
		display: flex;
		align-items: center;
		padding: 32rpx 24rpx;
	}
	
	.level-1-main {
		display: flex;
		align-items: center;
		flex: 1;
		min-width: 0;
		/* #ifdef H5 */
		cursor: pointer;
		/* #endif */
	}

	.level-1-name {
		font-size: 30rpx;
		color: #333;
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* 子类数量与排序:中性灰,属信息标识非主题色,按规范§14.2.1保持硬编码 */
	.sub-count {
		font-size: 22rpx;
		color: #909399;
		background: #f2f3f5;
		padding: 2rpx 10rpx;
		border-radius: 6rpx;
		margin-left: 12rpx;
		flex-shrink: 0;
	}

	.sort-tip {
		font-size: 22rpx;
		color: #c0c4cc;
		margin-left: 12rpx;
		flex-shrink: 0;
	}

	.empty-sub {
		font-size: 26rpx;
		color: #909399;
		text-align: center;
		padding: 28rpx 0;
	}
	
	.actions {
		margin-left: auto;
		display: flex;
		align-items: center;
		color: #666;
		font-size: 26rpx;
	}
	
	.action-item {
		display: flex;
		align-items: center;
		margin-left: 30rpx;
		/* #ifdef H5 */
		cursor: pointer;
		/* #endif */
		text {
			margin-left: 6rpx;
		}
	}

	.level-2-wrapper {
		padding: 0 24rpx;
		border-top: 1rpx solid #f0f0f0;
	}
	
	.level-2-item {
		display: flex;
		align-items: center;
		padding: 28rpx 0;
		border-bottom: 1rpx solid #f0f0f0;
		padding-left: 20rpx;
	}
	
	.level-2-name {
		font-size: 28rpx;
		color: #333;
	}

	.add-level-2-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--theme-color);
		font-size: 28rpx;
		padding: 24rpx 0;
		border: 1rpx dashed #dcdfe6;
		border-radius: 12rpx;
		margin: 24rpx 0;
		text {
			margin-left: 8rpx;
		}
	}
	
	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background-color: #fff;
		padding: 20rpx 24rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		box-shadow: 0 -2rpx 10rpx rgba(0,0,0,0.05);
		z-index: 10;
	}

	.add-level-1-btn {
		background-color: var(--theme-color);
		color: #fff;
		height: 88rpx;
		line-height: 88rpx;
		border-radius: 44rpx;
		font-size: 32rpx;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		&::after {
			border: none;
		}
		text {
			margin-left: 10rpx;
		}
	}
	
	.modal-title {
		text-align: center;
		font-size: 34rpx;
		color: #333;
		padding-top: 20rpx;
		font-weight: bold;
	}

	.modal-input-wrapper {
		padding: 40rpx 0;
	}

	.modal-input {
		background-color: #f5f5f5;
		border-radius: 16rpx;
		padding: 0 24rpx;
		font-size: 28rpx;
		height: 88rpx;
		box-sizing: border-box;
	}

	.modal-actions {
		display: flex;
		border-top: 1rpx solid #eee;
	}

	.modal-btn {
		flex: 1;
		height: 90rpx;
		line-height: 90rpx;
		font-size: 30rpx;
		text-align: center;

		&.cancel {
			color: #666;
		}
		&.confirm {
			color: var(--theme-color);
			font-weight: 500;
			border-left: 1rpx solid #eee;
		}
	}
</style>