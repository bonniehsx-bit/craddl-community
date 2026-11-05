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

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\core\Manager;

class ScheduleController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionExecute()
    {
        ob_start();

        // 致命错误兜底：OOM/超时/parse 这类【不可 catch】的 fatal 会把 PHP 堆栈直接写进
        // FastCGI stdout，导致 Go 派发端解析响应头报 "malformed MIME header: missing colon: #0 {main}"
        // 并把任务误判为"派发网络异常"重试到死信。这里在 shutdown 阶段清掉脏输出、补一段干净 JSON。
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=UTF-8');
                }
                echo json_encode(['status' => 'error', 'message' => 'Fatal: ' . $err['message']]);
            }
        });

        try {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $rawBody = Yii::$app->request->getRawBody();
            $data = json_decode($rawBody, true);

            if (!$data || !isset($data['class'])) {
                ob_clean();
                return ['status' => 'error', 'message' => 'Empty Payload'];
            }

            // 1. Cron 任务 (Go 传过来的 type=cron)
            if (isset($data['type']) && $data['type'] === 'cron') {
                return $this->handleCronTask($data);
            }

            // 2. 普通队列任务
            $job = Yii::createObject($data);
            if (method_exists($job, 'execute')) {
                $job->execute();
                ob_clean();
                return ['status' => 'success', 'message' => 'Job Done'];
            }

            ob_clean();
            return ['status' => 'error', 'message' => 'Invalid Job'];

        } catch (\Throwable $e) {
            // \Throwable 含 \Error：之前只 catch \Exception，TypeError / "call on null" 等 \Error
            // 会逃逸成未捕获致命错误，把堆栈泄进 FastCGI 响应 → Go 报 malformed MIME header → 死信。
            $msg = $e->getMessage();
            Yii::error("Scheduler Error: {$msg}\n" . $e->getTraceAsString(), 'scheduler');
            ob_clean();
            return ['status' => 'error', 'message' => $msg];
        }
    }

    private function handleCronTask($data)
    {
        $className = $data['class'];
        $method = $data['method'] ?? 'run';

        // 格式转换: app\cron\Test -> app.cron.Test@run
        $jobId = str_replace('\\', '.', $className) . '@' . $method;

        // 调用 Manager
        $manager = new Manager();
        $result = $manager->run($jobId);

        ob_clean();

        if ($result) {
            return ['status' => 'success', 'message' => "Cron {$jobId} OK"];
        } else {
            return ['status' => 'failed', 'message' => "Cron {$jobId} Failed"];
        }
    }
}