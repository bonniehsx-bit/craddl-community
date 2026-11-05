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

class UnlinkLogJob extends Task
{
    /** 日志文件保留天数;文件名日期早于「今天 − 本值」的按天日志会被删除 */
    const KEEP_DAYS = 10;

    /** 要清理的日志目录(相对 runtimePath) */
    const DIRS = ['/logs/', '/logs/info/', '/logs/errors/'];

    public function run($params = null)
    {
        // 判定基准:文件名里的日期,不是 filectime。
        //
        // 原来用 filectime((time() - $t) > N 天)判超期,那是 inode 变更时间 ——
        // 文件被 chmod / chown / mv / rsync 同步过就会刷新,该文件的保留期从那一刻重新起算,
        // 于是一批很老的日志因为某次运维动作永远清不掉,磁盘就是这么被吃满的。
        // 日志文件本来就叫 2026-08-31.log(见 LogFileTask 的 date('Y-m-d')),
        // 直接读文件名最准,也不受任何文件系统操作影响。
        $deadline = strtotime('-' . self::KEEP_DAYS . ' days', strtotime(date('Y-m-d')));

        $deleted = 0;
        $failed  = 0;
        foreach (self::DIRS as $dir) {
            [$d, $f] = $this->cleanDir(Yii::$app->runtimePath . $dir, $deadline);
            $deleted += $d;
            $failed  += $f;
        }

        $msg = "[UnlinkLog] 已删除 {$deleted} 个日志文件(早于 " . date('Y-m-d', $deadline) . ")";
        if ($failed > 0) {
            $msg .= ",另有 {$failed} 个删除失败(多半是权限)";
        }
        Yii::warning($msg, __CLASS__);
        return $deleted;
    }

    /**
     * 清理单个目录。
     *
     * 只认「文件名里带 YYYY-MM-DD」的按天日志 —— 解析不出日期的一律跳过(不删)。
     * 这样 app.log、xxx.log.1 这类轮转/非按天文件不会被误伤,宁可留着也不错删。
     *
     * @return array{0:int,1:int} [删除成功数, 删除失败数]
     */
    private function cleanDir(string $path, int $deadline): array
    {
        if (!is_dir($path)) {
            return [0, 0];
        }
        $items = scandir($path);
        if ($items === false) {
            return [0, 0];
        }

        $deleted = 0;
        $failed  = 0;
        foreach ($items as $item) {
            $file = $path . $item;
            if (!is_file($file)) {
                continue;
            }
            if (!preg_match('/(\d{4}-\d{2}-\d{2})/', $item, $m)) {
                continue;   // 文件名里没有日期 → 不是按天日志,不碰
            }
            $fileDate = strtotime($m[1]);
            if ($fileDate === false) {
                continue;   // 日期解析不出来(如 9999-99-99)→ 同样不碰
            }
            if ($fileDate >= $deadline) {
                continue;   // 在保留期内
            }
            if (@unlink($file)) {
                $deleted++;
            } else {
                $failed++;
            }
        }
        return [$deleted, $failed];
    }

    public function rule()
    {
        return "01 04 */1 * *";
    }

    public function description()
    {
        return "定期删除 " . self::KEEP_DAYS . " 天前的日志文件";
    }
}
