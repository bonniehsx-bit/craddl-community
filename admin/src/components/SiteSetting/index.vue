<template>
  <div class="site-setting-component" v-loading="is_loading">
    <div class="page-header" v-if="showHeader">
      <div class="header-left">
        <span class="page-title">站点全局配置</span>
        <span class="page-desc">管理系统的基础信息、SEO设置及品牌视觉素材</span>
      </div>
      <div class="header-right">
        <el-button
          type="primary"
          icon="el-icon-check"
          :loading="submitLoading"
          @click="submit"
        >保存配置</el-button>
      </div>
    </div>

    <el-form ref="form" :model="form" :rules="rules" label-position="top" size="medium">
      <el-row :gutter="24">

        <el-col :xs="24" :lg="16">
          <el-card shadow="never" class="setting-card">
            <div slot="header" class="card-header">
              <span class="title"><i class="el-icon-monitor"></i> 基础识别信息</span>
            </div>
            <el-row :gutter="24">
              <el-col :span="12" :xs="24">
                <el-form-item prop="name">
                  <template slot="label">
                    站点名称
                    <el-tooltip placement="top" effect="light">
                      <div slot="content">
                        显示在<b>后台顶部 Logo 旁边</b>的主标题<br>
                        例如登录页、入口页的"Craddl 商城管理系统"
                      </div>
                      <i class="el-icon-info field-info-icon"></i>
                    </el-tooltip>
                  </template>
                  <el-input v-model="form.name" placeholder="例如：Craddl 商城" maxlength="30" show-word-limit prefix-icon="el-icon-house"></el-input>
                  <div class="field-hint">显示在后台顶部 Logo 旁边的主标题</div>
                </el-form-item>
              </el-col>
              <el-col :span="12" :xs="24">
                <el-form-item prop="sub_name">
                  <template slot="label">
                    网站简称
                    <el-tooltip placement="top" effect="light">
                      <div slot="content">
                        左侧菜单<b>收缩</b>时显示的短标识（一般 2-4 字）
                      </div>
                      <i class="el-icon-info field-info-icon"></i>
                    </el-tooltip>
                  </template>
                  <el-input v-model="form.sub_name" placeholder="例如：Craddl" maxlength="10" prefix-icon="el-icon-price-tag"></el-input>
                  <div class="field-hint">左侧菜单收缩时显示的短标识</div>
                </el-form-item>
              </el-col>
              <el-col :span="24">
                <el-form-item prop="title">
                  <template slot="label">
                    浏览器标题
                    <el-tooltip placement="top" effect="light">
                      <div slot="content">
                        显示在<b>浏览器标签页上</b>的文字<br>
                        留空时会自动使用上方"站点名称"
                      </div>
                      <i class="el-icon-info field-info-icon"></i>
                    </el-tooltip>
                  </template>
                  <el-input v-model="form.title" :placeholder="form.name ? `留空将自动使用「${form.name}」` : '留空将自动使用站点名称'" prefix-icon="el-icon-document"></el-input>
                  <div class="field-hint">显示在浏览器标签页上的文字 · 留空时自动使用上方"站点名称"</div>
                </el-form-item>
              </el-col>
              <el-col :span="24">
                <el-form-item prop="slogan">
                  <template slot="label">
                    登录页副标题
                    <el-tooltip placement="top" effect="light">
                      <div slot="content">
                        显示在<b>登录页 WELCOME 下方</b>的品牌口号<br>
                        留空时使用默认文案
                      </div>
                      <i class="el-icon-info field-info-icon"></i>
                    </el-tooltip>
                  </template>
                  <el-input v-model="form.slogan" placeholder="例如：Craddl · 智能运营管理平台" maxlength="50" show-word-limit prefix-icon="el-icon-chat-line-square"></el-input>
                  <div class="field-hint">显示在登录页 WELCOME 下方的品牌口号</div>
                </el-form-item>
              </el-col>
              <el-col :span="24">
                <el-form-item label="站点域名" prop="domain">
                  <el-input v-model="form.domain" placeholder="如: https://www.example.com">
                    <template slot="prepend">URL</template>
                  </el-input>
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>

          <el-card shadow="never" class="setting-card" style="margin-top: 20px;">
            <div slot="header" class="card-header">
              <span class="title"><i class="el-icon-s-check"></i> 底部版权与备案</span>
            </div>
            <el-row :gutter="24">
              <el-col :span="12" :xs="24">
                <el-form-item label="ICP备案号" prop="record">
                  <el-input v-model="form.record" placeholder="粤ICP备xxxxxx号"></el-input>
                </el-form-item>
              </el-col>
              <el-col :span="12" :xs="24">
                <el-form-item label="版权链接" prop="copyright_url">
                  <el-input v-model="form.copyright_url" placeholder="点击版权跳转的地址"></el-input>
                </el-form-item>
              </el-col>
              <el-col :span="24">
                <el-form-item label="版权文字" prop="copyright">
                  <el-input v-model="form.copyright" type="textarea" :rows="2" placeholder="Copyright © 2023 All Rights Reserved."></el-input>
                </el-form-item>
              </el-col>
            </el-row>
          </el-card>
        </el-col>

        <el-col :xs="24" :lg="8">
          <el-card shadow="never" class="setting-card visuals-card">
            <div slot="header" class="card-header">
              <span class="title"><i class="el-icon-picture-outline"></i> 品牌视觉素材</span>
            </div>

            <div class="visual-list">
              <div class="visual-item">
                <div class="visual-info">
                  <span class="label">后台 Logo</span>
                  <span class="desc">尺寸 200x60 (PNG)</span>
                </div>
                <div class="upload-box-wrapper">
                  <div class="custom-upload-box" :class="{ 'has-file': form.logo }">
                    <div v-if="form.logo" class="preview-area">
                      <img :src="form.logo" />
                      <div class="mask"><i class="el-icon-delete" @click.stop="form.logo = ''"></i></div>
                    </div>
                    <file-picker v-else :multiple='false' :is_site="1" v-model="form.logo" class="uploader-trigger">
                      <i class="el-icon-upload cloud-icon"></i>
                    </file-picker>
                  </div>
                </div>
              </div>
              <el-divider class="visual-divider"></el-divider>

              <div class="visual-item">
                <div class="visual-info">
                  <span class="label">登录页 Logo</span>
                  <span class="desc">尺寸 120x120 (方)</span>
                </div>
                <div class="upload-box-wrapper">
                  <div class="custom-upload-box small" :class="{ 'has-file': form.login_logo }">
                    <div v-if="form.login_logo" class="preview-area">
                      <img :src="form.login_logo" />
                      <div class="mask"><i class="el-icon-delete" @click.stop="form.login_logo = ''"></i></div>
                    </div>
                    <file-picker v-else :multiple='false' :is_site="1" v-model="form.login_logo" class="uploader-trigger">
                      <i class="el-icon-plus"></i>
                    </file-picker>
                  </div>
                </div>
              </div>
              <el-divider class="visual-divider"></el-divider>

              <div class="visual-item">
                <div class="visual-info">
                  <span class="label">登录背景图</span>
                  <span class="desc">1920x1080 (JPG)</span>
                </div>
                <div class="upload-box-wrapper">
                  <div class="custom-upload-box landscape" :class="{ 'has-file': form.login_pic }">
                    <div v-if="form.login_pic" class="preview-area">
                      <img :src="form.login_pic" style="object-fit: cover;" />
                      <div class="mask"><i class="el-icon-delete" @click.stop="form.login_pic = ''"></i></div>
                    </div>
                    <file-picker v-else :multiple='false' :is_site="1" @selected="e => handlePicSuccess(e, 'login_pic')" class="uploader-trigger">
                      <i class="el-icon-picture"></i>
                    </file-picker>
                  </div>
                </div>
              </div>
              <el-divider class="visual-divider"></el-divider>

              <div class="visual-item">
                <div class="visual-info">
                  <span class="label">浏览器图标 (Favicon)</span>
                  <span class="desc">尺寸 32x32 (ICO/PNG)</span>
                </div>
                <div class="upload-box-wrapper">
                  <div class="custom-upload-box small" :class="{ 'has-file': form.favicon }">
                    <div v-if="form.favicon" class="preview-area">
                      <img :src="form.favicon" />
                      <div class="mask"><i class="el-icon-delete" @click.stop="form.favicon = ''"></i></div>
                    </div>
                    <file-picker v-else :multiple='false' :is_site="1" v-model="form.favicon" class="uploader-trigger">
                      <i class="el-icon-plus"></i>
                    </file-picker>
                  </div>
                </div>
              </div>
              <el-divider class="visual-divider"></el-divider>

              <div class="visual-item">
                <div class="visual-info">
                  <span class="label">顶部装饰图</span>
                  <span class="desc">高度60px</span>
                </div>
                <div class="upload-box-wrapper">
                  <div class="custom-upload-box wide" :class="{ 'has-file': form.header_pic }">
                    <div v-if="form.header_pic" class="preview-area">
                      <img :src="form.header_pic" style="object-fit: cover;" />
                      <div class="mask"><i class="el-icon-delete" @click.stop="form.header_pic = ''"></i></div>
                    </div>
                    <file-picker v-else :multiple='false' :is_site="1" @selected="e => handlePicSuccess(e, 'header_pic')" class="uploader-trigger">
                      <i class="el-icon-picture-outline-round"></i>
                    </file-picker>
                  </div>
                </div>
              </div>
            </div>
          </el-card>

          <div class="footer-actions" v-if="!showHeader && showFooterBtn">
            <el-button type="primary" :loading="submitLoading" icon="el-icon-check" @click="submit" style="width: 100%">保存当前配置</el-button>
          </div>

        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script>
import store from "@/store";

export default {
  name: 'SiteSetting',
  props: {
    // 是否显示顶部标题栏 (在弹窗中使用时通常设为 false)
    showHeader: {
      type: Boolean,
      default: true
    },
    // 当隐藏头部时，是否在右下角显示保存按钮
    showFooterBtn: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      is_loading: false,
      submitLoading: false,
      form: {
        name: '', sub_name: '', title: '', slogan: '', domain: '',
        record: '', copyright: '', copyright_url: '',
        logo: '', login_logo: '', login_pic: '', header_pic: '', favicon: ''
      },
      rules: {
        name: [{ required: true, message: '请输入站点名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getSetting();
  },
  methods: {
    getSetting() {
      this.is_loading = true;
      this.$request({
        url: '/admin/site/setting',
        method: 'get'
      }).then(res => {
        if (res.code === 0 && res.data && res.data.setting) {
          const setting = res.data.setting;
          Object.keys(this.form).forEach(key => {
            if (setting[key] !== undefined) this.form[key] = setting[key];
          });
        }
      }).finally(() => {
        this.is_loading = false;
      });
    },
    handlePicSuccess(e, key) {
      if (Array.isArray(e) && e.length > 0) this.form[key] = e[0].url;
    },
    submit() {
      this.$refs['form'].validate((valid) => {
        if (valid) {
          this.submitLoading = true;
          this.$request({
            url: '/admin/site/setting',
            method: 'post',
            data: this.form
          }).then(res => {
            if (res.code === 0) {
              this.$notify.success({ title: '成功', message: '站点配置已更新' });
              store.commit('SET_SETTING', this.form);
              localStorage.setItem("site-setting", JSON.stringify(this.form));
              // 发送事件，供父组件监听（例如父组件需要关闭弹窗）
              this.$emit('success');
            } else {
              this.$message.error(res.msg || '保存失败');
            }
          }).finally(() => {
            this.submitLoading = false;
          });
        }
      });
    }
  }
}
</script>

<style lang="scss" scoped>
/* 组件容器：自适应高度，不强行占满屏幕 */
.site-setting-component {
  background-color: transparent; /* 透明背景，适应弹窗或页面 */
  width: 100%;
}

/* 顶部标题区 */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e8e8e8;

  .header-left {
    .page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; }
    .page-desc { font-size: 12px; color: #909399; margin-top: 4px; display: block;}
  }
}

/* 卡片样式微调 */
.setting-card {
  border: 1px solid #ebeef5;
  border-radius: 8px;

  ::v-deep .el-card__header {
    padding: 12px 20px;
    background-color: #fcfcfc;
    border-bottom: 1px solid #f0f0f0;
  }

  ::v-deep .el-card__body {
    padding: 20px;
  }

  .title {
    font-size: 14px; font-weight: 600; color: #303133;
    i { margin-right: 6px; color: var(--admin-accent-text); }
  }
}

/* 视觉列表通用样式 */
.visual-list { padding: 0 5px; }
.visual-item {
  display: flex; justify-content: space-between; align-items: center; padding: 4px 0;
  .visual-info {
    display: flex; flex-direction: column;
    .label { font-size: 13px; font-weight: 500; color: #333; }
    .desc { font-size: 12px; color: #999; transform: scale(0.9); transform-origin: left; }
  }
}
.visual-divider { margin: 12px 0; background-color: #f5f7fa; }

/* 底部补充按钮 */
.footer-actions {
  margin-top: 15px;
}

/* 标签后的 info 图标 */
.field-info-icon {
  color: #909399;
  margin-left: 4px;
  cursor: help;
  font-size: 14px;
  &:hover { color: var(--admin-accent-text); }
}

/* 字段下方提示文字 */
.field-hint {
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
  margin-top: 2px;
}

/* 上传盒子样式 (简化版) */
.custom-upload-box {
  position: relative; width: 120px; height: 50px;
  border: 1px dashed #dcdfe6; border-radius: 4px; background-color: #fff;
  cursor: pointer; overflow: hidden; transition: all 0.3s;

  &:hover { border-color: var(--admin-accent); }
  &.has-file { border-style: solid; }

  /* 尺寸变体 */
  &.small { width: 50px; height: 50px; }
  &.landscape { width: 90px; height: 50px; }
  &.wide { width: 120px; height: 40px; }

  .uploader-trigger {
    width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; color: #8c939d;
    i { font-size: 20px; }
  }

  .preview-area {
    width: 100%; height: 100%; position: relative;
    img { width: 100%; height: 100%; object-fit: contain; }
    .mask {
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center;
      opacity: 0; transition: opacity 0.3s;
      i { color: #fff; cursor: pointer; }
    }
    &:hover .mask { opacity: 1; }
  }
}
</style>
