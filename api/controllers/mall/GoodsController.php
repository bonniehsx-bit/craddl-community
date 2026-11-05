<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 21:58
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
use app\forms\api\goods\RecommendGoodsListForm;
use app\forms\mall\goods\GoodsAssignMchForm;
use app\forms\mall\goods\GoodsBatchStatusForm;
use app\forms\mall\goods\GoodsCommentListForm;
use app\forms\mall\goods\AddAttrForm;
use app\forms\mall\goods\CatForm;
use app\forms\mall\goods\CatLevelForm;
use app\forms\mall\goods\CatListForm;
use app\forms\mall\goods\CommentForm;
use app\forms\mall\goods\DeleteAttrForm;
use app\forms\mall\goods\GoodsForm;
use app\forms\mall\goods\GoodsListForm;
use app\forms\mall\goods\GoodsTrafficForm;
use app\forms\mall\goods\GoodsPropertyForm;
use app\forms\mall\goods\GoodsUpDownJobForm;
use app\forms\goods\ServiceTagForm;
use app\forms\mall\slhc\BatchUpdateGoodsForm;


class GoodsController extends Controller
{
    /** 商品流量排行，统计当前商城已登录用户的累计浏览。 */
    public function actionTrafficList()
    {
        $form = new GoodsTrafficForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 商品服务标签:详情页价格下面那排「✓ 全国包邮」。平台在这儿维护的是全商城通用的那批(mch_id=0)。
     * 商家自建的标签在他自己后台维护,平台不经手(要看的话在商品编辑器的可选项里能看到)。
     */
    public function actionServiceTagList()
    {
        $form = (new ServiceTagForm())->asOwner(0);
        return $this->asJson($form->getList());
    }

    /** 商品编辑器的可选标签:平台标签 + 这个商品所属商家自建的(goods_mch_id) */
    public function actionServiceTagSelectable()
    {
        $form = (new ServiceTagForm())->asOwner(0);
        $form->attributes = $this->request->get();
        return $this->asJson($form->getSelectable());
    }

    public function actionServiceTagEdit()
    {
        $form = (new ServiceTagForm())->asOwner(0);
        $form->attributes = $this->request->post();
        return $this->asJson($form->save());
    }

    public function actionServiceTagDelete()
    {
        $form = (new ServiceTagForm())->asOwner(0);
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * [新增] 批量上下架
     * @return \yii\web\Response
     */
    public function actionBatchStatusChange()
    {
        $form = new GoodsBatchStatusForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-13
     * @Time: 16:25
     * @Note:商品列表
     * @return \yii\web\Response
     */
    public function actionList()
    {
        $form = new GoodsListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 22:29
     * @Note:商品编辑
     */
    public function actionEdit()
    {
        $form = new GoodsForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 22:35
     * @Note:商品删除
     */
    public function actionDelete()
    {
        $form = new GoodsForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->delete());
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '未处理任何操作']);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-27
     * @Time: 12:04
     * @Note:上下架
     * @return \yii\web\Response
     */
    public function actionStatusChange()
    {
        $form = new GoodsForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->statusChange());
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '未处理任何操作']);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-11
     * @Time: 9:18
     * @Note:推荐设置
     * @return \yii\web\Response
     */
    public function actionRecommendChange()
    {
        $form = new GoodsForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->recommendChange());
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '未处理任何操作']);
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 22:28
     * @Note:分类编辑
     * @return \yii\web\Response
     */
    public function actionCatEdit()
    {
        $form = new CatForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-27
     * @Time: 14:49
     * @Note:分类列表
     */
    public function actionCatList()
    {
        $form = new CatListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->query());
    }

    /**
     * Created by: ganxh
     * Date: 2021/10/9
     * Time: 16:49
     * Note:所有分类
     * @return \yii\web\Response
     */
    public function actionCat()
    {
        $form = new CatListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-13
     * @Time: 16:25
     * @Note:展示分类
     * @return \yii\web\Response
     */
    public function actionCatShow()
    {
        $form = new CatForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->showChange());
    }

    /**
     * Created by: ganxh
     * Date: 2021/11/9
     * Time: 14:17
     * Note:
     * @return \yii\web\Response
     */
    public function actionCatLevel()
    {
        $form = new CatLevelForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionAllCatLevel()
    {
        $form = new CatLevelForm();
        return $form->getList();
    }

    /**
     * Created by: ganxh
     * Date: 2021/11/9
     * Time: 14:16
     * Note:分类删除
     * @return \yii\web\Response
     */
    public function actionCatDelete()
    {

        $form = new CatForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/3/15
     * Time：20:33
     * Note：商品上下架队列
     */
    public function actionGoodsUpDownJob()
    {
        $form = new GoodsUpDownJobForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionGoodsProperty()
    {

        $form = new GoodsPropertyForm();
        return $form->getSetting();
    }

    public function actionAddAttr()
    {
        $form = new AddAttrForm();
        $form->attributes = $this->request->post();
        return $form->add();
    }

    public function actionDeleteAttr()
    {
        $form = new DeleteAttrForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    public function actionCommentList()
    {
        $form = new GoodsCommentListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    public function actionCommentDelete()
    {
        $form = new CommentForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->delete());
    }

    public function actionComment()
    {
        $form = new CommentForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    public function actionCheck()
    {
        $form = new GoodsForm();
        $form->attributes = $this->request->post();
        return $form->check();
    }

    public function actionCatTree()
    {
        $form = new CatListForm();
        $form->attributes = $this->request->get();
        return $form->getTreeList();
    }

    /**
     * [新增] 指派平台商品给商户
     * @return \yii\web\Response
     */
    public function actionAssignMch()
    {
        $form = new GoodsAssignMchForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->assign());
    }
}
