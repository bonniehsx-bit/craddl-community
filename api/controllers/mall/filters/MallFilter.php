<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 21:49
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

namespace app\controllers\mall\filters;

use app\core\ApiCode;
use app\models\Admin;
use app\models\Mall;
use Yii;
use yii\base\ActionFilter;

/**
 * Class MallFilter
 * @package app\controllers\mall\filters
 *
 * [最终精简版] 商城管理过滤器
 *
 * 职责：
 * 1. 确保商城 (Mall) 上下文已初始化 (由 AppBootstrap 或 Session 恢复)。
 * 2. 确保这个后台账号能管这个商城 (Admin::canAccessMall)。
 * 3. 确保后台管理员 (Admin) 已登录。
 *
 * 变更：
 * - 移除了所有商户 (isMch) 相关的判断逻辑，因为商户现在属于 User 体系。
 */
class MallFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        // ============================================================
        // 1. 检查商城是否存在 (上下文初始化)
        // ============================================================

        // 通常情况下，AppBootstrap 已经根据 header['x-mall-id'] 设置了 Yii::$app->mall
        if (!Yii::$app->mall) {

            // [兼容兜底] 如果 Header 没传，尝试从 Session 获取 (兼容旧版逻辑)
            $sessionMallId = Yii::$app->session->get('mall_id');
            if ($sessionMallId) {
                // 使用 Mall::getOne 利用 Redis 缓存
                $mall = Mall::getOne($sessionMallId);
                if ($mall) {
                    Yii::$app->mall = $mall;
                }
            }

            // [最终判定] 如果还是没有商城对象，拦截请求
            if (!Yii::$app->mall) {
                $current_url = Yii::$app->request->absoluteUrl;
                Yii::$app->response->data = [
                    'code' => ApiCode::CODE_MALL_NOT_EXIST,
                    'msg' => '商城不存在或未指定商城ID!',
                    'data' => ['redirect_url' => $current_url]
                ];
                return false;
            }
        }

        // [归属校验] 这个后台账号能不能管这个商城。
        // ⛔ 商城编号来自请求头 x-mall-id(或上面的 Session 兜底),前端可以随便改。原来只在「进入商城」那一下校验,
        //    之后只看登没登录:A 商城的操作员把请求头改成 B,就能看、能改 B 的数据(2026-10-01 资金审计)。
        //    没登录时这里不拦,交给下面的登录检查。
        if ($denied = Admin::mallScopeDenied()) {
            Yii::$app->session->remove('mall_id');   // 别让一个进不去的商城卡在 Session 兜底里
            Yii::$app->response->data = $denied;
            return false;
        }

        // [Session 同步] 保持 Session 状态一致，方便后续非 API 页面使用
        if (Yii::$app->mall && !Yii::$app->session->get('mall_id')) {
            Yii::$app->session->set('mall_id', Yii::$app->mall->id);
        }

        // ============================================================
        // 2. 检查管理员登录状态
        // ============================================================

        // 具体的认证工作 (Token解析、Redis查找、UA校验) 已由 AdminBootstrap 完成
        // 这里只负责查验结果：是否为访客(Guest)
        if (Yii::$app->admin->isGuest) {
            $current_url = Yii::$app->request->absoluteUrl;
            Yii::$app->response->data = [
                'code' => ApiCode::CODE_NOT_LOGIN,
                'msg' => '请先登录管理员账号!',
                'data' => ['redirect_url' => $current_url]
            ];
            return false;
        }

        return parent::beforeAction($action);
    }
}
