import { storeToRefs } from 'pinia';
import { useFavoritesStore } from '@/stores/favorites';

export function useFavorites() {
    const store = useFavoritesStore();
    const { favoriteIds, isInitialized, isFetching } = storeToRefs(store);

    if (!isInitialized.value && !isFetching.value && sessionStorage.getItem('auth_token')) {
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
