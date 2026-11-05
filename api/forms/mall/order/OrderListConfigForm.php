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

namespace app\forms\mall\order;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\services\order\OrderListConfig;
use app\services\order\OrderTypeRegistry;
use Yii;

/**
 * 后台「订单列表配置」读写(每商城一份,存 jxmall_option)。
 *
 * search():返回当前配置(已填默认)+ 可选订单类型清单(供前端勾选)。
 * save():规整后写回 OptionHelper。
 */
class OrderListConfigForm extends BaseModel
{
    public $config;

    public function rules()
    {
        return [
            [['config'], 'safe'],
        ];
    }

    public function search()
    {
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'config' => OrderListConfig::get((int)Yii::$app->mall->id),
            'types'  => OrderTypeRegistry::all(),
        ]);
    }

    public function save()
    {
        $cfg = is_array($this->config) ? $this->config : [];

        // 规整 status_tabs:只收 key / name / status / enabled
        $tabs = [];
        foreach (($cfg['status_tabs'] ?? []) as $t) {
            if (!is_array($t) || !isset($t['status'])) {
                continue;
            }
            $tabs[] = [
                'key'     => (string)($t['key'] ?? ''),
                'name'    => trim((string)($t['name'] ?? '')),
                'status'  => (int)$t['status'],
                'enabled' => !empty($t['enabled']),
            ];
        }
        // 全空(异常)→ 回退默认,避免存出一个 C 端无 tab 可显示的坏配置
        if (empty($tabs)) {
            $tabs = OrderListConfig::defaultStatusTabs();
        }

        // 规整 included_types(去空、去重)
        $included = [];
        foreach (($cfg['included_types'] ?? []) as $k) {
            if ($k !== '' && $k !== null) {
                $included[] = (string)$k;
            }
        }

        OptionHelper::set(OrderListConfig::OPTION_KEY, [
            'status_tabs'    => $tabs,
            'included_types' => array_values(array_unique($included)),
        ], (int)Yii::$app->mall->id);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }
}
