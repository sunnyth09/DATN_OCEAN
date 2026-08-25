<template>
    <Teleport to="body">
        <Transition name="vmodal">
            <div v-if="show" class="vmodal-overlay" @click.self="$emit('close')">
                <div class="vmodal-box" role="dialog" aria-modal="true" aria-labelledby="vmodal-title">
                    <!-- Close button in top-right -->
                    <button class="vmodal-close-top" type="button" @click="$emit('close')" title="Đóng">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>

                    <div class="vmodal-body-content">
                        <div class="vmodal-grid-layout">
                            <!-- Left: Gallery -->
                            <div class="vmodal-gallery-side">
                                <div class="vmodal-main-image-wrapper">
                                    <img :src="activeImg || imageUrl" :alt="productName" class="vmodal-main-image" />
                                </div>
                                <div class="vmodal-gallery-thumbs" v-if="modalImages.length > 1">
                                    <button v-for="(img, idx) in modalImages" :key="idx" type="button"
                                        class="vmodal-thumb-btn" :class="{ active: (activeImg || imageUrl) === img }"
                                        @click="activeImg = img">
                                        <img :src="img" :alt="productName" />
                                    </button>
                                </div>
                            </div>

                            <!-- Right: Options & Config -->
                            <div class="vmodal-details-side">
                                <div class="vmodal-product-meta">
                                    <span class="vmodal-badge">Chọn phân loại hàng</span>
                                    <h3 id="vmodal-title" class="vmodal-product-title">{{ productName }}</h3>
                                    <div class="vmodal-price-range">
                                        <span class="vmodal-price-original" v-if="displayOriginalPrice">
                                            {{ displayOriginalPrice }}
                                        </span>
                                        <span class="vmodal-price-active">
                                            {{ selectedVariant ? formatCurrency(selectedVariant.price) : priceRangeLabel
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="vmodal-ui-divider"></div>

                                <!-- Color Options -->
                                <div class="vmodal-section" v-if="hasColors">
                                    <p class="vmodal-label">Màu sắc:</p>
                                    <div class="vmodal-options">
                                        <button v-for="color in uniqueColors" :key="color"
                                            class="vmodal-opt-btn color-opt"
                                            :class="{ active: selectedColor === color }" type="button"
                                            @click="onColorSelect(color)" :title="color">
                                            {{ color }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Size Options -->
                                <div class="vmodal-section" v-if="availableSizes.some(s => s.size)">
                                    <p class="vmodal-label">Kích thước:</p>
                                    <div class="vmodal-options">
                                        <button v-for="s in availableSizes" :key="s.size"
                                            class="vmodal-opt-btn size-opt"
                                            :class="{ active: selectedSize === s.size, 'out-of-stock': s.stock <= 0 }"
                                            :disabled="s.stock <= 0" type="button"
                                            @click="$emit('update:selected-size', s.size)">
                                            {{ s.size }}
                                            <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{
                                                s.stock }})</span>
                                            <span v-else-if="s.stock <= 0" class="vmodal-opt-stock">Hết</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Quantity Section -->
                                <div class="vmodal-section vmodal-qty-section">
                                    <p class="vmodal-label">Số lượng:</p>
                                    <div class="vmodal-qty-row">
                                        <div class="vmodal-qty">
                                            <button type="button" @click="$emit('decrease')"
                                                :disabled="!selectedVariant">−</button>
                                            <input type="text" inputmode="numeric" autocomplete="off" :value="quantity"
                                                :disabled="!selectedVariant" @input="onQuantityInput"
                                                @blur="onQuantityBlur" @keydown.enter.prevent="$event.target.blur()" />
                                            <button type="button" @click="$emit('increase')"
                                                :disabled="!selectedVariant">+</button>
                                        </div>
                                        <span class="vmodal-stock-info" v-if="selectedVariant">
                                            Còn lại: <strong>{{ selectedVariant.stock }}</strong> sản phẩm
                                        </span>
                                        <span class="vmodal-stock-info" v-else-if="variants.length > 0">
                                            Còn lại: <strong>{{variants.reduce((sum, v) => sum + v.stock, 0)
                                                }}</strong> sản phẩm
                                        </span>
                                    </div>
                                </div>

                                <!-- Selection Status Alert -->
                                <div class="vmodal-selected-info" v-if="selectedVariant">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                        stroke-width="2">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span>
                                        Đã chọn:
                                        <strong>{{ [selectedVariant.color, selectedVariant.size].filter(Boolean).join(' / ') || selectedVariant.variant_name }}</strong>
                                        —
                                        <span class="vmodal-sel-orig" v-if="displayOriginalPrice">{{ displayOriginalPrice }}</span>
                                        <span class="vmodal-sel-sale">{{ formatCurrency(selectedVariant.price) }}</span>
                                        <span v-if="selectedVariant.stock <= 5" class="vmodal-low-stock">(còn {{ selectedVariant.stock }})</span>
                                    </span>
                                </div>
                                <div class="vmodal-selected-info vmodal-unselected" v-else>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                                        stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="12" />
                                        <line x1="12" y1="16" x2="12.01" y2="16" />
                                    </svg>
                                    <span>Vui lòng chọn {{ hasColors ? 'màu sắc' : '' }}{{hasColors &&
                                        availableSizes.some(s=>s.size) ? ' và ' : '' }}{{availableSizes.some(s => s.size)
                                        ? 'kích thước' : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="vmodal-footer">
                        <button class="vmodal-btn-confirm" type="button"
                            :disabled="!selectedVariant || selectedVariant.stock <= 0 || confirming"
                            @click="$emit('confirm')">
                            <span v-if="confirming">Đang thêm vào giỏ...</span>
                            <span v-else-if="selectedVariant && selectedVariant.stock <= 0">Hết hàng</span>
                            <span v-else>Thêm vào giỏ hàng</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { getStorageUrl } from "@/utils/url";

const props = defineProps({
    show: { type: Boolean, default: false },
    productName: { type: String, default: '' },
    imageUrl: { type: String, default: '' },
    variants: { type: Array, default: () => [] },
    uniqueColors: { type: Array, default: () => [] },
    hasColors: { type: Boolean, default: false },
    availableSizes: { type: Array, default: () => [] },
    selectedVariant: { type: Object, default: null },
    selectedColor: { type: String, default: null },
    selectedSize: { type: [String, Number], default: null },
    quantity: { type: Number, default: 1 },
    confirming: { type: Boolean, default: false },
    originalPrice: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'select-color', 'update:selected-size', 'update:quantity', 'increase', 'decrease', 'confirm']);

const activeImg = ref(props.imageUrl || '');

// Thu thập toàn bộ ảnh độc nhất của sản phẩm & biến thể để đưa vào slider/gallery nhỏ
const modalImages = computed(() => {
    const list = [];
    if (props.imageUrl) {
        list.push(props.imageUrl);
    }
    props.variants.forEach(v => {
        if (v.image_url && v.image_url !== '0') {
            const url = v.image_url.startsWith('http') || v.image_url.startsWith('data:')
                ? v.image_url
                : getStorageUrl(v.image_url);
            if (url && !list.includes(url)) {
                list.push(url);
            }
        }
    });
    return list;
});

// Hiển thị khoảng giá trước khi chọn biến thể
const priceRangeLabel = computed(() => {
    if (!props.variants || props.variants.length === 0) return 'Liên hệ';
    const prices = props.variants.map(v => v.price).filter(Boolean);
    if (prices.length === 0) return 'Liên hệ';
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    if (min === max) return formatCurrency(min);
    return `${formatCurrency(min)} - ${formatCurrency(max)}`;
});

// Tính toán giá gốc (gạch ngang) nếu có giảm giá
const displayOriginalPrice = computed(() => {
    const findValidOrig = (candidates, salePrice) => {
        const minSale = Number(salePrice || 0);
        for (const val of candidates) {
            const num = Number(val);
            if (Number.isFinite(num) && num > minSale) {
                return num;
            }
        }
        return 0;
    };

    if (props.selectedVariant) {
        const salePrice = Number(props.selectedVariant.price || 0);
        const candidates = [
            props.selectedVariant.compare_at_price,
            props.selectedVariant.original_price,
            props.selectedVariant.originalPrice,
            props.selectedVariant.old_price,
            props.selectedVariant.max_price,
            props.originalPrice,
        ];
        const origPrice = findValidOrig(candidates, salePrice);
        if (origPrice > 0) {
            return formatCurrency(origPrice);
        }
        return '';
    }

    const minSalePrice = props.variants?.length
        ? Math.min(...props.variants.map(v => Number(v.price || 0)).filter(p => p > 0))
        : 0;

    const variantOrigs = props.variants
        ?.map(v => findValidOrig([v.compare_at_price, v.original_price, v.originalPrice, props.originalPrice], v.price))
        .filter(p => p > 0) || [];

    if (variantOrigs.length > 0) {
        const minOrig = Math.min(...variantOrigs);
        const maxOrig = Math.max(...variantOrigs);
        if (minOrig > minSalePrice) {
            if (minOrig === maxOrig) return formatCurrency(minOrig);
            return `${formatCurrency(minOrig)} - ${formatCurrency(maxOrig)}`;
        }
    }

    const baseOrig = findValidOrig([props.originalPrice], minSalePrice);
    if (baseOrig > 0) {
        return formatCurrency(baseOrig);
    }

    return '';
});

// Khi đổi màu sắc -> cập nhật ảnh xem trước sang variant màu đầu tiên tìm thấy
const onColorSelect = (color) => {
    emit('select-color', color);
    const firstVarWithImg = props.variants.find(v => v.color === color && v.image_url && v.image_url !== '0');
    if (firstVarWithImg) {
        activeImg.value = getStorageUrl(firstVarWithImg.image_url);
    }
};

watch(() => props.imageUrl, (newUrl) => {
    if (newUrl) {
        activeImg.value = newUrl;
    }
});

watch(() => props.show, (newShow) => {
    if (newShow) {
        activeImg.value = props.imageUrl;
    }
});

function normalizeQuantity(value) {
    const digitsOnly = String(value ?? '').replace(/[^0-9]/g, '').slice(0, 6);
    if (!digitsOnly) return null;
    const parsed = Number.parseInt(digitsOnly, 10);
    if (!Number.isSafeInteger(parsed)) return null;
    return parsed;
}

function onQuantityInput(event) {
    const sanitized = String(event.target.value || '').replace(/[^0-9]/g, '').slice(0, 6);
    event.target.value = sanitized;
    const nextQuantity = normalizeQuantity(sanitized);
    if (nextQuantity !== null) {
        emit('update:quantity', nextQuantity);
    }
}

function onQuantityBlur(event) {
    const nextQuantity = normalizeQuantity(event.target.value);
    const stock = props.selectedVariant?.stock || 999;
    let safeQuantity = (nextQuantity ?? props.quantity) || 1;
    safeQuantity = Math.max(1, Math.min(safeQuantity, stock, 999));
    event.target.value = safeQuantity;
    emit('update:quantity', safeQuantity);
}

function formatCurrency(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) return value || 'Liên hệ';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
}
</script>

<style scoped>
.vmodal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.vmodal-box {
    width: min(850px, 96vw);
    max-height: 90vh;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.vmodal-close-top {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 50%;
    background: rgba(241, 245, 249, 0.9);
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
}

.vmodal-close-top:hover {
    background: #e2e8f0;
    color: #0f172a;
    transform: rotate(90deg);
}

.vmodal-body-content {
    overflow-y: auto;
    flex: 1;
}

.vmodal-grid-layout {
    display: grid;
    grid-template-columns: 1.1fr 1.3fr;
    gap: 0;
    min-height: 400px;
}

/* Left side styling - Interactive Gallery */
.vmodal-gallery-side {
    background: #fafafa;
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #f1f5f9;
}

.vmodal-main-image-wrapper {
    width: 100%;
    aspect-ratio: 1 / 1;
    max-height: 280px;
    background: #ffffff;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    border: 1px solid #f1f5f9;
    overflow: hidden;
}

.vmodal-main-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.vmodal-gallery-thumbs {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 14px;
    width: 100%;
}

.vmodal-thumb-btn {
    width: 46px;
    height: 46px;
    border-radius: 6px;
    border: 1.5px solid #e2e8f0;
    padding: 2px;
    background: #ffffff;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.2s ease;
}

.vmodal-thumb-btn img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.vmodal-thumb-btn:hover {
    border-color: #cbd5e1;
}

.vmodal-thumb-btn.active {
    border-color: #E63B6F;
    box-shadow: 0 0 0 2px rgba(230, 59, 111, 0.12);
}

/* Right side styling - Selectors and Meta */
.vmodal-details-side {
    padding: 30px;
    display: flex;
    flex-direction: column;
}

.vmodal-product-meta {
    margin-bottom: 8px;
}

.vmodal-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    background: #fff0f3;
    color: #E63B6F;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}

.vmodal-product-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.vmodal-price-range {
    margin-top: 10px;
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.vmodal-price-original {
    font-size: 1.05rem;
    font-weight: 600;
    color: #94a3b8;
    text-decoration: line-through;
}

.vmodal-price-active {
    font-size: 1.5rem;
    font-weight: 900;
    color: #E63B6F;
}

.vmodal-sel-orig {
    text-decoration: line-through;
    color: #94a3b8;
    margin-right: 4px;
}

.vmodal-sel-sale {
    font-weight: 700;
    color: #E63B6F;
}

.vmodal-ui-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 18px 0;
}

.vmodal-section {
    margin-bottom: 20px;
}

.vmodal-label {
    margin: 0 0 10px;
    font-weight: 700;
    color: #475569;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.vmodal-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.vmodal-opt-btn {
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    border-radius: 12px;
    padding: 8px 16px;
    min-height: 42px;
    cursor: pointer;
    color: #334155;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.vmodal-opt-btn:hover:not(:disabled) {
    border-color: #E63B6F;
    color: #E63B6F;
    background: #fffafa;
}

.vmodal-opt-btn.active {
    border-color: #E63B6F;
    background: #fff1f5;
    color: #E63B6F;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.08);
}

.vmodal-opt-btn.out-of-stock {
    opacity: 0.4;
    cursor: not-allowed;
    text-decoration: line-through;
    background: #f8fafc;
}

.vmodal-opt-stock {
    font-size: 0.72rem;
    font-weight: 500;
    opacity: 0.75;
}



/* Quantity selector */
.vmodal-qty-section {
    margin-top: 10px;
}

.vmodal-qty-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.vmodal-qty {
    display: inline-flex;
    align-items: center;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.vmodal-qty button {
    width: 42px;
    height: 38px;
    border: 0;
    background: #ffffff;
    font-size: 1.2rem;
    cursor: pointer;
    color: #475569;
    font-weight: 600;
    transition: all 0.15s ease;
}

.vmodal-qty button:hover:not(:disabled) {
    background: #f1f5f9;
    color: #E63B6F;
}

.vmodal-qty button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.vmodal-qty input {
    width: 60px;
    height: 38px;
    border: 0;
    border-left: 1.5px solid #e2e8f0;
    border-right: 1.5px solid #e2e8f0;
    text-align: center;
    font-weight: 800;
    color: #0f172a;
    outline: none;
    font-size: 0.95rem;
}

.vmodal-qty input:focus {
    background: #fff5f8;
}

.vmodal-stock-info {
    color: #64748b;
    font-size: 0.9rem;
}

.vmodal-stock-info strong {
    color: #0f172a;
}

/* Status selection box */
.vmodal-selected-info {
    margin-top: 24px;
    padding: 12px 16px;
    border-radius: 12px;
    background: #fff0f3;
    border: 1px solid rgba(230, 59, 111, 0.15);
    color: #475569;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    animation: fadeIn 0.3s ease;
}

.vmodal-selected-info strong {
    color: #0f172a;
    font-weight: 700;
}

.vmodal-low-stock {
    color: #d97706;
    font-weight: 700;
}

.vmodal-unselected {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #94a3b8;
}

/* Footer Section */
.vmodal-footer {
    padding: 20px 30px 24px;
    border-top: 1px solid #f1f5f9;
    background: #ffffff;
}

.vmodal-btn-confirm {
    width: 100%;
    height: 50px;
    border: 0;
    border-radius: 12px;
    background: #E63B6F;
    color: #ffffff;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 10px 25px -5px rgba(230, 59, 111, 0.4);
}

.vmodal-btn-confirm:hover:not(:disabled) {
    background: #d82f65;
    transform: translateY(-1px);
    box-shadow: 0 12px 28px -4px rgba(230, 59, 111, 0.5);
}

.vmodal-btn-confirm:active:not(:disabled) {
    transform: translateY(0);
}

.vmodal-btn-confirm:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
    box-shadow: none;
}

/* Transitions */
.vmodal-enter-active,
.vmodal-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.vmodal-enter-from,
.vmodal-leave-to {
    opacity: 0;
}

.vmodal-enter-from .vmodal-box,
.vmodal-leave-to .vmodal-box {
    transform: scale(0.95) translateY(15px);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .vmodal-overlay {
        padding: 10px;
        align-items: flex-end;
    }

    .vmodal-box {
        width: 100%;
        max-height: 92vh;
        border-radius: 20px 20px 0 0;
    }

    .vmodal-grid-layout {
        grid-template-columns: 1fr;
    }

    .vmodal-gallery-side {
        padding: 20px;
        border-right: 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .vmodal-main-image-wrapper {
        max-height: 200px;
    }

    .vmodal-details-side {
        padding: 20px;
    }

    .vmodal-footer {
        padding: 16px 20px 20px;
    }
}
</style>
