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
namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Yii;

class MchInitForm extends BaseModel
{
    public function getData()
    {
        $user = Yii::$app->user->identity;

        // [多商户隔离] 只列当前商城的店铺:一个用户可能在多个商城都有店,
        // 若不限定 mall,A 商城的 app 会露出并可切到 B 商城的店(切过去也会被 filter 的 mall_id 拒),
        // 造成列表与后端鉴权不一致。此处与 MchFilter/BossFilter 的 mall_id 兜底对齐。
        $mallId = (int)(Yii::$app->mall->id ?? 0);

        // 1. 查找用户作为店长的店铺
        $managerMchsQuery = Mch::find()->select('id, mch_name, logo, type')
            ->where(['user_id' => $user->id, 'is_delete' => 0, 'status' => 1]);
        if ($mallId) {
            $managerMchsQuery->andWhere(['mall_id' => $mallId]);
        }

        // 2. 查找用户作为店员的店铺
        $clerkMchsQuery = Mch::find()->alias('m')->select('m.id, m.mch_name, m.logo, m.type')
            ->innerJoin(['mc' => MchClerk::tableName()], 'm.id = mc.mch_id')
            ->where([
                'mc.user_id' => $user->id,
                'mc.is_delete' => 0,
                'mc.status' => 1,
                'm.is_delete' => 0,
                'm.status' => 1,
            ]);
        if ($mallId) {
            $clerkMchsQuery->andWhere(['m.mall_id' => $mallId]);
        }

        // 3. 合并两个查询结果
        $allMchs = array_merge($managerMchsQuery->asArray()->all(), $clerkMchsQuery->asArray()->all());

        // 4. 去重，防止一个用户既是店长又是店员导致重复
        $mchList = [];
        $tempIds = [];
        foreach ($allMchs as $mch) {
            if (!in_array($mch['id'], $tempIds)) {
                $mchList[] = $mch;
                $tempIds[] = $mch['id'];
            }
        }

        if (empty($mchList)) {
            // 区分「待审核」「已驳回」「未申请」，让前端显示对应提示
            $pendingMchQuery = Mch::find()
                ->where(['user_id' => $user->id, 'is_delete' => 0])
                ->andWhere(['in', 'status', [0, 2]])
                ->orderBy(['id' => SORT_DESC]);
            if ($mallId) {
                $pendingMchQuery->andWhere(['mall_id' => $mallId]);
            }
            $pendingMch = $pendingMchQuery->one();

            if ($pendingMch) {
                if ($pendingMch->status == 0) {
                    return $this->apiResponse(ApiCode::CODE_FAIL, '您的入驻申请正在审核中，请耐心等待', [
                        'mch_status' => 0,
                    ]);
                } else { // status == 2
                    $reason = $pendingMch->remarks ?: '未提供具体原因';
                    return $this->apiResponse(ApiCode::CODE_FAIL, "您的申请已被驳回，原因：{$reason}，请重新提交", [
                        'mch_status' => 2,
                    ]);
                }
            }

            return $this->apiResponse(ApiCode::CODE_FAIL, '您还没有店铺，现在去申请入驻吗？', [
                'mch_status' => null,
            ]);
        }

        // 5. 确定默认店铺
        // 优先使用用户在 mch 表中的 user_id 对应的店铺作为默认
        $defaultMch = null;
        $mchOwnedByUser = $managerMchsQuery->asArray()->one();
        if ($mchOwnedByUser) {
            $defaultMch = $mchOwnedByUser;
        } else {
            // 如果他不是任何店的店长，就用列表里的第一个
            $defaultMch = $mchList[0];
        }


        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'mch_list' => $mchList,
            'default_mch' => $defaultMch,
        ]);
    }
}