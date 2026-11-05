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

/**
 * Created by PhpStorm.
 * User: JUST
 * Date: 2022/6/28
 * Time: 17:21
 */

namespace app\helpers;


class RedisHelper
{
    /**
     * @param $key
     * @param $lat
     * @param $lon
     * @param int $distance
     * @param string $unit
     * @param string $sort
     * @return array
     */
    public static function getItemWithDistance($key, $lon, $lat, $distance = 10000, $unit = 'km', $sort = 'ASC')
    {
        $arr = \Yii::$app->redis->georadius($key, $lon, $lat, $distance, $unit, $sort, 'WITHDIST');
        $arr1 = \yii\helpers\ArrayHelper::getColumn($arr, 0);
        $arr2 = \yii\helpers\ArrayHelper::getColumn($arr, 1);
        $arr3 = array_combine($arr1, $arr2);
        return $arr3;
    }

    public static function getNearItem($key, $lon, $lat, $distance = 10000, $unit = 'km', $sort = 'ASC')
    {
        $res = \Yii::$app->redis->georadius($key, $lon, $lat, $distance, $unit, $sort);
        return $res;
    }

    public static function setGeoItem($key, $lon, $lat, $value)
    {
        \Yii::$app->redis->geoadd($key, $lon, $lat, $value);
    }

    public static function set($key, $value, $expire = 0)
    {
        if (\Yii::$app->redis->set($key, $value)) {
            if ($expire) {
                \Yii::$app->redis->expire($key, $expire);
            }
            return true;
        }
        return false;
    }

    public static function get($key, $value = null)
    {
        $val = \Yii::$app->redis->get($key);
        if (!$val) {
            if (!empty($value)) {
                return $value;
            }
            return null;
        }
        return $val;
    }

    //无序集合只会出现一个值
    public static function sadd($key, $value)
    {
        return \Yii::$app->redis->sadd($key, $value);
    }

    //返回集合所有元素
    public static function smemebers($key)
    {
        return \Yii::$app->redis->smembers($key);
    }


    public static function sismember($key, $val)
    {
        return \Yii::$app->redis->sismember($key, $val) ? true : false;
    }

    /**
     * @param $key
     * @param $val
     * @return mixed
     * 移除集合中某个元素
     */
    public static function srem($key, $val)
    {
        return \Yii::$app->redis->srem($key, $val);
    }


    public static function del($key)
    {
        return \Yii::$app->redis->del($key);
    }

    public static function incrby($key, $number)
    {
        return \Yii::$app->redis->incrby($key, intval($number));
    }

    public static function incr($key)
    {
        return \Yii::$app->redis->incr($key);
    }

    public static function decrby($key, $number)
    {
        return \Yii::$app->redis->decrby($key, intval($number));
    }

    public static function decr($key)
    {
        return \Yii::$app->redis->decr($key);
    }

    public static function setnx($key, $v, $expire = 0)
    {
        if ($expire > 0) {
            return (bool)\Yii::$app->redis->executeCommand('SET', [$key, $v, 'NX', 'EX', $expire]);
        }
        return (bool)\Yii::$app->redis->setnx($key, $v);
    }
}
