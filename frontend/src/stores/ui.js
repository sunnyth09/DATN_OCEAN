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

  return {
    isSearchModalOpen,
    isBackofficeDarkMode,
    isBackofficeSidebarOpen,
    isAdminStoreMenuOpen,
    isAdminStaffMenuOpen,
    adminUnreadNotificationCount,
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
  };
});

