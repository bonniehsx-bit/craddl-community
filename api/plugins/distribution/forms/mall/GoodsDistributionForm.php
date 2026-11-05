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

namespace app\plugins\distribution\forms\mall;

use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Level;
use app\plugins\distribution\models\DistributionGoods;
use yii\helpers\Json;
use Yii;

class GoodsDistributionForm extends BaseModel
{
    /**
     * 获取商品的分销配置
     * @param int $goodsId 商品ID (新建时为0)
     * @return array
     */
    public function getSetting($goodsId)
    {
        $mallId = Yii::$app->mall->id;

        // 1. 初始化默认结构
        // 没有商品配置行 = 按开启处理:算佣金时(DistributionListener)只有「有配置行且关闭」才跳过,
        // 这里若默认成关闭,编辑页就显示关着,运营随手保存一次商品就把分销写成关 —— 悄悄停发、不报错
        $data = [
            'is_commission_open'     => 1,
            'commission_rule_type'   => 'default',
            'commission_rules'       => [],
            'level_commission_rules' => [],
        ];

        // 2. 加载插件独立表中的商品配置
        if ($goodsId > 0) {
            $model = DistributionGoods::findOne([
                'goods_id' => $goodsId,
                'is_delete' => 0,
                'mall_id' => $mallId,
            ]);

            if ($model) {
                $data['is_commission_open']   = (int)$model->is_commission_open;
                $data['commission_rule_type'] = $model->commission_rule_type ?: 'default';

                if (!empty($model->commission_rules)) {
                    try {
                        $rules = Json::decode($model->commission_rules);
                        $data['commission_rules'] = is_array($rules) ? $rules : [];
                    } catch (\Exception $e) {
                        $data['commission_rules'] = [];
                    }
                }

                if (!empty($model->level_commission_rules)) {
                    try {
                        $obj = Json::decode($model->level_commission_rules);
                        $data['level_commission_rules'] = is_array($obj) ? $obj : [];
                    } catch (\Exception $e) {
                        $data['level_commission_rules'] = [];
                    }
                }
            }
        }

        // 3. 获取平台层级模板（供前端渲染"填空题"）
        $template = $this->getPlatformLevelTemplate();

        // 商品统一自定义规则模板
        if (empty($data['commission_rules'])) {
            $data['commission_rules'] = $template;
        } else {
            $data['commission_rules'] = $this->alignRulesToTemplate($data['commission_rules'], $template);
        }

        // 4. 附带会员等级列表，供"按会员等级独立配置"使用（分销商等级=会员等级）
        $distLevels = Level::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'enabled' => 1])
            ->orderBy(['level' => SORT_ASC])
            ->select(['id', 'name', 'level'])
            ->asArray()->all();

        $data['dist_levels']    = $distLevels;
        $data['level_template'] = $template;

        // 5. 补全 level_commission_rules 结构：确保每个现存分销商等级都有一套规则占位
        $filled = [];
        foreach ($distLevels as $lv) {
            $key = (string)$lv['id'];
            if (!empty($data['level_commission_rules'][$key]) && is_array($data['level_commission_rules'][$key])) {
                $filled[$key] = $this->alignRulesToTemplate($data['level_commission_rules'][$key], $template);
            } else {
                $filled[$key] = $this->emptyTemplate($template);
            }
        }
        $data['level_commission_rules'] = $filled;

        return $data;
    }

    /**
     * 保存分销配置到插件独立表
     * @param int $goodsId 商品ID
     * @param array $data 前端提交的数据
     * @return bool
     * @throws \Exception
     */
    public function save($goodsId, $data)
    {
        if (empty($goodsId)) {
            throw new \Exception('商品ID不能为空');
        }

        $mallId = Yii::$app->mall->id;

        $model = DistributionGoods::findOne([
            'goods_id' => $goodsId,
            'is_delete' => 0,
            'mall_id' => $mallId,
        ]);

        if (!$model) {
            $model = new DistributionGoods();
            $model->goods_id = $goodsId;
            $model->mall_id = $mallId;
            $model->created_at = time();
        }

        $model->updated_at         = time();
        $model->is_commission_open = isset($data['is_commission_open']) ? (int)$data['is_commission_open'] : 0;
        $model->commission_rule_type = isset($data['commission_rule_type']) ? $data['commission_rule_type'] : 'default';

        // 保存商品统一自定义规则
        if ($model->commission_rule_type === DistributionGoods::RULE_TYPE_CUSTOM && !empty($data['commission_rules'])) {
            $model->commission_rules = Json::encode($this->normalizeRules($data['commission_rules']));
        } else {
            $model->commission_rules = null;
        }

        // 保存按分销商等级独立配置的规则
        if ($model->commission_rule_type === DistributionGoods::RULE_TYPE_BY_LEVEL && !empty($data['level_commission_rules'])) {
            $cleaned = [];
            foreach ((array)$data['level_commission_rules'] as $levelId => $rules) {
                if (!is_array($rules)) continue;
                $cleaned[(string)$levelId] = $this->normalizeRules($rules);
            }
            $model->level_commission_rules = Json::encode($cleaned);
        } else {
            $model->level_commission_rules = null;
        }

        if (!$model->save()) {
            throw new \Exception('分销配置保存失败: ' . json_encode($model->getErrors()));
        }

        return true;
    }

    /**
     * 从 distribution_setting 中取出平台层级模板（按 level_count 截断）
     */
    private function getPlatformLevelTemplate()
    {
        $setting = OptionHelper::get('distribution_setting', Yii::$app->mall->id);

        $levels = [];
        if ($setting && !empty($setting['levels']) && is_array($setting['levels'])) {
            $levels = $setting['levels'];
        } else {
            $levels = [['level' => 1, 'name' => '一级分销', 'reward_rate' => '']];
        }

        // 按全局 level_count 裁剪（与分销设置页一致）
        $count = isset($setting['level_count']) ? (int)$setting['level_count'] : count($levels);
        if ($count > 0 && $count < count($levels)) {
            $levels = array_slice($levels, 0, $count);
        }

        // 模板保留 level 和 name（name 用于前端标签），reward_rate 作为"全局默认"回传供提示
        $tpl = [];
        foreach ($levels as $i => $item) {
            $tpl[] = [
                'level'        => isset($item['level']) ? (int)$item['level'] : ($i + 1),
                'name'         => $item['name'] ?? ('第' . ($i + 1) . '级'),
                'reward_rate'  => '',
                'global_rate'  => isset($item['reward_rate']) ? (string)$item['reward_rate'] : '',
            ];
        }
        return $tpl;
    }

    /**
     * 把已存在的规则按模板补齐（保证 level_count 层级一致）
     */
    private function alignRulesToTemplate(array $rules, array $template): array
    {
        $out = [];
        foreach ($template as $idx => $tpl) {
            $old = $rules[$idx] ?? null;
            $out[] = [
                'level'       => $tpl['level'],
                'name'        => $tpl['name'],
                'reward_rate' => ($old && isset($old['reward_rate'])) ? $old['reward_rate'] : '',
                'global_rate' => $tpl['global_rate'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * 根据模板生成一份全空规则
     */
    private function emptyTemplate(array $template): array
    {
        $out = [];
        foreach ($template as $tpl) {
            $out[] = [
                'level'       => $tpl['level'],
                'name'        => $tpl['name'],
                'reward_rate' => '',
                'global_rate' => $tpl['global_rate'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * 保存前规范化：仅保留 level/name/reward_rate，空 reward_rate 保留为空串
     */
    private function normalizeRules(array $rules): array
    {
        $clean = [];
        foreach ($rules as $rule) {
            if (!isset($rule['level'])) continue;
            $rate = $rule['reward_rate'] ?? '';
            if ($rate !== '' && $rate !== null) $rate = (string)$rate;
            $clean[] = [
                'level'       => (int)$rule['level'],
                'name'        => $rule['name'] ?? '',
                'reward_rate' => $rate,
            ];
        }
        return $clean;
    }
}
