<template>
  <div class="app-container">

    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <span>编辑运费</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
        <el-button v-if="embedded" style="float: right; padding: 3px 0; margin-right: 16px;" type="text" @click="$emit('close')">关闭</el-button>
      </div>
      <div>
        <el-form ref="form" :model="form" label-width="80px">
          <el-row>
            <el-col :span="12">

              <el-form-item label="模板名称">
                <el-input v-model="form.name"></el-input>
              </el-form-item>
              <el-form-item label="计费方式">
                <el-radio-group v-model="form.price_type">
                  <el-radio :label="0">按件计费</el-radio>
                  <el-radio :label="1">按重计费</el-radio>
                </el-radio-group>
              </el-form-item>
              <el-form-item label="是否启用">
                <el-radio-group v-model="form.enabled">
                  <el-radio :label="0">否</el-radio>
                  <el-radio :label="1">是</el-radio>
                </el-radio-group>
              </el-form-item>
              <el-form-item label="是否默认">
                <el-radio-group v-model="form.is_default">
                  <el-radio :label="0">否</el-radio>
                  <el-radio :label="1">是</el-radio>
                </el-radio-group>
              </el-form-item>

            </el-col>

          </el-row>

          <el-form-item label="费用设置">
            <el-row>
              <el-col :span="24">
                <el-table :data="list" border style="width: 100%;">
                  <el-table-column label="配送范围" width="500px">
                    <template slot-scope="scope">
                      <div style="overflow: hidden; word-wrap: break-word;  text-overflow: ellipsis;display: -webkit-box;max-height: 150px; ">
                        {{scope.row.send_area}}
                      </div>
                      <div>
                        <!-- [优化] 只有非第一行才显示重选按钮 -->
                        <el-button type="text" v-if="scope.$index > 0" @click="editRow(scope.$index)">重选</el-button>
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column prop="first_num" :label="form.price_type===0?'首件':'首重(克)'">
                    <template slot-scope="scope">
                      <el-input v-model="scope.row.first_num" style="width:100%;hight:100%"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column prop="first_price" label="运费(元)">
                    <template slot-scope="scope">
                      <el-input v-model="scope.row.first_price" style="width:100%;hight:100%"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column prop="other_num" :label="form.price_type===0?'续件':'续重(克)'">
                    <template slot-scope="scope">
                      <el-input v-model="scope.row.other_num" style="width:100%;hight:100%"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column prop="other_price" label="运费(元)">
                    <template slot-scope="scope">
                      <el-input v-model="scope.row.other_price" style="width:100%;hight:100%"></el-input>
                    </template>
                  </el-table-column>
                  <!-- [优化] 新增操作列，用于删除 -->
                  <el-table-column label="操作">
                    <template slot-scope="scope">
                      <el-button type="text" style="color: #F56C6C;" v-if="scope.$index > 0" @click="deleteRow(scope.$index)">删除</el-button>
                    </template>
                  </el-table-column>
                </el-table>
                <div class="add-area" @click="addArea">+添加区域</div>
              </el-col>
            </el-row>
          </el-form-item>
        </el-form>
      </div>

      <area-picker :openDialog="showAreaPicker" @success="success"></area-picker>

    </el-card>

  </div>
</template>

<script>
import AreaPicker from '@/components/common/AreaPicker'

export default {
  name: 'FreightEdit',
  components: {
    AreaPicker
  },
  props: {
    // 抽屉模式由父组件传入 id；独立页面模式从 $route.query.id 取
    pageId: { type: [String, Number], default: null },
    embedded: { type: Boolean, default: false },
  },
  data() {
    return {
      is_loading: false,
      showAreaPicker: false,
      table_item: {
        send_area: '',
        send_codes: [],
        first_num: '',
        first_price: '',
        other_num: '',
        other_price: '',
        is_union: 0
      },
      index: -1,
      list: [{
        send_area: '全国统一运费',
        send_codes: [],
        first_num: '1',
        first_price: '0.00',
        other_num: '1',
        other_price: '0.00',
        is_union: 1,
      }, ],
      select_list: [],
      form: {
        name: '',
        price_type: 0,
        enabled: 1,
        is_default: 0,
        price_list: [],
      }
    }
  },
  created() {
    // 抽屉模式取 prop.pageId；独立页面模式取路由 query.id
    const id = this.embedded
      ? this.pageId
      : (this.$route && this.$route.query ? this.$route.query.id : null);
    if (id) {
      this.form.id = id;
      this.getFreight();
    }
  },
  methods: {
    getFreight() {
      this.is_loading = true;
      this.$request({
        url: "/mall/mall/freight",
        data: this.form,
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.form = res.data.freight;
          if (res.data.price_list && res.data.price_list.length) {
            this.list = res.data.price_list;
          }
        }
      })
    },
    addArea() {
      this.showAreaPicker = true;
      this.index = -1;
    },
    editRow(index) {
      this.index = index;
      this.showAreaPicker = true;
    },
    // [优化] 新增删除行方法
    deleteRow(index) {
      this.$confirm('确认删除该配送区域规则吗?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.list.splice(index, 1);
      }).catch(() => {});
    },
    save() {
      this.is_loading = true;
      this.form.price_list = this.list;
      // 运费模板含大量嵌套区域编码(send_codes)，用 JSON 提交，
      // 避免 qs.stringify 拍平后超过 PHP max_input_vars=1000 导致字段被截断。
      this.$request({
        url: "/mall/mall/freight",
        data: this.form,
        method: 'post',
        headers: { 'Content-Type': 'application/json' }
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.$message.success(res.msg);
          // 通知父组件（抽屉模式）刷新列表
          this.$emit('saved');
          // 独立页面模式才返回列表；抽屉模式保持打开
          if (!this.embedded) {
            this.$go.back();
          }
        } else {
          // 如果后端有错误信息，也提示出来
          this.$message.error(res.msg);
        }
      })
    },
    success(e) {
      this.showAreaPicker = false;
      let area = '';
      e.checked_nodes.forEach((item) => {
        area += (item.name + ',')
      })
      // 去掉最后一个逗号
      if(area.length > 0) {
        area = area.substring(0, area.length - 1);
      }

      if (e.checked_keys) {
        if (this.index == -1) {
          let item = JSON.parse(JSON.stringify(this.table_item));
          item.send_area = area;
          item.send_codes = e.checked_keys;
          this.list.push(item);
        } else {
          this.list[this.index].send_area = area;
          this.list[this.index].send_codes = e.checked_keys;
          this.$forceUpdate();
        }
      }
    },
  }
}
</script>

<style scoped="scoped">
.list {
  height: 450px;
  max-height: 450px;
}
.custom-tree-node {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
  padding-right: 8px;
}
::-webkit-scrollbar {
  display: none;
}
.add-area {
  width: 100%;
  text-align: center;
  color: var(--admin-accent-text);
  border: 1px solid #EBEEF5;
  border-top: none;
  padding: 10px 0;
  cursor: pointer;
}
.add-area:hover {
  background-color: #F5F7FA;
}
.page-component__scroll {
  height: 100%;
}
.box {
  height: 900px;
  background-color: #000000;
  width: 200px;
  color: #fff;
}
</style>
