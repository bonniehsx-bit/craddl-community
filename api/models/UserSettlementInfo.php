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

namespace app\models;

use Yii;
use yii\helpers\Json;

/**
 * This is the model class for table "{{%user_settlement_info}}".
 * [重构版 - 支持动态字段 - 已放宽验证规则]
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 关联的用户ID
 * @property string $real_name 真实姓名 (可选物理字段)
 * @property string $identity_type 证件类型
 * @property string $identity_no 证件号码 (可选物理字段)
 * @property string $mobile 预留手机号 (可选物理字段)
 * @property string|null $channel_data (TEXT/JSON) 按渠道隔离的专属结算信息
 * @property string|null $settlement_data (TEXT/JSON) 扩展结算信息
 * @property string|null $extra_info (TEXT/JSON) 扩展信息(状态等)
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $created_time
 * @property string|null $updated_time
 * @property string|null $deleted_time
 *
 * @property User $user
 */
class UserSettlementInfo extends BaseActiveRecord
{
    // 定义进件状态常量
    const FUND_STATUS_NONE = 'none';
    const FUND_STATUS_PENDING = 'pending';
    const FUND_STATUS_SUCCESS = 'success';
    const FUND_STATUS_FAILED = 'failed';

    // 缓存变量
    private $_settlementDataCache = null;
    private $_extraInfoCache = null;
    private $_channelDataCache = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user_settlement_info}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // [核心修改 1] 仅保留主键依赖为必填
            // 移除了 real_name, identity_no, mobile 的必填限制
            [['mall_id', 'user_id'], 'required'],

            [['mall_id', 'user_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],

            // JSON 字段
            [['settlement_data', 'extra_info', 'channel_data'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],

            // [核心修改 2] 物理字段仅保留类型和长度限制，允许为空
            // 这样如果没有传手机号或身份证，数据库允许存入空字符串或 NULL (视数据库结构而定)
            [['real_name', 'identity_no'], 'string', 'max' => 255],
            [['identity_type', 'mobile'], 'string', 'max' => 50],

            [['user_id'], 'unique', 'message' => '该用户的结算信息已存在。'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'real_name' => '真实姓名',
            'identity_type' => '证件类型',
            'identity_no' => '证件号码',
            'mobile' => '预留手机号',
            'channel_data' => '渠道专属结算信息',
            'settlement_data' => '扩展结算信息',
            'extra_info' => '扩展信息',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间戳',
            'deleted_at' => '删除时间戳',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    // ======================= JSON 字段访问器 =======================

    public function getExtraInfo(): array
    {
        if ($this->_extraInfoCache === null) {
            $this->_extraInfoCache = $this->extra_info ? Json::decode($this->extra_info) : [];
        }
        return $this->_extraInfoCache;
    }

    public function setExtraInfo(array $data)
    {
        $this->extra_info = Json::encode($data);
        $this->_extraInfoCache = $data;
    }

    public function getChannelData(): array
    {
        if ($this->_channelDataCache === null) {
            $this->_channelDataCache = $this->channel_data ? Json::decode($this->channel_data) : [];
        }
        return $this->_channelDataCache;
    }

    public function setChannelData(array $data)
    {
        $this->channel_data = Json::encode($data);
        $this->_channelDataCache = $data;
    }

    /**
     * 取某渠道的进件状态。
     *
     * 该记录存在、但没有这个 fundClass 的进件条目时(如用户从别的渠道填过资料/历史迁移数据),
     * 返回「未填写」态而非 null —— 各 Fund 的 checkOnboardingStatus(): array 会直接透传本方法结果,
     * 返回 null 会违反其 :array 返回类型抛 TypeError(生产 ManualFund/FastPayAgentPayFund 已踩)。
     * 统一在源头归一化,调用方拿到的永远是带 status 键的数组。
     */
    public function getOnboardingStatus(string $fundClass): array
    {
        $extra = $this->getExtraInfo();

        return $extra['onboarding_status'][$fundClass] ?? ['status' => self::FUND_STATUS_NONE, 'message' => '未填写信息'];
    }

    public function updateOnboardingStatus(string $fundClass, array $statusInfo)
    {
        $extra = $this->getExtraInfo();
        if (!isset($extra['onboarding_status'])) {
            $extra['onboarding_status'] = [];
        }
        $extra['onboarding_status'][$fundClass] = array_merge(
            $extra['onboarding_status'][$fundClass] ?? [],
            $statusInfo,
            ['updated_at' => time()]
        );
        $this->setExtraInfo($extra);
    }

    // ======================= 生命周期事件 =======================

    public function afterFind()
    {
        parent::afterFind();
        $this->getExtraInfo();
        $this->getChannelData();
    }

    public function beforeSave($insert)
    {
        $this->_extraInfoCache = null;
        $this->_channelDataCache = null;
        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // 归一化影子双写:把 blob 派生同步到 user_settlement_account 子表。
        // 现阶段子表是影子(读仍走 blob),故 best-effort——同步失败只记日志,绝不破坏主结算保存。
        try {
            $this->syncAccountsFromBlob();
        } catch (\Throwable $e) {
            Yii::warning("[UserSettlementInfo] 账户表影子同步失败(不影响主存) user_id={$this->user_id}: " . $e->getMessage());
        }
    }

    /**
     * 把 channel_data(账户) + extra_info.onboarding_status(状态)两个 blob 派生同步到
     * user_settlement_account 子表(一渠道一行,UPSERT)。
     *
     * 归一化阶段的"影子双写";回填命令也复用它把存量数据迁入。blob 仍是权威,子表是派生影子。
     * 只按 fund_class UPSERT,天然无 lost-update。
     */
    public function syncAccountsFromBlob()
    {
        if (empty($this->user_id) || empty($this->mall_id)) {
            return;
        }

        $channelMap = $this->getChannelData();                              // {fund_class: {账户字段}}
        $statusMap  = $this->getExtraInfo()['onboarding_status'] ?? [];      // {fund_class: {status,...}}

        // 两个 blob 的 fund_class 键取并集(某渠道可能只有账户或只有状态)
        $fundClasses = array_unique(array_merge(array_keys($channelMap), array_keys($statusMap)));

        foreach ($fundClasses as $rawFc) {
            $fc = UserSettlementAccount::normalizeFundClass((string)$rawFc);
            if ($fc === '') {
                continue;
            }

            $row = UserSettlementAccount::findOne([
                'user_id'    => $this->user_id,
                'mall_id'    => $this->mall_id,
                'fund_class' => $fc,
            ]);
            if (!$row) {
                $row = new UserSettlementAccount();
                $row->mall_id    = $this->mall_id;
                $row->user_id    = $this->user_id;
                $row->fund_class = $fc;
            }

            // 账户散字段(blob 归一化后前导斜杠已抹平,原始 key 也可能带斜杠 → 兜底两种都取)
            $account = $channelMap[$rawFc] ?? $channelMap[$fc] ?? null;
            if (is_array($account)) {
                $row->setAccountData($account);
            }

            // 进件状态
            $status = $statusMap[$rawFc] ?? $statusMap[$fc] ?? null;
            if (is_array($status)) {
                $row->status            = $status['status'] ?? self::FUND_STATUS_NONE;
                $row->status_remark     = (string)($status['remark'] ?? $status['message'] ?? '');
                $row->fund_user_id      = isset($status['fund_user_id']) ? (string)$status['fund_user_id'] : null;
                $row->status_updated_at = (int)($status['updated_at'] ?? time());
            }

            $row->save(false);
        }
    }
}