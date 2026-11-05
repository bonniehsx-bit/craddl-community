<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-03
 * Time: 11:51
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

class ExpressHelper
{

    public static function eorderStyle()
    {

        //电子面单对应的模板
        return [
            'ANE' => [
                [
                    'label' => '二联 180 (宽100mm 高180mm 切点110/70)',
                    'value' => '',
                ],
            ],
            'ANEKY' => [
                [
                    'label' => '二联 180 (宽100mm 高180mm 切点110/70)',
                    'value' => '',
                ],
            ],
            'CND' => [
                [
                    'label' => '二联 180 (宽 100mm)',
                    'value' => '',
                ],
            ],
            'DBL' => [
                [
                    'label' => '二联 177 (宽 100mm 高 177mm 切点 107/70)',
                    'value' => '',
                ],
                [
                    'label' => '二联 177 新 (宽 100mm 高 177mm 切点 107/70)',
                    'value' => '18001',
                ],
                [
                    'label' => '三联 177 新 (宽 100mm 高 177mm 切点 107/30/40)',
                    'value' => '18002',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'DBLKY' => [
                [
                    'label' => '三联 180 (宽 100mm 高180mm 切点 110/30/40)',
                    'value' => '',
                ],
            ],
            'DNWL' => [
                [
                    'label' => '一联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
            ],
            'EST365' => [
                [
                    'label' => '一联 120 (宽 100mm 高 120mm)',
                    'value' => '',
                ],
            ],
            'EMS' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 (宽 100mm 高180mm 切点 110/70)',
                    'value' => '180',
                ],
            ],
            'HTKY' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'HTKYKY' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'CNEX' => [
                [
                    'label' => '一联 100 (宽 90mm 高 100mm)',
                    'value' => '',
                ],
            ],
            'JD' => [
                [
                    'label' => '二联 110 (宽 100mm 高 110mm 切点 60/50)',
                    'value' => '',
                ],
                [
                    'label' => '二联 110 新 (宽 100mm 高 110mm 切点 60/50)',
                    'value' => '110',
                ],
            ],
            'JDKY' => [
                [
                    'label' => '二联 110 (宽 100mm 高 110mm 切点 60/50)',
                    'value' => '',
                ],
            ],
            'JTSD' => [
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '139',
                ],
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'KYSY' => [
                [
                    'label' => '二联 137 (宽 100mm 高 137mm 切点 101/36)',
                    'value' => '',
                ],
                [
                    'label' => '三联 210 (宽 100mm 高 210mm 切点 90/60/60)',
                    'value' => '210',
                ],
            ],
            'LB' => [
                [
                    'label' => '三联 104 (宽 75mm 高 104mm)',
                    'value' => '',
                ],
            ],
            'LHT' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
            ],
            'PJ' => [
                [
                    'label' => '一联 120 (宽 80mm 高 120mm)',
                    'value' => '',
                ],
            ],
            'UAPEX' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
            ],
            'SF' => [
                [
                    'label' => '二联 150 新 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '15001',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '三联 210 新 (宽 100mm 高 210mm 切点 90/60/60)',
                    'value' => '21001',
                ],
            ],
            'STO' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '150',
                ],
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '三联 180 新 (宽 100mm 高 180mm 切点 110/30/40)',
                    'value' => '18003',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'SURE' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
                [
                    'label' => '二联 150 新 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '150',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
            ],
            'SX' => [
                [
                    'label' => '一联 105 (宽 75mm 高 105mm)',
                    'value' => '',
                ],
            ],
            'SNWL' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'TLWL' => [
                [
                    'label' => '一联 70 (宽 100mm 高 70mm)',
                    'value' => '',
                ],
            ],
            'HOAU' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'HHTT' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'XFEX' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
            ],
            'YD' => [
                [
                    'label' => '二联 203 (宽 100mm 高 203mm 切点 152/51)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'YDKY' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'YTO' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
                [
                    'label' => '三联 180 (宽 100mm 高 180mm 切点 110/30/40)',
                    'value' => '180',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '18001',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'YZBK' => [
                [
                    'label' => '二联 150 (宽 100mm 高 150mm 切点 90/60)',
                    'value' => '',
                ],
            ],
            'YZPY' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
            ],
            'UC' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'YCWL' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'YMDD' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
            'ZJS' => [
                [
                    'label' => '二联 120 (宽 100mm 高 116mm 切点 98/18)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 (宽 100m 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '二联 120 新 (宽 100mm 高 116mm 切点 98/18)',
                    'value' => '120',
                ],
            ],
            'ZTO' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
                [
                    'label' => '二联 180 新 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '180',
                ],
                [
                    'label' => '一联 130 (宽 76mm 高 130mm)',
                    'value' => '130',
                ],
            ],
            'ZTOKY' => [
                [
                    'label' => '二联 180 (宽 100mm 高 180mm 切点 110/70)',
                    'value' => '',
                ],
            ],
        ];
    }

    public static function express()
    {
        $list = \app\models\Express::find()
            ->where(['is_delete' => 0])
            ->orderBy('sort ASC, id DESC')
            ->asArray()
            ->all();

        // 为兼容原有数据格式，如果数据中没有字符串格式的 r_datas，确保填充默认值
        foreach ($list as &$item) {
            $item['id'] = (string)$item['id'];
            if (empty($item['r_datas'])) {
                $item['r_datas'] = '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]';
            }
        }
        return $list;
    }
}
