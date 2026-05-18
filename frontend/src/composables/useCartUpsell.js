import { storeToRefs } from 'pinia';
import { useCartUpsellStore } from '@/stores/cartUpsell';

export function useCartUpsell() {
    const store = useCartUpsellStore();
    const { progress, remaining, hasFreeship } = storeToRefs(store);

    return {
        state: store,
        progress,
        remaining,
        hasFreeship,
        setTotalPrice: store.setTotalPrice,
        fetchUpsellData: store.fetchUpsellData,
        quickAddToCart: store.quickAddToCart,
        resetUpsellState: store.resetUpsellState,
    };
}
