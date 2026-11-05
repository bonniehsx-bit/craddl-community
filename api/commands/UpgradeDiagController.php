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

namespace app\commands;

use app\helpers\JobHelper;
use Yii;
use yii\console\Controller;

/**
 * 云端发版 / 升级链路诊断
 *
 * 用法:
 *   php yii upgrade-diag/check   全链路体检 + 端到端实测"检查更新"
 *   php yii upgrade-diag/clear   清掉卡住的 check_now 占位与队列残留
 *
 * 关注点:PHP 与 scheduler 是否同一个 Redis db、心跳是否新鲜、
 *         check_now 命令能否被 scheduler 处理(会写新的 available.checked_at)。
 */
class UpgradeDiagController extends Controller
{
    const KEY_HEARTBEAT = 'jxmall_scheduler:heartbeat';
    const KEY_AVAILABLE = 'jxmall_scheduler:upgrade:available';
    const KEY_STATUS = 'jxmall_scheduler:upgrade:status';
    const KEY_UNIQ = 'jxmall_scheduler:uniq:system_check_now';

    private function redis()
    {
        return Yii::$app->redis;
    }

    /**
     * 全链路体检
     */
    public function actionCheck()
    {
        $r = $this->redis();
        $this->stdout("========= 云端发版 / 升级链路诊断 =========\n\n");

        // 1. Redis 库
        $db = isset($r->database) ? $r->database : '未知';
        $this->stdout("① PHP 连接的 Redis db = {$db}\n");
        $this->stdout("   ★ 必须与 scheduler.yaml 的 redis.db 一致,否则 PHP 读不到 scheduler 写的键\n\n");

        // 2. 心跳
        $beat = (int)$r->get(self::KEY_HEARTBEAT);
        if ($beat > 0) {
            $ago = time() - $beat;
            $flag = $ago <= 60 ? '✅ 存活' : "⚠️ 可能已停({$ago}s 前)";
            $this->stdout("② 调度器心跳: {$flag},最后心跳 {$ago}s 前\n\n");
        } else {
            $this->stdout("② 调度器心跳: ❌ 无(scheduler 没运行,或不在这个 db)\n\n");
        }

        // 3. available
        $av = $r->get(self::KEY_AVAILABLE);
        if ($av) {
            $d = json_decode($av, true) ?: [];
            $ca = (int)($d['checked_at'] ?? 0);
            $this->stdout("③ 最新版本(available): 有\n");
            $this->stdout("   latest_version_code = " . ($d['latest_version_code'] ?? '?') . "\n");
            $this->stdout("   has_update = " . var_export($d['has_update'] ?? null, true) . "\n");
            $this->stdout("   checked_at = {$ca} (" . ($ca ? (time() - $ca) . 's 前' : '?') . ")\n\n");
        } else {
            $this->stdout("③ 最新版本(available): ❌ 空(scheduler 还没心跳成功,或 db 不一致)\n\n");
        }

        // 4. status
        $st = $r->get(self::KEY_STATUS);
        $this->stdout("④ 升级状态(status): " . ($st ?: '(无,正常)') . "\n\n");

        // 5. 唯一性占位(check_now 被卡的元凶)
        $uniqExists = $r->exists(self::KEY_UNIQ);
        if ($uniqExists) {
            $ttl = $r->ttl(self::KEY_UNIQ);
            $this->stdout("⑤ check_now 占位键: ⚠️ 存在(TTL {$ttl}s)\n");
            $this->stdout("   ★ 这会导致后续「检查更新」被去重跳过、根本不进队列!\n");
            $this->stdout("   → 跑 php yii upgrade-diag/clear 清掉它\n\n");
        } else {
            $this->stdout("⑤ check_now 占位键: ✅ 无(不阻塞)\n\n");
        }

        // 6. 队列堆积
        $lanes = [
            'high' => JobHelper::laneKey(JobHelper::LANE_HIGH),
            'normal' => JobHelper::laneKey(JobHelper::LANE_NORMAL),
            'low' => JobHelper::laneKey(JobHelper::LANE_LOW),
            'processing' => JobHelper::SCHEDULER_KEY . ':processing',
        ];
        $this->stdout("⑥ 队列堆积: ");
        foreach ($lanes as $name => $key) {
            $this->stdout("{$name}=" . (int)$r->zcard($key) . " ");
        }
        $this->stdout("\n\n");

        // 7. 端到端实测
        $this->stdout("⑦ 端到端实测「检查更新」...\n");
        $before = 0;
        if ($av) {
            $before = (int)((json_decode($av, true) ?: [])['checked_at'] ?? 0);
        }
        // 先清占位,保证命令能真正入队
        $r->del(self::KEY_UNIQ);
        JobHelper::pushRaw(['class' => 'system.check_now', 'type' => 'check_now'], 0, [
            'lane' => 'high', 'unique_key' => 'system_check_now',
        ]);
        $this->stdout("   已下发 check_now,等待 scheduler 处理(最多 12s)...\n");

        $ok = false;
        for ($i = 0; $i < 12; $i++) {
            sleep(1);
            $nav = $r->get(self::KEY_AVAILABLE);
            $after = $nav ? (int)((json_decode($nav, true) ?: [])['checked_at'] ?? 0) : 0;
            if ($after > $before) {
                $ok = true;
                $this->stdout("   ✅ scheduler 已处理!checked_at 从 {$before} → {$after}(用时约 " . ($i + 1) . "s)\n");
                break;
            }
        }
        if (!$ok) {
            $this->stdout("   ❌ 12s 内 checked_at 没变化,scheduler 未处理 check_now。可能:\n");
            $this->stdout("      · scheduler 没运行(看②心跳)\n");
            $this->stdout("      · scheduler 是旧版、不认 check_now(重编最新代码部署)\n");
            $this->stdout("      · PHP 与 scheduler 不在同一个 db(看①)\n");
            $this->stdout("      · check_now 命令的心跳请求打到云端失败(看 scheduler.log)\n");
        }

        $this->stdout("\n========= 诊断结束 =========\n");
    }

    /**
     * 清掉卡住的 check_now 占位 + 队列里残留的 check_now 命令
     */
    public function actionClear()
    {
        $r = $this->redis();
        $n = $r->del(self::KEY_UNIQ);
        $this->stdout("已清除 check_now 占位键: " . ($n ? '删除成功' : '本就没有') . "\n");
        $this->stdout("提示:占位清掉后,再点「检查更新」或跑 upgrade-diag/check 重试。\n");
    }
}
