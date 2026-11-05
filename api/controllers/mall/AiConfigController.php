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

namespace app\controllers\mall;

use app\forms\mall\mall\AiConfigForm;
use Yii;
use app\helpers\ResponseHelper;

/**
 * 商城级 AI 配置 — 后台「商城管理 → AI 配置」
 *
 * 路由前缀：mall/ai-config/
 *   GET   index   读取配置（密钥脱敏）+ 可选供应商清单
 *   POST  save    保存配置
 *   POST  test    主供应商连通性测试（仅测文本对话）
 *
 * AI 供应商配置是全商城公共配置，所有用到 AI 的插件共用这一份，
 * 插件不再各建配置页、各存密钥。存储见 AiProviderFactory::OPTION_KEY。
 */
class AiConfigController extends Controller
{
    /** 读取配置 */
    public function actionIndex()
    {
        $form = new AiConfigForm();
        return $this->asJson(ResponseHelper::baseSend($form->get()));
    }

    /** 保存配置 */
    public function actionSave()
    {
        $form = new AiConfigForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson(ResponseHelper::baseSend($form->save()));
    }

    /**
     * 主供应商连通性测试
     *
     * ⚠️ 只发一句文本对话，通过不代表图片理解与语音识别可用 ——
     * 那两类能力需要各插件的联调页分别实测。
     */
    public function actionTest()
    {
        $form = new AiConfigForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson(ResponseHelper::baseSend($form->test()));
    }
}
