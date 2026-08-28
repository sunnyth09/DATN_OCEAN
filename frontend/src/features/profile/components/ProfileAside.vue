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
        <!-- Điểm thưởng mini badge -->
        <div v-if="rewardPoints > 0" class="aside-points-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #fbbf24; margin-right: 2px;">
            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
            <path d="M4 22h16"/>
            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
          </svg>
          <strong>{{ formatPoints(rewardPoints) }}</strong> điểm
        </div>
      </div>
    </div>

    <!-- Nav Menu -->
    <!-- Nút toggle chỉ hiển thị trên mobile -->
    <button class="aside-nav-toggle" @click="isMobileNavOpen = !isMobileNavOpen">
      <span>{{ currentMenuTitle }}</span>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="{ transform: isMobileNavOpen ? 'rotate(180deg)' : 'rotate(0)' }" style="transition: transform 0.3s">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </button>

    <nav class="aside-nav" :class="{ 'aside-nav--open': isMobileNavOpen }">
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
        :to="{ name: 'profile-return-requests' }"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <span>Yêu cầu hoàn hàng</span>
      </router-link>

      <router-link
        to="/profile/tickets"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
        </div>
        <span>Khiếu nại của tôi</span>
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
        to="/profile/affiliate"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
          </svg>
        </div>
        <span>Affiliate</span>
      </router-link>

      <!-- ── Ví tiền ── -->
      <router-link
        to="/profile/wallet"
        class="aside-nav-item aside-nav-item--wallet"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
            <circle cx="17" cy="15" r="2" fill="currentColor" stroke="none"/>
          </svg>
        </div>
        <span>Ví tiền</span>
      </router-link>

      <!-- ── Điểm thưởng ── -->
      <router-link
        to="/profile/loyalty"
        class="aside-nav-item aside-nav-item--loyalty"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
        </div>
        <span>Điểm thưởng</span>
        <span v-if="rewardPoints > 0" class="aside-points-pill">{{ formatPoints(rewardPoints) }}</span>
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

      <router-link
        to="/profile/court-bookings"
        class="aside-nav-item"
        active-class="aside-nav-item--active"
      >
        <div class="aside-nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
        </div>
        <span>Lịch đặt sân</span>
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { loyaltyService } from '@/services/loyaltyService';
import Swal from 'sweetalert2';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isMobileNavOpen = ref(false);

const menuLabels = {
  '/profile': 'Thông tin tài khoản',
  '/profile/addresses': 'Sổ địa chỉ',
  '/profile/orders': 'Đơn hàng của tôi',
  '/profile/return-requests': 'Yêu cầu hoàn hàng',
  '/profile/wishlist': 'Sản phẩm yêu thích',
  '/profile/coupons': 'Mã giảm giá của tôi',
  '/profile/affiliate': 'Affiliate',
  '/profile/wallet': 'Ví tiền',
  '/profile/rewards': 'Điểm thưởng',
  '/profile/change-password': 'Đổi mật khẩu',
  '/profile/notifications': 'Thông báo',
  '/profile/bookings': 'Lịch đặt sân'
};

const currentMenuTitle = computed(() => {
  return menuLabels[route.path] || route.meta?.title || 'Quản lý tài khoản';
});

watch(() => route.path, () => {
  isMobileNavOpen.value = false;
});

// FIX L3: Dùng auth store thay vì đọc sessionStorage trực tiếp
const userName = computed(() => authStore.displayName);
const userEmail = computed(() => authStore.email);
const userInitial = computed(() => authStore.userInitial);
const userAvatar = computed(() => authStore.avatarUrl || '');

// Điểm thưởng
const rewardPoints = ref(0);

const formatPoints = (n) => new Intl.NumberFormat('vi-VN').format(n ?? 0);

const fetchRewardPoints = async () => {
  try {
    const token = sessionStorage.getItem('auth_token');
    if (!token) return;
    const res = await loyaltyService.getSummary();
    if (res.data?.status === 'success') {
      rewardPoints.value = res.data.data?.current_balance ?? 0;
    }
  } catch (e) {
    // silent fail
  }
};

// FIX M5: Xác định role để ẩn menu items không phù hợp
const isCustomerRole = computed(() => {
  const role = authStore.role;
  return !role || role === 'customer';
});

const isExactActive = (path) => {
  return route.path === path;
};

// FIX L8: Lắng nghe auth-logout event từ tab khác
const handleAuthLogout = () => {
  router.push('/client/login');
};

onMounted(async () => {
  window.addEventListener('auth-logout', handleAuthLogout);
  await fetchRewardPoints();
});

onUnmounted(() => {
  window.removeEventListener('auth-logout', handleAuthLogout);
});

// FIX C8: Dùng auth store logout() thay vì xóa thủ công
// broadcastLogout() sẽ được gọi bên trong store.logout()
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

  await authStore.logout();
  router.push('/client/login');
};
</script>

<style scoped>
.profile-aside {
  width: 280px;
  background: var(--card-bg);
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
  background-color:  var(--primary);
  color: #fff;
}

.aside-points-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  background: rgba(255,255,255,0.2);
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.78rem;
}
.aside-points-badge strong {
  font-weight: 700;
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

.aside-nav-toggle {
  display: none; /* Ẩn trên Desktop */
}

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
  background: #fff0f3;
  color: var(--primary);
}

.aside-nav-item:hover .aside-nav-icon {
  opacity: 1;
}

.aside-nav-item--active {
  background: #fff0f3 !important;
  color: var(--primary) !important;
  font-weight: 600;
}

.aside-nav-item--active .aside-nav-icon {
  opacity: 1;
  color: var(--primary);
}

/* Điểm thưởng pill badge */
.aside-points-pill {
  margin-left: auto;
  background: linear-gradient(135deg, var(--primary), #f97316);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
  min-width: 28px;
  text-align: center;
}

/* Loyalty nav item star */
.aside-nav-item--loyalty .aside-nav-icon svg {
  stroke: #1e293b;
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
    border-radius: 14px;
  }

  .aside-user-card {
    padding: 12px 14px;
    gap: 10px;
  }

  .aside-avatar {
    width: 38px;
    height: 38px;
    font-size: 1.05rem;
  }

  .aside-user-name {
    font-size: 0.92rem;
  }

  .aside-user-email {
    font-size: 0.74rem;
  }

  .aside-points-badge {
    margin-top: 2px;
    padding: 2px 7px;
    font-size: 0.7rem;
  }

  /* --- MOBILE STYLES --- */
  .aside-nav-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 14px;
    background: #fff;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-main);
    cursor: pointer;
  }
  
  .aside-nav {
    display: none; /* Ẩn đi mặc định trên mobile */
    padding: 8px;
    gap: 2px;
  }
  
  .aside-nav.aside-nav--open {
    display: block;
  }

  .aside-nav-item {
    padding: 9px 12px;
    font-size: 0.85rem;
    border-radius: 8px;
  }

  .aside-nav-divider {
    display: none;
  }
}
</style>
