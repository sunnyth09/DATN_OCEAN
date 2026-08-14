import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { pinia } from '@/stores/index';
import api from '@/axios';

export const useCartUpsellStore = defineStore('cart-upsell', () => {
    const totalPrice = ref(0);
    const freeshipThreshold = ref(500000);
    const suggestions = ref([]);
    const loadingSuggestions = ref(false);
    let upsellRequest = null;

    const progress = computed(() =>
        Math.min(100, Math.round((totalPrice.value / freeshipThreshold.value) * 100))
    );

    const remaining = computed(() =>
        Math.max(0, freeshipThreshold.value - totalPrice.value)
    );

    const hasFreeship = computed(() => totalPrice.value >= freeshipThreshold.value);

    const setTotalPrice = (value) => {
        totalPrice.value = value || 0;
    };

    const fetchUpsellData = async () => {
        const { useAuthStore } = await import('@/stores/auth');
        const authStore = useAuthStore(pinia);

        if (!authStore.isAuthenticated) {
            suggestions.value = [];
            freeshipThreshold.value = 500000;
            return;
        }

        if (upsellRequest) return upsellRequest;

        loadingSuggestions.value = true;
        upsellRequest = (async () => {
            const res = await api.get('/cart/upsell-suggestions');
            if (res.data.status === 'success') {
                freeshipThreshold.value = res.data.data.freeship_threshold ?? 500000;
                suggestions.value = res.data.data.suggestions ?? [];
            }
        })().catch((error) => {
            console.warn('[cartUpsellStore] fetchUpsellData error:', error?.response?.status);
            suggestions.value = [];
        }).finally(() => {
            loadingSuggestions.value = false;
            upsellRequest = null;
        });

        return upsellRequest;
    };

    const quickAddToCart = async (variantId) => {
        const { useAuthStore } = await import('@/stores/auth');
        const authStore = useAuthStore(pinia);
        const { useCartStore } = await import('@/stores/cart');
        const cartStore = useCartStore(pinia);

        if (!authStore.isAuthenticated) {
            let cartItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
            const index = cartItems.findIndex(item => item.variant_id === variantId);
            if (index !== -1) {
                cartItems[index].quantity += 1;
            } else {
                cartItems.push({
                    variant_id: variantId,
                    quantity: 1,
                    selected: true
                });
            }
            localStorage.setItem('cart_items', JSON.stringify(cartItems));
            await cartStore.fetchCount();
            return { status: 'success', message: 'Đã thêm vào giỏ hàng (tạm thời)' };
        }

        const res = await api.post('/cart/items', { variant_id: variantId, quantity: 1 });
        await cartStore.fetchCount();
        return res.data;
    };


    const resetUpsellState = () => {
        totalPrice.value = 0;
        freeshipThreshold.value = 500000;
        suggestions.value = [];
        loadingSuggestions.value = false;
    };

    return {
        totalPrice,
        freeshipThreshold,
        suggestions,
        loadingSuggestions,
        progress,
        remaining,
        hasFreeship,
        setTotalPrice,
        fetchUpsellData,
        quickAddToCart,
        resetUpsellState,
    };
});
