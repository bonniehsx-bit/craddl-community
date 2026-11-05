<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:29
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

use yii\console\Request;

class ConsoleRequest extends Request
{
    public $enableCsrfCookie = false;
    public function getUserIp()
    {
        return '0.0.0.0';
    }



    public function getCsrfToken()
    {
        return null;
    }

    /**
     * 命令行没有 GET/POST 参数,一律返回调用方给的默认值。
     *
     * 本类的职责就是把 web Request 的方法在 console 下补齐(上下的 getUserIp /
     * getCsrfToken / getIsAjax / getMethod 都是为此),但一直漏了这两个 ——
     * 于是 BaseActiveQuery::page() 里那句 `Yii::$app->request->get('page', 1)`
     * 在命令行下直接 "Calling unknown method",**任何在 cron / job / 控制台里
     * 调用带分页的列表 Form 都会 fatal**。(BaseModel::init() 里同样的调用之所以
     * 没事,是因为它包在 `instanceof yii\web\Application` 判断里,console 下不执行。)
     */
    public function get($name = null, $defaultValue = null)
    {
        return $defaultValue;
    }

    public function post($name = null, $defaultValue = null)
    {
        return $defaultValue;
    }
    public static function getAbsoluteUrl()
    {

        return false;
    }

    public static function getIsAjax()
    {

        return false;
    }

    public static function getMethod()
    {

        return false;
    }
}
