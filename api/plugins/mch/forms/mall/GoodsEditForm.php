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

namespace app\plugins\mch\forms\mall;

use app\forms\goods\BaseGoodsEditForm;
use app\models\Goods;
use app\plugins\mch\models\Mch;
use Yii;
use yii\db\ActiveQuery;

/**
 * 平台后台 → 商品管理 → 代某商户新增/编辑商品
 *
 * 与商家自端 {@see \app\plugins\mch\forms\admin\goods\GoodsEditForm} 的差异：
 *  1. mch_id 由前端传入（平台运营选择目标商户），不是从 Yii::$app->mch 取
 *  2. 平台特权：自动通过审核 (is_check=1)、自动上架 (status=1)、不需要并发锁
 *  3. getDetail 不限定 mch_id（平台运营可以查任意商户的商品）
 *  4. 返回字段名是 'detail' 而不是 'info'（兼容历史前端 GoodsEditForm.vue）
 *
 * 共享部分（来自 BaseGoodsEditForm）：rules / Pipeline 流程 / 老数据兼容 / mch_cats / 事件分发 / 删除 / 上下架
 */
class GoodsEditForm extends BaseGoodsEditForm
{
    /**
     * 前端传入的目标商户 ID（平台运营选择代谁上传）
     * @var int
     */
    public $mch_id;

    /**
     * 平台特权：自动审核通过 + 自动上架。
     * 这两个字段在父类没有声明，必须在子类显式声明，才能让 GoodsBaseSavePipe
     * 通过 $goods->attributes = $form->attributes 同步到 Goods 模型。
     */
    public $is_check = 1;

    /**
     * 平台抽点(商品级),同样必须在此显式声明才能落库 —— 父类 BaseGoodsEditForm 没有这个属性。
     * 此前前端 components/GoodsEditForm.vue 一直在提交它,后端却因为没声明而静默丢弃:
     * 运营在平台后台给某商品设了抽点、页面提示"保存成功",实际一直按商户全局费率抽佣。
     * 留空(''/null)= 继承商户全局费率,由 GoodsBaseSavePipe 统一归 NULL(读取端 isset() 才回退)。
     */
    public $concession_rate;

    public function __construct($config = [])
    {
        // 父类的 status 默认是 0，平台后台需要默认 1（自动上架）
        $this->status = 1;
        parent::__construct($config);
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            [['mch_id'], 'required'],
            [['mch_id', 'is_check'], 'integer'],
            [['status', 'is_check'], 'default', 'value' => 1],
            // 抽点是百分比,越界会直接算错商家货款,这里卡死 0~100(留空不校验,交由 pipe 归 NULL)
            [['concession_rate'], 'number', 'min' => 0, 'max' => 100],
        ]);
    }

    /**
     * 从前端传入的 mch_id 解析商户实例（替代默认的 Yii::$app->mch）
     */
    protected function resolveMch()
    {
        if (!$this->mch_id) {
            return null;
        }
        // mch_id 由前端传入,必须限定本商城 —— 否则 A 商城运营填个 B 商城的商户 id
        // 就能给对方建商品(mall_id 记成 A、mch_id 却是 B 的商户)。
        return Mch::findOne([
            'id' => $this->mch_id,
            'mall_id' => Yii::$app->mall->id,
            'is_delete' => 0,
        ]);
    }

    /**
     * 平台后台代理操作不需要并发锁（平台运营是受信任的）
     */
    protected function getLockKey(): ?string
    {
        return null;
    }

    /**
     * 平台后台代为编辑不触发重新审核
     */
    protected function shouldTriggerRecheckOnEdit(): bool
    {
        return false;
    }

    /**
     * 详情查询：不限定具体商户，只要 mch_id != 0 (排除平台自营商品) 即可
     * 平台运营可以查看【本商城】任意商户的商品 —— mall_id 必须带上,
     * 否则平台运营可跨商城读取/编辑/删除别的商城商户的商品(兄弟表单 GoodsReviewForm 已做此限定)。
     */
    protected function buildDetailQuery(): ActiveQuery
    {
        return Goods::find()->with([
            'info',
            'cat',
            'attrs' => function ($query) {
                $query->andWhere(['is_delete' => 0]);
            },
            'mch'
        ])->where([
            'id' => $this->id,
            'mall_id' => Yii::$app->mall->id,
            'is_delete' => 0,
        ])->andWhere(['!=', 'mch_id', 0]);
    }

    /**
     * 兼容历史前端：GoodsEditForm.vue 用 res.data.detail 读取，不是 res.data.info
     */
    protected function getDetailResponseKey(): string
    {
        return 'detail';
    }
}
