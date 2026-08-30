<script setup>
import AppIcon from "@/components/AppIcon.vue";
import CouponDetailModal from "@/features/shop/components/CouponDetailModal.vue";

const props = defineProps({
    publicCoupons: { type: Array, default: () => [] },
    copiedCouponCode: { type: String, default: '' },
    selectedCoupon: { type: Object, default: null },
});

const emit = defineEmits(['copy', 'open', 'close', 'view-all']);

const formatCurrency = (val) => {
    if (!val) return '0₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatCouponValue = (coupon) => {
    if (coupon.type === 'percent') return `Giảm ${coupon.value}%`;
    if (coupon.type === 'free_ship') return 'Miễn phí vận chuyển';
    return `Giảm ${formatCurrency(coupon.value)}`;
};

const getCouponIcon = (coupon) => {
    if (coupon.type === 'free_ship') return 'shipping';
    if (coupon.type === 'percent') return 'percent';
    return 'tag';
};
</script>

<template>
    <section class="home-coupon-section py-5" v-if="publicCoupons.length > 0">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-4">
                <div>
                    <span class="coupon-section-kicker">ƯU ĐÃI CÓ HẠN</span>
                    <h2 class="section-title mb-1">SĂN VOUCHER HÔM NAY</h2>
                    <p class="section-subtitle mb-0">Sao chép mã trước, đăng nhập khi thanh toán để sử dụng voucher.</p>
                </div>
                <router-link to="/coupon" class="link-more d-flex align-items-center gap-1">
                    Xem tất cả voucher
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </router-link>
            </div>

            <div class="home-coupon-grid">
                <article
                    v-for="coupon in publicCoupons"
                    :key="coupon.id"
                    class="home-coupon-card"
                    @click="emit('open', coupon)"
                >
                    <div class="coupon-ticket-cut coupon-ticket-cut--left"></div>
                    <div class="coupon-ticket-cut coupon-ticket-cut--right"></div>
                    <div class="home-coupon-icon">
                        <AppIcon :name="getCouponIcon(coupon)" width="24" height="24" :stroke-width="2.2" />
                    </div>
                    <div class="home-coupon-main">
                        <span class="home-coupon-type">{{ coupon.type === 'free_ship' ? 'FREESHIP' : 'VOUCHER' }}</span>
                        <strong class="home-coupon-value">{{ formatCouponValue(coupon) }}</strong>
                        <span class="home-coupon-condition" v-if="coupon.min_order_value">
                            Đơn từ {{ formatCurrency(coupon.min_order_value) }}
                        </span>
                        <span class="home-coupon-condition" v-else>Không yêu cầu đơn tối thiểu</span>
                    </div>
                    <button class="home-coupon-copy" type="button" @click.stop="emit('copy', coupon.code)">
                        {{ coupon.code }}
                        <span>{{ copiedCouponCode === coupon.code ? 'Đã sao chép' : 'Sao chép' }}</span>
                    </button>
                </article>
            </div>
        </div>
    </section>

    <CouponDetailModal
        :coupon="selectedCoupon"
        @close="emit('close')"
        @copy="(code) => emit('copy', code)"
        @view-all="emit('view-all')"
    />
</template>

<style scoped>
/* ── Shared section header styles ── */
.section-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.5px;
    margin: 0;
}

.section-subtitle {
    color: #636E72;
    font-size: 0.95rem;
}

.link-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    white-space: nowrap;
    transition: gap 0.2s, color 0.2s;
}

.link-more:hover {
    color: #d82f65;
    gap: 10px;
}

/* ── Voucher section ── */
.home-coupon-section {
    background: #fbfcfd;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

.coupon-section-kicker {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--primary);
    margin-bottom: 4px;
}

.home-coupon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

.home-coupon-card {
    position: relative;
    background: #ffffff;
    border: 1.5px dashed rgba(230, 59, 111, 0.28);
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 20px 20px 24px;
    cursor: pointer;
    transition: all 0.25s ease;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.home-coupon-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(230, 59, 111, 0.1);
    border-color: var(--primary);
}

.coupon-ticket-cut {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    background: #fbfcfd;
    border-radius: 50%;
}

.coupon-ticket-cut--left { left: -8px; }
.coupon-ticket-cut--right { right: -8px; }

.home-coupon-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.home-coupon-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}

.home-coupon-type {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: #94a3b8;
    text-transform: uppercase;
}

.home-coupon-value {
    font-size: 1rem;
    font-weight: 800;
    color: var(--primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.home-coupon-condition {
    font-size: 0.78rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.home-coupon-copy {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 0.8rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.home-coupon-copy span {
    font-family: inherit;
    font-size: 0.68rem;
    font-weight: 600;
    opacity: 0.85;
    letter-spacing: 0;
}

.home-coupon-copy:hover {
    background: #c4295a;
    transform: scale(1.05);
}

/* ── Dark Mode ── */
html.dark .home-coupon-section {
    background: linear-gradient(135deg, #141718 0%, #0e1112 100%);
}

html.dark .home-coupon-card {
    background: #1a1d1e;
    border-color: rgba(255, 178, 191, 0.2);
}

html.dark .coupon-ticket-cut {
    background: #141718;
}

html.dark .home-coupon-icon {
    background: rgba(255, 178, 191, 0.1);
}

html.dark .home-coupon-condition {
    color: #94a3b8;
}

@media (max-width: 640px) {
    .home-coupon-grid {
        grid-template-columns: 1fr;
    }
}
</style>
