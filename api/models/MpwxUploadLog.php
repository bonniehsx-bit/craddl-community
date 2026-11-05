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

/**
 * 小程序代码上传记录。表 {{%mpwx_upload_log}}。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property string $appid     小程序appid
 * @property string $version   上传版本号
 * @property string $remark    上传备注/描述
 * @property int    $status    1=成功 0=失败
 * @property string $msg       结果信息/失败原因
 * @property int    $admin_id  操作管理员ID
 * @property int    $created_at
 */
class MpwxUploadLog extends BaseActiveRecord
{
    const STATUS_FAIL    = 0; // 失败
    const STATUS_SUCCESS = 1; // 成功

    public static function tableName()
    {
        return '{{%mpwx_upload_log}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'status', 'admin_id'], 'integer'],
            [['appid', 'version'], 'string', 'max' => 64],
            [['remark'], 'string', 'max' => 255],
            [['msg'], 'string'],
        ];
    }
}
