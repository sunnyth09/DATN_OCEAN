import { storeToRefs } from 'pinia';
import { useFavoritesStore } from '@/stores/favorites';
import { useAuthStore } from '@/stores/auth';

export function useFavorites() {
    const store = useFavoritesStore();
    const authStore = useAuthStore();
    const { favoriteIds, isInitialized, isFetching } = storeToRefs(store);

    if (!isInitialized.value && !isFetching.value && authStore.isAuthenticated) {
        store.fetchFavoriteIds();
    }

    return {
        favoriteIds,
        isInitialized,
        isFetching,
        fetchFavoriteIds: store.fetchFavoriteIds,
        toggleFavorite: store.toggleFavorite,
        isFavorited: store.isFavorited,
        resetFavorites: store.resetFavorites,
    };
}
