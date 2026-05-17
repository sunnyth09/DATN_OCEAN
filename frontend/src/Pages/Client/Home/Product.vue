<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../../axios.js";
import { useRouter, useRoute } from "vue-router";
import { useFavorites } from "@/composables/useFavorites";
import ProductCard from "../../../components/ProductCard.vue";

const { isFavorited, toggleFavorite } = useFavorites();
const router = useRouter();
const route = useRoute();
const Products = ref([]);
const Categories = ref([]);
const isSearching = ref(false);

// State lưu trữ bộ lọc
const selectedCategory = ref("All");
const selectedSubcategory = ref("All"); // Thêm state cho sub category
const selectedPriceRange = ref("All");
const sortBy = ref("newest");
const searchQuery = ref(""); // Từ khoá tìm kiếm
const expandedCategories = ref({});  // track danh mục cha nào đang mở

const toggleCategory = (catId) => {
    expandedCategories.value[catId] = !expandedCategories.value[catId];
};

// Dữ liệu Các khoảng giá
const priceRanges = ref([
    { id: "All", label: "Tất cả mức giá" },
    { id: "under-500k", label: "Dưới 500.000₫" },
    { id: "500k-1m", label: "Từ 500.000₫ - 1.000.000₫" },
    { id: "above-1m", label: "Trên 1.000.000₫" },
]);

const currentPage = ref(1);
const totalPages = ref(1);

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    
    if (total <= 7) {
        const pages = [];
        for (let i = 1; i <= total; i++) pages.push(i);
        return pages;
    }
    
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    
    return [1, '...', current - 1, current, current + 1, '...', total];
});

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#E63B6F" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/></svg>`);

const getImageUrl = (path) => {
    if (!path || path === '0') return defaultSvg;
    if (path.startsWith('http')) return path;
    return `${BASE_URL}/storage/${path}`;
};

const fetchProducts = async () => {
    try {
        isSearching.value = true;
        let queryParams = `limit=12&page=${currentPage.value}`;
        
        let targetCategory = selectedSubcategory.value !== "All" ? selectedSubcategory.value : selectedCategory.value;
        if (targetCategory !== "All") {
            queryParams += `&category_id=${targetCategory}`;
        }
        
        if (selectedPriceRange.value !== "All") {
            queryParams += `&price_range=${selectedPriceRange.value}`;
        }
        
        if (sortBy.value) {
            queryParams += `&sort_by=${sortBy.value}`;
        }

        // Gửi từ khoá tìm kiếm lên API
        if (searchQuery.value.trim()) {
            queryParams += `&search=${encodeURIComponent(searchQuery.value.trim())}`;
        }

        const response = await api.get(`/products?${queryParams}`);
        Products.value = response.data.data.map((item) => {
            const lowest = item.lowest_price_variant || item.lowestPriceVariant || null;
            const currentPrice = lowest && lowest.effective_price ? lowest.effective_price : (item.min_price || 0);
            
            let originalPrice = null;
            if (lowest && lowest.is_on_sale) {
                originalPrice = lowest.price;
            } else if (lowest && lowest.compare_at_price > lowest.price) {
                originalPrice = lowest.compare_at_price;
            }

            let maxDiscount = lowest ? lowest.discount_percent || 0 : 0;
            if (item.variants && Array.isArray(item.variants)) {
                const variantsDiscounts = item.variants.map(v => v.discount_percent || 0);
                if (variantsDiscounts.length > 0) {
                    maxDiscount = Math.max(...variantsDiscounts, maxDiscount);
                }
            }

            return {
                id: item.product_id,
                name: item.name,
                price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(currentPrice),
                originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
                discount_percent: maxDiscount,
                is_on_sale: lowest ? lowest.is_on_sale : false,
                image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
                badge: item.is_featured ? "Hot" : null,
                slug: item.slug,
                category_id: item.category_id,
                category_name: item.category ? item.category.name : 'Danh mục',
                date: item.created_at,
            };
        });
        totalPages.value = response.data.total_pages || 1;
    } catch (error) {
        console.error("Error fetching products:", error);
    } finally {
        isSearching.value = false;
    }
};

const fetchCategories = async () => {
    try {
        const response = await api.get("/categories");
        Categories.value = response.data.data;
    } catch (error) {
        console.error("Error fetching categories:", error);
    }
};

// Theo dõi khi danh mục cha thay đổi thì reset danh mục con
watch(selectedCategory, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        selectedSubcategory.value = "All";
    }
});

// Tính toán danh sách danh mục con tùy thuộc vào danh mục cha được chọn
const subcategories = computed(() => {
    if (selectedCategory.value === "All") return [];
    const parent = Categories.value.find(
        (c) => c.category_id === selectedCategory.value,
    );
    return parent && parent.children ? parent.children : [];
});

let isResettingPage = false;

// Lọc dữ liệu được xử lý ở backend, trả về nguyên trạng mảng
const filteredProducts = computed(() => Products.value);

// Lắng nghe sự thay đổi của bộ lọc để fetch lại và đồng bộ ngược lên URL (giúp Header Navigation update active)
watch([selectedCategory, selectedSubcategory, selectedPriceRange, sortBy], () => {
    if (currentPage.value !== 1) {
        isResettingPage = true;
        currentPage.value = 1;
    }
    fetchProducts();
    
    const newQuery = { ...route.query };
    if (selectedCategory.value !== 'All') {
        newQuery.category = selectedCategory.value;
    } else {
        delete newQuery.category;
    }
    
    if (currentPage.value === 1) delete newQuery.page;
    
    router.replace({ query: newQuery }).catch(() => {});
});

// Lắng nghe khi header search điều hướng sang /product?search=...
watch(() => route.query.search, (newSearch) => {
    searchQuery.value = newSearch || '';
    currentPage.value = 1;
    fetchProducts();
});

watch(() => route.query.q, (newQ) => {
    if (newQ) {
        searchQuery.value = newQ;
        currentPage.value = 1;
        fetchProducts();
    }
});

// Lắng nghe khi điều hướng danh mục từ Header
watch(() => route.query.category, (newCategory) => {
    if (newCategory) {
        const cat = Categories.value.find(c => c.category_id == newCategory || c.slug === newCategory);
        if (cat) {
            selectedCategory.value = cat.category_id;
            selectedSubcategory.value = "All";
            expandedCategories.value[cat.category_id] = true;
        }
    } else {
        selectedCategory.value = "All";
        selectedSubcategory.value = "All";
    }
});

const clearSearch = () => {
    router.replace({ path: '/product', query: {} });
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

watch(currentPage, () => {
    if (isResettingPage) {
        isResettingPage = false;
        return; // Đã được xử lý fetch ở trong watch của bộ lọc
    }
    fetchProducts();
    router.replace({ query: { ...route.query, page: currentPage.value } }).catch(()=>{});
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

onMounted(async () => {
    const pageFromUrl = parseInt(route.query.page);
    if (pageFromUrl && pageFromUrl > 0) currentPage.value = pageFromUrl;

    // Đọc từ khoá tìm kiếm từ URL (do header search điều hướng đến)
    if (route.query.search) {
        searchQuery.value = route.query.search;
    } else if (route.query.q) {
        searchQuery.value = route.query.q;
    }

    // Load categories first to match URL params
    await fetchCategories();

    // Xử lý query param ?category=...
    const categoryParam = route.query.category;
    if (categoryParam) {
        const cat = Categories.value.find(c => c.category_id == categoryParam || c.slug === categoryParam);
        if (cat) {
            selectedCategory.value = cat.category_id;
            expandedCategories.value[cat.category_id] = true;
        }
    }
    
    // Now load products with the correct selectedCategory applied
    await fetchProducts();
});
</script>

<template>
    <div class="client-products">
        <main class="products-main-layout">
            <!-- Banner trang sản phẩm -->
            <div class="page-header animate-in">
                <h1>Bộ Sưu Tập Thời Trang</h1>
                <p>
                    Khám phá bộ sưu tập với hàng ngàn phong cách đón đầu mọi xu
                    hướng. Tự do định hình cá tính của bạn.
                </p>
            </div>

            <div class="layout-grid">
                <!-- Cột trái: Bộ lọc Sidebar Nhỏ hơn -->
                <aside class="sidebar animate-in" style="animation-delay: 0.1s">
                    <div class="sidebar-panel">
                        <!-- Header -->
                        <div class="sidebar-header">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            <span>Nhóm sản phẩm</span>
                        </div>

                        <!-- Tất cả sản phẩm -->
                        <div class="tree-item" :class="{ active: selectedCategory === 'All' }" @click="selectedCategory = 'All'">
                            <span class="tree-label">Tất cả sản phẩm</span>
                        </div>

                        <!-- Danh mục cha + con -->
                        <div v-for="cat in Categories" :key="cat.category_id" class="tree-group">
                            <div class="tree-item tree-parent" :class="{ active: selectedCategory === cat.category_id && selectedSubcategory === 'All' }" @click="selectedCategory = cat.category_id; selectedSubcategory = 'All'; if (cat.children && cat.children.length) toggleCategory(cat.category_id)">
                                <span class="tree-label">{{ cat.name }}</span>
                                <svg v-if="cat.children && cat.children.length" class="tree-chevron" :class="{ open: expandedCategories[cat.category_id] }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>

                            <!-- Danh mục con -->
                            <div class="tree-children" v-if="cat.children && cat.children.length" :class="{ expanded: expandedCategories[cat.category_id] }">
                                <div v-for="child in cat.children" :key="child.category_id" class="tree-item tree-child" :class="{ active: selectedSubcategory === child.category_id }" @click="selectedCategory = cat.category_id; selectedSubcategory = child.category_id">
                                    <span class="tree-label">{{ child.name }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Phân cách -->
                        <div class="sidebar-divider"></div>

                        <!-- Mức giá -->
                        <div class="sidebar-subheader">Mức Giá</div>
                        <div v-for="price in priceRanges" :key="price.id" class="tree-item tree-price" :class="{ active: selectedPriceRange === price.id }" @click="selectedPriceRange = price.id">
                            <div class="custom-radio">
                                <div class="radio-inner" v-if="selectedPriceRange === price.id"></div>
                            </div>
                            <span class="tree-label">{{ price.label }}</span>
                        </div>
                    </div>
                </aside>

                <!-- Cột phải: Danh sách sản phẩm (4 thẻ trên 1 hàng) -->
                <section
                    class="products-content animate-in"
                    style="animation-delay: 0.2s"
                >
                    <!-- Hiển thị từ khoá tìm kiếm nếu có -->
                    <div v-if="searchQuery" class="search-result-hint">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Kết quả tìm kiếm cho: <strong>"{{ searchQuery }}"</strong>
                        <button class="clear-search-tag" @click="clearSearch" title="Xóa tìm kiếm">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <!-- Thanh công cụ (Action Bar) -->
                    <div class="action-bar">
                        <div class="results-count">
                            Hiển thị
                            <strong>{{ filteredProducts.length }}</strong> sản
                            phẩm
                        </div>

                        <div class="sort-box">
                            <label for="sortSelector">Sắp xếp:</label>
                            <select
                                id="sortSelector"
                                v-model="sortBy"
                                class="custom-select"
                            >
                                <option value="newest">
                                    Sản phẩm mới nhất
                                </option>
                                <option value="oldest">Sản phẩm cũ nhất</option>
                                <option value="price-asc">
                                    Giá: Thấp đến cao
                                </option>
                                <option value="price-desc">
                                    Giá: Cao đến thấp
                                </option>
                            </select>
                        </div>
                    </div>



                    <div class="products-grid" v-if="filteredProducts.length > 0">
                        <ProductCard
                            v-for="product in filteredProducts"
                            :key="product.id"
                            :product="product"
                        />
                        <div class="pagination" v-if="totalPages > 1">
                            <button class="page-btn" :disabled="currentPage <= 1" @click="prevPage">Trước</button>
                            <template v-for="(item, index) in visiblePages" :key="index">
                                <span v-if="item === '...'" class="page-dots">...</span>
                                <button v-else class="page-btn" :class="{ 'page-btn--active': item === currentPage }" @click="goToPage(item)">{{ item }}</button>
                            </template>
                            <button class="page-btn" :disabled="currentPage >= totalPages" @click="nextPage">Sau</button>
                        </div>
                    </div>

                    <!-- Empty State khi không có sản phẩm -->
                    <div v-else class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>Không tìm thấy sản phẩm nào!</h3>
                        <p v-if="searchQuery">
                            Không có sản phẩm nào khớp với từ khoá <strong>"{{ searchQuery }}"</strong>.
                        </p>
                        <p v-else>
                            Không có sản phẩm nào phù hợp với bộ lọc bạn vừa chọn.
                        </p>
                        <button
                            class="btn-outline"
                            @click="
                                selectedCategory = 'All';
                                selectedPriceRange = 'All';
                                clearSearch();
                            "
                        >
                            Xóa bộ lọc
                        </button>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.client-products {
    font-family: var(--font-jakarta, "Inter", sans-serif);
    width: 100%;
    color: var(--text-main, #102a43);
    min-height: 100vh;
}

/* Layout Container kéo rộng ra một tí để chứa 4 cột */
.products-main-layout {
    padding: 24px 0;
    width: 100%;
}

/* Header */
.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    font-size: 2.25rem;
    font-weight: 800;
    color: #E63B6F;
    margin-bottom: 12px;
}

.page-header p {
    font-size: 1.05rem;
    color: var(--text-muted, #627d98);
    max-width: 600px;
    margin: 0 auto;
}

/* Main Grid (Sidebar + Content) */
.layout-grid {
    display: flex;
    gap: 24px;
    /* Khoảng cách gọn lại */
    align-items: flex-start;
}

/* ======== SIDEBAR thu nhỏ lại ======== */
.sidebar {
    width: 250px;
    /* Cũ là 280px -> Thu gọn lại theo ý muốn người dùng */
    flex-shrink: 0;
    position: sticky;
    top: 40px;
}

/* ====== Tree-View Sidebar ====== */
.sidebar-panel {
    background: transparent;
    border-radius: 0;
    border: none;
    box-shadow: none;
    overflow: hidden;
}

.sidebar-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #2D3436;
    border-bottom: 1px solid #E9ECEF;
    background: transparent;
}

.tree-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 0;
    cursor: pointer;
    font-size: 0.92rem;
    font-weight: 500;
    color: #636E72;
    transition: all 0.18s ease;
    border-left: none;
}

.tree-item:hover {
    background: transparent;
    color: #E63B6F;
}

.tree-item.active {
    background: transparent;
    color: #E63B6F;
    font-weight: 700;
}

.tree-parent {
    justify-content: space-between;
}

.tree-parent .tree-label {
    flex: 1;
}

.tree-chevron {
    color: #b0b8c9;
    transition: transform 0.3s ease, color 0.2s;
    flex-shrink: 0;
}

.tree-item:hover .tree-chevron {
    color: #0369a1;
}

.tree-chevron.open {
    transform: rotate(180deg);
    color: #0369a1;
}

.tree-children {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: #fafbfd;
}

.tree-children.expanded {
    max-height: 400px;
}

.tree-child {
    padding: 10px 20px 10px 38px;
    font-size: 0.88rem;
    color: #6b7f96;
    border-left: 3px solid transparent;
}

.tree-child:hover {
    color: #0369a1;
    background: #eef4f9;
}

.tree-child.active {
    color: #0369a1;
    font-weight: 700;
    background: rgba(3, 105, 161, 0.06);
    border-left-color: #0369a1;
}

.sidebar-divider {
    height: 1px;
    background: #eef0f4;
    margin: 6px 16px;
}

.sidebar-subheader {
    padding: 14px 20px 6px;
    font-size: 0.95rem;
    font-weight: 800;
    color: #1a2b4a;
}

.tree-price {
    gap: 12px;
}

.custom-radio {
    width: 17px;
    height: 17px;
    border: 1px solid #c8ced8;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.tree-price.active .custom-radio {
    border-color: #E63B6F;
}

.radio-inner {
    width: 10px;
    height: 10px;
    background: #E63B6F;
    border-radius: 50%;
}

/* ======== MAIN CONTENT ======== */
.products-content {
    flex: 1;
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: transparent;
    padding: 14px 0;
    border-radius: 12px;
    box-shadow: none;
    border: none;
    border-bottom: 1px solid #E9ECEF;
}

.results-count {
    font-size: 0.95rem;
    color: #636E72;
}

.results-count strong {
    color: #2D3436;
    font-weight: 800;
}

.sort-box {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sort-box label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #2D3436;
}

.custom-select {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #c9d6df;
    background: white;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    color: #2D3436;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
}

.custom-select:focus {
    border-color: #E63B6F;
}

/* ======== SEARCH RESULT HINT ======== */
.search-result-hint {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding: 8px 14px;
    background: rgba(230, 59, 111, 0.07);
    border: 1px solid rgba(230, 59, 111, 0.2);
    border-radius: 999px;
    font-size: 0.88rem;
    color: var(--text-muted, #627d98);
}

.search-result-hint strong {
    color: #E63B6F;
    font-weight: 700;
}

.search-result-hint svg {
    color: #E63B6F;
    flex-shrink: 0;
}

.clear-search-tag {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    display: flex;
    align-items: center;
    padding: 2px;
    border-radius: 50%;
    transition: background 0.18s, color 0.18s;
    margin-left: 2px;
}

.clear-search-tag:hover {
    background: rgba(230, 59, 111, 0.15);
    color: #E63B6F;
}
/* ======== PRODUCTS GRID (Đúng 4 cột hàng ngang) ======== */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

/* Pagination */
.pagination {
    grid-column: 1 / -1;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e8ecf1;
}

.page-btn {
    padding: 8px 14px;
    border: 1px solid #E9ECEF;
    background: transparent;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    color: #636E72;
    transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
    background: #E63B6F;
    border-color: #E63B6F;
    color: white;
}

.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-btn--active {
    background: #E63B6F !important;
    color: white !important;
    border-color: #E63B6F !important;
}

/* ======= OVERRIDES CLEANUP ======= */

/* ======= EMPTY STATE ======= */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: transparent;
    border-radius: 12px;
    border: 1px dashed #E63B6F;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.35rem;
    color: #E63B6F;
    margin-bottom: 8px;
}

.empty-state p {
    color: #636E72;
    margin-bottom: 24px;
}

.btn-outline {
    background: transparent;
    color: #E63B6F;
    border: 1px solid #E63B6F;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline:hover {
    background: #E63B6F;
    color: white;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 1024px) {
    .products-grid {
        /* Màn hình nhỏ thì lùi xuống 3 ô ngang */
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    .layout-grid {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        position: static;
    }

    .products-grid {
        /* Tablet thì xuống 2 */
        grid-template-columns: repeat(2, 1fr);
    }

    .action-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}

@media (max-width: 480px) {
    .products-grid {
        gap: 8px;
    }
}
</style>
