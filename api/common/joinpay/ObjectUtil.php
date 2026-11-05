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

namespace app\common\joinpay;

class ObjectUtil
{
    /**
     * 把json格式的字符串参数值赋值到对象属性中去
     * @param object $obj
     * @param string $paramJson
     * @param int $jsonDept
     */
    public static function fillProperties(object &$obj, string $paramJson, $jsonDept = 2){
        $arr = json_decode($paramJson, true, $jsonDept);

        if(!$arr || !is_array($arr)) {
            return;
        }
        $isMagicSetExist = method_exists($obj, "__set");//魔术方法是否存在
        foreach($arr as $key => $value) {
            if(! property_exists($obj, $key)){
                continue;
            }
            if($isMagicSetExist){
                $obj->$key = $value;
            }else{
                $method = "set" . ucfirst($key);

                if(! method_exists($obj, $method)){
                    $method = "set";
                    $fArr = explode("_", $key);
                    foreach($fArr as $idx => $fName){
                        $method .= ucfirst($fName);
                    }
                }
                $obj->$method($value);
            }
        }
    }
}