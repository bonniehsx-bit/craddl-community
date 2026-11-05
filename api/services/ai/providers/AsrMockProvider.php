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

namespace app\services\ai\providers;

use app\services\ai\AsrProviderInterface;

/**
 * Mock ASR 驱动 —— 未配置真实供应商时的占位实现，用于开发联调，不消耗任何额度。
 *
 * 与主 AI 的 MockProvider 同思路：query() 恒返回固定占位文本，submit() 后立即可查完成，
 * 便于在没有真实密钥的环境下把「提交 → 轮询 → 完成 → LLM 提取」整条链路跑通验证。
 */
class AsrMockProvider implements AsrProviderInterface
{
    public function __construct(string $secretId = '', string $secretKey = '')
    {
    }

    public function submit(string $audioUrl, array $options = []): string
    {
        return 'mock_' . substr(md5($audioUrl . microtime(true)), 0, 16);
    }

    public function query(string $taskId): array
    {
        return [
            'status'    => 'done',
            'text'      => '[Mock ASR] 这是模拟转写文本，用于联调，非真实识别结果。',
            'error_msg' => '',
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'mock';
    }
}
