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

use yii\helpers\Json;

/**
 * 结算账户(归一化子表) user_settlement_account。
 *
 * 一人 × 渠道(fund_class) 一行,承载该渠道的账户信息(account_data)与进件/签约状态(status 列)。
 * 取代原 UserSettlementInfo 的 channel_data / extra_info.onboarding_status 两个 JSON blob。
 *
 * key = 打款网关完整类名(FQCN),与 self::class 规范一致(写入前 ltrim 前导反斜杠)。
 * account_data 是 text 列 + 单层 Json::encode——刻意不用 json 列,避免与手动 encode 叠加成双重编码。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property string $fund_class 打款网关完整类名
 * @property string $status 进件状态 none/pending/pending_verify/success/failed
 * @property string|null $status_remark
 * @property string|null $fund_user_id
 * @property int $status_updated_at
 * @property string|null $account_data 该渠道账户散字段(单层 JSON 文本)
 * @property int $is_default
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class UserSettlementAccount extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%user_settlement_account}}';
    }

    /**
     * 渠道标识归一化:去前导反斜杠,统一成 self::class 的规范形式(app\funds\X)。
     * 防止脏数据 '\app\funds\X' 与 'app\funds\X' 落成两个 key。
     */
    public static function normalizeFundClass(string $fundClass): string
    {
        return ltrim($fundClass, '\\');
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'fund_class'], 'required'],
            [['mall_id', 'user_id', 'status_updated_at', 'is_default', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['fund_class'], 'string', 'max' => 150],
            [['status'], 'string', 'max' => 20],
            [['status_remark'], 'string', 'max' => 255],
            [['fund_user_id'], 'string', 'max' => 64],
            [['account_data'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
        ];
    }

    /**
     * 账户散字段:单层 JSON 解码(text 列,无 Yii typecast 叠加)。
     */
    public function getAccountData(): array
    {
        if (empty($this->account_data)) {
            return [];
        }
        try {
            $data = Json::decode($this->account_data);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setAccountData(array $data)
    {
        $this->account_data = Json::encode($data);
    }
}
