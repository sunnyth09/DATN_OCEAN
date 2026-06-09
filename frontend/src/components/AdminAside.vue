<template>
  <aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-icon">
        <img src="../../public/favicon.ico" alt="logo-ocean" width="100" height="60">
      </div>
      <h2 class="brand-title">Admin</h2>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="dashboard" />
        </div>
        <span>Dashboard</span>
      </router-link>

      <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/attendance" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="clock" />
        </div>
        <span>Chấm công</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/order" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="order" />
        </div>
        <span>Đơn hàng</span>
      </router-link>

      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/return-requests" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 8v13H3V8"></path>
            <path d="M1 3h22v5H1z"></path>
            <path d="M10 12h4"></path>
            <path d="M12 10v4"></path>
          </svg>
        </div>
        <span>Hoàn hàng</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/pos" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="pos" />
        </div>
        <span>Bán hàng (POS)</span>
      </router-link>

      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="isCourtMenuOpen = !isCourtMenuOpen" :class="{ 'nav-item--open': isCourtMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <path d="M12 3v18"></path>
          </svg>
        </div>
        <span>Sân Cầu Lông</span>
        <svg class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isCourtMenuOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Court Submenu -->
      <transition name="slide-fade">
        <div v-if="isCourtMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/court-dashboard" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Dashboard Lễ Tân</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/courts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Hệ thống sân</span>
          </router-link>
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/court-bookings" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Quản lý Đặt Sân</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/court-reports" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Báo Cáo Thống Kê</span>
          </router-link>
        </div>
      </transition>

      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-item" @click="isStoreMenuOpen = !isStoreMenuOpen" :class="{ 'nav-item--open': isStoreMenuOpen }">
        <div class="nav-icon">
          <AppIcon name="store" />
        </div>
        <span>Quản lý cửa hàng</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isStoreMenuOpen }" size="14" />
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
          <AppIcon name="users" />
        </div>
        <span>Quản lý nhân viên</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isStaffMenuOpen }" size="14" />
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
          <router-link to="/admin/work-locations" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Vị trí làm việc</span>
          </router-link>
          <router-link to="/admin/work-shifts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Ca làm việc & Phân ca</span>
          </router-link>
        </div>
      </transition>
       <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/chat" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="chat" />
        </div>
        <span>Tin nhắn</span>
      </router-link>

      <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/contact" class="nav-item" active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="contact" />
        </div>
        <span>Liên hệ</span>
      </router-link>
    </nav>

    <!-- Footer (User Profile) -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div v-if="userAvatar" class="user-avatar-circle"><img :src="userAvatar" alt="" width="50" height="50" style="border-radius: 50%;"></div>
        <div v-else class="user-avatar-circle">{{ userInitial }}</div>
        <div class="user-details" @click="handleLogout" style="cursor: pointer;" title="Nhấn để đăng xuất">
          <span class="user-name-bold">{{ userName }}</span>
          <span class="user-email-text">{{ userEmail || 'admin123@gmail.com' }}</span>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import AppIcon from '@/icons/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const userName = ref('Admin');
const userEmail = ref('');
const userAvatar = ref('');
const userRole = ref('Manager');
const userRoleRaw = ref('');
const isStoreMenuOpen = ref(true); // Mặc định mở theo ảnh mẫu
const isStaffMenuOpen = ref(false); // Mặc định đóng
const isCourtMenuOpen = ref(true); // Mặc định mở

const userInitial = computed(() => (userName.value?.[0] || 'A').toUpperCase());

onMounted(() => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      const path = user.avatar_url;
      const BASE_URL = import.meta.env.VITE_BASE_URL || 'http://localhost:8383';
      
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
    await authStore.logout();
    router.push('/');
  }
};
</script>

<style scoped>
.sidebar {
  width: 250px;
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
  height: 70px;
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
}

.brand-title {
  font-size: 1.4rem;
  margin-left: 5px;
  margin-top: 5px;
  font-weight: 700;
  color: var(--text-main, #000);
  letter-spacing: -0.5px;
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
  color: var(--text-main, #1a1a1a);
}

.nav-item--active {
  background: var(--ocean-blue, #1d4ed8) !important;
  color: white !important;
  font-weight: 600;
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
  color: var(--ocean-blue, #1d4ed8);
}

.submenu-item:hover .submenu-dot {
  background: var(--ocean-blue, #1d4ed8);
}

.submenu-item--active {
  color: #1d4ed8 !important;
  font-weight: 600;
}

.submenu-item--active .submenu-dot {
  background: #1d4ed8 !important;
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
  color: var(--ocean-blue, #1d4ed8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
  flex-shrink: 0;
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
</style>
