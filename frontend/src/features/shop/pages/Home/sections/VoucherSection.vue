<script setup>
import AppIcon from "@/components/AppIcon.vue";
import CouponDetailModal from "@/features/shop/components/CouponDetailModal.vue";

const props = defineProps({
    publicCoupons: { type: Array, default: () => [] },
    copiedCouponCode: { type: String, default: '' },
    selectedCoupon: { type: Object, default: null },
});

const emit = defineEmits(['copy', 'open', 'close', 'view-all']);

const formatCurrencyShort = (val) => {
    if (!val) return '0₫';
    const num = Number(val);
    if (num >= 1000000) {
        const tr = (num / 1000000).toFixed(1).replace(/\.0$/, '');
        return `${tr}tr`;
    }
    if (num >= 1000) {
        return `${Math.round(num / 1000)}k`;
    }
    return `${num}₫`;
};

const formatCouponValue = (coupon) => {
    if (coupon.type === 'percent') return `Giảm ${coupon.value}%`;
    if (coupon.type === 'free_ship') return 'Freeship';
    const val = Number(coupon.value);
    if (val >= 1000) {
        return `Giảm ${Math.round(val / 1000)}k`;
    }
    return `Giảm ${val}₫`;
};

const getCouponIcon = (coupon) => {
    if (coupon.type === 'free_ship') return 'shipping';
    if (coupon.type === 'percent') return 'percent';
    return 'tag';
};
</script>

<template>
    <section class="home-coupon-section py-4" v-if="publicCoupons.length > 0">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-3 flex-wrap gap-2">
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

            <!-- 4 Columns Grid -->
            <div class="home-coupon-grid">
                <article
                    v-for="coupon in publicCoupons"
                    :key="coupon.id"
                    class="home-coupon-card"
                    @click="emit('open', coupon)"
                >
                    <div class="coupon-ticket-cut coupon-ticket-cut--left"></div>
                    <div class="coupon-ticket-cut coupon-ticket-cut--right"></div>
                    
                    <!-- Left Icon Box -->
                    <div class="home-coupon-icon">
                        <AppIcon :name="getCouponIcon(coupon)" width="18" height="18" :stroke-width="2.2" />
                    </div>

                    <!-- Middle Info -->
                    <div class="home-coupon-main">
                        <span class="home-coupon-type">{{ coupon.type === 'free_ship' ? 'FREESHIP' : 'GIẢM GIÁ' }}</span>
                        <strong class="home-coupon-value">{{ formatCouponValue(coupon) }}</strong>
                        <span class="home-coupon-condition">
                            {{ coupon.min_order_value ? 'Đơn từ ' + formatCurrencyShort(coupon.min_order_value) : 'Đơn từ 0₫' }}
                        </span>
                    </div>

                    <!-- Right Copy Button -->
                    <button 
                        class="btn-copy-coupon" 
                        :class="{ 'is-copied': copiedCouponCode === coupon.code }"
                        type="button" 
                        @click.stop="emit('copy', coupon.code)"
                    >
                        <span class="coupon-code-label">{{ coupon.code }}</span>
                        <span class="coupon-action-label">{{ copiedCouponCode === coupon.code ? 'Đã sao chép' : 'Sao chép' }}</span>
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
    color: var(--text-main, #2D3436);
    letter-spacing: -0.5px;
    margin: 0;
}

.section-subtitle {
    color: var(--text-secondary, #636E72);
    font-size: 0.9rem;
}

.link-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--primary, #E63B6F);
    font-weight: 600;
    font-size: 0.88rem;
    text-decoration: none;
    white-space: nowrap;
    transition: gap 0.2s, color 0.2s;
}

.link-more:hover {
    color: var(--primary-dark, #b50c4d);
    gap: 8px;
}

/* ── Voucher section ── */
.home-coupon-section {
    background: #fbfcfd;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

.coupon-section-kicker {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--primary, #E63B6F);
    margin-bottom: 2px;
}

/* 4 columns layout */
.home-coupon-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.home-coupon-card {
    position: relative;
    background: #ffffff;
    border: 1.5px dashed rgba(230, 59, 111, 0.25);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.25s ease;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.home-coupon-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(230, 59, 111, 0.1);
    border-color: var(--primary, #E63B6F);
}

.coupon-ticket-cut {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 12px;
    background: #fbfcfd;
    border-radius: 50%;
}

.coupon-ticket-cut--left { left: -6px; }
.coupon-ticket-cut--right { right: -6px; }

.home-coupon-icon {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border-radius: 8px;
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary, #E63B6F);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.home-coupon-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}

.home-coupon-type {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.6px;
    color: #94a3b8;
    text-transform: uppercase;
}

.home-coupon-value {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--primary, #E63B6F);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.home-coupon-condition {
    font-size: 0.72rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Right Copy Button */
.btn-copy-coupon {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    background: var(--primary, #E63B6F);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 6px 8px;
    min-width: 68px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.coupon-code-label {
    font-size: 0.72rem;
    font-weight: 800;
    font-family: 'Courier New', monospace;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 64px;
}

.coupon-action-label {
    font-size: 0.62rem;
    font-weight: 600;
    opacity: 0.9;
    white-space: nowrap;
}

.btn-copy-coupon:hover {
    background: var(--primary-dark, #b50c4d);
    transform: scale(1.03);
}

.btn-copy-coupon.is-copied {
    background: #10b981;
}

/* ── Dark Mode ── */
html.dark .home-coupon-section {
    background: linear-gradient(135deg, #141718 0%, #0e1112 100%);
    border-color: rgba(255, 255, 255, 0.06);
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

/* Responsive */
@media (max-width: 1200px) {
    .home-coupon-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .home-coupon-grid {
        grid-template-columns: 1fr;
    }
}
</style>
