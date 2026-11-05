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

namespace app\helpers;

use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

class CacheHelper extends BaseObject
{
    /**
     * 获取统一的 Key 名称
     * Go 语言端也需要使用同样的逻辑拼接前缀
     */
    public static function getKeyName($name)
    {
        // 确保 dbName 也就是数据库名是固定的，比如 "jxmall"
        // 最终 Key 格式: jxmall_user_id_by_token:xxx
        $prefix = Yii::$app->db->dbName;
        return $prefix . '_' . $name;
    }

    /**
     * 短信验证码的缓存键。
     *
     * 键里必须带商城 —— 原来是 'sms_code' . $mobile,全平台共用一个键,于是:
     * 各商城用的是**自己配置的**短信账号(mall_setting 里各配各的),商城 A 的运营
     * 在 A 触发给任意手机号发码后,能在自己的短信平台后台看到这条发送记录,
     * 拿到码再去商城 B 登录同一手机号的账号 —— 跨租户拿别人的账号。
     * 登录、改绑手机、找回密码读的都是这一个键,任意一处都能被这样利用。
     *
     * 商城一律取**真实上下文** Yii::$app->mall,不要改用 Form 的 $this->mall_id ——
     * 那个在 BaseModel 里是 safe 属性,能被前端 load() 覆盖;一旦被伪造,
     * 发码与校验就会落在两个不同的键上,验证码永远校验不过。
     * C 端每个请求都带 x-mall-id / mall_id(没有 mall 上下文连商品都查不出来),
     * 所以取上下文足够可靠。
     *
     * ⚠ 上线瞬间旧键(不带商城)里已发出的验证码会失效 —— 窗口是 180 秒,
     * 期间正在登录的用户重新获取一次即可。
     *
     * @param string|int $mobile
     * @param int|null $mallId 不传则取当前上下文
     */
    public static function smsCodeKey($mobile, $mallId = null)
    {
        if ($mallId === null) {
            $mallId = Yii::$app->mall->id ?? 0;
        }
        return 'sms_code' . (int)$mallId . '_' . $mobile;
    }

    /** 同一个验证码最多允许试错几次,超过即作废 */
    const SMS_CODE_MAX_FAILS = 5;

    /**
     * 写入验证码,并把试错计数归零。
     *
     * 发码必须走这里,不要直接 set(smsCodeKey(...)) —— 计数键是「验证码键 + :fail」,
     * 重新发码时若不清它,用户会被旧计数拖死:输错 4 次后重新获取,新码只剩 1 次机会,
     * 输对了也照样报「错误次数过多」;而每次失败又会把计数的 TTL 刷新一遍,
     * 于是反复重新获取都没用,只能干等 180 秒。
     */
    public static function setSmsCode($mobile, $code, $duration = 180, $mallId = null)
    {
        $key = self::smsCodeKey($mobile, $mallId);
        self::set($key, $code, $duration);
        self::remove($key . ':fail');
    }

    /**
     * 校验短信验证码,带失败计数。
     *
     * 原来各处都是「取出来比一下」,**没有任何失败次数限制**,而全仓也没有接口级限流。
     * 6 位码只有 100 万种、有效期 180 秒,校验接口不设防的话可以一直试 ——
     * 登录和找回密码走的是同一套,试出来就等于拿到账号。发码侧那道 IP 限流
     * (10 次/小时)限的是**发码**,拦不住校验。
     *
     * 连错 SMS_CODE_MAX_FAILS 次直接把码作废,逼对方重新获取(重新获取要过 IP 限流那关),
     * 把无限次试错变成每 180 秒最多 5 次。
     *
     * 计数键与验证码同生共死(同样 180 秒),码一换计数自然归零。
     *
     * @return array [bool 是否通过, string 失败原因]
     */
    public static function verifySmsCode($mobile, $input, $mallId = null)
    {
        $key = self::smsCodeKey($mobile, $mallId);
        $code = self::get($key);
        if (!$code) {
            return [false, '验证码已过期或不存在'];
        }

        $failKey = $key . ':fail';
        $fails = (int)self::get($failKey);
        if ($fails >= self::SMS_CODE_MAX_FAILS) {
            self::remove($key);
            self::remove($failKey);
            return [false, '验证码错误次数过多，请重新获取'];
        }

        // 一律按字符串比:缓存里存的可能是 int(mt_rand 的结果),
        // 前端传的是 string,用 != 弱比较容易在边缘输入上出意外。
        if ((string)$code !== (string)$input) {
            self::set($failKey, $fails + 1, 180);
            return [false, '验证码不正确'];
        }

        self::remove($failKey);
        return [true, ''];
    }

    /**
     * 写入缓存 (强制转为 JSON 字符串)
     * @param string $name 键名
     * @param mixed $data 数据 (数组/对象/字符串)
     * @param int|null $duration 过期时间(秒)
     */
    public static function set($name, $data, $duration = null)
    {
        $key = self::getKeyName($name);

        // 1. 序列化：如果不是字符串，强制转 JSON
        // JSON_UNESCAPED_UNICODE 防止中文被转义，节省空间且可读性好
        $value = is_string($data) ? $data : Json::encode($data, JSON_UNESCAPED_UNICODE);

        // 2. 存入 Redis
        if ($duration && $duration > 0) {
            // 使用 SETEX 命令：设置值并指定过期时间
            Yii::$app->redis->setex($key, $duration, $value);
        } else {
            // 永久存储
            Yii::$app->redis->set($key, $value);
        }
    }

    /**
     * 读取缓存 (尝试自动解析 JSON)
     * @param string $name
     * @return mixed
     */
    public static function get($name)
    {
        $key = self::getKeyName($name);

        // 1. 直接从 Redis 读取原始字符串
        $value = Yii::$app->redis->get($key);

        if ($value === false || $value === null) {
            return null;
        }

        // 2. 尝试反序列化 JSON
        // 这里的 try-catch 是为了兼容旧数据或纯字符串数据
        try {
            $data = Json::decode($value);
            return $data;
        } catch (\Exception $e) {
            // 如果不是 JSON，就返回原始字符串
            return $value;
        }
    }

    /**
     * 删除缓存
     */
    public static function remove($name)
    {
        $key = self::getKeyName($name);
        return Yii::$app->redis->del($key);
    }

    /**
     * [新增] 专门用于判断 Key 是否存在
     */
    public static function exists($name)
    {
        $key = self::getKeyName($name);
        return (bool)Yii::$app->redis->exists($key);
    }

    /**
     * [新增] 向队列推送JSON结构（携带隔离前缀）
     * 自动附加 $prefix_ 后发送 lpush指令
     */
    public static function lpush($name, $data)
    {
        $key = self::getKeyName($name);
        $value = is_string($data) ? $data : Json::encode($data, JSON_UNESCAPED_UNICODE);
        return Yii::$app->redis->lpush($key, $value);
    }
}
