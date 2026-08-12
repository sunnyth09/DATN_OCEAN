import { ref } from 'vue';
import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', () => {
  const isSearchModalOpen = ref(false);
  const isBackofficeDarkMode = ref(false);
  const isBackofficeSidebarOpen = ref(false);
  const isAdminStoreMenuOpen = ref(true);
  const isAdminStaffMenuOpen = ref(false);

  const setSearchModalOpen = (value) => {
    isSearchModalOpen.value = !!value;
  };

  const toggleSearchModal = () => {
    isSearchModalOpen.value = !isSearchModalOpen.value;
  };

  const setBackofficeDarkMode = (value) => {
    isBackofficeDarkMode.value = !!value;
    document.documentElement.classList.toggle('dark', isBackofficeDarkMode.value);
    localStorage.setItem('admin_theme', isBackofficeDarkMode.value ? 'dark' : 'light');
  };

  const initializeBackofficeTheme = () => {
    const storedTheme = localStorage.getItem('admin_theme') || 'light';
    setBackofficeDarkMode(storedTheme === 'dark');
  };

  const toggleBackofficeDarkMode = () => {
    setBackofficeDarkMode(!isBackofficeDarkMode.value);
  };

  const setBackofficeSidebarOpen = (value) => {
    isBackofficeSidebarOpen.value = !!value;
  };

  const toggleBackofficeSidebar = () => {
    isBackofficeSidebarOpen.value = !isBackofficeSidebarOpen.value;
  };

  const setAdminStoreMenuOpen = (value) => {
    isAdminStoreMenuOpen.value = !!value;
  };

  const toggleAdminStoreMenu = () => {
    isAdminStoreMenuOpen.value = !isAdminStoreMenuOpen.value;
  };

  const setAdminStaffMenuOpen = (value) => {
    isAdminStaffMenuOpen.value = !!value;
  };

  const toggleAdminStaffMenu = () => {
    isAdminStaffMenuOpen.value = !isAdminStaffMenuOpen.value;
  };

  const adminUnreadNotificationCount = ref(0);
  const setAdminUnreadNotificationCount = (count) => {
    if (count > adminUnreadNotificationCount.value) {
      window.dispatchEvent(new CustomEvent('play-notif-sound', {
        detail: { count, message: `(${count}) Bạn có ${count} thông báo mới!` }
      }));
    } else if (count === 0) {
      window.dispatchEvent(new Event('stop-title-notification'));
    }
    adminUnreadNotificationCount.value = count;
  };
  const decrementAdminUnreadNotificationCount = (amount = 1) => {
    adminUnreadNotificationCount.value = Math.max(0, adminUnreadNotificationCount.value - amount);
    const count = adminUnreadNotificationCount.value;
    if (count === 0) {
      window.dispatchEvent(new Event('stop-title-notification'));
    } else {
      window.dispatchEvent(new CustomEvent('new-title-notification', {
        detail: { count, message: `(${count}) Bạn có ${count} thông báo mới!` }
      }));
    }
  };
  const incrementAdminUnreadNotificationCount = (amount = 1) => {
    adminUnreadNotificationCount.value += amount;
    const count = adminUnreadNotificationCount.value;
    window.dispatchEvent(new CustomEvent('play-notif-sound', {
      detail: {
        count,
        message: `(${count}) Bạn có ${count} thông báo mới!`
      }
    }));
  };

  const adminUnreadChatCount = ref(0);
  const setAdminUnreadChatCount = (count) => {
    adminUnreadChatCount.value = count;
  };
  const decrementAdminUnreadChatCount = (amount = 1) => {
    adminUnreadChatCount.value = Math.max(0, adminUnreadChatCount.value - amount);
  };
  const incrementAdminUnreadChatCount = (amount = 1) => {
    adminUnreadChatCount.value += amount;
  };

  const adminPendingReviewCount = ref(0);
  const setAdminPendingReviewCount = (count) => {
    adminPendingReviewCount.value = count;
  };

  const adminPendingTicketCount = ref(0);
  const setAdminPendingTicketCount = (count) => {
    adminPendingTicketCount.value = count;
  };

  const adminPendingContactCount = ref(0);
  const setAdminPendingContactCount = (count) => {
    adminPendingContactCount.value = count;
  };

  return {
    isSearchModalOpen,
    isBackofficeDarkMode,
    isBackofficeSidebarOpen,
    isAdminStoreMenuOpen,
    isAdminStaffMenuOpen,
    adminUnreadNotificationCount,
    adminUnreadChatCount,
    adminPendingReviewCount,
    adminPendingTicketCount,
    adminPendingContactCount,
    setSearchModalOpen,
    toggleSearchModal,
    setBackofficeDarkMode,
    initializeBackofficeTheme,
    toggleBackofficeDarkMode,
    setBackofficeSidebarOpen,
    toggleBackofficeSidebar,
    setAdminStoreMenuOpen,
    toggleAdminStoreMenu,
    setAdminStaffMenuOpen,
    toggleAdminStaffMenu,
    setAdminUnreadNotificationCount,
    decrementAdminUnreadNotificationCount,
    incrementAdminUnreadNotificationCount,
    setAdminUnreadChatCount,
    decrementAdminUnreadChatCount,
    incrementAdminUnreadChatCount,
    setAdminPendingReviewCount,
    setAdminPendingTicketCount,
    setAdminPendingContactCount,
  };
});

