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

namespace app\models;

use app\helpers\CacheHelper;
use yii\base\Model;
use yii\caching\DbDependency;
use yii\db\ActiveRecord;

class BaseActiveRecord extends ActiveRecord
{
    /**
     * 定义默认要隐藏的字段 (全局黑名单)
     * 子类可以覆盖这个方法来增加自己的敏感字段
     */
    protected function getHiddenFields()
    {
        return [];
    }

    /**
     * 统一重写 fields
     */
    public function fields()
    {
        $fields = parent::fields();

        // 获取当前模型定义的隐藏字段
        $hidden = $this->getHiddenFields();

        foreach ($hidden as $field) {
            unset($fields[$field]);
        }

        return $fields;
    }

    /**
     * 获取模型验证失败的第一条错误信息。
     */
    public function getErrorMsg(?Model $model = null)
    {
        $model = $model ?: $this;
        if ($model->hasErrors()) {
            $errors = $model->getFirstErrors();
            return current($errors);
        }
        return '保存失败，发生未知错误。';
    }

    public function beforeSave($insert)
    {
        // 1. 原有逻辑：处理 'null' 字符串
        foreach ($this->attributes as $key => $value) {
            if ($value === 'null') {
                $this->setAttribute($key, null);
            }
        }

        // 2. 新增 SaaS 逻辑：自动注入 mall_id
        // 如果是新增数据 ($insert=true)
        // 且当前模型有 mall_id 字段
        // 且当前没有手动设置 mall_id
        // 且当前上下文中存在 Mall 实例
        if ($insert && $this->hasAttribute('mall_id')) {
            if (empty($this->mall_id) && isset(\Yii::$app->mall)) {
                $this->mall_id = \Yii::$app->mall->id;
            }
        }

        // 3. 兜底时间戳：save(false) 会跳过 beforeValidate，
        //    若仅依赖 beforeValidate 写时间戳，created_at/updated_at 将为 0。
        //    此处在 beforeSave 再写一次，保证任何保存路径时间戳都正确。
        $this->touchTimestamps($insert);

        return parent::beforeSave($insert);
    }

    public function beforeValidate()
    {
        $this->touchTimestamps($this->isNewRecord);
        return parent::beforeValidate();
    }

    /**
     * 维护 created_at / updated_at / deleted_at 时间戳
     */
    private function touchTimestamps(bool $insert)
    {
        $time = time();

        $hasCreatedAt = in_array('created_at', $this->attributes());
        $hasUpdatedAt = in_array('updated_at', $this->attributes());
        $hasDeletedAt = in_array('deleted_at', $this->attributes());
        $hasIsDelete = in_array('is_delete', $this->attributes());

        // 插入时设置 created_at & created_time
        if ($insert && $hasCreatedAt) {
            $this->created_at = $time;
            if (property_exists($this, 'created_time')) {
                $this->created_time = date('Y-m-d H:i:s', $time);
            }
        }

        // 插入或更新时设置 updated_at & updated_time
        if ($hasUpdatedAt) {
            $this->updated_at = $time;
            if (property_exists($this, 'updated_time')) {
                $this->updated_time = date('Y-m-d H:i:s', $time);
            }
        }

        // 删除标记时维护 deleted_at & deleted_time
        if ($hasIsDelete && $hasDeletedAt) {
            if ((int)$this->is_delete === 1) {
                $this->deleted_at = $time;
                if (property_exists($this, 'deleted_time')) {
                    $this->deleted_time = date('Y-m-d H:i:s', $time);
                }
            } else {
                $this->deleted_at = 0;
                if (property_exists($this, 'deleted_time')) {
                    $this->deleted_time = null;
                }
            }
        }
    }

    /**
     * 复写 updateAll 方法
     */
    public static function updateAll($attributes, $condition = '', $params = [])
    {
        $time = time();

        if (!array_key_exists('updated_time', $attributes)) {
            $attributes['updated_time'] = date('Y-m-d H:i:s', $time);
        }
        if (!array_key_exists('updated_at', $attributes)) {
            $attributes['updated_at'] = $time;
        }

        if (array_key_exists('is_delete', $attributes)) {
            if (!array_key_exists('deleted_at', $attributes)) {
                $attributes['deleted_at'] = $time;
                $attributes['deleted_time'] = date('Y-m-d H:i:s', $time);
            }
        }

        return parent::updateAll($attributes, $condition, $params);
    }

    /**
     * 原有逻辑：返回自定义 Query 对象
     */
    public static function find()
    {
        return new BaseActiveQuery(get_called_class());
    }

    /**
     * 新增 SaaS 逻辑：快捷查询当前商城的数据
     * 使用方法: Role::findByMall()->all();
     */
    public static function findByMall()
    {
        $query = static::find();
        $instance = new static();

        // 只有当表里有 mall_id 字段，且当前上下文有商城时，才加过滤
        if ($instance->hasAttribute('mall_id') && isset(\Yii::$app->mall)) {
            $query->andWhere(['mall_id' => \Yii::$app->mall->id]);
        }

        return $query;
    }

    public static function getCacheDbDependency($params = null)
    {
        $dbDependencySql = self::find()->select('max(updated_at)')
            ->where('is_delete =0')
            ->createCommand()
            ->sql;

        $dbDependency = new DbDependency();
        $dbDependency->db = \Yii::$app->components['db'];
        $dbDependency->sql = $dbDependencySql;
        $dbDependency->params = $params;
        return $dbDependency;
    }

    public static function getOne($id)
    {
        return self::findOne($id);
    }

    public function afterSave($insert, $changedAttributes)
    {
        if (\Yii::$app->id == 'jxmall' && \Yii::$app->isAdmin) {
            if (!\Yii::$app->admin->isGuest) {
                $admin_id = \Yii::$app->admin->identity->id;
                if (get_called_class() != AdminActionLog::class) {
                    $arr = ['created_at', 'updated_at', 'deleted_at'];
                    $afterUpdate = $this->attributes;
                    $newBeforeUpdate = [];
                    $newAfterUpdate = [];

                    if ($insert) {
                        $remark = '数据插入';
                    } else {
                        $remark = '数据更新';
                    }
                    if (isset($afterUpdate['is_delete']) && $afterUpdate['is_delete'] == 1) {
                        $remark = '数据删除';
                    }

                    foreach ($changedAttributes as $key => $item) {
                        if (in_array($key, $arr)) {
                            continue;
                        }
                        if ($item != $afterUpdate[$key]) {
                            try {
                                $newBeforeUpdate[$key] = \yii\helpers\Json::decode($item);
                            } catch (\Exception $e) {
                                $newBeforeUpdate[$key] = $item;
                            }
                            try {
                                $newAfterUpdate[$key] = \yii\helpers\Json::decode($afterUpdate[$key]);
                            } catch (\Exception $e) {
                                $newAfterUpdate[$key] = $afterUpdate[$key];
                            }
                        }
                    }

                    $mall_id = isset($this->attributes['mall_id']) ? $this->attributes['mall_id'] : 0;
                    if (count($newAfterUpdate)) {
                        $logPayload = [
                            'admin_id'      => $admin_id,
                            'model'         => get_called_class(),
                            'model_id'      => $this->getPrimaryKey(false),
                            'mall_id'       => $mall_id,
                            'before_update' => \yii\helpers\Json::encode($newBeforeUpdate),
                            'after_update'  => \yii\helpers\Json::encode($newAfterUpdate),
                            'remarks'       => $remark,
                            'created_at'    => time(),
                            'updated_at'    => time(),
                            'created_time'  => date('Y-m-d H:i:s'),
                            'updated_time'  => date('Y-m-d H:i:s'),
                        ];

                        try {
                            $logKey = env('REDIS_KEY_ADMIN_LOG', 'admin_action_logs'); // 去掉 jxmall 前缀，交由 CacheHelper
                            \app\helpers\CacheHelper::lpush($logKey, $logPayload);
                        } catch (\Exception $e) {
                            \Yii::warning("Failed to push admin log to redis: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    public static function defaultWhere()
    {
        return ['is_delete' => 0];
    }
}
