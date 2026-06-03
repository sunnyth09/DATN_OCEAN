import { ref } from 'vue';
import { defineStore } from 'pinia';
import { pinia } from '@/stores/index';
import { useAuthStore } from '@/stores/auth';

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

    if (!authStore.isAuthenticated) {
      count.value = 0;
      return 0;
    }

    if (countRequest) {
      return countRequest;
    }

    isFetchingCount.value = true;
    countRequest = (async () => {
      const { default: api } = await import('@/axios');
      const response = await api.get('/cart/count');
      count.value = response.data?.count || 0;
      return count.value;
    })().catch(() => {
      count.value = 0;
      return 0;
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

    const { default: api } = await import('@/axios');
    const response = await api.post('/cart/items', {
      variant_id: variantId,
      quantity,
    });

    window.dispatchEvent(new Event('cart-updated'));
    return response.data;
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
    bindWindowListeners,
  };
});
