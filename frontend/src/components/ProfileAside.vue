<template>
  <aside class="profile-aside">
    <!-- User Info Card -->
    <div class="aside-user-card">
      <div class="aside-avatar">
        <img v-if="userAvatar" :src="userAvatar" alt="Avatar" class="aside-avatar-img" />
        <span v-else>{{ userInitial }}</span>
      </div>
      <div class="aside-user-info">
        <h3 class="aside-user-name">{{ userName }}</h3>
        <p class="aside-user-email">{{ userEmail }}</p>
      </div>
    </div>

    <!-- Nav Menu -->
    <nav class="aside-nav">
      <router-link
        to="/profile"
        class="aside-nav-item"
        :class="{ 'aside-nav-item--active': isExactActive('/profile') }"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
        <span>Thông tin tài khoản</span>
      </router-link>

      <router-link
        to="/profile/addresses"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <span>Sổ địa chỉ</span>
      </router-link>

      <router-link
        to="/profile/orders"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
          </svg>
        </div>
        <span>Đơn hàng của tôi</span>
      </router-link>

      <router-link
        to="/profile/wishlist"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
          </svg>
        </div>
        <span>Sản phẩm yêu thích</span>
      </router-link>

      <router-link
        to="/profile/coupons"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 00-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>
          </svg>
        </div>
        <span>Mã giảm giá của tôi</span>
      </router-link>

      <router-link
        to="/profile/change-password"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <span>Đổi mật khẩu</span>
      </router-link>

      <router-link
        to="/profile/notifications"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </div>
        <span>Thông báo</span>
      </router-link>

      <div class="aside-nav-divider"></div>

      <button class="aside-nav-item aside-nav-item--logout" @click="handleLogout">
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </div>
        <span>Đăng xuất</span>
      </button>
    </nav>
  </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/axios';
import Swal from 'sweetalert2';

const router = useRouter();
const route = useRoute();

const userName = ref('');
const userEmail = ref('');
const userInitial = ref('?');

const isExactActive = (path) => {
  return route.path === path;
};

onMounted(() => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      userName.value = user.name || user.full_name || 'Người dùng';
      userEmail.value = user.email || '';
      userInitial.value = (userName.value[0] || '?').toUpperCase();
    } catch (e) {
      console.error('Failed to parse user data', e);
    }
  }
});

const handleLogout = async () => {
  const result = await Swal.fire({
      title: 'Xác nhận',
      text: 'Bạn có chắc chắn muốn đăng xuất?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Đăng xuất',
      cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;
  try { await api.post('/logout'); } catch (e) { /* ignore */ }
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  localStorage.removeItem('ocean_live_chat_token');
  sessionStorage.removeItem('auth_token');
  sessionStorage.removeItem('user');
  sessionStorage.removeItem('ocean_chatbot_messages');
  sessionStorage.removeItem('ocean_chatbot_history');
  router.push('/client/login');
};
</script>

<style scoped>
.profile-aside {
  width: 280px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
  flex-shrink: 0;
}

/* User Card */
.aside-user-card {
  padding: 24px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  background-color:  #E63B6F;
  color: #fff;
}

.aside-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  font-weight: 700;
  flex-shrink: 0;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

.aside-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.aside-user-info {
  min-width: 0;
}

.aside-user-name {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.aside-user-email {
  font-size: 0.8rem;
  margin: 2px 0 0;
  opacity: 0.85;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Nav */
.aside-nav {
  padding: 12px;
}

.aside-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  color: #4b5563;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s ease;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  font-family: inherit;
  text-align: left;
}

.aside-nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.aside-nav-item:hover {
  background: #f3f4f6;
  color: #1a56db;
}

.aside-nav-item:hover .aside-nav-icon {
  opacity: 1;
}

.aside-nav-item--active {
  background: #eff6ff !important;
  color: #E63B6F !important;
  font-weight: 600;
}

.aside-nav-item--active .aside-nav-icon {
  opacity: 1;
  color: #1a56db;
}

.aside-nav-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 8px 12px;
}

.aside-nav-item--logout {
  color: #dc2626;
}

.aside-nav-item--logout:hover {
  background: #fef2f2 !important;
  color: #dc2626;
}

.aside-nav-item--logout .aside-nav-icon {
  color: #dc2626;
}

@media (max-width: 768px) {
  .profile-aside {
    width: 100%;
  }

  .aside-nav {
    display: flex;
    overflow-x: auto;
    gap: 4px;
    padding: 8px;
  }

  .aside-nav-item {
    white-space: nowrap;
    padding: 10px 14px;
    font-size: 0.85rem;
  }

  .aside-nav-divider {
    display: none;
  }
}
</style>
