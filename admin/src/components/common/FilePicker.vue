<template>
  <div class="com-file">
    <el-dialog class="com-file-dialog" :title="title ? title : '选择文件'" :visible.sync="dialogVisible"
               :width="dialogWidth" top="6vh" append-to-body @opened="dialogOpened" @close="dialogClose">
      <template v-if="simple">
       <div class="com-file-drop-zone com-file-simple-zone" v-on="dropListeners">
        <div style="margin-bottom: 15px; text-align: center;" v-if="type === 'image'">
          <el-tooltip content="勾选后将不进行任何压缩，保留原图画质上传 (大文件可能会消耗较多带宽与存储空间)" placement="right">
            <el-checkbox v-model="is_lossless" :true-label="1" :false-label="0" border size="small">无损原图上传</el-checkbox>
          </el-tooltip>
        </div>
        <file-upload ref="uploader" v-loading="uploading" class="com-file-simple-upload flex-x-center flex-y-center"
                     :disabled="uploading" :multiple="true" :max="10" :params="Object.assign({}, uploadParams, {is_lossless: is_lossless})" :fields="uploadFields"
                     :accept="accept" flex="main:center cross:center" @start="handleStart" @progress="handleProgress"
                     @complete="handleComplete" :is_site="is_site">
          <div v-if="uploading">{{ uploadCompleteFilesNum }}/{{ uploadFilesNum }}</div>
          <i v-else class="el-icon-upload" />
        </file-upload>
        <div class="com-file-drop-hint">也可以把文件直接拖到这里</div>
        <div v-show="dragging" class="com-file-drop-mask">
          <i class="el-icon-upload" />
          <div class="com-file-drop-title">松开鼠标,开始上传</div>
          <div class="com-file-drop-sub">{{ dropTip }}</div>
        </div>
       </div>
      </template>
      <template v-else>
        <div style="height: 0;overflow: hidden;">
          <canvas id="com-file-canvas" style="border: 1px solid #ccc;visibility: hidden;" />
        </div>
        <div class="com-file-main">
          <div class="com-file-side">
            <el-menu v-loading="groupListLoading" class="group-menu" mode="vertical">
              <div class="group-head">
                <el-input v-model="keyword" size="small" placeholder="搜索分组" prefix-icon="el-icon-search"
                          @keyup.enter.native="getGroupList" />
                <el-button type="text" icon="el-icon-plus" @click="showAddGroup(-1)">添加</el-button>
              </div>
              <el-scrollbar class="group-scroll">
                <el-menu-item index="all" @click="switchGroup(-1)">
                  <i class="el-icon-tickets" />
                  <span>全部</span>
                </el-menu-item>
                <template v-for="(item, index) in group_list">
                  <el-menu-item :key="index" :index="'' + index" @click="switchGroup(index)">
                    <div flex="dir:left cross:center" class="flex-row">
                      <div style="overflow: hidden;text-overflow: ellipsis;white-space: nowrap;flex: 1;min-width: 0;">
                        <i class="el-icon-tickets" />
                        <span>{{ item.name }}</span>
                      </div>
                      <div flex="dir:left" class="flex-row" style="flex-shrink: 0;">
                        <el-button type="text" @click.stop="showAddGroup(index)">编辑</el-button>
                        <div style="color:var(--admin-accent-text);margin:0 2px">|</div>
                        <el-button type="text" @click.stop="deleteGroup(index)">删除</el-button>
                      </div>
                    </div>
                  </el-menu-item>
                </template>
              </el-scrollbar>
            </el-menu>
          </div>
          <div v-loading="loading" class="com-file-panel com-file-drop-zone" v-on="dropListeners">
            <div v-show="dragging" class="com-file-drop-mask">
              <i class="el-icon-upload" />
              <div class="com-file-drop-title">松开鼠标,上传到「{{ currentGroupName }}」</div>
              <div class="com-file-drop-sub">{{ dropTip }}</div>
            </div>
            <div class="com-file-toolbar">
              <el-input v-model="p_keyword" size="small" placeholder="搜索文件名" class="com-file-search"
                        @keyup.enter.native="fileSearch">
                <el-button slot="append" icon="el-icon-search" @click="fileSearch" />
              </el-input>
              <el-tooltip v-if="type === 'image'" content="勾选后将不进行任何压缩，保留原图体积画质 (可能会消耗较多空间)" placement="bottom">
                <el-checkbox v-model="is_lossless" :true-label="1" :false-label="0">无损原图上传</el-checkbox>
              </el-tooltip>
              <div class="com-file-toolbar-right">
                <el-button v-if="!showEditBlock" size="small" @click="showEditBlock=true">开启编辑</el-button>
                <template v-else>
                  <el-checkbox v-model="selectAll" label="全选" @change="selectAllChange" />
                  <el-button size="small" :loading="deleteLoading" @click="deleteItems">删除</el-button>
                  <el-dropdown v-loading="moveLoading" trigger="click" :split-button="true" size="small"
                               @command="moveItems">
                    <span>移动至</span>
                    <el-dropdown-menu slot="dropdown">
                      <el-dropdown-item v-for="(item, index) in group_list" :key="index" :command="index">
                        {{ item.name }}</el-dropdown-item>
                    </el-dropdown-menu>
                  </el-dropdown>
                  <el-button size="small" @click="showEditBlock=false">退出编辑</el-button>
                </template>
              </div>
            </div>
            <el-scrollbar class="scrollbar">

              <div class="com-file-list">
                <div class="com-file-item com-file-upload">
                  <file-upload ref="uploader" style="height: 100%;" v-loading="uploading" :disabled="uploading"
                               :multiple="true" :max="10" :params="Object.assign({}, uploadParams, {is_lossless: is_lossless})" :fields="uploadFields"
                               :accept="accept" flex="main:center cross:center"
                               @start="handleStart" @progress="handleProgress" @complete="handleComplete" :is_site="is_site">
                    <div class="flex-x-center flex-y-center" style="height: 100%;">

                      <div v-if="uploading">{{ uploadCompleteFilesNum }}/{{ uploadFilesNum }}
                      </div>
                      <i v-else class="el-icon-upload" />
                    </div>

                  </file-upload>
                </div>
                <template v-for="(item, index) in files">
                  <el-tooltip :key="index" class="item" effect="dark" :content="fileTip(item)"
                              placement="top" :open-delay="1">
                    <div :class="'com-file-item'+((item.checked)?' checked':'')"
                         @click="handleClick(item)" @dblclick="handleDblClick(item)">
                      <el-image v-if="item.type == 'image'" class="com-file-img" :src="item.thumb_url" fit="cover">
                      </el-image>
                      <span v-if="imageBadge(item)" :class="'com-file-badge is-' + imageBadge(item).type">{{ imageBadge(item).text }}</span>
                      <div v-if="item.type == 'video'" class="com-file-img"
                           style="position: relative">
                        <div v-if="item.cover_pic_src" class="com-file-video-cover"
                             :style="'background-image: url('+item.cover_pic_src+');'" />
                        <video :id="'app_attachment_'+ _uid + '_' + index"
                               style="width: 0;height: 0;visibility: hidden;">
                          <source :src="item.url">
                        </video>
                        <div class="com-file-video-info">
                          <i class="el-icon-video-play" />
                          <span>{{ item.duration?item.duration:'--:--' }}</span>
                        </div>
                      </div>
                      <div v-if="item.type == 'audio'" class="com-file-img"
                           style="display: flex; align-items: center; justify-content: center">
                        <i class="file-type-icon el-icon-headset" />
                      </div>
                      <div v-if="item.type == 'doc'" class="com-file-img"
                           style="display: flex; align-items: center; justify-content: center">
                        <i class="file-type-icon el-icon-document" />
                      </div>
                      <div class="com-file-name">{{ item.name }}</div>
                      <i v-if="item.checked" class="com-file-active-icon el-icon-circle-check" />
                    </div>
                  </el-tooltip>
                </template>
              </div>
            </el-scrollbar>
            <div v-if="pagination" class="com-file-pagination">
              <el-pagination background :small="isMobile" :pager-count="isMobile ? 5 : 7"
                             :current-page="page" :page-size="pageSize" :page-sizes="pageSizes"
                             :layout="paginationLayout" :total="pagination.total_count"
                             @size-change="handleSizeChange" @current-change="handleLoadMore" />
            </div>
          </div>
        </div>
        <div class="com-file-footer">
          <span class="com-file-footer-info">已选 <b>{{ checkedfiles.length }}</b> 项</span>
          <div>
            <el-button @click="dialogVisible = false">取消</el-button>
            <el-button type="primary" @click="confirm">选定<span v-if="checkedfiles.length"> ({{ checkedfiles.length }})</span></el-button>
          </div>
        </div>
      </template>
    </el-dialog>
    <el-dialog append-to-body title="分组管理" :visible.sync="addGroupVisible" :close-on-click-modal="false"
               width="25%">
      <el-form ref="groupForm" label-width="80px" :model="groupForm" :rules="groupFormRule"
               @submit.native.prevent>
        <el-form-item label="分组名称" prop="name" style="margin-bottom: 22px;">
          <el-input v-model="groupForm.name" maxlength="8" show-word-limit />
        </el-form-item>
        <el-form-item style="text-align: right">
          <el-button type="primary" :loading="groupFormLoading" @click="groupFormSubmit('groupForm')">保存
          </el-button>
        </el-form-item>
      </el-form>
    </el-dialog>
    <div style="line-height: normal;" :style="'display:'+(display?display:'inline-block')"
         @click="dialogVisible = !dialogVisible">
      <slot />
    </div>
  </div>
</template>

<script>
import FileUpload from '@/components/common/file-upload'

const PAGE_SIZES = [15, 30, 50, 100]
const PAGE_SIZE_KEY = 'file_picker_page_size'
function readPageSize() {
  try {
    const n = Number(localStorage.getItem(PAGE_SIZE_KEY))
    return PAGE_SIZES.includes(n) ? n : PAGE_SIZES[0]
  } catch (e) {
    return PAGE_SIZES[0]
  }
}
// 一次拖入最多上传多少个文件(点选上传仍是每次最多 10 个)
const DROP_MAX = 100

export default {
  name: 'FilePicker',
  components: {
    'file-upload': FileUpload
  },
  props: {
    display: String,
    title: String,
    multiple: Boolean,
    max: Number,
    params: Object,
    simple: {
      type: Boolean,
      value: false
    },
    type: {
      type: String,
      default: 'image'
    },
    value: {
      type: String,
      default: ''
    },
    openDialog: {
      type: Boolean,
      default: false
    },
    is_site: {
      type: Number,
      default: 0
    }
  },
  data() {
    return {
      is_lossless: 0,
      canvas: null,
      uploading: false,
      dialogVisible: false,
      loading: true,
      loadingMore: false,
      noMore: false,
      files: [],
      checkedfiles: [],
      uploadParams: {},
      uploadFields: {},
      uploadCompleteFilesNum: 0,
      uploadFilesNum: 0,
      dragging: false, // 有文件正拖在上传区域上方
      page: 1,
      addGroupVisible: false,
      noMall: true,
      group_list: [],
      current_group_id: 0,
      groupListLoading: false,
      groupForm: {
        id: null,
        name: ''
      },
      groupFormRule: {
        name: [{
          required: true,
          message: '请填写分组名称。'
        }]
      },
      groupFormLoading: false,
      showEditBlock: false,
      selectAll: false,
      deleteLoading: false,
      moveLoading: false,
      video: null,
      keyword: '',
      pagination: null,
      // 每页条数:选过一次就记住(只存在本机浏览器里)
      pageSize: readPageSize(),
      pageSizes: PAGE_SIZES,
      p_keyword: ''
    }
  },
  computed: {
    // 拖入文件的事件。放在一处,简易模式和完整模式共用
    dropListeners() {
      return {
        dragenter: this.onDragEnter,
        dragover: this.onDragOver,
        dragleave: this.onDragLeave,
        drop: this.onDrop
      }
    },
    currentGroupName() {
      const g = (this.group_list || []).find(x => x.id === this.current_group_id)
      return g ? g.name : (this.current_group_id ? '当前分组' : '未分组')
    },
    dropTip() {
      const kind = { image: '图片', video: '视频', audio: '音频' }[this.type]
      return (kind ? '只上传' + kind + ',' : '') + '一次最多 ' + DROP_MAX + ' 个,支持批量拖入'
    },
    accept: {
      get() {
        if (this.type === 'image') {
          return 'image/*'
        }
        if (this.type === 'video') {
          return 'video/*'
        }
        if (this.type === 'audio') {
          return 'audio/*'
        }
        return '*/*'
      }
    },
    // 是否小屏(手机/窄窗)
    isMobile() {
      return typeof window !== 'undefined' && window.innerWidth <= 768
    },
    // 弹窗宽度响应式:小屏用更大宽度,避免固定宽度内容(分组菜单/搜索/图片项)被挤出弹窗
    dialogWidth() {
      if (this.simple) return '25%'
      return this.isMobile ? '92%' : '65%'
    },
    // 分页布局响应式:小屏精简——去掉 total 文字与跳页输入框,只留上一页/页码/下一页,否则一整排会溢出弹窗
    paginationLayout() {
      return this.isMobile ? 'prev, pager, next' : 'total, sizes, prev, pager, next, jumper'
    }
  },
  watch: {
    openDialog(newVal, oldVal) {
      this.dialogVisible = newVal
    },
    dialogVisible(newVal, oldVal) {
      if (!newVal) {
        this.$emit('closed')
      }
    },
    keyword(newVal, oldVal) {
      let group_list = this.group_list
      let arr = []
      group_list.map(v => {
        if (v.name.indexOf(newVal) !== -1) {
          arr.push(v)
        }
      })
      this.group_list = arr
    }
  },
  created() {},
  beforeDestroy() {
    this.removeDropGuard()
  },
  methods: {
    /**
     * 图片角标:有缩略图的(上传时自动生成,首页 / 列表加载的是小图)标「已优化」;
     * 没有缩略图又超过 300KB 的标「大图」提醒运营,小图不打标。老图没补大小前 size 为 0,也不打标
     */
    imageBadge(item) {
      if (item.type !== 'image') return null
      if (item.thumb_url && item.url && item.thumb_url !== item.url) return { type: 'ok', text: '已优化' }
      const size = Number(item.size) || 0
      if (size > 300 * 1024) return { type: 'big', text: '大图 ' + this.formatSize(size) }
      return null
    },
    fileTip(item) {
      const parts = [item.name]
      if (Number(item.width) > 0) parts.push(item.width + '×' + item.height)
      if (Number(item.size) > 0) parts.push(this.formatSize(item.size))
      return parts.join(' · ')
    },
    formatSize(size) {
      size = Number(size) || 0
      return size >= 1024 * 1024 ? (size / 1024 / 1024).toFixed(1) + 'MB' : Math.round(size / 1024) + 'KB'
    },
    // ───────────── 拖入文件上传 ─────────────
    // 只响应「拖的是文件」,拖文字、拖页面元素不触发
    hasFiles(e) {
      const types = e && e.dataTransfer && e.dataTransfer.types
      return !!types && Array.prototype.indexOf.call(types, 'Files') !== -1
    },
    // dragenter / dragleave 会在子元素上反复触发,用计数避免遮罩闪烁
    onDragEnter(e) {
      if (!this.hasFiles(e)) return
      e.preventDefault()
      this._dragDepth = (this._dragDepth || 0) + 1
      if (!this.uploading) this.dragging = true
    },
    onDragOver(e) {
      if (!this.hasFiles(e)) return
      e.preventDefault()
      e.dataTransfer.dropEffect = this.uploading ? 'none' : 'copy'
    },
    onDragLeave(e) {
      if (!this.hasFiles(e)) return
      this._dragDepth = Math.max(0, (this._dragDepth || 0) - 1)
      if (!this._dragDepth) this.dragging = false
    },
    onDrop(e) {
      if (!this.hasFiles(e)) return
      e.preventDefault()
      this._dragDepth = 0
      this.dragging = false
      if (this.uploading) {
        this.$message.warning('正在上传,请等这一批完成后再拖入')
        return
      }
      // 优先从 items 取:能认出文件夹(文件夹拖进来不是文件,不能当文件传)
      let list = []
      let folders = 0
      const items = e.dataTransfer.items
      if (items && items.length) {
        for (let i = 0; i < items.length; i++) {
          if (items[i].kind !== 'file') continue
          const entry = items[i].webkitGetAsEntry && items[i].webkitGetAsEntry()
          if (entry && entry.isDirectory) { folders++; continue }
          const f = items[i].getAsFile()
          if (f) list.push(f)
        }
      } else {
        list = Array.prototype.slice.call(e.dataTransfer.files || [])
      }
      // 按弹窗类型过滤:选图片的弹窗只收图片(拖入不受 <input accept> 约束,要自己筛)
      const kindRe = { image: /^image\//, video: /^video\//, audio: /^audio\// }[this.type]
      let skipped = 0
      if (kindRe) {
        const ok = list.filter(f => kindRe.test(f.type))
        skipped = list.length - ok.length
        list = ok
      }
      const notes = []
      if (folders) notes.push(folders + ' 个文件夹(暂不支持拖文件夹,请打开文件夹后选中里面的文件再拖)')
      if (skipped) notes.push(skipped + ' 个不是' + { image: '图片', video: '视频', audio: '音频' }[this.type] + '的文件')
      if (list.length > DROP_MAX) {
        notes.push('超过一次 ' + DROP_MAX + ' 个的上限,只上传前 ' + DROP_MAX + ' 个')
        list = list.slice(0, DROP_MAX)
      }
      if (notes.length) this.$message.warning('已忽略:' + notes.join(';'))
      if (!list.length) return
      this.$refs.uploader && this.$refs.uploader.uploadFiles(list, { ignoreMax: true })
    },
    // 弹窗开着时,文件拖到弹窗外面(浏览器默认会把文件当页面打开,后台页面就丢了),拦住
    guardWindowDrop(e) {
      if (!this.hasFiles(e)) return
      e.preventDefault()
      if (!(e.target && e.target.closest && e.target.closest('.com-file-drop-zone'))) {
        e.dataTransfer.dropEffect = 'none'
      }
    },
    addDropGuard() {
      if (this._dropGuard) return
      this._dropGuard = this.guardWindowDrop
      window.addEventListener('dragover', this._dropGuard)
      window.addEventListener('drop', this._dropGuard)
    },
    removeDropGuard() {
      if (!this._dropGuard) return
      window.removeEventListener('dragover', this._dropGuard)
      window.removeEventListener('drop', this._dropGuard)
      this._dropGuard = null
      this._dragDepth = 0
      this.dragging = false
    },
    fileSearch() {
      this.page = 1
      this.loading = true
      this.getGroupList()
      this.getList({})
    },

    dialogOpened() {
      this.addDropGuard()
      if (this.simple) {
        return
      }
      if (!this.files || !this.files.length) {
        this.loading = true
        this.getGroupList()
        this.getList({})
      }
    },
    deleteItems() {
      const itemIds = []
      for (const i in this.files) {
        if (this.files[i].checked) {
          itemIds.push(this.files[i].id)
        }
      }
      if (!itemIds.length) {
        this.$message.warning('请先选择需要删除的图片。')
        return
      }
      this.$confirm('确认删除所选的' + itemIds.length + '张图片？', '提示', {
        type: 'warning'
      })
        .then((e) => {
          this.deleteLoading = true;
          this.$request({
            url: '/common/file/delete',
            data: {
              ids: itemIds
            },
            method: 'post'
          }).then(res => {
            this.deleteLoading = false;
            if (res.code == 0) {
              this.$message.success('成功删除' + res.data.res + '条数据');
              this.getList();
              this.showEditBlock = false;
            }
          }).catch(() => {
            this.deleteLoading = false;
          });
        })
        .catch(() => {})
    },
    selectAllChange(value) {
      for (const i in this.files) {
        this.files[i].checked = value
      }
    },
    selectItem(item) {
      item.selected = !item.selected
    },
    moveItems(index) {
      let group = this.group_list[index];
      const itemIds = []
      for (const i in this.files) {
        if (this.files[i].checked) {
          itemIds.push(this.files[i].id)
        }
      }
      if (!itemIds.length) {
        this.$message.warning('请先选择需要移动的图片。')
        return
      }
      this.$confirm('确认移动所选的' + itemIds.length + '张图片？', '提示', {
        type: 'warning'
      })
        .then(() => {
          this.moveLoading = true
          this.$request({
            url: '/common/file/move',
            data: {
              ids: itemIds,
              group_id: group.id,
            },
            method: 'post'
          }).then(res => {
            this.moveLoading = false;
            if (res.code == 0) {
              this.$message.success('成功移动' + res.data.res + '条数据');
              this.getList();
              this.showEditBlock = false;
            } else {
              this.$message.error('操作失败');
            }
          }).catch(() => {
            this.moveLoading = false;
          });
        })
        .catch(() => {})
    },

    getGroupList() {
      this.groupListLoading = true;
      this.$request({
        url: '/common/file/group-list',
        data: {
          keyword: this.keyword,
          is_site: this.is_site
        }
      }).then(res => {
        this.groupListLoading = false;
        if (res.code == 0) {
          this.group_list = res.data.list
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.groupListLoading = false;
      });
    },
    showAddGroup(index) {
      if (index > -1) {
        this.groupForm.id = this.group_list[index].id
        this.groupForm.name = this.group_list[index].name
      } else {
        this.groupForm.id = null
        this.groupForm.name = ''
      }
      this.groupForm.edit_index = index
      this.addGroupVisible = true
    },
    deleteGroup(index) {
      const title = '是否确认将分组放入回收站中？删除的分组可通过回收站还原'
      this.$confirm(title, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
        .then(() => {
          this.groupFormLoading = true;
          let id = this.group_list[index].id;
          this.$request({
            url: '/common/file/group-delete',
            data: {
              id: id
            },
            method: 'post'
          }).then(res => {
            this.groupFormLoading = false
            if (res.code === 0) {
              this.$message.success(res.msg)
              this.group_list.splice(index, 1);
            } else {
              this.$message.error(res.msg)
            }
          }).catch(() => {
            this.groupFormLoading = false;
          });
        })
        .catch(() => {})
    },
    groupFormSubmit(formName) {
      this.$refs[formName].validate(valid => {
        if (valid) {
          this.groupFormLoading = true
          this.$request({
            url: '/common/file/group-edit',
            method: 'post',
            data: Object.assign({}, this.groupForm, {
              type: this.type,
              is_site: this.is_site
            })
          })
            .then(e => {
              this.groupFormLoading = false
              if (e.code === 0) {
                this.$message.success(e.msg)
                this.addGroupVisible = false
                if (this.groupForm.edit_index > -1) {
                  this.group_list[this.groupForm.edit_index] = e.data.group
                } else {
                  this.group_list.push(e.data.group)
                }
              } else {
                this.$message.error(e.msg)
              }
            })
            .catch(e => {
              this.groupFormLoading = false
            })
        }
      })
    },
    switchGroup(index) {
      this.files = []
      this.page = 1
      this.noMore = false
      this.loading = true
      this.current_group_id = index > -1 ? this.group_list[index].id : null;
      this.uploadParams = {
        group_id: this.current_group_id
      }
      this.getList({})
    },
    getList(params) {
      this.noMore = false
      this.selectAll = false
      this.checkedfiles = []
      this.loading = true
      this.loadingMore = false
      this.$request({
        url: '/common/file/list',
        data: {
          page: this.page,
          limit: this.pageSize,
          group_id: this.current_group_id,
          type: this.type,
          keyword: this.p_keyword,
          is_site: this.is_site
        }
      }).then(res => {
        this.loading = false;
        if (res.code == 0) {
          this.files = res.data.list.map(file => {
            file.checked = false;
            return file;
          });
          this.pagination = res.data.pagination;
        }
      }).catch(() => {
        this.loading = false;
      });
    },
    handleClick(item) {
      if (item.checked) {
        item.checked = false
        for (const i in this.checkedfiles) {
          if (item.id === this.checkedfiles[i].id) {
            this.checkedfiles.splice(i, 1)
          }
        }
        return
      }
      // 编辑模式按"批量选择"处理:可多选(与 picker 的 multiple 无关,供批量删除/移动),且不受 picker max 限制
      if (this.multiple || this.showEditBlock) {
        let checkedCount = 0
        for (const i in this.files) {
          if (this.files[i].checked) checkedCount++
        }
        if (!this.showEditBlock && this.max && checkedCount >= this.max) return
        item.checked = true
        this.checkedfiles.push(item)
      } else {
        for (const i in this.files) this.files[i].checked = false
        item.checked = true
        this.checkedfiles = [item]
      }
    },
    handleDblClick(item) {
      if (!item.checked) {
        this.handleClick(item)
      }
      this.confirm()
    },
    confirm() {
      this.$emit('selected', this.checkedfiles, this.params)
      this.dialogVisible = false
      let urls = []
      for (const i in this.checkedfiles) {
        urls.push(this.checkedfiles[i].url)
      }
      for (const i in this.files) {
        this.files[i].checked = false
      }
      this.checkedfiles = []
      if (!urls.length) {
        return
      }
      if (this.multiple) {
        this.$emit('input', urls)
      } else {
        this.$emit('input', urls[0])
      }
    },
    handleStart(files) {
      this.uploading = true
      this.uploadFilesNum = files.length
      this.uploadCompleteFilesNum = 0
    },
    // 【关键修改】
    handleComplete(files) {
      this.uploading = false;
      if (this.simple) {
        const urls = [];
        const upload_files = [];
        for (const i in files) {
          if (files[i].response && files[i].response.code === 0) {
            let newItem = files[i].response.data;
            urls.push(newItem.url);
            upload_files.unshift(newItem);
          }
        }
        const _this = this;
        setTimeout(() => {
          if (!urls.length) {
            return;
          }
          _this.dialogVisible = false;
          _this.$emit('selected', upload_files, _this.params);
          if (_this.multiple) {
            _this.$emit('input', urls);
          } else {
            _this.$emit('input', urls[0]);
          }
        }, 1000);
        return;
      }
      // 上传完成后，调用 getList() 重新加载当前分组的文件列表
      const failed = files.filter(f => !(f.response && f.response.code === 0))
      if (!failed.length) {
        this.$message.success(files.length > 1 ? '上传完成,共 ' + files.length + ' 个,正在刷新列表...' : '上传完成，正在刷新列表...');
      } else if (failed.length === files.length) {
        this.$message.error('上传失败,请检查网络或文件后重试');
      } else {
        this.$message.warning((files.length - failed.length) + ' 个上传成功,' + failed.length + ' 个失败:' +
          failed.slice(0, 3).map(f => f.rawFile.name).join('、') + (failed.length > 3 ? ' 等' : ''));
      }
      this.getList();
    },
    dialogClose(){
      this.removeDropGuard()
      this.$emit('close')
    },
    // 每完成一个(成功或失败)更新进度 n/m
    handleProgress({ done }) {
      this.uploadCompleteFilesNum = done
    },
    handleSizeChange(size) {
      this.pageSize = size
      try {
        localStorage.setItem(PAGE_SIZE_KEY, String(size))
      } catch (e) {
        // 隐私模式等存不了就算了,本次打开仍然生效
      }
      this.page = 1
      this.loading = true
      this.getList({})
    },
    handleLoadMore(currentPage) {
      if (this.noMore) {
        return
      }
      this.page = currentPage
      this.loading = true
      this.loadingMore = true
      this.getList({})
    },
    updateVideo() {
      if (!this.canvas) {
        this.canvas = document.getElementById('com-file-canvas')
      }
      for (const i in this.files) {
        if (this.files[i].type === 'video') {
          if (this.files[i].duration) {
            continue
          }
          let times = 0
          let video = null
          const maxRetry = 10
          const id = 'app_attachment_' + this._uid + '_' + i
          const timer = setInterval(() => {
            times++
            if (times >= maxRetry) {
              clearInterval(timer)
            }
            if (!video) {
              video = document.getElementById(id)
            }
            if (!video) {
              return
            }
            try {
              const zoom = 0.15
              this.canvas.width = video.videoWidth * zoom
              this.canvas.height = video.videoHeight * zoom
              this.canvas
                .getContext('2d')
                .drawImage(video, 0, 0, this.canvas.width, this.canvas.height)
              this.files[i].cover_pic_src = this.canvas.toDataURL(
                'image/jpg'
              )
            } catch (e) {
              console.warn('获取视频封面异常: ', e)
            }

            if (video.duration && !isNaN(video.duration)) {
              let m = Math.trunc(video.duration / 60)
              let s = Math.trunc(video.duration) % 60
              m = m < 10 ? `0${m}` : `${m}`
              s = s < 10 ? `0${s}` : `${s}`
              this.files[i].duration = `${m}:${s}`
              clearInterval(timer)
            }
          }, 500)
        }
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.com-file-list {
  padding: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(108px, 1fr));
  gap: 12px;
  align-items: start;

  * {
    box-sizing: border-box;
  }
}

.com-file-item {
  cursor: pointer;
  position: relative;
  text-align: center;
  padding: 6px;
  border-radius: 8px;
  transition: background 0.15s;

  &:hover {
    background: #f5f7fa;
  }

  .com-file-img {
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 6px;
    overflow: hidden;
    background: #f0f2f5;
  }

  .file-type-icon {
    width: 30px;
    height: 30px;
    border-radius: 30px;
    background: #666;
    color: #fff;
    text-align: center;
    line-height: 30px;
    font-size: 24px;
  }
}

.com-file-badge {
  position: absolute;
  left: 10px;
  top: 10px;
  padding: 0 5px;
  font-size: 11px;
  line-height: 17px;
  border-radius: 3px;
  color: #fff;
  pointer-events: none;

  &.is-ok {
    background: rgba(103, 194, 58, 0.9);
  }

  &.is-big {
    background: rgba(230, 162, 60, 0.92);
  }
}

.com-file-active-icon {
  position: absolute;
  right: 9px;
  top: 9px;
  font-size: 22px;
  color: var(--admin-accent-text);
  background: #fff;
  border-radius: 50%;
  line-height: 1;
}

.com-file-item.checked,
.com-file-item.selected {
  box-shadow: 0 0 0 1px var(--admin-accent);
  background: var(--admin-accent-soft);
  border-radius: 5px;
}

.com-file-item.checked .com-file-active-icon,
.com-file-item.selected .com-file-active-icon {
  opacity: 1;
}

.com-file-upload {
  box-shadow: none;
  border: 1px dashed #b2b6bd;
  aspect-ratio: 1 / 1;
  border-radius: 8px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;

  /* 上传组件铺满整格 → 整格可点。file-upload 根节点自带 @click、内部已 flex 居中图标;
     不铺满时会被上面的 justify/align 居中、缩成图标大小,只有图标可点(网格化重构引入的回归) */
  .file-upload {
    flex: 1;
    align-self: stretch;
  }

  i {
    font-size: 30px;
    color: #909399;
  }

  &:hover {
    box-shadow: none;
    border: 1px dashed var(--admin-accent-text);

    i {
      color: var(--admin-accent-text);
    }
  }

  &:active {
    border: 1px dashed var(--admin-accent-text);

    i {
      color: var(--admin-accent-text);
    }
  }
}

.com-file-dialog {
  ::v-deep .el-dialog__header {
    padding: 14px 20px 10px;
  }

  ::v-deep .el-dialog__body {
    padding: 4px 20px 14px;
  }

  /* 左分组 + 右图片两栏等高,各自内部滚动;工具栏和分页固定在面板上下,不跟着滚 */
  .com-file-main {
    display: flex;
    height: 66vh;
    min-height: 420px;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    overflow: hidden;
  }

  .com-file-side {
    flex-shrink: 0;
    border-right: 1px solid #e4e7ed;
    display: flex;
    flex-direction: column;

    > .group-menu {
      flex: 1;
      min-height: 0;
      display: flex;
      flex-direction: column;
    }
  }

  .group-head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px;
    border-bottom: 1px solid #f0f2f5;

    .el-button {
      padding: 0;
      flex-shrink: 0;
    }
  }

  .group-scroll {
    flex: 1;
    min-height: 0;

    ::v-deep .el-scrollbar__wrap {
      overflow-x: hidden;
    }
  }

  .com-file-panel {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
  }

  .com-file-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 14px;
    padding: 8px 12px;
    border-bottom: 1px solid #f0f2f5;

    .el-checkbox {
      margin-right: 0;
    }
  }

  .com-file-search {
    width: 220px;
  }

  .com-file-toolbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;

    .el-button + .el-button {
      margin-left: 0;
    }
  }

  .com-file-pagination {
    padding: 6px 12px;
    text-align: right;
    overflow-x: auto;
    border-top: 1px solid #f0f2f5;
  }

  .group-menu {
    border-right: none;
    width: 180px;

    .el-menu-item {
      height: 36px;
      line-height: 36px;
      font-size: 13px;
      color: #4b5563;
      padding-left: 10px !important;
      padding-right: 10px;

      [class^="el-icon-"] {
        font-size: 14px;
        width: auto;
        margin-right: 4px;
      }

      &.is-active {
        color: var(--admin-accent-text);
      }

      .el-button {
        padding: 3px 0;
        font-size: 12px;

        i {
          margin-right: 0;
        }
      }

      /* 「编辑 | 删除」中间的竖线 */
      .flex-row > div {
        font-size: 12px;
      }
    }
  }

  .com-file-name {
    color: #666666;
    margin-top: 5px;
    font-size: 13px;
    word-break: break-all;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
    overflow: hidden;
  }

  .scrollbar {
    /* 面板是 flex 列:列表区占剩余空间(flex:1)、min-height:0 允许内部滚动,
       从而给下方分页留出固定位置——否则 height:100% 占满后,分页的 margin-top:auto 会被顶出面板、盖住底栏按钮 */
    flex: 1;
    min-height: 0;

    .el-scrollbar__wrap {
      overflow-y: auto;
    }
  }
}

.del-com-file-dialog {
  .group-menu {
    .el-menu-item {
      .el-button {
        &:hover {
          background: #e2e2e2;
        }
      }
    }
  }
}

.com-file-simple-upload {
  width: 100% !important;
  height: 120px;
  border: 1px dashed #e3e3e3;
  cursor: pointer;

  &:hover {
    background: rgba(0, 0, 0, 0.05);
  }

  i {
    font-size: 32px;
  }
}

.com-file-video-cover {
  background-size: cover;
  background-position: center;
  width: 100%;
  height: 100%;
}

.com-file-video-info {
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
  position: absolute;
  left: 0;
  bottom: 0;
  padding: 1px 3px;
  font-size: 14px;
}

.com-file-video-name {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.com-file-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
}

.com-file-footer-info {
  font-size: 13px;
  color: #909399;

  b {
    color: var(--admin-accent-text);
    font-weight: 600;
  }
}

/* 小屏响应式:原"固定宽分组菜单(180px)+ 固定宽搜索(250px)+ 左右侧栏"布局,
   在窄弹窗里塞不下,会把图片列表挤出 dialog 框。
   小屏改为上下堆叠:分组菜单在上(占满宽、限高,避免把图片推得很远),图片面板在下占满宽,自然换行不溢出。 */
@media (max-width: 768px) {
  .com-file-dialog {
    .com-file-main {
      flex-direction: column;
      height: auto;
    }

    .com-file-side {
      border-right: none;
      border-bottom: 1px solid #e4e7ed;
    }

    .group-menu {
      width: 100%;
    }

    .group-scroll {
      flex: none;
      height: 160px;
    }

    .com-file-panel {
      height: 60vh;
    }
  }
}

/* 拖入文件上传:遮罩盖住整个上传区域,提示会传到哪个分组 */
.com-file-drop-zone {
  position: relative;
}

.com-file-drop-mask {
  position: absolute;
  inset: 0;
  z-index: 20;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 2px dashed var(--admin-accent-text);
  border-radius: 8px;
  background: var(--admin-accent-soft);
  color: var(--admin-accent-text);
  pointer-events: none; /* 遮罩不吃事件,drop 仍由下面的区域接收,也不会因进出遮罩反复触发 dragleave */

  .el-icon-upload {
    font-size: 56px;
  }

  .com-file-drop-title {
    font-size: 16px;
    font-weight: 500;
  }

  .com-file-drop-sub {
    font-size: 12px;
    color: var(--admin-accent-text);
  }
}

.com-file-simple-zone {
  min-height: 160px;
}

.com-file-drop-hint {
  margin-top: 10px;
  text-align: center;
  font-size: 12px;
  color: #909399;
}
</style>
