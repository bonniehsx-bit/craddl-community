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

namespace app\cron;

use app\core\Task;
use Yii;

/**
 * 定期清理 Yii2 错误监控日志（保留最近 10 天）
 */
class YiiErrorLogCleanTask extends Task
{
    /** 错误日志保留天数 */
    const KEEP_DAYS = 10;

    public function run($params = null)
    {
        $expire = time() - self::KEEP_DAYS * 86400;

        $count = Yii::$app->db->createCommand(
            'DELETE FROM {{%yii_error_log}} WHERE created_at < :expire AND created_at > 0',
            [':expire' => $expire]
        )->execute();

        Yii::warning("[YiiErrorLogClean] 已清理 {$count} 条超期日志（" . self::KEEP_DAYS . "天前）", __CLASS__);

        return $count;
    }

    public function rule()
    {
        // 每天凌晨 3:15 执行
        return '15 3 * * *';
    }

    public function description()
    {
        return 'Yii2错误日志清理（保留 ' . self::KEEP_DAYS . ' 天）';
    }
}
