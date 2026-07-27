<template>
    <teleport to="body">
        <transition name="checkout-address-modal-fade">
            <div v-if="show" class="checkout-address-modal-overlay" @click.self="close">
                <div class="checkout-address-modal">
                    <div class="modal-header">
                        <div>
                            <h3>Chọn địa chỉ giao hàng</h3>
                            <p>Chọn địa chỉ bạn muốn dùng cho đơn hàng này</p>
                        </div>
                        <button class="modal-close" type="button" @click="close">×</button>
                    </div>

                    <div class="modal-body">
                        <label
                            v-for="address in addresses"
                            :key="address.address_id"
                            class="modal-address-card"
                            :class="{ 'is-selected': tempSelectedId === address.address_id }"
                        >
                            <input v-model="tempSelectedId" type="radio" :value="address.address_id" class="hidden-radio" />
                            <span class="radio-indicator"><span></span></span>
                            <span class="address-content">
                                <span class="address-header">
                                    <strong>{{ address.recipient_name }}</strong>
                                    <span>{{ address.phone }}</span>
                                    <em v-if="address.is_default">MẶC ĐỊNH</em>
                                </span>
                                <span class="address-line">{{ formatFullAddress(address) }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button class="btn-cancel" type="button" @click="close">Hủy</button>
                        <button class="btn-confirm" type="button" :disabled="!tempSelectedId" @click="confirm">
                            Giao đến địa chỉ này
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    addresses: { type: Array, default: () => [] },
    selectedAddressId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'confirm']);
const tempSelectedId = ref(null);

watch(() => props.show, (show) => {
    if (show) {
        tempSelectedId.value = props.selectedAddressId;
    }
});

function close() {
    emit('close');
}

function confirm() {
    if (tempSelectedId.value) {
        emit('confirm', tempSelectedId.value);
    }
}

function formatFullAddress(address) {
    return [address.address_line, address.ward, address.district, address.province]
        .filter(Boolean)
        .join(', ') || 'Chưa có thông tin địa chỉ cụ thể';
}
</script>

<style scoped>
.checkout-address-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(17, 24, 39, 0.55);
    backdrop-filter: blur(4px);
}

.checkout-address-modal {
    width: min(720px, 100%);
    max-height: min(86vh, 760px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
}

.modal-header,
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid #eef2f7;
}

.modal-footer {
    border-top: 1px solid #eef2f7;
    border-bottom: 0;
    justify-content: flex-end;
}

.modal-header h3 {
    margin: 0;
    color: #2f3437;
    font-size: 1.25rem;
    font-weight: 800;
}

.modal-header p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 0.9rem;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 50%;
    background: #f3f4f6;
    color: #374151;
    font-size: 26px;
    line-height: 1;
    cursor: pointer;
}

.modal-body {
    padding: 18px 24px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.modal-address-card {
    display: flex;
    gap: 14px;
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-address-card:hover,
.modal-address-card.is-selected {
    border-color: #ec407a;
    background: #fff5f8;
}

.hidden-radio {
    display: none;
}

.radio-indicator {
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
    margin-top: 2px;
    border: 1.5px solid #cbd5e1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-address-card.is-selected .radio-indicator {
    border-color: #ec407a;
}

.radio-indicator span {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: transparent;
}

.modal-address-card.is-selected .radio-indicator span {
    background: #ec407a;
}

.address-content {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
}

.address-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    color: #2f3437;
}

.address-header strong {
    font-weight: 800;
}

.address-header span {
    color: #64748b;
}

.address-header em {
    padding: 3px 8px;
    border-radius: 999px;
    background: #fff1f5;
    color: #ec407a;
    font-size: 0.72rem;
    font-style: normal;
    font-weight: 800;
}

.address-line {
    color: #64748b;
    line-height: 1.5;
}

.btn-cancel,
.btn-confirm {
    height: 42px;
    border-radius: 12px;
    padding: 0 18px;
    font-weight: 800;
    cursor: pointer;
}

.btn-cancel {
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #475569;
}

.btn-confirm {
    border: 0;
    background: #ec407a;
    color: #fff;
}

.btn-confirm:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.checkout-address-modal-fade-enter-active,
.checkout-address-modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.checkout-address-modal-fade-enter-from,
.checkout-address-modal-fade-leave-to {
    opacity: 0;
}

@media (max-width: 640px) {
    .checkout-address-modal-overlay {
        padding: 10px;
        align-items: flex-end;
    }

    .checkout-address-modal {
        max-height: 90vh;
        border-radius: 18px 18px 0 0;
    }

    .modal-header,
    .modal-footer,
    .modal-body {
        padding-left: 16px;
        padding-right: 16px;
    }
}
</style>
