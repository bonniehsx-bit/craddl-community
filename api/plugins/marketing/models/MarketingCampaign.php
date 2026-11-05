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

namespace app\plugins\marketing\models;

use app\models\BaseActiveRecord;

/**
 * 营销活动主体
 *
 * @property int $id
 * @property int $mall_id
 * @property string $name
 * @property int $type 1=触发型 2=群发型
 * @property int $status 0草稿 1进行中 2暂停 3已结束
 * @property int $start_at
 * @property int $end_at
 * @property int $budget_type 0不限 1按张数 2按金额
 * @property float $budget_total
 * @property int $grant_count
 * @property float $grant_amount
 * @property int $per_user_limit
 * @property float $budget_integral 积分预算(0=不限)
 * @property float $budget_balance 余额预算元(0=不限)
 * @property float $grant_integral 已发积分(扣除回收)
 * @property float $grant_balance 已发余额(扣除回收)
 * @property string $audience_json
 * @property string $remark
 * @property int $is_delete
 */
class MarketingCampaign extends BaseActiveRecord
{
    const TYPE_TRIGGER = 1; // 触发型(事件/定时自动发)
    const TYPE_BATCH = 2;   // 群发型(定时一次性群发)
    const TYPE_CLAIM = 3;   // 领取型(C端领券中心,用户主动领)

    const STATUS_DRAFT = 0;
    const STATUS_RUNNING = 1;
    const STATUS_PAUSED = 2;
    const STATUS_ENDED = 3;

    const BUDGET_NONE = 0;   // 不限
    const BUDGET_COUNT = 1;  // 按发放张数封顶
    const BUDGET_AMOUNT = 2; // 按发放金额(∑面额)封顶

    public static function tableName()
    {
        return '{{%plugin_marketing_campaign}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'name'], 'required'],
            [['mall_id', 'type', 'status', 'start_at', 'end_at', 'budget_type', 'grant_count', 'per_user_limit', 'is_delete'], 'integer'],
            [['budget_total', 'grant_amount', 'budget_integral', 'budget_balance', 'grant_integral', 'grant_balance'], 'number'],
            [['audience_json'], 'string'],
            [['name'], 'string', 'max' => 60],
            [['remark'], 'string', 'max' => 255],
        ];
    }

    /**
     * 当前是否在可发放状态:进行中 + 处于活动时段内。
     */
    public function isGrantable(int $now = 0): bool
    {
        $now = $now ?: time();
        if ($this->status != self::STATUS_RUNNING) {
            return false;
        }
        if ($this->start_at > 0 && $now < $this->start_at) {
            return false;
        }
        if ($this->end_at > 0 && $now > $this->end_at) {
            return false;
        }
        return true;
    }

    /**
     * 预算是否已耗尽(据此发完自停)。
     */
    public function isBudgetExhausted(): bool
    {
        if ($this->budget_type == self::BUDGET_COUNT
            && $this->budget_total > 0 && $this->grant_count >= $this->budget_total) {
            return true;
        }
        if ($this->budget_type == self::BUDGET_AMOUNT
            && $this->budget_total > 0 && $this->grant_amount >= $this->budget_total) {
            return true;
        }
        if ($this->budget_integral > 0 && $this->grant_integral >= $this->budget_integral) {
            return true;
        }
        if ($this->budget_balance > 0 && $this->grant_balance >= $this->budget_balance) {
            return true;
        }
        return false;
    }

    /**
     * 再发这一份(券张数/券面额/积分/余额)是否会超预算。整份奖励要么全发要么不发。
     */
    public function wouldExceedBudget(int $couponCount, float $couponFace, float $integral, float $balance): bool
    {
        if ($this->budget_type == self::BUDGET_COUNT && $this->budget_total > 0
            && $couponCount > 0 && $this->grant_count + $couponCount > $this->budget_total) {
            return true;
        }
        if ($this->budget_type == self::BUDGET_AMOUNT && $this->budget_total > 0
            && $couponFace > 0 && $this->grant_amount + $couponFace > $this->budget_total) {
            return true;
        }
        if ($this->budget_integral > 0 && $integral > 0 && $this->grant_integral + $integral > $this->budget_integral) {
            return true;
        }
        if ($this->budget_balance > 0 && $balance > 0 && $this->grant_balance + $balance > $this->budget_balance) {
            return true;
        }
        return false;
    }

    /**
     * 人群规则(解析后的数组)。
     */
    public function getAudience(): array
    {
        $d = json_decode((string)$this->audience_json, true);
        return is_array($d) ? $d : [];
    }
}
