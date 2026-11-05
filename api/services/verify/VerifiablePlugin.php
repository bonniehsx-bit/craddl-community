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
 * 可核销插件契约(核销治理阶段4)。
 *
 * 强契约版:插件实现本接口即接入平台/商家核销中心,返回 DTO(消除既有裸数组钩子的
 * 返回结构漂移、is_manual_force 语义靠自觉等弱契约问题)。
 *
 * 兼容:VerifyGateway 优先识别本接口;未实现的存量插件回退到老钩子
 * getVerifyCodeDetail/verifyCode(反射 3/4 参),不强制迁移、零回归。
 * 迁移方式:插件实现本接口,内部产出 VerifyDetail/VerifyResult 即可,可与老钩子共存过渡。
 *
 * mall 隔离:实现方须以 $ctx->mallId 约束码查询(建议 findByMall),从源头堵多租户串场。
 */
interface VerifiablePlugin
{
    /**
     * 查询核销码详情(scan 预览)。
     * @return VerifyDetail|null null=这码不归我(交下一个插件);否则=命中详情。抛业务异常=校验失败。
     */
    public function getVerifyDetail(string $code, VerifyContext $ctx): ?VerifyDetail;

    /**
     * 执行核销(commit)。需在调用方的锁+事务内执行。
     * @return VerifyResult|null null=这码不归我;否则=核销结果。抛业务异常=校验失败。
     */
    public function doVerify(string $code, VerifyContext $ctx): ?VerifyResult;
}
