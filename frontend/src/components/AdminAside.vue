<template>
  <aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-icon">
        <img src="/favicon.ico" alt="logo-ocean" width="40" height="40">
      </div>
      <h2 class="brand-title">Quản trị</h2>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
          </svg>
        </div>
        <span>Dashboard</span>
      </router-link>

      <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/attendance" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
          </svg>
        </div>
        <span>Chấm công</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/order" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
          </svg>
        </div>
        <span>Đơn hàng</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/pos" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
        </div>
        <span>Bán hàng (POS)</span>
      </router-link>

      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-item" @click="isStoreMenuOpen = !isStoreMenuOpen" :class="{ 'nav-item--open': isStoreMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <span>Quản lý cửa hàng</span>
        <svg class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isStoreMenuOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Store Submenu -->
      <transition name="slide-fade">
        <div v-if="isStoreMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/product" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Sản phẩm</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Danh mục</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/users" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Khách hàng</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/coupon" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Mã giảm giá</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/flash-sale" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Flash Sale</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/post" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Bài viết</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/post-category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Danh mục bài viết</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/review" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Đánh giá</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/stats" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Thống kê</span>
          </router-link>
        </div>
      </transition>

      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="isStaffMenuOpen = !isStaffMenuOpen" :class="{ 'nav-item--open': isStaffMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
          </svg>
        </div>
        <span>Quản lý nhân viên</span>
        <svg class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isStaffMenuOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Staff Submenu -->
      <transition name="slide-fade">
        <div v-if="isStaffMenuOpen && ['admin'].includes(userRoleRaw)" class="nav-submenu">
          <router-link to="/admin/staff" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Danh sách nhân sự</span>
          </router-link>
          <router-link to="/admin/attendance-list" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Lịch sử chấm công</span>
          </router-link>
        </div>
      </transition>
       <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/chat" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
        </div>
        <span>Tin nhắn</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/contact" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
          </svg>
        </div>
        <span>Liên hệ</span>
      </router-link>
    </nav>

    <!-- Footer (User Profile) -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar-circle"><img :src="userAvatar" alt="" width="50" height="50" style="border-radius: 50%;"></div>
        <div class="user-details" @click="handleLogout" style="cursor: pointer;" title="Nhấn để đăng xuất">
          <span class="user-name-bold">{{ userName }}</span>
          <span class="user-email-text">{{ userEmail || 'admin123@gmail.com' }}</span>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

const router = useRouter();
const userName = ref('Admin');
const userEmail = ref('');
const userAvatar = ref('');
const userRole = ref('Manager');
const userRoleRaw = ref('');
const isStoreMenuOpen = ref(true); // Mặc định mở theo ảnh mẫu
const isStaffMenuOpen = ref(false); // Mặc định đóng


onMounted(() => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      const path = user.avatar_url;
      const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');
      
      userName.value = user.full_name || user.name || 'Admin';
      userEmail.value = user.email || '';
      userRoleRaw.value = user.role;
      userRole.value = user.role === 'admin' ? 'Super Admin' : (user.role === 'staff' ? 'Staff' : (user.role === 'seller' ? 'Seller' : 'Customer'));
      
      if (path) {
        userAvatar.value = path.startsWith('http') ? path : `${BASE_URL}${path}`;
      }
    } catch (e) {
      console.error("Failed to parse user data", e);
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
  if (result.isConfirmed) {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user');
    router.push('/client/login');
  }
};
</script>

<style scoped>
.sidebar {
  width: var(--shell-sidebar-width);
  min-height: 100vh;
  background: var(--card-bg, #fff);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  border-right: 1px solid var(--border-color, #eee);
}

/* Brand */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 22px;
  height: var(--shell-header-height);
  border-bottom: 1px solid var(--border-color, #eee);
  flex-shrink: 0;
}

.brand-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--border-subtle);
  overflow: hidden;
}

.brand-title {
  font-size: 1.05rem;
  margin: 0;
  font-weight: 700;
  color: var(--text-main, #000);
  letter-spacing: -0.02em;
}

/* Nav */
.sidebar-nav {
  flex: 1;
  padding: 20px 14px;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  color: var(--text-muted, #666);
  text-decoration: none;
  font-size: 0.925rem;
  font-weight: 500;
  transition: all 0.2s ease;
  margin-bottom: 4px;
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0.7;
}

.dropdown-arrow {
  margin-left: auto;
  opacity: 0.5;
}

.nav-item:hover {
  background: var(--hover-bg, #f3f4f6);
  color: var(--primary);
}

.nav-item--active {
  background: var(--primary) !important;
  color: white !important;
  font-weight: 600;
  box-shadow: 0 10px 20px rgba(230, 59, 111, 0.18);
}

.nav-item--active .nav-icon {
  opacity: 1;
}

.nav-item--open {
  background: var(--hover-bg, #f8f9fa);
  color: var(--text-main, #1a1a1a);
}

.dropdown-arrow {
  margin-left: auto;
  opacity: 0.5;
  transition: transform 0.2s;
}

.dropdown-arrow--open {
  transform: rotate(180deg);
}

/* Submenu */
.nav-submenu {
  padding-left: 12px;
  margin-bottom: 8px;
}

.submenu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 24px;
  color: var(--text-muted, #64748b);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s;
  border-radius: 8px;
}

.submenu-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--border-color, #cbd5e1);
  transition: background 0.2s;
}

.submenu-item:hover {
  color: var(--primary);
}

.submenu-item:hover .submenu-dot {
  background: var(--primary);
}

.submenu-item--active {
  color: var(--primary) !important;
  font-weight: 600;
}

.submenu-item--active .submenu-dot {
  background: var(--primary) !important;
}

/* Transitions */
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

/* Footer */
.sidebar-footer {
  padding: 16px;
  border-top: 1px solid var(--border-color, #eee);
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 4px;
}

.user-avatar-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--hover-bg, #eef2ff);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
  flex-shrink: 0;
  overflow: hidden;
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name-bold {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-main, #1a1a1a);
  line-height: 1.2;
}

.user-email-text {
  font-size: 0.8rem;
  color: var(--text-light, #888);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 768px) {
  .sidebar {
    width: 100%;
    min-height: auto;
    border-right: none;
    border-bottom: 1px solid var(--border-color, #eee);
  }

  .sidebar-nav {
    padding-top: 14px;
    padding-bottom: 14px;
  }
}
</style>
