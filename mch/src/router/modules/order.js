import Layout from '@/layout/index.vue'

const orderRouter = [
  {
    path: '/order-management',
    component: Layout,
    redirect: '/order/index',
    name: 'OrderManagement',
    meta: { title: '订单管理', icon: 'el-icon-s-order' },
    children: [
      {
        path: '/order/index',
        name: 'StandardOrderList',
        component: () => import('@/views/order/index.vue'),
        meta: { title: '订单列表' }
      },
      {
        path: '/refund/index',
        name: 'RefundList',
        component: () => import('@/views/refund/index.vue'),
        meta: { title: '售后订单', roles: ['manager'] }
      }
    ]
  }
]

export default orderRouter
