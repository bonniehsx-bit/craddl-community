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
 * 填充 express 快递公司表基础数据
 *
 * 原 m260306_020000_express_init 在某些环境下未成功写入数据，
 * 导致 jxmall_express 表为空。此 migration 按 code 查重，
 * 仅补齐缺失的记录，对已有数据无副作用，可反复执行。
 */
class m260420_000000_express_seed extends Migration
{
    public function safeUp()
    {
        // 以 code 为业务唯一键，已有数据不覆盖
        $existCodes = (new \yii\db\Query())
            ->select('code')
            ->from('{{%express}}')
            ->column();
        $existCodes = array_flip($existCodes);

        $list = $this->expressData();
        $now = time();
        $insertData = [];
        foreach ($list as $item) {
            if (isset($existCodes[$item['code']])) {
                continue;
            }
            $insertData[] = [
                0,                          // mall_id 全局可用
                $item['name'],
                $item['code'],
                $item['key'] ?? '',
                $item['ali_key'] ?? '',
                $item['r_datas'] ?? '',
                (int)$item['id'],           // 用旧 id 做默认排序，保持顺序
                0,                          // is_delete
                $now,
                $now,
                0,                          // deleted_at
            ];
        }

        if (empty($insertData)) {
            echo "    > express 数据已齐全，无需补齐\n";
            return;
        }

        $this->batchInsert('{{%express}}', [
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
            'deleted_at',
        ], $insertData);

        echo "    > 已补齐 " . count($insertData) . " 条快递公司数据\n";
    }

    public function safeDown()
    {
        // 仅删除本 migration 补齐的 code 对应记录；为简单起见不做精细回滚
        echo "    > 不支持回滚（数据性 migration）\n";
        return false;
    }

    /**
     * 快递公司基础数据（本迁移内置历史快照副本）
     */
    private function expressData(): array
    {
        $defRdatas = '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]';
        $raw = [
            ['1', '顺丰', 'shunfeng', 'SF', 'SF'],
            ['2', '申通', 'shentong', 'STO', 'STO'],
            ['3', '韵达快运', 'yunda', 'YD', 'YD'],
            ['4', '天天快递', 'tiantian', 'HHTT', 'HHTT'],
            ['5', '圆通速递', 'yuantong', 'YTO', 'YTO'],
            ['6', '中通速递', 'zhongtong', 'ZTO', 'ZTO'],
            ['7', 'ems快递', 'ems', 'EMS', 'EMS'],
            ['8', '百世快递', 'huitongkuaidi', 'HTKY', 'HTKY'],
            ['9', '全峰快递', 'quanfengkuaidi', 'QFKD', 'QFKD'],
            ['10', '宅急送', 'zhaijisong', 'ZJS', 'ZJS'],
            ['11', 'aae全球专递', 'aae', 'AAE', 'AAE'],
            ['12', '安捷快递', 'anjie', 'AJ', 'AJ'],
            ['13', '安信达快递', 'anxindakuaixi', 'anxindakuaixi', 'AXD'],
            ['14', '彪记快递', 'biaojikuaidi', 'biaojikuaidi', 'biaojikuaidi'],
            ['15', 'bht', 'bht', 'BHT', 'BHT'],
            ['16', '百福东方国际物流', 'baifudongfang', 'BFDF', 'BFDF'],
            ['17', '中国东方（COE）', 'coe', 'COE', 'COE'],
            ['18', '长宇物流', 'changyuwuliu', 'changyuwuliu', 'changyuwuliu'],
            ['19', '大田物流', 'datianwuliu', 'DTWL', 'DTWL'],
            ['20', '德邦物流', 'debangwuliu', 'DBL', 'DBL'],
            ['21', 'dhl', 'dhl', 'DHL', 'DHL'],
            ['22', 'dpex', 'dpex', 'DPEX', 'DPEX'],
            ['23', 'd速快递', 'dsukuaidi', 'dsukuaidi', 'DSWL'],
            ['24', '递四方', 'disifang', 'disifang', 'D4PX'],
            ['25', 'fedex（国外）', 'fedex', 'FEDEX_GJ', 'FEDEX_GJ'],
            ['26', '飞康达物流', 'feikangda', 'FKD', 'FKD'],
            ['27', '凤凰快递', 'fenghuangkuaidi', 'fenghuangkuaidi', 'fenghuangkuaidi'],
            ['28', '飞快达', 'feikuaida', 'feikuaida', 'feikuaida'],
            ['29', '国通快递', 'guotongkuaidi', 'GTO', 'GTO'],
            ['30', '港中能达物流', 'ganzhongnengda', 'ganzhongnengda', 'ganzhongnengda'],
            ['31', '广东邮政物流', 'guangdongyouzhengwuliu', 'GDEMS', 'GDEMS'],
            ['32', '共速达', 'gongsuda', 'GSD', 'GSD'],
            ['33', '恒路物流', 'hengluwuliu', 'HLWL', 'HLWL'],
            ['34', '华夏龙物流', 'huaxialongwuliu', 'HXLWL', 'HXLWL'],
            ['35', '海红', 'haihongwangsong', 'haihongwangsong', 'haihongwangsong'],
            ['36', '海外环球', 'haiwaihuanqiu', 'haiwaihuanqiu', 'haiwaihuanqiu'],
            ['37', '佳怡物流', 'jiayiwuliu', 'JYWL', 'JYWL'],
            ['38', '京广速递', 'jinguangsudikuaijian', 'JGSD', 'JGSD'],
            ['39', '急先达', 'jixianda', 'JXD', 'JXD'],
            ['40', '佳吉物流', 'jiajiwuliu', 'jiajiwuliu', 'JJKY'],
            ['41', '加运美物流', 'jymwl', 'JYM', 'JYM'],
            ['42', '金大物流', 'jindawuliu', 'jindawuliu', 'jindawuliu'],
            ['43', '嘉里大通', 'jialidatong', 'JLDT', 'jialidatong'],
            ['44', '晋越快递', 'jykd', 'JYKD', 'JYKD'],
            ['45', '快捷速递', 'kuaijiesudi', 'FAST', 'FAST'],
            ['46', '联邦快递（国内）', 'lianb', 'FEDEX', 'FEDEX'],
            ['47', '联昊通物流', 'lianhaowuliu', 'lianhaowuliu', 'LHT'],
            ['48', '龙邦物流', 'longbanwuliu', 'longbanwuliu', 'LB'],
            ['49', '立即送', 'lijisong', 'LJSKD', 'lijisong'],
            ['50', '乐捷递', 'lejiedi', 'lejiedi', 'lejiedi'],
            ['51', '民航快递', 'minghangkuaidi', 'MHKD', 'MHKD'],
            ['52', '美国快递', 'meiguokuaidi', 'meiguokuaidi', 'meiguokuaidi'],
            ['53', '门对门', 'menduimen', 'MDM', 'menduimen'],
            ['54', 'OCS', 'ocs', 'OCS', 'ocs'],
            ['55', '配思货运', 'peisihuoyunkuaidi', 'peisihuoyunkuaidi', 'peisihuoyunkuaidi'],
            ['56', '全晨快递', 'quanchenkuaidi', 'QCKD', 'QCKD'],
            ['57', '全际通物流', 'quanjitong', 'quanjitong', 'quanjitong'],
            ['58', '全日通快递', 'quanritongkuaidi', 'QRT', 'QRT'],
            ['59', '全一快递', 'quanyikuaidi', 'UAPEX', 'UAPEX'],
            ['60', '如风达', 'rufengda', 'RFD', 'RFD'],
            ['61', '三态速递', 'santaisudi', 'santaisudi', 'santaisudi'],
            ['62', '盛辉物流', 'shenghuiwuliu', 'shenghuiwuliu', 'SHWL'],
            ['63', '速尔物流', 'sue', 'SURE', 'SURE'],
            ['64', '盛丰物流', 'shengfeng', 'SFWL', 'SFWL'],
            ['65', '赛澳递', 'saiaodi', 'SAD', 'SAD'],
            ['66', '天地华宇', 'tiandihuayu', 'HOAU', 'HOAU'],
            ['67', 'tnt', 'tnt', 'TNT', 'TNT'],
            ['68', 'ups', 'ups', 'UPS', 'UPS'],
            ['69', '万家物流', 'wanjiawuliu', 'WJWL', 'WJWL'],
            ['70', '文捷航空速递', 'wenjiesudi', 'wenjiesudi', 'wenjiesudi'],
            ['71', '伍圆', 'wuyuan', 'wuyuan', 'wuyuan'],
            ['72', '万象物流', 'wxwl', 'wxwl', 'WXWL'],
            ['73', '新邦物流', 'xinbangwuliu', 'xinbangwuliu', 'XBWL'],
            ['74', '信丰物流', 'xinfengwuliu', 'XFEX', 'XFEX'],
            ['75', '亚风速递', 'yafengsudi', 'YFSD', 'YFSD'],
            ['76', '一邦速递', 'yibangwuliu', 'yibangwuliu', 'yibangwuliu'],
            ['77', '优速物流', 'youshuwuliu', 'UC', 'UC'],
            ['78', '邮政快递包裹', 'youzhengguonei', 'YZBK', 'YZPY'],
            ['79', '邮政国际包裹挂号信', 'youzhengguoji', 'youzhengguoji', 'youzhengguoji'],
            ['80', '远成物流', 'yuanchengwuliu', 'YCWL', 'YCWL'],
            ['81', '源伟丰快递', 'yuanweifeng', 'yuanweifeng', 'yuanweifeng'],
            ['82', '元智捷诚快递', 'yuanzhijiecheng', 'yuanzhijiecheng', 'yuanzhijiecheng'],
            ['83', '运通快递', 'yuntongkuaidi', 'YTKD', 'CTG'],
            ['84', '越丰物流', 'yuefengwuliu', 'YFEX', 'YFEX'],
            ['85', '源安达', 'yad', 'YADEX', 'YADEX'],
            ['86', '银捷速递', 'yinjiesudi', 'yinjiesudi', 'yinjiesudi'],
            ['87', '中铁快运', 'zhongtiekuaiyun', 'ZTKY', 'ZTKY'],
            ['88', '中邮物流', 'zhongyouwuliu', 'ZYKD', 'ZYWL'],
            ['89', '忠信达', 'zhongxinda', 'zhongxinda', 'zhongxinda'],
            ['90', '芝麻开门', 'zhimakaimen', 'zhimakaimen', 'ZMKMEX'],
            ['91', '安能物流', 'annengwuliu', 'ANE', 'ANE'],
            ['92', '京东快递', 'jd', 'JD', 'jd'],
            ['93', '众邮快递', 'zhongyouex', 'ZYE', 'ZYKD'],
            ['94', '极兔速递', 'jtexpress', 'JTSD', 'JITU'],
            ['95', '中通快运', 'zhongtongkuaiyun', 'ZTOKY', 'ZTO56'],
        ];

        $result = [];
        foreach ($raw as $row) {
            $result[] = [
                'id'      => $row[0],
                'name'    => $row[1],
                'code'    => $row[2],
                'key'     => $row[3],
                'ali_key' => $row[4],
                'r_datas' => $defRdatas,
            ];
        }
        return $result;
    }
}
