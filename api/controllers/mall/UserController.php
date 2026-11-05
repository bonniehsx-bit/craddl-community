<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-18
 * Time: 12:47
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

namespace app\controllers\mall;


use app\core\ApiCode;
use app\forms\mall\user\ChargeForm;
use app\forms\mall\user\DefaultLevelListForm;
use app\forms\mall\user\LevelForm;
use app\forms\mall\user\LevelListForm;
use app\forms\mall\user\ParentChangeForm;
use app\forms\mall\user\UserBanForm;
use app\forms\mall\user\UserBatchCreateForm;
use app\forms\mall\user\UserCreateForm;
use app\forms\mall\user\UserForm;
use app\forms\mall\user\UserWalletForm;
use app\forms\mall\user\UserInfoForm;
use app\forms\mall\user\UserLevelChangeForm;
use app\forms\mall\user\TeamStatListForm;
use app\forms\mall\user\UserUpgradeLogListForm;
use app\forms\mall\user\UserListForm;
use app\forms\mall\user\UserMapForm;
use app\forms\mall\user\UserSearchListForm;
use app\forms\mall\user\UserSettingForm;
use app\forms\mall\user\UserExportForm; // [新增引用]
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\AccessToken;
use app\models\User;
use app\services\UserService;

class UserController extends Controller
{
    /**
     * 已注销账号(status=2)禁止执行的写操作。
     *
     * 注销 = 账号冻结:数据保留、关系链保留,但不该再对它做任何业务变更。
     * 给一个已注销账号充值、改密码、改推荐人,业务上说不通,也会污染恢复后的状态。
     *
     * 放行的是:各类查询、后台备注(运营自己的笔记)、以及 account-restore(唯一出口)。
     * ban / unban / account-del 不列在这里 —— 它们各自的 Form 已有更精确的状态校验
     * (「该用户已注销, 不能拉黑」「该用户已是注销状态」)。
     *
     * 拉黑(status=1)不受限制:拉黑是惩戒,账号仍在,运营可能正需要扣回违规所得。
     */
    private const DENY_ACTIONS_WHEN_DELETED = [
        'charge',            // 核心三资产增减
        'wallet-charge',     // 扩展钱包增减
        'set-password',
        'set-pay-password',
        'set-user-info',     // 昵称/手机号/头像/推广员资格
        'level-change',
        'parent-change',      // 改推荐人 = 动关系链
        'set-inviter',        // 推广员资格(此前完全没有状态校验)
        'trigger-level-check',// 自带 is_delete=0 会拦,但这里能给出更明确的提示
    ];

    /**
     * 前端在 status=2 时禁用了这些按钮,但禁用是可以绕过的 —— 后端必须也拦。
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!in_array($action->id, self::DENY_ACTIONS_WHEN_DELETED, true)) {
            return true;
        }

        $userId = (int)($this->request->post('user_id') ?: $this->request->get('user_id'));
        if ($userId <= 0) {
            return true;   // 没带 user_id 的交给各自 Form 校验
        }

        $target = User::findOne(['id' => $userId, 'mall_id' => \Yii::$app->mall->id]);
        if ($target && (int)$target->status === User::STATUS_DELETE) {
            \Yii::$app->response->data = ResponseHelper::send(
                ApiCode::CODE_FAIL,
                '该用户已注销,请先恢复账号后再操作'
            );
            return false;
        }
        return true;
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 12:48
     * @Note:用户管理
     */
    public function actionIndex()
    {
        $form = new UserListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * [新增] 团队数据(user_team_stat 展示,供验证团队统计)
     */
    public function actionTeamStat()
    {
        $form = new TeamStatListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * [新增] 升级日志(user_upgrade_log 展示,含升级前后等级/原因/备注)
     */
    public function actionUpgradeLog()
    {
        $form = new UserUpgradeLogListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * [新增] 异步导出用户数据
     * @return \yii\web\Response
     */
    public function actionAsyncExport()
    {
        $form = new UserExportForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->asyncExport());
    }

    /**
     * [新增] 查询导出任务进度
     * @return \yii\web\Response
     */
    public function actionExportStatus()
    {
        $form = new UserExportForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->checkStatus());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 22:30
     * @Note:用户会员等级改变
     */
    public function actionLevelChange()
    {
        $form = new UserLevelChangeForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-28
     * @Time: 16:43
     * @Note:用户信息
     * @return \yii\web\Response
     */
    public function actionInfo()
    {
        $form = new UserInfoForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-28
     * @Time: 16:44
     * @Note:修改推荐人
     */
    public function actionParentChange()
    {
        $form = new ParentChangeForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 14:29
     * @Note:默认等级列表
     */
    public function actionDefaultLevel()
    {
        $form = new DefaultLevelListForm();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 15:13
     * @Note:等级列表
     * @return \yii\web\Response
     */
    public function actionLevel()
    {
        $form = new LevelListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 15:09
     * @Note:等级编辑
     * @return \yii\web\Response
     */
    public function actionLevelEdit()
    {

        $form = new LevelForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }


    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:45
     * Note:等级删除
     * @return \yii\web\Response
     */
    public function actionLevelDelete()
    {
        $form = new LevelForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:45
     * Note:备注改变
     * @return \yii\web\Response
     */
    public function actionRemarksChange()
    {
        $form = new UserForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->remarksChange());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-18
     * @Time: 22:06
     * @Note:用户充值
     * @return \yii\web\Response
     */
    public function actionCharge()
    {
        $form = new ChargeForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 列出用户的扩展钱包(注册表驱动:各插件 getWallets 声明,核心零硬编码)
     */
    public function actionWallets()
    {
        $form = new UserWalletForm();
        $form->attributes = $this->request->get();
        return $form->list();
    }

    /**
     * 增减用户的某个扩展钱包(通用接口 → WalletService → 触发 UserEvent::WALLET_CHANGED)
     */
    public function actionWalletCharge()
    {
        $form = new UserWalletForm();
        $form->attributes = $this->request->post();
        return $form->charge();
    }

    /**
     * 某扩展钱包的进出流水(后台明细弹窗)
     */
    public function actionWalletLog()
    {
        $form = new UserWalletForm();
        $form->attributes = $this->request->get();
        return $form->walletLog();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-24
     * @Time: 23:45
     * @Note:保存用户关系设置
     */
    public function actionSetting()
    {
        $form = new UserSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/21
     * Time：9:41
     * Note：搜索用户
     */
    public function actionUserSearchList()
    {
        $form = new UserSearchListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/6/19
     * Time：15:06
     * Note：用户创建
     */
    public function actionCreate()
    {
        $form = new UserCreateForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * Note：批量创建测试用户(手机号自增)
     */
    public function actionBatchCreate()
    {
        $form = new UserBatchCreateForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/6/25
     * Time：15:59
     * Note：设置用户信息
     */
    public function actionSetUserInfo()
    {
        $data = $this->request->post();
        // 只能改本商城的用户:user_id 是全局主键,不带 mall_id 的话传别家商城的 id 一样能改
        $user = User::findOne(['id' => (int)($data['user_id'] ?? 0), 'mall_id' => \Yii::$app->mall->id]);
        if (!$user) {
            return $this->asJson(['code' => 1, 'msg' => '用户不存在']);
        }
        if ($data['key'] == 'is_clerk') {
            $user->is_clerk = intval($data['value']);
            if ($user->save()) {
                return $this->asJson(['code' => 0, 'msg' => '设置成功']);
            }
            return $this->asJson(['code' => 1, 'msg' => '设置失败']);
        }
        // [已废弃] status 不再走 set-user-info, 拉黑/解封统一走 /mall/user/ban 和 /mall/user/unban
        // 后台强制注销入口也已移除: 注销是用户权利, 管理员只能拉黑. 旧版"强制注销 status=2"分支一并删除
        if ($data['key'] == 'status') {
            return $this->asJson([
                'code' => 1,
                'msg'  => '请通过封禁/解封接口操作用户状态'
            ]);
        }
        if ($data['key'] == 'mobile' && $data['value'] && $data['user_id']) {
            $exist = User::find()->andWhere(['status' => User::STATUS_NORMAL, 'mall_id' => $this->mall_id, 'mobile' => $data['value']])->andWhere(['!=', 'id', $data['user_id']])->exists();
            if ($exist) {
                return $this->asJson(['code' => 1, 'msg' => '号码已存在']);
            }
        }
        // 后台要改手机号、姓名等 C 端不让改的字段 → 传 false 走后台白名单(不是不限制:
        // 资产/等级/上级都不在里面,见 UserHelper::ADMIN_EDITABLE_ATTRIBUTES)
        if (UserHelper::setAttribute($user->id, $data['key'], $data['value'], false)) {
            return $this->asJson(['code' => 0, 'msg' => '保存成功']);
        }
        return $this->asJson(['code' => 1, 'msg' => '保存失败']);
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/6/25
     * Time：15:59
     * Note：设置分销资格
     */
    public function actionSetInviter()
    {
        $form = new UserForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->setInviter());
    }

    /**
     * @return array
     */
    public function actionSetPassword()
    {
        $form = new UserForm();
        $form->attributes = $this->request->post();
        return $form->setPassword();
    }

    public function actionSetPayPassword()
    {
        $form = new UserForm();
        $form->attributes = $this->request->post();
        return $form->setPayPassword();
    }

    public function actionMap()
    {
        $form = new UserMapForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * 返回某用户的祖先 id 链, 供关系图谱"定位到节点"功能使用.
     * 前端拿到 chain 后逐层懒加载对应 parent_id 的子节点, 把 target 展开并高亮.
     *
     * GET /mall/user/ancestry?user_id=123
     * 返回: { chain: [顶层祖先, ..., 直接上级], target: {id, nickname, mobile, avatar_url} }
     */
    public function actionAncestry()
    {
        $userId = (int)$this->request->get('user_id');
        if (!$userId) {
            return $this->asJson(['code' => 1, 'msg' => '缺少 user_id']);
        }
        // 不带 is_delete=0:图谱的树与搜索都已放开注销用户(UserMapForm),
        // 这里若仍过滤,搜到一个注销用户后点「定位」会拿到「用户不存在」——
        // 它明明就显示在树上。mall_id 隔离保留。
        $user = User::findOne([
            'id' => $userId,
            'mall_id' => $this->mall_id,
        ]);
        if (!$user) {
            return $this->asJson(['code' => 1, 'msg' => '用户不存在']);
        }
        // 关系图谱展示【真实结构】,链上不过滤任何状态 —— 树里也显示已注销/被拉黑节点,
        // 两边口径必须一致,否则 el-tree 逐层展开会在缺失的那一层断掉。
        // parseAncestorIds 返回「根→父」,正是前端懒加载需要的 [顶层祖先, ..., 直接上级]。
        // (分润/计层一律走 getParents,那是另一套口径,见规范 §4.3.1)
        $chain = UserService::parseAncestorIds(UserService::getPath($userId));

        return $this->asJson([
            'code' => 0,
            'data' => [
                'chain'  => $chain,
                'target' => [
                    'id'         => (int)$user->id,
                    'nickname'   => $user->nickname,
                    'mobile'     => $user->mobile,
                    'avatar_url' => $user->avatar_url,
                ],
            ],
        ]);
    }

    /**
     * 后台拉黑用户
     * 必填: user_id, reason
     * 可选: banned_until (时间戳, 不传 = 永久)
     */
    public function actionBan()
    {
        $form = new UserBanForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->ban());
    }

    /**
     * 后台解封用户
     * 必填: user_id
     * 可选: reason (操作备注, 写入审计)
     */
    public function actionUnban()
    {
        $form = new UserBanForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->unban());
    }

    /**
     * 后台代为注销账号(正常/拉黑 → 已注销),可由 restore 还原
     * 必填: user_id  可选: reason(写入审计)
     */
    public function actionAccountDel()
    {
        $form = new UserBanForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->del());
    }

    /**
     * 后台恢复已注销账号(已注销 → 正常)
     * 必填: user_id  可选: reason(写入审计)
     */
    public function actionAccountRestore()
    {
        $form = new UserBanForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->restore());
    }

    /**
     * 触发该用户的会员等级升级检测（异步走 UserLevelUpgradeJob）
     * 必填: user_id
     *
     * 等级升级是平台会员等级体系的职责，故入口放在核心用户管理（不放插件）。
     */
    public function actionTriggerLevelCheck()
    {
        $userId = (int)$this->request->post('user_id');
        if (!$userId) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '参数错误']);
        }
        $user = \app\models\User::findOne(['id' => $userId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$user) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '用户不存在']);
        }
        if (!empty($user->disable_auto_upgrade)) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '该用户已设置「禁止自动升级」，升级检测不会生效']);
        }

        // ① 同步「试算」:跑一遍与真实升级完全相同的条件判定(纯只读,实测最大团队 0.4s),
        //    把逐等级、逐条件的达标明细直接返回给页面 —— 运营能当场看到卡在哪、还差多少,
        //    不必再靠"已触发,稍后刷新"猜结果。
        //    试算失败不能让整个操作失败:降级为原来的"派发异步检测",功能不倒退。
        $detail = null;
        try {
            $detail = (new \app\jobs\user\UserLevelUpgradeJob(['user_id' => $userId]))->dryRun();
        } catch (\Throwable $e) {
            \Yii::error("[升级检测] 试算失败 user#{$userId}: " . $e->getMessage(), 'user.upgrade');
        }
        if (!is_array($detail) || empty($detail['ok'])) {
            \Yii::$app->trigger(
                \app\events\UserLevelCheckNeededEvent::NAME,
                new \app\events\UserLevelCheckNeededEvent(['user_id' => $userId, 'trigger' => 'admin_manual'])
            );
            return $this->asJson([
                'code' => ApiCode::CODE_SUCCESS,
                'msg'  => is_array($detail) && !empty($detail['msg'])
                    ? $detail['msg']
                    : '已触发升级检测（后台异步执行，稍后刷新查看等级）',
                'data' => is_array($detail) ? $detail : null,
            ]);
        }

        // ② 只有确实达标才派发异步 Job 执行真正的升级(写等级/日志/级联仍走原有异步链路,
        //    保持行锁与防并发设计不变);未达标则不必打扰队列。
        if (!empty($detail['can_upgrade'])) {
            \Yii::$app->trigger(
                \app\events\UserLevelCheckNeededEvent::NAME,
                new \app\events\UserLevelCheckNeededEvent([
                    'user_id' => $userId,
                    'trigger' => 'admin_manual',
                ])
            );
        }

        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => !empty($detail['can_upgrade'])
                ? "已满足【{$detail['target_level']}】升级条件，正在执行升级（稍后刷新查看等级）"
                : ($detail['msg'] ?: '检测完成：暂不满足升级条件'),
            'data' => $detail,
        ]);
    }
}
