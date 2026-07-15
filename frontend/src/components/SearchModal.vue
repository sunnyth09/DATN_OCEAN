<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { catalogService, extractCollection } from '@/services/catalogService';
import { getStorageUrl } from '@/utils/url';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const router = useRouter();
const searchQuery = ref('');
const results = ref([]);
const isLoading = ref(false);
const hasSearched = ref(false);

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f7ff"/><circle cx="100" cy="80" r="40" fill="#bfdbfe"/><path d="M60,160 Q100,120 140,160" stroke="#93c5fd" stroke-width="8" fill="none" stroke-linecap="round"/></svg>`);

const getImageUrl = (path) => {
    if (!path || path === '0' || path === '') return defaultSvg;
    return getStorageUrl(path);
};

const formatPrice = (price) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);

// Debounce search
let debounceTimer = null;
watch(searchQuery, (val) => {
    clearTimeout(debounceTimer);
    if (!val.trim()) {
        results.value = [];
        hasSearched.value = false;
        return;
    }
    isLoading.value = true;
    debounceTimer = setTimeout(() => doSearch(val.trim()), 300);
});

const doSearch = async (q) => {
    try {
        const res = await catalogService.searchProducts(q, { limit: 6 });
        results.value = extractCollection(res);
        hasSearched.value = true;
    } catch (e) {
        results.value = [];
        hasSearched.value = true;
    } finally {
        isLoading.value = false;
    }
};

const close = () => {
    emit('update:modelValue', false);
    searchQuery.value = '';
    results.value = [];
    hasSearched.value = false;
};

// Focus input khi modal mở
watch(() => props.modelValue, (val) => {
    if (val) {
        setTimeout(() => {
            document.getElementById('searchModalInput')?.focus();
        }, 100);
    }
});

// Phím ESC đóng modal
const onKeydown = (e) => {
    if (e.key === 'Escape') close();
    if (e.key === 'Enter' && searchQuery.value.trim()) viewAll();
};
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    clearTimeout(debounceTimer);
});

const goToProduct = (slug) => {
    close();
    router.push({ name: 'product-detail', params: { id: slug } });
};

const viewAll = () => {
    if (!searchQuery.value.trim()) return;
    const q = searchQuery.value.trim();
    close();
    router.push({ path: '/product', query: { search: q } });
};

const tipsKeywords = ['Vợt cầu lông', 'Giày đá bóng', 'Pickleball', 'Áo thể thao', 'Phụ kiện'];
const clickTip = (kw) => {
    searchQuery.value = kw;
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div v-if="modelValue" class="search-backdrop" @click.self="close" role="dialog" aria-modal="true">
                <div class="search-dialog">

                    <!-- ── Header ─────────────────────────────────── -->
                    <div class="search-header">
                        <svg class="header-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <span class="header-title">Tìm kiếm sản phẩm</span>
                        <button class="close-btn" @click="close" title="Đóng (ESC)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>

                    <!-- ── Search Input ───────────────────────────── -->
                    <div class="search-input-wrap">
                        <div class="input-row">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input
                                id="searchModalInput"
                                v-model="searchQuery"
                                type="text"
                                class="search-input"
                                placeholder="Nhập tên sản phẩm, thương hiệu..."
                                autocomplete="off"
                                spellcheck="false"
                            />
                            <button v-if="searchQuery" class="clear-btn" @click="searchQuery = ''" title="Xóa">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                            <div v-if="isLoading" class="spinner"></div>
                        </div>
                    </div>

                    <!-- ── Results Body ───────────────────────────── -->
                    <div class="search-body">

                        <!-- Có kết quả -->
                        <template v-if="hasSearched && results.length > 0">
                            <div class="results-header">
                                <span class="results-label">Kết quả tìm kiếm</span>
                                <button class="view-all-btn" @click="viewAll">
                                    Xem tất cả
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5">
                                        <polyline points="9 18 15 12 9 6" />
                                    </svg>
                                </button>
                            </div>
                            <ul class="hits-list">
                                <li v-for="item in results" :key="item.product_id"
                                    class="hit-item" @click="goToProduct(item.slug)">
                                    <div class="hit-img-wrap">
                                        <img
                                            :src="getImageUrl(item.thumbnail_url || item.mainImage?.image_url)"
                                            :alt="item.name"
                                            class="hit-img"
                                            loading="lazy"
                                            @error="e => e.target.src = defaultSvg"
                                        />
                                    </div>
                                    <div class="hit-info">
                                        <div class="hit-name">{{ item.name }}</div>
                                        <div class="hit-cat" v-if="item.category?.name">
                                            {{ item.category.name }}
                                        </div>
                                        <div class="hit-price">
                                            {{ formatPrice(item.min_price || item.lowestPriceVariant?.price) }}
                                            <span v-if="item.is_featured" class="hit-badge">Hot</span>
                                        </div>
                                    </div>
                                    <svg class="hit-arrow" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6" />
                                    </svg>
                                </li>
                            </ul>
                        </template>

                        <!-- Không có kết quả -->
                        <div v-else-if="hasSearched && results.length === 0" class="empty-state">
                            <div class="empty-icon">🔍</div>
                            <p class="empty-title">Không tìm thấy kết quả</p>
                            <p class="empty-sub">Hãy thử từ khoá khác hoặc kiểm tra chính tả</p>
                        </div>

                        <!-- Chưa tìm (mặc định) -->
                        <div v-else class="placeholder-state">
                            <div class="placeholder-icon">🌊</div>
                            <p class="placeholder-text">Gõ để bắt đầu tìm kiếm</p>
                            <div class="tips-row">
                                <button
                                    v-for="kw in tipsKeywords" :key="kw"
                                    class="tip-chip"
                                    @click="clickTip(kw)"
                                >
                                    {{ kw }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Footer ─────────────────────────────────── -->
                    <div class="search-footer">
                        <span class="footer-hint">
                            <kbd>ESC</kbd> đóng &nbsp;·&nbsp; <kbd>↵</kbd> xem tất cả
                        </span>
                        <span class="footer-brand">Tìm nhanh</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Backdrop ─────────────────────────────────────────────────── */
.search-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(5, 20, 45, 0.55);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 72px;
}

/* ── Dialog ───────────────────────────────────────────────────── */
.search-dialog {
    width: 100%;
    max-width: 640px;
    background: var(--card-bg);
    border-radius: 20px;
    box-shadow:
        0 24px 60px rgba(2, 40, 90, 0.18),
        0 4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: calc(100vh - 110px);
}

/* ── Header ───────────────────────────────────────────────────── */
.search-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 20px 14px;
    border-bottom: 1px solid #eef2f7;
    flex-shrink: 0;
}
.header-icon { color: var(--primary); flex-shrink: 0; }
.header-title {
    flex: 1;
    font-size: 0.97rem;
    font-weight: 700;
    color: #102a43;
    letter-spacing: -0.01em;
}
.close-btn {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #627d98;
    transition: background .15s, color .15s;
    flex-shrink: 0;
}
.close-btn:hover { background: #fee2e2; color: #dc2626; }

/* ── Input ────────────────────────────────────────────────────── */
.search-input-wrap {
    padding: 14px 20px 8px;
    flex-shrink: 0;
}
.input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border: 2px solid #d1dce8;
    border-radius: 14px;
    background: #f8fafc;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.input-row:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(230, 59, 111, 0.1);
    background: var(--card-bg);
}
.input-icon { color: #94a3b8; flex-shrink: 0; transition: color .2s; }
.input-row:focus-within .input-icon { color: var(--primary); }
.search-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: 1rem;
    font-family: inherit;
    color: #102a43;
    line-height: 1.4;
}
.search-input::placeholder { color: #94a3b8; }
.clear-btn {
    background: none; border: none; cursor: pointer;
    color: #94a3b8; display: flex; align-items: center;
    padding: 2px; border-radius: 50%;
    transition: color .15s, background .15s;
}
.clear-btn:hover { color: #dc2626; background: #fee2e2; }

/* Spinner */
.spinner {
    width: 16px; height: 16px;
    border: 2px solid #e2e8f0;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin .6s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Body ─────────────────────────────────────────────────────── */
.search-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 20px 12px;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.results-label {
    font-size: 0.73rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #8fa3b8;
}
.view-all-btn {
    display: flex; align-items: center; gap: 4px;
    background: none; border: none; cursor: pointer;
    color: var(--primary); font-size: 0.82rem; font-weight: 700;
    padding: 0;
    transition: opacity .15s;
}
.view-all-btn:hover { opacity: .7; }

/* ── Hits ─────────────────────────────────────────────────────── */
.hits-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }

.hit-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 12px;
    border-radius: 12px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s, transform .1s;
}
.hit-item:hover {
    background: #f0f7ff;
    border-color: #bfdbfe;
    transform: translateX(3px);
}
.hit-img-wrap {
    width: 58px; height: 58px;
    border-radius: 10px; overflow: hidden;
    flex-shrink: 0; background: #f4f9f9;
    border: 1px solid #e8ecf1;
}
.hit-img { width: 100%; height: 100%; object-fit: cover; }
.hit-info { flex: 1; min-width: 0; }
.hit-name {
    font-size: 0.92rem; font-weight: 600; color: #102a43;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-bottom: 2px;
}
.hit-cat {
    font-size: 0.76rem; color: #94a3b8; margin-bottom: 4px;
}
.hit-price {
    font-size: 0.85rem; font-weight: 700; color: var(--primary);
    display: flex; align-items: center; gap: 6px;
}
.hit-badge {
    background: linear-gradient(135deg, #f97316, #ef4444);
    color: #fff; font-size: 0.65rem; font-weight: 800;
    padding: 1px 7px; border-radius: 999px;
}
.hit-arrow { color: #c8d5e0; flex-shrink: 0; transition: color .15s; }
.hit-item:hover .hit-arrow { color: var(--primary); }

/* ── Empty / Placeholder ──────────────────────────────────────── */
.empty-state,
.placeholder-state {
    text-align: center;
    padding: 32px 16px 24px;
}
.empty-icon, .placeholder-icon { font-size: 2.4rem; margin-bottom: 10px; }
.empty-title { font-size: 0.95rem; font-weight: 700; color: #334155; margin-bottom: 4px; }
.empty-sub { font-size: 0.82rem; color: #94a3b8; }
.placeholder-text { font-size: 0.9rem; color: #8fa3b8; margin-bottom: 14px; }

.tips-row { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.tip-chip {
    background: #f0f7ff;
    border: 1px solid #bfdbfe;
    color: #0369a1;
    border-radius: 999px;
    padding: 5px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s, border-color .15s;
}
.tip-chip:hover { background: #dbeafe; border-color: #93c5fd; }

/* ── Footer ───────────────────────────────────────────────────── */
.search-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    border-top: 1px solid #eef2f7;
    background: #fafbfd;
    flex-shrink: 0;
}
.footer-hint { font-size: 0.76rem; color: #94a3b8; }
.footer-hint kbd {
    background: #e8ecf1; border: 1px solid #c8d3df;
    border-radius: 4px; padding: 1px 5px;
    font-size: 0.73rem; font-family: inherit; color: #627d98;
}
.footer-brand { font-size: 0.74rem; color: #b0bac9; font-weight: 700; }

/* ── Transition ───────────────────────────────────────────────── */
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity .2s ease;
}
.modal-fade-enter-active .search-dialog,
.modal-fade-leave-active .search-dialog {
    transition: transform .22s cubic-bezier(.4,0,.2,1), opacity .2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .search-dialog,
.modal-fade-leave-to .search-dialog {
    transform: translateY(-18px) scale(0.98);
    opacity: 0;
}

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 680px) {
    .search-backdrop { padding-top: 0; align-items: flex-start; }
    .search-dialog { max-width: 100%; border-radius: 0 0 20px 20px; }
}
</style>
