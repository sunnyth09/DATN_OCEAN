<script setup>
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';
import Swal from 'sweetalert2';
import FreeshipBar from '@/features/shop/components/FreeshipBar.vue';
import QuickAddSlider from '@/features/shop/components/QuickAddSlider.vue';
import { useCartUpsell } from '@/composables/useCartUpsell';
import { productService } from '@/services/productService';
import ProductCard from '@/components/ProductCard.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { getStorageUrl } from '@/utils/url';
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';
const router = useRouter();
const authStore = useAuthStore();

const cartItems = ref([]);
const cartId = ref(null);
const loading = ref(true);
const updating = ref({});
const selectAll = ref(true);
const toast = ref({ show: false, message: '', type: 'success' });
let cartRequest = null;
let cartRefreshPending = false;
const quantityInputTimers = new Map();
const QUANTITY_INPUT_DEBOUNCE_MS = 600;

// ====== UPSELL & GAMIFICATION ======
const { state: upsellState, setTotalPrice, fetchUpsellData } = useCartUpsell();

// ====== VARIANT CHANGE MODAL ======
const variantModal = ref({
    show: false,
    item: null,             // cart item đang được chỉnh sửa
    variants: [],           // danh sách variants của sản phẩm
    loadingVariants: false,
    selectedColor: null,
    selectedSize: null,
    confirming: false,
});

const openVariantModal = async (item) => {
    if (!item.product?.product_id) return;
    variantModal.value.show = true;
    variantModal.value.item = item;
    variantModal.value.variants = [];
    variantModal.value.loadingVariants = true;

    // Pre-select màu/size hiện tại
    variantModal.value.selectedColor = item.variant?.color || null;
    variantModal.value.selectedSize = item.variant?.size || null;

    try {
        const res = await api.get(`/products/${item.product.product_id}/variants`);
        variantModal.value.variants = res.data.data || [];
    } catch (e) {
        showToast('Không thể tải thông tin sản phẩm.', 'error');
        variantModal.value.show = false;
    } finally {
        variantModal.value.loadingVariants = false;
    }
};

const closeVariantModal = () => {
    variantModal.value.show = false;
    variantModal.value.item = null;
};

// Danh sách màu duy nhất
const modalUniqueColors = computed(() => {
    const colors = [...new Set(variantModal.value.variants.map(v => v.color).filter(Boolean))];
    return colors;
});

// Có sản phẩm có màu không?
const modalHasColors = computed(() => modalUniqueColors.value.length > 0);

// Danh sách size theo màu đã chọn
const modalAvailableSizes = computed(() => {
    const variants = variantModal.value.variants;
    if (!variants.length) return [];

    // Nếu có màu: lọc theo màu, nếu không: lấy tất cả
    const filtered = variantModal.value.selectedColor
        ? variants.filter(v => v.color === variantModal.value.selectedColor)
        : variants;

    const sizeMap = {};
    filtered.forEach(v => {
        const key = v.size || '__no_size__';
        if (!sizeMap[key]) sizeMap[key] = { size: v.size, stock: 0, variant_id: v.variant_id };
        sizeMap[key].stock += v.stock;
        sizeMap[key].variant_id = v.variant_id; // dùng variant đầu tiên tìm thấy
    });
    return Object.values(sizeMap);
});

// Variant được chọn trong modal
const modalSelectedVariant = computed(() => {
    const vars = variantModal.value.variants;
    const color = variantModal.value.selectedColor;
    const size = variantModal.value.selectedSize;

    if (!vars.length) return null;

    // Sản phẩm chỉ có size (không có màu)
    if (!modalHasColors.value && size) {
        return vars.find(v => v.size === size) || null;
    }
    // Sản phẩm có màu + size
    if (color && size) {
        return vars.find(v => v.color === color && v.size === size) || null;
    }
    // Sản phẩm chỉ có màu (không có size)
    if (color && !modalAvailableSizes.value.some(s => s.size)) {
        return vars.find(v => v.color === color) || null;
    }
    return null;
});

const onModalColorSelect = (color) => {
    variantModal.value.selectedColor = color;
    const varsForColor = variantModal.value.variants.filter(v => v.color === color);
    if (!varsForColor.length) return;

    // Nếu size hiện tại có ở màu mới và còn hàng thì giữ nguyên
    const currentMatch = varsForColor.find(v => v.size === variantModal.value.selectedSize && (v.stock || 0) > 0);
    if (currentMatch) {
        return;
    }
    // Nếu không, tự động chọn size đầu tiên còn hàng của màu mới
    const inStockMatch = varsForColor.find(v => (v.stock || 0) > 0);
    if (inStockMatch) {
        variantModal.value.selectedSize = inStockMatch.size || null;
    } else {
        variantModal.value.selectedSize = varsForColor[0]?.size || null;
    }
};

const confirmVariantChange = async () => {
    if (!modalSelectedVariant.value) return;
    const item = variantModal.value.item;
    if (!item) return;

    variantModal.value.confirming = true;

    if (!authStore.isAuthenticated) {
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        const idx = localItems.findIndex(i => i.variant_id === item.variant_id);
        if (idx !== -1) {
            const newVariantId = modalSelectedVariant.value.variant_id;
            const existingIdx = localItems.findIndex(i => i.variant_id === newVariantId);
            
            if (existingIdx !== -1 && existingIdx !== idx) {
                localItems[existingIdx].quantity += item.quantity;
                localItems.splice(idx, 1);
            } else {
                localItems[idx].variant_id = newVariantId;
            }
            
            localStorage.setItem('cart_items', JSON.stringify(localItems));
            showToast('Đã cập nhật phân loại sản phẩm!', 'success');
            closeVariantModal();
            await fetchCart(false);
            cartStore.fetchCount()
        }
        variantModal.value.confirming = false;
        return;
    }

    try {
        const res = await api.put(`/cart/items/${item.cart_item_id}/variant`, {
            variant_id: modalSelectedVariant.value.variant_id,
        });
        if (res.data.status === 'success') {
            showToast('Đã cập nhật biến thể sản phẩm!', 'success');
            closeVariantModal();
            await fetchCart(false); // Cập nhật lại list ngầm, không hiện spinner toàn trang
            cartStore.fetchCount()
        }
    } catch (e) {
        let msg = 'Không thể đổi biến thể. Vui lòng thử lại.';
        // Extract validation message if available
        if (e.response?.data?.message) {
            msg = e.response.data.message;
        }
        showToast(msg, 'error');
    } finally {
        variantModal.value.confirming = false;
    }
};


// Helper: lấy variant_name hiển thị
const getVariantLabel = (item) => {
    if (!item.variant) return '';
    const parts = [];
    if (item.variant.color) parts.push(item.variant.color);
    if (item.variant.size) parts.push(item.variant.size);
    if (!item.variant.color && !item.variant.size && item.variant.variant_name) parts.push(item.variant.variant_name);
    return parts.join(' / ');
};

// ====== END VARIANT MODAL ======

// Helper: Kiểm tra item có khả dụng để mua hàng không
const isItemAvailable = (item) => {
    if (!item) return false;
    if (item.is_available === false) return false;
    if (!item.product || item.product.is_active === false || item.product.status === 'inactive') return false;
    if (!item.variant || item.variant.status !== 'active') return false;
    if ((item.variant.stock ?? 0) <= 0) return false;
    return true;
};

// Helper: Lấy lý do không khả dụng để hiển thị
const getItemUnavailableReason = (item) => {
    if (!item) return 'Sản phẩm không tồn tại';
    if (item.is_available === false && item.error_message) return item.error_message;
    if (!item.product || item.product.is_active === false || item.product.status === 'inactive') {
        return 'Sản phẩm ngừng kinh doanh hoặc đã bị gỡ';
    }
    if (!item.variant || item.variant.status !== 'active') {
        return 'Phân loại hàng ngừng kinh doanh';
    }
    if ((item.variant.stock ?? 0) <= 0) {
        return 'Sản phẩm tạm thời hết hàng';
    }
    if (item.quantity > item.variant.stock) {
        return `Vượt quá tồn kho (Tối đa: ${item.variant.stock})`;
    }
    return null;
};

// Lấy giỏ hàng
const fetchCart = async (showGlobalLoading = true) => {
    if (cartRequest) {
        cartRefreshPending = true;
        return cartRequest;
    }

    if (showGlobalLoading) loading.value = true;
    cartRequest = (async () => {
        try {
            if (!authStore.isHydrated) {
                authStore.hydrate();
            }

            const token = authStore.token || localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
            const hasAuth = !!token;

            if (hasAuth) {
                const response = await api.get('/cart');
                if (response.data && (response.data.status === 'success' || response.data.data)) {
                    const data = response.data.data || response.data;
                    cartId.value = data.cart_id;
                    const items = data.items || [];
                    // Tự động bỏ chọn các sản phẩm không khả dụng
                    items.forEach(item => {
                        if (!isItemAvailable(item)) {
                            item.selected = false;
                        }
                    });
                    cartItems.value = items;
                    updateSelectAllState();
                    cartStore.count = items.length;
                }
            } else {
                const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
                if (localItems.length === 0) {
                    cartId.value = null;
                    cartItems.value = [];
                    selectAll.value = false;
                    cartStore.count = 0;
                } else {
                    try {
                        const response = await api.post('/cart/guest-details', { items: localItems });
                        if (response.data && (response.data.status === 'success' || response.data.data)) {
                            const guestData = response.data.data || response.data;
                            cartId.value = null;
                            const items = guestData.items || [];
                            items.forEach(item => {
                                if (!isItemAvailable(item)) {
                                    item.selected = false;
                                }
                            });
                            cartItems.value = items;
                            updateSelectAllState();

                            // Đồng bộ lại localStorage và cart badge với những sản phẩm còn tồn tại thực tế
                            const validVariantIds = items.map(i => i.variant_id);
                            const validLocalItems = localItems.filter(li => validVariantIds.includes(li.variant_id));
                            if (validLocalItems.length !== localItems.length) {
                                localStorage.setItem('cart_items', JSON.stringify(validLocalItems));
                            }
                            cartStore.count = validLocalItems.length;

                            if (guestData.freeship_threshold) {
                                upsellState.freeshipThreshold = guestData.freeship_threshold;
                            }
                        }
                    } catch (guestErr) {
                        console.error('Lỗi lấy chi tiết giỏ hàng guest:', guestErr);
                        if (guestErr.response?.status === 422) {
                            localStorage.removeItem('cart_items');
                            cartItems.value = [];
                            cartStore.count = 0;
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching cart:', error);
            if (error.response?.status === 403) {
                cartItems.value = [];
                cartStore.count = 0;
                showToast(error.response?.data?.message || 'Tài khoản nhân viên/quản trị không thể sử dụng giỏ hàng khách hàng.', 'warning');
            } else if (error.response?.status === 401) {
                const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
                cartItems.value = [];
                cartStore.count = localItems.length;
            }
        } finally {
            if (showGlobalLoading) loading.value = false;
        }
    })();

    try {
        return await cartRequest;
    } finally {
        cartRequest = null;
        if (cartRefreshPending) {
            cartRefreshPending = false;
            await fetchCart(false);
        }
    }
};


// Computed danh sách sản phẩm khả dụng & không khả dụng
const availableItems = computed(() => cartItems.value.filter(isItemAvailable));
const availableCount = computed(() => availableItems.value.length);
const unavailableItems = computed(() => cartItems.value.filter(item => !isItemAvailable(item)));
const unavailableCount = computed(() => unavailableItems.value.length);

// Cập nhật trạng thái "Chọn tất cả" (chỉ xét các item khả dụng)
const updateSelectAllState = () => {
    if (availableItems.value.length === 0) {
        selectAll.value = false;
        return;
    }
    selectAll.value = availableItems.value.every(item => item.selected);
};

// Toggle chọn tất cả (chỉ tác động đến item khả dụng)
const toggleSelectAll = async () => {
    if (availableItems.value.length === 0) return;
    const newState = !selectAll.value;
    selectAll.value = newState;
    
    if (!authStore.isAuthenticated) {
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        localItems.forEach(item => {
            const inMemory = cartItems.value.find(ci => ci.variant_id === item.variant_id);
            if (inMemory && isItemAvailable(inMemory)) {
                item.selected = newState;
            } else {
                item.selected = false;
            }
        });
        localStorage.setItem('cart_items', JSON.stringify(localItems));
        cartItems.value.forEach(item => {
            if (isItemAvailable(item)) {
                item.selected = newState;
            } else {
                item.selected = false;
            }
        });
        return;
    }
    
    // Cập nhật UI lập tức cho các item khả dụng
    cartItems.value.forEach(item => { 
        if (isItemAvailable(item)) {
            item.selected = newState; 
        } else {
            item.selected = false;
        }
    });

    // Gửi 1 request batch duy nhất
    try {
        await api.put('/cart/select-all', { selected: newState });
    } catch (error) {
        // Rollback UI nếu lỗi
        cartItems.value.forEach(item => { 
            if (isItemAvailable(item)) {
                item.selected = !newState; 
            }
        });
        selectAll.value = !newState;
        showToast('Không thể cập nhật. Vui lòng thử lại.', 'error');
    }
};

// Toggle chọn 1 item
const toggleSelect = async (item) => {
    if (!isItemAvailable(item)) {
        showToast(getItemUnavailableReason(item) || 'Sản phẩm không khả dụng, không thể chọn.', 'warning');
        return;
    }
    
    item.selected = !item.selected;
    updateSelectAllState();

    if (!authStore.isAuthenticated) {
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        const idx = localItems.findIndex(i => i.variant_id === item.variant_id);
        if (idx !== -1) {
            localItems[idx].selected = item.selected;
            localStorage.setItem('cart_items', JSON.stringify(localItems));
        }
        return;
    }

    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { selected: item.selected });
    } catch (error) {
        item.selected = !item.selected;
        showToast('Không thể cập nhật. Vui lòng thử lại.', 'error');
    }
};

const normalizeQuantity = (rawValue) => {
    const digitsOnly = String(rawValue ?? '').replace(/[^0-9]/g, '').slice(0, 6);
    if (!digitsOnly) return null;

    const quantity = Number.parseInt(digitsOnly, 10);
    if (!Number.isSafeInteger(quantity)) return null;

    return quantity;
};

const getQuantityTimerKey = (item) => item.cart_item_id || item.variant_id;

const clearQuantityInputTimer = (item) => {
    const key = getQuantityTimerKey(item);
    const timer = quantityInputTimers.get(key);
    if (timer) {
        clearTimeout(timer);
        quantityInputTimers.delete(key);
    }
};

const originalQuantities = new Map();

// Hàm gọi API thực tế (được trì hoãn)
const submitQuantityUpdate = async (item, targetQuantity) => {
    if (!authStore.isAuthenticated) return;
    
    const key = getQuantityTimerKey(item);
    updating.value[item.cart_item_id] = true;
    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { quantity: targetQuantity });
        originalQuantities.delete(key);
    } catch (error) {
        // Rollback nếu API lỗi
        const fallbackQty = originalQuantities.get(key) ?? item.quantity;
        item.quantity = fallbackQty;
        if (item.variant) {
            item.line_total = item.variant.price * fallbackQty;
        }
        const msg = error.response?.data?.message || 'Không thể cập nhật số lượng.';
        showToast(msg, 'error');
        originalQuantities.delete(key);
    } finally {
        updating.value[item.cart_item_id] = false;
    }
};

// Cập nhật số lượng tại giao diện lập tức và lên lịch gửi API sau 600ms
const changeQuantity = (item, rawQuantity) => {
    if (item.is_available === false) {
        showToast('Sản phẩm không khả dụng.', 'error');
        return;
    }
    
    const newQuantity = normalizeQuantity(rawQuantity);

    if (newQuantity === null || newQuantity < 1) {
        showToast('Số lượng tối thiểu là 1.', 'error');
        return;
    }

    if (newQuantity > 999) {
        showToast('Số lượng tối đa là 999.', 'error');
        return;
    }

    if (!item.variant || newQuantity > item.variant.stock) {
        showToast(`Chỉ còn ${item.variant?.stock || 0} sản phẩm trong kho.`, 'error');
        return;
    }

    if (newQuantity === item.quantity) {
        return;
    }

    const key = getQuantityTimerKey(item);
    
    // Lưu lại số lượng gốc ban đầu trước khi bắt đầu chuỗi thao tác bấm liên tục
    if (!originalQuantities.has(key)) {
        originalQuantities.set(key, item.quantity);
    }

    // Cập nhật UI lập tức
    item.quantity = newQuantity;
    if (item.variant) {
        item.line_total = item.variant.price * newQuantity;
    }

    // Debounce xử lý
    clearQuantityInputTimer(item);

    // Nếu là khách vãng lai
    if (!authStore.isAuthenticated) {
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        const idx = localItems.findIndex(i => i.variant_id === item.variant_id);
        if (idx !== -1) {
            localItems[idx].quantity = newQuantity;
            localStorage.setItem('cart_items', JSON.stringify(localItems));
        }
        
        const timer = setTimeout(() => {
            cartStore.fetchCount()
            quantityInputTimers.delete(key);
        }, QUANTITY_INPUT_DEBOUNCE_MS);
        
        quantityInputTimers.set(key, timer);
        originalQuantities.delete(key);
        return;
    }

    // Dành cho user đã đăng nhập
    const timer = setTimeout(() => {
        submitQuantityUpdate(item, newQuantity);
        quantityInputTimers.delete(key);
    }, QUANTITY_INPUT_DEBOUNCE_MS);

    quantityInputTimers.set(key, timer);
};

const scheduleQuantityInputUpdate = (item, event) => {
    const sanitized = String(event.target.value || '').replace(/[^0-9]/g, '').slice(0, 6);
    event.target.value = sanitized;

    const nextQuantity = normalizeQuantity(sanitized);
    if (nextQuantity !== null && nextQuantity >= 1) {
        changeQuantity(item, nextQuantity);
    }
};

const handleQuantityInputBlur = (item, event) => {
    const sanitized = String(event.target.value || '').replace(/[^0-9]/g, '').slice(0, 6);
    const nextQuantity = normalizeQuantity(sanitized);
    if (nextQuantity === null || nextQuantity < 1) {
        event.target.value = item.quantity;
        showToast('Vui lòng nhập số lượng hợp lệ.', 'error');
    }
};

// Xóa 1 item
const removeItem = async (item) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    if (!authStore.isAuthenticated) {
        cartItems.value = cartItems.value.filter(i => i.variant_id !== item.variant_id);
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        localItems = localItems.filter(i => i.variant_id !== item.variant_id);
        localStorage.setItem('cart_items', JSON.stringify(localItems));
        showToast('Đã xóa sản phẩm khỏi giỏ hàng!', 'success');
        updateSelectAllState();
        cartStore.fetchCount()
        return;
    }

    try {
        await api.delete(`/cart/items/${item.cart_item_id}`);
        cartItems.value = cartItems.value.filter(i => i.cart_item_id !== item.cart_item_id);
        showToast('Đã xóa sản phẩm khỏi giỏ hàng!', 'success');
        updateSelectAllState();
        cartStore.fetchCount()
    } catch (error) {
        showToast('Không thể xóa sản phẩm. Vui lòng thử lại.', 'error');
    }
};

// Xóa toàn bộ
const clearCart = async () => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc muốn xóa toàn bộ giỏ hàng?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    if (!authStore.isAuthenticated) {
        cartItems.value = [];
        localStorage.removeItem('cart_items');
        showToast('Đã xóa toàn bộ giỏ hàng!', 'success');
        cartStore.fetchCount()
        return;
    }

    try {
        await api.delete('/cart');
        cartItems.value = [];
        showToast('Đã xóa toàn bộ giỏ hàng!', 'success');
        cartStore.fetchCount()
    } catch (error) {
        showToast('Không thể xóa giỏ hàng. Vui lòng thử lại.', 'error');
    }
};


// Xóa toàn bộ sản phẩm không khả dụng / hết hàng
const isClearingUnavailable = ref(false);
const removeAllUnavailable = async () => {
    if (unavailableCount.value === 0) return;
    const result = await Swal.fire({
        title: 'Xóa sản phẩm hết hàng',
        text: `Bạn có muốn xóa ${unavailableCount.value} sản phẩm hết hàng / không khả dụng khỏi giỏ hàng?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E63B6F',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;

    isClearingUnavailable.value = true;
    if (!authStore.isAuthenticated) {
        let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
        const unavailVids = unavailableItems.value.map(i => i.variant_id);
        localItems = localItems.filter(i => !unavailVids.includes(i.variant_id));
        localStorage.setItem('cart_items', JSON.stringify(localItems));
        cartItems.value = cartItems.value.filter(isItemAvailable);
        showToast('Đã xóa các sản phẩm không khả dụng!', 'success');
        updateSelectAllState();
        cartStore.fetchCount();
        isClearingUnavailable.value = false;
        return;
    }

    try {
        for (const item of unavailableItems.value) {
            if (item.cart_item_id) {
                await api.delete(`/cart/items/${item.cart_item_id}`).catch(() => {});
            }
        }
        cartItems.value = cartItems.value.filter(isItemAvailable);
        showToast('Đã dọn dẹp các sản phẩm không khả dụng!', 'success');
        updateSelectAllState();
        cartStore.fetchCount();
    } catch (e) {
        showToast('Không thể xóa sản phẩm. Vui lòng thử lại.', 'error');
    } finally {
        isClearingUnavailable.value = false;
    }
};

// Tính tổng: Chỉ tính các sản phẩm vừa được chọn VÀ còn khả dụng
const selectedItems = computed(() => cartItems.value.filter(i => i.selected && isItemAvailable(i)));
const totalSelectedQuantity = computed(() => selectedItems.value.reduce((sum, i) => sum + i.quantity, 0));
const totalPrice = computed(() => selectedItems.value.reduce((sum, i) => sum + (i.variant?.price || 0) * i.quantity, 0));

// Lấy giá gốc của item trong giỏ (nếu có giảm giá)
const getItemOriginalPrice = (item) => {
    if (!item || !item.variant) return null;
    const salePrice = Number(item.variant.price || 0);
    const candidates = [
        item.variant.original_price,
        item.variant.compare_at_price,
        item.variant.originalPrice,
        item.variant.old_price,
        item.product?.original_price,
        item.product?.compare_at_price,
        item.product?.originalPrice,
        item.product?.max_price,
        item.original_price,
    ];
    for (const val of candidates) {
        const num = Number(val);
        if (Number.isFinite(num) && num > salePrice) {
            return num;
        }
    }
    return null;
};

// Tổng giá gốc của các sản phẩm được chọn
const totalOriginalPrice = computed(() => {
    return selectedItems.value.reduce((sum, item) => {
        const orig = getItemOriginalPrice(item) || item.variant?.price || 0;
        return sum + orig * item.quantity;
    }, 0);
});

// Tiết kiệm từ giảm giá sản phẩm
const totalProductDiscount = computed(() => {
    const diff = totalOriginalPrice.value - totalPrice.value;
    return Math.max(0, diff);
});

// Format tiền VND
const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#E63B6F" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/></svg>`);

// Lấy ảnh sản phẩm
const getProductImage = (item) => {
    if (item.variant?.image_url) return getStorageUrl(item.variant.image_url);
    if (item.product?.main_image) return getStorageUrl(item.product.main_image);
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return getStorageUrl(item.product.thumbnail_url);
    return defaultSvg;
};

// Toast notification
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const hasInvalidStockSelected = computed(() => {
    return selectedItems.value.some(item => {
        return item.is_available === false || !item.variant || item.variant.stock <= 0 || item.quantity > item.variant.stock || item.variant.status !== 'active';
    });
});

const isAnyItemUpdating = computed(() => {
    const hasUpdating = Object.values(updating.value).some(val => val);
    return hasUpdating || loading.value;
});

// Chuyển tới trang thanh toán
const proceedToCheckout = () => {
    if (selectedItems.value.length === 0) return;
    if (hasInvalidStockSelected.value) {
        showToast('Vui lòng loại bỏ hoặc đổi phân loại các sản phẩm hết hàng/vượt quá tồn kho.', 'error');
        return;
    }
    router.push('/checkout');
};

const productRelated = ref([]);
const getProductRelated = async () => {
    try {
        productRelated.value = await productService.getProductRelated();
    } catch (error) {
        console.error('Lỗi khi lấy danh sách sản phẩm liên quan:', error);
    }
};

onMounted(() => {
    getProductRelated();
});

// Đồng bộ totalPrice → shared composable (FreeshipBar phản ứng realtime)
watch(totalPrice, (val) => {
    setTotalPrice(val);
}, { immediate: true });

// Theo dõi thay đổi trạng thái đăng nhập để tự động tải lại giỏ hàng đúng
watch(() => authStore.isAuthenticated, async (newVal, oldVal) => {
    if (newVal !== oldVal) {
        await fetchCart(false);
        fetchUpsellData();
    }
});

watch(() => authStore.user, async (newVal, oldVal) => {
    if (newVal && !oldVal) {
        await fetchCart(false);
        fetchUpsellData();
    }
});

const handleCartUpdated = async () => {
    await fetchCart(false);
    fetchUpsellData();
};

const handleAuthChanged = async () => {
    if (!authStore.isHydrated) {
        authStore.hydrate();
    }
    await fetchCart(false);
    fetchUpsellData();
};

onMounted(async () => {
    await fetchCart();
    // Fetch gợi ý upsell sau khi giỏ hàng đã load
    fetchUpsellData();

    // Khi QuickAddSlider thêm sản phẩm → cập nhật lại giỏ + upsell
    window.addEventListener('cart-updated', handleCartUpdated);
    window.addEventListener('user-updated', handleAuthChanged);
    window.addEventListener('auth-logout', handleAuthChanged);
});

onUnmounted(() => {
    window.removeEventListener('cart-updated', handleCartUpdated);
    window.removeEventListener('user-updated', handleAuthChanged);
    window.removeEventListener('auth-logout', handleAuthChanged);
    quantityInputTimers.forEach((timer) => clearTimeout(timer));
    quantityInputTimers.clear();
});
</script>

<template>
    <div class="cart-page container">
        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" class="toast-notification" :class="toast.type">
                <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
                <span>{{ toast.message }}</span>
            </div>
        </Transition>

        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="breadcrumb">
                <router-link to="/">Trang chủ</router-link>
                <span class="separator">›</span>
                <span class="current">Giỏ hàng</span>
            </div>
            <h1>Giỏ hàng của bạn</h1>
        </div>

        <!-- Loading State (Skeleton) -->
        <div v-if="loading" class="cart-layout skeleton-layout">
            <!-- Left: Skeleton Items -->
            <div class="cart-items-section">
                <!-- Action bar skeleton -->
                <div class="cart-action-bar skeleton-box" style="height: 54px; border-radius: 12px;"></div>
                
                <!-- Items list skeleton -->
                <div class="items-list">
                    <div class="cart-item-card skeleton-item" v-for="i in 3" :key="i">
                        <div class="skeleton-checkbox skeleton-box"></div>
                        <div class="skeleton-image skeleton-box"></div>
                        <div class="skeleton-details">
                            <div class="skeleton-text skeleton-box" style="width: 70%; height: 20px; margin-bottom: 8px;"></div>
                            <div class="skeleton-text skeleton-box" style="width: 40%; height: 14px; margin-bottom: 16px;"></div>
                            <div class="skeleton-text skeleton-box" style="width: 30%; height: 18px;"></div>
                        </div>
                        <div class="skeleton-qty skeleton-box"></div>
                        <div class="skeleton-btn skeleton-box"></div>
                    </div>
                </div>
            </div>
            
            <!-- Right: Skeleton Summary -->
            <div class="order-summary">
                <div class="summary-card">
                    <div class="skeleton-text skeleton-box" style="width: 60%; height: 24px; margin-bottom: 24px;"></div>
                    <div class="skeleton-text skeleton-box" style="width: 100%; height: 16px; margin-bottom: 16px;"></div>
                    <div class="skeleton-text skeleton-box" style="width: 100%; height: 16px; margin-bottom: 16px;"></div>
                    <div class="skeleton-text skeleton-box" style="width: 100%; height: 16px; margin-bottom: 24px;"></div>
                    <div class="summary-divider"></div>
                    <div class="skeleton-text skeleton-box" style="width: 100%; height: 24px; margin-bottom: 24px;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 50px; border-radius: 12px; margin-bottom: 12px;"></div>
                    <div class="skeleton-box" style="width: 100%; height: 50px; border-radius: 12px;"></div>
                </div>
            </div>
        </div>

        <!-- Empty Cart -->
        <div v-else-if="cartItems.length === 0" class="empty-cart animate-in">
            <div class="empty-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#b0c4de" stroke-width="1.2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                </svg>
            </div>
            <h2>Giỏ hàng trống</h2>
            <p>Hãy khám phá và thêm sản phẩm yêu thích vào giỏ hàng nhé!</p>
            <router-link to="/product" class="btn-primary btn-shop">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <path d="M16 10a4 4 0 01-8 0" />
                </svg>
                Tiếp tục mua sắm
            </router-link>
        </div>

        <!-- ── Freeship Progress Bar ── (hiện sau khi giỏ hàng có sản phẩm) -->

        <!-- Cart Content -->
        <div v-if="!loading && cartItems.length > 0" class="cart-layout animate-in" style="animation-delay: 0.1s">
            <!-- Cột trái: Danh sách sản phẩm -->
            <div class="cart-items-section">
                <!-- Thanh Freeship độc lập -->
                <FreeshipBar v-if="!loading && cartItems.length > 0" />

                <!-- Vùng chứa khối liền mạch -->
                <div class="seamless-cart-container">
                    <!-- Banner cảnh báo nếu có sản phẩm hết hàng / không khả dụng -->
                    <div class="cart-notice-banner" v-if="unavailableCount > 0">
                        <div class="cart-notice-left">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            <span>Có <strong>{{ unavailableCount }}</strong> sản phẩm tạm hết hàng hoặc ngừng bán đã tự động được bỏ chọn.</span>
                        </div>
                        <button type="button" class="btn-clear-unavailable" @click="removeAllUnavailable" :disabled="isClearingUnavailable">
                            {{ isClearingUnavailable ? 'Đang xóa...' : 'Xóa sản phẩm hết hàng' }}
                        </button>
                    </div>

                    <div class="cart-action-bar">
                        <label class="checkbox-wrapper" @click.prevent="toggleSelectAll" :class="{ 'wrapper-disabled': availableCount === 0 }">
                            <div class="custom-checkbox" :class="{ checked: selectAll && availableCount > 0, disabled: availableCount === 0 }">
                                <svg v-if="selectAll && availableCount > 0" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white"
                                    stroke-width="4">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            </div>
                            <span>Chọn tất cả ({{ availableCount }})</span>
                        </label>
                        <button class="btn-clear" @click="clearCart" v-if="cartItems.length > 0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                            </svg>
                            Xóa đã chọn
                        </button>
                    </div>
    
                    <!-- Cart Items List -->
                    <TransitionGroup name="cart-item" tag="div" class="items-list">
                        <div v-for="item in cartItems" :key="item.cart_item_id || item.variant_id" class="cart-item-card"
                            :class="{ 'item-unavailable': !isItemAvailable(item) }">
                            <!-- Checkbox -->
                            <div class="item-checkbox" @click="toggleSelect(item)" :class="{ 'checkbox-disabled': !isItemAvailable(item) }"
                                :title="!isItemAvailable(item) ? (getItemUnavailableReason(item) || 'Sản phẩm không khả dụng') : 'Chọn sản phẩm'">
                                <div class="custom-checkbox" :class="{ checked: item.selected && isItemAvailable(item), disabled: !isItemAvailable(item) }">
                                    <svg v-if="item.selected && isItemAvailable(item)" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                        stroke="white" stroke-width="4">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <svg v-else-if="!isItemAvailable(item)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Product Image -->
                            <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-image-link">
                                <img :src="getProductImage(item)" :alt="item.product?.name" class="item-image" :class="{ 'img-grayscale': !isItemAvailable(item) }" />
                            </router-link>

                            <!-- Product Info & Actions -->
                            <div class="item-details">
                                <div class="item-title-row">
                                    <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-name">
                                        {{ item.product?.name || 'Sản phẩm không tồn tại' }}
                                    </router-link>
                                    <button class="btn-remove" @click="removeItem(item)" title="Xóa sản phẩm">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2.2">
                                            <line x1="18" y1="6" x2="6" y2="18" />
                                            <line x1="6" y1="6" x2="18" y2="18" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Variant Tag (Clickable to change) -->
                                <div class="item-variant-row" v-if="item.variant">
                                    <button type="button" class="item-variant-btn" @click.stop="openVariantModal(item)" title="Bấm để đổi màu sắc / kích thước">
                                        <span class="item-variant-text">
                                            {{ item.variant.color && item.variant.size ? `Phân loại: ${item.variant.color} - ${item.variant.size}` : (item.variant.color ? `Màu: ${item.variant.color}` : (item.variant.size ? `Size: ${item.variant.size}` : item.variant.variant_name)) }}
                                        </span>
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="variant-caret">
                                            <polyline points="6 9 12 15 18 9" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Alert Box & Badges -->
                                <div class="unavailable-alert-box" v-if="!isItemAvailable(item)">
                                    <span class="alert-icon">⚠️</span>
                                    <span class="alert-msg">{{ getItemUnavailableReason(item) }}</span>
                                    <button v-if="item.product" type="button" class="btn-inline-change-var" @click.stop="openVariantModal(item)">
                                        Đổi phân loại
                                    </button>
                                </div>
                                <div class="item-low-stock-badge" v-else-if="item.quantity > item.variant?.stock">
                                    ⚠️ Vượt quá tồn kho (Tối đa: {{ item.variant.stock }})
                                </div>
                                <div class="item-stock" v-else-if="item.variant?.stock <= 5 && item.variant?.stock > 0">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    Chỉ còn {{ item.variant.stock }} sản phẩm
                                </div>

                                <!-- Bottom Row: Price & Quantity Stepper -->
                                <div class="item-bottom-row">
                                    <div class="item-price-row">
                                        <span class="item-price">{{ formatPrice(item.variant?.price) }}</span>
                                        <span class="item-price-original" v-if="getItemOriginalPrice(item)">
                                            {{ formatPrice(getItemOriginalPrice(item)) }}
                                        </span>
                                    </div>

                                    <!-- Quantity Stepper -->
                                    <div class="quantity-control" :class="{ 'qty-disabled': !isItemAvailable(item) }">
                                        <button class="qty-btn" @click="changeQuantity(item, item.quantity - 1)"
                                            :disabled="item.quantity <= 1 || updating[item.cart_item_id] || !isItemAvailable(item)">
                                            -
                                        </button>
                                        <input
                                            class="qty-display qty-input"
                                            :class="{ 'qty-updating': updating[item.cart_item_id] }"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            :value="item.quantity"
                                            :disabled="updating[item.cart_item_id] || !isItemAvailable(item)"
                                            @input="scheduleQuantityInputUpdate(item, $event)"
                                            @keydown.enter.prevent="scheduleQuantityInputUpdate(item, $event)"
                                            @blur="handleQuantityInputBlur(item, $event)"
                                        />
                                        <button class="qty-btn" @click="changeQuantity(item, item.quantity + 1)"
                                            :disabled="item.quantity >= (item.variant?.stock || 0) || updating[item.cart_item_id] || !isItemAvailable(item)">
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>
                </div> <!-- END seamless-cart-container -->

                <!-- ── Quick Add Slider ── -->
                <QuickAddSlider />
            </div>

            <!-- Cột phải: Tóm tắt đơn hàng -->
            <div class="order-summary animate-in" style="animation-delay: 0.2s">
                <div class="summary-card">
                    <h3 class="summary-title">Tóm tắt đơn hàng</h3>

                    <div class="summary-row">
                        <span>Tạm tính ({{ totalSelectedQuantity }} sản phẩm)</span>
                        <strong>{{ formatPrice(totalProductDiscount > 0 ? totalOriginalPrice : totalPrice) }}</strong>
                    </div>
                    <div class="summary-row">
                        <span>Giảm giá</span>
                        <strong :style="totalProductDiscount > 0 ? 'color: #E63B6F;' : ''">
                            {{ totalProductDiscount > 0 ? `- ${formatPrice(totalProductDiscount)}` : '0đ' }}
                        </strong>
                    </div>
                    <div class="summary-row">
                        <span>Phí giao hàng</span>
                        <strong>35.000đ</strong>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-row summary-total">
                        <span>Tổng cộng</span>
                        <div style="text-align: right">
                            <strong class="total-price">{{ formatPrice(totalPrice > 0 ? totalPrice + 35000 : 0)
                                }}</strong>
                            <div class="vat-note">(Đã bao gồm VAT nếu có)</div>
                        </div>
                    </div>

                    <button class="btn-checkout" @click="proceedToCheckout" :disabled="selectedItems.length === 0 || isAnyItemUpdating">
                        <span v-if="isAnyItemUpdating" style="display: flex; align-items: center; justify-content: center; gap: 8px">
                            <span class="vmodal-spinner" style="width: 16px; height: 16px; border-width: 2px; border-top-color: #fff"></span>
                            Đang xử lý...
                        </span>
                        <template v-else>
                            Thanh toán
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" style="margin-left: 4px; vertical-align: -3px">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </template>
                    </button>

                    <router-link to="/product" class="btn-continue">
                        Tiếp tục mua hàng
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Mobile Sticky Checkout Bar (Hiển thị khi màn hình mobile) -->
        <div v-if="!loading && cartItems.length > 0" class="mobile-sticky-checkout-bar">
            <label class="mobile-sticky-select-all" @click.prevent="toggleSelectAll" :class="{ 'wrapper-disabled': availableCount === 0 }">
                <div class="custom-checkbox" :class="{ checked: selectAll && availableCount > 0, disabled: availableCount === 0 }">
                    <svg v-if="selectAll && availableCount > 0" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <span>Tất cả ({{ availableCount }})</span>
            </label>

            <div class="mobile-sticky-price-box">
                <div class="mobile-sticky-label">Tổng cộng:</div>
                <div class="mobile-sticky-total">{{ formatPrice(totalPrice > 0 ? totalPrice + 35000 : 0) }}</div>
            </div>

            <button class="btn-mobile-sticky-checkout" @click="proceedToCheckout" :disabled="selectedItems.length === 0 || isAnyItemUpdating">
                <span v-if="isAnyItemUpdating" class="vmodal-spinner" style="width: 14px; height: 14px; border-width: 2px; border-top-color: #fff"></span>
                <span>Thanh toán ({{ totalSelectedQuantity }})</span>
            </button>
        </div>
    </div>

    <!-- ====== VARIANT CHANGE MODAL ====== -->
    <Teleport to="body">
        <Transition name="vmodal">
            <div v-if="variantModal.show" class="vmodal-overlay" @click.self="closeVariantModal">
                <div class="vmodal-box">
                    <!-- Header -->
                    <div class="vmodal-header">
                        <div class="vmodal-product-snippet" v-if="variantModal.item">
                            <!-- Hiển thị ảnh của biến thể đang chọn (nếu có), nếu không có thì lấy ảnh mặc định của sp -->
                            <img :src="modalSelectedVariant?.image_url && modalSelectedVariant?.image_url !== '0' ? getStorageUrl(modalSelectedVariant.image_url) : getProductImage(variantModal.item)"
                                :alt="variantModal.item.product?.name" class="vmodal-product-img" />
                            <div class="vmodal-product-info">
                                <h3 class="vmodal-title">Đổi phân loại hàng</h3>
                                <p class="vmodal-product-name" :title="variantModal.item.product?.name">{{
                                    variantModal.item.product?.name }}</p>
                            </div>
                        </div>
                        <button class="vmodal-close" @click="closeVariantModal" title="Đóng">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="variantModal.loadingVariants" class="vmodal-loading">
                        <div class="vmodal-spinner"></div>
                        <span>Đang tải biến thể...</span>
                    </div>

                    <template v-else>
                        <!-- Chọn màu sắc -->
                        <div class="vmodal-section" v-if="modalHasColors">
                            <p class="vmodal-label">Màu sắc:</p>
                            <div class="vmodal-options">
                                <button v-for="color in modalUniqueColors" :key="color" class="vmodal-opt-btn"
                                    :class="{ active: variantModal.selectedColor === color }"
                                    @click="onModalColorSelect(color)">{{ color }}</button>
                            </div>
                        </div>

                        <!-- Chọn kích thước -->
                        <div class="vmodal-section" v-if="modalAvailableSizes.some(s => s.size)">
                            <p class="vmodal-label">Kích thước:</p>
                            <div class="vmodal-options">
                                <button v-for="s in modalAvailableSizes" :key="s.size" class="vmodal-opt-btn"
                                    :class="{ active: variantModal.selectedSize === s.size, 'out-of-stock': s.stock <= 0 }"
                                    :disabled="s.stock <= 0" @click="variantModal.selectedSize = s.size">
                                    {{ s.size }}
                                    <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{ s.stock
                                        }})</span>
                                    <span v-else-if="s.stock <= 0" class="vmodal-opt-stock">Hết</span>
                                </button>
                            </div>
                        </div>

                        <!-- Thông tin variant đã chọn -->
                        <div class="vmodal-selected-info" v-if="modalSelectedVariant">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                stroke-width="2">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>
                                Đã chọn:
                                <strong>{{ [modalSelectedVariant.color,
                                modalSelectedVariant.size].filter(Boolean).join(' / ') ||
                                    modalSelectedVariant.variant_name }}</strong>
                                — {{ new Intl.NumberFormat('vi-VN', {
                                    style: 'currency', currency: 'VND'
                                }).format(modalSelectedVariant.price) }}
                                <span v-if="modalSelectedVariant.stock <= 5" class="vmodal-low-stock">(còn {{
                                    modalSelectedVariant.stock }})</span>
                            </span>
                        </div>
                        <div class="vmodal-selected-info vmodal-unselected" v-else>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            <span>Vui lòng chọn {{ modalHasColors ? 'màu sắc' : '' }}{{modalHasColors &&
                                modalAvailableSizes.some(s=>s.size) ? ' và ' : '' }}{{
                                    modalAvailableSizes.some(s => s.size) ? 'kích thước' : '' }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="vmodal-footer">
                            <button class="vmodal-btn-cancel" @click="closeVariantModal">Hủy bỏ</button>
                            <button class="vmodal-btn-confirm"
                                :disabled="!modalSelectedVariant || variantModal.confirming"
                                @click="confirmVariantChange">
                                <span v-if="variantModal.confirming">Đang cập nhật...</span>
                                <span v-else>Xác nhận</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>

    <section class="upsell-section container mb-4 mb-md-5">
        <h2 class="upsell-title">Có thể bạn cũng thích</h2>
        <div v-if="productRelated.length">
            <div class="row g-2 g-md-3 mt-1">
                <div class="col-6 col-md-4 col-lg-3" v-for="product in productRelated" :key="product.id || product.product_id">
                    <ProductCard :product="product" />
                </div>
            </div>
        </div>
        <div v-else class="empty-state mt-3">
            <p>Không có sản phẩm liên quan</p>
        </div>

    </section>
</template>

<style scoped>
.cart-page {
    padding: 24px 0 60px;
    font-family: var(--font-inter, 'Inter', sans-serif);
    color: #102a43;
    min-height: 60vh;
}

.upsell-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #102a43;
    margin-bottom: 20px;
}

.upsell-section {
    padding-bottom: 40px;
}

/* Page Header */
.page-header {
    text-align: left;
    margin-bottom: 24px;
}

.breadcrumb {
    font-size: 0.9rem;
    color: #627d98;
    margin-bottom: 12px;
    font-weight: 500;
}

.breadcrumb a {
    color: #334e68;
    text-decoration: none;
    font-weight: 600;
}

.breadcrumb .separator {
    margin: 0 8px;
    color: #94a3b8;
}

.page-header h1 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #102a43;
    margin-bottom: 8px;
}

/* Skeleton Loading */
.skeleton-box {
    background: linear-gradient(90deg, #f0f2f5 25%, #e6e8eb 50%, #f0f2f5 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite linear;
    border-radius: 4px;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-layout {
    opacity: 0.8;
    pointer-events: none;
}

.skeleton-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid #e8ecf1;
    border-radius: 12px;
    background: #fff;
}

.skeleton-checkbox { width: 20px; height: 20px; border-radius: 5px; flex-shrink: 0; }
.skeleton-image { width: 90px; height: 90px; border-radius: 8px; flex-shrink: 0; }
.skeleton-details { flex: 1; display: flex; flex-direction: column; }
.skeleton-qty { width: 100px; height: 36px; border-radius: 8px; flex-shrink: 0; margin-left: auto; }
.skeleton-btn { width: 20px; height: 20px; border-radius: 4px; flex-shrink: 0; margin-left: 16px; }

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 16px;
    border: 1px dashed #b0c4de;
}

.empty-icon {
    margin-bottom: 24px;
}

.empty-cart h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #334e68;
    margin-bottom: 8px;
}

.empty-cart p {
    color: #627d98;
    margin-bottom: 28px;
    font-size: 1rem;
}

.btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 10px;
    font-size: 1rem;
    text-decoration: none;
}

/* Layout */
.cart-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    padding-bottom: 120px;
}

.cart-items-section {
    width: calc(66.666% - 16px);
}

/* Seamless Container */
.seamless-cart-container {
    background: #fff;
    border: 1px solid #e8ecf1;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-bottom: 24px;
}

/* Action Bar */
.cart-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 16px;
    background: #fff;
    border-bottom: 1px solid #e8ecf1;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.92rem;
    font-weight: 600;
    color: #334e68;
    user-select: none;
}

.custom-checkbox {
    width: 18px;
    height: 18px;
    border: 2px solid #c8d6e0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.custom-checkbox.checked {
    background: #E63B6F;
    border-color: #E63B6F;
}

.btn-clear {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 0;
    border: none;
    background: transparent;
    color: #E63B6F;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}

.btn-clear:hover {
    color: #C4305D;
}

/* Cart Items */
/* Cart Items */
.items-list {
    display: flex;
    flex-direction: column;
}

.cart-item-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    background: #fff;
    border-bottom: 1px solid #e8ecf1;
    transition: all 0.25s ease;
}

.cart-item-card:last-child {
    border-bottom: none;
}

.cart-item-card:hover {
    background: #fafbfc;
}

.cart-item-card.item-unavailable {
    opacity: 0.55;
    background: #fafafa;
}

/* Checkbox */
.item-checkbox {
    cursor: pointer;
    flex-shrink: 0;
    padding-top: 2px;
}

/* Product Image */
.item-image-link {
    flex-shrink: 0;
    display: block;
}

.item-image {
    width: 75px;
    height: 75px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #eef2f6;
    display: block;
    transition: transform 0.3s;
}

.item-image:hover {
    transform: scale(1.04);
}

/* Product Details */
.item-details {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.item-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.item-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #1a2b4a;
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
    flex: 1;
    min-width: 0;
    transition: color 0.2s;
}

.item-name:hover {
    color: #E63B6F;
}

/* Remove Button */
.btn-remove {
    width: 24px;
    height: 24px;
    border: none;
    background: #f8fafc;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    transition: all 0.2s;
    flex-shrink: 0;
    padding: 0;
}

.btn-remove:hover {
    color: #E63B6F;
    background: #fff0f4;
}

.item-variant-row {
    display: flex;
    align-items: center;
    margin: 2px 0 4px;
}

.item-variant-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 22px;
    min-height: 22px;
    max-height: 22px;
    padding: 0 7px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    max-width: 100%;
    box-sizing: border-box;
    line-height: 1;
    font-family: inherit;
    font-size: 0.72rem;
    color: #475569;
}

.item-variant-btn:hover {
    background: #fff0f5;
    border-color: #fbcfe8;
}

.item-variant-btn:hover .item-variant-text {
    color: #E63B6F;
}

.item-variant-btn .variant-caret {
    width: 9px;
    height: 9px;
    color: #94a3b8;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}

.item-variant-btn:hover .variant-caret {
    color: #E63B6F;
    transform: translateY(1px);
}

.item-variant-text {
    font-size: 0.72rem;
    line-height: 1;
    color: #475569;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Cart Notice Banner */
.cart-notice-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: #fff1f2;
    border-bottom: 1px solid #fecdd3;
    padding: 10px 16px;
    transition: all 0.3s ease;
}

.cart-notice-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #9f1239;
}

.cart-notice-left strong {
    color: #e11d48;
    font-weight: 700;
}

.btn-clear-unavailable {
    flex-shrink: 0;
    padding: 5px 12px;
    background: #e11d48;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: inherit;
}

.btn-clear-unavailable:hover:not(:disabled) {
    background: #be123c;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.25);
}

.btn-clear-unavailable:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.wrapper-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.checkbox-disabled {
    cursor: not-allowed !important;
}

.custom-checkbox.disabled {
    background: #f1f5f9;
    border-color: #cbd5e1;
    cursor: not-allowed;
}

.img-grayscale {
    filter: grayscale(80%) opacity(0.75);
}

.unavailable-alert-box {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 6px;
    padding: 4px 8px;
    margin-top: 4px;
}

.unavailable-alert-box .alert-icon {
    font-size: 0.82rem;
}

.unavailable-alert-box .alert-msg {
    font-size: 0.76rem;
    color: #e11d48;
    font-weight: 600;
}

.btn-inline-change-var {
    background: #e11d48;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 2px 7px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-left: 2px;
    font-family: inherit;
}

.btn-inline-change-var:hover {
    background: #be123c;
}

.qty-disabled {
    opacity: 0.45;
    pointer-events: none;
}

.item-stock {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.76rem;
    color: #d97706;
    font-weight: 600;
    margin-top: 2px;
}

.item-unavailable-badge {
    font-size: 0.76rem;
    color: #dc2626;
    font-weight: 600;
    margin-top: 2px;
}

.item-low-stock-badge {
    font-size: 0.76rem;
    color: #d97706;
    font-weight: 600;
    margin-top: 2px;
}

.stock-warning-text {
    font-size: 0.8rem;
    color: #dc2626;
    background: #fff5f5;
    border: 1px solid #fecaca;
    padding: 8px 12px;
    border-radius: 8px;
    margin: 10px 0 16px;
    text-align: center;
    font-weight: 600;
    line-height: 1.4;
}

/* Bottom Row: Price & Quantity */
.item-bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 6px;
}

.item-price-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
}

.item-price-original {
    font-size: 0.8rem;
    font-weight: 500;
    color: #94a3b8;
    text-decoration: line-through;
}

.item-price {
    font-size: 1.02rem;
    font-weight: 800;
    color: #E63B6F;
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    height: 28px;
    flex-shrink: 0;
}

.qty-btn {
    width: 26px;
    height: 28px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #486581;
    font-size: 0.95rem;
    transition: all 0.15s;
    padding: 0;
}

.qty-btn:hover:not(:disabled) {
    background: #f0f7fa;
    color: #E63B6F;
}

.qty-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.qty-display {
    width: 32px;
    height: 28px;
    text-align: center;
    font-size: 0.85rem;
    font-weight: 700;
    color: #102a43;
    border: 0;
    border-left: 1px solid #e8ecf1;
    border-right: 1px solid #e8ecf1;
    line-height: 28px;
    outline: none;
    background: #fff;
    padding: 0;
}

.qty-input:focus {
    background: #fff5f8;
    box-shadow: inset 0 0 0 1px #E63B6F;
}

.qty-updating {
    color: #a0aec0;
    background: #f8fafc;
}

/* Mobile Sticky Checkout Bar */
.mobile-sticky-checkout-bar {
    display: none;
}

/* Order Summary */
.order-summary {
    width: calc(33.333% - 8px);
    flex-shrink: 0;
    position: sticky;
    top: 100px;
}

.summary-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8ecf1;
    padding: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.summary-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1a2b4a;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 2px solid #f0f4f8;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 0.92rem;
    color: #486581;
}

.summary-row strong {
    color: #102a43;
}

.summary-divider {
    height: 1px;
    background: #eef2f6;
    margin: 8px 0;
}

.summary-total {
    font-size: 1.05rem;
    padding: 14px 0;
}

.total-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: #E63B6F !important;
}

.vat-note {
    font-size: 0.75rem;
    color: #627d98;
    font-weight: 500;
    margin-top: 4px;
}

.coupon-section {
    display: flex;
    gap: 8px;
    margin: 16px 0 24px;
}

.coupon-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    font-size: 0.95rem;
    outline: none;
    background: #f8fafc;
    transition: border-color 0.2s;
}

.coupon-input:focus {
    border-color: #E63B6F;
    background: #fff;
}

.btn-apply-coupon {
    padding: 0 16px;
    background: #e2e8f0;
    color: #334e68;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-apply-coupon:hover {
    background: #cbd5e1;
}

.btn-checkout {
    width: 100%;
    padding: 10px;
    background: #E63B6F;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.25s;
    box-shadow: 0 4px 14px rgba(230, 59, 111, 0.3);
}

.btn-checkout:hover:not(:disabled) {
    background: #C4305D;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(230, 59, 111, 0.4);
}

.btn-checkout:disabled {
    background: #c8d6e0;
    cursor: not-allowed;
    box-shadow: none;
}

.btn-continue {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 8px;
    margin-top: 8px;
    color: #E63B6F;
    border: 1px solid #E63B6F;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
    background: #fff;
    transition: all 0.2s;
}

.btn-continue:hover {
    background: #fff0f4;
}

/* BTN Primary */
.btn-primary {
    background: #E63B6F;
    color: #fff;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 10px;
}

.btn-primary:hover {
    background: #C4305D; /* Tối hơn màu E63B6F một chút */
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}

/* Toast */
.toast-notification {
    position: fixed;
    top: 90px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 22px;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 600;
    z-index: 999;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.toast-notification.success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.toast-notification.error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Transitions */
.toast-enter-active {
    animation: slideInRight 0.3s ease;
}

.toast-leave-active {
    animation: slideOutRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(40px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }

    to {
        opacity: 0;
        transform: translateX(40px);
    }
}

.cart-item-enter-active {
    animation: fadeSlideIn 0.3s ease;
}

.cart-item-leave-active {
    animation: fadeSlideOut 0.25s ease;
}

@keyframes fadeSlideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeSlideOut {
    from {
        opacity: 1;
        transform: translateX(0);
        height: auto;
    }

    to {
        opacity: 0;
        transform: translateX(20px);
        height: 0;
        padding: 0;
        margin: 0;
        overflow: hidden;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 900px) {
    .cart-layout {
        flex-direction: column;
        gap: 20px;
        padding-bottom: 90px;
    }

    .cart-items-section,
    .order-summary {
        width: 100%;
        position: static;
    }
}

@media (max-width: 768px) {
    .cart-layout {
        padding-bottom: 120px;
    }

    .summary-card .btn-checkout,
    .summary-card .btn-continue {
        display: none;
    }

    .summary-card {
        margin-bottom: 20px;
    }

    .mobile-sticky-checkout-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 9990;
        background: #ffffff;
        padding: 6px 12px;
        border-top: 1px solid #eef2f6;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }

    .mobile-sticky-select-all {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.78rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
    }

    .mobile-sticky-price-box {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        flex: 1;
        min-width: 0;
        padding: 0 4px;
    }

    .mobile-sticky-label {
        font-size: 0.65rem;
        color: #64748b;
        font-weight: 500;
        line-height: 1.1;
    }

    .mobile-sticky-total {
        font-size: 0.95rem;
        font-weight: 800;
        color: #E63B6F;
        white-space: nowrap;
        line-height: 1.2;
    }

    .btn-mobile-sticky-checkout {
        flex-shrink: 0;
        padding: 7px 14px;
        background: var(--primary, #E63B6F);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(230, 59, 111, 0.25);
        transition: all 0.2s;
        font-family: inherit;
    }

    .btn-mobile-sticky-checkout:hover:not(:disabled) {
        background: #C4305D;
    }

    .btn-mobile-sticky-checkout:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        box-shadow: none;
    }
}

@media (max-width: 600px) {
    .cart-page {
        padding: 14px 0 80px;
    }

    .page-header {
        margin-bottom: 12px;
    }

    .page-header h1 {
        font-size: 1.25rem;
        margin-bottom: 4px;
    }

    .item-image {
        width: 64px;
        height: 64px;
        border-radius: 8px;
    }

    .cart-item-card {
        padding: 10px 12px;
        gap: 10px;
    }

    .item-name {
        font-size: 0.86rem;
        line-height: 1.3;
    }

    .item-price {
        font-size: 0.95rem;
    }

    .item-price-original {
        font-size: 0.74rem;
    }

    .item-variant-row {
        margin: 1px 0 3px;
    }

    .item-variant-btn {
        height: 20px;
        min-height: 20px;
        max-height: 20px;
        padding: 0 6px;
        font-size: 0.7rem;
        gap: 3px;
        border-radius: 4px;
    }

    .item-variant-text {
        font-size: 0.7rem;
        line-height: 1;
    }

    .item-variant-btn .variant-caret {
        width: 8px;
        height: 8px;
    }

    .btn-remove {
        width: 22px;
        height: 22px;
        border-radius: 5px;
    }

    .quantity-control {
        height: 26px;
        border-radius: 6px;
    }

    .qty-btn {
        width: 24px;
        height: 26px;
        font-size: 0.85rem;
    }

    .qty-display {
        width: 28px;
        height: 26px;
        font-size: 0.8rem;
        line-height: 26px;
    }

    .cart-action-bar {
        padding: 6px 12px;
    }

    .summary-card {
        padding: 12px;
    }

    /* Empty Cart Mobile */
    .empty-cart {
        padding: 28px 14px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .empty-icon {
        margin-bottom: 10px;
    }

    .empty-icon svg {
        width: 48px;
        height: 48px;
    }

    .empty-cart h2 {
        font-size: 1.15rem;
        margin-bottom: 4px;
    }

    .empty-cart p {
        font-size: 0.82rem;
        margin-bottom: 16px;
    }

    .btn-shop {
        padding: 8px 18px;
        font-size: 0.85rem;
        border-radius: 8px;
    }

    .upsell-title {
        font-size: 1.05rem;
        margin-bottom: 8px;
    }

    .upsell-section {
        padding-bottom: 30px;
    }

    .vmodal-footer {
        padding: 12px 16px;
        margin-top: 10px;
    }

    .vmodal-btn-cancel,
    .vmodal-btn-confirm {
        padding: 8px 12px;
        font-size: 0.85rem;
        border-radius: 8px;
    }
}

@media (max-width: 380px) {
    .item-image {
        width: 56px;
        height: 56px;
    }

    .cart-item-card {
        padding: 8px 10px;
        gap: 8px;
    }

    .item-name {
        font-size: 0.82rem;
    }

    .item-price {
        font-size: 0.88rem;
    }

    .btn-mobile-sticky-checkout {
        padding: 6px 10px;
        font-size: 0.78rem;
        border-radius: 6px;
    }

    .mobile-sticky-total {
        font-size: 0.88rem;
    }
}

/* ====== VARIANT MODAL ====== */
.vmodal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 40, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 16px;
}

.vmodal-box {
    background: #fff;
    border-radius: 18px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 24px 60px rgba(230, 59, 111, 0.15), 0 8px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    font-family: var(--font-inter, 'Inter', sans-serif);
}

.vmodal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4f8;
    gap: 16px;
}

.vmodal-product-snippet {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.vmodal-product-img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #eef2f6;
    flex-shrink: 0;
}

.vmodal-product-info {
    flex: 1;
    min-width: 0;
}

.vmodal-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a2b4a;
    margin: 0 0 4px;
}

.vmodal-product-name {
    font-size: 0.85rem;
    color: #627d98;
    margin: 0;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vmodal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    transition: all 0.18s;
    flex-shrink: 0;
}

.vmodal-close:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.vmodal-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px 24px;
    color: #627d98;
    font-size: 0.9rem;
}

.vmodal-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #e2e8f0;
    border-top-color: #E63B6F;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.vmodal-section {
    padding: 16px 24px 0;
}

.vmodal-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334e68;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 10px;
}

.vmodal-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.vmodal-opt-btn {
    padding: 7px 16px;
    border: 1.5px solid #d9e2ec;
    border-radius: 8px;
    background: #fff;
    font-size: 0.88rem;
    font-weight: 600;
    color: #334e68;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.vmodal-opt-btn:hover:not(:disabled) {
    border-color: #E63B6F;
    color: #E63B6F;
}

.vmodal-opt-btn.active {
    border-color: #E63B6F;
    background: #E63B6F;
    color: #fff;
}

.vmodal-opt-btn.out-of-stock {
    opacity: 0.4;
    cursor: not-allowed;
    text-decoration: line-through;
}

.vmodal-opt-stock {
    font-size: 0.72rem;
    font-weight: 500;
    opacity: 0.85;
}

.vmodal-selected-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 24px 0;
    padding: 12px 16px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 10px;
    font-size: 0.88rem;
    color: #0369a1;
}

.vmodal-selected-info strong {
    font-weight: 700;
}

.vmodal-low-stock {
    color: #f59e0b;
    font-weight: 600;
}

.vmodal-unselected {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.vmodal-footer {
    display: flex;
    gap: 10px;
    padding: 20px 24px;
    border-top: 1px solid #f0f4f8;
    margin-top: 16px;
}

.vmodal-btn-cancel {
    flex: 1;
    padding: 11px;
    border: 1.5px solid #d9e2ec;
    border-radius: 10px;
    background: #fff;
    color: #627d98;
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s;
}

.vmodal-btn-cancel:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.vmodal-btn-confirm {
    flex: 2;
    padding: 11px;
    border: none;
    border-radius: 10px;
    background: #E63B6F;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}

.vmodal-btn-confirm:hover:not(:disabled) {
    background: #C4305D;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(230, 59, 111, 0.4);
}

.vmodal-btn-confirm:disabled {
    background: #c8d6e0;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Modal Transition */
.vmodal-enter-active,
.vmodal-leave-active {
    transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

.vmodal-enter-from,
.vmodal-leave-to {
    opacity: 0;
}

.vmodal-enter-from .vmodal-box,
.vmodal-leave-to .vmodal-box {
    transform: scale(0.94) translateY(20px);
}
</style>
