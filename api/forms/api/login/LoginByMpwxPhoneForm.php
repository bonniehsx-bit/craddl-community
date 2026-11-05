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

namespace app\forms\api\login;

use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\helpers\Helper;
use app\helpers\HttpHelper;
use app\helpers\JobHelper;
use app\helpers\LockHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\helpers\WechatHelper;
use app\jobs\user\UserLoginJob;
use app\models\BaseModel;
use app\models\User;
use app\services\UserService;
use Exception;
use Yii;

class LoginByMpwxPhoneForm extends BaseModel
{
    public $code;
    public $encryptedData;
    public $iv;
    public $wxcode;

    // private const RE_REGISTER_COOL_DOWN_DAYS = 360; // 移至 User 模型


    public function rules()
    {
        return [
            [['code', 'iv', 'encryptedData', 'wxcode'], 'string']
        ];
    }


    public function login()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $header = Yii::$app->request->headers;
        // 无邀请来源时前端发的是哨兵值 -1(request.js:getPid),不是空;empty("-1") 为 false。
        // 与 LoginForm:403 / UserLoginJob:105 统一口径:<=0 一律视为「无邀请来源」,回退默认推荐人。
        $parent_id = (int)$header->get('x-parent-id');
        if ($parent_id <= 0) {
            $parent_id = 0;
            $s = OptionHelper::get('mall_setting', $this->mall_id);
            $dp = (int)($s['default_parent_id'] ?? 0);
            if ($dp > 0) {
                $parent_id = $dp;
            }
        }
        WechatHelper::init($this->mall_id);
        $app = Yii::$app->wechat->miniProgram;
        if (!$app) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序配置失败');
        }
        if (!LockHelper::lock('mpwx_login_' . $this->wxcode, 3)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请勿频繁点击登录,请3秒后重试');
        }
        $unionid = '';
        $openid = '';
        try {
            // v6: getUtils()->codeToSession()
            $session = $app->getUtils()->codeToSession($this->wxcode);
            $openid = $session['openid'] ?? '';
            $unionid = $session['unionid'] ?? '';
            if (isset($session['errcode']) && $session['errcode'] !== 0) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, $session['errmsg'] ?? '小程序登录失败');
            }
        } catch (\Exception $e) {
            Yii::error("小程序登录失败: " . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序配置错误或网络异常');
        }

        $mobile = null;

        if (!$mobile && $this->code) {
            // 缓存里的 access_token 被顶掉(40001:同一 appid 在别处又换了新 token,旧的 5 分钟后作废)
            // 或过期(42001)时,强制换一个新的再试一次。原来直接回「请重新打开小程序」,
            // 可重开也没用 —— 缓存不过期就一直是那个死 token,要等最长 2 小时自愈。
            // 手机号 code 在 token 无效时微信不会核销,重试可以接着用。
            $result = null;
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $accessToken = $app->getAccessToken();
                    // v6: getToken() 走缓存,refresh() 强制向微信换新并写回缓存
                    if ($attempt === 0) {
                        $realToken = $accessToken->getToken();
                    } elseif ($accessToken instanceof \EasyWeChat\Kernel\Contracts\RefreshableAccessToken) {
                        $realToken = $accessToken->refresh();
                    } else {
                        break;
                    }
                } catch (Exception $e) {
                    Yii::error("获取微信 Access Token 失败: " . $e->getMessage());
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '获取微信授权失败，请稍后重试');
                }
                $result = HttpHelper::postRaw("https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=$realToken", ['code' => $this->code]);
                if (!in_array((int)($result['errcode'] ?? 0), [40001, 42001], true)) {
                    break;
                }
                Yii::warning("微信 Access Token 已失效(errcode={$result['errcode']}),第" . ($attempt + 1) . "次,强制刷新后重试");
            }

            if (!isset($result['errcode'])) {
                Yii::error("获取手机号接口返回格式错误: " . json_encode($result));
                return ResponseHelper::send(ApiCode::CODE_FAIL, '登录发生错误(E1)，请重试或联系我们');
            }
            if ($result['errcode'] == 0) {
                $mobile = $result['phone_info']['purePhoneNumber'];
            } elseif (in_array((int)$result['errcode'], [40001, 42001], true)) { // 刷新后仍失效
                Yii::error("微信 Access Token 刷新后仍失效: " . json_encode($result));
                return ResponseHelper::send(ApiCode::CODE_FAIL, '微信授权已过期，请重新打开小程序。');
            } else {
                Yii::error("获取手机号失败: " . json_encode($result));
                return ResponseHelper::send(ApiCode::CODE_FAIL, $result['errmsg'] ?? '获取手机号失败(E4)');
            }
        }

        if (!$mobile) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '未获取到手机号!');
        }
        $token = Helper::createUserToken();

        // 查找用户：先查正常手机号，再查注销用户的备份手机号
        $user = User::find()
            ->where(['mall_id' => $this->mall_id])
            ->andWhere([
                'or',
                ['mobile' => $mobile],
                ['backup_mobile' => $mobile, 'status' => User::STATUS_DELETE],
            ])->one();

        $isNewUserRegistration = false; // 标记是否是全新注册

        if ($user) {
            // Case 1: 用户存在且状态正常
            if ($user->status == User::STATUS_NORMAL && $user->is_delete == 0) {
                $user->access_token = $token;
                $user->auth_key = $token;
                $user->mpwx_openid = $openid; // 确保更新小程序 openid
                if ($user->parent_id != -2 && $user->parent_id <= 0 && $parent_id) { // 只有在没有父级时才设置 maybe_parent_id
                    $user->maybe_parent_id = $parent_id;
                }
                if ($unionid) {
                    $user->union_id = $unionid;
                }
                $user->login_ip = Yii::$app->request->userIP; // 更新登录IP
                $user->platform = User::PLATFORM_MPWX; // 更新平台
                if (!$user->save()) {
                    return $this->responseErrorMsg($user);
                }
                $user->saveToken($openid);
            } // Case 2: 用户存在但已注销
            elseif ($user->status == User::STATUS_DELETE && $user->is_delete == 1) {
                // 检查注销时间是否满足冷却期
                if ($user->account_del_time) {
                    $deletedTimestamp = strtotime($user->account_del_time);
                    $coolDownSeconds = User::RE_REGISTER_COOL_DOWN_DAYS * 24 * 60 * 60;
                    if (time() < ($deletedTimestamp + $coolDownSeconds)) {
                        // 冷却期未满，不允许重新注册/登录
                        return ResponseHelper::send(ApiCode::CODE_FAIL, '账号注销冷却期未满（需' . User::RE_REGISTER_COOL_DOWN_DAYS . '天），请稍后再试。');
                    }
                }
                // 冷却期已满或无注销时间记录，允许重新激活
                Yii::warning("用户 {$mobile} (ID: {$user->id}) 重新激活账户。");
                $user->is_delete = 0;
                $user->status = User::STATUS_NORMAL;
                $user->access_token = $token;
                $user->auth_key = $token;
                $user->mpwx_openid = $openid;
                $user->mobile = $mobile; // 恢复主手机号
                $user->backup_mobile = null; // 清空备份手机号
                $user->account_del_time = null; // 清空注销时间
                if ($unionid) {
                    $user->union_id = $unionid;
                }
                $user->login_ip = Yii::$app->request->userIP;
                $user->platform = User::PLATFORM_MPWX;
                // 重新激活时记录推荐人意向，由 UserLoginJob 处理绑定
                if ($parent_id && $user->parent_id <= 0) {
                    $user->maybe_parent_id = $parent_id;
                }

                if (!$user->save()) {
                    return $this->responseErrorMsg($user);
                }
                $user->saveToken($openid);
            } // Case 3: 用户被拉黑 (STATUS_BLACK) 或其它异常状态
            else {
                $banInfo = UserService::checkBanStatus($user);
                if ($banInfo !== null) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, $banInfo['msg'], $banInfo);
                }
                return ResponseHelper::send(ApiCode::CODE_FAIL, '账号状态异常，请联系客服。');
            }
        } else {
            // Case 4: 用户不存在，执行全新注册
            $isNewUserRegistration = true; // 标记为新用户
            $setting = OptionHelper::get('mall_setting', $this->mall_id);
            $avatar_url = $setting && isset($setting['avatar_url']) && $setting['avatar_url'] ? $setting['avatar_url'] : UserHelper::getDefaultAvatar(); // 使用助手获取默认头像
            $user = new User();
            $user->mall_id = $this->mall_id;
            $user->mobile = $mobile;
            $user->avatar_url = $avatar_url;
            if ($unionid) {
                $user->union_id = $unionid;
            }
            $user->platform = User::PLATFORM_MPWX;
            $user->nickname = Helper::maskPhoneNumber($mobile);
            $user->name = $user->nickname;
            $user->access_token = $token;
            $user->auth_key = $token;
            $user->mpwx_openid = $openid; // 保存小程序 openid
            $user->status = User::STATUS_NORMAL; // 初始状态正常
            $user->is_delete = 0; // 初始未删除
            $user->login_ip = Yii::$app->request->userIP;

            if ($parent_id) { // 如果有推荐人 ID
                $user->maybe_parent_id = $parent_id;
            }

            // 检查微信公众号关注状态 (如果需要)
            /*
            try {
                if (Yii::$app->wechat->getIsWechat()) { // 这个判断可能不适用于小程序场景
                    // 获取公众号 openid 可能需要额外授权或通过 unionid 关联
                    // $userDetail = Yii::$app->wechat->app->user->get($user->wechat_openid);
                    // if ($userDetail && $userDetail['subscribe']) {
                    //     $user->is_subscribe = 1;
                    // }
                }
            } catch (\Exception $exception) {
                Yii::warning($exception->getMessage());
            }
            */

            if (!$user->save()) {
                return $this->responseErrorMsg($user);
            }
            $user->saveToken($openid);
        }

        // --- 登录/注册后处理 ---
        // 注册/激活成功后立刻建立关系链,不等异步 job 落地。
        // bindParent 自带事务,必须在上面的 commit 之后调用(避免嵌套事务)。
        // 失败不阻断注册:maybe_parent_id 已落库,下面的 UserLoginJob 会再兜一次。
        // tryBindParent 幂等(parent_id 已非 0 直接跳过),重复调用安全。
        if ($parent_id > 0) {
            try {
                UserService::tryBindParent($user, $parent_id, ':register');
            } catch (\Throwable $e) {
                Yii::error("[注册] 同步绑定推荐人失败 user={$user->id} parent={$parent_id}: " . $e->getMessage());
            }
        }

        JobHelper::push(new UserLoginJob(['user_id' => $user->id, 'parent_id' => $parent_id]));   // 兜底 + 推广者资格 + 等级检测

        // 根据是否新用户返回不同提示或数据
        $message = $isNewUserRegistration ? '注册成功' : '登录成功';

        return $this->apiResponse(ApiCode::CODE_SUCCESS, $message, [
            'access_token' => $user->access_token,
            // 返回基础信息，确保包含 id, nickname, avatar_url
            'user' => User::getBaseInfo($user->id) // 使用 getBaseInfo 获取安全字段
        ]);
    }
}
