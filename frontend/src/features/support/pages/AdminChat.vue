<template>
  <div class="admin-chat-layout">
    <!-- Left Sidebar: Session List -->
    <div class="chat-sidebar">
      <div class="sidebar-header">
        <h2 class="sidebar-title">Hộp thư đến</h2>
        <span class="chat-badge">{{ unreadTotal }} tin chưa đọc</span>
      </div>

      <div class="session-list" v-if="sessions.length > 0">
        <div 
          v-for="session in sessions" 
          :key="session.id" 
          class="session-item"
          :class="{ 'is-active': activeSession?.id === session.id, 'is-unread': session.unread_count > 0 }"
          @click="selectSession(session)"
        >
          <div class="session-avatar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span v-if="session.unread_count > 0" class="unread-badge">{{ session.unread_count }}</span>
          </div>
          <div class="session-info">
            <div class="session-header">
              <span class="session-name">
                {{ session.user ? session.user.full_name : 'Khách vãng lai (' + session.session_token.substring(0, 6) + ')' }}
              </span>
              <span class="session-time">{{ formatTime(session.last_message_at) }}</span>
            </div>
            <div class="session-status">
              <span class="status-dot" :class="session.status === 'open' ? 'bg-green-500' : 'bg-gray-400'"></span>
              <span>{{ session.status === 'open' ? 'Đang mở' : 'Đã đóng' }}</span>
            </div>
          </div>
        </div>
      </div>
      
      <div v-else class="empty-sessions">
        <p>Không có cuộc hội thoại nào.</p>
      </div>
    </div>

    <!-- Right Content: Chat Window -->
    <div class="chat-main" v-if="activeSession">
      <div class="chat-header">
        <div class="chat-user-info">
          <h3 class="chat-user-name">
            {{ activeSession.user ? activeSession.user.full_name : 'Khách vãng lai (' + activeSession.session_token.substring(0, 6) + ')' }}
          </h3>
          <p v-if="activeSession.user" class="chat-user-email">{{ activeSession.user.email }}</p>
        </div>
        <div class="chat-actions">
           <button class="btn btn-outline-danger btn-sm action-btn" @click="closeSession" v-if="activeSession.status === 'open'">
             Kết thúc hỗ trợ
           </button>
        </div>
      </div>

      <div class="chat-messages-container" ref="messagesContainer">
        <div v-for="(msg, index) in currentMessages" :key="index" class="msg-row" :class="{ 'msg-mine': msg.sender_type === 'admin' && msg.message !== 'SYSTEM_SESSION_CLOSED', 'msg-theirs': msg.sender_type === 'user', 'msg-system': msg.message === 'SYSTEM_SESSION_CLOSED' }">
          
          <div v-if="msg.message === 'SYSTEM_SESSION_CLOSED'" class="system-notice">
            <span class="system-icon">🔒</span>
            Phiên hỗ trợ đã kết thúc lúc {{ formatTime(msg.created_at) }}
          </div>
          
          <div v-else class="msg-bubble">
            <p>{{ msg.message }}</p>
            <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
          </div>
        </div>
      </div>

      <div class="chat-input-area" v-if="activeSession.status === 'open'">
        <input 
          v-model="replyText" 
          type="text" 
          placeholder="Nhập phản hồi..." 
          @keyup.enter="sendReply"
          :disabled="isSending"
        />
        <button @click="sendReply" :disabled="!replyText.trim() || isSending" class="btn-send">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>
      </div>
      <div class="chat-closed-notice" v-else>
         Hội thoại đã kết thúc.
      </div>
    </div>
    
    <div class="chat-main empty-state" v-else>
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
      </div>
      <h3>Chọn một cuộc hội thoại</h3>
      <p>Nhấp vào danh sách bên trái để bắt đầu trò chuyện</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const sessions = ref([]);
const activeSession = ref(null);
const currentMessages = ref([]);
const replyText = ref('');
const isSending = ref(false);
const messagesContainer = ref(null);
let isConnectedEcho = false;

const unreadTotal = computed(() => {
  return sessions.value.reduce((sum, s) => sum + (s.unread_count || 0), 0);
});

onMounted(() => {
  fetchSessions();
  setupEcho();
});

onUnmounted(() => {
  window.removeEventListener('admin-chat-message', handleAdminChatMessage);
});

const handleAdminChatMessage = (event) => {
  const e = event.detail;
  // Có tin nhắn mới từ bất kỳ ai
  const sessionId = e.message.chat_session_id;

  if (e.senderType === 'admin') {
     const existingSession = sessions.value.find(s => s.id === sessionId);
     if (existingSession) {
       existingSession.last_message_at = new Date().toISOString();
       sessions.value.sort((a,b) => new Date(b.last_message_at) - new Date(a.last_message_at));
     }

     if (activeSession.value && activeSession.value.id === sessionId) {
         const exists = currentMessages.value.some(m => m.id === e.message.id);
         const isPending = currentMessages.value.some(m => m._tempId && m.message === e.message.message);
         if (!exists && !isPending && e.message.id) {
             currentMessages.value.push(e.message);
             scrollToBottom();
         }
     }
     return;
  }

  // Tính năng: tin nhắn mới từ User
  const existingSession = sessions.value.find(s => s.id === sessionId);
  
  if (existingSession) {
    existingSession.last_message_at = new Date().toISOString();
    
    if (activeSession.value && activeSession.value.id === sessionId) {
       // Đang mở cuộc trò chuyện này, mark as read luôn phía Backend + đẩy tin nhắn vào UI
       currentMessages.value.push(e.message);
       scrollToBottom();
       api.get(`/admin/live-chats/${sessionId}`); // Gọi nhỏ lẻ để đánh dấu đã đọc
    } else {
       // Tăng số chưa đọc
       existingSession.unread_count = (existingSession.unread_count || 0) + 1;
    }
    
    sessions.value.sort((a,b) => new Date(b.last_message_at) - new Date(a.last_message_at));
  } else {
    // New session! Refresh session list
    fetchSessions();
  }
};

const setupEcho = () => {
  if (!isConnectedEcho) {
    window.addEventListener('admin-chat-message', handleAdminChatMessage);
    isConnectedEcho = true;
  }
};

const fetchSessions = async () => {
  try {
    const res = await api.get('/admin/live-chats');
    sessions.value = res.data;
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
  } catch (error) {
    console.error("Lỗi khi tải danh sách chat", error);
  }
};

const selectSession = async (session) => {
  activeSession.value = session;
  session.unread_count = 0; // Đánh dấu đã đọc trên UI
  try {
    const res = await api.get(`/admin/live-chats/${session.id}`);
    currentMessages.value = res.data.messages;
    scrollToBottom();
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
  } catch (error) {
    console.error(error);
  }
};

const sendReply = async () => {
  if (!replyText.value.trim() || isSending.value || !activeSession.value) return;
  
  const text = replyText.value;
  replyText.value = '';
  isSending.value = true;
  
  // Optimistic UI
  const tempMsg = { _tempId: Date.now(), message: text, sender_type: 'admin', created_at: new Date() };
  currentMessages.value.push(tempMsg);
  scrollToBottom();

  try {
    const res = await api.post(`/admin/live-chats/${activeSession.value.id}/reply`, {
      message: text
    });
    // Thay thế temp bằng real
    if (res.data.success) {
      const idx = currentMessages.value.findIndex(m => m._tempId === tempMsg._tempId);
      if (idx !== -1) {
        currentMessages.value[idx] = res.data.message;
      } else {
        currentMessages.value.push(res.data.message);
      }
      fetchSessions(); // Cập nhật danh sách last time
    }
  } catch (err) {
    console.error(err);
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể gửi!', icon: 'error', showConfirmButton: false, timer: 3000 });
    const idx = currentMessages.value.findIndex(m => m._tempId === tempMsg._tempId);
    if (idx !== -1) {
      currentMessages.value.splice(idx, 1);
    }
    replyText.value = text;
  } finally {
    isSending.value = false;
  }
};

const closeSession = async () => {
   const result = await Swal.fire({
     title: 'Xác nhận',
     text: 'Xác nhận kết thúc hỗ trợ khách hàng này?',
     icon: 'warning',
     showCancelButton: true,
     confirmButtonText: 'Kết thúc',
     cancelButtonText: 'Hủy'
   });
   
   if (result.isConfirmed) {
      try {
         await api.post(`/admin/live-chats/${activeSession.value.id}/close`);
         activeSession.value.status = 'closed';
         fetchSessions();
         Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Đã kết thúc phiên!', icon: 'success', showConfirmButton: false, timer: 3000 });
      } catch (e) {}
   }
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + ' ' + d.toLocaleDateString('vi-VN');
};
</script>

<style scoped>
.admin-chat-layout {
  display: flex;
  height: calc(100vh - 100px);
  background: var(--card-bg);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Sidebar */
.chat-sidebar {
  width: 320px;
  max-width: 40%;
  flex-shrink: 0;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  background: var(--surface-container);
  transition: width 0.3s;
}

.sidebar-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  background: var(--card-bg);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sidebar-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0;
  color: var(--text-main);
}

.chat-badge {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 12px;
  background: #ef4444;
  color: white;
  font-weight: 600;
}

.session-list {
  flex: 1;
  overflow-y: auto;
}

.empty-sessions {
  padding: 24px;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.session-item {
  display: flex;
  padding: 16px;
  border-bottom: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
}

.session-item:hover {
  background: var(--surface-container);
}

.session-item.is-active {
  background: #eff6ff;
  border-left: 3px solid #3b82f6;
}

.session-avatar {
  position: relative;
  width: 40px;
  height: 40px;
  background: #e2e8f0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  margin-right: 12px;
}

.unread-badge {
  position: absolute;
  top: -2px;
  right: -2px;
  background: #ef4444;
  color: white;
  font-size: 0.65rem;
  font-weight: bold;
  padding: 2px 5px;
  border-radius: 10px;
  border: 2px solid #fff;
}

.session-info {
  flex: 1;
  min-width: 0;
}

.session-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.session-name {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-main);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.session-time {
  font-size: 0.7rem;
  color: var(--text-light);
}

.session-status {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.bg-green-500 { background-color: #10b981; }
.bg-gray-400 { background-color: var(--text-light); }

/* Main Chat */
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--card-bg);
}

.empty-state {
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
}
.empty-icon { margin-bottom: 16px; opacity: 0.5; }

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  flex-wrap: wrap;
  gap: 12px;
}

.chat-user-name {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 2px 0;
  color: var(--text-main);
}

.chat-user-email {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 0;
}

.action-btn {
  padding: 6px 12px;
  font-size: 0.85rem;
  border: 1px solid #ef4444;
  color: #ef4444;
  background: var(--card-bg);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.action-btn:hover { background: #fee2e2; }

.chat-messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  background: var(--surface-container);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.msg-row {
  display: flex;
}

.msg-theirs { justify-content: flex-start; }
.msg-mine { justify-content: flex-end; }
.msg-system { justify-content: center; margin: 12px 0; }

.system-notice {
  background: var(--surface-container);
  color: var(--text-muted);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}
.system-icon { font-size: 0.9rem; }

.msg-bubble {
  max-width: 80%;
  padding: 12px 16px;
  border-radius: 12px;
  position: relative;
  font-size: 0.95rem;
  line-height: 1.5;
  word-break: break-word; /* Tránh tràn layout */
}

.msg-bubble p { margin: 0; }

.msg-theirs .msg-bubble {
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-bottom-left-radius: 2px;
  color: #1f2937;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.msg-mine .msg-bubble {
  background: #3b82f6;
  border-bottom-right-radius: 2px;
  color: #fff;
  box-shadow: 0 1px 2px rgba(59, 130, 246, 0.2);
}

.msg-time {
  display: block;
  font-size: 0.65rem;
  margin-top: 4px;
  opacity: 0.7;
  text-align: right;
}

.msg-theirs .msg-time { text-align: left; }

.chat-input-area {
  padding: 16px 24px;
  background: var(--card-bg);
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 12px;
}

.chat-input-area input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 24px;
  outline: none;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}

.chat-input-area input:focus {
  border-color: #3b82f6;
}

.btn-send {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: none;
  background: #3b82f6;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-send:hover:not(:disabled) {
  background: #2563eb;
}
.btn-send:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

.chat-closed-notice {
  padding: 16px;
  text-align: center;
  background: #f3f4f6;
  color: var(--text-muted);
  font-size: 0.9rem;
  border-top: 1px solid #e5e7eb;
}

/* Responsive */
@media (max-width: 992px) {
  .chat-sidebar {
    width: 260px;
    max-width: 35%;
  }
  .chat-header {
    padding: 12px 16px;
  }
}

@media (max-width: 768px) {
  .admin-chat-layout {
    flex-direction: column;
  }
  .chat-sidebar {
    width: 100%;
    max-width: 100%;
    height: 40%;
    border-right: none;
    border-bottom: 1px solid #e5e7eb;
  }
  .chat-main {
    height: 60%;
  }
  .msg-bubble {
    max-width: 90%;
  }
}
</style>
