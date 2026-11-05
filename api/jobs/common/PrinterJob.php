<?php

/**
 * Copyright (c) 2022-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/6/18
 * Time: 8:07
 * Note:
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

namespace app\jobs\common;


use app\helpers\FeieHelper;
use app\helpers\OptionHelper;
use app\core\Job;
use app\models\PrinterLog;
use yii\helpers\Json;

class PrinterJob extends Job
{
    public $content;
    public $mall_id;
    public $type; //label  ticket

    public function execute($params = null)
    {
        $log = new PrinterLog();
        $log->mall_id = $this->mall_id;
        $log->content = is_array($this->content) ? Json::encode($this->content) : $this->content;
        $res = [];
        if ($this->type == 'label') {
            $setting = OptionHelper::get('mall_setting', $this->mall_id);
            if (!$setting || !$setting['fe_account'] || !$setting['fe_ukey'] || !$setting['fe_label_sn']) {
                \Yii::warning('飞鹅打印机配置不完整');
            }
            $time = time();
            $data = [
                'user' => $setting['fe_account'],
                'stime' => $time,
                'sig' => sha1($setting['fe_account'] . $setting['fe_ukey'] . $time),
                'apiname' => 'Open_printLabelMsg',
                'sn' => $setting['fe_label_sn'],
                'content' => $this->content,
            ];
            $res = FeieHelper::printBC128($data);
            $log->account = $setting['fe_account'];
            $log->api = 'Open_printLabelMsg';
            $log->printer_sn = $setting['fe_label_sn'];
            \Yii::warning($res);
        }
        if ($this->type == 'ticket') {
            $setting = OptionHelper::get('mall_setting', $this->mall_id);
            if (!$setting || !$setting['fe_account'] || !$setting['fe_ukey'] || !$setting['fe_ticket_sn']) {
                \Yii::warning('飞鹅打印机配置不完整');
            }
            $time = time();
            $data = [
                'user' => $setting['fe_account'],
                'stime' => $time,
                'sig' => sha1($setting['fe_account'] . $setting['fe_ukey'] . $time),
                'apiname' => 'Open_printMsg',
                'sn' => $setting['fe_ticket_sn'],
                'content' => $this->content,
            ];
            $res = FeieHelper::printMsg($data);
            $log->account = $setting['fe_account'];
            $log->printer_sn = $setting['fe_ticket_sn'];
            $log->api = 'Open_printMsg';
            \Yii::warning($res);
        }
        $log->res = Json::encode($res);
        $log->save();
    }
}
