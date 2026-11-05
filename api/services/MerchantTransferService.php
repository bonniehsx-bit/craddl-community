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
 * Name:
 * Author: blank
 * Profile: shop
 * Date: 2025/2/18
 * Time: 17:51
 */


namespace app\services;


class MerchantTransferService
{
    public static function sceneConfig()
    {
        $scene = [
            [
                'name' => '现金营销',
                'info_type' => [
                    ['info_type' => '活动名称', 'info_content' => '平台推广'],
                    ['info_type' => '奖励说明', 'info_content' => '推广奖励'],
                ],
                'user_perception' => ['活动奖励', '现金奖励']
            ],
            [
                'name' => '企业赔付',
                'info_type' => [
                    ['info_type' => '赔付原因', 'info_content' => '平台退款'],
                ],
                'user_perception' => ['退款', '商家赔付']
            ],
            [
                'name' => '奖励报酬',
                'info_type' => [
                    ['info_type' => '岗位类型', 'info_content' => '会员'],
                    ['info_type' => '报酬说明', 'info_content' => '平台会员补贴'],
                ],
                'user_perception' => ['劳务报酬', '报销款', '企业补贴', '开工利是']
            ],
            [
                'name' => '采购货款',
                'info_type' => [['info_type' => '采购商品名称', 'info_content' => '商城提现']],
                'user_perception' => ['货款']
            ],
            [
                'name' => '二手回收',
                'info_type' => [['info_type' => '回收商品名称', 'info_content' => '商城提现']],
                'user_perception' => ['二手回收货款']
            ],
            [
                'name' => '公益补助',
                'info_type' => [
                    ['info_type' => '公益活动名称', 'info_content' => '无'],
                    ['info_type' => '公益活动备案编号', 'info_content' => '无'],
                ],
                'user_perception' => ['公益补助金']
            ],
            [
                'name' => '行政补贴',
                'info_type' => [['info_type' => '补贴类型', 'info_content' => '平台收入补贴']],
                'user_perception' => ['行政补贴', '行政奖励']
            ],
            [
                'name' => '保险理赔',
                'info_type' => [
                    ['info_type' => '保险产品备案编号', 'info_content' => '无'],
                    ['info_type' => '保险名称', 'info_content' => '无'],
                    ['info_type' => '保险操作单号', 'info_content' => '无'],
                ],
                'user_perception' => ['保险理赔款']
            ],

        ];
        return $scene;
    }
}