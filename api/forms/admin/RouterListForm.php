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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\MallMenuOption;

// 新增引用
use app\models\MallPlugin;
use app\models\Menu;
use app\models\RoleMenu;
use Yii;

class RouterListForm extends BaseModel
{
    /**
     * 获取动态路由 (合并 SaaS 个性化配置)
     */
    public function getRouters()
    {
        // -----------------------------------------------------------------
        // 1. 获取当前商城已开通的插件
        // -----------------------------------------------------------------
        $mallId = Yii::$app->mall->id;
        $plugins = MallPlugin::enabledPlugins($mallId);

        // -----------------------------------------------------------------
        // 2. 根据用户角色获取菜单ID集合
        // -----------------------------------------------------------------
        $identity = Yii::$app->admin->identity;
        $menuIds = [];

        if ($identity->isMallSuper()) {
            // 超管 / 商城主账号：本店(插件限制)全部菜单（FOUNDER 即本店全部菜单）
            $menuIds = Menu::idsByPlugins($plugins);
        } else {
            // 操作员：需同时满足 [插件限制] AND [角色权限]
            $menuIds = RoleMenu::find()->alias('r')
                ->innerJoin(['m' => Menu::tableName()], 'm.menu_id=r.menu_id')
                ->andWhere(['m.plugin' => $plugins])
                ->andWhere(['r.role_id' => $identity->role_id])
                ->select('r.menu_id')
                ->column();
        }

        // -----------------------------------------------------------------
        // 3. 查询系统原始菜单
        // -----------------------------------------------------------------
        $allMenus = Menu::find()
            ->andWhere(['menu_id' => $menuIds])
            ->andWhere(['menu_type' => ['M', 'C']])
            ->select([
                'parent_id', 'menu_id', 'menu_name', 'icon', 'hidden', 'is_cache',
                'link', 'path', 'component', 'breadcrumb', 'show_tag', 'active_menu',
                'menu_type', 'name', 'plugin', 'order_num'
            ])
            ->orderBy('order_num asc')
            ->asArray()
            ->all();

        // -----------------------------------------------------------------
        // 3.5 【统一重构】：直接计算真实绝对路径，杜绝相对路径拖拽导致的前端拼接碰撞
        // -----------------------------------------------------------------
        $originalGrouped = [];
        $allMenusDict = [];
        foreach ($allMenus as &$m) {
            $originalGrouped[$m['parent_id']][] = $m;
            $allMenusDict[$m['menu_id']] = &$m;
        }
        $this->assignAbsolutePaths($originalGrouped, '0', '', $allMenusDict);

        // -----------------------------------------------------------------
        // 4. 【核心新增】融合商户个性化配置
        // -----------------------------------------------------------------
        // 查询当前商城的配置
        $options = MallMenuOption::find()
            ->where(['mall_id' => $mallId])
            ->indexBy('menu_id') // 以 menu_id 为 key，方便查找
            ->asArray()
            ->all();

        if (!empty($options)) {
            foreach ($allMenus as &$menu) {
                $mId = $menu['menu_id'];
                if (isset($options[$mId])) {
                    $opt = $options[$mId];

                    // 1. 覆盖名称
                    if (!empty($opt['custom_name'])) {
                        $menu['menu_name'] = $opt['custom_name'];
                    }

                    // 2. 覆盖隐藏状态
                    if ($opt['is_hide'] == 1) {
                        $menu['hidden'] = 1;
                    }

                    // 3. 覆盖排序
                    if (isset($opt['custom_sort']) && $opt['custom_sort'] !== null) {
                        $menu['order_num'] = intval($opt['custom_sort']);
                    }

                    // 4. 【新增】覆盖路由地址
                    if (!empty($opt['custom_path'])) {
                        $menu['path'] = $opt['custom_path'];
                    }

                    // 5. 【新增】覆盖父级ID (移动菜单位置)
                    // 必须确保新父级ID在当前列表中存在，否则菜单会消失（变成了孤儿）
                    if (!empty($opt['custom_parent_id'])) {
                        $menu['parent_id'] = $opt['custom_parent_id'];
                    }
                }
            }
            unset($menu); // 断开引用

            // 重新排序 (因为商户可能修改了 order_num)
            // 使用 array_multisort 按 order_num 升序排列
            $orderNums = array_column($allMenus, 'order_num');
            array_multisort($orderNums, SORT_ASC, $allMenus);
        }

        // -----------------------------------------------------------------
        // 5. 构建路由树
        // -----------------------------------------------------------------
        $groupedMenus = [];
        foreach ($allMenus as $menu) {
            $groupedMenus[$menu['parent_id']][] = $menu;
        }

        $routerTree = $this->buildRouterTree($groupedMenus, '0');

        $response = ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $routerTree);
        $response['has_im'] = false;
        return $response;
    }

    /**
     * 递归构建路由树 (逻辑保持不变)
     */
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
                'title' => $item['menu_name'], // 这里已经是被 custom_name 覆盖过的名称了
                'icon' => $item['icon'],
                'is_cache' => $item['is_cache']??0,
                'link' => $item['link'] ?? null,
                'breadcrumb' => $item['breadcrumb'] ? true : false,
                'activeMenu' => $item['active_menu']
            ];
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

            $this->assignAbsolutePaths($originalGrouped, $mId, $absPath, $allMenusDict);
        }
    }
}