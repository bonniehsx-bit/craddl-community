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

/**
 * 积分过期回收操作流水(管理端审计,防争议)
 *
 * @property int    $id
 * @property int    $mall_id
 * @property string $run_no          本次回收编号(贯穿被回收行)
 * @property string $mode            manual / rolling
 * @property string $clear_before    回收截止日 YYYY-MM-DD
 * @property int    $affected_users  影响用户数
 * @property float  $total_recovered 回收积分总额
 * @property int    $operator_id     操作管理员ID(0=系统自动)
 * @property int    $executed_at     执行时间戳
 */
class IntegralExpireRun extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%integral_expire_run}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'run_no', 'clear_before'], 'required'],
            [['mall_id', 'affected_users', 'operator_id', 'executed_at'], 'integer'],
            [['total_recovered'], 'number'],
            [['run_no', 'mode'], 'string', 'max' => 32],
            [['clear_before'], 'string', 'max' => 10],
        ];
    }
}
