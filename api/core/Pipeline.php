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

class Pipeline
{
    protected $payload;
    protected $stages = [];

    /**
     * 发送载荷（上下文数据）
     */
    public function send($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * 设置需要穿过的管道节点（按顺序）
     */
    public function through(array $stages)
    {
        $this->stages = $stages;
        return $this;
    }

    /**
     * 执行管道并返回最终结果
     */
    public function then(\Closure $destination)
    {
        // 使用 array_reduce 包装洋葱模型（倒序组装闭包）
        $pipeline = array_reduce(
            array_reverse($this->stages),
            function ($next, $stage) {
                return function ($payload) use ($next, $stage) {
                    // 如果传入的是类名，则实例化
                    if (is_string($stage) && class_exists($stage)) {
                        $stage = new $stage();
                    }
                    // 调用节点的 handle 方法，并将控制权 ($next) 传给它
                    return $stage->handle($payload, $next);
                };
            },
            $destination
        );

        return $pipeline($this->payload);
    }
}
