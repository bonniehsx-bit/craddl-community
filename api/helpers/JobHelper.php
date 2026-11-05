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
 * Author:ganxh
 * User: cp
 * Date: 2021/9/27
 * Time: 14:14
 */

namespace app\helpers;


use app\core\Job;
use app\models\Queue;
use yii\helpers\Json;
use yii\queue\JobInterface;
use Yii;

// [新增] 引入我们新的调度器 Job 类
use app\engine\scheduler\Job as SchedulerJob;
use app\engine\scheduler\runnable\Runnable;

// [新增] 引入 Runnable 接口

class JobHelper
{


// 定义 Redis Key，必须与 Go config.yaml 保持一致
    const SCHEDULER_KEY = 'jxmall_scheduler';        // normal 车道（沿用原 key）
    const EVENT_KEY = 'jxmall_scheduler_event';

    // 默认最大尝试次数（含首次），超过后 Go 端写入死信表
    const MAX_ATTEMPT = 3;

    // 任务车道 —— 决定入哪个 ZSet；Go 端按 high→normal→low 优先级派发，throttle 限量派发
    const LANE_HIGH = 'high';
    const LANE_NORMAL = 'normal';
    const LANE_LOW = 'low';
    const LANE_THROTTLE = 'throttle';

    /**
     * 车道 → ZSet key 映射
     */
    public static function laneKey($lane)
    {
        switch ($lane) {
            case self::LANE_HIGH:     return self::SCHEDULER_KEY . ':high';
            case self::LANE_LOW:      return self::SCHEDULER_KEY . ':low';
            case self::LANE_THROTTLE: return self::SCHEDULER_KEY . ':throttle';
            case self::LANE_NORMAL:
            default:                  return self::SCHEDULER_KEY;
        }
    }

    /**
     * 推送任务到 Go 调度器 (Redis ZSet + PubSub)
     *
     * @param object $job 任务对象 (例如 new TestJob(['id'=>1]))
     * @param int $delay 延迟秒数 (0 表示立即执行)
     * @param array $options 可选：['lane'=>'high', 'unique_key'=>'stat_mall_5']
     * @return bool
     */
    public static function push($job, $delay = 0, $options = [])
    {
        // 1. 获取任务数据 (类名 + 公共属性)
        // 使用反射提取 public 属性，这样不仅支持数组传参，也支持对象属性赋值
        $reflection = new \ReflectionClass($job);
        $attributes = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            // 排除静态属性
            if (!$property->isStatic()) {
                // 如果属性没有初始化，getValue 可能会报错，这里加个判断或默认值处理
                if ($property->isInitialized($job)) {
                    $attributes[$property->getName()] = $property->getValue($job);
                }
            }
        }

        $payload = array_merge([
            'class' => get_class($job),
        ], $attributes);

        return self::pushRaw($payload, $delay, $options);
    }

    /**
     * 以"信封"格式推送一个原始 payload 到调度器
     *
     * 信封结构（Go 端按此解析；Go 派发给 PHP 时只取 payload）：
     *   { v:1, attempt:0, max:3, lane:..., unique_key:..., enqueued_at:ts, payload:{class:..., ...} }
     *
     * 用途：① push() 内部调用  ② 死信任务后台人工重投（attempt 归 0 重新计数）
     *
     * @param array $payload 任务配置，必须含 class，形如 ['class'=>'app\\jobs\\XxxJob', 'id'=>1]
     * @param int $delay 延迟秒数
     * @param array $options ['lane'=>'high|normal|low|throttle', 'unique_key'=>'xxx']
     * @return bool 入队成功 / 已被去重(也算成功) 返回 true
     */
    public static function pushRaw(array $payload, $delay = 0, $options = [])
    {
        if (empty($payload['class'])) {
            Yii::error('JobHelper::pushRaw payload 缺少 class', 'scheduler');
            return false;
        }

        $lane = isset($options['lane']) ? $options['lane'] : self::LANE_NORMAL;
        if (!in_array($lane, [self::LANE_HIGH, self::LANE_NORMAL, self::LANE_LOW, self::LANE_THROTTLE], true)) {
            $lane = self::LANE_NORMAL;
        }
        $uniqueKey = isset($options['unique_key']) ? (string)$options['unique_key'] : '';

        $delay = (int)$delay;
        $runTime = time() + $delay;

        try {
            $redis = Yii::$app->redis;

            // 唯一性投递：同 unique_key 已有在途任务则跳过（占位 key 带 TTL 防泄漏）
            if ($uniqueKey !== '') {
                $uniqRedisKey = self::SCHEDULER_KEY . ':uniq:' . $uniqueKey;
                $ttl = max($delay + 3600, 3600);
                // SET NX EX：占位成功才入队
                $ok = $redis->set($uniqRedisKey, '1', 'EX', $ttl, 'NX');
                if (!$ok) {
                    Yii::info("JobHelper 去重跳过: {$payload['class']} unique_key={$uniqueKey}", 'scheduler');
                    return true; // 已有在途同名任务，逻辑上视为已调度
                }
            }

            $envelope = [
                'v'           => 1,
                'attempt'     => 0,
                'max'         => self::MAX_ATTEMPT,
                'lane'        => $lane,
                'unique_key'  => $uniqueKey,
                'enqueued_at' => time(),
                'payload'     => $payload,
            ];

            // 写入对应车道 ZSet
            $redis->zadd(self::laneKey($lane), $runTime, Json::encode($envelope));
            // 发布事件，供 Go 打印 "📅 新增任务" 日志
            $redis->publish(self::EVENT_KEY, Json::encode([
                'class'    => $payload['class'],
                'run_time' => $runTime,
            ]));
            return true;
        } catch (\Exception $e) {
            Yii::error('Failed to push job: ' . $e->getMessage(), 'scheduler');
            return false;
        }
    }
}

