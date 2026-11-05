<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:26
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

namespace app\events;


use yii\base\BaseObject;
use yii\base\Event;

class FastpayEvent extends Event
{
    const EVENT_AGENT_PAY = 'fast_agent_pay'; //代付
    const EVENT_LG_GRANT  = 'fast_lg_grant';  //灵工下发

    /** @var string 通知报文的 data 段(JSON),业务字段都在这里 */
    public $body;

    /**
     * @var string 通知报文**全量**(JSON,含 sign / signType / timeStamp …)。
     * 验签必须拿整份报文算,只有 data 段签不出来 —— 监听器据此校验来源真伪。
     * 单独给一份而不是让监听器去读全局 request:监听器因此自包含、可单测,
     * 也不假设自己一定跑在 web 请求里。
     */
    public $raw;

    /**
     * @var bool 监听器置 true = 本次**没能处理成功、但属于可恢复的失败**(验签时公钥没配、
     * 回流抛异常等),请求核心给网关回 FAIL 以触发重推。
     *
     * 为什么需要它:核心原先无论如何都回 SUCCESS,网关据此认为已送达、不再重推。
     * 那么只要我们这边一时处理不了(配置没配好/数据库抖动),这笔打款结果就**永久丢失** ——
     * 与"根本没有监听器"是同一个后果。
     *
     * 反过来,确定性的、重推也没用的情况(伪造签名、单号不属于本系统)保持 false:
     * 让网关别再推了。
     */
    public $retry = false;
}
