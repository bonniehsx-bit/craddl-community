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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/8/31
 * Time: 10:19
 */

namespace app\cron;

use app\core\Task;
use app\models\UserCoupon; // 引入用户优惠券模型
use Yii;

class UserCouponExpireTask extends Task
{

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    public function run($params = null)
    {
        Yii::warning('定期检查优惠券过期');
        try {
            // 商城级任务：必须按 mall_id 过滤，否则每个商城迭代都会处理全平台券（跨租户越界 + N 倍重复）
            $mall = $params['mall'] ?? \Yii::$app->mall ?? null;
            if (!$mall) {
                return true;
            }

            // 1. 定义查询条件
            // mall_id = 当前商城
            // status = 0 (未使用)
            // is_delete = 0 (未删除)
            // end_at < time() (结束时间早于当前时间)
            // end_at > 0 (排除掉那些永久有效或未设置结束时间的券)
            $condition = [
                'AND',
                ['mall_id' => $mall->id],
                ['status' => UserCoupon::STATUS_UNUSE],
                ['is_delete' => 0],
                ['<', 'end_at', time()],
                ['>', 'end_at', 0]
            ];

            // 2. 执行批量更新
            // updateAll(更新的字段, 条件) 返回受影响的行数
            // 同时更新 updated_at 字段，保持数据规范
            $count = UserCoupon::updateAll(
                [
                    'status' => UserCoupon::STATUS_EXPIRE,
                    'updated_at' => time()
                ],
                $condition
            );

            // 3. 记录日志 (如果有数据更新)
            if ($count > 0) {
                Yii::warning("定时任务[UserCouponExpireTask]: 扫描并过期了 {$count} 张优惠券。", 'cron');
            }

            return true;

        } catch (\Exception $e) {
            // 捕获异常并记录错误日志
            Yii::error("定时任务[UserCouponExpireTask]执行异常: " . $e->getMessage(), 'cron');
            return false;
        }
    }

    /**
     * 定时任务规则
     * @return mixed
     */
    public function rule()
    {
        // 每5分钟执行一次
        return "*/5 * * * *";
    }

    /**
     * 任务描述
     * @return mixed
     */
    public function description()
    {
        return "定时检查优惠券过期状态";
    }
}