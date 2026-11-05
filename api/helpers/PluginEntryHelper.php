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

namespace app\helpers;

use app\models\MallMenuOption;
use app\models\Menu;

/**
 * 插件入口路由解析 —— 算出「点这个插件应该跳到哪个前端路由」。
 *
 * ## 为什么必须有这个东西
 * 后台侧边栏的路由由 RouterListForm 生成,规则是:
 *   ① 先按**原始 parent_id** 把菜单树的 path 拼成绝对路径(父绝对路径 + 子 path)
 *   ② 再用 mall_menu_option 里商户自己改的 custom_path 覆盖
 *
 * 而「应用中心」和「极速工作台」这两个入口,原来各自拿插件名硬拼
 * `/plugins/{插件名}/{菜单path}`。只要商户在后台动过菜单(改路径 / 拖层级),
 * 或者插件的菜单 path 本来就是相对写法,拼出来的地址就和真实注册的路由对不上,
 * 点进去直接 404 —— 而且因为多数插件的菜单 path 恰好写成 `/plugins/xxx/index`
 * 这种绝对形式、碰巧能对上,所以表现为「有的插件能点、有的点了 404」,
 * 极易被误判成"客户把菜单改坏了"。实际上商户压根改不到 component,
 * 他改的是路由,而入口没跟着走。
 *
 * 结论:入口地址必须和侧边栏走**同一套算法**,不能各拼各的。
 */
class PluginEntryHelper
{
    /**
     * 批量取插件入口路由。
     *
     * @param string[]   $plugins        插件名
     * @param int        $mallId
     * @param int[]|null $allowedMenuIds 操作员角色能看到的菜单;null = 不限(超管 / 商城主账号)。
     *                                   给了就只在这些页面里挑入口 —— 否则会把操作员带到他没权限的页面
     * @return array {插件名: 绝对路由}  没有可见菜单的插件不会出现在结果里
     */
    public static function resolve(array $plugins, int $mallId, ?array $allowedMenuIds = null): array
    {
        $plugins = array_values(array_unique(array_filter($plugins)));
        if (!$plugins) {
            return [];
        }

        // 路由要靠父级回溯,所以必须取**全量**菜单,不能只取这些插件自己的
        $all = Menu::find()
            ->where(['is_delete' => 0])
            ->select(['menu_id', 'parent_id', 'path', 'menu_type', 'plugin', 'order_num', 'hidden'])
            ->orderBy('order_num asc')
            ->asArray()
            ->all();
        if (!$all) {
            return [];
        }

        // ① 按原始层级算绝对路径(与 RouterListForm::assignAbsolutePaths 同口径)
        $grouped = [];
        $dict = [];
        foreach ($all as &$m) {
            $grouped[$m['parent_id']][] = &$m;
            $dict[$m['menu_id']] = &$m;
        }
        unset($m);
        self::assignAbsolutePaths($grouped, '0', '', $dict);

        // ② 套商户自定义:路径直接覆盖,隐藏的不能当入口,排序会影响"第一个"是谁
        $options = MallMenuOption::find()
            ->where(['mall_id' => $mallId])
            ->indexBy('menu_id')
            ->asArray()
            ->all();
        foreach ($dict as $mId => &$m) {
            $opt = $options[$mId] ?? null;
            if (!$opt) {
                continue;
            }
            if (!empty($opt['custom_path'])) {
                $m['path'] = $opt['custom_path'];
            }
            if (isset($opt['is_hide']) && $opt['is_hide'] !== null && $opt['is_hide'] !== '') {
                $m['hidden'] = (int)$opt['is_hide'] === 1 ? 1 : 0;
            }
            if (isset($opt['custom_sort']) && $opt['custom_sort'] !== null && $opt['custom_sort'] !== '') {
                $m['order_num'] = (int)$opt['custom_sort'];
            }
        }
        unset($m);

        // ③ 每个插件按**侧边栏的树形顺序**取第一个能落地的页面
        //
        // 这里原来是把该插件所有层级的菜单拉平、只比 order_num 取最小的那个 —— 错在
        // order_num 只在**同一层级内**有意义,跨层级比没有意义。真实后果(plaza):
        // 侧边栏第一项是顶层的「数据概览」(order=1),而「租户管理」藏在三级目录下、
        // order 恰好是 0,于是点进应用中心落到了租户管理,和用户在侧边栏看到的第一项对不上。
        //
        // 正确的顺序就是侧边栏的顺序:从该插件的根菜单起,每层按 order_num 排,
        // 深度优先,第一个 C 类可见页面即入口。
        $want = array_flip($plugins);
        $allowed = $allowedMenuIds === null ? null : array_flip(array_map('intval', $allowedMenuIds));

        // 先按插件把菜单归堆,并找出每个插件的根(父节点不属于本插件的那些)
        $mine = [];
        foreach ($dict as $mId => $m) {
            $p = (string)($m['plugin'] ?? '');
            if ($allowed !== null && !isset($allowed[(int)$mId])) {
                continue;   // 操作员没这个菜单的权限:侧边栏里也看不到,不能当入口
            }
            if ($p !== '' && isset($want[$p])) {
                $mine[$p][$mId] = $m;
            }
        }

        $result = [];
        foreach ($mine as $p => $nodes) {
            $roots = [];
            foreach ($nodes as $mId => $m) {
                if (!isset($nodes[$m['parent_id']])) {
                    $roots[] = $m;   // 父级不在本插件里 → 它就是本插件的一个根
                }
            }
            // 子节点索引:同样只在本插件范围内找,免得顺着别的插件的菜单走出去
            $childrenOf = [];
            foreach ($nodes as $m) {
                if (isset($nodes[$m['parent_id']])) {
                    $childrenOf[$m['parent_id']][] = $m;
                }
            }
            $hit = self::firstPage($roots, $childrenOf);
            if ($hit !== null) {
                $result[$p] = '/' . ltrim((string)$hit['path'], '/');
            }
        }

        return $result;
    }

    /**
     * 深度优先找第一个可落地的页面级菜单(与侧边栏渲染顺序一致)。
     *
     * 每进一层都要重新按 order_num 排一次 —— 上面第 ② 步套商户自定义时会改写
     * order_num,而数组本身还是按数据库原始顺序排的,不重排就白改了。
     *
     * @return array|null
     */
    private static function firstPage(array $nodes, array $childrenOf)
    {
        // order_num 相同时**保持原顺序**(即数据库 order_num asc 的返回顺序),
        // 不要另加 menu_id 之类的兜底 —— 侧边栏那边是 array_multisort,PHP 8 起排序稳定,
        // 相等元素同样保持数据库顺序。自作主张换个兜底规则,就会出现
        // 「入口跳到 A、侧边栏第一项却是 B」的不一致(xwy 的「数据概览」和「基础设置」
        // 都是 order=1,正是这种情况)。
        usort($nodes, function ($a, $b) {
            return (int)($a['order_num'] ?? 0) <=> (int)($b['order_num'] ?? 0);
        });

        foreach ($nodes as $n) {
            // 隐藏的节点连同它整棵子树都不该作为入口:侧边栏里根本看不见
            if ((int)($n['hidden'] ?? 0) === 1) {
                continue;
            }
            // M=目录、F=按钮都不是能落地的页面,但目录要继续往下找
            if (($n['menu_type'] ?? '') === 'C' && trim((string)$n['path']) !== '') {
                return $n;
            }
            $kids = $childrenOf[$n['menu_id']] ?? [];
            if ($kids) {
                $hit = self::firstPage($kids, $childrenOf);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }
        return null;
    }

    /** 单个插件的入口,取不到返回空串(调用方自行决定兜底) */
    public static function one(string $plugin, int $mallId): string
    {
        return self::resolve([$plugin], $mallId)[$plugin] ?? '';
    }

    /**
     * 把菜单树的 path 拼成绝对路径。
     * 与 RouterListForm::assignAbsolutePaths 保持一致:顶级加 `/`,子级接在父绝对路径后,
     * 但子级自己已是 `/` 或 http 开头时原样保留(那种写法本身就是完整路由)。
     */
    private static function assignAbsolutePaths(array &$grouped, $parentId, string $parentPath, array &$dict)
    {
        if (!isset($grouped[$parentId])) {
            return;
        }
        foreach ($grouped[$parentId] as $item) {
            $mId = $item['menu_id'];
            $path = (string)$item['path'];

            if ($parentId === '0' || $parentId === 0) {
                $abs = '/' . ltrim($path, '/');
            } elseif (strpos($path, 'http') === 0 || strpos($path, '/') === 0) {
                $abs = $path;
            } else {
                $abs = $parentPath . '/' . ltrim($path, '/');
            }

            $dict[$mId]['path'] = $abs;
            self::assignAbsolutePaths($grouped, $mId, $abs, $dict);
        }
    }
}
