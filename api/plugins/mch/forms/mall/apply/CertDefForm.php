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
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchCertDef;
use app\plugins\mch\services\MchApplyConfigService;

/**
 * 后台「证照库」增删改查。预置证照(营业执照, is_system=1)只可编辑/停用,不可删。
 */
class CertDefForm extends BaseModel
{
    public function getList()
    {
        // 先惰性补齐预置证照,保证营业执照一定在列
        MchApplyConfigService::ensureCertDefaults($this->mall_id);

        $rows = MchCertDef::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $list = array_map(function ($r) {
            return [
                'id'            => (int)$r->id,
                'name'          => $r->name,
                'preset_key'    => $r->preset_key,
                'types'         => $r->getTypeList(),
                'is_required'   => (int)$r->is_required,
                'need_validity' => (int)$r->need_validity,
                'is_system'     => (int)$r->is_system,
                'is_enabled'    => (int)$r->is_enabled,
                'sort'          => (int)$r->sort,
            ];
        }, $rows);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '获取成功', 'data' => ['list' => $list]];
    }

    public function save($post)
    {
        $id = (int)($post['id'] ?? 0);
        if ($id > 0) {
            $m = MchCertDef::find()->where(['id' => $id, 'mall_id' => $this->mall_id, 'is_delete' => 0])->one();
            if (!$m) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '证照不存在'];
            }
        } else {
            $m = new MchCertDef();
            $m->mall_id = $this->mall_id;
            $m->is_system = 0;     // 后台新增的一律自定义证照
            $m->preset_key = null;
        }

        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请填写证照名称'];
        }

        // 适用主体类型:取交集 [1,2,4],至少一项
        $types = array_values(array_unique(array_map('intval', (array)($post['types'] ?? []))));
        $types = array_values(array_filter($types, function ($t) {
            return in_array($t, [Mch::TYPE_UP_STORE, Mch::TYPE_DOWN_STORE, Mch::TYPE_INDIVIDUAL], true);
        }));
        if (empty($types)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请选择适用主体类型'];
        }

        $m->name          = $name;
        $m->types         = implode(',', $types);
        $m->is_enabled    = array_key_exists('is_enabled', $post) ? (!empty($post['is_enabled']) ? 1 : 0) : 1;
        // 停用即必填归零,避免"停用却必填"的矛盾态(与字段侧口径一致)
        $m->is_required   = ($m->is_enabled && !empty($post['is_required'])) ? 1 : 0;
        $m->need_validity = !empty($post['need_validity']) ? 1 : 0;
        $m->sort          = (int)($post['sort'] ?? 0);
        $m->save(false);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功', 'data' => ['id' => (int)$m->id]];
    }

    public function remove($id)
    {
        $m = MchCertDef::find()->where(['id' => (int)$id, 'mall_id' => $this->mall_id, 'is_delete' => 0])->one();
        if (!$m) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '证照不存在'];
        }
        if ((int)$m->is_system === 1) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '预置证照不可删除,可改为「停用」'];
        }
        $m->is_delete = 1;
        $m->save(false);
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '删除成功'];
    }
}
