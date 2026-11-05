<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="loading">
      <div slot="header" class="clearfix">
        <span>商家插件全局设置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save" :loading="submitLoading">保存
        </el-button>
      </div>

      <!-- [健壮性] 确保在form加载完成前，表单不渲染，避免报错 -->
      <el-form v-if="form" :model="form" ref="formRef" label-width="200px" size="small">
        <el-tabs v-model="activeName">
          <el-tab-pane label="入驻与审核" name="entry">
            <div class="setting-section">
              <div class="section-title">基础配置</div>
              <el-form-item label="开启商户入驻">
                <el-switch v-model="form.is_mch_open" active-value="1" inactive-value="0"></el-switch>
              </el-form-item>
              <el-form-item label="允许入驻类型">
                <el-checkbox-group v-model="form.allowed_mch_types" class="options-row">
                  <el-checkbox :label="1">线上商家</el-checkbox>
                  <el-checkbox :label="2">门店商家</el-checkbox>
                  <el-checkbox :label="4">个人租户</el-checkbox>
                </el-checkbox-group>
                <div class="field-tip">勾选后，入驻页将展示对应类型供商家选择</div>
              </el-form-item>
              <el-form-item label="审核方式">
                <el-radio-group v-model="form.review_type" >
                  <el-radio label="1">后台人工审核</el-radio>
                  <el-radio label="0">无需审核，自动通过</el-radio>
                </el-radio-group>
              </el-form-item>
            </div>

            <div class="setting-section">
              <div class="section-title">入驻说明</div>
              <el-form-item label="入驻说明标题">
                <el-input v-model="form.instructions_title" placeholder="例如：入驻说明" style="width: 400px;"></el-input>
              </el-form-item>
              <el-form-item label="入驻说明内容">
                <el-input type="textarea" v-model="form.instructions_content" :rows="4"
                          placeholder="请输入入驻的简单说明" style="width: 600px;"
                ></el-input>
              </el-form-item>
              <el-form-item label="入驻协议标题">
                <el-input v-model="form.entry_agreement_title" placeholder="例如：门店入驻协议" style="width: 400px;"></el-input>
              </el-form-item>
              <el-form-item label="入驻协议内容">
                <rich-text v-model="form.entry_agreement_content"></rich-text>
              </el-form-item>
            </div>

          </el-tab-pane>

          <el-tab-pane label="入驻字段" name="apply-fields">
            <div class="setting-section">
              <div class="section-title">统一经营地址</div>

              <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px;">
                <div slot="title" style="font-weight: 600;">什么时候用这个功能</div>
                <div style="font-size: 12px; line-height: 1.9; color: #606266;">
                  适用于<b>所有商户都在同一个地址</b>的商城 —— 商场、写字楼、商业综合体、产业园。
                  商户入驻时不用再一级级选省市区镇、填详细地址、在地图上戳位置,这些由你在这里配一次,所有新商户共用。
                  <br>
                  如果你的商户分散在不同地方(比如同城的连锁店、跨区域招商),<b>不要开这个开关</b>,让商户自己填。
                </div>
              </el-alert>

              <el-form-item label="启用">
                <el-switch v-model="form.use_default_address" :active-value="1" :inactive-value="0"
                           @change="onUseDefaultAddressChange" />
                <div class="field-tip">
                  开启后,入驻表单里的<b>所在城市、街道、详细地址、地图位置</b>四项会直接消失,
                  商户提交时自动写入下面配置的地址。
                </div>
              </el-form-item>

              <el-alert v-if="form.use_default_address == 1" type="warning" :closable="false"
                        style="margin: 0 0 16px 100px; width: 620px;">
                <div style="font-size: 12px; line-height: 1.9;">
                  <b>配置步骤:</b>
                  ① 选到区县(只到市级也行);
                  ② 填详细地址,建议写到楼栋,例如「芸众大厦 A 座」;
                  ③ 经纬度可留空,但<b>开了同城配送就必须填</b> —— 配送距离按它算。
                  <br>
                  <b>生效范围:</b>只对<b>之后</b>入驻的商户生效。已入驻的不会被改写;
                  后台编辑某个商户时,地址照样能单独改(标楼层、铺号就用这个)。
                  <br>
                  <b>关掉之后</b>四个字段立刻回到入驻表单,商户重新自己填,已存的地址不受影响。
                </div>
              </el-alert>

              <template v-if="form.use_default_address == 1">
                <el-form-item label="所在地区" required>
                  <el-cascader
                    v-model="defaultRegion"
                    :options="addressList"
                    :props="{ value: 'code', label: 'name', children: 'list', checkStrictly: true }"
                    clearable filterable style="width: 400px;"
                    @change="onDefaultRegionChange" />
                  <div class="field-tip">省 / 市 / 区县,可只选到市级</div>
                </el-form-item>
                <el-form-item label="详细地址" required>
                  <el-input v-model="form.default_address" placeholder="例如:芸众大厦 A 座" style="width: 400px;" />
                  <div class="field-tip">
                    只写到楼栋即可,不用带省市区(那部分由上面的地区决定)。
                    具体楼层、铺号留给后台编辑单个商户时补。
                  </div>
                </el-form-item>
                <el-form-item label="地图位置">
                  <el-input v-model="form.default_location_address" placeholder="地图上显示的位置名称,例如:广东省佛山市南海区芸众大厦" style="width: 400px;" />
                  <div style="margin-top: 8px;">
                    <el-input v-model="form.default_longitude" placeholder="经度,例如 113.123456" style="width: 195px;" />
                    <el-input v-model="form.default_latitude" placeholder="纬度,例如 23.023456" style="width: 195px; margin-left: 10px;" />
                  </div>
                  <div class="field-tip">
                    经纬度可在腾讯位置服务「坐标拾取器」搜索楼栋名后复制(注意顺序:先经度后纬度)。
                  </div>
                  <div class="field-tip">
                    留空不影响入驻,但<b>这一栏空着,整个商城的商户就都没有坐标</b>(以前是个别商户漏填,现在是全体),
                    会同时影响两处:
                  </div>
                  <div class="field-tip" style="padding-left: 12px;">
                    · <b>同城配送</b> —— 配送范围和运费按商户坐标算,没坐标算不出距离;<br>
                    · <b>附近商家</b> —— 距离排序会把无坐标的商户排到最后甚至不展示。
                  </div>
                  <div class="field-tip">同一栋楼的商户共用一个坐标是正常的,不必担心重复。</div>
                </el-form-item>
              </template>
            </div>

            <div class="setting-section">
              <div class="section-title">入驻表单字段</div>
              <el-form-item label="主体类型">
                <el-radio-group v-model="fieldType" size="small" @change="loadFieldConfig">
                  <el-radio-button :label="1">线上商家</el-radio-button>
                  <el-radio-button :label="2">门店商家</el-radio-button>
                  <el-radio-button :label="4">个人租户</el-radio-button>
                </el-radio-group>
                <div class="field-tip">营业执照等证照请到「证照库」菜单管理;核心身份字段(姓名/手机/身份证等)锁定不可关闭</div>
              </el-form-item>
              <el-form-item label="字段开关">
                <el-table :data="fieldList" v-loading="fieldLoading" border size="small" style="width: 620px;">
                  <el-table-column prop="label" label="字段" min-width="180">
                    <template slot-scope="{ row }">
                      {{ row.label }}
                      <el-tag v-if="row.core" size="mini" type="info" effect="plain">必备</el-tag>
                      <el-tag v-if="row.managed_by_default_address" size="mini" type="warning" effect="plain">
                        统一地址接管
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column label="启用" width="110" align="center">
                    <template slot-scope="{ row }">
                      <el-switch v-model="row.is_enabled" :active-value="1" :inactive-value="0" :disabled="!!row.core" />
                    </template>
                  </el-table-column>
                  <el-table-column label="必填" width="110" align="center">
                    <template slot-scope="{ row }">
                      <el-switch v-model="row.is_required" :active-value="1" :inactive-value="0"
                                 :disabled="!!row.core || !row.is_enabled" />
                    </template>
                  </el-table-column>
                </el-table>
              </el-form-item>
              <el-form-item label=" ">
                <el-button type="primary" size="small" :loading="fieldSaving" @click="saveFieldConfig">保存字段配置</el-button>
              </el-form-item>
            </div>
          </el-tab-pane>

          <el-tab-pane label="商家推荐奖" name="recruit">
            <div class="setting-section">
              <div class="section-title">商家推荐奖</div>
              <el-form-item label="开启商家推荐奖">
                <el-switch v-model="form.recruit_enable" :active-value="1" :inactive-value="0"></el-switch>
                <div class="field-tip">
                  总开关。<b>关闭后</b>：商品编辑页不再显示「商家推荐奖」配置 Tab，新订单也不再产生商家推荐奖（已产生的待结算记录仍按原规则结算/作废，不影响已赚的钱）。
                </div>
              </el-form-item>
              <template v-if="form.recruit_enable == 1">
                <el-form-item label="全局分红比例">
                  <el-input type="number" v-model="form.recruit_rate" min="0" max="100" style="width: 200px;">
                    <template slot="append">%</template>
                  </el-input>
                  <div class="field-tip">
                    商家购买商品时，其直属上级（parent_id，且上级本身也是商家）按此比例获得商家推荐奖。<br />
                    仅对「商家推荐奖」选择「使用全局比例」的商品生效；选「单独设置比例」的商品用其自身比例。填 0 关闭全局。
                  </div>
                </el-form-item>
              </template>
            </div>
          </el-tab-pane>

          <el-tab-pane label="货款结算" name="withdraw">
            <div class="setting-section">
              <div class="section-title">结算流向</div>
              <el-form-item label="货款结算至">
                <el-select v-model="form.mch_settle_target" style="width: 260px;">
                  <el-option v-for="item in settleTargetOptions" :key="item.key" :value="item.key"
                             :label="item.plugin_label ? `${item.label}（${item.plugin_label}）` : item.label"></el-option>
                </el-select>
                <div class="field-tip">
                  商家的订单货款（含运费）统一结算到店长本人的这一资产账户；商家通过「用户中心 → 提现」发起提现，
                  提现规则请在「财务设置 → 提现配置」中统一管理。选项随已安装插件变化（如收银台的货款账户、其它插件声明的扩展钱包）。
                </div>
              </el-form-item>
            </div>
          </el-tab-pane>

          <el-tab-pane label="消息通知" name="notify">
            <!-- ── 入驻通知管理员 ── -->
            <div class="setting-section" v-if="form && form.admin_notify">
              <div class="section-title">入驻通知管理员</div>
              <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px;">
                <div slot="default" style="line-height: 1.8; font-size: 12px;">
                  商家提交入驻申请时，自动通知下方指定的管理员（可多选），提醒尽快审核。
                  配置后请点击右上角 <strong>「保存」</strong> 生效。
                </div>
              </el-alert>

              <el-form-item label="开启入驻通知">
                <el-switch v-model="form.admin_notify.enabled" :active-value="1" :inactive-value="0"></el-switch>
              </el-form-item>

              <template v-if="form.admin_notify.enabled">
                <el-form-item label="接收通知的管理员">
                  <div class="admin-tags">
                    <el-tag v-for="u in form.admin_notify.users" :key="u.id" closable
                            @close="removeAdmin(u.id)" style="margin: 0 8px 8px 0;">
                      {{ u.nickname || ('用户#' + u.id) }}
                    </el-tag>
                    <el-button size="mini" icon="el-icon-plus" @click="showAdminPicker = true">选择用户</el-button>
                  </div>
                  <div class="field-tip">支持多选；这些用户将按下方勾选的通道收到入驻提醒</div>
                </el-form-item>

                <el-form-item label="通知渠道">
                  <el-checkbox-group v-model="form.admin_notify.channels" class="options-row">
                    <el-checkbox label="im">站内 IM</el-checkbox>
                    <el-checkbox label="wx">微信公众号</el-checkbox>
                    <el-checkbox label="sms">短信</el-checkbox>
                  </el-checkbox-group>
                  <div class="field-tip">
                    站内 IM 无需额外配置；微信公众号需在下方「微信公众号模板消息」中配置
                    <code>mch.entry.submitted</code> 模板；短信配置见下方。
                  </div>
                </el-form-item>

                <template v-if="form.admin_notify.channels.includes('sms')">
                  <el-form-item label="短信内容（短信宝）">
                    <el-input type="textarea" v-model="form.admin_notify.sms_dxb_text" :rows="3"
                              placeholder="支持变量 {mch_name} 店铺名、{mch_type} 商家类型；短信宝需在内容开头加【签名】"
                              style="width: 600px;"></el-input>
                    <div class="field-tip">变量：<code>{mch_name}</code> 店铺名、<code>{mch_type}</code> 商家类型。短信宝渠道需自带【签名】。</div>
                  </el-form-item>
                  <el-form-item label="阿里云短信模板ID">
                    <el-input v-model="form.admin_notify.sms_aliyun_tpl" placeholder="如 SMS_123456，模板变量按 mch_name / mch_type 顺序"
                              clearable style="width: 400px;"></el-input>
                  </el-form-item>
                  <el-form-item label="腾讯云短信模板ID">
                    <el-input v-model="form.admin_notify.sms_tx_tpl" placeholder="如 123456，模板变量按 mch_name / mch_type 顺序"
                              clearable style="width: 400px;"></el-input>
                  </el-form-item>
                  <el-alert type="warning" :closable="false" show-icon style="width: 640px;">
                    <template slot="title">
                      短信走商城基础配置的服务商（短信宝 / 阿里云 / 腾讯云）。用阿里云/腾讯云时必须填对应模板ID，否则该渠道静默跳过。
                    </template>
                  </el-alert>
                </template>
              </template>
            </div>

            <div class="setting-section">
              <div class="section-title">微信公众号模板消息</div>
              <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px;">
                <div slot="default" style="line-height: 1.8; font-size: 12px;">
                  前往 <el-link type="primary" href="https://mp.weixin.qq.com" target="_blank">mp.weixin.qq.com</el-link>
                  → 功能 → 模板消息 → 添加模板，按下方各场景所需字段申请后，将模板 ID 和字段映射填入并保存。
                  商家入驻审核通过/驳回时会自动触达。
                </div>
              </el-alert>

              <div v-loading="notifyLoading">
                <div v-for="(item, index) in wxTemplates" :key="item.scene" class="scene-card">
                  <div class="scene-header">
                    <span class="scene-badge">{{ index + 1 }}</span>
                    <span class="scene-title">{{ item.title }}</span>
                    <el-tag size="mini" type="info" style="margin-left: 8px; font-family: monospace;">{{ item.scene }}</el-tag>
                    <el-tag v-if="item.template_id" size="mini" type="success" style="margin-left: 6px;">已配置</el-tag>
                    <el-tag v-else size="mini" type="warning" style="margin-left: 6px;">未配置</el-tag>
                  </div>

                  <div class="row-line">
                    <span class="row-label">模板 ID</span>
                    <el-input v-model="item.template_id" placeholder="从微信公众平台复制模板 ID"
                              clearable style="width: 460px;" />
                  </div>

                  <div class="map-section">
                    <div class="map-title">
                      字段映射
                      <span class="map-hint">— 填写模板中对应占位符名称（如 keyword1）</span>
                    </div>
                    <div v-for="field in getSceneFields(item.scene)" :key="field.key" class="map-row">
                      <code class="internal-key">{{ field.key }}</code>
                      <span class="field-desc">{{ field.desc }}</span>
                      <span class="arrow">→</span>
                      <el-input v-model="item.fieldMap[field.key]" :placeholder="field.defaultVar"
                                size="small" style="width: 120px;" />
                    </div>
                  </div>
                </div>
              </div>

              <div style="margin-top: 16px;">
                <el-button type="primary" :loading="notifySaving" @click="handleSaveNotify">保存配置</el-button>
                <span style="margin-left: 12px; font-size: 12px; color: #909399;">
                  保存后立即生效，未填模板 ID 的场景不会发送微信推送
                </span>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="操作手册" name="manual">
            <div style="max-width: 820px;">
              <el-alert type="success" :closable="false" show-icon style="margin-bottom: 16px;"
                        title="多商户:让多个商家入驻你的商城,各自上架商品、处理订单;平台统一配置入驻规则、审核资质、管理商家与分账。" />

              <el-collapse v-model="manualActive">
                <el-collapse-item name="1" title="① 开启入驻 —— 本页「入驻与审核」Tab">
                  <ol style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0;">
                    <li>打开「开启商户入驻」总开关。</li>
                    <li>「允许入驻类型」勾选要开放的主体:<b>线上商家</b>(纯线上经营)/<b>门店商家</b>(有线下地址、支持到店核销)/<b>个人租户</b>(凭身份证入驻)。<b>只有勾选的类型才会在商家入驻页出现。</b></li>
                    <li>「审核方式」选「后台人工审核」或「无需审核自动通过」。</li>
                    <li>填「入驻说明」「入驻协议」,会展示在商家入驻页底部。</li>
                  </ol>
                </el-collapse-item>

                <el-collapse-item name="2" title="② 配置入驻表单字段 —— 本页「入驻字段」Tab">
                  <ol style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0 0 10px;">
                    <li>切换「主体类型」(线上 / 门店 / 个人),分别配置各自的入驻表单。</li>
                    <li>每个字段两个开关:<b>启用</b>(是否出现在入驻页)、<b>必填</b>(是否必须填写)。停用的字段商家看不到。</li>
                    <li>带「必备」标签的核心字段(名称 / 联系电话 / 地址 / 联系人 / 身份证)<b>锁定不可关闭</b>,保证平台能识别商家。</li>
                    <li>改完点「保存字段配置」。<b style="color:#67C23A;">改字段即时生效,无需开发改代码、无需发版。</b></li>
                  </ol>
                  <el-alert type="info" :closable="false"
                            title="营业执照等证照不在这里配,请到「多商户 → 证照库」菜单管理。" />
                </el-collapse-item>

                <el-collapse-item name="3" title="③ 配置证照库 —— 菜单「多商户 → 证照库」">
                  <ol style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0;">
                    <li>「新增证照」:填证照名称(如「食品经营许可证」)、勾选适用主体、设是否必填、是否「采集有效期」。</li>
                    <li>开了「采集有效期」,商家上传该证照时需填写有效期,或勾选「长期有效」。</li>
                    <li><b>营业执照</b>是预置证照,可调整配置(必填 / 有效期 / 适用类型 / 停用),但<b>不可删除</b>(可改为停用)。</li>
                    <li>停用某证照后,商家入驻页不再要求上传它,但<b>已审核商家的旧证照不受影响</b>。</li>
                  </ol>
                </el-collapse-item>

                <el-collapse-item name="4" title="④ 商家如何入驻(C 端体验)">
                  <ol style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0;">
                    <li>商家在小程序 / H5 进入入驻页,先选主体类型。</li>
                    <li>页面按你的配置<b>动态展示字段 + 证照上传区</b>(含有效期),填完勾选协议后提交。</li>
                    <li>提交后状态变「待审核」,等待平台审核。</li>
                  </ol>
                </el-collapse-item>

                <el-collapse-item name="5" title="⑤ 审核商家 —— 菜单「多商户 → 商户管理」">
                  <ol style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0 0 10px;">
                    <li>用「审核状态」筛选出「待审核」。</li>
                    <li>点商家打开审核抽屉,查看 基本信息 + 身份证 + 证照(含有效期,<span style="color:#F56C6C;">过期会标红</span>)。</li>
                    <li>资料无误点「通过」;有问题点「驳回」并填写理由,商家可按理由<b>修改后重新提交</b>。</li>
                  </ol>
                  <div style="line-height: 2;">
                    <span style="color:#909399;">商家状态:</span>
                    <el-tag size="mini" type="info">待审核</el-tag>
                    <el-tag size="mini" type="success" style="margin-left:6px;">已通过</el-tag>
                    <el-tag size="mini" type="warning" style="margin-left:6px;">已驳回(可重新申请)</el-tag>
                    <el-tag size="mini" type="danger" style="margin-left:6px;">已禁用(需联系平台)</el-tag>
                  </div>
                </el-collapse-item>

                <el-collapse-item name="6" title="⑥ 小贴士">
                  <ul style="padding-left: 18px; line-height: 1.9; color: #555; margin: 0;">
                    <li>字段、证照的增减与开关<b>都是后台配置、即时生效</b>,不需要开发介入。</li>
                    <li>停用证照 / 字段不会影响已通过商家的历史数据。</li>
                    <li>「积分 / 余额 / 佣金」等叫法可在「品牌词库」里改成你自己的称呼,入驻页会跟着变。</li>
                    <li>多商户的抽佣、分账、提现、商家推荐奖,在本页其它 Tab 配置。</li>
                  </ul>
                </el-collapse-item>
              </el-collapse>
            </div>
          </el-tab-pane>

        </el-tabs>
      </el-form>
    </el-card>

    <!-- 管理员多选弹窗 -->
    <user-multiple-picker :show="showAdminPicker" @close="showAdminPicker = false"
                          @selected="onAdminSelected"></user-multiple-picker>
  </div>
</template>

<script>
// 消息通知场景字段定义（与后端 WxTemplateForm::MCH_SCENES 保持一致）
const SCENE_FIELDS = {
  'mch.entry.submitted': [
    { key: 'mch_name', desc: '申请店铺名', defaultVar: 'keyword1' },
    { key: 'mch_type', desc: '商家类型',   defaultVar: 'keyword2' },
    { key: 'tip',      desc: '提示语',     defaultVar: 'keyword3' },
  ],
  'mch.entry.approved': [
    { key: 'mch_name', desc: '店铺名', defaultVar: 'keyword1' },
    { key: 'tip',      desc: '提示语', defaultVar: 'keyword2' },
  ],
  'mch.entry.rejected': [
    { key: 'mch_name', desc: '店铺名',   defaultVar: 'keyword1' },
    { key: 'reason',   desc: '驳回原因', defaultVar: 'keyword2' },
    { key: 'tip',      desc: '提示语',   defaultVar: 'keyword3' },
  ],
}

import UserMultiplePicker from '@/components/mall/UserMultiplePicker'

export default {
  name: 'mch-setting',
  components: { UserMultiplePicker },
  data() {
    return {
      loading: false,
      submitLoading: false,
      activeName: 'entry',
      form: null,
      bank_funds: [],
      // 「货款结算」Tab：结算流向下拉选项，数据源=后端 MchSettlementService::settleTargetOptions
      // （核心账户 + 本商城已授权的扩展钱包），装了什么插件就有什么选项，前端不硬编码。
      settleTargetOptions: [],

      // 入驻字段配置 Tab(走 apply-config 独立接口,不进 setting blob)
      fieldType: 1,
      fieldLoading: false,
      fieldSaving: false,
      fieldList: [],

      // 操作手册 Tab
      manualActive: ['1'],

      // 消息通知 Tab
      notifyLoading: false,
      notifySaving: false,
      wxTemplates: [],

      // 入驻通知管理员
      showAdminPicker: false,

      // 统一经营地址:省市区级联(数据源与 MchEditDrawer 同一个接口,带 localStorage 缓存)
      addressList: [],
      defaultRegion: [],
      // 字段表格是否处于「统一地址接管」状态(后端回传,用于给地址那几行打标)
      fieldsUseDefaultAddress: false,
    }
  },
  created() {
    this.loadSetting()
    this.loadNotifyData()
    this.loadFieldConfig()
    this.loadAddressData()
  },
  methods: {
    // 省市区数据:与 MchEditDrawer 用同一个核心接口和同一份 localStorage 缓存,不另造轮子
    async loadAddressData() {
      const cached = localStorage.getItem('ADDRESS_DATA')
      if (cached) {
        this.addressList = JSON.parse(cached)
        return
      }
      const res = await this.$request({ url: '/common/address/address-data' })
      if (res.code === 0) {
        this.addressList = res.data.list
        localStorage.setItem('ADDRESS_DATA', JSON.stringify(res.data.list))
      }
    },
    // 级联选完把三级 code 拆进 form(后端按 province/city/county 三列存)
    onDefaultRegionChange(val) {
      const v = val || []
      this.form.default_province_code = v[0] || ''
      this.form.default_city_code = v[1] || ''
      this.form.default_county_code = v[2] || ''
    },
    // 开关一动就重拉字段表格,让「统一地址接管」标记立刻反映出来。
    // 注意接管标记是后端按**已保存**的设置算的,所以这里提示要先保存。
    onUseDefaultAddressChange() {
      this.$message({
        type: 'info',
        message: '保存后生效;保存前入驻表单仍按原样显示',
        duration: 2500,
      })
    },
    // 回显:把已存的三级 code 装回级联组件
    syncDefaultRegion() {
      this.defaultRegion = [
        this.form.default_province_code,
        this.form.default_city_code,
        this.form.default_county_code,
      ].filter(v => v !== '' && v !== null && v !== undefined)
    },

    async loadSetting() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/setting/setting',
          method: 'get'
        })
        // 严格遵循您原来的 if (res.code === 0) 结构
        if (res.code === 0) {
          // 严格遵循您原来的 const { data } = res 结构
          const {data} = res

          this.bank_funds = data.bank_funds || []
          this.settleTargetOptions = data.settle_target_options || []

          // [健壮性处理] 严格遵循您原来的 if (!res.data.setting.xxx) 结构
          if (!data.setting.withdraw_rules) {
            data.setting.withdraw_rules = {}
          }
          // 入口归一化：后端 JSON 反序列化可能把数组里的整数变成字符串（如 ['2']），
          // 而 el-checkbox 的 :label="2" 是整数，严格比较下 v-model 无法匹配 → 视觉上"未勾选"。
          // 这里统一转回整数数组，过滤掉无法转换的脏值。
          if (Array.isArray(data.setting.allowed_mch_types)) {
            data.setting.allowed_mch_types = data.setting.allowed_mch_types
              .map(v => Number(v))
              .filter(n => !isNaN(n))
          } else {
            data.setting.allowed_mch_types = [1, 2, 4]
          }
          // 入驻通知管理员：兜底成完整结构，避免老配置缺字段导致模板渲染报错
          const an = data.setting.admin_notify || {}
          data.setting.admin_notify = {
            enabled:        Number(an.enabled) || 0,
            user_ids:       Array.isArray(an.user_ids) ? an.user_ids.map(v => Number(v)).filter(n => !isNaN(n)) : [],
            users:          Array.isArray(an.users) ? an.users : [],
            channels:       Array.isArray(an.channels) ? an.channels : [],
            sms_dxb_text:   an.sms_dxb_text || '',
            sms_aliyun_tpl: an.sms_aliyun_tpl || '',
            sms_tx_tpl:     an.sms_tx_tpl || '',
          }
          // 商家推荐奖总开关归一化为数字（与本页其它开关一致：数字 active-value + Number 归一）
          data.setting.recruit_enable = Number(data.setting.recruit_enable) || 0
          // 结算流向兜底：未配置时视觉上落在"佣金"，与后端 MchSettlementService 未配置时的历史默认行为一致
          data.setting.mch_settle_target = data.setting.mch_settle_target || 'commission'
          this.form = data.setting
          // 把已存的省市区 code 装回级联组件,否则重进页面选择框是空的
          this.syncDefaultRegion()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error('加载配置失败', e)
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.submitLoading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/setting/setting',
          method: 'post',
          // 严格遵循您原来的 data: this.form 结构
          data: this.form
        })
        if (res.code === 0) {
          this.$message.success('保存成功')
          // 非阻断提醒：货款结算目标暂无可用提现渠道时后端会带这句话，停留久一点方便看完整句
          if (res.warning) {
            this.$message({ type: 'warning', message: res.warning, duration: 6000, showClose: true })
          }
          this.loadSetting() // 重新加载以获取最新配置
          // 统一地址开关会改变字段表格里的「接管」标记,保存后一并刷新
          this.loadFieldConfig()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error('保存配置失败', e)
      } finally {
        this.submitLoading = false
      }
    },

    // ── 入驻字段配置 Tab ──
    async loadFieldConfig() {
      this.fieldLoading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/apply-config/field-config',
          method: 'get',
          data: { type: this.fieldType }
        })
        if (res.code === 0) {
          this.fieldList = res.data.fields || []
          this.fieldsUseDefaultAddress = res.data.use_default_address == 1
        } else this.$message.error(res.msg)
      } finally {
        this.fieldLoading = false
      }
    },
    async saveFieldConfig() {
      this.fieldSaving = true
      try {
        const fields = this.fieldList.map(f => ({
          field_key: f.field_key,
          is_enabled: f.is_enabled,
          // 停用的字段必填归零,避免"停用却必填"的矛盾脏数据入库
          is_required: f.is_enabled ? f.is_required : 0
        }))
        const res = await this.$request({
          url: '/plugin/mch/mall/apply-config/save-field-config',
          method: 'post',
          data: { type: this.fieldType, fields }
        })
        if (res.code === 0) {
          this.$message.success('保存成功')
          this.loadFieldConfig()
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.fieldSaving = false
      }
    },

    // ── 消息通知 Tab ──
    loadNotifyData() {
      this.notifyLoading = true
      this.$request({ url: '/plugin/mch/mall/setting/wx-template', method: 'get' })
        .then(res => {
          if (res.code === 0) {
            this.wxTemplates = (res.data.templates || []).map(t => ({
              ...t,
              fieldMap: this.parseFieldMap(t.scene, t.field_map),
            }))
          }
        })
        .finally(() => { this.notifyLoading = false })
    },
    handleSaveNotify() {
      this.notifySaving = true
      const payload = this.wxTemplates.map(t => ({
        scene:       t.scene,
        template_id: t.template_id || '',
        field_map:   JSON.stringify(t.fieldMap || {}),
      }))
      this.$request({
        url: '/plugin/mch/mall/setting/wx-template',
        method: 'post',
        data: { templates: payload }
      }).then(res => {
        if (res.code === 0) this.$message.success('保存成功')
        else this.$message.error(res.msg)
      }).finally(() => { this.notifySaving = false })
    },
    parseFieldMap(scene, fieldMapJson) {
      const saved = fieldMapJson ? (JSON.parse(fieldMapJson) || {}) : {}
      const fields = SCENE_FIELDS[scene] || []
      const result = {}
      fields.forEach(f => {
        result[f.key] = saved[f.key] || f.defaultVar
      })
      return result
    },
    getSceneFields(scene) {
      return SCENE_FIELDS[scene] || []
    },

    // ── 入驻通知管理员 ──
    onAdminSelected({ checked_list }) {
      const an = this.form.admin_notify
      const existIds = new Set(an.user_ids)
      ;(checked_list || []).forEach(u => {
        if (!existIds.has(u.id)) {
          an.user_ids.push(u.id)
          an.users.push({ id: u.id, nickname: u.nickname, avatar: u.avatar_url || '' })
          existIds.add(u.id)
        }
      })
      this.showAdminPicker = false
    },
    removeAdmin(id) {
      const an = this.form.admin_notify
      an.user_ids = an.user_ids.filter(uid => uid !== id)
      an.users = an.users.filter(u => u.id !== id)
    },
  }
}
</script>

<style scoped>
.setting-section {
  margin-bottom: 24px;
  padding: 20px 24px;
  background: #FAFBFC;
  border: 1px solid #EBEEF5;
  border-radius: 6px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid #EBEEF5;
}

.options-row {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 24px;
}

.field-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.admin-tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}
.setting-section code {
  background: var(--admin-accent-soft);
  color: var(--admin-accent-text);
  padding: 1px 6px;
  border-radius: 3px;
  font-family: monospace;
  font-size: 12px;
}

/* ── 消息通知 Tab 样式 ── */
.scene-card {
  padding: 16px 18px;
  margin-bottom: 12px;
  background: #fff;
  border: 1px solid #E4E7ED;
  border-radius: 4px;
}
.scene-header {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
}
.scene-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  background: var(--admin-primary);
  color: var(--admin-on-primary);
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
  margin-right: 10px;
}
.scene-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}
.row-line {
  display: flex;
  align-items: center;
  margin-bottom: 14px;
}
.row-label {
  width: 80px;
  color: #606266;
  font-size: 13px;
}
.map-section {
  padding-top: 12px;
  border-top: 1px dashed #E4E7ED;
}
.map-title {
  font-size: 13px;
  color: #303133;
  margin-bottom: 10px;
  font-weight: 500;
}
.map-hint {
  color: #909399;
  font-size: 12px;
  font-weight: normal;
}
.map-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  font-size: 13px;
}
.internal-key {
  display: inline-block;
  min-width: 100px;
  padding: 2px 8px;
  background: var(--admin-accent-soft);
  color: var(--admin-accent-text);
  border-radius: 3px;
  font-family: monospace;
  font-size: 12px;
}
.field-desc {
  margin-left: 12px;
  color: #606266;
  min-width: 80px;
}
.arrow {
  margin: 0 10px;
  color: #C0C4CC;
}
</style>
