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

/**
 * 「按打开页面时的值算差额」的编辑,同一个页面只能生效一次
 *
 * 后台编辑库存 / 名额 / 余额时,保存的是「表单值 − 打开页面时的值」的差额(不整值写回,免得盖掉期间的销量)。
 * 但差额天生不幂等:同一个页面提交两次(保存成功后按钮还能点、网络重发),差额就加两遍 ——
 * 10 改成 15 保存两次变成 20,凭空多出 5 件,然后超卖。
 *
 * 用法:打开编辑页时发一个令牌(issue),前端原样带回;保存时先 claim,
 *   · 抢到了:这是这个页面的第一次保存,照常加减差额;
 *   · 没抢到:同一个页面已经保存过,差额不再应用(其他字段照常保存,重复写一遍无害);
 *   · 保存失败要 release,不然用户改完重试,这次的差额会被当成重复而丢掉。
 * 没带令牌的(上线前打开的老页面)不设防,和原来一样。
 */
class EditOnceHelper
{
    /** 令牌留一天:编辑页开一天还没保存的,保存时差额照常生效(令牌过期不影响第一次保存) */
    const TTL = 86400;

    public static function issue(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param string $scope  业务名,如 goods_stock / mch_first_order / card_wallet
     * @param int $id        被编辑的那条记录
     * @param mixed $token   前端带回来的令牌
     * @return array [bool 是否这个页面的第一次保存, ?array 失败时交给 release 的凭据]
     */
    public static function claim(string $scope, int $id, $token): array
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '' || !preg_match('/^[0-9a-f]{16,64}$/', $token)) {
            return [true, null];   // 没带令牌:不设防
        }
        $key = "edit_once:{$scope}:{$id}:{$token}";
        try {
            $lock = LockHelper::lock($key, self::TTL);
        } catch (\Throwable $e) {
            // Redis 出问题时放行(退回没有令牌时的行为),不能因为防重复把正常保存也卡死
            \Yii::error("[EditOnce] 抢令牌失败,本次不设防: " . $e->getMessage());
            return [true, null];
        }
        if ($lock === false) {
            return [false, null];
        }
        return [true, [$key, $lock]];
    }

    /** 保存失败时放回令牌,让用户改完重试时差额还能生效 */
    public static function release(?array $claim): void
    {
        if ($claim) {
            try {
                LockHelper::unlock($claim[0], $claim[1]);
            } catch (\Throwable $e) {
                \Yii::error('[EditOnce] 放回令牌失败: ' . $e->getMessage());
            }
        }
    }
}
