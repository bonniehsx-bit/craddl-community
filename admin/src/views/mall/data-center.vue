<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>数据中心</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>


     <el-form size="mini" ref="form" label-width="160px">
        <el-form-item label="菜单按钮">
          <el-form-item>
            <div v-for="(item, index) in form.menus" class="edit-nav-item">
              <div class="nav-edit-options">
                <el-button @click="menuItemDelete(index)" type="primary" icon="el-icon-delete"
                           style="top: -6px;right: -31px;"></el-button>
              </div>
              <div flex="dir:left box:first cross:center">
                <single-image-selector v-model="item.icon"></single-image-selector>
                <div style="margin-top: 10px;">
                  <el-input v-model="item.name" placeholder="名称" size="mini" style="margin-bottom: 5px">
                  </el-input>
                  <div>
                    <el-input v-model="item.url" placeholder="点击选择链接" size="mini">

                      <template slot="append">
                        <div>
                          <link-picker v-model="item.url">
                            选择链接
                          </link-picker>
                        </div>
                      </template>
                    </el-input>
                  </div>
                </div>
              </div>
            </div>
            <el-button size="mini" @click="addMenuItem">添加按钮</el-button>
          </el-form-item>
        </el-form-item>
      </el-form>


    </el-card>

  </div>

</template>

<script>
export default {
  name: 'mall-data-center',
  data() {
    return {
      form: {
        menus: []
      },
    }
  },
  created() {
    this.getSetting();
  },
  methods: {
    addMenuItem() {
      this.form.menus.push({
        pic_url: '',
        name: '',
        url: '',
        open_type: '',
      });
    },
    menuItemDelete(index) {
      this.form.menus.splice(index, 1);
    },
    save() {
      this.$request({
        url: '/mall/mall/data-center',
        data: this.form,
        method: 'post'
      }).then(res => {
        this.$message.success(res.msg)
      })
    },
    getSetting() {
      this.$request({
        url: '/mall/mall/data-center',
      }).then(res => {
        this.form = Object.assign(this.form, res.data)
      })
    },
  }
}
</script>


<style lang="scss">
.mobile-preview {
  width: 850px;
  background-color: #F3F3F3;
  height: 1650px;
  zoom: 50%;
  border-radius: 10px;
  box-sizing: border-box;
  border: 4px solid #F3F3F3;
  overflow: hidden;
  font-size: 24px;
}

.tab-item {
  text-align: center;

  display: block;
  width: 100px;

  .icon {
    width: 60px;
    height: 60px;
  }

  .text {

    padding-top: 5px;
  }

}

.nav-item {
  text-align: center;
  font-size: 24px;
  padding: 20px 0;
}

.nav-item > div {
  height: 25px;
  line-height: 25px;
}

.nav-item img {
  display: block;
  width: 88px;
  height: 88px;
  margin: 0 auto 5px auto;
}

.edit-nav-item {
  border: 1px solid #e2e2e2;
  line-height: normal;
  padding: 5px;
  margin-bottom: 5px;
  max-width: 450px;
}

.nav-icon-upload {
  display: block;
  width: 65px;
  height: 65px;
  line-height: 65px;
  border: 1px dashed var(--admin-accent-text);
  color: var(--admin-accent-text);
  background: #f9f9f9;
  cursor: pointer;
  background-size: 100% 100%;
  font-size: 28px;
  text-align: center;
  vertical-align: middle;
}

.nav-edit-options {
  position: relative;
}

.nav-edit-options {
  .el-button {
    height: 25px;
    line-height: 25px;
    width: 25px;
    padding: 0;
    text-align: center;
    border: none;
    border-radius: 0;
    position: absolute;
    margin-left: 0;
  }
}
</style>
