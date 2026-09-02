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
    adminUnreadNotificationCount.value = count;
  };
  const decrementAdminUnreadNotificationCount = (amount = 1) => {
    adminUnreadNotificationCount.value = Math.max(0, adminUnreadNotificationCount.value - amount);
  };
  const incrementAdminUnreadNotificationCount = (amount = 1) => {
    adminUnreadNotificationCount.value += amount;
  };

  const pendingContactCount = ref(0);
  const adminPendingContactCount = pendingContactCount;
  const setPendingContactCount = (count) => {
    pendingContactCount.value = count;
  };
  const setAdminPendingContactCount = setPendingContactCount;

  const pendingChatCount = ref(0);
  const adminUnreadChatCount = pendingChatCount;
  const setPendingChatCount = (count) => {
    pendingChatCount.value = count;
  };
  const setAdminUnreadChatCount = setPendingChatCount;

  const pendingReviewCount = ref(0);
  const adminPendingReviewCount = pendingReviewCount;
  const setPendingReviewCount = (count) => {
    pendingReviewCount.value = count;
  };
  const setAdminPendingReviewCount = setPendingReviewCount;

  const adminPendingTicketCount = ref(0);
  const setAdminPendingTicketCount = (count) => {
    adminPendingTicketCount.value = count;
  };

  const adminPendingReturnCount = ref(0);
  const setAdminPendingReturnCount = (count) => {
    adminPendingReturnCount.value = count;
  };

  const adminPendingOrderCount = ref(0);
  const setAdminPendingOrderCount = (count) => {
    adminPendingOrderCount.value = count;
  };

  const adminPendingCourtBookingCount = ref(0);
  const setAdminPendingCourtBookingCount = (count) => {
    adminPendingCourtBookingCount.value = count;
  };

  const adminPendingWithdrawalCount = ref(0);
  const setAdminPendingWithdrawalCount = (count) => {
    adminPendingWithdrawalCount.value = count;
  };

  return {
    isSearchModalOpen,
    isBackofficeDarkMode,
    isBackofficeSidebarOpen,
    isAdminStoreMenuOpen,
    isAdminStaffMenuOpen,
    adminUnreadNotificationCount,
    pendingContactCount,
    adminPendingContactCount,
    pendingChatCount,
    adminUnreadChatCount,
    pendingReviewCount,
    adminPendingReviewCount,
    adminPendingTicketCount,
    adminPendingReturnCount,
    adminPendingOrderCount,
    adminPendingCourtBookingCount,
    adminPendingWithdrawalCount,
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
    setPendingContactCount,
    setAdminPendingContactCount,
    setPendingChatCount,
    setAdminUnreadChatCount,
    setPendingReviewCount,
    setAdminPendingReviewCount,
    setAdminPendingTicketCount,
    setAdminPendingReturnCount,
    setAdminPendingOrderCount,
    setAdminPendingCourtBookingCount,
    setAdminPendingWithdrawalCount,
  };
});


