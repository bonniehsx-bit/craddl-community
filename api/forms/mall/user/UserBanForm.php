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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\User;
use app\models\UserBanLog;
use app\services\UserService;
use Yii;
use yii\helpers\Json;

/**
 * 后台拉黑/解封单一入口
 *
 * 替代原先"UserController::actionSetUserInfo 里 key=status 的匿名分支" +
 * 已删的 "mall/UserForm::accountDel() 拉黑切换死方法"。
 *
 * - ban()   必填 reason, 可选 banned_until (空=永久)
 * - unban() 可选 reason (记录日志, 给运营写备注)
 *
 * 每次操作都往 user_ban_log 写一条审计记录 (谁/何时/为什么/快照前状态)。
 */
class UserBanForm extends BaseModel
{
    public $user_id;
    public $reason;
    public $banned_until; // 时间戳, 空 = 永久

    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'banned_until'], 'integer'],
            [['reason'], 'string', 'max' => 255],
            [['reason'], 'trim'],
        ];
    }

    /**
     * 执行拉黑
     * 新 status = STATUS_BLACK
     * 已是 BLACK → 允许"续期", 更新 reason/until
     * 已注销 (STATUS_DELETE) → 拒绝 (要拉黑必须先走过注销恢复流程)
     */
    public function ban()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (empty($this->reason)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '请填写封禁原因');
        }
        $user = $this->findUser();
        if (!$user instanceof User) {
            return $user;
        }
        if ($user->status == User::STATUS_DELETE) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该用户已注销, 不能拉黑');
        }

        $operator = $this->getOperator();
        $snapshot = [
            'status'       => (int)$user->status,
            'ban_reason'   => (string)$user->ban_reason,
            'banned_until' => $user->banned_until,
        ];

        $user->status       = User::STATUS_BLACK;
        $user->ban_reason   = $this->reason;
        $user->banned_by    = $operator['id'];
        $user->banned_at    = time();
        $user->banned_until = $this->banned_until ?: null;

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        // 踢全设备 session
        UserService::invalidateUserAuth($user->id);

        // 写审计
        $log = new UserBanLog();
        $log->user_id       = $user->id;
        $log->action        = UserBanLog::ACTION_BAN;
        $log->reason        = $this->reason;
        $log->operator_id   = $operator['id'];
        $log->operator_name = $operator['name'];
        $log->banned_until  = $this->banned_until ?: null;
        $log->snapshot      = Json::encode($snapshot);
        $log->save();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '拉黑成功');
    }

    /**
     * 执行解封
     * 只允许对 STATUS_BLACK 操作; 注销用户不可通过本接口解封
     */
    public function unban()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $user = $this->findUser();
        if (!$user instanceof User) {
            return $user;
        }
        if ($user->status != User::STATUS_BLACK) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户当前不在拉黑状态');
        }

        $operator = $this->getOperator();
        $snapshot = [
            'status'       => (int)$user->status,
            'ban_reason'   => (string)$user->ban_reason,
            'banned_until' => $user->banned_until,
        ];

        $user->status       = User::STATUS_NORMAL;
        $user->ban_reason   = '';
        $user->banned_by    = 0;
        $user->banned_at    = 0;
        $user->banned_until = null;

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        $log = new UserBanLog();
        $log->user_id       = $user->id;
        $log->action        = UserBanLog::ACTION_UNBAN;
        $log->reason        = $this->reason ?: '';
        $log->operator_id   = $operator['id'];
        $log->operator_name = $operator['name'];
        $log->snapshot      = Json::encode($snapshot);
        $log->save();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '解封成功');
    }

    /**
     * 后台代为注销账号(正常/拉黑 → 已注销)
     *
     * 字段变化与 C 端自助注销(api\forms\api\user\UserForm::accountDel)保持一致:
     * 真号存入 backup_mobile,mobile 置 DEL_<id> 唯一占位(多个注销号 mobile='' 会撞 (mobile,is_delete) 唯一键)。
     * 该操作可由 restore() 完整还原。
     */
    public function del()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $user = $this->findUser();
        if (!$user instanceof User) {
            return $user;
        }
        if ((int)$user->status === User::STATUS_DELETE) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该用户已是注销状态');
        }

        $operator = $this->getOperator();
        $snapshot = [
            'status'        => (int)$user->status,
            'is_delete'     => (int)$user->is_delete,
            'mobile'        => (string)$user->mobile,
            'backup_mobile' => (string)$user->backup_mobile,
        ];

        $user->backup_mobile    = $user->mobile;
        $user->mobile           = 'DEL_' . $user->id;
        $user->status           = User::STATUS_DELETE;
        $user->is_delete        = 1;
        $user->account_del_time = date('Y-m-d H:i:s');

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        UserService::invalidateUserAuth($user->id);

        $log = new UserBanLog();
        $log->user_id       = $user->id;
        $log->action        = UserBanLog::ACTION_DELETE;
        $log->reason        = $this->reason ?: '后台代为注销';
        $log->operator_id   = $operator['id'];
        $log->operator_name = $operator['name'];
        $log->snapshot      = Json::encode($snapshot);
        $log->save();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '账号已注销');
    }

    /**
     * 恢复已注销账号(已注销 → 正常)
     *
     * 注销时真号存在 backup_mobile、mobile 是 DEL_<id> 占位。恢复前必须确认真号未被他人占用,
     * 否则 save() 会撞 (mobile, is_delete) 唯一键报 1062。
     */
    public function restore()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $user = $this->findUser();
        if (!$user instanceof User) {
            return $user;
        }
        if ((int)$user->status !== User::STATUS_DELETE) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '该用户不是已注销状态');
        }

        $realMobile = trim((string)$user->backup_mobile);
        if ($realMobile !== '') {
            $taken = User::find()
                ->where(['mall_id' => $user->mall_id, 'mobile' => $realMobile, 'is_delete' => 0])
                ->andWhere(['!=', 'id', $user->id])
                ->exists();
            if ($taken) {
                return ResponseHelper::send(ApiCode::CODE_FAIL, '原手机号 ' . $realMobile . ' 已被其他账号占用,无法恢复');
            }
        }

        $operator = $this->getOperator();
        $snapshot = [
            'status'           => (int)$user->status,
            'is_delete'        => (int)$user->is_delete,
            'mobile'           => (string)$user->mobile,
            'backup_mobile'    => (string)$user->backup_mobile,
            'account_del_time' => $user->account_del_time,
        ];

        $user->status = User::STATUS_NORMAL;
        $user->is_delete = 0;
        if ($realMobile !== '') {
            $user->mobile = $realMobile;
            $user->backup_mobile = null;
        }
        $user->account_del_time = null;

        if (!$user->save()) {
            return $this->responseErrorMsg($user);
        }

        $log = new UserBanLog();
        $log->user_id       = $user->id;
        $log->action        = UserBanLog::ACTION_RESTORE;
        $log->reason        = $this->reason ?: '后台恢复注销账号';
        $log->operator_id   = $operator['id'];
        $log->operator_name = $operator['name'];
        $log->snapshot      = Json::encode($snapshot);
        $log->save();

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '账号已恢复正常');
    }

    private function findUser()
    {
        // 注意:不加 is_delete 过滤 —— 恢复注销必须能查到 is_delete=1 的用户
        $user = User::findOne(['id' => $this->user_id, 'mall_id' => $this->mall_id]);
        if (!$user) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '用户不存在');
        }
        return $user;
    }

    private function getOperator()
    {
        $id = 0;
        $name = '系统';
        if (Yii::$app->admin && Yii::$app->admin->identity) {
            $id   = (int)Yii::$app->admin->identity->id;
            $name = (string)(Yii::$app->admin->identity->nickname ?: Yii::$app->admin->identity->username);
        }
        return ['id' => $id, 'name' => $name];
    }
}
