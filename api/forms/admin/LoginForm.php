<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-09
 * Time: 17:58
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
use app\helpers\LoginHelper;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\AdminMall;
use app\models\BaseModel;
use app\models\Mall;
use Yii;

/**
 * Class LoginForm
 * @package app\forms\admin
 *
 * [重构版] 管理员登录表单
 * 1. 仅支持账号密码 + 图形验证码登录。
 * 2. 对接新的 Admin 模型架构 (saveToken)。
 */
class LoginForm extends BaseModel
{
    public $username;
    public $password;
    public $captcha;     // 用户输入的验证码字符
    public $captcha_key; // 前端传来的验证码唯一标识

    public function rules()
    {
        return [
            [['username', 'password', 'captcha', 'captcha_key'], 'required', 'message' => '{attribute}不能为空'],
            [['username', 'password', 'captcha', 'captcha_key'], 'string'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => '用户名',
            'password' => '密码',
            'captcha' => '验证码',
            'captcha_key' => '验证码Key',
        ];
    }

    public function login()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // ==========================================
        // 1. 图形验证码校验
        // ==========================================

        // 从缓存获取真实验证码
        $trueCode = LoginHelper::getAuthCode($this->captcha_key);

        if (empty($trueCode)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '验证码已失效，请点击刷新'];
        }

        // 统一转小写比对
        if (strtolower($this->captcha) !== strtolower($trueCode)) {
            // 安全策略：输错一次销毁 Key，防止暴力破解
            LoginHelper::removeAuthCode($this->captcha_key);
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '验证码错误'];
        }

        // 验证通过，立即销毁该 Key (防止重复使用)
        LoginHelper::removeAuthCode($this->captcha_key);

        // ==========================================
        // 2. 账号密码校验
        // ==========================================

        /** @var Admin $admin */
        $admin = Admin::findOne(['username' => $this->username, 'is_delete' => 0]);

        if (!$admin) {
            // 模糊报错，防止枚举账号
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户名或密码不正确'];
        }


        // 存储哈希非法(空/老格式/被截断)时 validatePassword 会抛异常而非返回 false,裸调会 500。
        // 归一为「用户名或密码不正确」(不泄露账号状态),该账号需超管重置密码后才能登录。
        try {
            $pwdOk = Yii::$app->security->validatePassword($this->password, (string)$admin->password);
        } catch (\yii\base\InvalidArgumentException $e) {
            $pwdOk = false;
        }
        if (!$pwdOk) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户名或密码不正确'];
        }

        // ==========================================
        // 3. 账户状态检查
        // ==========================================

        if ($admin->is_expire) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '账户已过期，请联系管理员'];
        }

        // 检查请求头中的商户/管理员标识 (保留业务逻辑)
        $headers = Yii::$app->request->headers;
        $isMchRequest = isset($headers['is-mch']) && $headers['is-mch'];

        if ($admin->mch_id) {
            // 如果是商户账号，必须从商户端入口登录
            if (!$isMchRequest) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '登录失败，您不是平台管理员！'];
            }
        } else {
            // 如果是平台管理员账号，不能从商户端入口登录
            if ($isMchRequest) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '登录失败，您不是商户！'];
            }
        }

        // ==========================================
        // 4. 商城上下文逻辑 (Mall ID)
        // ==========================================
        $mall_id = 1; // 默认值

        // 非超级管理员，需要查找关联的商城
        if ($admin->admin_type != Admin::ADMIN_TYPE_SUPER) {
            $adminMall = AdminMall::findOne(['admin_id' => $admin->id, 'is_delete' => 0]);

            // 如果没有关联记录
            if (!$adminMall) {
                // 创始人自动创建默认商城
                if ($admin->admin_type == Admin::ADMIN_TYPE_FOUNDER) {
                    $mall = new Mall();
                    $mall->admin_id = $admin->id;
                    $mall->name = $admin->name . '的商城';
                    if (!$mall->save()) {
                        return $this->responseErrorMsg($mall);
                    }

                    $adminMall = new AdminMall();
                    $adminMall->admin_id = $admin->id;
                    $adminMall->mall_id = $mall->id;
                    $adminMall->role = AdminMall::FOUNDER;
                    $adminMall->save();
                } else {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '此账户未关联商城');
                }
            }
            $mall_id = $adminMall->mall_id;
        } else {
            // 超级管理员 mall_id 设为 0，前端需跳转选择页
            $mall_id = 0;
        }

        // ==========================================
        // 5. 登录成功处理
        // ==========================================

        // 更新基础信息
        $admin->login_ip = Yii::$app->request->userIP;
        // 注意：不再这里直接生成 access_token 字段，而是交给 saveToken 方法统一处理
        $admin->save();

        // [核心] 调用新架构的 saveToken
        // 这会生成随机 Token，存入 AccessToken 表，绑定 UA，并存入 Redis
        $tokenStr = $admin->saveToken();

        // Yii 登录 (让本次请求立即变为已登录状态)
        Yii::$app->admin->login($admin);

        // 返回数据
        $data = [
            'mall_id' => $mall_id,
            "token" => $tokenStr, // 返回给前端
            "expiresAt" => (time() + 7 * 24 * 60 * 60) * 1000,
            "nickname" => $admin->nickname,
            "avatar" => $admin->avatar,
            "admin_type" => $admin->admin_type,
            "username" => $admin->username
        ];

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '登录成功', 'data' => $data];
    }
}
