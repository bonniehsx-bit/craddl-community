<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-17
 * Time: 15:34
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


class DateHelper
{

    /**
     * 把前端传来的时间值规范成【秒级整数时间戳】,供 Form 的 filter 规则用。
     *
     * ## 为什么要有这个
     * 后台的时间字段几乎都是 el-date-picker + value-format="timestamp",而它:
     *   · 输出的是**毫秒**(源码 `if (format === 'timestamp') return value.getTime()`)
     *   · 面板初始值是 `new Date()`,毫秒位就是打开选择器那一刻的毫秒数
     *   · 选日期/时间时只 set 年月日时分秒 —— 全组件没有一处 setMilliseconds
     * 于是毫秒位一路带到提交,调用方 `/1000` 就得到 1785225435.123 这种小数,
     * 后端 integer 规则直接拒 →「XX 必须是整数」。只有毫秒刚好为 0(1/1000)才不报。
     * 商城到期时间(MallEditForm::end_at)就是这么炸的。
     *
     * ## 用法(filter 规则要排在 integer 之前,Yii 按 rules 顺序执行)
     *   [['end_at'], 'filter', 'filter' => [DateHelper::class, 'normalizeTimestamp']],
     *   [['end_at'], 'integer', 'skipOnEmpty' => true],
     *
     * 空值保持原样(配合 skipOnEmpty 放行"不限期"这类语义);
     * 非数字原样返回,交给 integer 规则报错 —— 那才是真的传错了类型,不该悄悄改掉。
     */
    public static function normalizeTimestamp($value)
    {
        if ($value === '' || $value === null || $value === []) {
            return $value;
        }
        if (!is_numeric($value)) {
            return $value;
        }
        $n = (float) $value;
        if ($n <= 0) {
            return 0;
        }
        // 秒级时间戳到 2286 年仍是 10 位;>= 1e11 只可能是毫秒(13 位),折回秒
        if ($n >= 100000000000) {
            $n = $n / 1000;
        }
        return (int) $n;
    }

    public static function format($timestamp = null, $format = 'Y-m-d H:i:s')
    {
        if (!$timestamp) {
            $timestamp = time();
        }
        return date($format, $timestamp);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2021-01-05
     * @Time: 15:38
     * @Note:昨日
     */
    public static function yesterday_start_at()
    {
        return mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
    }


    public static function yesterday_end_at()
    {
        return mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
    }

    public static function month_start_at()
    {
        return mktime(0, 0, 0, date('m'), 1, date('Y'));
    }

    public static function week_start_at()
    {
        return mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
    }

    public static function today_start_at()
    {
        return strtotime(date('Y-m-d', time()));
    }

    public static function last_month_start_at()
    {
        return strtotime('-1 month', strtotime(date('Y-m-01'))); // 从当月 1 号推,31 号直接推会算回本月
    }

    public static function beforeTime($time = 0)
    {
        $t = time() - $time;
        $f = array(
            '31536000' => '年',
            '2592000' => '个月',
            '604800' => '星期',
            '86400' => '天',
            '3600' => '小时',
            '60' => '分钟',
            '1' => '秒'
        );
        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                return $c . $v . '前';
            }
        }
    }

    public static function rangeParse($searchDate = null, $isTimestamp = false)
    {
        if (!$searchDate) {
            return null;
        }
        if (is_array($searchDate)) {
            if (count($searchDate) == 2) {
                if ($isTimestamp) {
                    return [strtotime($searchDate[0]), strtotime($searchDate[1])];
                } else {
                    return $searchDate;
                }
            }
        }
        return null;
    }

    public static function getNowTime()
    {
        return date('Y-m-d H:i:s');
    }
}
