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
 * 推广(邀请)海报 —— C 端「分享海报」页调用
 *
 * 模板由后台在五套固定模板里选,画法在 app\services\InvitePosterRenderer;素材、码、缓存文件走 PosterAssets。
 */

namespace app\forms\api\poster;

use app\core\ApiCode;
use app\helpers\PosterHelper;
use app\models\BaseModel;
use app\models\User;
use app\services\InvitePosterRenderer;
use app\services\PosterAssets;

class SharePosterForm extends BaseModel
{
    public $mall_id;

    /** 模板改版时 +1,旧缓存自动失效 */
    const TEMPLATE_VERSION = 4;

    const SCAN_PAGE = 'pages/index/index';

    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            [['mall_id'], 'integer'],
        ];
    }

    public function create()
    {
        if (!$this->validate()) return $this->responseErrorInfo();
        ini_set('memory_limit', '512M');

        /** @var User $user */
        $user = \Yii::$app->user->identity;
        if (!$user) return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');

        try {
            $platform = PosterAssets::platform();
            $config = PosterHelper::getInviterPoster($this->mall_id);
            [$mallName, $mallLogo] = PosterHelper::mallBrand($this->mall_id);

            // 文件名带上「会影响画面的所有输入」的指纹:后台改了设置、用户换了头像昵称,立刻生成新图
            $fingerprint = PosterAssets::fingerprint([
                self::TEMPLATE_VERSION, $config, $mallName, $mallLogo,
                (string)$user->nickname, (string)$user->avatar_url, $platform,
            ]);
            $webPath = "/poster/share/{$platform}/share_{$user->id}_{$fingerprint}.jpg";
            if ($url = PosterAssets::cachedUrl($webPath)) {
                return $this->success($url);
            }

            $assets = new PosterAssets((int)$this->mall_id);
            [$qrPath, $qrKind] = $assets->qrCode(self::SCAN_PAGE, "pid={$user->id}", $platform);

            $image = (new InvitePosterRenderer())->render([
                'template' => $config['template'],
                'theme_color' => $config['theme_color'],
                'main_title' => $config['main_title'],
                'sub_title' => $config['sub_title'],
                'bg_path' => $assets->download($config['bg_image']),
                'nickname' => (string)$user->nickname,
                'avatar_path' => $assets->download((string)$user->avatar_url) ?: PosterAssets::defaultAvatar(),
                'qr_path' => $qrPath,
                'qr_kind' => $qrKind,
                'mall_name' => $mallName,
                'logo_path' => $assets->download($mallLogo),
            ]);

            return $this->success(PosterAssets::save($image, $webPath, "share_{$user->id}_*.jpg"));
        } catch (\Throwable $e) {
            \Yii::error('推广海报生成失败: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return $this->apiResponse(ApiCode::CODE_FAIL, '海报生成失败，请稍后再试');
        }
    }

    private function success(string $url)
    {
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['url' => $url]);
    }
}
