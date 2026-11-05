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

use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use app\services\UserService;
use Yii;
use yii\web\IdentityInterface;

/**
 * User 模型 - 用户核心表
 *
 * ============================================================================
 * 基础身份信息
 * ============================================================================
 * @property int $id                        用户ID (主键)
 * @property int $mall_id                   商城ID (多租户隔离)
 * @property string $nickname               用户昵称 (显示时建议脱敏)
 * @property string $avatar_url             头像链接
 * @property string|null $mobile            手机号
 * @property string|null $backup_mobile     备份手机号
 * @property string|null $name              真实姓名/用户名
 * @property string $password               登录密码 (加密存储)
 * @property string $pay_pwd                支付密码 (加密存储)
 * @property string $auth_key               认证密钥 (Cookie/Session验证)
 * @property string $access_token           API访问令牌
 * @property string|null $login_ip          最后登录IP
 * @property string|null $remarks           后台备注
 * @property int $is_delete                 是否删除 (0:未删除 1:已删除)
 * @property int|null $status               状态 (0:正常 1:拉黑 2:注销)
 * @property string $birthday               生日 (YYYY-MM-DD)
 * @property int $gender                    自然人性别: 0=未知 1=男 2=女
 * @property int $year                      出生年
 * @property int $month                     出生月
 * @property int $day                       出生日
 * @property int $is_avatar                 是否已同步头像/昵称
 * @property string $detail                 个人简介/详情
 * @property string $bg_pic                 个人中心背景图
 *
 * ============================================================================
 * 平台与第三方标识
 * ============================================================================
 * @property string $platform               注册来源平台 (wxapp, wechat, mobile等)
 * @property string $current_platform       用户当前活跃平台
 * @property string|null $union_id          微信开放平台 UnionID (跨应用识别)
 * @property string $openid                 当前平台的 OpenID (临时或主要)
 * @property string|null $wechat_openid     微信公众号 OpenID
 * @property string|null $mpwx_openid       微信小程序 OpenID
 * @property string|null $wechat_no         微信号
 * @property string|null $application_id    第三方平台应用ID
 * @property string|null $other_platform_user_id 第三方平台用户ID
 *
 * ============================================================================
 * 资产与钱包 (Money & Points)
 * ============================================================================
 * @property float $price                   当前余额 (可用于消费)
 * @property float $total_price             累计消费金额 (用于计算会员等级等)
 * @property float $money                   当前佣金/收益 (可提现金额)
 * @property float $total_money             累计获得佣金
 * @property float $cashed_price            已提现金额
 * @property float $integral                当前可用积分
 * @property float $total_integral          累计获得积分
 * @property float $retained_integral       累计留存积分 (自定义业务字段)
 *
 * ============================================================================
 * 分销与层级关系 (Relationship)
 * ============================================================================
 * @property int $parent_id                 直属上级ID (推荐人)，0为顶层
 * @property int|null $parent_at            绑定上级的时间戳
 * @property string $parent_date            绑定上级的日期 (Y-m-d)
 * @property int $maybe_parent_id           潜在上级ID (扫码但未正式绑定时记录)
 * @property int $is_inviter                是否具有推广员/邀请者资格 (0:否 1:是)
 * @property int|null $inviter_at           成为推广员的时间
 * @property int $level                     会员等级 (数字，如 0,1,2)
 * @property int $level_id                  会员等级配置ID (关联 MemberLevel表)
 * @property int $level_at                  等级升级/变更时间
 * @property int|null $agent_level_id       代理商等级ID
 * @property int $is_manual                 是否是后台手动调整过等级
 * @property int $disable_auto_upgrade       禁止自动升级:0=允许 1=禁止
 * @property User $inviter                  [关联] 直属上级用户模型
 * @property UserPath $pathInfo             [关联] 垂直分表的路径信息 (-1-5-88-)
 *
 * ============================================================================
 * 商户与店员 (Merchant & Clerk)
 * ============================================================================
 * @property int $mch_id                    入驻商户ID (如果用户自己是商户)
 * @property int $is_mch                    是否是商户 (0:否 1:是)
 * @property string|null $mch_time          成为商户时间
 * @property string|null $out_mch_no        外部商户号
 * @property int $is_clerk                  是否是核销员/店员
 * @property string|null $clerk_time        成为店员时间
 * @property int $is_shop                   是否是门店管理员 (0:否 1:是)
 * @property int $shop_id                   管理的门店ID
 * @property string $shop_time              成为门店管理员时间
 * @property int|null $bind_mch_id          用户当前绑定的商户ID (扫商户码进入)
 *
 * ============================================================================
 * 系统与元数据 (System Meta)
 * ============================================================================
 * @property int|null $created_at           创建时间戳
 * @property int|null $updated_at           更新时间戳
 * @property int|null $deleted_at           删除时间戳
 * @property string $created_time           创建时间 (格式化 Y-m-d H:i:s)
 * @property string|null $updated_time      更新时间 (格式化)
 * @property string|null $deleted_time      删除时间 (格式化)
 * @property string $account_del_time       账号注销申请时间
 * @property integer $order_always          是否开启订单提醒/常显
 * @property int $is_subscribe              是否关注公众号 (0:否 1:是)
 * @property float $lat                     最近一次定位纬度
 * @property float $lon                     最近一次定位经度
 * @property integer $admin_fd              后台WebSocket连接句柄
 * @property int $cashier_fd                收银台WebSocket连接句柄
 * @property Mch $mch                       [关联] 所属商户模型
 */
class User extends BaseActiveRecord implements IdentityInterface
{
    const PLATFORM_WECHAT = "WECHAT";
    const PLATFORM_MPWX = "MPWX";
    const PLATFORM_WXAPP = "WXAPP";
    const PLATFORM_MOBILE = "MOBILE";

    const STATUS_NORMAL = 0;
    const STATUS_BLACK = 1;
    const STATUS_DELETE = 2;

    /**
     * 缓存键前缀：Token -> UserID 映射
     */
    const CACHE_KEY_TOKEN_MAP = 'user_id_by_token:';

    /**
     * 缓存键前缀：UserID -> 静态身份数据
     */
    /**
     * 缓存键前缀：UserID -> 静态身份数据
     */
    const CACHE_KEY_IDENTITY = 'user_identity:';

    /**
     * 缓存键前缀：强制登出标记（值为时间戳，TTL=8天覆盖Token缓存7天窗口）
     */
    const CACHE_KEY_FORCE_LOGOUT = 'user_force_logout:';

    /**
     * 注销后重新注册的冷却期（天）
     */
    const RE_REGISTER_COOL_DOWN_DAYS = 360;

    /**
     * 「动态字段」清单 —— 这些字段**严禁**进入 identity 缓存，必须每次直查 DB。
     *
     * 维护规则：
     *   schema 新增任何高频变化的字段（余额/积分/计数器/累计值/价格类）必须加入此清单。
     *   遗漏会导致：用户余额/积分被缓存 1 小时，界面显示旧值，用户投诉。
     *
     * AppBootstrap::bootstrapUser() 用它过滤 $userModel->attributes。
     */
    const DYNAMIC_CACHE_EXCLUDE_FIELDS = [
        'money',
        'total_money',
        'integral',
        'total_integral',
        'retained_integral',
        'price',
        'total_price',
        'cashed_price',
        'coupon_count', // 虚拟字段（动态聚合）
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * 定义隐藏字段（黑名单策略）
     * 配合 BaseActiveRecord 的 fields() 方法使用
     * @return array
     */
    protected function getHiddenFields()
    {
        // 获取基类隐藏字段 (如 deleted_at, is_delete)
        $globalHidden = parent::getHiddenFields();
        // User 特有的敏感字段
        $userHidden = [
            'password',
            'auth_key',
            'access_token',
            'pay_pwd',
            // 如果觉得 union_id 或 openid 敏感，也可以加在这里
        ];
        return array_merge($globalHidden, $userHidden);
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'nickname'], 'required'],
            [['mall_id', 'is_delete', 'status', 'year', 'month', 'day', 'is_avatar', 'parent_id', 'parent_at', 'maybe_parent_id', 'is_inviter', 'inviter_at', 'level', 'level_id', 'level_at', 'agent_level_id', 'is_manual', 'disable_auto_upgrade', 'mch_id', 'is_mch', 'is_clerk', 'is_shop', 'shop_id', 'bind_mch_id', 'created_at', 'updated_at', 'deleted_at', 'order_always', 'is_subscribe', 'admin_fd', 'cashier_fd', 'gender'], 'integer'],
            [['price', 'total_price', 'money', 'total_money', 'cashed_price', 'integral', 'total_integral', 'retained_integral', 'lat', 'lon'], 'number'],
            [['nickname', 'login_ip', 'birthday', 'name', 'wechat_no', 'platform', 'account_del_time', 'backup_mobile'], 'string', 'max' => 45],
            [['avatar_url', 'remarks', 'detail', 'bg_pic'], 'string', 'max' => 255],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['union_id', 'out_mch_no', 'other_platform_user_id', 'application_id', 'access_token', 'auth_key', 'password', 'openid', 'pay_pwd'], 'string', 'max' => 64],
            [['mobile', 'current_platform', 'parent_date'], 'string', 'max' => 15],
            [['wechat_openid', 'mpwx_openid'], 'string', 'max' => 128],
            [['access_token'], 'unique'],
        ];
    }
    // =========================================================================
    // IdentityInterface 实现 (用户认证核心)
    // =========================================================================

    public static function findIdentity($id)
    {
        if (empty($id)) {
            return null;
        }
        return static::find()
            ->where(['id' => $id, 'is_delete' => 0, 'status' => self::STATUS_NORMAL])
            ->one();
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return self::findOne(['access_token' => $token, 'is_delete' => 0]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    // =========================================================================
    // 代理方法 (Delegates) - 核心业务托管给 Service
    // =========================================================================

    /**
     * 保存前钩子:生日一旦变化,自动同步拆分列 year / month / day。
     *
     * ⚠ 为什么必须收口在模型层:**生日券的自动发放读的是 `month`/`day` 两个整数列**
     * (见 marketing 插件 TriggerService::runBirthday —— `andWhere(['month'=>..,'day'=>..])`),
     * 而不是 `birthday` 字符串。只写 birthday 不同步这三列的话,该用户永远收不到生日券,
     * 而且【完全无声】:后台看着生日填好了,cron 也不报错,就是发不出去。
     *
     * 原先只有 C 端 UserForm 手工 explode 拆一次,后台 UserHelper::setAttribute 走
     * setAttribute+save 这条路根本不拆 —— 后台/客服改的生日全是残缺的。
     * 与其在每个写入方各拆一遍(必然漏),不如放在这里:任何路径写 birthday 都自动同步。
     *
     * 格式:统一按 Y-m-d 解析并回写归一化值(接受 2026/1/5、2026-1-5 这类输入,
     * 存成 2026-01-05)。解析不出来就把三列清零,不留半旧半新的脏数据。
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert || $this->isAttributeChanged('birthday')) {
            $raw = trim((string) $this->birthday);
            if ($raw === '') {
                $this->year = 0;
                $this->month = 0;
                $this->day = 0;
            } else {
                $ts = strtotime(str_replace('/', '-', $raw));
                if ($ts === false) {
                    // 解析不了就不猜,三列清零 + 留痕,免得旧值和新生日对不上
                    Yii::warning("[User] 生日格式无法解析,已清空拆分列: user_id={$this->id} birthday={$raw}", 'user');
                    $this->year = 0;
                    $this->month = 0;
                    $this->day = 0;
                } else {
                    $this->birthday = date('Y-m-d', $ts);   // 归一化,免得库里三种写法混着
                    $this->year  = (int) date('Y', $ts);
                    $this->month = (int) date('n', $ts);
                    $this->day   = (int) date('j', $ts);
                }
            }
        }

        return true;
    }

    /**
     * 模型保存后的钩子
     * 所有的副作用（Path创建、缓存刷新、关系链处理、日志）全部托管给 UserService
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        UserService::handleAfterSave($this, $insert, $changedAttributes);
    }

    /**
     * 绑定上级 (代理给 UserRelationService)
     * @param int $newParentId
     * @return bool
     * @throws \Exception
     */
    public function bindParent(int $newParentId, bool $byAdmin = false): bool
    {
        return UserService::bindParent($this, $newParentId, $byAdmin);
    }

    /**
     * 保存 Token (代理给 UserService)
     * @param string|null $wx_openid
     * @return bool
     */
    public function saveToken($wx_openid = null)
    {
        return UserService::saveToken($this, $wx_openid);
    }

    // =========================================================================
    // 关联关系 (Relations)
    // =========================================================================

    /**
     * 关联：直属推荐人
     */
    public function getInviter()
    {
        return $this->hasOne(User::class, ['id' => 'parent_id']);
    }

    /**
     * 关联：直属推荐人 (同上，保留兼容性)
     */
    public function getHasOneParent()
    {
        return $this->hasOne(User::class, ['id' => 'parent_id']);
    }

    /**
     * 关联：UserPath (垂直分表，用于层级路径)
     */
    public function getPathInfo()
    {
        return $this->hasOne(UserPath::class, ['user_id' => 'id']);
    }

    /**
     * 关联：商户成员关系
     */
    public function getMchRelations()
    {
        return $this->hasMany(MchClerk::class, ['user_id' => 'id']);
    }

    /**
     * 关联：用户自身入驻的商户（user.mch_id → mch.id）
     * 用 class_exists 守卫，mch 插件未安装时访问 $user->mch 返回 null 而非抛错
     */
    public function getMch()
    {
        if (!class_exists(Mch::class)) {
            return null;
        }
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }


    // =========================================================================
    // 数据读取与辅助方法 (Read-only Helpers)
    // =========================================================================

    /**
     * 获取用户对象 (快捷方法)
     */
    public static function getUser($id = null)
    {
        if (!$id) return null;
        return self::find()->where(['is_delete' => 0, 'id' => $id])->one();
    }

    /**
     * 获取用户基础信息 (用于不需要全部字段的场景)
     */
    public static function getBaseInfo($id = null)
    {
        if (!$id) return null;
        return self::findOne(['id' => $id, 'is_delete' => 0]);
    }

    /**
     * 获取直属推荐人对象 (手动查询)
     */
    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id'])
            ->where(['is_delete' => 0]);
    }

    /**
     * [核心] CTE 递归获取所有上级 (高性能)
     * 用于发佣金、查层级关系
     *
     * @param int $userId 当前用户ID
     * @param int $level 查询层数 (0=不限)
     * @param bool $includeSelf 是否包含自己
     * @return array
     */
    public static function getParents($userId, $level = 0, $includeSelf = false)
    {
        return UserService::getParents($userId, $level, $includeSelf);
    }

    /**
     * [快捷方法] 获取 Path 字符串
     * @return string 例如 "-1-5-88-"
     */
    public function getPath()
    {
        return $this->pathInfo ? $this->pathInfo->path : "-{$this->id}-";
    }

    /**
     * [快捷方法] 极速统计团队人数
     * 代理给 UserPathService，利用前缀索引
     * @return int
     */
    public function getTeamCount()
    {
        return UserService::getTeamCount($this->id);
    }
}
