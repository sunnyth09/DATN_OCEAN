<script setup>
import { computed } from 'vue';

/**
 * PremiumUpgrade.vue
 * Hiển thị box gợi ý nâng cấp variant khi sản phẩm có nhiều phiên bản.
 *
 * Props:
 *  - currentVariant  : Object  — Variant đang được chọn
 *  - allVariants     : Array   — Toàn bộ variants của sản phẩm (đã sort theo giá tăng dần từ API)
 *
 * Emits:
 *  - upgrade(variant) : khi khách nhấn "Nâng cấp", trả về variant premium
 */
const props = defineProps({
    currentVariant: {
        type: Object,
        default: null,
    },
    allVariants: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['upgrade']);

/**
 * Tìm variant "tiếp theo" về giá so với variant hiện tại.
 * Vì API trả variants đã sắp xếp theo price ASC, ta chỉ cần lấy
 * phần tử đứng sau currentVariant trong mảng này.
 */
const premiumVariant = computed(() => {
    if (!props.currentVariant || props.allVariants.length < 2) return null;

    const currentIdx = props.allVariants.findIndex(
        (v) => v.variant_id === props.currentVariant.variant_id
    );
    if (currentIdx === -1) return null;

    // Lấy variant kế tiếp (giá cao hơn)
    const next = props.allVariants[currentIdx + 1];
    if (!next) return null;

    return next;
});

/** Chênh lệch giá giữa premium và hiện tại */
const priceDiff = computed(() => {
    if (!premiumVariant.value || !props.currentVariant) return 0;
    return premiumVariant.value.price - props.currentVariant.price;
});

/** Nhãn hiển thị cho variant premium: ưu tiên variant_name, sau đó ghép color + size */
const premiumLabel = computed(() => {
    if (!premiumVariant.value) return '';
    const v = premiumVariant.value;
    if (v.variant_name) return v.variant_name;
    const parts = [v.color, v.size].filter(Boolean);
    return parts.length ? parts.join(' / ') : `Phiên bản #${v.variant_id}`;
});

const formatVND = (amount) =>
    new Intl.NumberFormat('vi-VN').format(amount) + 'đ';

const handleUpgrade = () => {
    if (premiumVariant.value) {
        emit('upgrade', premiumVariant.value);
    }
};
</script>

<template>
    <Transition name="upsell-slide">
        <div
            v-if="premiumVariant && priceDiff > 0"
            class="premium-upsell-box"
            role="complementary"
            aria-label="Gợi ý nâng cấp phiên bản"
        >
            <!-- Crown icon -->
            <div class="upsell-icon-wrap">
                <svg class="crown-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M2 19h20M3 9l4 5 5-8 5 8 4-5v8H3z"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                    />
                </svg>
            </div>

            <!-- Message -->
            <div class="upsell-content">
                <p class="upsell-text">
                    Chỉ cần thêm
                    <strong class="price-diff">{{ formatVND(priceDiff) }}</strong>
                    để sở hữu phiên bản
                    <span class="premium-name">{{ premiumLabel }}</span>
                </p>
                <p class="upsell-sub">Được nhiều khách hàng lựa chọn hơn ✓</p>
            </div>

            <!-- CTA button -->
            <button
                class="btn-upgrade"
                @click="handleUpgrade"
                :aria-label="`Nâng cấp lên phiên bản ${premiumLabel}`"
            >
                <span>Nâng cấp</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>

<style scoped>
/* ── Wrapper box ── */
.premium-upsell-box {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 14px;
    padding: 14px 16px;
    border: 2px dashed #c8a415;          /* Viền nét đứt vàng kim */
    border-radius: 12px;
    background: linear-gradient(135deg, #fffbea 0%, #fff8db 100%);
    position: relative;
    overflow: hidden;
    cursor: default;
    box-shadow: 0 2px 12px rgba(200, 164, 21, 0.12);
    animation: shimmer-bg 4s ease-in-out infinite;
}

/* Tinh tế: hiệu ứng ánh sáng lướt qua */
.premium-upsell-box::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.35) 50%,
        transparent 100%
    );
    transform: translateX(-100%);
    animation: shimmer-sweep 3.5s ease-in-out infinite;
    pointer-events: none;
}

@keyframes shimmer-sweep {
    0%   { transform: translateX(-100%); }
    60%  { transform: translateX(100%); }
    100% { transform: translateX(100%); }
}

/* ── Crown icon ── */
.upsell-icon-wrap {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #f5c518, #d69e2e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(214, 158, 46, 0.5);
    animation: pulse-crown 2.5s ease-in-out infinite;
}

@keyframes pulse-crown {
    0%, 100% { box-shadow: 0 2px 8px rgba(214, 158, 46, 0.5); }
    50%       { box-shadow: 0 4px 18px rgba(214, 158, 46, 0.75); }
}

.crown-icon {
    width: 18px;
    height: 18px;
    color: #fff;
}

/* ── Nội dung chữ ── */
.upsell-content {
    flex: 1;
    min-width: 0;
}

.upsell-text {
    font-size: 0.9rem;
    font-weight: 500;
    color: #5a4000;
    line-height: 1.4;
    margin: 0 0 3px;
}

/* Phần chênh lệch giá — bôi đậm, màu cam/đỏ */
.price-diff {
    font-weight: 800;
    font-size: 1rem;
    color: #e53e3e;             /* Đỏ thu hút */
    background: #fff0f0;
    padding: 1px 5px;
    border-radius: 4px;
    display: inline-block;
}

.premium-name {
    font-weight: 700;
    color: #92610a;
}

.upsell-sub {
    font-size: 0.78rem;
    color: #a07820;
    margin: 0;
    font-weight: 500;
}

/* ── Nút Nâng cấp ── */
.btn-upgrade {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #f5c518, #d69e2e);
    color: #3d2800;
    font-size: 0.82rem;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 8px rgba(214, 158, 46, 0.5);
    transition: all 0.22s ease;
    text-transform: uppercase;
}

.btn-upgrade:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 6px 18px rgba(214, 158, 46, 0.65);
    background: linear-gradient(135deg, #fdd835, #c8a415);
}

.btn-upgrade:active {
    transform: translateY(0);
}

/* ── Transition vào / ra ── */
.upsell-slide-enter-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.upsell-slide-leave-active {
    transition: all 0.25s ease-in;
}
.upsell-slide-enter-from,
.upsell-slide-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.97);
}
</style>
