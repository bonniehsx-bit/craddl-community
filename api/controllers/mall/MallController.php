<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-29
 * Time: 1:35
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


use app\forms\mall\mall\AlertAdvForm;
use app\forms\mall\mall\CheckingForm;
use app\forms\mall\mall\DataCenterForm;
use app\forms\mall\mall\DiyForm;
use app\forms\mall\mall\DiyPageListForm;
use app\forms\mall\mall\EorderForm;
use app\forms\mall\mall\EorderListForm;
use app\forms\mall\mall\FeedbackListForm;
use app\forms\mall\mall\FileForm;
use app\forms\mall\mall\FileHtmlForm;
use app\forms\mall\mall\FileListForm;
use app\forms\mall\mall\FreightForm;
use app\forms\mall\mall\FreightListForm;
use app\forms\mall\mall\HotImageForm;
use app\forms\mall\mall\HotImageListForm;
use app\forms\mall\mall\MallDataForm;
use app\forms\mall\mall\MallSettingForm;
use app\forms\mall\mall\NounForm;
use app\forms\mall\mall\NounListForm;
use app\forms\mall\mall\OutsideLinkForm;
use app\forms\mall\mall\OutsideLinkListForm;
use app\forms\mall\mall\PageEditForm;
use app\forms\mall\mall\PagePathForm;
use app\forms\mall\mall\PaymentForm;
use app\forms\mall\mall\RandomMinusSettingForm;
use app\forms\mall\mall\TabbarSettingForm;
use app\forms\mall\mall\TabGoodsForm;
use app\forms\mall\mall\TabGoodsListForm;
use app\forms\mall\mall\UploadSettingForm;
use app\forms\mall\mall\UserCenterSettingForm;
use app\forms\mall\mall\SettingPageSettingForm;
use app\forms\mall\mall\UserManageSettingForm;

class MallController extends Controller
{

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-11
     * @Time: 10:12
     * @Note:获取预设组件
     */
    public function actionComponents()
    {
        $form = new DiyForm();
        return $form->getComponents();
    }

    /**
     * 装修编辑器预览用:「按分类自动」的商品组 / 选项卡标签选完分类,马上按手机端同一套规则
     * (含子分类、在售、排序)取出商品给左边手机预览,不用保存再打开才看到。
     */
    public function actionDiyCatGoods()
    {
        $req = $this->request;
        $mallId = (int)\Yii::$app->mall->id;
        $ids = \app\helpers\DiyHydrateHelper::idsByCat((int)$req->get('cat_id'), $mallId,
            (int)$req->get('num', 6), (string)$req->get('sort', 'sales'));
        $list = $ids ? \app\models\Goods::find()->select(['id', 'name', 'cover_pic', 'price', 'origin_price', 'sales', 'virtual_sales', 'is_negotiable'])
            ->where(['id' => $ids, 'mall_id' => $mallId])
            ->orderBy([new \yii\db\Expression('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')])
            ->asArray()->all() : [];
        return $this->asJson(['code' => 0, 'msg' => '', 'data' => ['list' => $list]]);
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:47
     * Note:弹窗广告
     * @return \yii\web\Response
     */
    public function actionAlertAdv()
    {
        $form = new AlertAdvForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-21
     * @Time: 13:06
     * @Note:diy 编辑

     */
    public function actionDiyEdit()
    {
        $form = new PageEditForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        } else {
            $form->attributes = $this->request->get();
            return $form->search();
        }
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-21
     * @Time: 13:06
     * @Note:diy 设置
     * @return \yii\web\Response
     */
    public function actionDiy()
    {
        $form = new DiyPageListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-08
     * @Time: 23:09
     * @Note:diy 页面删除
     */
    public function actionDiyDelete()
    {
        $form = new PageEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-10
     * @Time: 8:03
     * @Note:设为主页
     */
    public function actionDiyHome()
    {
        $form = new PageEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->setHome());
    }

    /**
     * 设为「审核版」页面（App Store/微信小程序审核期间专用）
     */
    public function actionDiyAuditPage()
    {
        $form = new PageEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->setAuditPage());
    }

    /**
     * 取消「审核版」页面标记
     */
    public function actionDiyUnsetAuditPage()
    {
        $form = new PageEditForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->unsetAuditPage());
    }

    /**
     * 首页主题切换
     *
     * 配套前端架构：uniapp/themes/home/registry.js + 插件目录 home/HomeTheme.vue
     *
     *   GET  /mall/mall/home-theme-get          → { home_theme: 'diy' | 'santa' | ... }
     *   POST /mall/mall/home-theme-set { code } → 切换主题
     *
     * 切到非 'diy' 主题时，DIY 装修配置仍保留，切回 'diy' 即恢复装修引擎渲染。
     * 主题码必须与前端 plugins1/{plugin}/home/HomeTheme.vue 的目录名一致。
     */
    public function actionHomeThemeGet()
    {
        $mallId = (int) \Yii::$app->mall->id;
        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'data' => [
                'home_theme' => (string) (\app\helpers\OptionHelper::get('home_theme', $mallId, 'diy') ?: 'diy'),
            ],
        ]);
    }

    /**
     * 列出所有可用首页主题（默认 DIY + 各插件通过事件注册的主题）
     *
     * 实现思路：触发 HomeThemeEvent::EVENT_REGISTER，各插件 Listener
     * 把自己的主题 push 进 $event->themes[] 后聚合返回。
     */
    public function actionHomeThemeList()
    {
        $event = new \app\events\HomeThemeEvent();
        \Yii::$app->trigger(\app\events\HomeThemeEvent::EVENT_REGISTER, $event);

        $list = array_merge(
            [['code' => 'diy', 'label' => 'DIY 装修引擎（默认）', 'plugin' => '']],
            $event->themes
        );

        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'data' => ['list' => $list],
        ]);
    }

    public function actionHomeThemeSet()
    {
        $mallId = (int) \Yii::$app->mall->id;
        $code   = trim((string) $this->request->post('home_theme', ''));
        if ($code === '') {
            return $this->asJson(['code' => \app\core\ApiCode::CODE_FAIL, 'msg' => '主题码不能为空']);
        }
        if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/', $code)) {
            return $this->asJson(['code' => \app\core\ApiCode::CODE_FAIL, 'msg' => '主题码格式非法']);
        }
        \app\helpers\OptionHelper::set('home_theme', $code, $mallId);
        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'msg'  => '主题已切换为：' . $code,
        ]);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-12
     * @Time: 22:42
     * @Note:tabbar 设置
     */
    public function actionTabbar()
    {
        $form = new TabbarSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:24
     * @Note:用户中心设置
     * @return \yii\web\Response
     */
    public function actionUserCenter()
    {
        $form = new UserCenterSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * 设置页(uniapp others/setting)的菜单配置。
     * GET 读、POST 存;reset 是"恢复出厂默认菜单",与前端那个"撤销未保存修改"不是一回事。
     */
    public function actionSettingPage()
    {
        $form = new SettingPageSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /** 设置页菜单:恢复默认(清空配置,读取时自然回落到 DefaultDataHelper::settingPage) */
    public function actionSettingPageReset()
    {
        return (new SettingPageSettingForm())->reset();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:24
     * @Note:推广中心设置
     * @return \yii\web\Response
     */
    public function actionUserManage()
    {
        $form = new UserManageSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-15
     * @Time: 10:40
     * @Note: 商城设置
     */
    public function actionSetting()
    {
        $form = new MallSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:24
     * @Note:运费模板
     * @return \yii\web\Response
     */
    public function actionFreight()
    {
        $form = new FreightForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:24
     * @Note:运费模板删除
     * @return \yii\web\Response
     */
    public function actionFreightDelete()
    {
        $form = new FreightForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:24
     * @Note:运费模板列表
     * @return \yii\web\Response
     */

    public function actionFreightList()
    {
        $form = new FreightListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:25
     * @Note:支付设置
     * @return \yii\web\Response
     */
    public function actionPayment()
    {
        $form = new PaymentForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:25
     * @Note:电子面单模板列表
     * @return \yii\web\Response
     */
    public function actionEorderList()
    {
        $form = new EorderListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-10
     * @Time: 17:25
     * @Note:电子面单编辑
     * @return \yii\web\Response
     */
    public function actionEorder()
    {
        $form = new EorderForm();
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
     * Time: 16:47
     * Note:电子面单删除
     * @return \yii\web\Response
     */
    public function actionEorderDelete()
    {
        $form = new EorderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:47
     * Note:数据概览
     * @return \yii\web\Response
     */
    public function actionData()
    {
        $form = new MallDataForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:48
     * Note:上传设置
     * @return \yii\web\Response
     */
    public function actionUpload()
    {
        $form = new UploadSettingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:48
     * Note:小程序页面
     * @return \yii\web\Response
     */
    public function actionPage()
    {

        $form = new PagePathForm();
        return $form->getList();
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:48
     * Note: 页面路径编辑
     * @return \yii\web\Response
     */
    public function actionPageEdit()
    {
        $form = new PagePathForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/16
     * Time：16:34
     * Note：意见反馈
     */
    public function actionFeedback()
    {
        $form = new FeedbackListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/23
     * Time：14:22
     * Note：版本审核
     */
    public function actionChecking()
    {
        $form = new CheckingForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    public function actionHotImage()
    {
        $form = new HotImageForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    public function actionHotImageList()
    {
        $form = new HotImageListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionHotImageDel()
    {
        $form = new HotImageForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->delete());
        }
    }

    public function actionOutsideLink()
    {
        $form = new OutsideLinkForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/5/28
     * Time：10:24
     * Note：外部链接
     */
    public function actionOutsideLinkList()
    {
        $form = new OutsideLinkListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionFileList()
    {
        $form = new FileListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionFileGroup()
    {
        $form = new FileForm();

        return $form->getGroup();
    }

    /**
     * 文件编辑
     */
    public function actionFileEdit()
    {
        $form = new FileForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 文件编辑
     */
    public function actionFileDelete()
    {
        $form = new FileForm();
        $form->attributes = $this->request->post();
        return $form->delete();
    }

    /**
     * 名词配置
     */
    public function actionNoun()
    {
        $form = new NounForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 名词配置
     */
    public function actionNounDelete()
    {
        $form = new NounForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    public function actionNounList()
    {
        $form = new NounListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 扁平词表 key => 最终值（配置值 ?: 默认词），供后台 admin 全局 $noun() 取词。
     * 与 noun-list（含 type/label/占位符/示例，供词库配置页）区分：此接口极简、每次进后台拉一次。
     */
    public function actionNounMap()
    {
        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'msg'  => '',
            'data' => ['nouns' => \app\helpers\NounHelper::resolvedMap(\Yii::$app->mall->id)],
        ]);
    }

    public function actionDataCenter()
    {
        $form = new DataCenterForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionTabGoodsList()
    {

        $form = new TabGoodsListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionTabGoods()
    {
        $form = new TabGoodsForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    public function actionTabGoodsDel()
    {
        $form = new TabGoodsForm();
        $form->attributes = $this->request->post();
        return $form->delete();
    }

    public function actionTabGoodsMap()
    {
        $form = new TabGoodsForm();
        return $form->map();
    }

    public function actionFileHtml()
    {

        $form = new FileHtmlForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * 随机减 - 获取配置
     */
    public function actionRandomMinusSetting()
    {
        $form = new RandomMinusSettingForm();
        return $this->asJson($form->get());
    }

    /**
     * 随机减 - 保存配置
     */
    public function actionRandomMinusSettingSave()
    {
        $form = new RandomMinusSettingForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /**
     * @Author: AI
     * @Note:快递公司获取
     */
    public function actionExpressList()
    {
        $form = new \app\forms\mall\express\ExpressListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->search());
    }

    /**
     * @Author: AI
     * @Note:快递公司添加与编辑
     */
    public function actionExpressEdit()
    {
        if ($this->request->isPost) {
            $form = new \app\forms\mall\express\ExpressEditForm();
            $form->attributes = $this->request->post('form');
            return $this->asJson($form->save());
        }
    }

    /**
     * @Author: AI
     * @Note:快递公司删除
     */
    public function actionExpressDestroy()
    {
        if ($this->request->isPost) {
            $form = new \app\forms\mall\express\ExpressDestroyForm();
            $form->attributes = $this->request->post();
            return $this->asJson($form->destroy());
        }
    }

    /**
     * @Author: AI
     * @Note:同步系统快递公司数据(幂等补齐标准快递公司,解决某些站点迁移未灌数据导致表为空)
     */
    public function actionExpressSync()
    {
        if ($this->request->isPost) {
            $form = new \app\forms\mall\express\ExpressSyncForm();
            return $this->asJson($form->sync());
        }
    }
}
