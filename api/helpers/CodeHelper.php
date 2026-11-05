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

class CodeHelper
{
    /**
     * 生成业务核销码
     *
     * @param string $prefix 业务前缀 (如 '9', '6')
     * @param int $length 总长度 (默认12位)
     * @return string
     */
    public static function generateCode($prefix = '', $length = 12)
    {
        // 1. 计算剩余需要的随机长度
        $prefixLen = strlen($prefix);
        $randomLen = $length - $prefixLen;

        if ($randomLen < 6) {
            $randomLen = 6;
        }

        // 2. 核心算法：前缀 + 时间因子 + 随机数
        // 采用 [年月日时分秒] 的压缩形式会让码更有序，不仅防重，还能看出大概时间

        // 方案 A (高并发推荐)：前缀(1) + 时间戳后8位 + 随机3位 = 12位
        // 优点：同一秒内即使随机数只有3位，因为时间戳在变，也不易重复

        // 方案 B (你的逻辑优化版)：前缀 + 毫秒微秒混淆 + 补齐
        // 这种生成的码看起来毫无规律，像乱序码

        $numericTime = floor(microtime(true) * 1000); // 毫秒级时间戳 13位
        $timeStr = substr((string)$numericTime, -5); // 取最后5位 (变化最快的部分)

        // 补齐随机数
        $remainLen = $randomLen - 5; // 剩余给随机数的位数
        $min = pow(10, $remainLen - 1);
        $max = pow(10, $remainLen) - 1;

        return $prefix . $timeStr . mt_rand($min, $max);
    }

    /**
     * [进阶] 生成绝对不重复的码 (带数据库排重)
     *
     * @param string $prefix 前缀
     * @param string $modelClass 模型类名 (用于查重)
     * @param string $field 字段名 (如 verify_code)
     * @return string
     */
    public static function generateUniqueCode($prefix, $modelClass, $field = 'verify_code')
    {
        $limit = 10; // 防止死循环
        while ($limit > 0) {
            $code = self::generateCode($prefix);
            // 数据库查重
            $exists = $modelClass::find()->where([$field => $code])->exists();
            if (!$exists) {
                return $code;
            }
            $limit--;
        }
        throw new \Exception('核销码生成拥堵，请稍后重试');
    }
}