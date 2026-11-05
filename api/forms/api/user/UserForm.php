<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-18
 * Time: 22:51
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

namespace app\forms\api\user;


use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\helpers\McryptHelper;
use app\helpers\RedisHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\AccessToken;
use app\models\BaseModel;
use app\models\User;
use app\services\UserService;
use Yii;

class UserForm extends BaseModel
{
    public $remarks;
    public $mobile;
    public $birthday;
    public $gender;
    public $password2;
    public $password1;
    public $avatar_url;
    public $nickname;
    public $pay_pwd;
    public $code;
    public $old_code;


    public function rules()
    {
        return [
            [['gender'], 'integer'],
            [['remarks', 'mobile', 'birthday', 'password2', 'password1', 'avatar_url', 'nickname', 'pay_pwd', 'code', 'old_code'], 'string']
        ];
    }


    /**
     * 绑定手机号(老接口 user/binding)。
     *
     * ⛔ 原来不验证码,只查「有没有别人在用」就直接写进当前账号 —— 任何登录用户都能把手机号改成别人的。
     * 手机号又是身份锚:商管按登录号自动认领后台建档的承租方,改成承租方的号就能冒领他的合同、账单、押金。
     * 现在统一走换手机号那一套(ChangeMobileForm):首次绑定验新号,已有手机号的换绑还要验旧号,输错有次数上限。
     */
    public function bind()
    {
        if (!\Yii::$app->user->identity) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户未登录');
        }
        $form = new ChangeMobileForm();
        $form->attributes = ['mobile' => $this->mobile, 'code' => $this->code, 'old_code' => $this->old_code];
        $form->mall_id = $this->mall_id;
        return $form->save();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/12
     * Time：10:40
     * Note：设置生日
     */
    public function setBirthday()
    {
        /**
         * @var  User $user
         */
        $user = \Yii::$app->user->identity;

        if ($this->birthday) {
            // year/month/day 由 User::beforeSave 统一拆分并归一化,这里只管写 birthday。
            // 原先在这里手工 explode('-'):一旦前端传 "2026/3/5" 这类分隔符,
            // $arr[1]/$arr[2] 就是未定义下标(PHP 8 报 Warning 并写入 null),
            // 而生日券的发放读的正是 month/day 两列 —— 拆错等于这个人永远收不到券。
            $user->birthday = $this->birthday;
        }

        if ($this->gender !== null && in_array($this->gender, [0, 1, 2])) {
            $user->gender = $this->gender;
        }

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '设置成功!');
    }


    public function setPassword()
    {
        if (!$this->password2 || !$this->password1) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '密码不能为空');
        }
        if ($this->password2 != $this->password1) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '两次输入的密码不一致!');
        }
        // 长度下限 —— 防止用户设"1"这种极弱密码
        if (mb_strlen($this->password1) < 6 || mb_strlen($this->password1) > 20) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '密码长度需为 6-20 位');
        }
        $user = UserHelper::getUser();
        if (!$user) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '未登录');
        }
        // 必须绑定手机号才能改密 —— 否则无法走 SMS 二次验证
        if (empty($user->mobile)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请先绑定手机号');
        }
        // [核心] SMS 二次验证 —— 防止已登录会话被劫持后直接改密锁定账户
        if (!$this->code) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入短信验证码');
        }
        // 带失败计数,见 CacheHelper::verifySmsCode
        list($ok, $msg) = CacheHelper::verifySmsCode($user->mobile, $this->code);
        if (!$ok) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $msg);
        }

        $user->password = \Yii::$app->security->generatePasswordHash($this->password1);
        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }
        CacheHelper::remove(CacheHelper::smsCodeKey($user->mobile));
        // 改密后踢出所有设备，强制重新登录
        UserService::invalidateUserAuth($user->id);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '设置成功!');
    }


    public function setAvatar()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->avatar_url || !$this->nickname) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请完善资料');
        }
        /**
         * @var User $user
         */
        $user = \Yii::$app->user->identity;
        if (!$user) {
            return ResponseHelper::send(ApiCode::CODE_NOT_LOGIN, '未登录');
        }
        $user->nickname = $this->nickname;
        $user->avatar_url = $this->avatar_url;
        $user->is_avatar = 1;
        if (!$user->save()) {
            return $this->responseErrorInfo($user);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '修改成功');
    }


    /**
     *设置支付密码
     */
    public function setPayPwd()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (!$this->pay_pwd) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入密码');
        }
        if (!$this->code) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入验证码');
        }
        $user = UserHelper::getUser();
        // 带失败计数,见 CacheHelper::verifySmsCode
        list($ok, $msg) = CacheHelper::verifySmsCode($user->mobile, $this->code);
        if (!$ok) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $msg);
        }
        $user->pay_pwd = \Yii::$app->security->generatePasswordHash($this->pay_pwd);
        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }
        CacheHelper::remove(CacheHelper::smsCodeKey($user->mobile));
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '设置成功');
    }

    /**
     * [重构] 注销账户 (使用场景验证)
     */
    public function accountDel()
    {
        $this->scenario = 'account-del'; // 设置场景
        $user = UserHelper::getUser();
        if (!$user || !$user->mobile) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请先登录并绑定手机号');
        }
        // [重要] 需要先验证验证码

        if (!$this->code) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请输入验证码');
        }
        // 带失败计数,见 CacheHelper::verifySmsCode
        list($ok, $msg) = CacheHelper::verifySmsCode($user->mobile, $this->code);
        if (!$ok) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $msg);
        }
        // --- 开始注销流程 ---
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user->backup_mobile = $user->mobile;
            // 置唯一占位而非 ''：多个注销账号都 mobile='' 会撞 (mobile,is_delete) 唯一键(老系统 1062 的根因);
            // 真号已存 backup_mobile,恢复流程按 backup_mobile 查找,与此无关。DEL_<id> 唯一、不匹配任何真手机号、放得下 varchar(15)。
            $user->mobile = 'DEL_' . $user->id;
            $user->status = User::STATUS_DELETE;
            $user->account_del_time = date('Y-m-d H:i:s');
            $user->is_delete = 1; // 标记为删除
            // [修复] 保留第三方标识，以便支持账号恢复（同一微信再次登录可激活旧号）
            // $user->openid = null;
            // $user->wechat_openid = null;
            // $user->mpwx_openid = null;
            // $user->union_id = null;
            $user->access_token = null; // 使旧 token 失效

            if (!$user->save()) {
                throw new \Exception('更新用户状态失败');
            }

            // 强制失效所有会话（含缓存清理）
            UserService::invalidateUserAuth($user->id);
            // 清除验证码缓存
            CacheHelper::remove(CacheHelper::smsCodeKey($user->backup_mobile));

            $transaction->commit();

            // [可选] 主动让当前会话登出 (如果需要)
            Yii::$app->user->logout();

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '注销成功');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('注销账户失败: ' . $e->getMessage());
            return ResponseHelper::send(ApiCode::CODE_FAIL, '注销失败，请稍后重试');
        }
    }
}
