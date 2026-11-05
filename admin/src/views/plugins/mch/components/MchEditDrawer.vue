<template>
  <el-drawer :title="form.id ? '编辑商户' : '添加商户'" :visible.sync="visible" size="65%"
    @closed="onClosed" append-to-body>

    <div style="display: flex; flex-direction: column; height: 100%;">
      <div style="flex: 1; padding: 0 20px; overflow-y: auto;">
        <el-form size="mini" :model="form" ref="form" label-width="140px" :rules="rules" v-loading="loading">
          <el-tabs v-model="activeName">

        <!-- ======================= Tab 1: 基础信息 ======================= -->
        <el-tab-pane label="基础信息" name="basic">
          <el-row :gutter="20">
            <el-col :span="18">
              <!-- 店长选择 -->
              <el-form-item label="店长" prop="user_id">
                <el-select v-model="form.user_id" filterable remote :remote-method="searchUsers"
                  placeholder="请输入昵称/ID搜索" :loading="userSearchLoading" style="width: 100%;" :disabled="!!form.id">
                  <el-option v-for="user in userList" :key="user.id" :label="user.nickname" :value="user.id">
                    <div style="display: flex; align-items: center;">
                      <el-avatar size="small" :src="user.avatar_url" style="margin-right: 10px;"></el-avatar>
                      <span>{{ user.nickname }} (ID: {{ user.id }})</span>
                    </div>
                  </el-option>
                </el-select>
              </el-form-item>

              <el-form-item label="商户名称" prop="mch_name">
                <el-input v-model="form.mch_name" placeholder="请输入店铺名称"></el-input>
              </el-form-item>

              <!-- [核心] 商家类型
                   新架构下 mch 不再代表具体业务，type 仅决定"商家(线上/门店) vs 个人"。
                   原"民宿酒店"选项已下线，存量 type=3 商户保留兼容只读展示，
                   酒店等业务能力请通过商户中心「业务能力开通」入口由对应插件管理。-->
              <el-form-item label="商家类型" prop="type">
                <el-radio-group v-model="form.type" @change="handleTypeChange">
                  <el-radio :label="1">线上商家</el-radio>
                  <el-radio :label="2">门店商家</el-radio>
                  <!-- 兼容已有 type=3 数据：仅当当前商户已经是 type=3 时可见(不可主动选) -->
                  <el-radio :label="3" v-if="form.type === 3" disabled>民宿酒店（已废弃）</el-radio>
                  <el-radio :label="4">个人租户</el-radio>
                </el-radio-group>
                <div v-if="form.type === 3" style="color:#909399;font-size:12px;margin-top:6px;">
                  当前商户为"民宿酒店"类型（历史数据）。该类型已停用，新建商户请选择上方"门店商家"，并到商户中心开通酒店业务。
                </div>
              </el-form-item>

              <el-form-item v-if="form.type !== 4" label="商家分类" prop="cat_id">
                <el-select v-model="form.cat_id" placeholder="请选择商家分类" style="width: 100%;">
                  <el-option v-for="cat in mchCatList" :key="cat.id" :label="cat.cat_name" :value="cat.id">
                  </el-option>
                </el-select>
              </el-form-item>

              <el-form-item v-if="form.type !== 4" label="商家Logo" prop="logo">
                <single-image-selector v-model="form.logo"></single-image-selector>
              </el-form-item>

              <el-form-item label="联系电话(座机)">
                <el-input v-model="form.tel"></el-input>
              </el-form-item>
              <el-form-item label="联系手机">
                <el-input v-model="form.mobile"></el-input>
              </el-form-item>

              <!-- 地址字段：门店/酒店/个人租户都需要省市区+详细地址 -->
              <template v-if="form.type === 2 || form.type === 3 || form.type === 4">
                <el-form-item label="所在地区" prop="address">
                  <el-input :value="formatAddressDisplay" placeholder="请选择省市区" readonly>
                    <el-button slot="append" icon="el-icon-location-outline" @click="showAddressPickerDialog = true">
                      选择
                    </el-button>
                  </el-input>
                </el-form-item>
                <el-form-item label="详细地址" prop="address">
                  <el-input v-model="form.address" placeholder="请输入街道、门牌号等"></el-input>
                </el-form-item>

                <!-- 楼层 / 经纬度仅线下经营主体需要（门店/酒店），个人租户跳过 -->
                <template v-if="form.type !== 4">
                  <el-form-item label="所在楼层" prop="floor">
                    <el-select v-model="form.floor" placeholder="可选择或输入楼层" filterable allow-create style="width: 100%;">
                      <el-option label="F1" value="F1"></el-option>
                      <el-option label="F2" value="F2"></el-option>
                      <el-option label="F3" value="F3"></el-option>
                      <el-option label="F4" value="F4"></el-option>
                      <el-option label="B1" value="B1"></el-option>
                      <el-option label="B2" value="B2"></el-option>
                    </el-select>
                  </el-form-item>
                  <el-form-item label="经纬度">
                    <div style="display: flex;">
                      <el-input v-model="form.longitude" placeholder="经度" style="margin-right: 10px;"></el-input>
                      <el-input v-model="form.latitude" placeholder="纬度" style="margin-right: 10px;"></el-input>
                      <a href="https://lbs.baidu.com/maptool/getpoint" target="_blank">
                        <el-button type="text">拾取坐标</el-button>
                      </a>
                    </div>
                  </el-form-item>
                </template>
              </template>

              <!-- [门店专属] 营业时间 -->
              <el-form-item label="营业时间" v-if="form.type === 2">
                <div style="display: flex; align-items: center;">
                  <el-time-select
                    v-model="openingStart"
                    :picker-options="{ start: '00:00', step: '00:30', end: '23:30' }"
                    placeholder="开始时间"
                    @change="onOpeningHoursChange"
                    style="width: 130px;">
                  </el-time-select>
                  <span style="margin: 0 8px; color: #606266;">至</span>
                  <el-time-select
                    v-model="openingEnd"
                    :picker-options="{ start: '00:00', step: '00:30', end: '23:30', minTime: openingStart }"
                    placeholder="结束时间"
                    @change="onOpeningHoursChange"
                    style="width: 130px;">
                  </el-time-select>
                  <span v-if="form.opening_hours" style="margin-left: 12px; color: #909399; font-size: 12px;">
                    {{ form.opening_hours }}
                  </span>
                </div>
              </el-form-item>

              <!-- 已废弃：酒店专属入住/离店时间。
                   入住/离店等酒店运营字段由 hotel 插件自管，在「酒店插件后台 / 商户中心-酒店开通页」编辑。
                   平台 mch 编辑表单不再渲染（即使渲染了 MchEditForm 也不保存这些字段，是"僵尸 UI"）。 -->

              <el-form-item v-if="form.type !== 4" label="Banner图 (最多6张)">
                <images-selector :limit="6" :list="form.banner_images" v-model="form.banner_images"></images-selector>
              </el-form-item>
              <el-form-item v-if="form.type !== 4" label="商家介绍">
                <el-input type="textarea" v-model="form.description" :rows="4"></el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- ======================= Tab 2: 分润与业务 ======================= -->
        <el-tab-pane label="分润与业务" name="profit">
          <el-row>
            <el-col :span="18">
              <!-- 通用分润 -->
              <el-form-item label="通用让利比例">
                <el-input type="number" v-model="form.concession_rate" min="0" max="100">
                  <template slot="append">%</template>
                </el-input>
                <div class="form-tip">平台抽佣比例，例如填10，则订单金额的10%归平台。</div>
              </el-form-item>

              

              <!-- 已废弃：酒店结算模式 / 星级。原因同上，由 hotel 插件自管。 -->

              <!-- [门店专属] 配送与首单 -->
              <div v-if="form.type === 2">
                <el-divider content-position="left">首单优惠设置</el-divider>
                <el-form-item label="开启首单优惠">
                  <el-switch v-model="form.is_first_order_discount_enabled" :active-value="1"
                    :inactive-value="0"></el-switch>
                </el-form-item>

                <template v-if="form.is_first_order_discount_enabled === 1">
                  <el-form-item label="首单优惠金额">
                    <el-input type="number" v-model="form.first_order_discount_amount" min="0">
                      <template slot="append">元</template>
                    </el-input>
                  </el-form-item>
                  <el-form-item label="总优惠单数">
                    <el-input type="number" v-model.number="form.first_order_discount_total_stock" min="0"
                      placeholder="优惠总名额">
                      <template slot="append">单</template>
                    </el-input>
                  </el-form-item>
                  <el-form-item label="优惠有效期">
                    <el-date-picker v-model="form.discount_date_range" type="datetimerange"
                      value-format="yyyy-MM-dd HH:mm:ss" range-separator="至" start-placeholder="开始日期"
                      end-placeholder="结束日期" @change="onDiscountDateChange" style="width: 100%;">
                    </el-date-picker>
                    <div class="form-tip">不设置时间则表示永久有效</div>
                  </el-form-item>
                </template>

                <el-form-item label="配送方式">
                  <el-checkbox-group v-model="form.delivery_options">
                    <el-checkbox label="express">快递</el-checkbox>
                    <el-checkbox label="pickup">到店自提</el-checkbox>
                    <el-checkbox label="city">同城配送</el-checkbox>
                  </el-checkbox-group>
                </el-form-item>

                <!-- 自提设置 -->
                <div v-if="form.delivery_options && form.delivery_options.includes('pickup')" class="sub-setting-box">
                  <el-divider content-position="left">自提设置</el-divider>
                  <el-form-item label="自提优惠折扣">
                    <el-input type="number" v-model="form.pickup_discount_rate" min="0" max="100">
                      <template slot="append">%</template>
                    </el-input>
                  </el-form-item>
                  <el-form-item label="自提优惠折扣上限金额">
                    <el-input type="number" v-model="form.pickup_discount_max_amount" min="0">
                      <template slot="append">元</template>
                    </el-input>
                  </el-form-item>
                </div>

                <!-- 同城配送设置 -->
                <div v-if="form.delivery_options && form.delivery_options.includes('city')" class="sub-setting-box">
                  <el-divider content-position="left">同城配送设置</el-divider>
                  <template v-if="form.city_delivery_config">
                    <el-form-item label="开启同城配送">
                      <el-switch v-model="form.city_delivery_config.enable" active-value="1"
                        inactive-value="0"></el-switch>
                    </el-form-item>
                    <el-form-item label="最大配送半径">
                      <el-input type="number" v-model="form.city_delivery_config.delivery_radius" min="0">
                        <template slot="append">公里</template>
                      </el-input>
                    </el-form-item>
                    <el-form-item label="最低消费金额">
                      <el-input type="number" v-model="form.city_delivery_config.order_amount_limit.min_order_amount"
                        min="0" placeholder="0为不限制">
                        <template slot="append">元</template>
                      </el-input>
                    </el-form-item>

                    <div style="background: #fff; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                      <el-form-item label="起步距离" label-width="120px">
                        <el-input type="number" v-model="form.city_delivery_config.charge_rules.start_distance" min="0">
                          <template slot="append">公里</template>
                        </el-input>
                      </el-form-item>
                      <el-form-item label="起步价" label-width="120px">
                        <el-input type="number" v-model="form.city_delivery_config.charge_rules.start_fee" min="0">
                          <template slot="append">元</template>
                        </el-input>
                      </el-form-item>
                      <el-form-item label="超出后每公里加价" label-width="120px">
                        <el-input type="number" v-model="form.city_delivery_config.charge_rules.extra_fee_per_km"
                          min="0">
                          <template slot="append">元</template>
                        </el-input>
                      </el-form-item>
                    </div>

                    <el-form-item label="可预约天数">
                      <el-input type="number" v-model.number="form.city_delivery_config.time_config.schedule_limit_days"
                        min="1">
                        <template slot="append">天</template>
                      </el-input>
                    </el-form-item>
                    <el-form-item label="时间段间隔">
                      <el-select v-model="form.city_delivery_config.time_config.time_slot_interval">
                        <el-option label="30分钟" value="30"></el-option>
                        <el-option label="60分钟" value="60"></el-option>
                        <el-option label="90分钟" value="90"></el-option>
                      </el-select>
                    </el-form-item>

                    <el-form-item label="营业时间段">
                      <div v-for="(hour, index) in form.city_delivery_config.time_config.business_hours" :key="index"
                        style="margin-bottom: 10px; display:flex; align-items:center;">
                        <el-time-picker is-range v-model="hour.range" range-separator="至" start-placeholder="开始时间"
                          end-placeholder="结束时间" value-format="HH:mm" @change="timeRangeChange(index, hour)"
                          size="small">
                        </el-time-picker>
                        <el-button type="danger" icon="el-icon-delete" circle size="mini"
                          @click="removeBusinessHour(index)" style="margin-left: 10px;"></el-button>
                      </div>
                      <el-button type="text" @click="addBusinessHour" icon="el-icon-plus">添加营业时间段</el-button>
                    </el-form-item>
                  </template>
                </div>
              </div>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- ======================= Tab 3: 支付配置（个人租户不需要独立支付/分账） ======================= -->
        <el-tab-pane label="支付与分账" name="split" v-if="form.type !== 4">
          <el-row>
            <el-col :span="18">
              <el-form-item label="快付应用ID">
                <el-input v-model="form.application_id" placeholder="请填写快付应用ID（application_id）"></el-input>
                <div style="color: #ff4455">非测试阶段请勿擅自修改</div>
              </el-form-item>
              <el-form-item label="快付用户ID">
                <el-input v-model="form.other_platform_user_id"
                  placeholder="请填写快付支付商户（other_platform_user_id）"></el-input>
                <div style="color: #ff4455">非测试阶段请勿擅自修改</div>
              </el-form-item>

              <el-divider content-position="left">微信智慧商圈配置</el-divider>
              <el-form-item label="微信支付商户号">
                <el-input v-model="form.wechat_mchid" placeholder="选填，微信支付分配的商户号，多商户独立收款时使用"></el-input>
                <div class="form-tip">若商圈积分由统一总商户号收款回调，此项可留空</div>
              </el-form-item>
              <el-form-item label="微信商圈门店编号">
                <el-input v-model="form.wechat_shop_number" placeholder="必填，微信智慧商圈系统中的门店唯一编号(shop_number)"></el-input>
                <div class="form-tip">用于在产生跨端支付时，准确将商圈交易数据匹配归属到本商户</div>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- ======================= Tab 4: 资质信息 ======================= -->
        <el-tab-pane label="资质信息" name="qualification">
          <el-row>
            <el-col :span="18">
              <el-form-item :label="form.type === 4 ? '本人姓名' : '法人姓名'">
                <el-input v-model="form.legal_person_name"></el-input>
              </el-form-item>
              <el-form-item v-if="form.type !== 4" label="营业执照">
                <single-image-selector v-model="form.business_license"></single-image-selector>
              </el-form-item>
              <el-form-item :label="form.type === 4 ? '身份证人像面(本人)' : '身份证(人像面)'">
                <single-image-selector v-model="form.id_card_front"></single-image-selector>
              </el-form-item>
              <el-form-item :label="form.type === 4 ? '身份证国徽面(本人)' : '身份证(国徽面)'">
                <single-image-selector v-model="form.id_card_back"></single-image-selector>
              </el-form-item>

              <!-- 已废弃：酒店专属资质（特种行业证 / 消防证 / 政策图）。
                   由 hotel 插件自管，MchEditForm 也不存这些字段。 -->

              <el-form-item label="其他证件">
                <images-selector :limit="5" :list="form.other_certs" v-model="form.other_certs"></images-selector>
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>
        </el-tabs>
        </el-form>
      </div>
      <div style="padding: 20px; border-top: 1px solid #EBEEF5; text-align: right;">
        <el-button @click="close" size="small">取 消</el-button>
        <el-button type="primary" @click="submit" :loading="submitLoading" size="small">保 存</el-button>
      </div>
    </div>

    <!-- 地址选择器组件 -->
    <address-picker :openDialog.sync="showAddressPickerDialog" @selected="onAddressSelected" :level="4">
    </address-picker>
  </el-drawer>
</template>

<script>
import AddressPicker from '@/components/common/AddressPicker'
// 假设已全局注册 SingleImageSelector 和 ImagesSelector

export default {
  name: 'MchEditDrawer',
  components: { AddressPicker },
  data() {
    return {
      visible: false,
      loading: false,
      submitLoading: false,
      activeName: 'basic',
      showAddressPickerDialog: false,
      mchCatList: [],
      userList: [],
      userSearchLoading: false,
      addressList: [],
      // 营业时间选择器（拆分自 form.opening_hours，不提交此字段）
      openingStart: '',
      openingEnd: '',

      // 表单数据
      form: this.getCleanForm(),

      // 校验规则
      rules: {
        type: [{ required: true, message: '请选择商家类型', trigger: 'change' }],
        user_id: [{ required: true, message: '请搜索并选择一个用户作为店长', trigger: 'change' }],
        mch_name: [{ required: true, message: '请输入商户名称', trigger: 'blur' }],
      }
    }
  },
  computed: {
    formatAddressDisplay() {
      if (!this.form) return ''
      const parts = [this.form.province, this.form.city, this.form.county, this.form.town]
      return parts.filter(Boolean).join(' ')
    }
  },
  created() {
    this.getMchCatList();
    this.loadAddressData();
  },
  methods: {
    /**
     * 打开弹窗
     */
    open(row) {
      this.visible = true;
      this.activeName = 'basic';
      if (row && row.id) {
        this.fetchDetail(row.id);
      } else {
        this.form = this.getCleanForm();
        this.userList = [];
        this.openingStart = '';
        this.openingEnd = '';
      }
    },

    /**
     * 关闭弹窗
     */
    close() {
      this.visible = false;
    },
    onClosed() {
      if (this.$refs.form) this.$refs.form.resetFields();
      this.activeName = 'basic';
      this.openingStart = '';
      this.openingEnd = '';
    },

    /**
     * 获取详情（核心：处理数据回显）
     */
    async fetchDetail(id) {
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/edit',
          method: 'get',
          data: { id: id }
        });

        if (res.code === 0) {
          let detail = res.data.detail;
          detail = this.findAddressNames(detail); // 补全地址名称

          // JSON字段转数组
          this.ensureArray(detail, 'banner_images');
          this.ensureArray(detail, 'other_certs');
          this.ensureArray(detail, 'delivery_options');

          // 已废弃：酒店字段合并。
          // 入住时间/星级/特种行业证等酒店运营字段交由 hotel 插件后台维护，
          // mch 编辑表单不再处理。残留的 form.* 默认值仅作兼容初始化用，
          // 用户填了也不会保存（MchEditForm 不接收这些字段）。

          // 时间段处理
          if (detail.city_delivery_config && detail.city_delivery_config.time_config.business_hours) {
            detail.city_delivery_config.time_config.business_hours.forEach(item => {
              this.$set(item, 'range', [item.start, item.end]);
            });
          }

          this.form = { ...this.getCleanForm(), ...detail };

          // 回显营业时间选择器
          this.parseOpeningHours(detail.opening_hours);

          // 回显店长
          if (res.data.detail.user_info) {
            this.userList = [res.data.detail.user_info];
          }
        } else {
          this.$message.error(res.msg);
          this.close();
        }
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },

    // 辅助：确保字段是数组
    ensureArray(obj, key) {
      if (typeof obj[key] === 'string') {
        try {
          obj[key] = JSON.parse(obj[key]);
        } catch (e) {
          obj[key] = [];
        }
      }
      if (!Array.isArray(obj[key])) obj[key] = [];
    },

    /**
     * 提交保存
     */
    async submit() {
      this.$refs.form.validate(async (valid) => {
        if (valid) {
          this.submitLoading = true;
          try {
            // 提交前处理：时间段
            if (this.form.city_delivery_config && this.form.city_delivery_config.time_config.business_hours) {
              this.form.city_delivery_config.time_config.business_hours.forEach(item => {
                if (item.range && item.range.length === 2) {
                  item.start = item.range[0];
                  item.end = item.range[1];
                }
              });
            }

            const res = await this.$request({
              url: '/plugin/mch/mall/mch/edit',
              method: 'post',
              data: { form: this.form }
            });

            if (res.code === 0) {
              this.$message.success('保存成功');
              this.$emit('success');
              this.close();
            } else {
              this.$message.error(res.msg);
            }
          } catch (e) {
            this.submitLoading = false;
          } finally {
            this.submitLoading = false;
          }
        }
      });
    },

    // 切换类型清理数据
    handleTypeChange(val) {
      if (val === 3) { // 酒店
        this.form.delivery_options = [];
      }
      if (val === 4) { // 个人租户
        this.form.cat_id = null;
        this.form.logo = '';
        this.form.banner_images = [];
        this.form.description = '';
        this.form.business_license = '';
        this.form.opening_hours = '';
        this.form.delivery_options = [];
        this.openingStart = '';
        this.openingEnd = '';
      }
    },

    getCleanForm() {
      return {
        id: null, user_id: null, mch_name: '', logo: '', cat_id: null, type: 2,

        // 已废弃：酒店专属字段（settlement_model / star_rating / check_in_time / ...）
        // 由 hotel 插件自管，平台 mch 编辑表单不再渲染也不再保存。

        // 通用
        tel: '', mobile: '', address: '', floor: '',
        latitude: '', longitude: '', opening_hours: '',
        banner_images: [], other_certs: [],
        description: '', concession_rate: '10.00',

        // 资质
        business_license: '', legal_person_name: '',
        id_card_front: '', id_card_back: '',

        province: '', city: '', county: '', town: '',
        province_code: '', city_code: '', county_code: '', town_code: '',

        // 支付与商圈
        application_id: '', other_platform_user_id: '',
        wechat_mchid: '', wechat_shop_number: '',

        // 门店配送
        delivery_options: [],
        city_delivery_config: {
          enable: 0,
          delivery_radius: 5,
          charge_rules: { start_distance: 3, start_fee: 5, extra_fee_per_km: 2 },
          time_config: {
            schedule_limit_days: 3,
            time_slot_interval: 30,
            business_hours: [{ start: '09:00', end: '21:00', range: ['09:00', '21:00'] }]
          },
          order_amount_limit: { min_order_amount: 0 }
        },

        // 首单优惠
        is_first_order_discount_enabled: 0,
        first_order_discount_amount: '0.00',
        first_order_discount_total_stock: 0,
        first_order_discount_start_time: null,
        first_order_discount_end_time: null,
        discount_date_range: []
      }
    },

    // --- 远程搜索、地址选择、时间处理等辅助方法 (保持不变) ---
    async searchUsers(keyword) {
      if (!keyword) {
        this.userList = [];
        return;
      }
      this.userSearchLoading = true;
      try {
        const res = await this.$request({
          url: '/mall/user/user-search-list',
          data: { keyword, limit: 20 }
        });
        if (res.code === 0) {
          this.userList = res.data.list;
        }
      } finally {
        this.userSearchLoading = false;
      }
    },
    onDiscountDateChange(val) {
      if (val && val.length === 2) {
        this.form.first_order_discount_start_time = val[0];
        this.form.first_order_discount_end_time = val[1];
      } else {
        this.form.first_order_discount_start_time = null;
        this.form.first_order_discount_end_time = null;
      }
    },
    async getMchCatList() {
      const res = await this.$request({ url: '/plugin/mch/mall/mch-cat/index' });
      if (res.code === 0) this.mchCatList = res.data.list;
    },
    async loadAddressData() {
      let address_data = localStorage.getItem('ADDRESS_DATA');
      if (address_data) {
        this.addressList = JSON.parse(address_data);
        return;
      }
      const res = await this.$request({ url: '/common/address/address-data' });
      if (res.code === 0) {
        this.addressList = res.data.list;
        localStorage.setItem('ADDRESS_DATA', JSON.stringify(res.data.list));
      }
    },
    findAddressNames(form) {
      if (!this.addressList.length || !form.province_code) return form;
      const province = this.addressList.find(p => p.code == form.province_code);
      if (!province) return form;
      form.province = province.name;
      if (form.city_code && province.list) {
        const city = province.list.find(c => c.code == form.city_code);
        if (!city) return form;
        form.city = city.name;
        if (form.county_code && city.list) {
          const county = city.list.find(co => co.code == form.county_code);
          if (!county) return form;
          form.county = county.name;
          if (form.town_code && county.list) {
            const town = county.list.find(t => t.code == form.town_code);
            if (town) form.town = town.name;
          }
        }
      }
      return form;
    },
    onAddressSelected(e) {
      if (e) {
        this.form.province_code = e.province_code;
        this.form.city_code = e.city_code;
        this.form.county_code = e.county_code;
        this.form.town_code = e.town_code;
        this.form.province = e.province_name;
        this.form.city = e.city_name;
        this.form.county = e.county_name;
        this.form.town = e.town_name;
      }
    },
    // 营业时间：将两个选择器的值拼成 "HH:mm-HH:mm" 写入 form.opening_hours
    onOpeningHoursChange() {
      if (this.openingStart && this.openingEnd) {
        this.form.opening_hours = `${this.openingStart}-${this.openingEnd}`;
      } else {
        this.form.opening_hours = '';
      }
    },
    // 营业时间：回显时将 "HH:mm-HH:mm" 拆回两个选择器
    parseOpeningHours(val) {
      if (val && val.includes('-')) {
        const parts = val.split('-');
        if (parts.length === 2 && parts[0].includes(':') && parts[1].includes(':')) {
          this.openingStart = parts[0].trim();
          this.openingEnd = parts[1].trim();
          return;
        }
      }
      this.openingStart = '';
      this.openingEnd = '';
    },
    timeRangeChange(index, hour) {
      const range = hour.range;
      if (range && range.length === 2) {
        hour.start = range[0];
        hour.end = range[1];
      }
    },
    addBusinessHour() {
      if (!this.form.city_delivery_config.time_config.business_hours) {
        this.$set(this.form.city_delivery_config.time_config, 'business_hours', []);
      }
      this.form.city_delivery_config.time_config.business_hours.push({ start: '', end: '', range: [] });
    },
    removeBusinessHour(index) {
      this.form.city_delivery_config.time_config.business_hours.splice(index, 1);
    }
  }
}
</script>

<style scoped>
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
  margin-top: 5px;
}

.sub-setting-box {
  background-color: #f5f7fa;
  padding: 15px;
  border-radius: 4px;
  margin-top: 10px;
  margin-bottom: 20px;
}
</style>
