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

namespace app\forms\mall\plugin;

use app\core\ApiCode;
use app\models\AdminPluginShortcut;
use app\models\BaseModel;
use app\models\Plugin;
use app\helpers\PluginEntryHelper;
use Yii;

class PluginShortcutListForm extends BaseModel
{
    public function getList()
    {
        $adminId = Yii::$app->admin->id;
        $mallId = Yii::$app->mall ? Yii::$app->mall->id : 0;

        $shortcuts = AdminPluginShortcut::find()->where([
            'mall_id' => $mallId,
            'admin_id' => $adminId,
            'is_delete' => 0,
        ])->orderBy('id DESC')->asArray()->all();

        $pluginNames = array_column($shortcuts, 'plugin_name');

        $plugins = [];
        if (!empty($pluginNames)) {
            $plugins = Plugin::find()
                ->where(['name' => $pluginNames, 'is_delete' => 0])
                ->select('name, label, logo as icon')
                ->indexBy('name')
                ->asArray()
                ->all();
        }

        $list = [];
        foreach ($shortcuts as $s) {
            if (isset($plugins[$s['plugin_name']])) {
                $list[] = $plugins[$s['plugin_name']];
            }
        }

        // 入口路由跟着侧边栏那套算法走,不要在这里另拼一套(见 PluginEntryHelper 注释)。
        // 一次性批量解析,避免每个插件各查一遍全量菜单。
        $entries = PluginEntryHelper::resolve(array_column($list, 'name'), (int)$mallId);

        foreach ($list as &$item) {
            // route_path 是**完整路由**,前端直接 push,不要再加任何前缀 ——
            // 原来后端给完整路径、前端又拼一次 '/plugins/{name}',拼出
            // /plugins/hotel/plugins/hotel/apply-list 这种双前缀地址,点了就是 404。
            // 而菜单 path 写成相对形式的插件反倒碰巧拼对了,于是表现为
            // 「有的插件能点、有的 404」,极易被误判成客户把菜单改坏了。
            $item['route_path'] = $entries[$item['name']] ?? '';
            $item['path'] = $item['route_path'];   // 兼容仍在读 path 的旧前端

            // 快捷入口与应用中心保持一致，旧默认图片自动切换到 Craddl C。
            $item['icon'] = \app\helpers\PluginHelper::resolveLogo($item['name'], $item['icon'] ?? '');
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '操作成功',
            'data' => [
                'list' => $list
            ]
        ];
    }
}
