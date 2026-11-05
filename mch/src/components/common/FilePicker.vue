<template>
  <div class="com-file">
    <el-dialog class="com-file-dialog" :title="title ? title : '选择文件'" :visible.sync="dialogVisible"
               :close-on-click-modal="false" :width="simple?'25%':'65%'" top="6vh" append-to-body @opened="dialogOpened">
      <template v-if="simple">
        <!-- [核心修改] 传入动态的 action 上传地址 -->
        <file-upload v-loading="uploading" class="com-file-simple-upload flex-x-center flex-y-center"
                     :action="currentApi.upload"
                     :disabled="uploading" :multiple="true" :max="10" :params="uploadParams" :fields="uploadFields"
                     :accept="accept" flex="main:center cross:center" @start="handleStart" @success="handleSuccess"
                     @complete="handleComplete" @fail="handleFail" :is_site="is_site">
          <div v-if="uploading">{{ uploadCompleteFilesNum }}/{{ uploadFilesNum }}</div>
          <i v-else class="el-icon-upload" />
        </file-upload>
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
                    <div flex="dir:left box:last" class="flex-row">
                      <div style="overflow: hidden;text-overflow: ellipsis;white-space: nowrap;flex: 1;min-width: 0;">
                        <i class="el-icon-tickets" />
                        <span>{{ item.name }}</span>
                      </div>
                      <div flex="dir:left" class="flex-row" style="flex-shrink: 0;">
                        <el-button type="text" @click.stop="showAddGroup(index)">编辑</el-button>
                        <div style="color:#409EFF;margin:0 2px">|</div>
                        <el-button type="text" @click.stop="deleteGroup(index)">删除</el-button>
                      </div>
                    </div>
                  </el-menu-item>
                </template>
              </el-scrollbar>
            </el-menu>
          </div>
          <div v-loading="loading" class="com-file-panel">
            <div class="com-file-toolbar">
              <el-input v-model="p_keyword" size="small" placeholder="搜索文件名" class="com-file-search"
                        @keyup.enter.native="fileSearch">
                <el-button slot="append" icon="el-icon-search" @click="fileSearch" />
              </el-input>
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
                  <!-- [核心修改] 传入动态的 action 上传地址 -->
                  <file-upload style="height: 100%;" v-loading="uploading" :disabled="uploading"
                               :action="currentApi.upload"
                               :multiple="true" :max="10" :params="uploadParams" :fields="uploadFields"
                               :accept="accept" @fail="handleFail" flex="main:center cross:center"
                               @start="handleStart" @complete="handleComplete" :is_site="is_site">
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
                         @click="handleClick(item)">
                      <!-- :key="index" -->
                      <el-image v-if="item.type == 'image'" class="com-file-img"
                                :src="item.thumb_url" style="width: 100px;height: 100px;" fit="cover">
                      </el-image>
                      <span v-if="imageBadge(item)" :class="'com-file-badge is-' + imageBadge(item).type">{{ imageBadge(item).text }}</span>
                      <div v-if="item.type == 'video'" class="com-file-img"
                           style="width: 100px;height: 100px;position: relative">
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
                      <div v-if="item.type == 'doc'" class="com-file-img"
                           style="width: 100px;height: 100px;line-height: 100px;text-align: center">
                        <i class="file-type-icon el-icon-document" />
                      </div>
                      <div class="com-file-name">{{ item.name }}</div>
                      <i v-if="false" class="com-file-active-icon el-icon-circle-check" />
                    </div>
                  </el-tooltip>
                </template>
              </div>
            </el-scrollbar>
            <div v-if="pagination" class="com-file-pagination">
              <el-pagination background :current-page="page" :page-size="pageSize" :page-sizes="pageSizes"
                             layout="total, sizes, prev, pager, next, jumper" :total="pagination.total_count"
                             @size-change="handleSizeChange" @current-change="handleLoadMore" />
            </div>
          </div>
        </div>
        <div style="text-align: right;margin-top: 12px;">
          <el-button type="primary" @click="confirm">选定</el-button>
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
import FileUpload from '@/components/common/file-upload.vue'

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
    },
    // [核心新增] 是否为商家端
    isMch: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
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
      p_keyword: '',

      // [核心新增] API 接口映射表
      apiMap: {
        admin: {
          list: '/common/file/list',
          upload: '/common/file/upload',
          delete: '/common/file/delete',
          groupList: '/common/file/group-list',
          groupEdit: '/common/file/group-edit',
          groupDelete: '/common/file/group-delete',
          move: '/common/file/move'
        },
        mch: {
          // 商家端使用 plugin/mch/common/file/* 接口
          list: '/plugin/mch/common/file/list',
          upload: '/plugin/mch/common/file/upload',
          delete: '/plugin/mch/common/file/delete',
          groupList: '/plugin/mch/common/file/group-list',
          groupEdit: '/plugin/mch/common/file/group-edit',
          groupDelete: '/plugin/mch/common/file/group-delete',
          move: '/plugin/mch/common/file/move' // 暂用，后端需实现或屏蔽
        }
      }
    }
  },
  computed: {
    accept: {
      get() {
        if (this.type === 'image') {
          return 'image/*'
        }
        if (this.type === 'video') {
          return 'video/*'
        }
        return '*/*'
      }
    },
    // [核心新增] 计算当前使用的 API 集合
    currentApi() {
      return this.isMch ? this.apiMap.mch : this.apiMap.admin;
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
  created() { },
  methods: {
    /**
     * 图片角标:有缩略图的(上传时自动生成,首页 / 列表加载的是小图)标「已优化」;
     * 没有缩略图又超过 300KB 的标「大图」提醒,小图不打标。老图没补大小前 size 为 0,也不打标
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
    handleFail(e) {
      this.uploading = false
    },
    fileSearch() {
      this.page = 1
      this.loading = true
      this.getGroupList()
      this.getList({})
    },

    dialogOpened() {
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
          this.$request({
            url: this.currentApi.delete, // [修改]
            data: {
              ids: itemIds
            },
            method: 'post'
          }).then(res => {

            if (res.code == 0) {
              this.$message.success('成功删除' + res.data.res + '条数据');
              this.getList();
              this.showEditBlock = false;
            }

          })
        })
        .catch(() => { })
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
            url: this.currentApi.move, // [修改]
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

          })



        })
        .catch(() => { })
    },

    getGroupList() {
      this.groupListLoading = true
      this.groupListLoading = false
      this.$request({
        url: this.currentApi.groupList, // [修改]
        data: {
          keyword: this.keyword,
          is_site: this.is_site
        }
      }).then(res => {
        if (res.code == 0) {
          this.group_list = res.data.list
        } else {
          this.$message.error(res.msg);
        }
      })

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
      this.groupFormLoading = true;
      const title = '是否确认将分组放入回收站中？删除的分组可通过回收站还原'
      this.$confirm(title, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
        .then(() => {
          let id = this.group_list[index].id;
          this.$request({
            url: this.currentApi.groupDelete, // [修改]
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
          })


        })
        .catch(() => { })
    },
    groupFormSubmit(formName) {
      this.$refs[formName].validate(valid => {
        if (valid) {
          this.groupFormLoading = true
          this.$request({
            url: this.currentApi.groupEdit, // [修改]
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
      this.current_group_id =
        index > -1 ? this.group_list[index].id : null
      this.getList({})
    },
    getList(params) {
      this.noMore = false
      this.selectAll = false
      this.checkedfiles = []
      this.loading = true
      this.loadingMore = false
      this.$request({
        url: this.currentApi.list, // [修改]
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
          this.files = res.data.list;
          this.pagination = res.data.pagination;
        }
      })
    },
    handleClick(item) {
      if (item.checked) {
        item.checked = false
        for (const i in this.checkedfiles) {
          if (item.id === this.checkedfiles[i].id) {
            this.checkedfiles.splice(i, 1)
          }
        }
        this.$forceUpdate();
        return
      }
      if (this.multiple) {
        let checkedCount = 0
        for (const i in this.files) {
          if (this.files[i].checked) checkedCount++
        }
        if (this.max && !item.checked && checkedCount >= this.max) return
        item.checked = true
        this.checkedfiles.push(item)
      } else {
        for (const i in this.files) this.files[i].checked = false
        item.checked = true
        this.checkedfiles = [item]
      }
      this.$forceUpdate();
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
    handleComplete(files) {
      this.uploading = false
      if (this.simple) {
        const urls = []
        const upload_files = []
        for (const i in files) {
          let newItem = files[i]
          urls.push(newItem.url)
          upload_files.unshift(newItem)
        }
        const _this = this
        setTimeout(() => {
          if (!urls.length) {
            return
          }
          _this.dialogVisible = false
          _this.$emit('selected', upload_files, _this.params)
          if (_this.multiple) {
            _this.$emit('input', urls)
          } else {
            _this.$emit('input', urls[0])
          }
        }, 1000)
        return;
      }
      for (const i in files) {
        if (files[i].response.data && files[i].response.code === 0) {
          this.files.unshift(files[i].response.data);
        }
      }
    },
    handleSuccess(file) {

      if (file.response && file.response.data && file.response.code === 0) {
        const newItem = {
          checked: false,
          selected: false,
          created_at: file.response.data.created_at,
          deleted_at: file.response.data.deleted_at,
          id: `${file.response.data.id}`,
          is_delete: file.response.data.is_delete,
          mall_id: file.response.data.mall_id,
          name: file.response.data.name,
          size: file.response.data.size,
          storage_id: file.response.data.storage_id,
          thumb_url: file.response.data.thumb_url,
          type: file.response.data.type,
          updated_at: file.response.data.updated_at,
          url: file.response.data.url,
          user_id: file.response.data.user_id,
          duration: null,
          cover_pic_src: null,
        };
        this.files.unshift(newItem);
        this.uploadCompleteFilesNum++;
        this.updateVideo();
      }
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
        if (this.files[i].type == 2) {
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
/* 样式部分保持不变 */
.com-file-list {
  padding: 5px;

  * {
    box-sizing: border-box;
  }

  &:after {
    clear: both;
    display: block;
    content: " ";
  }
}
/* ... 略去未修改的 CSS 样式 ... */
.com-file-item {
  display: inline-block;
  cursor: pointer;
  position: relative;
  float: left;
  width: 120px;
  height: 140px;
  margin: 7.5px;
  text-align: center;
  padding: 10px 10px 0;

  &:hover {}

  .com-file-img {
    display: block;
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

.com-file-active-icon {
  position: absolute;
  right: 5px;
  top: 5px;
  font-size: 28px;
  color: #409eff;
  text-shadow: 0 0 1px rgba(255, 255, 255, 0.75);
  opacity: 0;
}

.com-file-item.checked,
.com-file-item.selected {
  box-shadow: 0 0 0 1px #1ed0ff;
  background: #daf5ff;
  border-radius: 5px;
}

.com-file-item.checked .com-file-active-icon,
.com-file-item.selected .com-file-active-icon {
  opacity: 1;
}

.com-file-upload {
  box-shadow: none;
  border: 1px dashed #b2b6bd;
  height: 100px;
  width: 100px;
  margin: 17.5px;
  padding: 0;

  i {
    font-size: 30px;
    color: #909399;
  }

  &:hover {
    box-shadow: none;
    border: 1px dashed #409eff;

    i {
      color: #409eff;
    }
  }

  &:active {
    border: 1px dashed #20669c;

    i {
      color: #20669c;
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
        color: #409eff;
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

  /* 面板是 flex 列:列表区占剩余空间并内部滚动,分页固定在底部 */
  .scrollbar {
    flex: 1;
    min-height: 0;

    ::v-deep .el-scrollbar__wrap {
      overflow-x: hidden;
    }
  }
}

.com-file-badge {
  position: absolute;
  left: 12px;
  top: 12px;
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
</style>
