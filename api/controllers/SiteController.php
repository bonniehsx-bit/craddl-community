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

namespace app\controllers;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\Admin;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Cookie;
use yii\web\Response;
use yii\filters\VerbFilter;


class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }


    /**
     * Displays homepage.
     *
     * @return \yii\web\Response|string
     */
    public function actionIndex()
    {
        return $this->redirect('/admin');
    }

    /**
     * Logout action.
     * 解决组件错位问题：兼容清理前台 user 和后台 admin 二者的认证持久态。
     *
     * @return Response
     */
    public function actionLogout()
    {
        // 尝试销毁可能存在的 Admin（后台）登录凭证
        if (Yii::$app->has('admin') && !Yii::$app->admin->isGuest) {
            Yii::$app->admin->logout();
        }

        // 尝试销毁可能存在的 User（前台C端）登录凭证
        if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
        }

        return $this->goHome();
    }


    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionError()
    {
        Yii::$app->response->format = Response::FORMAT_HTML;
        return $this->render('error', ['name' => '出现了一个错误~', 'message' => '给您带来的不便，我们深感抱歉！']);
    }

    /**
     * 隐私协议页面
     * 优化：动态注入地址、电话等详细信息，满足应用市场审核
     */
    public function actionPrivacy()
    {
        $this->layout = false;
        Yii::$app->response->format = Response::FORMAT_HTML;

        $mallId = Yii::$app->request->get('mall_id', 1);
        $setting = OptionHelper::get('mall_setting', $mallId);

        // 如果获取不到配置，使用默认占位符，防止页面报错
        if (!$setting) {
            $data = [
                'company' => '未填写公司名称',
                'app_name' => 'Craddl',
                'app_short_name' => 'Craddl',
                'address' => '未填写公司地址',
                'tel' => '',
                'email' => ''
            ];
        } else {
            // 组装数据，确保字段存在
            $data = [
                'company' => $setting['company'] ?? '未填写公司名称',
                // 优先使用 app_name，如果没有则使用 name (商城名称)
                'app_name' => !empty($setting['app_name']) ? $setting['app_name'] : ($setting['name'] ?? 'Craddl'),
                'app_short_name' => $setting['app_short_name'] ?? 'Craddl',
                // 开发者地址（使用商城设置中的 address 字段）
                'address' => $setting['address'] ?? '未填写地址',
                // 联系电话（使用商城设置中的 tel 字段）
                'tel' => $setting['tel'] ?? '',
                // 联系邮箱 (虽然表单没有显式字段，但这里预留逻辑，或者你可以复用 tel 字段旁边的字段)
                'email' => '', // 暂时留空，或者在 setting 中添加字段
            ];
        }

        return $this->render('privacy', $data);
    }

    /**
     * 用户协议页面
     * 优化：同样注入详细信息
     */
    public function actionProtocol()
    {
        $this->layout = false;
        Yii::$app->response->format = Response::FORMAT_HTML;

        $mallId = Yii::$app->request->get('mall_id', 1);
        $setting = OptionHelper::get('mall_setting', $mallId);

        if (!$setting) {
            $data = [
                'company' => '未填写公司名称',
                'app_name' => 'Craddl',
                'app_short_name' => 'Craddl',
                'address' => '',
                'tel' => ''
            ];
        } else {
            $data = [
                'company' => $setting['company'] ?? '未填写公司名称',
                'app_name' => !empty($setting['app_name']) ? $setting['app_name'] : ($setting['name'] ?? 'Craddl'),
                'app_short_name' => $setting['app_short_name'] ?? 'Craddl',
                'address' => $setting['address'] ?? '',
                'tel' => $setting['tel'] ?? '',
            ];
        }

        return $this->render('protocol', $data);
    }
}
