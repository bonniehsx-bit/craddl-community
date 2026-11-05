<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\helpers\DefaultDataHelper;
use app\helpers\Helper;
use app\helpers\ImageHelper;
use app\helpers\OptionHelper;
use app\helpers\UserHelper;
use app\helpers\VersionHelper;
use app\models\BaseModel;
use app\models\GoodsFavorite;
use app\models\GoodsFootmark;
use app\models\Level;
use app\models\MallPlugin;
use app\models\Order;
use app\models\User;
use app\models\UserCoupon;
use app\services\order\OrderListConfig;
use app\services\order\OrderTypeRegistry;
use yii\db\Expression;
use yii\db\Query;
use Yii;

class IndexDataForm extends BaseModel
{
    /**
     * [V3 修复版] 获取用户个人中心核心数据
     * 修复了 "Unknown column '1'" 错误
     * 策略：基础数据用模型，统计数据独立查询，避免复杂的子查询拼接问题
     */
    public function getData()
    {
        /** @var User $user */
        // 1. 获取用户基础模型 (走缓存优化的 UserHelper)
        $user = UserHelper::getUser();

        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['user' => $this->getDefaultUserData()]);
        }

        // 2. [独立查询] 各项统计数据
        // 这里的查询非常快，因为都走了索引覆盖 (Index Scan)

        // 优惠券数量
        $couponCount = (int)UserCoupon::find()
            ->where(['user_id' => $user->id, 'is_delete' => 0, 'status' => 0])
            ->count();

        // 收藏数量
        $favCount = (int)GoodsFavorite::find()
            ->where(['user_id' => $user->id, 'is_delete' => 0])
            ->count();

        // 足迹数量
        $footCount = (int)GoodsFootmark::find()
            ->where(['user_id' => $user->id, 'is_delete' => 0])
            ->count();

        // 订单数量 (已完成)
        $orderCount = (int)Order::find()
            ->where(['user_id' => $user->id, 'is_delete' => 0, 'status' => 40])
            ->count();

        // 3. 提现金额统计
        //
        // 走 WithdrawStatEvent 问、提现插件答 —— 核心不认识插件的表/模型。
        // ⚠ 这里原先是 (new Query())->from('{{%cash}}') **裸表名**直查老表,
        //   表删掉后本接口直接 500,而按类名做的残留扫描根本扫不到它。
        //   改成事件后核心里既无插件类名也无插件表名,插件卸载自动回落 0。
        // 口径:提现中 = 待审核+待打款+打款中;已提现 = 已到账(已驳回/已退回不计)。
        $statEvent = new \app\events\WithdrawStatEvent(['mallId' => $this->mall_id, 'userId' => $user->id]);
        Yii::$app->trigger(\app\events\WithdrawStatEvent::NAME, $statEvent);
        $cashStats = [
            'cashing' => (float) ($statEvent->pendingAmount ?? 0),
            'cashed'  => (float) ($statEvent->completedAmount ?? 0),
        ];



        // 5. [关联查询] 等级和邀请人信息
        // A. 获取等级名称
        $levelName = '普通用户';
        $levelIcon = '';
        if ($user->level > -1) {
            $levelInfo = Level::find()
                ->select(['name', 'pic_url'])
                ->where(['level' => $user->level, 'mall_id' => $user->mall_id, 'is_delete' => 0, 'enabled' => 1])
                ->asArray()
                ->one();
            if ($levelInfo) {
                $levelName = $levelInfo['name'];
                $levelIcon = $levelInfo['pic_url'] ?? '';
            }
        }

        // B. 获取邀请人
        // parent_id: 0=未绑 / -1=平台直属 / -2=收银台游客,都落到「平台」;>0 才是真实上级
        $inviterInfo = ['nickname' => '平台', 'id' => 0];
        if ($user->parent_id > 0) {
            $parent = User::find()
                // is_delete / status 必须一并取出,否则 displayNickname 判不出已注销
                ->select(['nickname', 'id', 'is_delete', 'status'])
                ->where(['id' => $user->parent_id])
                ->asArray()
                ->one();
            if ($parent) {
                // 上级注销后不再暴露其昵称(墓碑节点:关系链保留,个人信息不外显)
                $inviterInfo = [
                    'id'       => (int)$parent['id'],
                    'nickname' => UserHelper::displayNickname($parent),
                ];
            }
        }

        // 6. 组装最终数据
        $user_info = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            // 基础资产 (实时数据，来自 UserHelper::getUser 的模型对象)
            'integral' => $user->integral,
            'price' => $user->price,
            'money' => $user->money,
            'mobile' => $user->mobile,
            'mask_mobile' => $user->mobile ? Helper::maskPhoneNumber($user->mobile) : '暂无手机号',
            // 完善资料字段:必须下发,否则「编辑资料」弹窗回显不出用户此前填的值
            'gender' => (int)$user->gender,
            'birthday' => (string)($user->birthday ?: ''),
            // 身份信息
            'level' => $user->level,
            'level_name' => $levelName,
            'level_icon' => $levelIcon,
            'inviter' => $inviterInfo,
            // 统计数据
            'coupon' => $couponCount,
            'goods_favorite' => $favCount,
            'goods_footmark' => $footCount,
            'order_num' => $orderCount,
            // 提现数据
            'cashing' => (float)($cashStats['cashing'] ?? 0),
            'cashed' => (float)($cashStats['cashed'] ?? 0),

        ];

        // 7. 附加配置开关
        $setting = OptionHelper::get('cash_setting', \Yii::$app->mall->id);
        $user_info['is_integral_cash'] = ($setting['integral']['is_cash'] ?? 0) == 1;
        $user_info['is_price_cash'] = ($setting['price']['is_cash'] ?? 0) == 1;
        $user_info['is_balance_cash'] = ($setting['balance']['is_cash'] ?? 0) == 1;

        // 是否设置过支付密码 (不回传 pay_pwd 哈希值本身, 只给一个 bool 标记)
        // 收银台 pay.vue 用它决定"直接弹密码框"还是"先跳去设置页"
        $user_info['has_pay_pwd'] = \app\helpers\UserHelper::hasPayPwd() ? 1 : 0;
        // 是否设置过登录密码 —— 微信/授权注册的用户可能为空
        // 用于设置页登录密码弹窗切换"设置/修改"文案
        $user_info['has_password'] = empty($user->password) ? 0 : 1;
        // 微信公众号 openid —— 前端用于判断是否已绑定（setting.vue 微信通知入口）
        $user_info['wechat_openid'] = $user->wechat_openid ?: '';

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['user' => $user_info]);
    }

    /**
     * 获取默认（未登录）用户信息
     */
    private function getDefaultUserData()
    {
        return [
            'nickname' => '未登录',
            'avatar_url' => ImageHelper::getUrl('/statics/default/user/avatar.png'),
            'integral' => 0,
            'coupon' => 0,
            'price' => 0.00,
            'money' => 0.00,
            'green_integral' => '0.00',
            'goods_favorite' => 0,
            'mch_favorite' => 0,
            'order_num' => 0,
            'goods_footmark' => 0,
            'level_name' => '普通用户',
            'inviter' => ['nickname' => '平台', 'id' => 0],
            'cashing' => 0.00,
            'cashed' => 0.00,
            'has_pay_pwd' => 0,
            'has_password' => 0,
        ];
    }

    /**
     * 获取用户中心页面配置数据
     */
    public function getPageData()
    {
        // 1. 获取装修配置
        $isChecking = VersionHelper::checkIsAuditMode($this->mall_id);

        if ($isChecking) {
            // 审核版用默认干净页(隐藏敏感入口),但客服并非敏感入口、审核方通常也要求 App 有联系客服途径,
            // 故把商家真实配置里的客服合并回来,避免审核版丢失客服(默认数据不含 customer_service)。
            $userPageData = DefaultDataHelper::userCenter();
            $realSetting = OptionHelper::get('user_center_setting', $this->mall_id);
            if (!empty($realSetting['customer_service'])) {
                $userPageData['customer_service'] = $realSetting['customer_service'];
            }
        } else {
            $userPageData = OptionHelper::get('user_center_setting', $this->mall_id);
            if (!$userPageData) {
                $userPageData = DefaultDataHelper::userCenter();
            }
        }

        // 1.5 订单入口跟随「订单列表配置」的 tab 显隐(详见方法注释)。
        //     放在登录判断之前 —— 未登录时同样要隐藏,否则游客看到的入口比登录后还多。
        $userPageData = $this->applyOrderTabVisibility($userPageData);

        // 2. 填充动态数据 (订单状态)
        if (!\Yii::$app->user->isGuest) {
            $userId = UserHelper::getMyId();

            // 聚合查询订单各状态数量
            $countQuery = (new Query())
                ->select([
                    'unpaid_count' => new Expression('SUM(CASE WHEN `status` = :unpaid THEN 1 ELSE 0 END)', [':unpaid' => Order::STATUS_UNPAID]),
                    'to_be_shipped_count' => new Expression('SUM(CASE WHEN `status` = :tbs THEN 1 ELSE 0 END)', [':tbs' => Order::STATUS_TO_BE_SHIPPED]),
                    'shipped_count' => new Expression('SUM(CASE WHEN `status` = :shipped THEN 1 ELSE 0 END)', [':shipped' => Order::STATUS_SHIPPED]),
                ])
                ->from(['o' => Order::tableName()])
                ->where(['o.user_id' => $userId, 'o.is_delete' => 0]);

            // 角标只数「订单列表里真能看到」的那些单 ——
            // 与 OrderListForm::getList() 第 2.5 步是同一个条件,必须保持一致。
            //
            // 后台把列表限定成某几个订单类型(included_types)之后,角标若还数全部订单,
            // 用户会看到「待发货 3」、点进去列表里只有 1 条 —— 数字对不上,看起来就是坏的。
            // included_types 为空 = 不限类型(老商城/未配置,向后兼容)。
            $listCfg = OrderListConfig::get((int)$this->mall_id);
            if (!empty($listCfg['included_types'])) {
                $plugins = OrderTypeRegistry::pluginsForKeys($listCfg['included_types']);
                if (!empty($plugins)) {
                    $countQuery->andWhere(['o.plugin' => $plugins]);
                }
            }

            $counts = $countQuery->one();

            // 这里的查询虽然和 getData 有轻微重叠，但为了 page_data 结构完整性，保留查询
            $footmarkCount = GoodsFootmark::find()->where(['user_id' => $userId, 'is_delete' => 0])->count();
            $favoriteCount = GoodsFavorite::find()->where(['user_id' => $userId, 'is_delete' => 0])->count();

            // 填充到 page_data 结构中
            if (isset($userPageData['order'])) {
                $order = $userPageData['order'];
                $order['status_0']['num'] = (int)($counts['unpaid_count'] ?? 0);
                $order['status_1']['num'] = (int)($counts['to_be_shipped_count'] ?? 0);
                $order['status_2']['num'] = (int)($counts['shipped_count'] ?? 0);
                $order['status_3']['num'] = 0;
                $userPageData['order'] = $order;
            }

            if (isset($userPageData['header'])) {
                $header = $userPageData['header'];
                // 强制转字符串或整型，防止前端显示 null
                if (isset($header['order'])) $header['order']['value'] = (string)($counts['to_be_shipped_count'] ?? 0);
                if (isset($header['footmark'])) $header['footmark']['value'] = (string)$footmarkCount;
                if (isset($header['goods'])) $header['goods']['value'] = (string)$favoriteCount;
                $userPageData['header'] = $header;
            }
        }

        $imEnabled = MallPlugin::find()
            ->where(['mall_id' => $this->mall_id, 'plugin' => 'im', 'is_delete' => 0])
            ->exists();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'page_data'  => $userPageData,
            'im_enabled' => $imEnabled,
        ]);
    }

    /**
     * 个人中心的订单入口,跟随后台「订单列表配置」里 tab 的显隐。
     *
     * 背景:这两处配置本来各管各的 —— 入口显隐在「个人中心装修」,tab 显隐在「订单列表配置」。
     * 运营在订单列表里停用某个 tab 后,个人中心那个入口还挂着(还带着角标数字),
     * 点进去 order.vue 的 findIndex 匹配不到该 status、回退到第一个 tab ——
     * 用户点「待评价」却落在「全部」,看起来就是坏了。
     *
     * 做法:按入口自己 url 里的 status 反查该 tab 是否启用,停用的直接置 is_show = 0。
     * 刻意不写死 status_N → status 的映射表 —— 那个映射本来就在 url 里
     * (见 DefaultDataHelper::userCenter():status_0 → status=1、status_3 → status=4),
     * 从 url 解析既不会和默认数据脱节,运营自己改了 url 也能跟上。
     *
     * 收口在后端的原因:个人中心有 10 套风格组件,全部读同一个 is_show,
     * 改这里一处即可,不用去改 10 个模板,以后新增风格也自动生效。
     *
     * @param array $pageData 装修配置
     * @return array
     */
    private function applyOrderTabVisibility($pageData)
    {
        if (empty($pageData['order']) || !is_array($pageData['order'])) {
            return $pageData;
        }

        $enabled = [];
        foreach (OrderListConfig::get((int)$this->mall_id)['status_tabs'] as $tab) {
            // 与 OrderController::actionListConfig 同一判定:没有 enabled 键视为启用
            if (!isset($tab['enabled']) || $tab['enabled']) {
                $enabled[(int)($tab['status'] ?? 0)] = true;
            }
        }

        foreach ($pageData['order'] as $key => $item) {
            // 只处理「带 url 的入口项」,跳过 title / sub_title / is_show 这些标量键
            if (!is_array($item) || empty($item['url']) || !array_key_exists('is_show', $item)) {
                continue;
            }
            $status = self::parseOrderListStatus((string)$item['url']);
            // null = 这个入口根本不指向订单列表;负数 = ?status=-1 的「全部」深链
            // (order.vue 匹配不到会回退首个 tab,本就是预期行为)—— 两种都不动它
            if ($status === null || $status < 0) {
                continue;
            }
            if (!isset($enabled[$status])) {
                $pageData['order'][$key]['is_show'] = 0;
            }
        }

        return $pageData;
    }

    /**
     * 从 /others/order/order?status=N 里取出 N。
     * 不是订单列表链接、或没带 status,返回 null(调用方据此放行)。
     *
     * @param string $url
     * @return int|null
     */
    private static function parseOrderListStatus($url)
    {
        if (strpos($url, '/others/order/order') === false) {
            return null;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        if (empty($query)) {
            return null;
        }
        parse_str($query, $params);

        return isset($params['status']) && is_numeric($params['status'])
            ? (int)$params['status']
            : null;
    }
}
