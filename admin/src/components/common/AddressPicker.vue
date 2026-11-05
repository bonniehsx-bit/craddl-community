<template>
  <!--
    修改点 1: 添加 append-to-body 防止多层弹窗遮罩层级混乱
  -->
  <el-dialog
    title="选择地址"
    :visible.sync="dialogVisible"
    width="40%"
    append-to-body
  >
    <el-cascader
      ref="address"
      v-model="selectedValue"
      :options="list"
      :props="optionProps"
      @change="handleChange"
      clearable
      style="width: 100%;"
      placeholder="请选择所在省/市/区/街道"
    ></el-cascader>
    <span slot="footer" class="dialog-footer">
      <el-button @click="handleCancel">取 消</el-button>
      <el-button type="primary" @click="handleConfirm">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
export default {
  name: 'AddressPicker',
  props: {
    openDialog: {
      type: Boolean,
      default: false
    },
    // 新增：允许父组件直接传数据进来，避免重复请求
    dataSource: {
      type: Array,
      default: () => []
    },
    level: {
      type: Number,
      default: 4
    }
  },
  data() {
    return {
      optionProps: {
        children: 'list',
        label: 'name',
        value: 'code',
        checkStrictly: true // 允许选择任意一级
      },
      selectedValue: [],
      selectedAddressData: null,
      dialogVisible: this.openDialog,
      list: [],
    }
  },
  watch: {
    openDialog(newVal) {
      this.dialogVisible = newVal;
      if (!newVal) {
        this.selectedValue = [];
        this.selectedAddressData = null;
      } else {
        // 打开时检查数据
        this.checkData();
      }
    },
    dialogVisible(newVal) {
      this.$emit('update:openDialog', newVal);
    },
    // 监听父组件传来的数据
    dataSource: {
      handler(val) {
        if (val && val.length > 0) {
          this.list = val;
        }
      },
      immediate: true
    }
  },
  created() {
    this.checkData();
  },
  methods: {
    checkData() {
      // 如果父组件传了数据，直接用
      if (this.dataSource && this.dataSource.length > 0) {
        this.list = this.dataSource;
        return;
      }
      // 否则自己加载
      this.loadAddressData();
    },

    async loadAddressData() {
      let address_data = localStorage.getItem('ADDRESS_DATA');
      if (address_data) {
        try {
          const parsed = JSON.parse(address_data);
          // 修改点 2: 必须判断数组长度大于0，否则视为无效缓存，继续发起请求
          if (Array.isArray(parsed) && parsed.length > 0) {
            this.list = parsed;
            return;
          }
        } catch (e) {
          console.error("缓存解析失败", e);
          localStorage.removeItem('ADDRESS_DATA');
        }
      }

      // 缓存无效或为空，发起请求
      try {
        const res = await this.$request({
          url: '/common/address/address-data'
        });
        if (res.code === 0 && res.data.list && res.data.list.length > 0) {
          this.list = res.data.list;
          localStorage.setItem('ADDRESS_DATA', JSON.stringify(res.data.list));
        } else {
          this.$message.warning("获取地址数据为空");
        }
      } catch (e) {
        console.error("加载地址数据失败", e);
        // this.$message.error("地址数据加载失败");
      }
    },

    handleChange(codes) {
      const codesArr = Array.isArray(codes) ? codes : (codes ? [codes] : []);
      if (!codesArr.length) {
        this.selectedAddressData = null;
        return;
      }

      // 通过 codes 遍历树获取各级名称，不依赖 getCheckedNodes()（单选模式下该 API 返回空数组）
      const labels = [];
      let currentList = this.list;
      for (const code of codesArr) {
        const node = currentList.find(item => String(item.code) === String(code));
        if (!node) break;
        labels.push(node.name);
        currentList = node.list || [];
      }

      this.selectedAddressData = {
        province_code: codesArr[0] || '',
        city_code:     codesArr[1] || '',
        county_code:   codesArr[2] || '',
        town_code:     codesArr[3] || '',
        province_name: labels[0] || '',
        city_name:     labels[1] || '',
        county_name:   labels[2] || '',
        town_name:     labels[3] || '',
      };
    },
    handleConfirm() {
      if (this.selectedAddressData) {
        this.$emit('selected', this.selectedAddressData);
      }
      this.dialogVisible = false;
    },
    handleCancel() {
      this.dialogVisible = false;
    }
  }
}
</script>
