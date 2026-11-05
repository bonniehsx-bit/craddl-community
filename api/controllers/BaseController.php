<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:10
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

namespace app\controllers;

use yii\web\Request;

/**
 * Class BaseController
 * @package app\controllers
 * @Notes
 * [最终优化版]
 * 基础控制器，已彻底瘦身。
 * 所有全局应用状态的初始化工作已全部移交至 AppBootstrap 组件。
 * @property Request $request
 */
class BaseController extends \yii\web\Controller
{
    // 保留这些属性是为了在控制器实例内部方便访问，
    // 但它们的赋值逻辑已被大大简化。
    public $request;
    public $mall_id;
    public $is_mch;
    public $is_pc;

    public function init()
    {
        // 1. [保留] 配置 Admin 组件的行为
        // 这是属于 Controller 上下文的逻辑，保留在这里是合适的，
        //因为它定义了这批控制器（如后台Admin控制器）的特定行为。
        \Yii::$app->admin->enableSession = false;
        \Yii::$app->admin->enableAutoLogin = false;
        \Yii::$app->isAdmin = true;

        // 2. [保留] 设置控制器内部方便使用的属性
        $this->request = \Yii::$app->request;

        // 直接从已初始化的全局状态中读取信息，赋值给控制器属性
        if (\Yii::$app->mall) {
            $this->mall_id = \Yii::$app->mall->id;
        }
        // \Yii::$app->isPc 和 isMch 是由 AppBootstrap 设置的
        $this->is_pc = \Yii::$app->isPc ?? 0;
        $this->is_mch = \Yii::$app->isMch ?? 0;

        // [已移除] -----------------------------------------------------------------
        // 所有解析 headers 并设置全局 Yii::$app 属性的逻辑均已移除。
        // 包括：is-pc, user-agent, x-version, is-mch 的解析。
        // 这使得 BaseController 职责更单一，且避免了在每个请求中重复解析。
        // -----------------------------------------------------------------------

        parent::init();
    }
}
