import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { broadcastLogout } from '@/sessionSync';
import { authService } from '@/services/authService';
import { getAppBaseUrl } from '@/utils/url';

const ADMIN_ROLES = ['admin', 'seller', 'staff'];
const STORAGE_KEYS = {
  token: 'auth_token',
  user: 'user',
};

const parseStoredUser = (raw) => {
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
};

const getStorageUser = () => parseStoredUser(sessionStorage.getItem(STORAGE_KEYS.user));
const getStorageToken = () => sessionStorage.getItem(STORAGE_KEYS.token) || '';

const persistSession = (token, user) => {
  if (token) {
    sessionStorage.setItem(STORAGE_KEYS.token, token);
  } else {
    sessionStorage.removeItem(STORAGE_KEYS.token);
  }

  if (user) {
    sessionStorage.setItem(STORAGE_KEYS.user, JSON.stringify(user));
  } else {
    sessionStorage.removeItem(STORAGE_KEYS.user);
  }

  localStorage.removeItem(STORAGE_KEYS.token);
  localStorage.removeItem(STORAGE_KEYS.user);
};

const clearPersistedSession = () => {
  localStorage.removeItem(STORAGE_KEYS.token);
  localStorage.removeItem(STORAGE_KEYS.user);
  localStorage.removeItem('ocean_live_chat_token');
  sessionStorage.removeItem(STORAGE_KEYS.token);
  sessionStorage.removeItem(STORAGE_KEYS.user);
  sessionStorage.removeItem('ocean_chatbot_messages');
  sessionStorage.removeItem('ocean_chatbot_history');
};

const resolveAvatarUrl = (path) => {
  if (!path) return '';
  if (path.startsWith('http')) return path;

  return `${getAppBaseUrl()}${path}`;
};

export const getDefaultRouteForRole = (role) => {
  if (role === 'admin') return { name: 'admin' };
  if (role === 'seller') return { name: 'admin-pos' };
  if (role === 'staff') return { name: 'admin-product' };
  return { name: 'home' };
};

export const useAuthStore = defineStore('auth', () => {
  const token = ref('');
  const user = ref(null);
  const unreadNotificationCount = ref(0);
  const isHydrated = ref(false);

  const isAuthenticated = computed(() => !!token.value && !!user.value);
  const role = computed(() => user.value?.role || '');
  const isAdminUser = computed(() => ADMIN_ROLES.includes(role.value));
  const displayName = computed(() => user.value?.full_name || user.value?.name || user.value?.email || 'Người dùng');
  const email = computed(() => user.value?.email || '');
  const avatarUrl = computed(() => resolveAvatarUrl(user.value?.avatar_url || ''));
  const userInitial = computed(() => (displayName.value?.[0] || '?').toUpperCase());
  const preferredRoute = computed(() => getDefaultRouteForRole(role.value));

  const hydrate = () => {
    token.value = getStorageToken();
    user.value = getStorageUser();
    isHydrated.value = true;
  };

  const setToken = (nextToken) => {
    token.value = nextToken || '';
    persistSession(token.value, user.value);
  };

  const setSession = async (nextToken, nextUser, options = {}) => {
    const { notify = true } = options;

    token.value = nextToken || '';
    user.value = nextUser || null;
    persistSession(token.value, user.value);
    isHydrated.value = true;

    if (notify) {
      hydrate()
    }

    if (token.value && user.value) {
      try {
        const { useCartStore } = await import('@/stores/cart');
        const cartStore = useCartStore();
        await cartStore.syncCart();
      } catch (err) {
        console.error("Failed to load cart store for sync", err);
      }
    }
  };

  const setUser = (nextUser, options = {}) => {
    const { notify = true } = options;

    user.value = nextUser || null;
    persistSession(token.value, user.value);
    isHydrated.value = true;

    if (notify) {
      hydrate()
    }
  };

  const clearSession = (options = {}) => {
    const { notify = true } = options;

    token.value = '';
    user.value = null;
    unreadNotificationCount.value = 0;
    clearPersistedSession();
    isHydrated.value = true;

    if (notify) {
      hydrate()
    }
  };

  const logout = async (options = {}) => {
    const { broadcast = true } = options;

    try {
      await authService.logout();
    } catch {
      // Ignore logout API failures and still clear local session.
    }

    if (broadcast) {
      broadcastLogout();
    }

    clearSession();
  };

  const fetchUnreadNotificationCount = async () => {
    if (!isAuthenticated.value) {
      unreadNotificationCount.value = 0;
      return 0;
    }

    try {
      const response = await authService.fetchProfileNotifications();
      const newCount = response.data?.unread_count || 0;
      if (newCount > unreadNotificationCount.value) {
        window.dispatchEvent(new CustomEvent('has-new-unread-notifications', { detail: { count: newCount } }));
      }
      unreadNotificationCount.value = newCount;
      return unreadNotificationCount.value;
    } catch {
      unreadNotificationCount.value = 0;
      return 0;
    }
  };

  const incrementUnreadNotificationCount = (amount = 1) => {
    unreadNotificationCount.value += amount;
    window.dispatchEvent(new Event('play-notif-sound'));
  };

  const decrementUnreadNotificationCount = (amount = 1) => {
    unreadNotificationCount.value = Math.max(0, unreadNotificationCount.value - amount);
  };

  const resetUnreadNotificationCount = () => {
    unreadNotificationCount.value = 0;
  };

  return {
    token,
    user,
    unreadNotificationCount,
    isHydrated,
    isAuthenticated,
    role,
    isAdminUser,
    displayName,
    email,
    avatarUrl,
    userInitial,
    preferredRoute,
    hydrate,
    setToken,
    setSession,
    setUser,
    clearSession,
    logout,
    fetchUnreadNotificationCount,
    incrementUnreadNotificationCount,
    decrementUnreadNotificationCount,
    resetUnreadNotificationCount,
  };
});
