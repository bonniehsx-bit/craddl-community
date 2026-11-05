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
 * This is the model class for table "{{%split_onboarding_log}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 关联的用户ID
 * @property string|null $platform_user_id 第三方平台返回的用户唯一标识 (如快付的userId)
 * @property string $split_class 分账渠道实现类
 * @property string $out_mer_code 外部商户号
 * @property string $status 进件状态: wait, success, failed
 * @property string|null $request_data 请求报文
 * @property string|null $response_data 响应报文 (包含H5链接)
 * @property string|null $notify_data 异步回调数据
 * @property string|null $remark 备注
 * @property string|null $h5_register_flow h5回执
 * @property string|null $application_id 应用ID
 * @property string|null $pay_auth_path 支付授权目录
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $created_time
 * @property string|null $updated_time
 * @property string|null $deleted_time
 * @property string|null $receive_user_id 分账接收方用户ID
 */
class SplitOnboardingLog extends BaseActiveRecord
{
    const STATUS_WAIT = 'WAIT';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAILED = 'FAIL';
    const STATUS_DOING = "DOING";

    public static function tableName()
    {
        return '{{%split_onboarding_log}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'split_class', 'out_mer_code', 'status'], 'required'],
            [['mall_id', 'user_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['request_data', 'response_data', 'notify_data', 'platform_user_id','receive_user_id'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['split_class', 'h5_register_flow', 'out_mer_code', 'application_id', 'pay_auth_path'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 512],
            [['status'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => '用户ID',
            'out_mer_code' => '外部商户号',
            'status' => '状态',
            'platform_user_id' => '第三方平台返回的用户唯一标识 (如快付的userId)',
            'created_time' => '申请时间',
            'application_id' => '快付应用ID',
            'h5_register_flow' => 'h5回执',
            'pay_auth_path' => '支付授权目录',
            'receive_user_id'=>'分账接收方'
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
