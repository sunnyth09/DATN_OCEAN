/**
 * useCartUpsell — Shared state composable cho Cart Upsell & Gamification
 *
 * Pattern: module-level reactive state (Vue 3) — tương đương Pinia store
 * nhưng không cần cài thêm package. State được tạo 1 lần ở module scope,
 * mọi component import cùng tham chiếu reactive object.
 */
import { reactive, computed } from 'vue';
import api from '@/axios';

// ─── Module-level State (shared across all imports) ───────────────────────────
const state = reactive({
    totalPrice: 0,
    freeshipThreshold: 500_000,
    suggestions: [],
    loadingSuggestions: false,
});

// ─── Computed (dùng computed() bên ngoài setup vẫn hoạt động với Vue 3) ──────
const progress = computed(() =>
    Math.min(100, Math.round((state.totalPrice / state.freeshipThreshold) * 100))
);

const remaining = computed(() =>
    Math.max(0, state.freeshipThreshold - state.totalPrice)
);

const hasFreeship = computed(() => state.totalPrice >= state.freeshipThreshold);

// ─── Actions ──────────────────────────────────────────────────────────────────

/**
 * Đồng bộ tổng tiền từ giỏ hàng vào shared state
 * Gọi từ Index.vue sau mỗi lần totalPrice thay đổi
 */
function setTotalPrice(val) {
    state.totalPrice = val || 0;
}

/**
 * Fetch danh sách gợi ý + freeship threshold từ API backend
 */
async function fetchUpsellData() {
    state.loadingSuggestions = true;
    try {
        const res = await api.get('/cart/upsell-suggestions');
        if (res.data.status === 'success') {
            state.freeshipThreshold = res.data.data.freeship_threshold ?? 500_000;
            state.suggestions       = res.data.data.suggestions       ?? [];
        }
    } catch (e) {
        // Không hiện lỗi — graceful degradation, giỏ hàng vẫn hoạt động bình thường
        console.warn('[useCartUpsell] fetchUpsellData error:', e?.response?.status);
        state.suggestions = [];
    } finally {
        state.loadingSuggestions = false;
    }
}

/**
 * Thêm nhanh 1 sản phẩm gợi ý vào giỏ hàng
 * @param {number} variantId
 * @returns {{ success: boolean, message: string }}
 */
async function quickAddToCart(variantId) {
    const res = await api.post('/cart/items', { variant_id: variantId, quantity: 1 });
    return res.data;
}

// ─── Public API of composable ─────────────────────────────────────────────────
export function useCartUpsell() {
    return {
        state,
        progress,
        remaining,
        hasFreeship,
        setTotalPrice,
        fetchUpsellData,
        quickAddToCart,
    };
}
