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

namespace app\plugins\mch\controllers\admin;

use app\controllers\api\filters\LoginFilter;
use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\MallPlugin;
use app\plugins\ApiController;
use Yii;

/**
 * 商家后台菜单/路由聚合下发。
 *
 * 按【当前商城已开通插件】聚合各插件 Plugin::getMchMenus() 声明的菜单，
 * 返回菜单树给前端动态注册路由 + 渲染侧边栏。核心菜单(店铺运营/订单/门店资产)仍由前端 constantRoutes 承载，
 * 本接口只负责「插件」菜单，商城没开的插件自然不会出现。
 */
class MenuController extends ApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // 全部 action 需登录
        $behaviors['login'] = [
            'class' => LoginFilter::class,
        ];
        return $behaviors;
    }

    /**
     * 动态路由：聚合已开通插件的 mch 菜单
     */
    public function actionRouters()
    {
        $user = Yii::$app->user->identity;
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_NOT_LOGIN, '用户未登录');
        }

        // 已开通插件(含基座 'mall')。按 token 用户所在商城取，天然多租户隔离。
        $enabled = MallPlugin::enabledPlugins($user->mall_id);

        $routers = [];
        foreach ($enabled as $name) {
            // 'mall' 是基座(无 Plugin 类)，核心菜单由前端 constantRoutes 承载，跳过避免无谓告警日志
            if ($name === 'mall') {
                continue;
            }
            // 缓存实例；插件文件缺失/类加载失败返回 false
            $plugin = Yii::$app->plugin->getPlugin($name);
            if (!$plugin) {
                continue;
            }
            try {
                $menus = $plugin->getMchMenus();
            } catch (\Throwable $e) {
                Yii::warning('[mch/menu] 插件 ' . $name . ' getMchMenus 失败: ' . $e->getMessage());
                continue;
            }
            if (is_array($menus)) {
                foreach ($menus as $node) {
                    $routers[] = $node;
                }
            }
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $routers);
    }
}
