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
use app\models\AccessToken;
use Yii;
use yii\web\IdentityInterface;

/**
 * Class Admin
 * @package app\models
 * 
 * [后台管理员模型 - 重构版]
 * 复用 User 端的动静分离与缓存架构。
 *
 * @property int $id ID
 * @property int $mall_id 商城ID
 * @property string $name 真实姓名
 * @property int $mall_count 店铺数量
 * @property int $is_expire 是否过期
 * @property int $end_at 结束时间
 * @property int $is_has_expire 是否拥有过期时间
 * @property int $admin_type 账号类型（0超级管理员 1子账户 2操作员）
 * @property string $password 密码
 * @property string $username 登录用户名
 * @property string $login_ip 登录IP
 * @property string $auth_key 鉴权KEY
 * @property string $mobile 手机号码
 * @property int $created_by 创建人ID
 * @property int $we7_uid 微擎关联UID
 * @property int $mch_id 多商户ID
 * @property int $is_delete 是否软删除
 * @property int $created_at 创建时间(戳)
 * @property int $updated_at 更新时间(戳)
 * @property int $deleted_at 删除时间(戳)
 * @property string $created_time 创建时间
 * @property string $updated_time 更新时间
 * @property string $deleted_time 删除时间
 * @property string $remark 备注
 * @property int $role_id 角色ID
 * @property string $avatar 头像
 * @property string $email 邮箱
 * @property string $nickname 昵称
 * @property string $ext_data 扩展数据
 * @property int $bind_uid 绑定的前台真身小程序用户UID (打通 IM 移动端接单必备)
 * @property int $is_kefu 是否处于营业状态客服 (0:休息 1:营业接单)
 */
class Admin extends BaseActiveRecord implements IdentityInterface
{
    // 定义缓存 Key 前缀
    const CACHE_KEY_TOKEN_MAP = 'admin_id_by_token:';
    const CACHE_KEY_IDENTITY = 'admin_identity:';
    const CACHE_KEY_FORCE_LOGOUT = 'admin_force_logout:';

    const ADMIN_TYPE_SUPER = 0;
    const ADMIN_TYPE_FOUNDER = 1;
    const ADMIN_TYPE_OPERATOR = 2;
    const ADMIN_TYPE = [0 => '超级管理员', 1 => '子账户', 2 => '操作员'];

    // [新增] 用于运行时接收 Token (不存库，只在内存)
    public $access_token;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['username'], 'required'],
            [['mall_count', 'role_id', 'is_expire', 'end_at', 'is_has_expire', 'admin_type', 'created_by', 'mch_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'mall_id', 'bind_uid', 'is_kefu'], 'integer'],
            [['created_time'], 'safe'],
            [['name', 'username', 'login_ip', 'nickname', 'email'], 'string', 'max' => 45],
            [['password', 'auth_key', 'avatar', 'remark'], 'string', 'max' => 255],
            [['ext_data'], 'string'],
            // access_token 虽然库里可能有，但我们主要用 AccessToken 表管理
            [['access_token'], 'string', 'max' => 254],
            [['mobile'], 'string', 'max' => 15],
        ];
    }

    // ... attributeLabels 保持不变 ...

    /**
     * 刷新管理员身份缓存:直接删掉,下一次请求由 AdminBootstrap 回库重建。
     *
     * 原来这里另拼一份精简快照写回缓存(只有 id / 昵称 / 头像 / 角色等 9 个字段),
     * 而 AdminBootstrap 缓存的是整条记录(去掉密码 / 令牌)—— 两边口径不一致:
     * 管理员资料一保存,afterSave 就把精简快照写回去,之后每个请求拿到的 identity 都缺字段,
     * 「我的资料」里的前台真身 / 客服状态 / 创建日期一保存就变空(数据其实存上了)。
     * 快捷菜单(ext_data)早先也踩过同一个坑,当时是绕开(回库读)没根治。
     * 统一由 AdminBootstrap 一处决定缓存什么,这里只负责让旧缓存失效。
     */
    public function refreshIdentityCache()
    {
        CacheHelper::remove(self::CACHE_KEY_IDENTITY . $this->id);
    }

    /**
     * [核心] 登录/保存 Token
     * 包含安全加固：生成随机 Token + 绑定 User-Agent
     */
    public function saveToken()
    {
        // 1. 生成安全随机 Token (替换原有的 md5 写法)
        $tokenStr = Yii::$app->security->generateRandomString(32);

        // 2. 数据库持久化（哈希存储，DB泄露不暴露原始Token）
        $token = new AccessToken();
        $token->token = hash('sha256', $tokenStr);
        $token->model_id = $this->id;
        $token->model = self::class; // app\models\Admin
        $token->expire_at = time() + 7 * 24 * 3600; // 7天过期
        $token->save();

        // 3. Redis 缓存：Token -> ID 映射 (绑定 UA 指纹 + IP)
        $tokenCacheData = [
            'id' => $this->id,
            'ua_hash' => md5(Yii::$app->request->userAgent),
            'ip' => Yii::$app->request->userIP,
        ];

        $cacheKey = self::CACHE_KEY_TOKEN_MAP . md5($tokenStr);
        CacheHelper::set($cacheKey, $tokenCacheData, 7 * 24 * 3600);

        // 新登录成功，清除强制登出标记
        CacheHelper::remove(self::CACHE_KEY_FORCE_LOGOUT . $this->id);

        // 4. 预热身份缓存
        $this->refreshIdentityCache();

        // 5. 将新 Token 赋值给当前对象，方便 Controller 返回给前端
        $this->access_token = $tokenStr;

        return $tokenStr;
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        // 利用注入的 access_token 精准退出
        if ($this->access_token) {
            // DB 中存的是哈希，用哈希匹配删除
            AccessToken::deleteAll([
                'token' => hash('sha256', $this->access_token),
                'model' => self::class
            ]);
            // 删缓存
            $cacheKey = self::CACHE_KEY_TOKEN_MAP . md5($this->access_token);
            CacheHelper::remove($cacheKey);
        }

        Yii::$app->admin->logout();
    }

    /**
     * 强制失效指定管理员的所有会话（改密/封禁/踢出时调用）
     */
    public static function invalidateAuth($adminId)
    {
        CacheHelper::remove(self::CACHE_KEY_IDENTITY . $adminId);
        AccessToken::deleteAll(['model_id' => $adminId, 'model' => self::class]);
        CacheHelper::set(self::CACHE_KEY_FORCE_LOGOUT . $adminId, time(), 8 * 24 * 60 * 60);
        Yii::warning("[Admin] 已强制失效管理员 #{$adminId} 的所有会话");
    }

    // --- IdentityInterface 实现 ---

    public static function findIdentity($id)
    {
        return self::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        // 兼容旧逻辑，但实际业务主要走 AdminBootstrap
        return self::findOne(['access_token' => $token]);
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * 是否"商城级超管"：平台超管(SUPER) 或 商城主账号(FOUNDER)，在其可见范围内全权。
     * 统一鉴权判断，替代散落各处的 in_array([SUPER,FOUNDER]) / != SUPER && != FOUNDER 字面量。
     */
    public function isMallSuper(): bool
    {
        return in_array($this->admin_type, [self::ADMIN_TYPE_SUPER, self::ADMIN_TYPE_FOUNDER]);
    }

    /**
     * 本账号能进入哪些商城。返回 null 表示【不受限】(平台超管,全部商城)。
     *
     * 这套口径原来只写在 MallEntryListForm 里,而「要不要给切换商城的入口」是另一处
     * 各自判断的(前端写死 admin_type === 0)。结果:名下管着多个商城的商户主账号
     * (FOUNDER)看不到切换入口,只能手动敲 /mall/entry 的 URL 才能换 —— 后端明明
     * 允许他进,前端却把门关了。抽到这里,列表和入口显示共用一套判断,不会再分叉。
     *
     * @return int[]|null
     */
    public function accessibleMallIds(): ?array
    {
        if ((int)$this->admin_type === self::ADMIN_TYPE_SUPER) {
            return null;                       // 超管不受限
        }
        if ((int)$this->mall_id > 0) {
            return [(int)$this->mall_id];      // 员工被绑死在某个商城
        }
        // 商户主账号:关联表授权的 + 自己创建的
        $granted = AdminMall::find()
            ->where(['admin_id' => $this->id, 'is_delete' => 0])
            ->select('mall_id')->column();
        $owned = Mall::find()
            ->where(['admin_id' => $this->id, 'is_delete' => 0])
            ->select('id')->column();
        return array_values(array_unique(array_map('intval', array_merge($granted, $owned))));
    }

    /**
     * 是否该给「切换商城」入口。
     *
     * 判据是**能进的商城多于一个**,而不是「是不是超管」——
     * 只能进一个商城的账号,点进去还是它,给入口只是徒增困惑。
     */
    public function canSwitchMall(): bool
    {
        $ids = $this->accessibleMallIds();

        // 平台超管:**永远**给入口,哪怕全站只有一个商城。
        //
        // 这里原来判的是「商城数 > 1 才给」,理由是"只有一个商城时切了还是它"——
        // 那是把 /mall/entry 只当成「切换商城」页看了。对超管来说它同时是**唯一的商城管理入口**:
        // 新增商城、调整授权、看到期时间都在那儿。全站就一个商城时反而更需要进去(去开第二个),
        // 结果按数量一卡,超管被锁死在当前商城里,只能手敲 URL 才回得去。
        if ($ids === null) {
            return true;
        }

        // 非超管(商户主账号 / 员工):对他们而言 entry 页确实只是「切换」,
        // 只能进一个商城的话给了也是原地打转,继续按数量判断。
        return count($ids) > 1;
    }

    /** @var int[]|null|false 本次请求内的 accessibleMallIds() 结果(false = 还没算过) */
    private $accessibleMallIdsMemo = false;

    /**
     * 本账号能不能管这个商城 —— 后台每个带商城上下文的请求都要过这一道(MallFilter / AdminFilter)。
     *
     * ⛔ 商城上下文来自请求头 x-mall-id,前端可以随便改。原来只在「进入商城」那一下校验,
     *    之后的请求只看登没登录:A 商城的操作员把请求头改成 B,就能看、能改 B 的合同账单会员订单,
     *    还能重置 B 商城员工的密码(2026-10-01 资金审计)。范围与「切换商城」列表同一套口径。
     */
    public function canAccessMall(int $mallId): bool
    {
        if ($this->accessibleMallIdsMemo === false) {
            $this->accessibleMallIdsMemo = $this->accessibleMallIds();
        }
        return $this->accessibleMallIdsMemo === null || in_array($mallId, $this->accessibleMallIdsMemo, true);
    }

    /**
     * 当前请求的商城上下文,登录的后台账号不能管 → 返回拒绝用的响应体;能管 / 没有商城上下文 / 没登录 → null。
     * 用「商城不存在」那个码:前端收到会清掉缓存的商城、退回选择商城页,只列他能进的商城。
     */
    public static function mallScopeDenied(): ?array
    {
        $mall = Yii::$app->mall ?? null;
        /** @var Admin|null $admin */
        $admin = Yii::$app->admin->identity ?? null;
        if (!$mall || !$admin || $admin->canAccessMall((int)$mall->id)) {
            return null;
        }
        Yii::warning("[后台跨商城] admin#{$admin->id}(类型 {$admin->admin_type})访问商城#{$mall->id} 被拒", 'security');
        return [
            'code' => \app\core\ApiCode::CODE_MALL_NOT_EXIST,
            'msg'  => '无权访问该商城，请重新选择商城',
            'data' => [],
        ];
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        // [修复] 原代码是 $this->auth_key == $this->auth_key (恒真漏洞)
        return $this->auth_key === $authKey;
    }

    // 钩子：修改资料后自动刷新缓存
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->refreshIdentityCache();
    }
}