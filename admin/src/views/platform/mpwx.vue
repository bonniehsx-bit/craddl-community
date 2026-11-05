<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <span>参数设置</span>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="submit">保存</el-button>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="drawerVisible=true">上传代码
        </el-button>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="openLogDrawer">上传记录
        </el-button>
        <el-button style="float: right; padding: 3px 20px;" type="text" @click="codeDialogVisible = true">获取小程序码
        </el-button>
        <el-button style="float: right; padding: 3px 20px;" type="text" :loading="tokenClearing" @click="clearToken">清理微信缓存
        </el-button>
      </div>
      <div>
        <el-row>
          <el-col :span="12">
           <el-form size="mini" ref="form" :model="form" label-width="120px">
              <el-form-item label="小程序名称">
                <el-input v-model="form.name"></el-input>
              </el-form-item>
              <el-form-item label="原始ID">
                <el-input v-model="form.id"></el-input>
              </el-form-item>
              <el-form-item label="APPID">
                <el-input v-model="form.appid"></el-input>
              </el-form-item>
              <el-form-item label="MCHID">
                <el-input v-model="form.mchid"></el-input>
                <div>小程序订单发货推送的商户号</div>
              </el-form-item>
              <el-form-item label="密钥">
                <el-input v-model="form.secret"></el-input>
              </el-form-item>


              <el-form-item label="小程序插件">
                <div class="plugin-rows">
                  <div v-for="(p, i) in pluginRows" :key="i" class="plugin-row">
                    <el-input v-model="p.alias" placeholder="引用名 如 myPlugin" style="width:170px" />
                    <el-input v-model="p.provider" placeholder="插件 AppID（填了才会注入）" style="width:260px;margin:0 8px" />
                    <el-input v-model="p.version" placeholder="版本 如 2.5.5" style="width:120px" />
                    <el-button type="text" icon="el-icon-delete" style="margin-left:8px;color:#F56C6C"
                               @click="pluginRows.splice(i, 1)"></el-button>
                  </div>
                  <el-button type="text" icon="el-icon-plus" @click="pluginRows.push({ alias: '', provider: '', version: '' })">
                    添加插件
                  </el-button>
                </div>

                <!-- 插件一律进主包,所以主包还剩多少是唯一要盯的数。放不下会在上传时
                     明确报错(不会静默),但让人上传前就知道更省事。 -->
                <div v-if="pluginRows.length && mainPack" class="pack-hint"
                     :class="{ danger: mainPack.free_kb < 350 }">
                  <i :class="mainPack.free_kb < 350 ? 'el-icon-warning' : 'el-icon-info'"></i>
                  当前代码包 <b>主包 {{ mainPack.size_kb }}KB</b>，离 2048KB 上限还剩
                  <b>{{ mainPack.free_kb }}KB</b>。插件体积会加到这里
                  <span v-if="mainPack.free_kb < 350">
                    —— <b>可能放不下</b>（「商圈会员快速积分」约 291KB）。请先把只被单个分包使用的组件
                    下沉到那个分包腾体积，再上传。
                  </span>
                </div>

                <div class="form-tip">
                  上传时写进 app.json 顶层的 <code>plugins</code>。<b>填了 AppID 就会注入，留空就不注入</b>，不用去别处找开关。<br>
                  ⚠ 注入的插件<b>必须已在微信公众平台「设置 → 第三方服务 → 添加插件」申请通过</b>，否则本次上传会直接失败。<br>
                  引用名要和小程序里 <code>requirePlugin('引用名')</code> 一致；无感积分用的是「商圈会员快速积分」，引用名固定 <code>myPlugin</code>。<br>
                  <b style="color:var(--admin-accent-text)">插件一律进主包，不再可选分包。</b>
                  分包里声明的插件，<b>主包代码（app.js / App.vue）<code>requirePlugin</code> 拿不到</b>。
                  而两种配错的代价完全不对等：主包放不下会<b>当场报错</b>传不上去；
                  放进分包却是<b>一切正常、功能就是不工作</b>，没有任何地方会提示
                  （无感积分的位置上报就这样静默失效过一次）。所以宁可去腾主包体积，也不留这个坑。
                </div>
              </el-form-item>
              <el-form-item label="上传密钥">
                <el-input type="textarea" v-model="form.upload_key" :autosize="{ minRows: 2, maxRows: 20 }"></el-input>
                <div>注：自动上传代码请关闭白名单</div>
              </el-form-item>
            </el-form>
          </el-col>
        </el-row>
      </div>
    </el-card>
    <el-drawer title="上传小程序代码" :visible.sync="drawerVisible" size="460px" @closed="resetUpload"
               :wrapper-closable="!uploading" :show-close="!uploading" :close-on-press-escape="!uploading">
      <div class="drawer-body">
        <!-- 表单：未上传 / 未出结果时显示 -->
        <el-form v-show="!uploading && !uploadFinished" size="small" ref="form" :model="form" label-width="100px">
          <el-form-item label="版本号">
            <el-input v-model="form.version" placeholder="留空自动按时间生成，如 1.0.0"></el-input>
            <div class="form-tip">默认读取主框架当前版本号（api/version.json），可按需修改；留空则自动按时间生成</div>
          </el-form-item>
          <el-form-item label="描述信息">
            <el-input v-model="form.desc" placeholder="本次上传说明"></el-input>
          </el-form-item>
          <el-form-item label="指定域名">
            <el-input v-model="form.inject_domain" placeholder="留空则用当前后端域名"></el-input>
            <div class="form-tip">注入小程序的后端域名，默认当前，可手动改（需含 http(s)://）</div>
          </el-form-item>
          <el-form-item label="指定商城ID">
            <el-input v-model="form.inject_mall_id" placeholder="留空则用当前商城"></el-input>
            <div class="form-tip">注入小程序的 mall_id，默认当前商城，可手动改</div>
          </el-form-item>
          <el-form-item label="本次插件声明">
            <el-alert v-if="effectivePlugins.length" type="warning" :closable="false" show-icon
                      :title="'本次上传会注入 ' + effectivePlugins.length + ' 个插件声明'">
              <div style="font-size:12px;line-height:1.8">
                <div v-for="p in effectivePlugins" :key="p.alias">
                  <code>{{ p.alias }}</code> → AppID <code>{{ p.provider }}</code>　版本 <code>{{ p.version || 'latest' }}</code>
                  　写入 <span style="color:#E6A23C">app.json 顶层（体积计入主包）</span>
                </div>
                <b>这些插件必须已申请到使用权限，否则上传会失败。</b>改动插件配置后记得先保存再上传。
                <div v-if="mainPack" style="margin-top:4px">
                  主包 {{ mainPack.size_kb }}KB，余 {{ mainPack.free_kb }}KB<span
                    v-if="mainPack.free_kb < 350" style="color:#F56C6C">
                    —— 可能装不下插件，上传会报 main package source size exceed max limit</span>
                </div>
              </div>
            </el-alert>
            <el-alert v-else type="info" :closable="false" show-icon title="本次上传不带任何插件声明">
              <div style="font-size:12px;line-height:1.7">代码包保持干净，不受任何插件资质限制。</div>
            </el-alert>
          </el-form-item>

        </el-form>

        <!-- 进度：上传中显示 -->
        <div v-if="uploading" class="upload-progress">
          <el-progress type="circle" :percentage="uploadProgress"
                       :status="uploadProgress >= 100 ? 'success' : null"
                       :width="130"></el-progress>
          <div class="up-status">{{ uploadStatusText }}</div>
          <div class="up-elapsed">已用时 {{ uploadElapsed }}s</div>
          <ul class="up-steps">
            <li v-for="(s, i) in uploadSteps" :key="i"
                :class="{ done: i < uploadStep, active: i === uploadStep }">
              <i :class="i < uploadStep ? 'el-icon-success' : (i === uploadStep ? 'el-icon-loading' : 'el-icon-time')"></i>
              {{ s.text }}
            </li>
          </ul>
          <div class="up-tip">上传通常需 30秒~2分钟，请勿关闭页面，耐心等待…</div>
        </div>

        <!-- 结果：上传结束后常驻显示，不自动消失 -->
        <div v-if="uploadFinished" class="upload-result">
          <i class="ur-icon" :class="uploadFailed ? 'el-icon-circle-close' : 'el-icon-circle-check'"
             :style="{ color: uploadFailed ? '#f56c6c' : '#67c23a' }"></i>
          <div class="ur-title" :style="{ color: uploadFailed ? '#f56c6c' : '#67c23a' }">
            {{ uploadFailed ? '上传失败' : '上传成功' }}
          </div>
          <div class="ur-msg">{{ uploadResultMsg }}</div>
          <div v-if="!uploadFailed" class="ur-hint">请登录微信公众平台 → 版本管理，提交审核并发布</div>
        </div>
      </div>
      <div class="drawer-footer">
        <span v-if="uploading" class="up-footer-tip"><i class="el-icon-loading"></i> 正在上传，请稍候…</span>
        <template v-else-if="uploadFinished">
          <el-button @click="resetUpload">重新上传</el-button>
          <el-button type="primary" @click="drawerVisible = false">完成</el-button>
        </template>
        <template v-else>
          <el-button @click="drawerVisible = false">取消</el-button>
          <el-button type="primary" @click="upload">确定上传</el-button>
        </template>
      </div>
    </el-drawer>

    <!-- 上传记录 -->
    <el-drawer title="上传记录" :visible.sync="logDrawerVisible" size="72%"
                 custom-class="upload-log-drawer" :wrapper-closable="true">
      <div class="log-drawer-body" v-loading="logLoading">
        <div class="log-table-wrap">
          <el-table :data="logList" height="100%" border size="small">
            <el-table-column prop="version" label="版本号" width="120" show-overflow-tooltip></el-table-column>
            <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip></el-table-column>
            <el-table-column label="状态" width="70" align="center">
              <template slot-scope="{ row }">
                <el-tag :type="row.status == 1 ? 'success' : 'danger'" size="mini">{{ row.status_text }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="admin_name" label="操作人" width="90" show-overflow-tooltip></el-table-column>
            <el-table-column prop="created_time" label="上传时间" width="150"></el-table-column>
            <el-table-column label="详情/失败原因" min-width="320">
              <template slot-scope="{ row }">
                <!-- 上传失败时是微信 CI 抛的原始报错，动辄几百字符还带换行。
                     show-overflow-tooltip 只显示一行、也复制不出来 ——
                     而这段报错恰恰是排查问题唯一的线索，必须能完整看到、能拷走 -->
                <div class="msg-cell">
                  <span class="msg-text">{{ row.msg || '—' }}</span>
                  <el-button v-if="isLongMsg(row.msg)" type="text" size="mini"
                             class="msg-more" @click="openMsgDetail(row)">查看</el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>
        <div class="pagination-container" v-if="logPagination.totalCount > 0">
          <el-pagination background layout="prev, pager, next, total"
                         :current-page="logPage" :page-size="logPagination.pageSize || 10"
                         :total="logPagination.totalCount" @current-change="onLogPageChange">
          </el-pagination>
        </div>
      </div>
    </el-drawer>

    <!-- 报错详情 -->
    <el-dialog title="详情 / 失败原因" :visible.sync="msgDialogVisible" width="720px"
               custom-class="round-dialog" append-to-body>
      <div class="msg-detail-meta" v-if="msgDetailRow">
        <span>版本 {{ msgDetailRow.version || '—' }}</span>
        <el-divider direction="vertical"></el-divider>
        <span>{{ msgDetailRow.created_time }}</span>
        <el-divider direction="vertical"></el-divider>
        <span>{{ msgDetailRow.admin_name || '—' }}</span>
      </div>
      <!-- 用 pre 保留换行与缩进：CI 报错是多行堆栈，压成一行就读不了了 -->
      <pre class="msg-detail-pre">{{ msgDetailText }}</pre>
      <span slot="footer">
        <el-button size="small" @click="msgDialogVisible = false">关闭</el-button>
        <el-button type="primary" size="small" icon="el-icon-document-copy"
                   @click="copyMsg">复制全部</el-button>
      </span>
    </el-dialog>

    <!-- 获取小程序码 -->
    <el-dialog title="获取小程序码" :visible.sync="codeDialogVisible" width="560px"
               custom-class="round-dialog" append-to-body :close-on-click-modal="false">
      <el-form size="small" label-width="72px" @submit.native.prevent>
        <el-form-item label="页面路径" style="margin-bottom:6px">
          <link-picker v-model="codePath" size="small" placeholder="选择链接，或直接输入页面路径"></link-picker>
        </el-form-item>
        <div class="code-tip">
          默认是首页 <code>pages/index/index</code>，可以带参数，如 <code>others/goods/goods?id=12</code>，开头的斜杠会自动去掉。<br>
          页面必须已经在<b>小程序正式版</b>里发布过，否则码能生成，扫进去会提示页面不存在。
        </div>
      </el-form>

      <div class="code-stage" v-loading="codeLoading">
        <template v-if="codeUrl">
          <img :src="codeUrl" class="code-img" alt="小程序码">
          <!-- 回显实际编进码里的路径：输入框里可能带着开头斜杠，这里是去掉之后真正生效的那个 -->
          <div class="code-path">{{ codeResultPath }}</div>
          <el-link :href="codeUrl" target="_blank" download type="primary" :underline="false"
                   icon="el-icon-download">下载图片</el-link>
        </template>
        <div v-else class="code-empty">
          <i class="el-icon-picture-outline"></i>
          <span>选好页面后点「立即获取」</span>
        </div>
      </div>

      <span slot="footer">
        <el-button size="small" @click="codeDialogVisible = false">关闭</el-button>
        <el-button type="primary" size="small" :loading="codeLoading" @click="fetchCode">立即获取</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'platform-mpwx',
  data() {
    return {
      drawerVisible: false,
      is_loading: false,
      is_can_upload: false,
      // —— 上传记录 ——
      logDrawerVisible: false,
      // 报错详情弹窗
      msgDialogVisible: false,
      msgDetailRow: null,
      logLoading: false,
      logList: [],
      logPage: 1,
      logPagination: {},
      // —— 上传进度 / 结果展示 ——
      uploading: false,
      uploadFinished: false,
      uploadFailed: false,
      uploadResultMsg: '',
      uploadProgress: 0,
      uploadElapsed: 0,
      uploadStep: 0,
      uploadStatusText: '',
      uploadSteps: [
        { t: 0, text: '准备代码包' },
        { t: 3, text: '注入域名 / 商城配置' },
        { t: 6, text: '编译并打包小程序' },
        { t: 15, text: '上传到微信服务器' },
        { t: 45, text: '微信处理中，即将完成' }
      ],
      form: {
        mchid: '',
        id: '',

        name: '',
        appid: '',
        secret: '',
        upload_key: '',
        version: '',
        desc: '首次提交',
        inject_domain: '',
        inject_mall_id: ''
      },
      pluginRows: [],
      // 代码包各包体积 + 余量。原来是给「归属分包」下拉用的,分包选项取消后只用其中的
      // 主包那条(见 mainPack)——插件一律进主包,主包放不放得下是唯一要判断的事。
      // 其余分包数据留着:排查体积问题时想看全貌,不必再改接口。
      // 后端读 web/mp-weixin/app.json 得来;代码包没传时为空,下拉降级成可手输。
      subpackages: [],
      // —— 获取小程序码 ——
      codeDialogVisible: false,
      codePath: 'pages/index/index',
      codeUrl: '',
      codeResultPath: '',
      codeLoading: false,
      tokenClearing: false
    }
  },
  watch: {
    // 路径一改，之前那张码就对不上了，清掉免得拿错图去用
    codePath() {
      this.codeUrl = ''
      this.codeResultPath = ''
    }
  },
  computed: {
    // 报错正文。CI 抛来的常是 JSON 字符串或带转义的堆栈，
    // 直接丢进 pre 会看到满屏 \n，所以先把转义还原成真实换行。
    msgDetailText() {
      const raw = (this.msgDetailRow && this.msgDetailRow.msg) || '';
      if (!raw) return '（无内容）';
      return String(raw)
        .replace(/\\r\\n/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\\t/g, '    ');
    },

    // 与后端 normalizePlugins 同口径:只有填了 AppID 的行才会真的注入。
    // 让运营在上传前看到的,就是后端实际会写进 app.json 的东西。
    effectivePlugins() {
      return (this.pluginRows || [])
        .filter(p => p && String(p.provider || '').trim() !== '')
        .map((p, i) => ({
          alias: String(p.alias || '').trim() || ('plugin' + (i + 1)),
          provider: String(p.provider).trim(),
          version: String(p.version || '').trim()
        }))
    },
    // 插件一律进主包,所以主包余量是唯一要盯的数(后端按文件字节算,与微信卡的
    // source size 同口径)。代码包还没放上服务器时后端返回空列表,这里就是 null,
    // 相关提示整块不渲染 —— 没有数据就别显示一个 0KB 吓人。
    mainPack() {
      return (this.subpackages || []).find(s => s.is_main) || null
    }
  },
  created: function () {

    this.getSetting()

  },
  beforeDestroy() {
    clearInterval(this._upTimer)
  },
  methods: {
    // 超过这个长度就给「查看」按钮 —— 一行放不下的才需要弹窗
    isLongMsg(msg) {
      return !!msg && String(msg).length > 40;
    },

    openMsgDetail(row) {
      this.msgDetailRow = row;
      this.msgDialogVisible = true;
    },

    copyMsg() {
      const text = this.msgDetailText;
      if (!text) return;

      // navigator.clipboard 只在 HTTPS 或 localhost 下可用。
      // 后台常挂在内网 IP / http 域名上，那里它是 undefined，
      // 不做降级的话「复制」按钮点了毫无反应，用户只会以为坏了。
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
          .then(() => this.$message.success('已复制'))
          .catch(() => this.fallbackCopy(text));
        return;
      }
      this.fallbackCopy(text);
    },

    fallbackCopy(text) {
      const ta = document.createElement('textarea');
      ta.value = text;
      // 放到视口外而不是 display:none —— 隐藏元素选不中，execCommand 会失败
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }
      document.body.removeChild(ta);
      if (ok) {
        this.$message.success('已复制');
      } else {
        this.$message.warning('复制失败，请手动选中文本复制');
      }
    },


    openLogDrawer() {
      this.logDrawerVisible = true
      this.logPage = 1
      this.loadLogs()
    },
    loadLogs() {
      this.logLoading = true
      this.$request({
        url: '/mall/platform/mpwx-upload-log',
        data: { page: this.logPage, limit: 10 }
      }).then(res => {
        this.logLoading = false
        if (res.code === 0) {
          this.logList = res.data.list || []
          this.logPagination = res.data.pagination || {}
        }
      }).catch(() => { this.logLoading = false })
    },
    onLogPageChange(p) {
      this.logPage = p
      this.loadLogs()
    },
    getSetting() {
      this.is_loading = true
      this.$request({
        url: '/mall/platform/mpwx'
      }).then(res => {
        this.is_loading = false
        if (res.code == 0) {
          this.is_can_upload = true
          this.form = Object.assign({}, this.form, res.data.mpwx)
          // 指定域名 / 商城ID / 版本号 默认填当前值，上传时可手动改
          this.form.inject_domain = res.data.current_domain || ''
          this.form.inject_mall_id = res.data.current_mall_id != null ? String(res.data.current_mall_id) : ''
          this.form.version = res.data.current_version || ''
          // 上传说明默认填版本号(SaaS 场景多数客户不关心更新了啥),留空则回退;用户仍可自行改
          this.form.desc = res.data.current_version ? String(res.data.current_version) : '首次提交'
          this.pluginRows = Array.isArray(res.data.plugins) ? res.data.plugins.slice() : []
          this.subpackages = Array.isArray(res.data.subpackages) ? res.data.subpackages : []
        }
      }).catch(e => {
        this.is_loading = false
      })
    },
    upload() {
      if (this.uploading) {
        this.$message.warning('正在上传中，请稍候')
        return
      }
      this.startUploadProgress()
      this.$request({
        url: '/mall/platform/mpwx-upload',
        data: this.form,
        method: 'post'
      }).then(res => {
        // 结果常驻抽屉，不自动关闭，由用户看清后点「完成 / 重新上传」
        this.finishUploadProgress(res.code == 0, res.msg)
      }).catch(e => {
        this.finishUploadProgress(false, '上传请求异常，请重试')
      })
    },
    // 启动拟真进度（后端单次阻塞拿不到真实进度，前快后慢、封顶 95%）
    startUploadProgress() {
      this.uploading = true
      this.uploadFinished = false
      this.uploadFailed = false
      this.uploadResultMsg = ''
      this.uploadProgress = 0
      this.uploadElapsed = 0
      this.uploadStep = 0
      this.uploadStatusText = this.uploadSteps[0].text
      clearInterval(this._upTimer)
      this._upTimer = setInterval(() => {
        this.uploadElapsed += 1
        if (this.uploadProgress < 95) {
          const inc = this.uploadProgress < 60 ? 2 : (this.uploadProgress < 85 ? 1 : 0.5)
          this.uploadProgress = Math.min(95, Math.round((this.uploadProgress + inc) * 10) / 10)
        }
        // 按已用时切换阶段
        let step = 0
        for (let i = 0; i < this.uploadSteps.length; i++) {
          if (this.uploadElapsed >= this.uploadSteps[i].t) step = i
        }
        this.uploadStep = step
        this.uploadStatusText = this.uploadSteps[step].text
      }, 1000)
    },
    // 上传结束：切到常驻结果态（不自动关抽屉）
    finishUploadProgress(success, msg) {
      clearInterval(this._upTimer)
      this._upTimer = null
      this.uploading = false
      this.uploadFinished = true
      this.uploadFailed = !success
      this.uploadResultMsg = msg || (success ? '上传成功' : '上传失败')
    },
    // 重置回表单（「重新上传」按钮 + 抽屉关闭后调用）
    resetUpload() {
      clearInterval(this._upTimer)
      this._upTimer = null
      this.uploading = false
      this.uploadFinished = false
      this.uploadFailed = false
      this.uploadResultMsg = ''
      this.uploadProgress = 0
      this.uploadElapsed = 0
      this.uploadStep = 0
      this.uploadStatusText = ''
    },
    fetchCode() {
      const path = (this.codePath || '').trim()
      if (!path) {
        this.$message.warning('请先选择或填写页面路径')
        return
      }
      this.codeLoading = true
      this.$request({
        url: '/mall/platform/mpwx-code',
        data: { path },
        method: 'post'
      }).then(res => {
        this.codeLoading = false
        if (res.code === 0) {
          this.codeUrl = res.data.url
          this.codeResultPath = res.data.path
        }
      }, () => {
        // 失败原因已由请求拦截器按后端 msg 弹出，这里只收掉 loading
        this.codeLoading = false
      })
    },
    // 清理服务器上缓存的微信 access_token 并立刻重新获取。
    // 调微信接口报 40001 / 42001 时点它:同一个小程序在别处(本地、测试服)又换了新 token,这边缓存的就作废了。
    clearToken() {
      this.$confirm(
        '会删掉这台服务器上缓存的小程序 / 公众号 access_token,并立刻重新向微信取一次。' +
        '一般只在调微信接口报 40001(access_token is invalid)或 42001 时才需要,正常使用不用点。确定清理吗?',
        '清理微信缓存', { type: 'warning', confirmButtonText: '清理并重新获取', cancelButtonText: '取消' }
      ).then(() => {
        this.tokenClearing = true
        this.$request({ url: '/mall/platform/wechat-token-clear', method: 'post', data: {}}).then(res => {
          this.tokenClearing = false
          const items = (res.data && res.data.items) || []
          const html = items.map(i => `<div style="margin:4px 0"><b>${i.name}</b>:清掉 ${i.removed} 条缓存,<span style="color:${i.ok ? '#67c23a' : '#f56c6c'}">${this.escapeHtml(i.msg)}</span></div>`).join('')
          if (res.code === 0) {
            this.$alert(html || res.msg, res.msg, { dangerouslyUseHTMLString: true, type: items.every(i => i.ok) ? 'success' : 'warning' })
          }
        }, () => { this.tokenClearing = false })
      }).catch(() => {})
    },
    escapeHtml(t) {
      return String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
    },
    submit() {
      this.$request({
        url: '/mall/platform/mpwx',
        data: Object.assign({}, this.form, { plugins: this.pluginRows }),
        method: 'post'
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg)
        }
      })
    }
  }
}
</script>

<style scoped>
.drawer-body {
  padding: 20px;
  height: calc(100vh - 120px);
  overflow-y: auto;
}
.drawer-footer {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: 12px 20px;
  border-top: 1px solid #ebeef5;
  background: #fff;
  text-align: right;
}
.code-tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.7;
  margin: 0 0 14px 72px;
}
.code-tip code {
  font-family: Menlo, Consolas, monospace;
  background: #f4f4f5;
  padding: 0 4px;
  border-radius: 3px;
}
.code-stage {
  min-height: 300px;
  border: 1px dashed #dcdfe6;
  border-radius: 8px;
  background: #fafbfc;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px;
}
.code-img {
  width: 220px;
  height: 220px;
  object-fit: contain;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
}
.code-path {
  font-family: Menlo, Consolas, monospace;
  font-size: 12px;
  color: #606266;
  word-break: break-all;
  text-align: center;
  max-width: 100%;
}
.code-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #c0c4cc;
  font-size: 13px;
}
.code-empty i {
  font-size: 40px;
}
.form-tip {
  color: #999;
  line-height: 1.4;
  font-size: 12px;
}
/* 主包余量提示:够放是中性蓝，放不下转红。这个数直接决定插件能不能带上，
   比下面那段长说明更该被第一眼看到，所以单独一块而不是并进 form-tip。 */
.pack-hint {
  margin: 8px 0 4px;
  padding: 6px 10px;
  font-size: 12px;
  line-height: 1.7;
  color: #606266;
  background: var(--admin-accent-soft);
  border: 1px solid var(--admin-accent-border);
  border-radius: 4px;
}
.pack-hint i {
  color: var(--admin-accent-text);
  margin-right: 4px;
}
.pack-hint.danger {
  color: #a33;
  background: #fef0f0;
  border-color: #fbc4c4;
}
.pack-hint.danger i {
  color: #F56C6C;
}
.upload-progress {
  text-align: center;
  padding: 24px 8px;
}
.upload-progress .up-status {
  margin-top: 18px;
  font-size: 15px;
  color: #303133;
  font-weight: 500;
}
.upload-progress .up-elapsed {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}
.upload-progress .up-steps {
  list-style: none;
  margin: 20px auto 0;
  padding: 0;
  max-width: 280px;
  text-align: left;
}
.upload-progress .up-steps li {
  padding: 6px 0;
  font-size: 13px;
  color: #c0c4cc;
}
.upload-progress .up-steps li i {
  margin-right: 6px;
}
.upload-progress .up-steps li.done {
  color: #67c23a;
}
.upload-progress .up-steps li.active {
  color: var(--admin-accent-text);
  font-weight: 500;
}
.upload-progress .up-tip {
  margin-top: 20px;
  font-size: 12px;
  color: #e6a23c;
  line-height: 1.5;
}
.up-footer-tip {
  color: var(--admin-accent-text);
  font-size: 13px;
}
.upload-result {
  text-align: center;
  padding: 48px 16px;
}
.upload-result .ur-icon {
  font-size: 72px;
  line-height: 1;
}
.upload-result .ur-title {
  margin-top: 18px;
  font-size: 18px;
  font-weight: 600;
}
.upload-result .ur-msg {
  margin-top: 12px;
  font-size: 14px;
  color: #606266;
  line-height: 1.6;
  word-break: break-all;
  white-space: pre-wrap;
}
.upload-result .ur-hint {
  margin-top: 16px;
  font-size: 13px;
  color: #909399;
}
/* 上传记录抽屉：表格内部滚动，不整页滚 */
.log-drawer-body {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 80px);
  padding: 16px;
  box-sizing: border-box;
}
.log-table-wrap {
  flex: 1;
  min-height: 0;
}
.log-drawer-body .pagination-container {
  padding-top: 10px;
  text-align: right;
}
.plugin-rows .plugin-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

/* ── 上传记录的报错列 ── */
.msg-cell {
  display: flex;
  align-items: center;
}

.msg-text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #606266;
}

.msg-more {
  flex: none;
  margin-left: 6px;
  padding: 0;
}

.msg-detail-meta {
  font-size: 12px;
  color: #909399;
  margin-bottom: 10px;
}

/* 报错正文：等宽字体 + 保留换行，堆栈才读得下去 */
.msg-detail-pre {
  margin: 0;
  padding: 12px 14px;
  max-height: 380px;
  overflow: auto;
  background: #f7f8fa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  font-family: Menlo, Consolas, "Courier New", monospace;
  font-size: 12px;
  line-height: 1.7;
  color: #303133;
  /* 长 URL / 长路径不换行会把弹窗撑破 */
  white-space: pre-wrap;
  word-break: break-all;
}

/* 上传记录抽屉：用百分比自适应，再用上下限兜住两头 ——
   固定 px 在小屏会溢出、在宽屏又浪费；只给百分比则 4K 屏上宽得离谱。
   1200 是「报错列够看」和「不至于横跨整屏」的平衡点。 */
.upload-log-drawer {
  /* 920 不是随手取的：列宽最小合计 870 + 抽屉内边距 40 = 910，
     再留 10px 余量，保证最窄状态下也不出横向滚动条 */
  min-width: 920px;
  max-width: 1200px;
}
</style>
