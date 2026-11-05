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

namespace app\controllers\common;

use app\services\TablerIconService;
use app\services\LegacyIconFontService;
use app\helpers\ServerHelper;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/** 公开、只读的公共图标资源；与用户身份及商城数据无关。 */
class IconController extends Controller
{
    public function actionFont()
    {
        $request = Yii::$app->request;
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $headers = $response->headers;
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('X-Content-Type-Options', 'nosniff');
        try {
            $family = $request->get('family', '');
            $version = $request->get('v', '');
            if (!is_string($family) || !is_string($version)) {
                throw new \OutOfBoundsException('字体参数无效');
            }
            $font = (new LegacyIconFontService())->get($family, $version);
        } catch (\OutOfBoundsException $e) {
            $response->statusCode = 404;
            $headers->set('Cache-Control', 'no-store');
            return '';
        }
        $headers->set('Content-Type', $font['mime']);
        $headers->set('ETag', $font['etag']);
        $headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $tags = array_map('trim', explode(',', $request->headers->get('If-None-Match', '')));
        if (in_array($font['etag'], $tags, true) || in_array('W/' . $font['etag'], $tags, true) || in_array('*', $tags, true)) {
            $response->statusCode = 304;
            return '';
        }
        return $font['data'];
    }

    public function actionList()
    {
        try {
            $data = (new TablerIconService())->listing(Yii::$app->request->get());
            $data['asset_base_url'] = ServerHelper::getHost() . '/index.php?r=common/icon/svg';
            return $this->asJson(['code' => 0, 'data' => $data]);
        } catch (\InvalidArgumentException $e) {
            return $this->asJson(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function actionAsset()
    {
        try {
            return $this->asJson(['code' => 0, 'data' => (new TablerIconService())->asset(Yii::$app->request->get())]);
        } catch (\InvalidArgumentException | \OutOfBoundsException $e) {
            return $this->asJson(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    public function actionSvg()
    {
        $request = Yii::$app->request;
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $headers = $response->headers;
        $headers->set('Content-Type', 'image/svg+xml; charset=UTF-8');
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        try {
            $service = new TablerIconService();
            $svg = $service->svg(
                (string)$request->get('name', ''),
                (string)$request->get('color', '#303133'),
                (string)$request->get('variant', 'outline'),
                (string)$request->get('v', TablerIconService::VERSION),
                $service->scale($request->get('scale', 100))
            );
        } catch (\InvalidArgumentException $e) {
            $response->statusCode = 400;
        } catch (\OutOfBoundsException $e) {
            $response->statusCode = 404;
        }
        if ($response->statusCode !== 200) {
            $headers->set('Cache-Control', 'no-store');
            return '';
        }
        $etag = '"' . hash('sha256', $svg) . '"';
        $headers->set('ETag', $etag);
        $headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $tags = array_map('trim', explode(',', $request->headers->get('If-None-Match', '')));
        if (in_array($etag, $tags, true) || in_array('W/' . $etag, $tags, true) || in_array('*', $tags, true)) {
            $response->statusCode = 304;
            return '';
        }
        return $svg;
    }
}
