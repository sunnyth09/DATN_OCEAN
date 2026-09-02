<template>
  <div class="profile-card">
    <div class="profile-card-header flex-header">
      <h2 class="profile-card-title">Thông báo của bạn</h2>
      <button v-if="unreadCount > 0" class="btn-mark-all" @click="markAllAsRead" :disabled="isMarking">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span>Đánh dấu đã đọc</span>
      </button>
    </div>
    
    <div class="profile-card-body p-0">
      <!-- Modern Skeleton Loading -->
      <div v-if="loading" class="notifications-skeleton">
        <div v-for="i in 5" :key="i" class="skeleton-noti-item">
          <div class="skeleton-box" style="width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;"></div>
          <div style="flex: 1;">
            <div class="skeleton-box" style="width: 45%; height: 16px; border-radius: 4px; margin-bottom: 6px;"></div>
            <div class="skeleton-box" style="width: 75%; height: 12px; border-radius: 4px; margin-bottom: 6px;"></div>
            <div class="skeleton-box" style="width: 80px; height: 10px; border-radius: 4px;"></div>
          </div>
        </div>
      </div>
      
      <div v-else-if="notifications.length === 0" class="empty-state">
        <svg class="empty-img" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #cbd5e1; margin-bottom: 14px;">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <p>Bạn chưa có thông báo nào.</p>
        <router-link to="/product" class="btn-primary mt-2 d-inline-block">Khám phá sản phẩm</router-link>
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
            <svg v-if="notification.data.type === 'order_created'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <svg v-else-if="notification.data.type === 'payment_success'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <svg v-else-if="notification.data.type === 'coupon_received'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"></path><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"></path><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"></path>
            </svg>
            <svg v-else-if="notification.data.type === 'contact_reply'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
          </div>
          
          <div class="noti-content">
            <h4 class="noti-title">
                <span>{{ notification.data.title }}</span>
                <span v-if="notification.data.is_flash_sale || (notification.data.title && notification.data.title.includes('Flash Sale'))" class="badge bg-warning text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.62rem; padding: 2px 5px; border-radius: 4px; font-weight: 700;"><AppIcon name="zap" size="9" /> Flash Sale</span>
            </h4>
            <div class="noti-message">{{ notification.data.message }}</div>
            <div class="noti-time">{{ formatTime(notification.created_at) }}</div>
          </div>
          
          <div v-if="notification.read_at === null" class="noti-unread-dot"></div>
        </div>
      </div>
      
      <!-- Pagination -->
      <div v-if="totalPages >= 1" class="pagination-wrapper">
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
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import api from '@/axios';
import { useAuthStore } from '@/stores/auth';
import AppIcon from '@/components/AppIcon.vue';

const router = useRouter();
const authStore = useAuthStore();
// Dùng chung state với ClientHeader qua auth store → badge đồng bộ tức thì
const { unreadNotificationCount: unreadCount } = storeToRefs(authStore);
const notifications = ref([]);
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
      authStore.decrementUnreadNotificationCount();
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
    authStore.resetUnreadNotificationCount();
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
  background: var(--card-bg, #fff);
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  border: 1px solid #f1f5f9;
  font-family: var(--font-inter, 'Inter', sans-serif);
}

.profile-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  background: var(--card-bg, #fff);
}

.flex-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.profile-card-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main, #0f172a);
  margin: 0;
  white-space: nowrap;
}

.btn-mark-all {
  background: rgba(230, 59, 111, 0.06);
  border: none;
  color: var(--primary, #e63b6f);
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  padding: 6px 12px;
  border-radius: 8px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-mark-all:hover {
  background: rgba(230, 59, 111, 0.12);
}

.p-0 {
  padding: 0;
}

.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: #64748b;
}

.empty-img {
  max-width: 90px;
  margin-bottom: 14px;
  opacity: 0.7;
}

.empty-state p {
  font-size: 0.9rem;
  margin-bottom: 14px;
}

.btn-primary {
  background: var(--primary, #e63b6f);
  color: #fff;
  padding: 8px 18px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.86rem;
  transition: opacity 0.2s;
}

.btn-primary:hover {
  opacity: 0.9;
}

.d-inline-block {
  display: inline-block;
}

.mt-2 {
  margin-top: 0.5rem;
}

.notification-list {
  display: flex;
  flex-direction: column;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
  gap: 12px;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item:hover {
  background: #f8fafc;
}

.notification-item.is-unread {
  background: #fff8f9;
}

.notification-item.is-unread:hover {
  background: #fff0f3;
}

.noti-icon-wrapper {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
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
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--text-main, #0f172a);
  margin: 0 0 4px 0;
  line-height: 1.35;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.noti-message {
  font-size: 0.84rem;
  color: #475569;
  margin-bottom: 4px;
  line-height: 1.45;
}

.noti-time {
  font-size: 0.74rem;
  color: #94a3b8;
}

.noti-unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--primary, #e63b6f);
  position: absolute;
  right: 16px;
  top: 16px;
}

.pagination-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid #f1f5f9;
}

.btn-page {
  background: var(--card-bg, #fff);
  border: 1px solid #cbd5e1;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  cursor: pointer;
  color: #333;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-page:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.btn-page:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 500;
}

/* ===== Modern Skeleton Loading Styles ===== */
.notifications-skeleton {
  padding: 4px 0;
  pointer-events: none;
}

.skeleton-noti-item {
  display: flex;
  gap: 12px;
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  align-items: center;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}

/* Mobile Media Query */
@media (max-width: 768px) {
  .profile-card {
    border-radius: 14px;
  }
  .profile-card-header {
    padding: 12px 14px;
  }
  .profile-card-title {
    font-size: 1rem;
  }
  .btn-mark-all {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 6px;
  }
  .notification-item {
    padding: 12px 14px;
    gap: 10px;
  }
  .noti-icon-wrapper {
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }
  .noti-icon-wrapper svg {
    width: 16px;
    height: 16px;
  }
  .noti-title {
    font-size: 0.85rem;
    margin-bottom: 2px;
    gap: 6px;
  }
  .noti-message {
    font-size: 0.78rem;
    line-height: 1.38;
    margin-bottom: 3px;
  }
  .noti-time {
    font-size: 0.7rem;
  }
  .noti-unread-dot {
    right: 10px;
    top: 14px;
    width: 7px;
    height: 7px;
  }
}
</style>
