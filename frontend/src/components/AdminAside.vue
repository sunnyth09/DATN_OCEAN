<script setup>
import { computed, ref, reactive, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { getAbsoluteUrl } from '@/utils/url';
import oceanLogo from '@/assets/images/OCEAN_SPORT_LOGO_v0_tranperant.png';

const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false
  }
});

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const uiStore = useUiStore();

const currentUser = computed(() => {
  if (authStore.user) return authStore.user;
  try {
    const raw = localStorage.getItem('user') || sessionStorage.getItem('user');
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
});

const userRoleRaw = computed(() => currentUser.value?.role || authStore.userRole || 'admin');
const userName = computed(() => currentUser.value?.full_name || currentUser.value?.name || 'Admin');
const userRole = computed(() => {
  const role = userRoleRaw.value;
  return role === 'admin' ? 'Super Admin' : (role === 'staff' ? 'Staff' : (role === 'seller' ? 'Seller' : 'Customer'));
});
const userAvatar = computed(() => {
  const path = currentUser.value?.avatar_url;
  return path ? getAbsoluteUrl(path) : '';
});

const userInitial = computed(() => (userName.value?.[0] || 'A').toUpperCase());

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

// Real-time notification badge counts
const businessPendingCount = computed(() => (uiStore.adminPendingOrderCount || 0) + (uiStore.adminPendingReturnCount || 0));
const courtPendingCount = computed(() => uiStore.adminPendingCourtBookingCount || 0);
const financePendingCount = computed(() => uiStore.adminPendingWithdrawalCount || 0);
const careReviewTicketCount = computed(() => (uiStore.pendingReviewCount || 0) + (uiStore.adminPendingTicketCount || 0));
const carePendingCount = computed(() => (uiStore.pendingContactCount || 0) + (uiStore.pendingChatCount || 0) + careReviewTicketCount.value);

// Single Accordion sync with active route
const syncActiveRouteToMenu = () => {
  const path = route.path;
  Object.keys(openMenus).forEach((key) => {
    openMenus[key] = false;
  });

  if (path.includes('/admin/pos') || path.includes('/admin/order') || path.includes('/admin/return-requests')) {
    openMenus.business = true;
  } else if (path.includes('/admin/product') || path.includes('/admin/category') || path.includes('/admin/brand') || path.includes('/admin/size-guide')) {
    openMenus.inventory = true;
  } else if (path.includes('/admin/court') || path.includes('/admin/open-play')) {
    openMenus.court = true;
  } else if (path.includes('/admin/coupon') || path.includes('/admin/affiliate') || path.includes('/admin/stats') || path.includes('/admin/user-rewards') || path.includes('/admin/flash-sale')) {
    openMenus.marketing = true;
  } else if (path.includes('/admin/wallet')) {
    openMenus.finance = true;
  } else if (path.includes('/admin/users') || path.includes('/admin/review') || path.includes('/admin/chat') || path.includes('/admin/contact')) {
    openMenus.care = true;
  } else if (path.includes('/admin/post')) {
    openMenus.content = true;
  } else if (path.includes('/admin/staff') || path.includes('/admin/attendance') || path.includes('/admin/work-') || path.includes('/admin/face-')) {
    openMenus.staff = true;
  }
};

watch(() => route.path, syncActiveRouteToMenu, { immediate: true });

const toggleSidebar = () => {
  uiStore.toggleBackofficeSidebar();
};

// Smart Single Accordion toggle
const handleSubmenuClick = (menu) => {
  if (props.collapsed) {
    uiStore.toggleBackofficeSidebar();
    Object.keys(openMenus).forEach((key) => {
      openMenus[key] = false;
    });
    openMenus[menu] = true;
  } else {
    const currentState = openMenus[menu];
    Object.keys(openMenus).forEach((key) => {
      openMenus[key] = false;
    });
    openMenus[menu] = !currentState;
  }
};

const isParentActive = (menu) => {
  const path = route.path;
  switch (menu) {
    case 'business':
      return path.includes('/admin/pos') || path.includes('/admin/order') || path.includes('/admin/return-requests');
    case 'inventory':
      return path.includes('/admin/product') || path.includes('/admin/category') || path.includes('/admin/brand') || path.includes('/admin/size-guide');
    case 'court':
      return path.includes('/admin/court') || path.includes('/admin/open-play');
    case 'marketing':
      return path.includes('/admin/coupon') || path.includes('/admin/affiliate') || path.includes('/admin/stats') || path.includes('/admin/user-rewards') || path.includes('/admin/flash-sale');
    case 'finance':
      return path.includes('/admin/wallet');
    case 'care':
      return path.includes('/admin/users') || path.includes('/admin/review') || path.includes('/admin/chat') || path.includes('/admin/contact');
    case 'content':
      return path.includes('/admin/post');
    case 'staff':
      return path.includes('/admin/staff') || path.includes('/admin/attendance') || path.includes('/admin/work-') || path.includes('/admin/face-');
    default:
      return false;
  }
};

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
    <!-- Brand / Header -->
    <div class="sidebar-brand">
      <router-link to="/admin" class="logo" :title="collapsed ? 'Ocean Sport' : ''">
        <img :src="oceanLogo" alt="Ocean Sport" class="logo-img">
        <span class="logo-text">Ocean Sport</span>
      </router-link>

      <button 
        class="aside-toggle-btn" 
        @click="toggleSidebar" 
        :title="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
        :aria-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      >
        <svg v-if="collapsed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </button>
    </div>

    <!-- Nav List -->
    <nav class="sidebar-nav">
      <!-- 1. Dashboard -->
      <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin" class="nav-item" exact-active-class="nav-item--active" :title="collapsed ? 'Trang chủ Dashboard' : ''">
        <div class="nav-icon"><i class="bi bi-grid-1x2-fill"></i></div>
        <span class="nav-label">Trang chủ</span>
      </router-link>

      <!-- 2. Kinh doanh (Business) -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('business')" 
          :class="{ 'nav-item--open': openMenus.business, 'nav-item--parent-active': isParentActive('business') }"
          :title="collapsed ? 'Kinh doanh' : ''"
        >
          <div class="nav-icon position-relative">
            <i class="bi bi-cart3"></i>
            <span v-if="businessPendingCount > 0" class="capsule-pulse-dot"></span>
          </div>
          <span class="nav-label">Kinh doanh</span>
          <div class="nav-trailing">
            <span v-if="!openMenus.business && businessPendingCount > 0" class="capsule-count-badge">
              {{ businessPendingCount > 99 ? '99+' : businessPendingCount }}
            </span>
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.business }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.business && !collapsed && ['admin', 'seller'].includes(userRoleRaw)" class="nav-tree-container">
            <router-link to="/admin/pos" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Bán hàng (POS)</span>
            </router-link>
            <router-link to="/admin/order" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Đơn hàng</span>
              <span v-if="uiStore.adminPendingOrderCount > 0" class="tree-badge">
                {{ uiStore.adminPendingOrderCount > 99 ? '99+' : uiStore.adminPendingOrderCount }}
              </span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/return-requests" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Hoàn hàng</span>
              <span v-if="uiStore.adminPendingReturnCount > 0" class="tree-badge">
                {{ uiStore.adminPendingReturnCount > 99 ? '99+' : uiStore.adminPendingReturnCount }}
              </span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 3. Kho & Sản phẩm -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('inventory')" 
          :class="{ 'nav-item--open': openMenus.inventory, 'nav-item--parent-active': isParentActive('inventory') }"
          :title="collapsed ? 'Kho & Sản phẩm' : ''"
        >
          <div class="nav-icon"><i class="bi bi-box-seam"></i></div>
          <span class="nav-label">Kho &amp; Sản phẩm</span>
          <div class="nav-trailing">
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.inventory }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.inventory && !collapsed" class="nav-tree-container">
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/product" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Sản phẩm</span>
            </router-link>
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/category" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Danh mục</span>
            </router-link>
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/brand" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Thương hiệu</span>
            </router-link>
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/size-guide" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Bảng size</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 4. Sân Cầu Lông (Court System) -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('court')" 
          :class="{ 'nav-item--open': openMenus.court, 'nav-item--parent-active': isParentActive('court') }"
          :title="collapsed ? 'Cụm Sân Cầu Lông' : ''"
        >
          <div class="nav-icon position-relative">
            <i class="bi bi-buildings"></i>
            <span v-if="courtPendingCount > 0" class="capsule-pulse-dot"></span>
          </div>
          <span class="nav-label">Sân Cầu Lông</span>
          <div class="nav-trailing">
            <span v-if="!openMenus.court && courtPendingCount > 0" class="capsule-count-badge">
              {{ courtPendingCount > 99 ? '99+' : courtPendingCount }}
            </span>
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.court }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.court && !collapsed" class="nav-tree-container">
            <router-link to="/admin/court-dashboard" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Dashboard Lễ Tân</span>
            </router-link>
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/courts" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Cấu hình &amp; Quản lý Sân</span>
            </router-link>
            <router-link to="/admin/court-bookings" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Lịch Đặt &amp; Khung Giờ</span>
              <span v-if="uiStore.adminPendingCourtBookingCount > 0" class="tree-badge">
                {{ uiStore.adminPendingCourtBookingCount > 99 ? '99+' : uiStore.adminPendingCourtBookingCount }}
              </span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/court-reports" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Báo Cáo Thống Kê</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 5. Marketing -->
      <div v-if="['admin', 'staff', 'seller'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('marketing')" 
          :class="{ 'nav-item--open': openMenus.marketing, 'nav-item--parent-active': isParentActive('marketing') }"
          :title="collapsed ? 'Marketing' : ''"
        >
          <div class="nav-icon"><i class="bi bi-tag"></i></div>
          <span class="nav-label">Marketing</span>
          <div class="nav-trailing">
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.marketing }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.marketing && !collapsed" class="nav-tree-container">
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/coupon" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Mã giảm giá</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/affiliate" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Quản lý Affiliate</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/stats" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Thống kê doanh thu</span>
            </router-link>
            <router-link v-if="['admin', 'staff'].includes(userRoleRaw)" to="/admin/user-rewards" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Lịch sử đổi quà</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/flash-sale" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Flash Sale</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 6. Tài chính -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('finance')" 
          :class="{ 'nav-item--open': openMenus.finance, 'nav-item--parent-active': isParentActive('finance') }"
          :title="collapsed ? 'Tài chính & Ví' : ''"
        >
          <div class="nav-icon position-relative">
            <i class="bi bi-wallet2"></i>
            <span v-if="financePendingCount > 0" class="capsule-pulse-dot"></span>
          </div>
          <span class="nav-label">Tài chính</span>
          <div class="nav-trailing">
            <span v-if="!openMenus.finance && financePendingCount > 0" class="capsule-count-badge">
              {{ financePendingCount > 99 ? '99+' : financePendingCount }}
            </span>
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.finance }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.finance && !collapsed" class="nav-tree-container">
            <router-link to="/admin/wallet-deposits" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Lịch sử nạp tiền</span>
            </router-link>
            <router-link to="/admin/wallet-withdrawals" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Duyệt rút tiền</span>
              <span v-if="uiStore.adminPendingWithdrawalCount > 0" class="tree-badge">
                {{ uiStore.adminPendingWithdrawalCount > 99 ? '99+' : uiStore.adminPendingWithdrawalCount }}
              </span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 7. Chăm sóc Khách hàng -->
      <div v-if="['admin', 'seller'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('care')" 
          :class="{ 'nav-item--open': openMenus.care, 'nav-item--parent-active': isParentActive('care') }"
          :title="collapsed ? 'Khách hàng' : ''"
        >
          <div class="nav-icon position-relative">
            <i class="bi bi-chat-dots"></i>
            <span v-if="carePendingCount > 0" class="capsule-pulse-dot"></span>
          </div>
          <span class="nav-label">Khách hàng</span>
          <div class="nav-trailing">
            <span v-if="!openMenus.care && carePendingCount > 0" class="capsule-count-badge">
              {{ carePendingCount > 99 ? '99+' : carePendingCount }}
            </span>
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.care }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.care && !collapsed" class="nav-tree-container">
            <router-link to="/admin/users" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Danh sách khách</span>
            </router-link>
            <router-link to="/admin/review" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Đánh giá &amp; Khiếu nại</span>
              <span v-if="careReviewTicketCount > 0" class="tree-badge">
                {{ careReviewTicketCount > 99 ? '99+' : careReviewTicketCount }}
              </span>
            </router-link>
            <router-link to="/admin/chat" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Tin nhắn Chat</span>
              <span v-if="uiStore.pendingChatCount > 0" class="tree-badge">
                {{ uiStore.pendingChatCount > 99 ? '99+' : uiStore.pendingChatCount }}
              </span>
            </router-link>
            <router-link to="/admin/contact" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Yêu cầu liên hệ</span>
              <span v-if="uiStore.pendingContactCount > 0" class="tree-badge">
                {{ uiStore.pendingContactCount > 99 ? '99+' : uiStore.pendingContactCount }}
              </span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 8. Nội dung bài viết -->
      <div v-if="['admin'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('content')" 
          :class="{ 'nav-item--open': openMenus.content, 'nav-item--parent-active': isParentActive('content') }"
          :title="collapsed ? 'Nội dung' : ''"
        >
          <div class="nav-icon"><i class="bi bi-newspaper"></i></div>
          <span class="nav-label">Nội dung</span>
          <div class="nav-trailing">
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.content }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.content && !collapsed" class="nav-tree-container">
            <router-link to="/admin/post" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Bài viết tin tức</span>
            </router-link>
            <router-link to="/admin/post-comments" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Bình luận</span>
            </router-link>
            <router-link to="/admin/post-category" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Chuyên mục</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- 9. Nhân sự & Chấm công -->
      <div v-if="['admin', 'seller', 'staff'].includes(userRoleRaw)" class="nav-group">
        <div 
          class="nav-item nav-item--parent" 
          @click="handleSubmenuClick('staff')" 
          :class="{ 'nav-item--open': openMenus.staff, 'nav-item--parent-active': isParentActive('staff') }"
          :title="collapsed ? 'Nhân sự' : ''"
        >
          <div class="nav-icon"><i class="bi bi-people"></i></div>
          <span class="nav-label">Nhân sự</span>
          <div class="nav-trailing">
            <i class="bi bi-chevron-down nav-chevron" :class="{ 'nav-chevron--rotated': openMenus.staff }"></i>
          </div>
        </div>
        <transition name="tree-expand">
          <div v-if="openMenus.staff && !collapsed" class="nav-tree-container">
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/staff" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Danh sách nhân sự</span>
            </router-link>
            <router-link to="/admin/attendance" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Chấm công</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/attendance-list" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Lịch sử chấm công</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-locations" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Chi nhánh</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/work-shifts" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Ca làm &amp; Phân ca</span>
            </router-link>
            <router-link to="/admin/face-register" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Đăng ký khuôn mặt</span>
            </router-link>
            <router-link v-if="['admin'].includes(userRoleRaw)" to="/admin/face-management" class="tree-item" active-class="tree-item--active">
              <span class="tree-dot"></span><span class="tree-text">Quản lý Face Data</span>
            </router-link>
          </div>
        </transition>
      </div>
    </nav>

    <!-- Footer / User Profile -->
    <div class="sidebar-footer">
      <div class="user-profile" @click="handleLogout" title="Nhấn để đăng xuất">
        <div v-if="userAvatar" class="user-avatar-circle">
          <img :src="userAvatar" alt="Avatar">
        </div>
        <div v-else class="user-avatar-circle">
          {{ userInitial }}
        </div>
        <div class="user-details">
          <span class="user-name-bold">{{ userName }}</span>
          <span class="user-role-badge">{{ userRole }}</span>
        </div>
        <div class="logout-icon-btn">
          <i class="bi bi-box-arrow-right"></i>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* ─── BASE SIDEBAR CONTAINER ─── */
.sidebar {
  width: var(--shell-sidebar-width, 275px);
  height: 100vh;
  background: var(--card-bg, #ffffff);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  border-right: 1px solid var(--border-color, #eef2f6);
  transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow-y: auto;
  overflow-x: hidden;
  position: relative;
  box-shadow: 1px 0 12px rgba(0, 0, 0, 0.03);
}

.sidebar::-webkit-scrollbar {
  width: 4px;
}
.sidebar::-webkit-scrollbar-thumb {
  background-color: var(--border-color, #e2e8f0);
  border-radius: 4px;
}

/* ─── BRAND & HEADER ─── */
.sidebar-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 14px 0 16px;
  height: var(--shell-header-height, 60px);
  min-height: var(--shell-header-height, 60px);
  border-bottom: 1px solid var(--border-color, #f1f5f9);
  flex-shrink: 0;
  box-sizing: border-box;
  background: var(--card-bg, #ffffff);
}

.logo {
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
  min-width: 0;
}

.logo-img {
  width: 44px;
  height: 44px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.08));
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.logo:hover .logo-img {
  transform: scale(1.08);
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
  letter-spacing: -0.4px;
  white-space: nowrap;
  transition: color 0.2s ease;
}

.logo:hover .logo-text {
  color: var(--primary, #E63B6F);
}

.aside-toggle-btn {
  background: #f8fafc;
  border: 1px solid var(--border-color, #e2e8f0);
  color: var(--text-muted, #64748b);
  cursor: pointer;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin-left: 4px;
}

.aside-toggle-btn:hover {
  background: var(--primary, #E63B6F);
  color: #ffffff;
  border-color: var(--primary, #E63B6F);
  transform: scale(1.08);
  box-shadow: 0 3px 10px rgba(230, 59, 111, 0.25);
}

/* ─── NAVIGATION LIST ─── */
.sidebar-nav {
  padding: 14px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.nav-group {
  display: flex;
  flex-direction: column;
}

/* ─── MAIN NAV ITEM (Light Luxury SaaS Theme) ─── */
.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: 10px;
  color: var(--text-main, #475569);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s ease;
  user-select: none;
  position: relative;
  border: 1px solid transparent;
}

.nav-item:hover {
  background: #f8fafc;
  color: var(--text-main, #0f172a);
}

/* Single Active item (Dashboard) */
.nav-item--active {
  background: linear-gradient(135deg, #E63B6F 0%, #d82b5f 100%) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.28);
  border: 1px solid transparent !important;
}

.nav-item--active .nav-icon {
  color: #ffffff !important;
}

/* Parent Active / Open State (Elegant Light Container with Brand Accent) */
.nav-item--parent-active,
.nav-item--open {
  background: #f8fafc !important;
  color: var(--text-main, #0f172a) !important;
  border-radius: 10px;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.nav-item--parent-active .nav-icon,
.nav-item--open .nav-icon {
  color: var(--primary, #E63B6F) !important;
}

.nav-item--parent-active .nav-chevron,
.nav-item--open .nav-chevron {
  color: var(--primary, #E63B6F) !important;
}

.nav-icon {
  font-size: 1.15rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--text-muted, #64748b);
  transition: color 0.18s;
}

.nav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.nav-trailing {
  display: flex;
  align-items: center;
  gap: 6px;
}

.nav-chevron {
  font-size: 0.78rem;
  color: var(--text-muted, #94a3b8);
  transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-chevron--rotated {
  transform: rotate(180deg);
}

/* ─── NESTED TREE HIERARCHY (Light & Brand Glow) ─── */
.nav-tree-container {
  margin-left: 22px;
  padding-left: 12px;
  border-left: 2px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-top: 6px;
  margin-bottom: 6px;
}

.tree-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  color: var(--text-muted, #64748b);
  text-decoration: none;
  font-size: 0.86rem;
  font-weight: 500;
  transition: all 0.18s ease;
  position: relative;
  border: 1px solid transparent;
}

.tree-item:hover {
  color: var(--text-main, #0f172a);
  background: #f8fafc;
}

.tree-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #cbd5e1;
  flex-shrink: 0;
  transition: all 0.18s;
}

.tree-item:hover .tree-dot {
  background: var(--primary, #E63B6F);
}

/* Active Submenu Item (Crisp White Card with Soft Pink Border & Glow) */
.tree-item--active {
  background: #ffffff !important;
  color: var(--primary, #E63B6F) !important;
  font-weight: 700;
  box-shadow: 0 2px 10px rgba(230, 59, 111, 0.08);
  border: 1px solid rgba(230, 59, 111, 0.22) !important;
}

.tree-item--active .tree-dot {
  background: var(--primary, #E63B6F);
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.15);
  transform: scale(1.3);
}

.tree-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

/* ─── NOTIFICATION BADGES & DOTS ─── */
.tree-badge {
  font-size: 0.72rem;
  font-weight: 700;
  background: #ef4444;
  color: white;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  padding: 0 5px;
  border-radius: 999px;
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.capsule-pulse-dot {
  position: absolute;
  top: -2px;
  right: -2px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 0 2px var(--card-bg, #ffffff);
  animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.2);
    opacity: 0.85;
  }
}

.capsule-count-badge {
  font-size: 0.7rem;
  font-weight: 800;
  background: #ef4444;
  color: white;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  border-radius: 999px;
  padding: 0 5px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
  margin-right: 4px;
}

/* ─── SIDEBAR FOOTER (User Profile) ─── */
.sidebar-footer {
  padding: 12px 14px;
  border-top: 1px solid var(--border-color, #f1f5f9);
  flex-shrink: 0;
  background: var(--card-bg, #ffffff);
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.18s;
}

.user-profile:hover {
  background: #f8fafc;
}

.user-avatar-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(230, 59, 111, 0.12);
  color: var(--primary, #E63B6F);
  font-weight: 800;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.user-avatar-circle img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.user-name-bold {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-main, #0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role-badge {
  font-size: 0.72rem;
  color: var(--text-muted, #64748b);
  font-weight: 500;
}

.logout-icon-btn {
  font-size: 1.05rem;
  color: var(--text-muted, #94a3b8);
  transition: color 0.18s;
}

.user-profile:hover .logout-icon-btn {
  color: #ef4444;
}

/* ─── COLLAPSED STATE (ICON RAIL) ─── */
.sidebar--collapsed {
  width: 80px;
}

.sidebar--collapsed .logo-text,
.sidebar--collapsed .nav-label,
.sidebar--collapsed .nav-trailing,
.sidebar--collapsed .nav-tree-container,
.sidebar--collapsed .user-details,
.sidebar--collapsed .logout-icon-btn {
  display: none !important;
}

.sidebar--collapsed .sidebar-brand {
  padding: 0;
  height: var(--shell-header-height, 60px);
  min-height: var(--shell-header-height, 60px);
  justify-content: center;
  position: relative;
}

.sidebar--collapsed .logo {
  justify-content: center;
  width: 100%;
}

.sidebar--collapsed .logo-img {
  width: 44px;
  height: 44px;
  margin: 0 auto;
}

.sidebar--collapsed .aside-toggle-btn {
  display: flex;
  position: absolute;
  top: 50%;
  right: -13px;
  transform: translateY(-50%);
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #ffffff;
  color: #64748b;
  border: 1.5px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  z-index: 100;
  opacity: 0;
  pointer-events: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  margin-left: 0;
}

.sidebar--collapsed:hover .aside-toggle-btn,
.sidebar--collapsed .sidebar-brand:hover .aside-toggle-btn {
  opacity: 1;
  pointer-events: auto;
  transform: translateY(-50%) scale(1.12);
}

.sidebar--collapsed .aside-toggle-btn:hover {
  background: var(--primary, #E63B6F);
  color: #ffffff;
  border-color: var(--primary, #E63B6F);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.35);
}

.sidebar--collapsed .nav-item {
  justify-content: center;
  padding: 10px 0;
  border-radius: 9px;
}

.sidebar--collapsed .nav-icon {
  font-size: 1.25rem;
}

.sidebar--collapsed .nav-item--active,
.sidebar--collapsed .nav-item--parent-active {
  background: rgba(230, 59, 111, 0.12) !important;
}

.sidebar--collapsed .nav-item--active .nav-icon,
.sidebar--collapsed .nav-item--parent-active .nav-icon {
  color: var(--primary, #E63B6F) !important;
}

.sidebar--collapsed .sidebar-footer {
  padding: 8px 0;
  display: flex;
  justify-content: center;
}

.sidebar--collapsed .user-profile {
  padding: 0;
  justify-content: center;
}

/* ─── ANIMATION TRANSITION ─── */
.tree-expand-enter-active,
.tree-expand-leave-active {
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  max-height: 280px;
  opacity: 1;
  overflow: hidden;
}

.tree-expand-enter-from,
.tree-expand-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-4px);
}
</style>
