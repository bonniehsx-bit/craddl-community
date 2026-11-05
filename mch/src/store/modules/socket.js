import {
  Base64
} from 'js-base64';
var heartbeat=null;
export default {
  state: {
    socketTask: null,
    socketUrl: '',
    socketState: false,
    socketMessage: null,
  },
  mutations: {
    WEBSOCKET_INIT(state, url) {
      // 实例化socket

      state.socketTask = new WebSocket(url)
      // 监听socket连接
      state.socketTask.onopen = function(res) {
        state.socketState = true;
        state.socketUrl = url;
        if (!heartbeat) {
          heartbeat = setInterval(() => {
            let data = JSON.stringify({
              'command': 'HEARTBEAT'
            });
            data = Base64.encode(data);
            state.socketTask.send(data)
          }, 5000)
        }

        //收到消息
        state.socketTask.onmessage = function(res) {
          let data = JSON.parse(res.data);
          state.socketMessage = data.data;
        }
      }
      // 监听socket错误信息
      state.socketTask.onerror = function(e) {
        console.log(e)
        if (heartbeat) {
          clearInterval(heartbeat)
        }
      }
      state.socketTask.onclose = function(e) {
        //WebSocket连接已断开...
        // 注：只有连接正常打开中 ，才能正常收到消息
        state.socketState = false
        if (heartbeat) {
          clearInterval(heartbeat)
          heartbeat=null;
        }
        //断线了5s后重连
      }
    },
    WEBSOCKET_CONNECT(state) {
      // 实例化socket
      state.socketTask = new WebSocket(state.socketUrl)
      // 监听socket连接
      state.socketTask.onopen = function(res) {
        state.socketState = true;
        if (!heartbeat) {
          heartbeat = setInterval(() => {
            let data = JSON.stringify({
              'command': 'HEARTBEAT'
            });
            data = Base64.encode(data);
            state.socketTask.send(data)
          }, 5000)
        }

        //收到消息
        state.socketTask.onmessage = function(res) {
          let data = JSON.parse(res.data);
          state.socketMessage = data.data;
        }
      }
      // 监听socket错误信息
      state.socketTask.onerror = function(e) {
        if (heartbeat) {
          clearInterval(heartbeat)
        }
      }
      state.socketTask.onclose = function(e) {
        //WebSocket连接已断开...
        // 注：只有连接正常打开中 ，才能正常收到消息
        state.socketState = false
        if (heartbeat) {
          clearInterval(heartbeat);
           heartbeat=null;
        }
        //断线了5s后重连
      }
    },
    WEBSOCKET_SEND(state, data) {
      data = JSON.stringify(data);
      data = Base64.encode(data);
      state.socketTask.send(data)
    },
    CLOSE_SOCKET(state) {
      if (!state.socketTask) return
      state.socketTask.close()
    },
    TEST(state, data) {
      state.socketMessage = data;
    },
  },
  actions: {
    WEBSOCKET_INIT({
      commit
    }, url) {
      commit('WEBSOCKET_INIT', url)
    },
    WEBSOCKET_SEND({
      commit
    }, data) {
      commit('WEBSOCKET_SEND', data)
    },
    CLOSE_SOCKET({
      commit
    }) {
      commit('CLOSE_SOCKET')
    },
    WEBSOCKET_CONNECT({
      commit
    }, url) {
      commit('WEBSOCKET_CONNECT')
    },
    stest(context, data) {
      context.commit('TEST', data)
    },
  }
}
