
<script setup>
import { computed, onMounted, onBeforeUnmount, ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { getAbsoluteUrl, getAppBaseUrl } from '@/utils/url';
import api from '@/axios';

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

const openMenus = reactive({
  business: false,
  inventory: false,
  court: false,
  marketing: false,
  finance: false,
  care: false,
  content: false,
  staff: false
});

const userInitial = computed(() => (userName.value?.[0] || 'A').toUpperCase());

const toggleSidebar = () => {
  uiStore.toggleBackofficeSidebar();
};

const handleSubmenuClick = (menu) => {
  if (props.collapsed) {
    uiStore.toggleBackofficeSidebar();
    openMenus[menu] = true;
  } else {
    openMenus[menu] = !openMenus[menu];
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

  fetchBadges();
  badgeTimer = setInterval(fetchBadges, 60000); // Cập nhật mỗi 1 phút
  window.addEventListener('admin-notification-received', fetchBadges);
  window.addEventListener('admin-order-updated', fetchBadges);
});

onBeforeUnmount(() => {
  if (badgeTimer) clearInterval(badgeTimer);
  window.removeEventListener('admin-notification-received', fetchBadges);
  window.removeEventListener('admin-order-updated', fetchBadges);
});

// ─── Sidebar Badges ────────────────────────────────────────────────
const badges = reactive({
  pending_orders: 0,
  pending_returns: 0,
  open_tickets: 0,
  pending_contacts: 0,
  unreplied_chats: 0,
});

let badgeTimer = null;

const fetchBadges = async () => {
  try {
    const res = await api.get('/admin/sidebar-badges');
    if (res.data?.data) {
      badges.pending_orders   = res.data.data.pending_orders   || 0;
      badges.pending_returns  = res.data.data.pending_returns  || 0;
      badges.open_tickets     = res.data.data.open_tickets     || 0;
      badges.pending_contacts = res.data.data.pending_contacts || 0;
      badges.unreplied_chats  = res.data.data.unreplied_chats  || 0;

      // Cập nhật cả uiStore để trigger các v-if dùng uiStore trên template
      uiStore.setAdminUnreadChatCount(res.data.data.unread_chats || res.data.data.unreplied_chats || 0);
      uiStore.setAdminPendingReviewCount(res.data.data.pending_reviews || 0);
      uiStore.setAdminPendingTicketCount(res.data.data.open_tickets || 0);
      uiStore.setAdminPendingContactCount(res.data.data.pending_contacts || 0);
    }
  } catch {
    // Fail silently — badges are non-critical UI
  }
};

// Tổng badge cho nhóm Kinh doanh (hiển thị trên tên nhóm khi submenu đóng)
const totalBusinessBadge = computed(() => badges.pending_orders + badges.pending_returns);
// Tổng badge cho nhóm Chăm sóc Khách hàng
const totalCareBadge = computed(() => badges.unreplied_chats + badges.open_tickets + badges.pending_contacts);
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
      <router-link to="/admin" class="logo">
        <img :src="BASE_URL + '/storage/logo/OCEAN_SPORT_LOGO_v0_tranperant.png'" alt="logo-ocean" width="36" >
        <span class="logo-text">Ocean Sport</span>
      </router-link>
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
      <!-- Dashboard -->
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <div class="nav-icon"><AppIcon name="dashboard" /></div>
        <span>Dashboard</span>
      </router-link>

      <!-- Kinh doanh -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('business')" :class="{ 'nav-item--open': openMenus.business }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
        </div>
        <span>Kinh doanh</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.business }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.business && ['admin', 'seller'].includes(userRoleRaw)" class="nav-submenu">
          <router-link to="/admin/pos" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bán hàng (POS)</span>
          </router-link>
          <router-link to="/admin/order" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Đơn hàng</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/return-requests" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Hoàn hàng</span>
          </router-link>
        </div>
      </transition>

      <!-- Kho & Sản phẩm -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('inventory')" :class="{ 'nav-item--open': openMenus.inventory }">
        <div class="nav-icon"><AppIcon name="store" /></div>
        <span>Kho & Sản phẩm</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.inventory }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.inventory" class="nav-submenu">
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/product" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Sản phẩm</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh mục</span>
          </router-link>
        </div>
      </transition>

      <!-- Sân Cầu Lông -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('court')" :class="{ 'nav-item--open': openMenus.court }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="12" x2="21" y2="12"></line><path d="M12 3v18"></path></svg>
        </div>
        <span>Sân Cầu Lông</span>
        <svg class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.court }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.court" class="nav-submenu">
          <router-link to="/admin/court-dashboard" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Dashboard Lễ Tân</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/courts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Hệ thống sân</span>
          </router-link>
          <router-link to="/admin/court-bookings" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quản lý Đặt Sân</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/court-reports" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Báo Cáo Thống Kê</span>
          </router-link>
        </div>
      </transition>

      <!-- Marketing -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('marketing')" :class="{ 'nav-item--open': openMenus.marketing }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
        </div>
        <span>Marketing</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.marketing }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.marketing" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/coupon" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Mã giảm giá</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/affiliate" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quản lý Affiliate</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/stats" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span>
            <span>Thống kê</span>
          </router-link>
          <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/user-rewards" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Lịch sử đổi quà</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/flash-sale" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Flash Sale</span>
          </router-link>
        </div>
      </transition>

      <!-- Tài chính -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('finance')" :class="{ 'nav-item--open': openMenus.finance }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        </div>
        <span>Tài chính</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.finance }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.finance" class="nav-submenu">
          <router-link to="/admin/wallet-deposits" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duyệt nạp tiền</span>
          </router-link>
          <router-link to="/admin/wallet-withdrawals" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Duyệt rút tiền</span>
          </router-link>
        </div>
      </transition>

      <!-- Chăm sóc Khách hàng -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('care')" :class="{ 'nav-item--open': openMenus.care }">
        <div class="nav-icon"><AppIcon name="chat" /></div>
        <span>Chăm sóc Khách hàng</span>
        <span v-if="(uiStore.adminUnreadChatCount + uiStore.adminPendingReviewCount + uiStore.adminPendingTicketCount + uiStore.adminPendingContactCount) > 0" style="width: 8px; height: 8px; border-radius: 50%; background-color: #ef4444; margin-left: 6px;"></span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.care }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.care" class="nav-submenu">
          <router-link to="/admin/users" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Khách hàng</span>
          </router-link>
          <router-link to="/admin/review" class="submenu-item" active-class="submenu-item--active" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span class="submenu-dot"></span><span>Đánh giá & Khiếu nại</span>
            </div>
            <span v-if="(uiStore.adminPendingReviewCount + uiStore.adminPendingTicketCount + (badges?.open_tickets || 0)) > 0" style="background-color: #ef4444; color: white; border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; margin-left: 8px;">{{ ((uiStore.adminPendingReviewCount || 0) + (uiStore.adminPendingTicketCount || 0) + (badges?.open_tickets || 0)) > 99 ? '99+' : ((uiStore.adminPendingReviewCount || 0) + (uiStore.adminPendingTicketCount || 0) + (badges?.open_tickets || 0)) }}</span>
          </router-link>
          <router-link to="/admin/chat" class="submenu-item" active-class="submenu-item--active" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span class="submenu-dot"></span><span>Chat</span>
            </div>
            <span v-if="uiStore.adminUnreadChatCount > 0" style="background-color: #ef4444; color: white; border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; margin-left: 8px;">{{ uiStore.adminUnreadChatCount > 99 ? '99+' : uiStore.adminUnreadChatCount }}</span>
          </router-link>
          <router-link to="/admin/contact" class="submenu-item" active-class="submenu-item--active" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span class="submenu-dot"></span><span>Liên hệ</span>
            </div>
            <span v-if="(uiStore.adminPendingContactCount + (badges?.pending_contacts || 0)) > 0" style="background-color: #ef4444; color: white; border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; margin-left: 8px;">{{ ((uiStore.adminPendingContactCount || 0) + (badges?.pending_contacts || 0)) > 99 ? '99+' : ((uiStore.adminPendingContactCount || 0) + (badges?.pending_contacts || 0)) }}</span>
          </router-link>
        </div>
      </transition>

      <!-- Nội dung -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('content')" :class="{ 'nav-item--open': openMenus.content }">
        <div class="nav-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
        <span>Nội dung</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.content }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.content" class="nav-submenu">
          <router-link to="/admin/post" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bài viết</span>
          </router-link>
          <router-link to="/admin/post-comments" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Bình luận bài viết</span>
          </router-link>
          <router-link to="/admin/post-category" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh mục bài viết</span>
          </router-link>
        </div>
      </transition>

      <!-- Nhân sự -->
      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-item" @click="handleSubmenuClick('staff')" :class="{ 'nav-item--open': openMenus.staff }">
        <div class="nav-icon"><AppIcon name="users" /></div>
        <span>Nhân sự</span>
        <AppIcon name="chevron-down" class="dropdown-arrow" :class="{ 'dropdown-arrow--open': openMenus.staff }" size="14" />
      </div>
      <transition name="slide-fade">
        <div v-if="openMenus.staff" class="nav-submenu">
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/staff" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Danh sách nhân sự</span>
          </router-link>
          <router-link to="/admin/attendance" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chấm công</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/attendance-list" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Lịch sử chấm công</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-locations" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Chi nhánh</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-shifts" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Ca làm việc & Phân ca</span>
          </router-link>
          <router-link to="/admin/face-register" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Đăng ký khuôn mặt</span>
          </router-link>
          <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/face-management" class="submenu-item" active-class="submenu-item--active">
            <span class="submenu-dot"></span><span>Quản lý khuôn mặt</span>
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
.logo {
  text-decoration: none;
  display: flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
}
.logo-text {
  margin-left: 10px;
  font-size: 15px;
  font-weight: bold;
  color: #64748b;
}
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

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.logo-text {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-main, #1a1a1a);
  white-space: nowrap;
  letter-spacing: -0.2px;
  margin: 0;
  padding: 0;
  line-height: 1.2;
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
  cursor: pointer;
  user-select: none;
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
  background: var(--primary) !important;
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
  cursor: pointer;
  user-select: none;
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
