<template>
  <el-drawer :title="levelId ? '编辑会员等级' : '添加会员等级'" :visible.sync="drawerVisible" size="70%" :before-close="handleClose"
    :destroy-on-close="true" custom-class="level-edit-drawer">
    <div class="drawer-content" v-loading="is_loading">
      <el-form size="mini" ref="form" :model="form" label-width="170px" class="custom-form">
        <el-form-item label="等级名称">
          <el-input v-model="form.name" placeholder="请输入等级名称"></el-input>
        </el-form-item>

        <el-form-item label="是否具有推广权限">
          <el-radio-group v-model="form.is_inviter">
            <el-radio :label="0">否</el-radio>
            <el-radio :label="1">是</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="启用等级折扣">
          <el-radio-group v-model="form.is_discount">
            <el-radio :label="0">不启用</el-radio>
            <el-radio :label="1">启用</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="享受折扣" v-if="form.is_discount == 1">
          <el-input v-model="form.discount" placeholder="如：9.5 (代表95折)"></el-input>
        </el-form-item>

        <el-form-item label="选择等级">
          <el-select v-model="form.level" placeholder="请选择等级" style="width: 100%;">
            <el-option v-if="list.length" v-for="(level, i) in list" :key="i" :label="level.name"
              :value="level.level"></el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="等级图标">
          <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center" v-if="form.pic_url">
            <img :src="form.pic_url" alt="" style="width: 100%;height: 100%;">
            <span class="flex-x-center flex-y-center pic-del" @click="deleteIcon">
              <i class="el-icon-close"></i>
            </span>
          </div>
          <file-picker v-if="!form.pic_url" style="margin-right: 5px;" v-model="form.pic_url" width="88" height="88">
            <div flex="main:center cross:center" class="add-image-btn flex-x-center flex-y-center">
              <i class="el-icon-upload"></i>
            </div>
          </file-picker>
        </el-form-item>


        <el-form-item label="说明">
          <el-input type="textarea" v-model="form.detail" :rows="3"></el-input>
        </el-form-item>

        <el-form-item label="是否启用">
          <el-radio-group v-model="form.enabled">
            <el-radio :label="0">不启用</el-radio>
            <el-radio :label="1">启用</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="开启自动升级">
          <el-radio-group v-model="form.is_auto">
            <el-radio :label="0">不启用</el-radio>
            <el-radio :label="1">启用</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-alert v-if="form.is_auto == 1" type="info" :closable="false" show-icon style="margin-bottom: 14px;">
          <div slot="title" style="font-weight: 600;">升级逻辑说明</div>
          <div style="font-size: 12px; line-height: 1.9; color: #606266;">
            · <b>触发时机</b>：用户登录、下单支付后，以及下级消费 / 下级等级变化后（自动重检上级），满足条件即<b>自动升级</b>。<br/>
            · <b>越级直达</b>：系统在所有更高等级中<b>从高到低</b>匹配，满足哪个就直接升到哪个（可跳级）。<br/>
            · <b>判定口径</b>：消费额 / 团队业绩等门槛一律按<b>终身累计</b>计算。<br/>
            · <b>团队业绩</b>：指该用户<b>整个团队（含本人）</b>的累计消费金额。<br/>
            · <b>直推有效人数</b>：指其直推下级中<b>已下单（有有效订单）</b>的人数。<br/>
            · <b>退款剔除</b>：消费 / 商品 / 业绩统计均<b>自动扣除已退款（无效）订单</b>，不计入升级。<br/>
            · <b>只升不降</b>：条件不再满足也<b>不会自动降级</b>（退款不降级）。<br/>
            · <b>例外</b>：被设为「禁止自动升级」的用户不参与自动升级，仅可后台手动调级。
          </div>
        </el-alert>

        <el-form-item label="条件关系" v-if="form.is_auto == 1">
          <el-radio-group v-model="form.upgrade_logic">
            <el-radio :label="0">全部满足（且）</el-radio>
            <el-radio :label="1">满足任一（或）</el-radio>
          </el-radio-group>
          <div style="font-size: 12px; color: #909399; line-height: 1.6; margin-top: 4px;">
            勾选多个条件时：「全部满足」需<b>同时</b>达标才升级；「满足任一」满足<b>其中一个</b>即可升级。
          </div>
        </el-form-item>

        <el-form-item label="选择升级条件" v-if="form.is_auto == 1">
          <div style="display: flex;flex-wrap: wrap;justify-content: start;">
            <div class="flex-x-center flex-y-center condition-item"
              :class="item.selected == 1 ? 'condition-item-active' : ''" @click="clickItem(item)"
              v-for="(item, index) in condition_list" :key="index">
              {{ item.name }}
            </div>
          </div>

          <div style="margin-top: 15px;background-color: #F8F9FA;padding: 15px;border-radius: 4px;">
            <el-form-item style="margin-bottom: 10px;" :label="item.name" v-if="item.selected == 1"
              v-for="(item, idx) in condition_list" :key="idx">

              <el-input v-model="item.value" v-if="item.type == 0 || item.type == 4 || item.type == 5 || item.type == 11" type="number"
                min="0">
                <template slot="append"><span>{{ item.unit }}</span></template>
              </el-input>

              <div v-if="item.type == 1">
                <el-table size="mini" :data="item.value" style="width: 100%" border>
                  <el-table-column label="商品">
                    <template slot-scope="scope">
                      <div style="display:flex; align-items:center;">
                        <el-image :src="scope.row.cover_pic"
                          style="width: 40px;height: 40px;flex-shrink:0;border-radius:4px;" fit="cover">
                          <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                        </el-image>
                        <div style="padding-left: 10px; line-height:1.2;">{{ scope.row.name }}</div>
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column label="价格" width="100" align="center">
                    <template slot-scope="scope">
                      <span style="font-weight: bold;color: #F56C6C;">￥{{ scope.row.price }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="操作" width="80" align="center">
                    <template slot-scope="scope">
                      <el-button size="mini" type="text" class="text-danger"
                        @click="goodsDelete(scope.$index)">删除</el-button>
                    </template>
                  </el-table-column>
                </el-table>
                <div style="width: 100%;text-align: center;margin-top: 10px;">
                  <goods-picker type="all" @selected="selectedGoods" style="margin-right: 5px;">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">添加商品 ({{ item.value.length
                      }}/50)</el-button>
                  </goods-picker>
                </div>
              </div>

              <div style="display:flex; align-items:center; gap:10px;" v-if="item.type == 2">
                <el-select v-model="item.value" placeholder="选择需要已达到的等级(及以上)">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
                <el-input v-model="item.value1" placeholder="填写要求购买的商品ID">
                  <template slot="prepend"><span>且购买商品ID</span></template>
                </el-input>
              </div>

              <!-- 新增：直推指定等级及以上满N人 -->
              <div style="display:flex; align-items:center; gap:10px; margin-top:5px;" v-if="item.type == 6">
                <el-select v-model="item.value" placeholder="选择要求的直推下级等级">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
                <el-input v-model="item.value1" type="number" min="1" placeholder="填写达标人数">
                  <template slot="prepend"><span>人数满</span></template>
                  <template slot="append"><span>人</span></template>
                </el-input>
              </div>

              <!-- 新增：自身等级 + 购买特定商品ID及数量 -->
              <div style="display:flex; align-items:center; gap:10px; margin-top:5px;" v-if="item.type == 7">
                <el-select v-model="item.value" placeholder="选择要求的自身等级(及以上)">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
                <el-input v-model="item.value1" placeholder="填写要求购买的商品ID">
                  <template slot="prepend"><span>购买商品ID</span></template>
                </el-input>
                <el-input v-model="item.value2" type="number" min="1" placeholder="数量要求">
                  <template slot="prepend"><span>需购买数量</span></template>
                  <template slot="append"><span>件</span></template>
                </el-input>
              </div>

              <!-- 新增：自身等级 + 团队包含某等级及以上的人数 -->
              <div style="display:flex; align-items:center; gap:10px; margin-top:5px;" v-if="item.type == 8">
                <el-select v-model="item.value" placeholder="选择要求的自身等级(及以上)">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
                <el-select v-model="item.value1" placeholder="选择网体囊括等级(及以上权重)">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
                <el-input v-model="item.value2" type="number" min="1" placeholder="填写达标人数">
                  <template slot="prepend"><span>总人数满</span></template>
                  <template slot="append"><span>人</span></template>
                </el-input>
              </div>

              <!-- 新增：团队总消费金额 -->
              <div style="display:flex; align-items:center; gap:10px; margin-top:5px;" v-if="item.type == 9">
                <el-input v-model="item.value" type="number" min="0" placeholder="填写团队总业绩要求">
                  <template slot="prepend"><span>团队总消费金额(业绩)满</span></template>
                  <template slot="append"><span>元</span></template>
                </el-input>
              </div>

              <!-- 新增：包含N条独立且合格的下线(解决大象腿) -->
              <div style="display:flex; align-items:center; gap:10px; margin-top:5px;" v-if="item.type == 10">
                <el-input v-model="item.value" type="number" min="1" placeholder="填写要求独立并行的线数量">
                  <template slot="prepend"><span>包含</span></template>
                  <template slot="append"><span>条独立代理线</span></template>
                </el-input>
                <el-select v-model="item.value1" placeholder="每条线至少包含该等级及以上">
                  <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                </el-select>
              </div>

              <!-- 新增：累计购买指定商品(多选)满N件 -->
              <div class="cond-sentence" v-if="item.type == 12">
                <div class="cs-line">
                  <span class="cs-text">本人累计购买下面这些商品,合计满</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">数量口径:已支付、自动剔除退款。</div>
              </div>

              <!-- 新增：直推指定等级及以上满n人 + 团队累购指定商品满m件 -->
              <div class="cond-sentence" v-if="item.type == 13">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">并且<b>本人</b>累计购买下面「自购商品」,合计满</span>
                  <el-input class="cs-num" v-model="item.value5" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">3</span>
                  <span class="cs-text">并且<b>团队成员(不含本人)</b>累计购买下面「团队商品」,合计满</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value2" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value2', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value2', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">直推那步:等级<b>留空=任意等级(含普通)</b>、人数<b>填 0=不限</b>(可整步不设直推要求);第 2 步「自购」(算本人)与第 3 步「团队」(只算下级、不含本人)两组商品<b>至少填一组</b>。</div>
              </div>

              <!-- 新增：两级直推 + 团队累购 -->
              <div class="cond-sentence" v-if="item.type == 14">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">这些直推里,至少有</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个达到</span>
                  <el-select class="cs-sel" v-model="item.value2" clearable placeholder="任意等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">3</span>
                  <span class="cs-text">并且<b>本人</b>累计购买下面「自购商品」,合计满</span>
                  <el-input class="cs-num" v-model="item.value7" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value6" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value6', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value6', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">4</span>
                  <span class="cs-text">并且<b>团队成员(不含本人)</b>累计购买下面「团队商品」,合计满</span>
                  <el-input class="cs-num" v-model="item.value5" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">直推两步:等级<b>留空=任意(含普通)</b>、人数<b>填 0=不限</b>;第 3 步「自购」(算本人)与第 4 步「团队」(只算下级、不含本人)两组商品<b>至少填一组</b>。</div>
              </div>

              <!-- 新增：直推某等级 + 自购A与团队B「合计」满N件(与条件14区别:两组货量合并计) -->
              <div class="cond-sentence" v-if="item.type == 15">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">并且<b>本人自购「商品A」</b>加上<b>团队成员(不含本人)购买「商品B」</b>,<b>合计</b>满</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购商品 A(算本人):</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队商品 B(算下级、不含本人):</span>
                  <el-tag v-for="(g, gi) in item.value2" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value2', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value2', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">自购A与团队B的件数<b>合并计算</b>(A+B 合计满 N,与条件14的各自达标不同)。直推那步等级<b>留空=任意</b>、人数<b>填 0=不限</b>;两组商品至少填一组。</div>
              </div>

              <!-- 新增：两级直推 + 自购A与团队B「合计」满N件(与条件15区别:两组货量合并计) -->
              <div class="cond-sentence" v-if="item.type == 16">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">这些直推里,至少有</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个达到</span>
                  <el-select class="cs-sel" v-model="item.value2" clearable placeholder="任意等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">3</span>
                  <span class="cs-text">并且<b>本人自购「商品A」</b>加上<b>团队成员(不含本人)购买「商品B」</b>,<b>合计</b>满</span>
                  <el-input class="cs-num" v-model="item.value5" type="number" min="1" placeholder="数量" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购商品 A(算本人):</span>
                  <el-tag v-for="(g, gi) in item.value6" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value6', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value6', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队商品 B(算下级、不含本人):</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">自购A与团队B的件数<b>合并计算</b>(A+B 合计满 N)。直推两步等级<b>留空=任意</b>、人数<b>填 0=不限</b>;两组商品至少填一组。</div>
              </div>

              <!-- 新增：一次性购买满 N 件(单笔订单内达标,区别于条件7/13的「累计」) -->
              <div class="cond-sentence" v-if="item.type == 17">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text"><b>同一笔订单</b>内购买满</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="1" placeholder="件数" />
                  <span class="cs-text">件</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">限定商品(留空=不限,按整单件数算):</span>
                  <el-tag v-for="(g, gi) in item.value" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">
                  「<b>一次性</b>购买 N 件」——要求<b>单笔订单</b>内买够,分多单累计买够<b>不算</b>达标(这是与条件13「累计购买」的唯一区别)。
                  典型用法:一次性进货 5 件升店主、20 件升合伙人。退款/取消的订单不计。
                </div>
              </div>

                <div class="cond-sentence" v-if="item.type == 18">
                  <div class="cs-line">
                    <span class="cs-badge">1</span>
                    <span class="cs-text">·<b>直推下级</b>购买下面「直推业绩商品」，累计满</span>
                    <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="金额" />
                    <span class="cs-text">元</span>
                  </div>
                  <div class="cs-goods">
                    <span class="cs-label">直推业绩商品:</span>
                    <el-tag v-for="(g, gi) in item.value" :key="gi" size="small" closable
                      @close="goodsDeleteFor(item, 'value', gi)">{{ g.name }}</el-tag>
                    <goods-picker type="all" @selected="selectedGoodsFor(item, 'value', $event)">
                      <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                    </goods-picker>
                  </div>
                  <div class="cs-line">
                    <span class="cs-badge">2</span>
                    <span class="cs-text">并且<b>本人</b>购买下面「自购业绩商品」，累计满</span>
                    <el-input class="cs-num" v-model="item.value3" type="number" min="0" placeholder="金额" />
                    <span class="cs-text">元</span>
                  </div>
                  <div class="cs-goods">
                    <span class="cs-label">自购业绩商品:</span>
                    <el-tag v-for="(g, gi) in item.value2" :key="gi" size="small" closable
                      @close="goodsDeleteFor(item, 'value2', gi)">{{ g.name }}</el-tag>
                    <goods-picker type="all" @selected="selectedGoodsFor(item, 'value2', $event)">
                      <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                    </goods-picker>
                  </div>
                  <div class="cs-line">
                    <span class="cs-badge">3</span>
                    <span class="cs-text">并且<b>团队成员(不含本人)</b>购买下面「团队业绩商品」，累计满</span>
                    <el-input class="cs-num" v-model="item.value5" type="number" min="0" placeholder="金额" />
                    <span class="cs-text">元</span>
                  </div>
                  <div class="cs-goods">
                    <span class="cs-label">团队业绩商品:</span>
                    <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                      @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                    <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                      <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                    </goods-picker>
                  </div>
                  <div class="cs-goods">
                    <span class="cs-label" style="color:#E6A23C;">自购排除商品:</span>
                    <el-tag v-for="(g, gi) in item.value6" :key="gi" size="small" closable type="warning"
                      @close="goodsDeleteFor(item, 'value6', gi)">{{ g.name }}</el-tag>
                    <goods-picker type="all" @selected="selectedGoodsFor(item, 'value6', $event)">
                      <el-button plain size="mini" icon="el-icon-plus">选商品</el-button>
                    </goods-picker>
                  </div>
                  <div class="cond-tip">
                    三条业绩线<b>各自独立</b>达标（全部满足才算通过），不是把金额加在一起。
                    某条线的商品留空 = 不校验该条线。
                    「自购排除商品」只影响第 2 条线：清单里的商品本人买了不计入自购业绩；
                    下级买同款仍照常计入第 1、3 条线。金额口径为用户实付。
                  </div>
                </div>

              <!-- 条件20:条件13 的金额版 —— 只把「件」换成「元」 -->
              <div class="cond-sentence" v-if="item.type == 19">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text"><b>自身等级</b>已达到</span>
                  <el-select class="cs-sel" v-model="item.value2" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">本人累计购买下面这些商品,合计满</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="金额" />
                  <span class="cs-text">元</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">考核商品:</span>
                  <el-tag v-for="(g, gi) in item.value" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">第 1 步<b>自身等级留空 = 不设这道门</b>。与条件13 的区别是<b>按金额算而不是按件数</b>、且多一道自身等级门。金额取<b>用户实付</b>,已支付口径、自动剔除退款。</div>
              </div>

              <!-- 条件21:条件16 的金额版 -->
              <div class="cond-sentence" v-if="item.type == 20">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text"><b>自身等级</b>已达到</span>
                  <el-select class="cs-sel" v-model="item.value5" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">3</span>
                  <span class="cs-text">并且<b>本人自购「商品A」</b>加上<b>团队成员(不含本人)购买「商品B」</b>,<b>合计</b>满</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="0" placeholder="金额" />
                  <span class="cs-text">元</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购商品 A(算本人):</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队商品 B(算下级、不含本人):</span>
                  <el-tag v-for="(g, gi) in item.value2" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value2', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value2', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">第 1 步<b>自身等级留空 = 不设这道门</b>。与条件16 的区别是<b>按金额算而不是按件数</b>、且多一道自身等级门:自购A与团队B的<b>金额合并计算</b>(A+B 合计满 N 元)。直推那步等级<b>留空=任意</b>、人数<b>填 0=不限</b>;两组商品至少填一组。金额取用户实付。</div>
              </div>

              <!-- 条件22:条件17 的金额版 -->
              <div class="cond-sentence" v-if="item.type == 21">
                <div class="cs-line">
                  <span class="cs-badge">1</span>
                  <span class="cs-text"><b>自身等级</b>已达到</span>
                  <el-select class="cs-sel" v-model="item.value7" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">2</span>
                  <span class="cs-text">直推</span>
                  <el-input class="cs-num" v-model="item.value1" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个</span>
                  <el-select class="cs-sel" v-model="item.value" clearable placeholder="不限等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上的会员</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">3</span>
                  <span class="cs-text">这些直推里,至少有</span>
                  <el-input class="cs-num" v-model="item.value3" type="number" min="0" placeholder="人数,0=不限" />
                  <span class="cs-text">个达到</span>
                  <el-select class="cs-sel" v-model="item.value2" clearable placeholder="任意等级">
                    <el-option v-for="l in level_list" :key="l.id" :label="l.name" :value="l.id"></el-option>
                  </el-select>
                  <span class="cs-text">及以上</span>
                </div>
                <div class="cs-line">
                  <span class="cs-badge">4</span>
                  <span class="cs-text">并且<b>本人自购「商品A」</b>加上<b>团队成员(不含本人)购买「商品B」</b>,<b>合计</b>满</span>
                  <el-input class="cs-num" v-model="item.value5" type="number" min="0" placeholder="金额" />
                  <span class="cs-text">元</span>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">自购商品 A(算本人):</span>
                  <el-tag v-for="(g, gi) in item.value6" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value6', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value6', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cs-goods">
                  <span class="cs-label">团队商品 B(算下级、不含本人):</span>
                  <el-tag v-for="(g, gi) in item.value4" :key="gi" size="small" closable
                    @close="goodsDeleteFor(item, 'value4', gi)">{{ g.name }}</el-tag>
                  <goods-picker type="all" @selected="selectedGoodsFor(item, 'value4', $event)">
                    <el-button type="primary" plain size="mini" icon="el-icon-plus">选商品</el-button>
                  </goods-picker>
                </div>
                <div class="cond-tip">第 1 步<b>自身等级留空 = 不设这道门</b>。与条件17 的区别是<b>按金额算而不是按件数</b>、且多一道自身等级门:自购A与团队B的<b>金额合并计算</b>(A+B 合计满 N 元)。直推两步等级<b>留空=任意</b>、人数<b>填 0=不限</b>;两组商品至少填一组。金额取用户实付。</div>
              </div>

            </el-form-item>
          </div>
        </el-form-item>

        <el-form-item label="升级时机" v-if="form.is_auto == 1">
          <el-radio-group v-model="form.buy_type">
            <el-radio :label="0">付款后</el-radio>
            <el-radio :label="1">订单完成后</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
    </div>

    <div class="drawer-footer">
      <el-button size="small" @click="handleClose">取 消</el-button>
      <el-button size="small" type="primary" @click="save" :loading="is_loading">确 定</el-button>
    </div>
  </el-drawer>
</template>

<script>
// 获取干净的默认表单，防止关掉再打开时数据串台
const getDefaultForm = () => ({
  id: '',
  name: '',
  level: '',
  detail: '',
  pic_url: '',
  enabled: 0,
  is_auto: 0,
  upgrade_logic: 0,
  upgrade: '',
  discount: '',
  is_inviter: 0,
  buy_type: 0
});

// 获取干净的默认条件列表
const getDefaultConditions = () => [
  { name: '条件1(消费满)', type: 0, value: '', selected: 0, unit: '元' },
  { name: '条件2(购买指定商品)', type: 1, value: [], selected: 0, unit: '' },
  { name: '条件3(自身等级基础)', type: 2, value: '', value1: '', selected: 0, unit: '' },
  { name: '条件4(无条件)', type: 3, selected: 0 },
  { name: '条件5(团队总人数满)', type: 4, value: '', selected: 0, unit: '人' },
  { name: '条件6(直推人数满)', type: 5, value: '', selected: 0, unit: '人' },
  { name: '条件7(直推指定等级及以上满N人)', type: 6, value: '', value1: '', selected: 0, unit: '人' },
  { name: '条件8(自身等级+购买指定商品及数量)', type: 7, value: '', value1: '', value2: '', selected: 0, unit: '' },
  { name: '条件9(自身等级+团队某等级及以上人数)', type: 8, value: '', value1: '', value2: '', selected: 0, unit: '人' },
  { name: '条件10(团队总消费金额满)', type: 9, value: '', selected: 0, unit: '元' },
  { name: '条件11(多线并行考核-包含N条符合等级的独立线)', type: 10, value: '', value1: '', selected: 0, unit: '条' },
  { name: '条件12(个人直推业绩满)', type: 11, value: '', selected: 0, unit: '元' },
  { name: '条件13(累计购买指定商品满N件)', type: 12, value: [], value1: '', selected: 0, unit: '' },
  { name: '条件14(直推指定等级满n人+自购/团队累购)', type: 13, value: '', value1: '', value2: [], value3: '', value4: [], value5: '', selected: 0, unit: '' },
  { name: '条件15(两级直推+自购/团队累购)', type: 14, value: '', value1: '', value2: '', value3: '', value4: [], value5: '', value6: [], value7: '', selected: 0, unit: '' },
  { name: '条件16(直推指定等级+自购A与团队B合计满N件)', type: 15, value: '', value1: '', value2: [], value3: '', value4: [], selected: 0, unit: '' },
  { name: '条件17(两级直推+自购A与团队B合计满N件)', type: 16, value: '', value1: '', value2: '', value3: '', value4: [], value5: '', value6: [], selected: 0, unit: '' },
  { name: '条件18(一次性购买满N件)', type: 17, value: [], value1: '', selected: 0, unit: '' },
  { name: '条件19(直推业绩+自购业绩+团队业绩,三线各自达标)', type: 18, value: [], value1: '', value2: [], value3: '', value4: [], value5: '', value6: [], selected: 0, unit: '' },
  // 20/21/22 = 13/16/17 的「金额版」:字段位置逐一对应,只把阈值从件数换成金额
  { name: '条件20(自身等级+累计购买指定商品满N元)', type: 19, value: [], value1: '', value2: '', selected: 0, unit: '元' },
  { name: '条件21(自身等级+直推指定等级+自购A与团队B合计满N元)', type: 20, value: '', value1: '', value2: [], value3: '', value4: [], value5: '', selected: 0, unit: '元' },
  { name: '条件22(自身等级+两级直推+自购A与团队B合计满N元)', type: 21, value: '', value1: '', value2: '', value3: '', value4: [], value5: '', value6: [], value7: '', selected: 0, unit: '元' }
];

export default {
  name: 'LevelEditDrawer',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    // 如果有 id 传过来代表编辑，0 或空代表新建
    levelId: {
      type: [String, Number],
      default: 0
    }
  },
  computed: {
    drawerVisible: {
      get() {
        return this.visible;
      },
      set(val) {
        this.$emit('update:visible', val);
      }
    }
  },
  data() {
    return {
      is_loading: false,
      list: [], // 权重列表
      level_list: [], // 真实的数据库等级列表 (含ID)
      form: getDefaultForm(),
      condition_list: getDefaultConditions()
    }
  },
  watch: {
    visible(newVal) {
      if (newVal) {
        this.initData();
      } else {
        this.resetData();
      }
    }
  },
  methods: {
    initData() {
      this.getDefaultList();
      this.getRealLevelList();
      if (this.levelId && this.levelId != 0) {
        this.form.id = this.levelId;
        this.getLevel();
      }
    },
    resetData() {
      this.form = getDefaultForm();
      this.condition_list = getDefaultConditions();
      this.is_loading = false;
    },
    handleClose() {
      if (this.is_loading) return;
      this.drawerVisible = false;
    },
    goodsDelete(index) {
      this.condition_list[1].value.splice(index, 1);
    },
    selectedGoods(e) {
      // 重复选的商品只留一个(后端保存时也会去重)
      this.selectedGoodsFor(this.condition_list[1], 'value', e);
    },
    // 通用商品多选处理：给新增条件(type 12/13/14)的商品数组字段用，按 id 去重追加
    selectedGoodsFor(item, field, e) {
      if (!Array.isArray(item[field])) this.$set(item, field, []);
      const exist = new Set(item[field].map(g => g.id));
      for (const g of e) {
        if (!exist.has(g.id)) item[field].push(g);
      }
    },
    goodsDeleteFor(item, field, index) {
      item[field].splice(index, 1);
    },
    clickItem(row) {
      if (row.selected == 1) {
        row.selected = 0;
        return;
      }
      row.selected = 1;
    },
    getLevel() {
      this.is_loading = true;
      this.$request({
        url: '/mall/user/level-edit',
        data: { id: this.form.id },
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.form = Object.assign({}, this.form, res.data.level);
          // 防御:后端 tinyint 可能以字符串返回,转 number 保证单选框正确回显
          this.form.upgrade_logic = Number(res.data.level.upgrade_logic) || 0;
          if (res.data.level.upgrade) {
            // 安全合并后端返回的配置到现有的 condition_list 结构体系中，防旧数据覆盖新字段
            let savedConditions = res.data.level.upgrade;
            // 获取最新鲜的空白模板
            let templateConditions = getDefaultConditions();

            // 遍历保存下来的旧配置，把它们覆盖到鲜热的模板对应的选项里
            templateConditions.forEach(tc => {
              let match = savedConditions.find(sc => sc.type === tc.type);
              if (match) {
                Object.assign(tc, match);
              }
            });
            this.condition_list = templateConditions;
          }
        }
      }).catch(() => {
        this.is_loading = false;
      })
    },
    save() {
      if (this.form.is_discount == 1) {
        if (this.form.discount > 10 || this.form.discount < 0) {
          this.$message.warning('等级折扣不正确');
          return;
        }
      }
      this.is_loading = true;
      this.form.upgrade = JSON.stringify(this.condition_list);

      this.$request({
        url: '/mall/user/level-edit',
        data: this.form,
        method: 'post'
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.$message.success(res.msg);
          this.drawerVisible = false;
          // 通知列表页刷新数据
          this.$emit('success');
        } else {
          this.$message.error(res.msg);
        }
      }).catch(e => {
        this.is_loading = false;
      })
    },
    deleteIcon() {
      this.form.pic_url = '';
    },
    getDefaultList() {
      this.$request({
        url: '/mall/user/default-level'
      }).then(res => {
        if (res.code == 0) {
          this.list = res.data.list;
        }
      })
    },
    getRealLevelList() {
      this.$request({
        url: '/mall/user/level',
        data: {
          limit: 100 // 拿最多前100个自定义等级即可
        }
      }).then(res => {
        if (res.code == 0) {
          this.level_list = res.data.list;
        }
      })
    }
  }
}
</script>

<style lang="scss">
/* 覆盖抽屉的默认外框结构，实现上下固定，中间滚动 */
.level-edit-drawer {
  display: flex;
  flex-direction: column;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ebeef5;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
  }

  .el-drawer__body {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
}
</style>

<style scoped lang="scss">
/* 中间表单滚动区 */
.drawer-content {
  flex: 1;
  overflow-y: auto;
  padding: 20px 30px;
  background-color: #fff;
}

/* 底部操作区 */
.drawer-footer {
  flex-shrink: 0;
  padding: 12px 20px;
  border-top: 1px solid #ebeef5;
  background-color: #fff;
  display: flex;
  justify-content: flex-end;
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.02);
}

/* 针对原有一些样式的复用调整 */
.custom-form {
  padding-right: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 5px;
}

.text-danger {
  color: #F56C6C;
}

.add-image-btn {
  width: 88px;
  height: 88px;
  border: 1px dashed #d9d9d9;
  border-radius: 4px;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  background: #fafafa;

  &:hover {
    border-color: var(--admin-accent-text);
  }

  .el-icon-upload {
    font-size: 20px;
    color: #8c939d;
  }
}

.pic-del {
  position: absolute;
  top: 0;
  right: 0;
  width: 20px;
  height: 20px;
  background-color: rgba(0, 0, 0, 0.5);
  color: #fff;
  border-bottom-left-radius: 4px;
  cursor: pointer;
  z-index: 10;
}

.condition-item {
  width: auto;
  min-width: 130px;
  padding: 0 10px;
  height: 36px;
  line-height: 34px;
  text-align: center;
  border: solid #ebeef5 1px;
  border-radius: 4px;
  margin: 0 10px 10px 0;
  font-size: 12px;
  background: #fff;
  color: #606266;
  transition: all 0.2s;
  cursor: pointer;

  &:hover {
    border-color: var(--admin-accent-text);
    color: var(--admin-accent-text);
  }
}

.condition-item-active {
  border: solid var(--admin-accent-text) 1px;
  background-color: var(--admin-accent-soft);
  color: var(--admin-accent-text);
  font-weight: bold;
}

/* 新增条件:口语化「填空句」排版 */
.cond-sentence {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 14px 16px;
}

.cs-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 13px;
  color: #303133;
}

.cs-line:last-of-type {
  margin-bottom: 0;
}

.cs-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--admin-primary);
  color: var(--admin-on-primary);
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}

.cs-text {
  color: #606266;
}

.cs-num {
  width: 84px;
}

.cs-sel {
  width: 150px;
}

.cs-goods {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #ebeef5;
}

.cs-label {
  font-size: 13px;
  color: #909399;
  margin-right: 2px;
}

.cond-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
  margin-top: 10px;
}
</style>
