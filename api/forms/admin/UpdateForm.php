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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\EditionHelper;
use app\helpers\JobHelper;
use app\helpers\RedisHelper;
use app\models\BaseModel;

/**
 * 系统更新(云端发版 / scheduler 升级)
 *
 * 客户端不直连云端:scheduler 心跳时把「最新版本」写进 Redis,PHP 只读 Redis;
 * 点升级 → 推升级命令到 Redis 队列(scheduler 原生执行)+ 写状态键,前端轮询状态。
 *
 * Redis 键(与 scheduler 约定,同 jxmall_scheduler 命名空间):
 *   jxmall_scheduler:upgrade:available  scheduler 心跳写:{latest_version, latest_version_code, changelog, force_level, checked_at}
 *   jxmall_scheduler:upgrade:status     升级进度:{state, target_version_code, message, updated_at}
 *                                        state: queued/downloading/extracting/migrating/done/failed
 */
class UpdateForm extends BaseModel
{
    const KEY_AVAILABLE = 'jxmall_scheduler:upgrade:available';
    const KEY_STATUS = 'jxmall_scheduler:upgrade:status';
    // check_now 命令的去重占位键(scheduler 侧同名);推命令前必须先清掉,否则被去重跳过、根本不入队
    const KEY_UNIQ = 'jxmall_scheduler:uniq:system_check_now';

    // 进行中的状态(用于防重复触发)
    const RUNNING_STATES = ['queued', 'downloading', 'extracting', 'migrating'];

    public $new_version; // 目标版本码(字符串,由前端传入;留空则取最新可用)

    public function rules()
    {
        return [
            [['new_version'], 'string'],
        ];
    }

    /**
     * 检查更新 + 返回当前升级进度(前端同一接口轮询)
     */
    public function check()
    {
        if (EditionHelper::isCommunity()) {
            list($version, $code) = $this->currentVersion();
            $available = $this->readJson(self::KEY_AVAILABLE);
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
                'current_version' => $version,
                'current_version_code' => $code,
                'online_upgrade' => false,
                'upgrade_message' => EditionHelper::MANUAL_UPGRADE_MESSAGE,
                'has_update' => false,
                'latest' => isset($available['license_notice']) ? ['license_notice' => $available['license_notice']] : null,
                'status' => null,
                'scheduler_ok' => null,
            ]);
        }

        // ?refresh=1:当场触发 scheduler 心跳一次刷新云端版本(双路,见 triggerSchedulerCheck)
        $schedulerOk = null;
        if (\Yii::$app->request->get('refresh')) {
            $schedulerOk = $this->triggerSchedulerCheck();
        }

        list($curVer, $curCode) = $this->currentVersion();

        $available = $this->readJson(self::KEY_AVAILABLE);
        $status = $this->readJson(self::KEY_STATUS);

        $hasUpdate = false;
        if ($available && isset($available['latest_version_code'])) {
            $hasUpdate = $curCode > 0 && (int)$available['latest_version_code'] > $curCode;
        }

        // 状态粘滞清理,三种情况都清(避免"下载中/升级完成"永久卡住、按钮被锁):
        //   ① 已达目标版本(含手动更新):无论什么状态,升级目标已实现 → 清;
        //   ② 终态(done/failed)超 10 分钟视为已读 → 清;
        //   ③ 进行中状态(queued/downloading/...)超 20 分钟没更新 → 调度器多半已死/被重启(如手动更新)
        //      → 判定超时,清掉允许重试。20 分钟阈值给大包下载+解压+迁移留足余量。
        if ($status) {
            $state = $status['state'] ?? '';
            $target = (int)($status['target_version_code'] ?? 0);
            $updatedAt = (int)($status['updated_at'] ?? 0);
            $age = $updatedAt > 0 ? (time() - $updatedAt) : 0;
            $reached = $target > 0 && $curCode > 0 && $curCode >= $target;
            $terminalStale = in_array($state, ['done', 'failed'], true) && $age > 600;
            $runningStale = in_array($state, self::RUNNING_STATES, true) && $age > 1200;
            if ($reached || $terminalStale || $runningStale) {
                RedisHelper::del(self::KEY_STATUS);
                $status = null;
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'current_version' => $curVer,
            'current_version_code' => $curCode,
            'has_update' => $hasUpdate,
            'latest' => $available,        // 可能为 null(scheduler 尚未回写)
            'status' => $status,           // 可能为 null(无升级在途)
            'scheduler_ok' => $schedulerOk, // 仅 refresh 时有值:true=调度器响应,false=没连上
        ]);
    }

    /**
     * 触发 scheduler 立即检查更新;返回后本地 available 已刷新(checked_at 推进)。
     *
     * 双路,保证「点一下一定能查到更新」:
     *   ① 快路:直连 scheduler 本地 HTTP /check-update(通的机器毫秒级,同步返回即已刷新);
     *   ② 兜底:HTTP 没连上/没刷新时,走 Redis check_now 命令 —— php-fpm 一定能连 Redis,
     *      先清 uniq 占位(否则被去重跳过),推 check_now,scheduler 主循环轮询即处理(通常 1~3s)。
     *
     * 返回 true=available.checked_at 已推进(刷新成功);false=超时仍未刷新(多为 scheduler 没运行)。
     */
    private function triggerSchedulerCheck()
    {
        $before = $this->availableCheckedAt();

        // ① 快路:HTTP 直连(失败不致命,如出站被 SELinux 拦)
        if ($this->httpCheckUpdate() && $this->availableCheckedAt() > $before) {
            return true;
        }

        // ② 兜底:清占位 + 下发 check_now,轮询 available 刷新(最多 12s,变了即返回)
        RedisHelper::del(self::KEY_UNIQ);
        JobHelper::pushRaw(
            ['class' => 'system.check_now', 'type' => 'check_now'],
            0,
            ['lane' => 'high', 'unique_key' => 'system_check_now']
        );
        for ($i = 0; $i < 12; $i++) {
            sleep(1);
            if ($this->availableCheckedAt() > $before) {
                return true;
            }
        }
        return false;
    }

    /**
     * 快路:直连 scheduler 本地 HTTP /check-update。true=响应正常;失败(超时/连接被拦)返回 false。
     * 超时压到 3s,连不上时尽快落到 Redis 兜底,不让用户干等。
     */
    private function httpCheckUpdate()
    {
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $resp = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($this->schedulerBaseUrl() . '/check-update')
                ->setOptions(['timeout' => 3])
                ->send();
            return $resp->isOk;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 当前 available 的 checked_at(秒),无则 0 —— 用于判定 scheduler 是否真的刷新过。
     */
    private function availableCheckedAt()
    {
        $av = $this->readJson(self::KEY_AVAILABLE);
        return (int)($av['checked_at'] ?? 0);
    }

    /**
     * scheduler 本地 HTTP 基址。优先级:
     *   ① 环境变量 SCHEDULER_HTTP(整段覆盖)
     *   ② 自动读 api/scheduler.yaml 的 server.port(端口随配置走,不写死)
     *   ③ 兜底 127.0.0.1:8081
     */
    private function schedulerBaseUrl()
    {
        $env = getenv('SCHEDULER_HTTP');
        if ($env) {
            return rtrim($env, '/');
        }
        $port = '8081';
        $file = \Yii::$app->basePath . '/scheduler.yaml';
        if (is_file($file)) {
            $content = (string)file_get_contents($file);
            // 匹配 server 块下的 port: ":8081" / port: 8081(scheduler.yaml 里唯一的 port 键)
            if (preg_match('/^\s*port:\s*["\']?:?(\d+)/m', $content, $m)) {
                $port = $m[1];
            }
        }
        return 'http://127.0.0.1:' . $port;
    }

    /**
     * 触发升级:推命令给 scheduler + 写初始状态,立即返回(异步,不等待)
     */
    public function update()
    {
        if (EditionHelper::isCommunity()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, EditionHelper::MANUAL_UPGRADE_MESSAGE);
        }

        $target = (int)$this->new_version;
        if ($target <= 0) {
            $available = $this->readJson(self::KEY_AVAILABLE);
            $target = (int)($available['latest_version_code'] ?? 0);
        }
        if ($target <= 0) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '没有可升级的目标版本');
        }

        // 当前版本已 >= 目标,无需升级
        list(, $curCode) = $this->currentVersion();
        if ($curCode > 0 && $target <= $curCode) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '当前已是最新版本');
        }

        // 防重复:已有升级在途
        $status = $this->readJson(self::KEY_STATUS);
        if ($status && in_array(($status['state'] ?? ''), self::RUNNING_STATES, true)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '已有升级任务进行中,请稍候');
        }

        // 推送升级命令 —— scheduler 按 type=upgrade 原生处理(不转 PHP-FPM)
        JobHelper::pushRaw([
            'class' => 'system.upgrade',
            'type' => 'upgrade',
            'version_code' => $target,
        ], 0, ['lane' => 'high', 'unique_key' => 'system_upgrade']);

        // 写初始状态,前端立即可见"已排队"
        RedisHelper::set(self::KEY_STATUS, json_encode([
            'state' => 'queued',
            'target_version_code' => $target,
            'message' => '已排队,等待调度器执行',
            'updated_at' => time(),
        ]));

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '升级任务已下发');
    }

    /**
     * 读取主目录版本文件 api/version.json → [version, version_code]
     */
    private function currentVersion()
    {
        $file = \Yii::$app->basePath . '/version.json';
        if (!is_file($file)) {
            return ['0.0.0', 0];
        }
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            return ['0.0.0', 0];
        }
        return [$data['version'] ?? '0.0.0', (int)($data['version_code'] ?? 0)];
    }

    /**
     * 读 Redis 字符串键并 JSON 解码,失败返回 null
     */
    private function readJson($key)
    {
        $raw = RedisHelper::get($key);
        if (!$raw) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
