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

namespace app\forms\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\GoodsServiceTag;
use Yii;

/**
 * 商品服务标签的维护 + 取用(平台后台和商家后台共用这一个类)
 *
 * 谁能改哪一批,由控制器调 `asOwner()` 定死,**不从请求里读** ——
 * 平台后台 asOwner(0) 管平台标签,商家后台 asOwner(自己的 mch_id) 管自建标签。
 * 若把它做成 public 字段跟着 `$form->attributes = request` 一起灌进来,
 * 商家改个参数就能删掉平台标签。
 *
 * 商品编辑器取可选项走 getSelectable():平台标签 + 该商品所属商家自建的。
 */
class ServiceTagForm extends BaseModel
{
    public $id;
    public $name;
    public $sort;
    public $status;

    /** 编辑器取可选标签时传:这个商品属于哪个商家(0 = 平台自营) */
    public $goods_mch_id;

    /** @var int 本次操作能改动的那一批标签的归属,只能由控制器设置 */
    protected $ownerMchId = 0;

    public function rules()
    {
        return [
            [['id', 'sort', 'status', 'goods_mch_id'], 'integer'],
            [['name'], 'string', 'max' => 12],
            [['name'], 'trim'],
        ];
    }

    /**
     * 我管的是谁的标签(平台后台 0;商家后台传他自己的 mch_id)
     */
    public function asOwner(int $mchId): self
    {
        $this->ownerMchId = max(0, $mchId);
        return $this;
    }

    private function mallId(): int
    {
        return (int)Yii::$app->mall->id;
    }

    /**
     * 维护页:我自己这批标签(含停用的,停用的也要能在后台看到并改回来)
     */
    public function getList()
    {
        $list = GoodsServiceTag::find()
            ->where(['mall_id' => $this->mallId(), 'mch_id' => $this->ownerMchId, 'is_delete' => 0])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'sort' => (int)$row['sort'],
                    'status' => (int)$row['status'],
                ];
            }, $list),
            // 商家端要知道平台铺了哪些(他不能改,但选商品标签时能用),顺带告诉前端一声
            'platform_count' => $this->ownerMchId > 0
                ? (int)GoodsServiceTag::find()->where(['mall_id' => $this->mallId(), 'mch_id' => 0, 'status' => GoodsServiceTag::STATUS_ON, 'is_delete' => 0])->count()
                : 0,
            'show_limit' => GoodsServiceTag::SHOW_LIMIT,
        ]);
    }

    /**
     * 商品编辑器:这个商品能勾的标签(平台的 + 它所属商家自建的),停用的不给
     */
    public function getSelectable()
    {
        // 商家后台不看请求里的 goods_mch_id:他编辑的永远是自己的商品
        $scopeMchId = $this->ownerMchId > 0 ? $this->ownerMchId : (int)$this->goods_mch_id;

        $list = GoodsServiceTag::visibleQuery($this->mallId(), $scopeMchId)->asArray()->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'is_own' => (int)$row['mch_id'] > 0 ? 1 : 0, // 1 = 商家自建,前端标个「自建」区分平台标签
                ];
            }, $list),
            'show_limit' => GoodsServiceTag::SHOW_LIMIT,
        ]);
    }

    /**
     * 新增 / 编辑。编辑时按归属复查,改不到别人的标签。
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $name = trim((string)$this->name);
        if ($name === '') {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请填写标签名称');
        }

        if ($this->id) {
            $tag = GoodsServiceTag::findOne([
                'id' => $this->id,
                'mall_id' => $this->mallId(),
                'mch_id' => $this->ownerMchId,
                'is_delete' => 0,
            ]);
            if (!$tag) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '标签不存在或无权修改');
            }
        } else {
            $tag = new GoodsServiceTag();
            $tag->mall_id = $this->mallId();
            $tag->mch_id = $this->ownerMchId;
        }

        // 同一批里重名没意义(详情页会出现两个一样的标签)
        $dup = GoodsServiceTag::find()
            ->where(['mall_id' => $this->mallId(), 'mch_id' => $this->ownerMchId, 'name' => $name, 'is_delete' => 0])
            ->andFilterWhere(['!=', 'id', $tag->id])
            ->exists();
        if ($dup) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '已经有同名标签了');
        }

        $tag->name = $name;
        $tag->sort = (int)$this->sort;
        $tag->status = (int)$this->status === GoodsServiceTag::STATUS_OFF
            ? GoodsServiceTag::STATUS_OFF : GoodsServiceTag::STATUS_ON;

        if (!$tag->save()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($tag));
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', ['id' => (int)$tag->id]);
    }

    /**
     * 删除(软删)。已经勾了这个标签的商品不用动:详情页按「还在、且启用」筛,删掉自然就不显示了。
     */
    public function delete()
    {
        $tag = GoodsServiceTag::findOne([
            'id' => $this->id,
            'mall_id' => $this->mallId(),
            'mch_id' => $this->ownerMchId,
            'is_delete' => 0,
        ]);
        if (!$tag) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '标签不存在或无权删除');
        }
        $tag->is_delete = 1;
        if (!$tag->save()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $this->getErrorMsg($tag));
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }
}
