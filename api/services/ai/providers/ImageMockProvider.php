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

use app\services\ai\ImageProviderInterface;

/**
 * 文生图 Mock 驱动 —— 未配置生图供应商时的兜底，返回一张占位图。
 *
 * ⚠️ 与 MockProvider（文本）的定位有本质区别，别照抄那边的"静默兜底"思路：
 *   文本兜底返回一段模板文案，用户看到的只是内容平庸；
 *   生图兜底返回占位图，用户看到的是**一本花钱买来的空白绘本**。
 *
 * 所以本驱动的 isAvailable() 恒为 false，AiGateway::imageGenerate() 见到不可用会
 * **直接抛异常**而不是拿占位图当成功——让业务侧走失败退款，而不是把废品交付给用户。
 * （这正是老 aidb GeminiDriver 踩过的坑：返回 placehold.co 且不抛异常，
 *   于是残次绘本被判成功、钱照扣。）
 */
class ImageMockProvider implements ImageProviderInterface
{
    public function generateImage(string $prompt, array $options = []): string
    {
        throw new \RuntimeException('未配置文生图供应商（后台「商城管理 → AI 配置 → 文生图」）');
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'mock';
    }
}
