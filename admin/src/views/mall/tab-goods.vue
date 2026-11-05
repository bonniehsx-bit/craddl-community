<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <span>标签商品组</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="drawer=true">新建标签</el-button>
      </div>
      <el-table size="mini"  :data="list" border style="width: 100%">
        <el-table-column prop="id" label="ID">
        </el-table-column>
        <el-table-column prop="name" label="标签组名称">
        </el-table-column>
        <el-table-column label="操作">
          <template slot-scope="scope">
            <el-button type="text" @click="edit(scope.row)">编辑</el-button>
            <el-button type="text" @click="del(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div style="text-align: right;margin: 20px 0;">
        <pagination :pagination="pagination" @pageChage="getList">
        </pagination>
      </div>
    </el-card>
    <el-drawer
      @close="closeDrawer"
      title="标签组编辑"
      :visible.sync="drawer"
      size="50%">
     <el-form size="mini" ref="form" :model="form" label-width="80px">
        <el-form-item label="组名">
          <el-input v-model="form.name"></el-input>
        </el-form-item>
        <el-form-item label="标签配置">
          <el-button type="primary" @click="addItem">添加标签</el-button>
          <div v-for="item in form.list" style="padding: 10px 0">
            <el-form-item label="标签组">
              <div style="border: solid #f0f0f0 4px;border-radius: 2px;padding: 10px;margin: 2px">
                <el-form-item label="标签名称">
                  <el-input v-model="item.name"></el-input>
                </el-form-item>
                <el-form-item label="类型">
                  <el-radio-group v-model="item.type">
                    <el-radio label="0">分类商品</el-radio>
                    <el-radio label="1">自选商品</el-radio>
                  </el-radio-group>
                </el-form-item>
                <el-form-item label="分类ID">
                  <el-input v-model="item.cat_id" placeholder="填写分类ID"></el-input>
                </el-form-item>
                <div style="padding: 20px">
                  <goods-picker  v-model="item.list" style="margin-right: 5px;" width="88"
                                 height="88">
                    <el-button type="text">+添加商品({{ item.list.length }}/50)</el-button>
                  </goods-picker>
                </div>
                <el-table size="mini"  :data="item.list" style="width: 100%">
                  <el-table-column label="商品" width="350">
                    <template slot-scope="scope">
                      <div class="flex-row">
                        <div>
                          <el-image :src="scope.row.cover_pic" style="width: 60px;height: 60px;" fit="fit">
                            <div slot="error" class="image-slot">
                              <i class="el-icon-picture-outline"></i>
                            </div>
                          </el-image>
                        </div>
                        <div style="padding: 10px;">
                          {{ scope.row.name }}
                        </div>
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column label="价格" width="80">
                    <template slot-scope="scope">
                      <span style="font-weight: bold;color: #000000;">￥{{ scope.row.price }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="操作">
                    <template slot-scope="scope">
                      <el-button size="mini" type="text" @click="goodsDelete(scope.$index,item)">
                        删除
                      </el-button>
                    </template>
                  </el-table-column>
                </el-table>
              </div>
            </el-form-item>
          </div>
          <el-form-item style="margin-top: 30px">
            <el-button type="primary" @click="save">保存</el-button>
            <el-button @click="closeDrawer">取消</el-button>
          </el-form-item>
        </el-form-item>
      </el-form>
    </el-drawer>
  </div>
</template>

<script>
export default {
  name: 'mall-tab-goods',
  data() {
    return {
      form: {
        id: '',
        name: '',
        list: [],
      },
      item: {
        name: '',
        type: '0',
        list: [],
        cat_id: '',
      },
      drawer: false,
      page: 1,
      list: [],
      is_loading: false,
      pagination: null
    }
  },
  created() {
    this.getList();
  },
  methods: {
    goodsDelete(index, item) {
      item.list.splice(index, 1)
    },
    closeDrawer(e) {
      this.form = {
        id: '',
        name: '',
        list: [],
      }
      this.item = {
        name: '',
        type: '0',
        list: [],
        cat_id: '',
      };
      this.drawer = false;
    },
    addItem() {
      this.form.list.push(this.item);
    },
    save() {
      if (this.is_loading) {
        return;
      }
      this.is_loading = true;
      this.$request({
        url: "/mall/mall/tab-goods",
        data: this.form,
        method: 'post'
      }).then(res => {
        this.$message.success(res.msg);
        this.closeDrawer();
        this.getList();
      })
    },
    search() {
      this.$request({
        url: "/mall/mall/tab-goods",
        data: this.form
      }).then(res => {

        this.form = res.data.info;
        this.drawer = true;
      })
    },
    getList() {
      this.$request({
        url: '/mall/mall/tab-goods-list',
        data: {
          page: this.page,
        }
      }).then(res => {
        this.is_loading = false;
        this.list = res.data.list
        this.pagination = res.data.pagination
      })
    },
    edit(row) {
      this.form.id = row.id;
      this.search();
    },
    del(row) {
      this.$confirm('确认删除标签组：' + row.name + '？', '提示', {
        type: 'warning'
      }).then(() => {
        this.is_loading = true;
        this.$request({
          url: "/mall/mall/tab-goods-del",
          data: {
            id: row.id
          },
          method: 'POST'
        }).then(res => {
          this.is_loading = false;
          this.$message.success(res.msg)
          this.getList();
        })
      }).catch(() => {


      })


    },

  }
}
</script>
<style>
</style>
