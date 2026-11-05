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

namespace app\models;

use yii\helpers\Json;

/**
 * 商品服务标签(详情页价格下面那排「✓ 全国包邮」)
 *
 * mch_id = 0 是平台的标签,本商城所有商品都能选;mch_id > 0 是某个商家自建的,
 * 只有他自己的商品能选、也只有他能改。详见建表迁移 m260920_100000。
 *
 * ⚠ 纯展示话术,不参与任何计算:选了「全国包邮」不会让订单真的不收运费。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id
 * @property string $name
 * @property int $sort
 * @property int $status
 * @property int $is_delete
 */
class GoodsServiceTag extends BaseActiveRecord
{
    const STATUS_ON = 1;
    const STATUS_OFF = 0;

    /** 详情页最多显示几个:那一排放不下会挤成两行,设计上只留一行 */
    const SHOW_LIMIT = 6;

    public static function tableName()
    {
        return '{{%goods_service_tag}}';
    }

    public function rules()
    {
        return [
            [['name'], 'required', 'message' => '标签名称不能为空'],
            [['name'], 'string', 'max' => 12, 'tooLong' => '标签名称最多 12 个字,太长详情页一行放不下'],
            [['name'], 'trim'],
            [['mall_id', 'mch_id', 'sort', 'status', 'is_delete'], 'integer'],
            [['status'], 'default', 'value' => self::STATUS_ON],
            [['sort'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => '标签名称',
            'sort' => '排序',
            'status' => '启用',
        ];
    }

    /**
     * 这个身份能选 / 能看到的标签:平台的 + 自己的
     *
     * @param int $mallId
     * @param int $mchId 0 = 只看平台标签(平台自营商品);>0 = 平台标签 + 该商家自建的
     * @param bool $onlyEnabled 只要启用的(C 端展示用;后台维护页要连停用的一起列)
     * @return \yii\db\ActiveQuery
     */
    public static function visibleQuery(int $mallId, int $mchId = 0, bool $onlyEnabled = true)
    {
        $query = self::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->andWhere(['mch_id' => $mchId > 0 ? [0, $mchId] : [0]])
            ->orderBy(['mch_id' => SORT_ASC, 'sort' => SORT_ASC, 'id' => SORT_ASC]);
        if ($onlyEnabled) {
            $query->andWhere(['status' => self::STATUS_ON]);
        }
        return $query;
    }

    /**
     * 商品存的标签 ID 数组(存的就是 ID,展示时现查名称 —— 改了标签名,已发布的商品跟着变)
     *
     * @param string|array|null $raw goods.service_tags 的原始值
     * @return int[]
     */
    public static function decodeIds($raw): array
    {
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            try {
                $list = Json::decode($raw);
            } catch (\Exception $e) {
                return [];
            }
        } else {
            return [];
        }
        if (!is_array($list)) {
            return [];
        }
        $ids = [];
        foreach ($list as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * 商品要存的标签:只留这个商品真能选的,按标签库的顺序存
     *
     * ⛔ 必须按 mall_id + mch_id 过滤:接口是商家也能调的,直接存前端传来的 ID,
     *    改一下请求就能把别家商家自建的标签(甚至别的商城的)挂到自己商品上。
     *
     * @param mixed $raw   前端传来的 ID 数组
     * @param int $mallId
     * @param int $mchId   商品所属商家,0 = 平台自营(只能选平台标签)
     * @return string|null JSON;一个都没选返回 null(列可空,别存 "[]" 这种脏值)
     */
    public static function sanitizeForGoods($raw, int $mallId, int $mchId): ?string
    {
        $ids = self::decodeIds($raw);
        if (!$ids) {
            return null;
        }
        $valid = self::visibleQuery($mallId, $mchId, false)
            ->andWhere(['id' => $ids])
            ->select('id')
            ->column();
        $valid = array_map('intval', $valid);
        return $valid ? Json::encode(array_values($valid)) : null;
    }

    /**
     * 商品详情页要显示的标签文字
     *
     * 按标签自己的排序出,不按商品里存的顺序 —— 运营调了标签排序,全商城一起变。
     * 停用、删掉、以及不属于本商城的 ID(商品换过商家、标签被删了)一律不显示。
     *
     * @param Goods|array $goods
     * @return string[]
     */
    public static function namesOfGoods($goods): array
    {
        $raw = is_array($goods) ? ($goods['service_tags'] ?? null) : ($goods->service_tags ?? null);
        $ids = self::decodeIds($raw);
        if (!$ids) {
            return [];
        }
        $mallId = (int)(is_array($goods) ? ($goods['mall_id'] ?? 0) : $goods->mall_id);
        return self::find()
            ->select('name')
            ->where(['id' => $ids, 'mall_id' => $mallId, 'status' => self::STATUS_ON, 'is_delete' => 0])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(self::SHOW_LIMIT)
            ->column();
    }
}
