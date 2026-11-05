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

namespace app\plugins\mch\forms\api\mch;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\Address;
use app\models\BaseModel;
use app\plugins\mch\events\MchEvent;
use app\plugins\mch\helpers\MchApplyFieldRegistry;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchCertificate;
use app\plugins\mch\services\MchApplyConfigService;
use Yii;
use yii\helpers\Json;

/**
 * 商家入驻申请(C 端)—— 字段可配置 + 证照库版本。
 *
 *  - get():  下发 setting + 每个允许主体类型的「启用字段配置 + 证照定义」(apply_config),驳回重填时附带已提交证照。
 *  - save(): 按 MchApplyConfigService 解析的配置动态校验(启用+必填)+ 证照必填/有效期校验;
 *            核心字段写 plugin_mch 真实列;证照写 plugin_mch_certificate;营业执照回写 business_license 列兼容下游。
 *
 * 注:字段「显隐/必填」以后台配置为准(表空=注册表默认=旧行为),本类不再写死 required 规则。
 */
class MchApplyForm extends BaseModel
{
    public $type;
    public $mch_name;
    public $cat_id;
    public $province_code;
    public $city_code;
    public $county_code;
    public $town_code;          // [修复] 加入 safe 规则,否则批量赋值丢弃,街道永远存不进库
    public $address;
    public $location_address;   // 门店地图位置文字
    public $latitude;
    public $longitude;
    public $logo;
    public $banner_images;
    public $mobile;
    public $legal_person_name;
    public $business_license;   // 兼容字段:实际由营业执照证照回写,不再作为前端表单项
    public $id_card_front;
    public $id_card_back;
    public $other_certs;
    public $description;
    public $opening_hours;
    public $certificates;       // 证照提交数组 [{cert_def_id,image,valid_start,valid_end,is_long_term}]

    public function rules()
    {
        return [
            [['type'], 'required'],
            // 主体类型范围:线上 / 门店 / 个人(Mch::TYPE_HOTEL=3 已废弃,不允许新建)
            [['type'], 'in', 'range' => [Mch::TYPE_UP_STORE, Mch::TYPE_DOWN_STORE, Mch::TYPE_INDIVIDUAL], 'message' => '非法主体类型'],
            [['cat_id'], 'integer'],
            [['mobile'], 'match', 'pattern' => '/^1[3-9]\d{9}$/', 'message' => '请填写正确的手机号'],
            // 其余字段全部 safe:批量赋值放行(含 town_code/location_address/certificates);
            // 「是否必填」由 checkConfig() 按后台配置动态判定,不在此写死。
            [[
                'mch_name', 'province_code', 'city_code', 'county_code', 'town_code', 'address',
                'location_address', 'latitude', 'longitude', 'logo', 'banner_images', 'legal_person_name',
                'business_license', 'id_card_front', 'id_card_back', 'other_certs', 'description',
                'opening_hours', 'certificates',
            ], 'safe'],
        ];
    }

    public function get()
    {
        $mallId = \Yii::$app->mall->id;

        // 当前用户的申请记录(未登录跳过)
        $mch = null;
        if (!\Yii::$app->user->isGuest) {
            $userId = \Yii::$app->user->id;
            $mch = Mch::find()->where([
                'user_id'   => $userId,
                'mall_id'   => $mallId,
                'is_delete' => 0,
            ])->asArray()->one();
        }

        // setting(允许类型 / 说明 / 协议 / 分类)
        $settingForm = new \app\plugins\mch\forms\api\setting\SettingForm();
        $settingRes = $settingForm->getIndex();
        $setting = $settingRes['data']['setting'] ?? [];

        if ($mch) {
            // banner_images: 库里是 JSON 字符串 → 解析成数组,修复 tui-upload 回显(旧 bug)
            $mch['banner_images'] = !empty($mch['banner_images']) ? (Json::decode($mch['banner_images']) ?: []) : [];
            // 省市区中文回显(驳回重填用)
            $addr = Address::find()
                ->select(['province_name', 'city_name', 'county_name'])
                ->where([
                    'province_code' => $mch['province_code'],
                    'city_code'     => $mch['city_code'],
                    'county_code'   => $mch['county_code'],
                ])
                ->asArray()
                ->one();
            $mch['area'] = $addr
                ? trim(($addr['province_name'] ?? '') . ' ' . ($addr['city_name'] ?? '') . ' ' . ($addr['county_name'] ?? ''))
                : '';
            // 已提交证照,供回显
            $mch['certificates'] = MchCertificate::find()
                ->where(['mch_id' => $mch['id'], 'mall_id' => $mallId, 'is_delete' => 0])
                ->asArray()->all();
        }

        // 允许的主体类型(归一化:去重 + 转 int,过滤已废弃的 3)
        $allTypeMap = [
            Mch::TYPE_UP_STORE   => '线上商家',
            Mch::TYPE_DOWN_STORE => '门店商家',
            Mch::TYPE_INDIVIDUAL => '个人租户',
        ];
        $allowedTypes = array_values(array_unique(array_map(
            'intval',
            (array)($setting['allowed_mch_types'] ?? [1, 2, 4])
        )));
        $mchTypes = [];
        $applyConfig = [];
        foreach ($allowedTypes as $typeVal) {
            if (!isset($allTypeMap[$typeVal])) {
                continue;
            }
            $mchTypes[] = ['value' => (int)$typeVal, 'label' => $allTypeMap[$typeVal]];
            // 每个允许类型:仅下发「已启用」字段 + 该类型证照定义,供 C 端动态渲染
            $fields = array_values(array_filter(
                MchApplyConfigService::getFieldConfig($mallId, $typeVal),
                function ($f) {
                    return !empty($f['is_enabled']);
                }
            ));
            $applyConfig[(string)$typeVal] = [
                'fields' => $fields,
                'certs'  => MchApplyConfigService::getCertDefs($mallId, $typeVal),
            ];
        }
        $setting['mch_types']         = $mchTypes;
        $setting['allowed_mch_types'] = $allowedTypes;
        $setting['apply_config']      = $applyConfig;
        $setting['field_groups']      = MchApplyFieldRegistry::groups();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '获取成功',
            'data' => [
                'setting' => $setting,
                'mch'     => $mch,
            ],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = Yii::$app->mall->id;

        // 按后台配置动态校验「字段必填 + 证照必填/有效期」
        if ($err = $this->checkConfig($mallId)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $err];
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $userId = Yii::$app->user->id;
            $type = (int)$this->type;

            // 1. 主表(必须带 mall_id —— find() 无默认作用域,user_id 全局唯一会跨商城串号)
            $mch = Mch::findOne(['user_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0]);
            if (!$mch) {
                $mch = new Mch();
                $mch->mall_id = $mallId;
                $mch->user_id = $userId;
            } else {
                if ($mch->status == 1) {
                    throw new \Exception('您已拥有正常营业的店铺，请勿重复申请');
                }
                if ($mch->status == 0) {
                    throw new \Exception('您的申请正在审核中，请耐心等待');
                }
                if ($mch->status == 3) {
                    throw new \Exception('您的店铺已被禁用，请联系平台处理');
                }
                // status==2(已驳回)→ 允许重新提交
            }

            $mch->type = $type;
            $mch->status = 0; // 重置为待审核

            // 通用字段
            $mch->mch_name = (string)$this->mch_name;
            $mch->cat_id = (int)$this->cat_id;
            $mch->floor = '';
            $mch->mobile = (string)$this->mobile;
            $mch->tel = (string)$this->mobile;
            $mch->logo = (string)$this->logo;
            $mch->banner_images = Json::encode(is_array($this->banner_images) ? $this->banner_images : []);
            $mch->other_certs = Json::encode(is_array($this->other_certs) ? $this->other_certs : []);
            $mch->description = (string)$this->description;

            // 统一经营地址:开启后一律用后台配好的默认值,**忽略前端传来的任何地址值**。
            // 表单虽然已经不下发这几个字段(getFieldConfig 里过滤掉了),但接口是公开的,
            // 有人照着旧版本硬传 province_code/address 一样能进来 —— 在落库前直接盖掉,
            // 保证同一栋楼里的商户地址真的一致。
            if (MchApplyConfigService::useDefaultAddress($mallId)) {
                $da = MchApplyConfigService::defaultAddress($mallId);
                $this->province_code    = $da['province_code'];
                $this->city_code        = $da['city_code'];
                $this->county_code      = $da['county_code'];
                $this->town_code        = $da['town_code'];
                $this->address          = $da['address'];
                $this->location_address = $da['location_address'];
                $this->longitude        = $da['longitude'];
                $this->latitude         = $da['latitude'];
            }

            // 地址(town_code/经纬度 空则存 null)
            $mch->province_code = $this->province_code ?: null;
            $mch->city_code = $this->city_code ?: null;
            $mch->county_code = $this->county_code ?: null;
            $mch->town_code = $this->notBlank($this->town_code) ? (int)$this->town_code : null;
            $mch->address = (string)$this->address;
            $mch->latitude = $this->notBlank($this->latitude) ? $this->latitude : null;
            $mch->longitude = $this->notBlank($this->longitude) ? $this->longitude : null;
            $mch->location_address = $this->notBlank($this->location_address) ? $this->location_address : (string)$this->address;

            // 资质:营业执照由证照回写;身份证/法人仍为系统字段
            //   - 个人租户:营业执照不适用 → 清空
            //   - 线上/门店:解析到营业执照图才写;解析不到(营业执照证照被后台停用)→ 保留旧值,防冲空已审执照
            $blImage = $this->businessLicenseImage($mallId);
            if ($type == Mch::TYPE_INDIVIDUAL) {
                $mch->business_license = '';
            } elseif ($blImage !== '') {
                $mch->business_license = $blImage;
            }
            $mch->legal_person_name = (string)$this->legal_person_name;
            $mch->id_card_front = (string)$this->id_card_front;
            $mch->id_card_back = (string)$this->id_card_back;

            // 个人租户:无分类
            if ($type == Mch::TYPE_INDIVIDUAL) {
                $mch->cat_id = 0;
                $mch->opening_hours = '';
            }

            // 营业时间 / 配送:门店存填写值;线上默认 24 小时、无配送
            if ($type == Mch::TYPE_DOWN_STORE) {
                $mch->opening_hours = (string)$this->opening_hours;
            } elseif ($type == Mch::TYPE_UP_STORE) {
                $mch->opening_hours = '24小时';
                $mch->delivery_options = Json::encode([]);
                $mch->city_delivery_distance = 0;
            }

            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }

            // 2. 证照:重新申请先清旧再写新
            $this->saveCertificates($mallId, $mch->id);

            $transaction->commit();

            try {
                Yii::$app->trigger(MchEvent::MCH_SUBMITTED, new MchEvent(['mch' => $mch]));
            } catch (\Throwable $e) {
                Yii::error('[MchApply] 触发入驻通知事件失败 mch#' . $mch->id . ' ' . $e->getMessage());
            }

            // 商城配置了「无需审核，自动通过」时,立刻走一遍与人工审核完全相同的通过流程
            if ($this->autoApproveIfConfigured($mch)) {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '入驻成功，店铺已开通'];
            }

            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '申请已提交，请等待审核'];
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }

    // ---------------- 内部:动态校验 ----------------

    /** 按配置校验字段必填 + 证照必填/有效期,返回错误文案或 null */
    private function checkConfig($mallId)
    {
        $type = (int)$this->type;

        foreach (MchApplyConfigService::getFieldConfig($mallId, $type) as $f) {
            if (empty($f['is_enabled']) || empty($f['is_required'])) {
                continue;
            }
            // 街道是否必填取决于该区县有无乡镇(前端按列表控制),后端只负责存,不硬卡
            if ($f['field_key'] === 'town') {
                continue;
            }
            if (!$this->fieldPresent($f['field_key'])) {
                return '请完善' . $f['label'];
            }
        }

        $submitted = [];
        foreach ((array)$this->certificates as $c) {
            $submitted[(int)($c['cert_def_id'] ?? 0)] = $c;
        }
        foreach (MchApplyConfigService::getCertDefs($mallId, $type) as $cd) {
            if (empty($cd['is_required'])) {
                continue;
            }
            $s = $submitted[$cd['id']] ?? null;
            if (!$s || trim((string)($s['image'] ?? '')) === '') {
                return '请上传' . $cd['name'];
            }
            if (!empty($cd['need_validity']) && empty($s['is_long_term'])) {
                if (empty($s['valid_start']) || empty($s['valid_end'])) {
                    return '请填写' . $cd['name'] . '的有效期(或勾选长期有效)';
                }
            }
        }
        return null;
    }

    /** 某 field_key 对应的值是否已填 */
    private function fieldPresent($key)
    {
        switch ($key) {
            case 'mch_name':          return $this->notBlank($this->mch_name);
            case 'cat_id':            return !empty($this->cat_id);
            case 'region':            return $this->notBlank($this->province_code) && $this->notBlank($this->city_code) && $this->notBlank($this->county_code);
            case 'address':           return $this->notBlank($this->address);
            case 'location':          return $this->notBlank($this->latitude) && $this->notBlank($this->longitude);
            case 'opening_hours':     return $this->notBlank($this->opening_hours);
            case 'legal_person_name': return $this->notBlank($this->legal_person_name);
            case 'mobile':            return $this->notBlank($this->mobile);
            case 'logo':              return $this->notBlank($this->logo);
            case 'banner_images':     return is_array($this->banner_images) && !empty($this->banner_images);
            case 'description':       return $this->notBlank($this->description);
            case 'id_card_front':     return $this->notBlank($this->id_card_front);
            case 'id_card_back':      return $this->notBlank($this->id_card_back);
            default:                  return true; // 未知字段不卡
        }
    }

    private function notBlank($v)
    {
        return !($v === null || $v === '' || (is_array($v) && empty($v)));
    }

    // ---------------- 内部:证照存储 ----------------

    /** 营业执照证照图(回写 plugin_mch.business_license 列,兼容下游) */
    private function businessLicenseImage($mallId)
    {
        $defs = MchApplyConfigService::getCertDefModels($mallId);
        foreach ((array)$this->certificates as $c) {
            $def = $defs[(int)($c['cert_def_id'] ?? 0)] ?? null;
            if ($def && $def->preset_key === 'business_license') {
                return trim((string)($c['image'] ?? ''));
            }
        }
        return '';
    }

    /**
     * 商城配置为「无需审核，自动通过」(mch_setting.review_type=0)时,提交即开通。
     *
     * 【为什么需要它】这个开关后台一直能配、前端也一直在渲染,但**全后端零读取方**:
     * save() 里写死 `$mch->status = 0`,于是运营选了"自动通过"后,申请人永远停在待审核,
     * 而运营以为不用再管审核队列 —— 入驻链路就此断成死胡同。
     *
     * 【为什么复用 MchEditForm::updateStatus 而不是自己置 status=1】
     * 审核通过不只是改一个状态位,还要:发 MCH_APPROVED 事件(plaza/massage 等插件靠它建租户)、
     * 建店长 MchClerk 关系(没有它 MchFilter 会判定"您尚未加入任何商户")、发 IM/公众号通知。
     * 自己复制一份必然漂移,所以直接走人工审核那条路,保证两种审核方式副作用完全一致。
     * 它自带事务且在本方法调用点(外层事务已 commit)之后执行,不构成嵌套。
     *
     * @return bool 是否已自动通过
     */
    private function autoApproveIfConfigured(Mch $mch): bool
    {
        $setting = OptionHelper::get('mch_setting', $mch->mall_id, []);
        // 默认 1=后台人工审核;仅显式配成 0 才自动通过
        if ((int)($setting['review_type'] ?? 1) !== 0) {
            return false;
        }

        try {
            $form = new \app\plugins\mch\forms\mall\MchEditForm();
            $form->id = $mch->id;
            $form->status = 1;
            $form->remarks = '商城已配置为无需审核，系统自动通过';
            $res = $form->updateStatus();
            if ((int)($res['code'] ?? -1) !== ApiCode::CODE_SUCCESS) {
                Yii::error("[MchApply] 自动通过失败 mch#{$mch->id}: " . ($res['msg'] ?? '未知错误'));
                return false; // 退回"待审核",运营仍可人工处理,不把商家卡死
            }
            return true;
        } catch (\Throwable $e) {
            Yii::error("[MchApply] 自动通过异常 mch#{$mch->id}: " . $e->getMessage());
            return false;
        }
    }

    /** 写入提交的证照(重新申请先清旧) */
    private function saveCertificates($mallId, $mchId)
    {
        MchCertificate::deleteAll(['mch_id' => $mchId, 'mall_id' => $mallId]);

        $defs = MchApplyConfigService::getCertDefModels($mallId);
        foreach ((array)$this->certificates as $c) {
            $defId = (int)($c['cert_def_id'] ?? 0);
            $def = $defs[$defId] ?? null;
            if (!$def) {
                continue; // 未知/已停用证照,忽略
            }
            $image = trim((string)($c['image'] ?? ''));
            if ($image === '') {
                continue;
            }
            $longTerm = !empty($c['is_long_term']) ? 1 : 0;
            $cert = new MchCertificate();
            $cert->mall_id = $mallId;
            $cert->mch_id = $mchId;
            $cert->cert_def_id = $defId;
            $cert->preset_key = $def->preset_key;
            $cert->name_snapshot = $def->name;
            $cert->image = $image;
            $cert->is_long_term = $longTerm;
            $cert->valid_start = (!$longTerm && !empty($c['valid_start'])) ? $c['valid_start'] : null;
            $cert->valid_end = (!$longTerm && !empty($c['valid_end'])) ? $c['valid_end'] : null;
            $cert->save(false);
        }
    }
}
