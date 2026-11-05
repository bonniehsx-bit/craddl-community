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

class DefaultDataHelper
{

    public static function userCenter()
    {
        $default['tabbar'] = [
            'color' => '#E41F19',
            'list' => [
                [
                    'name' => '首页',
                    'url' => '/pages/index/index',
                    'icon' => ImageHelper::getUrl('/assets/default/tabbar/index.png'),
                    'active_icon' => ImageHelper::getUrl('/assets/default/tabbar/index-active.png')
                ],
                [
                    'name' => '分类',
                    'url' => '/others/new-cat-goods/new-cat-goods',
                    'icon' => ImageHelper::getUrl('/assets/default/tabbar/cat.png'),
                    'active_icon' => ImageHelper::getUrl('/assets/default/tabbar/cat-active.png')
                ],
                [
                    'name' => '购物车',
                    'url' => '/others/cart/cart',
                    'icon' => ImageHelper::getUrl('/assets/default/tabbar/cart.png'),
                    'active_icon' => ImageHelper::getUrl('/assets/default/tabbar/cart-active.png')
                ],
                [
                    'name' => '我的',
                    'url' => '/others/user/user',
                    'icon' => ImageHelper::getUrl('/assets/default/tabbar/my.png'),
                    'active_icon' => ImageHelper::getUrl('/assets/default/tabbar/my-active.png')
                ],
            ]
        ];
        $default['header'] = [
            'user_bg' => ImageHelper::getUrl('/assets/default/user/user_bg.png'),
            'avatar' => ImageHelper::getUrl('/assets/default/user/avatar.png'),
            'goods' => [
                'is_show' => 1,
                'name' => '商品收藏'
            ],
            'shop' => [
                'is_show' => 1,
                'name' => '店铺收藏'
            ],
            'order' => [
                'is_show' => 1,
                'name' => '订单'
            ],
            'footmark' => [
                'is_show' => 1,
                'name' => '足迹'
            ],
        ];
        $default['order'] = [
            'title' => '我的订单',
            'sub_title' => '全部订单',
            'status_0' => [
                'name' => '待付款',
                'is_show' => 1,
                'url' => '/others/order/order?status=1',
                'icon' => ImageHelper::getUrl('/assets/default/user/order0.png')
            ],
            'status_1' => [
                'name' => '待发货',
                'is_show' => 1,
                'url' => '/others/order/order?status=2',
                'icon' => ImageHelper::getUrl('/assets/default/user/order1.png')
            ],
            'status_2' => [
                'name' => '待收货',
                'is_show' => 1,
                'url' => '/others/order/order?status=3',
                'icon' => ImageHelper::getUrl('/assets/default/user/order2.png')
            ],
            'status_3' => [
                'name' => '待评价',
                'is_show' => 1,
                'url' => '/others/order/order?status=4',
                'icon' => ImageHelper::getUrl('/assets/default/user/order3.png')
            ],
            'status_4' => [
                'name' => '售后',
                'is_show' => 1,
                'url' => '/others/order/order?status=5',
                'icon' => ImageHelper::getUrl('/assets/default/user/order4.png')
            ]
        ];

        $default['menus'] = [
            'name' => '常用工具',
            'type' => 0,
            'is_show' => 0,
            'list' => [
                [
                    'name' => '订单管理',
                    'url' => '/others/order/order?status=0',
                    'icon' => ImageHelper::getUrl('/assets/default/user/order0.png')
                ],
            ]
        ];
        $default['menus2'] = [
            'name' => '常用工具',
            'type' => 0,
            'is_show' => 0,
            'list' => [
                [
                    'name' => '待付款',
                    'url' => '/others/order/order?status=0',
                    'icon' => ImageHelper::getUrl('/assets/default/user/order0.png')
                ],
            ]
        ];
        $default['finance'] = [
            'name' => '我的资产',
            'type' => 0,
            'is_show' => 1,
            'integral' => [
                'name' => '积分',
                'is_show' => 1,
            ],
            'balance' => [
                'name' => '余额',
                'is_show' => 1,
            ],
            'price' => [
                'name' => '奖励',
                'is_show' => 0,
            ],
            'coupon' => [
                'name' => '优惠券',
                'is_show' => 0,
            ],

        ];
        return $default;
    }

    /**
     * 设置页(others/setting)的菜单默认清单。
     *
     * ── 为什么不是一个纯自由列表 ──
     * 这页的菜单项有三种点击行为,只有一种能配成链接:
     *   link   跳转页面           —— 可以配
     *   modal  打开**本页**的弹窗 —— 改手机号/登录密码/支付密码,组件挂在本页,只能按 key 调
     *   method 执行本页的方法     —— 清缓存/隐私政策/用户协议/微信绑定,同上
     * 所以内置项是**固定 key**,运营只能改名/换图标/排序/显隐,不能删、不能改行为。
     * 想加自己的入口就加 type=link 的自定义项。
     * (与 user_center_setting 里"订单状态固定 key、常用工具自由列表"是同一套路子)
     *
     * ── 🔒 locked 的三项 ──
     * privacy / protocol / account_deletion 连显隐开关都锁死:
     * 隐私政策、用户协议、注销账号是上架硬性要求,删了或藏了会被拒审,
     * 而运营在后台关掉它的那一刻不会有任何报错 —— 要几个月后提审被拒才发现。
     *
     * ── ⚠ block_list 为什么可关但默认开 ──
     * 它跳的是 massage 插件的页面,没装该插件的商城点进去是坏页 —— 所以要**可关**。
     * 但默认必须是**开**:改造前这一项是写死显示的,默认关会让装了屏蔽功能的存量商城
     * 升级后合规入口静默消失,那比坏页严重得多(Apple Guideline 1.2 会直接拒审)。
     * 也正因为它可关,不能进 locked 名单。后台对这项单独给提示。
     *
     * 字段说明:
     *   type    builtin(内置,按 key 分派) | link(自定义,只能跳转)
     *   key     内置项的身份,前端据此找点击函数与动态副文案,**不可变**
     *   icon    tui-icon 图标名(字体图标,跟主题色)
     *   pic     图片图标 URL。填了优先用图片,没填才用 icon
     *   value   右侧静态副文案。内置项若有动态副文案(手机号掩码/版本号),前端会覆盖它
     *   locked  1=显隐开关锁死(合规项)
     */
    public static function settingPage()
    {
        return [
            'groups' => [
                [
                    'key' => 'security', 'name' => '账户安全', 'is_show' => 1,
                    'list' => [
                        ['type' => 'builtin', 'key' => 'mobile',    'name' => '手机号码', 'icon' => 'voipphone',   'pic' => '', 'value' => '',         'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'login_pwd', 'name' => '登录密码', 'icon' => 'setup',       'pic' => '', 'value' => '设置',     'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'pay_pwd',   'name' => '支付密码', 'icon' => 'attestation', 'pic' => '', 'value' => '设置',     'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'address',   'name' => '地址管理', 'icon' => 'location',    'pic' => '', 'value' => '管理',     'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'wx_notify', 'name' => '微信通知', 'icon' => 'wechat',      'pic' => '', 'value' => '',         'url' => '', 'is_show' => 1, 'locked' => 0],
                    ],
                ],
                [
                    'key' => 'service', 'name' => '服务与支持', 'is_show' => 1,
                    'list' => [
                        ['type' => 'builtin', 'key' => 'about',    'name' => '关于我们', 'icon' => 'message-fill', 'pic' => '', 'value' => '',             'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'feedback', 'name' => '意见反馈', 'icon' => 'kefu',         'pic' => '', 'value' => '期待您的声音', 'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'privacy',  'name' => '隐私政策', 'icon' => 'service',      'pic' => '', 'value' => '',             'url' => '', 'is_show' => 1, 'locked' => 1],
                        ['type' => 'builtin', 'key' => 'protocol', 'name' => '用户协议', 'icon' => 'order',        'pic' => '', 'value' => '',             'url' => '', 'is_show' => 1, 'locked' => 1],
                        ['type' => 'builtin', 'key' => 'upgrade',  'name' => '检查更新', 'icon' => 'download',     'pic' => '', 'value' => '',             'url' => '', 'is_show' => 1, 'locked' => 0],
                    ],
                ],
                [
                    'key' => 'system', 'name' => '系统设置', 'is_show' => 1,
                    'list' => [
                        ['type' => 'builtin', 'key' => 'clear_cache',      'name' => '清理缓存', 'icon' => 'delete',  'pic' => '', 'value' => '需重新登录', 'url' => '', 'is_show' => 1, 'locked' => 0],
                        ['type' => 'builtin', 'key' => 'account_deletion', 'name' => '注销账号', 'icon' => 'close',   'pic' => '', 'value' => '账户注销',   'url' => '', 'is_show' => 1, 'locked' => 1],
                    ],
                ],
            ],
        ];
    }
}
