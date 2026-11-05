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

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchCertificate;

class MchListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $keyword;
    public $status;
    public $date_range;
    public $type; // 1=供应商, 2=门店, 3=酒店

    public function rules()
    {
        return [
            [['type'], 'integer'],
            [['page', 'limit', 'status'], 'integer'],
            [['keyword'], 'string'],
            [['date_range'], 'safe'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 构建基础查询
        $query = Mch::find()->alias('m')->where([
            'm.mall_id' => \Yii::$app->mall->id,
            'm.is_delete' => 0,
        ]);

        // 2. 预加载关联数据
        // user: 店长信息
        // 注：酒店业务字段已迁出 mch 列表，由 hotel 插件独立的「酒店开通申请」页查看
        $query->with(['user']);

        // 3. 关键词搜索
        // 搜索范围：商户ID、商户名、联系手机、店长昵称
        if ($this->keyword) {
            $query->leftJoin(['u' => User::tableName()], 'm.user_id = u.id');
            $query->andWhere([
                'or',
                ['=', 'm.id', $this->keyword],
                ['like', 'm.mch_name', $this->keyword],
                ['=', 'm.user_id', $this->keyword],
                ['like', 'u.nickname', $this->keyword],
                ['like', 'm.mobile', $this->keyword],
            ]);
        }

        // 4. 筛选条件
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['m.status' => $this->status]);
        }

        if ($this->date_range && is_array($this->date_range) && count($this->date_range) == 2) {
            $query->andWhere(['between', 'm.created_time', $this->date_range[0] . ' 00:00:00', $this->date_range[1] . ' 23:59:59']);
        }

        if ($this->type) {
            $query->andWhere(['m.type' => $this->type]);
        }

        // 5. 分页查询
        // select * 可能会比较大，建议只查列表需要的字段，但在后台管理端通常容忍度较高
        $list = $query->orderBy(['m.id' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        // 5.1 批量附带证照(一次查询按 mch_id 分组,避免 N+1)——供审核页展示
        $certMap = [];
        $mchIds = array_column($list, 'id');
        if (!empty($mchIds)) {
            $certs = MchCertificate::find()
                ->where(['mch_id' => $mchIds, 'mall_id' => \Yii::$app->mall->id, 'is_delete' => 0])
                ->orderBy(['id' => SORT_ASC])
                ->asArray()->all();
            foreach ($certs as $c) {
                $certMap[$c['mch_id']][] = [
                    'name'         => $c['name_snapshot'],
                    'image'        => $c['image'],
                    'valid_start'  => $c['valid_start'],
                    'valid_end'    => $c['valid_end'],
                    'is_long_term' => (int)$c['is_long_term'],
                    'preset_key'   => $c['preset_key'],
                ];
            }
        }

        // 6. 数据格式化
        foreach ($list as &$item) {
            // 格式化店长信息
            $item['user_info'] = $item['user'] ? [
                'nickname' => $item['user']['nickname'],
                'avatar_url' => $item['user']['avatar_url'],
            ] : null;

            // 格式化类型文本
            $item['type_text'] = $this->getTypeText($item['type']);

            // 已提交证照(图+有效期),老商户无记录时前端回退 business_license 列
            $item['certificates'] = $certMap[$item['id']] ?? [];

            // 清理冗余字段，减小报文体积
            unset($item['user']);
        }
        unset($item);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'pagination' => $pagination,
            ],
        ];
    }

    /**
     * 获取类型文本
     */
    private function getTypeText($type)
    {
        $map = [
            Mch::TYPE_UP_STORE   => '供应商',
            Mch::TYPE_DOWN_STORE => '门店/团购',
            Mch::TYPE_HOTEL      => '酒店/民宿',
            Mch::TYPE_INDIVIDUAL => '个人租户',
        ];
        return $map[$type] ?? '未知类型';
    }
}