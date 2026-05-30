<script setup>
import { computed } from 'vue';
import { useCartUpsell } from '@/composables/useCartUpsell';

const { state, progress, remaining, hasFreeship } = useCartUpsell();

const formatPrice = (val) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

// Width style cho thanh progress
const barStyle = computed(() => ({
    width: `${progress.value}%`,
}));
</script>

<template>
    <div class="freeship-bar-wrapper">
        <!-- Icon + Text -->
        <div class="freeship-header">
            <div class="freeship-icon-label">
                <span class="truck-icon">
                    <!-- Truck SVG -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13" rx="2"/>
                        <path d="M16 8h4l3 4v5h-7V8z"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/>
                        <circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                </span>
                <Transition name="fade-msg" mode="out-in">
                    <span v-if="hasFreeship" key="done" class="freeship-msg freeship-done">
                        <svg class="freeship-celebrate-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3L2 22l10.7-3.8"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="M22 2l-2.24.75a2.9 2.9 0 00-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="M22 13l-1.34-.75a2.9 2.9 0 00-3.12-1.96v0c-.86.1-1.63-.57-1.63-1.45V8.46c0-.86-.6-1.6-1.44-1.76L13 6.5"/></svg>
                        Chúc mừng! Bạn đã được <strong>Freeship</strong>!
                    </span>
                    <span v-else key="progress" class="freeship-msg">
                        Mua thêm
                        <strong class="freeship-amount">{{ formatPrice(remaining) }}</strong>
                        để được <strong>Freeship</strong>
                    </span>
                </Transition>
            </div>
            <span class="freeship-badge" :class="{ 'badge-done': hasFreeship }">
                {{ hasFreeship ? '✓ Freeship' : `${progress}%` }}
            </span>
        </div>

        <!-- Track -->
        <div class="freeship-track">
            <div class="freeship-fill" :style="barStyle">
                <!-- Shimmer animation -->
                <span class="fill-shimmer"></span>
            </div>
            <!-- Milestone marker -->
            <div
                class="freeship-milestone"
                :class="{ 'milestone-reached': hasFreeship }"
                title="Mốc Freeship"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
.freeship-bar-wrapper {
    background: #fff;
    border: 1px solid #e8ecf1;
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 12px rgba(230, 59, 111, 0.06);
}

/* ── Header row ── */
.freeship-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.freeship-icon-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.truck-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: linear-gradient(135deg, #fff3e0 0%, #e8f5e9 100%);
    border-radius: 50%;
    color: #f4811f;
    flex-shrink: 0;
}

.freeship-msg {
    font-size: 0.875rem;
    color: #475569;
    font-weight: 500;
}

.freeship-msg.freeship-done {
    color: #16a34a;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.freeship-celebrate-icon {
    flex-shrink: 0;
}

.freeship-amount {
    color: #ef5350;
    font-weight: 700;
}

.freeship-badge {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    letter-spacing: 0.3px;
    transition: all 0.4s ease;
}

.freeship-badge.badge-done {
    background: linear-gradient(90deg, #22c55e, #16a34a);
    color: #fff;
}

/* ── Progress track ── */
.freeship-track {
    position: relative;
    height: 10px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: visible;
}

.freeship-fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #FF6B35 0%, #F7C948 50%, #2ECC71 100%);
    background-size: 200% 100%;
    transition: width 0.65s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

/* Shimmer effect trên fill */
.fill-shimmer {
    position: absolute;
    top: 0;
    left: -60%;
    width: 60%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.45) 50%,
        transparent 100%
    );
    animation: shimmer 2s infinite;
    border-radius: 999px;
}

@keyframes shimmer {
    0%   { left: -60%; }
    100% { left: 120%; }
}

/* Milestone marker (bolt icon ở cuối track) */
.freeship-milestone {
    position: absolute;
    right: -5px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e2e8f0;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 8px;
    transition: all 0.4s ease;
    z-index: 2;
}

.freeship-milestone.milestone-reached {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-color: #16a34a;
    color: #fff;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
}

/* ── Fade transition cho text ── */
.fade-msg-enter-active,
.fade-msg-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-msg-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}
.fade-msg-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
