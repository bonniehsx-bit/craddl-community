<?php
/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * 官网：https://www.craddl.cn
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the
 * “License”); you may not use this file except in compliance with the License. You may obtain a
 * copy of the License from LICENSE in the source package or
 * https://opensource.org/license/cpal-1.0. The License is based on the Mozilla Public License
 * Version 1.1 but Sections 14 and 15 have been added to cover use of software over a computer
 * network and provide for limited attribution for the Original Developer. In addition, Exhibit A
 * has been modified to be consistent with Exhibit B.
 * Software distributed under the License is distributed on an “AS IS” basis, WITHOUT WARRANTY OF
 * ANY KIND, either express or implied. See the License for the specific language governing rights
 * and limitations under the License.
 * The Original Code is Craddl Community Edition.
 * The Original Developer is the Initial Developer.
 * The Initial Developer of the Original Code is 佛山今日鼎盛信息科技有限公司. All portions of the code written
 * by 佛山今日鼎盛信息科技有限公司 are Copyright (c) 2026. All Rights Reserved.
 */

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\PaymentDescriptionHelper;
use app\helpers\ServerHelper;
use app\models\BaseModel;
use app\models\ExpressSetting;
use app\models\Mall;

class MallSettingForm extends BaseModel
{
    // [优化] 只保留一个属性，用于接收前端发来的所有设置
    public $setting;

    public function rules()
    {
        // [优化] 规则也相应简化
        return [
            [['setting'], 'safe'],
            [['setting'], 'validatePaymentDescription', 'skipOnEmpty' => false],
        ];
    }

    /** 校验新增的支付描述；旧客户端未提交此字段时保留已有配置。 */
    public function validatePaymentDescription($attribute)
    {
        if (!is_array($this->setting)) {
            $this->addError($attribute, '设置参数不正确');
            return;
        }
        if (!array_key_exists('payment_body', $this->setting)) {
            return;
        }
        if (!is_string($this->setting['payment_body'])) {
            $this->addError($attribute, '支付商品描述必须为文字');
            return;
        }
        $value = PaymentDescriptionHelper::normalize($this->setting['payment_body']);
        if (!PaymentDescriptionHelper::isValid($value)) {
            $this->addError($attribute, '支付商品描述请使用单行文字，且不超过127个字符');
            return;
        }
        $this->setting['payment_body'] = $value;
    }

    /**
     * [新增] 获取所有设置项的默认值。
     * 这里的字段和默认值与您 Vue 组件中的 form 对象完全对应。
     * @return array
     */
    private function getDefaultSettings()
    {
        // 从 Vue 组件的 data() 中复制并转换为 PHP 数组
        return [
            'mch_keyword' => '',
            'goods_keyword' => '',
            'amap_username' => '',
            'theme_color' => '#F7931E',
            'amap_ak_api' => '',
            'amap_ak_js' => '',
            'dxb_user' => '',
            'dxb_pass' => '',
            'dxb_content' => '',
            'dxb_sign' => '',
            'default_sms_code' => '',
            'is_dev' => '0',
            // 同一手机号每天最多获取几次验证码。原先只有「180 秒内不能重发」和
            // 「同 IP 10 次/小时」,换个 IP 就能绕开后者,单个号一天理论上能收 480 条 ——
            // 既是骚扰也是实打实的短信费。0 表示不限制。
            'sms_day_limit' => '10',
            'privacy_url' => ServerHelper::getHost() . '/index.php?r=/site/privacy&mall_id=' . $this->mall_id,
            'protocol_url' => ServerHelper::getHost() . '/index.php?r=/site/protocol&mall_id=' . $this->mall_id,
            'company' => '',
            'app_name' => '',
            'app_short_name' => '',
            'is_show_addr_recognize' => '0',
            'sms_type' => 'aliyun',
            'tx_sdk_app_id' => '',
            'tx_secret_id' => '',
            'tx_secret_key' => '',
            'tx_template_id' => '',
            'tx_sign_name' => '',
            'show_follow_banner' => '0',
            'mpwx_use_wechat_info' => '0',
            'avatar_url' => '',
            'share_title' => '',   // 全局分享标题;留空回退商城名称
            'share_desc'  => '',   // 全局分享描述(微信卡片标题下那行小字);留空回退商城简介
            'share_pic'   => '',   // 全局分享图;留空回退商城 LOGO。微信要求 ≥300×300 且非 webp
            'nickname' => '',
            'baidu_ak_id' => '',
            'goods_property' => '',
            'baidu_ak' => '',
            'is_must_parent_id' => '0',
            'default_parent_id' => '', // 默认推荐人ID:注册时无邀请来源(x-parent-id)则默认绑定此上级;留空=不默认绑定
            'support_pickup' => '0',   // 平台自营订单是否支持到店自提(per-mall,默认关);商家单沿用商家 delivery_options,不受此开关影响
            'pickup_address_type' => 'platform', // 平台自提地址来源:platform=平台自提点 | user_address=用户默认收货地址(无则回退自提点)
            'pickup_address' => '',    // 平台自提点地址(留空回退商城 address)
            'pickup_name' => '',       // 平台自提点名称(留空回退商城名)
            'pickup_lat' => '',        // 平台自提点纬度(留空回退商城 lat)
            'pickup_lon' => '',        // 平台自提点经度(留空回退商城 lon)
            'is_ban_cancel' => '0',
            'buy_level_limit_msg' => '您的用户等级不可购买商品: {name}', // 交易规则:等级限购提示文案(占位符 {name})
            'buy_limit_num_msg' => '商品：{name}，超出了购买限制(限购{limit}件，已购{history}件)', // 交易规则:限购提示文案(占位符 {name}/{limit}/{history})
            'is_show_add_cart' => '0',
            'collect_birthday' => '0', // 完善资料弹窗是否采集「生日」(per-mall,默认关)
            'collect_gender' => '0',   // 完善资料弹窗是否采集「性别」(per-mall,默认关)
            // 采集到之后能否由用户【自行修改】:0=锁定(仅后台/客服可改) 1=用户可自行修改。
            // 默认值保持各自历史行为不变:生日原本填写即锁定→0;性别原本可随时改→1。
            // 锁定时前端「完善资料」弹窗对已填字段直接隐藏(不展示只读态),后端 UserHelper 同步拦截。
            'birthday_editable' => '0',
            'gender_editable' => '1',
            'address' => '',
            'lat' => '',
            'lon' => '',
            'is_login_mobile' => '0',
            'reg_need_sms_code' => '1', // 注册是否需要短信验证码校验手机号(per-mall,默认需要);关闭=注册不校验手机号真伪
            'reg_need_pay_password' => '0', // 注册是否必须设置6位数字支付密码(per-mall,默认否)
            'login_expire_days' => '30', // 登录有效期(天):连续这么多天没打开才需要重新登录,用着就自动续期(UserService::renewToken),1-365
            'access_key_id' => '',
            'access_key_secret' => '',
            'sign_name' => '',
            'about' => '',
            'nav_icon' => '',
            'show_nav' => '0',
            'logo' => '',
            'detail' => '',
            'name' => '',
            'tel' => '',
            'is_binding' => '0',
            'is_home_login' => '0',
            // 首页登录弹窗(jx-login-sheet)的文案:标题 + 最多 3 条「图标 + 短句」;留空用前端默认
            'home_login_title' => '',
            'home_login_benefits' => [],
            'login_pic' => '',
            'auto_confirm_day' => '7',
            'over_time' => '30',
            'order_settle_day' => '1',
            'receive_mobile' => '',
            'receive_address' => '',
            'receive_name' => '',
            'EBusinessID' => '',
            'AppKey' => '',
            'file_location' => 'local',
            'contact_icon' => '',
            'phone_icon' => '',
            'show_contact' => '0',
            // 背景音乐:进小程序循环播放(节日氛围)。默认关;可设生效时间段,过了自动不放
            'bg_music_open' => '0',
            'bg_music_url' => '',
            'bg_music_volume' => '60',
            'bg_music_start' => '',
            'bg_music_end' => '',
            'show_phone' => '0',
            'sms_tpl_code' => '',
            'sms_tpl' => '',
            'is_must_avatar' => '0',
            'mpwx_tpl_ids' => '',
            'user_protocol' => '',
            'privation_protocol' => '',
            'bd_addr_appid' => '',
            'bd_addr_apikey' => '',
            'bd_addr_secret' => '',
            'show_in_goods_pic' => '',
            'is_transfer_password' => '0',
            'is_balance_pay_password' => '0',
            'payment_body' => '', // 支付商品描述：留空保留各支付入口的默认文案
            'wx_notify_enabled' => '0',
            // 是否允许用户在地址编辑页选择地理位置(经纬度)。同城配送依赖用户地址经纬度算距离,
            // 需要同城配送的商城应开启;0=关闭(隐藏该字段),1=开启。
            'address_location_enabled' => '0',
            // 分类页版式主题。legacy=沿用旧版分类页(左栏 + 二级分类图片宫格,要再点一层才到商品),
            // 默认值就是它 —— 已上线的商城不会因为这次改动换页面。
            // classic/market/trend/dense = 四套新版式(淘宝式:右侧顶部二级横滑,下面直接出商品)。
            // ⚠ 这里只选**骨架**,配色一律跟商城全局主题色(theme_color),不单独配 ——
            //    否则同一个商城会出现分类页和其它页面两套颜色。
            'cat_page_theme' => 'legacy',
        ];
    }

    /**
     * [重构] 读取设置
     * @return array
     */
    public function search()
    {
        $defaults = $this->getDefaultSettings();
        $savedSettings = OptionHelper::get('mall_setting', \Yii::$app->mall->id, []);

        // [核心改造] 将保存的设置覆盖到默认设置上，确保所有字段都存在
        $setting = array_merge($defaults, $savedSettings);

        // 从 mall 表读取最新的核心信息，覆盖掉可能过时的保存值
        $mall = \Yii::$app->mall;
        $setting['name'] = $mall->name;
        $setting['logo'] = $mall->logo;
        $setting['detail'] = $mall->detail;

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['setting' => $setting]);
    }

    /**
     * [重构] 保存设置
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $incomingSetting = $this->setting;

        // 默认推荐人:保存时就校验,别等到注册时静默绑不上 ——
        // 那时运营完全不知道,新人也不知道,拉新链路白跑。
        // 口径与 UserService::tryBindParent 一致(本商城 + 未注销 + 未拉黑 + 具备推广资格)。
        $dp = (int)($incomingSetting['default_parent_id'] ?? 0);
        if ($dp > 0) {
            $usable = \app\models\User::find()
                ->where([
                    'id'         => $dp,
                    'mall_id'    => \Yii::$app->mall->id,
                    'is_delete'  => 0,
                    'status'     => \app\models\User::STATUS_NORMAL,
                    'is_inviter' => 1,
                ])
                ->exists();
            if (!$usable) {
                return $this->apiResponse(
                    ApiCode::CODE_FAIL,
                    "默认推荐人 ID {$dp} 不可用:需为本商城、未注销、未拉黑、且已开启推广员资格的用户"
                );
            }
        }

        // 登录有效期:夹到 1-365 天,填空/乱填回落默认 30
        if (array_key_exists('login_expire_days', $incomingSetting)) {
            $days = (int) $incomingSetting['login_expire_days'];
            $incomingSetting['login_expire_days'] = (string) ($days < 1 ? 30 : min(365, $days));
        }

        // 首页登录弹窗的好处:最多 3 条,图标只认弹窗里有的那几个,短句截到 8 个字(弹窗一行放得下)
        if (array_key_exists('home_login_benefits', $incomingSetting)) {
            $incomingSetting['home_login_benefits'] = self::cleanLoginBenefits($incomingSetting['home_login_benefits']);
        }
        if (array_key_exists('home_login_title', $incomingSetting)) {
            $incomingSetting['home_login_title'] = mb_substr(trim((string) $incomingSetting['home_login_title']), 0, 20);
        }

        // 背景音乐:音量夹到 10-100;日期只认 YYYY-MM-DD,填反了就对调
        if (array_key_exists('bg_music_volume', $incomingSetting)) {
            $incomingSetting['bg_music_volume'] = (string) max(10, min(100, (int) $incomingSetting['bg_music_volume']));
        }
        foreach (['bg_music_start', 'bg_music_end'] as $k) {
            if (array_key_exists($k, $incomingSetting)) {
                $v = trim((string) $incomingSetting[$k]);
                $incomingSetting[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
            }
        }
        if (!empty($incomingSetting['bg_music_start']) && !empty($incomingSetting['bg_music_end'])
            && $incomingSetting['bg_music_start'] > $incomingSetting['bg_music_end']) {
            [$incomingSetting['bg_music_start'], $incomingSetting['bg_music_end']] = [$incomingSetting['bg_music_end'], $incomingSetting['bg_music_start']];
        }
        if (($incomingSetting['bg_music_open'] ?? '0') === '1' && trim((string) ($incomingSetting['bg_music_url'] ?? '')) === '') {
            return $this->apiResponse(ApiCode::CODE_FAIL, '开启背景音乐需要先选择音乐文件');
        }

        $mall = Mall::findOne(\Yii::$app->mall->id);

        // --- 1. 处理需要直接存入 mall 表的特殊字段 ---
        $mall->name = $incomingSetting['name'] ?? '默认商城名';
        $mall->logo = $incomingSetting['logo'] ?? '';
        $mall->detail = $incomingSetting['detail'] ?? '';
        if (!$mall->save()) {
            return $this->responseErrorMsg($mall);
        }

        // --- 2. 处理快递鸟等独立设置 ---
        if (!empty($incomingSetting['EBusinessID']) && !empty($incomingSetting['AppKey'])) {
            ExpressSetting::set(\Yii::$app->mall->id, $incomingSetting['EBusinessID'], $incomingSetting['AppKey']);
        }

        // --- 3. [核心改造] 保存通用设置到 option 表 ---
        $currentSettings = OptionHelper::get('mall_setting', \Yii::$app->mall->id, []);
        $defaults = $this->getDefaultSettings();

        // 将新提交的设置，覆盖到现有的设置上
        $newSettings = array_merge($currentSettings, $incomingSetting);
        // 再与默认值合并，确保数据完整性，并过滤掉不再需要的旧字段
        $finalSettings = array_intersect_key($newSettings, $defaults);

        OptionHelper::set('mall_setting', $finalSettings, \Yii::$app->mall->id);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /** 首页登录弹窗能用的图标(ThorUI 名),和后台下拉、C 端 jx-login-sheet 一致 */
    const LOGIN_BENEFIT_ICONS = ['coupon', 'member', 'transport', 'redpacket', 'star', 'shield', 'service'];

    public static function cleanLoginBenefits($raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $b) {
            if (!is_array($b)) continue;
            $icon = in_array($b['icon'] ?? '', self::LOGIN_BENEFIT_ICONS, true) ? $b['icon'] : 'star';
            $out[] = ['icon' => $icon, 'text' => mb_substr(trim((string) ($b['text'] ?? '')), 0, 8)];
            if (count($out) >= 3) break;
        }
        return $out;
    }
}