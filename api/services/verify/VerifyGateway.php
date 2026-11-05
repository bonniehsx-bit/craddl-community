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

use Yii;

/**
 * 核销统一内核——收口"插件核销链"的分发逻辑。
 *
 * 阶段1(行为保持型重构):平台核销中心 PlatformVerifyForm 与商家 UnifiedVerifyForm
 * 原各自内联一份完全相同的"遍历插件调 getVerifyCodeDetail/verifyCode"责任链,现统一到此。
 * 自营普通单核销、锁、事务、结算策略仍保留在各 Form(平台延后结算 / 商家极速结算,
 * 是有意的策略差异,不在本阶段强行统一,见 docs/方案-核销体系统一治理.md §9 决策项5)。
 *
 * 契约(与既有一致):插件 getVerifyCodeDetail/verifyCode 返回 false="这码不归我,交下一个插件";
 * 返回数组=命中结果;抛业务异常="是我的码但校验失败",直接上抛阻断遍历。
 * 容错采用更成熟的商家版口径:业务 Exception 上抛,系统级 Throwable(插件被删/语法错)记日志跳过,
 * 防单个坏插件阻塞整条扫描链(平台侧原缺此隔离,此处收口后一并获得)。
 */
class VerifyGateway
{
    /**
     * 商城上下文守卫 —— 无 mall 时明确拒绝,而不是让它静默失效。
     *
     * 背景:Web 请求的 mall 完全来自前端的 x-mall-id header(见 AppBootstrap::bootstrapMall),
     * 没传就是 null。而插件按商城隔离时用的是 Yii::$app->mall->id —— PHP 8 里 null->id 只是
     * Warning 并返回 null,查询于是变成 `mall_id IS NULL`,一行也查不到:核销员只会看到
     * 「无效的核销码」,完全不知道真因是请求少了个 header。
     *
     * 更要紧的是另一头:若不拦而是放行,等于"不传 x-mall-id 就能跳过商城隔离",
     * 隔离形同虚设。所以这里一律拒绝,让两种后果都变成一句能照着排查的话。
     */
    private function assertMallContext(VerifyContext $ctx): void
    {
        if ($ctx->mallId <= 0) {
            throw new \Exception('缺少商城上下文，无法核销(请求未携带 x-mall-id)');
        }
    }

    /**
     * 遍历插件查询核销码详情(scan 预览阶段)。
     * @return array|null 命中插件返回其详情;无人认领返回 null(用 !== null 判定)
     */
    public function searchPlugins(string $code, VerifyContext $ctx)
    {
        $this->assertMallContext($ctx);

        foreach (Yii::$app->plugin->getAllPlugins() as $plugin) {
            if (!is_object($plugin)) {
                continue;
            }
            try {
                // 新契约优先:实现 VerifiablePlugin 的插件走 DTO
                if ($plugin instanceof VerifiablePlugin) {
                    $detail = $plugin->getVerifyDetail($code, $ctx);
                    if ($detail !== null) {
                        return $detail->toArray();
                    }
                    continue; // 已接新契约但不认领此码,不再走老钩子
                }
                // 老钩子回退(存量插件不迁移即可继续跑)
                if (method_exists($plugin, 'getVerifyCodeDetail')) {
                    $res = call_user_func([$plugin, 'getVerifyCodeDetail'], $code, $ctx->mchId);
                    if ($res !== false) {
                        return $res;
                    }
                }
            } catch (\Exception $e) {
                // 业务异常(码属该插件但状态不对等):上抛阻断
                throw $e;
            } catch (\Throwable $e) {
                Yii::error("插件执行 getVerifyCodeDetail 崩溃: " . $e->getMessage());
                continue;
            }
        }
        return null;
    }

    /**
     * 遍历插件执行核销(commit 阶段)。反射兼容 3/4 参(第4参 is_manual_force 强制核销)。
     * 需由调用方在自己的锁 + 事务内调用(本阶段锁/事务仍归各 Form)。
     * 用 call_user_func 调用以兼容"基类 3 参、子类扩成 4 参"(直连会被静态检查判参数过多)。
     * @return array|null 命中插件返回其核销结果;无人认领返回 null(用 !== null 判定)
     */
    public function verifyPlugins(string $code, VerifyContext $ctx)
    {
        $this->assertMallContext($ctx);

        foreach (Yii::$app->plugin->getAllPlugins() as $plugin) {
            if (!is_object($plugin)) {
                continue;
            }
            try {
                // 新契约优先:实现 VerifiablePlugin 的插件走 DTO
                if ($plugin instanceof VerifiablePlugin) {
                    $result = $plugin->doVerify($code, $ctx);
                    if ($result !== null) {
                        return $result->toArray();
                    }
                    continue;
                }
                // 老钩子回退(反射兼容 3/4 参)
                if (method_exists($plugin, 'verifyCode')) {
                    $reflection = new \ReflectionMethod($plugin, 'verifyCode');
                    if ($reflection->getNumberOfParameters() >= 4) {
                        $res = call_user_func([$plugin, 'verifyCode'], $code, $ctx->mchId, $ctx->clerkId, $ctx->isManualForce);
                    } else {
                        $res = call_user_func([$plugin, 'verifyCode'], $code, $ctx->mchId, $ctx->clerkId);
                    }
                    if ($res !== false) {
                        return $res;
                    }
                }
            } catch (\Exception $e) {
                throw $e;
            } catch (\Throwable $e) {
                Yii::error("插件执行 verifyCode 崩溃: " . $e->getMessage());
                continue;
            }
        }
        return null;
    }
}
