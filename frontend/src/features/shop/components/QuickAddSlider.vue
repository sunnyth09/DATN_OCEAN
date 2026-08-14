<script setup>
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();
import { ref } from 'vue';
import { useCartUpsell } from '@/composables/useCartUpsell';
import { getStorageUrl } from '@/utils/url';

const { state, quickAddToCart } = useCartUpsell();

const addingId   = ref(null);   // variant_id đang loading
const addedIds   = ref([]);     // variant_id đã thêm thành công (hiện tick)

const formatPrice = (val) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

const getImage = (item) => {
    if (item.thumbnail_url && item.thumbnail_url !== '0') {
        return getStorageUrl(item.thumbnail_url);
    }
    return defaultSvg;
};

// placeholder SVG giống Cart/Index.vue
const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6">
        <rect width="400" height="500" fill="#f4f9f9"/>
        <g transform="translate(130,230)">
            <path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F"/>
        </g>
        <path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/>
    </svg>`
);

const handleQuickAdd = async (item) => {
    if (addingId.value === item.variant_id) return;
    addingId.value = item.variant_id;

    try {
        const result = await quickAddToCart(item.variant_id);
        if (result.status === 'success') {
            addedIds.value.push(item.variant_id);
            // Dispatch để header badge + Index.vue biết cập nhật
            cartStore.fetchCount()
            // Xóa trạng thái "added" sau 2.5s
            setTimeout(() => {
                addedIds.value = addedIds.value.filter(id => id !== item.variant_id);
            }, 2500);
        }
    } catch (e) {
        console.error('[QuickAddSlider] quickAdd error:', e);
    } finally {
        addingId.value = null;
    }
};
</script>

<template>
    <div v-if="state.loadingSuggestions" class="qs-loading">
        <div class="qs-skeleton" v-for="i in 3" :key="i"></div>
    </div>

    <div v-else-if="state.suggestions.length > 0" class="qs-wrapper">
        <!-- Header -->
        <div class="qs-header">
            <div class="qs-header-left">
                <span class="qs-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/>
                        <path d="M5 3v4"/><path d="M3 5h4"/>
                        <path d="M19 17v4"/><path d="M17 19h4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="qs-title">Gợi Ý Mua Kèm</h3>
                    <p class="qs-subtitle">Tiết kiệm thêm <strong>10%</strong> khi mua ngay hôm nay</p>
                </div>
            </div>
            <span class="qs-tag">Ưu đãi đặc biệt</span>
        </div>

        <!-- Horizontal scroll slider -->
        <div class="qs-slider">
            <div
                v-for="item in state.suggestions"
                :key="item.product_id"
                class="qs-card"
            >
                <!-- Badge giảm giá -->
                <div class="qs-discount-badge">-10%</div>

                <!-- Ảnh -->
                <router-link :to="`/product/${item.slug}`" class="qs-img-wrap">
                    <img
                        :src="getImage(item)"
                        :alt="item.name"
                        class="qs-img"
                        loading="lazy"
                    />
                </router-link>

                <!-- Info -->
                <div class="qs-info">
                    <router-link :to="`/product/${item.slug}`" class="qs-name" :title="item.name">
                        {{ item.name }}
                    </router-link>

                    <div class="qs-prices">
                        <span class="qs-price-original">{{ formatPrice(item.original_price) }}</span>
                        <span class="qs-price-discounted">{{ formatPrice(item.discounted_price) }}</span>
                    </div>

                    <div class="qs-stock-hint" v-if="item.stock <= 5">
                        Chỉ còn {{ item.stock }} sản phẩm
                    </div>
                </div>

                <!-- Nút Thêm nhanh -->
                <button
                    class="qs-btn-add"
                    :class="{
                        'qs-btn-loading': addingId === item.variant_id,
                        'qs-btn-added':   addedIds.includes(item.variant_id),
                    }"
                    :disabled="addingId === item.variant_id || addedIds.includes(item.variant_id)"
                    @click="handleQuickAdd(item)"
                >
                    <!-- Loading spinner -->
                    <span v-if="addingId === item.variant_id" class="qs-spinner"></span>
                    <!-- Success check -->
                    <svg v-else-if="addedIds.includes(item.variant_id)"
                        width="14" height="14" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <!-- Default icon -->
                    <svg v-else
                        width="14" height="14" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5"  y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>
                        {{ addingId === item.variant_id
                            ? 'Đang thêm...'
                            : addedIds.includes(item.variant_id)
                                ? 'Đã thêm!'
                                : 'Thêm nhanh' }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Loading skeleton ── */
.qs-loading {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}
.qs-skeleton {
    flex: 0 0 200px;
    height: 280px;
    border-radius: 14px;
    background: linear-gradient(90deg, #f0f4f8 25%, #e2e8f0 50%, #f0f4f8 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s infinite;
}
@keyframes skeleton-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Wrapper ── */
.qs-wrapper {
    background: linear-gradient(135deg, #fff8f0 0%, #f0fdf4 100%);
    border: 1px solid #fed7aa;
    border-radius: 16px;
    padding: 16px 20px 20px;
    margin-bottom: 20px;
}

/* ── Header ── */
.qs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.qs-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.qs-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    flex-shrink: 0;
}
.qs-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.3;
}
.qs-subtitle {
    margin: 2px 0 0;
    font-size: 0.78rem;
    color: #64748b;
}
.qs-subtitle strong {
    color: #f4811f;
}
.qs-tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: linear-gradient(90deg, #f97316, #eab308);
    color: #fff;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

/* ── Slider ── */
.qs-slider {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.qs-slider::-webkit-scrollbar {
    height: 4px;
}
.qs-slider::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* ── Card ── */
.qs-card {
    flex: 0 0 190px;
    scroll-snap-align: start;
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    transition: box-shadow 0.25s ease, transform 0.25s ease;
}
.qs-card:hover {
    box-shadow: 0 8px 24px rgba(249, 115, 22, 0.12);
    transform: translateY(-3px);
    border-color: #fed7aa;
}

/* Discount badge */
.qs-discount-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
    letter-spacing: 0.3px;
    z-index: 1;
}

/* Image */
.qs-img-wrap {
    display: block;
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 1;
    background: #f8fafc;
}
.qs-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}
.qs-card:hover .qs-img {
    transform: scale(1.07);
}

/* Info */
.qs-info {
    flex: 1;
}
.qs-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-main);
    text-decoration: none;
    line-height: 1.4;
    transition: color 0.2s;
}
.qs-name:hover { color: #f97316; }

.qs-prices {
    display: flex;
    flex-direction: column;
    gap: 1px;
    margin-top: 5px;
}
.qs-price-original {
    font-size: 0.75rem;
    color: #94a3b8;
    text-decoration: line-through;
}
.qs-price-discounted {
    font-size: 0.92rem;
    font-weight: 800;
    color: #ef4444;
}
.qs-stock-hint {
    font-size: 0.7rem;
    color: #f59e0b;
    font-weight: 600;
    margin-top: 3px;
}

/* ── Quick Add Button ── */
.qs-btn-add {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    width: 100%;
    padding: 8px 0;
    border-radius: 9px;
    border: none;
    background: linear-gradient(90deg, #f97316, #eab308);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.25s ease;
}
.qs-btn-add:hover:not(:disabled) {
    background: linear-gradient(90deg, #ea6c0a, #d4a00c);
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35);
    transform: translateY(-1px);
}
.qs-btn-add:disabled {
    cursor: not-allowed;
    opacity: 0.8;
}
.qs-btn-add.qs-btn-added {
    background: linear-gradient(90deg, #22c55e, #16a34a);
    opacity: 1;
}

/* Loading spinner nhỏ trong button */
.qs-spinner {
    width: 13px;
    height: 13px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
