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

use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * 用户团队统计快照表
 * 预计算+增量更新，所有升级条件检查从这里读取，避免全表扫描
 *
 * @property int $user_id
 * @property int $mall_id
 * @property int $direct_count
 * @property string $direct_level_map JSON
 * @property int $team_count
 * @property string $team_level_map JSON
 * @property string $team_legs_map JSON
 * @property float $personal_spend_paid
 * @property float $personal_spend_settled
 * @property int $personal_order_count
 * @property string $purchased_goods_map JSON
 * @property float $team_spend_paid
 * @property float $team_spend_settled
 * @property float $month_personal_spend
 * @property float $month_team_spend
 * @property int $month_active_count
 * @property string $month_period YYYYMM
 * @property int $dirty
 * @property int $last_check_at
 */
class UserTeamStat extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%user_team_stat}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'mall_id'], 'required'],
            [['user_id', 'mall_id', 'direct_count', 'team_count', 'personal_order_count', 'month_active_count', 'dirty', 'last_check_at'], 'integer'],
            [['personal_spend_paid', 'personal_spend_settled', 'team_spend_paid', 'team_spend_settled', 'month_personal_spend', 'month_team_spend'], 'number'],
            [['direct_level_map', 'team_level_map', 'team_legs_map', 'purchased_goods_map'], 'string'],
            [['month_period'], 'string', 'max' => 6],
        ];
    }

    /**
     * 获取或创建用户的 stat 记录（并发安全）
     * 使用 INSERT IGNORE 防止并发重复插入
     */
    public static function getOrCreate(int $userId, int $mallId): self
    {
        $stat = static::findOne(['user_id' => $userId]);
        if ($stat) {
            return $stat;
        }

        // INSERT IGNORE: 如果 PK 冲突（并发插入），静默跳过
        try {
            $now = time();
            $nowStr = date('Y-m-d H:i:s');
            \Yii::$app->db->createCommand()->setSql(
                "INSERT IGNORE INTO {{%user_team_stat}} (user_id, mall_id, direct_count, team_count, personal_spend_paid, personal_spend_settled, personal_order_count, team_spend_paid, team_spend_settled, month_personal_spend, month_team_spend, month_active_count, month_period, dirty, last_check_at, is_delete, created_at, updated_at, deleted_at, created_time, updated_time, deleted_time) VALUES (:uid, :mid, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, :now, :now2, 0, :ct, :ut, NULL)"
            )->bindValues([':uid' => $userId, ':mid' => $mallId, ':now' => $now, ':now2' => $now, ':ct' => $nowStr, ':ut' => $nowStr])->execute();
        } catch (\Exception $e) {
            \Yii::warning("[UserTeamStat] INSERT IGNORE 异常(可忽略): " . $e->getMessage());
        }

        // 无论插入还是已存在，重新读取
        return static::findOne(['user_id' => $userId]) ?: new static(['user_id' => $userId, 'mall_id' => $mallId]);
    }

    /**
     * 获取 JSON 字段为数组
     */
    public function getMapField(string $field): array
    {
        $val = $this->$field;
        if (empty($val)) return [];
        try {
            return Json::decode($val);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 设置 JSON 字段
     */
    public function setMapField(string $field, array $data): void
    {
        $this->$field = Json::encode($data);
    }

    /**
     * 当前月度是否有效（未过期）
     */
    public function isMonthCurrent(): bool
    {
        return $this->month_period === date('Ym');
    }

    /**
     * 获取月度字段值（过期则返回0）
     */
    public function getMonthValue(string $field)
    {
        return $this->isMonthCurrent() ? $this->$field : 0;
    }
}
