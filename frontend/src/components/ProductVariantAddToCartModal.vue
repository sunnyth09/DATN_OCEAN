<template>
    <Teleport to="body">
        <Transition name="vmodal">
            <div v-if="show" class="vmodal-overlay" @click.self="$emit('close')">
                <div class="vmodal-box" role="dialog" aria-modal="true" aria-labelledby="vmodal-title">
                    <div class="vmodal-header">
                        <div class="vmodal-product-snippet">
                            <img :src="imageUrl" :alt="productName" class="vmodal-product-img" />
                            <div class="vmodal-product-info">
                                <h3 id="vmodal-title" class="vmodal-title">Chọn phân loại hàng</h3>
                                <p class="vmodal-product-name" :title="productName">{{ productName }}</p>
                            </div>
                        </div>
                        <button class="vmodal-close" type="button" @click="$emit('close')" title="Đóng">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="vmodal-body-content">
                        <div class="vmodal-section" v-if="hasColors">
                            <p class="vmodal-label">Màu sắc:</p>
                            <div class="vmodal-options">
                                <button
                                    v-for="color in uniqueColors"
                                    :key="color"
                                    class="vmodal-opt-btn"
                                    :class="{ active: selectedColor === color }"
                                    type="button"
                                    @click="$emit('select-color', color)"
                                    :title="color"
                                >
                                    <span class="color-swatch-circle" :style="{ backgroundColor: getHexCode(color) }"></span>
                                    {{ color }}
                                </button>
                            </div>
                        </div>

                        <div class="vmodal-section" v-if="availableSizes.some(s => s.size)">
                            <p class="vmodal-label">Kích thước:</p>
                            <div class="vmodal-options">
                                <button
                                    v-for="s in availableSizes"
                                    :key="s.size"
                                    class="vmodal-opt-btn"
                                    :class="{ active: selectedSize === s.size, 'out-of-stock': s.stock <= 0 }"
                                    :disabled="s.stock <= 0"
                                    type="button"
                                    @click="$emit('update:selected-size', s.size)"
                                >
                                    {{ s.size }}
                                    <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{ s.stock }})</span>
                                    <span v-else-if="s.stock <= 0" class="vmodal-opt-stock">Hết</span>
                                </button>
                            </div>
                        </div>

                        <div class="vmodal-selected-info" v-if="selectedVariant">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>
                                Đã chọn:
                                <strong>{{ [selectedVariant.color, selectedVariant.size].filter(Boolean).join(' / ') || selectedVariant.variant_name }}</strong>
                                — {{ formatCurrency(selectedVariant.price) }}
                                <span v-if="selectedVariant.stock <= 5" class="vmodal-low-stock">(còn {{ selectedVariant.stock }})</span>
                            </span>
                        </div>
                        <div class="vmodal-selected-info vmodal-unselected" v-else>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>Vui lòng chọn {{ hasColors ? 'màu sắc' : '' }}{{ hasColors && availableSizes.some(s=>s.size) ? ' và ' : '' }}{{ availableSizes.some(s=>s.size) ? 'kích thước' : '' }}</span>
                        </div>

                        <div class="vmodal-section vmodal-qty-section">
                            <p class="vmodal-label">Số lượng:</p>
                            <div class="vmodal-qty-row">
                                <div class="vmodal-qty">
                                    <button type="button" @click="$emit('decrease')" :disabled="!selectedVariant">−</button>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        :value="quantity"
                                        :disabled="!selectedVariant"
                                        @input="onQuantityInput"
                                        @blur="onQuantityBlur"
                                        @keydown.enter.prevent="$event.target.blur()"
                                    />
                                    <button type="button" @click="$emit('increase')" :disabled="!selectedVariant">+</button>
                                </div>
                                <span class="vmodal-stock-info" v-if="selectedVariant">
                                    Còn lại: <strong>{{ selectedVariant.stock }}</strong> sản phẩm
                                </span>
                                <span class="vmodal-stock-info" v-else-if="variants.length > 0">
                                    Còn lại: <strong>{{ variants.reduce((sum, v) => sum + v.stock, 0) }}</strong> sản phẩm
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="vmodal-footer">
                        <button
                            class="vmodal-btn-confirm"
                            type="button"
                            :disabled="!selectedVariant || selectedVariant.stock <= 0 || confirming"
                            @click="$emit('confirm')"
                        >
                            <span v-if="confirming">Đang thêm...</span>
                            <span v-else-if="selectedVariant && selectedVariant.stock <= 0">Hết hàng</span>
                            <span v-else>Thêm vào giỏ</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
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
});

const emit = defineEmits(['close', 'select-color', 'update:selected-size', 'update:quantity', 'increase', 'decrease', 'confirm']);

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

function getHexCode(colorName) {
    if (!colorName) return '#ccc';
    const colorMap = {
        'đỏ': '#ef4444', red: '#ef4444',
        'xanh dương': '#3b82f6', xanh: '#3b82f6', blue: '#3b82f6',
        'xanh lá': '#22c55e', green: '#22c55e',
        'vàng': '#eab308', yellow: '#eab308',
        'đen': '#171717', black: '#171717',
        'trắng': '#ffffff', white: '#ffffff',
        'hồng': '#ec4899', pink: '#ec4899',
        'tím': '#a855f7', purple: '#a855f7',
        'nâu': '#78350f', brown: '#78350f',
        'cam': '#f97316', orange: '#f97316',
        'xám': '#6b7280', grey: '#6b7280', gray: '#6b7280',
    };
    return colorMap[colorName.toString().toLowerCase().trim()] || '#e2e8f0';
}
</script>

<style scoped>
.vmodal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.vmodal-box {
    width: min(620px, 96vw);
    max-height: 88vh;
    background: #ffffff;
    border-radius: 22px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.vmodal-header {
    padding: 22px 26px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    border-bottom: 1px solid #edf2f7;
}

.vmodal-product-snippet {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.vmodal-product-img {
    width: 68px;
    height: 68px;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    flex-shrink: 0;
}

.vmodal-product-info { min-width: 0; }

.vmodal-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #25364d;
}

.vmodal-product-name {
    margin: 5px 0 0;
    color: #627d98;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 390px;
}

.vmodal-close {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 999px;
    background: #f8fafc;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vmodal-close:hover { background: #f1f5f9; color: #25364d; }

.vmodal-body-content {
    overflow-y: auto;
    padding-bottom: 18px;
}

.vmodal-section { padding: 18px 26px 0; }

.vmodal-label {
    margin: 0 0 12px;
    font-weight: 800;
    color: #334e68;
    text-transform: uppercase;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
}

.vmodal-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.vmodal-opt-btn {
    border: 1px solid #d9e2ec;
    background: #fff;
    border-radius: 10px;
    padding: 10px 14px;
    min-height: 42px;
    cursor: pointer;
    color: #486581;
    font-weight: 700;
    transition: all 0.18s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.vmodal-opt-btn:hover:not(:disabled) { border-color: #E63B6F; color: #E63B6F; }
.vmodal-opt-btn.active {
    border-color: #E63B6F;
    background: #fff1f5;
    color: #E63B6F;
}
.vmodal-opt-btn.out-of-stock {
    opacity: 0.45;
    cursor: not-allowed;
    text-decoration: line-through;
}
.vmodal-opt-stock { font-size: 0.72rem; font-weight: 500; opacity: 0.85; }

.vmodal-selected-info {
    margin: 18px 26px 0;
    padding: 13px 14px;
    border-radius: 12px;
    background: #fff5f8;
    border: 1px solid #fbcfe8;
    color: #334e68;
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 0.9rem;
}
.vmodal-selected-info strong { font-weight: 800; }
.vmodal-low-stock { color: #f59e0b; font-weight: 700; }
.vmodal-unselected {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.vmodal-qty-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.vmodal-qty {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d9e2ec;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.vmodal-qty button {
    width: 46px;
    height: 44px;
    border: 0;
    background: #fff;
    font-size: 1.25rem;
    cursor: pointer;
    color: #486581;
}
.vmodal-qty button:hover:not(:disabled) { background: #fff5f8; color: #E63B6F; }
.vmodal-qty button:disabled { opacity: 0.35; cursor: not-allowed; }

.vmodal-qty input {
    width: 76px;
    height: 44px;
    border: 0;
    border-left: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
    text-align: center;
    font-weight: 800;
    color: #25364d;
    outline: none;
    font-size: 1rem;
}
.vmodal-qty input:focus { background: #fff5f8; box-shadow: inset 0 0 0 1px #E63B6F; }
.vmodal-qty input:disabled { background: #f8fafc; color: #94a3b8; }

.vmodal-stock-info { color: #627d98; font-size: 0.92rem; }
.vmodal-stock-info strong { color: #E63B6F; }

.color-swatch-circle {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1px solid #cbd5e1;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.35);
}

.vmodal-footer {
    padding: 18px 26px 22px;
    border-top: 1px solid #edf2f7;
    background: #fff;
}

.vmodal-btn-confirm {
    width: 100%;
    height: 52px;
    border: 0;
    border-radius: 12px;
    background: #E63B6F;
    color: #fff;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 12px 28px rgba(230, 59, 111, 0.24);
}
.vmodal-btn-confirm:hover:not(:disabled) { background: #d72f61; transform: translateY(-1px); }
.vmodal-btn-confirm:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
    box-shadow: none;
}

.vmodal-enter-active, .vmodal-leave-active { transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.vmodal-enter-from, .vmodal-leave-to { opacity: 0; }
.vmodal-enter-from .vmodal-box, .vmodal-leave-to .vmodal-box { transform: scale(0.94) translateY(20px); }

@media (max-width: 640px) {
    .vmodal-overlay { padding: 10px; align-items: flex-end; }
    .vmodal-box { width: 100%; max-height: 92vh; border-radius: 20px 20px 0 0; }
    .vmodal-header, .vmodal-section, .vmodal-footer { padding-left: 18px; padding-right: 18px; }
    .vmodal-product-name { max-width: 260px; }
}
</style>
