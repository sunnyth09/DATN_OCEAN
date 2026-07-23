<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import AppIcon from '@/icons/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { getAbsoluteUrl, getAppBaseUrl } from '@/utils/url';

const BASE_URL = getAppBaseUrl();

const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false
  }
});

const router = useRouter();
const authStore = useAuthStore();
const uiStore = useUiStore();
const userName = ref('Admin');
const userEmail = ref('');
const userAvatar = ref('');
const userRole = ref('Manager');
const userRoleRaw = ref('');
const isBusinessMenuOpen = ref(false);
const isStoreMenuOpen = ref(true);
const isCourtMenuOpen = ref(false);
const isMarketingMenuOpen = ref(false);
const isFinanceMenuOpen = ref(false);
const isContentMenuOpen = ref(false);
const isCrmMenuOpen = ref(false);
const isHrMenuOpen = ref(false);

const userInitial = computed(() => (userName.value?.[0] || 'A').toUpperCase());

const toggleSidebar = () => {
  uiStore.toggleBackofficeSidebar();
};

const handleSubmenuClick = (menu) => {
  if (props.collapsed) {
    uiStore.toggleBackofficeSidebar();
  }
  
  const menus = {
    business: isBusinessMenuOpen,
    store: isStoreMenuOpen,
    court: isCourtMenuOpen,
    marketing: isMarketingMenuOpen,
    finance: isFinanceMenuOpen,
    content: isContentMenuOpen,
    crm: isCrmMenuOpen,
    hr: isHrMenuOpen
  };
  
  if (menus[menu]) {
    menus[menu].value = !menus[menu].value;
    
    // Auto-open submenu when clicking a menu from collapsed state
    if (props.collapsed) {
       menus[menu].value = true;
    }
  }
};

onMounted(() => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      const path = user.avatar_url;
      
      userName.value = user.full_name || user.name || 'Admin';
      userEmail.value = user.email || '';
      userRoleRaw.value = user.role;
      userRole.value = user.role === 'admin' ? 'Super Admin' : (user.role === 'staff' ? 'Staff' : (user.role === 'seller' ? 'Seller' : 'Customer'));
      
      if (path) {
        userAvatar.value = getAbsoluteUrl(path);
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
    router.push('/client/login');
  }
};
</script>
<template>
  <aside class="sidebar" :class="{ 'sidebar--collapsed': collapsed }">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-icon" v-show="!collapsed">
        <img :src="BASE_URL + '/storage/logo/LOGO_QS.png'" alt="logo-ocean" width="45" >
      </div>
      <h2 class="brand-title"> Quản trị </h2>
      <button class="aside-toggle-btn" @click="toggleSidebar" :title="collapsed ? 'Mở rộng' : 'Thu gọn'">
        <svg v-if="collapsed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="13 17 18 12 13 7"></polyline>
          <polyline points="6 17 11 12 6 7"></polyline>
        </svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="11 17 6 12 11 7"></polyline>
          <polyline points="18 17 13 12 18 7"></polyline>
        </svg>
      </button>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <!-- 1. Tổng quan -->
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <div class="nav-icon">
          <AppIcon name="dashboard" />
        </div>
        <span>Dashboard</span>
      </router-link>

      <!-- 2. Kinh doanh & Bán hàng -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('business')" :class="{ 'nav-item--open': isBusinessMenuOpen }">
        <div class="nav-icon">
          <AppIcon name="pos" />
        </div>
        <span>Kinh doanh</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isBusinessMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isBusinessMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/pos" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bán hàng (POS)</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/order" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Đơn hàng</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/return-requests" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Hoàn hàng</span>
          </router-link>
        </div>
      </transition>

      <!-- 3. Kho & Sản phẩm -->
      <div v-if="['admin', 'staff'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('store')" :class="{ 'nav-item--open': isStoreMenuOpen }">
        <div class="nav-icon">
          <AppIcon name="store" />
        </div>
        <span>Kho & Sản phẩm</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isStoreMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isStoreMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/product" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh sách Sản phẩm</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh mục</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/stats" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Thống kê bán hàng</span>
          </router-link>
        </div>
      </transition>

      <!-- 4. Sân Cầu Lông -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('court')" :class="{ 'nav-item--open': isCourtMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <path d="M12 3v18"></path>
          </svg>
        </div>
        <span>Sân Cầu Lông</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isCourtMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isCourtMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/court-dashboard" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Dashboard Lễ Tân</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/courts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Hệ thống sân</span>
          </router-link>
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/court-bookings" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quản lý Đặt Sân</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/court-reports" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Báo Cáo Thống Kê</span>
          </router-link>
        </div>
      </transition>

      <!-- 5. Marketing & Khuyến mãi -->
      <div v-if="['admin', 'staff'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('marketing')" :class="{ 'nav-item--open': isMarketingMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
            <line x1="7" y1="7" x2="7.01" y2="7"></line>
          </svg>
        </div>
        <span>Marketing</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isMarketingMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isMarketingMenuOpen" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/flash-sale" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Flash Sale</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/coupon" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Mã giảm giá</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/rewards" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quà tặng Loyalty</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/user-rewards" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Lịch sử đổi quà</span>
          </router-link>
        </div>
      </transition>

      <!-- 6. Tài chính & Ví -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('finance')" :class="{ 'nav-item--open': isFinanceMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <span>Tài chính</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isFinanceMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isFinanceMenuOpen" class="nav-submenu">
          <router-link to="/admin/wallet-deposits" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duyệt nạp tiền</span>
          </router-link>
          <router-link to="/admin/wallet-withdrawals" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duyệt rút tiền</span>
          </router-link>
        </div>
      </transition>

      <!-- 7. CRM & CSKH -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('crm')" :class="{ 'nav-item--open': isCrmMenuOpen }">
        <div class="nav-icon">
          <AppIcon name="contact" />
        </div>
        <span>Chăm sóc Khách hàng</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isCrmMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isCrmMenuOpen" class="nav-submenu">
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/users" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh sách Khách hàng</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/chat" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Tin nhắn (Chat)</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/contact" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Liên hệ</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/review" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Đánh giá</span>
          </router-link>
          <router-link v-if="['admin', 'seller'].includes(userRoleRaw)" to="/admin/tickets" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Khiếu nại</span>
          </router-link>
        </div>
      </transition>

      <!-- 8. Nội dung & Blog -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('content')" :class="{ 'nav-item--open': isContentMenuOpen }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </div>
        <span>Nội dung</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isContentMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isContentMenuOpen" class="nav-submenu">
          <router-link to="/admin/post" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bài viết</span>
          </router-link>
          <router-link to="/admin/post-category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh mục bài viết</span>
          </router-link>
        </div>
      </transition>

      <!-- 9. Nhân sự & Chấm công -->
      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('hr')" :class="{ 'nav-item--open': isHrMenuOpen }">
        <div class="nav-icon">
          <AppIcon name="users" />
        </div>
        <span>Nhân sự</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': isHrMenuOpen }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="isHrMenuOpen" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/staff" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh sách nhân sự</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-shifts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ca làm việc & Phân ca</span>
          </router-link>
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/attendance" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chấm công (Cá nhân)</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/attendance-list" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Lịch sử chấm công (Admin)</span>
          </router-link>
          <router-link v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" to="/admin/face-register" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Đăng ký khuôn mặt</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/face-management" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quản lý khuôn mặt (Admin)</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-locations" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chi nhánh</span>
          </router-link>
        </div>
      </transition>
    </nav>

    <!-- Footer (User Profile) -->
    <div class="sidebar-footer">
      <div class="user-profile">
        <div v-if="userAvatar" class="user-avatar-circle"><img :src="userAvatar" alt="" width="36" height="36" style="border-radius: 50%;"></div>
        <div v-else class="user-avatar-circle">{{ userInitial }}</div>
        <div class="user-details" @click="handleLogout" style="cursor: pointer;" title="Nhấn để đăng xuất">
          <span class="user-name-bold">{{ userName }}</span>
          <span class="user-email-text">{{ userEmail || 'admin123@gmail.com' }}</span>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 250px;
  height: 100vh;

  background: var(--card-bg, #fff);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  border-right: 1px solid var(--border-color, #eee);
  transition: width 0.3s ease;
  overflow-y: auto;
  overflow-x: hidden;
}

.sidebar::-webkit-scrollbar {
  width: 4px;
}
.sidebar::-webkit-scrollbar-thumb {
  background-color: var(--border-color, #eee);
  border-radius: 4px;
}

.sidebar--collapsed {
  width: 80px;
}

.sidebar--collapsed .brand-title,
.sidebar--collapsed .sidebar-nav span,
.sidebar--collapsed .dropdown-arrow,
.sidebar--collapsed .nav-submenu,
.sidebar--collapsed .user-details {
  display: none;
}

.sidebar--collapsed .brand-icon {
  display: none;
}

.sidebar--collapsed .aside-toggle-btn {
  margin-left: 0;
}

.sidebar--collapsed .sidebar-brand {
  padding: 0;
  justify-content: center;
}

.sidebar--collapsed .nav-item {
  justify-content: center;
  padding: 12px 0;
}

.sidebar--collapsed .user-profile {
  justify-content: center;
  padding: 8px 0;
}


/* Brand */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 16px;
  height: 56px;
  border-bottom: 1px solid var(--border-color, #eee);
  flex-shrink: 0;
}

.brand-icon {
  width: auto;
  height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
}

.brand-title {
  font-size: 1.15rem;
  margin-left: 0;
  margin-top: 0;
  font-weight: 700;
  color: var(--text-main, #000);
  letter-spacing: -0.3px;
  white-space: nowrap;
}

.aside-toggle-btn {
  background: transparent;
  border: none;
  color: var(--text-muted, #666);
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: auto;
  border-radius: 6px;
  transition: all 0.2s;
}

.aside-toggle-btn:hover {
  background: var(--hover-bg, #f3f4f6);
  color: var(--primary);
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
  padding: 8px 14px;
  border-radius: 5px;
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

.nav-icon svg {
  width: 18px;
  height: 18px;
  transition: transform 0.2s;
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
  padding: 12px;
  border-top: 1px solid var(--border-color, #eee);

  position: sticky;
  bottom: 0;
  background: var(--card-bg);
  z-index: 10;
  margin-top: 10px;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0px 4px;
}

.user-avatar-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--hover-bg, #eef2ff);
  color: var(--ocean-blue, #1d4ed8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name-bold {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-main, #1a1a1a);
  line-height: 1.2;
}

.user-email-text {
  font-size: 0.75rem;
  color: var(--text-light, #888);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
