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

namespace app\forms\mall\express;

use app\models\BaseModel;
use app\models\Express;
use Yii;

/**
 * 同步系统快递公司数据(后台一键幂等补齐)。
 *
 * 背景:m260306_express_init 迁移把"建表 + 灌数据 + 插菜单"耦合在一个 safeUp,
 * MySQL 的 DDL 隐式提交破坏事务原子性,加上结尾 menu 的 max(id)+1 插入易冲突,
 * 某些站点会落得"表在、数据空";m260420_express_seed 虽是幂等补数据迁移,但若该站点
 * 尚未执行到它,依旧空。此表单把同样的补齐逻辑搬到后台,管理员一键即可恢复,
 * 不依赖谁去跑 migrate。
 *
 * 逻辑:以 code 为业务唯一键,只补 mall_id=0 全局标准集里缺失的记录;已存在(含已删)
 * 的不动、不复活,可反复执行零副作用。
 *
 * 注:标准数据**内联自包含**,不复用 ExpressHelper(backup) —— 那些 PHP 侧的快递数据
 * 属遗留、计划清理,本功能作为"把数据灌进库"的入口,自带种子模板更稳妥。
 */
class ExpressSyncForm extends BaseModel
{
    /** 默认面单样式 */
    private const DEFAULT_RDATAS = '[{"style":"默认风格","spec":"默认风格","size":"150","isdefault":1}]';

    public function sync()
    {
        // 全局标准集(mall_id=0)已存在的 code;含已删记录,避免重复插入、也不复活管理员手动删掉的
        $existCodes = Express::find()
            ->select('code')
            ->where(['mall_id' => 0])
            ->andWhere(['!=', 'code', ''])
            ->column();
        $existCodes = array_flip($existCodes);

        $now = time();
        $rows = [];
        foreach ($this->standardList() as $row) {
            [$id, $name, $code, $key, $aliKey] = $row;
            // 跳过:空 code、库里已有、以及源数据内部的同 code 别名
            if ($code === '' || isset($existCodes[$code])) {
                continue;
            }
            $existCodes[$code] = true;
            $rows[] = [
                0,                      // mall_id 全局可用
                $name,
                $code,
                $key,
                $aliKey,
                self::DEFAULT_RDATAS,
                (int)$id,               // 旧 id 当默认排序,保持原顺序
                0,                      // is_delete
                $now,                   // created_at
                $now,                   // updated_at
                0,                      // deleted_at
            ];
        }

        $added = 0;
        if (!empty($rows)) {
            $added = Yii::$app->db->createCommand()->batchInsert(
                Express::tableName(),
                ['mall_id', 'name', 'code', 'key', 'ali_key', 'r_datas', 'sort', 'is_delete', 'created_at', 'updated_at', 'deleted_at'],
                $rows
            )->execute();
        }

        $total = (int)Express::find()->where(['is_delete' => 0])->count();

        return [
            'code' => 0,
            'msg' => $added > 0 ? "已同步 {$added} 条系统快递公司数据" : '系统快递公司数据已是最新,无需同步',
            'data' => ['added' => $added, 'total' => $total],
        ];
    }

    /**
     * 系统标准快递公司清单(自包含)。
     * 每行: [id(原序), 名称, 快递鸟编码 code, 核心 key, 阿里菜鸟 ali_key]
     */
    private function standardList(): array
    {
        return [
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
    }
}
