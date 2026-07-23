<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/axios';

const router = useRouter();
const route = useRoute();

const loading = ref(true);
const paymentResult = ref(null);
const error = ref(null);

/**
 * Khi VNPay redirect user về /payment/result?vnp_...,
 * ta gửi toàn bộ query params đến backend để verify và lấy kết quả.
 * 
 * Cache kết quả vào sessionStorage để tránh gọi API lại khi user refresh trang.
 */
const verifyPayment = async () => {
    try {
        const queryString = window.location.search;
        if (!queryString) {
            error.value = 'Không tìm thấy thông tin thanh toán.';
            loading.value = false;
            return;
        }

        // Kiểm tra cache — tránh gọi API lại khi user refresh
        const cacheKey = 'payment_result_' + btoa(queryString);
        const cached = sessionStorage.getItem(cacheKey);
        if (cached) {
            try {
                paymentResult.value = JSON.parse(cached);
                loading.value = false;
                return;
            } catch (e) {
                sessionStorage.removeItem(cacheKey);
            }
        }

        let endpoint = '';
        if (queryString.includes('vnp_')) {
            endpoint = '/payment/vnpay-return' + queryString;
        } else if (queryString.includes('partnerCode=') && queryString.includes('orderId=')) {
            endpoint = '/payment/momo-return' + queryString;
        } else {
            error.value = 'Đường dẫn thanh toán không hợp lệ.';
            loading.value = false;
            return;
        }

        const res = await api.get(endpoint);
        paymentResult.value = res.data;

        // Lưu kết quả vào sessionStorage
        sessionStorage.setItem(cacheKey, JSON.stringify(res.data));
    } catch (err) {
        console.error('Payment verify error:', err);
        if (err.response?.data) {
            paymentResult.value = err.response.data;
        } else {
            error.value = 'Đã xảy ra lỗi khi xác minh thanh toán. Vui lòng kiểm tra lại đơn hàng.';
        }
    } finally {
        loading.value = false;
    }
};

const isSuccess = () => paymentResult.value?.payment_status === 'paid';

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatPayDate = (dateStr) => {
    if (!dateStr) return '';
    // VNPay format: 20240402170000
    const y = dateStr.substring(0, 4);
    const m = dateStr.substring(4, 6);
    const d = dateStr.substring(6, 8);
    const h = dateStr.substring(8, 10);
    const mi = dateStr.substring(10, 12);
    const s = dateStr.substring(12, 14);
    return `${h}:${mi}:${s} ${d}/${m}/${y}`;
};

onMounted(() => {
    verifyPayment();
});
</script>

<template>
    <div class="payment-result-page">
      <div class="container d-flex justify-content-center">
        <!-- Loading State -->
        <div v-if="loading" class="result-loading">
            <div class="loading-spinner"></div>
            <p class="loading-text">Đang xác minh thanh toán...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="result-card result-error animate-in">
            <div class="result-icon error-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
            </div>
            <h1 class="result-title error">Lỗi xác minh</h1>
            <p class="result-message">{{ error }}</p>
            <div class="result-actions">
                <router-link to="/profile/orders" class="btn-primary">Xem đơn hàng</router-link>
                <router-link to="/" class="btn-secondary">Về trang chủ</router-link>
            </div>
        </div>

        <!-- Success State -->
        <div v-else-if="paymentResult && isSuccess()" class="result-card result-success animate-in">
            <div class="result-icon success-icon">
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
            </div>
            <h1 class="result-title success">Thanh toán thành công!</h1>
            <p class="result-message">Cảm ơn bạn đã mua hàng tại Ocean Store. Đơn hàng của bạn đã được xác nhận thanh
                toán.</p>

            <div class="result-details">
                <div class="detail-row">
                    <span class="detail-label">Mã đơn hàng</span>
                    <span class="detail-value highlight">{{ paymentResult.data?.order_code }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Số tiền</span>
                    <span class="detail-value price">{{ formatPrice(paymentResult.data?.grand_total) }}</span>
                </div>
                <div class="detail-row" v-if="paymentResult.data?.transaction_no">
                    <span class="detail-label">Mã giao dịch (TransID)</span>
                    <span class="detail-value">{{ paymentResult.data?.transaction_no }}</span>
                </div>
                <div class="detail-row" v-if="paymentResult.data?.bank_code">
                    <span class="detail-label">Ngân hàng</span>
                    <span class="detail-value">{{ paymentResult.data?.bank_code }}</span>
                </div>
                <div class="detail-row" v-if="paymentResult.data?.pay_date">
                    <span class="detail-label">Thời gian thanh toán</span>
                    <span class="detail-value">{{ formatPayDate(paymentResult.data?.pay_date) }}</span>
                </div>
            </div>

            <div class="result-actions">
                <router-link to="/profile/orders" class="btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                    Xem đơn hàng
                </router-link>
                <router-link to="/" class="btn-secondary">Tiếp tục mua sắm</router-link>
            </div>
        </div>

        <!-- Failed State -->
        <div v-else-if="paymentResult" class="result-card result-failed animate-in">
            <div class="result-icon failed-icon">
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
            </div>
            <h1 class="result-title failed">Thanh toán không thành công</h1>
            <p class="result-message">{{ paymentResult.message }}</p>

            <div class="result-details" v-if="paymentResult.data">
                <div class="detail-row">
                    <span class="detail-label">Mã đơn hàng</span>
                    <span class="detail-value">{{ paymentResult.data?.order_code }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Số tiền</span>
                    <span class="detail-value">{{ formatPrice(paymentResult.data?.grand_total) }}</span>
                </div>
            </div>

            <p class="result-note">Đơn hàng sẽ được hủy tự động nếu bạn không thanh toán lại. Bạn có thể liên hệ hỗ trợ nếu cần.</p>

            <div class="result-actions">
                <router-link to="/profile/orders" class="btn-primary">Xem đơn hàng</router-link>
                <router-link to="/" class="btn-secondary">Về trang chủ</router-link>
            </div>
        </div>
      </div>
    </div>
</template>

<style scoped>
.payment-result-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
    background: linear-gradient(135deg, #f0f4ff 0%, #fafbfd 50%, #fff5f0 100%);
    font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
}

/* Loading */
.result-loading {
    text-align: center;
}

.loading-spinner {
    width: 56px;
    height: 56px;
    border: 4px solid #e2e8f0;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 20px;
}

.loading-text {
    color: #64748b;
    font-size: 1rem;
    font-weight: 500;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Card */
.result-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 48px 40px;
    max-width: 520px;
    width: 100%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 4px 16px rgba(0, 0, 0, 0.04);
}

/* Icons */
.result-icon {
    margin-bottom: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    border-radius: 50%;
}

.success-icon {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #16a34a;
    animation: iconPop 0.5s ease-out;
}

.failed-icon {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
    animation: iconPop 0.5s ease-out;
}

.error-icon {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
    animation: iconPop 0.5s ease-out;
}

@keyframes iconPop {
    0% { transform: scale(0.5); opacity: 0; }
    70% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

/* Title */
.result-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.result-title.success { color: #16a34a; }
.result-title.failed { color: #d97706; }
.result-title.error { color: #dc2626; }

/* Message */
.result-message {
    color: #64748b;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 28px;
}

/* Details */
.result-details {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    text-align: left;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
}

.detail-value {
    color: var(--text-main);
    font-size: 0.9rem;
    font-weight: 600;
}

.detail-value.highlight {
    color: var(--primary);
    font-family: 'Roboto Mono', monospace;
    letter-spacing: 0.5px;
}

.detail-value.price {
    color: #dc2626;
    font-size: 1rem;
}

/* Note */
.result-note {
    color: #94a3b8;
    font-size: 0.85rem;
    line-height: 1.5;
    margin-bottom: 24px;
    padding: 12px 16px;
    background: #fffbeb;
    border-radius: 8px;
    border-left: 3px solid #f59e0b;
    text-align: left;
}

/* Actions */
.result-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary), #C4305D);
    color: #fff;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(230, 59, 111, 0.4);
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f1f5f9;
    color: #475569;
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

/* Animation */
.animate-in {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 560px) {
    .result-card {
        padding: 32px 20px;
        border-radius: 16px;
    }

    .result-title {
        font-size: 1.4rem;
    }

    .result-icon {
        width: 80px;
        height: 80px;
    }

    .result-icon svg {
        width: 48px;
        height: 48px;
    }

    .result-actions {
        flex-direction: column;
    }

    .btn-primary, .btn-secondary {
        justify-content: center;
        width: 100%;
    }
}
</style>
