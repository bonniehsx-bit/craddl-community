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

namespace app\core;

use app\helpers\CacheHelper;
use yii\log\Logger;
use yii\log\Target;

/**
 * Yii2 错误日志 Redis Target
 * 与 FileTarget 并行运行，把 error/warning 异步推送给 Go 消费落库监控
 * 任何异常都静默处理，不影响主请求
 */
class RedisLogTarget extends Target
{
    public function export()
    {
        try {
            $key = env('REDIS_KEY_YII_LOG', 'yii_error_logs');
            foreach ($this->messages as $message) {
                [$text, $level, $category, $timestamp, $traces] = array_pad($message, 5, []);

                // 异常对象或数组统一转字符串
                if ($text instanceof \Throwable) {
                    $msgStr = $text->getMessage() . "\n" . $text->getTraceAsString();
                } elseif (is_array($text)) {
                    $msgStr = json_encode($text, JSON_UNESCAPED_UNICODE);
                } else {
                    $msgStr = (string)$text;
                }

                // 堆栈（最多取前 5 帧）
                $traceStr = '';
                if (!empty($traces) && is_array($traces)) {
                    $frames = array_slice($traces, 0, 5);
                    $lines = [];
                    foreach ($frames as $frame) {
                        $file = $frame['file'] ?? '';
                        $line = $frame['line'] ?? '';
                        $lines[] = "{$file}:{$line}";
                    }
                    $traceStr = implode("\n", $lines);
                }

                $logPayload = [
                    'level'        => Logger::getLevelName($level),
                    'category'     => $category,
                    'message'      => mb_substr($msgStr, 0, 5000),   // 截断超长消息
                    'trace'        => $traceStr,
                    'url'          => $_SERVER['REQUEST_URI'] ?? '',
                    'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
                    'created_at'   => (int)$timestamp,
                    'created_time' => date('Y-m-d H:i:s', (int)$timestamp),
                    'updated_at'   => (int)$timestamp,
                    'updated_time' => date('Y-m-d H:i:s', (int)$timestamp),
                ];

                CacheHelper::lpush($key, $logPayload);
            }
        } catch (\Throwable $e) {
            // 静默失败，绝不因监控代码影响业务
        }
    }
}
