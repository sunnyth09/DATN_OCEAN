<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import ProductCard from "@/components/ProductCard.vue";
import { catalogService } from "@/services/catalogService";
import { orderService } from "@/services/orderService";
import { useAuthStore } from "@/stores/auth";
import { useCartStore } from "@/stores/cart";
import api from "@/axios";

const route = useRoute();
const orderCode = route.params.order_code || "";
const authStore = useAuthStore();
const cartStore = useCartStore();
const relatedProducts = ref([]);
const loading = ref(true);
const orderId = ref(null);
const paymentStatus = ref(null); // null = chưa biết, 'paid' = đã thanh toán, 'pending' = chờ
const paymentPollingTimer = ref(null);

const fetchRelatedProducts = async () => {
    loading.value = true;
    try {
        const res = await catalogService.listProducts({
            limit: 10,
            sort: "newest",
        });
        if (res.data.status === "success") {
            relatedProducts.value = res.data.data.data || [];
        } else if (res.data.data && Array.isArray(res.data.data)) {
            relatedProducts.value = res.data.data;
        }
    } catch (e) {
        // silent
    } finally {
        loading.value = false;
    }
};

const fetchOrderId = async () => {
    try {
        const res = await orderService.resolveOrderId(orderCode);
        if (res.data.status === "success") {
            orderId.value = res.data.data.order_id;
        }
    } catch (e) {
        // silent
    }
};

// Polling payment status cho đơn chuyển khoản ngân hàng
// Tối đa 3 phút (36 lần x 5 giây), dừng khi đã paid
let pollCount = 0;
const MAX_POLLS = 36;

const checkPaymentStatus = async () => {
    if (!orderCode) return;
    try {
        const res = await api.get(`/orders/status/${orderCode}`);
        const order = res.data?.data;
        if (order?.payment_status === 'paid') {
            paymentStatus.value = 'paid';
            stopPaymentPolling();
        } else {
            paymentStatus.value = order?.payment_status || 'pending';
        }
    } catch (e) {
        // silent — giữ trạng thái cũ
    }
};

const startPaymentPolling = () => {
    if (!orderCode) return;
    // Kiểm tra ngay lập tức
    checkPaymentStatus();
    paymentPollingTimer.value = setInterval(async () => {
        pollCount++;
        if (pollCount >= MAX_POLLS) {
            stopPaymentPolling();
            return;
        }
        await checkPaymentStatus();
    }, 5000);
};

const stopPaymentPolling = () => {
    if (paymentPollingTimer.value) {
        clearInterval(paymentPollingTimer.value);
        paymentPollingTimer.value = null;
    }
};

if (orderCode && authStore.isAuthenticated) {
    fetchOrderId();
}

onMounted(() => {
    fetchRelatedProducts();
    // Bắt đầu polling để phát hiện payment thành công (bank transfer)
    startPaymentPolling();
    cartStore.fetchCount();
    window.dispatchEvent(new Event('cart-updated'));
});

onUnmounted(() => {
    stopPaymentPolling();
});
</script>

<template>
    <div class="order-success-page theme-brown">
      <div class="order-success-container d-flex flex-column align-items-center">
        <!-- Khu vực thông báo thành công -->
        <div class="success-banner animate-in">
            <div class="success-icon-wrapper">
                <svg
                    width="64"
                    height="64"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#22c55e"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>

            <h1 class="success-title">Đặt hàng thành công!</h1>

            <p class="success-message">
                Cảm ơn bạn đã mua sắm tại <strong>Ocean Sport</strong>. Đơn hàng của
                bạn đã được tiếp nhận và đang trong quá trình xử lý.
            </p>

            <div v-if="orderCode" class="order-code-box">
                <span class="label">Mã đơn hàng:</span>
                <span class="code">#{{ orderCode }}</span>
            </div>

            <!-- Badge trạng thái thanh toán (chỉ hiển thị khi có kết quả polling) -->
            <div v-if="paymentStatus === 'paid'" class="payment-status-badge paid">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Thanh toán đã được xác nhận!
            </div>
            <div v-else-if="paymentStatus && paymentStatus !== 'paid'" class="payment-status-badge pending">
                <span class="spinner-dot"></span>
                Đang chờ xác nhận thanh toán...
            </div>

            <p class="email-notice">
                Thông tin chi tiết về đơn hàng đã được gửi đến email của bạn.<br />
                Bạn có thể theo dõi trạng thái đơn hàng bất kỳ lúc nào.
            </p>

            <div class="action-buttons">
                <router-link
                    v-if="authStore.isAuthenticated"
                    :to="orderId ? '/profile/orders/' + orderId : (orderCode ? '/profile/orders?code=' + orderCode : '/profile/orders')"
                    class="btn-outline-brown"
                    >Xem đơn hàng của tôi</router-link
                >
                <router-link to="/product" class="btn-solid-brown"
                    >Tiếp tục mua sắm</router-link
                >
            </div>
        </div>

        <!-- Khu vực Sản phẩm liên quan -->
        <div
            class="related-products-section animate-in"
            style="animation-delay: 0.15s"
        >
            <div class="section-title">
                <h2>Có thể bạn sẽ thích</h2>
                <div class="title-divider"></div>
            </div>

            <div v-if="loading" class="order-success-skeleton" style="text-align: center; padding: 40px 20px;">
                <div class="skeleton-pulse" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 20px;"></div>
                <div class="skeleton-pulse" style="height: 30px; width: 60%; margin: 0 auto 16px; border-radius: 8px;"></div>
                <div class="skeleton-pulse" style="height: 20px; width: 40%; margin: 0 auto 30px; border-radius: 8px;"></div>
                <div class="skeleton-pulse" style="height: 150px; width: 100%; max-width: 500px; margin: 0 auto; border-radius: 12px;"></div>
            </div>

            <div v-else-if="relatedProducts.length > 0" class="products-grid">
                <ProductCard
                    v-for="product in relatedProducts"
                    :key="product.product_id"
                    :product="product"
                />
            </div>
        </div>
      </div>
    </div>
</template>

<style scoped>
/* Payment status badges */
.payment-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 16px;
    animation: fadeInBadge 0.4s ease;
}
.payment-status-badge.paid {
    background: #f0fdf4;
    color: #16a34a;
    border: 1.5px solid #86efac;
}
.payment-status-badge.pending {
    background: #fffbeb;
    color: #d97706;
    border: 1.5px solid #fcd34d;
}
.spinner-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: currentColor;
    display: inline-block;
    animation: pulseDot 1.2s ease-in-out infinite;
}
@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.7); }
}
@keyframes fadeInBadge {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.order-success-page {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background-color: #f8fafc;
    min-height: 100vh;
    padding: 60px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.order-success-container {
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
    padding: 0 40px;
}

/* Success Banner */
.success-banner {
    background: var(--card-bg);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
    padding: 50px 40px;
    max-width: 650px;
    width: 100%;
    text-align: center;
    margin-bottom: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    border: 1px solid #E9ECEF;
}

.success-icon-wrapper {
    width: 100px;
    height: 100px;
    background: #f0fdf4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    box-shadow: 0 0 0 10px rgba(34, 197, 94, 0.1);
    animation: scale-up 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.success-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 16px;
}

.success-message {
    color: #636E72;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 24px;
}

.order-code-box {
    background: #f8fafc;
    border: 1px dashed var(--primary);
    padding: 12px 24px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.order-code-box .label {
    color: #636E72;
    font-size: 0.95rem;
}

.order-code-box .code {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
    letter-spacing: 1px;
}

.email-notice {
    color: #B2BEC3;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 32px;
}

.action-buttons {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-solid-brown,
.btn-outline-brown {
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.btn-solid-brown {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.btn-solid-brown:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, 0.25);
}

.btn-outline-brown {
    background: transparent;
    color: var(--text-main);
    border: 1px solid #cbd5e1;
}

.btn-outline-brown:hover {
    background: #f8fafc;
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-3px);
}

/* Related Products Section */
.related-products-section {
    max-width: 100%;
    width: 100%;
}

.section-title {
    text-align: center;
    margin-bottom: 40px;
}

.section-title h2 {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 12px;
}

.title-divider {
    width: 60px;
    height: 4px;
    background: var(--primary);
    border-radius: 2px;
    margin: 0 auto;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 24px;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 0;
    color: #636E72;
    font-weight: 600;
}

.small-spinner {
    width: 30px;
    height: 30px;
    border: 3px solid #E9ECEF;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

/* Responsive */
@media (max-width: 1024px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    .success-banner {
        padding: 40px 20px;
    }
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    .btn-solid-brown,
    .btn-outline-brown {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes scale-up {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
