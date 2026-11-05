import request from '@/utils/request'
import { getMallId } from '@/utils/auth'
import Vue from 'vue'

const state = {
  socketTask: null,
  imConfig: null,
  imToken: '',
  configRequestId: 0,
  isConnected: false,
  heartbeatTimer: null,
  reconnectTimer: null,
  lockReconnect: false,
  reconnectAttempts: 0,
  globalUnread: 0,
  latestMsg: null
}

const mutations = {
  SET_IM_CONFIG: (state, config) => {
    state.imConfig = config;
  },
  SET_IM_TOKEN: (state, token) => {
    state.imToken = token;
  },
  SET_IS_CONNECTED: (state, connected) => {
    state.isConnected = connected;
  },
  SET_GLOBAL_UNREAD: (state, count) => {
    state.globalUnread = count;
  },
  SET_LATEST_MSG: (state, msg) => {
    state.latestMsg = Object.assign({}, msg, { _t: Date.now() });
  },
  CLEAR_TIMERS: (state) => {
    if (state.heartbeatTimer) clearInterval(state.heartbeatTimer);
    if (state.reconnectTimer) clearTimeout(state.reconnectTimer);
    state.heartbeatTimer = null;
    state.reconnectTimer = null;
    state.lockReconnect = false;
  }
}

const actions = {
  loadImConfig({ state, rootGetters, commit, dispatch }, mallId) {
    if (!rootGetters.has_im || !(mallId > 0) || mallId !== Number(getMallId())) return

    const requestId = ++state.configRequestId
    return request({
      url: '/plugin/im/mall/index/config',
      method: 'get'
    }).then(res => {
      // 切换商城后，忽略旧商城还未返回的配置。
      if (requestId !== state.configRequestId || mallId !== Number(getMallId())) return
      if (res.code === 0 && res.data.config) {
        const conf = res.data.config;
        commit('SET_IM_CONFIG', conf);
        if (conf.im_token && conf.ws_url) {
          commit('SET_IM_TOKEN', conf.im_token);
          dispatch('initIM');
          dispatch('fetchGlobalUnread');
        } else {
          dispatch('closeSocket');
        }
        return conf;
      }
      return Promise.reject(res);
    });
  },

  reset({ state, commit, dispatch }) {
    state.configRequestId++;
    dispatch('closeSocket');
    commit('SET_IM_CONFIG', null);
    commit('SET_IM_TOKEN', '');
    commit('SET_GLOBAL_UNREAD', 0);
    commit('SET_LATEST_MSG', null);
    state.reconnectAttempts = 0;
  },

  initIM({ state, commit, dispatch }) {
    if (!state.imConfig || !state.imToken || state.isConnected) return;
    dispatch('closeSocket');

    const { ws_url, app_appid, mall_id } = state.imConfig;
    const token = state.imToken;
    const url = `${ws_url}?appid=${app_appid}&token=${token}&mall_id=${mall_id}`;

    console.log(`[Admin IM] 开始连接... AppID: ${app_appid}, MallID: ${mall_id}`);

    const ws = new WebSocket(url);
    state.socketTask = ws;
    commit('SET_IS_CONNECTED', false);

    ws.onopen = () => {
      console.log('[Admin IM] WebSocket 已连接');
      commit('SET_IS_CONNECTED', true);
      state.lockReconnect = false;
      state.reconnectAttempts = 0;
      dispatch('startHeartbeat');
    };

    ws.onmessage = (event) => {
      dispatch('handleMessage', event.data);
    };

    ws.onerror = (error) => {
      console.error('[Admin IM] 连接发生错误', error);
      commit('SET_IS_CONNECTED', false);
      dispatch('reconnect');
    };

    ws.onclose = (event) => {
      console.log('[Admin IM] 连接已关闭', event);
      commit('SET_IS_CONNECTED', false);
      dispatch('reconnect');
    };
  },

  handleMessage({ state, commit, dispatch }, data) {
    try {
      const msg = JSON.parse(data);
      if (msg.type === 'pong') return;
      
      console.log('[Admin IM Msg] 收到新消息:', msg);
      commit('SET_LATEST_MSG', msg);

      if (msg.type === 'read_ack') {
        dispatch('fetchGlobalUnread');
      } else if (['text', 'image', 'voice', 'video', 'card', 'goods', 'system'].includes(msg.type)) {
        commit('SET_GLOBAL_UNREAD', state.globalUnread + 1);
        // Dispatch global event for UI updates
        Vue.prototype.$eventBus && Vue.prototype.$eventBus.$emit('IM_NEW_MSG', msg);
      }
    } catch (e) {
      console.error('[Admin IM Msg] JSON 解析失败', e);
    }
  },

  fetchGlobalUnread({ state, commit }) {
    const config = state.imConfig;
    if (!config || !state.imToken || Number(config.mall_id) !== Number(getMallId())) return;
    return request({
      url: '/plugin/im/mall/chat/unread',
      method: 'get'
    }).then(res => {
      if (res.code === 0 && state.imConfig === config && Number(config.mall_id) === Number(getMallId())) {
        commit('SET_GLOBAL_UNREAD', res.data || 0);
      }
    }).catch(e => {});
  },

  startHeartbeat({ state, dispatch }) {
    if (state.heartbeatTimer) clearInterval(state.heartbeatTimer);
    state.heartbeatTimer = setInterval(() => {
      if (state.isConnected && state.socketTask && state.socketTask.readyState === WebSocket.OPEN) {
        const pingMsg = JSON.stringify({ type: 'ping', content: 'ping' });
        try {
          state.socketTask.send(pingMsg);
        } catch (e) {
          dispatch('closeSocket');
        }
      }
    }, 30000);
  },

  reconnect({ state, dispatch }) {
    if (state.lockReconnect) return;
    if (state.reconnectAttempts >= 10) {
       console.error('[Admin IM] 重连上限，放弃');
       return;
    }
    state.lockReconnect = true;
    if (!state.imConfig || !state.imToken) {
      state.lockReconnect = false;
      return;
    }

    state.reconnectAttempts++;
    console.log(`[Admin IM] 尝试重连 ${state.reconnectAttempts}...`);
    
    if (state.reconnectTimer) clearTimeout(state.reconnectTimer);
    state.reconnectTimer = setTimeout(() => {
      state.lockReconnect = false;
      dispatch('initIM');
    }, 4000);
  },

  closeSocket({ state, commit }) {
    if (state.socketTask) {
      state.socketTask.onopen = null;
      state.socketTask.onmessage = null;
      state.socketTask.onerror = null;
      state.socketTask.onclose = null;
      try {
        state.socketTask.close();
      } catch (e) {}
      state.socketTask = null;
    }
    commit('SET_IS_CONNECTED', false);
    commit('CLEAR_TIMERS');
  },
  
  sendMessage({ state }, msgObj) {
      return new Promise((resolve, reject) => {
          if (state.isConnected && state.socketTask && state.socketTask.readyState === WebSocket.OPEN) {
              state.socketTask.send(JSON.stringify(msgObj));
              resolve();
          } else {
              reject(new Error('WebSocket is not connected'));
          }
      });
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
