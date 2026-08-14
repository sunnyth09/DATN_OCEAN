<script setup>
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
defineProps(['flashSaleProducts', 'isLoadingFlashSale', 'countdown']);
</script>
<template>
    <section class="flash-sale-section" v-if="flashSaleProducts.length > 0 || isLoadingFlashSale">
        <div class="container py-5">
            <!-- Header row -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="flash-sale-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="flash-sale-title mb-0">FLASH SALE</h2>
                        <p class="flash-sale-sub mb-0">Ưu đãi sốc — Số lượng có hạn!</p>
                    </div>
                </div>
                <!-- Countdown -->
                <div class="d-flex align-items-center gap-2">
                    <span class="countdown-label d-none d-sm-inline">Kết thúc sau:</span>
                    <div class="d-flex align-items-center gap-1">
                        <div class="countdown-block">
                            <span class="countdown-num">{{ countdown.h }}</span>
                            <span class="countdown-unit">GIỜ</span>
                        </div>
                        <span class="countdown-sep">:</span>
                        <div class="countdown-block">
                            <span class="countdown-num">{{ countdown.m }}</span>
                            <span class="countdown-unit">PHÚT</span>
                        </div>
                        <span class="countdown-sep">:</span>
                        <div class="countdown-block">
                            <span class="countdown-num">{{ countdown.s }}</span>
                            <span class="countdown-unit">GIÂY</span>
                        </div>
                    </div>
                </div>
                <router-link to="/flash-sale" class="flash-sale-link">
                    Xem tất cả
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </router-link>
            </div>
            <!-- Products grid Bootstrap row -->
            <div class="row g-4">
                <template v-if="isLoadingFlashSale">
                    <div v-for="i in 4" :key="i" class="col-6 col-md-4 col-lg-3">
                        <div class="skeleton-pulse" style="min-height:320px;border-radius:12px;"></div>
                    </div>
                </template>
                <template v-else-if="flashSaleProducts.length > 0">
                    <div v-for="product in flashSaleProducts" :key="'flash-' + product.id"
                        class="col-6 col-md-4 col-lg-3">
                        <div class="flash-sale-card-wrapper h-100">
                            <ProductCard :product="product">
                                <template #bottom-content>
                                    <div class="flash-progress-wrap mt-3">
                                        <div class="flash-progress-bar-container">
                                            <div class="flash-progress-fill"
                                                :style="{ width: product.flash_percent + '%' }"></div>
                                            <div class="flash-progress-text">
                                                <svg v-if="product.flash_percent > 75" class="fire-icon" width="12"
                                                    height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                    <path
                                                        d="M17.5 13c0 2.8-2.5 5.5-5.5 5.5S6.5 15.8 6.5 13c0-3.5 3-6.5 4.5-9.5 1 2 2 3.5 2 4.5 0 .8-.5 1.5-1.5 2C13 8.5 17.5 9.5 17.5 13z" />
                                                </svg>
                                                Đã bán {{ product.flash_sold }}
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </ProductCard>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="col-12 text-center py-5">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="1.5">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                        </svg>
                        <p class="mt-3 text-muted">Flash Sale sẽ sớm trở lại!</p>
                    </div>
                </template>
            </div>
        </div>
    </section>
</template>


<style scoped>
.flash-sale-section {
    background: linear-gradient(135deg, #fff5f5 0%, #ffe4e6 100%);
    position: relative;
}

.flash-sale-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.4), transparent 40%);
    pointer-events: none;
}

.flash-sale-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.flash-sale-title {
    font-size: 1.8rem;
    font-weight: 900;
    color: #111827;
    letter-spacing: -.5px;
}

.flash-sale-sub {
    color: #4b5563;
    font-size: .85rem;
}

.countdown-label {
    color: #4b5563;
    font-size: .85rem;
    font-weight: 600;
}

.countdown-block {
    background: #ffffff;
    border: 1px solid rgba(225, 29, 72, 0.1);
    box-shadow: 0 2px 4px rgba(225, 29, 72, 0.05);
    border-radius: 10px;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 52px;
}

.countdown-num {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.countdown-unit {
    font-size: .6rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 1px;
    margin-top: 2px;
}

.countdown-sep {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    animation: blink 1s step-end infinite;
}

@keyframes blink {
    50% {
        opacity: 0;
    }
}

.flash-sale-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffe4e6;
    color: var(--primary);
    border-radius: 99px;
    padding: 10px 20px;
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}

.flash-sale-link:hover {
    background: var(--primary);
    color: #fff;
}

/* Flash Sale Progress Bar */
.flash-sold-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: #4b5563;
}

.flash-progress-bar {
    width: 100%;
    height: 6px;
    background: #ffe4e6;
    border-radius: 99px;
    overflow: hidden;
}

.flash-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #fb7185, #e11d48);
    border-radius: 99px;
    transition: width 0.5s ease-out;
}
</style>
