import Layout from '@/layout/index.vue'

const mallRouter = [
  {
    path: '/mall-business',
    component: Layout,
    redirect: '/goods/list',
    name: 'MallBusiness',
    meta: { title: '店铺运营', icon: 'el-icon-shopping-bag-1' },
    children: [

      {
        path: '/goods',
        name: 'GoodsGroup',
        component: { render: h => h('router-view') },
        meta: { title: '商品管理', icon: 'el-icon-s-goods', roles: ['manager'] },
        children: [
          {
            path: '/goods/list',
            name: 'GoodsList',
            component: () => import('@/views/goods/list.vue'),
            meta: { title: '商品列表' }
          },
          {
            path: '/goods/category',
            name: 'GoodsCategory',
            component: () => import('@/views/goods/category.vue'),
            meta: { title: '商品分类' }
          },
          {
            path: '/goods/comment',
            name: 'GoodsComment',
            component: () => import('@/views/goods/comment.vue'),
            meta: { title: '商品评价' }
          },
          {
            path: '/goods/service-tag',
            name: 'GoodsServiceTag',
            component: () => import('@/views/goods/service-tag.vue'),
            meta: { title: '服务标签' }
          }
        ]
      },

      {
        path: '/coupon/list',
        name: 'CouponList',
        component: () => import('@/views/coupon/list.vue'),
        meta: { title: '店铺优惠', icon: 'el-icon-s-ticket', roles: ['manager'] }
      },
      {
        path: '/customer/index',
        name: 'CustomerList',
        component: () => import('@/views/customer.vue'),
        meta: { title: '客户管理', icon: 'el-icon-user' }
      }
    ]
  }
]

export default mallRouter
