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

namespace app\services\ai;

/**
 * ASR（语音识别）驱动接口 —— 提交 / 查询两段式，而非阻塞返回文本。
 *
 * 与 AiProviderInterface::chat() 不同：语音识别任务（尤其数十分钟的长录音）耗时可达数分钟，
 * 而调用方（BeautyVoiceTranscribeJob 等转写 Job）经 Go 调度器派发时走 FastCGI，
 * 硬超时 30 秒（scheduler/worker/dispatcher.go 的 DispatchToFpm）。
 * 若在 submit() 里原地 sleep 轮询到完成再返回，任务必然被 FastCGI 掐断、判失败重投，
 * 且每次重投都会重新提交一次任务——永远完成不了，还重复计费。
 *
 * 正确做法：submit() 立即返回任务 ID；query() 立即返回当前状态，不等待。
 * 真正的"轮询"由调用方以 job 重投实现（每次派发都很快，天然吃得消 30 秒限制）。
 */
interface AsrProviderInterface
{
    /**
     * 提交一个异步转写任务。
     *
     * @param  string $audioUrl 音频文件的公网可访问 URL
     * @param  array  $options  各 provider 自行解读的扩展参数（如语言/模型选择）
     * @return string 供应商侧任务 ID，query() 时原样传回
     * @throws \RuntimeException 提交失败（未配置 / 网络错误 / 供应商拒绝）
     */
    public function submit(string $audioUrl, array $options = []): string;

    /**
     * 查询任务状态，立即返回，不阻塞等待。
     *
     * @param  string $taskId submit() 返回的任务 ID
     * @return array  ['status'=>'pending'|'done'|'failed', 'text'=>string, 'error_msg'=>string]
     *                status=pending 时 text/error_msg 均为空，调用方应稍后重新 query()
     * @throws \RuntimeException 查询请求本身失败（网络错误等，区别于"任务失败"）
     */
    public function query(string $taskId): array;

    /**
     * 驱动是否可用（密钥已配置且格式有效），不做网络探活。
     */
    public function isAvailable(): bool;

    /**
     * 驱动标识名（用于日志 / 前端展示）。例：'mock' | 'tencent'
     */
    public function getName(): string;
}
