import Layout from '@/layout/index.vue'

const storeRouter = [
  {
    path: '/store-admin',
    component: Layout,
    redirect: '/finance/income',
    name: 'StoreAdmin',
    meta: { title: '门店资产', icon: 'el-icon-setting', roles: ['manager'] },
    children: [
      {
        path: '/finance',
        name: 'FinanceGroup',
        component: { render: h => h('router-view') },
        meta: { title: '财务管理', icon: 'el-icon-wallet' },
        children: [
          {
            path: '/finance/income',
            name: 'FinanceIncome',
            component: () => import('@/views/finance/income.vue'),
            meta: { title: '收支明细' }
          }
          // 「提现记录」已移除：提现独立为 withdraw 插件后 mch 端 cash 接口已删，商家后台不再提供提现入口
        ]
      },
      {
        path: '/store',
        name: 'StoreGroup',
        component: { render: h => h('router-view') },
        meta: { title: '门店配置', icon: 'el-icon-s-shop' },
        children: [
          {
            path: '/store/setting',
            name: 'StoreSetting',
            component: () => import('@/views/store/setting.vue'),
            meta: { title: '退货地址' }
          },
          {
            path: '/store/info-edit',
            name: 'StoreInfoEdit',
            component: () => import('@/views/store/info-edit.vue'),
            meta: { title: '门店资料' }
          },
          {
            path: '/store/delivery',
            name: 'StoreDelivery',
            component: () => import('@/views/store/delivery.vue'),
            meta: { title: '配送设置' }
          },
          {
            path: '/store/freight',
            name: 'StoreFreight',
            component: () => import('@/views/store/freight.vue'),
            meta: { title: '运费模板' }
          }
        ]
      },
      {
        path: '/clerk',
        name: 'ClerkGroup',
        component: { render: h => h('router-view') },
        meta: { title: '员工管理', icon: 'el-icon-s-custom' },
        children: [
          {
            path: '/clerk/index',
            name: 'ClerkList',
            component: () => import('@/views/clerk/index.vue'),
            meta: { title: '店员管理' }
          },
          {
            path: '/clerk/record',
            name: 'ClerkRecord',
            component: () => import('@/views/clerk/record.vue'),
            meta: { title: '核销记录' }
          }
        ]
      }
    ]
  }
]

export default storeRouter
