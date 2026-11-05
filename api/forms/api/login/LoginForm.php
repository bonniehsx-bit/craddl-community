<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 22:54
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

namespace app\forms\api\login;


use app\core\ApiCode;
use app\helpers\ConstantHelper;
use app\helpers\Helper;
use app\helpers\HttpHelper;
use app\helpers\JobHelper;
use app\helpers\LockHelper; // [新增]
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;

use app\jobs\user\UserLoginJob;
use app\models\BaseModel;
use app\models\User;
use app\services\UserService;
use Yii;
use Exception; // [新增]


class LoginForm extends BaseModel
{
    public $code;
    public $encryptedData;
    public $iv;
    public $openId;
    public $unionId;
    public $avatarUrl;
    public $nickName;
    public $mobile;
    public $password;
    public $auth_code;


    private $userToken;

    public function rules()
    {
        return [
            [['code', 'iv', 'encryptedData', 'auth_code', 'openId', 'unionId', 'nickName', 'avatarUrl', 'mobile', 'password'], 'string']
        ];
    }

    public function init()
    {
        parent::init();
        $this->userToken = Helper::createUserToken();
    }


    // ===================================================================
    // ======================== 主登录入口方法 ===========================
    // ===================================================================

    /**
     * [核心] 手机号密码登录
     * @return array
     * @throws \yii\base\Exception
     */
    public function loginByMobile()
    {
        if (!$this->validate() || !$this->mobile || !$this->password) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '账号密码不正确或账号不存在');
        }


        // query user (including soft deleted)
        // [修复] 查找用户，支持查找已注销账户 (检查 mobile 或 backup_mobile)
        $user = User::find()
            ->where(['mall_id' => $this->mall_id, 'mobile' => $this->mobile])
            ->orWhere(['mall_id' => $this->mall_id, 'backup_mobile' => $this->mobile, 'status' => User::STATUS_DELETE])
            ->one();

        // 验证用户存在性及密码
        if (!$user || !$user->password || !Yii::$app->security->validatePassword($this->password, $user->password)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '账号密码不正确或账号不存在');
        }

        // 检查封禁状态 (可能 lazy 自动解封)
        $banInfo = UserService::checkBanStatus($user);
        if ($banInfo !== null) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $banInfo['msg'], $banInfo);
        }

        // [核心] 处理注销账户恢复
        if ($user->status == User::STATUS_DELETE || $user->is_delete == 1) {
            // 检查冷却期
            if ($user->account_del_time) {
                $deletedTimestamp = strtotime($user->account_del_time);
                $coolDownSeconds = User::RE_REGISTER_COOL_DOWN_DAYS * 24 * 60 * 60;
                if (time() < ($deletedTimestamp + $coolDownSeconds)) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '账号注销冷却期未满（需' . User::RE_REGISTER_COOL_DOWN_DAYS . '天），请稍后再试。');
                }
            }
            // 恢复账户 —— 必须把 backup_mobile 搬回 mobile, 否则再次登录时 mobile 为空会查不到用户
            $user->is_delete = 0;
            $user->status = User::STATUS_NORMAL;
            $user->account_del_time = null;
            if (empty($user->mobile) && !empty($user->backup_mobile)) {
                $user->mobile = $user->backup_mobile;
                $user->backup_mobile = null;
            }
            Yii::warning("手机号登录激活已注销账户: {$user->id}");
        }
        // 1. 获取当前平台的 openid
        $currentPlatformOpenId = $this->getOpenIdByPlatform();
        // 2. [核心修正] 调用模型的 saveToken 方法
        $user->access_token = $this->userToken;
        $user->auth_key = $this->userToken;
        $user->saveToken($currentPlatformOpenId); // 使用获取到的openid

        // 3. 更新其他用户信息
        $user->platform = User::PLATFORM_MOBILE;
        $user->current_platform = Yii::$app->platform;
        $this->updateWechatSubscribeStatus($user); // 更新公众号关注状态

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        $this->dispatchLoginJobs($user);
        return $this->buildSuccessResponse($user, '登录成功');
    }

    /**
     * [核心] 小程序一键登录/授权登录
     * @return array
     */
    public function loginByMpwx()
    {
        $app = Yii::$app->wechat->miniProgram;
        if (!$app) return $this->apiResponse(ApiCode::CODE_FAIL, '小程序配置错误');

        // [新增] 锁机制
        if (!LockHelper::lock('mpwx_login_code_' . md5($this->code), 3)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请勿频繁点击登录,请3秒后重试');
        }

        try {
            $session = $app->getUtils()->codeToSession($this->code);
            if (isset($session['errcode']) && $session['errcode'] != 0) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, $session['errmsg'] ?? '登录失败，请重试');
            }
        } catch (\Exception $e) {
            Yii::warning($e);
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序配置错误');
        }

        $openid = $session['openid'];
        $unionid = $session['unionid'] ?? '';

        $user = $this->findUserByUnionIdOrOpenId($unionid, $openid, 'mpwx');

        // [核心] 处理注销账户恢复
        if ($user && ($user->status == User::STATUS_DELETE || $user->is_delete == 1)) {
            if ($user->account_del_time) {
                $deletedTimestamp = strtotime($user->account_del_time);
                $coolDownSeconds = User::RE_REGISTER_COOL_DOWN_DAYS * 24 * 60 * 60;
                if (time() < ($deletedTimestamp + $coolDownSeconds)) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '账号注销冷却期未满（需' . User::RE_REGISTER_COOL_DOWN_DAYS . '天），请稍后再试。');
                }
            }
            $user->is_delete = 0;
            $user->status = User::STATUS_NORMAL;
            $user->account_del_time = null;
            // 恢复 mobile, 否则用户后续用手机号登录时 LoginForm 找不到人
            if (empty($user->mobile) && !empty($user->backup_mobile)) {
                $user->mobile = $user->backup_mobile;
                $user->backup_mobile = null;
            }
            Yii::warning("小程序登录激活已注销账户: {$user->id}");
        } elseif ($user) {
            $banInfo = UserService::checkBanStatus($user);
            if ($banInfo !== null) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, $banInfo['msg'], $banInfo);
            }
        }

        if (!$user) {
            $user = $this->createNewUser([
                'mpwx_openid' => $openid,
                'union_id' => $unionid,
                'platform' => User::PLATFORM_MPWX,
                'current_platform' => User::PLATFORM_MPWX,
            ]);
        }

        // [核心修正] 调用模型的 saveToken 方法
        $user->access_token = $this->userToken;
        $user->auth_key = $this->userToken;
        $user->saveToken($openid); // 传入小程序 openid

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        $this->dispatchLoginJobs($user);
        return $this->buildSuccessResponse($user, '登录成功');
    }


    /**
     * [保留] H5 微信公众号授权登录
     * @return array
     */
    public function loginByWechat()
    {
        $app = Yii::$app->wechat->app;
        try {
            $wechatUser = $app->getOAuth()->userFromCode($this->code);
            $info = $wechatUser->getRaw();

            if (empty($info)) return ResponseHelper::send(ApiCode::CODE_FAIL, '未正确获取到登录信息!');

            $openid = $info['openid'];
            $unionid = $info['unionid'] ?? null;

            // [新增] 锁机制
            if (!LockHelper::lock('wechat_login_openid_' . $openid, 3)) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '请勿频繁点击登录,请3秒后重试');
            }

            $user = $this->findUserByUnionIdOrOpenId($unionid, $openid, 'wechat');

            // [核心] 处理注销账户恢复
            if ($user && ($user->status == User::STATUS_DELETE || $user->is_delete == 1)) {
                if ($user->account_del_time) {
                    $deletedTimestamp = strtotime($user->account_del_time);
                    $coolDownSeconds = User::RE_REGISTER_COOL_DOWN_DAYS * 24 * 60 * 60;
                    if (time() < ($deletedTimestamp + $coolDownSeconds)) {
                        return ResponseHelper::send(ApiCode::CODE_FAIL, '账号注销冷却期未满（需' . User::RE_REGISTER_COOL_DOWN_DAYS . '天），请稍后再试。');
                    }
                }
                $user->is_delete = 0;
                $user->status = User::STATUS_NORMAL;
                $user->account_del_time = null;
                // 恢复 mobile, 否则用户后续用手机号登录时 LoginForm 找不到人
                if (empty($user->mobile) && !empty($user->backup_mobile)) {
                    $user->mobile = $user->backup_mobile;
                    $user->backup_mobile = null;
                }
                Yii::warning("微信登录激活已注销账户: {$user->id}");
            } elseif ($user) {
                $banInfo = UserService::checkBanStatus($user);
                if ($banInfo !== null) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, $banInfo['msg'], $banInfo);
                }
            }

            if (!$user) {
                $user = $this->createNewUser([
                    'platform' => User::PLATFORM_WECHAT,
                    'current_platform' => User::PLATFORM_WECHAT,
                    'avatar_url' => $wechatUser->getAvatar(),
                    'nickname' => $wechatUser->getNickname(),
                ]);
            }

            // 更新用户信息
            $user->openid = $openid;
            if ($unionid) $user->union_id = $unionid;
            $user->login_ip = Yii::$app->request->userIP;
            $this->updateWechatSubscribeStatus($user);

            // [核心修正] 调用模型的 saveToken 方法
            $user->access_token = $this->userToken;
            $user->auth_key = $this->userToken;
            $user->saveToken($openid); // 传入公众号 openid

            if (!$user->save()) {
                return $this->responseErrorMsg($user);
            }

            $this->dispatchLoginJobs($user);
            return $this->buildSuccessResponse($user, '登录成功');
        } catch (\Exception $e) {
            Yii::warning($e);
            return $this->apiResponse(ApiCode::CODE_FAIL, '登录失败,请重试: ' . $e->getMessage());
        }
    }


    // ===================================================================
    // ======================= 私有辅助方法 ==============================
    // ===================================================================

    /**
     * 根据平台获取 OpenID，用于手机登录场景
     * @return string|null
     */
    private function getOpenIdByPlatform(): ?string
    {
        if ($this->openId) return $this->openId;
        if (!$this->code) return null;

        try {
            if (Yii::$app->platform == ConstantHelper::PLATFORM_APP) {
                $config = Yii::$app->params['wechatOpenPlatformConfig'];
                $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$config['app_id']}&secret={$config['secret']}&code={$this->code}&grant_type=authorization_code";
                $result = HttpHelper::get($url);
                return $result['openid'] ?? null;
            }
            if (Yii::$app->platform == ConstantHelper::PLATFORM_MPWX) {
                $app = Yii::$app->wechat->miniProgram;
                if ($app) {
                    $session = $app->getUtils()->codeToSession($this->code);
                    return $session['openid'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Yii::warning("登录时获取OpenID失败: " . $e->getMessage());
        }
        return null;
    }

    /**
     * 统一的用户查找逻辑
     * @param string|null $unionid
     * @param string $openid
     * @param string $type 'wechat' or 'mpwx'
     * @return User|null
     */
    private function findUserByUnionIdOrOpenId(?string $unionid, string $openid, string $type): ?User
    {
        $user = null;
        if ($unionid) {
            // [修复] 查找用户时不再过滤 is_delete=0，以便能够找到已注销用户进行恢复
            $user = User::findOne(['union_id' => $unionid, 'mall_id' => $this->mall_id]);
        }
        if (!$user) {
            $openIdField = $type === 'mpwx' ? 'mpwx_openid' : 'openid';
            $user = User::findOne([$openIdField => $openid, 'mall_id' => $this->mall_id]);
        }
        return $user;
    }

    private function updateWechatSubscribeStatus(User &$user)
    {
        try {
            if (Yii::$app->wechat->getIsWechat() && $user->openid) {
                // V6 使用 HTTP Client 调用
                $response = Yii::$app->wechat->app->getClient()->get('cgi-bin/user/info', [
                    'query' => [
                        'openid' => $user->openid,
                        'lang' => 'zh_CN'
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $userDetail = json_decode($response->getContent(), true);
                    if ($userDetail && isset($userDetail['subscribe'])) {
                        $user->is_subscribe = $userDetail['subscribe'] ? 1 : 0;
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::warning("更新公众号关注状态失败: " . $e->getMessage());
        }
    }

    /**
     * 创建新用户
     * @param array $attributes
     * @return User
     */
    private function createNewUser(array $attributes = []): User
    {
        $setting = OptionHelper::get('mall_setting', $this->mall_id);
        $user = new User();
        $user->mall_id = $this->mall_id;
        $user->access_token = $this->userToken;
        $user->auth_key = $this->userToken;
        $user->nickname = '新用户' . uniqid();
        $user->avatar_url = $setting['avatar_url'] ?? '';

        // 合并传入的属性
        $user->setAttributes($attributes, false);

        $user->save();
        return $user;
    }

    /**
     * 投递登录后的异步任务
     * @param User $user
     */
    private function dispatchLoginJobs(User $user)
    {
        $headers = Yii::$app->request->headers;
        $parentId = $headers->get('x-parent-id');
        JobHelper::push(new UserLoginJob(['user_id' => $user->id, 'parent_id' => $parentId]));
        if ($parentId && $parentId != -1 && !$user->parent_id) {
            JobHelper::push(new \app\jobs\user\BindParentJob(['user_id' => $user->id, 'parent_id' => $parentId]));
        }
    }

    /**
     * 构建统一的登录成功响应
     * @param User $user
     * @param string $message
     * @return array
     */
    private function buildSuccessResponse(User $user, string $message = '登录成功'): array
    {
        $level = OptionHelper::get('level_' . $user->level_id, $this->mall_id);

        $responseUser = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'level_name' => $level['name'] ?? '默认等级',
            'mobile' => $user->mobile,
            'openid' => $user->openid,
        ];

        return $this->apiResponse(ApiCode::CODE_SUCCESS, $message, [
            'access_token' => $this->userToken,
            'user' => $responseUser
        ]);
    }
}
