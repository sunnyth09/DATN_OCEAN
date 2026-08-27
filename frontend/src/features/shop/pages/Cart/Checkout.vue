<script setup>
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/axios';
import { useCartUpsell } from '@/composables/useCartUpsell';
import { useToast } from '@/composables/useToast';
import AddressSelector from '@/components/AddressSelector.vue';
import CheckoutAddressModal from '@/features/shop/components/CheckoutAddressModal.vue';
import SepayPaymentModal from '@/components/SepayPaymentModal.vue';
import { addressService } from '@/services/addressService';
import { orderService } from '@/services/orderService';
import { walletService } from '@/services/walletService';
import { loyaltyService } from '@/services/loyaltyService';
import { useAuthStore } from '@/stores/auth';
import { sanitizeAddressPayload, validateAddressPayload } from '@/utils/addressValidation';
import AppIcon from '@/components/AppIcon.vue';

// Debounce helper — tránh gửi quá nhiều request liên tiếp gây rate-limit 429
const debounce = (fn, delay) => {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const cartItems = ref([]);
const loading = ref(true);

const isFlashSale = computed(() => !!route.query.flash_sale_id && !!route.query.product_id);
const flashSaleId = computed(() => route.query.flash_sale_id);
const flashSaleProductId = computed(() => route.query.product_id);

// --- Mua nhanh (Buy Now): chỉ đặt riêng 1 sản phẩm, không lấy từ giỏ ---
const isBuyNow = computed(() => route.query.buy_now === '1');
const buyNowItem = ref(null); // { variant_id, quantity }
const { showToast } = useToast();

const { state: upsellState, fetchUpsellData } = useCartUpsell();

const APP_URL = import.meta.env.VITE_BASE_URL;

// --- Địa chỉ ---
const addresses = ref([]);
const selectedAddressId = ref(null);
const showAddAddressForm = ref(false);
const showAddressModal = ref(false);
const addressSelectorKey = ref(0);
const formAddress = ref({
    recipient_name: '',
    phone: '',
    address_line: '',
    ward: '', district: '', province: '',
    ward_code: '', district_code: '', province_code: '',
    address_type: 'home',
    is_default: false,
});

// --- Email nhận xác nhận đơn hàng ---
// Khách đăng nhập: prefill từ email tài khoản (vẫn cho phép sửa).
// Khách vãng lai: bắt buộc nhập để nhận mail xác nhận.
const email = ref(authStore.email || '');

// --- GHN Data ---
const isCalculatingFee = ref(false);
const leadtimeDate = ref(null);


// --- Thanh toán & Khác ---
const paymentMethod = ref('cod'); // cod, vnpay, banking, wallet
const walletBalance = ref(0);
const note = ref('');

// --- Banking QR Modal ---
const showBankingModal = ref(false);
const bankingInfo = ref(null); // { bank_bin, account_number, account_name, amount, order_code, qr_url }
const bankingOrderCode = ref('');

const onBankingPayLater = () => {
    showToast('Sản phẩm/Đơn hàng của bạn chưa thanh toán! Vui lòng hoàn tất trong 15 phút tại Đơn hàng của tôi.', 'warning');
    if (authStore.isAuthenticated) {
        router.push({ name: 'profile-orders' });
    } else {
        router.push({ name: 'order-success', params: { order_code: bankingOrderCode.value } });
    }
};

const onBankingPaySuccess = () => {
    showToast('Thanh toán đơn hàng thành công!', 'success');
};

// --- Coupon ---
const couponCode = ref('');
const appliedCoupon = ref(null);
const checkingCoupon = ref(false);
const showCouponModal = ref(false);
const availableCoupons = ref([]);
const loadingCoupons = ref(false);

// --- Wallet ---
const useWallet = ref(false);
const walletPreview = ref(null); // { deposit_available, commission_available, max_commission, total_available }
const walletLoading = ref(false);

// Format tiền VND
const selectedSavedAddress = computed(() => addresses.value.find((address) => address.address_id === selectedAddressId.value));

const inlineSavedAddresses = computed(() => {
    const firstThree = addresses.value.slice(0, 3);
    const selected = selectedSavedAddress.value;

    if (!selected || firstThree.some((address) => address.address_id === selected.address_id)) {
        return firstThree;
    }

    return [selected, ...firstThree.filter((address) => address.address_id !== selected.address_id).slice(0, 2)];
});

const hasMoreSavedAddresses = computed(() => addresses.value.length > 3);

const remainingAddressCount = computed(() => Math.max(addresses.value.length - 3, 0));

// Format tiền VND
const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

// Lấy giỏ hàng
const fetchCart = async () => {
    try {
        if (authStore.isAuthenticated) {
            const response = await api.get('/cart');
            if (response.data.status === 'success') {
                cartItems.value = (response.data.data.items || []).filter(i => i.selected && i.is_available !== false);
                if (cartItems.value.length === 0) {
                    router.push('/cart');
                }
            }
        } else {
            const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
            const selectedLocalItems = localItems.filter(i => i.selected !== false && i.is_available !== false);
            if (selectedLocalItems.length === 0) {
                router.push('/cart');
                return;
            }
            const response = await api.post('/cart/guest-details', { items: selectedLocalItems });
            if (response.data.status === 'success') {
                cartItems.value = (response.data.data.items || []).filter(i => i.is_available !== false);
                if (response.data.data.freeship_threshold) {
                    upsellState.freeshipThreshold = response.data.data.freeship_threshold;
                }
            }
        }
    } catch (error) {
        console.error('Lỗi khi tải giỏ hàng thanh toán:', error);
        if (error.response?.status === 401 && authStore.isAuthenticated) {
            router.push({ name: 'login', query: { redirect: '/checkout' } });
        }
    }
};

// Lấy đúng 1 sản phẩm cho chế độ Mua nhanh (Buy Now)
const fetchBuyNowItem = async () => {
    try {
        const raw = sessionStorage.getItem('buy_now_item');
        const item = raw ? JSON.parse(raw) : null;
        if (!item || !item.variant_id) {
            router.push('/cart');
            return;
        }
        buyNowItem.value = { variant_id: item.variant_id, quantity: item.quantity || 1 };

        const response = await api.post('/cart/guest-details', { items: [buyNowItem.value] });
        if (response.data.status === 'success') {
            cartItems.value = response.data.data.items || [];
            if (response.data.data.freeship_threshold) {
                upsellState.freeshipThreshold = response.data.data.freeship_threshold;
            }
        }
    } catch (error) {
        console.error('Lỗi khi tải sản phẩm mua nhanh:', error);
        router.push('/cart');
    }
};

// Lấy điểm thưởng (Loyalty Points)
const loyaltyPoints = ref(0);
const useLoyaltyPoints = ref(false);
const inputPoints = ref(0);

const fetchLoyaltyPoints = async () => {
    if (!authStore.isAuthenticated) return;
    try {
        const res = await loyaltyService.getSummary();
        if (res.data?.status === 'success') {
            loyaltyPoints.value = res.data.data.current_balance || 0;
        }
    } catch (e) {
        console.error('Lỗi khi lấy điểm thưởng:', e);
    }
};

watch(() => authStore.isAuthenticated, (val) => {
    if (val) {
        fetchLoyaltyPoints();
    } else {
        appliedCoupon.value = null;
        couponCode.value = '';
        showCouponModal.value = false;
        useWallet.value = false;
        if (paymentMethod.value === 'wallet') paymentMethod.value = 'cod';
    }
}, { immediate: true });

// Lấy sản phẩm Flash Sale thay vì giỏ hàng
const fetchFlashSaleData = async () => {
    try {
        const { data } = await api.get('flash-sale');
        const list = data.data ?? [];
        const item = list.find(s => s.id == flashSaleId.value && s.product_id == flashSaleProductId.value);
        if (item) {
            cartItems.value = [{
                cart_item_id: 'fs_' + item.id,
                product: {
                    name: item.product_name,
                    thumbnail_url: item.product_thumbnail,
                },
                variant: {
                    price: item.sale_price,
                    color: 'Flash Sale',
                    size: 'Đặc biệt'
                },
                quantity: 1,
            }];
            shippingFee.value = 0; // Flash sale freeship
        } else {
            showToast('Không tìm thấy sản phẩm Flash Sale này.', 'error');
            router.push('/flash-sale');
        }
    } catch (e) {
        showToast('Lỗi khi tải dữ liệu Flash Sale.', 'error');
        router.push('/flash-sale');
    }
};

// Lấy danh sách địa chỉ
const fetchAddresses = async () => {
    if (!authStore.isAuthenticated) {
        addresses.value = [];
        selectedAddressId.value = null;
        showAddAddressForm.value = true;
        return;
    }
    try {
        const res = await addressService.listProfileAddresses();
        const payload = res.data?.data;
        addresses.value = Array.isArray(payload) ? payload : (payload?.data || []);
        if (addresses.value.length === 0) {
            selectedAddressId.value = null;
            showAddAddressForm.value = true;
            return;
        }

        // Tự động chọn địa chỉ mặc định hoặc cái đầu tiên
        const defaultAddr = addresses.value.find(a => a.is_default);
        if (defaultAddr) selectedAddressId.value = defaultAddr.address_id;
        else if (addresses.value.length > 0) selectedAddressId.value = addresses.value[0].address_id;
        showAddAddressForm.value = false;
    } catch (e) {
        console.error('Lỗi tải địa chỉ:', e);
    }
};

// Lấy danh sách mã giảm giá khả dụng
const fetchCoupons = async () => {
    if (!authStore.isAuthenticated) {
        availableCoupons.value = [];
        return;
    }
    loadingCoupons.value = true;
    try {
        // Fetch các mã giảm giá user ĐÃ LƯU
        const res = await api.get('/profile/coupons');
        if (res.data?.status === 'success') {
            availableCoupons.value = res.data.data;
        }
    } catch (e) {
        console.error('Lỗi tải mã giảm giá:', e);
    } finally {
        loadingCoupons.value = false;
    }
};

// Đã bỏ tính năng ShippingZone tự làm do sử dụng GHN API


// Format full address để render UI
const formatFullAddress = (addr) => {
    const parts = [];
    if (addr.address_line) parts.push(addr.address_line);
    if (addr.ward) parts.push(addr.ward);
    if (addr.district) parts.push(addr.district);
    if (addr.province) parts.push(addr.province);
    return [...new Set(parts)].join(', ') || 'Chưa có thông tin địa chỉ cụ thể';
};

// Xử lý tạo địa chỉ mới
const onAddressChange = (data) => {
    formAddress.value.province = data.province_name;
    formAddress.value.province_code = data.province_code;
    // Ocean Express không có Quận/Huyện — district_name rỗng, district_code = province_code
    formAddress.value.district = data.district_name || '';
    formAddress.value.district_code = data.district_code || data.province_code;
    formAddress.value.ward = data.ward_name;
    formAddress.value.ward_code = data.ward_code;
    formAddress.value.address_line = data.address_detail;

    if (data.ward_code) {
        getShippingFee(data.ward_code);
    } else {
        shippingFee.value = 0;
    }
};

const openAddAddressForm = () => {
    formAddress.value = {
        recipient_name: '', phone: '', address_line: '',
        ward: '', district: '', province: '',
        ward_code: '', district_code: '', province_code: '',
        address_type: 'home', is_default: false,
    };
    selectedAddressId.value = null;
    shippingFee.value = 0;
    addressSelectorKey.value += 1;
    showAddAddressForm.value = true;
};

const useSavedAddresses = () => {
    showAddAddressForm.value = false;

    if (!selectedAddressId.value) return;

    const savedAddress = addresses.value.find((addr) => addr.address_id === selectedAddressId.value);
    if (savedAddress?.ward_code) {
        getShippingFee(savedAddress.ward_code);
    }
};

const openAddressModal = () => {
    showAddressModal.value = true;
};

const closeAddressModal = () => {
    showAddressModal.value = false;
};

const confirmAddressSelection = (addressId) => {
    selectedAddressId.value = addressId;
    showAddressModal.value = false;
    showAddAddressForm.value = false;
};

// --- Xử lý tính toán hóa đơn ---
const totalQuantity = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
});

const subtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.variant?.price || 0) * item.quantity, 0);
});

const shippingFee = ref(0);

const shippingDiscount = computed(() => {
    let disc = 0;
    if (subtotal.value >= upsellState.freeshipThreshold) {
        disc = shippingFee.value;
    }
    if (appliedCoupon.value && appliedCoupon.value.type === 'free_ship') {
        const val = parseFloat(appliedCoupon.value.value) || shippingFee.value;
        disc = Math.max(disc, Math.min(val, shippingFee.value));
    }
    return disc;
});

const effectiveShippingFee = computed(() => Math.max(0, shippingFee.value - shippingDiscount.value));

const isShippingFree = computed(() => shippingFee.value > 0 && effectiveShippingFee.value === 0);

const shippingWeight = computed(() => {
    return Math.max(
        10,
        cartItems.value.reduce((sum, item) => {
            const weight = Number(item.product?.weight || item.weight || 500);
            return sum + weight * Number(item.quantity || 1);
        }, 0)
    );
});

const getShippingFee = async (ward_code) => {
    if (!ward_code) return;
    isCalculatingFee.value = true;
    try {
        shippingFee.value = await addressService.getShippingFee({
            wardCode: ward_code,
            weight: shippingWeight.value,
        });
        // Ocean Express không có leadtime endpoint — ẩn estimated delivery
        leadtimeDate.value = null;
    } catch (error) {
        console.error('Lỗi tính phí vận chuyển Ocean Express:', error.response?.data || error.message);
        shippingFee.value = 0;
        leadtimeDate.value = null;
    } finally {
        isCalculatingFee.value = false;
    }
};

// Watch for address selection change in the list
// Debounced — tránh gọi GHN API liên tiếp khi user đang chọn địa chỉ
const debouncedGetShippingFee = debounce((wardCode) => {
    getShippingFee(wardCode);
}, 300);

watch(selectedAddressId, (newVal) => {
    if (newVal && !showAddAddressForm.value) {
        const addr = addresses.value.find(a => a.address_id === newVal);
        if (addr?.ward_code) {
            debouncedGetShippingFee(addr.ward_code);
        } else {
            shippingFee.value = 0;
        }
    }
});

const discount = computed(() => {
    if (!appliedCoupon.value) return 0;

    let disc = 0;
    const type = appliedCoupon.value.type;
    const value = parseFloat(appliedCoupon.value.value) || 0;
    const maxDiscount = parseFloat(appliedCoupon.value.max_discount_value) || 0;

    if (type === 'percent' || type === 'percentage') {
        disc = (subtotal.value * value) / 100;
        if (maxDiscount > 0 && disc > maxDiscount) {
            disc = maxDiscount;
        }
        return Math.min(disc, subtotal.value);
    } else if (type === 'free_ship') {
        // Free ship coupon is handled in shippingDiscount
        return 0;
    } else {
        // fixed
        disc = value;
        return Math.min(disc, subtotal.value);
    }
});

const maxPointsCanUse = computed(() => {
    let max = loyaltyPoints.value;
    const totalBeforeLoyalty = Math.max(0, subtotal.value + shippingFee.value - discount.value - shippingDiscount.value);
    const maxForTotal = Math.floor((totalBeforeLoyalty * 0.3) / 100);
    return Math.min(max, maxForTotal);
});

watch(useLoyaltyPoints, (val) => {
    if (val) {
        inputPoints.value = maxPointsCanUse.value;
    } else {
        inputPoints.value = 0;
    }
});

watch(inputPoints, (val) => {
    if (val > maxPointsCanUse.value) {
        inputPoints.value = maxPointsCanUse.value;
    } else if (val < 0) {
        inputPoints.value = 0;
    }
});

const loyaltyDiscount = computed(() => {
    if (!useLoyaltyPoints.value) return 0;
    return (inputPoints.value || 0) * 100;
});

const total = computed(() => {
    const base = Math.max(0, subtotal.value + shippingFee.value - discount.value - shippingDiscount.value - loyaltyDiscount.value);
    return Math.max(0, base - walletDiscount.value);
});

// --- Wallet ---
const walletDiscount = computed(() => {
    if (!useWallet.value || !walletPreview.value) return 0;
    const maxDiscount = subtotal.value + shippingFee.value - discount.value - shippingDiscount.value - loyaltyDiscount.value;
    return Math.min(walletPreview.value.total_available || 0, Math.max(0, maxDiscount));
});

const fetchWalletPreview = async () => {
    if (!authStore.isAuthenticated) return;
    walletLoading.value = true;
    try {
        const res = await walletService.previewDiscount(subtotal.value);
        if (res.data?.status === 'success') {
            walletPreview.value = res.data.data;
        }
    } catch (e) {
        console.error('Wallet preview error', e);
    } finally {
        walletLoading.value = false;
    }
};

// Debounced wallet preview — tránh gọi liên tục mỗi khi subtotal thay đổi
const debouncedFetchWalletPreview = debounce(fetchWalletPreview, 500);

watch(subtotal, () => {
    if (authStore.isAuthenticated) debouncedFetchWalletPreview();
});

watch(useWallet, (val) => {
    if (val && !walletPreview.value) debouncedFetchWalletPreview();
});

const promptLoginForCoupon = () => {
    showToast('Vui lòng đăng nhập để sử dụng mã giảm giá.', 'warning');
    router.push({ name: 'login', query: { redirect: route.fullPath || '/checkout' } });
};

const formatVND = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val) || 0);

const applyCoupon = async () => {
    if (!authStore.isAuthenticated) {
        promptLoginForCoupon();
        return;
    }
    if (!couponCode.value.trim()) return;

    const code = couponCode.value.trim().toUpperCase();
    checkingCoupon.value = true;
    try {
        const response = await api.post('/profile/coupons/check', {
            code: code,
            subtotal: subtotal.value
        });
        if (response.data?.status === 'success') {
            const couponData = response.data.data;
            selectCoupon(couponData);
        } else {
            showToast(response.data?.message || 'Mã giảm giá không hợp lệ', 'error');
            appliedCoupon.value = null;
        }
    } catch (error) {
        console.error('Lỗi kiểm tra mã giảm giá:', error);
        const msg = error.response?.data?.message || 'Mã giảm giá không hợp lệ hoặc đã hết hạn';
        showToast(msg, 'error');
        appliedCoupon.value = null;
    } finally {
        checkingCoupon.value = false;
    }
};

const openCouponModal = () => {
    if (!authStore.isAuthenticated) {
        promptLoginForCoupon();
        return;
    }
    showCouponModal.value = true;
};

const selectCoupon = (coupon) => {
    if (!authStore.isAuthenticated) {
        promptLoginForCoupon();
        return;
    }
    if (coupon.min_order_value && subtotal.value < parseFloat(coupon.min_order_value)) {
        showToast(`Đơn hàng tối thiểu ${formatVND(coupon.min_order_value)} để áp dụng mã này!`, 'error');
        return;
    }
    if (coupon.type === 'free_ship' && subtotal.value >= (upsellState.freeshipThreshold || 500000)) {
        showToast('Đơn hàng từ 500.000₫ đã được tự động miễn phí vận chuyển!', 'warning');
        return;
    }
    appliedCoupon.value = {
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        max_discount_value: coupon.max_discount_value,
        min_order_value: coupon.min_order_value
    };
    couponCode.value = coupon.code;
    showCouponModal.value = false;
    showToast(`Đã áp dụng mã giảm giá ${coupon.code}!`, 'success');
};

const removingCoupon = () => {
    appliedCoupon.value = null;
    couponCode.value = '';
};

watch(subtotal, (newSubtotal) => {
    if (appliedCoupon.value) {
        if (newSubtotal >= (upsellState.freeshipThreshold || 500000) && appliedCoupon.value.type === 'free_ship') {
            appliedCoupon.value = null;
            couponCode.value = '';
            showToast('Đơn hàng từ 500.000₫ đã được tự động miễn phí vận chuyển. Mã freeship đã được gỡ bỏ!', 'warning');
        } else if (appliedCoupon.value.min_order_value && newSubtotal < parseFloat(appliedCoupon.value.min_order_value)) {
            appliedCoupon.value = null;
            couponCode.value = '';
            showToast(`Tổng tiền đơn hàng không còn đủ ${formatVND(appliedCoupon.value.min_order_value)} để áp dụng mã giảm giá.`, 'warning');
        }
    }
});

// ====== VARIANT CHANGE IN CHECKOUT ======
const isFlashSaleOrder = computed(() => isFlashSale.value || cartItems.value.some(i => i.is_flash_sale || i.flash_sale_id));
const isDirectOrder = isBuyNow;

const variantModal = ref({
    show: false,
    item: null,
    variants: [],
    loadingVariants: false,
    selectedColor: null,
    selectedSize: null,
    confirming: false,
});

const openVariantModal = async (item) => {
    if (isFlashSale.value || item?.is_flash_sale || item?.flash_sale_id) {
        showToast('Sản phẩm Flash Sale không hỗ trợ đổi phân loại.', 'warning');
        return;
    }
    const productId = item?.product?.product_id || item?.product?.id || item?.product_id;
    if (!productId) return;

    variantModal.value.show = true;
    variantModal.value.item = item;
    variantModal.value.variants = [];
    variantModal.value.loadingVariants = true;
    variantModal.value.selectedColor = item?.variant?.color || null;
    variantModal.value.selectedSize = item?.variant?.size || null;

    try {
        const res = await api.get(`/products/${productId}/variants`);
        variantModal.value.variants = res.data?.data || [];
    } catch (e) {
        showToast('Không thể tải thông tin phân loại sản phẩm.', 'error');
        variantModal.value.show = false;
    } finally {
        variantModal.value.loadingVariants = false;
    }
};

const closeVariantModal = () => {
    variantModal.value.show = false;
    variantModal.value.item = null;
};

const modalUniqueColors = computed(() => {
    return [...new Set(variantModal.value.variants.map(v => v.color).filter(Boolean))];
});

const modalHasColors = computed(() => modalUniqueColors.value.length > 0);

const modalAvailableSizes = computed(() => {
    const variants = variantModal.value.variants;
    if (!variants.length) return [];

    const filtered = variantModal.value.selectedColor
        ? variants.filter(v => v.color === variantModal.value.selectedColor)
        : variants;

    const sizeMap = {};
    filtered.forEach(v => {
        const key = v.size || '__no_size__';
        if (!sizeMap[key]) sizeMap[key] = { size: v.size, stock: 0, variant_id: v.variant_id };
        sizeMap[key].stock += v.stock;
        sizeMap[key].variant_id = v.variant_id;
    });
    return Object.values(sizeMap);
});

const modalSelectedVariant = computed(() => {
    const vars = variantModal.value.variants;
    const color = variantModal.value.selectedColor;
    const size = variantModal.value.selectedSize;

    if (!vars.length) return null;

    if (!modalHasColors.value && size) {
        return vars.find(v => v.size === size) || null;
    }
    if (color && size) {
        return vars.find(v => v.color === color && v.size === size) || null;
    }
    if (color && !modalAvailableSizes.value.some(s => s.size)) {
        return vars.find(v => v.color === color) || null;
    }
    return null;
});

const onModalColorSelect = (color) => {
    variantModal.value.selectedColor = color;
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
    const newVariantId = modalSelectedVariant.value.variant_id;

    try {
        if (isBuyNow.value) {
            // Mua ngay
            buyNowItem.value = {
                variant_id: newVariantId,
                quantity: item.quantity || 1,
            };
            sessionStorage.setItem('buy_now_item', JSON.stringify(buyNowItem.value));
            await fetchBuyNowItem();
            showToast('Đã cập nhật phân loại sản phẩm!', 'success');
            closeVariantModal();
        } else if (!authStore.isAuthenticated) {
            // Khách vãng lai
            let localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
            const idx = localItems.findIndex(i => i.variant_id === item.variant_id);
            if (idx !== -1) {
                const existingIdx = localItems.findIndex(i => i.variant_id === newVariantId);
                if (existingIdx !== -1 && existingIdx !== idx) {
                    localItems[existingIdx].quantity += item.quantity;
                    localItems.splice(idx, 1);
                } else {
                    localItems[idx].variant_id = newVariantId;
                }
                localStorage.setItem('cart_items', JSON.stringify(localItems));
            }
            await fetchCart();
            showToast('Đã cập nhật phân loại sản phẩm!', 'success');
            closeVariantModal();
        } else {
            // User đã đăng nhập
            const res = await api.put(`/cart/items/${item.cart_item_id}/variant`, {
                variant_id: newVariantId,
            });
            if (res.data.status === 'success') {
                showToast('Đã cập nhật phân loại sản phẩm!', 'success');
                closeVariantModal();
                await fetchCart();
            }
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Không thể đổi phân loại sản phẩm.';
        showToast(msg, 'error');
    } finally {
        variantModal.value.confirming = false;
    }
};

// Đặt hàng
const placingOrder = ref(false);
const placeOrder = async () => {
    const payload = {
        payment_method: paymentMethod.value,
        note: note.value,
        coupon_applied: authStore.isAuthenticated ? (appliedCoupon.value?.code || null) : null,
        referral_code: localStorage.getItem('affiliate_ref') || null,
        use_wallet: authStore.isAuthenticated && useWallet.value && walletDiscount.value > 0,
        wallet_amount: authStore.isAuthenticated && useWallet.value ? walletDiscount.value : 0,
        reward_points_used: useLoyaltyPoints.value ? inputPoints.value : 0,
    };

    // --- Email nhận xác nhận đơn hàng (bắt buộc) ---
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim()) {
        return showToast('Vui lòng nhập email để nhận xác nhận đơn hàng', 'error');
    }
    if (!emailRegex.test(email.value.trim())) {
        return showToast('Email không hợp lệ', 'error');
    }
    payload.email = email.value.trim();

    if (showAddAddressForm.value) {
        const validation = validateAddressPayload(formAddress.value);
        if (!validation.valid) {
            return showToast(validation.firstError, 'error');
        }

        const addressPayload = sanitizeAddressPayload(formAddress.value);
        payload.recipient_name = addressPayload.recipient_name;
        payload.phone = addressPayload.phone;
        payload.province = addressPayload.province;
        payload.district = addressPayload.district;
        payload.ward = addressPayload.ward;
        payload.address_line = addressPayload.address_line;
        payload.province_code = addressPayload.province_code;
        payload.district_code = addressPayload.district_code;
        payload.ward_code = addressPayload.ward_code;
    } else {
        if (!selectedAddressId.value) {
            return showToast('Vui lòng chọn hoặc thêm địa chỉ giao nhận hàng', 'error');
        }
        payload.address_id = selectedAddressId.value;
    }

    placingOrder.value = true;
    try {
        let res;
        if (isFlashSale.value) {
            const fsAddress = showAddAddressForm.value
                ? formatFullAddress(payload)
                : formatFullAddress(addresses.value.find(a => a.address_id === payload.address_id));

            const fsPhone = showAddAddressForm.value ? payload.phone : (addresses.value.find(a => a.address_id === payload.address_id)?.phone);
            const fsName = showAddAddressForm.value ? payload.recipient_name : (addresses.value.find(a => a.address_id === payload.address_id)?.recipient_name);

            const fsPayload = {
                flash_sale_id: flashSaleId.value,
                product_id: flashSaleProductId.value,
                quantity: 1,
                recipient_name: fsName,
                recipient_phone: fsPhone,
                shipping_address: fsAddress,
                payment_method: payload.payment_method,
            };
            res = await api.post('flash-sale/buy', fsPayload);
        } else if (isBuyNow.value && buyNowItem.value) {
            // Mua nhanh: chỉ gửi đúng 1 sản phẩm (không lấy từ giỏ)
            payload.items = [{
                variant_id: buyNowItem.value.variant_id,
                quantity: buyNowItem.value.quantity,
            }];
            res = authStore.isAuthenticated
                ? await orderService.createProfileOrder(payload)
                : await api.post('/orders/guest', payload);
        } else {
            if (authStore.isAuthenticated) {
                res = await orderService.createProfileOrder(payload);
            } else {
                const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
                payload.items = localItems.filter(i => i.selected !== false).map(i => ({
                    variant_id: i.variant_id,
                    quantity: i.quantity,
                }));
                res = await api.post('/orders/guest', payload);
            }
        }

        if (res.data.status === 'success') {
            // Xóa referral_code sau khi đặt hàng thành công
            localStorage.removeItem('affiliate_ref');

            if (isBuyNow.value) {
                // Mua nhanh: dọn item tạm
                sessionStorage.removeItem('buy_now_item');
            }
            
            // Xóa các sản phẩm đã mua khỏi giỏ hàng local nếu là khách vãng lai và thanh toán từ giỏ hàng
            if (!authStore.isAuthenticated && !isBuyNow.value && !isFlashSale.value) {
                const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
                const remaining = localItems.filter(i => i.selected === false);
                if (remaining.length > 0) {
                    localStorage.setItem('cart_items', JSON.stringify(remaining));
                } else {
                    localStorage.removeItem('cart_items');
                }
            }

            // Đồng bộ và làm mới số lượng giỏ hàng
            await cartStore.fetchCount();
            window.dispatchEvent(new Event('cart-updated'));
            if (res.data.payment_method === 'vnpay' && res.data.vnpay_url) {
                showToast('Đang chuyển đến cổng thanh toán VNPay...', 'success');
                setTimeout(() => {
                    window.location.href = res.data.vnpay_url;
                }, 500);
                return; // Không set placingOrder = false, giữ loading state
            }


            // === Banking: hiển thị QR code chuyển khoản ===
            if (res.data.payment_method === 'bank_transfer' && res.data.banking_info) {
                bankingInfo.value = res.data.banking_info;
                bankingOrderCode.value = res.data.data?.order_code || res.data.order_code || '';
                showBankingModal.value = true;
                return;
            }

            // === Flow mặc định (COD hoặc Flash Sale) ===
            if (!authStore.isAuthenticated && !isFlashSale.value) {
                showToast('Đặt hàng thành công! chúng tôi sẽ liên hệ sớm nhất cho bạn để xác nhận đơn hàng', 'success');
            } else {
                showToast('Đặt hàng thành công! Vui lòng kiểm tra email.', 'success');
            }
            setTimeout(() => {
                const finalOrderCode = res.data.data?.order_code || res.data.order_code;
                router.push({ name: 'order-success', params: { order_code: finalOrderCode } });
            }, 1000);
        }
    } catch (error) {
        console.error("Order error:", error);
        let msg = 'Đã xảy ra lỗi khi đặt hàng!';
        if (error.response?.data?.message) {
            msg = error.response.data.message;
        } else if (error.response?.statusText) {
            msg = error.response.statusText;
        } else if (error.message) {
            msg = error.message;
        }
        if (error.response?.data?.sold_out) {
            msg = 'Rất tiếc! Sản phẩm đã hết hàng.';
        }
        showToast(msg, 'error');
    } finally {
        placingOrder.value = false;
    }
};

// Các hàm tiện ích
const getProductImage = (item) => {
    const getStorageUrl = (path) => {
        if (!path) return 'https://placehold.co/120x120?text=No+Image';
        if (path.startsWith('http')) return path;
        // Xử lý trường hợp path đã có chữ storage/ ở đầu
        const cleanPath = path.replace(/^\/?storage\//, '');
        return `${APP_URL}/storage/${cleanPath}`;
    };

    if (item.variant?.image_url) return getStorageUrl(item.variant.image_url);
    if (item.product?.main_image) return getStorageUrl(item.product.main_image);
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return getStorageUrl(item.product.thumbnail_url);
    return 'https://placehold.co/120x120?text=No+Image';
};

onMounted(async () => {
    const promises = [fetchAddresses(), fetchCoupons(), fetchUpsellData()];
    if (authStore.isAuthenticated) {
        promises.push(fetchWalletPreview());
    }
    if (isFlashSale.value) {
        promises.push(fetchFlashSaleData());
    } else if (isBuyNow.value) {
        promises.push(fetchBuyNowItem());
    } else {
        promises.push(fetchCart());
    }
    await Promise.all(promises);
    loading.value = false;
});
</script>

<template>
    <div class="checkout-page theme-brown container">

        <!-- ===== BANKING SEPAY MODAL ===== -->
        <SepayPaymentModal
            :show="showBankingModal"
            :order-code="bankingOrderCode"
            :amount="bankingInfo?.amount || total"
            :banking-info="bankingInfo"
            :is-guest="!authStore.isAuthenticated"
            @close="showBankingModal = false"
            @later="onBankingPayLater"
            @success="onBankingPaySuccess"
        />

        <div v-if="loading" class="checkout-skeleton-grid" style="margin-top: 30px;">
            <div class="row" style="display:flex; gap: 30px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div class="skeleton-pulse" style="height:600px; border-radius:12px; margin-bottom:20px;"></div>
                </div>
                <div style="width: 400px; flex-shrink: 0;">
                    <div class="skeleton-pulse" style="height:500px; border-radius:12px;"></div>
                </div>
            </div>
        </div>

        <div v-else class="checkout-wrapper">
            <div class="page-header animate-in">
                <router-link to="/cart" class="back-link">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="back-icon" stroke="currentColor"
                        stroke-width="2.5">
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                    Quay lại giỏ hàng
                </router-link>
            </div>

            <div class="checkout-layout animate-in" style="animation-delay: 0.1s">
                <!-- LEFT SECTION -->
                <div class="checkout-main">

                    <section class="checkout-section">
                        <div class="section-header">
                            <h2>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="icon-brown"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                Thông tin giao hàng
                            </h2>
                        </div>
                        <div class="section-body block-border">
                            <div class="address-tabs">
                                <button v-if="authStore.isAuthenticated" class="add-tab"
                                    :class="{ 'active': !showAddAddressForm }" @click="useSavedAddresses">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                                    </svg>
                                    Địa chỉ đã lưu
                                    <span class="badge" v-if="addresses.length > 0">{{ addresses.length }}</span>
                                </button>
                                <button class="add-tab" :class="{ 'active': showAddAddressForm }"
                                    @click="openAddAddressForm">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="16" />
                                        <line x1="8" y1="12" x2="16" y2="12" />
                                    </svg>
                                    Thêm địa chỉ mới
                                </button>
                            </div>

                            <!-- INLINE ADD ADDRESS FORM -->
                            <div v-if="showAddAddressForm" class="new-address-form form-box animate-in">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Tên người nhận <span class="required">*</span></label>
                                        <input v-model="formAddress.recipient_name" type="text" class="form-input"
                                            placeholder="Ví dụ: Huỳnh Quang Minh" />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Điện thoại di động <span
                                                class="required">*</span></label>
                                        <input v-model="formAddress.phone" type="text" class="form-input"
                                            placeholder="Ví dụ: 09012xxx9" />
                                    </div>
                                </div>
                                <!-- EMAIL NHẬN XÁC NHẬN ĐƠN HÀNG -->
                                <div class="form-group" style="margin-bottom: 16px;">
                                    <label class="form-label">
                                        Email nhận xác nhận đơn hàng <span class="required">*</span>
                                    </label>
                                    <input v-model="email" type="email" class="form-input"
                                        placeholder="Ví dụ: email@gmail.com" autocomplete="email" />
                                    <small style="display:block; margin-top:6px; color:#6b7280; font-size:13px;">
                                        Chúng tôi sẽ gửi xác nhận đơn hàng tới email này.
                                    </small>
                                </div>

                                <div class="form-group pb-2 mt-2">
                                    <AddressSelector :key="addressSelectorKey" @change="onAddressChange" />
                                </div>
                            </div>

                            <!-- SAVED ADDRESSES -->
                            <div v-else class="address-grid animate-in">
                                <div v-if="addresses.length === 0" class="empty-address-box">
                                    <p>Bạn chưa có địa chỉ giao hàng nào.</p>
                                    <button class="btn-outline-brown" @click="openAddAddressForm">Tạo sổ địa chỉ
                                        ngay</button>
                                </div>
                                <label v-for="addr in inlineSavedAddresses" :key="addr.address_id" class="address-card"
                                    :class="{ 'is-selected': selectedAddressId === addr.address_id }">
                                    <input type="radio" v-model="selectedAddressId" :value="addr.address_id"
                                        class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="ac-right addr-card-content">
                                        <div class="addr-header">
                                            <span class="addr-icon"><svg width="14" height="14" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                                    <circle cx="12" cy="7" r="4" />
                                                </svg></span>
                                            <span class="addr-name">{{ addr.recipient_name }}</span>
                                            <span class="addr-phone">{{ addr.phone }}</span>
                                            <span v-if="addr.is_default" class="badge-default">MẶC ĐỊNH</span>
                                        </div>
                                        <div class="addr-body">
                                            <span class="addr-pin"><svg width="14" height="14" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                    <circle cx="12" cy="10" r="3" />
                                                </svg></span>
                                            {{ formatFullAddress(addr) }}
                                        </div>
                                    </div>
                                </label>
                                <button v-if="hasMoreSavedAddresses" type="button" class="btn-show-more-addresses" @click="openAddressModal">
                                    Xem thêm {{ remainingAddressCount }} địa chỉ
                                </button>
                            </div>

                            <CheckoutAddressModal
                                :show="showAddressModal"
                                :addresses="addresses"
                                :selected-address-id="selectedAddressId"
                                @close="closeAddressModal"
                                @confirm="confirmAddressSelection"
                            />

                            <div class="checkout-divider"></div>

                            <div v-if="leadtimeDate" class="leadtime-notice">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" class="me-2 text-green-600">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span>Dự kiến giao hàng vào: <strong>{{ leadtimeDate }}</strong></span>
                            </div>

                            <div class="checkout-divider"></div>

                            <div class="form-group note-group">
                                <label for="order-note" class="form-label">Ghi chú đơn hàng</label>
                                <textarea id="order-note" v-model="note" class="form-input note-input" rows="3"
                                    placeholder="Ví dụ: Giao hàng giờ hành chính, gọi trước khi giao..."></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="checkout-section">
                        <div class="section-header">
                            <h2>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="icon-brown"
                                    stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2" ry="2" />
                                    <line x1="2" y1="10" x2="22" y2="10" />
                                </svg>
                                Phương thức thanh toán
                            </h2>
                        </div>
                        <div class="section-body block-border">
                            <div class="payment-methods-simple">
                                <label class="payment-card-simple" :class="{ 'is-selected': paymentMethod === 'cod' }">
                                    <input type="radio" v-model="paymentMethod" value="cod" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="method-icon">
                                            <rect x="1" y="3" width="15" height="13"></rect>
                                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                        </svg>
                                        <span class="payment-name-simple">Thanh toán khi nhận hàng (COD)</span>
                                    </div>
                                </label>

                                <label class="payment-card-simple"
                                    :class="{ 'is-selected': paymentMethod === 'bank_transfer' }">
                                    <input type="radio" v-model="paymentMethod" value="bank_transfer"
                                        class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="method-icon">
                                            <rect x="2" y="21" width="20" height="2"></rect>
                                            <polygon points="12 2 2 7 22 7 12 2"></polygon>
                                            <path d="M5 21V9"></path>
                                            <path d="M19 21V9"></path>
                                            <path d="M12 21V9"></path>
                                        </svg>
                                        <span class="payment-name-simple">Chuyển khoản ngân hàng</span>
                                    </div>
                                </label>


                                <label v-if="!isFlashSale" class="payment-card-simple"
                                    :class="{ 'is-selected': paymentMethod === 'vnpay' }">
                                    <input type="radio" v-model="paymentMethod" value="vnpay" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#005a9e"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="method-icon">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                        </svg>
                                        <span class="payment-name-simple">VNPay</span>
                                    </div>
                                </label>
                        </div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT SECTION: BILL SUMMARY -->
                <div class="checkout-sidebar animate-in" style="animation-delay: 0.2s">
                    <div class="sticky-sidebar">
                        <div class="bill-summary-card theme-brown">
                            <div class="bill-header">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                                Đơn hàng của bạn
                            </div>

                            <div class="bill-body">
                                <div class="bill-items">
                                    <div v-for="item in cartItems" :key="item.cart_item_id" class="bill-item">
                                        <div class="bill-item-img-wrapper">
                                            <img :src="getProductImage(item)" :alt="item.product?.name"
                                                class="bill-item-img" />
                                        </div>
                                        <div class="bill-item-info">
                                            <h4 class="bill-item-name">{{ item.product?.name }}</h4>
                                            <button type="button" class="bill-item-variant-btn" @click="openVariantModal(item)" title="Bấm để đổi màu sắc / kích thước">
                                                <span class="bill-item-variant-text">
                                                    {{ item.variant?.color && item.variant?.size ? `${item.variant?.color} • ${item.variant?.size}` : (item.variant?.color || item.variant?.size || item.variant?.variant_name || 'Chọn phân loại') }}
                                                </span>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="variant-caret">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="bill-item-price-col">
                                            <div class="bill-item-price">{{ formatPrice((item.variant?.price || 0) *
                                                item.quantity) }}</div>
                                            <div class="bill-item-qty">✕ {{ item.quantity }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="coupon-section">
                                    <template v-if="authStore.isAuthenticated">
                                        <div class="coupon-input-group" v-if="!appliedCoupon">
                                            <input type="text" v-model="couponCode" placeholder="Nhập mã khuyến mãi"
                                                class="coupon-input" @keyup.enter="applyCoupon" />
                                            <button class="btn-apply-coupon" @click="applyCoupon"
                                                :disabled="checkingCoupon || !couponCode">
                                                <span v-if="checkingCoupon" class="small-spinner"></span>
                                                <span v-else>Áp dụng</span>
                                            </button>
                                        </div>
                                        <div v-else class="coupon-applied-box">
                                            <div class="coupon-tag">
                                                <AppIcon name="voucher" size="20" color="#111" class="me-1" />{{
                                                appliedCoupon.code }}
                                            </div>
                                            <button class="btn-remove-coupon" @click="removingCoupon">Gỡ bỏ</button>
                                        </div>
                                        <div class="text-right mt-1">
                                            <button class="btn-select-coupon" @click="openCouponModal">Chọn mã có
                                                sẵn</button>
                                        </div>
                                    </template>
                                    <div v-else class="coupon-login-required">
                                        <div class="coupon-input-group">
                                            <input type="text" placeholder="Đăng nhập để sử dụng mã khuyến mãi"
                                                class="coupon-input" disabled />
                                            <button class="btn-apply-coupon" @click="promptLoginForCoupon">
                                                Đăng nhập
                                            </button>
                                        </div>
                                        <p class="coupon-login-note">Bạn vẫn có thể đặt hàng không cần tài khoản, nhưng cần đăng nhập để dùng voucher.</p>
                                    </div>
                                </div>

                                <!-- Tiêu điểm thưởng -->
                                <div v-if="authStore.isAuthenticated" class="loyalty-section" style="margin-top: 16px; padding: 12px; background: #fff5f5; border-radius: 8px; border: 1px dashed #f87171;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
                                            <input type="checkbox" v-model="useLoyaltyPoints" :disabled="loyaltyPoints < 100" style="width: 16px; height: 16px; accent-color: #ef4444;" />
                                            Sử dụng điểm thưởng
                                        </label>
                                        <span style="font-size: 0.9rem; color: #ef4444; font-weight: 600;">Bạn có: {{ loyaltyPoints }} điểm</span>
                                    </div>
                                    <div v-if="useLoyaltyPoints" style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                                        <input type="number" v-model="inputPoints" :max="maxPointsCanUse" min="0" style="width: 100px; padding: 8px 10px; border: 1.5px solid #fca5a5; border-radius: 6px; font-size: 0.9rem; text-align: center; outline: none;" />
                                        <span style="font-size: 0.9rem; color: #666;">điểm = <strong style="color: #ef4444;">-{{ formatPrice(loyaltyDiscount) }}</strong></span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 8px; padding-top: 8px; border-top: 1px solid #fca5a5; line-height: 1.4;">
                                        * Tối thiểu 100 điểm để đổi giảm giá. Tối đa 30% giá trị đơn hàng. Điểm có hiệu lực 365 ngày kể từ ngày tích.
                                    </div>
                                </div>

                                <div class="totals-section">
                                    <div class="total-row">
                                        <span>Tạm tính ({{ totalQuantity }} sản phẩm)</span>
                                        <span class="fw-600">{{ formatPrice(subtotal) }}</span>
                                    </div>
                                    <div class="total-row">
                                        <span>Phí vận chuyển</span>
                                        <span class="fw-600">
                                            <span v-if="isCalculatingFee" class="calculating-text">Đang tính...</span>
                                            <span v-else-if="isShippingFree" class="shipping-free-text">Miễn phí</span>
                                            <span v-else>{{ formatPrice(effectiveShippingFee) }}</span>
                                        </span>
                                    </div>
                                    <div v-if="isShippingFree" class="freeship-alert-row">
                                        🎉 Đơn hàng của bạn đã được miễn phí vận chuyển!
                                        <span v-if="shippingFee > 0">Tiết kiệm {{ formatPrice(shippingFee) }}</span>
                                    </div>
                                    <div v-else-if="subtotal < upsellState.freeshipThreshold && shippingFee > 0"
                                        class="freeship-hint-row"
                                        style="text-align: right; margin-top: -6px; margin-bottom: 8px;">
                                        <small style="color: #0ea5e9; font-size: 0.8rem; font-weight: 500;">
                                            Chỉ cần mua thêm {{ formatPrice(upsellState.freeshipThreshold - subtotal) }}
                                            để được Freeship
                                        </small>
                                    </div>
                                    <div class="total-row" v-if="discount > 0 || shippingDiscount > 0">
                                        <span>Voucher Giảm giá</span>
                                        <div
                                            style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                                            <span class="discount-val mb-2" v-if="discount > 0"
                                                style="color: #ef4444;">- {{ formatPrice(discount) }}</span>
                                            <span class="free-badge" v-if="shippingDiscount > 0"
                                                style="color: #10b981;">Freeship: - {{ formatPrice(shippingDiscount)
                                                }}</span>
                                        </div>
                                    </div>
                                    <div class="total-row" v-if="loyaltyDiscount > 0">
                                        <span>Điểm thưởng ({{ inputPoints }} điểm)</span>
                                        <span class="discount-val" style="color: #ef4444;">- {{ formatPrice(loyaltyDiscount) }}</span>
                                    </div>


                                    <div class="summary-divider variant-dashed"></div>

                                    <!-- WALLET DISCOUNT WIDGET -->
                                    <div v-if="authStore.isAuthenticated && walletPreview && walletPreview.total_available > 0"
                                        class="wallet-checkout-widget">
                                        <label class="wallet-toggle">
                                            <input type="checkbox" v-model="useWallet" />
                                            <div class="wt-switch">
                                                <div class="wt-knob"></div>
                                            </div>
                                            <div class="wt-info">
                                                <span class="wt-label">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2">
                                                        <rect x="2" y="4" width="20" height="16" rx="2" />
                                                        <path d="M16 12h.01" />
                                                        <path d="M2 10h20" />
                                                    </svg>
                                                    Dùng ví thanh toán
                                                </span>
                                                <span class="wt-balance">Khả dụng: <strong>{{
                                                        formatPrice(walletPreview.total_available) }}</strong></span>
                                            </div>
                                        </label>
                                        <div v-if="useWallet && walletDiscount > 0" class="wallet-discount-detail">
                                            <div v-if="walletPreview.deposit_used > 0" class="wd-row">
                                                <span>Từ số dư nạp</span>
                                                <span class="wd-val">-{{ formatPrice(walletPreview.deposit_used)
                                                    }}</span>
                                            </div>
                                            <div v-if="walletPreview.commission_used > 0" class="wd-row">
                                                <span>Từ hoa hồng</span>
                                                <span class="wd-val">-{{ formatPrice(walletPreview.commission_used)
                                                    }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="useWallet && walletDiscount > 0" class="total-row wallet-discount-row">
                                        <span>Giảm từ ví</span>
                                        <span class="discount-val" style="color: #8b5cf6; font-weight: 700;">-{{
                                            formatPrice(walletDiscount) }}</span>
                                    </div>

                                    <div class="summary-divider variant-dashed"></div>

                                    <div class="total-final-row">
                                        <span class="total-label">Tổng cộng</span>
                                        <span class="total-price">{{ formatPrice(total) }}</span>
                                    </div>
                                </div>

                                <button class="btn-place-order" @click="placeOrder"
                                    :disabled="placingOrder || (!showAddAddressForm && !selectedAddressId)">
                                    <div v-if="placingOrder" class="spinner-small"></div>
                                    <span v-else>
                                        <!-- <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                            class="lock-icon" stroke="currentColor" stroke-width="2.5">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                        </svg> -->
                                        <span class="text-uppercase tracking-widest"> Đặt hàng </span>

                                    </span>
                                </button>
                                <p class="security-text">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="icon-green"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                    </svg>
                                    Thông tin của bạn được bảo mật an toàn
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL CHỌN MÃ GIẢM GIÁ ================= -->
        <teleport to="body">
            <transition name="modal-fade">
                <div v-if="showCouponModal" class="modal-overlay" @click.self="showCouponModal = false">
                    <div class="modal-content coupon-modal">
                        <div class="modal-header">
                            <h2 class="modal-title">Chọn mã giảm giá</h2>
                            <button class="modal-close" @click="showCouponModal = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div v-if="loadingCoupons" class="coupon-list-skeleton">
                                <div class="skeleton-pulse" style="height:100px; border-radius:12px; margin-bottom:12px;" v-for="i in 3" :key="i"></div>
                            </div>
                            <div v-else-if="availableCoupons.length > 0" class="coupon-list">
                                <div v-for="coupon in availableCoupons" :key="coupon.id" class="coupon-card" :class="{
                                    'is-applied': appliedCoupon?.code === coupon.code,
                                    'is-disabled': coupon.type === 'free_ship' && subtotal >= (upsellState.freeshipThreshold || 500000)
                                }">
                                    <div class="cp-left"><span class="cp-icon">
                                            <AppIcon name="voucher" size="24" color="#111" />
                                        </span></div>
                                    <div class="cp-right">
                                        <h4 class="cp-code">{{ coupon.code }}</h4>
                                        <p class="cp-desc">Giảm {{ coupon.type === 'percent' ? coupon.value + '%' :
                                            formatPrice(coupon.value) }}</p>
                                        <p v-if="coupon.min_order_value" class="cp-min">Đơn tối thiểu: {{
                                            formatPrice(coupon.min_order_value) }}</p>
                                    </div>
                                    <button class="btn-select-cp"
                                        :disabled="coupon.type === 'free_ship' && subtotal >= (upsellState.freeshipThreshold || 500000)"
                                        @click="selectCoupon(coupon)">
                                        <span
                                            v-if="coupon.type === 'free_ship' && subtotal >= (upsellState.freeshipThreshold || 500000)">
                                            Đã freeship
                                        </span>
                                        <span v-else>
                                            {{ appliedCoupon?.code === coupon.code ? 'Đang dùng' : 'Sử dụng' }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="empty-address-box">
                                <p>Rất tiếc! Hiện không có mã giảm giá nào phù hợp cho bạn.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

        <!-- ====== VARIANT CHANGE MODAL ====== -->
        <teleport to="body">
            <transition name="vmodal">
                <div v-if="variantModal.show" class="vmodal-overlay" @click.self="closeVariantModal">
                    <div class="vmodal-box">
                        <!-- Header -->
                        <div class="vmodal-header">
                            <div class="vmodal-product-snippet" v-if="variantModal.item">
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
                                        <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{ s.stock }})</span>
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
            </transition>
        </teleport>
    </div>
</template>

<style scoped>
/* Wallet Checkout Widget */
.wallet-checkout-widget {
    margin: 12px 0;
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    border-radius: 12px;
    padding: 14px;
}

.wallet-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.wallet-toggle input {
    display: none;
}

.wt-switch {
    width: 40px;
    height: 22px;
    background: #d1d5db;
    border-radius: 11px;
    position: relative;
    transition: background 0.2s;
    flex-shrink: 0;
}

.wallet-toggle input:checked~.wt-switch {
    background: #8b5cf6;
}

.wt-knob {
    width: 18px;
    height: 18px;
    background: var(--card-bg);
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

.wallet-toggle input:checked~.wt-switch .wt-knob {
    transform: translateX(18px);
}

.wt-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.wt-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 6px;
}

.wt-balance {
    font-size: 0.78rem;
    color: #8b5cf6;
}

.wallet-discount-detail {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e9d5ff;
}

.wd-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #6b7280;
    padding: 3px 0;
}

.wd-val {
    color: #8b5cf6;
    font-weight: 600;
}

.wallet-discount-row {
    background: #faf5ff;
    border-radius: 8px;
    padding: 8px 12px;
    margin: 4px 0;
}

.checkout-page {
    padding: 16px 0 80px;
    font-family: var(--font-inter, 'Inter', sans-serif);
    color: var(--text-main);
    min-height: 80vh;
}

/* Base states & Header */
.loading-state {
    text-align: center;
    padding: 100px 0;
    color: var(--primary);
    font-weight: 500;
}

.spinner {
    width: 44px;
    height: 44px;
    border: 4px solid #e2e8f0;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

.spinner-small {
    display: inline-block;
    width: 22px;
    height: 22px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease infinite;
}

.spinner-small.brown {
    border-color: #e2e8f0;
    border-top-color: var(--primary);
}

@keyframes spin {
    100% {
        transform: rotate(360deg);
    }
}

.page-header {
    margin-bottom: 16px;
    padding-bottom: 8px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    background: var(--card-bg);
    border: 1.5px solid #e2e8f0;
}

.back-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.icon-brown {
    color: var(--primary);
}

.icon-green {
    color: #166534;
}

/* Grid Layout */
.checkout-layout {
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 32px;
}

/* Typography elements */
h2 {
    font-weight: 700;
}

/* Blocks */
.checkout-section {
    margin-bottom: 24px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.block-border {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 10px 40px rgba(230, 59, 111, 0.04);
    border: 1px solid rgba(230, 59, 111, 0.1);
    transition: box-shadow 0.3s ease;
}

.block-border:hover {
    box-shadow: 0 15px 50px rgba(230, 59, 111, 0.08);
}

/* Segmented Control Tabs (Kiểu Apple) */
.address-tabs {
    display: flex;
    background: transparent;
    padding: 6px;
    border-radius: 12px;
    margin-bottom: 24px;
    gap: 12px;
    position: relative;
}

.add-tab {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 1;
    padding: 9px 16px;
    font-weight: 600;
    font-size: 0.95rem;
    background: #DCE4E6;
    color: var(--text-main);
    border: 1.5px solid transparent;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.add-tab:hover:not(.active) {
    color: var(--primary);
}

.add-tab.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.05);
}

.badge {
    background: #ef4444;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}

.add-tab.active .badge {
    background: #DEE8FE;
    color: rgb(0, 0, 0);
}

/* Form Elements */
.form-box {
    background: var(--card-bg);
    padding: 24px;
    border-radius: 16px;
    border: 1.5px solid #e2e8f0;
    position: relative;
}


.form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
    width: 100%;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #334155;
    margin-left: 2px;
}

.form-input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 14px;
    border: 1.5px solid transparent;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.2s ease;
    background: #f1f5f9;
    color: var(--text-main);
}

.form-input:focus {
    background: var(--card-bg);
    border-color: var(--primary);
    box-shadow: 0 4px 15px rgba(230, 59, 111, 0.08);
}

.form-input::placeholder {
    color: #94a3b8;
}

textarea.note-input {
    resize: vertical;
    min-height: 80px;
}

.required {
    color: #ef4444;
}

.pb-2 {
    padding-bottom: 8px;
}

.mt-2 {
    margin-top: 8px;
}

.mt-3 {
    margin-top: 16px;
}

.flex-end {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Checkbox */
.form-group-checkbox {
    flex-direction: row;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.95rem;
    color: #475569;
    font-weight: 500;
}

.checkbox-input {
    display: none;
}

.checkbox-custom {
    width: 22px;
    height: 22px;
    border: 2px solid #cbd5e1;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    background: var(--card-bg);
}

.checkbox-input:checked+.checkbox-custom {
    background: var(--primary);
    border-color: var(--primary);
}

.checkbox-input:checked+.checkbox-custom::after {
    content: '';
    width: 6px;
    height: 11px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    display: block;
    margin-bottom: 2px;
}

.form-error-msg {
    background: #fee2e2;
    color: #b91c1c;
    padding: 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-top: 8px;
    font-weight: 500;
    border: 1px solid #fecaca;
}

/* Address Cards */
.address-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.address-card {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.25s ease;
    background: var(--card-bg);
}

.hidden-radio {
    position: absolute;
    opacity: 0;
}

.address-card:hover {
    border-color: #7dd3fc;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, 0.06);
}

.address-card.is-selected {
    border-color: var(--primary);
    background: #f4faff;
    box-shadow: 0 4px 15px rgba(230, 59, 111, 0.08);
}

.btn-show-more-addresses {
    width: 100%;
    padding: 13px 16px;
    border: 1px dashed var(--primary);
    border-radius: 12px;
    background: #fff5f8;
    color: var(--primary);
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-show-more-addresses:hover {
    background: #ffe8f0;
    transform: translateY(-1px);
}

.radio-indicator {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-bg);
    transition: all 0.2s ease;
}

.radio-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: transparent;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    transform: scale(0);
}

.is-selected .radio-indicator {
    border-color: var(--primary);
    background: var(--card-bg);
}

.is-selected .radio-dot {
    background: var(--primary);
    transform: scale(1);
}

.addr-card-content {
    flex: 1;
}

.addr-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.addr-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
}

.addr-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--text-main);
}

.addr-phone {
    color: #475569;
    font-size: 0.95rem;
    font-weight: 500;
}

.badge-default {
    background: var(--primary);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.addr-body {
    font-size: 0.95rem;
    color: #475569;
    line-height: 1.5;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.addr-pin {
    margin-top: 2px;
    color: #94a3b8;
}

.checkout-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 24px 0;
}

.note-group {
    margin-bottom: 0;
}

.empty-address-box {
    text-align: center;
    padding: 40px 20px;
    background: var(--card-bg);
    border: 2px dashed #e2e8f0;
    border-radius: 12px;
}

.empty-address-box p {
    color: #64748b;
    margin-bottom: 16px;
    font-weight: 500;
}

/* Buttons */
.btn-save {
    padding: 12px 24px;
    font-weight: 600;
    background: var(--primary);
    border: none;
    color: white;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-save:hover:not(:disabled) {
    background: #C4305D;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-cancel {
    padding: 12px 24px;
    font-weight: 600;
    background: var(--card-bg);
    border: 1.5px solid #e2e8f0;
    color: #475569;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-cancel:hover {
    background: #f8fafc;
}

.btn-outline-brown {
    background: transparent;
    border: 2px solid var(--primary);
    color: var(--primary);
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline-brown:hover {
    background: var(--primary);
    color: white;
}

/* Payment Methods Simple */
.payment-methods-simple {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-card-simple {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.25s;
    background: var(--card-bg);
}

.payment-card-simple:hover {
    border-color: var(--primary);
}

.payment-card-simple.is-selected {
    border-color: var(--primary);
    background: var(--card-bg);
}

.payment-info-simple {
    display: flex;
    align-items: center;
    gap: 12px;
}

.payment-name-simple {
    font-size: 1rem;
    color: #333;
}

/* Redefine indicator for new simple card */
.payment-card-simple .radio-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-bg);
    transition: all 0.2s ease;
}

.payment-card-simple .radio-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: transparent;
    transition: all 0.2s;
    transform: scale(0);
}

.payment-card-simple.is-selected .radio-indicator {
    border-color: #7A1B38;
}

.payment-card-simple.is-selected .radio-dot {
    background: #7A1B38;
    transform: scale(1);
}


/* Right Section - Bill Summary */
.sticky-sidebar {
    position: sticky;
    top: 100px;
}

.bill-summary-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(230, 59, 111, 0.1);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.bill-header {
    background: transparent;
    padding: 20px 24px;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px dashed rgba(230, 59, 111, 0.15);
}

.bill-body {
    padding: 24px;
}

/* Bill items */
.bill-items {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-height: 380px;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 24px;
}

.bill-items::-webkit-scrollbar {
    width: 4px;
}

.bill-items::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.bill-item {
    display: flex;
    align-items: center;
    gap: 14px;
}

.bill-item-img-wrapper {
    position: relative;
}

.bill-item-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #f1f5f9;
    background: var(--card-bg);
}

.bill-item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bill-item-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

.bill-item-variant-btn {
    margin: 3px 0 0;
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

.bill-item-variant-btn:hover {
    background: #fff0f5;
    border-color: #fbcfe8;
}

.bill-item-variant-btn:hover .bill-item-variant-text {
    color: #E63B6F;
}

.bill-item-variant-btn .variant-caret {
    width: 9px;
    height: 9px;
    color: #94a3b8;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}

.bill-item-variant-btn:hover .variant-caret {
    color: #E63B6F;
    transform: translateY(1px);
}

.bill-item-variant-text {
    font-size: 0.72rem;
    line-height: 1;
    color: #475569;
    font-weight: 500;
}

.bill-item-variant {
    margin: 4px 0 0;
    font-size: 0.82rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.variant-divider {
    font-size: 0.8rem;
    color: #cbd5e1;
}

.bill-item-price-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.bill-item-price {
    font-weight: 700;
    color: var(--text-main);
    font-size: 0.95rem;
}

.bill-item-qty {
    font-size: 0.85rem;
    color: #94a3b8;
    font-weight: 600;
}

/* Coupons */
.coupon-section {
    background: var(--card-bg);
    border: 1px dashed #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
}

.coupon-input-group {
    display: flex;
    gap: 8px;
}

.coupon-input {
    flex: 1;
    min-width: 0;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.2s;
    text-transform: uppercase;
    background: var(--card-bg);
}

.coupon-input:focus {
    border-color: var(--primary);
}

.btn-apply-coupon {
    padding: 0 16px;
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    flex-shrink: 0;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-apply-coupon:hover:not(:disabled) {
    background: #d21551;
}

.btn-apply-coupon:disabled {
    opacity: 1;
    cursor: not-allowed;
    background: #cbd5e1;
    color: white;
}

.coupon-login-required .coupon-input {
    color: #94a3b8;
    background: #f8fafc;
    text-transform: none;
}

.coupon-login-note {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 0.78rem;
    line-height: 1.45;
}

.coupon-applied-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f0fdf4;
    border: 1.5px dashed #86efac;
    border-radius: 10px;
    padding: 10px 14px;
}

.coupon-tag {
    font-weight: 700;
    color: #166534;
    font-size: 0.95rem;
}

.btn-remove-coupon {
    background: none;
    border: none;
    font-size: 0.85rem;
    color: #ef4444;
    font-weight: 600;
    cursor: pointer;
    padding: 4px;
}

.btn-remove-coupon:hover {
    text-decoration: underline;
}

.text-right {
    text-align: right;
}

.btn-select-coupon {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary);
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
    padding: 4px 0;
    margin-top: 4px;
}

.btn-select-coupon:hover {
    color: #d21551;
    text-decoration: underline;
}

/* Totals */
.totals-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 24px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.95rem;
    color: #475569;
}

.fw-600 {
    font-weight: 600;
    color: var(--text-main);
}

.shipping-free-text {
    color: #10b981;
    font-weight: 800;
}

.freeship-alert-row {
    margin-top: -6px;
    padding: 10px 12px;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    background: #f0fdf4;
    color: #15803d;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.5;
}

.freeship-alert-row span {
    display: block;
    margin-top: 2px;
    color: #16a34a;
    font-weight: 600;
}

.discount-val {
    color: #166534;
    font-weight: 600;
}

.summary-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 10px 0;
}

.variant-dashed {
    background: transparent;
    border-bottom: 1px dashed #e2e8f0;
}

.total-final-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.total-label {
    font-size: 1.15rem;
    font-weight: 700;
    color: #333;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-price {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
}

.btn-place-order {
    width: 100%;
    background: var(--primary);
    /* Deep elegant ocean blue */
    color: white;
    border: none;
    border-radius: 6px;
    padding: 12px;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 6px 20px rgba(230, 59, 111, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-place-order:hover:not(:disabled) {
    background: #d21551;
    /* Darker deep blue on hover */
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(230, 59, 111, 0.3);
}

.btn-place-order:disabled {
    background: #cbd5e1;
    color: #94a3b8;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

.security-text {
    text-align: center;
    font-size: 0.85rem;
    color: #166534;
    margin-top: 16px;
    margin-bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

/* Coupon Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 20px;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    max-height: 85vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px dashed #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: var(--card-bg);
    border: 1.5px solid #e2e8f0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #fee2e2;
    color: #ef4444;
    border-color: #fca5a5;
}

.modal-body {
    padding: 24px;
    flex: 1;
    background: var(--card-bg);
}

.coupon-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-height: 450px;
    overflow-y: auto;
    padding: 4px 8px 8px 4px;
}

.coupon-list::-webkit-scrollbar {
    width: 6px;
}

.coupon-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.coupon-card {
    display: flex;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    align-items: stretch;
    position: relative;
    background: var(--card-bg);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}

.coupon-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.coupon-card.is-applied {
    border-color: var(--primary);
    background: #f0f9ff;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.12);
}

.cp-left {
    background: #f8fafc;
    width: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border-right: 2px dashed #e2e8f0;
    position: relative;
    border-top-left-radius: 11px;
    border-bottom-left-radius: 11px;
}

.cp-left::before,
.cp-left::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    background: var(--card-bg);
    border-radius: 50%;
    right: -9px;
    border: 1px solid #e2e8f0;
    z-index: 2;
}

.cp-left::before {
    top: -9px;
    border-bottom-color: transparent;
    border-right-color: transparent;
    transform: rotate(-45deg);
}

.cp-left::after {
    bottom: -9px;
    border-top-color: transparent;
    border-right-color: transparent;
    transform: rotate(45deg);
}

.coupon-card.is-applied .cp-left::before,
.coupon-card.is-applied .cp-left::after {
    background: var(--card-bg);
    border-color: var(--primary);
}

.coupon-card.is-applied .cp-left {
    border-right-color: var(--primary);
    background: #e0f2fe;
}

.cp-right {
    flex: 1;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-right: 120px;
    /* Ensures text never overlaps the button */
}

.cp-code {
    margin: 0 0 6px;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cp-desc {
    margin: 0 0 4px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--primary);
}

.cp-min {
    margin: 0;
    font-size: 0.8rem;
    color: #64748b;
}

.btn-select-cp {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    box-shadow: 0 2px 4px rgba(230, 59, 111, 0.2);
}

.btn-select-cp:hover {
    background: #C4305D;
    box-shadow: 0 4px 8px rgba(230, 59, 111, 0.3);
}

.coupon-card.is-applied .btn-select-cp {
    background: #10b981;
    /* Green success color */
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.coupon-card.is-applied .btn-select-cp:hover {
    background: #059669;
}

.coupon-card.is-disabled {
    opacity: 0.6;
    border-color: #cbd5e1;
    background: #f8fafc;
    box-shadow: none;
    cursor: not-allowed;
}

.coupon-card.is-disabled .btn-select-cp {
    background: #cbd5e1;
    color: #64748b;
    cursor: not-allowed;
    box-shadow: none;
}

/* Animations */
.animate-in {
    animation: fadeIn 0.5s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
    opacity: 0;
    transform: translateY(20px);
}

@keyframes fadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-fade-enter-active {
    animation: modalFadeIn 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
}

.modal-fade-leave-active {
    animation: modalFadeOut 0.2s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        backdrop-filter: blur(0);
        transform: scale(0.95) translateY(10px);
    }

    to {
        opacity: 1;
        backdrop-filter: blur(4px);
        transform: scale(1) translateY(0);
    }
}

@keyframes modalFadeOut {
    from {
        opacity: 1;
        transform: scale(1) translateY(0);
    }

    to {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }

    .sticky-sidebar {
        position: static;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .address-tabs {
        flex-direction: column;
    }

    .add-tab {
        width: 100%;
    }

    .block-border,
    .bill-body {
        padding: 18px;
    }

    .coupon-input-group {
        flex-direction: column;
    }

    .btn-apply-coupon {
        width: 100%;
        min-height: 44px;
    }
}

@media (max-width: 640px) {
    .page-header {
        margin-bottom: 14px;
    }

    .section-header h2 {
        font-size: 1.12rem;
    }

    .address-card,
    .payment-card {
        align-items: flex-start;
    }

    .addr-header,
    .coupon-applied-box,
    .total-final-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .modal-overlay {
        padding: 12px;
        align-items: flex-end;
    }

    .modal-content {
        max-width: none;
        max-height: min(82vh, 640px);
        border-radius: 20px 20px 0 0;
    }
}

.calculating-text {
    font-size: 0.85rem;
    color: #64748b;
    font-style: italic;
    animation: pulse 1.5s infinite;
}

.free-badge {
    background: #f0fdf4;
    color: #166534;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid #bbf7d0;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }

    50% {
        opacity: 0.6;
    }

    100% {
        opacity: 1;
    }
}

/* ===== Banking QR Modal ===== */
.banking-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.banking-modal {
    background: var(--card-bg);
    border-radius: 24px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.banking-modal-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
    padding: 28px 32px 24px;
    text-align: center;
    color: white;
}

.banking-modal-icon {
    font-size: 2.2rem;
    display: block;
    margin-bottom: 10px;
}

.banking-modal-header h2 {
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 6px;
}

.banking-modal-header p {
    font-size: 0.9rem;
    opacity: 0.85;
    line-height: 1.5;
}

.banking-modal-body {
    display: flex;
    gap: 24px;
    padding: 24px 28px;
    align-items: flex-start;
}

.qr-section {
    flex-shrink: 0;
    text-align: center;
}

.qr-image {
    width: 160px;
    height: 160px;
    border: 3px solid #e2e8f0;
    border-radius: 12px;
    display: block;
}

.qr-hint {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 6px;
}

.bank-info-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.bank-info-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.bank-label {
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bank-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-main);
}

.bank-value.highlight {
    color: #1d4ed8;
    font-size: 1rem;
}

.banking-note {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 12px 16px;
    margin: 0 28px;
    font-size: 0.85rem;
    color: #92400e;
    line-height: 1.4;
}

.banking-modal-actions {
    padding: 20px 28px 28px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-banking-done {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s;
}

.btn-banking-done:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(29, 78, 216, 0.3);
}

.btn-banking-later {
    width: 100%;
    padding: 12px;
    background: transparent;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s;
}

.btn-banking-later:hover {
    background: #f8fafc;
    color: #374151;
}

@media (max-width: 480px) {
    .banking-modal-body {
        flex-direction: column;
        align-items: center;
    }

    .qr-image {
        width: 180px;
        height: 180px;
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
