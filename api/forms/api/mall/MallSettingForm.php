<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-17
 * Time: 16:59
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

namespace app\forms\api\mall;


use app\core\ApiCode;
use app\helpers\ImageHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\StringHelper;
use app\helpers\VersionHelper;
use app\models\BaseModel;

class MallSettingForm extends BaseModel
{
    public $type;

    public function rules()
    {
        return [
            [['type'], 'string']
        ];
    }

    public function getAbout()
    {
        $res = OptionHelper::get('mall_setting', $this->mall_id);
        if (!$res) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未设置');
        }
        if (empty($res['about'])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未设置关于内容');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['about' => $res['about']]);
    }

    /**
     * 不可下发给前端的敏感字段黑名单。
     * 以后新增普通开关/配置字段时，不需要改这里，字段会自动透传前端。
     * 只有新增的字段本身是敏感的（密钥、密码等），才需要加到这里。
     */
    private const SENSITIVE_KEYS = [
        // SMS 服务商凭证
        'access_key_id', 'access_key_secret', 'sign_name', 'sms_tpl_code', 'sms_tpl',
        'tx_sdk_app_id', 'tx_secret_id', 'tx_secret_key', 'tx_sign_name', 'tx_template_id',
        'dxb_user', 'dxb_pass', 'dxb_content', 'dxb_sign', 'sms_type',
        // 地图 API Key（服务端调用，不暴露给客户端）
        'amap_ak_api', 'amap_ak_js', 'amap_username',
        'baidu_ak_id', 'baidu_ak', 'bd_addr_appid', 'bd_addr_apikey', 'bd_addr_secret',
        // 快递鸟 API
        'EBusinessID', 'AppKey',
        // 退货收货地址（商家隐私）
        'receive_name', 'receive_mobile', 'receive_address',
        // 文件存储策略（纯后台）
        'file_location',
        // 开发 / 安全
        'default_sms_code', 'is_dev',
        // 协议全文太大，有专用接口 /api/mall/user-protocol
        'user_protocol', 'privation_protocol',
        // 商品属性模板（纯后台）
        'goods_property',
    ];

    /**
     * 获取商城配置（黑名单过滤敏感字段，其余全部透传前端）
     *
     * 以后新增普通开关 / 配置字段，只需要在后台保存侧的
     * forms/mall/mall/MallSettingForm::getDefaultSettings() 里声明即可，这里无需改动。
     * @return array
     */
    public function getSetting()
    {
        $res = OptionHelper::get('mall_setting', $this->mall_id);
        if (!$res) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未设置');
        }

        // 平台信息（独立存储，脱敏后附加）
        $mpwx   = OptionHelper::get('platform_mpwx',  $this->mall_id);
        $wxapp  = OptionHelper::get('platform_wxapp',  $this->mall_id);
        $wechat = OptionHelper::get('platform_wechat', $this->mall_id);
        $this->sanitizePlatform($mpwx);
        $this->sanitizePlatform($wxapp);
        $this->sanitizePlatform($wechat);

        // 黑名单过滤：移除敏感字段，剩余字段全部下发
        $setting = array_diff_key($res, array_flip(self::SENSITIVE_KEYS));

        // 个别字段需要特殊处理
        $setting['theme_color']  = $res['theme_color'] ?? '#F7931E';  // 保底默认值
        // 首页登录弹窗:审核版本一律不弹。后台那行「审核期间请勿开启」靠人记容易忘,
        // 审核员一进来就被要求登录容易被打回;这里按审核版本号自动关掉,不用运营来回切
        $setting['is_home_login'] = (string) ($res['is_home_login'] ?? '0') === '1'
            && !VersionHelper::checkIsAuditMode($this->mall_id) ? '1' : '0';
        // 地址编辑「选择地理位置」开关(同城配送用);未配置的商城默认关,uniapp 据此显隐
        $setting['address_location_enabled'] = (int)($res['address_location_enabled'] ?? 0);
        $setting['mpwx_tpl_ids'] = isset($res['mpwx_tpl_ids'])
            ? StringHelper::explodeByComma($res['mpwx_tpl_ids'])
            : [];

        // 附加平台信息和词库（不在 mall_setting 里，单独注入）
        $setting['mpwx']   = $mpwx;
        $setting['wxapp']  = $wxapp;
        $setting['wechat'] = $wechat;
        $setting['nouns']  = $this->getNounList();
        // 设置页菜单跟着商城配置一起下发:C 端零额外请求、进页面就有,
        // 不会先渲染一份内置清单再被配置覆盖一次(那样会闪一下)。
        // ⚠ 必须注入到 $setting **里面**,不能挂在响应顶层 ——
        //   store/modules/mall.js 只存 `payload.setting`,顶层字段会被直接丢掉,
        //   而且不报错:前端 $util.getSetting('setting_page') 永远拿到空,
        //   表现就是"后台怎么配都不生效"。nouns/mpwx 也是同样注入进来的。
        $setting['setting_page'] = \app\forms\mall\mall\SettingPageSettingForm::resolved($this->mall_id);
        // 插件清单可能拿不到(无商城上下文/查询异常),那种情况【不下发这个键】,
        // C 端据此维持既有行为 —— 见 getPluginNames() 的说明
        $plugins = $this->getPluginNames();
        if ($plugins !== null) {
            $setting['plugins'] = $plugins;
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'setting'           => $setting,
            'tabbar'            => $this->getTabbar(),
            'checking_versions' => $this->getCheckingVersions(),
        ]);
    }

    /**
     * 本商城可用的插件名清单,随商城配置一起下发(不额外增加请求)。
     *
     * C 端据此判断「这个插件在不在」,不在就别去调它的接口 ——
     * `plugin/*` 路由解析不到会抛 NotFoundHttpException(404),
     * 而 uniapp 的 http 封装对 4xx 一律弹「服务错误(404)」。
     * 表现就是:没装该插件的客户每次 onShow 都被弹一次错,而这功能他本来就用不上。
     *
     * 口径:**授权给本商城的插件才算**(mall_plugin),不是「服务器上有这个目录」——
     * 多租户下 A 商城授权了不代表 B 也授权,按目录判会把别人的插件算成自己的。
     * 这里直接复用 Plugin::resolveAuthorizedPluginNames(),与插件监听器注册同源,
     * 避免两处各写一份授权口径、日后改一处漏一处。
     *
     * 再加一道文件存在性过滤:授权表可能残留「孤儿记录」(插件文件被删,
     * 或该客户的部署包压根没带这个目录)。只信 DB 的话照样会去调一个 404 的接口,
     * 而这正是本次要修的场景。
     *
     * @return string[]|null null = 无从判断(没有商城上下文或查询失败),此时不下发该字段,
     *                       C 端按老样子照调 —— 宁可多弹一次错,也不能把功能误关掉
     */
    private function getPluginNames(): ?array
    {
        $authorized = \Yii::$app->plugin->resolveAuthorizedPluginNames();
        if ($authorized === null) {
            return null;
        }

        $basePath = \Yii::$app->basePath . '/plugins/';
        $names = array_unique(array_filter(array_map('strval', $authorized)));
        return array_values(array_filter($names, function ($name) use ($basePath) {
            return file_exists($basePath . $name . '/Plugin.php');
        }));
    }

    /**
     * [辅助方法] 引用传递，直接移除数组中的敏感键值
     * @param $data
     */
    /**
     * [辅助方法] 平台配置下发前的字段裁剪 —— **白名单**,引用传递直接改数组。
     *
     * 原实现是黑名单(只 unset secret/key/cert 等 6 个键),问题是:
     *  · 公众号的 `token` 与 `aes_key` 没被删 —— 只要商城开了消息加解密(安全模式)这两项就有值,
     *    等于把「消息验签 token + 加解密密钥」下发给任意 C 端客户端。拿到 token 就能伪造出
     *    能通过签名校验的公众号回调(签名=sha1(排序(token,timestamp,nonce)));
     *  · 小程序的 `upload_key` 同理未删;
     *  · 更根本的是**黑名单会腐烂**:平台配置表单以后每加一个字段,默认就是对外泄漏,
     *    要靠人记得回来补 unset。
     *
     * 改成白名单后默认不下发,新增字段不会意外暴露。
     * 已核实 C 端(uniapp)与后台(admin)**都没有消费** setting.wechat/mpwx/wxapp 的任何字段,
     * 这里保留 appid/name 只为兼容可能的历史调用方,其余一律不发。
     */
    private function sanitizePlatform(&$data)
    {
        if (empty($data) || !is_array($data)) {
            $data = []; // 为空时给空数组,防止前端取值报错
            return;
        }
        // 仅这些可以出现在 C 端响应里:appid 是公开标识,name 是展示用名称
        $allow = ['appid', 'name'];
        $data = array_intersect_key($data, array_flip($allow));
    }

    public function getTabbar()
    {
        if (VersionHelper::checkIsAuditMode($this->mall_id)) {
            return $this->getDefaultTabbar();
        }
        $userPageData = OptionHelper::get('user_center_setting', \Yii::$app->mall->id);

        // 增加容错，防止 user_center_setting 为空时报错
        if (!$userPageData || !isset($userPageData['tabbar'])) {
            $tabbar = $this->getDefaultTabbar();
        } else {
            $tabbar = $userPageData['tabbar'];
        }

        // [新增] 如果配置了主题色，这里其实不需要改，因为前端 tui-page 组件会接管颜色
        // 但为了兼容性，确保 tabbar 的结构是完整的
        return $tabbar;
    }

    /**
     * 下发词库给前端 —— 对完整字典（核心 + 已启用插件）的每个 key
     * 输出"最终值"：商户已配置值优先，否则取默认词。
     * 这样 uniapp $noun('aicare.point') 无需本地登记插件默认词，后端兜底。
     */
    public function getNounList()
    {
        // 唯一实现在 NounHelper::resolvedMap（后台 admin 全局 $noun 也走它），避免两套合并逻辑漂移
        return \app\helpers\NounHelper::resolvedMap($this->mall_id);
    }


    public function getCheckingVersions()
    {
        $versions = OptionHelper::get('checking_versions', $this->mall_id);
        if ($versions && isset($versions['is_enabled']) && $versions['is_enabled'] == 1) {
            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $versions);
        }
        return ResponseHelper::send(ApiCode::CODE_FAIL, '');
    }

    private function getDefaultTabbar()
    {
        // 默认红色
        return [
            'color' => '#E41F19',
            'list' => [
                [
                    'name' => '首页',
                    'url' => '/pages/index/index',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/index.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/index-active.png')
                ],
                [
                    'name' => '分类',
                    'url' => '/others/new-cat-goods/new-cat-goods',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/cat.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/cat-active.png')
                ],
                [
                    'name' => '购物车',
                    'url' => '/others/cart/cart',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/cart.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/cart-active.png')

                ],
                [
                    'name' => '我的',
                    'url' => '/others/user/user',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/my.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/my-active.png')
                ],
            ]
        ];
    }

    public function getUserProtocol()
    {
        $res = OptionHelper::get('mall_setting', $this->mall_id);
        if (!$res) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未设置');
        }
        $protocol = '';
        if (!$this->type || $this->type == 'register') {
            if (empty($res['user_protocol'])) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '未设置注册协议');
            }
            $protocol = $res['user_protocol'];
        } else {
            if (empty($res['privation_protocol'])) {
                // 如果没有隐私协议，尝试返回空或者给个提示，不直接报错
                return $this->apiResponse(ApiCode::CODE_FAIL, '未设置隐私协议');
            }
            $protocol = $res['privation_protocol'];
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['user_protocol' => $protocol]);
    }
}
