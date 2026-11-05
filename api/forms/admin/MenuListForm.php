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
use app\models\Admin;
use app\models\BaseModel;
use app\models\MallMenuOption; // 引用配置表
use app\models\MallPlugin;
use app\models\Menu;
use app\models\Plugin;
use Yii;

class MenuListForm extends BaseModel
{
    public $menu_name;
    public $hidden;

    public function rules()
    {
        return [
            [['menu_name'], 'string'],
            [['hidden'], 'integer'],
            [['menu_name'], 'trim'],
        ];
    }

    public function getList()
    {
        $plugins = ['mall'];
        if (isset(Yii::$app->mall)) {
            $mallId = Yii::$app->mall->id;
            $purchasedPlugins = MallPlugin::find()->alias('m')
                ->innerJoin(['p' => Plugin::tableName()], 'p.name=m.plugin')
                ->select('m.plugin')
                ->andWhere(['m.mall_id' => $mallId])
                ->andWhere(['m.is_delete' => 0, 'p.is_delete' => 0])
                ->column();

            $plugins = array_merge($plugins, $purchasedPlugins);
        } else {
            // 如果不在指定商城环境下，查询所有已安装且未删除的系统插件
            $installedPlugins = Plugin::find()
                ->select('name')
                ->andWhere(['is_delete' => 0])
                ->column();
            $plugins = array_merge($plugins, $installedPlugins);
        }

        $query = Menu::find()
            ->where(['is_delete' => 0])
            ->andWhere(['plugin' => $plugins]);

        if ($this->menu_name) {
            $query->andWhere(['like', 'menu_name', $this->menu_name]);
        }

        if ($this->hidden !== null && $this->hidden !== '' && $this->hidden !== 'undefined') {
            $query->andWhere(['hidden' => $this->hidden]);
        }

        $list = $query->orderBy('order_num ASC, id ASC')
            ->asArray()
            ->all();

        // =========================================================
        // 【新增计算】：使用绝对路径系统计算真实 URL
        // =========================================================
        $originalGrouped = [];
        $allMenusDict = [];
        foreach ($list as &$m) {
            $originalGrouped[$m['parent_id']][] = $m;
            $allMenusDict[$m['menu_id']] = &$m;
        }
        $this->assignAbsolutePaths($originalGrouped, '0', '', $allMenusDict);

        // =========================================================
        // 【SaaS 核心逻辑】融合商户个性化配置
        // =========================================================
        // 只有非超管（商户/员工），或者你希望超管也能看到当前商城的装修效果时执行
        // 这里假设我们始终融合当前商城的配置
        if (isset(Yii::$app->mall)) {
            $mallId = Yii::$app->mall->id;

            $options = MallMenuOption::find()
                ->where(['mall_id' => $mallId])
                ->indexBy('menu_id')
                ->asArray()
                ->all();

            if (!empty($options)) {
                foreach ($list as &$item) {
                    $mId = $item['menu_id'];
                    if (isset($options[$mId])) {
                        $opt = $options[$mId];

                        // 标记该菜单已被自定义
                        $item['is_customized'] = true;
                        $item['original_name'] = $item['menu_name']; // 保留原名供参考

                        if (!empty($opt['custom_name'])) {
                            $item['menu_name'] = $opt['custom_name'];
                        }
                        if ($opt['is_hide'] == 1) {
                            $item['hidden'] = 1;
                        }
                        if (isset($opt['custom_sort']) && $opt['custom_sort'] !== null) {
                            $item['order_num'] = intval($opt['custom_sort']);
                        }
                    }
                }
                unset($item);

                // 重新排序
                $orderNums = array_column($list, 'order_num');
                array_multisort($orderNums, SORT_ASC, $list);
            }
        }
        // =========================================================

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', $list);
    }

    /**
     * 根据系统默认图纸递归计算最真实的绝对路由地址
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