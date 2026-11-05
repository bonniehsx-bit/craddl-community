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

namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use app\plugins\mch\services\PosterGenerateService;
use app\services\MiniProgramCodeService;
use Yii;
use yii\helpers\Url;

/**
 * Class ShopCodeForm
 * @package app\plugins\mch\forms\admin
 *
 * 负责处理商家店铺推广码的生成逻辑
 */
class MchPosterForm extends BaseModel
{
    public function getQrcode()
    {
        /** @var Mch $mch */
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无法获取当前商家信息，请重新登录');
        }
        // --- 步骤 1: 获取小程序码的 URL ---
        try {
            $pagePath = 'plugins1/mch/index'; // 店铺主页路径
            $scene = 'id=' . $mch->id . '&pid=' . $mch->user_id;
            $codeService = new MiniProgramCodeService();
            $qrCodeRelativeUrl = $codeService->getUnlimitedSceneCode($pagePath, $scene, $this->mall_id);
            $qrCodeFullUrl = Url::to($qrCodeRelativeUrl, true);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序码生成失败，请稍后重试');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'qrcode' => $qrCodeFullUrl
        ]);
    }


    /**
     * 获取店铺小程序主页码及海报所需信息, 并最终生成海报
     * @return array
     */
    public function get()
    {
        /** @var Mch $mch */
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无法获取当前商家信息，请重新登录');
        }
        // --- 步骤 1: 获取小程序码的 URL ---
        try {
            $res = $this->getQrcode();
            if ($res['code'] == 1) {
                return $res;
            }
            $qrCodeFullUrl = $res['data']['qrcode'];
            $logoFullUrl = Url::to($mch->logo, true);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '小程序码生成失败，请稍后重试');
        }
        // --- 步骤 2: 准备海报所需的所有数据 ---
        $posterData = [
            'mch_info' => [
                'mch_name' => $mch->mch_name,
                'logo_url' => $logoFullUrl, // 传递完整的 logo URL
                'sub_title' => 'XXXX优质门店',
                'service_text' => '为您服务'
            ],
            'qr_code_image_url' => $qrCodeFullUrl // 传递完整的小程序码 URL
        ];
        Yii::warning($posterData);

        // --- 步骤 3: 调用服务生成海报，获取海报的相对路径 ---
        try {
            $posterService = new PosterGenerateService();
            $posterRelativeUrl = $posterService->generateMchShopPoster($posterData);
            if (empty($posterRelativeUrl)) {
                throw new \Exception("海报服务返回了空的路径");
            }
        } catch (\Exception $e) {
            Yii::error("生成海报失败 (ShopCodeForm): " . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, '海报生成失败，请稍后重试');
        }

        // --- 步骤 4: [关键] 在最终返回给前端时，将相对路径转换为完整的 URL ---
        $finalPosterUrl = Url::to($posterRelativeUrl, true);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'poster_url' => $finalPosterUrl
        ]);
    }
}