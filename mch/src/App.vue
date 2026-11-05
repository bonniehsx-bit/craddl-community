<template>
  <div id="app">
    <router-view />
  </div>
</template>

<script>
  import {
    mapActions,
    mapState,
    mapGetters
  } from 'vuex'
  import store from '@/store/index.js'; //需要引入store
  import {
    watch
  } from "vue";
  export default {
    name: 'App',
    data(){
      return{
        connect_times:0,
      }
    },
    computed: {
      ...mapGetters([
        'socketState',
        'socketMessage'
      ]),
    },
    watch: {
      socketState(newVal, oldVal) {
        if (!newVal) {
          this.connect();
        }else{
          this.connect_times=0;
        }
      }
    },
    methods: {
      connect() {
        if (this.connect_times >= 20) {
          console.log('连接超时');
          return;
        }
        this.connect_times++;
        console.log(`连接断开，正在尝试第${this.connect_times}次重连`);
        setTimeout(() => {
          this.$store.dispatch('WEBSOCKET_CONNECT');
        }, 3000)
      }
    }
  }
</script>
<style lang="scss">
  .flex {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
  }

  .ql-editor {
    /* padding: 0 !important; */
  }

  .flex-row {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-orient: horizontal;
    -webkit-flex-direction: row;
    flex-direction: row;
  }

  .flex-col {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-orient: vertical;
    -webkit-flex-direction: column;
    flex-direction: column;
  }

  .flex-grow-0 {
    min-width: 0;
    -webkit-box-flex: 0;
    -webkit-flex-grow: 0;
    -ms-flex-positive: 0;
    flex-grow: 0;
    -webkit-flex-shrink: 0;
    -ms-flex-negative: 0;
    flex-shrink: 0;
  }

  .flex-grow-1 {
    min-width: 0;
    -webkit-box-flex: 1;
    -webkit-flex-grow: 1;
    -ms-flex-positive: 1;
    flex-grow: 1;
    -webkit-flex-shrink: 1;
    -ms-flex-negative: 1;
    flex-shrink: 1;
  }

  .flex-x-center {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-pack: center;
    -webkit-justify-content: center;
    -ms-flex-pack: center;
    justify-content: center;
  }

  .flex-y-center {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-align: center;
    -webkit-align-items: center;
    -ms-flex-align: center;
    -ms-grid-row-align: center;
    align-items: center;
  }

  .flex-y-bottom {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-align: end;
    -webkit-align-items: flex-end;
    -ms-flex-align: end;
    -ms-grid-row-align: flex-end;
    align-items: flex-end;
  }

  .flex-x-between {
    display: -webkit-box;
    display: -webkit-flex;
    display: flex;
    -webkit-box-align: center;
    -webkit-align-items: center;
    -ms-flex-align: center;
    -ms-grid-row-align: center;
    justify-content: space-between;
  }

  .add-image-btn {
    position: relative;
    width: 85px;
    height: 85px;
    color: #419EFB;
    border: 1px solid #e2e2e2;
    cursor: pointer;
    padding: 2px;
    margin-right: 10px;
    font-size: 45px;

    .pic-del {
      position: absolute;
      top: -10px;
      height: 20px;
      width: 20px;
      background-color: #F56C6C;
      border-radius: 50%;
      color: #FFF0FF;
      right: -10px;
      font-size: 12px;
    }
  }
</style>
