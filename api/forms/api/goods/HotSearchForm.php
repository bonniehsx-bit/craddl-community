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
// 文件路径: app/forms/api/goods/HotSearchForm.php
namespace app\forms\api\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\SearchLog;
use Yii;

class HotSearchForm extends BaseModel
{
    public function get()
    {
        // 统计最近30天的热门搜索
        $timeLimit = time() - 30 * 86400;

        $list = SearchLog::find()
            ->select(['keyword', 'COUNT(*) as search_count'])
            ->where(['mall_id' => Yii::$app->mall->id])
            ->andWhere(['>=', 'created_at', $timeLimit])
            ->groupBy('keyword')
            ->orderBy(['search_count' => SORT_DESC])
            ->limit(10) // 取前10名
            ->asArray()
            ->all();

        // 格式化为前端需要的 { name: 'xxx' } 结构
        $hotTags = array_map(function($item) {
            return ['name' => $item['keyword']];
        }, $list);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $hotTags]);
    }
}