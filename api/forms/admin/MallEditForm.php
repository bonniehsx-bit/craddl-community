<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 21:05
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
use app\helpers\DateHelper;
use app\models\Admin;
use app\models\BaseModel;
use app\models\Mall;
use app\models\MallPlugin;
use app\services\MallProvisionService;

class MallEditForm extends BaseModel
{

    public $id;
    public $name;
    public $is_has_expire;
    public $end_at;
    public $is_expire;
    public $is_stop;
    public $logo;
    public $detail;
    public $plugins;

    // 商城归属（仅新建生效）：owner_mode='new' 时新建商户主账号并交付；其它/缺省=创建者自管
    public $owner_mode;
    public $owner_username;
    public $owner_password;
    public $owner_mobile;
    public $owner_nickname;

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['is_has_expire', 'is_expire', 'is_stop', 'id'], 'integer'],
            // end_at 先归一化再校验,顺序不能反(Yii 按 rules 顺序执行)。
            // 空值放行:选「永久授权」时前端传空字符串 '',若不 skipOnEmpty,integer 会拒空串
            // → 报「End At 必须是整数」,连永久授权都建不了。空值由 save() 的 is_has_expire 分支兜底。
            [['end_at'], 'filter', 'filter' => [DateHelper::class, 'normalizeTimestamp']],
            [['end_at'], 'integer', 'skipOnEmpty' => true],
            [['name'], 'string', 'max' => 45],
            [['logo', 'detail'], 'string', 'max' => 255],
            [['plugins'], 'safe'],
            [['owner_mode', 'owner_username', 'owner_password', 'owner_mobile', 'owner_nickname'], 'safe'],
            [['is_has_expire', 'is_stop', 'is_expire'], 'default', 'value' => 0],
            [['end_at'], 'default', 'value' => 0],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        /**
         * @var Admin $admin
         */
        $admin = \Yii::$app->admin->identity;
        if (!$admin) {
            return $this->apiResponse(ApiCode::CODE_NOT_LOGIN, '请先登录');
        }
        if ($admin->admin_type == Admin::ADMIN_TYPE_OPERATOR) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '您无权限添加或修改商城信息');
        }
        if ($admin->admin_type == Admin::ADMIN_TYPE_FOUNDER) {
            $count = Mall::find()->where(['admin_id' => $admin->id, 'is_delete' => 0])->count();
            if ($count > $admin->mall_count) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '商城数量超过限制');
            }
            if ($count == $admin->mall_count) {
                if (!$this->id) {
                    return $this->apiResponse(ApiCode::CODE_FAIL, '商城数量超过限制');
                }
            }
        }
        // 建店链横跨 Mall/MallPlugin/Admin/Role/RoleMenu/AdminRole/AdminMall 多表，整体包事务，
        // 任一步失败整体回滚，杜绝"商城建了但主账号/店长角色没配齐"的半成品。
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $mall = null;
            if ($this->id) {
                $mall = Mall::findOne(['is_delete' => 0, 'id' => $this->id]);
                // 非超管编辑：必须是自己名下的商城，防跨商城改他人店(name/有效期/插件授权)
                if ($mall && $admin->admin_type != Admin::ADMIN_TYPE_SUPER
                    && (int)$mall->admin_id !== (int)$admin->id) {
                    $transaction->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, '无权操作该商城');
                }
            }
            if (!$mall) {
                $mall = new Mall();
                $mall->admin_id = \Yii::$app->admin->identity->id;
            }
            $mall->attributes = $this->attributes;
            if ($this->is_has_expire) {
                if (!$this->end_at) {
                    $transaction->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, '请完善商城有效期');
                }
                // 前端 el-date-picker value-format=timestamp 传的是 Unix 时间戳(秒,见 entry.vue 提交时 /1000)。
                // 直接转整数;原 strtotime("1782345678") 把时间戳当日期串解析会返回 false → end_at 整数校验报"必须是整数"。
                $mall->end_at = (int)$this->end_at;
            } else {
                $mall->end_at = 0; // 永久有效:清空到期时间
            }
            if (!$mall->save()) {
                $transaction->rollBack();
                return $this->responseErrorMsg($mall);
            }

            // 同步授权插件：先清后插。统一用 $mall->id —— 新建时 $this->id 还是空的，
            // 旧代码用 $this->id 会把插件 mall_id 存成 null，导致【新建商城勾的插件根本没绑上】。
            // 另：用 is_array 判定（而非 count>0），让"全不选"也能正确清空，不再残留旧授权。
            if (is_array($this->plugins)) {
                MallPlugin::deleteAll(['mall_id' => $mall->id]);
                foreach ($this->plugins as $item) {
                    $mallPlugin = new MallPlugin();
                    $mallPlugin->mall_id = $mall->id;
                    $mallPlugin->plugin = $item;
                    if (!$mallPlugin->save()) {
                        \Yii::warning($mallPlugin->getFirstErrors());
                    }
                }
            }

            if ($this->id) {
                $transaction->commit();
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '修改成功');
            }

            // ===== 新建商城：确定/交付"商城主账号"(owner) =====
            $ownerId   = $mall->admin_id; // 缺省 = 创建者(超管)自管
            $createdBy = \Yii::$app->admin->identity->id;
            if ($this->owner_mode === 'new') {
                $res = MallProvisionService::createOwnerAccount(
                    $mall->id, $this->owner_username, $this->owner_password,
                    $this->owner_mobile, $this->owner_nickname, $createdBy
                );
                if (!$res['ok']) {
                    $transaction->rollBack();
                    return $this->apiResponse(ApiCode::CODE_FAIL, $res['msg']);
                }
                $ownerId = $res['admin_id'];
                $mall->updateAttributes(['admin_id' => $ownerId]); // 归属落到商户主账号
                MallProvisionService::provisionShopAdminRole(
                    $mall, $ownerId, is_array($this->plugins) ? $this->plugins : [], $createdBy
                );
            }

            // owner 作为 founder 绑定本店（多对多关联）
            MallProvisionService::bindFounder($mall->id, $ownerId);

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '创建成功');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('[建店事务失败] ' . $e->getMessage());
            return $this->apiResponse(ApiCode::CODE_FAIL, '操作失败：' . $e->getMessage());
        }
    }
}
