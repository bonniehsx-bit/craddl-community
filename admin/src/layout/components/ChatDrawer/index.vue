<template>
  <el-drawer
    title="在线客服工作台 (接待中)"
    :visible.sync="visible"
    direction="rtl"
    size="80%"
    custom-class="chat-drawer"
    append-to-body
    :before-close="handleClose">

    <div class="im-layout" v-loading="!imConnected" element-loading-text="正在连接即时通讯引擎...">

      <!-- 左侧：会话列表 -->
      <div class="session-list">
        <div class="list-title">最近沟通 ({{ sessions.length }})</div>
        <div class="scroll-wrapper">
          <div
            v-for="sess in sessions"
            :key="sess.target_id"
            class="session-item"
            :class="{ active: currentTarget === sess.target_id, removable: sess.target_id !== 0 }"
            @click="selectSession(sess)"
          >
            <el-badge :value="sess.unread" :hidden="sess.unread === 0" class="badge">
              <el-avatar :src="sess.avatar" icon="el-icon-user-solid" size="medium"></el-avatar>
            </el-badge>
            <div class="session-info">
              <div class="session-header">
                <span class="name">{{ sess.nickname }}</span>
                <span class="time">{{ formatTimeDay(sess.last_time) }}</span>
              </div>
              <div class="last-msg text-ellipsis">{{ parseContent(sess.last_msg) }}</div>
            </div>
            <!-- 从列表移除(悬停才出现):只对自己隐藏,聊天记录不删,对方再发消息会重新出现。系统会话不给移除 -->
            <i v-if="sess.target_id !== 0" class="el-icon-close session-remove" title="从列表移除"
               @click.stop="removeSession(sess)"></i>
          </div>

          <el-empty v-if="sessions.length === 0 && imConnected" description="暂无客情消息" :image-size="60"></el-empty>
        </div>
      </div>

      <!-- 右侧：聊天窗口（target_id=0 是系统会话，不能用 truthy 判空） -->
      <div class="chat-window" v-if="currentTarget !== null">
        <!-- 聊天头部 -->
        <div class="chat-header flex-y-center">
          <span style="font-weight: bold; font-size: 16px;">{{ currentSession.nickname }}</span>
          <el-tag size="mini" type="info" style="margin-left: 10px;">ID: {{ currentTarget }}</el-tag>
        </div>

        <!-- 消息瀑布流 -->
        <div class="chat-messages" id="chat-box" ref="chatBox">
          <div v-for="msg in messages" :key="msg.id" class="msg-row" :class="{'msg-mine': msg.from_id == myUid}">

            <div class="msg-time" v-if="msg.showTime">{{ formatTimeFull(msg.created_at) }}</div>

            <div class="msg-content-wrapper">
              <template v-if="msg.from_id != myUid">
                <el-avatar :src="currentSession.avatar" icon="el-icon-user-solid" class="avatar" style="cursor: pointer;" @click.native="openUserEdit(msg.from_id)"></el-avatar>
              </template>

              <div class="msg-bubble">
                <div v-if="msg.msg_type === 'text'" class="msg-content">
                    <span>{{ msg.content.content }}</span>
                </div>
                <el-image
                  v-else-if="msg.msg_type === 'image'"
                  class="msg-image"
                  :src="msg.content.url || msg.content.image"
                  :preview-src-list="[msg.content.url || msg.content.image]"
                  style="max-width: 200px; border-radius: 6px;"
                  @load="scrollToBottom"
                ></el-image>
                <div v-else-if="['goods', 'order'].includes(msg.msg_type)" class="card-preview">
                  <el-image :src="msg.content.image || msg.content.pic_url" class="card-img" fit="cover">
                    <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                  </el-image>
                  <div class="card-info">
                    <div class="card-title" :title="msg.content.title">{{ msg.content.title || '商品详情' }}</div>
                    <div class="card-price">¥{{ msg.content.price || '0.00' }}</div>
                  </div>
                </div>
                <div v-else style="color:#999; font-size: 12px;">[{{ msg.msg_type }}类型卡片，请在手机端查看详细]</div>
              </div>

              <template v-if="msg.from_id == myUid">
                <el-avatar :src="myAvatar" icon="el-icon-user-solid" class="avatar"></el-avatar>
              </template>
            </div>

          </div>
          <div v-if="loadingMsgs" style="text-align: center; color: #999; font-size: 12px; padding: 10px;">加载中...</div>
        </div>

        <!-- 聊天输入框 -->
        <div class="chat-input-area">
          <div class="chat-toolbox">
            <!-- 传本地图片 -->
            <single-image-uploader
              v-if="currentTarget"
              accept="image/*"
              @success="onImageUploaded"
              style="display: inline-block; width: auto; min-height: auto; padding: 0; border: none; border-radius: 0;">
              <template #default="{ loading }">
                <i v-if="!loading" class="el-icon-upload2 toolbox-icon" title="上传本地图片"></i>
                <i v-else class="el-icon-loading toolbox-icon" style="color: var(--admin-accent-text); cursor: not-allowed;"></i>
              </template>
            </single-image-uploader>

            <!-- 从图库选择 -->
            <file-picker
              v-if="currentTarget"
              :multiple="false"
              :max="1"
              @selected="onGalleryImageSelected"
              style="display: inline-block;">
              <i class="el-icon-picture toolbox-icon" title="从云端图库选择"></i>
            </file-picker>
          </div>
          <el-input
            type="textarea"
            :rows="4"
            placeholder="请输入回复内容，按 Enter 键极速发送，Shift+Enter 换行"
            v-model="inputText"
            resize="none"
            class="msg-textarea"
            @keydown.native="handleKeyDown"
          />
          <div class="actions">
            <span style="color: #ccc; font-size: 12px; margin-right: 15px;">Enter发送 / Shift+Enter换行</span>
            <el-button type="primary" size="small" @click="sendMessage" :disabled="!inputText.trim()">发送</el-button>
          </div>
        </div>
      </div>

      <!-- 聊天区：空状态占位 (当未选择任何客情时显示) -->
      <div class="chat-window" style="display: flex; justify-content: center; align-items: center; background: #fafafa;" v-if="currentTarget === null">
        <el-empty description="即时通讯引擎已就绪，请点击左侧客户开始沟通" :image-size="150"></el-empty>
      </div>

      <!-- 右侧三：当前客户全息信息栏 -->
      <div class="user-sidebar" v-if="currentTarget && targetUserInfo" v-loading="loadingUserInfo">

        <div class="us-profile-banner">
          <div class="banner-bg"></div>
          <el-avatar :size="72" :src="targetUserInfo.avatar_url" class="us-avatar" style="cursor: pointer;" @click.native="openUserEdit()"></el-avatar>
          <div class="us-name">{{ targetUserInfo.nickname }}</div>
          <el-tag size="small" effect="dark" type="warning" class="us-tag" style="border-radius: 12px;">
            <i class="el-icon-medal-1"></i> {{ targetUserInfo.level_name || '普通客户' }}
          </el-tag>
        </div>

        <div class="us-scroll-body">
          <div class="us-section-card">
            <div class="sec-title">核心档案</div>
            <div class="info-row"><span class="label">客户 ID：</span><span class="value">{{ targetUserInfo.id }}</span></div>
            <div class="info-row"><span class="label">手机号码：</span><span class="value">{{ targetUserInfo.mobile || '未绑定 / 保护' }}</span></div>
            <div class="info-row"><span class="label">注册时间：</span><span class="value" style="font-size: 11px; color:#999">{{ targetUserInfo.created_time || '-' }}</span></div>
            <div class="info-row"><span class="label">最近登录：</span><span class="value" style="font-size: 11px; color:#999">{{ targetUserInfo.updated_time || '-' }}</span></div>
            <div class="info-row">
              <span class="label">推荐人：</span>
              <span class="value" style="display: flex; align-items: center; gap: 6px;">
                 <el-avatar v-if="targetUserInfo.parent_avatar_url" :size="20" :src="targetUserInfo.parent_avatar_url"></el-avatar>
                 {{ targetUserInfo.parent_nickname || '无 / 平台直属' }}
              </span>
            </div>
          </div>

          <div class="us-section-card">
            <div class="sec-title">资产引擎</div>
            <div class="asset-grid" style="flex-wrap: wrap; margin-bottom: -10px;">
              <div class="asset-item" style="width: calc(50% - 4px); margin: 0 0 10px 0;">
                <div class="v">¥{{ targetUserInfo.money || '0.00' }}</div>
                <div class="l">账户余额</div>
              </div>
              <div class="asset-item" style="width: calc(50% - 4px); margin: 0 0 10px 0;">
                <div class="v">{{ targetUserInfo.integral || '0' }}</div>
                <div class="l">消费积分</div>
              </div>
              <div class="asset-item" style="width: calc(50% - 4px); margin: 0 0 10px 0;">
                <div class="v">¥{{ targetUserInfo.price || '0.00' }}</div>
                <div class="l">累计佣金</div>
              </div>
              <div class="asset-item" style="width: calc(50% - 4px); margin: 0 0 10px 0;">
                <div class="v" style="color: #67C23A">{{ targetUserInfo.success_order_count || '0' }} <span style="font-size: 10px; font-weight: normal; color: #999">单</span></div>
                <div class="l">成交笔数</div>
              </div>
            </div>
          </div>

          <div class="us-action-area">
            <el-button
              type="primary"
              class="edit-btn shadow-btn"
              icon="el-icon-s-operation"
              @click="openUserEdit()"
              :disabled="targetUserInfo.level_name === '非平台注册客户'"
            >
              {{ targetUserInfo.level_name === '非平台注册客户' ? '无法提取非注册客资料' : '详细资料' }}
            </el-button>
          </div>
        </div>
      </div>

    </div>

    <!-- 接入强大的用户编辑抽屉 -->
    <user-edit-drawer :visible.sync="userEditVisible" :userId="editUserId"></user-edit-drawer>
  </el-drawer>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import request from '@/utils/request';
import SingleImageUploader from '@/components/mall/SingleImageUploader';
import UserEditDrawer from '@/views/user/UserEditDrawer.vue';

export default {
  name: 'ChatDrawer',
  components: {
    SingleImageUploader,
    UserEditDrawer
  },
  data() {
    return {
      visible: false,
      sessions: [],
      messages: [],
      currentTarget: null,
      currentSession: {},
      inputText: '',
      loadingMsgs: false,
      firstLoad: true,

      // 用户侧边栏及编辑抽屉状态
      targetUserInfo: null,
      loadingUserInfo: false,
      userEditVisible: false,
      editUserId: null
    }
  },
  computed: {
    ...mapState({
      latestMsg: state => state.im.latestMsg,
      imConnected: state => state.im.isConnected,
      myUid: state => state.im.imConfig ? state.im.imConfig.bind_uid : 0,
      myAvatar: state => state.user.avatar
    })
  },
  watch: {
    visible(val) {
      if (val && this.imConnected) {
        this.fetchSessions();
        if (this.currentTarget) {
          this.fetchUserInfo();
        }
      }
    },
    latestMsg(val) {
      this.imGlobalMessage(val);
    },
    userEditVisible(val) {
      if (!val && this.currentTarget) {
        // 当侧边用户抽屉关闭时，重新获取最新资料以展现可能的修改
        this.fetchUserInfo();
      }
    }
  },
  methods: {
    show() {
      this.visible = true;
    },
    handleClose(done) {
      // 当关闭通讯录时，释放当前焦点。
      // 否则当上一个客户发来新消息时，后台进程会错误地以为你在看他，瞬间将其秒变成了已读。
      this.currentTarget = null;
      this.currentSession = {};
      this.messages = [];
      done();
    },
    fetchSessions() {
      request({
        url: '/plugin/im/mall/chat/sessions',
        method: 'get'
      }).then(res => {
        if (res.code === 0) {
           // 系统会话（target_id=0）后端单独返回在 system_session，置顶合并进列表
           const list = Array.isArray(res.data.list) ? [...res.data.list] : [];
           if (res.data.system_session) list.unshift(res.data.system_session);
           this.sessions = list;
        }
      });
    },
    selectSession(sess) {
      this.currentTarget = sess.target_id;
      this.currentSession = sess;
      sess.unread = 0; // 点击时立即清除本地红点
      this.fetchHistory();
      this.fetchUserInfo();
    },
    fetchUserInfo() {
      if (!this.currentTarget) return;
      this.loadingUserInfo = true;
      request({
        url: '/mall/user/info',
        method: 'get',
        data: { user_id: this.currentTarget }
      }).then(res => {
        if (res.code === 0 && res.data && res.data.user) {
          this.targetUserInfo = res.data.user;
        } else {
          this.setFallbackUserInfo();
        }
      }).catch(err => {
        // 请求失败或报错时（例如用户不存在被拦截器抛出 reject），给个优雅的默认兜底
        this.setFallbackUserInfo();
      }).finally(() => {
        this.loadingUserInfo = false;
      });
    },
    setFallbackUserInfo() {
      this.targetUserInfo = {
        id: this.currentTarget,
        nickname: this.currentSession.nickname || '访客 / 未知用户',
        avatar_url: this.currentSession.avatar || '',
        level_name: '非平台注册客户',
        mobile: '-',
        created_time: '-',
        updated_time: '-',
        parent_nickname: '-',
        money: '0.00',
        integral: '0',
        price: '0.00',
        success_order_count: '0'
      };
    },
    openUserEdit(id) {
       let target = (typeof id === 'number' || typeof id === 'string') ? id : this.currentTarget;
       if (!target) return;
       this.editUserId = target;
       this.userEditVisible = true;
    },
    fetchHistory() {
      // 注意：target_id=0（系统会话）也是合法值，不能用 truthy 判空
      if (this.currentTarget === null || this.currentTarget === undefined) return;
      this.loadingMsgs = true;
      request({
        url: '/plugin/im/mall/chat/history',
        method: 'get',
        data: {
          target_id: this.currentTarget
        }
      }).then(res => {
        if (res.code === 0) {
          const rawList = res.data.list;
          this.messages = rawList.map(m => this.normalizeMsg(m));

          if (res.data.user_info && res.data.user_info[this.currentTarget]) {
            this.currentSession.avatar = res.data.user_info[this.currentTarget].avatar_url;
            this.currentSession.nickname = res.data.user_info[this.currentTarget].nickname;
          }

          this.$nextTick(() => {
            this.scrollToBottom();
          });
          this.markRead(this.currentTarget);
        }
      }).finally(() => {
        this.loadingMsgs = false;
      });
    },
    removeSession(sess) {
      this.$confirm(`从「最近沟通」里移除与「${sess.nickname}」的会话?聊天记录不会删除,对方再发消息时会重新出现。`, '移除会话', {
        confirmButtonText: '移除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => request({
        url: '/plugin/im/mall/chat/hide',
        method: 'post',
        data: { target_id: sess.target_id }
      })).then(res => {
        if (!res || res.code !== 0) {
          this.$message.error((res && res.msg) || '移除失败,请重试')
          return
        }
        const i = this.sessions.findIndex(s => s.target_id === sess.target_id)
        if (i > -1) this.sessions.splice(i, 1)
        if (this.currentTarget === sess.target_id) {
          this.currentTarget = null
          this.currentSession = {}
        }
        // 移除时后端顺手标了已读,刷新顶栏未读数
        this.$store.dispatch('im/fetchGlobalUnread')
        this.$message.success('已从列表移除')
      }).catch(() => {})
    },
    markRead(targetId) {
      request({
        url: '/plugin/im/mall/chat/read',
        method: 'post',
        data: { target_id: targetId }
      }).then(res => {
        if (res.code === 0) {
           this.$store.dispatch('im/fetchGlobalUnread');
        }
      });
    },
    generateUUID() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.random() * 16 | 0;
        const v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
      });
    },
    normalizeMsg(rawMsg) {
      const id = rawMsg.id || rawMsg._id || Math.random().toString(36).substr(2);
      const ts = rawMsg.created_at || rawMsg.timestamp || Date.now();

      const typeMatch = rawMsg.msg_type || rawMsg.type || 'text';
      let finalType = typeMatch;
      if (!['image', 'goods', 'order', 'account', 'coupon'].includes(typeMatch)) {
        finalType = 'text';
      }

      let rawContent = rawMsg.content || rawMsg.data;
      if (!rawContent) rawContent = {};

      if (typeof rawContent === 'string') {
        try {
          rawContent = JSON.parse(rawContent);
        } catch (e) {
          rawContent = { content: rawContent };
        }
      }

      let finalContent = {};

      if (finalType === 'image') {
        finalContent = {
          url: rawContent.url || rawContent.image || '',
          width: Number(rawContent.width) || 0,
          height: Number(rawContent.height) || 0
        };
      } else if (['goods', 'order', 'account', 'coupon'].includes(finalType)) {
        finalContent = rawContent;
      } else {
        finalContent = {
          content: rawContent.content || (typeof rawContent === 'string' ? rawContent : '')
        };
      }

      return {
        ...rawMsg,
        uniqueId: `${id}_${ts}`,
        msg_type: finalType,
        client_msg_id: rawMsg.client_msg_id || rawContent.client_msg_id || null,
        created_at: ts,
        content: finalContent,
        status: rawMsg.status || 'success'
      };
    },
    handleKeyDown(e) {
      if (e.keyCode === 13) {
        if (!e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      }
    },
    sendMessage() {
      if (!this.inputText.trim() || !this.currentTarget) return;
      const content = this.inputText.trim();
      const msgId = this.generateUUID();
      const now = Math.floor(Date.now() / 1000);

      const payload = {
        type: 'text',
        to_id: Number(this.currentTarget),
        data: {
          content: content,
          client_msg_id: msgId
        },
        timestamp: Date.now()
      };

      this.$store.dispatch('im/sendMessage', payload);

      const localRawMsg = {
        from_id: this.myUid,
        to_id: this.currentTarget,
        msg_type: 'text',
        client_msg_id: msgId,
        content: { content: content },
        created_at: now,
        is_read: 0,
        status: 'sending'
      };

      this.messages.push(this.normalizeMsg(localRawMsg));

      const sessIndex = this.sessions.findIndex(s => s.target_id == this.currentTarget);
      if (sessIndex > -1) {
          const sess = this.sessions[sessIndex];
          sess.last_msg = { type: 'text', content: content };
          sess.last_time = now;
          this.sessions.splice(sessIndex, 1);
          this.sessions.unshift(sess);
      } else {
          setTimeout(() => { this.fetchSessions(); }, 800);
      }

      this.inputText = '';
      this.scrollToBottom();
    },
    onImageUploaded(fileData) {
      if (!this.currentTarget) return;

      const imageUrl = fileData.url;
      const msgId = this.generateUUID();
      const now = Math.floor(Date.now() / 1000);

      const payload = {
        type: 'image',
        to_id: Number(this.currentTarget),
        data: {
          url: imageUrl,
          width: 0,
          height: 0,
          client_msg_id: msgId
        },
        timestamp: Date.now()
      };

      this.$store.dispatch('im/sendMessage', payload);

      const localRawMsg = {
        from_id: this.myUid,
        to_id: this.currentTarget,
        msg_type: 'image',
        client_msg_id: msgId,
        content: { url: imageUrl },
        created_at: now,
        is_read: 0,
        status: 'sending'
      };

      this.messages.push(this.normalizeMsg(localRawMsg));

      const sessIndex = this.sessions.findIndex(s => s.target_id == this.currentTarget);
      if (sessIndex > -1) {
          const sess = this.sessions[sessIndex];
          sess.last_msg = { type: 'image', content: '[图片]' };
          sess.last_time = now;
          this.sessions.splice(sessIndex, 1);
          this.sessions.unshift(sess);
      } else {
          setTimeout(() => { this.fetchSessions(); }, 800);
      }

      this.scrollToBottom();
    },
    onGalleryImageSelected(e) {
      if (!e || e.length === 0) return;
      const fileData = e[0];
      if (fileData && fileData.url) {
        this.onImageUploaded({ url: fileData.url });
      }
    },
    imGlobalMessage(val) {
      if (!val) return;

      this.fetchSessions();

      if (val.from_id == this.currentTarget || val.from_id == this.myUid) {
        let nMsg = this.normalizeMsg({
            ...val,
            msg_type: val.type,
            content: val.data,
            created_at: Math.floor(Date.now() / 1000)
        });

        let existing = this.messages.find(m => m.client_msg_id && m.client_msg_id === nMsg.client_msg_id);
        if (existing) {
           existing.status = 'success';
        } else {
           this.messages.push(nMsg);
           this.scrollToBottom();
           // 只有在屏幕真正打开、并且正好在和这个人聊天的时候，才向服务器发送“已阅”回执
           if (val.from_id == this.currentTarget && this.visible) {
             this.markRead(this.currentTarget);
           }
        }
      }
    },
    scrollToBottom() {
      this.$nextTick(() => {
        const box = this.$refs.chatBox;
        if (box) {
          box.scrollTop = box.scrollHeight;
        }
      });
    },
    parseContent(msgObj) {
      if (!msgObj) return '';
      if (typeof msgObj === 'string') {
          try {
              msgObj = JSON.parse(msgObj);
          } catch(e) {
              return msgObj;
          }
      }
      if (msgObj.type === 'image') return '[图片]';
      if (msgObj.type === 'goods') return '[商品链接]';
      if (msgObj.type === 'order') return '[订单信息]';
      if (msgObj.type === 'account' || msgObj.type === 'coupon') return '[账户优惠消息]';
      return msgObj.content || '[新消息]';
    },
    formatTimeDay(timestamp) {
        if (!timestamp) return '';
        const d = new Date(timestamp * 1000);
        return `${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    },
    formatTimeFull(timestamp) {
        if (!timestamp) return '';
        const d = new Date(timestamp * 1000);
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    }
  }
}
</script>

<style lang="scss">
.chat-drawer .el-drawer__header {
  margin-bottom: 0;
  padding: 15px 20px;
  border-bottom: 1px solid #EBEEF5;
  color: #303133;
  font-weight: bold;
}
.chat-drawer .el-drawer__body {
  padding: 0;
  overflow: hidden;
}
</style>

<style lang="scss" scoped>
.flex-y-center {
  display: flex;
  align-items: center;
}
.flex-center {
  display: flex;
  align-items: center;
  justify-content: center;
}
.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* 整体布局 */
.im-layout {
  display: flex;
  height: 100%;
  background-color: #f7f7f7;
}

/* 左侧：会话列表 */
.session-list {
  width: 250px;
  background-color: #fff;
  border-right: 1px solid #EBEEF5;
  display: flex;
  flex-direction: column;

  .list-title {
    padding: 12px 15px;
    font-size: 14px;
    font-weight: bold;
    color: #606266;
    border-bottom: 1px solid #f0f0f0;
  }

  .scroll-wrapper {
    flex: 1;
    overflow-y: auto;
  }

  .session-item {
    position: relative;
    display: flex;
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f9f9f9;
    transition: background 0.3s;

    &:hover {
      background-color: #f5f7fa;
    }

    &.active {
      background-color: var(--admin-accent-soft);
    }

    // 移除按钮:悬停才出现,盖在右上角时间上
    .session-remove {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 3px;
      border-radius: 50%;
      font-size: 12px;
      color: #909399;
      background: #fff;
      opacity: 0;
      transition: opacity 0.2s, background 0.2s, color 0.2s;

      &:hover { color: #fff; background: #f56c6c; }
    }

    &:hover .session-remove { opacity: 1; }
    // 悬停时时间让位给 ×,别叠在一起
    &.removable:hover .session-header .time { visibility: hidden; }

    .badge {
      margin-right: 12px;
    }

    .session-info {
      flex: 1;
      width: 0;

      .session-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 5px;

        .name {
          font-size: 14px;
          color: #333;
          font-weight: 500;
          max-width: 100px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .time {
          font-size: 11px;
          color: #999;
        }
      }

      .last-msg {
        font-size: 12px;
        color: #999;
      }
    }
  }
}

/* 右侧：聊天窗口 */
.chat-window {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;

  &.empty-box {
    background-color: #fff;
  }

  .chat-header {
    height: 50px;
    padding: 0 20px;
    background-color: #fff;
    border-bottom: 1px solid #EBEEF5;
  }

  .chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;

    .msg-time {
      text-align: center;
      margin: 15px 0;
      font-size: 12px;
      color: #bbb;
    }

    .msg-row {
      margin-bottom: 20px;

      .msg-content-wrapper {
        display: flex;
        align-items: flex-start;
      }

      .avatar {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
      }

      .msg-bubble {
        max-width: 70%;
        margin: 0 12px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 14px;
        line-height: 1.5;
        background-color: #fff;
        color: #333;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        word-break: break-word;
        white-space: pre-wrap;
      }

      /* 当是我发出的消息 */
      &.msg-mine {
        .msg-content-wrapper {
          justify-content: flex-end;
        }

        .msg-bubble {
          background-color: #95ec69; /* 微信绿 */
          color: #111;
        }
      }
    }
  }

  .chat-input-area {
    height: 180px;
    background-color: #fff;
    border-top: 1px solid #E4E7ED;
    display: flex;
    flex-direction: column;

    .chat-toolbox {
      height: 40px;
      padding: 0 15px;
      display: flex;
      align-items: center;

      .toolbox-icon {
        font-size: 20px;
        color: #606266;
        cursor: pointer;
        margin-right: 15px;

        &:hover {
          color: var(--admin-accent-text);
        }
      }
    }

    .msg-textarea {
      flex: 1;
      padding: 0 15px;

      ::v-deep .el-textarea__inner {
        border: none;
        resize: none;
        padding: 5px 0;
        box-shadow: none;

        &:focus {
          border: none;
          box-shadow: none;
        }
      }
    }

    .actions {
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding: 0 15px;
    }
  }
}

.card-preview {
    background: #ffffff;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #EBEEF5;
    width: 240px;
    display: flex;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);

    .card-img {
      width: 60px;
      height: 60px;
      border-radius: 4px;
      flex-shrink: 0;
      background: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ccc;
    }

    .card-info {
      flex: 1;
      margin-left: 10px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      height: 60px;

      .card-title {
          font-size: 13px;
          color: #333;
          line-height: 1.4;
          display: -webkit-box;
          -webkit-box-orient: vertical;
          -webkit-line-clamp: 2;
          overflow: hidden;
      }

      .card-price {
          color: #FF5000;
          font-weight: bold;
          font-size: 14px;
      }
    }
}

/* 右侧：用户侧边栏全新进阶架构 */
.user-sidebar {
  width: 320px;
  background-color: #f5f7fa;
  border-left: 1px solid #EBEEF5;
  display: flex;
  flex-direction: column;

  .us-profile-banner {
    position: relative;
    padding-top: 30px;
    padding-bottom: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #ffffff;
    border-bottom: 1px solid #EBEEF5;

    .banner-bg {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 60px;
      /* 极其高级的科技蓝到透明渐变 */
      background: linear-gradient(180deg, rgba(var(--admin-accent-rgb), 0.1) 0%, rgba(255,255,255,0) 100%);
      z-index: 0;
    }

    .us-avatar {
      position: relative;
      z-index: 1;
      border: 3px solid #ffffff;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
      margin-bottom: 15px;
      transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);

      &:hover {
        transform: translateY(-2px) scale(1.05);
      }
    }

    .us-name {
      position: relative;
      z-index: 1;
      font-size: 18px;
      font-weight: 800;
      color: #303133;
      margin-bottom: 10px;
    }

    .us-tag {
      position: relative;
      z-index: 1;
      padding: 0 12px;
    }
  }

  .us-scroll-body {
    padding: 15px;
    flex: 1;
    overflow-y: auto;

    .us-section-card {
      background: #ffffff;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 15px;
      box-shadow: 0 2px 12px 0 rgba(0,0,0,0.03);

      .sec-title {
        font-size: 14px;
        font-weight: bold;
        color: #303133;
        margin-bottom: 15px;
        display: flex;
        align-items: center;

        &::before {
          content: '';
          display: inline-block;
          width: 3px;
          height: 14px;
          background: var(--admin-primary);
          margin-right: 8px;
          border-radius: 2px;
        }
      }

      .info-row {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        font-size: 13px;

        &:last-child {
          margin-bottom: 0;
        }

        .label {
          color: #909399;
          width: 70px;
          flex-shrink: 0;
        }
        .value {
          color: #303133;
          font-weight: 500;
          flex: 1;
          word-break: break-all;
        }
      }

      .asset-grid {
        display: flex;
        justify-content: space-between;

        .asset-item {
          text-align: center;
          flex: 1;
          background: #fafafa;
          padding: 12px 0;
          border-radius: 6px;
          margin: 0 4px;
          border: 1px solid #f2f2f2;

          &:first-child { margin-left: 0; }
          &:last-child { margin-right: 0; }

          .v {
            font-size: 16px;
            font-weight: 800;
            color: #FF5000;
            margin-bottom: 6px;
            font-family: inherit; /* 保留数字字体特性 */
          }
          .l {
            font-size: 11px;
            color: #909399;
          }
        }
      }
    }

    .us-action-area {
      margin-top: 10px;

      .shadow-btn {
        width: 100%;
        border-radius: 8px;
        height: 48px;
        font-size: 15px;
        font-weight: bold;
        box-shadow: 0 6px 16px -4px rgba(var(--admin-accent-rgb), 0.4);
        transition: all 0.25s ease;

        &:hover:not(.is-disabled) {
          transform: translateY(-2px);
          box-shadow: 0 8px 20px -4px rgba(var(--admin-accent-rgb), 0.6);
        }

        &.is-disabled {
          box-shadow: none;
          transform: none;
        }
      }
    }
  }
}
</style>
