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

namespace app\services\verify;

/**
 * 核销上下文——承载一次核销所需的隔离维度与操作人身份。
 * 由各入口(平台核销中心 / 商家核销 / 插件落地页)按各自鉴权后构造,传给 VerifyGateway。
 *
 * 见 docs/方案-核销体系统一治理.md。本阶段(阶段1)仅承载插件链分发所需字段;
 * 后续阶段会扩展 clerkName/source 用于统一 ClerkLog 台账。
 */
class VerifyContext
{
    /** @var int 商城ID(数据隔离) */
    public int $mallId;

    /** @var int 商户ID:0=平台自营,>0=商家 */
    public int $mchId;

    /** @var int 操作人 user id(核销员/店员) */
    public int $clerkId;

    /** @var string 操作人昵称快照 */
    public string $clerkName = '';

    /** @var int 是否强制核销(1=是),透传给支持 4 参 verifyCode 的插件 */
    public int $isManualForce = 0;

    /** @var string 来源:platform|mch|plugin_landing(供台账区分,阶段3 用) */
    public string $source = '';

    public function __construct(int $mallId, int $mchId, int $clerkId, string $clerkName = '', int $isManualForce = 0, string $source = '')
    {
        $this->mallId = $mallId;
        $this->mchId = $mchId;
        $this->clerkId = $clerkId;
        $this->clerkName = $clerkName;
        $this->isManualForce = $isManualForce;
        $this->source = $source;
    }
}
