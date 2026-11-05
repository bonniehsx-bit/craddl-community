<template>
  <div class="app-container">
    <el-card shadow="always" :body-style="{ padding: '20px' }">
      <div slot="header" class="clearfix">
        <span>收银台</span>
        <div style="float: right;" v-if="userInfo&&userInfo.is_shop==1">
          <router-link tag="a" target="_blank" to="/customer">
            <el-button type="text">连接顾客显示屏</el-button>
          </router-link>
        </div>
      </div>
      <div class="flex-row">
        <div style="width:60%;padding-right: 20px;">
          <el-form ref="scanForm" label-width="80px" :inline="false" size="normal" @submit.native.prevent>
            <el-form-item label="商品条码">
              <el-input :autofocus="true" ref="code_input" v-model="scanForm.code" placeholder="请扫码商品条码" size="normal"
                clearable @change="scanEvent" @blur="onBlur"></el-input>
            </el-form-item>
          </el-form>

          <el-form ref="form" :model="form" label-width="80px">
            <el-form-item label="商品清单" size="normal">
              <el-empty v-if="goods_list.length == 0"></el-empty>
              <el-table v-if="goods_list.length > 0" border :data="goods_list" stripe style="width:100%">
                <el-table-column label="商品信息" width="300">
                  <template slot-scope='scope'>
                    <div>
                      <el-image :src="scope.row.cover_pic" style="width:200px;height: 200px;" fit="cover">
                      </el-image>
                    </div>
                    <div>{{ scope.row.name }}</div>
                  </template>
                </el-table-column>
                <el-table-column prop="no" label="商品条码">
                </el-table-column>
                <el-table-column prop="price" label="商品价格">
                </el-table-column>
                <el-table-column label="数量">
                  <template slot-scope='scope'>
                    <el-input-number @change="computePrice" v-model="scope.row.num" :min="1" label="商品数量">
                    </el-input-number>
                  </template>
                </el-table-column>
                <el-table-column label="当前库存" prop="stock">
                </el-table-column>
                <el-table-column label="操作">
                  <template slot-scope='scope'>
                    <el-button type="danger" size="default" @click="deleteItem(scope.$index)">
                      删除
                    </el-button>
                  </template>
                </el-table-column>
              </el-table>
            </el-form-item>
          </el-form>
        </div>
        <div style="width:40%;border-left: solid 1px #f0f0f0;padding-left: 30px;">
          <el-form ref="form" :model="form" label-width="80px">
            <el-form-item label="商品数量">
              <div style="font-size: 40px;">
                <span style="color:#ff4455">{{ form.num }}</span><span style="font-size:20px">/件</span>
              </div>
            </el-form-item>

            <el-form-item label="订单总额">
              <div style="font-size: 40px;color:#ff4455"><span style="font-size:20px">￥</span>
                <span>{{ form.total_price }}</span>
              </div>
            </el-form-item>
            <el-form-item label="订单ID" v-if="order">
              {{order.id}}
            </el-form-item>
            <el-form-item label="订单号" v-if="order">
              {{order.order_no}}
            </el-form-item>
            <el-form-item label="支付状态" v-if="order">
              <el-button type="primary" v-loading="isPaying" v-if="isPaying">等待支付</el-button>
              <el-button type="success" v-if="!isPaying">已支付</el-button>
            </el-form-item>
            <el-form-item label="提交订单" v-if="!order">
              <el-button type="primary" @click="orderSubmit">立即提交</el-button>
            </el-form-item>

            <el-form-item label="重新下单">
              <el-button type="danger" @click="resetOrder">重新下单</el-button>
            </el-form-item>
          </el-form>
        </div>
      </div>
    </el-card>
  </div>
</template>
<script>
  import {
    mapGetters
  } from 'vuex'
import mixin from "@/mixins"
  export default {
    name: 'cashier',
    mixins:[mixin],
    components: {

    },
    data() {
      return {
        pushData: {
          data: {
            msg_type: '',
            data: null
          }
        },
        isPaying: false,
        dialogVisible: false,
        showUserPicker: false,
        no: '',
        goods_list: [],
        is_loading: false,
        order: null,
        scanForm: {
          code: '',
        },
        pay_type: [],
        is_pay: 0,
        form: {
          order_id: '',
          num: 0,
          total_price: 0.00,
          goods_list: [],
          order_no: '',
          pay_price: '',
          goods_list: [],
          qrcode_url: '',
          created_time: '',
          step: 0, // 0 待下单 1正在下单  2待支付 3 已支付
        }
      }
    },
    created() {
      this.onBlur();
    },
    methods: {
      // 在元素失去焦点时触发，获取焦点
      checkOrderPay() {
        if (this.order && this.is_pay == 1) {
          return;
        }
        if (!this.order) {
          return;
        }
        setTimeout(() => {
          this.$request({
            url: "/plugin/shop/api/shop/order-detail",
            data: {
              order_id: this.order.id
            }
          }).then(res => {
            if (res.data.order.is_pay == 1) {
              this.is_pay = 1;
              this.isPaying = false;
              this.form.step = 3;
              this.push();
            } else {
              this.checkOrderPay();
            }
          })
        }, 3000)
      },
      onBlur() {
        this.$nextTick(() => {
          this.$refs.code_input.focus()
        })
      },
      scanEvent(e) {
        if (!this.scanForm.code) {
          return;
        }
        this.no = this.scanForm.code;
        this.getGoods();
        this.scanForm.code = '';
      },
      // 监听input的输入值, 这里可以拿到二维码信息
      onInput(e) {

      },
      numChange(e) {

      },
      deleteItem(index) {
        this.goods_list.splice(index, 1);
        this.computePrice();
      },
      closeDialog() {
        this.showUserPicker = false;
      },
      resetOrder() {
        this.form = {
          num: 0,
          total_price: 0.00,
          goods_list: [],
          order_no: '',
          pay_price: '',
        }
        this.order = null;
        this.goods_list = [];
        this.push()
      },
      push() {
        this.form.goods_list = this.goods_list;
        this.pushData.data.data = this.form;
        this.$request({
          url: "/plugin/shop/api/shop/ordering",
          data: {
            form: this.pushData
          },
          method: "post"
        }).then(res => {
          console.log(res);
        })
      },
      computePrice() {
        this.isPaying = false;
        this.order = null;
        this.is_pay = 0;
        this.form.total_price = 0;
        this.form.num = 0;
        this.goods_list.forEach(item => {
          this.form.num += item.num;
          this.form.total_price += (item.num * item.price)
        })
        this.form.step = 1;
        this.push();
      },
      orderSubmit() {
        if (this.is_loading) {
          return;
        }
        if (this.goods_list.length == 0) {
          this.$message.warning('请扫描商品条码');
          return;
        }
        this.is_loading = true;
        this.form.goods_list = this.goods_list;
        this.$request({ //获取一个商品应该清理一个商品的库存
          url: "/plugin/shop/api/shop/order-submit",
          data: this.form,
          method: 'post'
        }).then(res => {
          this.is_loading = false;
          if (res.code == 0) {
            this.order = res.data.order;
            this.form.order_no = this.order.order_no;
            this.form.order_id = this.order.id;
            this.form.step = 2;
            this.form.qrcode_url = this.order.qrcode_url;
            this.form.created_time = this.order.updated_time;
            this.isPaying = true;
            this.$message.success(res.msg);
            this.$forceUpdate();
            this.push();
            this.checkOrderPay();
          }
        }).catch(e => {
          this.is_loading = false;
        })
      },
      getSetting() {
        this.$request({
          url: "/plugin/shop/api/shop/setting",
        }).then(res => {
          if (res.code == 0) {
            if (res.data.setting) {
              let form = res.data.setting;
              if (!form.pay_type || form.pay_type.length == 0) {
                this.$message.error('未设置收银台支付方式');
              }
              this.pay_type = form.pay_type;
            }
          }
        })
      },
      getGoods() {
        this.$request({ //获取一个商品应该清理一个商品的库存
          url: "/plugin/shop/api/shop/goods-by-no",
          data: {
            no: this.no, //这里拿到的是商品的编码
          },
        }).then(e => {
          if (e.code == 0) { //获取商品信息
            let goods = e.data.goods;
            let flag = false;
            this.goods_list.forEach(item => {
              if (item.id == goods.id) {
                if (item.stock > item.num) {
                  flag = true;
                  item.num++;
                } else {
                  flag = true;
                  this.$message.error('商品：' + item.name + '，库存不足!');
                  return false;
                }
              }
            })
            if (!flag) {
              this.goods_list.unshift({
                id: goods.id,
                cover_pic: goods.cover_pic,
                name: goods.name,
                stock: goods.stock,
                no: goods.no,
                price: goods.price,
                num: 1,
              });
            }

            this.computePrice();
          }
        })

      }
    },
  }
</script>
<style scoped>

</style>
