<template>
	<div class="cat-picker">
		<el-dialog :title="title" width="1100px" :visible.sync="dialogVisible" append-to-body>
			<div class="cat-picker-box" v-loading="dialogLoading" style="margin-top: -20px;">
				<template v-if="first_list.length > 0">
					<!-- 一级分类 -->
					<div class="cat-panel cat-level-1">
						<div class="panel-header">一级分类</div>
						<div class="cat-list">
							<div :class="{'active': current1 == option.id}" class="cat-item"
							 v-for="(option,index) in first_list" :key="option.id" @click="selectCats(option,0)">
								<div class="item-content">
									<span class="item-name">{{option.name}}</span>
									<i v-if="option.is_child" class="el-icon-arrow-right"></i>
								</div>
							</div>
						</div>
					</div>

					<!-- 二级分类 -->
					<div class="cat-panel cat-level-2" v-if="second_list.length > 0">
						<div class="panel-header">二级分类</div>
						<div class="cat-list">
							<div :class="{'active': current2 == option.id}" class="cat-item"
							 v-for="(option,index) in second_list" :key="option.id" @click="selectCats(option,1)">
								<div class="item-content">
									<span class="item-name">{{option.name}}</span>
									<i v-if="option.is_child" class="el-icon-arrow-right"></i>
								</div>
							</div>
						</div>
					</div>
                    <div class="cat-panel empty-panel" v-else-if="current1">
                        <el-empty description="此分类下无子分类" :image-size="60"></el-empty>
                    </div>

					<!-- 三级分类 -->
					<div class="cat-panel cat-level-3" v-if="third_list.length > 0">
						<div class="panel-header">三级分类</div>
						<div class="cat-list">
							<div :class="{'active': current3 == option.id}" class="cat-item"
							 v-for="(option,index) in third_list" :key="option.id" @click="selectCats(option,2)">
								<div class="item-content">
									<span class="item-name">{{option.name}}</span>
									<i v-if="option.is_child" class="el-icon-arrow-right"></i>
								</div>
							</div>
						</div>
					</div>
					<div class="cat-panel empty-panel" v-else-if="current2">
                        <el-empty description="此分类下无子分类" :image-size="60"></el-empty>
                    </div>
				</template>

				<template v-else-if="!dialogLoading">
					<div class="global-empty">
                        <el-empty :description="mch_id > 0 ? '暂无本店私有分类' : '暂无系统分类'"></el-empty>
					</div>
				</template>
			</div>
			<div class="tag-box" v-if="cats.length > 0">
				<el-tag type="warning" class="tag-item" v-for="(item, index) in cats" :key="item.value">
					{{item.name}}
				</el-tag>
			</div>
			<span slot="footer" class="dialog-footer">
				<el-button size='small' @click="dialogVisible = false">取 消</el-button>
				<el-button size='small' type="primary" @click="confirm">确 定</el-button>
			</span>
		</el-dialog>
	</div>

</template>

<script>
	export default {
		props: {
			mch_id: {
				type: Number,
				default: 0
			},
			newCats: {
				type: Array,
				default: function() {
					return []
				}
			},
			showCatPicker: {
				type: Boolean,
				default: false
			},
			url: {
				type: String,
				default: '/mall/goods/cat-list' // 默认值保持不变，防止破坏其他页面
			}
		},
		data() {
			return {
				dialogVisible: false,
				first_list: [], // 商品分类列表
				second_list: [],
				third_list: [],
				cats: [], //用于前端已选的分类展示
				dialogLoading: false,
				title: '选择分类',
				cat_type: 0, //0一级分类  1 二级分类 2三级分类
				current1: '',
				current2: '',
				current3: '',
				parent_cat_id: 0,
			}
		},
		watch: {
			showCatPicker(newVal, oldVal) {
				 
				if (newVal) {
					this.openDialog()
				}

			},
			dialogVisible(newVal, oldVal) {

				 
				if (!newVal) {
					this.$emit('closed')
				}

			}

		},
		methods: {
			openDialog() {
				let self = this;
				this.getCats();
				self.title = self.mch_id > 0 ? '选择多商户分类' : '选择分类';
				this.cats = [];
				this.children = [];
				this.third = [];
				this.dialogVisible = true;
			},
			// 获取商品分类
			getCats() {
				let self = this;
				self.dialogLoading = true;
				this.$request({
					url: this.url,
					data: {
						mch_id: self.mch_id,
						type: this.cat_type,
						parent_cat_id: this.parent_cat_id
					},
				}).then(e => {
					self.dialogLoading = false;
					if (e.code === 0) {
						if (this.cat_type == 0) {
							self.first_list = e.data.list;
						}
						if (this.cat_type == 1) {
							self.second_list = e.data.list;
						}
						if (this.cat_type == 2) {
							self.third_list = e.data.list;
						}
						//	self.setCats();
					} else {
						self.$message.error(e.msg);
					}
				}).catch(e => {
					self.dialogLoading = false;
				});
			},
			// 选择分类
			selectCats(option, type) {
				if (type === 0) {
					this.current1 = option.id;
					this.current2 = '';
					this.current3 = '';
					this.second_list = [];
					this.third_list = [];
					
					this.cats = [option];
					
					this.cat_type = 1;
					this.parent_cat_id = option.id;
					if (option.is_child) this.getCats();
				}
				if (type === 1) {
					this.current2 = option.id;
					this.current3 = '';
					this.third_list = [];
					
					let l1 = this.first_list.find(c => c.id === this.current1);
					this.cats = [];
					if (l1) this.cats.push(l1);
					this.cats.push(option);

					this.cat_type = 2;
					this.parent_cat_id = option.id;
					if (option.is_child) this.getCats();
				}
				if (type === 2) {
					this.current3 = option.id;
					
					let l1 = this.first_list.find(c => c.id === this.current1);
					let l2 = this.second_list.find(c => c.id === this.current2);
					this.cats = [];
					if (l1) this.cats.push(l1);
					if (l2) this.cats.push(l2);
					this.cats.push(option);
				}
			},
			confirm() {
				this.dialogVisible = false;
				this.$emit('selected', this.cats)
			}
		}
	}
</script>

<style>
    /* 完美的类 Admin 边界融合样式 */
	.cat-picker-box {
        display: flex;
        height: 480px;
        overflow: hidden;
        border: 1px solid #DCDFE6;
        border-radius: 4px;
        background-color: #fff;
	}

    .cat-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e6e6e6;
    }
    .cat-panel:last-child {
        border-right: none;
    }

    .cat-level-1 {
        background-color: #f5f7fa;
    }
    
    .panel-header {
        height: 40px;
        line-height: 40px;
        padding: 0 15px;
        font-size: 14px;
        color: #606266;
        font-weight: 600;
        border-bottom: 1px solid #ebeef5;
        background-color: #fafafa;
    }

    .cat-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
    }
    
    .empty-panel {
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #fff;
    }

    .global-empty {
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* 滚动条美化 */
    .cat-list::-webkit-scrollbar {
        width: 6px;
    }
    .cat-list::-webkit-scrollbar-thumb {
        border-radius: 3px;
        background-color: #ddd;
    }
    .cat-list::-webkit-scrollbar-track {
        background: transparent;
    }

	.cat-item {
        display: flex;
        align-items: center;
		cursor: pointer;
		padding: 0 15px;
        height: 42px;
        transition: all 0.2s;
        border-left: 3px solid transparent;
	}

    .item-content {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 100%;
    }

    .item-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cat-item:hover {
		background: #f0f2f5;
	}

	.cat-item.active {
		background: #ecf5ff !important;
        border-left-color: #409eff;
	}
    
    .cat-item.active .item-name {
        color: #409eff;
        font-weight: bold;
    }

	.cat-picker .el-checkbox {
		margin-right: 15px;
	}

	.cat-picker .tag-box {
		margin: 20px 0 0 0;
        padding-top: 15px;
        border-top: 1px dashed #e4e7ed;
	}

	.cat-picker .tag-box .tag-item {
		margin-right: 8px;
        margin-bottom: 8px;
	}
</style>
