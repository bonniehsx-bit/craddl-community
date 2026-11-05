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

namespace app\plugins\withdraw\forms\admin\channel;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\FundRegistry;
use Yii;

/**
 * 提现渠道 - 列表 / 详情
 */
class ChannelListForm extends BaseModel
{
    public $id;
    public $page = 1;
    public $limit = 20;
    public $keyword;
    public $status;
    public $user_type;

    public function rules()
    {
        return [
            [['id', 'page', 'limit', 'status'], 'integer'],
            [['keyword', 'user_type'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;
        $query = Channel::find()->where(['mall_id' => $mallId, 'is_delete' => 0]);

        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere(['like', 'name', $this->keyword]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => (int)$this->status]);
        }
        if ($this->user_type !== null && $this->user_type !== '') {
            // 渠道 user_types 是 json 文本，用 like 粗筛足够（数据量小）
            $query->andWhere(['like', 'user_types', $this->user_type]);
        }

        $rows = $query->orderBy('sort ASC, id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->all();

        $list = array_map([$this, 'formatRow'], $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $this->getPaginationInfo($pagination),
        ]);
    }

    public function getDetail()
    {
        $mallId = (int)Yii::$app->mall->id;
        $channel = Channel::findOne(['id' => $this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$channel) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '渠道不存在');
        }

        $detail = $this->formatRow($channel, true);
        // 附带该网关的进件字段，便于前端"资料配置"回显
        $detail['fund_onboarding_fields'] = FundRegistry::onboardingFields($mallId, $channel->fund_class);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', $detail);
    }

    /**
     * @param Channel $c
     * @param bool $full 详情模式返回全部可配置字段
     */
    private function formatRow(Channel $c, bool $full = false): array
    {
        $base = [
            'id'            => (int)$c->id,
            'name'          => $c->name,
            'icon'          => $c->icon,
            'subtitle'      => $c->subtitle,
            'fund_class'    => $c->fund_class,
            'destination'   => $c->destination,
            'user_types'    => $c->getUserTypeList(),
            'asset_types'   => $c->getAssetTypeList(),
            'fee_rate'      => $c->fee_rate,
            'tax_rate'      => $c->tax_rate,
            'min_amount'    => $c->min_amount,
            'arrival_desc'  => $c->arrival_desc,
            'need_audit'    => (int)$c->need_audit,
            'sort'          => (int)$c->sort,
            'status'        => (int)$c->status,
        ];

        if (!$full) {
            return $base;
        }

        return array_merge($base, [
            'color'               => $c->color,
            'fund_config'         => $c->getFundConfig(),
            'onboarding_override' => $c->getOnboardingOverride(),
            'max_amount'          => $c->max_amount,
            'multiple'            => $c->multiple,
            'day_limit_amount'    => $c->day_limit_amount,
            'day_limit_count'     => (int)$c->day_limit_count,
            'remark'              => $c->remark,
            // 提现规则自定义文案(后台编辑回显;留空=C端用默认规则)
            'rules'               => (string)$c->rules,
        ]);
    }
}
