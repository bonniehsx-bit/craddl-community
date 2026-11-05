<template>
	<div class="app-container">
		<el-card class="box-card">
			<div slot="header" class="clearfix">
				<span>页面路径</span>
			</div>

			<!-- 工具栏：搜索 + 分组筛选 -->
			<div class="page-toolbar">
				<el-input
					v-model="keyword"
					size="small"
					clearable
					placeholder="搜索页面名称 / 路径"
					prefix-icon="el-icon-search"
					style="width: 260px;" />
				<el-select v-model="activeGroup" size="small" clearable placeholder="全部分组" style="width: 200px; margin-left: 12px;">
					<el-option v-for="g in groups" :key="g.name"
						:label="g.name + (g.type === 'plugin' ? '（插件）' : '')"
						:value="g.name" />
				</el-select>
				<span class="page-count">共 {{ totalCount }} 个页面，当前显示 {{ shownCount }} 个</span>
			</div>

			<!-- 按分组渲染 -->
			<div v-if="filteredGroups.length" class="page-groups">
				<div v-for="grp in filteredGroups" :key="grp.group" class="page-group">
					<div class="group-title">
						<span class="group-bar" :class="grp.group_type"></span>
						<span class="group-name">{{ grp.group }}</span>
						<el-tag size="mini" :type="grp.group_type === 'plugin' ? 'warning' : 'success'" effect="plain">
							{{ grp.group_type === 'plugin' ? '插件' : '核心' }}
						</el-tag>
						<span class="group-num">{{ grp.items.length }} 个</span>
					</div>

					<div class="card-grid">
						<div class="page-item" v-for="(item, index) in grp.items" :key="index">
							<div class="flex-x-between item-head">
								<div class="item-name">{{ item.name }}</div>
								<el-button type="text" @click="save(item)" v-if="item.is_show_save">保存</el-button>
							</div>
							<div class="item-path copyable" :title="'点击复制路径:' + item.page" @click="copyText(item.page, '页面路径')">
								{{ item.page }}<i class="el-icon-document-copy copy-icon"></i>
							</div>
							<div class="item-actions">
								<el-button type="text" class="item-h5" @click="copyText(buildH5(item.page), 'H5链接')">
									<i class="el-icon-document-copy"></i> 复制H5链接
								</el-button>
								<el-button type="text" class="item-qr" @click="showH5(item.page)">
									<i class="el-icon-picture-outline"></i> 二维码
								</el-button>
							</div>
							<el-input size="mini" @input="showSaveBtn(item)" placeholder="输入页面标题" v-model="item.name"></el-input>
						</div>
					</div>
				</div>
			</div>

			<div v-else class="page-empty">没有匹配的页面</div>

			<el-dialog title="二维码链接" :visible.sync="dialogVisible" width="420px" :before-close="handleClose">
				<div v-loading="loading" style="text-align: center;">
					<vue-qr :text="link" :size="200"></vue-qr>
					<div class="dialog-link copyable" title="点击复制链接" @click="copyText(link, 'H5链接')">
						{{ link }}<i class="el-icon-document-copy copy-icon"></i>
					</div>
				</div>
				<span slot="footer" class="dialog-footer">
					<el-button @click="dialogVisible = false">关 闭</el-button>
					<el-button type="primary" icon="el-icon-document-copy" @click="copyText(link, 'H5链接')">复制链接</el-button>
				</span>
			</el-dialog>

		</el-card>
	</div>
</template>

<script>
import vueQr from 'vue-qr'
export default {
	name: 'mall-page',
	components: {
		vueQr
	},
	data() {
		return {
			loading: false,
			list: [],
			h5Root: '',
			mall_id: '',
			dialogVisible: false,
			link: '',
			// 筛选
			keyword: '',
			activeGroup: '',
		}
	},
	computed: {
		// 全部分组（去重，保留出现顺序）
		groups() {
			const seen = {};
			const arr = [];
			for (const it of this.list) {
				if (!seen[it.group]) {
					seen[it.group] = true;
					arr.push({ name: it.group, type: it.group_type });
				}
			}
			return arr;
		},
		totalCount() {
			return this.list.length;
		},
		shownCount() {
			return this.filteredGroups.reduce((sum, g) => sum + g.items.length, 0);
		},
		// 按分组聚合 + 应用关键词/分组筛选
		filteredGroups() {
			const kw = (this.keyword || '').trim().toLowerCase();
			const map = {};
			const result = [];
			for (const it of this.list) {
				if (this.activeGroup && it.group !== this.activeGroup) continue;
				if (kw) {
					const hay = ((it.name || '') + ' ' + (it.page || '')).toLowerCase();
					if (hay.indexOf(kw) === -1) continue;
				}
				const key = it.group || '其他';
				if (!map[key]) {
					map[key] = { group: key, group_type: it.group_type, items: [] };
					result.push(map[key]);
				}
				map[key].items.push(it);
			}
			return result;
		},
	},
	created() {
		this.getPage();
	},
	methods: {
		handleClose() {
			this.dialogVisible = false;
		},
		showSaveBtn(row) {
			this.$set(row, 'is_show_save', true);
		},
		// H5 链接拼装抽出来,复制与二维码共用同一份,避免两处各拼一次拼出不一样的结果
		buildH5(page) {
			return this.h5Root + '/#' + page + "?mall_id=" + this.mall_id;
		},
		showH5(page) {
			this.dialogVisible = true;
			this.link = this.buildH5(page);
		},
		// 与订单页 copyText 同一范式:用临时 textarea + execCommand,
		// 不用 navigator.clipboard —— 后台常部署在内网 http 下,那个 API 在非安全上下文里不可用。
		copyText(text, label = '内容') {
			if (!text) return;
			const input = document.createElement('textarea');
			input.value = text;
			input.style.position = 'fixed';
			input.style.opacity = '0';
			document.body.appendChild(input);
			input.select();
			try {
				if (document.execCommand('copy')) {
					this.$message.success(`${label}已复制:${text}`);
				} else {
					this.$message.error('复制失败,请手动选中复制');
				}
			} catch (err) {
				this.$message.error('浏览器不支持快捷复制,请手动复制');
			}
			document.body.removeChild(input);
		},
		save(row) {
			if (!row.name || row.name == '') {
				this.$message.error('页面标题不能为空');
				return;
			}
			row.page_path = row.page;
			this.$request({
				url: '/mall/mall/page-edit',
				data: row,
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					this.getPage();
				}
			})
		},
		getPage() {
			this.$request({
				url: '/mall/mall/page'
			}).then(res => {
				if (res.code == 0) {
					this.list = res.data.list;
					this.mall_id = res.data.mall_id;
					this.h5Root = res.data.h5Root;
				}
			})
		}
	}
}
</script>

<style lang="scss" scoped>
.page-toolbar {
	display: flex;
	align-items: center;
	margin-bottom: 20px;

	.page-count {
		margin-left: auto;
		font-size: 13px;
		color: #909399;
	}
}

.page-group {
	margin-bottom: 24px;

	.group-title {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;

		.group-bar {
			width: 4px;
			height: 16px;
			border-radius: 2px;
			background: #67c23a;

			&.plugin { background: #e6a23c; }
		}

		.group-name {
			font-size: 15px;
			font-weight: 700;
			color: #303133;
		}

		.group-num {
			font-size: 12px;
			color: #c0c4cc;
		}
	}
}

.card-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.page-item {
	width: 239px;
	background: #f3f3f3;
	border-radius: 5px;
	padding: 10px;
	box-sizing: border-box;

	.item-head {
		font-weight: bold;
		font-size: 16px;
	}

	.item-name {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.item-path {
		color: var(--admin-accent-text);
		padding: 5px 0;
		font-size: 12px;
		word-break: break-all;
	}

	// 一行放两个操作,别把卡片撑高
	.item-actions {
		display: flex;
		align-items: center;
		gap: 12px;
		padding-bottom: 5px;
	}

	.item-h5,
	.item-qr {
		color: var(--admin-accent-text);
		padding: 0;
		font-size: 12px;
	}

	.item-qr {
		color: #909399;
	}
}

// 可点复制:平时只留一个淡图标,hover 时才明显 —— 让"能点"这件事看得出来,又不喧宾夺主
.copyable {
	cursor: pointer;
	transition: color .15s;

	.copy-icon {
		margin-left: 4px;
		opacity: .35;
		font-size: 12px;
	}

	&:hover {
		color: var(--admin-accent-text);

		.copy-icon { opacity: 1; }
	}
}

.dialog-link {
	margin-top: 12px;
	padding: 8px 10px;
	background: #f5f7fa;
	border-radius: 4px;
	font-size: 12px;
	color: #606266;
	word-break: break-all;
	line-height: 1.6;
}

.page-empty {
	text-align: center;
	color: #c0c4cc;
	padding: 60px 0;
	font-size: 14px;
}
</style>
