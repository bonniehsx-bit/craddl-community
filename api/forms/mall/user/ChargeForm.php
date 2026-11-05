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
use app\helpers\BalanceLogHelper;
use app\helpers\IntegralLogHelper;
use app\helpers\PriceLogHelper;
use app\models\BaseModel;
use app\models\ChargeLog;
use app\models\User;
use Yii;

class ChargeForm extends BaseModel
{
    public $num;
    public $type;    // 0=余额, 1=积分, 2=佣金(奖励)
    public $way;     // 1=增加, 2=减少 (与前端保持一致)
    public $remarks;
    public $user_id;

    // 搜索参数 (如果不使用可以删除，或者保留以兼容旧逻辑)
    public $search_date;
    public $nickname;
    public $is_export;

    public function rules()
    {
        return [
            [['user_id', 'type', 'way'], 'integer'],
            // 验证 num 必须为正数
            [['num'], 'number', 'min' => 0.01, 'message' => '变更数量必须大于0'],
            [['remarks'], 'string'],
            [['search_date', 'nickname', 'is_export'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'num' => '变更数量',
            'type' => '资产类型',
            'way' => '操作方式',
            'user_id' => '用户ID'
        ];
    }

    /**
     * 保存后台资产变更
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 必带 mall_id:否则平台管理员传任意 user_id 可给【其他商城】的用户充值/扣款(越权/资损)。
        // 带 is_delete=0:充值目标须为存活用户,Form 层自成防御,不依赖控制器守卫(纵深)。
        $user = User::findOne(['id' => $this->user_id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在！');
        }

        // 获取当前管理员ID (如果没有登录则为0或抛出异常，视系统而定)
        $adminId = \Yii::$app->admin->identity ? \Yii::$app->admin->identity->id : 0;

        // 自动生成默认备注(文案后台词库可配:charge_add/charge_sub/charge_source,默认=原文)
        $actionText = ($this->way == 1)
            ? \app\helpers\NounHelper::parse('charge_add')
            : \app\helpers\NounHelper::parse('charge_sub');
        $this->remarks = $this->remarks ?: $actionText;
        $sourceText = \app\helpers\NounHelper::parse('charge_source');

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $beforeNum = 0;

            // --- 核心：调度逻辑 ---
            // 注意：前端传 1 是增加，2 是减少

            switch ($this->type) {
                case 0: // 余额
                    $beforeNum = $user->money;
                    // BalanceLogHelper 参数通常是: user_id, money, desc, source_id, source_type, custom_desc
                    if ($this->way == 1) { // 增加
                        BalanceLogHelper::add($this->user_id, $this->num, $this->remarks, 0, 0, $this->remarks);
                    } else { // 减少
                        // 扣款前检查余额是否充足 (可选，取决于业务是否允许负余额)
                        if ($user->money < $this->num) {
                            throw new \Exception('用户余额不足，无法扣除');
                        }
                        BalanceLogHelper::sub($this->user_id, $this->num, $this->remarks, 0, 0, $this->remarks);
                    }
                    break;

                case 1: // 积分
                    $beforeNum = $user->integral;
                    // IntegralLogHelper 参数: user_id, integral, desc, custom_desc
                    // 走核心积分网关,业务类型区分人工调账,便于后台"积分来源"报表单列。
                    // 刻意不传幂等键:管理员合法地可以对同一用户连续多次加/减同额积分。
                    $integralSvc = new \app\services\IntegralService();
                    if ($this->way == 1) { // 增加
                        $integralSvc->grant($this->user_id, $this->num, 'admin_charge', [
                            'title'   => $sourceText,
                            'content' => $this->remarks,
                        ]);
                    } else { // 减少
                        if ($user->integral < $this->num) {
                            throw new \Exception('用户积分不足，无法扣除');
                        }
                        $integralSvc->consume($this->user_id, $this->num, 'admin_deduct', [
                            'title'   => $sourceText,
                            'content' => $this->remarks,
                        ]);
                    }
                    break;

                case 2: // 佣金 (Price)
                    $beforeNum = $user->price;
                    // PriceLogHelper 参数: user_id, price, desc, source_id, custom_desc
                    if ($this->way == 1) { // 增加
                        PriceLogHelper::add($this->user_id, $this->num, $this->remarks, 0, $sourceText);
                    } else { // 减少
                        if ($user->price < $this->num) {
                            throw new \Exception('用户佣金不足，无法扣除');
                        }
                        PriceLogHelper::sub($this->user_id, $this->num, $this->remarks, 0, $sourceText);
                    }
                    break;

                default:
                    throw new \Exception('无效的资产类型');
            }

            // 重新获取最新的用户信息来记录 after_num
            $user->refresh();
            $afterNum = 0;
            switch ($this->type) {
                case 0:
                    $afterNum = $user->money;
                    break;
                case 1:
                    $afterNum = $user->integral;
                    break;
                case 2:
                    $afterNum = $user->price;
                    break;
            }

            // 记录后台操作日志 (ChargeLog)
            $log = new ChargeLog();
            $log->mall_id = Yii::$app->mall->id;
            $log->admin_id = $adminId;
            $log->user_id = $this->user_id;
            $log->type = $this->type;
            $log->num = $this->num;
            $log->before_num = $beforeNum;
            $log->after_num = $afterNum;
            $log->remarks = $this->remarks;
            $log->created_at = time(); // 确保有时间戳

            if (!$log->save()) {
                throw new \Exception('保存操作日志失败：' . json_encode($log->getErrors()));
            }

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}