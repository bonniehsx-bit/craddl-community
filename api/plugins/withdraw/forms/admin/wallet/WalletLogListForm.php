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

namespace app\plugins\withdraw\forms\admin\wallet;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\models\UserWalletLog;
use app\plugins\withdraw\services\AssetService;
use app\services\WalletRegistry;
use Yii;

/**
 * 后台 - 通用钱包流水(明细)列表
 *
 * 只记录「通用钱包」(插件 getWallets 声明的钱包)的入账/扣减;核心 4 资产走各自 LogHelper,不在此表。
 */
class WalletLogListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $wallet_key;
    public $type;          // 1=入账 2=扣减
    public $user_keyword;  // 用户昵称/手机号

    public function rules()
    {
        return [
            [['page', 'limit', 'type'], 'integer'],
            [['wallet_key', 'user_keyword'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        $query = UserWalletLog::find()->alias('l')
            ->where(['l.mall_id' => $mallId, 'l.is_delete' => 0]);

        if ($this->wallet_key) {
            $query->andWhere(['l.wallet_key' => $this->wallet_key]);
        }
        if ($this->type !== null && $this->type !== '') {
            $query->andWhere(['l.type' => (int)$this->type]);
        }
        if ($this->user_keyword) {
            $userIds = User::find()->select('id')
                ->where(['mall_id' => $mallId])
                ->andWhere(['or', ['like', 'nickname', $this->user_keyword], ['like', 'mobile', $this->user_keyword]])
                ->column();
            $query->andWhere(['l.user_id' => $userIds ?: [0]]);
        }

        $rows = $query->orderBy('l.id DESC')->page($pagination, $this->limit, $this->page)->all();

        $userIds = array_unique(array_map(function ($l) { return $l->user_id; }, $rows));
        $users = $userIds
            ? User::find()->select(['id', 'nickname', 'mobile', 'avatar_url'])->where(['id' => $userIds])->indexBy('id')->asArray()->all()
            : [];

        $list = array_map(function (UserWalletLog $l) use ($users) {
            return [
                'id'            => (int)$l->id,
                'user_id'       => (int)$l->user_id,
                'user_nickname' => $users[$l->user_id]['nickname'] ?? '',
                'user_avatar'   => $users[$l->user_id]['avatar_url'] ?? '',
                'user_mobile'   => $users[$l->user_id]['mobile'] ?? '',
                'wallet_key'    => $l->wallet_key,
                'wallet_label'  => AssetService::label($l->wallet_key),
                'type'          => (int)$l->type,
                'type_text'     => (int)$l->type === 1 ? '入账' : '扣减',
                'amount'        => $l->amount,
                'balance_after' => $l->balance_after,
                'title'         => $l->title,
                'remark'        => $l->remark,
                'source'        => $l->source,
                'created_at'    => $l->created_at ? date('Y-m-d H:i:s', (int)$l->created_at) : '',
            ];
        }, $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list'           => $list,
            'pagination'     => $this->getPaginationInfo($pagination),
            'wallet_options' => WalletRegistry::all($mallId), // 钱包筛选项(插件钱包,label/前缀已就绪)
        ]);
    }
}
