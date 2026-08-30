<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { useCatalogStore } from "@/stores/catalog";
import { extractCollection } from "@/services/catalogService";
import { getAppBaseUrl, getStorageUrl } from '@/utils/url';
import api from "@/axios";

import HeroSection from './sections/HeroSection.vue';
import BenefitsBar from './sections/BenefitsBar.vue';
import FlashSaleSection from './sections/FlashSaleSection.vue';
import CategoriesSection from './sections/CategoriesSection.vue';
import BannerSection from './sections/BannerSection.vue';
import PromoBannersSection from './sections/PromoBannersSection.vue';
import TestimonialsSection from './sections/TestimonialsSection.vue';
import BlogSection from './sections/BlogSection.vue';
import CommunitySection from './sections/CommunitySection.vue';
import VoucherSection from './sections/VoucherSection.vue';
import PostsSection from './sections/PostsSection.vue';

const BASE_URL = getAppBaseUrl();

const Products = ref([]);
const Categories = ref([]);
const isLoadingFeatured = ref(true);
const isLoadingCategories = ref(true);
const publicCoupons = ref([]);
const selectedCoupon = ref(null);
const copiedCouponCode = ref('');

const catalogStore = useCatalogStore();
const { categories: storeCategories } = storeToRefs(catalogStore);

// ── Tab filter ──
const activeTab = ref('all');
const saleProducts = ref([]);
const isLoadingSale = ref(false);
let hasFetchedSale = false;

const filteredProducts = computed(() => {
    if (activeTab.value === 'sale') return saleProducts.value;
    return Products.value;
});

const fetchSaleProducts = async () => {
    if (hasFetchedSale) return;
    isLoadingSale.value = true;
    try {
        const res = await catalogStore.fetchOnSaleProducts();
        const rawData = extractCollection(res);
        saleProducts.value = (Array.isArray(rawData) ? rawData : []).map(mapProduct);
        hasFetchedSale = true;
    } catch (e) {
        console.error('Lỗi tải sản phẩm sale:', e);
    } finally {
        isLoadingSale.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 'sale' && !hasFetchedSale) {
        fetchSaleProducts();
    }
});

// ── Flash Sale Countdown ──
const countdown = ref({ h: '00', m: '00', s: '00' });
let countdownTimer = null;
let flashSaleEndTime = null;

const updateCountdown = () => {
    if (!flashSaleEndTime) return;
    const now = new Date();
    const diff = flashSaleEndTime - now;
    if (diff <= 0) {
        countdown.value = { h: '00', m: '00', s: '00' };
        return;
    }
    const h = Math.floor(diff / 3_600_000);
    const m = Math.floor((diff % 3_600_000) / 60_000);
    const s = Math.floor((diff % 60_000) / 1_000);
    countdown.value = {
        h: String(h).padStart(2, '0'),
        m: String(m).padStart(2, '0'),
        s: String(s).padStart(2, '0'),
    };
};

const flashSaleProducts = ref([]);
const isLoadingFlashSale = ref(true);

const fetchRealFlashSale = async () => {
    try {
        isLoadingFlashSale.value = true;
        const { data } = await api.get('flash-sale');
        if (data && data.data && data.data.length > 0) {
            // Lấy danh sách sản phẩm và giới hạn 4 sản phẩm
            flashSaleProducts.value = data.data.slice(0, 4).map(p => {
                const salePrice = p.sale_price ?? p.flash_price ?? p.price ?? 0;
                const origPrice = p.original_price ?? p.originalPrice ?? salePrice;
                const totalStock = p.total_stock ?? p.total_quantity ?? 0;
                const soldCount = p.sold_count ?? p.sold ?? 0;
                const percent = totalStock > 0 ? Math.min(100, Math.floor((soldCount / totalStock) * 100)) : 0;

                return {
                    id: p.product_id ?? p.id,
                    flash_sale_id: p.id,
                    item_id: p.item_id,
                    name: p.product_name ?? p.name ?? 'Sản phẩm Flash Sale',
                    min_price: salePrice,
                    price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(salePrice),
                    original_price: origPrice,
                    originalPrice: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(origPrice),
                    discount_percent: p.discount_percent ?? (origPrice > 0 ? Math.round(((origPrice - salePrice) / origPrice) * 100) : 0),
                    is_on_sale: true,
                    image: p.product_thumbnail ?? p.image_url ?? p.image ?? '',
                    badge: "Hot",
                    slug: p.slug ?? '',
                    category_name: p.category_name || '',
                    flash_sold: soldCount,
                    flash_total: totalStock,
                    flash_percent: percent
                };
            });
            // Lấy thời gian kết thúc flash sale (ends_at hoặc end_time)
            const endString = data.data[0].ends_at || data.data[0].end_time;
            if (endString) {
                flashSaleEndTime = new Date(endString);
            }
        } else {
            flashSaleProducts.value = [];
        }
    } catch (e) {
        console.error('Lỗi tải Flash Sale:', e);
        flashSaleProducts.value = [];
    } finally {
        isLoadingFlashSale.value = false;
    }
};

// ── Category helpers (Updated with Line Art SVGs) ──
const catIcons = [
    '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><path d="M11 7h2v6h-2zm0 8h2v2h-2z"/>', // Demo SVG path
    '<path d="M21 6.5l-4-4-2 2-6-6-2 2 6 6-2 2 4 4 2-2 6 6 2-2-4-4 2-2 4 4z"/>',
    '<circle cx="12" cy="12" r="8"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
    '<path d="M4 14v4a2 2 0 002 2h12a2 2 0 002-2v-4M8 10l4 4 4-4M12 14V4"/>',
    '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'
];
const getCatIcon = (idx) => catIcons[idx % catIcons.length];

const catGradients = [
    'linear-gradient(135deg,#2563eb,#4f46e5)',
    'linear-gradient(135deg,#ec4899,#e11d48)',
    'linear-gradient(135deg,#06b6d4,#0ea5e9)',
    'linear-gradient(135deg,#10b981,#059669)',
    'linear-gradient(135deg,#f59e0b,#ea580c)',
    'linear-gradient(135deg,#8b5cf6,#7c3aed)',
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

    // Xác định giá gốc (để gạch ngang)
    let originalPrice = null;
    if (lowest?.is_on_sale) {
        originalPrice = lowest.price; // Sale từ sale_price
    } else if (lowest?.compare_at_price > lowest?.price) {
        originalPrice = lowest.compare_at_price; // Sale từ compare_at_price
    }

    // Tính phần trăm giảm giá từ cả 2 nguồn
    let maxDiscount = lowest?.discount_percent || 0; // Từ sale_price (model accessor)
    if (!maxDiscount && lowest?.compare_at_price > lowest?.price && lowest?.price > 0) {
        // Tính từ compare_at_price nếu chưa có discount từ sale_price
        maxDiscount = Math.round((lowest.compare_at_price - lowest.price) / lowest.compare_at_price * 100);
    }
    if (item.variants?.length) {
        const variantDiscounts = item.variants.map(v => {
            let d = v.discount_percent || 0;
            if (!d && v.compare_at_price > v.price && v.price > 0) {
                d = Math.round((v.compare_at_price - v.price) / v.compare_at_price * 100);
            }
            return d;
        });
        maxDiscount = Math.max(maxDiscount, ...variantDiscounts);
    }

    // Sản phẩm coi là "đang sale" nếu có sale_price active hoặc compare_at_price > price
    const isOnSale = lowest?.is_on_sale || (lowest?.compare_at_price > lowest?.price) || false;

    return {
        id: item.product_id, name: item.name,
        price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(currentPrice),
        min_price: currentPrice,
        originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
        original_price: originalPrice,
        discount_percent: maxDiscount,
        is_on_sale: isOnSale,
        image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
        badge: item.is_featured ? "Hot" : (maxDiscount > 0 ? `-${maxDiscount}%` : null),
        slug: item.slug,
        category_name: item.category_name || '',
        variants_sum_stock: item.variants_sum_stock ?? null,
        variants: item.variants ?? [],
    };
};


const fetchCategories = async () => {
    if (catalogStore.hasFetchedCategories) {
        isLoadingCategories.value = false;
    } else {
        isLoadingCategories.value = true;
    }
    try {
        await catalogStore.fetchCategories();
        const data = storeCategories.value || [];
        
        // Sử dụng Breadth-First Search (BFS) để ưu tiên các danh mục cha cấp cao nhất lên đầu
        const flattenCategories = (topLevelCats) => {
            let result = [];
            let queue = topLevelCats.map(cat => ({ ...cat, parentName: '' }));
            
            while(queue.length > 0) {
                const current = queue.shift();
                
                let displayName = current.name;
                if (current.parentName) {
                    const childLower = current.name.toLowerCase();
                    const parentLower = current.parentName.toLowerCase();
                    // Nếu tên con chưa chứa tên cha (VD: "Vợt" chưa có "Cầu lông")
                    if (!childLower.includes(parentLower)) {
                        displayName = `${current.name} ${current.parentName}`;
                    }
                }
                
                result.push({ ...current, displayName });
                
                if (current.children && current.children.length > 0) {
                    for (const child of current.children) {
                        queue.push({ ...child, parentName: current.name });
                    }
                }
            }
            return result;
        };
        
        const flatData = flattenCategories(data);
        
        Categories.value = flatData.map(cat => ({
            id: cat.category_id || cat.id,
            name: cat.displayName || cat.name,
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
    if (catalogStore.hasFetchedFeaturedProducts) {
        isLoadingFeatured.value = false;
    } else {
        isLoadingFeatured.value = true;
    }

    try {
        const res = await catalogStore.fetchFeaturedProducts();
        const rawData = extractCollection(res);
        Products.value = (Array.isArray(rawData) ? rawData : []).map(mapProduct);
    } catch (e) {
        console.error('Lỗi tải sản phẩm:', e);
    } finally {
        isLoadingFeatured.value = false;
    }
};

const fetchPublicCoupons = async () => {
    try {
        const res = await api.get('/coupons/public');
        if (res.data?.status === 'success') {
            publicCoupons.value = (res.data.data || []).filter(c => c.is_active).slice(0, 4);
        }
    } catch (e) {
        console.error('Lỗi tải voucher:', e);
    }
};

const formatCurrency = (val) => {
    if (!val) return '0₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatCouponValue = (coupon) => {
    if (coupon.type === 'percent') return `Giảm ${coupon.value}%`;
    if (coupon.type === 'free_ship') return 'Miễn phí vận chuyển';
    return `Giảm ${formatCurrency(coupon.value)}`;
};

const getCouponIcon = (coupon) => {
    if (coupon.type === 'free_ship') return 'shipping';
    if (coupon.type === 'percent') return 'percent';
    return 'tag';
};

const copyCouponCode = async (code) => {
    try {
        await navigator.clipboard.writeText(code);
        copiedCouponCode.value = code;
        setTimeout(() => {
            if (copiedCouponCode.value === code) copiedCouponCode.value = '';
        }, 1600);
    } catch (e) {
        console.error('Không thể sao chép voucher:', e);
    }
};

const openCouponDetail = (coupon) => {
    selectedCoupon.value = coupon;
};

const closeCouponDetail = () => {
    selectedCoupon.value = null;
};

const goToCouponPage = () => {
    closeCouponDetail();
    window.location.href = '/coupon';
};

const sideCategories = computed(() => Categories.value.slice(1, 3));
const featuredProduct = computed(() =>
    Products.value.find(p => p.badge === 'Hot' || p.is_on_sale) || Products.value[0] || null
);

// ── Testimonials ──
const testimonials = [
    {
        id: 1, name: 'Nguyễn Minh Tuấn', role: 'VĐV Bóng đá Phong trào',
        avatar: 'https://ui-avatars.com/api/?name=Nguyen+Minh+Tuan&background=random',
        text: 'Sản phẩm chất lượng tuyệt vời! Giày tôi mua ở đây đã đi được 6 tháng mà vẫn như mới. Giao hàng nhanh, đóng gói cẩn thận. Sẽ tiếp tục ủng hộ Ocean Sport.'
    },
    {
        id: 2, name: 'Trần Thị Lan Anh', role: 'Huấn Luyện Viên Yoga',
        avatar: 'https://ui-avatars.com/api/?name=Tran+Thi+Lan+Anh&background=random',
        text: 'Mình đã mua đồ tập yoga ở đây được 2 năm rồi. Chất liệu co giãn tốt, màu sắc đẹp và bền màu. Giá cả hợp lý so với chất lượng. Rất hài lòng!'
    },
    {
        id: 3, name: 'Lê Hoàng Nam', role: 'Runner Nghiệp Dư',
        avatar: 'https://ui-avatars.com/api/?name=Le+Hoang+Nam&background=random',
        text: 'Tìm được đôi giày chạy bộ ưng ý nhất từ trước đến nay tại đây. Nhân viên tư vấn nhiệt tình, hiểu rõ nhu cầu. Shop uy tín, hàng chính hãng 100%.'
    }
];

// ── Brands ──
const brands = [
    { name: 'Nike', logo: 'https://upload.wikimedia.org/wikipedia/commons/a/a6/Logo_NIKE.svg' },
    { name: 'Adidas', logo: 'https://upload.wikimedia.org/wikipedia/commons/2/20/Adidas_Logo.svg' },
    { name: 'Puma', logo: 'https://cdn.simpleicons.org/puma' },
    { name: 'Under Armour', logo: 'https://upload.wikimedia.org/wikipedia/commons/4/44/Under_armour_logo.svg' },
    { name: 'Asics', logo: 'https://upload.wikimedia.org/wikipedia/commons/b/b1/Asics_Logo.svg' },
    { name: 'New Balance', logo: 'https://cdn.simpleicons.org/newbalance' },
];

// ── Public Posts ──
const homePosts = ref([]);
const fetchHomePosts = async () => {
    try {
        const res = await api.get('/posts', { params: { status: 'published', limit: 4 } });
        homePosts.value = res.data || [];
    } catch (e) {
        console.error('Lỗi tải bài viết:', e);
    }
};

const featuredHomePost = computed(() => {
    const feat = homePosts.value.find(p => p.is_featured);
    return feat || homePosts.value[0] || null;
});

const sideHomePosts = computed(() => {
    if (!featuredHomePost.value) return homePosts.value.slice(0, 3);
    return homePosts.value.filter(p => p.post_id !== featuredHomePost.value.post_id).slice(0, 3);
});

const getHomePostImage = (url) => {
    if (!url || url === '0') return 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&q=80';
    return getStorageUrl(url);
};

const getHomeAuthorName = (author) => {
    if (!author) return 'Ban Biên Tập';
    return author.full_name || author.name || 'Ban Biên Tập';
};

const getHomeAuthorFallbackAvatar = (author) => {
    const name = encodeURIComponent(getHomeAuthorName(author));
    return `https://ui-avatars.com/api/?name=${name}&background=e63b6f&color=fff&size=80&bold=true`;
};

const getHomeAuthorAvatar = (author) => {
    if (!author?.avatar_url) return getHomeAuthorFallbackAvatar(author);
    return getStorageUrl(author.avatar_url);
};

const getHomePostSummary = (post, limit = 96) => {
    const text = post?.summary || post?.excerpt || post?.content || '';
    const plainText = String(text).replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    if (!plainText) return '';
    return plainText.length > limit ? `${plainText.slice(0, limit).trim()}...` : plainText;
};

const formatPostDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const revealElements = ref([]);

const initScrollReveal = () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-active');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.reveal-on-scroll').forEach(el => observer.observe(el));
};

onMounted(() => {
    fetchCategories();
    fetchProducts();
    fetchPublicCoupons();
    fetchHomePosts();
    fetchRealFlashSale().then(() => {
        updateCountdown();
        countdownTimer = setInterval(updateCountdown, 1000);
    });
    // Initialize reveal slightly after mount to ensure DOM is ready
    setTimeout(initScrollReveal, 300);
});
onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });
</script>

<template>
    <div class="home-wrapper">
        <main class="home-main">
            <HeroSection />
            <BenefitsBar />
            <VoucherSection
                :publicCoupons="publicCoupons"
                :copiedCouponCode="copiedCouponCode"
                :selectedCoupon="selectedCoupon"
                @copy="copyCouponCode"
                @open="openCouponDetail"
                @close="closeCouponDetail"
                @view-all="goToCouponPage"
            />
            <FlashSaleSection :flashSaleProducts="flashSaleProducts" :isLoadingFlashSale="isLoadingFlashSale"
                :countdown="countdown" />
            <CategoriesSection :Categories="Categories" :isLoadingCategories="isLoadingCategories"
                :getCatIcon="getCatIcon" :getCatGradient="getCatGradient" />
            <BannerSection :activeTab="activeTab" :filteredProducts="filteredProducts"
                :isLoadingFeatured="isLoadingFeatured" :isLoadingSale="isLoadingSale"
                @update:activeTab="activeTab = $event" />
            <PromoBannersSection />
            <TestimonialsSection :brands="brands" />
            <PostsSection :homePosts="homePosts" />
            <BlogSection :testimonials="testimonials" />
            <CommunitySection />
        </main>

    </div>
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
</style>
