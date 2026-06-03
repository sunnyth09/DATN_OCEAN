<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';
import Swal from 'sweetalert2';
import FreeshipBar from '@/components/FreeshipBar.vue';
import QuickAddSlider from '@/components/QuickAddSlider.vue';
import { useCartUpsell } from '@/composables/useCartUpsell';

const router = useRouter();
const cartItems = ref([]);
const cartId = ref(null);
const loading = ref(true);
const updating = ref({});
const selectAll = ref(true);
const toast = ref({ show: false, message: '', type: 'success' });
let cartRequest = null;
let cartRefreshPending = false;

// ====== UPSELL & GAMIFICATION ======
const { setTotalPrice, fetchUpsellData } = useCartUpsell();

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
    variantModal.value.selectedSize  = item.variant?.size  || null;

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
    // Reset size nếu size hiện tại không có trong màu mới
    const available = variantModal.value.variants
        .filter(v => v.color === color)
        .map(v => v.size);
    if (!available.includes(variantModal.value.selectedSize)) {
        variantModal.value.selectedSize = null;
    }
};

const confirmVariantChange = async () => {
    if (!modalSelectedVariant.value) return;
    const item = variantModal.value.item;
    if (!item) return;

    variantModal.value.confirming = true;
    try {
        const res = await api.put(`/cart/items/${item.cart_item_id}/variant`, {
            variant_id: modalSelectedVariant.value.variant_id,
        });
        if (res.data.status === 'success') {
            showToast('Đã cập nhật biến thể sản phẩm!', 'success');
            closeVariantModal();
            await fetchCart(false); // Cập nhật lại list ngầm, không hiện spinner toàn trang
            window.dispatchEvent(new Event('cart-updated'));
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

// Lấy giỏ hàng
const fetchCart = async (showGlobalLoading = true) => {
    if (cartRequest) {
        cartRefreshPending = true;
        return cartRequest;
    }

    if (showGlobalLoading) loading.value = true;
    cartRequest = (async () => {
        try {
            const response = await api.get('/cart');
            if (response.data.status === 'success') {
                cartId.value = response.data.data.cart_id;
                cartItems.value = response.data.data.items || [];
                updateSelectAllState();
            }
        } catch (error) {
            console.error('Error fetching cart:', error);
            if (error.response?.status === 401) {
                router.push({ name: 'login', query: { redirect: '/cart' } });
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

// Cập nhật trạng thái "Chọn tất cả"
const updateSelectAllState = () => {
    if (cartItems.value.length === 0) {
        selectAll.value = false;
        return;
    }
    selectAll.value = cartItems.value.every(item => item.selected);
};

// Toggle chọn tất cả
const toggleSelectAll = async () => {
    const newState = !selectAll.value;
    selectAll.value = newState;
    
    const promises = cartItems.value.map(item => {
        item.selected = newState;
        return api.put(`/cart/items/${item.cart_item_id}`, { selected: newState }).catch(() => {});
    });
    await Promise.all(promises);
};

// Toggle chọn 1 item
const toggleSelect = async (item) => {
    item.selected = !item.selected;
    updateSelectAllState();
    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { selected: item.selected });
    } catch (error) {
        item.selected = !item.selected;
        showToast('Không thể cập nhật. Vui lòng thử lại.', 'error');
    }
};

// Cập nhật số lượng
const updateQuantity = async (item, newQuantity) => {
    if (newQuantity < 1) return;
    if (!item.variant || newQuantity > item.variant.stock) {
        showToast(`Chỉ còn ${item.variant?.stock || 0} sản phẩm trong kho.`, 'error');
        return;
    }

    const oldQuantity = item.quantity;
    item.quantity = newQuantity;
    item.line_total = item.variant.price * newQuantity;
    updating.value[item.cart_item_id] = true;

    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { quantity: newQuantity });
    } catch (error) {
        item.quantity = oldQuantity;
        item.line_total = item.variant.price * oldQuantity;
        const msg = error.response?.data?.message || 'Không thể cập nhật số lượng.';
        showToast(msg, 'error');
    } finally {
        updating.value[item.cart_item_id] = false;
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

    try {
        await api.delete(`/cart/items/${item.cart_item_id}`);
        cartItems.value = cartItems.value.filter(i => i.cart_item_id !== item.cart_item_id);
        showToast('Đã xóa sản phẩm khỏi giỏ hàng!', 'success');
        updateSelectAllState();
        window.dispatchEvent(new Event('cart-updated'));
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

    try {
        await api.delete('/cart');
        cartItems.value = [];
        showToast('Đã xóa toàn bộ giỏ hàng!', 'success');
        window.dispatchEvent(new Event('cart-updated'));
    } catch (error) {
        showToast('Không thể xóa giỏ hàng. Vui lòng thử lại.', 'error');
    }
};

// Tính tổng
const selectedItems = computed(() => cartItems.value.filter(i => i.selected));
const totalSelectedQuantity = computed(() => selectedItems.value.reduce((sum, i) => sum + i.quantity, 0));
const totalPrice = computed(() => selectedItems.value.reduce((sum, i) => sum + (i.variant?.price || 0) * i.quantity, 0));

// Format tiền VND
const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#E63B6F" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/></svg>`);

// Lấy ảnh sản phẩm
const getProductImage = (item) => {
    if (item.variant?.image_url) return `http://localhost:8383/storage/${item.variant.image_url}`;
    if (item.product?.main_image) return `http://localhost:8383/storage/${item.product.main_image}`;
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `http://localhost:8383/storage/${item.product.thumbnail_url}`;
    return defaultSvg;
};

// Toast notification
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

// Chuyển tới trang thanh toán
const proceedToCheckout = () => {
    if (selectedItems.value.length === 0) return;
    router.push('/checkout');
};

// Đồng bộ totalPrice → shared composable (FreeshipBar phản ứng realtime)
watch(totalPrice, (val) => {
    setTotalPrice(val);
}, { immediate: true });

const handleCartUpdated = async () => {
    await fetchCart(false);
    fetchUpsellData();
};

onMounted(async () => {
    await fetchCart();
    // Fetch gợi ý upsell sau khi giỏ hàng đã load
    fetchUpsellData();

    // Khi QuickAddSlider thêm sản phẩm → cập nhật lại giỏ + upsell
    window.addEventListener('cart-updated', handleCartUpdated);
});

onUnmounted(() => {
    window.removeEventListener('cart-updated', handleCartUpdated);
});
</script>

<template>
    <div class="cart-page">
        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" class="toast-notification" :class="toast.type">
                <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>{{ toast.message }}</span>
            </div>
        </Transition>

        <!-- Page Header -->
        <div class="page-header animate-in">
            <h1>Giỏ Hàng Của Bạn</h1>
            <p v-if="cartItems.length > 0">Bạn có <strong>{{ cartItems.length }}</strong> sản phẩm trong giỏ hàng</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang tải giỏ hàng...</p>
        </div>

        <!-- Empty Cart -->
        <div v-else-if="cartItems.length === 0" class="empty-cart animate-in">
            <div class="empty-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>
                </svg>
            </div>
            <h2>Giỏ hàng trống</h2>
            <p>Hãy khám phá và thêm sản phẩm yêu thích vào giỏ hàng nhé!</p>
            <router-link to="/product" class="btn-primary btn-shop">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                Tiếp tục mua sắm
            </router-link>
        </div>

        <!-- ── Freeship Progress Bar ── (hiện sau khi giỏ hàng có sản phẩm) -->
        <FreeshipBar v-if="!loading && cartItems.length > 0" />

        <!-- Cart Content -->
        <div v-if="!loading && cartItems.length > 0" class="cart-layout animate-in" style="animation-delay: 0.1s">
            <!-- Cột trái: Danh sách sản phẩm -->
            <div class="cart-items-section">
                <!-- Action Bar -->
                <div class="cart-action-bar">
                    <label class="checkbox-wrapper" @click.prevent="toggleSelectAll">
                        <div class="custom-checkbox" :class="{ checked: selectAll }">
                            <svg v-if="selectAll" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <span>Chọn tất cả ({{ cartItems.length }})</span>
                    </label>
                    <button class="btn-clear" @click="clearCart" v-if="cartItems.length > 0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Xóa tất cả
                    </button>
                </div>

                <!-- Cart Items List -->
                <TransitionGroup name="cart-item" tag="div" class="items-list">
                    <div v-for="item in cartItems" :key="item.cart_item_id" class="cart-item-card" :class="{ 'item-unavailable': item.variant?.status !== 'active' }">
                        <!-- Checkbox -->
                        <div class="item-checkbox" @click="toggleSelect(item)">
                            <div class="custom-checkbox" :class="{ checked: item.selected }">
                                <svg v-if="item.selected" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>

                        <!-- Product Image -->
                        <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-image-link">
                            <img :src="getProductImage(item)" :alt="item.product?.name" class="item-image" />
                        </router-link>

                        <!-- Product Info -->
                        <div class="item-details">
                            <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-name">
                                {{ item.product?.name || 'Sản phẩm' }}
                            </router-link>

                            <!-- Variant Tag + Change Button -->
                            <div class="item-variant-row" v-if="item.variant">
                                <span class="item-variant-tag">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    {{ getVariantLabel(item) || item.variant.variant_name || '—' }}
                                </span>
                                <!-- Chỉ hiện nút đổi nếu sản phẩm có màu hoặc size -->
                                <button
                                    v-if="item.variant.color || item.variant.size"
                                    class="btn-change-variant"
                                    @click="openVariantModal(item)"
                                    title="Đổi màu / kích thước"
                                >
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Đổi
                                </button>
                            </div>

                            <div class="item-stock" v-if="item.variant?.stock <= 5 && item.variant?.stock > 0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Chỉ còn {{ item.variant.stock }} sản phẩm
                            </div>
                            <div class="item-unavailable-badge" v-if="item.variant?.status !== 'active'">
                                Sản phẩm ngừng kinh doanh
                            </div>
                        </div>

                        <!-- Price & Quantity -->
                        <div class="item-price-qty">
                            <div class="item-price">{{ formatPrice(item.variant?.price) }}</div>
                            <div class="quantity-control">
                                <button class="qty-btn" @click="updateQuantity(item, item.quantity - 1)" :disabled="item.quantity <= 1 || updating[item.cart_item_id]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                                <span class="qty-display" :class="{ 'qty-updating': updating[item.cart_item_id] }">{{ item.quantity }}</span>
                                <button class="qty-btn" @click="updateQuantity(item, item.quantity + 1)" :disabled="item.quantity >= (item.variant?.stock || 0) || updating[item.cart_item_id]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                            </div>
                            <div class="item-total">{{ formatPrice((item.variant?.price || 0) * item.quantity) }}</div>
                        </div>

                        <!-- Remove Button -->
                        <button class="btn-remove" @click="removeItem(item)" title="Xóa">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </TransitionGroup>

                <!-- ── Quick Add Slider ── -->
                <QuickAddSlider />
            </div>

            <!-- Cột phải: Tóm tắt đơn hàng -->
            <div class="order-summary animate-in" style="animation-delay: 0.2s">
                <div class="summary-card">
                    <h3 class="summary-title">Tóm Tắt Đơn Hàng</h3>
                    
                    <div class="summary-row">
                        <span>Số lượng sản phẩm đã chọn</span>
                        <strong>{{ totalSelectedQuantity }}</strong>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="summary-row summary-total">
                        <span>Tạm tính</span>
                        <strong class="total-price">{{ formatPrice(totalPrice) }}</strong>
                    </div>

                    <p class="summary-note">Phí vận chuyển sẽ được tính ở bước thanh toán</p>

                    <button class="btn-checkout" @click="proceedToCheckout" :disabled="selectedItems.length === 0">
                        Tiến Hành Thanh Toán ({{ totalSelectedQuantity }})
                    </button>

                    <router-link to="/product" class="btn-continue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Tiếp tục mua sắm
                    </router-link>
                </div>
            </div>
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
                            <img 
                                :src="modalSelectedVariant?.image_url && modalSelectedVariant?.image_url !== '0' ? 'http://localhost:8383/storage/' + modalSelectedVariant.image_url : getProductImage(variantModal.item)" 
                                :alt="variantModal.item.product?.name" 
                                class="vmodal-product-img" 
                            />
                            <div class="vmodal-product-info">
                                <h3 class="vmodal-title">Đổi phân loại hàng</h3>
                                <p class="vmodal-product-name" :title="variantModal.item.product?.name">{{ variantModal.item.product?.name }}</p>
                            </div>
                        </div>
                        <button class="vmodal-close" @click="closeVariantModal" title="Đóng">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
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
                                <button
                                    v-for="color in modalUniqueColors"
                                    :key="color"
                                    class="vmodal-opt-btn"
                                    :class="{ active: variantModal.selectedColor === color }"
                                    @click="onModalColorSelect(color)"
                                >{{ color }}</button>
                            </div>
                        </div>

                        <!-- Chọn kích thước -->
                        <div class="vmodal-section" v-if="modalAvailableSizes.some(s => s.size)">
                            <p class="vmodal-label">Kích thước:</p>
                            <div class="vmodal-options">
                                <button
                                    v-for="s in modalAvailableSizes"
                                    :key="s.size"
                                    class="vmodal-opt-btn"
                                    :class="{ active: variantModal.selectedSize === s.size, 'out-of-stock': s.stock <= 0 }"
                                    :disabled="s.stock <= 0"
                                    @click="variantModal.selectedSize = s.size"
                                >
                                    {{ s.size }}
                                    <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{ s.stock }})</span>
                                    <span v-else-if="s.stock <= 0" class="vmodal-opt-stock">Hết</span>
                                </button>
                            </div>
                        </div>

                        <!-- Thông tin variant đã chọn -->
                        <div class="vmodal-selected-info" v-if="modalSelectedVariant">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>
                                Đã chọn:
                                <strong>{{ [modalSelectedVariant.color, modalSelectedVariant.size].filter(Boolean).join(' / ') || modalSelectedVariant.variant_name }}</strong>
                                — {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(modalSelectedVariant.price) }}
                                <span v-if="modalSelectedVariant.stock <= 5" class="vmodal-low-stock">(còn {{ modalSelectedVariant.stock }})</span>
                            </span>
                        </div>
                        <div class="vmodal-selected-info vmodal-unselected" v-else>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>Vui lòng chọn {{ modalHasColors ? 'màu sắc' : '' }}{{ modalHasColors && modalAvailableSizes.some(s=>s.size) ? ' và ' : '' }}{{ modalAvailableSizes.some(s=>s.size) ? 'kích thước' : '' }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="vmodal-footer">
                            <button class="vmodal-btn-cancel" @click="closeVariantModal">Hủy bỏ</button>
                            <button
                                class="vmodal-btn-confirm"
                                :disabled="!modalSelectedVariant || variantModal.confirming"
                                @click="confirmVariantChange"
                            >
                                <span v-if="variantModal.confirming">Đang cập nhật...</span>
                                <span v-else>Xác nhận</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cart-page {
    padding: 24px 0 60px;
    font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
    color: #102a43;
    min-height: 60vh;
}

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 32px;
}
.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: #E63B6F;
    margin-bottom: 8px;
}
.page-header p {
    font-size: 1rem;
    color: #627d98;
}

/* Loading */
.loading-state {
    text-align: center;
    padding: 80px 20px;
    color: #627d98;
}
.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e8ecf1;
    border-top-color: #E63B6F;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 16px;
    border: 1px dashed #b0c4de;
}
.empty-icon { margin-bottom: 24px; }
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
}
.cart-items-section {
    flex: 1;
}

/* Action Bar */
.cart-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8ecf1;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
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
    width: 20px;
    height: 20px;
    border: 2px solid #c8d6e0;
    border-radius: 5px;
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
    padding: 7px 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff5f5;
    color: #dc2626;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.btn-clear:hover {
    background: #fee2e2;
    border-color: #f87171;
}

/* Cart Items */
.items-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
}
.cart-item-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8ecf1;
    transition: all 0.25s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.cart-item-card:hover {
    border-color: rgba(230, 59, 111, 0.25);
    box-shadow: 0 4px 16px rgba(230, 59, 111, 0.06);
}
.cart-item-card.item-unavailable {
    opacity: 0.55;
    background: #fafafa;
}

/* Checkbox */
.item-checkbox {
    cursor: pointer;
    flex-shrink: 0;
}

/* Product Image */
.item-image-link {
    flex-shrink: 0;
}
.item-image {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #eef2f6;
    transition: transform 0.3s;
}
.item-image:hover {
    transform: scale(1.05);
}

/* Product Details */
.item-details {
    flex: 1;
    min-width: 0;
}
.item-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a2b4a;
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
    transition: color 0.2s;
}
.item-name:hover { color: #E63B6F; }
.item-variant {
    font-size: 0.82rem;
    color: #627d98;
    margin-top: 4px;
    font-weight: 500;
}
.item-stock {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    color: #f59e0b;
    font-weight: 600;
    margin-top: 6px;
}
.item-unavailable-badge {
    font-size: 0.78rem;
    color: #dc2626;
    font-weight: 600;
    margin-top: 6px;
}

/* Price & Quantity */
.item-price-qty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 140px;
}
.item-price {
    font-size: 0.92rem;
    font-weight: 700;
    color: #ef5350;
}
.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}
.qty-btn {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #486581;
    transition: all 0.15s;
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
    width: 38px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 700;
    color: #102a43;
    border-left: 1px solid #e8ecf1;
    border-right: 1px solid #e8ecf1;
    line-height: 34px;
}
.qty-updating {
    color: #a0aec0;
}
.item-total {
    font-size: 0.85rem;
    font-weight: 800;
    color: #1a2b4a;
}

/* Remove Button */
.btn-remove {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a0aec0;
    border-radius: 8px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.btn-remove:hover {
    background: #fff5f5;
    color: #dc2626;
}

/* Order Summary */
.order-summary {
    width: 340px;
    flex-shrink: 0;
    position: sticky;
    top: 100px;
}
.summary-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8ecf1;
    padding: 24px;
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
.summary-row strong { color: #102a43; }
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
    font-size: 1.3rem;
    font-weight: 800;
    color: #ef5350 !important;
}
.summary-note {
    font-size: 0.8rem;
    color: #829ab1;
    text-align: center;
    margin: 12px 0 20px;
    font-style: italic;
}
.btn-checkout {
    width: 100%;
    padding: 14px;
    background:  #E63B6F;
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    border: none;
    border-radius: 10px;
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
    gap: 6px;
    width: 100%;
    padding: 11px;
    margin-top: 10px;
    color: #E63B6F;
    border: 1px solid #c8d6e0;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    transition: all 0.2s;
}
.btn-continue:hover {
    background: #f0f7fa;
    border-color: #E63B6F;
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
    background: #039be5;
    transform: translateY(-1px);
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
.toast-enter-active { animation: slideInRight 0.3s ease; }
.toast-leave-active { animation: slideOutRight 0.3s ease; }
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes slideOutRight {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(40px); }
}

.cart-item-enter-active { animation: fadeSlideIn 0.3s ease; }
.cart-item-leave-active { animation: fadeSlideOut 0.25s ease; }
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes fadeSlideOut {
    from { opacity: 1; transform: translateX(0); height: auto; }
    to { opacity: 0; transform: translateX(20px); height: 0; padding: 0; margin: 0; overflow: hidden; }
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 900px) {
    .cart-layout {
        flex-direction: column;
    }
    .order-summary {
        width: 100%;
        position: static;
    }
    .cart-item-card {
        flex-wrap: wrap;
        gap: 12px;
    }
    .item-price-qty {
        flex-direction: row;
        min-width: auto;
        gap: 12px;
        width: 100%;
        justify-content: space-between;
        padding-left: 36px;
    }
}

@media (max-width: 600px) {
    .item-image {
        width: 70px;
        height: 70px;
    }
    .cart-item-card {
        padding: 12px 14px;
    }
}

/* ====== VARIANT ROW + CHANGE BTN ====== */
.item-variant-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.item-variant-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #627d98;
    font-weight: 500;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 3px 10px;
    line-height: 1.4;
}
.btn-change-variant {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border: 1px solid #E63B6F;
    border-radius: 20px;
    background: transparent;
    color: #E63B6F;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s;
    white-space: nowrap;
}
.btn-change-variant:hover {
    background: #E63B6F;
    color: white;
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
    box-shadow: 0 24px 60px rgba(230, 59, 111, 0.15), 0 8px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
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
.vmodal-close:hover { background: #f1f5f9; color: #0f172a; }

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
    width: 24px; height: 24px;
    border: 2px solid #e2e8f0;
    border-top-color: #E63B6F;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.vmodal-section { padding: 16px 24px 0; }
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
.vmodal-opt-btn:hover:not(:disabled) { border-color: #E63B6F; color: #E63B6F; }
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
.vmodal-opt-stock { font-size: 0.72rem; font-weight: 500; opacity: 0.85; }

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
.vmodal-selected-info strong { font-weight: 700; }
.vmodal-low-stock { color: #f59e0b; font-weight: 600; }
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
.vmodal-btn-cancel:hover { background: #f8fafc; border-color: #94a3b8; }
.vmodal-btn-confirm {
    flex: 2;
    padding: 11px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #E63B6F, #039be5);
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}
.vmodal-btn-confirm:hover:not(:disabled) {
    background: linear-gradient(135deg, #C4305D, #E63B6F);
    transform: translateY(-1px);
}
.vmodal-btn-confirm:disabled {
    background: #c8d6e0;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Modal Transition */
.vmodal-enter-active, .vmodal-leave-active { transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.vmodal-enter-from, .vmodal-leave-to { opacity: 0; }
.vmodal-enter-from .vmodal-box, .vmodal-leave-to .vmodal-box { transform: scale(0.94) translateY(20px); }
</style>
