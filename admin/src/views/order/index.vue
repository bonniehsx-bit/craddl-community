<template>
  <div class="app-container" :class="{ 'is-component': isComponent }">

    <!-- 顶部统计:一行排开。六项优惠合成「优惠合计」一格,鼠标移上去看各项明细
         (原来 10 张小卡要折成两行,占掉一百三十多像素) -->
    <div class="stats-bar">
      <div class="sb-item">
        <span class="s-label">成交金额</span>
        <span class="s-val text-blue">¥{{ stats.total_deal_amount }}</span>
      </div>
      <div class="sb-item">
        <span class="s-label">实付总额</span>
        <span class="s-val text-main">¥{{ stats.total_pay_price }}</span>
      </div>
      <div class="sb-item">
        <span class="s-label">商品总额</span>
        <span class="s-val text-sub">¥{{ stats.total_goods_price }}</span>
      </div>
      <div class="sb-item">
        <span class="s-label">运费</span>
        <span class="s-val text-sub">¥{{ stats.total_express_price }}</span>
      </div>
      <el-tooltip effect="light" placement="bottom-start" popper-class="order-discount-tip" :open-delay="100">
        <div slot="content">
          <div v-for="d in discountItems" :key="d.key" class="dt-row" :class="{ 'is-zero': !d.cents }">
            <span>{{ d.label }}</span><b>-¥{{ stats[d.key] }}</b>
          </div>
        </div>
        <div class="sb-item is-hoverable">
          <span class="s-label">优惠合计<i class="el-icon-arrow-down"></i></span>
          <span class="s-val text-orange">-¥{{ discountTotal }}</span>
        </div>
      </el-tooltip>
    </div>

    <el-card class="box-card table-card" shadow="never">

      <div class="filter-wrapper">
        <el-form size="mini" :inline="true" :model="searchParams" class="search-form" label-width="0">
          <div class="filter-row" style="flex-wrap: nowrap; overflow-x: auto;">
            <el-form-item style="margin-right: 5px;">
              <div class="custom-input-group" style="display: flex; align-items: center;">
                <el-select v-model="searchParams.keyword_field" class="group-select"
                  style="width: 100px; margin-right: -1px; z-index: 2;">
                  <el-option v-for="f in keywordFields" :key="f.value" :label="f.label" :value="f.value"></el-option>
                </el-select>
                <el-input v-model="searchParams.keyword" :placeholder="keywordPlaceholder" class="group-input"
                  style="width: 160px;" clearable @keyup.enter.native="search" @clear="search"></el-input>
              </div>
            </el-form-item>

            <el-form-item style="margin-right: 5px;">
              <el-select v-model="searchParams.deliver_type" placeholder="配送" clearable style="width: 90px;"
                @change="search">
                <el-option label="快递" value="express"></el-option>
                <el-option label="自提" value="pickup"></el-option>
                <el-option label="同城" value="city"></el-option>
              </el-select>
            </el-form-item>
            <el-form-item style="margin-right: 5px;">
              <el-select v-model="searchParams.payment" placeholder="支付" clearable style="width: 90px;"
                @change="search">
                <el-option v-for="item in payment_list" :key="item.value" :label="item.label" :value="item.value"></el-option>
              </el-select>
            </el-form-item>
            <el-form-item style="margin-right: 5px;">
              <el-select v-model="searchParams.mch_id" placeholder="商家" clearable filterable style="width: 110px;">
                <el-option label="自营" value="0"></el-option>
                <el-option v-for="item in mchList" :key="item.id" :label="item.mch_name" :value="item.id"></el-option>
              </el-select>
            </el-form-item>
            <el-form-item style="margin-right: 5px;">
              <el-select v-model="searchParams.plugin_type" placeholder="订单类型" clearable filterable style="width: 130px;"
                @change="search">
                <el-option v-for="t in orderTypeList" :key="t.key" :label="t.name" :value="t.key">
                  <span style="float:left">{{ t.name }}</span>
                  <span style="float:right;color:#8492a6;font-size:12px;margin-left:12px;">{{ t.plugin_label }}</span>
                </el-option>
              </el-select>
            </el-form-item>
            <el-form-item style="margin-right: 10px;">
              <div class="custom-input-group" style="display: flex; align-items: center;">
                <el-select v-model="searchParams.search_date_type" class="group-select"
                  style="width: 90px; margin-right: -1px; z-index: 2;">
                  <el-option label="下单" value="created_at"></el-option>
                  <el-option label="支付" value="pay_at"></el-option>
                  <el-option label="完成" value="finish_at"></el-option>
                </el-select>
                <el-date-picker v-model="searchParams.search_date" type="daterange" value-format="yyyy-MM-dd"
                  range-separator="-" start-placeholder="开始" end-placeholder="结束" class="group-picker"
                  style="width: 200px;" :picker-options="pickerOptions" @change="search" />
              </div>
            </el-form-item>

            <el-form-item class="action-btn-group" style="margin-right: 0; flex-shrink: 0;">
              <el-button type="primary" icon="el-icon-search" @click="search" circle></el-button>
              <el-button icon="el-icon-refresh" @click="resetSearch" circle plain></el-button>
              <el-button type="text" icon="el-icon-download" @click="openExportDialog" class="export-btn"
                style="margin-left: 5px;">
                {{ multipleSelection.length > 0 ? `(${multipleSelection.length})` : '' }}
              </el-button>
              <!-- 说明入口放这里而不是挂在「商家收入」那个统计格上:
                   挂在统计格里像是在解释那一个数字,而它讲的是整条订单流程;
                   工具栏才是"这一页怎么用"的归属地。 -->
              <el-tooltip content="订单生命周期 · 顶部统计怎么算" placement="top">
                <el-button icon="el-icon-info" circle plain class="help-btn"
                  @click="settleHelpVisible = true"></el-button>
              </el-tooltip>
            </el-form-item>
          </div>
        </el-form>
      </div>

      <el-tabs v-model="activeTabName" @tab-click="tabClick" class="custom-tabs">
        <el-tab-pane label="全部" name="-1"></el-tab-pane>
        <el-tab-pane label="待付款" name="10"></el-tab-pane>
        <el-tab-pane label="待发货" name="20"></el-tab-pane>
        <el-tab-pane label="待收货" name="30"></el-tab-pane>
        <el-tab-pane label="已完成" name="40"></el-tab-pane>
        <el-tab-pane label="已关闭" name="0"></el-tab-pane>
        <el-tab-pane label="售后" name="refund"></el-tab-pane>
      </el-tabs>

      <div class="table-flex-container">
        <el-table v-loading="loading" :data="list" border stripe height="100%" size="small" class="order-table"
          :row-key="getRowKey"
          :header-cell-style="{ background: '#F5F7FA', color: '#606266', fontWeight: '600', height: '40px', padding: '6px 0', fontSize: '13px' }"
          :cell-style="{ padding: '6px 0', fontSize: '13px' }" @selection-change="handleSelectionChange" @row-dblclick="handleRowDblClick">
          <el-table-column type="selection" width="40" align="center" :reserve-selection="true"></el-table-column>
          <el-table-column width="80" align="center" prop="id" label="ID">
            <template slot-scope="scope">
              <el-button type="text" size="mini" style="padding: 0;"
                @click="copyText(scope.row.id, '订单ID')" title="点击复制">{{ scope.row.id }}</el-button>
            </template>
          </el-table-column>

          <el-table-column label="订单信息" width="180">
            <template slot-scope="scope">
              <div class="info-wrapper">
                <div class="order-no-row">
                  <el-button type="text" size="mini" class="order-no" style="padding: 0; text-align: left;" @click="copyText(scope.row.order_no, '订单号')" title="点击复制">{{ scope.row.order_no }}</el-button>
                </div>
                <div class="meta-row">
                  <span class="shop-badge">{{ scope.row.mch ? scope.row.mch.mch_name : '自营' }}</span>
                  <span class="time">{{ formatTimeShort(scope.row.created_time) }}</span>
                  <el-tag v-if="scope.row.type === 'cashier'" size="mini" type="warning" effect="dark"
                    class="mini-tag">收银
                  </el-tag>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="商品明细" min-width="300">
            <template slot-scope="scope">
              <div class="goods-list-container custom-scrollbar">
                <div v-for="(item, index) in scope.row.orderDetails" :key="index" class="goods-item">
                  <el-image :src="item.goods.cover_pic" class="goods-thumb" fit="cover">
                    <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                  </el-image>
                  <div class="goods-detail">
                    <div class="g-name text-ellipsis" :title="item.goods.name">{{ item.goods.name }}</div>
                    <div class="g-spec text-ellipsis">
                      <el-button v-if="item.id" type="text" size="mini" class="g-did"
                        @click="copyText(item.id, '明细ID')"
                        title="明细ID(order_detail_id),点击复制">#{{ item.id }}</el-button>
                      <span v-for="(spec, si) in item.attr" :key="si" style="margin-right:4px;">{{ spec.value }}</span>
                    </div>
                  </div>
                  <div class="goods-price-num">
                    <span class="price">¥{{ Number(item.price) > 0 ? item.price : (Number(item.total_price) / Number(item.num)).toFixed(2) }}</span>
                    <!-- 数量只在 >1 时高亮:绝大多数订单就是买 1 件,给 1 也上色等于整列都在喊,
                         真正需要一眼看到的恰恰是"这单买了好几件"。× 用真乘号,x 是字母 -->
                    <span class="num" :class="{ 'num-multi': Number(item.num) > 1 }">×{{ item.num }}</span>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="用户信息" width="230">
            <template slot-scope="scope">
              <div class="user-wrapper" v-if="scope.row.user">
                <!-- 点头像 / 昵称打开用户编辑抽屉;嵌在用户详情里(isComponent)时就是这个用户本人,不再套一层 -->
                <el-avatar :size="30" :src="scope.row.user.avatar_url" shape="circle" icon="el-icon-user-solid"
                  style="flex-shrink:0" :class="{ 'user-link': canOpenUser }" @click.native="openUser(scope.row.user_id)" @dblclick.native.stop></el-avatar>
                <div class="u-info">
                  <div class="u-row-1">
                    <span class="u-name text-ellipsis" :class="{ 'user-link': canOpenUser }" :title="canOpenUser ? '查看 / 编辑用户' : ''"
                      @click="openUser(scope.row.user_id)" @dblclick.stop>{{ scope.row.user.nickname }}</span>
                  </div>
                  <div class="u-row-2">
                    <span class="u-id">ID: 
                      <el-button type="text" size="mini" style="padding: 0; font-size: 12px; color: #999;" @click="copyText(scope.row.user_id, '买家ID')" title="点击复制">{{ scope.row.user_id }}</el-button>
                    </span>
                    <el-tooltip v-if="scope.row.remarks" :content="scope.row.remarks" placement="top">
                      <i class="el-icon-chat-dot-round remark-icon"></i>
                    </el-tooltip>
                  </div>
                  <!-- 推荐人:下单时买家的直属上级(订单快照),点 ID 复制 -->
                  <div class="u-row-3" v-if="scope.row.referrer">
                    <span class="ref-tag">推荐人</span>
                    <span class="ref-name text-ellipsis" :class="{ 'user-link': canOpenUser }" :title="canOpenUser ? '查看 / 编辑推荐人' : scope.row.referrer.nickname"
                      @click="openUser(scope.row.referrer.id)" @dblclick.stop>{{ scope.row.referrer.nickname || '未命名' }}</span>
                    <span class="ref-id" title="点击复制推荐人ID" @click="copyText(scope.row.referrer.id, '推荐人ID')">ID {{ scope.row.referrer.id }}</span>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="实付金额" width="140" align="center">
            <template slot-scope="scope">
              <div class="price-display">
                <div v-if="Number(scope.row.pay_price) > 0 && Number(scope.row.use_integral) > 0">
                  <span class="cash">¥{{ scope.row.pay_price }}</span>
                  <span class="plus">+</span>
                  <span class="point">{{ scope.row.use_integral }}积分</span>
                </div>
                <div v-else-if="Number(scope.row.pay_price) == 0 && Number(scope.row.use_integral) > 0">
                  <span class="point">{{ scope.row.use_integral }}积分</span>
                </div>
                <div v-else>
                  <span class="cash">¥{{ scope.row.pay_price }}</span>
                </div>
                <div class="express-txt">
                  (含运费:¥{{ scope.row.express_price }})
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="支付" width="80" align="center">
            <template slot-scope="scope">
              <div class="pay-text">
                {{ scope.row.payment_name }}
              </div>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="150" align="center">
            <template slot-scope="scope">
              <el-tag :type="getStatusTagType(scope.row)" effect="plain" size="mini"
                style="border:none; padding:0 4px;">
                {{ getStatusText(scope.row) }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="收货信息" min-width="180">
            <template slot-scope="scope">
              <div class="deliver-simple">
                <div class="d-row">
                  <el-tag size="mini" :type="getDeliverTypeTag(scope.row.deliver_type)" effect="light" class="d-tag">
                    {{ getDeliverTypeText(scope.row.deliver_type) }}
                  </el-tag>
                  <el-button type="text" size="mini" class="d-name" style="padding: 0;" @click="copyText(scope.row.name, '收件人')" title="点击复制">{{ scope.row.name }}</el-button>
                  <el-button type="text" size="mini" class="d-phone" style="padding: 0;" @click="copyText(scope.row.mobile, '收件手机')" title="点击复制">{{ scope.row.mobile }}</el-button>
                  <span v-if="scope.row.deliver_type === 'pickup' && scope.row.pickup_code" class="pickup-code-inline">
                    提货码:
                    <el-button type="text" size="mini" style="padding: 0; font-weight: bold;" @click="copyText(scope.row.pickup_code, '提货码')" title="点击复制">{{ scope.row.pickup_code }}</el-button>
                  </span>
                </div>
                <div class="d-address text-ellipsis" :title="scope.row.address" @click="copyText(scope.row.address, '收货地址')" style="cursor: pointer;">
                  {{ scope.row.address }}
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="100" align="center">
            <template slot-scope="scope">
              <order-trigger :ref="'orderTrigger_' + scope.row.id" :order-id="scope.row.id" @refresh="getList">
                <el-button type="text" size="mini" style="padding: 0;">详情</el-button>
              </order-trigger>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="pagination-container">
        <el-pagination background small :current-page.sync="searchParams.page" :page-size="searchParams.limit"
          :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper"
          :total="pagination ? pagination.total_count : 0" @size-change="handleSizeChange" @current-change="pageChange">
        </el-pagination>
      </div>
    </el-card>

    <el-dialog title="自定义导出字段" :visible.sync="exportDialogVisible" width="550px" append-to-body top="8vh"
      custom-class="styled-export-dialog">
      <div class="export-header-tip">
        <i class="el-icon-info"></i> 请勾选需要导出的数据列，可上下调整顺序
      </div>
      <div class="export-body-styled custom-scrollbar">
        <div v-for="(field, index) in exportFields" :key="field.key" class="field-item-styled"
          :class="{ 'is-active': field.selected }" @click="field.selected = !field.selected">
          <div class="f-left">
            <el-checkbox v-model="field.selected" @click.native.stop></el-checkbox>
            <span class="f-label">{{ field.label }}</span>
          </div>
          <div class="f-right" @click.stop>
            <el-tooltip content="上移" placement="top" :open-delay="800">
              <div class="sort-btn" :class="{ disabled: index === 0 }" @click="moveField(index, -1)">
                <i class="el-icon-arrow-up"></i>
              </div>
            </el-tooltip>
            <el-tooltip content="下移" placement="top" :open-delay="800">
              <div class="sort-btn" :class="{ disabled: index === exportFields.length - 1 }" @click="moveField(index, 1)">
                <i class="el-icon-arrow-down"></i>
              </div>
            </el-tooltip>
          </div>
        </div>
      </div>
      <div slot="footer" class="dialog-footer">
        <div class="footer-left">
          <el-checkbox :indeterminate="isIndeterminate" v-model="checkAll" @change="handleCheckAllChange">全选
          </el-checkbox>
        </div>
        <div>
          <el-button size="medium" @click="exportDialogVisible = false">取 消</el-button>
          <el-button size="medium" type="primary" @click="confirmExport" icon="el-icon-download">确认导出</el-button>
        </div>
      </div>
    </el-dialog>



    <!-- ⛔ 用 drawer 而不是 dialog:这份说明有 6 个步骤 + 2 条支线 + 6 条排查项,
         dialog 在小屏上要么被压得很矮要么整个溢出(top="6vh" 也救不了);
         drawer 天然纵向滚动,内容再长也不会顶穿。
         wrapper-closable 让点遮罩就能关 —— 这是只读文档,不是需要谨慎确认的表单。 -->
    <el-drawer title="订单生命周期" :visible.sync="settleHelpVisible" direction="rtl"
      size="760px" append-to-body :wrapper-closable="true" custom-class="sh-drawer">
      <div class="sh-drawer-body custom-scrollbar">
      <el-alert type="info" :closable="false" show-icon class="sh-mb">
        <div slot="title">
          一张订单从下单到结算要走完下面这些步。<br>
          商家来问「怎么还没结算」时，对照这份流程就能当场答复 ——
          <b>到店核销的单：核销当场就结算</b>，不等收货、不走售后期；
          其余方式要先走完「确认收货 → 售后期」两步。
        </div>
      </el-alert>

      <el-steps direction="vertical" :active="6" finish-status="success" space="76px">
        <el-step title="① 下单（待付款）">
          <div slot="description" class="sh-d">
            订单状态 <code>10 待付款</code>，库存已锁、钱还没到。<br>
            超时未付会被自动关闭（<code>0 已关闭</code>），库存回滚，结算状态直接落
            <b>无需结算</b> —— 这类单不该出现在「未结算」的排查里。
          </div>
        </el-step>
        <el-step title="② 买家付款">
          <div slot="description" class="sh-d">转 <code>20 待发货</code>，货款在平台，<b>未</b>计入商家可提现余额。</div>
        </el-step>
        <el-step title="③ 发货 / 取货">
          <div slot="description" class="sh-d">
            · <b>快递配送</b> — 商家填单号发货，转「待收货」，继续走 ④⑤<br>
            · <b>同城配送</b> — 骑手送达，转「待收货」，继续走 ④⑤<br>
            · <b>无需物流</b> — 虚拟商品，付款后直接进入 ④<br>
            · <span class="sh-ok"><b>到店核销</b> — 扫码核销后订单<b>直达已完成并当场结算</b>，跳过 ④⑤</span>
          </div>
        </el-step>
        <el-step title="④ 确认收货">
          <div slot="description" class="sh-d">
            买家主动确认，<b>或</b>发货后满 <b>{{ settleHelp.confirmDay }} 天</b>系统自动确认，此时记录 finish_at。<br>
            <span class="sh-warn">商家反馈未结算，八成卡在这里 —— 买家不点确认。</span>
          </div>
        </el-step>
        <el-step title="⑤ 售后期">
          <div slot="description" class="sh-d">
            确认收货后再等 <b>{{ settleHelp.settleDay }} 天</b>，期间买家仍可申请退款。
            结算任务捞的是 <code>finish_at ≤ 当前时间 − 售后期</code> 的单。
          </div>
        </el-step>
        <el-step title="⑥ 结算到账">
          <div slot="description" class="sh-d">
            货款 + 运费结算到商家账户，订单结算状态转「已结算」。
            结算任务<b>每分钟</b>扫一次，另有每日 4 点的兜底任务。
          </div>
        </el-step>
      </el-steps>

      <el-divider content-position="left">随时可能岔出去的两条支线</el-divider>
      <div class="sh-branch">
        <div class="sh-branch-item">
          <div class="sh-branch-t"><i class="el-icon-refresh-left"></i> 退款 / 售后</div>
          <div class="sh-d">
            付款后到售后期结束前，买家都可以申请。退款状态走
            <code>10 已申请 → 20 处理中 → 30 已完成</code>（或 <code>40 已取消</code>）。<br>
            <b>只要有退款在处理，结算就会等</b> —— 否则钱结给商家了又要往回追。
            部分退款订单仍会结算，结的是<b>扣除退款后</b>的金额。
          </div>
        </div>
        <div class="sh-branch-item">
          <div class="sh-branch-t"><i class="el-icon-circle-close"></i> 关闭</div>
          <div class="sh-d">
            超时未付、买家取消、后台关闭都会转 <code>0 已关闭</code>。
            结算状态记 <b>无需结算</b>，这是正常终态，不是漏结。
          </div>
        </div>
      </div>

      <el-divider content-position="left">商家说没结算，按这个顺序排查</el-divider>
      <ol class="sh-list">
        <li>看订单状态 —— 还是<b>待收货</b>？那就是买家没确认，等自动确认到期。</li>
        <li>已完成但未结算 —— 看 <b>完成时间</b>，售后期（{{ settleHelp.settleDay }} 天）到了没。</li>
        <li>有<b>退款/售后</b>在处理？结算会等售后结束，避免钱结了又要退。</li>
        <li>自提单<b>没核销</b>？未核销不算交易完成。核销后是当场结算。</li>
        <li>订单<b>已关闭</b> —— 结算状态为「无需结算」，属正常。</li>
        <li>以上都排除仍未结算 —— 查该业态是否走独立结算（如收银台、艾灸、悦达由各自服务结算）。</li>
      </ol>

      <el-divider content-position="left">顶部这排数字怎么算</el-divider>
      <el-alert type="warning" :closable="false" show-icon class="sh-mb">
        <div slot="title">
          顶部的数字和下面的列表是<b>同一批订单</b> —— 当前选中的页签、时间、商家、关键词等筛选都会生效。<br>
          <b>两个收入只算已付款、没关闭的单</b>（待发货 / 待收货 / 已完成）：待付款的还没收到钱，已关闭的没付过钱或已全额退款。<br>
          <b>「全部」页签的「实付总额」和各项优惠仍包含待付款、已关闭的单</b>（下单时金额就写好了）。
          要看真实成交额，请切到<b>「待发货 / 待收货 / 已完成」</b>页签，或把时间筛选切到<b>「支付」</b>再选日期。
        </div>
      </el-alert>
      <el-table :data="statsHelp" border size="mini" class="sh-mb">
        <el-table-column prop="name" label="指标" width="96"></el-table-column>
        <el-table-column prop="how" label="怎么算"></el-table-column>
        <el-table-column prop="note" label="注意" width="250"></el-table-column>
      </el-table>
      <div class="sh-d">
        在「待发货 / 待收货 / 已完成」页签里，<b>平台收入 + 商家收入</b>一般等于<b>实付总额</b>。对不上时，差额来自两处：<br>
        ① <b>退了款的部分</b> —— 两个收入按剩余商品重算了，实付总额还是下单金额；<br>
        ② <b>卡券打给门店的结算底价</b> —— 是平台付出去的成本，两个收入都不算。
      </div>

      <div class="sh-tip">
        两个时长在<b>「设置 → 交易设置」</b>里调整：自动确认收货天数、订单结算（售后）天数。
        改动对<b>之后新产生的判定</b>生效。
      </div>

      </div>
    </el-drawer>

    <user-edit-drawer v-if="canOpenUser" :visible.sync="userDrawerVisible" :user-id="userDrawerId" />
  </div>
</template>

<script>
import UserEditDrawer from '@/views/user/UserEditDrawer.vue'

export default {
  name: 'order-index',
  components: { UserEditDrawer },
  props: {
    isComponent: { type: Boolean, default: false },
    userId: { type: [String, Number], default: '' }
  },
  data() {
    return {
      userDrawerVisible: false,
      userDrawerId: '',
      loading: false,
      list: [],
      pagination: null,
      mchList: [],
      orderTypeList: [], // 「按插件订单类型」筛选项(仅授权插件)
      multipleSelection: [],
      payment_list: [],
      pickerOptions: {
        shortcuts: [{
          text: '近一周',
          onClick(picker) {
            const end = new Date();
            const start = new Date();
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
            picker.$emit('pick', [start, end]);
          }
        }]
      },

      exportDialogVisible: false,
      checkAll: true,
      isIndeterminate: false,

      exportFields: [
        { key: 'id', label: '订单ID', selected: true },
        { key: 'order_no', label: '订单号', selected: true },
        { key: 'transaction_id', label: '交易单号', selected: true },
        { key: 'mch_name', label: '所属商家', selected: true },
        { key: 'user_info', label: '买家昵称/ID', selected: true },
        { key: 'referrer_info', label: '推荐人昵称/ID', selected: true },
        { key: 'name', label: '收货人', selected: true },
        { key: 'mobile', label: '收货电话', selected: true },
        { key: 'address', label: '收货地址', selected: true },
        { key: 'status_text', label: '订单状态', selected: true },
        { key: 'pay_type', label: '支付方式', selected: true },
        { key: 'created_at', label: '下单时间', selected: true },
        { key: 'pay_at', label: '支付时间', selected: true },
        { key: 'remarks', label: '买家备注', selected: true },
        { key: 'pay_price', label: '实付金额', selected: true },
        { key: 'express_price', label: '运费', selected: true },
        { key: 'goods_name', label: '商品名称', selected: true },
        { key: 'goods_short_name', label: '商品简称', selected: false },
        { key: 'attr', label: '规格/属性', selected: true },
        { key: 'factory', label: '生产厂家', selected: true },
        { key: 'price', label: '商品单价', selected: true },
        { key: 'num', label: '购买数量', selected: true },
        { key: 'total_price', label: '商品总价', selected: true },
        { key: 'is_send', label: '发货状态', selected: true }
      ],

      settleHelpVisible: false,

      settleHelp: { confirmDay: 7, settleDay: 0 },
      // 六项优惠:顶部合成一格「优惠合计」,明细放在悬浮框里
      discountFields: [
        { key: 'total_coupon_cut', label: '优惠券' },
        { key: 'total_first_order_discount_price', label: '首单立减' },
        { key: 'total_platform_subsidy_random_minus', label: '支付随机减' },
        { key: 'total_member_discount', label: '会员折扣' },
        { key: 'total_integral_cut', label: '积分抵扣' },
        { key: 'total_pickup_discount_price', label: '自提优惠' }
      ],
      // 顶部统计的口径说明(与后端 OrderListForm 的聚合字段一一对应,改口径时两边一起改)
      statsHelp: [
        { name: '成交金额', how: '已付款、没关闭的单（待发货 / 待收货 / 已完成），每单实付金额相加',
          note: '不分平台和商家；退款后不减，谁该拿多少钱以结算、分润的流水为准' },
        { name: '实付总额', how: '每单下单时算出的应付金额相加（商品 + 运费 − 各项优惠）',
          note: '退款后不减，仍是下单金额；待付款、已关闭的单也有值' },
        { name: '商品总额', how: '商品原价合计', note: '未扣任何优惠' },
        { name: '运费', how: '每单运费相加', note: '' },
        { name: '优惠合计', how: '优惠券、首单立减、支付随机减、会员折扣、积分抵扣、自提优惠六项相加，以负数显示；鼠标移到这一格上看各项明细',
          note: '优惠券不分平台券还是商家券，都算在优惠券里' },
      ],

      stats: {
        total_deal_amount: '0.00',
        total_pay_price: '0.00',
        total_goods_price: '0.00',
        total_express_price: '0.00',
        total_coupon_cut: '0.00',
        total_first_order_discount_price: '0.00',
        total_platform_subsidy_random_minus: '0.00',
        total_member_discount: '0.00',
        total_integral_cut: '0.00',
        total_pickup_discount_price: '0.00'
      },

      activeTabName: '-1',
      // 合并筛选:前面下拉选字段、后面输入框填值(取代原先 5 个并排文本框)
      keywordFields: [
        { label: '订单号', value: 'order_no' },
        { label: '用户ID', value: 'user_id' },
        { label: '收件手机', value: 'mobile' },
        { label: '用户手机', value: 'buyer_mobile' },
        { label: '昵称', value: 'nickname' },
        { label: '推荐人ID', value: 'referrer_id' },
      ],
      // 外部锁定的 user_id(从用户详情页嵌入/路由 ?user_id= 带入),始终生效、不被合并筛选覆盖
      fixedUserId: '',
      searchParams: {
        id: '',
        page: 1,
        limit: 10,
        status: '-1',
        keyword_field: 'order_no', // 合并筛选:当前选中的字段
        keyword: '',               // 合并筛选:输入的值
        order_no: '',
        buyer_mobile: '',
        user_id: '',
        referrer_id: '',
        mobile: '',
        nickname: '',
        search_date_type: 'created_at', // 新增：时间类型，默认下单时间
        search_date: [],
        deliver_type: '',
        payment: '',
        mch_id: '',
        plugin_type: '',
      },


      currentOrderId: null,
      exportTimer: null
    }
  },
  watch: {
    userId(val) {
      if (this.isComponent) {
        this.fixedUserId = val;
        this.searchParams.user_id = val;
        // 如果已经加载完毕，可以直接搜索最新用户订单
        this.search();
      }
    },
    exportFields: {
      handler(val) {
        const checkedCount = val.filter(f => f.selected).length;
        this.checkAll = checkedCount === val.length;
        this.isIndeterminate = checkedCount > 0 && checkedCount < val.length;
      },
      deep: true
    }
  },
  created() {
    if (this.isComponent) {
      if (this.userId) {
        this.fixedUserId = this.userId;
        this.searchParams.user_id = this.userId;
      }
    } else if (this.$route && this.$route.query.user_id) {
      this.fixedUserId = this.$route.query.user_id;
      this.searchParams.user_id = this.$route.query.user_id
    }
    // 后台首页「待发货订单」等卡片点进来:?status=20
    // 白名单校验 —— 只认 tab 上真实存在的值,别把 URL 里的任意字符串塞进查询
    if (!this.isComponent && this.$route && this.$route.query.status !== undefined) {
      const s = String(this.$route.query.status)
      if (['-1', '0', '10', '20', '30', '40', 'refund'].indexOf(s) > -1) {
        this.activeTabName = s
        this.searchParams.status = s
      }
    }
    this.getMchList()
    this.getOrderTypes()
    this.getList()
    this.loadFieldPreference()
  },
  beforeDestroy() {
    if (this.exportTimer) clearInterval(this.exportTimer)
  },
  computed: {
    // 嵌在用户详情里时列表只有这个用户本人的订单,不再从这里套开用户抽屉
    canOpenUser() {
      return !this.isComponent
    },
    discountItems() {
      return this.discountFields.map(f => ({ ...f, cents: Math.round((parseFloat(this.stats[f.key]) || 0) * 100) }))
    },
    discountTotal() {
      return (this.discountItems.reduce((sum, d) => sum + d.cents, 0) / 100).toFixed(2)
    },
    keywordPlaceholder() {
      const f = this.keywordFields.find(i => i.value === this.searchParams.keyword_field)
      return f ? `请输入${f.label}` : '请输入关键词'
    }
  },
  methods: {
    openUser(id) {
      if (!this.canOpenUser || !id) return
      this.userDrawerId = id
      this.userDrawerVisible = true
    },
    getRowKey(row) {
      return row.id
    },
    handleSelectionChange(val) {
      this.multipleSelection = val
    },
    copyText(text, label = '内容') {
      if (!text) return;
      const input = document.createElement('textarea');
      input.value = text;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      try {
        if (document.execCommand('copy')) {
          this.$message.success(`${label} ${text} 已复制到剪贴板`);
        }
      } catch (err) {
        this.$message.error('浏览器不支持快捷复制，请手动复制');
      }
      document.body.removeChild(input);
    },
    resetSearch() {
      this.searchParams = {
        page: 1, limit: 10, status: this.activeTabName,
        keyword_field: 'order_no', keyword: '',
        order_no: '', user_id: '', referrer_id: '', mobile: '', buyer_mobile: '', nickname: '',
        search_date_type: 'created_at', // 重置为默认
        search_date: [], mch_id: '', deliver_type: '', plugin_type: ''
      }
      this.getList()
    },
    // 把合并筛选(keyword_field + keyword)翻译回后端认识的具体字段,后端契约不变
    buildQuery() {
      const p = { ...this.searchParams }
      const kf = p.keyword_field
      const kv = (p.keyword || '').trim()
      // 文本字段默认清空,仅选中的那个填当前输入值
      ;['order_no', 'user_id', 'referrer_id', 'mobile', 'buyer_mobile', 'nickname'].forEach(f => {
        p[f] = (kf === f && kv) ? kv : ''
      })
      // 外部锁定的 user_id(用户详情页嵌入/路由带入)始终生效,不被合并筛选覆盖
      if (this.fixedUserId) p.user_id = this.fixedUserId
      delete p.keyword_field
      delete p.keyword
      return p
    },
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/mall/order/list',
          method: 'get',
          data: this.buildQuery()
        })
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
          this.stats = res.data.stats
          if (res.data.settle_help) {
            this.settleHelp = {
              confirmDay: res.data.settle_help.confirm_day,
              settleDay: res.data.settle_help.settle_day
            }
          }
          this.payment_list = res.data.payment_list
          this.list.forEach(item => {
            if (!item.user) item.user = { nickname: '未知', avatar_url: '' }
            if (item.orderDetails) {
              item.orderDetails.forEach(detail => {
                if (typeof detail.attr === 'string') {
                  try {
                    detail.attr = JSON.parse(detail.attr)
                  } catch (e) {
                  }
                }
              })
            }
          })
        } else {
          this.$message.error(res.msg)
        }
      } catch (error) {
        console.error(error)
      } finally {
        this.loading = false
      }
    },
    async getMchList() {
      try {
        const res = await this.$request({ url: '/plugin/mch/mall/mch/index', data: { limit: 99 } })
        if (res.code === 0) this.mchList = res.data.list
      } catch (e) {
      }
    },
    async getOrderTypes() {
      try {
        const res = await this.$request({ url: '/mall/order/order-types' })
        if (res.code === 0) this.orderTypeList = res.data.list || []
      } catch (e) {
      }
    },
    search() {
      this.searchParams.page = 1;
      this.getList()
    },
    tabClick(tab) {
      this.searchParams.status = tab.name;
      this.search()
    },
    pageChange(page) {
      this.searchParams.page = page;
      this.getList()
    },
    handleSizeChange(val) {
      this.searchParams.limit = val;
      this.getList()
    },


    // --- 导出逻辑 ---
    openExportDialog() {
      this.exportDialogVisible = true;
    },
    handleCheckAllChange(val) {
      this.exportFields.forEach(f => f.selected = val);
      this.isIndeterminate = false;
    },
    moveField(index, direction) {
      const target = index + direction;
      if (target < 0 || target >= this.exportFields.length) return;
      const temp = this.exportFields[index];
      this.$set(this.exportFields, index, this.exportFields[target]);
      this.$set(this.exportFields, target, temp);
      this.saveFieldPreference();
    },
    saveFieldPreference() {
      localStorage.setItem('order_export_styled', JSON.stringify(this.exportFields));
    },
    loadFieldPreference() {
      const saved = localStorage.getItem('order_export_styled');
      if (saved) {
        try {
          const savedArr = JSON.parse(saved);
          const newFields = savedArr.map(s => {
            const exist = this.exportFields.find(f => f.key === s.key);
            return exist ? { ...exist, selected: s.selected } : null;
          }).filter(Boolean);
          this.exportFields.forEach(f => {
            if (!newFields.find(nf => nf.key === f.key)) newFields.push(f);
          });
          this.exportFields = newFields;
        } catch (e) {
        }
      }
    },
    confirmExport() {
      const selectedFields = this.exportFields.filter(f => f.selected).map(f => f.key);
      if (!selectedFields.length) return this.$message.warning('请至少选择一个字段');
      this.exportDialogVisible = false;
      this.saveFieldPreference();
      const selectedIds = this.multipleSelection.map(item => item.id)
      this.$confirm(`确认导出 ${selectedIds.length > 0 ? selectedIds.length + ' 条' : '所有筛选'}订单？`, '导出', { type: 'info' }).then(() => {
        const loading = this.$loading({ text: '正在创建任务...', background: 'rgba(255,255,255,0.7)' });
        const data = { ...this.buildQuery(), export_fields: JSON.stringify(selectedFields) };
        if (selectedIds.length > 0) data.order_ids = JSON.stringify(selectedIds);
        this.$request({ url: '/mall/order/async-export', method: 'get', data }).then(res => {
          if (res.code === 0) {
            this.pollExportStatus(res.data.token, loading);
          } else {
            loading.close();
            this.$message.error(res.msg);
          }
        }).catch(() => loading.close());
      });
    },
    pollExportStatus(token, loading) {
      if (this.exportTimer) clearInterval(this.exportTimer);
      this.exportTimer = setInterval(() => {
        this.$request({ url: '/mall/order/export-status', method: 'get', data: { token } }).then(res => {
          if (res.code === 0 && res.data.status === 'success') {
            clearInterval(this.exportTimer);
            loading.close();
            window.location.href = res.data.url;
          } else if (res.code !== 0 || res.data.status === 'failed') {
            clearInterval(this.exportTimer);
            loading.close();
            this.$message.error('导出失败');
          }
        }).catch(() => {
          clearInterval(this.exportTimer);
          loading.close();
        });
      }, 1500);
    },

    getStatusText(o) {
      const map = {
        0: '已关闭',
        10: '待付款',
        20: o.deliver_type === 'pickup' ? '待自提' : '待发货',
        30: '待收货',
        40: '已完成'
      }
      const base = map[o.status] || '-'
      // 有售后时两样都写:订单走到哪一步 + 售后到哪一步。原来只写售后,把「待发货」这类订单状态盖掉了
      const refund = { 10: '售后待处理', 20: '售后处理中', 30: '售后已完成' }[o.refund_status]
      return refund ? `${base} · ${refund}` : base
    },
    getStatusTagType(o) {
      if (o.refund_status > 0) return 'danger'
      const map = { 0: 'info', 10: 'danger', 20: 'primary', 30: 'warning', 40: 'success' }
      return map[o.status] || 'info'
    },
    getDeliverTypeText(t) {
      const map = { express: '快递', pickup: '自提', city: '同城' };
      return map[t] || '其他'
    },
    getDeliverTypeTag(t) {
      return t === 'express' ? 'success' : (t === 'pickup' ? 'warning' : 'primary')
    },
    formatTimeShort(timeStr) {
      if (!timeStr) return '';
      return timeStr.substring(5, 16);
    },
    handleRowDblClick(row) {
      const ref = this.$refs['orderTrigger_' + row.id]
      if (ref) {
        // Vue refs inside v-for (or created dynamically) can be arrays
        const inst = Array.isArray(ref) ? ref[0] : ref
        if (inst && inst.openDrawer) {
          inst.openDrawer()
        } else if (inst && inst.$el) {
          // If the component doesn't expose openDrawer directly, fallback to triggering click on the button
          const btn = inst.$el.querySelector('.el-button') || inst.$el;
          if (btn) btn.click()
        }
      }
    }
  }
}
</script>

<style lang="scss" scoped>
/* 颜色变量 - 统一白色底风格 */
$primary: #409EFF;
$success: #67C23A;
$warning: #E6A23C;
$danger: #F56C6C;
$info: #909399;
$text-main: #303133;
$text-sub: #606266;
$border-color: #EBEEF5;

.app-container {
  height: calc(100vh - 85px);
  width: 100%;
  background-color: #f0f2f5;
  padding: 15px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  &.is-component {
    height: 100% !important;
    padding: 0 !important;
    background-color: transparent !important;
  }
}

/* 1. 统计面板:一行排开,格与格之间细竖线分隔;放不下时横向滚动,不折行 */
.stats-bar {
  flex-shrink: 0;
  display: flex;
  margin-bottom: 10px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow-x: auto;

  .sb-item {
    flex: 1 0 auto;
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 10px 16px;
    white-space: nowrap;

    & + .sb-item { border-left: 1px solid #f0f2f5; }

    &.is-hoverable {
      cursor: default;
      transition: background 0.2s;

      &:hover { background: #fafbfc; }
      .el-icon-arrow-down { margin-left: 2px; font-size: 11px; }
    }

    .s-label {
      font-size: 12px;
      color: #909399;
    }

    .s-val {
      font-size: 16px;
      font-weight: bold;
      font-family: 'DIN', sans-serif;
      font-variant-numeric: tabular-nums;
    }
  }
}

/* 颜色辅助类 */
.text-blue {
  color: $primary;
}

.text-green {
  color: $success;
}

.text-main {
  color: $text-main;
}

.text-info {
  color: $info;
}

.text-sub {
  color: $text-sub;
}

.text-orange {
  color: $warning;
}

.text-purple {
  color: #b37feb;
}

/* 2. 表格卡片 */
.table-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: none;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.05);
  overflow: hidden;
  min-height: 0;

  ::v-deep .el-card__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 15px;
    box-sizing: border-box;
    overflow: hidden;
    min-height: 0;
  }
}

.filter-wrapper {
  flex-shrink: 0;
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 1px dashed #f0f0f0;

  .filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
  }

  .el-form-item {
    margin-bottom: 8px;
    margin-right: 12px;
  }
}

.custom-tabs {
  flex-shrink: 0;
  margin-bottom: 5px;

  ::v-deep .el-tabs__item {
    font-size: 13px;
    font-weight: 500;
    height: 36px;
    line-height: 36px;
  }

  ::v-deep .el-tabs__nav-wrap::after {
    background-color: #f2f2f2;
    height: 1px;
  }
}

.table-flex-container {
  flex: 1;
  overflow: hidden;
  min-height: 0;
  position: relative;
}

/* 核心优化：输入框组合对齐 */
/* --- 核心优化：搜索栏组合输入框对齐 --- */
.custom-input-group {
  display: inline-flex;
  vertical-align: middle;

  ::v-deep {

    /* 1. 强制统一两者的绝对高度 (mini尺寸标准高度为28px) */
    .el-input__inner,
    .el-range-editor.el-input__inner {
      height: 28px !important;
      min-height: 28px !important;
      line-height: 26px !important;
      /* height 减去上下 1px 的 border */
      box-sizing: border-box;
    }

    /* 2. 左侧 Select 样式调整 */
    .group-select {
      position: relative;
      z-index: 2;
      /* 默认层级高于右侧，盖住接缝 */

      .el-input__inner {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        background-color: #F5F7FA;
        border-right-color: transparent;
        /* 隐藏右边框，使其融合 */
      }

      /* Hover 和 Focus 时恢复主题色边框 */
      &:hover .el-input__inner,
      .el-input.is-focus .el-input__inner {
        border-right-color: var(--admin-accent-text);
      }
    }

    /* 3. 右侧输入框样式调整(与左侧 Select 拼接) */
    .group-input {
      position: relative;
      z-index: 1;

      .el-input__inner {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
      }

      &:hover,
      &.is-active {
        z-index: 3;
      }
    }

    /* 3. 右侧 DatePicker 样式调整 */
    .group-picker {
      position: relative;
      z-index: 1;

      &.el-range-editor.el-input__inner {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        padding-top: 0;
        padding-bottom: 0;
        display: inline-flex;
        align-items: center;
        /* 确保内部文字和图标垂直居中 */
      }

      /* 右侧交互时提高层级，防止左侧边框遮挡 */
      &:hover,
      &.is-active,
      &.is-focus {
        z-index: 3;
        border-color: var(--admin-accent-text);
      }

      /* 调整内部图标对其 */
      .el-range__icon,
      .el-range__close-icon {
        line-height: 26px;
      }
    }
  }
}

/* --- 核心优化：紧凑型表格样式 --- */
.order-table {
  ::v-deep th.gutter {
    display: table-cell !important;
  }

  .price-display {
    line-height: 1.4;

    .cash {
      color: #F56C6C;
      /* 价格红 */
      font-weight: bold;
      font-size: 14px;
    }

    .point {
      color: #E6A23C;
      /* 积分黄/橙色 */
      font-weight: bold;
      font-size: 13px;
    }

    .plus {
      margin: 0 2px;
      color: #909399;
      font-size: 13px;
    }

    .express-txt {
      font-size: 12px;
      color: #909399;
      /* 灰色，不抢视觉重心 */
      margin-top: 3px;
    }
  }

  /* 通用行高控制 */
  .cell {
    line-height: 1.4;
  }

  /* 订单信息列 */
  .info-wrapper {
    .order-no-row {
      display: flex;
      align-items: center;
      margin-bottom: 2px;

      /* 紧凑间距 */
      .order-no {
        font-family: 'Arial', sans-serif;
        font-weight: bold;
        color: $text-main;
        font-size: 13px;
      }

      .copy-icon {
        margin-left: 5px;
        color: var(--admin-accent-text);
        cursor: pointer;
        font-size: 13px;
      }
    }

    .meta-row {
      display: flex;
      align-items: center;
      font-size: 12px;
      margin-top: 3px;

      .shop-badge {
        background: #f2f3f5;
        color: #606266;
        padding: 1px 4px;
        border-radius: 2px;
      }

      .time {
        color: #909399;
        margin-left: 8px;
      }

      .mini-tag {
        margin-left: 5px;
        height: 18px;
        line-height: 16px;
        padding: 0 4px;
      }
    }
  }

  /* 商品明细列 */
  .goods-list-container {
    max-height: 120px;
    overflow-y: auto;

    .goods-item {
      display: flex;
      padding: 3px 0;
      /* 极小内边距 */
      border-bottom: 1px dashed #f5f5f5;

      &:last-child {
        border-bottom: none;
      }

      .goods-thumb {
        width: 32px;
        /* 缩小图片 */
        height: 32px;
        border-radius: 3px;
        margin-right: 6px;
        flex-shrink: 0;
        border: 1px solid #f0f0f0;
      }

      .goods-detail {
        flex: 1;
        overflow: hidden;
        margin-right: 5px;

        .g-name {
          font-size: 13px;
          color: #333;
          margin-bottom: 2px;
          line-height: 18px;
        }

        .g-spec {
          font-size: 12px;
          color: #888;
          line-height: 16px;
        }

        /* 明细ID 借规格那一行,不另起一行 —— 这一栏 max-height 只有 120px,
           每个商品多一行会让可见商品数从 3 个掉到 2 个;而无规格的商品本来就空着这行。
           颜色用 el-button type=text 的默认蓝:这页「可点复制」是统一信号
           (订单ID/订单号/买家ID/收件人都是它),不另造一个色。
           等宽字体是刻意的 —— 明细ID 通常是拿去和日志、流水里的号做比对的,
           非等宽下两串数字对不齐,看错一位就白查半天。 */
        .g-did {
          padding: 0;
          margin-right: 6px;
          font-size: 12px;
          font-family: Menlo, Consolas, monospace;
          vertical-align: baseline;
        }
      }

      .goods-price-num {
        text-align: right;

        /* 价格是这格的主信息:靠字重和深色立住,不用饱和色 ——
           这一行旁边已经有状态 tag、优惠券暖色、可点蓝字在抢注意力,再加个红就是噪音;
           而红色在订单语境里是"退款/异常",标在正常单价上会让人以为这单出了问题。
           原来没写 color、靠继承,换主题或改父级色时会跟着漂,这里定死。 */
        .price {
          font-size: 13px;
          font-weight: 600;
          color: #303133;
          display: block;
          line-height: 18px;
        }

        .num {
          font-size: 12px;
          color: #999;
          line-height: 16px;
        }
        /* 多件才提示。不用主题蓝 —— 这页的蓝是"可点复制"的信号(订单号/ID/收件人都是),
           数量用了会诱导人去点。取个更沉的靛色,只加粗不加底,它是提示不是标签 */
        .num-multi {
          color: #5b7cfa;
          font-weight: 600;
        }
      }
    }
  }

  /* 用户信息列 */
  .user-wrapper {
    display: flex;
    align-items: center;

    .u-info {
      flex: 1;
      min-width: 0;
      margin-left: 6px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .u-row-1 {
      line-height: 16px;
    }

    .u-row-2 {
      line-height: 14px;
      margin-top: 1px;
    }

    .u-name {
      font-size: 13px;
      font-weight: 500;
      color: #333;
    }

    .user-link {
      cursor: pointer;
    }

    .u-name.user-link:hover,
    .ref-name.user-link:hover {
      color: var(--admin-accent-text);
      text-decoration: underline;
    }

    .u-id {
      font-size: 12px;
      color: #999;
      display: inline-block;
    }

    .u-row-3 {
      display: flex;
      align-items: center;
      gap: 4px;
      margin-top: 3px;
      line-height: 16px;
      min-width: 0;
    }

    .ref-tag {
      flex-shrink: 0;
      padding: 0 4px;
      font-size: 11px;
      border-radius: 3px;
      color: var(--admin-accent-text);
      background: var(--admin-accent-soft);
    }

    .ref-name {
      min-width: 0;
      font-size: 12px;
      color: #606266;
    }

    .ref-id {
      flex-shrink: 0;
      font-size: 12px;
      color: #909399;
      cursor: pointer;

      &:hover {
        color: var(--admin-accent-text);
      }
    }

    .remark-icon {
      color: #E6A23C;
      font-size: 13px;
      margin-left: 2px;
      vertical-align: -1px;
      cursor: pointer;
    }
  }

  .main-price {
    font-weight: bold;
    font-size: 14px;
    color: #333;
  }

  .express-txt {
    font-size: 12px;
    color: #909399;
    white-space: nowrap;
  }

  .pay-text {
    font-size: 13px;
    color: #606266;
  }

  .pay-type-0 {
    color: #67C23A;
  }

  /* 微信绿 */

  /* 收货信息列 */
  .deliver-simple {
    font-size: 12px;

    .d-row {
      margin-bottom: 2px;
      display: flex;
      align-items: center;
      flex-wrap: nowrap;
      overflow: hidden;
    }

    .d-tag {
      padding: 0 4px;
      height: 16px;
      line-height: 16px;
      margin-right: 4px;
    }

    .d-name {
      font-weight: bold;
      margin-right: 6px;
      color: #333;
      font-size: 13px;
      white-space: nowrap;
    }

    .d-phone {
      color: #666;
      font-size: 13px;
      font-family: 'Arial';
      margin-right: 5px;
      white-space: nowrap;
    }

    .d-address {
      color: #888;
      font-size: 12px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 2px;
    }

    .pickup-code-inline {
      color: $primary;
      font-weight: bold;
      white-space: nowrap;
      margin-left: 5px;
    }
  }
}

.pagination-container {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
  padding-right: 5px;
}

/* 导出弹窗 */
.export-body-styled {
  max-height: 380px;
  overflow-y: auto;
  padding: 2px;

  .field-item-styled {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 6px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      border-color: var(--admin-accent-border);
      background-color: var(--admin-accent-soft);
    }

    &.is-active {
      background-color: var(--admin-accent-soft);
      border-color: var(--admin-accent-text);

      .f-label {
        color: var(--admin-accent-text);
        font-weight: 600;
      }
    }

    .f-label {
      margin-left: 8px;
      font-size: 13px;
      color: #606266;
    }

    .sort-btn {
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-left: 6px;
      border-radius: 4px;
      color: #909399;
      cursor: pointer;

      &:hover {
        background: #fff;
        color: var(--admin-accent-text);
      }
    }
  }
}

/* 通用动画与滚动条 */
.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #dcdfe6;
  border-radius: 2px;
}

/* Joined Input Group Styles */
.custom-input-group {
  .group-select {
    ::v-deep .el-input__inner {
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
      border-right: 0;

      &:focus {
        border-color: var(--admin-accent-text);
        position: relative;
        z-index: 2;
      }
    }
  }

  .group-picker {
    ::v-deep .el-input__inner {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0;

      &:focus {
        border-color: var(--admin-accent-text);
        position: relative;
        z-index: 2;
      }
    }
  }
}

.sh-mb { margin-bottom: 14px; }
.sh-d { color: #606266; font-size: 13px; line-height: 1.9; }
.sh-warn { color: #E6A23C; }
.sh-ok { color: #67C23A; }
.sh-list { margin: 8px 0 0; padding-left: 20px; color: #606266; font-size: 13px; line-height: 2.1; }
/* ⛔ el-drawer__header 是 Element 自己渲染的,不带本组件的 data-v 属性 —— scoped 下
   `.sh-drawer .el-drawer__header` 会被编译成 `... .el-drawer__header[data-v-x]`,一条都匹配不上,
   必须走 ::v-deep。(而 .sh-drawer-body 是本组件模板里的 div,自带属性,不用穿透。
    同理下面那些 .sh-* 在 dialog 时代就能生效,也是这个原因 —— append-to-body 只是搬了 DOM 位置,
    不影响元素身上已有的 data-v。) */
::v-deep .sh-drawer .el-drawer__header {
  margin-bottom: 0;
  padding: 16px 20px;
  border-bottom: 1px solid #ebeef5;
  color: #303133;
  font-size: 16px;
}
::v-deep .sh-drawer .el-drawer__body { overflow: hidden; }
.sh-drawer-body { padding: 20px; height: 100%; overflow-y: auto; box-sizing: border-box; }

/* 与搜索/刷新同一族的圆形按钮,只是配色更淡 —— 它是辅助说明,不该和主操作等重 */
.help-btn {
  color: #909399;
  margin-left: 5px;
}
.help-btn:hover { color: var(--admin-accent-text); border-color: var(--admin-accent-border); }

.sh-branch {
  display: flex;
  gap: 12px;
  margin-bottom: 4px;
}
.sh-branch-item {
  flex: 1;
  min-width: 0;
  background: #fafafa;
  border-radius: 6px;
  padding: 10px 12px;
}
.sh-branch-t {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 6px;
}
.sh-branch-t i { margin-right: 4px; color: #909399; }

.sh-tip { margin-top: 14px; color: #909399; font-size: 12px; }
</style>

<style lang="scss">
/* 订单列表「优惠合计」悬浮明细(挂在 body 上,不能 scoped) */
.order-discount-tip {
  min-width: 170px;

  .dt-row {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    font-size: 12px;
    line-height: 24px;
    color: #606266;

    b { color: #e6a23c; font-variant-numeric: tabular-nums; }
    &.is-zero { color: #c0c4cc; b { color: #c0c4cc; font-weight: normal; } }
  }
}
</style>
