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
use Yii;

/**
 * This is the model class for table "{{%mall}}".
 *
 * @property int $id
 * @property int $admin_id 创建者管理员的ID
 * @property string $name 商城名称
 * @property int $is_has_expire 是否存在过期
 * @property int|null $end_at 到期时间
 * @property int $is_expire 是否过期
 * @property int|null $updated_at
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int $is_delete 是否删除
 * @property int $is_stop 是否停用
 * @property string|null $logo logo
 * @property string|null $detail
 * @property int|null $uniacid
 */
class Mall extends BaseActiveRecord
{
    /**
     * 定义缓存 Key 前缀
     * 完整 Key: mall_data:{id}
     */
    const CACHE_KEY_MALL = 'mall_data:';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%mall}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['admin_id', 'name'], 'required'],
            [['admin_id', 'uniacid', 'is_has_expire', 'end_at', 'is_expire', 'updated_at', 'created_at', 'deleted_at', 'is_delete', 'is_stop'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['logo', 'detail'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'admin_id' => '创建者管理员的ID',
            'name' => '商城名称',
            'is_has_expire' => '是否存在过期',
            'end_at' => '到期时间',
            'is_expire' => '是否过期',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'is_delete' => '是否删除',
            'is_stop' => '是否停用',
            'logo' => 'logo',
            'detail' => '详细',
            'uniacid' => '统一公众号ID'
        ];
    }

    /**
     * [核心方法] 获取商城信息（优先查缓存）
     * 被 AppBootstrap 调用
     *
     * @param int $id
     * @return Mall|null
     */
    public static function getOne($id)
    {
        if (!$id) {
            return null;
        }

        // 1. 尝试从 Redis 获取
        $cacheKey = self::CACHE_KEY_MALL . $id;
        $data = CacheHelper::get($cacheKey);

        if ($data) {
            // 如果缓存命中，直接实例化模型
            // 使用 false 跳过校验，提升性能
            $model = new self();
            $model->setAttributes($data, false);
            $model->setIsNewRecord(false);
            return $model;
        }

        // 2. 缓存未命中，查数据库
        // 注意：只查未删除的
        $model = self::findOne(['id' => $id, 'is_delete' => 0]);

        if ($model) {
            // 3. 查到后写入缓存
            $model->refreshCache();
        }

        return $model;
    }

    /**
     * [辅助方法] 刷新/设置当前商城的缓存
     * 有效期设置较长（例如 30 天），因为商城配置极少变动
     */
    public function refreshCache()
    {
        $cacheKey = self::CACHE_KEY_MALL . $this->id;
        // 存入属性数组，而不是整个对象，更轻量
        CacheHelper::set($cacheKey, $this->attributes, 86400 * 30);
    }

    /**
     * [钩子方法] 保存后自动更新缓存
     * 确保后台修改配置后，AppBootstrap 能立刻获取最新数据
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // 如果是软删除操作，建议直接清除缓存
        if ($this->is_delete == 1) {
            CacheHelper::remove(self::CACHE_KEY_MALL . $this->id);
            return;
        }

        // 其他情况（修改名称、Logo、到期时间等），刷新缓存
        $this->refreshCache();
    }
}