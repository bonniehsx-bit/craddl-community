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
namespace app\forms\mall\user;

use app\core\ApiCode;
use app\helpers\IconHelper;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Level;
use app\models\Order; // [核心] 引入 Order
use app\models\OrderRefund;
use app\models\User;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use Yii;

class UserInfoForm extends BaseModel
{
    public $user_id;

    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 步骤 1: 使用 with() 预加载推荐人信息
        //
        // 不加 is_delete 过滤:后台详情要能打开「正常 / 拉黑 / 已注销」三种状态的用户。
        // 否则注销后前端刷新详情会拿到「用户不存在」,运营也就再没有入口点「恢复账号」。
        // 与 UserBanForm::findUser() 保持同一口径(它同样不过滤 is_delete)。
        // mall_id 仍然过滤,保证多商城隔离。
        /** @var User $user */
        $user = User::find()->where([
            'id' => $this->user_id,
            'mall_id' => Yii::$app->mall->id
        ])->with('inviter')->one();

        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在!');
        }

        // 将基础信息转换为数组
        $info = $user->toArray();

        // 步骤 2: [已重构] 一次性查询所有交易统计数据
        $transactionStats = Order::find()
            ->select([
                // [核心] 只统计“已结算”的订单
                'success_order_count' => new Expression('COUNT(CASE WHEN status = 40 AND settlement_status = 1 THEN id END)'),
                // [核心] 统计已结算订单的“实付金额 - 退款金额”
                'success_order_price' => new Expression('SUM(CASE WHEN status = 40 AND settlement_status = 1 THEN (pay_price - refund_price) ELSE 0 END)'),
                // [核心] 统计所有实际发生的退款总额
                'success_refund_price' => new Expression('SUM(refund_price)'),
            ])
            ->where(['user_id' => $this->user_id, 'is_delete' => 0])
            ->asArray()
            ->one();

        // [核心] 单独统计“已成功退款”的售后单数量
        $successRefundCount = OrderRefund::find()
            ->where(['user_id' => $this->user_id, 'is_delete' => 0, 'is_refund' => 1])
            ->count();

        // 步骤 3: 合并统计数据
        $info['success_order_count'] = (int)($transactionStats['success_order_count'] ?? 0);
        $info['success_order_price'] = sprintf('%.2f', $transactionStats['success_order_price'] ?? 0.00);
        $info['success_refund_count'] = (int)$successRefundCount;
        $info['success_refund_price'] = sprintf('%.2f', $transactionStats['success_refund_price'] ?? 0.00);


        // 步骤 4: 处理推荐人信息
        if ($user->inviter) {
            $info['parent_nickname'] = $user->inviter->nickname;
            $info['parent_avatar_url'] = $user->inviter->avatar_url;
        } else {
            $mall_setting = OptionHelper::get('mall_setting', $user->mall_id);
            $info['parent_nickname'] = $mall_setting['name'] ?? '平台';
            $info['parent_avatar_url'] = $mall_setting['logo'] ?? '';
        }

        // 步骤 5: 处理会员等级
        $allLevels = Level::find()->where(['is_delete' => 0, 'mall_id' => $user->mall_id, 'enabled' => 1])
            ->orderBy('level ASC')->asArray()->all();
        $levelsMap = ArrayHelper::map($allLevels, 'id', 'name'); // 使用 level_id 映射

        $info['level_name'] = $levelsMap[$user->level_id] ?? '默认等级';

        // 构造可选等级列表 (让前端去过滤当前等级)
        $level_list = [['name' => '默认等级', 'id' => 0, 'level' => 0]];
        foreach ($allLevels as $level) {
            $level_list[] = $level;
        }

        // 步骤 6: 处理平台图标
        $platformIconMap = [
            User::PLATFORM_WECHAT => IconHelper::wechat(),
            User::PLATFORM_MPWX => IconHelper::mpwx(),
            User::PLATFORM_WXAPP => IconHelper::wxapp(),
            User::PLATFORM_MOBILE => IconHelper::mobile(),
        ];
        $info['platform_logo'] = $platformIconMap[$user->platform] ?? '';

        // 步骤 7: 格式化时间戳
        $info['created_time'] = Yii::$app->formatter->asDatetime($user->created_at);
        $info['updated_time'] = Yii::$app->formatter->asDatetime($user->updated_at);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['user' => $info, 'level_list' => $level_list]);
    }
}