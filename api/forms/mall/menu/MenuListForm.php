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
use app\models\BaseModel;
use app\models\MallMenuOption;
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

    /**
     * 根据系统默认图纸递归计算最真实的绝对路由地址（/开头），展示给商户
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

    public function getList()
    {
        $plugins = ['mall'];
        $mallId = Yii::$app->mall->id;
        $purchasedPlugins = MallPlugin::find()->alias('m')
            ->innerJoin(['p' => Plugin::tableName()], 'p.name=m.plugin')
            ->select('m.plugin')
            ->andWhere(['m.mall_id' => $mallId])
            ->andWhere(['m.is_delete' => 0, 'p.is_delete' => 0])
            ->column();

        $plugins = array_merge($plugins, $purchasedPlugins);

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

        // 【重构核心】：基于原始关系计算所有路由的绝对路径，暴露给前端表格展示真实 URL
        $originalGrouped = [];
        $allMenusDict = [];
        foreach ($list as &$m) {
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
            foreach ($list as &$item) {
                $mId = $item['menu_id'];
                if (isset($options[$mId])) {
                    $opt = $options[$mId];

                    $item['is_customized'] = true;
                    $item['original_name'] = $item['menu_name'];

                    if (!empty($opt['custom_name'])) {
                        $item['menu_name'] = $opt['custom_name'];
                    }
                    // 商户装修的显示/隐藏必须【双向】覆盖系统默认值:
                    // 原来只在 is_hide==1 时置隐藏,is_hide==0 分支什么都不做 → 系统表 hidden=1 的菜单
                    // (如"数据概览")被商户改成"显示"后,保存确实写进了 is_hide=0,但列表读取时仍回落
                    // 系统的 hidden=1,页面看起来"改了没生效"。与同文件 custom_sort/custom_parent_id
                    // 的"设了就信任沙盒值"口径保持一致。
                    if (isset($opt['is_hide']) && $opt['is_hide'] !== null && $opt['is_hide'] !== '') {
                        $item['hidden'] = (int)$opt['is_hide'] === 1 ? 1 : 0;
                    }
                    if (isset($opt['custom_sort']) && $opt['custom_sort'] !== null && $opt['custom_sort'] !== '') {
                        $item['order_num'] = intval($opt['custom_sort']);
                    }
                    // 商户装修使用的覆盖数据
                    if (isset($opt['custom_parent_id']) && $opt['custom_parent_id'] !== '') {
                        $item['parent_id'] = $opt['custom_parent_id'];
                        $item['is_customized'] = true;

                    }
                    if (!empty($opt['custom_path'])) {
                        $item['path'] = $opt['custom_path'];
                    }
                    if (isset($opt['is_home']) && $opt['is_home'] == 1) {
                        $item['is_home'] = 1;
                    }
                }
                
                // Add a simple default if missing
                if (!isset($item['is_home'])) {
                    $item['is_home'] = 0;
                }
            }
            unset($item);

            $orderNums = array_column($list, 'order_num');
            array_multisort($orderNums, SORT_ASC, $list);
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', $list);
    }
}
