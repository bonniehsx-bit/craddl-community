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
use app\helpers\OptionHelper;

/**
 * Class m260914_100000_seed_option_cache_registry
 *
 * 登记现有配置缓存键,让「刷新缓存」上线后马上就能清到数据库里已删掉的配置的旧缓存。
 *
 * 【背景】「刷新缓存」只清配置(option)缓存,不整库清空 —— 同一个 Redis 库里还有定时任务配置、
 * 延时任务队列等。原来它只按数据库现有的行逐条重写,数据库里已经删掉的配置(手动删行、导入备份),
 * 旧缓存会一直留着,读配置时仍读到旧值。现在 OptionHelper 写缓存时会登记键名,刷新时按登记表
 * 把这些旧缓存一并删掉(见 OptionHelper::CACHE_KEY_REGISTRY)。
 *
 * 【为什么要这条】登记表是新加的,上线前就已经在 Redis 里的配置缓存都没登记过;而读配置命中缓存时
 * 不登记(每次读都多一次 Redis 调用不划算)。不先登记一遍的话,要等有人手动点过一次「刷新缓存」,
 * 之后删掉的配置才清得到。
 *
 * 【只登记,不重写缓存】发布时自动执行,不能照搬后台「刷新缓存」按钮:
 *   · 按钮是先读全表、再逐条写回,中间有人保存配置,会被读表时的旧值盖掉,而且缓存不过期;
 *   · 写缓存出错会抛异常,迁移失败会取消核心迁移目录里排在它后面的迁移。
 * 所以这里只把键名登记进去,不碰缓存值;登记失败只记日志、不抛出,不影响发布。
 * 不改表结构、不动数据库数据,可重复执行。
 */
class m260914_100000_seed_option_cache_registry extends Migration
{
    public function safeUp()
    {
        $result = OptionHelper::registerExistingCacheKeys();
        if ($result['registered'] === $result['total']) {
            echo "    > 已登记 {$result['total']} 个配置缓存键\n";
        } else {
            echo "    > 配置缓存键登记失败(详见日志),不影响本次发布;之后在后台点一次「刷新缓存」会补登记\n";
        }
    }

    public function safeDown()
    {
        // 登记表只是清缓存用的账本,回滚代码后留在 Redis 里也不影响读配置,无需处理
        return true;
    }
}
