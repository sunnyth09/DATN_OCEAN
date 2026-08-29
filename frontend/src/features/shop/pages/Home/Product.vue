<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import { useRouter, useRoute } from "vue-router";
import ProductCard from "@/components/ProductCard.vue";
import ProductSkeleton from "@/components/ProductSkeleton.vue";
import AppIcon from "@/components/AppIcon.vue";
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
const priceMin = ref(null);
const priceMax = ref(null);
const selectedPriceRange = ref('all');
const customPriceMin = ref(null);
const customPriceMax = ref(null);

const sortBy = ref("newest");
const searchQuery = ref("");

// ── Custom Sort state ──
const sortOptions = [
    { value: "newest", label: "Mới nhất" },
    { value: "oldest", label: "Cũ nhất" },
    { value: "price-asc", label: "Giá: Thấp → Cao" },
    { value: "price-desc", label: "Giá: Cao → Thấp" },
];
const isSortModalOpen = ref(false);
const isDesktopSortOpen = ref(false);
const desktopSortRef = ref(null);

const currentSortLabel = computed(() => {
    const opt = sortOptions.find(o => o.value === sortBy.value);
    return opt ? opt.label : "Sắp xếp";
});

const selectSort = (val) => {
    sortBy.value = val;
    isSortModalOpen.value = false;
    isDesktopSortOpen.value = false;
};

const handleClickOutsideDesktopSort = (e) => {
    if (desktopSortRef.value && !desktopSortRef.value.contains(e.target)) {
        isDesktopSortOpen.value = false;
    }
};

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
        if (selectedPriceRange.value === 'custom') {
            if (customPriceMin.value !== null && customPriceMin.value !== '') {
                params.min_price = customPriceMin.value;
            }
            if (customPriceMax.value !== null && customPriceMax.value !== '') {
                params.max_price = customPriceMax.value;
            }
            if (customPriceMin.value !== null && customPriceMax.value !== null) {
                params.price_range = `${customPriceMin.value}-${customPriceMax.value}`;
            } else if (customPriceMin.value !== null) {
                params.price_range = `${customPriceMin.value}-`;
            } else if (customPriceMax.value !== null) {
                params.price_range = `-${customPriceMax.value}`;
            }
        } else if (selectedPriceRange.value !== 'all') {
            params.price_range = selectedPriceRange.value;
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

const scheduleFetchProducts = (delay = 100) => {
    clearTimeout(fetchTimer);
    fetchTimer = setTimeout(fetchProducts, delay);
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

watch(
    [selectedCategories, selectedBrands, sortBy, selectedPriceRange],
    () => {
        if (!isInitializing) {
            currentPage.value = 1;
            scheduleFetchProducts(100);
        }
    },
    { deep: true }
);

watch(
    [customPriceMin, customPriceMax],
    () => {
        if (!isInitializing && selectedPriceRange.value === 'custom') {
            currentPage.value = 1;
            scheduleFetchProducts(700);
        }
    }
);

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

// ── Flatten categories for lookup ──
const flatCategories = computed(() => {
    return Categories.value.reduce((acc, cat) => {
        acc.push(cat);
        if (cat.children && Array.isArray(cat.children)) acc.push(...cat.children);
        return acc;
    }, []);
});

// ── Active filters calculation ──
const activeFiltersCount = computed(() => {
    let count = 0;
    if (selectedCategories.value.length > 0) count += selectedCategories.value.length;
    if (selectedBrands.value.length > 0) count += selectedBrands.value.length;
    if (selectedPriceRange.value !== 'all') count += 1;
    if (searchQuery.value.trim()) count += 1;
    return count;
});

const activeFilterChips = computed(() => {
    const chips = [];
    if (searchQuery.value.trim()) {
        chips.push({
            type: 'search',
            key: 'search-query',
            label: `Tìm: "${searchQuery.value.trim()}"`
        });
    }
    selectedCategories.value.forEach(catId => {
        const cat = flatCategories.value.find(c => c.category_id === catId || c.id === catId);
        chips.push({
            type: 'category',
            key: `cat-${catId}`,
            id: catId,
            label: cat ? cat.name : `Danh mục #${catId}`
        });
    });
    selectedBrands.value.forEach(brandId => {
        const brand = Brands.value.find(b => (b.id || b.brand_id) === brandId);
        chips.push({
            type: 'brand',
            key: `brand-${brandId}`,
            id: brandId,
            label: brand ? brand.name : `Thương hiệu #${brandId}`
        });
    });
    if (selectedPriceRange.value !== 'all') {
        let priceLabel = '';
        if (selectedPriceRange.value === 'under-500k') priceLabel = '< 500.000đ';
        else if (selectedPriceRange.value === '500k-1m') priceLabel = '500k - 1tr';
        else if (selectedPriceRange.value === 'above-1m') priceLabel = '> 1.000.000đ';
        else if (selectedPriceRange.value === 'custom') {
            if (customPriceMin.value && customPriceMax.value) {
                priceLabel = `${formatPrice(customPriceMin.value)} - ${formatPrice(customPriceMax.value)}`;
            } else if (customPriceMin.value) {
                priceLabel = `≥ ${formatPrice(customPriceMin.value)}`;
            } else if (customPriceMax.value) {
                priceLabel = `≤ ${formatPrice(customPriceMax.value)}`;
            } else {
                priceLabel = 'Tùy chỉnh giá';
            }
        }
        if (priceLabel) {
            chips.push({
                type: 'price',
                key: 'price-range',
                label: priceLabel
            });
        }
    }
    return chips;
});

const removeFilterChip = (chip) => {
    if (chip.type === 'search') {
        clearSearch();
    } else if (chip.type === 'category') {
        selectedCategories.value = selectedCategories.value.filter(id => id !== chip.id);
    } else if (chip.type === 'brand') {
        selectedBrands.value = selectedBrands.value.filter(id => id !== chip.id);
    } else if (chip.type === 'price') {
        selectedPriceRange.value = 'all';
        customPriceMin.value = null;
        customPriceMax.value = null;
    }
};

const resetAllFilters = () => {
    selectedCategories.value = [];
    selectedBrands.value = [];
    selectedPriceRange.value = 'all';
    customPriceMin.value = null;
    customPriceMax.value = null;
    clearSearch();
};

// Khóa cuộn trang khi mở Drawer bộ lọc hoặc Sort Bottom Sheet trên mobile
watch([isMobileFilterOpen, isSortModalOpen], ([isFilterOpen, isSortOpen]) => {
    if (typeof document !== 'undefined') {
        if (isFilterOpen || isSortOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
});

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
    if (typeof document !== 'undefined') {
        document.addEventListener('click', handleClickOutsideDesktopSort);
    }

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
            const cat = flatCategories.value.find(c => c.category_id == categoryParam || c.slug === categoryParam);
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
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
        document.removeEventListener('click', handleClickOutsideDesktopSort);
    }
});
</script>

<template>
    <div class="product-page">
        <!-- ══ MAIN CONTAINER ══ -->
        <div class="product-container">

            <!-- ══ HERO BANNER (Đồng bộ kích thước chuẩn 16px) ══ -->
            <section class="page-hero" :style="{ background: bannerInfo.bg }">
                <div class="hero-inner-container">
                    <div class="product-hero-content">
                        <div class="hero-pill">
                            <i class="bi bi-shield-check me-1"></i>
                            OCEAN SPORT CHÍNH HÃNG
                        </div>
                        <h1>{{ bannerInfo.title }}</h1>
                        <p class="hero-sub">{{ bannerInfo.sub }}</p>

                        <!-- Trust Chips -->
                        <div class="product-hero-trust">
                            <div class="trust-chip">
                                <i class="bi bi-patch-check-fill text-warning"></i>
                                <span>100% Chính Hãng</span>
                            </div>
                            <div class="trust-chip">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Đổi trả 30 ngày</span>
                            </div>
                            <div class="trust-chip">
                                <i class="bi bi-truck"></i>
                                <span>Freeship đơn từ 500k</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Desktop Info Toolbar -->
            <div class="product-toolbar d-none d-md-flex">
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
                    <div class="desktop-sort-dropdown" ref="desktopSortRef">
                        <label>Sắp xếp:</label>
                        <button class="desktop-sort-toggle" type="button" @click.stop="isDesktopSortOpen = !isDesktopSortOpen">
                            <span>{{ currentSortLabel }}</span>
                            <svg class="sort-chevron" :class="{ 'is-open': isDesktopSortOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <transition name="dropdown-fade">
                            <div v-if="isDesktopSortOpen" class="desktop-sort-menu">
                                <button 
                                    v-for="opt in sortOptions" 
                                    :key="opt.value" 
                                    class="desktop-sort-item" 
                                    :class="{ active: sortBy === opt.value }"
                                    @click="selectSort(opt.value)"
                                >
                                    <span>{{ opt.label }}</span>
                                    <svg v-if="sortBy === opt.value" class="sort-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </button>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>

            <!-- Mobile Toolbar (Filter button + Custom Sort button + Active chips) -->
            <div class="product-toolbar-mobile d-md-none">
                <div class="mobile-toolbar-actions">
                    <button class="mobile-filter-btn" :class="{ 'has-active': activeFiltersCount > 0 }" @click="isMobileFilterOpen = true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        <span>Bộ lọc</span>
                        <span v-if="activeFiltersCount > 0" class="filter-badge">{{ activeFiltersCount }}</span>
                    </button>

                    <button class="mobile-sort-btn" type="button" @click="isSortModalOpen = true">
                        <svg class="sort-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg>
                        <span class="sort-text">{{ currentSortLabel }}</span>
                        <svg class="sort-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </div>

                <!-- Product count sub-row on mobile -->
                <div class="mobile-count-row">
                    <span class="mobile-count-text">
                        Tìm thấy <strong>{{ totalProducts }}</strong> sản phẩm
                    </span>
                </div>

                <!-- Active filter chips on mobile -->
                <div v-if="activeFilterChips.length > 0" class="active-chips-bar">
                    <span class="active-chip" v-for="chip in activeFilterChips" :key="chip.key" @click="removeFilterChip(chip)">
                        <span>{{ chip.label }}</span>
                        <span class="chip-remove">&times;</span>
                    </span>
                    <button class="chip-clear-all" @click="resetAllFilters">Xóa hết</button>
                </div>
            </div>

            <div class="product-layout">
                <!-- ══ LEFT SIDEBAR / MOBILE DRAWER ══ -->
                <div class="filter-overlay" :class="{ 'is-open': isMobileFilterOpen }" @click="isMobileFilterOpen = false"></div>
                <aside class="product-sidebar" :class="{ 'is-open': isMobileFilterOpen }">
                    <!-- Drawer Header -->
                    <div class="sidebar-header">
                        <div class="sidebar-header-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            <h3>Bộ Lọc</h3>
                            <span v-if="activeFiltersCount > 0" class="header-count-badge">{{ activeFiltersCount }}</span>
                        </div>
                        <div class="sidebar-header-actions">
                            <button v-if="activeFiltersCount > 0" class="btn-clear-inline d-md-none" @click="resetAllFilters">Xóa tất cả</button>
                            <button class="close-filter-btn d-md-none" @click="isMobileFilterOpen = false" aria-label="Đóng">&times;</button>
                        </div>
                    </div>

                    <!-- Drawer Scrollable Content -->
                    <div class="sidebar-body">
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
                            <h3 class="filter-title">KHOẢNG GIÁ</h3>
                            
                            <!-- Radio Tùy chọn giá -->
                            <div class="price-options">
                                <label class="filter-radio" :class="{ checked: selectedPriceRange === 'all' }">
                                    <input type="radio" value="all" v-model="selectedPriceRange" />
                                    <span class="rb-custom"></span>
                                    <span class="rb-label">Tất cả</span>
                                </label>
                                <label class="filter-radio" :class="{ checked: selectedPriceRange === 'under-500k' }">
                                    <input type="radio" value="under-500k" v-model="selectedPriceRange" />
                                    <span class="rb-custom"></span>
                                    <span class="rb-label">Dưới 500.000đ</span>
                                </label>
                                <label class="filter-radio" :class="{ checked: selectedPriceRange === '500k-1m' }">
                                    <input type="radio" value="500k-1m" v-model="selectedPriceRange" />
                                    <span class="rb-custom"></span>
                                    <span class="rb-label">500.000đ - 1.000.000đ</span>
                                </label>
                                <label class="filter-radio" :class="{ checked: selectedPriceRange === 'above-1m' }">
                                    <input type="radio" value="above-1m" v-model="selectedPriceRange" />
                                    <span class="rb-custom"></span>
                                    <span class="rb-label">Trên 1.000.000đ</span>
                                </label>
                                <label class="filter-radio" :class="{ checked: selectedPriceRange === 'custom' }">
                                    <input type="radio" value="custom" v-model="selectedPriceRange" />
                                    <span class="rb-custom"></span>
                                    <span class="rb-label">Tùy chỉnh</span>
                                </label>
                            </div>

                            <!-- Ô nhập tay (chỉ hiện khi chọn Tùy chỉnh) -->
                            <transition name="fade">
                                <div v-if="selectedPriceRange === 'custom'" class="custom-price-inputs">
                                    <div class="price-input-wrapper">
                                        <input type="number" v-model.number="customPriceMin" placeholder="Tối thiểu" class="price-input" min="0"/>
                                    </div>
                                    <span class="price-separator">-</span>
                                    <div class="price-input-wrapper">
                                        <input type="number" v-model.number="customPriceMax" placeholder="Tối đa" class="price-input" min="0"/>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>

                    <!-- Sticky Bottom Action Bar on Mobile -->
                    <div class="sidebar-footer d-md-none">
                        <button class="btn-reset-drawer" @click="resetAllFilters" :disabled="activeFiltersCount === 0">
                            Thiết lập lại
                        </button>
                        <button class="btn-apply-drawer" @click="isMobileFilterOpen = false">
                            Xem {{ isSearching ? '...' : totalProducts }} sản phẩm
                        </button>
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
                        <div class="empty-icon">
                            <AppIcon name="search" size="54" stroke-width="1.5" />
                        </div>
                        <h3>Không tìm thấy sản phẩm nào!</h3>
                        <p v-if="searchQuery">Không có sản phẩm nào khớp với từ khoá <strong>"{{ searchQuery }}"</strong>.</p>
                        <p v-else>Không có sản phẩm nào phù hợp với bộ lọc bạn vừa chọn.</p>
                        <button class="btn-reset-filter" @click="resetAllFilters">Xóa bộ lọc</button>
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

        <!-- ══ MOBILE SORT BOTTOM SHEET (TELEPORT) ══ -->
        <Teleport to="body">
            <Transition name="sheet-fade">
                <div v-if="isSortModalOpen" class="sort-sheet-overlay" @click="isSortModalOpen = false">
                    <div class="sort-sheet-card" @click.stop>
                        <div class="sort-sheet-handle"></div>
                        <div class="sort-sheet-header">
                            <div class="sort-sheet-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <line x1="6" y1="12" x2="18" y2="12"/>
                                    <line x1="10" y1="18" x2="14" y2="18"/>
                                </svg>
                                <span>Sắp xếp sản phẩm</span>
                            </div>
                            <button class="sort-sheet-close" @click="isSortModalOpen = false" aria-label="Đóng">&times;</button>
                        </div>
                        <div class="sort-sheet-list">
                            <button 
                                v-for="opt in sortOptions" 
                                :key="opt.value" 
                                class="sort-sheet-item"
                                :class="{ active: sortBy === opt.value }"
                                @click="selectSort(opt.value)"
                            >
                                <span class="opt-label">{{ opt.label }}</span>
                                <span class="opt-indicator">
                                    <svg v-if="sortBy === opt.value" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* ============================================
   PRODUCT PAGE — Vivid Clarity Design
=========================================== */
.product-page {
    width: 100%;
    min-height: 100vh;
    font-family: var(--font-inter, 'Inter', sans-serif);
    color: var(--text-main);
}

/* ── HERO BANNER (Đồng bộ chuẩn 16px) ── */
.page-hero {
  background: linear-gradient(135deg, #e63b6f, #a0204e);
  color: #fff;
  border-radius: 16px;
  padding: 36px 36px;
  margin: 16px 0 24px 0;
  position: relative;
  overflow: hidden;
  text-align: left;
  box-shadow: 0 10px 28px rgba(230, 59, 111, 0.16);
}
.hero-inner-container {
  width: 100%;
  position: relative;
  z-index: 2;
}
.hero-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 5px 14px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  margin-bottom: 10px;
  backdrop-filter: blur(8px);
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
.page-hero h1 { font-size: clamp(1.75rem, 2.6vw, 2.25rem); font-weight: 800; margin: 0 0 8px; position: relative; z-index: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.3); line-height: 1.2; }
.hero-sub { opacity: 0.95; font-size: 0.95rem; max-width: 580px; margin: 0; position: relative; z-index: 1; line-height: 1.55; text-shadow: 0 1px 3px rgba(0,0,0,0.3); }

.product-hero-trust {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 16px;
  position: relative;
  z-index: 2;
}

.trust-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #fff;
  background: rgba(255, 255, 255, 0.16);
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 5px 12px;
  border-radius: 8px;
  backdrop-filter: blur(8px);
}

/* ── CONTAINER ── */
.product-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px 60px;
}

/* ── DESKTOP TOOLBAR ── */
.product-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
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

/* ── DESKTOP SORT DROPDOWN ── */
.desktop-sort-dropdown {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
}
.desktop-sort-dropdown label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #636E72;
}
.desktop-sort-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border: 1px solid #E9ECEF;
    border-radius: 8px;
    background: var(--card-bg, #fff);
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-main);
    cursor: pointer;
    outline: none;
    transition: all 0.2s;
}
.desktop-sort-toggle:hover {
    border-color: var(--primary, #e63b6f);
}
.desktop-sort-toggle .sort-chevron {
    color: #64748b;
    transition: transform 0.25s ease;
}
.desktop-sort-toggle .sort-chevron.is-open {
    transform: rotate(180deg);
}

.desktop-sort-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 200px;
    background: var(--card-bg, #ffffff);
    border: 1px solid #E9ECEF;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    padding: 6px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.desktop-sort-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 9px 12px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.18s;
    text-align: left;
}
.desktop-sort-item:hover {
    background: #f8fafc;
    color: var(--text-main);
}
.desktop-sort-item.active {
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary, #e63b6f);
    font-weight: 700;
}
.desktop-sort-item .sort-check {
    color: var(--primary, #e63b6f);
}

.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
    transition: all 0.2s ease;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* ── MOBILE TOOLBAR ── */
.product-toolbar-mobile {
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mobile-toolbar-actions {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 10px;
}

.mobile-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 14px;
    background: var(--card-bg, #fff);
    border: 1.5px solid #E9ECEF;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-main);
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.mobile-filter-btn:active {
    transform: scale(0.98);
}
.mobile-filter-btn.has-active {
    border-color: var(--primary, #e63b6f);
    color: var(--primary, #e63b6f);
    background: rgba(230, 59, 111, 0.05);
}

.filter-badge {
    background: var(--primary, #e63b6f);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
    line-height: 1.2;
}

.mobile-sort-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 10px 12px;
    background: var(--card-bg, #fff);
    border: 1.5px solid #E9ECEF;
    border-radius: 10px;
    color: var(--text-main);
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.mobile-sort-btn:active {
    transform: scale(0.98);
}
.mobile-sort-btn .sort-icon {
    color: #64748b;
    flex-shrink: 0;
}
.mobile-sort-btn .sort-text {
    flex: 1;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-main);
    font-weight: 700;
    font-size: 0.88rem;
    font-family: inherit;
}
.mobile-sort-btn .sort-chevron {
    color: #94a3b8;
    flex-shrink: 0;
}

/* ── MOBILE SORT BOTTOM SHEET ── */
.sort-sheet-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1060;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.sort-sheet-card {
    width: 100%;
    max-width: 480px;
    background: var(--card-bg, #ffffff);
    border-radius: 20px 20px 0 0;
    padding: 12px 20px calc(24px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.2);
    animation: sheetSlideUp 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.sort-sheet-handle {
    width: 36px;
    height: 4px;
    background: #e2e8f0;
    border-radius: 999px;
    margin: 0 auto 12px;
}

.sort-sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 8px;
}

.sort-sheet-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main);
}
.sort-sheet-title svg {
    color: var(--primary, #e63b6f);
}

.sort-sheet-close {
    background: #f1f5f9;
    border: none;
    font-size: 1.3rem;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    cursor: pointer;
}
.sort-sheet-close:active {
    background: #e2e8f0;
}

.sort-sheet-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sort-sheet-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 14px 12px;
    border: none;
    background: transparent;
    border-radius: 10px;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}
.sort-sheet-item:active {
    background: #f8fafc;
}
.sort-sheet-item.active {
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary, #e63b6f);
    font-weight: 700;
}
.sort-sheet-item .opt-indicator {
    color: var(--primary, #e63b6f);
    display: flex;
    align-items: center;
}

/* Sheet Transitions */
.sheet-fade-enter-active,
.sheet-fade-leave-active {
    transition: opacity 0.25s ease;
}
.sheet-fade-enter-from,
.sheet-fade-leave-to {
    opacity: 0;
}
.sheet-fade-enter-from .sort-sheet-card,
.sheet-fade-leave-to .sort-sheet-card {
    transform: translateY(100%);
}

@keyframes sheetSlideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

.mobile-count-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2px;
}
.mobile-count-text {
    font-size: 0.84rem;
    color: #64748b;
}
.mobile-count-text strong {
    color: var(--text-main);
    font-weight: 700;
}

/* ── ACTIVE CHIPS ── */
.active-chips-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 0 6px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.active-chips-bar::-webkit-scrollbar {
    display: none;
}

.active-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: rgba(230, 59, 111, 0.08);
    border: 1px solid rgba(230, 59, 111, 0.22);
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary, #e63b6f);
    white-space: nowrap;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s;
}
.active-chip:active {
    background: rgba(230, 59, 111, 0.16);
}
.chip-remove {
    font-size: 1.1rem;
    line-height: 1;
    font-weight: bold;
}

.chip-clear-all {
    background: none;
    border: none;
    font-size: 0.8rem;
    font-weight: 700;
    color: #94a3b8;
    text-decoration: underline;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    padding: 4px 6px;
}

/* ── LAYOUT ── */
.product-layout {
    display: flex;
    gap: 32px;
    align-items: flex-start;
}

/* ── SIDEBAR (DESKTOP) ── */
.product-sidebar {
    width: 240px;
    flex-shrink: 0;
    position: sticky;
    top: 90px;
    max-height: calc(100vh - 110px);
    overflow-y: auto;
    padding-right: 8px;
    background: transparent;
}
.product-sidebar::-webkit-scrollbar {
    width: 4px;
}
.product-sidebar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.product-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-header {
    display: none; /* hidden on desktop */
}

.filter-group {
    margin-bottom: 28px;
}

.filter-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
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

/* Checkbox/Radio filters */
.category-parent-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.expand-icon {
    cursor: pointer;
    color: #636E72;
    padding: 6px;
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
    padding: 8px 0;
    cursor: pointer;
    font-size: 0.95rem;
    color: #636E72;
    transition: color 0.2s, font-weight 0.2s;
}
.filter-item:hover {
    color: var(--text-main);
}
.filter-item.active {
    color: var(--primary, #e63b6f);
    font-weight: 700;
}
.filter-item.sub-category {
    padding: 6px 0;
    font-size: 0.88rem;
}

.filter-checkbox, .filter-radio {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  margin-bottom: 10px;
  padding: 6px 4px;
  border-radius: 8px;
  transition: background 0.2s;
}
.filter-checkbox:hover, .filter-radio:hover {
  background: #f8fafc;
}
.filter-checkbox input, .filter-radio input {
  display: none;
}
.cb-custom {
  width: 20px;
  height: 20px;
  border: 2px solid #cbd5e1;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
.rb-custom {
  width: 20px;
  height: 20px;
  border: 2px solid #cbd5e1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
.filter-checkbox.checked .cb-custom {
  background: var(--primary, #e63b6f);
  border-color: var(--primary, #e63b6f);
}
.filter-radio.checked .rb-custom {
  border-color: var(--primary, #e63b6f);
}
.filter-checkbox.checked .cb-custom::after {
  content: '';
  width: 10px;
  height: 10px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
}
.filter-radio.checked .rb-custom::after {
  content: '';
  width: 10px;
  height: 10px;
  background: var(--primary, #e63b6f);
  border-radius: 50%;
}
.cb-label, .rb-label {
  font-size: 0.95rem;
  color: #475569;
  font-weight: 500;
  transition: color 0.2s;
}
.filter-checkbox.checked .cb-label, .filter-radio.checked .rb-label {
  color: #0f172a;
  font-weight: 700;
}

.sub-categories {
    margin-left: 24px;
    margin-bottom: 8px;
    border-left: 1.5px dashed #E2E8F0;
    padding-left: 14px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* ── PRICE OPTIONS ── */
.price-options {
    display: flex;
    flex-direction: column;
}
.custom-price-inputs {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding-left: 32px;
}
.price-input-wrapper {
    flex: 1;
}
.price-input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #334155;
    outline: none;
    transition: all 0.2s;
    font-family: inherit;
    -moz-appearance: textfield;
}
.price-input::-webkit-outer-spin-button,
.price-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.price-input:focus {
    border-color: var(--primary, #e63b6f);
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}
.price-separator {
    color: #64748b;
    font-weight: bold;
}
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
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
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
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
    color: var(--primary, #e63b6f);
    border: 2px solid var(--primary, #e63b6f);
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.btn-reset-filter:hover {
    background: var(--primary, #e63b6f);
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
    background: var(--card-bg, #fff);
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #636E72;
    cursor: pointer;
    transition: all 0.2s;
}
.pg-btn:hover:not(:disabled):not(.active) {
    border-color: var(--primary, #e63b6f);
    color: var(--primary, #e63b6f);
}
.pg-btn.active {
    background: var(--primary, #e63b6f);
    color: #fff;
    border-color: var(--primary, #e63b6f);
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
    .product-container { padding: 0 24px 48px; }
}

@media (max-width: 1024px) {
    .products-grid-3 { grid-template-columns: repeat(2, 1fr); }
    .page-hero h1 { font-size: 1.6rem; }
}

@media (max-width: 768px) {
    .page-hero {
        padding: 24px 0 20px;
        min-height: 130px;
        margin-bottom: 16px;
    }
    .page-hero h1 { font-size: 1.35rem; margin-bottom: 4px; }
    .hero-sub { font-size: 0.85rem; line-height: 1.4; }
    .hero-pill { font-size: 0.65rem; padding: 4px 10px; margin-bottom: 8px; }
    .hero-inner-container { padding: 0 16px; }

    .product-container { padding: 0 16px 40px; }
    .product-layout { flex-direction: column; gap: 0; }
    
    /* ── MOBILE FILTER OVERLAY ── */
    .filter-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 1040;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .filter-overlay.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    /* ── MOBILE FILTER DRAWER (SLIDE-IN OFFCANVAS) ── */
    .product-sidebar { 
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: min(340px, 86vw);
        height: 100vh;
        height: 100dvh;
        max-height: none; /* Reset desktop max-height */
        background: var(--card-bg, #ffffff);
        z-index: 1050;
        padding: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border-radius: 0 16px 16px 0;
        box-shadow: 6px 0 30px rgba(0, 0, 0, 0.2);
    }

    .product-sidebar.is-open {
        transform: translateX(0);
    }

    /* Drawer Header */
    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: var(--card-bg, #fff);
        flex-shrink: 0;
    }

    .sidebar-header-title {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sidebar-header-title svg {
        color: var(--primary, #e63b6f);
    }
    .sidebar-header-title h3 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-main);
    }

    .header-count-badge {
        background: var(--primary, #e63b6f);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 999px;
    }

    .sidebar-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .btn-clear-inline {
        background: none;
        border: none;
        color: var(--primary, #e63b6f);
        font-size: 0.84rem;
        font-weight: 700;
        cursor: pointer;
        padding: 4px 6px;
    }

    .close-filter-btn {
        background: #f1f5f9;
        border: none;
        font-size: 1.4rem;
        line-height: 1;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .close-filter-btn:active {
        background: #e2e8f0;
        transform: scale(0.95);
    }

    /* Drawer Body */
    .sidebar-body {
        flex: 1;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding: 16px 20px;
    }

    /* Drawer Footer */
    .sidebar-footer {
        padding: 14px 20px;
        border-top: 1px solid #f1f5f9;
        background: var(--card-bg, #fff);
        display: grid;
        grid-template-columns: 1fr 1.35fr;
        gap: 10px;
        box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.05);
        flex-shrink: 0;
        padding-bottom: calc(14px + env(safe-area-inset-bottom, 0px));
    }

    .btn-reset-drawer {
        padding: 12px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        background: transparent;
        font-weight: 700;
        font-size: 0.88rem;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-reset-drawer:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-apply-drawer {
        padding: 12px 16px;
        border: none;
        border-radius: 10px;
        background: var(--primary, #e63b6f);
        font-weight: 700;
        font-size: 0.88rem;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);
        transition: all 0.2s;
    }
    .btn-apply-drawer:active {
        transform: scale(0.98);
    }
}

@media (max-width: 480px) {
    .product-container { padding: 0 10px 36px; }
    .products-grid-3 { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .pagination { margin-top: 28px; padding-top: 16px; gap: 4px; }
    .pg-btn { width: 34px; height: 34px; font-size: 0.82rem; }
}
</style>
