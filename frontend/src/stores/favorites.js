import { ref } from 'vue';
import { defineStore } from 'pinia';
import Swal from 'sweetalert2';
import api from '@/axios';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

export const useFavoritesStore = defineStore('favorites', () => {
    const favoriteIds = ref([]);
    const isInitialized = ref(false);
    const isFetching = ref(false);

    const isLoggedIn = () => !!sessionStorage.getItem('auth_token');

    const fetchFavoriteIds = async (force = false) => {
        if (!isLoggedIn()) {
            favoriteIds.value = [];
            isInitialized.value = false;
            return [];
        }

        if ((isInitialized.value || isFetching.value) && !force) {
            return favoriteIds.value;
        }

        isFetching.value = true;
        try {
            const response = await api.get('/profile/favorites/ids');
            if (response.data?.status === 'success') {
                favoriteIds.value = response.data.data || [];
                isInitialized.value = true;
            }
            return favoriteIds.value;
        } catch (error) {
            console.error('Loi khi tai danh sach yeu thich:', error);
            return favoriteIds.value;
        } finally {
            isFetching.value = false;
        }
    };

    const toggleFavorite = async (productId) => {
        if (!isLoggedIn()) {
            Toast.fire({
                icon: 'warning',
                title: 'Vui lòng đăng nhập để yêu thích sản phẩm'
            });
            return false;
        }

        const index = favoriteIds.value.indexOf(productId);
        const originallyFavorited = index !== -1;

        if (originallyFavorited) {
            favoriteIds.value.splice(index, 1);
        } else {
            favoriteIds.value.push(productId);
        }

        try {
            const response = await api.post('/profile/favorites/toggle', { product_id: productId });
            if (response.data?.status === 'success') {
                Toast.fire({
                    icon: originallyFavorited ? 'info' : 'success',
                    title: originallyFavorited ? 'Đã bỏ yêu thích sản phẩm' : 'Đã thêm vào yêu thích'
                });
                isInitialized.value = true;
                return true;
            }
        } catch (error) {
            console.error('Loi khi toggle yeu thich:', error);

            if (originallyFavorited) {
                favoriteIds.value.push(productId);
            } else {
                const rollbackIndex = favoriteIds.value.indexOf(productId);
                if (rollbackIndex !== -1) {
                    favoriteIds.value.splice(rollbackIndex, 1);
                }
            }

            Toast.fire({
                icon: 'error',
                title: 'Có lỗi xảy ra, vui lòng thử lại.'
            });
        }

        return false;
    };

    const isFavorited = (productId) => favoriteIds.value.includes(productId);

    const resetFavorites = () => {
        favoriteIds.value = [];
        isInitialized.value = false;
        isFetching.value = false;
    };

    return {
        favoriteIds,
        isInitialized,
        isFetching,
        fetchFavoriteIds,
        toggleFavorite,
        isFavorited,
        resetFavorites,
    };
});
