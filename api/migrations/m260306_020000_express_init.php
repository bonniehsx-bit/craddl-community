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

use app\core\Migration;

/**
 * Class m260306_020000_express_init
 */
class m260306_020000_express_init extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB COMMENT="快递公司设置表"';
        }

        $this->createTable('{{%express}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID为0表示全局可用'),
            'name' => $this->string(64)->notNull()->defaultValue('')->comment('快递公司名称'),
            'code' => $this->string(64)->notNull()->defaultValue('')->comment('拼音简码/快递鸟编码'),
            'key' => $this->string(64)->notNull()->defaultValue('')->comment('原始核心Key'),
            'ali_key' => $this->string(64)->notNull()->defaultValue('')->comment('阿里菜鸟Key'),
            'r_datas' => $this->text()->comment('默认面单样式JSON'),
            'sort' => $this->integer()->notNull()->defaultValue(100)->comment('排序'),
        ], $tableOptions);

        // 获取原有的静态数组并写入数据库
        // 注意由于我们在同一项目中，可以直接调用原Helper，把历史数据刷进去
        $expressList = [
            [
                'id' => '1',
                'name' => '顺丰',
                'code' => 'shunfeng',
                'key' => 'SF',
                'ali_key' => 'SF',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '2',
                'name' => '申通',
                'code' => 'shentong',
                'key' => 'STO',
                'ali_key' => 'STO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '3',
                'name' => '韵达快运',
                'code' => 'yunda',
                'key' => 'YD',
                'ali_key' => 'YD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '4',
                'name' => '天天快递',
                'code' => 'tiantian',
                'key' => 'HHTT',
                'ali_key' => 'HHTT',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '5',
                'name' => '圆通速递',
                'code' => 'yuantong',
                'key' => 'YTO',
                'ali_key' => 'YTO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '6',
                'name' => '中通速递',
                'code' => 'zhongtong',
                'key' => 'ZTO',
                'ali_key' => 'ZTO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '7',
                'name' => 'ems快递',
                'code' => 'ems',
                'key' => 'EMS',
                'ali_key' => 'EMS',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '8',
                'name' => '百世快递',
                'code' => 'huitongkuaidi',
                'key' => 'HTKY',
                'ali_key' => 'HTKY',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '9',
                'name' => '全峰快递',
                'code' => 'quanfengkuaidi',
                'key' => 'QFKD',
                'ali_key' => 'QFKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '10',
                'name' => '宅急送',
                'code' => 'zhaijisong',
                'key' => 'ZJS',
                'ali_key' => 'ZJS',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '11',
                'name' => 'aae全球专递',
                'code' => 'aae',
                'key' => 'AAE',
                'ali_key' => 'AAE',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '12',
                'name' => '安捷快递',
                'code' => 'anjie',
                'key' => 'AJ',
                'ali_key' => 'AJ',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '13',
                'name' => '安信达快递',
                'code' => 'anxindakuaixi',
                'key' => 'anxindakuaixi',
                'ali_key' => 'AXD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '14',
                'name' => '彪记快递',
                'code' => 'biaojikuaidi',
                'key' => 'biaojikuaidi',
                'ali_key' => 'biaojikuaidi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '15',
                'name' => 'bht',
                'code' => 'bht',
                'key' => 'BHT',
                'ali_key' => 'BHT',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '16',
                'name' => '百福东方国际物流',
                'code' => 'baifudongfang',
                'key' => 'BFDF',
                'ali_key' => 'BFDF',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '17',
                'name' => '中国东方（COE）',
                'code' => 'coe',
                'key' => 'COE',
                'ali_key' => 'COE',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '18',
                'name' => '长宇物流',
                'code' => 'changyuwuliu',
                'key' => 'changyuwuliu',
                'ali_key' => 'changyuwuliu',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '19',
                'name' => '大田物流',
                'code' => 'datianwuliu',
                'key' => 'DTWL',
                'ali_key' => 'DTWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '20',
                'name' => '德邦物流',
                'code' => 'debangwuliu',
                'key' => 'DBL',
                'ali_key' => 'DBL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '21',
                'name' => 'dhl',
                'code' => 'dhl',
                'key' => 'DHL',
                'ali_key' => 'DHL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '22',
                'name' => 'dpex',
                'code' => 'dpex',
                'key' => 'DPEX',
                'ali_key' => 'DPEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '23',
                'name' => 'd速快递',
                'code' => 'dsukuaidi',
                'key' => 'dsukuaidi',
                'ali_key' => 'DSWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '24',
                'name' => '递四方',
                'code' => 'disifang',
                'key' => 'disifang',
                'ali_key' => 'D4PX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '25',
                'name' => 'fedex（国外）',
                'code' => 'fedex',
                'key' => 'FEDEX_GJ',
                'ali_key' => 'FEDEX_GJ',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '26',
                'name' => '飞康达物流',
                'code' => 'feikangda',
                'key' => 'FKD',
                'ali_key' => 'FKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '27',
                'name' => '凤凰快递',
                'code' => 'fenghuangkuaidi',
                'key' => 'fenghuangkuaidi',
                'ali_key' => 'fenghuangkuaidi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '28',
                'name' => '飞快达',
                'code' => 'feikuaida',
                'key' => 'feikuaida',
                'ali_key' => 'feikuaida',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '29',
                'name' => '国通快递',
                'code' => 'guotongkuaidi',
                'key' => 'GTO',
                'ali_key' => 'GTO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '30',
                'name' => '港中能达物流',
                'code' => 'ganzhongnengda',
                'key' => 'ganzhongnengda',
                'ali_key' => 'ganzhongnengda',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '31',
                'name' => '广东邮政物流',
                'code' => 'guangdongyouzhengwuliu',
                'key' => 'GDEMS',
                'ali_key' => 'GDEMS',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '32',
                'name' => '共速达',
                'code' => 'gongsuda',
                'key' => 'GSD',
                'ali_key' => 'GSD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '33',
                'name' => '恒路物流',
                'code' => 'hengluwuliu',
                'key' => 'HLWL',
                'ali_key' => 'HLWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '34',
                'name' => '华夏龙物流',
                'code' => 'huaxialongwuliu',
                'key' => 'HXLWL',
                'ali_key' => 'HXLWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '35',
                'name' => '海红',
                'code' => 'haihongwangsong',
                'key' => 'haihongwangsong',
                'ali_key' => 'haihongwangsong',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '36',
                'name' => '海外环球',
                'code' => 'haiwaihuanqiu',
                'key' => 'haiwaihuanqiu',
                'ali_key' => 'haiwaihuanqiu',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '37',
                'name' => '佳怡物流',
                'code' => 'jiayiwuliu',
                'key' => 'JYWL',
                'ali_key' => 'JYWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '38',
                'name' => '京广速递',
                'code' => 'jinguangsudikuaijian',
                'key' => 'JGSD',
                'ali_key' => 'JGSD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '39',
                'name' => '急先达',
                'code' => 'jixianda',
                'key' => 'JXD',
                'ali_key' => 'JXD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '40',
                'name' => '佳吉物流',
                'code' => 'jiajiwuliu',
                'key' => 'jiajiwuliu',
                'ali_key' => 'JJKY',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '41',
                'name' => '加运美物流',
                'code' => 'jymwl',
                'key' => 'JYM',
                'ali_key' => 'JYM',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '42',
                'name' => '金大物流',
                'code' => 'jindawuliu',
                'key' => 'jindawuliu',
                'ali_key' => 'jindawuliu',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '43',
                'name' => '嘉里大通',
                'code' => 'jialidatong',
                'key' => 'JLDT',
                'ali_key' => 'jialidatong',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '44',
                'name' => '晋越快递',
                'code' => 'jykd',
                'key' => 'JYKD',
                'ali_key' => 'JYKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '45',
                'name' => '快捷速递',
                'code' => 'kuaijiesudi',
                'key' => 'FAST',
                'ali_key' => 'FAST',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '46',
                'name' => '联邦快递（国内）',
                'code' => 'lianb',
                'key' => 'FEDEX',
                'ali_key' => 'FEDEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '47',
                'name' => '联昊通物流',
                'code' => 'lianhaowuliu',
                'key' => 'lianhaowuliu',
                'ali_key' => 'LHT',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '48',
                'name' => '龙邦物流',
                'code' => 'longbanwuliu',
                'key' => 'longbanwuliu',
                'ali_key' => 'LB',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '49',
                'name' => '立即送',
                'code' => 'lijisong',
                'key' => 'LJSKD',
                'ali_key' => 'lijisong',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '50',
                'name' => '乐捷递',
                'code' => 'lejiedi',
                'key' => 'lejiedi',
                'ali_key' => 'lejiedi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '51',
                'name' => '民航快递',
                'code' => 'minghangkuaidi',
                'key' => 'MHKD',
                'ali_key' => 'MHKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '52',
                'name' => '美国快递',
                'code' => 'meiguokuaidi',
                'key' => 'meiguokuaidi',
                'ali_key' => 'meiguokuaidi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '53',
                'name' => '门对门',
                'code' => 'menduimen',
                'key' => 'MDM',
                'ali_key' => 'menduimen',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '54',
                'name' => 'OCS',
                'code' => 'ocs',
                'key' => 'OCS',
                'ali_key' => 'ocs',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '55',
                'name' => '配思货运',
                'code' => 'peisihuoyunkuaidi',
                'key' => 'peisihuoyunkuaidi',
                'ali_key' => 'peisihuoyunkuaidi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '56',
                'name' => '全晨快递',
                'code' => 'quanchenkuaidi',
                'key' => 'QCKD',
                'ali_key' => 'QCKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '57',
                'name' => '全际通物流',
                'code' => 'quanjitong',
                'key' => 'quanjitong',
                'ali_key' => 'quanjitong',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '58',
                'name' => '全日通快递',
                'code' => 'quanritongkuaidi',
                'key' => 'QRT',
                'ali_key' => 'QRT',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '59',
                'name' => '全一快递',
                'code' => 'quanyikuaidi',
                'key' => 'UAPEX',
                'ali_key' => 'UAPEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '60',
                'name' => '如风达',
                'code' => 'rufengda',
                'key' => 'RFD',
                'ali_key' => 'RFD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '61',
                'name' => '三态速递',
                'code' => 'santaisudi',
                'key' => 'santaisudi',
                'ali_key' => 'santaisudi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '62',
                'name' => '盛辉物流',
                'code' => 'shenghuiwuliu',
                'key' => 'shenghuiwuliu',
                'ali_key' => 'SHWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '63',
                'name' => '速尔物流',
                'code' => 'sue',
                'key' => 'SURE',
                'ali_key' => 'SURE',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '64',
                'name' => '盛丰物流',
                'code' => 'shengfeng',
                'key' => 'SFWL',
                'ali_key' => 'SFWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '65',
                'name' => '赛澳递',
                'code' => 'saiaodi',
                'key' => 'SAD',
                'ali_key' => 'SAD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '66',
                'name' => '天地华宇',
                'code' => 'tiandihuayu',
                'key' => 'HOAU',
                'ali_key' => 'HOAU',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '67',
                'name' => 'tnt',
                'code' => 'tnt',
                'key' => 'TNT',
                'ali_key' => 'TNT',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '68',
                'name' => 'ups',
                'code' => 'ups',
                'key' => 'UPS',
                'ali_key' => 'UPS',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '69',
                'name' => '万家物流',
                'code' => 'wanjiawuliu',
                'key' => 'WJWL',
                'ali_key' => 'WJWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '70',
                'name' => '文捷航空速递',
                'code' => 'wenjiesudi',
                'key' => 'wenjiesudi',
                'ali_key' => 'wenjiesudi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '71',
                'name' => '伍圆',
                'code' => 'wuyuan',
                'key' => 'wuyuan',
                'ali_key' => 'wuyuan',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '72',
                'name' => '万象物流',
                'code' => 'wxwl',
                'key' => 'wxwl',
                'ali_key' => 'WXWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '73',
                'name' => '新邦物流',
                'code' => 'xinbangwuliu',
                'key' => 'xinbangwuliu',
                'ali_key' => 'XBWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '74',
                'name' => '信丰物流',
                'code' => 'xinfengwuliu',
                'key' => 'XFEX',
                'ali_key' => 'XFEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '75',
                'name' => '亚风速递',
                'code' => 'yafengsudi',
                'key' => 'YFSD',
                'ali_key' => 'YFSD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '76',
                'name' => '一邦速递',
                'code' => 'yibangwuliu',
                'key' => 'yibangwuliu',
                'ali_key' => 'yibangwuliu',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '77',
                'name' => '优速物流',
                'code' => 'youshuwuliu',
                'key' => 'UC',
                'ali_key' => 'UC',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '78',
                'name' => '邮政快递包裹',
                'code' => 'youzhengguonei',
                'key' => 'YZBK',
                'ali_key' => 'YZPY',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '79',
                'name' => '邮政国际包裹挂号信',
                'code' => 'youzhengguoji',
                'key' => 'youzhengguoji',
                'ali_key' => 'youzhengguoji',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '80',
                'name' => '远成物流',
                'code' => 'yuanchengwuliu',
                'key' => 'YCWL',
                'ali_key' => 'YCWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '81',
                'name' => '源伟丰快递',
                'code' => 'yuanweifeng',
                'key' => 'yuanweifeng',
                'ali_key' => 'yuanweifeng',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '82',
                'name' => '元智捷诚快递',
                'code' => 'yuanzhijiecheng',
                'key' => 'yuanzhijiecheng',
                'ali_key' => 'yuanzhijiecheng',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '83',
                'name' => '运通快递',
                'code' => 'yuntongkuaidi',
                'key' => 'YTKD',
                'ali_key' => 'CTG',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '84',
                'name' => '越丰物流',
                'code' => 'yuefengwuliu',
                'key' => 'YFEX',
                'ali_key' => 'YFEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '85',
                'name' => '源安达',
                'code' => 'yad',
                'key' => 'YADEX',
                'ali_key' => 'YADEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '86',
                'name' => '银捷速递',
                'code' => 'yinjiesudi',
                'key' => 'yinjiesudi',
                'ali_key' => 'yinjiesudi',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '87',
                'name' => '中铁快运',
                'code' => 'zhongtiekuaiyun',
                'key' => 'ZTKY',
                'ali_key' => 'ZTKY',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '88',
                'name' => '中邮物流',
                'code' => 'zhongyouwuliu',
                'key' => 'ZYKD',
                'ali_key' => 'ZYWL',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '89',
                'name' => '忠信达',
                'code' => 'zhongxinda',
                'key' => 'zhongxinda',
                'ali_key' => 'zhongxinda',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '90',
                'name' => '芝麻开门',
                'code' => 'zhimakaimen',
                'key' => 'zhimakaimen',
                'ali_key' => 'ZMKMEX',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '91',
                'name' => '安能物流',
                'code' => 'annengwuliu',
                'key' => 'ANE',
                'ali_key' => 'ANE',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '92',
                'name' => '京东快递',
                'code' => 'jd',
                'key' => 'JD',
                'ali_key' => 'jd',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '93',
                'name' => '众邮快递',
                'code' => 'zhongyouex',
                'key' => 'ZYE',
                'ali_key' => 'ZYKD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '94',
                'name' => '极兔速递',
                'code' => 'jtexpress',
                'key' => 'JTSD',
                'ali_key' => 'JITU',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '95',
                'name' => '中通快运',
                'code' => 'zhongtongkuaiyun',
                'key' => 'ZTOKY',
                'ali_key' => 'ZTO56',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            // New additions for direct matching
            [
                'id' => '96',
                'name' => '顺丰快递',
                'code' => 'shunfeng',
                'key' => 'SF',
                'ali_key' => 'SF',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '97',
                'name' => '申通快递',
                'code' => 'shentong',
                'key' => 'STO',
                'ali_key' => 'STO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '98',
                'name' => '圆通快递',
                'code' => 'yuantong',
                'key' => 'YTO',
                'ali_key' => 'YTO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '99',
                'name' => '中通快递',
                'code' => 'zhongtong',
                'key' => 'ZTO',
                'ali_key' => 'ZTO',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
            [
                'id' => '100',
                'name' => '韵达快递',
                'code' => 'yunda',
                'key' => 'YD',
                'ali_key' => 'YD',
                'r_datas' => '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]',
            ],
        ];

        $insertData = [];
        $now = time();
        foreach ($expressList as $item) {
            $insertData[] = [
                $item['id'],
                0, // mall_id 默认 0
                $item['name'],
                $item['code'],
                $item['key'] ?? '',
                $item['ali_key'] ?? '',
                $item['r_datas'] ?? '',
                $item['id'], // 用id做个默认排序
                0,
                $now,
                $now,
                0,
            ];
        }

        if (!empty($insertData)) {
            $this->batchInsert('{{%express}}', [
                'id',
                'mall_id',
                'name',
                'code',
                'key',
                'ali_key',
                'r_datas',
                'sort',
                'is_delete',
                'created_at',
                'updated_at',
                'deleted_at'
            ], $insertData);
        }

        // 插入菜单项
        // order_num: 放到商城设置后边，大概设个1
        // 注意父级 parent_id 我们使用 'mall'，菜单 menu_id= 'mall-express'
        $menuItem = [
            'menu_id' => 'mall-express',
            'menu_name' => '快递公司',
            'icon' => 'form',
            'menu_type' => 'C',
            'order_num' => 2,
            'parent_id' => 'mall', // 父级是商城管理
            'is_cache' => 1,
            'hidden' => 0,
            'is_frame' => 1,
            'status' => 0,
            'path' => 'express',
            'component' => 'mall/express',
            'query' => '',
            'perms' => '',
            'is_delete' => 0,
            'created_at' => 0,
            'updated_at' => 0,
            'deleted_at' => 0,
            'created_time' => date('Y-m-d H:i:s'),
            'updated_time' => null,
            'deleted_time' => null,
            'link' => '',
            'plugin' => 'mall',
            'active_menu' => '',
            'breadcrumb' => 1,
            'name' => 'mall-express',
            'show_tag' => 1
        ];

        // 查询出当前最大的 menu id (如果你系统里有个自增的主键 id 的话，batchInsert 需要注意主键，但有些菜单表可能 id是自动生成的。看前一个迁移，他们写死了 ID。所以我通过 DB 去拿最大的 ID)
        $maxId = (new \yii\db\Query())->from('{{%menu}}')->max('id');
        $menuItem['id'] = $maxId + 1;

        $cols = array_keys($menuItem);
        $vals = array_values($menuItem);

        $this->insert('{{%menu}}', $menuItem);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'mall-express']);
        $this->dropTable('{{%express}}');
    }
}
