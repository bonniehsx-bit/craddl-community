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

namespace app\plugins\mch\forms\admin\passport;

use app\core\ApiCode;
use app\helpers\Helper;
use app\helpers\LoginHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Yii;


/**
 * 商家管理端登录表单
 */
class MchLoginForm extends BaseModel
{
    public $username;
    public $password;
    public $captcha;
    public $captcha_key; // [新增] 用于接收前端传来的 key

    public function rules()
    {
        return [
            [['username', 'password', 'captcha', 'captcha_key'], 'required', 'message' => '{attribute}不能为空'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username' => '手机号',
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

        // 1. [核心] 校验验证码

        if (!LoginHelper::checkAuthCode($this->captcha_key, $this->captcha)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '验证码错误或已失效');
        }

        // 1. [核心改造] 跨商城查找手机号对应的所有身份
        $users = User::find()->where([
            'mobile' => $this->username,
            'is_delete' => 0
        ])->all();

        if (empty($users)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '账号不存在');
        }

        $validMalls = [];
        $validUser = null;
        $mch = null;
        $isClerk = false;

        // 尝试获取前端是否已经特意指定了要登录的 mall_id (用于第二阶段提交，向后兼容老前端)
        $explicitMallId = Yii::$app->request->headers->get('X-Mall-Id') ?: Yii::$app->request->post('mall_id');
        // mch_id 全局唯一，用于同城多店的精确定位（第二阶段提交的首选依据）
        $explicitMchId = Yii::$app->request->headers->get('X-Mch-Id') ?: Yii::$app->request->post('mch_id');

        // 遍历这批账号，寻找一个"密码正确"且"有商家/店员身份"的记录
        foreach ($users as $u) {
            // 先校验密码
            if (Yii::$app->security->validatePassword($this->password, $u->password)) {
                // 判断状态
                if ($u->status != User::STATUS_NORMAL) {
                    continue; // 可能在该商城被封禁
                }

                // 获取商城基础信息以便展示
                $mallInfo = \app\models\Mall::findOne($u->mall_id);
                if (!$mallInfo || $mallInfo->is_delete == 1 || $mallInfo->is_stop == 1) {
                    continue;
                }

                // 枚举该用户在本商城的【所有】有效店铺身份，按 mch_id 去重（支持同城多店）
                // 键：mch_id => ['mch' => Mch, 'is_clerk' => bool]；店长身份优先，不被店员记录覆盖
                $profiles = [];

                // 1) 店长：该用户在本商城拥有的所有店铺
                $ownMchList = Mch::find()->where([
                    'user_id' => $u->id,
                    'mall_id' => $u->mall_id,
                    'is_delete' => 0,
                    'status' => 1
                ])->all();
                foreach ($ownMchList as $ownMch) {
                    $profiles[$ownMch->id] = ['mch' => $ownMch, 'is_clerk' => false];
                }

                // 2) 店员：该用户在本商城被授权的所有店铺（已作为店长收录的跳过）
                $clerkList = MchClerk::find()->where([
                    'user_id' => $u->id,
                    'mall_id' => $u->mall_id,
                    'is_delete' => 0,
                    'status' => 1
                ])->all();
                foreach ($clerkList as $clerk) {
                    if (isset($profiles[$clerk->mch_id])) {
                        continue; // 该店已按店长身份收录
                    }
                    $clerkMch = Mch::findOne([
                        'id' => $clerk->mch_id,
                        'mall_id' => $u->mall_id,
                        'is_delete' => 0,
                        'status' => 1
                    ]);
                    if ($clerkMch) {
                        // MchClerk.is_manager=1 亦视为店长，与 MchFilter/actionUserInfo 口径一致
                        $profiles[$clerkMch->id] = ['mch' => $clerkMch, 'is_clerk' => !((bool)$clerk->is_manager)];
                    }
                }

                // 逐店铺加入候选列表（同一 User 行可能产出多条：同城多店）
                foreach ($profiles as $profile) {
                    $tempMch = $profile['mch'];
                    $validMalls[] = [
                        'mall_info' => $mallInfo,
                        'user_model' => $u,
                        'mch_model' => $tempMch,
                        'is_clerk' => $profile['is_clerk'],

                        // 以下是用于前端展示的字段
                        'mall_id' => $u->mall_id,
                        'mall_name' => $mallInfo->name,
                        'user_id' => $u->id,
                        'mch_id' => $tempMch->id,
                        'mch_name' => $tempMch->mch_name,
                        'logo' => $tempMch->logo,
                    ];
                }
            }
        }

        if (empty($validMalls)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '密码错误或您没有商家后台权限');
        }

        // 此时我们有了一个有效的候选列表 `$validMalls`（每条 = 一家店铺，同城多店会有多条）

        $validUser = null;
        $mch = null;
        $isClerk = false;
        $matchedCandidate = null;

        // 决策树：
        // 1. 只有一个有效店铺 → 无视前端传参直接登录（解决残留 cookie 冲突）
        if (count($validMalls) == 1) {
            $matchedCandidate = $validMalls[0];
        }

        // 2. 前端精确指定了 mch_id 且命中候选 → 登录该店铺（同城多店精确定位）
        if (!$matchedCandidate && $explicitMchId) {
            foreach ($validMalls as $candidate) {
                if ($candidate['mch_id'] == $explicitMchId) {
                    $matchedCandidate = $candidate;
                    break;
                }
            }
        }

        // 3. 向后兼容：老前端仅传 mall_id，且该商城下候选唯一（非同城多店）→ 登录之
        if (!$matchedCandidate && $explicitMallId) {
            $inMall = array_values(array_filter($validMalls, function ($c) use ($explicitMallId) {
                return $c['mall_id'] == $explicitMallId;
            }));
            if (count($inMall) == 1) {
                $matchedCandidate = $inMall[0];
            }
        }

        // 4. 仍未确定（多身份且未精确指定 / 传了失效残留值 / 同城多店未选店）→ 触发弹窗重选
        if (!$matchedCandidate) {
            $frontendList = array_map(function ($item) {
                return [
                    'mall_id' => $item['mall_id'],
                    'mall_name' => $item['mall_name'],
                    'user_id' => $item['user_id'],
                    'mch_id' => $item['mch_id'],
                    'mch_name' => $item['mch_name'],
                    'logo' => $item['logo'],
                    'is_clerk' => $item['is_clerk']
                ];
            }, $validMalls);

            return $this->apiResponse(2, '您管理着多家店铺，请选择要进入的店铺', [
                'malls' => $frontendList
            ]);
        }

        // 解构最终要登录的身份信息
        $validUser = $matchedCandidate['user_model'];
        $mch = $matchedCandidate['mch_model'];
        $isClerk = $matchedCandidate['is_clerk'];

        $user = $validUser;

        // 4. 生成/更新 Token
        $accessToken = Helper::createUserToken(); // 复用 Helper 生成 UUID
        $user->access_token = $accessToken;
        $user->saveToken();

        // 验证码一次性消费：仅在真正签发 token 成功后失效，
        // 避免影响 code=2 选店后的两阶段重登（此前该验证码需继续复用）
        LoginHelper::removeAuthCode($this->captcha_key);

        // 5. 返回商家专用信息
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '登录成功', [
            'access_token' => $accessToken,
            'mall_id' => $user->mall_id, // [核心新增] 返回自动识别的 mall_id 供前端绑定
            'mch_info' => [
                'id' => $mch->id,
                'name' => $mch->mch_name,
                'logo' => $mch->logo,
                'is_clerk' => $isClerk, // 告诉前端是否是店员
                'user_id' => $user->id,
                'nickname' => $user->nickname
            ]
        ]);
    }
}