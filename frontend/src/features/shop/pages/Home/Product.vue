<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import ProductCard from "@/components/ProductCard.vue";
import ProductSkeleton from "@/components/ProductSkeleton.vue";
import { catalogService } from "@/services/catalogService";
import { useCatalogStore } from "@/stores/catalog";
import { getStorageUrl } from '@/utils/url';

const router = useRouter();
const route = useRoute();
const catalogStore = useCatalogStore();
const Products = ref([]);
const Categories = ref([]);
const Brands = ref([]);
const isSearching = ref(true);
const totalProducts = ref(0);

// ── Filter state ──
const selectedCategories = ref([]);     // checkbox array
const selectedBrands = ref([]);         // checkbox array
const priceMin = ref(0);
const priceMax = ref(10000000);
const displayPriceMax = ref(10000000);
watch(priceMax, (newVal) => { displayPriceMax.value = newVal; });

const sortBy = ref("newest");
const searchQuery = ref("");

const currentPage = ref(1);
const totalPages = ref(1);
const perPage = 12;
let isInitializing = true;
let fetchTimer = null;
let productRequestController = null;
let latestProductRequest = 0;

const getImageUrl = (path) => {
    if (!path || path === '0') return '';
    return getStorageUrl(path);
};

// ── Pagination ──
const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        const pages = [];
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }
    if (current <= 3) return [1, 2, 3, '...', total];
    if (current >= total - 2) return [1, '...', total - 2, total - 1, total];
    return [1, '...', current - 1, current, current + 1, '...', total];
});

// ── Fetch products ──
const fetchProducts = async () => {
    const requestId = ++latestProductRequest;
    productRequestController?.abort();
    productRequestController = new AbortController();

    try {
        isSearching.value = true;
        const params = { limit: perPage, page: currentPage.value };

        if (selectedCategories.value.length > 0) {
            params.category_ids = selectedCategories.value.join(',');
        }
        if (selectedBrands.value.length > 0) {
            params.brand_ids = selectedBrands.value.join(',');
        }
        if (priceMax.value !== undefined && priceMax.value !== null) {
            params.max_price = priceMax.value;
        }
        
        if (sortBy.value) params.sort_by = sortBy.value;
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();

        const response = await catalogService.listProducts(params, {
            signal: productRequestController.signal,
        });
        if (requestId !== latestProductRequest) return;

        const rawData = response.data.data || response.data;

        Products.value = (Array.isArray(rawData) ? rawData : rawData.data || []).map((item) => {
            const lowest = item.lowest_price_variant || item.lowestPriceVariant || null;
            const currentPrice = lowest?.effective_price ?? (item.min_price || 0);
            let originalPrice = null;
            if (lowest?.is_on_sale) originalPrice = lowest.price;
            else if (lowest?.compare_at_price > lowest?.price) originalPrice = lowest.compare_at_price;
            let maxDiscount = lowest?.discount_percent || 0;
            if (item.variants?.length) {
                maxDiscount = Math.max(...item.variants.map(v => v.discount_percent || 0), maxDiscount);
            }
            return {
                id: item.product_id, name: item.name,
                price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(currentPrice),
                min_price: currentPrice,
                originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
                original_price: originalPrice,
                discount_percent: maxDiscount,
                is_on_sale: lowest?.is_on_sale || false,
                image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
                badge: item.is_featured ? "Hot" : (maxDiscount > 0 ? "Sale" : null),
                slug: item.slug,
                category_id: item.category_id,
                category_name: item.category?.name || '',
                // Truyền variants_sum_stock để ProductCard hiển thị badge Hết hàng / Sắp hết hàng
                variants_sum_stock: item.variants_sum_stock ?? null,
                variants: item.variants ?? [],
            };
        });
        totalPages.value = response.data.total_pages || Math.ceil((response.data.total || Products.value.length) / perPage) || 1;
        totalProducts.value = response.data.total || Products.value.length;
    } catch (error) {
        if (error.code === 'ERR_CANCELED') return;
        console.error("Error fetching products:", error);
    } finally {
        if (requestId === latestProductRequest) {
            isSearching.value = false;
        }
    }
};

const scheduleFetchProducts = () => {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(fetchProducts, 100);
};

// ── Fetch categories ──
const fetchCategories = async () => {
    try {
        Categories.value = await catalogStore.fetchCategories();
    } catch (error) {
        console.error("Error fetching categories:", error);
    }
};

// ── Fetch brands ──
const fetchBrands = async () => {
    try {
        const response = await catalogService.listBrands();
        Brands.value = response.data.data || response.data || [];
    } catch (e) {
        // Brands endpoint might not exist, fail silently
        Brands.value = [];
    }
};

// ── Category checkbox toggle ──
const toggleCategoryFilter = (catId) => {
    const idx = selectedCategories.value.indexOf(catId);
    if (idx > -1) selectedCategories.value.splice(idx, 1);
    else selectedCategories.value.push(catId);
};

// ── Brand checkbox toggle ──
const toggleBrandFilter = (brandId) => {
    const idx = selectedBrands.value.indexOf(brandId);
    if (idx > -1) selectedBrands.value.splice(idx, 1);
    else selectedBrands.value.push(brandId);
};

// ── Price slider formatting ──
const formatPrice = (val) => {
    return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
};

// ── Watchers ──
let isResettingPage = false;

watch([selectedCategories, selectedBrands, sortBy, priceMax], () => {
    if (isInitializing) return;
    if (currentPage.value !== 1) {
        isResettingPage = true;
        currentPage.value = 1;
    }
    scheduleFetchProducts();
    // Sync URL
    const newQuery = { ...route.query };
    if (selectedCategories.value.length > 0) newQuery.category = selectedCategories.value.join(',');
    else delete newQuery.category;
    if (currentPage.value === 1) delete newQuery.page;
    router.replace({ query: newQuery }).catch(() => {});
}, { deep: true });

watch(() => route.query.search, (val) => {
    if (isInitializing) return;
    searchQuery.value = val || '';
    currentPage.value = 1;
    scheduleFetchProducts();
});

watch(() => route.query.q, (val) => {
    if (isInitializing) return;
    if (val) { searchQuery.value = val; currentPage.value = 1; scheduleFetchProducts(); }
});

watch(() => route.query.category, (val) => {
    if (val) {
        const catId = parseInt(val);
        if (!selectedCategories.value.includes(catId)) {
            selectedCategories.value = [catId];
        }
    } else {
        selectedCategories.value = [];
    }
});

const clearSearch = () => {
    searchQuery.value = '';
    router.replace({ path: '/product', query: {} });
};

const goToPage = (page) => {
    if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

watch(currentPage, () => {
    if (isInitializing) return;
    if (isResettingPage) { isResettingPage = false; return; }
    scheduleFetchProducts();
    router.replace({ query: { ...route.query, page: currentPage.value } }).catch(() => {});
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

onMounted(async () => {
    const pageFromUrl = parseInt(route.query.page);
    if (pageFromUrl && pageFromUrl > 0) currentPage.value = pageFromUrl;
    if (route.query.search) searchQuery.value = route.query.search;
    else if (route.query.q) searchQuery.value = route.query.q;

    await Promise.all([fetchCategories(), fetchBrands()]);

    const categoryParam = route.query.category;
    if (categoryParam) {
        if (typeof categoryParam === 'string' && categoryParam.includes(',')) {
            selectedCategories.value = categoryParam.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
        } else {
            // Flatten categories to search for slug or single ID
            const flatCategories = Categories.value.reduce((acc, cat) => {
                acc.push(cat);
                if (cat.children) acc.push(...cat.children);
                return acc;
            }, []);
            
            const cat = flatCategories.find(c => c.category_id == categoryParam || c.slug === categoryParam);
            if (cat) selectedCategories.value = [cat.category_id];
        }
    }

    isInitializing = false;
    await fetchProducts();

    // === Affiliate: ghi nhận referral code từ URL ===
    const refCode = route.query.ref;
    if (refCode) {
        localStorage.setItem('affiliate_ref', refCode);
        localStorage.setItem('affiliate_ref_expiry', Date.now() + 30 * 24 * 60 * 60 * 1000);
        // Fire-and-forget track click
        const { default: api } = await import('@/axios');
        api.post('/affiliate/track-click', { referral_code: refCode }).catch(() => {});
    }
});

onUnmounted(() => {
    clearTimeout(fetchTimer);
    productRequestController?.abort();
});
</script>

<template>
    <div class="product-page">
        <!-- ══ MAIN CONTENT ══ -->
        <div class="product-container">
            <!-- ══ HERO BANNER ══ -->
            <section class="page-hero">
                <h1>Tất Cả Sản Phẩm</h1>
                <p class="hero-sub">Nâng tầm cuộc chơi với trang thiết bị chuyên nghiệp</p>
            </section>
            <!-- Info bar -->
            <div class="product-toolbar">
                <div class="toolbar-left">
                    <span v-if="searchQuery" class="search-tag">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        "{{ searchQuery }}"
                        <button class="search-tag-close" @click="clearSearch">&times;</button>
                    </span>
                    <span class="toolbar-count">
                        Hiển thị <strong>{{ Products.length }}</strong> trong <strong>{{ totalProducts }}</strong> sản phẩm
                    </span>
                </div>
                <div class="toolbar-right">
                    <label for="sortSelect">Sắp xếp:</label>
                    <select id="sortSelect" v-model="sortBy" class="sort-select">
                        <option value="newest">Mới nhất</option>
                        <option value="oldest">Cũ nhất</option>
                        <option value="price-asc">Giá: Thấp → Cao</option>
                        <option value="price-desc">Giá: Cao → Thấp</option>
                    </select>
                </div>
            </div>

            <div class="product-layout">
                <!-- ══ LEFT SIDEBAR ══ -->
                <aside class="product-sidebar">
                    <!-- Danh mục -->
                    <div class="filter-group">
                        <h3 class="filter-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="9" y2="18"/></svg>
                            DANH MỤC
                        </h3>
                        <template v-for="cat in Categories" :key="cat.category_id">
                            <!-- Danh mục cha -->
                            <label
                                class="filter-checkbox"
                                :class="{ checked: selectedCategories.includes(cat.category_id) }"
                            >
                                <input type="checkbox" :value="cat.category_id" :checked="selectedCategories.includes(cat.category_id)" @change="toggleCategoryFilter(cat.category_id)" />
                                <span class="cb-custom"></span>
                                <span class="cb-label">{{ cat.name }}</span>
                            </label>

                            <!-- Danh mục con -->
                            <div v-if="cat.children && cat.children.length > 0" class="sub-categories">
                                <label
                                    v-for="child in cat.children"
                                    :key="child.category_id"
                                    class="filter-checkbox sub-category"
                                    :class="{ checked: selectedCategories.includes(child.category_id) }"
                                >
                                    <input type="checkbox" :value="child.category_id" :checked="selectedCategories.includes(child.category_id)" @change="toggleCategoryFilter(child.category_id)" />
                                    <span class="cb-custom"></span>
                                    <span class="cb-label">{{ child.name }}</span>
                                </label>
                            </div>
                        </template>
                    </div>

                    <!-- Thương hiệu -->
                    <div class="filter-group" v-if="Brands.length > 0">
                        <h3 class="filter-title">THƯƠNG HIỆU</h3>
                        <label
                            v-for="brand in Brands"
                            :key="brand.id || brand.brand_id"
                            class="filter-checkbox"
                            :class="{ checked: selectedBrands.includes(brand.id || brand.brand_id) }"
                        >
                            <input type="checkbox" :value="brand.id || brand.brand_id" :checked="selectedBrands.includes(brand.id || brand.brand_id)" @change="toggleBrandFilter(brand.id || brand.brand_id)" />
                            <span class="cb-custom"></span>
                            <span class="cb-label">{{ brand.name }}</span>
                        </label>
                    </div>

                    <!-- Khoảng giá -->
                    <div class="filter-group">
                        <h3 class="filter-title">MỨC GIÁ TỐI ĐA</h3>
                        <div class="price-slider-wrap">
                            <div class="current-price-label">
                                Dưới <strong>{{ formatPrice(displayPriceMax) }}</strong>
                            </div>
                            <input type="range" min="0" max="10000000" step="100000" v-model.number="displayPriceMax" @change="priceMax = displayPriceMax" class="price-slider" />
                            <div class="price-labels">
                                <span>0đ</span>
                                <span>10.000.000đ</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- ══ RIGHT: PRODUCT GRID ══ -->
                <section class="product-grid-section">
                    <!-- Loading -->
                    <div v-if="isSearching" class="products-grid-3">
                        <ProductSkeleton v-for="i in 6" :key="i" />
                    </div>

                    <!-- Products -->
                    <div v-else-if="Products.length > 0" class="products-grid-3">
                        <ProductCard
                            v-for="product in Products"
                            :key="product.id"
                            :product="product"
                        />
                    </div>

                    <!-- Empty State -->
                    <div v-else class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>Không tìm thấy sản phẩm nào!</h3>
                        <p v-if="searchQuery">Không có sản phẩm nào khớp với từ khoá <strong>"{{ searchQuery }}"</strong>.</p>
                        <p v-else>Không có sản phẩm nào phù hợp với bộ lọc bạn vừa chọn.</p>
                        <button class="btn-reset-filter" @click="selectedCategories = []; selectedBrands = []; clearSearch();">Xóa bộ lọc</button>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination" v-if="totalPages > 1 && !isSearching">
                        <button class="pg-btn pg-arrow" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <template v-for="(item, index) in visiblePages" :key="index">
                            <span v-if="item === '...'" class="pg-dots">...</span>
                            <button v-else class="pg-btn" :class="{ active: item === currentPage }" @click="goToPage(item)">{{ item }}</button>
                        </template>
                        <button class="pg-btn pg-arrow" :disabled="currentPage >= totalPages" @click="goToPage(currentPage + 1)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ============================================
   PRODUCT PAGE — Vivid Clarity Design
============================================ */
.product-page {
    width: 100%;
    min-height: 100vh;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--text-main);
}

/* ── HERO ── */
.page-hero {
  background: linear-gradient(135deg, #e63b6f, #a0204e);
  color: #fff;
  border-radius: 16px;
  padding: 32px;
  margin: 0 0 28px 0;
  position: relative;
  overflow: hidden;
  text-align: left;
}
.page-hero::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
}
.page-hero h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 8px; position: relative; z-index: 1; }
.hero-sub { opacity: 0.85; font-size: 0.95rem; max-width: 500px; margin: 0; position: relative; z-index: 1; line-height: 1.6; }

/* ── CONTAINER ── */
.product-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 40px 60px;
}

/* ── TOOLBAR ── */
.product-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
    padding-bottom: 16px;
    border-bottom: 1px solid #E9ECEF;
}

.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toolbar-count {
    font-size: 0.9rem;
    color: #636E72;
}
.toolbar-count strong {
    color: var(--text-main);
    font-weight: 700;
}

.search-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: rgba(230,59,111,0.08);
    border: 1px solid rgba(230,59,111,0.2);
    border-radius: 999px;
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 600;
}
.search-tag svg { flex-shrink: 0; }
.search-tag-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    color: var(--primary);
    line-height: 1;
    padding: 0 2px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar-right label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #636E72;
}

.sort-select {
    padding: 8px 14px;
    border: 1px solid #E9ECEF;
    border-radius: 8px;
    background: var(--card-bg);
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-main);
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
}
.sort-select:focus {
    border-color: var(--primary);
}

/* ── LAYOUT ── */
.product-layout {
    display: flex;
    gap: 32px;
    align-items: flex-start;
}

/* ── SIDEBAR ── */
.product-sidebar {
    width: 220px;
    flex-shrink: 0;
    position: sticky;
    top: 90px;
}

.filter-group {
    margin-bottom: 28px;
}

.filter-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: 0.05em;
    margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #2D3436;
}

/* Checkbox filter */
.filter-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
    cursor: pointer;
    font-size: 0.9rem;
    color: #636E72;
    transition: color 0.2s;
}
.filter-checkbox:hover { color: var(--text-main); }
.filter-checkbox.checked { color: var(--text-main); font-weight: 600; }
.filter-checkbox input[type="checkbox"] {
    display: none;
}

.sub-categories {
    margin-left: 28px;
    margin-bottom: 8px;
    border-left: 1px dashed #EAEAEA;
    padding-left: 14px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.filter-checkbox.sub-category {
    padding: 5px 0;
    font-size: 0.85rem;
}

.cb-custom {
    width: 18px;
    height: 18px;
    border: 2px solid #B2BEC3;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
}

.filter-checkbox.checked .cb-custom {
    background: var(--primary);
    border-color: var(--primary);
}
.filter-checkbox.checked .cb-custom::after {
    content: '';
    width: 10px;
    height: 6px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: rotate(-45deg);
    margin-top: -2px;
}

/* Price slider */
.price-slider-wrap {
    padding: 4px 0;
}
.current-price-label {
    font-size: 0.9rem;
    color: var(--primary);
    margin-bottom: 12px;
    text-align: center;
}
.current-price-label strong {
    font-weight: 800;
    font-size: 1.1rem;
}
.price-slider {
    width: 100%;
    -webkit-appearance: none;
    appearance: none;
    height: 4px;
    background: #E9ECEF;
    border-radius: 2px;
    outline: none;
}
.price-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background: var(--primary);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(230,59,111,0.3);
}
.price-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    background: var(--primary);
    border-radius: 50%;
    cursor: pointer;
    border: none;
}
.price-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #636E72;
    margin-top: 8px;
    font-weight: 600;
}

/* ── PRODUCT GRID 3 columns ── */
.product-grid-section {
    flex: 1;
    min-width: 0;
}

.products-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

/* ── EMPTY STATE ── */
.empty-state {
    text-align: center;
    padding: 80px 24px;
}
.empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
}
.empty-state p {
    color: #636E72;
    font-size: 0.95rem;
    margin-bottom: 24px;
}
.btn-reset-filter {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.btn-reset-filter:hover {
    background: var(--primary);
    color: #fff;
}

/* ── PAGINATION ── */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 40px;
    padding-top: 24px;
}

.pg-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #E9ECEF;
    background: var(--card-bg);
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #636E72;
    cursor: pointer;
    transition: all 0.2s;
}
.pg-btn:hover:not(:disabled):not(.active) {
    border-color: var(--primary);
    color: var(--primary);
}
.pg-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.pg-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.pg-arrow {
    background: transparent;
}
.pg-dots {
    font-size: 0.9rem;
    color: #B2BEC3;
    padding: 0 4px;
}

/* ── RESPONSIVE ── */
@media (max-width: 1200px) {
    .product-container { padding: 24px 24px 48px; }
}

@media (max-width: 1024px) {
    .products-grid-3 { grid-template-columns: repeat(2, 1fr); }
    .page-hero h1 { font-size: 1.5rem; }
}

@media (max-width: 768px) {
    .product-layout { flex-direction: column; }
    .product-sidebar { width: 100%; position: static; display: flex; flex-wrap: wrap; gap: 24px; }
    .filter-group { flex: 1; min-width: 200px; }
    .product-toolbar { flex-direction: column; align-items: flex-start; gap: 12px; }
    .page-hero { padding: 24px; }
    .page-hero h1 { font-size: 1.3rem; }
}

@media (max-width: 480px) {
    .products-grid-3 { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .product-container { padding: 16px 12px 32px; }
}
</style>
