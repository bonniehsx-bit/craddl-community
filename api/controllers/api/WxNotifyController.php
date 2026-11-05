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

use app\controllers\api\filters\LoginFilter;
use app\forms\api\user\WxNotifyForm;

/**
 * C 端 — 公众号推送通知绑定（平台核心能力，任何插件都能用）。
 *
 * GET  /api/wx-notify/status         查当前用户绑没绑
 * POST /api/wx-notify/bind-by-follow 关注公众号组件回调后保存 openid
 * POST /api/wx-notify/unbind         关闭推送（清掉 openid）
 *
 * 原来这三个接口长在 hardware 插件下(`/plugin/hardware/api/notification/*`)，
 * 核心首页的关注横幅、核心设置页的「微信通知」、别的插件都在调它 ——
 * hardware 目录没部署时整条链静默失效。逻辑已收到 `WxNotifyForm`，
 * hardware 那三个路由保留为转调（老版本小程序还在调，不能删）。
 */
class WxNotifyController extends Controller
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => ['class' => LoginFilter::class],
        ]);
    }

    public function actionStatus()
    {
        return $this->asJson((new WxNotifyForm())->status());
    }

    public function actionBindByFollow()
    {
        $form = new WxNotifyForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->bind());
    }

    public function actionUnbind()
    {
        return $this->asJson((new WxNotifyForm())->unbind());
    }
}
