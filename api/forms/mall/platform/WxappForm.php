<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-14
 * Time: 9:45
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

namespace app\forms\mall\platform;


use app\core\ApiCode;
use app\helpers\ConstantHelper;
use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\models\BaseModel;


class WxappForm extends BaseModel
{
    public $appid;
    public $secret;
    public $name;
    public $wxapp_pay_mpwx_appid;
    public $use_payment;
    public $h5_pay_logo;
    public $h5_pay_title;
    public $h5_pay_sub_title;

    // [新增] 借用小程序支付所需参数
    public $mp_id;
    public $mp_type;

    // [新增] iOS Universal Link 关联所需(只有这两个是变量,其余全是模板)
    public $ios_team_id;    // 苹果 Team ID(应用ID,appID 前半)
    public $ios_bundle_id;  // App Bundle ID(包名,appID 后半)


    public function rules()
    {
        return [
            [['use_payment', 'mp_type'], 'integer'], // [修改] mp_type 也是整数
            [['appid', 'wxapp_pay_mpwx_appid'], 'string', 'max' => 64],
            [['secret'], 'string', 'max' => 255],
            [['name'], 'string', 'max' => 45],
            [['h5_pay_logo', 'h5_pay_title', 'h5_pay_sub_title'], 'string', 'max' => 1024],

            // [新增] 借用小程序支付参数的验证规则
            [['mp_id'], 'string', 'max' => 64],

            // [新增] iOS Universal Link:Team ID(应用ID) + Bundle ID(包名)
            [['ios_team_id', 'ios_bundle_id'], 'string', 'max' => 128],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->appid || !$this->secret || !$this->name) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请完善必填信息');
        }

        // 检查当借用小程序支付时，必要字段是否填写
        if ($this->use_payment == '2') { // '2' 代表借用小程序支付
            if (empty($this->mp_id) || empty($this->wxapp_pay_mpwx_appid)) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '借用小程序支付时，必须填写“小程序原始ID”和“小程序AppID”');
            }
        }

        OptionHelper::set('platform_wxapp', $this->attributes, \Yii::$app->mall->id);
        OptionHelper::set($this->appid, ['platform' => ConstantHelper::PLATFORM_APP, 'mall_id' => $this->mall_id], \Yii::$app->mall->id);

        // [新增] 按 (Team ID.Bundle ID) 生成/更新本商城的 iOS Universal Link 关联文件
        $this->generateAasa();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }


    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $wxapp = OptionHelper::get('platform_wxapp', \Yii::$app->mall->id,  $this->getDefaults());

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'wxapp' => $wxapp,
            // 本商城 iOS Universal Link 关联文件的最终地址(保存后按 appID 生成到该地址)
            'aasa_url' => rtrim(ServerHelper::getHost(), '/') . '/' . self::aasaRelPath(),
        ]);
    }

    public function saveSettingByMallId($mall_id)
    {
        if (!$this->validate()) {
            return false;
        }
        return OptionHelper::set('platform_wxapp', $this->attributes, $mall_id);
    }

    /**
     * AASA 文件相对 web 根的【固定】路径。iOS(14+ 走苹果 CDN)只认
     * <域名>/.well-known/apple-app-site-association,不能放子目录。
     * 落盘 = @app/web/{此路径};最终 URL = 域名 + '/' + {此路径}。
     */
    public static function aasaRelPath(): string
    {
        return '.well-known/apple-app-site-association';
    }

    /**
     * 生成/更新站点的 apple-app-site-association(落到 .well-known/,取当前商城配置)。
     * 模板取 @app/web/apple-app-site-association,只把占位 "ios应用ID.包名" 替换成真实 {Team ID}.{Bundle ID},其余全部原样。
     * 两个变量任一为空则跳过(不写出无效 appID 文件)。
     * 注:一套后端挂多商城时此文件会互相覆盖(极少数场景,暂不处理)。
     */
    private function generateAasa(): void
    {
        $team = trim((string)$this->ios_team_id);
        $bundle = trim((string)$this->ios_bundle_id);
        if ($team === '' || $bundle === '') {
            return;
        }
        try {
            $tplPath = \Yii::getAlias('@app/web/apple-app-site-association');
            $tpl = is_file($tplPath) ? (string)file_get_contents($tplPath) : '';
            // 模板缺失或占位已被改掉 → 用内置结构兜底,保证一定含占位
            if ($tpl === '' || strpos($tpl, 'ios应用ID.包名') === false) {
                $tpl = "{\n    \"applinks\": {\n        \"apps\": [],\n        \"details\": [\n            {\n                \"appID\": \"ios应用ID.包名\",\n                \"paths\": [ \"/ulink/*\"]\n            }\n        ]\n    }\n}";
            }
            $content = str_replace('ios应用ID.包名', $team . '.' . $bundle, $tpl);

            $dest = \Yii::getAlias('@app/web/') . self::aasaRelPath();
            $dir = dirname($dest);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                \Yii::error('[AASA] 目录创建失败: ' . $dir);
                return;
            }
            file_put_contents($dest, $content);
        } catch (\Throwable $e) {
            \Yii::error('[AASA] 生成失败: ' . $e->getMessage());
        }
    }

    /**
     * [新增] 提供默认值，防止前端获取配置时因缺少字段而报错
     * @return array
     */
    private function getDefaults()
    {
        return [
            'name' => '',
            'appid' => '',
            'secret' => '',
            'mp_id' => '',
            'wxapp_pay_mpwx_appid' => '',
            'use_payment' => '0',
            'mp_type' => '0',
            'h5_pay_logo' => '',
            'h5_pay_title' => '代付订单',
            'h5_pay_sub_title' => '您有一笔代付订单，去支付',
            'ios_team_id' => '',
            'ios_bundle_id' => '',
        ];
    }
}
