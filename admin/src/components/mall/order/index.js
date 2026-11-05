import OrderDetailDrawer from './OrderDetailDrawer.vue'

export default {
  install(Vue, options = {}) {
    // 抽屉单独挂在 body 上、不在 App 组件树里,默认拿不到 $router;注册时把 router 传进来
    const router = options.router

    // 1. 创建构造器
    const DrawerConstructor = Vue.extend(OrderDetailDrawer)

    // 单例实例
    let instance = null

    // 初始化实例的方法
    const initInstance = () => {
      instance = new DrawerConstructor({
        el: document.createElement('div'),
        router
      })
      // 挂载到 body
      document.body.appendChild(instance.$el)
    }

    // 换页面(菜单、浏览器返回、代码跳转)时关掉抽屉和它弹出的窗口,不然会一直盖在新页面上
    if (router) {
      router.afterEach(() => {
        if (instance) instance.closeAll()
      })
    }

    // 2. 定义核心打开方法
    const openDrawer = (orderId, callback) => {
      if (!instance) {
        initInstance()
      }
      // 调用组件内部的 open 方法
      // callback 用于操作成功后的刷新（比如发货成功刷新列表）
      instance.open(orderId, callback)
    }

    // 3. 挂载全局方法 $previewOrder
    // 用法: this.$previewOrder(1001, () => { this.getList() })
    Vue.prototype.$previewOrder = openDrawer

    // 4. 注册全局组件 <order-trigger>
    // 这是一个轻量级的“触发器”组件，专门用于列表 Slot
    Vue.component('OrderTrigger', {
      props: {
        orderId: {
          type: [Number, String],
          required: true
        }
      },
      render(h) {
        // 渲染一个 span 包裹你的 slot 内容
        return h('span', {
          class: 'order-trigger-wrapper',
          style: {
            display: 'inline-block',
            cursor: 'pointer'
          },
          on: {
            click: (e) => {
              // 阻止冒泡，防止触发 el-table 行点击事件
              e.stopPropagation()

              // 调用上面的打开方法
              openDrawer(this.orderId, () => {
                // 当 Drawer 内部执行刷新回调时，触发组件的 @refresh 事件
                this.$emit('refresh')
              })
            }
          }
        }, this.$slots.default) // 渲染 <order-trigger>...</order-trigger> 中间的内容
      }
    })
  }
}
