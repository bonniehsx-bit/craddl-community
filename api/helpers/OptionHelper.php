<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 19:40
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

namespace app\helpers;

use app\models\Option;
use yii\base\BaseObject;
use yii\db\Connection;
use yii\helpers\Json;

class OptionHelper extends BaseObject
{
    /**
     * 配置缓存登记表(Redis Set):本类写进 Redis 的每个配置缓存键都记在这里。
     *
     * 「刷新缓存」要清掉的是全部配置缓存,包括数据库里已经没有这一行的旧缓存
     * (手动删行、导入备份后残留的)。这些键从数据库查不出来,也不能按键名模式去扫:
     * 同一个 Redis 库里还有定时任务配置、延时任务队列、分润防重占位(brand_paid_done_1_xxx)等,
     * 长得像「名字_数字」的键不一定是配置缓存。所以写缓存时顺手登记,清的时候只按登记表删。
     */
    const CACHE_KEY_REGISTRY = 'option_cache_keys';

    /**
     * 配置缓存的有效期(兜底)。写配置时已经删缓存,这个只用来收住漏网的不一致
     * (并发读写、绕过本类直接改库),错了最多错这么久;加一段随机秒数,免得同一时间回填的一起过期
     */
    const CACHE_TTL = 3600;
    const CACHE_TTL_JITTER = 600;

    /**
     * 写配置:写库成功后删缓存,下次读从库里取。
     *
     * 原来是写库后直接覆盖缓存,而且不看写库结果:写库失败也写缓存、照样返回成功;
     * 并发保存时缓存和库的先后可能对不上,缓存又不过期,错了就一直错。
     *
     * @param int|null $duration 已不使用(缓存统一在读的时候回填、带过期时间),保留只为兼容旧调用
     * @return bool 写库是否成功
     */
    public static function set($name, $data, $mall_id = 0, $duration = null)
    {
        $model = Option::findOne(['name' => $name, 'mall_id' => $mall_id]);
        if (!$model) {
            $model = new Option();
        }
        if (is_array($data)) { //如果这三个同时存在就说明了是来自model里面的
            if (array_key_exists('page', $data) && array_key_exists('limit', $data) && array_key_exists('pagination', $data)) {
                unset($data['page']);
                unset($data['limit']);
                unset($data['pagination']);
            }
        }
        $model->name = $name;
        $model->mall_id = $mall_id;
        $model->value = Json::encode($data);
        if (!$model->save()) {
            \Yii::error("保存配置失败 {$name}_{$mall_id}: " . Json::encode($model->getErrors()));
            return false;
        }
        self::forgetCache($name . '_' . $mall_id);
        return true;
    }

    /**
     * 缓存里的「库里没有这项配置」占位。不是合法 JSON,也不会是正常的配置值
     */
    const ABSENT = '__option_absent__';

    /** 占位的有效期:配置被绕过本类写进库(迁移、导入)时,最多这么久也能读到 */
    const ABSENT_TTL = 300;

    /**
     * 读配置:先读缓存,没有再查库并回填。
     *
     * 按「有没有」判断,不按真假:存的是 0 / false / '0' 照样返回存的值;
     * 没有这一行、或存的是 null / 空数组 / 空字符串,才返回 $default。
     * 原来按真假判断,这几种值缓存里有也当没命中,每次都查库,而且存进去的 0 读回来是默认值;
     * 库里没有的配置也不记,每读一次查一次库(首页的 home_theme 就是每个请求一次)。
     */
    public static function get($name, $mall_id = 0, $default = null)
    {
        $key = $name . '_' . $mall_id;
        $value = CacheHelper::get($key);
        if ($value === self::ABSENT) {
            return $default;
        }
        if ($value === null) {
            $model = Option::findOne(['name' => $name, 'mall_id' => intval($mall_id)]);
            $value = $model ? Json::decode($model->value) : null;
            self::fillCache($key, $value);
        }
        return ($value === null || $value === [] || $value === '') ? $default : $value;
    }

    /**
     * 回源后回填缓存,带过期时间,只在缓存里还没有时写(SET NX):查库到回填之间有人保存了配置,
     * 缓存里已经是新值,不能被这边查到的旧值或「没有」盖掉
     */
    private static function fillCache(string $key, $value)
    {
        $args = $value === null
            ? [CacheHelper::getKeyName($key), self::ABSENT, 'EX', self::ABSENT_TTL]
            : [CacheHelper::getKeyName($key), Json::encode($value), 'EX', self::CACHE_TTL + mt_rand(0, self::CACHE_TTL_JITTER)];
        $redis = \Yii::$app->redis;
        // 键在、内容却读出来是空(老写法存进去的 '' / null):当作没有,直接覆盖,不然每次读都要回源
        if ($redis->executeCommand('SET', array_merge($args, ['NX'])) === null && CacheHelper::get($key) === null) {
            $redis->executeCommand('SET', $args);
        }
        self::registerCacheKeys([$key]);
    }

    /**
     * 删配置缓存(写库之后调),下次读从库里取。
     * 在数据库事务里时,事务结束(提交或回滚)后再删一次:没结束之前,别的请求可能把旧值读回缓存,
     * 本请求也可能把还没提交的新值读进缓存,只删这一次会留下和库对不上的缓存
     */
    private static function forgetCache(string $key)
    {
        CacheHelper::remove($key);
        $db = Option::getDb();
        if ($db->getTransaction() === null) {
            return;
        }
        $handler = function () use ($db, $key, &$handler) {
            $db->off(Connection::EVENT_COMMIT_TRANSACTION, $handler);
            $db->off(Connection::EVENT_ROLLBACK_TRANSACTION, $handler);
            CacheHelper::remove($key);
        };
        $db->on(Connection::EVENT_COMMIT_TRANSACTION, $handler);
        $db->on(Connection::EVENT_ROLLBACK_TRANSACTION, $handler);
    }

    /**
     * 读取并合并配置：默认值 + 已存配置（安全合并，规避 ArrayHelper::merge 的整数索引数组 append 陷阱）。
     *
     * 背景：ArrayHelper::merge 对「值为顺序数组(list)」的字段是 **append** 而非覆盖，会导致
     *   merge(['t'=>[1,2,4]], ['t'=>[1]]) => ['t'=>[1,2,4,1]]，造成"勾选项怎么取消都恢复成全选"的 bug。
     *
     * 用法：getSetting 里读配置时，把 list 型字段名全部声明到 $listFields，本方法会用 saved 值显式覆盖它们。
     *   $setting = OptionHelper::mergeSetting('mch_setting', $mallId, $this->getDefaults(), ['allowed_mch_types']);
     *
     * 注意：本方法只负责「结构层安全合并」，不做业务清洗（intval/unique/过滤空项等仍由各 Form 自行处理）。
     *
     * @param string $name       option key
     * @param int    $mall_id    商城 ID
     * @param array  $default    默认配置（getDefaults 返回）
     * @param array  $listFields 值为顺序数组(list)的字段名清单
     * @return array
     */
    public static function mergeSetting($name, $mall_id, array $default, array $listFields = [])
    {
        $saved = self::get($name, $mall_id, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        $merged = \yii\helpers\ArrayHelper::merge($default, $saved);
        // 对 list 字段用 saved 显式覆盖，杜绝 merge 的 append 行为
        foreach ($listFields as $field) {
            if (isset($saved[$field]) && is_array($saved[$field])) {
                $merged[$field] = array_values($saved[$field]);
            }
        }
        return $merged;
    }

    public static function remove($name, $mall_id = 0)
    {
        $model = Option::findOne(['name' => $name, 'mall_id' => $mall_id]);
        if ($model) {
            $model->delete();
        }
        // 库里没有这一行也删缓存:可能残留着手动删行之前的旧值
        self::forgetCache($name . '_' . $mall_id);
        return true;
    }

    /**
     * 刷新全部配置缓存:清掉,下次读从库里重新取。登记表里有、库里已经没有的旧缓存一并删掉。
     * 只动配置缓存,Redis 里的其他数据不碰。适用场景:库被手动改 / 导入备份后,缓存和库对不上。
     *
     * 只删不写:原来是先读全表再逐条写回,读表到写回之间有人保存配置,会被这里读到的旧值盖掉。
     *
     * @return array ['total' => 配置行数, 'refreshed' => 清掉后能正常读回的, 'skipped' => 值为空或不是合法 JSON 的行,
     *                'removed' => 库里已经没有的旧缓存]
     */
    public static function refreshCache()
    {
        $rows = Option::find()
            ->where(['is_delete' => 0])
            ->select(['name', 'mall_id', 'value'])
            ->asArray()
            ->all();

        $total = count($rows);
        $skipped = 0;
        $liveKeys = [];
        $liveNames = [];
        foreach ($rows as $row) {
            $liveKeys[$row['name'] . '_' . intval($row['mall_id'])] = true;
            $liveNames[$row['name']] = true;
            // 值为空或不是合法 JSON:读的时候拿到的是默认值(或解析报错),这里只计数提示
            if ($row['value'] === null || $row['value'] === '' || json_decode($row['value']) === null) {
                $skipped++;
            }
        }

        // 0) 数据库里已经没有这一行的旧缓存:按登记表删,不按键名模式扫
        $staleKeys = [];
        foreach (self::registeredCacheKeys() as $key) {
            if (isset($liveKeys[$key])) {
                continue;
            }
            // 2026-10-14 以前,平台级配置(mall_id=0)在商城里首次保存会被 BaseActiveRecord::beforeSave 存到当前商城下,
            // 数据库行和缓存键 name_0 对不上,读配置恰恰靠这份缓存(如 PaymentForm 存的 wechat_mch_*,退款回调要读)。
            // Option::beforeSave 已修、迁移 m261014_140000 改回了老数据;这层判断留着给还没跑那条迁移的环境兜底:
            // 键尾不是具体商城号的,只要库里还有同名配置就不动;只清名字已经彻底没了的
            $pos = strrpos($key, '_');
            $suffix = $pos === false ? '' : substr($key, $pos + 1);
            if ((!ctype_digit($suffix) || (int)$suffix === 0) && isset($liveNames[$pos === false ? $key : substr($key, 0, $pos)])) {
                continue;
            }
            $staleKeys[] = $key;
        }
        self::deleteCacheKeys($staleKeys);
        self::unregisterCacheKeys($staleKeys);
        $removed = count($staleKeys);

        // 1) 库里有的:清掉,下次读从库里重新取
        self::deleteCacheKeys(array_keys($liveKeys));
        $refreshed = $total - $skipped;

        return compact('total', 'refreshed', 'skipped', 'removed');
    }

    /** 批量删配置缓存(传不带前缀的键名) */
    private static function deleteCacheKeys(array $keys)
    {
        foreach (array_chunk($keys, 500) as $chunk) {
            \Yii::$app->redis->executeCommand('DEL', array_map([CacheHelper::class, 'getKeyName'], $chunk));
        }
    }

    /**
     * 登记数据库里现有配置的缓存键,只登记键名、不读写缓存值。
     *
     * 给上线迁移用:登记表是后加的,上线前写进 Redis 的配置缓存都没登记过。
     * 刻意不复用 refreshCache():那会清掉全部配置缓存,发布时所有请求一起回源;
     * 动缓存出错还会抛异常,迁移失败会取消排在后面的迁移。登记失败只记日志,不抛出。
     *
     * @return array ['total' => 配置行数, 'registered' => 成功登记数]
     */
    public static function registerExistingCacheKeys(): array
    {
        $keys = [];
        foreach (Option::find()->select(['name', 'mall_id'])->asArray()->all() as $row) {
            $keys[] = $row['name'] . '_' . intval($row['mall_id']);
        }
        $ok = self::registryCommand('SADD', $keys);
        return ['total' => count($keys), 'registered' => $ok ? count($keys) : 0];
    }

    /**
     * 登记配置缓存键。失败只记日志 —— 登记表只是清缓存用的账本,不能因为它让读配置报错。
     */
    private static function registerCacheKeys(array $keys)
    {
        self::registryCommand('SADD', $keys);
    }

    private static function unregisterCacheKeys(array $keys)
    {
        self::registryCommand('SREM', $keys);
    }

    private static function registeredCacheKeys(): array
    {
        try {
            return \Yii::$app->redis->executeCommand('SMEMBERS', [CacheHelper::getKeyName(self::CACHE_KEY_REGISTRY)]) ?: [];
        } catch (\Throwable $e) {
            \Yii::warning('读取配置缓存登记表失败: ' . $e->getMessage());
            return [];
        }
    }

    private static function registryCommand(string $command, array $keys): bool
    {
        if (!$keys) {
            return true;
        }
        $registry = CacheHelper::getKeyName(self::CACHE_KEY_REGISTRY);
        try {
            foreach (array_chunk($keys, 500) as $chunk) {
                \Yii::$app->redis->executeCommand($command, array_merge([$registry], $chunk));
            }
        } catch (\Throwable $e) {
            \Yii::warning("配置缓存登记表 {$command} 失败: " . $e->getMessage());
            return false;
        }
        return true;
    }
}
