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

use yii\helpers\Json;

class JsonHelper
{
    /**
     * [健壮版] 递归解码一个可能被多次编码的JSON字符串
     * @param mixed $data
     * @return mixed
     */
    public static function decode($data)
    {
        if (!is_string($data)) {
            return $data; // 如果不是字符串，直接返回
        }

        // 尝试解码
        try {
            $decoded = Json::decode($data);
        } catch (\Exception $e) {
            // 如果解码失败（比如不是合法的JSON），原样返回字符串
            return $data;
        }

        // 如果解码后仍然是字符串（说明发生了双重编码），则继续递归尝试解码
        if (is_string($decoded)) {
            return self::decode($decoded);
        }

        return $decoded;
    }

    /**
     * [健壮版] 编码数据为JSON字符串
     * 默认不转义中文和斜杠，使数据库存储的内容更可读
     *
     * @param mixed $data 需要编码的数据
     * @param int $options 编码选项
     * @return string
     */
    public static function encode($data, $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    {
        // 如果数据已经是字符串，并且看起来像 JSON，为了防止双重编码，
        // 有些业务场景下可以选择直接返回，但在通用 Helper 中，
        // 我们通常假设调用者传进来就是想转成 JSON 格式的字符串。

        // 使用 Yii 自带的 Json::encode，由于它内部会处理对象转数组等逻辑
        // 这里主要对其默认参数进行了优化
        return Json::encode($data, $options);
    }
}