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

namespace app\plugins\withdraw\forms\api;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\BalanceLog;
use app\models\IntegralLog;
use app\models\PriceLog;
use app\models\User;
use app\models\UserWalletLog;
use app\plugins\withdraw\forms\admin\setting\SettingForm;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\AssetService;
use Yii;

/**
 * C 端 - 通用钱包(总览 + 明细)
 *
 * 全钱包通用:核心 3(佣金 price / 余额 balance / 积分 integral)+ 任意插件 getWallets() 声明的
 * 可提现钱包(渠道专户 xwy.channel / 货款 mch.funds / 未来任意钱包)都走同一套接口与页面,
 * 新增钱包零改动自动纳入(经 AssetService 桥接 + WalletRegistry)。
 */
class WalletForm extends BaseModel
{
    public $asset_type;
    public $page = 1;
    public $limit = 20;

    public function rules()
    {
        return [
            [['asset_type'], 'string'],
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    /**
     * 钱包总览:列出本商城全部钱包 + 当前余额 + 是否可提现。
     */
    public function overview()
    {
        $mallId = (int)Yii::$app->mall->id;
        $user = User::findOne(Yii::$app->user->id);
        if (!$user) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户不存在'];
        }
        $setting = SettingForm::get($mallId);
        $entries = is_array($setting['entries'] ?? null) ? $setting['entries'] : [];
        $withdrawEnabled = (int)($setting['enabled'] ?? 0) === 1;

        $list = [];
        // 传 userId:扩展钱包按逐用户可见性过滤(如渠道专户仅对开了后台开关的用户显示)
        foreach (AssetService::assetOptions($mallId, (int)$user->id) as $o) {
            $key = $o['key'];
            // 积分是「分」不是「元」:单位区分 + 是否计入「合计总额(元)」,避免积分被当钱加进总额
            $isPoints = ($key === Channel::ASSET_INTEGRAL);
            $list[] = [
                'asset_type'   => $key,
                'name'         => $o['label'],
                'plugin_label' => $o['plugin_label'] ?? '',
                // 积分为整数口径,其余 2 位小数
                'balance'      => $this->fmt($key, AssetService::balance($user, $key)),
                'unit'         => $isPoints ? '积分' : '元',
                'in_total'     => $isPoints ? 0 : 1, // 仅「元」类计入合计总额
                'withdrawable' => ($withdrawEnabled && (int)($entries[$key] ?? 0) === 1) ? 1 : 0,
            ];
        }
        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['list' => $list]];
    }

    /**
     * 通用钱包明细:任意 asset_type 的收支流水。
     * 核心 3 走各自 log 表(price_log/balance_log/integral_log),扩展钱包走 user_wallet_log(按 wallet_key 过滤)。
     */
    public function logs()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;
        $userId = (int)Yii::$app->user->id;
        $asset = (string)$this->asset_type;
        if (!AssetService::isValid($asset)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '未知的资产类型'];
        }
        // 逐用户可见性:扩展钱包对该用户不可见则拒绝(防深链直接看隐藏钱包明细)
        if (\app\services\WalletRegistry::exists($asset) && !\app\services\WalletRegistry::isVisible($asset, $userId)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '无权查看该资金明细'];
        }

        // 核心 3 资产的归一化列:[模型, 金额列, 余额后列, 标题列, 备注列]
        $map = [
            Channel::ASSET_PRICE    => [PriceLog::class,    'price',    'current', 'title', 'remarks'],
            Channel::ASSET_BALANCE  => [BalanceLog::class,  'money',    'current', 'title', 'content'],
            Channel::ASSET_INTEGRAL => [IntegralLog::class, 'integral', 'current', 'title', 'content'],
        ];

        if (isset($map[$asset])) {
            [$model, $amtCol, $afterCol, $titleCol, $remarkCol] = $map[$asset];
            $query = $model::find()->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0]);
        } else {
            // 扩展钱包统一走 user_wallet_log
            $model = UserWalletLog::class;
            $amtCol = 'amount';
            $afterCol = 'balance_after';
            $titleCol = 'title';
            $remarkCol = 'remark';
            $query = UserWalletLog::find()->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0, 'wallet_key' => $asset]);
        }

        $pagination = null;
        $rows = $query->orderBy('id DESC')->page($pagination, $this->limit, $this->page)->all();

        $list = [];
        foreach ($rows as $r) {
            // 收支方向统一:核心 type 1=增 / 扩展钱包 type 1=入账 → 「==1 即收入」,其余为支出
            $isIncome = ((int)$r->type === 1);
            $amtAbs = $this->fmt($asset, abs((float)$r->{$amtCol}));
            $after = ($r->{$afterCol} === null || $r->{$afterCol} === '') ? '' : $this->fmt($asset, $r->{$afterCol});
            $list[] = [
                'is_income'     => $isIncome ? 1 : 0,
                'amount'        => ($isIncome ? '+' : '-') . $amtAbs,
                'balance_after' => $after,
                'title'         => (string)($r->{$titleCol} ?? ''),
                'remark'        => (string)($r->{$remarkCol} ?? ''),
                'created_at'    => $r->created_at ? date('Y-m-d H:i', (int)$r->created_at) : '',
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'asset_type' => $asset,
                'name'       => AssetService::label($asset),
                'list'       => $list,
                'pagination' => $pagination,
            ],
        ];
    }

    /** 金额格式化:积分整数,其余 2 位小数 */
    private function fmt(string $asset, $value): string
    {
        $p = ($asset === Channel::ASSET_INTEGRAL) ? 0 : 2;
        return number_format((float)$value, $p, '.', '');
    }
}
