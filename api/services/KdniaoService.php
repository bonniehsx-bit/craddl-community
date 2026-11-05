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

namespace app\services;

use app\models\BaseModel;
use app\models\ExpressSetting;
use Yii;
use yii\httpclient\Client;

class KdniaoService extends BaseModel
{
    private $EBusinessID;
    private $ApiKey;

    private $ReqURL = "https://api.kdniao.com/api/dist";

    public function __construct($mall_id = null)
    {
        if (!$mall_id) {
            $mall_id = Yii::$app->mall->id;
        }
        $setting = ExpressSetting::findOne(['mall_id' => $mall_id, 'is_delete' => 0]);
        if (!$setting || !$setting->kdniao_appcode || !$setting->kdniao_customer) {
            throw new \Exception('快递鸟接口未配置或配置不完整');
        }
        $this->EBusinessID = $setting->kdniao_customer;
        $this->ApiKey = $setting->kdniao_appcode;
    }

    /**
     * 查询物流轨迹
     * @param string $shipperCode 快递公司编码
     * @param string $logisticCode 快递单号
     * @param string $customerName 手机号后四位 (可选)
     * @return array
     * @throws \Exception
     */
    public function query(string $express_no, ?string $customerName = null): array
    {
        // 1. [优化] 使用数组构建请求体，然后用 json_encode，更安全、更清晰
        $requestData = [
            'CustomerName' => $customerName,
            'OrderCode' => '',
            'LogisticCode' => $express_no,
        ];
        $requestDataJson = json_encode($requestData, JSON_UNESCAPED_UNICODE);

        // 2. [优化] 封装签名和请求逻辑
        $datas = [
            'EBusinessID' => $this->EBusinessID,
            'RequestType' => '8002',
            'RequestData' => urlencode($requestDataJson),
            'DataType' => '2',
        ];
        $datas['DataSign'] = $this->generateSign($requestDataJson);

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $response = $client->createRequest()
            ->setHeaders(['Content-type' => 'application/x-www-form-urlencoded'])
            ->setMethod('post')
            ->setUrl($this->ReqURL)
            ->setData($datas)
            ->send();

        if (!$response->isOk) {
            throw new \Exception('请求快递鸟API失败: ' . $response->content);
        }

        // 3. [优化] 封装响应处理逻辑
        return $this->parseResponse($response->data);
    }

    private function generateSign(string $data): string
    {
        return urlencode(base64_encode(md5($data . $this->ApiKey)));
    }

    private function parseResponse(array $res): array
    {
        Yii::warning($res); // 保留日志

        $stateMap = [
            '0' => '暂无轨迹',
            '2' => '在途',
            '3' => '已签收',
            '4' => '问题件',
        ];

        $success = $res['Success'] ?? false;
        $state = $res['State'] ?? null;

        if (!$success) {
            throw new \Exception($res['Reason'] ?? '快递鸟接口返回未知错误');
        }

        return [
            'msg' => $stateMap[$state] ?? '查询成功',
            'list' => isset($res['Traces']) ? array_reverse($res['Traces']) : [],
            'state' => $state,
            'shipper_code' => $res['ShipperCode'] ?? '',
            'logistic_code' => $res['LogisticCode'] ?? '',
        ];
    }
}