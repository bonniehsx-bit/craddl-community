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

namespace app\forms\admin;

use app\core\ApiCode;
use app\models\Admin;
use app\models\AdminMall;
use app\models\BaseModel;
use app\models\Mall;
use app\models\MallPlugin;
use app\services\MallProvisionService;
use Yii;

/**
 * 移交/转让商城归属（仅平台超管）。
 *
 * owner_mode='new'  → 为商户新建主账号(FOUNDER)并交付本店；
 * owner_mode='exist'→ 把本店归属移交给一个已有的商户主账号(FOUNDER)。
 *
 * 落点：mall.admin_id = 新 owner + AdminMall(founder)。FOUNDER 即"商城级超管"，
 * 进店后对本店全权（由 enter() + FOUNDER 分支保证），无需依赖按钮级 perms。
 */
class MallTransferForm extends BaseModel
{
    public $mall_id;
    public $owner_mode;     // 'new' | 'exist'
    public $owner_admin_id; // exist 模式：目标主账号 id
    public $owner_username;
    public $owner_password;
    public $owner_mobile;
    public $owner_nickname;

    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            [['mall_id', 'owner_admin_id'], 'integer'],
            [['owner_mode', 'owner_username', 'owner_password', 'owner_mobile', 'owner_nickname'], 'safe'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;
        if (!$admin || $admin->admin_type != Admin::ADMIN_TYPE_SUPER) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '仅平台超管可移交商城归属');
        }
        $mall = Mall::findOne(['id' => $this->mall_id, 'is_delete' => 0]);
        if (!$mall) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商城不存在');
        }
        $oldOwnerId = (int)$mall->admin_id; // 移交前的旧 owner，移交后需回收其访问

        $plugins = MallPlugin::find()->where(['mall_id' => $mall->id, 'is_delete' => 0])->select('plugin')->column();

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($this->owner_mode === 'exist') {
                $ownerId = (int)$this->owner_admin_id;
                $target = Admin::findOne(['id' => $ownerId, 'is_delete' => 0]);
                if (!$target) {
                    $tx->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, '目标账号不存在');
                }
                if ($target->admin_type != Admin::ADMIN_TYPE_FOUNDER) {
                    $tx->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, '只能移交给商户主账号(FOUNDER)');
                }
                // 该账号原本被绑死在另一单店 → 转为多商城模式，并保留对原店的访问(补 AdminMall)
                if ($target->mall_id > 0 && (int)$target->mall_id !== (int)$mall->id) {
                    MallProvisionService::bindFounder($target->mall_id, $target->id);
                    $target->updateAttributes(['mall_id' => 0]);
                }
            } else {
                // new：新建商户主账号 + 配店长角色
                $res = MallProvisionService::createOwnerAccount(
                    $mall->id, $this->owner_username, $this->owner_password,
                    $this->owner_mobile, $this->owner_nickname, $admin->id
                );
                if (!$res['ok']) {
                    $tx->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, $res['msg']);
                }
                $ownerId = $res['admin_id'];
                MallProvisionService::provisionShopAdminRole($mall, $ownerId, $plugins, $admin->id);
            }

            // 归属落到新 owner + AdminMall(founder)
            $mall->updateAttributes(['admin_id' => $ownerId]);
            MallProvisionService::bindFounder($mall->id, $ownerId);

            // 回收旧 owner 对本店的访问：删其本店 AdminMall 关联；若被绑死在本店则解绑。
            // 否则旧店主仍能通过 enter() 的 mall_id 匹配 / AdminMall 进店，造成移交不独占。
            if ($oldOwnerId && $oldOwnerId !== (int)$ownerId) {
                AdminMall::deleteAll(['mall_id' => $mall->id, 'admin_id' => $oldOwnerId]);
                $oldOwner = Admin::findOne($oldOwnerId);
                if ($oldOwner && (int)$oldOwner->mall_id === (int)$mall->id) {
                    $oldOwner->updateAttributes(['mall_id' => 0]);
                }
            }

            $tx->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '移交成功');
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('[移交商城失败] ' . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, '移交失败：' . $e->getMessage());
        }
    }

    /**
     * 候选商户主账号列表（供"选择已有"下拉）。
     */
    public static function founderList()
    {
        return Admin::find()
            ->where(['admin_type' => Admin::ADMIN_TYPE_FOUNDER, 'is_delete' => 0])
            ->select(['id', 'username', 'nickname', 'mobile', 'mall_id'])
            ->orderBy('id desc')
            ->asArray()
            ->all();
    }
}
