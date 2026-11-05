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

namespace app\controllers\api;

use app\core\ApiCode;
use app\forms\api\fund\OnboardingForm;
use app\forms\api\fund\UserSettlementInfoForm;
use app\forms\api\fund\UserSettlementForm;
use app\forms\api\fund\UserSettlementVerifyForm;
use app\helpers\ResponseHelper;
use app\models\BranchBank;
use app\plugins\ApiController;
use app\services\OnboardingService;
use Yii;

class FundController extends ApiController
{
    /**
     * [核心改造] 获取用户进件所需的动态表单结构及已填信息
     */
    public function actionOnboardingForm()
    {
        $form = new OnboardingForm();

        $form->attributes = $this->request->get();
        return $this->asJson($form->getForm());
    }

    /**
     * 获取用户结算信息及进件状态
     */
    public function actionSettlementInfo()
    {
        $form = new UserSettlementInfoForm();
        return $this->asJson($form->getInfo());
    }

    /**
     * 保存结算信息并发起进件
     */
    public function actionSettlement()
    {
        $form = new UserSettlementForm();
        $form->formData = $this->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 验证进件（提交短信验证码）
     */
    public function actionSettlementVerify()
    {
        $form = new UserSettlementVerifyForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->verify());
    }


    public function actionCheckEligibility()
    {
        $channel = Yii::$app->request->get('channel', 'bank');
        $userId = Yii::$app->user->id;

        if (empty($userId)) {
            return $this->asJson(ResponseHelper::send(ApiCode::CODE_NOT_LOGIN, '用户未登录'));
        }

        try {
            $service = new OnboardingService(Yii::$app->mall->id);

            $fundInstance = $service->getFundInstanceByChannel($channel);
            $result = $fundInstance->checkUserEligibility(['user_id' => $userId]);

            // 如果资格检查不通过，并且明确需要进件
            if (isset($result['is_eligible']) && $result['is_eligible'] === false) {

                // 检查是否需要跳转
                if (isset($result['require_onboarding']) && $result['require_onboarding'] === true) {
                    // 在返回数据中附加跳转地址
                    $result['redirect_url'] = '/others/fund-onboard/fund-onboard';
                }

                // 资格不通过时，code 应该是成功的，因为这是一个预期的业务结果
                return $this->asJson(ResponseHelper::send(
                    ApiCode::CODE_SUCCESS,
                    '查询成功', // msg 应该是中性的
                    $result
                ));
            }

            // 资格通过
            return $this->asJson(ResponseHelper::send(
                ApiCode::CODE_SUCCESS,
                '查询成功',
                $result
            ));

        } catch (\Exception $e) {
            // 捕获系统级异常（如通道未配置）
            Yii::error("资格检查时发生系统异常 (Channel: {$channel}, User: {$userId}): " . $e->getMessage());

            // 这种情况下，返回一个通用的系统错误
            return $this->asJson(ResponseHelper::send(
                ApiCode::CODE_FAIL, // 使用表示服务器错误的 code
                '资格校验失败',
                [
                    'is_eligible' => false,
                    'message' => '系统繁忙，无法完成资格校验，请稍后再试。'
                ]
            ));
        }
    }

    /**
     * 开户银行搜索。**允许空关键字** —— 空时下发默认列表(前 30 家),
     * 让用户打开选择器就能直接挑,不必盲猜着先打字。
     *
     * ⚠ 这里原来有一道 `if (empty($bankName)) return FAIL`,而下游
     * BankFund::queryBankInfo() 本来就实现了「空关键字返回前 30 条」,
     * 前端也是按这个契约写的(打开弹窗即请求)。结果整条链被这道 guard 掐断:
     * 前端拿到 FAIL → 列表空 → 空态一直显示「加载中…」,看着像卡死。
     * 另外 empty() 还会把关键字 "0" 一并判成空。
     */
    public function actionQueryBankCode()
    {
        $bankName = (string) $this->request->get('bankName', '');
        $service = new OnboardingService($this->mall_id);
        return $this->asJson($service->queryBankCode($bankName));
    }


    public function actionQueryBankInfoBranch()
    {
        $bankName = trim((string)$this->request->get('bankName'));
        if ($bankName === '') {
            return $this->asJson([
                'code' => \app\core\ApiCode::CODE_FAIL,
                'msg'  => '请输入支行关键字',
            ]);
        }

        // 已选开户银行:用来把结果限定在这家银行内。
        //
        // branch_bank 表没有银行标识列(branch_code 前 3 位虽是 CNAPS 行别码,
        // 但 BankFund 字典用的是 ICBC/CMB 这类字母码,两边对不上),
        // 好在 branch_name 本身就是「银行名+地区+支行」,再加一个 LIKE 即可。
        //
        // 不加这一条的后果(实测):选了招商银行、输「顺德」→ 命中 154 条横跨 6 家银行,
        // 而招商那条排在第 119 位,被 limit(100) 截掉 —— 用户根本搜不到自己的支行。
        $selectedBank = trim((string) $this->request->get('bank_name'));

        $list = BranchBank::find()
            ->select(['branch_name', 'branch_code', 'province_code', 'city_code'])
            ->where(['like', 'branch_name', $bankName])
            ->andFilterWhere(['like', 'branch_name', $selectedBank])
            ->andWhere(['is_delete' => 0])
            ->limit(100)
            ->asArray()
            ->all();

        // DB 字段是下划线，前端 Vue 模板用驼峰（bankBranchName/bankBranchCode），必须转换
        $formattedList = array_map(function ($item) {
            return [
                'bankBranchName' => $item['branch_name'],
                'bankBranchCode' => $item['branch_code'],
                'provinceCode'   => $item['province_code'],
                'cityCode'       => $item['city_code'],
            ];
        }, $list);

        // [核心修复] 必须用 $this->asJson() 包裹，否则响应不是 JSON 格式，前端拿不到 data
        return $this->asJson(ResponseHelper::send(ApiCode::CODE_SUCCESS, '', [
            'branchList' => $formattedList,
        ]));
    }
}