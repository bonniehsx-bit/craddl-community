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
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/5/28
 * Time: 10:17
 * Note:
 */

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\helpers\NounHelper;
use app\models\BaseModel;
use app\models\Noun;


class NounListForm extends BaseModel
{
    public $key;
    public $page;
    public $value;

    public function rules()
    {
        return [
            [['page'], 'integer'],
            [['key', 'value'], 'string']
        ];
    }

    /**
     * 返回分组词库（核心 + 各已启用插件）+ 当前商城已配置值
     * 结构: groups[ { source, label, items[ {key, default, value} ] } ]
     */
    public function getList()
    {
        // 当前商城已配置的词： key => value
        $rows = Noun::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->select(['key', 'value'])
            ->asArray()
            ->all();
        $configured = array_column($rows, 'value', 'key');

        $groups = [];
        foreach (NounHelper::groupedDictionary() as $g) {
            $meta = $g['meta'] ?? [];
            $items = [];
            foreach ($g['nouns'] as $key => $defaultWord) {
                $m = $meta[$key] ?? [];
                $items[] = [
                    'key'          => $key,
                    'default'      => $defaultWord,
                    'value'        => isset($configured[$key]) ? $configured[$key] : '',
                    'type'         => $m['type'] ?? NounHelper::TYPE_PHRASE,
                    'label'        => $m['label'] ?? '',
                    'placeholders' => $m['placeholders'] ?? (object)[], // token=>说明；空时给 {} 便于前端判断
                    'example'      => $m['example'] ?? '',
                ];
            }
            $groups[] = [
                'source' => $g['source'],
                'label'  => $g['label'],
                'items'  => $items,
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['groups' => $groups]);
    }
}