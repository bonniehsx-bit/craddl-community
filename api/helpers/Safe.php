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

namespace app\helpers;

use Yii;

/**
 * 防御性执行工具 —— 让"忽略异常"这件事变得有语义、可追溯。
 *
 * 背景：代码库里散落大量 `catch (\Throwable $e) {}` 裸空 catch，
 * 同时表达了两种相反语义："失败无所谓"(合理) 和 "没想好怎么处理"(高危)，读代码无法区分。
 *
 * 本类用于前者：**best-effort 的可选副作用**（通知推送、快照解析、统计上报、缓存写入等
 * "失败不影响主流程"的操作）。用它替代裸空 catch，好处：
 *   1. 语义自解释 —— 调用处一眼看出"这是可失败的副作用"
 *   2. 失败有痕 —— 统一记 warning 日志，线上可排查，不再是黑洞
 *
 * ⚠️ 注意：**主流程/状态变更/金钱相关**的异常**禁止**用本类吞掉，
 *    必须显式处理（事务回滚 / 重试 / 降级）并 Yii::error 留痕。详见开发规范 §2.8。
 */
class Safe
{
    /**
     * 安全执行 best-effort 闭包：捕获所有异常 + 记 warning 日志，不向上抛。
     *
     * @param callable $fn      要执行的闭包
     * @param string   $context 日志上下文标识，建议用「模块.场景」格式，如 'mch.apply.notify'
     * @param mixed    $default 失败时的返回值（默认 null）
     * @return mixed 成功返回闭包结果，失败返回 $default
     *
     * 用法：
     *   Safe::tryQuietly(fn() => $this->sendApplyNotify($mch), 'mch.apply.notify');
     *   $snap = Safe::tryQuietly(fn() => Json::decode($row->snapshot), 'group.order.snapshot', []);
     */
    public static function tryQuietly(callable $fn, string $context = '', $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $tag = $context !== '' ? "[{$context}] " : '';
            Yii::warning($tag . '忽略 best-effort 异常: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'safe');
            return $default;
        }
    }
}
