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
use app\events\UserEvent;
use app\models\BaseModel;
use app\models\User;
use app\models\UserWalletLog;
use app\services\WalletRegistry;
use app\services\WalletService;
use Yii;

/**
 * 后台用户编辑 —— 扩展钱包(user_wallet)的列出与增减。
 *
 * 挂载:注册表驱动。核心不认识具体插件钱包名,一切来自各插件 Plugin::getWallets() 声明,
 *       经 WalletRegistry 汇总(本商城授权 + withdrawable)。插件装了并声明钱包,卡片自动出现。
 * 增减:通用接口,底层走核心 WalletService::add/sub(原子 + UserWalletLog 流水),
 *       完成后触发 UserEvent::WALLET_CHANGED,由声明该钱包的插件监听做副作用(核心不碰插件语义)。
 *
 * 注意:此处【不套】C 端逐用户可见性 WalletRegistry::isVisible —— 后台需要管理全部授权钱包,
 *       包括余额为 0 / C 端隐藏的(否则无法给零余额钱包首次入账)。isVisible 仅约束 C 端展示。
 */
class UserWalletForm extends BaseModel
{
    public $user_id;
    public $wallet_key;
    public $way;   // 1=增加 2=减少
    public $num;
    public $remarks;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'way', 'page', 'limit'], 'integer'],
            [['wallet_key', 'remarks'], 'string'],
            [['num'], 'number', 'min' => 0.01, 'message' => '变更数量必须大于0'],
        ];
    }

    /** 某扩展钱包的进出流水(供后台明细弹窗;统一 type==1 为入账) */
    public function walletLog()
    {
        $user = $this->findUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在！');
        }
        $pagination = null;
        $list = UserWalletLog::find()
            ->where(['user_id' => (int)$user->id, 'wallet_key' => (string)$this->wallet_key, 'is_delete' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->page($pagination, $this->limit ?: 10, $this->page ?: 1)
            ->all();
        $rows = [];
        foreach ($list as $l) {
            $rows[] = [
                'type'          => (int)$l->type,        // 1=入账 2=扣减
                'amount'        => $l->amount,
                'balance_after' => $l->balance_after,
                'title'         => $l->title,
                'remark'        => $l->remark,
                'created_at'    => $l->created_at ? date('Y-m-d H:i:s', (int)$l->created_at) : '',
            ];
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $rows, 'pagination' => $pagination]);
    }

    /** 本商城授权且 withdrawable 的钱包 + 该用户余额(供后台渲染钱包卡) */
    public function list()
    {
        $user = $this->findUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在！');
        }

        $rows = [];
        foreach (WalletRegistry::all((int)$user->mall_id) as $w) {
            $rows[] = [
                'key'     => $w['key'],
                'name'    => $w['name'],
                'balance' => WalletService::balance((int)$user->id, $w['key']),
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $rows]);
    }

    /** 增减某扩展钱包 */
    public function charge()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $user = $this->findUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在！');
        }
        if (!in_array((int)$this->way, [1, 2], true)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无效的操作方式');
        }
        // 防非法/越权 key:必须是本商城授权钱包(与 list 同源)
        $authorized = array_column(WalletRegistry::all((int)$user->mall_id), null, 'key');
        $wallet = $authorized[$this->wallet_key] ?? null;
        if (!$wallet) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无效的钱包类型');
        }

        $adminId = Yii::$app->admin->identity ? (int)Yii::$app->admin->identity->id : 0;
        $actionText = ((int)$this->way === 1)
            ? \app\helpers\NounHelper::parse('charge_add')
            : \app\helpers\NounHelper::parse('charge_sub');
        $remark = $this->remarks ?: $actionText;
        // 审计:UserWalletLog 无 admin_id 列,把操作人写进 source 留痕(免加列)
        $source = '后台调整#admin' . $adminId;

        try {
            if ((int)$this->way === 1) {
                WalletService::add((int)$user->id, $this->wallet_key, $this->num, $remark, $wallet['name'], $source);
            } else {
                // force=false:余额不足即抛,后台扣减不允许扣成负数
                WalletService::sub((int)$user->id, $this->wallet_key, $this->num, $remark, $wallet['name'], $source, false);
            }
        } catch (\Throwable $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }

        $balanceAfter = WalletService::balance((int)$user->id, $this->wallet_key);

        // 触发事件:声明该钱包的插件监听做后续副作用(核心不认识插件语义)
        try {
            Yii::$app->trigger(UserEvent::WALLET_CHANGED, new UserEvent([
                'user_id'       => (int)$user->id,
                'wallet_key'    => $this->wallet_key,
                'way'           => (int)$this->way,
                'amount'        => number_format((float)$this->num, 2, '.', ''),
                'balance_after' => $balanceAfter,
                'admin_id'      => $adminId,
                'remark'        => $remark,
            ]));
        } catch (\Throwable $e) {
            Yii::error('UserEvent::WALLET_CHANGED 触发异常 uid=' . $user->id . ' key=' . $this->wallet_key . ' - ' . $e->getMessage());
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功', ['balance' => $balanceAfter]);
    }

    private function findUser()
    {
        return User::find()->where([
            'id' => $this->user_id,
            'mall_id' => Yii::$app->mall->id,
        ])->one();
    }
}
