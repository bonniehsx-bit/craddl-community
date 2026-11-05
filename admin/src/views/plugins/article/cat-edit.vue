<template>
  <el-dialog :visible.sync="dialogVisible" title="分类编辑" width="50%" :before-close="handleClose">
    <el-row>
      <el-col :span="24">
        <el-form size="mini" ref="form" :model="form" label-width="120px">

          <el-form-item label="分类名称">
            <el-input v-model="form.name"></el-input>
          </el-form-item>
          <el-form-item label="封面图">
            <template v-if="form.cover_pic">
              <div class="flex-row">
                <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center">
                  <img :src="form.cover_pic" alt="" style="width: 100%;height: 100%;">
                  <div class="flex-x-center flex-y-center pic-del" @click="form.cover_pic=''">
                    <i class="el-icon-close"></i>
                  </div>
                </div>
              </div>
            </template>
            <file-picker :max="1" :multiple="false" v-model="form.cover_pic"
                         v-if="form.cover_pic==''||!form.cover_pic">
              <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center">
                <i class="el-icon-upload"></i>
              </div>
            </file-picker>
          </el-form-item>


        </el-form>


      </el-col>
    </el-row>
    <span slot="footer" class="dialog-footer">
      <el-button @click="handleClose" size="mini">取 消</el-button>
      <el-button type="primary" @click="save" size="mini">保 存</el-button>
    </span>
  </el-dialog>
</template>
<script>
export default {
  name: 'cat-edit',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    catId: {
      type: [String, Number],
      default: ''
    }
  },
  data() {
    return {
      dialogVisible: false,
      form: {
        id: '',
        name: '',
        cover_pic: '',
      }
    }
  },
  watch: {
    visible(val) {
      this.dialogVisible = val;
      if (val) {
        if (this.catId) {
          this.form.id = this.catId;
          this.getCat();
        } else {
          this.resetForm();
        }
      }
    },
    dialogVisible(val) {
      if (!val) {
        this.$emit('update:visible', false);
      }
    }
  },
  methods: {
    handleClose() {
      this.dialogVisible = false;
    },
    resetForm() {
      this.form = {
        id: '',
        name: '',
        cover_pic: ''
      };
    },
    getCat() {
      this.$request({
        url: "/plugin/article/mall/article/cat",
        data: {id: this.form.id},

      }).then(res => {
        if (res.code == 0) {
          if (res.data.cat) {
            this.form = res.data.cat;
          }
        }
      })
    },
    save() {
      this.$request({
        url: "/plugin/article/mall/article/cat",
        data: this.form,
        method: 'post'
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg);
          this.handleClose();
          this.$emit('success');
        } else {
          this.$message.error(res.msg);
        }
      })

    }
  }
}
</script>

<style scoped>
</style>
