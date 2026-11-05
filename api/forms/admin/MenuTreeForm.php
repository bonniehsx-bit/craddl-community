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
use app\models\MallPlugin;
use app\models\Menu;
use app\models\RoleMenu;
use Yii;

class MenuTreeForm extends BaseModel
{
    /**
     * 获取菜单树
     * 对应原控制器 actionMenuTree 和 loopMenuTree 的组合逻辑
     */
    public function getTree()
    {
        // -----------------------------------------------------------------
        // 1. 确定当前环境允许的插件 (SaaS 逻辑)
        // -----------------------------------------------------------------
        // 当前商城已开通插件（基座 mall + 已购）。原代码超管/非超管两分支查询完全相同(死分支)，
        // 这里统一为一条——菜单范围始终按当前商城插件收口。
        $plugins = MallPlugin::enabledPlugins(Yii::$app->mall->id);

        // -----------------------------------------------------------------
        // 2. 确定当前用户允许看到的 MenuID 集合 (RBAC 逻辑)
        // -----------------------------------------------------------------
        $menuIds = [];
        if (!Yii::$app->admin->identity->isMallSuper()) {
            // 操作员：只能在自己角色已有的菜单范围内授权（防越权：给不出自己没有的）
            $menuIds = RoleMenu::find()
                ->andWhere(['role_id' => Yii::$app->admin->identity->role_id])
                ->select('menu_id')
                ->column();
        } else {
            // 超管 / 商城主账号：本店(插件限制)全部菜单都可分配
            $menuIds = Menu::idsByPlugins($plugins);
        }

        // -----------------------------------------------------------------
        // 3. 查询数据 (一次性查出，优化 N+1 问题)
        // -----------------------------------------------------------------
        // 注意：这里为了后续构建树方便，先查出原始字段，在 buildTree 里转成 id/label
        // 必须包含 parent_id 用于递归
        $allMenus = Menu::find()
            ->andFilterWhere(['menu_id' => $menuIds]) // 对应原代码 loopMenuTree 的 filter
            ->orderBy('order_num asc')
            ->select(['menu_id', 'menu_name', 'parent_id'])
            ->asArray()
            ->all();

        // -----------------------------------------------------------------
        // 4. 构建树形结构 (字段严格匹配：id, label, children)
        // -----------------------------------------------------------------
        $tree = $this->buildTree($allMenus, '0');

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '操作成功', $tree);
    }

    /**
     * 递归构建树
     * 严格保证输出字段为：id, label, children
     * @param array $elements 所有菜单数据
     * @param string $parentId 父级ID
     * @return array
     */
    private function buildTree(array &$elements, $parentId)
    {
        $branch = [];

        foreach ($elements as $element) {
            // 确保类型一致，数据库里 parent_id 是字符串
            if (strval($element['parent_id']) === strval($parentId)) {

                // 【关键点】：字段映射，必须和你原来的 loopMenuTree select 别名一致
                $node = [
                    'id'    => $element['menu_id'],   // menu_id as id
                    'label' => $element['menu_name'], // menu_name as label
                ];

                // 递归查找子节点
                $children = $this->buildTree($elements, $element['menu_id']);

                // 只有当有子节点时才添加 children 字段 (保持原逻辑风格)
                if (!empty($children)) {
                    $node['children'] = $children;
                }

                $branch[] = $node;
            }
        }

        return $branch;
    }
}