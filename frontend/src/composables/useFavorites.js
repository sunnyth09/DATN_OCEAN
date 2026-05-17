import { ref } from 'vue';
import api from '@/axios'; // Giả sử axios instance được cấu hình sẵn token JWT
import Swal from 'sweetalert2';

// Cấu hình Toast chung
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

// State global, chỉ khởi tạo 1 lần
const favoriteIds = ref([]);
const isInitialized = ref(false);

// Helper: kiểm tra user đã đăng nhập chưa (chỉ cần dùng sessionStorage)
const isLoggedIn = () => !!sessionStorage.getItem('auth_token');

export function useFavorites() {
    /**
     * Mặc định load ids yêu thích của user
     */
    const fetchFavoriteIds = async () => {
        if (!isLoggedIn()) return; // Chỉ load khi có đăng nhập
        try {
            const response = await api.get('/profile/favorites/ids');
            if (response.data && response.data.status === 'success') {
                favoriteIds.value = response.data.data;
                isInitialized.value = true;
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách yêu thích:', error);
        }
    };

    /**
     * Toggle trái tim (thêm/xoá)
     */
    const toggleFavorite = async (productId) => {
        if (!isLoggedIn()) {
            Toast.fire({
                icon: 'warning',
                title: 'Vui lòng đăng nhập để yêu thích sản phẩm'
            });
            return false;
        }

        // Cập nhật Optimistic UI trước khi call API (cho nhanh nhạy)
        const index = favoriteIds.value.indexOf(productId);
        const originallyFavorited = index !== -1;

        if (originallyFavorited) {
            favoriteIds.value.splice(index, 1);
        } else {
            favoriteIds.value.push(productId);
        }

        try {
            const response = await api.post('/profile/favorites/toggle', { product_id: productId });
            if (response.data && response.data.status === 'success') {
                if (originallyFavorited) {
                    Toast.fire({
                        icon: 'info',
                        title: 'Đã bỏ yêu thích sản phẩm'
                    });
                } else {
                    Toast.fire({
                        icon: 'success',
                        title: 'Đã thêm vào yêu thích'
                    });
                }
                return true;
            }
        } catch (error) {
            console.error('Lỗi khi toggle yêu thích:', error);
            // Rollback state nếu lỗi
            if (originallyFavorited) {
                favoriteIds.value.push(productId);
            } else {
                const idx = favoriteIds.value.indexOf(productId);
                if (idx !== -1) favoriteIds.value.splice(idx, 1);
            }
            Toast.fire({
                icon: 'error',
                title: 'Có lỗi xảy ra, vui lòng thử lại.'
            });
            return false;
        }
    };

    /**
     * Kiểm tra xem 1 product_id có được yêu thích chưa
     */
    const isFavorited = (productId) => {
        return favoriteIds.value.includes(productId);
    };

    // Auto load khi dùng hook (nếu chưa init)
    if (!isInitialized.value && isLoggedIn()) {
        fetchFavoriteIds();
    }

    return {
        favoriteIds,
        fetchFavoriteIds,
        toggleFavorite,
        isFavorited
    };
}
