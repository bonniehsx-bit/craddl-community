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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Option;

class SiteSettingForm extends BaseModel
{
    /**
     * 定义所有配置项及其默认值
     * 作为数据结构的“唯一事实来源”
     */
    private function getDefaultSettings()
    {
        return [
            'name'          => '',
            'sub_name'      => '',
            'title'         => '',
            'slogan'        => '', // 登录页副标题/品牌口号
            'keywords'      => '',
            'domain'        => \Yii::$app->request->hostInfo,
            'logo'          => '',
            'login_logo'    => '',
            'login_pic'     => '',
            'header_pic'    => '',
            'favicon'       => '',
            'record'        => '',
            'copyright'     => '',
            'copyright_url' => '',
            'auth_key'      => '',
            'websocket_url' => '',
            'is_register'   => 0,
            'try_day'       => 0,
        ];
    }

    public function rules()
    {
        // 虽然我们动态处理数据，但为了兼容 BaseModel 的 validate 调用，保持为空
        // 如果需要严格校验（如 domain 必须是 url），建议手动写在 save 中或定义属性
        return [];
    }

    /**
     * 保存设置
     */
    public function save()
    {
        // 1. 获取提交的数据
        $postData = \Yii::$app->request->post();

        // 2. 获取默认结构（白名单）
        $defaults = $this->getDefaultSettings();

        // 3. 数据清洗与合并
        // 只提取在 defaults 中存在的 key，防止恶意字段注入
        $validData = array_intersect_key($postData, $defaults);

        // 可选：对字符串进行 trim 处理
        foreach ($validData as $key => $val) {
            if (is_string($val)) {
                $validData[$key] = trim($val);
            }
        }

        // 3.5 字段从属关系：title 留空时自动用 name 填充
        // (title 用于浏览器标签页；name 用于后台顶部展示。
        //  两者语义类似，运营常常只填一个，留空时自动复用避免空标签页。)
        if (empty($validData['title']) && !empty($validData['name'])) {
            $validData['title'] = $validData['name'];
        }
        // sub_name 留空时取 name 前 4 个字
        if (empty($validData['sub_name']) && !empty($validData['name'])) {
            $validData['sub_name'] = mb_substr($validData['name'], 0, 4);
        }

        // 4. 特殊字段校验 (例如域名)
        if (isset($validData['domain']) && !empty($validData['domain'])) {
            // 简单的正则或者 filter_var 校验
            if (!preg_match("/^https?:\/\//", $validData['domain'])) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '域名必须以 http:// 或 https:// 开头');
            }
        }

        // 5. 获取数据库旧数据并合并（保留旧数据中未提交的字段，覆盖新提交的字段）
        $oldSettings = OptionHelper::get(Option::NAME_SYS_SETTING, 0, []);
        // 注意顺序：旧数据 < 新数据
        $finalSettings = array_merge($oldSettings, $validData);
        // 再次确保结构纯净（去掉旧数据中可能存在的废弃字段）
        $finalSettings = array_intersect_key($finalSettings, $defaults);

        // 6. 存入数据库
        OptionHelper::set(Option::NAME_SYS_SETTING, $finalSettings, 0);

        // 7. 特殊处理：domain 单独存一份（兼容旧逻辑或用于全局快速读取）
        if (isset($finalSettings['domain'])) {
            OptionHelper::set('domain', $finalSettings['domain'], 0);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /**
     * 获取设置
     */
    public function search()
    {
        $defaults = $this->getDefaultSettings();
        $savedSettings = OptionHelper::get(Option::NAME_SYS_SETTING, 0, []);

        // 合并：默认值 < 数据库值
        // 这样如果数据库里缺少某个新加的字段，会自动使用默认值
        $setting = array_merge($defaults, $savedSettings);

        // 确保 domain 逻辑一致性：如果 option 表里单独存了 domain，以此为准
        // (视你的业务逻辑而定，通常 setting 里的 domain 应该和单独存的保持同步，这里做个兜底)
        $singleDomain = OptionHelper::get('domain', 0);
        if ($singleDomain) {
            $setting['domain'] = $singleDomain;
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['setting' => $setting]);
    }

    /**
     * 获取部分公开/基础信息（用于登录页等不需要鉴权的场景）
     */
    public function baseInfo()
    {
        // 1. 获取完整配置 (复用 search 逻辑，确保数据处理一致)
        $res = $this->search();
        $fullSettings = $res['data']['setting'];

        // 2. 定义允许公开的字段白名单
        $publicKeys = [
            'name', 'sub_name', 'title', 'slogan', 'logo', 'login_logo', 'login_pic',
            'copyright', 'copyright_url', 'record', 'is_register', 'try_day',
            'header_pic', 'websocket_url', 'favicon'
        ];

        // 3. 过滤敏感数据
        // array_flip 将值变成键，然后取交集
        $info = array_intersect_key($fullSettings, array_flip($publicKeys));

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['setting' => $info]);
    }
}