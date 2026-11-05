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

use app\helpers\EditionHelper;
use app\helpers\JobHelper;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * 命令行强制升级 —— 页面更新失败/系统异常时的兜底通道
 *
 * 直接把升级命令塞进调度器的 Redis 队列(绕开 web/PHP-FPM,不受 opcache/SELinux 影响),
 * 调度器照常执行(下载→解压→迁移→自更新),本命令实时监控进度到结束。
 *
 * 用法(在 api/ 目录):
 *   php yii upgrade/force        强制升级到云端最新版(已是最新则提示)
 *   php yii upgrade/to 610       强制升级到指定版本码(可用于重装同版本、修复半截升级)
 *
 * 前提:调度器在运行、与 PHP 同一个 Redis db(否则命令进不了它的队列)。
 */
class UpgradeController extends Controller
{
    const KEY_AVAILABLE   = 'jxmall_scheduler:upgrade:available';
    const KEY_STATUS      = 'jxmall_scheduler:upgrade:status';
    const KEY_UNIQ_CHECK  = 'jxmall_scheduler:uniq:system_check_now';
    const KEY_UNIQ_UPRADE = 'jxmall_scheduler:uniq:system_upgrade';

    private function redis()
    {
        return Yii::$app->redis;
    }

    /**
     * 强制升级到云端最新版
     */
    public function actionForce()
    {
        if (EditionHelper::isCommunity()) {
            $this->stderr(EditionHelper::MANUAL_UPGRADE_MESSAGE . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("===== 命令行强制升级(→ 最新版)=====\n\n");

        // 1. 心跳一次,拿最新版本
        $available = $this->refreshAvailable();
        $latest = (int)($available['latest_version_code'] ?? 0);
        $cur = $this->currentCode();
        $this->stdout("  当前版本码: {$cur}\n");
        $this->stdout("  云端最新码: " . ($latest ?: '未知') . "\n\n");

        if ($latest <= 0) {
            $this->stderr("❌ 未获取到云端最新版本。请确认:调度器在运行、与 PHP 同一个 Redis db。\n");
            $this->stderr("   排查:php yii upgrade-diag/check\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if ($cur > 0 && $latest <= $cur) {
            $this->stdout("✅ 当前已是最新版本(code {$cur})。\n");
            $this->stdout("   如需强制重装/修复,可指定版本:php yii upgrade/to {$latest}\n");
            return ExitCode::OK;
        }

        return $this->dispatchAndWatch($latest);
    }

    /**
     * 强制升级到指定版本码(真·强制,不校验是否比当前新)
     */
    public function actionTo($code)
    {
        if (EditionHelper::isCommunity()) {
            $this->stderr(EditionHelper::MANUAL_UPGRADE_MESSAGE . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $code = (int)$code;
        if ($code <= 0) {
            $this->stderr("❌ 版本码非法。用法:php yii upgrade/to 610\n");
            return ExitCode::USAGE;
        }
        $this->stdout("===== 命令行强制升级(→ code {$code})=====\n");
        $this->stdout("  当前版本码: {$this->currentCode()}\n\n");
        return $this->dispatchAndWatch($code);
    }

    /**
     * 下发升级命令 + 实时监控进度到终态
     */
    private function dispatchAndWatch($target)
    {
        $r = $this->redis();

        // 清障:去重占位 + 粘滞状态(避免命令被去重跳过 / 旧状态卡住)
        $r->del(self::KEY_UNIQ_UPRADE);
        $r->del(self::KEY_STATUS);

        // 推升级命令(与后台 UpdateForm::update 同源)
        JobHelper::pushRaw([
            'class' => 'system.upgrade',
            'type' => 'upgrade',
            'version_code' => $target,
        ], 0, ['lane' => 'high', 'unique_key' => 'system_upgrade']);

        $r->set(self::KEY_STATUS, json_encode([
            'state' => 'queued',
            'target_version_code' => $target,
            'message' => '已排队(命令行强制),等待调度器执行',
            'updated_at' => time(),
        ]));

        $this->stdout("✅ 已下发升级命令(目标 code {$target}),等待调度器执行…\n");
        $this->stdout("   注:升级由调度器后台执行,期间请勿关闭/重启调度器。\n\n");

        // 监控进度(最多 10 分钟)
        $lastState = '';
        $deadline = time() + 600;
        while (time() < $deadline) {
            sleep(2);
            $raw = $r->get(self::KEY_STATUS);
            if (!$raw) {
                // 状态被清(多为已达标自愈)→ 视当前版本判断
                if ($this->currentCode() >= $target) {
                    $this->stdout("\n🎉 升级完成(当前版本码已达 {$target})。\n");
                    return ExitCode::OK;
                }
                continue;
            }
            $st = json_decode($raw, true) ?: [];
            $state = $st['state'] ?? '';
            $msg = $st['message'] ?? '';
            if ($state !== $lastState) {
                $this->stdout("  [" . date('H:i:s') . "] {$state}" . ($msg ? " · {$msg}" : "") . "\n");
                $lastState = $state;
            }
            if ($state === 'done') {
                $this->stdout("\n🎉 升级完成:{$msg}\n");
                return ExitCode::OK;
            }
            if ($state === 'failed') {
                $this->stderr("\n❌ 升级失败:{$msg}\n");
                $this->stderr("   已自动回滚(如为文件权限,先 bash deploy.sh 选 6 修属主再重试)。\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stderr("\n⚠️ 10 分钟内未见终态。调度器可能未运行或卡住。\n");
        $this->stderr("   排查:php yii upgrade-diag/check;必要时 bash deploy.sh 选 4 重启调度器后重试。\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * 触发调度器心跳一次,尽量拿到最新 available(best-effort)
     */
    private function refreshAvailable()
    {
        $r = $this->redis();
        $before = 0;
        if ($av = $r->get(self::KEY_AVAILABLE)) {
            $before = (int)((json_decode($av, true) ?: [])['checked_at'] ?? 0);
        }
        // 清占位 + 下发 check_now
        $r->del(self::KEY_UNIQ_CHECK);
        JobHelper::pushRaw(['class' => 'system.check_now', 'type' => 'check_now'], 0, [
            'lane' => 'high', 'unique_key' => 'system_check_now',
        ]);
        $this->stdout("  正在向云端核对最新版本…\n");
        for ($i = 0; $i < 8; $i++) {
            sleep(1);
            $av = $r->get(self::KEY_AVAILABLE);
            $after = $av ? (int)((json_decode($av, true) ?: [])['checked_at'] ?? 0) : 0;
            if ($after > $before) {
                break;
            }
        }
        $av = $r->get(self::KEY_AVAILABLE);
        return $av ? (json_decode($av, true) ?: []) : [];
    }

    /**
     * 读本机 version.json 的 version_code
     */
    private function currentCode()
    {
        $file = Yii::$app->basePath . '/version.json';
        if (!is_file($file)) {
            return 0;
        }
        $d = json_decode((string)file_get_contents($file), true);
        return is_array($d) ? (int)($d['version_code'] ?? 0) : 0;
    }
}
