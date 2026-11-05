/**
 * 【说明】微信小程序 WXML 不支持 `<component :is="...">` 动态组件，
 * 因此 Panel 的挂载方式改为：在 `shell/OrderDetailShell.vue` 里静态 import 所有 Panel，
 * 并在模板中用 `v-if="panel.component === 'XxxPanel'"` 显式分支渲染。
 *
 * 这个文件保留作为「已注册 Panel 列表」的文档和白名单来源，
 * 让后端 / 开发者快速对齐"哪些 component 名是合法的"。
 *
 * 新增 Panel 步骤：
 *   1. 写 panels/XxxPanel.vue
 *   2. 在 shell/OrderDetailShell.vue:
 *      · import XxxPanel
 *      · components 注册
 *      · KNOWN_PANELS 数组加 'XxxPanel'
 *      · 在动态面板区 template 加 <XxxPanel v-else-if="panel.component === 'XxxPanel'" ...>
 *   3. 在这里 PANELS 里加一条说明
 */

export const PANELS = [
    { name: 'HotelStayPanel',    desc: '酒店入住信息：日期轴 + 入住人（商品上方 priority<50）' },
    { name: 'GroupPackagePanel', desc: '团购套餐内容 + 使用规则（商品下方 priority≥50）' },
    { name: 'YudaPartyPanel',    desc: '遇搭拼局：角色 + 约会时间 + 商家门店（商品上方）' },
    { name: 'MassageBookingPanel', desc: '艾灸预约：状态 + 时间 + 门店 + 技师（商品上方）' },
    { name: 'DiningOrderPanel',  desc: '门店点单：就餐方式 + 桌号/取餐号 + 出餐进度 + 餐位费/打包费（商品上方）' },
    { name: 'SeckillInfoPanel',  desc: '限时秒杀：场次 + 秒杀价/原价 + 已省金额 + 抢购时间（商品上方）' },
    { name: 'BookingPanel',      desc: '预约服务：预约时间 + 序号 + 人员/预约人 + 门店 + 预约凭证入口（商品上方）' },
    { name: 'AidbLetterPanel',   desc: '圣诞老人有声来信：主题 + 收信孩子 + 制作进度 + 交付链接（商品上方）' },
];

export default PANELS;
