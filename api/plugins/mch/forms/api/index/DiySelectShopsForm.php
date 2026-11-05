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

namespace app\plugins\mch\forms\api\index;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use Yii;

/**
 * DIY「精选商家」组件数据接口
 *
 * 入参 mch_ids（后台勾选的商户ID，逗号分隔字符串或数组），
 * 按后台勾选的顺序返回这些商户的展示卡片；被删除/下架的自动剔除。
 *
 * GET /plugin/mch/api/index/diy-select-shops?mch_ids=1,2,3
 */
class DiySelectShopsForm extends BaseModel
{
    public $mch_ids;

    public function rules()
    {
        return [
            [['mch_ids'], 'safe'],
        ];
    }

    public function getList()
    {
        // 1. 归一化 mch_ids → 有序、去重的正整数数组（保留后台勾选顺序）
        $ids = $this->mch_ids;
        if (is_string($ids)) {
            $ids = $ids === '' ? [] : explode(',', $ids);
        }
        if (!is_array($ids)) {
            $ids = [];
        }
        $orderedIds = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }
        if (empty($orderedIds)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => []]);
        }

        // 2. 查这些商户（限本商城、审核通过、未删除）
        $rows = Mch::find()
            ->where([
                'id'        => $orderedIds,
                'mall_id'   => (int)Yii::$app->mall->id,
                'is_delete' => 0,
                'status'    => 1,
            ])
            ->select(['id', 'mch_name', 'logo', 'address', 'location_address', 'rating'])
            ->asArray()
            ->all();

        // 3. 组装卡片，再按后台勾选顺序排序
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['id']] = [
                'id'      => (int)$r['id'],
                'name'    => (string)$r['mch_name'],
                'logo'    => $r['logo'] ?: '',
                'address' => $r['location_address'] ?: ($r['address'] ?: ''),
                'rating'  => isset($r['rating']) && $r['rating'] !== null ? (float)$r['rating'] : 0,
            ];
        }
        $list = [];
        foreach ($orderedIds as $id) {
            if (isset($map[$id])) {
                $list[] = $map[$id];
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}
