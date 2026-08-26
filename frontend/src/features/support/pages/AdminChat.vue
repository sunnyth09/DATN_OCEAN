<template>
  <div class="admin-chat-layout">
    <!-- Left Sidebar: Session List -->
    <aside class="chat-sidebar">
      <!-- Sidebar Header -->
      <div class="sidebar-header">
        <div class="sidebar-title-row">
          <div class="title-with-badge">
            <h2 class="sidebar-title">Hộp thư đến</h2>
            <span class="active-count-tag" v-if="sessions.length > 0">{{ sessions.length }}</span>
          </div>
          <button class="btn-icon-refresh" @click="fetchSessions" title="Tải lại danh sách" :class="{ 'is-spinning': isRefreshing }">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
          </button>
        </div>

        <!-- Search Bar -->
        <div class="sidebar-search">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Tìm tên, email, mã phiên..." 
            class="search-input"
          />
          <button v-if="searchQuery" class="btn-clear-search" @click="searchQuery = ''">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <!-- Filter Tabs -->
        <div class="sidebar-filter-tabs">
          <button 
            type="button" 
            class="tab-btn" 
            :class="{ active: activeFilterTab === 'all' }"
            @click="activeFilterTab = 'all'"
          >
            Tất cả
          </button>
          <button 
            type="button" 
            class="tab-btn" 
            :class="{ active: activeFilterTab === 'unread' }"
            @click="activeFilterTab = 'unread'"
          >
            Chưa đọc
            <span v-if="unreadTotal > 0" class="tab-badge">{{ unreadTotal }}</span>
          </button>
          <button 
            type="button" 
            class="tab-btn" 
            :class="{ active: activeFilterTab === 'open' }"
            @click="activeFilterTab = 'open'"
          >
            Đang mở
          </button>
          <button 
            type="button" 
            class="tab-btn" 
            :class="{ active: activeFilterTab === 'closed' }"
            @click="activeFilterTab = 'closed'"
          >
            Đã đóng
          </button>
        </div>
      </div>

      <!-- Session List Stream -->
      <div class="session-list" v-if="filteredSessions.length > 0">
        <div 
          v-for="session in filteredSessions" 
          :key="session.id" 
          class="session-card"
          :class="{ 
            'is-active': activeSession?.id === session.id, 
            'is-unread': (session.unread_count || 0) > 0,
            'is-closed': session.status === 'closed'
          }"
          @click="selectSession(session)"
        >
          <!-- User Avatar with Status Indicator -->
          <div class="session-avatar-wrap">
            <div class="user-avatar" :style="{ background: getAvatarBg(session) }">
              {{ getAvatarInitials(session) }}
            </div>
            <span class="online-indicator" :class="session.status === 'open' ? 'is-online' : 'is-offline'"></span>
          </div>

          <!-- Session Metadata -->
          <div class="session-content">
            <div class="session-top-line">
              <span class="user-display-name">
                {{ getDisplayName(session) }}
              </span>
              <span class="session-timestamp">
                {{ formatRelativeTime(session.last_message_at) }}
              </span>
            </div>

            <div class="session-tags-line">
              <span class="user-type-tag" :class="session.user ? 'tag-member' : 'tag-guest'">
                {{ session.user ? 'Thành viên' : 'Khách vãng lai' }}
              </span>
              <span v-if="session.status === 'closed'" class="status-tag-closed">Đã đóng</span>
            </div>

            <div class="session-bottom-line">
              <p class="last-msg-snippet">
                {{ getLastMessagePreview(session) }}
              </p>
              <span v-if="(session.unread_count || 0) > 0" class="unread-pill">
                {{ session.unread_count }}
              </span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Empty Sessions State -->
      <div v-else class="empty-sessions-box">
        <div class="empty-icon-wrap">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
        </div>
        <p class="empty-title">Không tìm thấy hội thoại</p>
        <p class="empty-sub">{{ searchQuery ? 'Thử tìm kiếm với từ khóa khác' : 'Chưa có phiên hỗ trợ nào trong mục này' }}</p>
      </div>
    </aside>

    <!-- Right Content: Main Chat Area -->
    <main class="chat-main" v-if="activeSession">
      <!-- Chat Header -->
      <header class="chat-header">
        <div class="header-user-profile">
          <div class="header-avatar" :style="{ background: getAvatarBg(activeSession) }">
            {{ getAvatarInitials(activeSession) }}
          </div>
          <div class="header-user-meta">
            <div class="user-meta-top">
              <h3 class="active-user-name">{{ getDisplayName(activeSession) }}</h3>
              <span class="user-type-badge" :class="activeSession.user ? 'badge-member' : 'badge-guest'">
                {{ activeSession.user ? 'Thành viên' : 'Khách vãng lai' }}
              </span>
              <span class="active-status-badge" :class="activeSession.status === 'open' ? 'status-open' : 'status-closed'">
                <span class="status-dot"></span>
                {{ activeSession.status === 'open' ? 'Đang trực tuyến' : 'Đã kết thúc' }}
              </span>
            </div>
            <div class="user-meta-bottom">
              <span v-if="activeSession.user?.email" class="meta-email">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                {{ activeSession.user.email }}
              </span>
              <span class="meta-token" :title="'Mã phiên: ' + activeSession.session_token">
                ID: {{ activeSession.session_token.substring(0, 8) }}...
              </span>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button 
            type="button" 
            class="btn-header-refresh" 
            @click="reloadActiveMessages" 
            title="Tải lại tin nhắn"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
          </button>
          <button 
            type="button"
            class="btn-close-session" 
            @click="closeSession" 
            v-if="activeSession.status === 'open'"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            Kết thúc hỗ trợ
          </button>
        </div>
      </header>

      <!-- Message History Stream -->
      <div class="chat-messages-container" ref="messagesContainer">
        <!-- Date Groups -->
        <template v-for="(group, gIdx) in groupedMessages" :key="gIdx">
          <div class="date-divider">
            <span class="date-pill">{{ group.dateLabel }}</span>
          </div>

          <div 
            v-for="(msg, index) in group.messages" 
            :key="msg.id || msg._tempId || index" 
            class="msg-row" 
            :class="{ 
              'msg-mine': msg.sender_type === 'admin' && msg.message !== 'SYSTEM_SESSION_CLOSED', 
              'msg-theirs': msg.sender_type === 'user', 
              'msg-system': msg.message === 'SYSTEM_SESSION_CLOSED' 
            }"
          >
            <!-- System Notice for Closed Sessions -->
            <div v-if="msg.message === 'SYSTEM_SESSION_CLOSED'" class="system-notice-card">
              <span class="system-icon">🔒</span>
              <span>Phiên hỗ trợ đã kết thúc lúc {{ formatTime(msg.created_at) }}</span>
            </div>
            
            <!-- Standard Chat Bubble -->
            <div v-else class="msg-bubble-wrap">
              <div class="msg-bubble">
                <p class="msg-content">{{ msg.message }}</p>
                <div class="msg-meta-row">
                  <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                  <span v-if="msg.sender_type === 'admin'" class="msg-status-icon">
                    <svg v-if="msg._tempId" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="sending-spinner">
                      <circle cx="12" cy="12" r="10" stroke-opacity="0.3"></circle>
                      <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                    </svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Bottom Chat Area -->
      <footer class="chat-footer-wrap" v-if="activeSession.status === 'open'">
        <!-- Quick Reply Chips -->
        <div class="quick-replies-bar">
          <div class="quick-replies-label">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
            </svg>
            Mẫu trả lời nhanh:
          </div>
          <div class="quick-replies-scroll">
            <button 
              v-for="(qr, qIdx) in quickReplies" 
              :key="qIdx" 
              type="button" 
              class="quick-reply-chip"
              @click="insertQuickReply(qr.text)"
            >
              {{ qr.label }}
            </button>
          </div>
        </div>

        <!-- Chat Input Form -->
        <div class="chat-input-area">
          <input 
            ref="replyInput"
            v-model="replyText" 
            type="text" 
            placeholder="Nhập phản hồi cho khách hàng..." 
            @keydown.enter="sendReply"
            autocomplete="off"
            class="main-chat-input"
          />
          <button 
            type="button" 
            @click="sendReply" 
            :disabled="!replyText.trim() || isSending" 
            class="btn-send-message"
            title="Gửi tin nhắn (Enter)"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </div>
      </footer>

      <!-- Closed Session Notice -->
      <footer class="chat-closed-footer" v-else>
        <div class="closed-notice-inner">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>Phiên hỗ trợ này đã kết thúc. Để tiếp tục, khách hàng cần mở phiên hỗ trợ mới.</span>
        </div>
      </footer>
    </main>
    
    <!-- Empty Active State -->
    <main class="chat-main empty-state-view" v-else>
      <div class="empty-state-card">
        <div class="empty-illustration">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            <circle cx="9" cy="10" r="1" fill="#E63B6F"></circle>
            <circle cx="12" cy="10" r="1" fill="#E63B6F"></circle>
            <circle cx="15" cy="10" r="1" fill="#E63B6F"></circle>
          </svg>
        </div>
        <h3 class="empty-title-main">Trung tâm Hỗ trợ Khách hàng Trực tuyến</h3>
        <p class="empty-desc-main">Chọn một cuộc hội thoại từ danh sách bên trái để bắt đầu hỗ trợ và chăm sóc khách hàng.</p>
        <div class="empty-tips-row">
          <span class="tip-item">⚡ Phản hồi nhanh với phím Enter</span>
          <span class="tip-item">🏷️ Sử dụng mẫu tin nhắn gợi ý</span>
          <span class="tip-item">🔒 Kết thúc phiên khi đã giải đáp xong</span>
        </div>
      </div>
    </main>
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
const isRefreshing = ref(false);
const searchQuery = ref('');
const activeFilterTab = ref('all');
const messagesContainer = ref(null);
const replyInput = ref(null);
let isConnectedEcho = false;

// Quick reply templates
const quickReplies = [
  { label: '👋 Chào khách', text: 'Chào bạn! Ocean Sport có thể hỗ trợ gì cho bạn hôm nay ạ?' },
  { label: '📦 Tra cứu đơn', text: 'Bạn vui lòng cung cấp mã đơn hàng để mình kiểm tra tiến độ giao hàng cho bạn nhé!' },
  { label: '💳 Thanh toán', text: 'Ocean Sport hỗ trợ thanh toán qua VietQR, VNPAY, Ví Ocean và thanh toán khi nhận hàng (COD) ạ.' },
  { label: '🔄 Đổi size & mẫu', text: 'Sản phẩm bên mình hỗ trợ đổi size/mẫu trong vòng 7 ngày và bảo hành chính hãng ạ.' },
  { label: '🙏 Cảm ơn', text: 'Cảm ơn bạn đã liên hệ Ocean Sport! Chúc bạn một ngày tốt lành và nhiều niềm vui ạ.' }
];

const insertQuickReply = (text) => {
  replyText.value = text;
  nextTick(() => {
    replyInput.value?.focus();
  });
};

const unreadTotal = computed(() => {
  return sessions.value.reduce((sum, s) => sum + (s.unread_count || 0), 0);
});

// Filter & Search
const filteredSessions = computed(() => {
  let list = sessions.value;

  // Filter tab
  if (activeFilterTab.value === 'unread') {
    list = list.filter(s => (s.unread_count || 0) > 0);
  } else if (activeFilterTab.value === 'open') {
    list = list.filter(s => s.status === 'open');
  } else if (activeFilterTab.value === 'closed') {
    list = list.filter(s => s.status === 'closed');
  }

  // Search query
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    list = list.filter(s => {
      const name = (s.user?.full_name || '').toLowerCase();
      const email = (s.user?.email || '').toLowerCase();
      const token = (s.session_token || '').toLowerCase();
      return name.includes(q) || email.includes(q) || token.includes(q);
    });
  }

  return list;
});

// Group messages by date for date dividers
const groupedMessages = computed(() => {
  const groups = [];
  let currentDate = '';
  let currentGroup = null;

  currentMessages.value.forEach(msg => {
    const d = new Date(msg.created_at);
    const dateStr = isNaN(d.getTime()) ? 'unknown' : d.toDateString();

    if (dateStr !== currentDate) {
      currentDate = dateStr;
      currentGroup = {
        dateLabel: formatDateLabel(msg.created_at),
        messages: []
      };
      groups.push(currentGroup);
    }

    currentGroup.messages.push(msg);
  });

  return groups;
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
  if (!e || !e.message) return;
  const sessionId = e.message.chat_session_id;

  // Cập nhật session list
  const existingSession = sessions.value.find(s => s.id === sessionId);
  if (existingSession) {
    existingSession.last_message_at = new Date().toISOString();
    if (e.senderType === 'user' && (!activeSession.value || activeSession.value.id !== sessionId)) {
      existingSession.unread_count = (existingSession.unread_count || 0) + 1;
    }
    sessions.value.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
  } else {
    fetchSessions();
  }

  // Nếu đang mở đúng cuộc trò chuyện này
  if (activeSession.value && activeSession.value.id === sessionId) {
    const msgId = e.message.id;

    // 1. Kiểm tra xem tin nhắn với ID này đã có trong danh sách chưa
    if (msgId) {
      const existsIdx = currentMessages.value.findIndex(m => m.id && String(m.id) === String(msgId));
      if (existsIdx !== -1) {
        currentMessages.value[existsIdx] = { ...currentMessages.value[existsIdx], ...e.message };
        return;
      }
    }

    // 2. Nếu là tin nhắn của Admin và có optimistic temp message đang chờ -> Thay thế tempMsg
    if (e.senderType === 'admin') {
      const pendingIdx = currentMessages.value.findIndex(m => m._tempId && m.message === e.message.message);
      if (pendingIdx !== -1) {
        currentMessages.value[pendingIdx] = e.message;
        scrollToBottom();
        return;
      }
    }

    // 3. Tin nhắn hợp lệ mới -> Push vào danh sách
    currentMessages.value.push(e.message);
    scrollToBottom();

    // Nếu là tin nhắn từ user -> Gọi đánh dấu đã đọc
    if (e.senderType === 'user') {
      api.get(`/admin/live-chats/${sessionId}`).catch(() => {});
    }
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
    isRefreshing.value = true;
    const res = await api.get('/admin/live-chats');
    sessions.value = res.data || [];
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
  } catch (error) {
    console.error("Lỗi khi tải danh sách chat", error);
  } finally {
    isRefreshing.value = false;
  }
};

const selectSession = async (session) => {
  activeSession.value = session;
  session.unread_count = 0; // Đánh dấu đã đọc trên UI
  try {
    const res = await api.get(`/admin/live-chats/${session.id}`);
    currentMessages.value = res.data.messages || [];
    scrollToBottom();
    window.dispatchEvent(new CustomEvent('update-sidebar-badges'));
    nextTick(() => {
      replyInput.value?.focus();
    });
  } catch (error) {
    console.error(error);
  }
};

const reloadActiveMessages = async () => {
  if (!activeSession.value) return;
  try {
    const res = await api.get(`/admin/live-chats/${activeSession.value.id}`);
    currentMessages.value = res.data.messages || [];
    scrollToBottom();
  } catch (e) {
    console.error(e);
  }
};

const sendReply = async () => {
  if (!replyText.value.trim() || isSending.value || !activeSession.value) return;
  
  const text = replyText.value.trim();
  replyText.value = '';
  isSending.value = true;
  
  // Giữ focus ngay lập tức
  nextTick(() => {
    replyInput.value?.focus();
  });
  
  // Optimistic UI với unique tempId
  const tempId = Date.now() + '_' + Math.random().toString(36).substr(2, 6);
  const tempMsg = { _tempId: tempId, message: text, sender_type: 'admin', created_at: new Date().toISOString() };
  currentMessages.value.push(tempMsg);
  scrollToBottom();

  try {
    const res = await api.post(`/admin/live-chats/${activeSession.value.id}/reply`, {
      message: text
    });
    if (res.data.success && res.data.message) {
      const realMsg = res.data.message;
      const tempIdx = currentMessages.value.findIndex(m => m._tempId === tempId);
      const realIdx = currentMessages.value.findIndex(m => m.id && String(m.id) === String(realMsg.id));

      if (tempIdx !== -1) {
        if (realIdx !== -1 && realIdx !== tempIdx) {
          currentMessages.value.splice(tempIdx, 1);
        } else {
          currentMessages.value[tempIdx] = realMsg;
        }
      } else if (realIdx === -1) {
        currentMessages.value.push(realMsg);
      }
      fetchSessions();
    }
  } catch (err) {
    console.error(err);
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể gửi!', icon: 'error', showConfirmButton: false, timer: 3000 });
    const idx = currentMessages.value.findIndex(m => m._tempId === tempId);
    if (idx !== -1) {
      currentMessages.value.splice(idx, 1);
    }
    replyText.value = text;
  } finally {
    isSending.value = false;
    nextTick(() => {
      replyInput.value?.focus();
    });
  }
};

const closeSession = async () => {
   const result = await Swal.fire({
     title: 'Kết thúc hỗ trợ',
     text: 'Bạn có chắc chắn muốn đóng phiên hỗ trợ khách hàng này?',
     icon: 'question',
     showCancelButton: true,
     confirmButtonColor: '#E63B6F',
     cancelButtonColor: '#64748b',
     confirmButtonText: 'Đồng ý kết thúc',
     cancelButtonText: 'Hủy'
   });
   
   if (result.isConfirmed) {
      try {
         await api.post(`/admin/live-chats/${activeSession.value.id}/close`);
         activeSession.value.status = 'closed';
         fetchSessions();
         Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: 'Đã kết thúc phiên hỗ trợ!', icon: 'success', showConfirmButton: false, timer: 2500 });
      } catch (e) {
        Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: 'Không thể đóng phiên!', icon: 'error', showConfirmButton: false, timer: 2500 });
      }
   }
};

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
};

// UI Helpers
const getDisplayName = (session) => {
  if (!session) return 'Khách hàng';
  if (session.user?.full_name) return session.user.full_name;
  return `Khách vãng lai (${session.session_token ? session.session_token.substring(0, 6) : 'Guest'})`;
};

const getAvatarInitials = (session) => {
  if (!session) return 'K';
  if (session.user?.full_name) {
    const parts = session.user.full_name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return session.user.full_name.substring(0, 2).toUpperCase();
  }
  return 'KV';
};

const getAvatarBg = (session) => {
  if (!session) return 'linear-gradient(135deg, #64748b, #475569)';
  if (session.user) {
    const gradients = [
      'linear-gradient(135deg, #3b82f6, #1d4ed8)',
      'linear-gradient(135deg, #8b5cf6, #6d28d9)',
      'linear-gradient(135deg, #06b6d4, #0891b2)',
      'linear-gradient(135deg, #10b981, #059669)',
      'linear-gradient(135deg, #f59e0b, #d97706)',
      'linear-gradient(135deg, #ec4899, #be185d)',
    ];
    const hash = (session.user.full_name || '').split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return gradients[hash % gradients.length];
  }
  return 'linear-gradient(135deg, #94a3b8, #64748b)';
};

const getLastMessagePreview = (session) => {
  if (!session) return 'Chưa có tin nhắn';
  return 'Bấm để mở cuộc trò chuyện';
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const formatRelativeTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  if (isNaN(d.getTime())) return '';
  
  const now = new Date();
  const diffMs = now - d;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);

  if (diffMins < 1) return 'Vừa xong';
  if (diffMins < 60) return `${diffMins}p trước`;
  if (diffHours < 24 && d.getDate() === now.getDate()) {
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
};

const formatDateLabel = (isoString) => {
  if (!isoString) return 'Hôm nay';
  const d = new Date(isoString);
  if (isNaN(d.getTime())) return 'Hôm nay';
  
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const msgDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diffDays = Math.round((today - msgDate) / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return 'Hôm nay';
  if (diffDays === 1) return 'Hôm qua';
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<style scoped>
/* Main Container */
.admin-chat-layout {
  display: flex;
  width: 100%;
  height: calc(100vh - 85px);
  background: #ffffff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
  border: 1px solid #eef2f6;
  font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* ==================== LEFT SIDEBAR ==================== */
.chat-sidebar {
  width: 360px;
  max-width: 35%;
  flex-shrink: 0;
  border-right: 1px solid #eef2f6;
  display: flex;
  flex-direction: column;
  background: #f8fafc;
}

.sidebar-header {
  padding: 16px 18px 12px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sidebar-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sidebar-title {
  font-size: 1.15rem;
  font-weight: 800;
  margin: 0;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.active-count-tag {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #64748b;
}

.btn-icon-refresh {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-icon-refresh:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

.btn-icon-refresh.is-spinning svg {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Search Bar */
.sidebar-search {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 9px 32px 9px 36px;
  background: #f1f5f9;
  border: 1px solid transparent;
  border-radius: 10px;
  font-size: 0.85rem;
  color: #0f172a;
  outline: none;
  transition: all 0.2s ease;
  font-family: inherit;
}

.search-input:focus {
  background: #ffffff;
  border-color: #E63B6F;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.12);
}

.search-input::placeholder {
  color: #94a3b8;
}

.btn-clear-search {
  position: absolute;
  right: 10px;
  background: #cbd5e1;
  border: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  cursor: pointer;
}

/* Filter Tabs */
.sidebar-filter-tabs {
  display: flex;
  gap: 4px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
}

.tab-btn {
  flex: 1;
  padding: 6px 4px;
  border: none;
  background: transparent;
  font-size: 0.76rem;
  font-weight: 600;
  color: #64748b;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-family: inherit;
  white-space: nowrap;
}

.tab-btn:hover {
  color: #0f172a;
}

.tab-btn.active {
  background: #ffffff;
  color: #E63B6F;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.tab-badge {
  background: #ef4444;
  color: #ffffff;
  font-size: 0.65rem;
  padding: 1px 5px;
  border-radius: 10px;
  font-weight: 700;
}

/* Session List Items */
.session-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.session-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.session-card:hover {
  background: #f1f5f9;
}

.session-card.is-active {
  background: #fff0f5;
  border-color: #fbcfe8;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.08);
}

.session-card.is-closed {
  opacity: 0.75;
}

/* Avatar & Status */
.session-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.user-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.95rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

.online-indicator {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #ffffff;
}

.online-indicator.is-online {
  background: #10b981;
}

.online-indicator.is-offline {
  background: #94a3b8;
}

/* Content */
.session-content {
  flex: 1;
  min-width: 0;
}

.session-top-line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 2px;
}

.user-display-name {
  font-weight: 700;
  font-size: 0.88rem;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.session-card.is-active .user-display-name {
  color: #E63B6F;
}

.session-timestamp {
  font-size: 0.7rem;
  color: #94a3b8;
  flex-shrink: 0;
  margin-left: 6px;
  font-weight: 500;
}

.session-tags-line {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.user-type-tag {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
}

.tag-member {
  background: #eff6ff;
  color: #2563eb;
}

.tag-guest {
  background: #f1f5f9;
  color: #64748b;
}

.status-tag-closed {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  background: #fee2e2;
  color: #dc2626;
}

.session-bottom-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.last-msg-snippet {
  font-size: 0.78rem;
  color: #64748b;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.session-card.is-unread .last-msg-snippet {
  color: #0f172a;
  font-weight: 600;
}

.unread-pill {
  background: #E63B6F;
  color: #ffffff;
  font-size: 0.68rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 10px;
  margin-left: 6px;
  flex-shrink: 0;
  animation: pulseBadge 2s infinite;
}

@keyframes pulseBadge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}

.empty-sessions-box {
  padding: 40px 20px;
  text-align: center;
  color: #64748b;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.empty-icon-wrap {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  margin-bottom: 12px;
}

.empty-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #334155;
  margin: 0 0 4px;
}

.empty-sub {
  font-size: 0.8rem;
  color: #94a3b8;
  margin: 0;
}

/* ==================== RIGHT MAIN CHAT ==================== */
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  min-width: 0;
}

/* Header */
.chat-header {
  padding: 14px 24px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.header-user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.header-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 1rem;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.header-user-meta {
  min-width: 0;
}

.user-meta-top {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 2px;
}

.active-user-name {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.01em;
}

.user-type-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.badge-member {
  background: #eff6ff;
  color: #2563eb;
}

.badge-guest {
  background: #f1f5f9;
  color: #64748b;
}

.active-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 12px;
}

.active-status-badge.status-open {
  background: #ecfdf5;
  color: #059669;
}

.active-status-badge.status-open .status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
}

.active-status-badge.status-closed {
  background: #f1f5f9;
  color: #64748b;
}

.active-status-badge.status-closed .status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #94a3b8;
}

.user-meta-bottom {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.76rem;
  color: #64748b;
}

.meta-email {
  display: flex;
  align-items: center;
  gap: 4px;
}

.meta-token {
  color: #94a3b8;
  font-family: monospace;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.btn-header-refresh {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-header-refresh:hover {
  background: #f8fafc;
  color: #0f172a;
}

.btn-close-session {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  border: 1px solid #fecaca;
  color: #dc2626;
  background: #fff5f5;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.btn-close-session:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}

/* ==================== MESSAGES CONTAINER ==================== */
.chat-messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.date-divider {
  display: flex;
  justify-content: center;
  margin: 10px 0;
}

.date-pill {
  font-size: 0.72rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #64748b;
  padding: 3px 12px;
  border-radius: 20px;
  letter-spacing: 0.02em;
}

.msg-row {
  display: flex;
  width: 100%;
}

.msg-theirs {
  justify-content: flex-start;
}

.msg-mine {
  justify-content: flex-end;
}

.msg-system {
  justify-content: center;
  margin: 8px 0;
}

/* System Notice Card */
.system-notice-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #64748b;
  padding: 8px 18px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Message Bubble */
.msg-bubble-wrap {
  max-width: 72%;
}

.msg-bubble {
  padding: 12px 16px;
  font-size: 0.92rem;
  line-height: 1.5;
  word-break: break-word;
  position: relative;
}

.msg-content {
  margin: 0;
  white-space: pre-wrap;
}

.msg-meta-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  margin-top: 4px;
}

.msg-time {
  font-size: 0.68rem;
  opacity: 0.75;
}

/* Theirs Bubble (User) */
.msg-theirs .msg-bubble {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px 18px 18px 4px;
  color: #0f172a;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.msg-theirs .msg-meta-row {
  justify-content: flex-start;
}

/* Mine Bubble (Admin) */
.msg-mine .msg-bubble {
  background: linear-gradient(135deg, #E63B6F, #ff5c8a);
  border-radius: 18px 18px 4px 18px;
  color: #ffffff;
  box-shadow: 0 3px 12px rgba(230, 59, 111, 0.25);
}

.msg-mine .msg-time {
  color: rgba(255, 255, 255, 0.9);
}

.msg-status-icon {
  display: inline-flex;
  align-items: center;
  color: rgba(255, 255, 255, 0.9);
}

.sending-spinner {
  animation: spin 1s linear infinite;
}

/* ==================== FOOTER & INPUT ==================== */
.chat-footer-wrap {
  background: #ffffff;
  border-top: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
}

/* Quick Replies */
.quick-replies-bar {
  padding: 10px 20px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.quick-replies-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.74rem;
  font-weight: 700;
  color: #94a3b8;
  white-space: nowrap;
  flex-shrink: 0;
}

.quick-replies-scroll {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 6px;
  scrollbar-width: thin;
}

.quick-reply-chip {
  flex-shrink: 0;
  padding: 5px 11px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  font-size: 0.76rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
  white-space: nowrap;
}

.quick-reply-chip:hover {
  background: #fff0f5;
  border-color: #fbcfe8;
  color: #E63B6F;
  transform: translateY(-1px);
}

/* Main Input Area */
.chat-input-area {
  padding: 12px 20px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.main-chat-input {
  flex: 1;
  padding: 12px 18px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  outline: none;
  font-size: 0.92rem;
  color: #0f172a;
  transition: all 0.2s ease;
  font-family: inherit;
}

.main-chat-input:focus {
  background: #ffffff;
  border-color: #E63B6F;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.12);
}

.main-chat-input::placeholder {
  color: #94a3b8;
  font-size: 0.88rem;
}

.btn-send-message {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: none;
  background: linear-gradient(135deg, #E63B6F, #ff5c8a);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.28);
}

.btn-send-message:hover:not(:disabled) {
  transform: scale(1.05);
  box-shadow: 0 6px 16px rgba(230, 59, 111, 0.38);
}

.btn-send-message:disabled {
  background: #cbd5e1;
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.65;
}

/* Closed State Footer */
.chat-closed-footer {
  padding: 18px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.closed-notice-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #64748b;
  font-size: 0.85rem;
  font-weight: 500;
}

/* ==================== EMPTY STATE VIEW ==================== */
.empty-state-view {
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.empty-state-card {
  max-width: 480px;
  text-align: center;
  padding: 40px 30px;
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  border: 1px solid #eef2f6;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.empty-illustration {
  width: 90px;
  height: 90px;
  border-radius: 24px;
  background: #fff0f5;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.empty-title-main {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.empty-desc-main {
  font-size: 0.88rem;
  color: #64748b;
  line-height: 1.5;
  margin: 0 0 24px;
}

.empty-tips-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.tip-item {
  font-size: 0.78rem;
  color: #475569;
  background: #f8fafc;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
  text-align: left;
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 1024px) {
  .chat-sidebar {
    width: 300px;
    max-width: 38%;
  }
}

@media (max-width: 768px) {
  .admin-chat-layout {
    flex-direction: column;
    height: calc(100vh - 80px);
  }
  .chat-sidebar {
    width: 100%;
    max-width: 100%;
    height: 45%;
    border-right: none;
    border-bottom: 1px solid #eef2f6;
  }
  .chat-main {
    height: 55%;
  }
  .msg-bubble-wrap {
    max-width: 88%;
  }
}
</style>
