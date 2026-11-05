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
 * Author:ganxh
 * User: cp
 * Date: 2021/9/28
 * Time: 17:34
 */

namespace app\helpers;


class BankHelper
{

    public static function getBankList()
    {
        return [
            ['name' => "工商银行", 'code' => 1002],
            ['name' => "农业银行", 'code' => 1005],
            ['name' => "建设银行", 'code' => 1003],
            ['name' => "中国银行", 'code' => 1026],
            ['name' => "交通银行", 'code' => 1020],
            ['name' => "招商银行", 'code' => 1001],
            ['name' => "邮储银行", 'code' => 1066],
            ['name' => "民生银行", 'code' => 1006],
            ['name' => "平安银行", 'code' => 1010],
            ['name' => "中信银行", 'code' => 1021],
            ['name' => "浦发银行", 'code' => 1004],
            ['name' => "兴业银行", 'code' => 1009],
            ['name' => "光大银行", 'code' => 1022],
            ['name' => "广发银行", 'code' => 1027],
            ['name' => "华夏银行", 'code' => 1025],
            ['name' => "宁波银行", 'code' => 1056],
            ['name' => "北京银行", 'code' => 4836],
            ['name' => "上海银行", 'code' => 1024],
            ['name' => "南京银行", 'code' => 1054],
            ['name' => "长子县融汇村镇银行", 'code' => 4755],
            ['name' => "长沙银行", 'code' => 4216],
            ['name' => "浙江泰隆商业银行", 'code' => 4051],
            ['name' => "中原银行", 'code' => 4753],
            ['name' => "企业银行（中国）", 'code' => 4761],
            ['name' => "顺德农商银行", 'code' => 4036],
            ['name' => "衡水银行", 'code' => 4752],
            ['name' => "长治银行", 'code' => 4756],
            ['name' => "大同银行", 'code' => 4767],
            ['name' => "河南省农村信用社", 'code' => 4115],
            ['name' => "宁夏黄河农村商业银行", 'code' => 4150],
            ['name' => "山西省农村信用社", 'code' => 4156],
            ['name' => "安徽省农村信用社", 'code' => 4166],
            ['name' => "甘肃省农村信用社 ", 'code' => 4157],
            ['name' => "天津农村商业银行", 'code' => 4153],
            ['name' => "广西壮族自治区农村信用社", 'code' => 4113],
            ['name' => "陕西省农村信用社", 'code' => 4108],
            ['name' => "深圳农村商业银行", 'code' => 4076],
            ['name' => "宁波鄞州农村商业银行", 'code' => 4052],
            ['name' => "浙江省农村信用社联合社", 'code' => 4764],
            ['name' => "江苏省农村信用社联合社", 'code' => 4217],
            ['name' => "江苏紫金农村商业银行股份有限公司", 'code' => 4072],
            ['name' => "北京中关村银行股份有限公司", 'code' => 4769],
            ['name' => "星展银行（中国）有限公司", 'code' => 4778],
            ['name' => "枣庄银行股份有限公司", 'code' => 4766],
            ['name' => "海口联合农村商业银行股份有限公司", 'code' => 4758],
            ['name' => "南洋商业银行（中国）有限公司", 'code' => 4763],
        ];
    }
}
