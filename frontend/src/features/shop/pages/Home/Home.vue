<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { storeToRefs } from "pinia";
import ProductCard from "@/components/ProductCard.vue";
import ProductSkeleton from "@/components/ProductSkeleton.vue";
import AppIcon from "@/components/AppIcon.vue";
import { useCatalogStore } from "@/stores/catalog";
import { catalogService, extractCollection } from "@/services/catalogService";
import { getAppBaseUrl, getStorageUrl } from '@/utils/url';

const BASE_URL = getAppBaseUrl();

const Products = ref([]);
const Categories = ref([]);
const isLoadingFeatured = ref(true);
const isLoadingCategories = ref(true);
const catalogStore = useCatalogStore();
const { categories: storeCategories } = storeToRefs(catalogStore);

// ── Tab filter ──
const activeTab = ref('all');

const filteredProducts = computed(() => {
    if (activeTab.value === 'sale') {
        return Products.value.filter(p =>
            p.is_on_sale ||
            (p.discount_percent && p.discount_percent > 0) ||
            (p.original_price && p.original_price > p.min_price)
        );
    }
    return Products.value;
});

// ── Flash Sale Countdown ──
const countdown = ref({ h: '00', m: '00', s: '00' });
let countdownTimer = null;

const updateCountdown = () => {
    const now = new Date();
    const endOfDay = new Date();
    endOfDay.setHours(23, 59, 59, 999);
    const diff = endOfDay - now;
    const h = Math.floor(diff / 3_600_000);
    const m = Math.floor((diff % 3_600_000) / 60_000);
    const s = Math.floor((diff % 60_000) / 1_000);
    countdown.value = {
        h: String(h).padStart(2, '0'),
        m: String(m).padStart(2, '0'),
        s: String(s).padStart(2, '0'),
    };
};

const flashSaleProducts = computed(() =>
    Products.value.filter(p => p.is_on_sale || p.discount_percent > 0).slice(0, 4)
);

// ── Category helpers ──
const catIcons = ['⚽', '🏀', '🎾', '🏊', '🥊', '🏋️', '🎽', '👟'];
const getCatIcon = (idx) => catIcons[idx % catIcons.length];

const catGradients = [
    'linear-gradient(135deg,#667eea,#764ba2)',
    'linear-gradient(135deg,#f093fb,#f5576c)',
    'linear-gradient(135deg,#4facfe,#00f2fe)',
    'linear-gradient(135deg,#43e97b,#38f9d7)',
    'linear-gradient(135deg,#fa709a,#fee140)',
    'linear-gradient(135deg,#a18cd1,#fbc2eb)',
];
const getCatGradient = (idx) => catGradients[idx % catGradients.length];

const getImageUrl = (path) => {
    if (!path || path === '0') return '';
    return getStorageUrl(path);
};

const getCategoryImage = (cat) => {
    if (cat.image) return getImageUrl(cat.image);
    if (cat.image_url) return getImageUrl(cat.image_url);
    return '';
};

const mapProduct = (item) => {
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
        category_name: item.category_name || '',
        variants_sum_stock: item.variants_sum_stock ?? null,
        variants: item.variants ?? [],
    };
};

const fetchCategories = async () => {
    isLoadingCategories.value = true;
    try {
        await catalogStore.fetchCategories();
        const data = storeCategories.value || [];
        Categories.value = (Array.isArray(data) ? data : []).map(cat => ({
            id: cat.category_id || cat.id,
            name: cat.name,
            slug: cat.slug || '',
            image: getCategoryImage(cat),
            product_count: cat.products_count || cat.product_count || 0,
        }));
    } catch (e) {
        console.error('Lỗi tải danh mục:', e);
    } finally {
        isLoadingCategories.value = false;
    }
};

const fetchProducts = async () => {
    isLoadingFeatured.value = true;
    try {
        const res = await catalogService.listProducts({ limit: 8, sort: 'newest' });
        const rawData = extractCollection(res);
        Products.value = (Array.isArray(rawData) ? rawData : []).map(mapProduct);
    } catch (e) {
        console.error('Lỗi tải sản phẩm:', e);
    } finally {
        isLoadingFeatured.value = false;
    }
};

const sideCategories = computed(() => Categories.value.slice(1, 3));
const featuredProduct = computed(() =>
    Products.value.find(p => p.badge === 'Hot' || p.is_on_sale) || Products.value[0] || null
);

// ── Testimonials ──
const testimonials = [
    {
        id: 1, name: 'Nguyễn Minh Tuấn', role: 'VĐV Bóng đá Phong trào',
        avatar: 'https://i.pravatar.cc/80?img=12',
        text: 'Sản phẩm chất lượng tuyệt vời! Giày tôi mua ở đây đã đi được 6 tháng mà vẫn như mới. Giao hàng nhanh, đóng gói cẩn thận. Sẽ tiếp tục ủng hộ Quyền Sport.'
    },
    {
        id: 2, name: 'Trần Thị Lan Anh', role: 'Huấn Luyện Viên Yoga',
        avatar: 'https://i.pravatar.cc/80?img=25',
        text: 'Mình đã mua đồ tập yoga ở đây được 2 năm rồi. Chất liệu co giãn tốt, màu sắc đẹp và bền màu. Giá cả hợp lý so với chất lượng. Rất hài lòng!'
    },
    {
        id: 3, name: 'Lê Hoàng Nam', role: 'Runner Nghiệp Dư',
        avatar: 'https://i.pravatar.cc/80?img=33',
        text: 'Tìm được đôi giày chạy bộ ưng ý nhất từ trước đến nay tại đây. Nhân viên tư vấn nhiệt tình, hiểu rõ nhu cầu. Shop uy tín, hàng chính hãng 100%.'
    }
];

// ── Brands ──
const brands = [
    { name: 'Nike', logo: 'https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg' },
    { name: 'Adidas', logo: 'https://upload.wikimedia.org/wikipedia/commons/2/20/Adidas_Logo.svg' },
    { name: 'Puma', logo: 'https://upload.wikimedia.org/wikipedia/commons/b/b3/Puma_AG_Logo.svg' },
    { name: 'Under Armour', logo: 'https://upload.wikimedia.org/wikipedia/commons/4/44/Under_armour_logo.svg' },
    { name: 'Asics', logo: 'https://upload.wikimedia.org/wikipedia/commons/b/b1/Asics_Logo.svg' },
    { name: 'New Balance', logo: 'https://upload.wikimedia.org/wikipedia/commons/e/ea/New_Balance_logo.svg' },
];

onMounted(() => {
    fetchCategories();
    fetchProducts();
    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);
});
onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });
</script>

<template>
    <main class="home-main">
        <section class="hero-section">
            <div class="hero-bg">
                <img :src="BASE_URL + '/storage/banners/banner_1.jpg'" alt="hero" class="hero-bg-img" />
                <div class="hero-overlay"></div>
            </div>
            <div class="container hero-content-wrap">
                <div class="hero-stat--top-right d-none d-lg-flex mt-5">
                    <div class="hero-stat-item">
                        <span class="hero-stat-num">50K+</span>
                        <span class="hero-stat-label">Khách hàng</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num">1000+</span>
                        <span class="hero-stat-label">Sản phẩm</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat-item">
                        <span class="hero-stat-num">100%</span>
                        <span class="hero-stat-label">Chính hãng</span>
                    </div>
                </div>
                <div class="hero-content">
                    <span class="hero-tag">BỘ SƯU TẬP MỚI 2026</span>
                    <h1 class="hero-title">
                        Tốc độ.<br />Sức mạnh.<br /><em>Chiến thắng.</em>
                    </h1>
                    <p class="hero-desc">Khám phá những thiết bị chất lượng cao, được thiết kế để nâng tầm kỹ năng
                        và đưa bạn đến chiến thắng. Đam mê bắt đầu từ đây.</p>
                    <div class="d-flex gap-3 flex-wrap hero-btns">
                        <router-link to="/product" class="btn-primary-hero">
                            Khám phá ngay
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </router-link>
                        <router-link to="/flash-sale" class="btn-outline-hero">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                            </svg>
                            Flash Sale
                        </router-link>
                    </div>
                </div>
            </div>

        </section>

        <!-- ══════════════════════════════════════════
             2. BENEFITS BAR — Full Width
        ══════════════════════════════════════════ -->
        <section class="benefits-bar">
            <div class="container">
                <div class="benefits-inner">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="shipping" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Miễn phí vận chuyển</span>
                            <span class="benefit-sub">Đơn hàng từ 500K</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="return" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Đổi trả 30 ngày</span>
                            <span class="benefit-sub">Không cần lý do</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="payment" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Thanh toán bảo mật</span>
                            <span class="benefit-sub">SSL 256-bit</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="shield" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Hàng chính hãng 100%</span>
                            <span class="benefit-sub">Cam kết đảm bảo</span>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <AppIcon name="heart" />
                        </div>
                        <div class="benefit-text">
                            <span class="benefit-title">Hỗ trợ 24/7</span>
                            <span class="benefit-sub">Tư vấn nhiệt tình</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             3. FLASH SALE COUNTDOWN — Full Width Dark
        ══════════════════════════════════════════ -->
        <section class="flash-sale-section" v-if="flashSaleProducts.length > 0 || isLoadingFeatured">
            <div class="container py-5">
                <!-- Header row -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flash-sale-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="flash-sale-title mb-0">FLASH SALE</h2>
                            <p class="flash-sale-sub mb-0">Ưu đãi sốc — Số lượng có hạn!</p>
                        </div>
                    </div>
                    <!-- Countdown -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="countdown-label d-none d-sm-inline">Kết thúc sau:</span>
                        <div class="d-flex align-items-center gap-1">
                            <div class="countdown-block">
                                <span class="countdown-num">{{ countdown.h }}</span>
                                <span class="countdown-unit">GIỜ</span>
                            </div>
                            <span class="countdown-sep">:</span>
                            <div class="countdown-block">
                                <span class="countdown-num">{{ countdown.m }}</span>
                                <span class="countdown-unit">PHÚT</span>
                            </div>
                            <span class="countdown-sep">:</span>
                            <div class="countdown-block">
                                <span class="countdown-num">{{ countdown.s }}</span>
                                <span class="countdown-unit">GIÂY</span>
                            </div>
                        </div>
                    </div>
                    <router-link to="/flash-sale" class="flash-sale-link">
                        Xem tất cả
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>
                <!-- Products grid Bootstrap row -->
                <div class="row g-4">
                    <template v-if="isLoadingFeatured">
                        <div v-for="i in 4" :key="i" class="col-6 col-md-4 col-lg-3">
                            <div class="skeleton-pulse" style="min-height:320px;border-radius:12px;"></div>
                        </div>
                    </template>
                    <template v-else-if="flashSaleProducts.length > 0">
                        <div v-for="product in flashSaleProducts" :key="'flash-' + product.id" class="col-6 col-md-4 col-lg-3">
                            <ProductCard :product="product" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-12 text-center py-5">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                stroke-width="1.5">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                            </svg>
                            <p class="mt-3 text-muted">Flash Sale sẽ sớm trở lại!</p>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             4. DANH MỤC NỔI BẬT — Container width
        ══════════════════════════════════════════ -->
        <section class="section-categories py-5 bg-light">
            <div class="container">
                <!-- Section head -->
                <div class="d-flex align-items-end justify-content-between mb-4">
                    <div>
                        <h2 class="section-title mb-1">DANH MỤC NỔI BẬT</h2>
                        <p class="section-subtitle mb-0">Tìm kiếm sản phẩm theo thể thao yêu thích của bạn.</p>
                    </div>
                    <router-link to="/product" class="link-more d-flex align-items-center gap-1">
                        Tất cả danh mục
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>
                <!-- Categories Bootstrap row -->
                <div class="row g-3 justify-content-center">
                    <template v-if="isLoadingCategories">
                        <div v-for="i in 6" :key="i" class="col-6 col-sm-4 col-lg-2">
                            <div class="skeleton-pulse cat-card-shell"></div>
                        </div>
                    </template>
                    <template v-else-if="Categories.length > 0">
                        <div v-for="(cat, idx) in Categories.slice(0, 6)" :key="cat.id" class="col-6 col-sm-4 col-lg-2">
                            <router-link :to="'/product?category=' + cat.id" class="cat-card">
                                <div class="cat-card-bg" :style="{ background: getCatGradient(idx) }"></div>
                                <div class="cat-card-img" v-if="cat.image">
                                    <img :src="cat.image" :alt="cat.name" />
                                </div>
                                <div class="cat-card-icon" v-else>{{ getCatIcon(idx) }}</div>
                                <div class="cat-card-info">
                                    <span class="cat-card-name">{{ cat.name }}</span>
                                    <span class="cat-card-count" v-if="cat.product_count">{{ cat.product_count }} SP</span>
                                </div>
                            </router-link>
                        </div>
                    </template>
                    <template v-else>
                        <div v-for="(icon, idx) in catIcons.slice(0, 6)" :key="idx" class="col-6 col-sm-4 col-lg-2">
                            <router-link to="/product" class="cat-card">
                                <div class="cat-card-bg" :style="{ background: getCatGradient(idx) }"></div>
                                <div class="cat-card-icon">{{ icon }}</div>
                                <div class="cat-card-info">
                                    <span class="cat-card-name">Thể thao {{ idx + 1 }}</span>
                                </div>
                            </router-link>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             5. TRANG BỊ THIẾT YẾU — Container width
        ══════════════════════════════════════════ -->
        <section class="py-5">
            <div class="container">
                <div class="d-flex align-items-end justify-content-between mb-4">
                    <div>
                        <h2 class="section-title mb-1">TRANG BỊ THIẾT YẾU</h2>
                        <p class="section-subtitle mb-0">Lựa chọn hàng đầu cho vận động viên.</p>
                    </div>
                    <router-link to="/product" class="link-more d-flex align-items-center gap-1">
                        Xem tất cả
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>

                <!-- Loading -->
                <div v-if="isLoadingCategories || isLoadingFeatured" class="row g-4">
                    <div class="col-lg-7">
                        <div class="skeleton-pulse" style="min-height:420px;border-radius:16px;"></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-4 h-100">
                            <div class="col-12">
                                <div class="skeleton-pulse" style="min-height:190px;border-radius:16px;"></div>
                            </div>
                            <div class="col-12">
                                <div class="skeleton-pulse" style="min-height:190px;border-radius:16px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div v-else class="row g-4">
                    <!-- Big featured product -->
                    <div class="col-lg-7" v-if="featuredProduct">
                        <div class="equip-big-card h-100">
                            <div class="equip-big-img">
                                <img :src="featuredProduct.image" :alt="featuredProduct.name" />
                            </div>
                            <div class="d-flex flex-column align-items-start gap-3 mt-4">
                                <span v-if="featuredProduct.discount_percent > 0" class="equip-badge">
                                    Giảm {{ featuredProduct.discount_percent }}%
                                </span>
                                <span v-else-if="featuredProduct.badge" class="equip-badge">{{ featuredProduct.badge
                                    }}</span>
                                <h3 class="equip-big-name mb-0">{{ featuredProduct.name }}</h3>
                                <p class="equip-big-desc mb-0">
                                    <span v-if="featuredProduct.category_name">{{ featuredProduct.category_name }}
                                        &middot;
                                    </span>
                                    <strong class="text-danger">{{ featuredProduct.price }}</strong>
                                </p>
                                <router-link :to="'/product/' + (featuredProduct.slug || featuredProduct.id)"
                                    class="btn-buy-now btn">
                                    Mua ngay
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Side category cards -->
                    <div class="col-lg-5">
                        <div class="d-flex flex-column gap-4 h-100">
                            <template v-if="sideCategories.length > 0">
                                <router-link v-for="cat in sideCategories" :key="cat.id"
                                    :to="'/product?category=' + cat.id" class="equip-small-card flex-fill">
                                    <div class="equip-small-info">
                                        <h4 class="equip-small-name mb-1">{{ cat.name }}</h4>
                                        <span class="equip-small-link">
                                            Xem thêm
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="3">
                                                <path d="M9 18l6-6-6-6" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="equip-small-img" v-if="cat.image">
                                        <img :src="cat.image" :alt="cat.name" />
                                    </div>
                                </router-link>
                            </template>
                            <div v-if="sideCategories.length < 2"
                                class="equip-small-card equip-small-card--empty flex-fill">
                                <div class="text-center">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                                        <circle cx="10" cy="10" r="5.5" stroke="#E63B6F" stroke-width="2.5" />
                                        <path d="M14 14L18.5 18.5" stroke="#E63B6F" stroke-width="3"
                                            stroke-linecap="round" />
                                        <circle cx="17.5" cy="12.5" r="2.5" fill="#E63B6F" />
                                    </svg>
                                    <p class="mb-0 mt-2 fw-bold small">Khám phá thêm</p>
                                    <p class="mb-0 text-muted" style="font-size:.8rem;">Xem tất cả danh mục</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             6. SẢN PHẨM BÁN CHẠY — Container width
        ══════════════════════════════════════════ -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="section-title accent-title mb-2">SẢN PHẨM BÁN CHẠY</h2>
                    <p class="section-subtitle">Được yêu thích nhất bởi cộng đồng thể thao Việt Nam</p>
                </div>
                <!-- Tab filter -->
                <div class="d-flex justify-content-center gap-2 mb-4">
                    <button class="tab-btn" :class="{ active: activeTab === 'all' }" @click="activeTab = 'all'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #f97316; margin-right: 4px; margin-bottom: 2px;">
                            <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
                        </svg> Tất cả
                    </button>
                    <button class="tab-btn" :class="{ active: activeTab === 'sale' }" @click="activeTab = 'sale'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #eab308; margin-right: 4px; margin-bottom: 2px;">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg> Đang Sale
                    </button>
                </div>
                <!-- Products Bootstrap row -->
                <div class="row g-4">
                    <template v-if="isLoadingFeatured">
                        <div v-for="i in 8" :key="i" class="col-6 col-md-4 col-lg-3">
                            <ProductSkeleton />
                        </div>
                    </template>
                    <template v-else-if="filteredProducts.length > 0">
                        <div v-for="product in filteredProducts.slice(0, 8)" :key="product.id"
                            class="col-6 col-md-4 col-lg-3">
                            <ProductCard :product="product" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="col-12 text-center py-5 text-muted">
                            <p>Chưa có sản phẩm nào. Vui lòng quay lại sau!</p>
                        </div>
                    </template>
                </div>
                <!-- View more -->
                <div class="text-center mt-5" v-if="Products.length > 0">
                    <router-link to="/product" class="btn-view-more">
                        Xem thêm sản phẩm
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             7. DUAL PROMO BANNERS — Container width
        ══════════════════════════════════════════ -->
        <section class="py-5">
            <div class="container">
                <div class="row g-4">
                    <!-- Banner 1 -->
                    <div class="col-md-6">
                        <div class="promo-banner promo-banner--dark">
                            <div class="promo-banner-content">
                                <span class="promo-banner-tag">ƯU ĐÃI ĐỘC QUYỀN</span>
                                <h3 class="promo-banner-title">Giảm <em>20%</em><br />đơn hàng đầu tiên</h3>
                                <p class="promo-banner-desc">Đăng ký tài khoản ngay hôm nay để nhận mã giảm giá 20% cho
                                    đơn hàng
                                    đầu tiên.</p>
                                <router-link to="/client/register" class="promo-banner-btn">
                                    Đăng ký ngay
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </router-link>
                            </div>
                            <div class="promo-banner-deco">
                                <div class="deco-circle deco-c1"></div>
                                <div class="deco-circle deco-c2"></div>
                                <span class="deco-badge">20% OFF</span>
                            </div>
                        </div>
                    </div>
                    <!-- Banner 2 -->
                    <div class="col-md-6">
                        <div class="promo-banner promo-banner--red">
                            <div class="promo-banner-content">
                                <span class="promo-banner-tag">MỖI NGÀY</span>
                                <h3 class="promo-banner-title">Flash Sale<br /><em>12:00 – 14:00</em></h3>
                                <p class="promo-banner-desc">Giảm đến 50% cho hàng trăm sản phẩm thể thao mỗi ngày trong
                                    khung
                                    giờ vàng.</p>
                                <router-link to="/flash-sale" class="promo-banner-btn promo-banner-btn--outline">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10" />
                                    </svg>
                                    Xem Flash Sale
                                </router-link>
                            </div>
                            <div class="promo-banner-deco">
                                <div class="deco-circle deco-c3"></div>
                                <div class="deco-circle deco-c4"></div>
                                <span class="deco-badge deco-badge--light">⚡ HOT</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             8. THƯƠNG HIỆU ĐỐI TÁC — Full Width Marquee
        ══════════════════════════════════════════ -->
        <section class="py-5 border-top">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="section-title mb-2">THƯƠNG HIỆU ĐỐI TÁC</h2>
                    <p class="section-subtitle">Chúng tôi phân phối chính hãng từ các thương hiệu hàng đầu thế giới</p>
                </div>
            </div>
            <div class="brands-marquee-wrap">
                <div class="brands-track">
                    <div v-for="(brand, idx) in [...brands, ...brands]" :key="idx + '-brand'" class="brand-logo-item">
                        <img :src="brand.logo" :alt="brand.name" :title="brand.name" loading="lazy" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             9. TESTIMONIALS — Container width
        ══════════════════════════════════════════ -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-2">KHÁCH HÀNG NÓI GÌ?</h2>
                    <p class="section-subtitle">Hơn 50,000 khách hàng đã tin tưởng và yêu thích Quyền Sport</p>
                </div>
                <div class="row g-4">
                    <div v-for="t in testimonials" :key="t.id" class="col-md-4">
                        <div class="testimonial-card h-100">
                            <div class="d-flex gap-1 mb-3">
                                <svg v-for="s in 5" :key="s" width="16" height="16" viewBox="0 0 24 24" fill="#FBBF24">
                                    <polygon
                                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            </div>
                            <p class="testimonial-text">"{{ t.text }}"</p>
                            <div class="d-flex align-items-center gap-3 pt-3 border-top">
                                <img :src="t.avatar" :alt="t.name" class="testimonial-avatar" />
                                <div>
                                    <span class="d-block fw-bold" style="font-size:.9rem;">{{ t.name }}</span>
                                    <span class="d-block text-muted" style="font-size:.78rem;">{{ t.role }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             10. COMMUNITY — Full Width
        ══════════════════════════════════════════ -->
        <section class="community-section">
            <div class="row g-0" style="min-height:520px;">
                <!-- Left content -->
                <div class="col-lg-6 community-content">
                    <div class="container-fluid px-0">
                        <div class="community-inner-pad">
                            <span class="community-tag mb-3 d-flex align-items-center gap-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                CỘNG ĐỒNG QUYỀN SPORT
                            </span>
                            <h2 class="community-title mb-0">Hơn cả một cửa hàng.<br />Chúng tôi là <em>đam mê</em>.
                            </h2>
                            <!-- Stats row -->
                            <div class="d-flex gap-4 py-4 my-3 community-stats-row">
                                <div>
                                    <div class="community-stat-num">10,000+</div>
                                    <div class="community-stat-label">Thành viên</div>
                                </div>
                                <div>
                                    <div class="community-stat-num">500+</div>
                                    <div class="community-stat-label">Buổi Workshop</div>
                                </div>
                                <div>
                                    <div class="community-stat-num">50+</div>
                                    <div class="community-stat-label">Đối tác</div>
                                </div>
                            </div>
                            <p class="community-desc mb-4">Tham gia câu lạc bộ của chúng tôi để nhận ưu đãi độc quyền,
                                kết nối
                                với cộng đồng yêu thể thao và nâng cao kỹ năng qua các buổi giao lưu.</p>
                            <ul class="community-list mb-4">
                                <li class="d-flex align-items-center gap-2">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                        stroke-width="2.5">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                        <polyline points="22 4 12 14.01 9 11.01" />
                                    </svg>
                                    Giảm giá 10% cho mọi đơn hàng.
                                </li>
                                <li class="d-flex align-items-center gap-2">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                        stroke-width="2.5">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                        <polyline points="22 4 12 14.01 9 11.01" />
                                    </svg>
                                    Tham gia các buổi workshop nâng cao kỹ năng miễn phí.
                                </li>
                                <li class="d-flex align-items-center gap-2">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F"
                                        stroke-width="2.5">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                        <polyline points="22 4 12 14.01 9 11.01" />
                                    </svg>
                                    Cập nhật sản phẩm mới nhất trước mọi người.
                                </li>
                            </ul>
                            <router-link to="/client/register" class="btn-community">
                                Đăng ký tham gia ngay
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path d="M5 12h14M12 5l7 7-7 7" />
                                </svg>
                            </router-link>
                        </div>
                    </div>
                </div>
                <!-- Right image -->
                <div class="col-lg-6 community-images">
                    <img src="https://images.unsplash.com/photo-1526232761682-d26e03ac148e?w=800&q=80" alt="community"
                        class="community-img" />
                </div>
            </div>
        </section>

    </main>
</template>

<style scoped>
/* ============================================
   HOME WRAPPER
============================================ */
.home-main {
    width: 100%;
    padding: 0;
    overflow-x: clip;
}

/* ============================================
   SHARED TYPOGRAPHY
============================================ */
.section-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.5px;
    margin: 0;
}

.section-subtitle {
    color: #636E72;
    font-size: 0.95rem;
}

.accent-title::after {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), #ff8fab);
    border-radius: 4px;
    margin: 8px auto 0;
}

.link-more {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    white-space: nowrap;
    transition: gap 0.2s;
}

.link-more:hover {
    color: #d82f65;
}

/* ============================================
   1. HERO
============================================ */
.hero-section {
    position: relative;
    width: 100%;
    height: 620px;
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    inset: 0;
}

.hero-bg-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 30%;
    animation: heroZoom 12s ease-in-out infinite alternate;
}

@keyframes heroZoom {
    from {
        transform: scale(1);
    }

    to {
        transform: scale(1.06);
    }
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg, rgba(15, 15, 25, .92) 0%, rgba(15, 15, 25, .65) 45%, rgba(15, 15, 25, .2) 75%);
}

/* Floating stats */
.hero-stat--top-right {
    position: absolute;
    top: 40px;
    right: 12px;
    z-index: 3;
    align-items: center;
    gap: 24px;
    background: rgba(255, 255, 255, .1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, .2);
    border-radius: 16px;
    padding: 16px 24px;
    animation: fadeInDown .8s ease both .4s;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.hero-stat-num {
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.hero-stat-label {
    font-size: .7rem;
    font-weight: 500;
    color: rgba(255, 255, 255, .7);
    letter-spacing: .5px;
}

.hero-stat-divider {
    width: 1px;
    height: 36px;
    background: rgba(255, 255, 255, .2);
}

/* Hero content */
.hero-content-wrap {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.hero-content {
    max-width: 540px;
}

.hero-tag {
    display: inline-block;
    background: rgba(230, 59, 111, .2);
    border: 1px solid rgba(230, 59, 111, .4);
    color: #ffb2bf;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: 2px;
    padding: 6px 20px;
    border-radius: 99px;
    margin-bottom: 20px;
    text-transform: uppercase;
    animation: fadeInLeft .7s ease both;
}

.hero-title {
    font-size: 4rem;
    font-weight: 900;
    line-height: 1.08;
    color: #fff;
    letter-spacing: -1.5px;
    margin: 0 0 20px;
    animation: fadeInLeft .7s ease both .1s;
}

.hero-title em {
    font-style: italic;
    color: var(--primary);
    text-shadow: 0 0 40px rgba(230, 59, 111, .5);
}

.hero-desc {
    font-size: 1rem;
    color: #cbd5e1;
    line-height: 1.7;
    margin-bottom: 32px;
    animation: fadeInLeft .7s ease both .2s;
}

.hero-btns {
    animation: fadeInLeft .7s ease both .3s;
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.btn-primary-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    color: #fff;
    border: none;
    padding: 14px 36px;
    border-radius: 99px;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s;
    box-shadow: 0 6px 24px rgba(230, 59, 111, .45);
    text-decoration: none;
}

.btn-primary-hero:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(230, 59, 111, .55);
    color: #fff;
}

.btn-outline-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    color: #fff;
    border: 2px solid rgba(255, 255, 255, .4);
    padding: 12px 24px;
    border-radius: 99px;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .3s;
    text-decoration: none;
}

.btn-outline-hero:hover {
    border-color: var(--primary);
    background: rgba(230, 59, 111, .15);
    color: #ffb2bf;
}

/* Scroll indicator */
.hero-scroll-indicator {
    position: absolute;
    bottom: 32px;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 40px;
    border: 2px solid rgba(255, 255, 255, .4);
    border-radius: 12px;
    justify-content: center;
    padding-top: 6px;
}

.scroll-dot {
    width: 4px;
    height: 8px;
    background: var(--card-bg);
    border-radius: 2px;
    animation: scrollBounce 1.8s ease-in-out infinite;
}

@keyframes scrollBounce {

    0%,
    100% {
        transform: translateY(0);
        opacity: 1;
    }

    50% {
        transform: translateY(10px);
        opacity: .3;
    }
}

/* ============================================
   2. BENEFITS BAR
============================================ */
.benefits-bar {
    background: #ffffff;
    padding: 35px 0;
    position: relative;
    z-index: 10;
}

.benefits-inner {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    background: #ffffff;
    border-radius: 20px;
    padding: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03), 0 1px 3px rgba(0, 0, 0, 0.01);
    border: 1px solid rgba(0, 0, 0, 0.035);
}

.benefit-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: transparent;
    cursor: pointer;
}

.benefit-item:hover {
    background: #fff5f7;
    transform: translateY(-4px);
    box-shadow: 0 12px 20px rgba(230, 59, 111, 0.06);
}

.benefit-item:hover .benefit-icon {
    transform: scale(1.1) rotate(5deg);
    color: var(--primary);
    background: #ffe5ed;
}

.benefit-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 12px;
    color: #64748b;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.benefit-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.benefit-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    transition: color 0.3s;
}

.benefit-item:hover .benefit-title {
    color: var(--primary);
}

.benefit-sub {
    font-size: 0.78rem;
    color: #64748b;
}

@media (max-width: 1200px) {
    .benefits-inner {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .benefits-inner {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 8px;
    }
    .benefit-item {
        padding: 14px 16px;
    }
}

@media (max-width: 480px) {
    .benefits-inner {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   3. FLASH SALE
============================================ */
.flash-sale-section {
    background: linear-gradient(135deg, #0f0f1a 0%, #1a0a1e 50%, #1f0d0d 100%);
}

.flash-sale-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
    animation: flashPulse 1.5s ease-in-out infinite;
}

@keyframes flashPulse {

    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(230, 59, 111, .5);
    }

    50% {
        box-shadow: 0 0 0 12px rgba(230, 59, 111, 0);
    }
}

.flash-sale-title {
    font-size: 1.8rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -.5px;
}

.flash-sale-sub {
    color: #94a3b8;
    font-size: .85rem;
}

.countdown-label {
    color: #94a3b8;
    font-size: .85rem;
    font-weight: 600;
}

.countdown-block {
    background: rgba(255, 255, 255, .08);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 10px;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 52px;
}

.countdown-num {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.countdown-unit {
    font-size: .6rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 1px;
    margin-top: 2px;
}

.countdown-sep {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    animation: blink 1s step-end infinite;
}

@keyframes blink {
    50% {
        opacity: 0;
    }
}

.flash-sale-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--primary);
    border: 1px solid rgba(230, 59, 111, .4);
    border-radius: 8px;
    padding: 10px 20px;
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}

.flash-sale-link:hover {
    background: rgba(230, 59, 111, .1);
    border-color: var(--primary);
    color: var(--primary);
}

/* ============================================
   4. CATEGORIES
============================================ */
.cat-card-shell {
    min-height: 180px;
    border-radius: 16px;
}

.cat-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    min-height: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    text-decoration: none;
    cursor: pointer;
    transition: transform .3s, box-shadow .3s;
}

.cat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, .18);
}

.cat-card-bg {
    position: absolute;
    inset: 0;
    opacity: .9;
    transition: opacity .3s;
}

.cat-card:hover .cat-card-bg {
    opacity: 1;
}

.cat-card-img {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 90px;
    height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    padding: 12px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.cat-card:hover .cat-card-img {
    transform: translateX(-50%) scale(1.1);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.cat-card-img img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.cat-card-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -65%);
    font-size: 2.8rem;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, .15));
    transition: transform .3s;
}

.cat-card:hover .cat-card-icon {
    transform: translate(-50%, -75%) scale(1.1);
}

.cat-card-info {
    position: relative;
    z-index: 2;
    width: 100%;
    padding: 12px 10px;
    background: linear-gradient(to top, rgba(0, 0, 0, .6), transparent);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.cat-card-name {
    color: #fff;
    font-size: .95rem;
    font-weight: 700;
    text-align: center;
    text-shadow: 0 1px 4px rgba(0, 0, 0, .4);
}

.cat-card-count {
    color: rgba(255, 255, 255, .8);
    font-size: .75rem;
    font-weight: 500;
}

/* ============================================
   5. EQUIPMENT SECTION
============================================ */
.equip-big-card {
    background: var(--card-bg);
    border: 1px solid #eaeaea;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
    transition: box-shadow .3s, transform .3s;
}

.equip-big-card:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, .08);
    transform: translateY(-2px);
}

.equip-big-img {
    width: 100%;
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    overflow: hidden;
}

.equip-big-img img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    mix-blend-mode: multiply;
    transition: transform .4s;
}

.equip-big-card:hover .equip-big-img img {
    transform: scale(1.05);
}

.equip-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    color: #fff;
    font-size: .8rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 20px;
}

.equip-big-name {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
}

.equip-big-desc {
    font-size: .95rem;
    color: #636E72;
    line-height: 1.5;
}

.btn-buy-now {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    padding: 11px 28px;
    border-radius: 99px;
    font-weight: 700;
    font-size: .9rem;
    text-decoration: none;
    transition: all .2s;
    box-shadow: 0 4px 14px rgba(230, 59, 111, .35);
}

.btn-buy-now:hover {
    background: #d82f65;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, .45);
    color: #fff;
}

.equip-small-card {
    background: var(--card-bg);
    border: 1px solid #eaeaea;
    border-radius: 16px;
    padding: 32px 24px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 190px;
    text-decoration: none;
    transition: box-shadow .3s, transform .3s;
    overflow: hidden;
    position: relative;
}

.equip-small-card:hover {
    box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
    transform: translateY(-2px);
}

.equip-small-card--empty {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}

.equip-small-img {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 45%;
    height: 85%;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.equip-small-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: right center;
    mix-blend-mode: multiply;
    transition: transform .4s ease;
}

.equip-small-card:hover .equip-small-img img {
    transform: scale(1.1);
}

.equip-small-info {
    position: relative;
    max-width: 60%;
    z-index: 2;
}

.equip-small-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 8px !important;
}

.equip-small-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--primary);
    font-size: .85rem;
    font-weight: 700;
}

/* ============================================
   6. BEST SELLERS TABS
============================================ */
.tab-btn {
    padding: 10px 24px;
    border: 2px solid #e5e7eb;
    background: transparent;
    border-radius: 99px;
    font-size: .88rem;
    font-weight: 600;
    cursor: pointer;
    color: #636E72;
    transition: all .2s;
    font-family: inherit;
}

.tab-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.tab-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(230, 59, 111, .3);
}

.btn-view-more {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 40px;
    border: 2px solid var(--primary);
    color: var(--primary);
    background: transparent;
    border-radius: 99px;
    font-size: .95rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
}

.btn-view-more:hover {
    background: var(--primary);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(230, 59, 111, .3);
}

/* ============================================
   7. PROMO BANNERS
============================================ */
.promo-banner {
    border-radius: 20px;
    padding: 44px 36px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    min-height: 260px;
    height: 100%;
}

.promo-banner--dark {
    background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
}

.promo-banner--red {
    background: linear-gradient(135deg, #2d0011, #4a0020, #6b1a3a);
}

.promo-banner-content {
    position: relative;
    z-index: 2;
    flex: 1;
}

.promo-banner-tag {
    display: inline-block;
    background: rgba(230, 59, 111, .2);
    border: 1px solid rgba(230, 59, 111, .4);
    color: #ff8fab;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 5px 14px;
    border-radius: 99px;
    margin-bottom: 14px;
    text-transform: uppercase;
}

.promo-banner-title {
    font-size: 1.7rem;
    font-weight: 900;
    color: #fff;
    margin: 0 0 10px;
    line-height: 1.2;
    letter-spacing: -.5px;
}

.promo-banner-title em {
    color: var(--primary);
    font-style: italic;
}

.promo-banner-desc {
    color: rgba(255, 255, 255, .65);
    font-size: .85rem;
    line-height: 1.6;
    margin: 0 0 22px;
    max-width: 280px;
}

.promo-banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary);
    color: #fff;
    padding: 11px 26px;
    border-radius: 99px;
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s;
    box-shadow: 0 4px 16px rgba(230, 59, 111, .4);
}

.promo-banner-btn:hover {
    background: #d82f65;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(230, 59, 111, .5);
    color: #fff;
}

.promo-banner-btn--outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, .4);
    box-shadow: none;
}

.promo-banner-btn--outline:hover {
    background: rgba(255, 255, 255, .1);
    border-color: rgba(255, 255, 255, .8);
}

/* Deco elements */
.promo-banner-deco {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 220px;
    pointer-events: none;
}

.deco-circle {
    position: absolute;
    border-radius: 50%;
    opacity: .15;
}

.deco-c1 {
    width: 180px;
    height: 180px;
    background: var(--card-bg);
    top: -40px;
    right: -40px;
}

.deco-c2 {
    width: 120px;
    height: 120px;
    background: var(--primary);
    bottom: -20px;
    right: 60px;
    opacity: .25;
}

.deco-c3 {
    width: 200px;
    height: 200px;
    background: var(--primary);
    top: -50px;
    right: -50px;
}

.deco-c4 {
    width: 100px;
    height: 100px;
    background: var(--card-bg);
    bottom: -20px;
    right: 80px;
    opacity: .12;
}

.deco-badge {
    position: absolute;
    bottom: 28px;
    right: 24px;
    background: rgba(255, 255, 255, .12);
    border: 2px solid rgba(255, 255, 255, .2);
    color: #fff;
    font-size: 1.6rem;
    font-weight: 900;
    padding: 10px 18px;
    border-radius: 12px;
    backdrop-filter: blur(8px);
    letter-spacing: -1px;
}

.deco-badge--light {
    background: rgba(230, 59, 111, .2);
    border-color: rgba(230, 59, 111, .4);
    color: #ff8fab;
    font-size: 1.1rem;
}

/* ============================================
   8. BRAND MARQUEE
============================================ */
.brands-marquee-wrap {
    overflow: hidden;
    mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);
}

.brands-track {
    display: flex;
    align-items: center;
    gap: 60px;
    animation: marqueeScroll 22s linear infinite;
    width: max-content;
}

.brands-track:hover {
    animation-play-state: paused;
}

@keyframes marqueeScroll {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

.brand-logo-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    filter: grayscale(100%) opacity(.5);
    transition: filter .3s;
    flex-shrink: 0;
}

.brand-logo-item:hover {
    filter: grayscale(0%) opacity(1);
}

.brand-logo-item img {
    height: 36px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
}

/* ============================================
   9. TESTIMONIALS
============================================ */
.testimonial-card {
    background: var(--card-bg);
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
    transition: transform .3s, box-shadow .3s;
    position: relative;
}

.testimonial-card::before {
    content: '"';
    position: absolute;
    top: 12px;
    right: 20px;
    font-size: 5rem;
    font-weight: 900;
    color: #fde8ef;
    line-height: 1;
    font-family: Georgia, serif;
}

.testimonial-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, .08);
}

.testimonial-text {
    color: #4B5563;
    font-size: .92rem;
    line-height: 1.7;
    margin: 0 0 0;
    font-style: italic;
}

.testimonial-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fde8ef;
}

/* ============================================
   10. COMMUNITY
============================================ */
.community-section {
    width: 100%;
}

.community-content {
    background: linear-gradient(135deg, #1a1a2e, #2d3436);
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.community-inner-pad {
    padding-top: 64px;
    padding-bottom: 64px;
    padding-right: 48px;
    padding-left: max(12px, calc((100vw - 1320px) / 2 + 12px));
}

@media (max-width: 1399.98px) {
    .community-inner-pad {
        padding-left: max(12px, calc((100vw - 1140px) / 2 + 12px));
    }
}

@media (max-width: 1199.98px) {
    .community-inner-pad {
        padding-left: max(12px, calc((100vw - 960px) / 2 + 12px));
    }
}

@media (max-width: 991.98px) {
    .community-inner-pad {
        padding-left: max(12px, calc((100vw - 720px) / 2 + 12px));
        padding-right: 24px;
    }
}

@media (max-width: 767.98px) {
    .community-inner-pad {
        padding-left: max(12px, calc((100vw - 540px) / 2 + 12px));
    }
}

@media (max-width: 575.98px) {
    .community-inner-pad {
        padding-left: 12px;
    }
}

.community-tag {
    color: #94a3b8;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.community-title {
    font-size: 2.1rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin: 0 0 0;
    letter-spacing: -.5px;
}

.community-title em {
    font-style: italic;
    color: var(--primary);
}

.community-stats-row {
    border-top: 1px solid rgba(255, 255, 255, .08);
    border-bottom: 1px solid rgba(255, 255, 255, .08);
}

.community-stat-num {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}

.community-stat-label {
    font-size: .78rem;
    color: #64748b;
    font-weight: 500;
    margin-top: 4px;
}

.community-desc {
    color: #94a3b8;
    font-size: .95rem;
    line-height: 1.7;
    max-width: 480px;
}

.community-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.community-list li {
    color: #cbd5e1;
    font-size: .9rem;
    font-weight: 500;
}

.btn-community {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary), #ff6b9d);
    color: #fff;
    border: none;
    padding: 13px 30px;
    border-radius: 99px;
    font-size: .95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s;
    text-decoration: none;
    box-shadow: 0 6px 20px rgba(230, 59, 111, .4);
}

.btn-community:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(230, 59, 111, .5);
    color: #fff;
}

.community-images {
    position: relative;
    overflow: hidden;
}

.community-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .6s;
}

.community-images:hover .community-img {
    transform: scale(1.04);
}

/* ============================================
   SKELETON
============================================ */
.skeleton-pulse {
    background: linear-gradient(90deg, #f1f3f5 25%, #e9ecef 50%, #f1f3f5 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s ease-in-out infinite;
    border-radius: 12px;
}

@keyframes skeleton-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

/* Fix router-link text-decoration */
a.btn-primary-hero,
a.btn-outline-hero,
a.btn-view-more,
a.btn-community,
a.btn-buy-now,
a.link-more,
a.promo-banner-btn {
    text-decoration: none;
}

/* ============================================
   RESPONSIVE (supplement Bootstrap breakpoints)
============================================ */
@media (max-width: 992px) {
    .hero-section {
        height: 520px;
    }

    .hero-title {
        font-size: 3rem;
    }

    .community-inner-pad {
        padding: 48px 32px;
    }

    .community-title {
        font-size: 1.8rem;
    }

    .promo-banner-deco {
        display: none;
    }
}

@media (max-width: 768px) {
    .hero-section {
        min-height: 460px;
        height: auto;
        padding-bottom: 40px;
    }

    .hero-title {
        font-size: 2.3rem;
    }

    .benefits-inner {
        flex-wrap: wrap;
    }

    .benefit-item {
        flex: 0 0 48%;
    }

    .flash-sale-section .container {
        padding-top: 2rem !important;
        padding-bottom: 2rem !important;
    }

    .cat-card {
        min-height: 150px;
    }

    .section-title {
        font-size: 1.4rem;
    }
}

@media (max-width: 576px) {
    .hero-section {
        min-height: 480px;
        height: auto;
        padding-bottom: 60px;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-btns {
        flex-direction: column !important;
    }

    .btn-primary-hero,
    .btn-outline-hero {
        width: 100%;
        justify-content: center;
    }

    .benefit-item {
        flex: 0 0 100%;
    }

    .countdown-block {
        min-width: 44px;
    }

    .countdown-num {
        font-size: 1.2rem;
    }

    .community-inner-pad {
        padding: 36px 20px;
    }

    .promo-banner {
        padding: 28px 22px;
    }

    .promo-banner-title {
        font-size: 1.3rem;
    }
}
</style>
