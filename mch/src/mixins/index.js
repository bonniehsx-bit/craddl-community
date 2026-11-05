import {
  mapActions,
  mapState,
  mapGetters
} from 'vuex'
import store from '@/store/index.js'; //需要引入store
module.exports = {
  // 定义每个组件都可能需要用到的外部样式以及类名
  props: {

  },
  data() {
    return {

    }
  },
  created() {
    // 组件当中，只有created声明周期，为了能在组件使用，故也在created中将方法挂载到$u
  },
  computed: {
    ...mapGetters(['socketState', 'socketMessage','userInfo']),
  },
  watch: {
    socketMessage(newVal, oldVal) {},
  },
  methods: {
    ...mapActions([]),
    // 跳转某一个页面
  }
}
