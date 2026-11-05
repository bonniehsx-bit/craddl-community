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
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/3/24
 * Time: 17:59
 * Note:
 */

namespace app\forms\api\login;


use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\helpers\Helper;

// <-- 引入 Helper
use app\helpers\JobHelper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;

// <-- 引入 UserHelper
use app\jobs\user\UserLoginJob;
use app\models\BaseModel;
use app\models\User;
use app\services\UserService;
use Yii;

// <-- 引入 Yii

class MobileSignInForm extends BaseModel
{

    public $avatarUrl;
    public $nickName;
    public $openid; // 注意：手机号注册通常没有 openid，除非是从 H5 跳转过来的
    public $mobile;
    public $password;
    public $code;
    public $name;
    public $type; // 2: 小程序授权手机号快捷注册(号码取自 phone_ticket);其余一律按短信验证码注册
    public $phone_ticket; // type=2 时:手机号授权接口签发的一次性凭证
    public $pid;  // 注册页手填的推荐人ID;仅在「无邀请来源 且 后台未设默认推荐人」时生效
    public $pay_pwd; // 6位数字支付密码;仅在商城开启「注册需设置支付密码」时必填

    // [新增] 定义注销后重新注册的冷却期（天）
    private const RE_REGISTER_COOL_DOWN_DAYS = 360;


    public function rules()
    {
        // [优化] 更明确的规则
        return [
            [['mobile', 'password', 'nickName'], 'required'],
            // 原来是「type==1 才校验」:不传 type、或传 type=2,验证码都被跳过,
            // 再加上直接信前端的 mobile,等于拿任何人的手机号都能注册/恢复账号。
            // 现在反过来:除了带凭证的 type=2,一律要验证码(商城开着「注册需验证码」时)
            [['code'], 'required', 'when' => function ($model) {
                return (int) $model->type !== 2 && $model->regNeedSmsCode();
            }, 'message' => '验证码不能为空。'],

            [['mobile'], 'match', 'pattern' => '/^1[3-9]\d{9}$/', 'message' => '手机号码格式不正确。'],
            [['password'], 'string', 'min' => 6, 'tooShort' => '密码长度不能少于6位。'],
            [['nickName', 'name'], 'string', 'max' => 45],
            [['avatarUrl'], 'string', 'max' => 255],
            [['openid'], 'string', 'max' => 64],
            [['type', 'pid'], 'integer'],
            [['pay_pwd'], 'string'],

            // [新增] 验证码验证逻辑 (仅在 type=1 且商城开启「注册需验证码」时触发)
            [['code'], 'validateSmsCode', 'when' => function ($model) {
                return (int) $model->type !== 2 && $model->regNeedSmsCode();
            }],
            [['phone_ticket'], 'string'],
        ];
    }

    /**
     * 注册是否需要短信验证码(mall_setting.reg_need_sms_code,默认需要)。
     * 关闭后注册不再校验手机号真伪,仅在明确业务需求下使用。
     *
     * 注意:与 is_login_mobile 无关 —— 那个开关只决定登录弹窗跳「手机号+密码」登录页还是走微信一键授权,
     * 登录链路本就不使用验证码(见 LoginForm::loginByMobile 走 validatePassword)。
     * @return bool
     */
    public function regNeedSmsCode()
    {
        return self::mallRequiresSmsCode((int)$this->mall_id);
    }

    /**
     * 同上,按商城查。插件要判断「这个商城的用户手机号验没验过真」时用它(如商管按手机号认领承租方),
     * 别自己读 mall_setting —— 默认值只在这一处。
     */
    public static function mallRequiresSmsCode(int $mallId): bool
    {
        $s = OptionHelper::get('mall_setting', $mallId) ?: [];
        return (int)($s['reg_need_sms_code'] ?? 1) === 1;
    }

    /**
     * [新增] 自定义验证规则：验证短信验证码
     */
    public function validateSmsCode($attribute, $params)
    {
        if (!$this->hasErrors()) {
            // 走带失败计数的统一校验:原来只是取出来比一下,连错多少次都不管,
            // 而全仓没有接口级限流 —— 6 位码 100 万种、180 秒有效,
            // 不设上限就能一直试,试出来就等于拿到这个账号。
            list($ok, $msg) = CacheHelper::verifySmsCode($this->mobile, $this->$attribute);
            if (!$ok) {
                $this->addError($attribute, $msg);
            }
        }
    }


    /**
     * 注册/重新激活时写入支付密码。只在传了合法 6 位数字时写,避免把空值哈希进库。
     * 是否必填由 signIn() 按 mall_setting.reg_need_pay_password 前置校验。
     */
    private function applyPayPwd(User $user)
    {
        if (!empty($this->pay_pwd) && preg_match('/^\d{6}$/', (string)$this->pay_pwd)) {
            $user->pay_pwd = Yii::$app->security->generatePasswordHash($this->pay_pwd);
        }
    }

    /**
     * type=2:手机号从服务端签发的凭证里取,覆盖前端传来的 mobile(不信前端)。
     * 放在校验之前,后面的手机号格式、验证码规则都对真实号码生效。
     */
    public function beforeValidate()
    {
        if ((int) $this->type === 2) {
            $mobile = MpwxPhoneForm::peekTicket($this->phone_ticket, (int) $this->mall_id);
            if ($mobile === null) {
                $this->addError('mobile', '手机号授权已过期,请重新获取');
                return false;
            }
            $this->mobile = $mobile;
        }
        return parent::beforeValidate();
    }

    public function signIn()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallSetting = OptionHelper::get('mall_setting', $this->mall_id);

        // 商城开启「注册需设置支付密码」时强制校验(6 位数字,与 tui-paypwd 约定一致)
        $needPayPwd = (int)($mallSetting['reg_need_pay_password'] ?? 0) === 1;
        if ($needPayPwd && !preg_match('/^\d{6}$/', (string)$this->pay_pwd)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请设置6位数字支付密码');
        }
        // 凭证一次性:校验都过了才作废,中途填错不用重新授权手机号
        if ((int) $this->type === 2) {
            MpwxPhoneForm::dropTicket($this->phone_ticket);
        }

        $header = Yii::$app->request->headers;
        // 推荐人优先级(与注册页展示一致):① 邀请来源 x-parent-id → ② 后台默认推荐人 → ③ 注册页手填 pid
        //
        // ⚠ 前端在「无邀请来源」时发的不是空,而是哨兵值 -1
        //   (uniapp/utils/request.js:getPid → `uni.getStorageSync("X-PARENT-ID") || -1`)。
        //   empty("-1") 为 false,原来会把 -1 当成合法邀请人,导致 ②③ 两条分支永远走不到,
        //   maybe_parent_id 被写成 -1,UserLoginJob 再按 -1 哨兵跳过绑定 → 默认推荐人形同虚设。
        //   LoginForm:403 与 UserLoginJob:105 早已按 -1 判哨兵,这里补齐同一口径:<=0 一律视为「无邀请来源」。
        $parent_id = (int)$header->get('x-parent-id');
        if ($parent_id <= 0) {
            $parent_id = 0;
            $s = $mallSetting;
            $dp = (int)($s['default_parent_id'] ?? 0);
            if ($dp > 0) {
                $parent_id = $dp;
            } elseif (!empty($this->pid)) {
                // 手填值必须校验,而且要与【最终绑定】完全同口径 ——
                // 否则「校验通过 → 绑定静默失败」,用户看到注册成功却没绑上,毫无感知。
                // 口径 = UserService::tryBindParent 的 parent 查询:
                //   本商城 + 未注销 + 未拉黑 + 具备推广资格(is_inviter=1)
                //
                // 文案统一,不区分「不存在」与「被封 / 无资格」:否则可以拿它枚举用户状态。
                $manual = (int)$this->pid;
                $usable = User::find()
                    ->where([
                        'id'         => $manual,
                        'mall_id'    => $this->mall_id,
                        'is_delete'  => 0,
                        'status'     => User::STATUS_NORMAL,
                        'is_inviter' => 1,
                    ])
                    ->exists();
                if (!$usable) {
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '推荐人ID不存在或当前不可用,请核对后重新填写');
                }
                $parent_id = $manual;
            }
        }

        // 查找用户：先查正常手机号，再查注销用户的备份手机号
        $user = User::find()
            ->where(['mall_id' => $this->mall_id])
            ->andWhere([
                'or',
                ['mobile' => $this->mobile],
                ['backup_mobile' => $this->mobile, 'status' => User::STATUS_DELETE],
            ])->one();

        $isNewUserRegistration = false; // 标记是否是全新注册
        $token = Helper::createUserToken(); // 预先生成 token

        if ($user) {
            // Case 1: 用户存在且状态正常
            if ($user->status == User::STATUS_NORMAL && $user->is_delete == 0) {
                // 手机号已被正常用户使用，注册失败
                return ResponseHelper::send(ApiCode::CODE_FAIL, '该手机号已被注册，请尝试登录或更换手机号。');
            } // Case 2: 用户存在但已注销
            elseif ($user->status == User::STATUS_DELETE && $user->is_delete == 1) {
                // 检查注销时间是否满足冷却期
                if ($user->account_del_time) {
                    $deletedTimestamp = strtotime($user->account_del_time);
                    $coolDownSeconds = self::RE_REGISTER_COOL_DOWN_DAYS * 24 * 60 * 60;
                    if (time() < ($deletedTimestamp + $coolDownSeconds)) {
                        // 冷却期未满，不允许重新注册
                        return ResponseHelper::send(ApiCode::CODE_FAIL, '该手机号处于注销冷却期（需' . self::RE_REGISTER_COOL_DOWN_DAYS . '天），请稍后再试。');
                    }
                }
                // 冷却期已满或无注销时间记录，允许重新激活
                Yii::warning("用户 {$this->mobile} (ID: {$user->id}) 通过手机号注册重新激活账户。");
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    $user->is_delete = 0;
                    $user->status = User::STATUS_NORMAL;
                    $user->access_token = $token;
                    $user->auth_key = $token;
                    $user->mobile = $this->mobile; // 恢复主手机号
                    $user->backup_mobile = null; // 清空备份手机号
                    $user->account_del_time = null; // 清空注销时间
                    $user->password = Yii::$app->security->generatePasswordHash($this->password); // 更新密码
                    $this->applyPayPwd($user);
                    $user->nickname = $this->nickName ?: Helper::maskPhoneNumber($this->mobile); // 更新昵称
                    $user->avatar_url = $this->avatarUrl ?: $user->avatar_url ?: UserHelper::getDefaultAvatar(); // 更新头像
                    $user->login_ip = Yii::$app->request->userIP;
                    $user->platform = Yii::$app->platform ?: User::PLATFORM_MOBILE;

                    // 重新激活时也记录推荐人意向，由 UserLoginJob 处理绑定
                    if ($parent_id && $user->parent_id <= 0) {
                        $user->maybe_parent_id = $parent_id;
                    }

                    if (!$user->save()) {
                        throw new \Exception($this->getErrorMsg($user));
                    }
                    $user->saveToken($this->openid); // 注意: openid 可能为空
                    CacheHelper::remove(CacheHelper::smsCodeKey($this->mobile)); // 清除验证码

                    $transaction->commit();
                } catch (\Throwable $e) {
                    $transaction->rollBack();
                    Yii::error('重新激活用户失败: ' . $e->getMessage());
                    return ResponseHelper::send(ApiCode::CODE_FAIL, '账户激活失败，请稍后再试');
                }
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
            $avatar_url = $this->avatarUrl ?: ($setting && isset($setting['avatar_url']) && $setting['avatar_url'] ? $setting['avatar_url'] : UserHelper::getDefaultAvatar());

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $user = new User();
                $user->mall_id = $this->mall_id;
                $user->mobile = $this->mobile;
                $user->avatar_url = $avatar_url;
                $user->platform = Yii::$app->platform ?: User::PLATFORM_MOBILE;
                $user->nickname = $this->nickName ?: Helper::maskPhoneNumber($this->mobile);
                if ($this->name) {
                    $user->name = $this->name;
                } else {
                    // 如果没提供 name，可以用 nickname 作为 name
                    $user->name = $user->nickname;
                }
                $user->password = Yii::$app->security->generatePasswordHash($this->password);
                $this->applyPayPwd($user);
                $user->access_token = $token;
                $user->auth_key = $token;
                $user->openid = $this->openid; // 保存 openid (可能为空)
                $user->status = User::STATUS_NORMAL;
                $user->is_delete = 0;
                $user->login_ip = Yii::$app->request->userIP;

                if ($parent_id) { // 如果有推荐人 ID
                    $user->maybe_parent_id = $parent_id;
                }

                // [移除] 检查微信关注状态的逻辑在此场景下通常不适用

                if (!$user->save()) {
                    throw new \Exception($this->getErrorMsg($user));
                }
                $user->saveToken($this->openid); // 注意: openid 可能为空
                CacheHelper::remove(CacheHelper::smsCodeKey($this->mobile)); // 清除验证码

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Yii::error('新用户注册失败: ' . $e->getMessage());
                return ResponseHelper::send(ApiCode::CODE_FAIL, '注册失败，请稍后再试');
            }
        }

        // --- 登录/注册/激活后处理 ---
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

        $message = $isNewUserRegistration ? '注册成功' : '账号激活成功'; // 或者可以统一返回 "操作成功"

        return $this->apiResponse(ApiCode::CODE_SUCCESS, $message, [
            'access_token' => $user->access_token,
            // openid 在手机注册场景下可能没有意义，除非特定流程需要
            // 'openid' => $this->openid,
            'user' => User::getBaseInfo($user->id) // 使用 getBaseInfo 获取安全字段
        ]);
    }
}
