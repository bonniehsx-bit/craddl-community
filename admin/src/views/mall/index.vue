<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="clearfix">
        <span class="header-title">商城设置</span>
        <el-button style="float: right; padding: 3px 0" type="primary" text @click="save">保存配置</el-button>
      </div>

      <el-form size="small" ref="form" :model="form" label-width="150px" class="setting-form">
        <el-tabs v-model="activeName" tab-position="left" style="min-height: 600px;">

          <el-tab-pane label="基本信息" name="mall">
            <div class="pane-title">基础信息</div>
            <el-form-item label="商城名称">
              <el-input v-model="form.name" placeholder="请输入商城名称" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="商城Logo">
              <single-image-selector :url="form.logo" v-model="form.logo"></single-image-selector>
            </el-form-item>
            <el-form-item label="主题色">
              <div style="display: flex; align-items: center;">
                <el-color-picker v-model="form.theme_color"></el-color-picker>
                <el-input v-model="form.theme_color" style="width: 150px; margin-left: 10px;"
                  placeholder="#F7931E"></el-input>
              </div>
              <div class="form-tip">控制App及H5端的按钮、导航栏、图标等核心颜色（默认 #F7931E）</div>
            </el-form-item>
            <el-form-item label="公司名称">
              <el-input v-model="form.company" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="APP名称">
              <el-input v-model="form.app_name" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="APP简称">
              <el-input v-model="form.app_short_name" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="联系电话">
              <el-input v-model="form.tel" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="联系地址">
              <el-input v-model="form.address" class="w-500"></el-input>
            </el-form-item>

            <el-form-item label="经度">
              <el-input v-model="form.lon" placeholder="Longitude" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="纬度">
              <el-input v-model="form.lat" placeholder="Latitude" class="w-500"></el-input>
            </el-form-item>

            <el-form-item label="商城介绍">
              <el-input type="textarea" :rows="3" v-model="form.detail" class="w-500"></el-input>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="功能开关" name="show">
            <div class="pane-title">前端显示设置</div>
            <el-form-item label="地址识别">
              <el-radio-group v-model="form.is_show_addr_recognize">
                <el-radio label="1">显示</el-radio>
                <el-radio label="0">隐藏</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="地址选择地理位置">
              <el-radio-group v-model="form.address_location_enabled">
                <el-radio label="1">开启</el-radio>
                <el-radio label="0">关闭</el-radio>
              </el-radio-group>
              <div class="form-tips">开启后,用户在「新增/编辑收货地址」页可点选地图定位、获取经纬度。<b>同城配送按经纬度计算距离与运费,需要用到同城配送的商城请开启;</b>不用同城配送可关闭以简化填写。</div>
            </el-form-item>
            <el-form-item label="显示加入购物车">
              <el-radio-group v-model="form.is_show_add_cart">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
            </el-form-item>

          </el-tab-pane>

          <el-tab-pane label="背景音乐" name="bg_music">
            <div class="pane-title">背景音乐</div>
            <el-form-item label="开启背景音乐">
              <el-radio-group v-model="form.bg_music_open">
                <el-radio label="1">开启</el-radio>
                <el-radio label="0">关闭</el-radio>
              </el-radio-group>
              <div class="form-tips">开启后用户一进小程序就循环播放,切换页面不中断;离开小程序自动暂停,回来接着放。右下角有个小唱片按钮,用户点一下就静音,并记住他的选择。iPhone 开了静音键时不出声。</div>
            </el-form-item>
            <template v-if="form.bg_music_open === '1'">
              <el-form-item label="音乐文件">
                <el-input v-model="form.bg_music_url" class="w-500" placeholder="选择音乐或粘贴音乐链接(mp3)" clearable>
                  <file-picker slot="append" type="audio" :multiple="false" :max="1" @selected="onPickMusic">
                    <el-button>选择音乐</el-button>
                  </file-picker>
                </el-input>
                <div v-if="form.bg_music_url" style="margin-top: 8px">
                  <audio :src="form.bg_music_url" controls preload="none" style="height: 32px; width: 500px"></audio>
                </div>
                <div class="form-tips">建议 mp3,2MB 以内(太大首次加载慢)。<b>请使用有商用版权的音乐</b>,热门歌曲大多有版权,被投诉可能影响小程序。</div>
              </el-form-item>
              <el-form-item label="音量">
                <el-slider :value="Number(form.bg_music_volume) || 60" :min="10" :max="100" :step="10" show-stops
                           style="width: 500px" @input="form.bg_music_volume = String($event)"></el-slider>
              </el-form-item>
              <el-form-item label="播放时间段">
                <el-date-picker v-model="bgMusicRange" type="daterange" value-format="yyyy-MM-dd"
                                range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期"></el-date-picker>
                <div class="form-tips">不选就一直播放。比如选 12 月 1 日至 12 月 26 日,过了自动不放,不用记得回来关。</div>
              </el-form-item>
            </template>
          </el-tab-pane>

          <el-tab-pane label="登录注册" name="login">
            <div class="pane-title">登录与注册规则</div>
            <el-form-item label="注册需短信验证码">
              <el-radio-group v-model="form.reg_need_sms_code">
                <el-radio label="1">需要</el-radio>
                <el-radio label="0">不需要</el-radio>
              </el-radio-group>
              <div class="form-tip">注册时是否发送短信验证码校验手机号真伪。需先在「短信设置」配好服务商，否则无法发送。</div>
              <div class="form-tip text-danger"><i class="el-icon-warning"></i> 关闭后，手机号注册将<b>不校验短信验证码</b>（即不验证手机号真伪），存在被恶意批量注册的风险，请谨慎使用。</div>
            </el-form-item>

            <el-form-item label="注册需设置支付密码">
              <el-radio-group v-model="form.reg_need_pay_password">
                <el-radio label="1">需要</el-radio>
                <el-radio label="0">不需要</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，用户在注册页必须设置 <b>6 位数字支付密码</b>（用于余额支付、转账等场景）；关闭则注册后再到「账户设置」里单独设置。</div>
            </el-form-item>

            <el-form-item label="登录有效期">
              <el-input-number v-model="form.login_expire_days" :min="1" :max="365" :step="1" step-strictly controls-position="right" style="width:140px"></el-input-number>
              <span style="margin-left:8px">天</span>
              <div class="form-tip">用户<b>连续这么多天没打开</b>才需要重新登录；期间只要打开过，有效期就会自动往后顺延。默认 30 天，可填 1-365。</div>
              <div class="form-tip">改动只影响之后的续期和新登录，已登录用户不会被立刻踢下线。</div>
            </el-form-item>

            <el-form-item label="强制绑定手机号">
              <el-radio-group v-model="form.is_binding">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
              <div class="form-tip">提示：若未启用手机号登录但开启此项，用户授权登录后将提示绑定手机号。</div>
            </el-form-item>

            <div class="pane-title">完善资料</div>
            <el-form-item label="完善头像信息">
              <el-radio-group v-model="form.is_must_avatar">
                <el-radio label="1">强制完善</el-radio>
                <el-radio label="0">不强制</el-radio>
              </el-radio-group>
              <div class="form-tip">强制完善:用户进个人中心时,还没设置过头像昵称的会自动弹出「完善资料」弹窗。用户也可以随时在「设置」页点头像打开这个弹窗。下面的生日、性别就在这个弹窗里填。</div>
            </el-form-item>
            <el-form-item label="采集生日">
              <el-radio-group v-model="form.collect_birthday">
                <el-radio label="1">采集</el-radio>
                <el-radio label="0">不采集</el-radio>
              </el-radio-group>
              <div class="form-tips">开启后,用户在「完善资料」弹窗可填写生日(选填)。可用于生日月多倍积分等营销场景。</div>
            </el-form-item>
            <el-form-item label="生日可否自改" v-if="form.collect_birthday === '1'">
              <el-radio-group v-model="form.birthday_editable">
                <el-radio label="0">填写后锁定(仅后台/客服可改)</el-radio>
                <el-radio label="1">允许用户自行修改</el-radio>
              </el-radio-group>
              <div class="form-tips">选「锁定」后,用户已填过生日则再次打开「完善资料」弹窗时<b>不再显示生日项</b>,需由后台/客服代为调整(防刷生日礼)。</div>
            </el-form-item>
            <el-form-item label="采集性别">
              <el-radio-group v-model="form.collect_gender">
                <el-radio label="1">采集</el-radio>
                <el-radio label="0">不采集</el-radio>
              </el-radio-group>
              <div class="form-tips">开启后,用户在「完善资料」弹窗可选择性别(选填)。</div>
            </el-form-item>
            <el-form-item label="性别可否自改" v-if="form.collect_gender === '1'">
              <el-radio-group v-model="form.gender_editable">
                <el-radio label="1">允许用户自行修改</el-radio>
                <el-radio label="0">填写后锁定(仅后台/客服可改)</el-radio>
              </el-radio-group>
              <div class="form-tips">选「锁定」后,用户已选过性别则再次打开弹窗时<b>不再显示性别项</b>。</div>
            </el-form-item>

            <div class="pane-title">首页登录弹窗</div>
            <el-form-item label="开启首页登录">
              <el-radio-group v-model="form.is_home_login">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，未登录用户每次打开小程序/App 时在首页弹一次登录弹窗，可以点「先随便逛逛」关掉，不强制登录。</div>
              <div class="form-tip">命中「版本审核」的审核版本号时自动不弹，审核期间不用手动关。</div>
            </el-form-item>

            <template v-if="form.is_home_login === '1'">
              <el-form-item label="弹窗登录方式">
                <!-- 字段沿用 is_login_mobile(原说明就是「控制登录弹窗点立即登录的去向」,前端一直没读):'0' 一键登录,'1' 跳登录页 -->
                <el-radio-group v-model="form.is_login_mobile">
                  <el-radio label="0">手机号一键登录</el-radio>
                  <el-radio label="1">跳转登录页</el-radio>
                </el-radio-group>
                <div class="form-tip">手机号一键登录：小程序里在弹窗内授权微信手机号直接登录，新用户自动注册；跳转登录页：弹窗只有「登录 / 注册」按钮，点了进登录页。登录页本身不受影响，照旧可选手机号授权或账号密码。H5 和 App 没有一键登录，始终跳转登录页。</div>
                <div class="form-tip">微信「手机号快速验证」按次收费（有免费额度），且要求小程序完成主体认证。</div>
              </el-form-item>
              <el-form-item label="登录弹窗标题">
                <el-input v-model="form.home_login_title" placeholder="登录后，好物和优惠都在这" maxlength="20" show-word-limit style="width:320px" clearable></el-input>
                <div class="form-tip">留空使用默认标题。</div>
              </el-form-item>
              <el-form-item label="登录好处">
                <div v-for="(b, i) in form.home_login_benefits" :key="i" class="benefit-row">
                  <el-select v-model="b.icon" size="small" style="width:120px">
                    <el-option v-for="o in benefitIcons" :key="o.value" :label="o.label" :value="o.value"></el-option>
                  </el-select>
                  <el-input v-model="b.text" size="small" maxlength="8" show-word-limit placeholder="如：领取优惠券" style="width:200px;margin-left:8px"></el-input>
                </div>
                <div class="form-tip">弹窗里的三个小图标和短句，写这个商城真实有的好处（比如「芬兰直邮」「会员价」）。全部留空使用默认：领取优惠券 / 会员专享价 / 订单物流随时查。</div>
              </el-form-item>
            </template>

            <div class="pane-title">推荐关系</div>
            <el-form-item label="默认推荐人ID">
              <el-input v-model="form.default_parent_id" placeholder="填用户ID；留空则不默认绑定" style="width:220px" clearable></el-input>
              <div class="form-tip">用户注册时若没有邀请来源（推荐人），自动绑定为此用户ID的下级。留空 = 不默认绑定；有邀请链接时以邀请为准。</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="交易规则" name="order">
            <div class="pane-title">订单与支付</div>
            <el-form-item label="必须绑定推荐人">
              <el-radio-group v-model="form.is_must_parent_id">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，用户下单前必须绑定上级关系。</div>
            </el-form-item>

            <el-form-item label="平台自提">
              <el-radio-group v-model="form.support_pickup">
                <el-radio label="1">支持</el-radio>
                <el-radio label="0">不支持</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，平台自营订单可选到店自提（核销走「平台核销中心」插件）。商家订单自提由商家自行配置，不受此开关影响。</div>
            </el-form-item>
            <el-form-item label="自提地址类型" v-if="form.support_pickup === '1'">
              <el-radio-group v-model="form.pickup_address_type">
                <el-radio label="platform">平台自提点</el-radio>
                <el-radio label="user_address">用户默认收货地址</el-radio>
              </el-radio-group>
              <div class="form-tip">
                决定自提订单里存的地址来源。选「用户默认收货地址」时，下单页会像快递配送一样让用户
                <b>自行选择一个收货地址（必选）</b>，此时不展示下方的平台自提点。
              </div>
            </el-form-item>
            <el-form-item label="自提点名称" v-if="form.support_pickup === '1'">
              <el-input v-model="form.pickup_name" placeholder="留空则用商城名称" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="自提点地址" v-if="form.support_pickup === '1'">
              <el-input v-model="form.pickup_address" placeholder="留空则用商城注册地址" class="w-500"></el-input>
              <div class="form-tip">
                用户到此地址自提取货；经纬度可选填，用于地图定位。
                <span v-if="form.pickup_address_type === 'user_address'">当前地址类型为「用户默认收货地址」，此项不生效。</span>
              </div>
            </el-form-item>
            <el-form-item label="自提点经纬度" v-if="form.support_pickup === '1'">
              <el-input v-model="form.pickup_lon" placeholder="经度" style="width:180px"></el-input>
              <el-input v-model="form.pickup_lat" placeholder="纬度" style="width:180px;margin-left:8px"></el-input>
            </el-form-item>

            <el-form-item label="支付商品描述">
              <el-input v-model="form.payment_body" class="w-500" maxlength="127" show-word-limit clearable placeholder="选填，留空使用系统默认描述"></el-input>
              <div class="form-tip">填写后用于支付账单的商品描述；留空或清空后沿用系统默认文案。请填写与实际商品相符的简短描述。</div>
            </el-form-item>

            <el-form-item label="余额转账验证密码">
              <el-radio-group v-model="form.is_transfer_password">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，用户在客户端进行余额互转时，必须验证支付密码。</div>
            </el-form-item>

            <el-form-item label="余额支付验证密码">
              <el-radio-group v-model="form.is_balance_pay_password">
                <el-radio label="1">是</el-radio>
                <el-radio label="0">否</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，用户使用余额支付订单时必须输入支付密码；未设置密码的用户会被引导到设置中心。</div>
            </el-form-item>

            <el-form-item label="禁止取消/退款">
              <el-radio-group v-model="form.is_ban_cancel">
                <el-radio label="1">禁止</el-radio>
                <el-radio label="0">允许</el-radio>
              </el-radio-group>
            </el-form-item>

            <el-form-item label="等级限购提示文案">
              <el-input v-model="form.buy_level_limit_msg" class="w-500" placeholder="您的用户等级不可购买商品: {name}" />
              <div class="form-tip">用户等级不满足商品购买等级时的提示。占位符:<b>{name}</b>=商品名。留空则用默认文案。</div>
            </el-form-item>

            <el-form-item label="限购提示文案">
              <el-input v-model="form.buy_limit_num_msg" class="w-500" placeholder="商品：{name}，超出了购买限制(限购{limit}件，已购{history}件)" />
              <div class="form-tip">超出商品限购数量时的提示。占位符:<b>{name}</b>=商品名、<b>{limit}</b>=限购数、<b>{history}</b>=已购数。留空则用默认文案。</div>
            </el-form-item>

            <el-form-item label="未支付超时时间">
              <el-input v-model="form.over_time" class="w-500">
                <template slot="append">分钟</template>
              </el-input>
              <div class="form-tip">订单生成后超过此时长未支付将自动取消。</div>
            </el-form-item>

            <el-form-item label="自动确认收货">
              <el-input v-model="form.auto_confirm_day" class="w-500">
                <template slot="append">天</template>
              </el-input>
              <div class="form-tip">发货后超过此时长，订单将自动确认收货。</div>
            </el-form-item>

            <el-form-item label="订单结算周期">
              <el-input v-model="form.order_settle_day" class="w-500">
                <template slot="append">天</template>
              </el-input>
              <div class="form-tip">订单完成后经过此周期（处理售后）再进行佣金结算。</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="退货地址" name="refund">
            <div class="pane-title">售后收货信息</div>
            <el-form-item label="收货人姓名">
              <el-input v-model="form.receive_name" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="收货联系电话">
              <el-input v-model="form.receive_mobile" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="详细收货地址">
              <el-input v-model="form.receive_address" class="w-500"></el-input>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="物流接口" name="express_bird">
            <div class="pane-title">快递鸟配置 (用于查询物流轨迹)</div>
            <el-form-item label="用户ID (EBusinessID)">
              <el-input v-model="form.EBusinessID" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="API Key">
              <el-input v-model="form.AppKey" class="w-500"></el-input>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="短信设置" name="sms_setting">
            <div class="pane-title">发送限制</div>
            <el-form-item label="每号每日上限">
              <el-input v-model="form.sms_day_limit" class="w-500" type="number" min="0"></el-input>
              <div class="form-tip">
                同一手机号每天最多可获取多少次验证码，0 表示不限制。
                另有两道固定限制：同一号码 180 秒内不可重复获取、同一 IP 每小时 10 次。
              </div>
              <div class="form-tip">短信按条计费，且这道限制换 IP 也绕不开，建议保留。</div>
            </el-form-item>

            <div class="pane-title">短信服务商配置</div>
            <el-form-item label="选择服务商">
              <el-radio-group v-model="form.sms_type">
                <el-radio label="aliyun" border>阿里云</el-radio>
                <el-radio label="tx" border>腾讯云</el-radio>
                <el-radio label="dxb" border>短信宝</el-radio>
              </el-radio-group>
            </el-form-item>

            <div v-if="form.sms_type === 'aliyun'" class="sub-setting-block">
              <el-form-item label="Access Key ID">
                <el-input v-model="form.access_key_id" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="Access Key Secret">
                <el-input v-model="form.access_key_secret" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="短信签名">
                <el-input v-model="form.sign_name" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="验证码模板CODE">
                <el-input v-model="form.sms_tpl_code" placeholder="例如：SMS_12345678" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="模板变量名">
                <el-input v-model="form.sms_tpl" placeholder="例如：code" class="w-500"></el-input>
              </el-form-item>
            </div>

            <div v-if="form.sms_type === 'tx'" class="sub-setting-block">
              <el-form-item label="SDK AppID">
                <el-input v-model="form.tx_sdk_app_id" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="Secret ID">
                <el-input v-model="form.tx_secret_id" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="Secret Key">
                <el-input v-model="form.tx_secret_key" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="短信签名">
                <el-input v-model="form.tx_sign_name" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="模板ID">
                <el-input v-model="form.tx_template_id" class="w-500"></el-input>
              </el-form-item>
            </div>

            <div v-if="form.sms_type === 'dxb'" class="sub-setting-block">
              <el-form-item label="平台账号">
                <el-input v-model="form.dxb_user" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="平台密码">
                <el-input v-model="form.dxb_pass" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="内容模板">
                <el-input v-model="form.dxb_content" placeholder="【签名】您的验证码是{code}，5分钟内有效" class="w-500"></el-input>
              </el-form-item>
              <el-form-item label="短信签名">
                <el-input v-model="form.dxb_sign" placeholder="例如：XX广场（只填文字，系统自动加【】）" class="w-500"></el-input>
                <div class="form-tip" style="line-height:1.6">
                  用于<b>业务通知短信</b>（账单下发 / 逾期催缴 / 合同临期 / 证照到期等）正文最前面的签名，<b>必须与短信宝账号报备通过的签名一致</b>，否则会被运营商拦截。系统自动加【】，只填文字。<br>
                  <span style="color:#E6A23C">留空则不加签名</span>（适用于网关已自动追加签名的账号，避免双签名）。上方「内容模板」是验证码短信，签名仍写在其中。
                </div>
              </el-form-item>
              <el-form-item>
                <div class="form-tip">开通地址：<a href="https://www.smsbao.com" target="_blank">https://www.smsbao.com</a>
                </div>
              </el-form-item>
            </div>
          </el-tab-pane>

          <el-tab-pane label="文件存储" name="file_location">
            <div class="pane-title">文件上传存储策略</div>
            <el-form-item label="存储位置">
              <el-radio-group v-model="form.file_location">
                <el-radio label="local" border>本地服务器</el-radio>
                <el-radio label="oss" border>阿里云 OSS</el-radio>
                <el-radio label="cos" border>腾讯云 COS</el-radio>
                <el-radio label="qiniu" border>七牛云</el-radio>
              </el-radio-group>
              <div class="form-tip">切换后请确保对应插件已正确配置。</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="地图接口" name="map_api">
            <div class="pane-title">高德地图配置</div>
            <el-form-item label="用户名">
              <el-input v-model="form.amap_username" class="w-500" placeholder="高德开放平台用户名"></el-input>
            </el-form-item>
            <el-form-item label="Web服务API Key">
              <el-input v-model="form.amap_ak_api" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="JS端 Key">
              <el-input v-model="form.amap_ak_js" class="w-500"></el-input>
            </el-form-item>

            <el-divider></el-divider>
            <div class="pane-title">百度地图配置</div>
            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="应用编号">
                  <el-input v-model="form.baidu_ak_id"></el-input>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="访问AK">
                  <el-input v-model="form.baidu_ak"></el-input>
                </el-form-item>
              </el-col>
            </el-row>

            <el-divider></el-divider>
            <div class="pane-title">百度地址识别</div>
            <el-form-item label="AppID">
              <el-input v-model="form.bd_addr_appid" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="API Key">
              <el-input v-model="form.bd_addr_apikey" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="Secret Key">
              <el-input v-model="form.bd_addr_secret" class="w-500"></el-input>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="商品配置" name="goods">
            <el-form-item label="商品参数属性">
              <el-input type="textarea" :rows="4" v-model="form.goods_property" placeholder="例如：品牌,型号,材质 (使用英文逗号隔开)"
                class="w-500"></el-input>
              <div class="form-tip">用于商品发布时的通用属性模板，多个属性请用英文逗号隔开。</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="分类样式" name="page_style">
            <el-form-item label="分类页版式">
              <div class="cat-theme-picker">
                <div v-for="t in catThemes" :key="t.value" class="cat-theme-card"
                  :class="{ active: form.cat_page_theme === t.value }" @click="form.cat_page_theme = t.value">
                  <div class="cat-theme-preview" v-html="t.preview"></div>
                  <div class="cat-theme-name">{{ t.label }}</div>
                  <div class="cat-theme-desc">{{ t.desc }}</div>
                </div>
              </div>
              <div class="form-tip" style="margin-top: 10px;">
                这里只选<b>版式骨架</b>，配色一律跟随「基本信息」里的主题色，不单独配置 ——
                避免同一个商城出现分类页和其它页面两套颜色。
              </div>
              <div class="form-tip">
                选择「沿用旧版」即保持现状（左侧一级分类 + 右侧二级分类图片宫格，需要再点一层才进入商品列表）。
                其余四套是新版分类页：右侧顶部为二级分类横向滑动，下方<b>直接展示商品</b>，少一次跳转。
              </div>
              <div class="form-tip text-warning">
                新版式下二级分类会直接展示商品，所以<b>商品要挂到二级分类</b>才看得到。
                目前多数商品只挂了一级分类，切换后各二级标签可能是空的 ——
                每个一级分类下的第一个「全部」标签始终展示该分类的全部商品，不会整页空白。
              </div>
              <div class="form-tip text-warning">
                商品销量优先展示<b>虚拟销量</b>（商品编辑页可填），未填写的商品自动回退为真实销量。
              </div>
              <div class="form-tip text-danger">
                切换版式后需要<b>重新发布小程序 / H5</b> 才会生效，仅保存后台设置不会改变已上线的前端。
              </div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="用户协议" name="protocol">
            <el-form-item label="注册协议内容">
              <el-input type="textarea" :rows="6" v-model="form.user_protocol" placeholder="支持纯文本"
                class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="隐私协议内容">
              <el-input type="textarea" :rows="6" v-model="form.privation_protocol" placeholder="支持纯文本"
                class="w-500"></el-input>
            </el-form-item>
          </el-tab-pane>

          <!-- 原来在「基本信息」最底下,富文本编辑器太长把整页撑得很高,单独成页 -->
          <el-tab-pane label="关于我们" name="about">
            <el-form-item label="页面内容">
              <rich-text v-model="form.about"></rich-text>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="关注引导" name="subscribe">
            <div class="pane-title">通知功能开关</div>
            <el-form-item label="微信通知绑定">
              <el-radio-group v-model="form.wx_notify_enabled">
                <el-radio label="1">开启</el-radio>
                <el-radio label="0">关闭</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后，用户可在账户设置页绑定微信公众号，接收推送通知。</div>
            </el-form-item>
            <el-divider></el-divider>
            <div class="pane-title">小程序关注组件</div>
            <el-form-item label="首页悬浮关注提示">
              <el-switch
                v-model="form.show_follow_banner"
                active-value="1"
                inactive-value="0"
              />
              <div class="form-tip">开启后，未关注公众号的用户进入小程序首页时，顶部将滑出关注引导横幅，10 秒无操作自动收起。需配合小程序关注公众号组件资质使用。</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="开发选项" name="dev">
            <div class="pane-title">开发者模式</div>
            <el-form-item label="测试模式">
              <el-radio-group v-model="form.is_dev">
                <el-radio label="1" border>开启</el-radio>
                <el-radio label="0" border>关闭</el-radio>
              </el-radio-group>
              <div class="form-tip">开启后支付金额可能变为0.01元，仅供测试使用。</div>
            </el-form-item>
            <el-form-item label="全局万能验证码">
              <el-input v-model="form.default_sms_code" class="w-500"></el-input>
              <div class="form-tip text-danger">设置后，所有手机验证码均可使用此码通过，生产环境请留空！</div>
            </el-form-item>
          </el-tab-pane>

          <el-tab-pane label="其他设置" name="other">
            <el-form-item label="隐私协议链接">
              <el-input v-model="form.privacy_url" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="用户协议链接">
              <el-input v-model="form.protocol_url" class="w-500"></el-input>
            </el-form-item>
            <el-divider></el-divider>

            <el-form-item label="商品默认搜索词">
              <el-input v-model="form.goods_keyword" class="w-500"></el-input>
            </el-form-item>
            <el-form-item label="商家默认搜索词">
              <el-input v-model="form.mch_keyword" class="w-500"></el-input>
            </el-form-item>
            <el-divider></el-divider>

            <el-form-item label="商品详情插图">
              <single-image-selector :url="form.show_in_goods_pic"
                v-model="form.show_in_goods_pic"></single-image-selector>
              <div class="form-tip">显示在所有商品详情页底部的通用图片。</div>
            </el-form-item>
            <el-form-item label="默认用户头像">
              <single-image-selector :url="form.avatar_url" v-model="form.avatar_url"></single-image-selector>
            </el-form-item>
            <el-form-item label="全局分享标题">
              <el-input v-model="form.share_title" class="w-500" placeholder="留空则用商城名称"></el-input>
              <div class="form-tip">微信分享卡片的大标题。</div>
            </el-form-item>
            <el-form-item label="全局分享描述">
              <el-input v-model="form.share_desc" class="w-500" maxlength="60" show-word-limit
                placeholder="留空则用商城简介"></el-input>
              <div class="form-tip">标题下方那行小字。留空的话卡片只有一行标题，看着比较空。分享到朋友圈时微信不显示描述，只显示标题。</div>
            </el-form-item>
            <el-form-item label="全局分享图">
              <single-image-selector :url="form.share_pic" v-model="form.share_pic"></single-image-selector>
              <div class="form-tip">留空则用商城 LOGO。微信要求图片不小于 300×300，且不支持 webp；太小的图微信会直接不显示。</div>
            </el-form-item>
            <el-form-item label="订阅消息模板ID">
              <el-input type="textarea" :rows="3" v-model="form.mpwx_tpl_ids" placeholder="模板ID1,模板ID2"
                class="w-500"></el-input>
              <div class="form-tip">小程序订阅消息模板ID，多个请用英文逗号隔开。</div>
            </el-form-item>
          </el-tab-pane>

        </el-tabs>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import RichText from '@/components/common/RichText'

// 首页登录弹窗的三条好处:图标只能选 C 端弹窗(jx-login-sheet)里有的,和后端 LOGIN_BENEFIT_ICONS 一致
function defaultLoginBenefits() {
  return [
    { icon: 'coupon', text: '' },
    { icon: 'member', text: '' },
    { icon: 'transport', text: '' }
  ]
}

export default {
  name: 'mall-index',
  components: {
    RichText
  },
  data() {
    return {
      activeName: 'mall',
      benefitIcons: [
        { value: 'coupon', label: '优惠券' },
        { value: 'member', label: '会员' },
        { value: 'transport', label: '物流' },
        { value: 'redpacket', label: '红包' },
        { value: 'star', label: '精选' },
        { value: 'shield', label: '保障' },
        { value: 'service', label: '客服' }
      ],
      // 分类页版式选项。preview 是纯 SVG 骨架示意图（不含颜色，因为配色跟全局主题色走），
      // 让运营在保存前就能看出「这套换的是结构，不是颜色」。
      catThemes: [
        {
          value: 'legacy', label: '沿用旧版', desc: '左栏 + 二级分类图片宫格',
          preview: '<svg viewBox="0 0 100 64"><rect x="0" y="0" width="22" height="64" rx="2" fill="#EBEEF5"/><rect x="2" y="4" width="18" height="7" rx="1" fill="#C0C4CC"/><rect x="2" y="14" width="18" height="7" rx="1" fill="#DCDFE6"/><rect x="2" y="24" width="18" height="7" rx="1" fill="#DCDFE6"/><rect x="26" y="4" width="70" height="14" rx="2" fill="#DCDFE6"/><rect x="26" y="22" width="20" height="20" rx="2" fill="#C0C4CC"/><rect x="51" y="22" width="20" height="20" rx="2" fill="#C0C4CC"/><rect x="76" y="22" width="20" height="20" rx="2" fill="#C0C4CC"/><rect x="26" y="46" width="20" height="14" rx="2" fill="#C0C4CC"/><rect x="51" y="46" width="20" height="14" rx="2" fill="#C0C4CC"/></svg>'
        },
        {
          value: 'classic', label: '经典侧栏', desc: '窄侧栏 + 二级横滑 + 商品列表',
          preview: '<svg viewBox="0 0 100 64"><rect x="0" y="0" width="22" height="64" rx="2" fill="#EBEEF5"/><rect x="2" y="4" width="18" height="7" rx="1" fill="#C0C4CC"/><rect x="2" y="14" width="18" height="7" rx="1" fill="#DCDFE6"/><rect x="2" y="24" width="18" height="7" rx="1" fill="#DCDFE6"/><rect x="26" y="4" width="14" height="4" rx="1" fill="#909399"/><rect x="44" y="4" width="14" height="4" rx="1" fill="#DCDFE6"/><rect x="62" y="4" width="14" height="4" rx="1" fill="#DCDFE6"/><rect x="26" y="14" width="16" height="16" rx="2" fill="#C0C4CC"/><rect x="46" y="16" width="50" height="4" rx="1" fill="#DCDFE6"/><rect x="46" y="24" width="30" height="4" rx="1" fill="#DCDFE6"/><rect x="26" y="34" width="16" height="16" rx="2" fill="#C0C4CC"/><rect x="46" y="36" width="50" height="4" rx="1" fill="#DCDFE6"/><rect x="46" y="44" width="30" height="4" rx="1" fill="#DCDFE6"/></svg>'
        },
        {
          value: 'market', label: '商超图文', desc: '图标侧栏 + 二级圆标 + 双列卡',
          preview: '<svg viewBox="0 0 100 64"><rect x="0" y="0" width="26" height="64" rx="2" fill="#EBEEF5"/><circle cx="13" cy="9" r="5" fill="#C0C4CC"/><circle cx="13" cy="24" r="5" fill="#DCDFE6"/><circle cx="13" cy="39" r="5" fill="#DCDFE6"/><circle cx="36" cy="9" r="6" fill="#C0C4CC"/><circle cx="52" cy="9" r="6" fill="#DCDFE6"/><circle cx="68" cy="9" r="6" fill="#DCDFE6"/><circle cx="84" cy="9" r="6" fill="#DCDFE6"/><rect x="30" y="20" width="32" height="20" rx="2" fill="#C0C4CC"/><rect x="66" y="20" width="32" height="20" rx="2" fill="#C0C4CC"/><rect x="30" y="44" width="32" height="18" rx="2" fill="#DCDFE6"/><rect x="66" y="44" width="32" height="18" rx="2" fill="#DCDFE6"/></svg>'
        },
        {
          value: 'trend', label: '潮流卡片', desc: '无侧栏 + 顶部横滑 + 单列大卡',
          preview: '<svg viewBox="0 0 100 64"><rect x="2" y="3" width="18" height="5" rx="1" fill="#909399"/><rect x="24" y="3" width="18" height="5" rx="1" fill="#DCDFE6"/><rect x="46" y="3" width="18" height="5" rx="1" fill="#DCDFE6"/><rect x="68" y="3" width="18" height="5" rx="1" fill="#DCDFE6"/><rect x="2" y="12" width="16" height="6" rx="3" fill="#C0C4CC"/><rect x="22" y="12" width="16" height="6" rx="3" fill="#EBEEF5"/><rect x="42" y="12" width="16" height="6" rx="3" fill="#EBEEF5"/><rect x="2" y="23" width="24" height="24" rx="2" fill="#C0C4CC"/><rect x="30" y="26" width="66" height="5" rx="1" fill="#DCDFE6"/><rect x="30" y="35" width="40" height="5" rx="1" fill="#DCDFE6"/><rect x="2" y="51" width="24" height="12" rx="2" fill="#C0C4CC"/><rect x="30" y="53" width="66" height="5" rx="1" fill="#DCDFE6"/></svg>'
        },
        {
          value: 'dense', label: '密集三列', desc: '窄侧栏 + 二级双行 + 三列小卡',
          preview: '<svg viewBox="0 0 100 64"><rect x="0" y="0" width="20" height="64" rx="2" fill="#EBEEF5"/><rect x="2" y="4" width="16" height="6" rx="1" fill="#C0C4CC"/><rect x="2" y="13" width="16" height="6" rx="1" fill="#DCDFE6"/><rect x="2" y="22" width="16" height="6" rx="1" fill="#DCDFE6"/><rect x="24" y="3" width="16" height="5" rx="1" fill="#909399"/><rect x="44" y="3" width="16" height="5" rx="1" fill="#DCDFE6"/><rect x="64" y="3" width="16" height="5" rx="1" fill="#DCDFE6"/><rect x="84" y="3" width="14" height="5" rx="1" fill="#DCDFE6"/><rect x="24" y="11" width="16" height="5" rx="1" fill="#DCDFE6"/><rect x="44" y="11" width="16" height="5" rx="1" fill="#DCDFE6"/><rect x="24" y="21" width="22" height="19" rx="2" fill="#C0C4CC"/><rect x="50" y="21" width="22" height="19" rx="2" fill="#C0C4CC"/><rect x="76" y="21" width="22" height="19" rx="2" fill="#C0C4CC"/><rect x="24" y="44" width="22" height="18" rx="2" fill="#DCDFE6"/><rect x="50" y="44" width="22" height="18" rx="2" fill="#DCDFE6"/><rect x="76" y="44" width="22" height="18" rx="2" fill="#DCDFE6"/></svg>'
        }
      ],
      // 数据字段完全保持原样，未做任何修改
      form: {
        cat_page_theme: 'legacy',
        theme_color: '',
        amap_ak_api: '',
        amap_ak_js: '',
        amap_username: '',
        mch_keyword: '',
        goods_keyword: '',
        dxb_user: '',
        dxb_pass: '',
        dxb_content: '',
        dxb_sign: '',
        sms_day_limit: '10',
        default_sms_code: '',
        is_dev: '0',
        privacy_url: '',
        protocol_url: '',
        company: '',
        show_in_goods_pic: '',
        app_name: '',
        app_short_name: '',
        is_show_addr_recognize: '0',
        address_location_enabled: '0',
        sms_type: 'aliyun',
        tx_sdk_app_id: '',
        tx_secret_id: '',
        tx_secret_key: '',
        tx_template_id: '',
        tx_sign_name: '',
        show_follow_banner: '0',
        mpwx_use_wechat_info: '0',
        avatar_url: '',
        share_title: '',
        share_desc: '',
        share_pic: '',
        nickname: '',
        baidu_ak_id: '',
        goods_property: '',
        baidu_ak: '',
        is_transfer_password: '0',
        is_balance_pay_password: '0',
        payment_body: '',
        is_must_parent_id: '0',
        default_parent_id: '',
        support_pickup: '0',
        pickup_address_type: 'platform',
        pickup_address: '',
        pickup_name: '',
        pickup_lat: '',
        pickup_lon: '',
        is_ban_cancel: '0',
        buy_level_limit_msg: '',
        buy_limit_num_msg: '',
        is_show_add_cart: '0',
        collect_birthday: '0',
        collect_gender: '0',
        birthday_editable: '0', // 生日填写后能否自改:默认锁定(沿用历史行为)
        gender_editable: '1',   // 性别填写后能否自改:默认可改(沿用历史行为)
        address: '',
        lat: '',
        lon: '',
        is_login_mobile: '0',
        reg_need_sms_code: '1',
        reg_need_pay_password: '0',
        access_key_id: '',
        access_key_secret: '',
        sign_name: '',
        about: '',
        nav_icon: '',
        show_nav: 0,
        logo: '',
        detail: '',
        name: '',
        tel: '',
        is_binding: '0',
        login_expire_days: 30,
        is_home_login: '0',
        home_login_title: '',
        home_login_benefits: defaultLoginBenefits(),
        login_pic: '',
        auto_confirm_day: '',
        over_time: 3,
        order_settle_day: '0',
        receive_mobile: '',
        receive_address: '',
        receive_name: '',
        EBusinessID: '',
        AppKey: '',
        file_location: '',
        contact_icon: '',
        phone_icon: '',
        show_contact: '0',
        show_phone: '0',
        bg_music_open: '0',
        bg_music_url: '',
        bg_music_volume: '60',
        bg_music_start: '',
        bg_music_end: '',
        wx_notify_enabled: '0',
        sms_tpl_code: '',
        sms_tpl: '',
        is_must_avatar: '0',
        mpwx_tpl_ids: '',
        user_protocol: '',
        privation_protocol: '',
        bd_addr_appid: '',
        bd_addr_apikey: '',
        bd_addr_secret: ''
      }
    }
  },
  created() {
    this.getSetting()
  },
  computed: {
    // 播放时间段:日期区间控件 ↔ 存成 bg_music_start / bg_music_end 两个字段
    bgMusicRange: {
      get() {
        return this.form.bg_music_start && this.form.bg_music_end ? [this.form.bg_music_start, this.form.bg_music_end] : null
      },
      set(v) {
        this.form.bg_music_start = v ? v[0] : ''
        this.form.bg_music_end = v ? v[1] : ''
      }
    }
  },
  methods: {
    onPickMusic(files) {
      if (files && files[0] && files[0].url) this.form.bg_music_url = files[0].url
    },
    save() {
      this.$request({
        url: '/mall/mall/setting',
        data: { setting: this.form },
        method: 'post'
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg)
          // 保存后同步更新侧边栏 logo
          this.$store.commit('SET_MALL_LOGO', this.form.logo || '')
        }
      })
    },
    getSetting() {
      this.$request({
        url: '/mall/mall/setting'
      }).then(res => {
        if (res.code == 0) {
          this.form = Object.assign({}, this.form, res.data.setting)
          // 存的是字符串,el-input-number 要数字
          this.form.login_expire_days = parseInt(this.form.login_expire_days, 10) || 30
          // 登录好处固定 3 行:库里存的可能是空数组或不足 3 条,补齐后才好编辑
          const saved = Array.isArray(this.form.home_login_benefits) ? this.form.home_login_benefits : []
          this.form.home_login_benefits = defaultLoginBenefits().map((d, i) => Object.assign(d, saved[i] || {}))
          // 加载成功后同步商城 logo 到 Vuex，供侧边栏使用
          this.$store.commit('SET_MALL_LOGO', res.data.setting.logo || '')
        }
      })
    }
  }
}
</script>

<style scoped lang="scss">
.header-title {
  font-size: 16px;
  font-weight: bold;
}

.pane-title {
  font-size: 15px;
  font-weight: bold;
  color: #303133;
  padding-bottom: 15px;
  margin-bottom: 20px;
  border-bottom: 1px solid #ebeef5;
}

/* 同一个 tab 里的第二段起,和上一段拉开距离 */
.pane-title:not(:first-child) {
  margin-top: 36px;
}

.sub-setting-block {
  background-color: #f8fcfd;
  padding: 20px 0;
  border-radius: 4px;
  margin-top: 10px;
}

/* 宽度控制工具类 */
.w-300 {
  width: 300px;
}

.w-400 {
  width: 400px;
}

.w-500 {
  width: 500px;
}

.w-600 {
  width: 600px;
}

/* 提示文字样式 */
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
  margin-top: 6px;
}

.cat-theme-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  line-height: 1.4;
}

.cat-theme-card {
  width: 168px;
  padding: 10px;
  border: 1px solid #DCDFE6;
  border-radius: 6px;
  cursor: pointer;
  transition: border-color .2s, box-shadow .2s;

  &:hover {
    border-color: #C0C4CC;
  }

  &.active {
    border-color: var(--admin-accent-text);
    box-shadow: 0 0 0 1px var(--admin-accent-text) inset;
  }
}

.cat-theme-preview {
  background: #F5F7FA;
  border-radius: 4px;
  padding: 6px;

  ::v-deep svg {
    display: block;
    width: 100%;
    height: auto;
  }
}

.cat-theme-name {
  margin-top: 8px;
  font-size: 14px;
  color: #303133;
}

.cat-theme-desc {
  margin-top: 2px;
  font-size: 12px;
  color: #909399;
}

.form-tip.text-warning {
  color: #E6A23C;
}

.form-tip.text-danger {
  color: #F56C6C;
}

/* 统一手动上传组件样式 */
.upload-wrapper {
  .preview-box {
    width: 88px;
    height: 88px;
    border: 1px solid #e2e2e2;
    position: relative;
    border-radius: 4px;
    overflow: hidden;

    img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .del-btn {
      position: absolute;
      top: 0;
      right: 0;
      width: 20px;
      height: 20px;
      background: rgba(0, 0, 0, 0.5);
      color: #fff;
      text-align: center;
      line-height: 20px;
      cursor: pointer;

      &:hover {
        background: #F56C6C;
      }
    }
  }

  .upload-btn {
    width: 88px;
    height: 88px;
    border: 1px dashed #d9d9d9;
    border-radius: 4px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 24px;
    color: #8c939d;
    cursor: pointer;

    &:hover {
      border-color: var(--admin-accent-text);
      color: var(--admin-accent-text);
    }
  }
}

/* 表单项下方补充说明 */
.form-tips {
  font-size: 12px;
  color: #909399;
  line-height: 1.6;
  margin-top: 4px;
}
.benefit-row { display: flex; align-items: center; margin-bottom: 8px; }
</style>
