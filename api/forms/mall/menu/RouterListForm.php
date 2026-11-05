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

namespace app\forms\mall\menu;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\BaseModel;
use app\models\MallMenuOption;
use app\models\MallPlugin;
use app\models\Menu;
use app\models\Plugin;
use app\models\RoleMenu;
use Yii;

class RouterListForm extends BaseModel
{
    /**
     * 获取商户私有配置的沙盒动态路由
     */
    public function getRouters()
    {
        $mallId = Yii::$app->mall->id;
        $plugins = ['mall'];

        $purchasedPlugins = MallPlugin::find()->alias('m')
            ->innerJoin(['p' => Plugin::tableName()], 'p.name=m.plugin')
            ->select('m.plugin')
            ->andWhere(['m.mall_id' => $mallId])
            ->andWhere(['m.is_delete' => 0, 'p.is_delete' => 0])
            ->column();

        $plugins = array_merge($plugins, $purchasedPlugins);

        $identity = Yii::$app->admin->identity;
        $menuIds = [];

        if ($identity->admin_type == Admin::ADMIN_TYPE_SUPER) {
            $menuIds = Menu::find()
                ->andWhere(['plugin' => $plugins])
                ->andWhere(['is_delete' => 0])
                ->select('menu_id')
                ->column();
        } else {
            $menuIds = RoleMenu::find()->alias('r')
                ->innerJoin(['m' => Menu::tableName()], 'm.menu_id=r.menu_id')
                ->andWhere(['m.plugin' => $plugins])
                ->andWhere(['r.role_id' => $identity->role_id])
                ->select('r.menu_id')
                ->column();
        }

        $allMenus = Menu::find()
            ->andWhere(['menu_id' => $menuIds])
            ->andWhere(['menu_type' => ['M', 'C']])
            ->select([
                'parent_id',
                'menu_id',
                'menu_name',
                'icon',
                'hidden',
                'is_cache',
                'link',
                'path',
                'component',
                'breadcrumb',
                'show_tag',
                'active_menu',
                'menu_type',
                'name',
                'plugin',
                'order_num'
            ])
            ->orderBy('order_num asc')
            ->asArray()
            ->all();

        // 【重构核心】：基于原始关系计算所有路由的绝对路径，防止拖拽后出现 index 等相对路径冲突
        $originalGrouped = [];
        $allMenusDict = [];
        foreach ($allMenus as &$m) {
            $originalGrouped[$m['parent_id']][] = $m;
            $allMenusDict[$m['menu_id']] = &$m;
        }
        $this->assignAbsolutePaths($originalGrouped, '0', '', $allMenusDict);

        $options = MallMenuOption::find()
            ->where(['mall_id' => $mallId])
            ->indexBy('menu_id')
            ->asArray()
            ->all();

        if (!empty($options)) {
            foreach ($allMenus as &$menu) {
                $mId = $menu['menu_id'];
                if (isset($options[$mId])) {
                    $opt = $options[$mId];

                    if (!empty($opt['custom_name'])) {
                        $menu['menu_name'] = $opt['custom_name'];
                    }
                    // 与 MenuListForm 同口径:显示/隐藏【双向】覆盖系统默认值。
                    // 只单向置 1 的话,系统表 hidden=1 的菜单被商户改成"显示"后这里读不出来,
                    // 侧边栏依旧不渲染 —— 后台列表和实际菜单会同时"改了不生效"。
                    if (isset($opt['is_hide']) && $opt['is_hide'] !== null && $opt['is_hide'] !== '') {
                        $menu['hidden'] = (int)$opt['is_hide'] === 1 ? 1 : 0;
                    }
                    if (isset($opt['custom_sort']) && $opt['custom_sort'] !== null && $opt['custom_sort'] !== '') {
                        $menu['order_num'] = intval($opt['custom_sort']);
                    }
                    // 【解耦关键】：完全信任沙盒数据，覆盖层级
                    if (isset($opt['custom_parent_id']) && $opt['custom_parent_id'] !== '') {
                        $menu['parent_id'] = $opt['custom_parent_id'];
                    }
                    if (!empty($opt['custom_path'])) {
                        $menu['path'] = $opt['custom_path'];
                    }
                    if (isset($opt['is_home']) && $opt['is_home'] == 1) {
                        $menu['is_home'] = 1;
                    }
                }
            }
            unset($menu);

            $orderNums = array_column($allMenus, 'order_num');
            array_multisort($orderNums, SORT_ASC, $allMenus);
        }

        $groupedMenus = [];
        foreach ($allMenus as $menu) {
            $groupedMenus[$menu['parent_id']][] = $menu;
        }

        $routerTree = $this->buildRouterTree($groupedMenus, '0');

        $response = ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $routerTree);
        $response['has_im'] = in_array('im', $purchasedPlugins);
        return $response;
    }

    private function buildRouterTree(&$groupedMenus, $parentId)
    {
        $tree = [];
        if (!isset($groupedMenus[$parentId])) {
            return $tree;
        }

        foreach ($groupedMenus[$parentId] as $item) {
            $row = [];
            $row['hidden'] = $item['hidden'] ? true : false;
            $row['name'] = $item['name'];

            $meta = [
                'title' => $item['menu_name'],
                'icon' => $item['icon'],
                'is_cache' => $item['is_cache'] ?? 0,
                'link' => $item['link'] ?? null,
                'breadcrumb' => $item['breadcrumb'] ? true : false,
                'activeMenu' => $item['active_menu']
            ];

            if (!empty($item['is_home'])) {
                $meta['is_home'] = 1;
                $meta['affix'] = true;
                $row['is_home'] = 1; // 供快捷读取
            }

            // dashboard 仅在它是主页时才 affix（用户可能设置了其他页面为主页）
            if ($item['menu_id'] === 'dashboard' && !empty($item['is_home'])) {
                $meta['affix'] = true;
            }

            if (!empty($item['show_tag'])) {
                $meta['show_tag'] = $item['show_tag'];
            }

            $children = $this->buildRouterTree($groupedMenus, $item['menu_id']);

            if ($parentId == '0' || $parentId === 0) {
                $row['component'] = 'Layout';
                $row['path'] = '/' . ltrim($item['path'], '/');

                if ($item['menu_type'] == 'M') {
                    $row['redirect'] = 'noRedirect';
                    $row['alwaysShow'] = true;
                    $row['children'] = $children;
                } elseif ($item['menu_type'] == 'C') {
                    $row['name'] = $item['name'] . 'Parent'; // 防止 VueRouter 的 name 查重覆盖导致 Layout 丢失
                    $row['path'] = '/';
                    $meta['component'] = $item['component'];
                    $row['children'] = [
                        [
                            'component' => $item['component'],
                            'hidden' => $item['hidden'] ? true : false,
                            'name' => $item['name'],
                            'path' => $item['path'],
                            'meta' => $meta
                        ]
                    ];
                    $meta = [];
                }
            } else {
                $row['path'] = $item['path'];

                if ($item['menu_type'] == 'M') {
                    $row['component'] = 'ParentView';
                    $row['redirect'] = 'noRedirect';
                    $row['alwaysShow'] = true;
                    $row['children'] = $children;
                } else {
                    $row['component'] = $item['component'];
                }

                if (!empty($children)) {
                    $row['children'] = $children;
                }
            }

            if (!empty($meta)) {
                $row['meta'] = $meta;
            }

            $tree[] = $row;
        }

        return $tree;
    }

    /**
     * 根据系统默认图纸递归计算最真实的绝对路由地址（/开头），从而免疫被改动层级后的串站问题
     */
    private function assignAbsolutePaths(&$originalGrouped, $parentId, $parentPath, &$allMenusDict)
    {
        if (!isset($originalGrouped[$parentId])) {
            return;
        }

        foreach ($originalGrouped[$parentId] as $item) {
            $mId = $item['menu_id'];
            $path = $item['path'];

            if ($parentId == '0' || $parentId === 0) {
                $absPath = '/' . ltrim($path, '/');
            } else {
                if (strpos($path, 'http') === 0 || strpos($path, '/') === 0) {
                    $absPath = $path;
                } else {
                    $absPath = $parentPath . '/' . ltrim($path, '/');
                }
            }

            // 写回原生绝对路由给字典
            $allMenusDict[$mId]['path'] = $absPath;

            // 继续深挖
            $this->assignAbsolutePaths($originalGrouped, $mId, $absPath, $allMenusDict);
        }
    }
}
