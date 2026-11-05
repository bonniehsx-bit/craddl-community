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

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\helpers\DefaultDataHelper;
use app\helpers\OptionHelper;
use app\helpers\SerializeHelper;
use app\models\BaseModel;

/**
 * 设置页(uniapp others/setting)的菜单配置。
 *
 * ⛔ 存独立的 option 键,**不要**并进 user_center_setting ——
 *    那边保存是整个 JSON 覆盖,两个后台页面同时开着编辑,后保存的会把先保存的整个冲掉。
 *
 * ⛔ 落库前必须过一遍 normalize()。后台 UI 里那些"删除按钮不显示""开关置灰"只是**提示**,
 *    绕过前端直接 POST 就没了。合规项(隐私政策/用户协议/注销账号)被藏起来的代价是
 *    几个月后提审被拒,而且当时不会有任何报错 —— 这种事必须在服务端兜住。
 */
class SettingPageSettingForm extends BaseModel
{
    public $page_data;

    public function rules()
    {
        return [
            [['page_data'], 'string'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $data = SerializeHelper::decode($this->page_data);
        if (!is_array($data)) {
            return $this->apiResponse(ApiCode::CODE_ERROR, '配置格式不正确');
        }
        OptionHelper::set('setting_page_setting', self::normalize($data), \Yii::$app->mall->id);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['page_data' => self::resolved(\Yii::$app->mall->id)]);
    }

    /** 恢复出厂:直接清掉配置,读取时自然回落到默认清单 */
    public function reset()
    {
        OptionHelper::set('setting_page_setting', null, \Yii::$app->mall->id);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '已恢复默认菜单', [
            'page_data' => DefaultDataHelper::settingPage(),
        ]);
    }

    /**
     * 读取生效配置。没配过、或配置被清空 → 默认清单。
     * 读出来也要 normalize:代码里新增了内置项时,老配置里没有那一项,
     * 不补的话新功能对存量商城永远不出现。
     */
    public static function resolved(int $mallId): array
    {
        $saved = OptionHelper::get('setting_page_setting', $mallId);
        if (!is_array($saved) || empty($saved['groups'])) {
            return DefaultDataHelper::settingPage();
        }
        return self::normalize($saved);
    }

    /**
     * 把提交上来的配置校正成"合法"的样子。
     *
     * 三条硬约束:
     *  1. 内置项一个都不能少 —— 缺了就按默认补回去(可能是被删了,也可能是新版本新增的)
     *  2. locked 的内置项强制 is_show=1,且 locked 标记本身以代码为准,不认前端传的
     *  3. 内置项的 type/key 不可变;自定义项一律 type=link,没有 url 的直接丢掉
     */
    public static function normalize(array $data): array
    {
        $default = DefaultDataHelper::settingPage();

        // 默认清单里所有内置项:key => [组key, 原始定义]
        $builtinDefault = [];
        foreach ($default['groups'] as $g) {
            foreach ($g['list'] as $item) {
                $builtinDefault[$item['key']] = ['group' => $g['key'], 'item' => $item];
            }
        }

        $seen = [];
        $groups = [];
        foreach ((array)($data['groups'] ?? []) as $g) {
            if (!is_array($g) || empty($g['key'])) {
                continue;
            }
            $list = [];
            foreach ((array)($g['list'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = ($item['type'] ?? 'link') === 'builtin' ? 'builtin' : 'link';

                if ($type === 'builtin') {
                    $key = (string)($item['key'] ?? '');
                    // 认不出来的 key 直接丢:可能是老版本删掉的内置项,留着前端也分派不了
                    if (!isset($builtinDefault[$key]) || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $def = $builtinDefault[$key]['item'];
                    $list[] = [
                        'type'   => 'builtin',
                        'key'    => $key,
                        'name'   => self::str($item['name'] ?? '', $def['name'], 20),
                        'icon'   => self::str($item['icon'] ?? '', $def['icon'], 40),
                        'pic'    => self::str($item['pic'] ?? '', '', 255),
                        'value'  => self::str($item['value'] ?? '', '', 20),
                        'url'    => '',                      // 内置项的跳转由前端按 key 决定,不吃前端传的
                        // locked 以代码为准。锁定项强制显示 —— 这是本方法存在的主要理由
                        'locked' => (int)$def['locked'],
                        'is_show' => $def['locked'] ? 1 : (int)!empty($item['is_show']),
                    ];
                } else {
                    $url = self::str($item['url'] ?? '', '', 255);
                    if ($url === '') {
                        continue;   // 自定义项没配链接 = 点了没反应,不如不存
                    }
                    $list[] = [
                        'type'   => 'link',
                        'key'    => '',
                        'name'   => self::str($item['name'] ?? '', '未命名', 20),
                        'icon'   => self::str($item['icon'] ?? '', '', 40),
                        'pic'    => self::str($item['pic'] ?? '', '', 255),
                        'value'  => self::str($item['value'] ?? '', '', 20),
                        'url'    => $url,
                        'locked' => 0,
                        'is_show' => (int)!empty($item['is_show']),
                    ];
                }
            }
            $groups[] = [
                'key'     => self::str($g['key'], '', 40),
                'name'    => self::str($g['name'] ?? '', '未命名分组', 20),
                'is_show' => (int)!empty($g['is_show']),
                'list'    => $list,
            ];
        }

        // 补回被删掉/新增的内置项:回到它在默认清单里的那个组;组也没了就补一个组
        foreach ($builtinDefault as $key => $meta) {
            if (isset($seen[$key])) {
                continue;
            }
            $idx = null;
            foreach ($groups as $i => $g) {
                if ($g['key'] === $meta['group']) {
                    $idx = $i;
                    break;
                }
            }
            if ($idx === null) {
                foreach ($default['groups'] as $dg) {
                    if ($dg['key'] === $meta['group']) {
                        $groups[] = ['key' => $dg['key'], 'name' => $dg['name'], 'is_show' => 1, 'list' => []];
                        $idx = count($groups) - 1;
                        break;
                    }
                }
            }
            if ($idx !== null) {
                $groups[$idx]['list'][] = $meta['item'];
            }
        }

        return ['groups' => $groups];
    }

    private static function str($v, $fallback, int $max): string
    {
        $v = is_scalar($v) ? trim((string)$v) : '';
        if ($v === '') {
            return $fallback;
        }
        return mb_substr($v, 0, $max);
    }
}
