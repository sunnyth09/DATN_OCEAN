import { ref } from 'vue';
import { defineStore } from 'pinia';
import { pinia } from './index';
import { useAuthStore } from './auth';

let hasBoundCartEvents = false;

export const useCartStore = defineStore('cart', () => {
  const count = ref(0);
  const isFetchingCount = ref(false);
  let countRequest = null;

  const reset = () => {
    count.value = 0;
  };

  const fetchCount = async () => {
    const authStore = useAuthStore(pinia);
    if (!authStore.isHydrated) {
      authStore.hydrate();
    }

    const token = authStore.token || localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

    // Admin/Staff không có giỏ hàng khách hàng
    if (authStore.user?.role === 'admin' || authStore.user?.role === 'super_admin' || authStore.user?.role === 'staff' || authStore.isAdmin || window.location.pathname.startsWith('/admin')) {
      count.value = 0;
      return 0;
    }

    if (!token) {
      const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
      count.value = localItems.length;
      return count.value;
    }

    if (countRequest) {
      return countRequest;
    }

    isFetchingCount.value = true;
    countRequest = (async () => {
      const { default: api } = await import('../axios');
      const response = await api.get('/cart/count');
      count.value = response.data?.count || 0;
      return count.value;
    })().catch((err) => {
      if (err?.response?.status === 403) {
        count.value = 0;
        return 0;
      }
      const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
      count.value = localItems.length;
      return count.value;
    }).finally(() => {
      isFetchingCount.value = false;
      countRequest = null;
    });

    return countRequest;
  };

  const addItem = async ({ variantId, quantity = 1 }) => {
    const authStore = useAuthStore(pinia);

    if (!authStore.isAuthenticated) {
      return { status: 'unauthenticated' };
    }

    const { default: api } = await import('../axios');
    const response = await api.post('/cart/items', {
      variant_id: variantId,
      quantity,
    });

    await fetchCount();
    window.dispatchEvent(new Event('cart-updated'));
    return response.data;
  };

  const syncCart = async () => {
    const authStore = useAuthStore(pinia);
    if (!authStore.isAuthenticated) return;

    const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
    if (localItems.length === 0) return;

    try {
      const { default: api } = await import('../axios');
      await api.post('/cart/sync', {
        items: localItems.map(item => ({
          variant_id: item.variant_id,
          quantity: item.quantity
        }))
      });
      localStorage.removeItem('cart_items');
      await fetchCount();
      window.dispatchEvent(new Event('cart-updated'));
    } catch (error) {
      console.error("Failed to sync cart:", error);
    }
  };

  const bindWindowListeners = () => {
    if (hasBoundCartEvents) return;

    window.addEventListener('cart-updated', fetchCount);
    window.addEventListener('auth-logout', reset);
    hasBoundCartEvents = true;
  };

  return {
    count,
    isFetchingCount,
    reset,
    fetchCount,
    addItem,
    syncCart,
    bindWindowListeners,
  };
});
