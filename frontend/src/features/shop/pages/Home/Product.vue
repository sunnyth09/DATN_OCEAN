<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
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
const isMobileFilterOpen = ref(false);
const isCategoryOpen = ref(true);
const isBrandOpen = ref(true);
const selectedCategories = ref([]);     // checkbox array
const selectedBrands = ref([]);         // checkbox array
const priceMin = ref(0);
const priceMax = ref(10000000);
const displayPriceMax = ref(10000000);
const maxPriceLimit = ref(10000000);
const hasUserSetPrice = ref(false);
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

// ── Dynamic Banner Info ──
const bannerInfo = computed(() => {
    const overlay = 'linear-gradient(90deg, rgba(15, 23, 42, 0.75) 0%, rgba(15, 23, 42, 0.4) 40%, transparent 80%)';
    const defaultBanner = {
        title: "Tất Cả Sản Phẩm",
        sub: "Nâng tầm cuộc chơi với trang thiết bị chuyên nghiệp",
        bg: `${overlay}, url('/banners/general.png') center/cover no-repeat`
    };

    if (selectedCategories.value.length === 1) {
        const catId = selectedCategories.value[0];
        // Find category name by flattening Categories
        const flatCategories = Categories.value.reduce((acc, cat) => {
            acc.push(cat);
            if (cat.children) acc.push(...cat.children);
            return acc;
        }, []);
        
        const category = flatCategories.find(c => c.category_id === catId || c.id === catId);
        if (category && category.name) {
            const nameLower = category.name.toLowerCase();
            let bgImage = '/banners/general.png';
            if (nameLower.includes('pickleball')) {
                bgImage = '/banners/pickleball.png';
            } else if (nameLower.includes('cầu lông') || nameLower.includes('badminton')) {
                bgImage = '/banners/badminton.png';
            } else if (nameLower.includes('tennis')) {
                bgImage = '/banners/tennis.png';
            }
            return {
                title: `Sản Phẩm ${category.name}`,
                sub: `Trang thiết bị chuyên nghiệp cho môn ${category.name}`,
                bg: `${overlay}, url('${bgImage}') center/cover no-repeat`
            };
        }
    }
    return defaultBanner;
});

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
        if (response.data.max_price_limit) {
            maxPriceLimit.value = Number(response.data.max_price_limit);
            if (!hasUserSetPrice.value) {
                priceMax.value = maxPriceLimit.value;
                displayPriceMax.value = maxPriceLimit.value;
            }
        }
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
        Brands.value = await catalogStore.fetchBrands();
    } catch (e) {
        Brands.value = [];
    }
};

// ── Category toggle (Single Select) ──
const expandedCategories = ref([]);
const toggleExpandCategory = (catId) => {
    const idx = expandedCategories.value.indexOf(catId);
    if (idx > -1) {
        expandedCategories.value = [];
    } else {
        expandedCategories.value = [catId];
    }
};

const toggleCategoryFilter = (cat, isParent = false) => {
    const catId = typeof cat === 'object' ? cat.category_id : cat;
    
    if (selectedCategories.value.includes(catId)) {
        selectedCategories.value = []; // Bỏ chọn nếu click lại
    } else {
        selectedCategories.value = [catId]; // Chọn 1 danh mục duy nhất
        if (isParent) {
            expandedCategories.value = [catId]; // Xổ ra danh mục con của nó, đóng các danh mục khác
        }
    }
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
        if (typeof val === 'string' && val.includes(',')) {
            const parsed = val.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
            if (JSON.stringify(selectedCategories.value) !== JSON.stringify(parsed)) {
                selectedCategories.value = parsed;
            }
        } else {
            const flatCategories = Categories.value.reduce((acc, cat) => {
                acc.push(cat);
                if (cat.children) acc.push(...cat.children);
                return acc;
            }, []);
            
            const cat = flatCategories.find(c => c.category_id == val || c.slug === val);
            const catId = cat ? cat.category_id : parseInt(val);
            
            if (!isNaN(catId) && !selectedCategories.value.includes(catId)) {
                selectedCategories.value = [catId];
            }
        }
    } else {
        selectedCategories.value = [];
    }
});

const clearSearch = () => {
    searchQuery.value = '';
    hasUserSetPrice.value = false;
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

    await nextTick();
    isInitializing = false;
    scheduleFetchProducts();

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
        <!-- ══ HERO BANNER ══ -->
        <section class="page-hero" :style="{ background: bannerInfo.bg }">
            <div class="hero-inner-container">
                <div class="hero-pill">OCEAN SPORT DEALS</div>
                <h1>{{ bannerInfo.title }}</h1>
                <p class="hero-sub">{{ bannerInfo.sub }}</p>
            </div>
        </section>

        <!-- ══ MAIN CONTENT ══ -->
        <div class="product-container">

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
                <!-- Mobile Filter Toggle -->
                <button class="mobile-filter-toggle d-md-none" @click="isMobileFilterOpen = true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Bộ lọc sản phẩm
                </button>

                <!-- ══ LEFT SIDEBAR ══ -->
                <div class="filter-overlay" v-if="isMobileFilterOpen" @click="isMobileFilterOpen = false"></div>
                <aside class="product-sidebar" :class="{ 'is-open': isMobileFilterOpen }">
                    <div class="sidebar-header d-md-none">
                        <h3>Bộ Lọc</h3>
                        <button class="close-filter-btn" @click="isMobileFilterOpen = false">&times;</button>
                    </div>
                    <!-- Danh mục -->
                    <div class="filter-group">
                        <h3 class="filter-title collapsible" @click="isCategoryOpen = !isCategoryOpen">
                            <div class="title-left">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="9" y2="18"/></svg>
                                DANH MỤC
                            </div>
                            <svg class="collapse-icon" :class="{ 'is-open': isCategoryOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </h3>
                        <div v-show="isCategoryOpen" class="filter-content">
                            <template v-for="cat in Categories" :key="cat.category_id">
                                <div class="category-parent-wrap">
                                    <!-- Danh mục cha -->
                                    <div
                                        class="filter-item"
                                        :class="{ active: selectedCategories.includes(cat.category_id) }"
                                        @click="toggleCategoryFilter(cat, true)"
                                    >
                                        <span class="filter-name">{{ cat.name }}</span>
                                    </div>
                                    <span v-if="cat.children && cat.children.length > 0" 
                                          class="expand-icon" 
                                          :class="{ expanded: expandedCategories.includes(cat.category_id) }"
                                          @click.stop="toggleExpandCategory(cat.category_id)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </span>
                                </div>

                                <!-- Danh mục con -->
                                <div v-show="expandedCategories.includes(cat.category_id)" v-if="cat.children && cat.children.length > 0" class="sub-categories">
                                    <div
                                        v-for="child in cat.children"
                                        :key="child.category_id"
                                        class="filter-item sub-category"
                                        :class="{ active: selectedCategories.includes(child.category_id) }"
                                        @click="toggleCategoryFilter(child, false)"
                                    >
                                        <span class="filter-name">{{ child.name }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Thương hiệu -->
                    <div class="filter-group" v-if="Brands.length > 0">
                        <h3 class="filter-title collapsible" @click="isBrandOpen = !isBrandOpen">
                            <div class="title-left">THƯƠNG HIỆU</div>
                            <svg class="collapse-icon" :class="{ 'is-open': isBrandOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </h3>
                        <div v-show="isBrandOpen" class="filter-content">
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
                    </div>

                    <!-- Khoảng giá -->
                    <div class="filter-group">
                        <h3 class="filter-title">MỨC GIÁ TỐI ĐA</h3>
                        <div class="price-slider-wrap">
                            <div class="current-price-label">
                                Dưới <strong>{{ formatPrice(displayPriceMax) }}</strong>
                            </div>
                            <input type="range" min="0" :max="maxPriceLimit" step="100000" v-model.number="displayPriceMax" @change="priceMax = displayPriceMax; hasUserSetPrice = true" class="price-slider" />
                            <div class="price-labels">
                                <span>0đ</span>
                                <span>{{ formatPrice(maxPriceLimit) }}</span>
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
  border-radius: 0;
  padding: 85px 0;
  margin: 0 0 28px 0;
  position: relative;
  overflow: hidden;
  text-align: left;
  min-height: 250px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.hero-inner-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 40px;
  width: 100%;
}
.hero-pill {
  display: inline-block;
  background: rgba(255, 255, 255, 0.2);
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 16px;
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
.page-hero h1 { font-size: 2.2rem; font-weight: 800; margin: 0 0 12px; position: relative; z-index: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
.hero-sub { opacity: 0.95; font-size: 1.1rem; max-width: 500px; margin: 0; position: relative; z-index: 1; line-height: 1.6; text-shadow: 0 1px 3px rgba(0,0,0,0.3); }

/* ── CONTAINER ── */
.product-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px 60px;
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

.filter-title.collapsible {
    cursor: pointer;
    justify-content: space-between;
    user-select: none;
}
.filter-title .title-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-title .collapse-icon {
    transition: transform 0.3s ease;
    color: #636E72;
}
.filter-title .collapse-icon.is-open {
    transform: rotate(180deg);
}

/* Checkbox filter */
.category-parent-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.expand-icon {
    cursor: pointer;
    color: #636E72;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
}
.expand-icon:hover {
    color: var(--text-main);
}
.expand-icon.expanded {
    transform: rotate(180deg);
}

.filter-item {
    display: flex;
    align-items: center;
    padding: 7px 0;
    cursor: pointer;
    font-size: 0.95rem;
    color: #636E72;
    transition: color 0.2s, font-weight 0.2s;
}
.filter-item:hover {
    color: var(--text-main);
}
.filter-item.active {
    color: var(--primary);
    font-weight: 700;
}
.filter-item.sub-category {
    padding: 5px 0;
    font-size: 0.85rem;
}

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
    
    .mobile-filter-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 12px;
        background: var(--card-bg);
        border: 1px solid #E9ECEF;
        border-radius: 8px;
        font-weight: 700;
        color: var(--text-main);
        justify-content: center;
        margin-bottom: 16px;
    }

    .filter-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
    }

    .product-sidebar { 
        position: fixed;
        top: 0;
        left: -100%;
        width: 300px;
        max-width: 85vw;
        height: 100vh;
        background: #fff;
        z-index: 1050;
        padding: 24px;
        overflow-y: auto;
        transition: left 0.3s ease;
        display: block;
    }

    .product-sidebar.is-open {
        left: 0;
    }

    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #eee;
    }

    .sidebar-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 800;
    }

    .close-filter-btn {
        background: none;
        border: none;
        font-size: 2rem;
        line-height: 1;
        padding: 0;
    }
    
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
