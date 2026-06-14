<template>
  <div class="backoffice-shell">
    <Transition name="shell-scrim">
      <button
        v-if="isSidebarOpen"
        type="button"
        class="backoffice-scrim"
        aria-label="Đóng menu điều hướng"
        @click="closeSidebar"
      ></button>
    </Transition>

    <div
      class="backoffice-sidebar-shell"
      :class="{ 
        'backoffice-sidebar-shell--open': isSidebarOpen,
        'backoffice-sidebar-shell--collapsed': !isSidebarOpen
      }"
    >
      <component :is="sidebarComponent" :collapsed="!isSidebarOpen" />
    </div>

    <div class="backoffice-main">
      <header class="backoffice-header">
        <div class="backoffice-header__leading">
          <div class="backoffice-header__meta">
            <p v-if="sectionLabel" class="backoffice-header__eyebrow">
              {{ sectionLabel }}
            </p>
          </div>
        </div>

        <div class="backoffice-header__actions">
          <router-link to="/admin/notifications" class="shell-icon-btn position-relative" title="Thông báo">
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span v-if="unreadCount > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25em 0.4em;">
              {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
          </router-link>

          <button
            type="button"
            class="shell-icon-btn"
            :aria-pressed="isDarkMode"
            :title="isDarkMode ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
            @click="toggleDarkMode"
          >
            <svg
              v-if="isDarkMode"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg
              v-else
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
          </button>

          <router-link to="/" class="back-home-btn">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Trang chủ</span>
          </router-link>
        </div>
      </header>

      <main class="backoffice-content">
        <div class="backoffice-content__inner">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  sidebarComponent: {
    type: [Object, Function],
    required: true,
  },
  defaultTitle: {
    type: String,
    default: 'Dashboard',
  },
  sectionLabel: {
    type: String,
    default: '',
  },
});

const route = useRoute();
const uiStore = useUiStore();
const {
  isBackofficeDarkMode: isDarkMode,
  isBackofficeSidebarOpen: isSidebarOpen,
} = storeToRefs(uiStore);

const resolvedTitle = computed(() => {
  for (let i = route.matched.length - 1; i >= 0; i -= 1) {
    const matchedTitle = route.matched[i]?.meta?.title;
    if (matchedTitle) return matchedTitle;
  }

  return props.defaultTitle;
});

const toggleDarkMode = () => {
  uiStore.toggleBackofficeDarkMode();
};

const toggleSidebar = () => {
  uiStore.toggleBackofficeSidebar();
};

const closeSidebar = () => {
  uiStore.setBackofficeSidebarOpen(false);
};

const syncSidebarForViewport = () => {
  if (window.innerWidth >= 1024) {
    uiStore.setBackofficeSidebarOpen(true);
  } else {
    closeSidebar();
  }
};

watch(
  () => route.fullPath,
  () => {
    if (window.innerWidth < 1024) {
      closeSidebar();
    }
  },
);

const unreadCount = ref(0);

const fetchUnreadCount = async () => {
  try {
    const response = await axios.get('/api/admin/notifications?unread_only=true&per_page=1');
    if (response.data.success) {
      unreadCount.value = response.data.unread_count || response.data.total || 0;
    }
  } catch (error) {
    console.error('Failed to fetch notifications count', error);
  }
};

onMounted(() => {
  syncSidebarForViewport();
  window.addEventListener('resize', syncSidebarForViewport);
  
  fetchUnreadCount();

  if (window.Echo) {
    const handleNotification = (e, eventType) => {
        // Increment unread count
        unreadCount.value++;
        
        let message = `Đơn đặt sân ${e.booking_code} có cập nhật mới.`;
        if (eventType === 'CourtBookingCreated' || e.status === 'pending') {
            message = `Có đơn đặt sân mới: ${e.booking_code}`;
        } else if (eventType === 'CourtBookingCancelled' || e.status === 'cancelled') {
            message = `Đơn đặt sân ${e.booking_code} đã bị hủy.`;
        }

        // Show Toast
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'info',
          title: 'Thông báo',
          text: message,
          showConfirmButton: false,
          timer: 5000,
          timerProgressBar: true,
          didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
          }
        });
    };

    window.Echo.private('admin-notifications')
      .listen('.CourtBookingCreated', (e) => handleNotification(e, 'CourtBookingCreated'))
      .listen('.CourtBookingCancelled', (e) => handleNotification(e, 'CourtBookingCancelled'))
      .listen('.booking.status.updated', (e) => {
          if (e.status === 'cancelled') {
              handleNotification(e, 'CourtBookingCancelled');
          }
      });
  }
});

onUnmounted(() => {
  window.removeEventListener('resize', syncSidebarForViewport);
  if (window.Echo) {
    window.Echo.leave('admin-notifications');
  }
});
</script>

<style scoped>
.backoffice-shell {
  display: flex;
  min-height: 100vh;
  background: var(--ocean-deepest);
  color: var(--text-main);
  font-family: var(--font-primary);
}

.backoffice-sidebar-shell {
  width: var(--shell-sidebar-width, 250px);
  flex-shrink: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  transition: width 0.3s ease;
}

.backoffice-sidebar-shell--collapsed {
  width: 80px;
}

.backoffice-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.backoffice-header {
  min-height: var(--shell-header-height);
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid var(--border-color);
  backdrop-filter: blur(14px);
}

.backoffice-header__leading {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.backoffice-header__meta {
  min-width: 0;
}

.backoffice-header__eyebrow {
  margin: 0 0 4px;
  color: var(--text-light);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.backoffice-header__title {
  margin: 0;
  color: var(--text-main);
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.2;
}

.backoffice-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.shell-icon-btn,
.back-home-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 40px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background: var(--card-bg);
  color: var(--text-muted);
  transition: all 0.2s ease;
}

.shell-icon-btn {
  width: 40px;
  cursor: pointer;
}

.back-home-btn {
  padding: 0 14px;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
}

.shell-icon-btn:hover,
.back-home-btn:hover {
  color: var(--primary);
  border-color: rgba(230, 59, 111, 0.28);
  background: var(--hover-bg);
}

.backoffice-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.backoffice-content__inner {
  max-width: var(--layout-max-width);
  margin: 0 auto;
}

.backoffice-scrim {
  display: none;
}

@media (max-width: 1024px) {
  .backoffice-header {
    padding: 0 20px;
  }

  .backoffice-content {
    padding: 20px;
  }

  .backoffice-sidebar-shell {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 40;
    width: min(86vw, var(--shell-sidebar-width, 250px));
    transform: translateX(-100%);
    transition: transform 0.24s ease;
    pointer-events: none;
  }

  .backoffice-sidebar-shell--open {
    transform: translateX(0);
    pointer-events: auto;
  }

  .backoffice-sidebar-shell--collapsed {
    width: min(86vw, var(--shell-sidebar-width, 250px)); /* Ignore collapsed state on mobile */
  }

  .backoffice-scrim {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 30;
    border: 0;
    background: rgba(15, 23, 42, 0.38);
    backdrop-filter: blur(2px);
  }
}

@media (max-width: 768px) {
  .backoffice-header {
    padding: 14px 16px;
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .backoffice-header__title {
    font-size: 1.05rem;
  }

  .backoffice-header__actions {
    width: 100%;
    justify-content: flex-end;
  }

  .backoffice-content {
    padding: 16px;
  }

  .back-home-btn span {
    display: none;
  }
}

.shell-scrim-enter-active,
.shell-scrim-leave-active {
  transition: opacity 0.2s ease;
}

.shell-scrim-enter-from,
.shell-scrim-leave-to {
  opacity: 0;
}
</style>
