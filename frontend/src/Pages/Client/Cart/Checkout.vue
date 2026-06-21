<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/axios';
import { useCartUpsell } from '@/composables/useCartUpsell';
import { useToast } from '@/composables/useToast';
import AddressSelector from '@/components/AddressSelector.vue';
import { addressService } from '@/services/addressService';
import { orderService } from '@/services/orderService';
import { loyaltyService } from '@/services/loyaltyService';
import { useAuthStore } from '@/stores/auth';
import AppIcon from '@/icons/AppIcon.vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const cartItems = ref([]);
const loading = ref(true);

const isFlashSale = computed(() => !!route.query.flash_sale_id && !!route.query.product_id);
const flashSaleId = computed(() => route.query.flash_sale_id);
const flashSaleProductId = computed(() => route.query.product_id);
const { showToast } = useToast();

const { state: upsellState, fetchUpsellData } = useCartUpsell();

const APP_URL = import.meta.env.VITE_BASE_URL;

// --- Địa chỉ ---
const addresses = ref([]);
const selectedAddressId = ref(null);
const showAddAddressForm = ref(false);
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

// --- GHN Data ---
const isCalculatingFee = ref(false);
const leadtimeDate = ref(null);
const serviceId = ref(53320); // Default GHN service ID for normal delivery

// --- Thanh toán & Khác ---
const paymentMethod = ref('cod'); // cod, vnpay, momo, banking, wallet
const walletBalance = ref(0);
const note = ref('');

// --- Banking QR Modal ---
const showBankingModal = ref(false);
const bankingInfo = ref(null); // { bank_bin, account_number, account_name, amount, order_code, qr_url }
const bankingOrderCode = ref('');

// --- Coupon ---
const couponCode = ref('');
const appliedCoupon = ref(null);
const checkingCoupon = ref(false);
const showCouponModal = ref(false);
const availableCoupons = ref([]);
const loadingCoupons = ref(false);

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
                cartItems.value = (response.data.data.items || []).filter(i => i.selected);
                if (cartItems.value.length === 0) {
                    router.push('/cart');
                }
            }
        } else {
            const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
            const selectedLocalItems = localItems.filter(i => i.selected !== false);
            if (selectedLocalItems.length === 0) {
                router.push('/cart');
                return;
            }
            const response = await api.post('/cart/guest-details', { items: selectedLocalItems });
            if (response.data.status === 'success') {
                cartItems.value = response.data.data.items || [];
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

// Lấy số dư ví điện tử
const fetchWalletBalance = async () => {
    try {
        const response = await api.get('/wallet/summary');
        if (response.data.status === 'success') {
            walletBalance.value = response.data.data.balance || 0;
        }
    } catch (error) {
        console.error('Lỗi khi lấy số dư ví:', error);
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
        addresses.value = res.data?.data || [];
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
    return parts.join(', ') || 'Chưa có thông tin địa chỉ cụ thể';
};

// Xử lý tạo địa chỉ mới
const onAddressChange = (data) => {
    formAddress.value.province = data.province_name;
    formAddress.value.province_code = data.province_code;
    formAddress.value.district = data.district_name;
    formAddress.value.district_code = data.district_code;
    formAddress.value.ward = data.ward_name;
    formAddress.value.ward_code = data.ward_code;
    formAddress.value.address_line = data.address_detail;

    if (data.district_code && data.ward_code) {
        getShippingFee(data.district_code, data.ward_code);
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
    if (savedAddress?.district_code && savedAddress?.ward_code) {
        getShippingFee(savedAddress.district_code, savedAddress.ward_code);
    }
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

const getShippingFee = async (district_id, ward_code) => {
    if (!district_id || !ward_code) return;
    isCalculatingFee.value = true;
    try {
        shippingFee.value = await addressService.getShippingFee({
            districtCode: district_id,
            wardCode: ward_code,
            weight: 3000,
        });

        // Lấy leadtime
        const res = await api.post('/ghn/leadtime', {
            to_district_id: Number(district_id),
            to_ward_code: String(ward_code),
        });

        if (res.data.code === 200 && res.data.data) {
            const timestamp = res.data.data.leadtime * 1000;
            const date = new Date(timestamp);
            leadtimeDate.value = date.toLocaleDateString('vi-VN');
        } else {
            leadtimeDate.value = null;
        }

    } catch (error) {
        console.error("Lỗi tính phí vận chuyển GHN:", error.response?.data || error.message);
        shippingFee.value = 0;
        leadtimeDate.value = null;
    } finally {
        isCalculatingFee.value = false;
    }
};

// Watch for address selection change in the list
watch(selectedAddressId, (newVal) => {
    if (newVal && !showAddAddressForm.value) {
        const addr = addresses.value.find(a => a.address_id === newVal);
        if (addr && addr.district_code && addr.ward_code) {
            getShippingFee(addr.district_code, addr.ward_code);
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
    const maxForTotal = Math.floor(totalBeforeLoyalty / 100);
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
    return Math.max(0, subtotal.value + shippingFee.value - discount.value - shippingDiscount.value - loyaltyDiscount.value);
});

// Appy coupon (Mã cứng mockup cho UI: OCEAN10)
const applyCoupon = () => {
    if (!couponCode.value.trim()) return;

    // Tìm trong danh sách available xem có mã nào trùng không
    const found = availableCoupons.value.find(c => c.code.toUpperCase() === couponCode.value.trim().toUpperCase());
    if (found) {
        selectCoupon(found);
    } else {
        // Fake apply cho OCEAN10 nếu backend chưa có data để test UI
        checkingCoupon.value = true;
        setTimeout(() => {
            checkingCoupon.value = false;
            if (couponCode.value.toUpperCase() === 'OCEAN10') {
                appliedCoupon.value = { code: 'OCEAN10', type: 'percent', value: 10 };
                showToast('Đã áp dụng mã giảm giá 10% (OCEAN10)!', 'success');
            } else {
                showToast('Mã giảm giá không hợp lệ hoặc đã hết hạn', 'error');
                appliedCoupon.value = null;
            }
        }, 800);
    }
};

const openCouponModal = () => {
    showCouponModal.value = true;
};

const selectCoupon = (coupon) => {
    if (coupon.type === 'free_ship' && subtotal.value >= (upsellState.freeshipThreshold || 500000)) {
        showToast('Đơn hàng từ 500.000₫ đã được tự động miễn phí vận chuyển!', 'warning');
        return;
    }
    appliedCoupon.value = {
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        max_discount_value: coupon.max_discount_value
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
    if (newSubtotal >= (upsellState.freeshipThreshold || 500000) && appliedCoupon.value && appliedCoupon.value.type === 'free_ship') {
        appliedCoupon.value = null;
        couponCode.value = '';
        showToast('Đơn hàng từ 500.000₫ đã được tự động miễn phí vận chuyển. Mã freeship đã được gỡ bỏ!', 'warning');
    }
});

// Đặt hàng
const placingOrder = ref(false);
const placeOrder = async () => {
    const payload = {
        payment_method: paymentMethod.value,
        note: note.value,
        coupon_applied: appliedCoupon.value?.code || null,
        referral_code: localStorage.getItem('affiliate_ref') || null,
        reward_points_used: useLoyaltyPoints.value ? inputPoints.value : 0,
    };

    if (showAddAddressForm.value) {
        const phoneRegex = /^(0|\+84)(3|5|7|8|9)[0-9]{8}$/;
        if (!formAddress.value.recipient_name.trim()) return showToast('Vui lòng nhập họ tên người nhận', 'error');
        if (!formAddress.value.phone.trim()) {
            showToast('Vui lòng nhập số điện thoại', 'error');
            return;
        }
        if (!formAddress.value.phone.match(phoneRegex)) {
            showToast('Vui lòng nhập số điện thoại hợp lệ', 'error');
            return;
        }    
        if (!formAddress.value.province) return showToast('Vui lòng chọn Tỉnh/Thành phố', 'error');
        if (!formAddress.value.district) return showToast('Vui lòng chọn Quận/Huyện', 'error');
        if (!formAddress.value.ward) return showToast('Vui lòng chọn Phường/Xã', 'error');
        if (!formAddress.value.address_line.trim()) return showToast('Vui lòng nhập địa chỉ chi tiết', 'error');

        payload.recipient_name = formAddress.value.recipient_name;
        payload.phone = formAddress.value.phone;
        payload.province = formAddress.value.province;
        payload.district = formAddress.value.district;
        payload.ward = formAddress.value.ward;
        payload.address_line = formAddress.value.address_line;
        payload.province_code = formAddress.value.province_code;
        payload.district_code = formAddress.value.district_code;
        payload.ward_code = formAddress.value.ward_code;
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
                ? `${payload.address_line}, ${payload.ward}, ${payload.district}, ${payload.province}`
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
            
            // Xóa giỏ hàng local của guest
            if (!authStore.isAuthenticated && !isFlashSale.value) {
                localStorage.removeItem('cart_items');
                window.dispatchEvent(new Event('cart-updated'));
            }
            if (res.data.payment_method === 'vnpay' && res.data.vnpay_url) {
                showToast('Đang chuyển đến cổng thanh toán VNPay...', 'success');
                setTimeout(() => {
                    window.location.href = res.data.vnpay_url;
                }, 500);
                return; // Không set placingOrder = false, giữ loading state
            }

            // === MoMo: redirect sang cổng thanh toán ===
            if (res.data.payment_method === 'momo' && res.data.momo_url) {
                showToast('Đang chuyển đến cổng thanh toán MoMo...', 'success');
                setTimeout(() => {
                    window.location.href = res.data.momo_url;
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
    if (item.variant?.image_url) return `${APP_URL}/storage/${item.variant.image_url}`;
    if (item.product?.main_image) return `${APP_URL}/storage/${item.product.main_image}`;
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `${APP_URL}/storage/${item.product.thumbnail_url}`;
    return 'https://placehold.co/120x120?text=No+Image';
};

onMounted(async () => {
    const promises = [fetchAddresses(), fetchCoupons(), fetchUpsellData(), fetchWalletBalance(), fetchLoyaltyPoints()];
    if (isFlashSale.value) {
        promises.push(fetchFlashSaleData());
    } else {
        promises.push(fetchCart());
    }
    await Promise.all(promises);
    loading.value = false;
});
</script>

<template>
    <div class="checkout-page theme-brown">

        <!-- ===== BANKING QR MODAL ===== -->
        <Teleport to="body">
            <div v-if="showBankingModal" class="banking-modal-overlay" @click.self="null">
                <div class="banking-modal">
                    <div class="banking-modal-header">
                        <span class="banking-modal-icon"><AppIcon name="bank" size="24" /></span>
                        <h2>Thanh toán chuyển khoản</h2>
                        <p>Quét mã QR hoặc chuyển khoản thủ công theo thông tin dưới đây</p>
                    </div>

                    <div class="banking-modal-body">
                        <div class="qr-section">
                            <img
                                v-if="bankingInfo?.qr_url"
                                :src="bankingInfo.qr_url"
                                alt="QR Chuyển khoản"
                                class="qr-image"
                            />
                            <p class="qr-hint">Quét bằng app ngân hàng bất kỳ</p>
                        </div>

                        <div class="bank-info-section">
                            <div class="bank-info-row">
                                <span class="bank-label">Số tài khoản</span>
                                <span class="bank-value highlight">{{ bankingInfo?.account_number }}</span>
                            </div>
                            <div class="bank-info-row">
                                <span class="bank-label">Chủ tài khoản</span>
                                <span class="bank-value">{{ bankingInfo?.account_name }}</span>
                            </div>
                            <div class="bank-info-row">
                                <span class="bank-label">Số tiền</span>
                                <span class="bank-value highlight">{{ formatPrice(bankingInfo?.amount) }}</span>
                            </div>
                            <div class="bank-info-row">
                                <span class="bank-label">Nội dung CK</span>
                                <span class="bank-value highlight">{{ bankingOrderCode }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="banking-note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Nhập đúng <strong>nội dung chuyển khoản</strong> là mã đơn hàng để hệ thống xác nhận tự động!</span>
                    </div>

                    <div class="banking-modal-actions">
                        <button
                            class="btn-banking-done"
                            @click="router.push({ name: 'order-success', params: { order_code: bankingOrderCode } })"
                        >
                            Tôi đã chuyển khoản xong
                        </button>
                        <button class="btn-banking-later" @click="router.push({ name: 'order-success', params: { order_code: bankingOrderCode } })">
                            Chuyển khoản sau
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang chuẩn bị trang thanh toán...</p>
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
                                <button v-if="authStore.isAuthenticated" class="add-tab" :class="{ 'active': !showAddAddressForm }"
                                    @click="useSavedAddresses">
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
                                <div class="form-group pb-2 mt-2">
                                    <AddressSelector
                                        :key="addressSelectorKey"
                                        @change="onAddressChange"
                                    />
                                </div>
                            </div>

                            <!-- SAVED ADDRESSES -->
                            <div v-else class="address-grid animate-in">
                                <div v-if="addresses.length === 0" class="empty-address-box">
                                    <p>Bạn chưa có địa chỉ giao hàng nào.</p>
                                    <button class="btn-outline-brown" @click="openAddAddressForm">Tạo sổ địa chỉ
                                        ngay</button>
                                </div>
                                <label v-for="addr in addresses" :key="addr.address_id" class="address-card"
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
                            </div>

                            <div class="checkout-divider"></div>

                            <div v-if="leadtimeDate" class="leadtime-notice">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2 text-green-600"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
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
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="method-icon"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                        <span class="payment-name-simple">Thanh toán khi nhận hàng (COD)</span>
                                    </div>
                                </label>

                                <label class="payment-card-simple" :class="{ 'is-selected': paymentMethod === 'bank_transfer' }">
                                    <input type="radio" v-model="paymentMethod" value="bank_transfer" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="method-icon"><rect x="2" y="21" width="20" height="2"></rect><polygon points="12 2 2 7 22 7 12 2"></polygon><path d="M5 21V9"></path><path d="M19 21V9"></path><path d="M12 21V9"></path></svg>
                                        <span class="payment-name-simple">Chuyển khoản ngân hàng</span>
                                    </div>
                                </label>

                                <label v-if="!isFlashSale" class="payment-card-simple" :class="{ 'is-selected': paymentMethod === 'momo' }">
                                    <input type="radio" v-model="paymentMethod" value="momo" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d82d8b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="method-icon"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                                        <span class="payment-name-simple">Ví MoMo</span>
                                    </div>
                                </label>

                                <label v-if="!isFlashSale" class="payment-card-simple" :class="{ 'is-selected': paymentMethod === 'vnpay' }">
                                    <input type="radio" v-model="paymentMethod" value="vnpay" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#005a9e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="method-icon"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        <span class="payment-name-simple">VNPay</span>
                                    </div>
                                </label>

                                <label class="payment-card-simple" :class="{ 'is-selected': paymentMethod === 'wallet', 'is-disabled': walletBalance < total }">
                                    <input type="radio" v-model="paymentMethod" value="wallet" :disabled="walletBalance < total" class="hidden-radio" />
                                    <div class="ac-left">
                                        <div class="radio-indicator">
                                            <div class="radio-dot"></div>
                                        </div>
                                    </div>
                                    <div class="payment-info-simple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#8d6e63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="method-icon"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="12" y1="4" x2="12" y2="20"></line><line x1="2" y1="12" x2="22" y2="12"></line></svg>
                                        <span class="payment-name-simple">
                                            Ví điện tử (Ocean Pay) - <strong>{{ formatPrice(walletBalance) }}</strong>
                                            <span v-if="walletBalance < total" style="color: #ef4444; font-size: 11px; margin-left: 8px;">(Số dư không đủ)</span>
                                        </span>
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
                                            <p class="bill-item-variant">
                                                {{ item.variant?.color || '' }}
                                                <span v-if="item.variant?.color && item.variant?.size" class="variant-divider">•</span>
                                                {{ item.variant?.size || '' }}
                                            </p>
                                        </div>
                                        <div class="bill-item-price-col">
                                            <div class="bill-item-price">{{ formatPrice((item.variant?.price || 0) * item.quantity) }}</div>
                                            <div class="bill-item-qty">✕ {{ item.quantity }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="coupon-section">
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
                                        <div class="coupon-tag"><AppIcon name="voucher" size="20" color="#111" class="me-1" />{{ appliedCoupon.code }}</div>
                                        <button class="btn-remove-coupon" @click="removingCoupon">Gỡ bỏ</button>
                                    </div>
                                    <div v-if="authStore.isAuthenticated" class="text-right mt-1">
                                        <button class="btn-select-coupon" @click="openCouponModal">Chọn mã có
                                            sẵn</button>
                                    </div>
                                </div>

                                <!-- Tiêu điểm thưởng -->
                                <div v-if="authStore.isAuthenticated && loyaltyPoints > 0" class="loyalty-section" style="margin-top: 16px; padding: 12px; background: #fff5f5; border-radius: 8px; border: 1px dashed #f87171;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
                                            <input type="checkbox" v-model="useLoyaltyPoints" style="width: 16px; height: 16px; accent-color: #ef4444;" />
                                            Sử dụng điểm thưởng
                                        </label>
                                        <span style="font-size: 0.9rem; color: #ef4444; font-weight: 600;">Bạn có: {{ loyaltyPoints }} điểm</span>
                                    </div>
                                    <div v-if="useLoyaltyPoints" style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                                        <input type="number" v-model="inputPoints" :max="maxPointsCanUse" min="0" style="width: 100px; padding: 6px; border: 1px solid #fca5a5; border-radius: 4px; text-align: center; outline: none;" />
                                        <span style="font-size: 0.9rem; color: #666;">điểm = <strong style="color: #ef4444;">-{{ formatPrice(loyaltyDiscount) }}</strong></span>
                                    </div>
                                    <div v-if="useLoyaltyPoints && maxPointsCanUse < loyaltyPoints" style="font-size: 0.8rem; color: #f59e0b; margin-top: 4px;">
                                        * Chỉ có thể tiêu tối đa {{ maxPointsCanUse }} điểm cho đơn hàng này.
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
                                            <span v-else>{{ formatPrice(shippingFee) }}</span>
                                        </span>
                                    </div>
                                    <div v-if="subtotal < upsellState.freeshipThreshold && shippingFee > 0" class="freeship-hint-row" style="text-align: right; margin-top: -6px; margin-bottom: 8px;">
                                        <small style="color: #0ea5e9; font-size: 0.8rem; font-weight: 500;">
                                           Chỉ cần mua thêm {{ formatPrice(upsellState.freeshipThreshold - subtotal) }} để được Freeship
                                        </small>
                                    </div>
                                    <div class="total-row" v-if="discount > 0 || shippingDiscount > 0 || loyaltyDiscount > 0">
                                        <span>Giảm giá</span>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
                                            <span class="discount-val mb-2" v-if="discount > 0" style="color: #ef4444;">Voucher: - {{ formatPrice(discount) }}</span>
                                            <span class="free-badge mb-2" v-if="shippingDiscount > 0" style="color: #10b981;">Freeship: - {{ formatPrice(shippingDiscount) }}</span>
                                            <span class="discount-val mb-2" v-if="loyaltyDiscount > 0" style="color: #ef4444;">Điểm thưởng: - {{ formatPrice(loyaltyDiscount) }}</span>
                                        </div>
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
                            <div v-if="loadingCoupons" class="loading-state">
                                <div class="spinner-small brown"></div>
                            </div>
                            <div v-else-if="availableCoupons.length > 0" class="coupon-list">
                                <div v-for="coupon in availableCoupons" :key="coupon.id" class="coupon-card"
                                    :class="{
                                        'is-applied': appliedCoupon?.code === coupon.code,
                                        'is-disabled': coupon.type === 'free_ship' && subtotal >= (upsellState.freeshipThreshold || 500000)
                                    }">
                                    <div class="cp-left"><span class="cp-icon"><AppIcon name="voucher" size="24" color="#111" /></span></div>
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
                                        <span v-if="coupon.type === 'free_ship' && subtotal >= (upsellState.freeshipThreshold || 500000)">
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
    </div>
</template>

<style scoped>
.checkout-page {
    padding: 40px 0 80px;
    font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
    color: #0f172a;
    min-height: 80vh;
}

/* Base states & Header */
.checkout-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 16px;
}

.loading-state {
    text-align: center;
    padding: 100px 0;
    color: #E63B6F;
    font-weight: 500;
}

.spinner {
    width: 44px;
    height: 44px;
    border: 4px solid #e2e8f0;
    border-top-color: #E63B6F;
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
    border-top-color: #E63B6F;
}

@keyframes spin {
    100% {
        transform: rotate(360deg);
    }
}

.page-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px dashed #e2e8f0;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 1rem;
    font-weight: 600;
    color: #E63B6F;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    background: white;
    border: 1.5px solid #e2e8f0;
}

.back-link:hover {
    background: #E63B6F;
    color: white;
    border-color: #E63B6F;
}

.icon-brown {
    color: #E63B6F;
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
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.block-border {
    background: #ffffff;
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
    color: #000000;
    border: 1.5px solid transparent;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.add-tab:hover:not(.active) {
    color: #E63B6F;
}

.add-tab.active {
    background: #E63B6F;
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0,0,0,0.05);
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
    background: #ffffff;
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
    padding: 14px 16px;
    border: 1.5px solid transparent;
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s ease;
    background: #f1f5f9;
    color: #0f172a;
}

.form-input:focus {
    background: #ffffff;
    border-color: #E63B6F;
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
    background: white;
}

.checkbox-input:checked+.checkbox-custom {
    background: #E63B6F;
    border-color: #E63B6F;
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
    background: #ffffff;
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
    border-color: #E63B6F;
    background: #f4faff;
    box-shadow: 0 4px 15px rgba(230, 59, 111, 0.08);
}

.radio-indicator {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
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
    border-color: #E63B6F;
    background: #ffffff;
}

.is-selected .radio-dot {
    background: #E63B6F;
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
    color: #E63B6F;
}

.addr-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: #0f172a;
}

.addr-phone {
    color: #475569;
    font-size: 0.95rem;
    font-weight: 500;
}

.badge-default {
    background: #E63B6F;
    color: white;
    padding: 2px 8px;
    border-radius:12px;
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
    background: #ffffff;
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
    background: #E63B6F;
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
    background: white;
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
    border: 2px solid #E63B6F;
    color: #E63B6F;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline-brown:hover {
    background: #E63B6F;
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
    background: #ffffff;
}

.payment-card-simple:hover {
    border-color: #E63B6F;
}

.payment-card-simple.is-selected {
    border-color: #E63B6F;
    background: #ffffff;
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
    background: white;
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
    color: #0f172a;
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
    background: #ffffff;
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
    color: #0f172a;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
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
    color: #0f172a;
    font-size: 0.95rem;
}

.bill-item-qty {
    font-size: 0.85rem;
    color: #94a3b8;
    font-weight: 600;
}

/* Coupons */
.coupon-section {
    background: #ffffff;
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
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
    text-transform: uppercase;
    background: white;
}

.coupon-input:focus {
    border-color: #E63B6F;
}

.btn-apply-coupon {
    padding: 0 20px;
    font-weight: 600;
    background: #E63B6F;
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
    color: #E63B6F;
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
    color: #0f172a;
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
    color: #E63B6F;
}

.btn-place-order {
    width: 100%;
    background: #E63B6F; /* Deep elegant ocean blue */
    color: white;
    border: none;
    border-radius: 5px;
    padding: 14px;
    font-size: 1.15rem;
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
    background: #d21551; /* Darker deep blue on hover */
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
    background: white;
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
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: white;
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
    background: #ffffff;
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
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
}

.coupon-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.coupon-card.is-applied {
    border-color: #E63B6F;
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
    background: #ffffff;
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
    background: #ffffff;
    border-color: #E63B6F;
}

.coupon-card.is-applied .cp-left {
    border-right-color: #E63B6F;
    background: #e0f2fe;
}

.cp-right {
    flex: 1;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-right: 120px; /* Ensures text never overlaps the button */
}

.cp-code {
    margin: 0 0 6px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cp-desc {
    margin: 0 0 4px;
    font-size: 0.95rem;
    font-weight: 600;
    color: #E63B6F;
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
    background: #E63B6F;
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
    background: #10b981; /* Green success color */
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
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
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
    background: white;
    border-radius: 24px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.banking-modal-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
    padding: 28px 32px 24px;
    text-align: center;
    color: white;
}

.banking-modal-icon { font-size: 2.2rem; display: block; margin-bottom: 10px; }

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
    color: #1e293b;
}

.bank-value.highlight { color: #1d4ed8; font-size: 1rem; }

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

.btn-banking-done:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29,78,216,0.3); }

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

.btn-banking-later:hover { background: #f8fafc; color: #374151; }

@media (max-width: 480px) {
    .banking-modal-body { flex-direction: column; align-items: center; }
    .qr-image { width: 180px; height: 180px; }
}


</style>
