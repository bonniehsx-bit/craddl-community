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
 * 商品海报 —— C 端商品详情页「分享海报」调用
 *
 * 模板由后台在四套固定模板里选,画法在 app\services\GoodsPosterRenderer;素材、码、缓存文件走 PosterAssets。
 */

namespace app\forms\api\poster;

use app\services\goods\StandaloneGoodsGuard;
use app\core\ApiCode;
use app\helpers\PosterHelper;
use app\models\BaseModel;
use app\models\Goods;
use app\models\User;
use app\services\GoodsPosterRenderer;
use app\services\PosterAssets;

class GoodsPosterForm extends BaseModel
{
    public $goods_id;
    public $mall_id;

    /** 模板改版时 +1,旧缓存自动失效 */
    const TEMPLATE_VERSION = 3;

    const SCAN_PAGE = 'others/goods/goods';

    public function rules()
    {
        return [
            [['goods_id', 'mall_id'], 'required'],
            [['goods_id', 'mall_id'], 'integer'],
        ];
    }

    public function create()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        ini_set('memory_limit', '512M');

        /** @var User $user */
        $user = \Yii::$app->user->identity;
        if (!$user) return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');

        // 必须限定本商城:否则拿别的商城的商品 id 也能出海报
        $goods = Goods::findOne(['id' => $this->goods_id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在');
        }
        if ((int)$goods->status !== 1) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品已下架，暂不能分享');
        }
        // 插件专属商品:二维码仍指向通用详情页,详情页会按后端给的 entry_path 跳到插件下单页(带 pid),
        // 所以有入口的照常出海报;没有对外入口的占位商品(收银台、账单缴费)不能分享
        if (StandaloneGoodsGuard::isStandalone($goods) && !StandaloneGoodsGuard::entryPath($goods)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在');
        }

        try {
            $platform = PosterAssets::platform();
            $config = PosterHelper::getGoodsPoster($this->mall_id);
            [$mallName, $mallLogo] = PosterHelper::mallBrand($this->mall_id);

            // 价格、名称、主图任何一项改了都要出新图,不能再拿一小时前的旧价格去分享
            $goodsData = [
                'goods_name' => (string)$goods->name,
                'price' => GoodsPosterRenderer::formatPrice($goods->price),
                'origin_price' => GoodsPosterRenderer::formatPrice($goods->origin_price),
                'is_negotiable' => (int)$goods->is_negotiable,
                // 和详情页一致:销量显示虚拟销量
                'sales' => (int)$goods->virtual_sales,
                'cover_pic' => (string)$goods->cover_pic,
            ];
            $fingerprint = PosterAssets::fingerprint([
                self::TEMPLATE_VERSION, $config, $goodsData, $mallName, $mallLogo,
                (string)$user->nickname, (string)$user->avatar_url, $platform,
            ]);
            $webPath = "/poster/goods/{$platform}/{$user->id}_{$goods->id}_{$fingerprint}.jpg";
            if ($url = PosterAssets::cachedUrl($webPath)) {
                return $this->success($url);
            }

            $assets = new PosterAssets((int)$this->mall_id);
            [$qrPath, $qrKind] = $assets->qrCode(self::SCAN_PAGE, "id={$goods->id}&pid={$user->id}", $platform);

            $image = (new GoodsPosterRenderer())->render(array_merge($config, $goodsData, [
                'goods_pic_path' => $assets->download($goodsData['cover_pic']),
                'nickname' => (string)$user->nickname,
                'avatar_path' => $assets->download((string)$user->avatar_url) ?: PosterAssets::defaultAvatar(),
                'qr_path' => $qrPath,
                'qr_kind' => $qrKind,
                'mall_name' => $mallName,
                'logo_path' => $assets->download($mallLogo),
            ]));

            return $this->success(PosterAssets::save($image, $webPath, "{$user->id}_{$goods->id}_*.jpg"));
        } catch (\Throwable $e) {
            \Yii::error('商品海报生成失败: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return $this->apiResponse(ApiCode::CODE_FAIL, '海报生成失败，请稍后再试');
        }
    }

    private function success(string $url)
    {
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '请求成功', ['url' => $url]);
    }
}
