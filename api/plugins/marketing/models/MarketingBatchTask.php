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
 * 营销活动-定时群发任务(二期)
 *
 * @property int $id
 * @property int $mall_id
 * @property int $campaign_id
 * @property int $plan_at
 * @property string $audience_json
 * @property int $status 0待发 1发放中 2完成 3失败
 * @property int $total_target
 * @property int $total_sent
 * @property int $last_user_id
 * @property string $fail_msg
 * @property int $is_delete
 */
class MarketingBatchTask extends BaseActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_DONE = 2;
    const STATUS_FAILED = 3;

    public static function tableName()
    {
        return '{{%plugin_marketing_batch_task}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'campaign_id'], 'required'],
            [['mall_id', 'campaign_id', 'plan_at', 'status', 'total_target', 'total_sent', 'last_user_id', 'is_delete'], 'integer'],
            [['audience_json'], 'string'],
            [['fail_msg'], 'string', 'max' => 255],
        ];
    }

    public function getAudience(): array
    {
        $d = json_decode((string)$this->audience_json, true);
        return is_array($d) ? $d : [];
    }
}
