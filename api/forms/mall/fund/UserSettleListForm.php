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

namespace app\forms\mall\fund;

use app\core\ApiCode;
use app\funds\BaseFund;
use app\models\BaseModel;
use app\models\UserSettlementInfo;
use Yii;

/**
 * 用户结算信息列表查询 Form
 * [重构版] - 适配多渠道数据结构 (channel_data) 和 JSON 状态查询
 */
class UserSettleListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;
    public $fund_status; // 搜索特定状态 (pending, success, failed)
    public $fund_class;  // 搜索特定渠道 (app\funds\BankFund)
    public $date_range;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['keyword', 'fund_status', 'fund_class'], 'string'],
            [['date_range'], 'safe'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    /**
     * 获取结算信息列表
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = UserSettlementInfo::find()->alias('s')->where([
            's.mall_id' => Yii::$app->mall->id,
            's.is_delete' => 0
        ]);

        $query->joinWith(['user u']);

        // 1. 按状态筛选
        // 由于状态存储在 extra_info JSON 字段中，我们使用 LIKE 进行模糊查询
        // 结构示例: "onboarding_status":{"app\\funds\\BankFund":{"status":"success"...}}
        if ($this->fund_status) {
            $query->andWhere(['like', 's.extra_info', '"status":"' . $this->fund_status . '"']);
        }

        // 2. 按渠道筛选
        // 筛选是否包含某个渠道的 key
        if ($this->fund_class) {
            // 转义反斜杠以匹配 JSON 字符串
            $escapedClass = str_replace('\\', '\\\\', $this->fund_class);
            $query->andWhere(['like', 's.extra_info', '"' . $escapedClass . '":{']);
        }

        // 3. 关键词搜索
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['s.user_id' => $this->keyword],
                ['like', 'u.nickname', $this->keyword],
                ['like', 's.real_name', $this->keyword],
                ['like', 's.mobile', $this->keyword],
            ]);
        }

        // 4. 时间筛选
        if ($this->date_range && is_array($this->date_range) && count($this->date_range) == 2) {
            $query->andWhere(['between', 's.created_at', strtotime($this->date_range[0] . ' 00:00:00'), strtotime($this->date_range[1] . ' 23:59:59')]);
        }

        $pagination = null;
        $list = $query->select('s.*')
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('s.updated_at DESC, s.id DESC')
            ->all();

        $formattedList = [];
        /** @var UserSettlementInfo $item */
        foreach ($list as $item) {
            // 获取物理字段数据
            $userInfo = [
                'nickname' => $item->user->nickname ?? '用户不存在',
                'avatar_url' => $item->user->avatar_url ?? '',
            ];

            // [核心] 获取各渠道专属的详细数据 (银行卡号、支付宝账号等)
            $channelData = $item->getChannelData();

            // [核心] 获取各渠道的进件状态
            $extraInfo = $item->getExtraInfo();
            $onboardingStatus = $extraInfo['onboarding_status'] ?? [];

            $formattedItem = [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_info' => $userInfo,
                'real_name' => $item->real_name,
                'mobile' => $item->mobile,

                // 返回前端用于展开显示的详细数据 map
                'channel_data' => $channelData,

                // 格式化后的状态列表
                'onboarding_status' => $this->formatOnboardingStatus($onboardingStatus),

                'created_time' => date('Y-m-d H:i:s', $item->created_at),
                'updated_time' => date('Y-m-d H:i:s', $item->updated_at),
            ];
            $formattedList[] = $formattedItem;
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $formattedList,
            'pagination' => $pagination,
        ]);
    }

    /**
     * 格式化多渠道进件状态
     * 将类名转换为人类可读的渠道名称
     *
     * @param array $statuses ['app\funds\BankFund' => ['status' => 'success', ...]]
     * @return array
     */
    private function formatOnboardingStatus(array $statuses): array
    {
        $statusMap = [
            UserSettlementInfo::FUND_STATUS_NONE => '未进件',
            UserSettlementInfo::FUND_STATUS_PENDING => '处理中',
            'pending_verify' => '待验证',
            UserSettlementInfo::FUND_STATUS_SUCCESS => '成功',
            UserSettlementInfo::FUND_STATUS_FAILED => '失败',
        ];

        $formatted = [];
        foreach ($statuses as $class => $info) {
            $channelName = '未知渠道';
            try {
                // 尝试实例化渠道类以获取配置的显示名称
                if (class_exists($class)) {
                    /** @var BaseFund $instance */
                    $instance = Yii::createObject($class, [Yii::$app->mall->id]);
                    $display = $instance->getDisplay();
                    $channelName = $display['name'] ?? $class;
                } else {
                    // 如果类不存在（可能被删除了），只显示类名最后一部分
                    $parts = explode('\\', $class);
                    $channelName = end($parts);
                }
            } catch (\Exception $e) {
                // 实例化失败降级处理
                $parts = explode('\\', $class);
                $channelName = end($parts);
            }

            $formatted[] = [
                'channel_class' => $class, // 唯一标识
                'channel_name'  => $channelName, // 显示名称 (e.g. 银行卡转账)
                'status'        => $info['status'] ?? 'unknown',
                'status_text'   => $statusMap[$info['status'] ?? ''] ?? '未知',
                'remark'        => $info['remark'] ?? '',
                'updated_at'    => isset($info['updated_at']) ? date('Y-m-d H:i:s', $info['updated_at']) : '-',
            ];
        }
        return $formatted;
    }
}