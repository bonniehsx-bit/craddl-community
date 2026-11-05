<template>
	<div class="app-container">
    <el-card class="box-card" v-loading='is_loading'>
      <div slot="header" class="clearfix">
        <span>参数设置</span>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="submit">保存</el-button>
      </div>
      <div>
        <el-row>
          <el-col :span="12">
           <el-form size="mini" ref="form" :model="form" label-width="120px">
              <el-form-item label="公众号名称">
                <el-input v-model="form.name"></el-input>
              </el-form-item>
              <el-form-item label="APPID">
                <el-input v-model="form.appid"></el-input>
              </el-form-item>
              <el-form-item label="密钥">
                <el-input v-model="form.secret"></el-input>
              </el-form-item>
              <el-form-item label="token">
                <el-input v-model="form.token"></el-input>
              </el-form-item>
              <el-form-item label="消息加解密密钥">
                <el-input v-model="form.aes_key"></el-input>
              </el-form-item>


            </el-form>
          </el-col>
        </el-row>
      </div>
    </el-card>

    <!-- 公众号域名验证文件：填文件名+内容，系统写到 web 根目录(api/web)，供 https://域名/文件名 直接访问 -->
    <el-card class="box-card" v-loading="verifyLoading" style="margin-top: 16px;">
      <div slot="header" class="clearfix">
        <span>公众号域名验证文件</span>
      </div>
      <div class="verify-help">
        微信后台设置「JS接口安全域名 / 网页授权域名 / 业务域名」时，会要求把一个验证文件放到网站根目录。<br />
        把微信给的【文件名】和【文件内容】填到下面点保存，系统会生成到根目录，可从 <b>https://你的域名/文件名</b> 直接访问完成验证。
      </div>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form size="mini" label-width="80px">
            <el-form-item label="文件名">
              <el-input v-model="verifyForm.filename" placeholder="如 MP_verify_xxxxxxxx.txt"></el-input>
            </el-form-item>
            <el-form-item label="文件内容">
              <el-input type="textarea" :rows="3" v-model="verifyForm.content"
                placeholder="粘贴微信给的文件内容（通常是一行字符）"></el-input>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" size="mini" :loading="verifySaving" @click="saveVerifyFile">生成 / 保存</el-button>
              <span class="verify-tip">仅支持 字母/数字/下划线/连字符 命名的 .txt 文件</span>
            </el-form-item>
          </el-form>
        </el-col>
        <el-col :span="12">
          <div class="verify-list-title">已生成的验证文件</div>
          <div v-if="!verifyFiles.length" class="verify-empty">暂无，去左侧填写生成</div>
          <div v-for="f in verifyFiles" :key="f.filename" class="verify-item">
            <div class="vf-row">
              <span class="vf-name">{{ f.filename }}</span>
              <el-button type="text" size="mini" style="color:#F56C6C" @click="deleteVerifyFile(f)">删除</el-button>
            </div>
            <a :href="f.url" target="_blank" class="vf-url">{{ f.url }}</a>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 公众号推送记录：模板消息发没发出去、为什么没发出去。数据来自核心 wx_notify_log，各插件的推送都在这里 -->
    <el-card class="box-card" v-loading="logLoading" style="margin-top: 16px;">
      <div slot="header" class="clearfix">
        <span>推送记录</span>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="getLogs(1)">刷新</el-button>
      </div>

      <div class="log-help">
        用户收不到通知，先来这里看一眼：<b>已跳过</b>基本就是「这个人没绑公众号」或「这个场景没配模板 ID」；
        <b>发送失败</b>会带上微信返回的 errcode/errmsg。记录保留 90 天。
      </div>

      <div class="log-filter">
        <el-select v-model="logQuery.plugin" size="mini" clearable placeholder="插件" style="width:130px" @change="getLogs(1)">
          <el-option v-for="p in pluginOptions" :key="p" :label="p" :value="p" />
        </el-select>
        <el-select v-model="logQuery.scene" size="mini" clearable filterable placeholder="场景" style="width:220px" @change="getLogs(1)">
          <el-option v-for="sc in sceneOptions" :key="sc" :label="sc" :value="sc" />
        </el-select>
        <el-select v-model="logQuery.status" size="mini" clearable placeholder="结果" style="width:120px" @change="getLogs(1)">
          <el-option v-for="(text, val) in statusMap" :key="val" :label="text" :value="Number(val)" />
        </el-select>
        <el-date-picker v-model="logDateRange" size="mini" type="daterange" value-format="yyyy-MM-dd"
          range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" style="width:240px"
          @change="getLogs(1)" />
        <el-input v-model="logQuery.keyword" size="mini" clearable placeholder="openid / 失败原因 / 场景"
          style="width:220px" @keyup.enter.native="getLogs(1)" />
        <el-button type="primary" size="mini" icon="el-icon-search" @click="getLogs(1)">查询</el-button>
      </div>

      <div class="log-summary" v-if="logSummary.total !== undefined">
        共 <b>{{ logSummary.total }}</b> 条 ·
        <span class="ls-ok">已发送 {{ logSummary[1] || 0 }}</span> ·
        <span class="ls-fail">发送失败 {{ logSummary[2] || 0 }}</span> ·
        <span class="ls-skip">已跳过 {{ logSummary[3] || 0 }}</span>
      </div>

      <el-table :data="logs" size="mini" border style="margin-top:10px">
        <el-table-column label="时间" prop="created_time" width="150" />
        <el-table-column label="场景" width="230">
          <template slot-scope="s">
            <div>{{ s.row.scene }}</div>
            <div class="log-sub">{{ s.row.plugin }}</div>
          </template>
        </el-table-column>
        <el-table-column label="接收人" width="150">
          <template slot-scope="s">
            <div>{{ s.row.user_name }}</div>
            <div class="log-sub">{{ s.row.openid_mask || '未绑定' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="结果" width="100">
          <template slot-scope="s">
            <el-tag size="mini" :type="logTagType(s.row.status)">{{ s.row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="原因 / 说明" show-overflow-tooltip>
          <template slot-scope="s">{{ s.row.reason || '—' }}</template>
        </el-table-column>
        <el-table-column label="内容" width="70" align="center">
          <template slot-scope="s">
            <el-button type="text" size="mini" @click="showPayload(s.row)">查看</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container" style="margin-top:12px; text-align:right">
        <el-pagination background layout="total, prev, pager, next" :total="logPagination.total_count || 0"
          :page-size="logQuery.limit" :current-page="logQuery.page" @current-change="getLogs" />
      </div>
    </el-card>

    <el-dialog title="推送内容" :visible.sync="payloadVisible" width="560px">
      <el-descriptions :column="1" border size="small" v-if="payloadRow">
        <el-descriptions-item label="场景">{{ payloadRow.scene }}</el-descriptions-item>
        <el-descriptions-item label="模板 ID">{{ payloadRow.template_id || '未配置' }}</el-descriptions-item>
        <el-descriptions-item label="openid">{{ payloadRow.openid || '未绑定' }}</el-descriptions-item>
        <el-descriptions-item label="跳转页">{{ payloadRow.page_url || '无' }}</el-descriptions-item>
        <el-descriptions-item label="模板数据">
          <pre class="log-payload">{{ payloadText }}</pre>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script>
	export default {
    name:'platform-wechat',
		data() {
			return {
				is_loading: false,
				form: {
					name: '',
					appid: '',
					secret: '',
					token: '',
					aes_key: ''
				},
				// 域名验证文件
				verifyLoading: false,
				verifySaving: false,
				verifyForm: { filename: '', content: '' },
				verifyFiles: [],
				// 推送记录
				logLoading: false,
				logs: [],
				logPagination: {},
				logSummary: {},
				logQuery: { page: 1, limit: 20, plugin: '', scene: '', status: '', keyword: '', start_date: '', end_date: '' },
				logDateRange: [],
				statusMap: {},
				pluginOptions: [],
				sceneOptions: [],
				payloadVisible: false,
				payloadRow: null
			}
		},
		computed: {
			// payload 存的是 JSON 串，格式化一下再给人看
			payloadText() {
				const raw = this.payloadRow && this.payloadRow.payload;
				if (!raw) return '（无）';
				try { return JSON.stringify(JSON.parse(raw), null, 2); } catch (e) { return String(raw); }
			}
		},
		created: function() {

			this.getWechat();
			this.getVerifyFiles();
			this.getLogs(1);

		},
		methods: {


			menu() {
				this.$router.push({
					path: "/wechat/menu"
				})

			},
			getWechat() {
				this.is_loading = true;
				this.$request({
					url: '/mall/platform/wechat'
				}).then(res => {
					this.is_loading = false;
					if (res.code == 0) {
						this.form = res.data.wechat;
					}
				}).catch(e => {
					this.is_loading = false;
				})
			},
			submit() {
				this.$request({
					url: '/mall/platform/wechat',
					data: this.form,
					method: 'post'
				}).then(res => {
					if (res.code == 0) {
						this.$message.success(res.msg);
					}
				})
			},
			// ── 域名验证文件 ──
			getVerifyFiles() {
				this.verifyLoading = true;
				this.$request({ url: '/mall/platform/wechat-verify-file-list' }).then(res => {
					this.verifyLoading = false;
					if (res.code == 0) this.verifyFiles = res.data.list || [];
				}).catch(() => { this.verifyLoading = false; });
			},
			saveVerifyFile() {
				if (!this.verifyForm.filename || !this.verifyForm.content) {
					return this.$message.warning('请填写文件名和文件内容');
				}
				this.verifySaving = true;
				this.$request({
					url: '/mall/platform/wechat-verify-file-save',
					method: 'post',
					data: this.verifyForm
				}).then(res => {
					this.verifySaving = false;
					if (res.code == 0) {
						this.$message.success(res.msg);
						this.verifyForm = { filename: '', content: '' };
						this.getVerifyFiles();
					} else {
						this.$message.error(res.msg);
					}
				}).catch(() => { this.verifySaving = false; });
			},
			// ── 推送记录 ──
			getLogs(page) {
				this.logQuery.page = page || 1;
				this.logQuery.start_date = (this.logDateRange && this.logDateRange[0]) || '';
				this.logQuery.end_date = (this.logDateRange && this.logDateRange[1]) || '';
				this.logLoading = true;
				this.$request({ url: '/mall/platform/wechat-notify-log', data: this.logQuery }).then(res => {
					this.logLoading = false;
					if (res.code != 0) return this.$message.error(res.msg);
					this.logs = res.data.list || [];
					this.logPagination = res.data.pagination || {};
					this.logSummary = res.data.summary || {};
					this.statusMap = res.data.status_map || {};
					this.pluginOptions = res.data.plugin_options || [];
					this.sceneOptions = res.data.scene_options || [];
				}).catch(() => { this.logLoading = false; });
			},
			logTagType(status) {
				return { 1: 'success', 2: 'danger', 3: 'info' }[status] || 'info';
			},
			showPayload(row) {
				this.payloadRow = row;
				this.payloadVisible = true;
			},
			deleteVerifyFile(f) {
				this.$confirm(`确定删除验证文件 ${f.filename} 吗？`, '提示', { type: 'warning' }).then(() => {
					this.$request({
						url: '/mall/platform/wechat-verify-file-delete',
						method: 'post',
						data: { filename: f.filename }
					}).then(res => {
						if (res.code == 0) { this.$message.success('已删除'); this.getVerifyFiles(); }
						else this.$message.error(res.msg);
					});
				}).catch(() => {});
			},
		}
	}
</script>

<style scoped>
.log-help {
  background: #f4f4f5;
  color: #606266;
  font-size: 12px;
  line-height: 1.8;
  padding: 10px 14px;
  border-radius: 4px;
  margin-bottom: 12px;
}
.log-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.log-summary { margin-top: 10px; font-size: 13px; color: #606266; }
.log-summary .ls-ok { color: #67C23A; }
.log-summary .ls-fail { color: #F56C6C; }
.log-summary .ls-skip { color: #909399; }
.log-sub { font-size: 11px; color: #909399; }
.log-payload { margin: 0; white-space: pre-wrap; word-break: break-all; font-size: 12px; max-height: 260px; overflow: auto; }
.verify-help {
  background: #f4f4f5;
  color: #606266;
  font-size: 12px;
  line-height: 1.8;
  padding: 10px 14px;
  border-radius: 4px;
  margin-bottom: 16px;
}
.verify-tip { margin-left: 12px; font-size: 12px; color: #c0c4cc; }
.verify-list-title { font-size: 13px; font-weight: 600; color: #303133; margin-bottom: 10px; }
.verify-empty { font-size: 12px; color: #c0c4cc; padding: 8px 0; }
.verify-item {
  padding: 10px 12px;
  background: #f7f9fc;
  border-radius: 6px;
  margin-bottom: 8px;
}
.verify-item .vf-row { display: flex; align-items: center; justify-content: space-between; }
.verify-item .vf-name { font-size: 13px; font-weight: 600; color: #303133; word-break: break-all; }
.verify-item .vf-url {
  display: block;
  font-size: 12px;
  color: var(--admin-accent-text);
  margin-top: 4px;
  word-break: break-all;
}
</style>
