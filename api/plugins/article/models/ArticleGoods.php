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

namespace app\plugins\article\models;

use app\models\BaseActiveRecord;

/**
 * 文章挂商品
 *
 * @property int $id
 * @property int $mall_id
 * @property int $article_id
 * @property int $goods_id
 * @property int $sort
 * @property string $recommend 推荐语
 * @property int $is_main 底部购买条主推
 * @property int $is_delete
 */
class ArticleGoods extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_article_goods}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'article_id', 'goods_id'], 'required'],
            [['mall_id', 'article_id', 'goods_id', 'sort', 'is_main', 'is_delete'], 'integer'],
            [['recommend'], 'string', 'max' => 60],
        ];
    }
}
