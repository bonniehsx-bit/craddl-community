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

namespace app\plugins\mch\forms\mall\apply;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\helpers\MchApplyFieldRegistry;
use app\plugins\mch\models\Mch;
use app\plugins\mch\services\MchApplyConfigService;

/**
 * 后台「入驻字段配置」读取/保存(按主体类型)。v1 只开放 启用/必填,核心字段锁定。
 */
class ApplyFieldForm extends BaseModel
{
    private function allowedTypes(): array
    {
        return [Mch::TYPE_UP_STORE, Mch::TYPE_DOWN_STORE, Mch::TYPE_INDIVIDUAL];
    }

    public function getConfig($type)
    {
        $type = (int)$type;
        if (!in_array($type, $this->allowedTypes(), true)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '非法主体类型'];
        }
        // 后台管理页要看到**完整**字段目录,所以不应用统一地址的过滤 —— 否则开启后
        // 那四个字段会从表格里凭空消失,管理员以为丢了,保存时还可能把它们的配置误清。
        // 改为照常列出,再给地址字段打上 managed_by_default_address 标记,由前端提示"已被统一地址接管"。
        $fields = MchApplyConfigService::getFieldConfig($this->mall_id, $type, false);
        $useDefaultAddress = MchApplyConfigService::useDefaultAddress($this->mall_id);
        if ($useDefaultAddress) {
            $managed = array_flip(MchApplyConfigService::DEFAULT_ADDRESS_FIELDS);
            foreach ($fields as &$f) {
                $f['managed_by_default_address'] = isset($managed[$f['field_key']]) ? 1 : 0;
            }
            unset($f);
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '获取成功',
            'data' => [
                'type'   => $type,
                'groups' => MchApplyFieldRegistry::groups(),
                'fields' => $fields,
                // 前端据此在表格上方给一条整体提示
                'use_default_address' => $useDefaultAddress ? 1 : 0,
            ],
        ];
    }

    public function save($post)
    {
        $type = (int)($post['type'] ?? 0);
        if (!in_array($type, $this->allowedTypes(), true)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '非法主体类型'];
        }
        $fields = $post['fields'] ?? [];
        if (!is_array($fields)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '字段配置格式错误'];
        }
        MchApplyConfigService::saveFieldConfig($this->mall_id, $type, $fields);
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功'];
    }
}
