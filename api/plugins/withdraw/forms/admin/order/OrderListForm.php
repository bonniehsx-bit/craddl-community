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

namespace app\plugins\withdraw\forms\admin\order;

use app\core\ApiCode;
use app\funds\BaseFund;
use app\models\BaseModel;
use app\models\User;
use app\models\UserSettlementInfo;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\models\WithdrawOrder;
use app\plugins\withdraw\services\AssetService;
use Yii;

/**
 * 后台 - 提现单列表 / 详情
 */
class OrderListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $order_no;
    public $status;
    public $asset_type;
    public $channel_id;   // 提现渠道(= 列表「提现渠道」列展示的 channel_name,与后台「提现渠道」配置页同源)
    public $user_type;
    public $user_keyword; // 用户昵称/手机号

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'channel_id'], 'integer'],
            [['order_no', 'asset_type', 'user_type', 'user_keyword'], 'string'],
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

        $query = WithdrawOrder::find()->alias('o')
            ->where(['o.mall_id' => $mallId, 'o.is_delete' => 0]);

        if ($this->order_no) {
            $query->andWhere(['like', 'o.order_no', $this->order_no]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['o.status' => (int)$this->status]);
        }
        if ($this->asset_type) {
            $query->andWhere(['o.asset_type' => $this->asset_type]);
        }
        // 按提现渠道筛选:与列表「提现渠道」列(channel_name)同一维度。
        // 用 channel_id 而不是 channel_name:渠道改名后历史单仍存旧名,按 id 筛才不会漏。
        if ($this->channel_id) {
            $query->andWhere(['o.channel_id' => (int)$this->channel_id]);
        }
        if ($this->user_type) {
            $query->andWhere(['o.user_type' => $this->user_type]);
        }
        if ($this->user_keyword) {
            $userIds = User::find()->select('id')
                ->where(['mall_id' => $mallId])
                ->andWhere(['or', ['like', 'nickname', $this->user_keyword], ['like', 'mobile', $this->user_keyword]])
                ->column();
            $query->andWhere(['o.user_id' => $userIds ?: [0]]);
        }

        $rows = $query->orderBy('o.id DESC')->page($pagination, $this->limit, $this->page)->all();

        // 批量取用户展示信息
        $userIds = array_unique(array_map(function ($o) { return $o->user_id; }, $rows));
        $users = $userIds ? User::find()->select(['id', 'nickname', 'mobile', 'avatar_url'])->where(['id' => $userIds])->indexBy('id')->asArray()->all() : [];

        $list = array_map(function (WithdrawOrder $o) use ($users) {
            return [
                'id'            => (int)$o->id,
                'order_no'      => $o->order_no,
                'user_id'       => (int)$o->user_id,
                'user_nickname' => $users[$o->user_id]['nickname'] ?? '',
                'user_avatar'   => $users[$o->user_id]['avatar_url'] ?? '',
                'user_mobile'   => $users[$o->user_id]['mobile'] ?? '',
                'user_type'     => $o->user_type,
                'channel_name'  => $o->channel_name,
                'destination'   => $o->destination,
                'asset_type'    => $o->asset_type,
                'asset_label'   => AssetService::label($o->asset_type),
                'amount'        => $o->amount,
                'fee'           => $o->fee,
                'tax'           => $o->tax,
                'actual_amount' => $o->actual_amount,
                // 收款账户：摘要(窄屏) + 完整 label/value 列表(对齐老 cash 列内铺开) + 网关名
                'payout_summary' => $this->accountSummary($o),
                'payout_list'    => $this->resolvePayoutInfo($o),
                'fund_channel_name' => $this->getChannelName($o->fund_class),
                'receipt'       => $o->receipt,
                'status'        => (int)$o->status,
                'status_text'   => $o->getStatusText(),
                // 失败/退回原因(如快付返回的"IP白名单未配置..."),列表直接可见,无需点详情
                'fail_reason'   => $o->fail_reason,
                'created_at'    => $o->created_at ? date('Y-m-d H:i:s', (int)$o->created_at) : '',
            ];
        }, $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination),
            'status_map' => WithdrawOrder::$statusMap,
            'asset_options' => AssetService::assetOptions($mallId), // 资金来源筛选项(核心3 + 授权插件钱包)
            // 提现渠道筛选项:即后台「提现渠道」里配置的渠道,与列表 channel_name 同源。
            // 含已停用渠道:历史单据仍需可筛,否则停用某渠道后那批单子再也筛不出来。
            'channel_options' => Channel::find()
                ->select(['id', 'name'])
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
                ->asArray()->all(),
            // 「线下打款」是否可用(ManualFund 已在资金渠道里开启)。
            // 前端据此显示/隐藏按钮 —— 只是提示,真正的闸门在 WithdrawService::payout 里。
            'offline_payout_enabled' => \app\plugins\withdraw\services\WithdrawService::isOfflinePayoutEnabled($mallId),
        ]);
    }

    public function getDetail($id)
    {
        $mallId = (int)Yii::$app->mall->id;
        $o = WithdrawOrder::findOne(['id' => $id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$o) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '提现单不存在');
        }
        $u = User::find()->select(['id', 'nickname', 'mobile', 'avatar_url'])->where(['id' => $o->user_id])->asArray()->one();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'id'            => (int)$o->id,
            'order_no'      => $o->order_no,
            'user_id'       => (int)$o->user_id,
            'user_nickname' => $u['nickname'] ?? '',
            'user_avatar'   => $u['avatar_url'] ?? '',
            'user_mobile'   => $u['mobile'] ?? '',
            'user_type'     => $o->user_type,
            'channel_name'  => $o->channel_name,
            'fund_class'    => $o->fund_class,
            'destination'   => $o->destination,
            'asset_type'    => $o->asset_type,
            'asset_label'   => AssetService::label($o->asset_type),
            'amount'        => $o->amount,
            'fee'           => $o->fee,
            'tax'           => $o->tax,
            'actual_amount' => $o->actual_amount,
            'fee_rate'      => $o->fee_rate,
            'tax_rate'      => $o->tax_rate,
            'status'        => (int)$o->status,
            'status_text'   => $o->getStatusText(),
            'payout_info'   => $this->resolvePayoutInfo($o),
            'payout_no'     => $o->payout_no,
            'receipt'       => $o->receipt,
            'audit_remark'  => $o->audit_remark,
            'fail_reason'   => $o->fail_reason,
            'user_remark'   => $o->user_remark,
            'created_at'    => $o->created_at ? date('Y-m-d H:i:s', (int)$o->created_at) : '',
            'audit_at'      => $o->audit_at ? date('Y-m-d H:i:s', (int)$o->audit_at) : '',
            'payout_at'     => $o->payout_at ? date('Y-m-d H:i:s', (int)$o->payout_at) : '',
            'finish_at'     => $o->finish_at ? date('Y-m-d H:i:s', (int)$o->finish_at) : '',
        ]);
    }

    /**
     * 收款资料：优先用下单时的快照(payout_info)；
     * 快照为空（老单/边缘单）则回退读用户当前结算资料，保证后台永远看得到。
     */
    private function resolvePayoutInfo(WithdrawOrder $o): array
    {
        $list = $o->getPayoutInfoList();
        if (!empty($list)) {
            return $list;
        }
        // 内部转余额无账户资料，直接返回空
        if ($o->destination === 'balance') {
            return [];
        }
        // 回退：实时读结算资料 + 网关格式化
        $fundClass = $o->fund_class;
        if (!class_exists($fundClass) || !is_subclass_of($fundClass, BaseFund::class)) {
            return [];
        }
        try {
            /** @var BaseFund $fund */
            $fund = Yii::createObject($fundClass, [(int)$o->mall_id]);
            if (!method_exists($fund, 'getUserAccountInfo') || !method_exists($fund, 'getPayoutDisplay')) {
                return [];
            }
            $settlement = UserSettlementInfo::findOne(['user_id' => $o->user_id, 'mall_id' => $o->mall_id, 'is_delete' => 0]);
            if (!$settlement) {
                return [];
            }
            $raw = $fund->getUserAccountInfo($settlement);
            return $fund->getPayoutDisplay($raw) ?: [];
        } catch (\Throwable $e) {
            Yii::warning('[withdraw] 回退读收款资料失败：' . $e->getMessage());
            return [];
        }
    }

    /** 网关展示名（按类名缓存，避免逐行重复实例化） */
    private $fundNameCache = [];

    private function getChannelName(string $fundClass): string
    {
        if (isset($this->fundNameCache[$fundClass])) {
            return $this->fundNameCache[$fundClass];
        }
        $name = '';
        if (class_exists($fundClass) && is_subclass_of($fundClass, BaseFund::class)) {
            try {
                $fund = Yii::createObject($fundClass, [(int)Yii::$app->mall->id]);
                $display = $fund->getDisplay();
                $name = is_array($display) ? ($display['name'] ?? '') : '';
            } catch (\Throwable $e) {
                // 取网关展示名失败按空处理，不影响列表；留痕便于排查
                Yii::warning('[withdraw] 取网关展示名失败 ' . $fundClass . '：' . $e->getMessage());
                $name = '';
            }
        }
        return $this->fundNameCache[$fundClass] = $name;
    }

    /** 列表用的收款账户摘要：户名 + 卡号尾号（优先下单快照 snapshot.account；快照为空回退实时读结算资料） */
    private function accountSummary(WithdrawOrder $o): string
    {
        if ($o->destination === 'balance') {
            return '转入余额';
        }
        $snap = $o->snapshot ? json_decode($o->snapshot, true) : [];
        $acc = is_array($snap) && isset($snap['account']) && is_array($snap['account']) ? $snap['account'] : [];
        // 快照为空(老单/迁移单——老系统不在单据存收款信息)时回退实时读 user_settlement_info,
        // 与详情 resolvePayoutInfo 同口径,保证列表也能显示银行卡,不再空白。
        if (empty($acc)) {
            $acc = $this->fallbackAccount($o);
        }
        if (empty($acc)) {
            return '';
        }
        $name = $acc['real_name'] ?? '';
        $no = $acc['bank_card_no'] ?? ($acc['account'] ?? '');
        $tail = $no ? '****' . mb_substr((string)$no, -4) : '';
        return trim($name . ($tail ? ' ' . $tail : ''));
    }

    /**
     * 快照为空时回退：实时读用户结算资料 + 网关格式化，返回账户数组(real_name / account 卡号 等)。
     * 与 resolvePayoutInfo 的回退同源；列表分页量小(约每页 20 行),按行实例化网关可接受。
     */
    private function fallbackAccount(WithdrawOrder $o): array
    {
        $fundClass = $o->fund_class;
        if (!$fundClass || !class_exists($fundClass) || !is_subclass_of($fundClass, BaseFund::class)) {
            return [];
        }
        try {
            /** @var BaseFund $fund */
            $fund = Yii::createObject($fundClass, [(int)$o->mall_id]);
            if (!method_exists($fund, 'getUserAccountInfo')) {
                return [];
            }
            $settlement = UserSettlementInfo::findOne(['user_id' => $o->user_id, 'mall_id' => $o->mall_id, 'is_delete' => 0]);
            if (!$settlement) {
                return [];
            }
            return $fund->getUserAccountInfo($settlement) ?: [];
        } catch (\Throwable $e) {
            Yii::warning('[withdraw] 列表回退读收款资料失败：' . $e->getMessage());
            return [];
        }
    }
}
