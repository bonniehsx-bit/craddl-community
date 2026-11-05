<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-04
 * Time: 20:25
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

namespace app\forms\api\manage;

use app\core\ApiCode;
use app\helpers\ImageHelper;
use app\helpers\OptionHelper;
use app\helpers\SerializeHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\User;
use app\services\UserService;
use Yii;

class ManageDataForm extends BaseModel
{

    public function getData()
    {
        /**
         * @var User $user ;
         */
        $user = UserHelper::getUser();
        $info = [
            "money" => $user->money,
            'price' => $user->price,
            'cashing' => 0,
            'cash' => 0,
            'integral' => $user->integral,
            'total_price' => $user->total_price,
            'avatar_url' => $user->avatar_url,
            'nickname' => $user->nickname
        ];
        $parent = User::getBaseInfo($user->parent_id);
        if ($parent) {
            $info['parent_name'] = $parent->nickname;
        } else {
            $info['parent_name'] = '平台';
        }

        // 提现中 / 已提现
        //
        // 走 WithdrawStatEvent 问、提现插件答 —— 核心不认识插件的表/模型。
        // 口径:提现中 = 待审核+待打款+打款中;已提现 = 已到账。
        // (老代码把 status=1 当"已提现",但 1 是"审核通过待打款",钱还没出去)
        $e = new \app\events\WithdrawStatEvent(['mallId' => $this->mall_id, 'userId' => $user->id]);
        Yii::$app->trigger(\app\events\WithdrawStatEvent::NAME, $e);
        $info['cashing'] = (float) ($e->pendingAmount ?? 0);
        $info['cash']    = (float) ($e->completedAmount ?? 0);

        $setting = OptionHelper::get('user_manage_setting', \Yii::$app->mall->id);
        if (!$setting) {
            $setting = $this->getPageDefault();
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['setting' => $setting, 'info' => $info]);
    }

    private function getPageDefault()
    {

        $default['header'] = [
            'parent_label' => '推荐人',
            'user_bg' => ImageHelper::getUrl('/statics/default/user/user_bg.png'),
            'avatar' => ImageHelper::getUrl('/statics/default/user/avatar.png'),
            'qrcode' => ImageHelper::getUrl('/statics/default/images/qrcode.png'),
            'balance' => [
                'name' => '余额'
            ],
            'price' => [
                'name' => '奖励'
            ],
            'integral' => [
                'name' => '积分'
            ],
        ];


        $default['menus'] = [
            'name' => '常用工具',
            'type' => 0,
            'list' => [
                [
                    'name' => '待付款',
                    'url' => '/others/order/order?status=0',
                    'icon' => ImageHelper::getUrl('/statics/default/user/order0.png')
                ],
                [
                    'name' => '待发货',
                    'url' => '/others/order/order?status=1',
                    'icon' => ImageHelper::getUrl('/statics/default/user/order1.png')
                ],
                [
                    'name' => '待收货',
                    'url' => '/others/order/order?status=2',
                    'icon' => ImageHelper::getUrl('/statics/default/user/order2.png')
                ],
                [
                    'name' => '待评价',
                    'url' => '/others/order/order?status=3',
                    'icon' => ImageHelper::getUrl('/statics/default/user/order3.png')
                ],
                [
                    'name' => '退款/售后',
                    'url' => '/others/order/order?status=4',
                    'icon' => ImageHelper::getUrl('/statics/default/user/order4.png')
                ],
            ]
        ];
        $default['finance'] = [
            'name' => '我的资产',
            'type' => 0,
            'total' => [
                'name' => '累计奖励',

            ],
            'price' => [
                'name' => '奖励',

            ],
            'cashing' => [

                'name' => '提现中',

            ],
            'cash' => [
                'name' => '已提现',

            ],

        ];

        return $default;
    }
}
