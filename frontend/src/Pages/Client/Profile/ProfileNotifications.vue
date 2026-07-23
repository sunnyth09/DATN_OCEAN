<template>
  <div class="profile-card">
    <div class="profile-card-header flex-header">
      <h2 class="profile-card-title">Thông báo của bạn</h2>
      <button v-if="unreadCount > 0" class="btn-mark-all" @click="markAllAsRead" :disabled="isMarking">
        Đánh dấu tất cả đã đọc
      </button>
    </div>
    
    <div class="profile-card-body p-0">
      <div v-if="loading" class="notifications-loading">
        <div class="spinner"></div>
      </div>
      
      <div v-else-if="notifications.length === 0" class="empty-state">
        <svg class="empty-img" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #cbd5e1; margin-bottom: 20px;">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <p>Bạn chưa có thông báo nào.</p>
        <router-link to="/product" class="btn-primary mt-3 d-inline-block">Khám phá sản phẩm</router-link>
      </div>

      <div v-else class="notification-list">
        <div 
          v-for="notification in notifications" 
          :key="notification.id"
          class="notification-item"
          :class="{ 'is-unread': notification.read_at === null }"
          @click="handleNotificationClick(notification)"
        >
          <div class="noti-icon-wrapper" :class="getIconClass(notification.data.type)">
            <svg v-if="notification.data.type === 'order_created'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <svg v-else-if="notification.data.type === 'payment_success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <svg v-else-if="notification.data.type === 'coupon_received'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path>
            </svg>
            <svg v-else-if="notification.data.type === 'contact_reply'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
          </div>
          
          <div class="noti-content">
            <h4 class="noti-title">{{ notification.data.title }}</h4>
            <div class="noti-message">{{ notification.data.message }}</div>
            <div class="noti-time">{{ formatTime(notification.created_at) }}</div>
          </div>
          
          <div v-if="notification.read_at === null" class="noti-unread-dot"></div>
        </div>
      </div>
      
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination-wrapper">
        <button 
          class="btn-page" 
          :disabled="currentPage === 1" 
          @click="fetchNotifications(currentPage - 1)"
        >
          &laquo;
        </button>
        <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
        <button 
          class="btn-page" 
          :disabled="currentPage === totalPages" 
          @click="fetchNotifications(currentPage + 1)"
        >
          &raquo;
        </button>
      </div>
      
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';

const router = useRouter();
const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(true);
const isMarking = ref(false);

const currentPage = ref(1);
const totalPages = ref(1);

const fetchNotifications = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get('/profile/notifications', { params: { page } });
    notifications.value = response.data.data.data || [];
    unreadCount.value = response.data.unread_count || 0;
    currentPage.value = response.data.data.current_page || 1;
    totalPages.value = response.data.data.last_page || 1;
  } catch (error) {
    console.error('Failed to fetch notifications:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchNotifications();
});

const handleNotificationClick = async (notification) => {
  if (notification.read_at === null) {
    try {
      await api.post(`/profile/notifications/${notification.id}/read`);
      notification.read_at = new Date().toISOString();
      if (unreadCount.value > 0) {
        unreadCount.value--;
      }
    } catch (e) {
      console.error(e);
    }
  }

  // Navigate based on type or url_redirect
  if (notification.data.url_redirect) {
    router.push(notification.data.url_redirect);
  } else if (notification.data.type === 'order_created' || notification.data.type === 'payment_success') {
    router.push('/profile/orders'); // can navigate specifically to order detail if needed
  } else if (notification.data.type === 'coupon_received') {
    router.push('/profile/coupon');
  }
};

const markAllAsRead = async () => {
  isMarking.value = true;
  try {
    await api.post('/profile/notifications/read-all');
    unreadCount.value = 0;
    notifications.value.forEach(n => {
      n.read_at = new Date().toISOString();
    });
  } catch (e) {
    console.error(e);
  } finally {
    isMarking.value = false;
  }
};

const formatTime = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getIconClass = (type) => {
  switch (type) {
    case 'order_created': return 'icon-blue';
    case 'payment_success': return 'icon-green';
    case 'coupon_received': return 'icon-yellow';
    case 'contact_reply': return 'icon-purple';
    default: return 'icon-gray';
  }
};
</script>

<style scoped>
.profile-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  border: 1px solid #f1f5f9;
}

.profile-card-header {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
  background: #fff;
}

.flex-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.profile-card-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.btn-mark-all {
  background: transparent;
  border: none;
  color: #E63B6F;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 6px;
  transition: background 0.2s;
}

.btn-mark-all:hover {
  background: #eff6ff;
}

.p-0 {
  padding: 0;
}

.notifications-loading {
  display: flex;
  justify-content: center;
  padding: 60px 0;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #E63B6F;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.empty-img {
  max-width: 150px;
  margin-bottom: 20px;
  opacity: 0.7;
}

.empty-state p {
  font-size: 1rem;
  margin-bottom: 20px;
}

.btn-primary {
  background: #E63B6F;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: background 0.2s;
}

.btn-primary:hover {
  background: #1e40af;
}

.d-inline-block {
  display: inline-block;
}

.mt-3 {
  margin-top: 1rem;
}

.notification-list {
  display: flex;
  flex-direction: column;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item:hover {
  background: #f8fafc;
}

.notification-item.is-unread {
  background: #eff6ff;
}

.notification-item.is-unread:hover {
  background: #e0f2fe;
}

.noti-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
  flex-shrink: 0;
}

.icon-blue { background: #e0f2fe; color: #0284c7; }
.icon-green { background: #dcfce7; color: #16a34a; }
.icon-yellow { background: #fef9c3; color: #ca8a04; }
.icon-purple { background: #f3e8ff; color: #9333ea; }
.icon-gray { background: #f1f5f9; color: #64748b; }

.noti-content {
  flex: 1;
  min-width: 0;
}

.noti-title {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px 0;
}

.noti-message {
  font-size: 0.95rem;
  color: #475569;
  margin-bottom: 8px;
  line-height: 1.5;
}

.noti-time {
  font-size: 0.85rem;
  color: #94a3b8;
}

.noti-unread-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #E63B6F;
  position: absolute;
  right: 24px;
  top: 50%;
  transform: translateY(-50%);
}

.pagination-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px;
  border-top: 1px solid #f1f5f9;
}

.btn-page {
  background: #fff;
  border: 1px solid #cbd5e1;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  cursor: pointer;
  color: #333;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-page:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.btn-page:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.95rem;
  color: #64748b;
  font-weight: 500;
}
</style>
