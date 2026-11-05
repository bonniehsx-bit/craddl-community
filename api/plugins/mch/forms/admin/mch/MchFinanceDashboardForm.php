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

namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\BalanceLog;
use app\models\IntegralLog;
use app\models\PriceLog;
use app\models\UserWalletLog;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\services\MchSettlementService;
use app\services\WalletRegistry;
use Yii;

/**
 * 商家财务面板 —— **只做统计展示**,不参与任何资金决策。
 *
 * 「货款进哪个账户」由 mch_setting.mch_settle_target 决定(默认 commission → User.price,
 * 也可配成扩展钱包 mch.funds)。本页不改这个逻辑,只是**跟着它走**:
 * 解析出实际账户后,余额与流水都读那个账户,保证页面数字与商家提现时看到的一致。
 *
 * 流水按账户取自不同的表:
 *   price    → price_log
 *   balance  → balance_log
 *   扩展钱包  → user_wallet_log(wallet_key = 该钱包 key,如 mch.funds)
 */
class MchFinanceDashboardForm extends BaseModel
{
    public $page;
    public $limit = 20;
    public $log_type; // ''=全部 | 'in'=入账 | 'out'=支出

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['log_type'], 'string'],
        ];
    }

    public function getData()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在');
        }
        $stats = $this->getStats($mch);
        list($list, $pagination) = $this->getLogList($mch);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'stats' => $stats,
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination),
        ]);
    }

    private function getStats(Mch $mch)
    {
        $user = User::findOne($mch->user_id);
        $mallId = (int) $mch->mall_id;

        // 实际结算账户:跟着 mch_settle_target 走。本页只展示,不改这个配置。
        $settleAsset = MchSettlementService::resolvePayoutType(MchSettlementService::configuredTarget($mallId));

        return [
            // 当前余额:与提现页读的是同一个数
            'balance'           => number_format($user ? MchSettlementService::withdrawableForPayoutType($user, $settleAsset) : 0.0, 2, '.', ''),
            'settle_asset'      => $settleAsset,
            'settle_asset_name' => $this->assetName($settleAsset),
            // 提现入口是否可用:提现插件总开关 + 该资产入口开关 + 该资产有可用渠道,三者都要满足。
            // 后台关掉任一项,这里就置灰,免得商家点进去看到空列表还以为坏了。
            'withdrawable'      => $this->canWithdraw($mallId, $settleAsset) ? 1 : 0,
        ];
    }

    /**
     * 该资产当前能否提现。判定完全交给提现插件的配置,本页不自己定规则。
     * 插件未安装时 class_exists 兜底(见 hasWithdrawChannelFor),不误报。
     */
    private function canWithdraw(int $mallId, string $asset): bool
    {
        $settingClass = '\\app\\plugins\\withdraw\\forms\\admin\\setting\\SettingForm';
        if (!class_exists($settingClass)) {
            return false; // 没装提现插件,自然无处可提
        }
        if (!$settingClass::entryEnabled($mallId, $asset)) {
            return false; // 总开关或该资产入口被后台关了
        }
        return MchSettlementService::hasWithdrawChannelFor($mallId, $asset);
    }

    /** 账户展示名:核心三资产写死,扩展钱包取注册表(走品牌词库) */
    private function assetName(string $asset): string
    {
        $core = ['price' => '货款', 'balance' => '余额', 'integral' => '积分'];
        if (isset($core[$asset])) {
            return $core[$asset];
        }
        return WalletRegistry::exists($asset) ? WalletRegistry::label($asset) : $asset;
    }


    /**
     * 账户 → 流水来源。返回 [模型类, 金额列, 基础查询条件, 余额后列, 标题列, 备注列]。
     *
     * 核心三资产各有自己的流水表,扩展钱包统一在 user_wallet_log 里按 wallet_key 区分 ——
     * 与 withdraw 插件的资金明细同一套口径,避免两处对同一笔钱给出不同的账。
     */
    /**
     * 账户 → 流水来源。返回 [模型类, 金额列, 基础条件, 余额后列, 标题列, 备注列, 入账type, 支出type]。
     *
     * ⚠ 四张流水表的共同规则:**金额列恒为正数,方向看 type**(实测 balance_log/integral_log/price_log
     * 里没有任何负数金额)。差别只在 type 的取值:
     *   核心三资产:1=增加 / 0=减少
     *   扩展钱包  :1=入账 / 2=扣减
     * 所以判断方向必须用各自的 type 值,不能靠"金额是否大于 0" —— 那样所有支出都会被算成入账
     * (实测:一笔"退款追回"曾被显示成 +10.00,而余额实际是减少的)。
     */
    private function logSource(int $mallId, int $userId, string $asset): array
    {
        $base = ['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0];
        switch ($asset) {
            case 'price':
                return [PriceLog::class, 'price', $base, 'current', 'title', 'remarks', 1, 0];
            case 'balance':
                return [BalanceLog::class, 'money', $base, 'current', 'title', 'content', 1, 0];
            case 'integral':
                return [IntegralLog::class, 'integral', $base, 'current', 'title', 'content', 1, 0];
            default:
                return [UserWalletLog::class, 'amount', $base + ['wallet_key' => $asset], 'balance_after', 'title', 'remark',
                    UserWalletLog::TYPE_ADD, UserWalletLog::TYPE_SUB];
        }
    }

    /**
     * 资金流水 —— 读**钱包账本**,不是分润记录。
     *
     * 之前这里查的是 CommissionLog(分润流水),那是"这笔佣金怎么算出来的",
     * 只有进、没有出:商家提现、退款回冲、人工调账统统看不到,和余额也对不上。
     * 现在改成读账户自己的流水表,一进一出都在,期末余额能和顶部的"可提现"对上。
     *
     * 表按账户分:price→price_log,balance→balance_log,扩展钱包→user_wallet_log(按 wallet_key)。
     */
    private function getLogList(Mch $mch)
    {
        $mallId = (int) $mch->mall_id;
        $userId = (int) $mch->user_id;
        $asset  = MchSettlementService::resolvePayoutType(MchSettlementService::configuredTarget($mallId));

        [$model, $amtCol, $where, $afterCol, $titleCol, $remarkCol, $inType, $outType]
            = $this->logSource($mallId, $userId, $asset);

        $query = $model::find()->where($where);
        if ($this->log_type === 'in') {
            $query->andWhere(['type' => $inType]);
        } elseif ($this->log_type === 'out') {
            $query->andWhere(['type' => $outType]);
        }

        $pagination = new \yii\data\Pagination([
            'totalCount' => (int) $query->count(),
            'pageSize'   => $this->limit,
            'page'       => $this->page - 1,
        ]);

        $logs = $query->orderBy(['id' => SORT_DESC])
            ->limit($pagination->limit)
            ->offset($pagination->offset)
            ->asArray()
            ->all();

        $list = [];
        foreach ($logs as $log) {
            $isIn = ((int) $log['type'] === (int) $inType);
            $abs  = abs((float) ($log[$amtCol] ?? 0));

            $list[] = [
                'id'            => $log['id'],
                'log_type'      => $isIn ? 'in' : 'out',
                'amount'        => ($isIn ? '+' : '-') . number_format($abs, 2, '.', ''),
                'title'         => (string) ($log[$titleCol] ?? ''),
                'remark'        => (string) ($log[$remarkCol] ?? ''),
                // 变动后余额:让商家能逐笔对账 —— 这正是原来查分润流水给不了的
                'balance_after' => isset($log[$afterCol]) ? number_format((float) $log[$afterCol], 2, '.', '') : '',
                'created_at'    => $log['created_at'],
                'created_time'  => date('Y-m-d H:i', (int) $log['created_at']),
                'status_text'   => $isIn ? '入账' : '支出',
            ];
        }

        return [$list, $pagination];
    }
}
