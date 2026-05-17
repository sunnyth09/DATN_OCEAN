<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useRouter } from "vue-router";
import api from "../../../axios.js";
import ProductCard from "../../../components/ProductCard.vue";
import ProductSkeleton from "../../../components/ProductSkeleton.vue";

const router = useRouter();
const Products = ref([]);
const Categories = ref([]);
const categoryProducts = ref({});
const isLoadingFeatured = ref(true);
const isLoadingCategories = ref(true);

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const NEW_IMAGES = [
    'products/luxury_watch_1776303372051.png','products/leather_wallet_1776303390698.png',
    'products/sunglasses_1776303412493.png','products/silver_necklace_1776303426962.png',
    'products/leather_loafer_1776303445224.png','products/white_sneaker_1776303469345.png',
    'products/womens_clutch_1776303489059.png','products/card_holder_1776303513454.png',
    'products/zippered_wallet_1776303528282.png','products/button_down_shirt_1776303542708.png',
    'products/summer_dress_1776303557050.png','products/denim_jeans_1776303576632.png',
    'products/light_jacket_1776303589362.png','products/leather_belt_1776303603972.png'
];
let globalImageIdx = 0;

const getImageUrl = (path) => {
    if (!path || path === '0') {
        const url = `${BASE_URL}/storage/${NEW_IMAGES[globalImageIdx % NEW_IMAGES.length]}`;
        globalImageIdx++;
        return url;
    }
    if (path.startsWith('http')) return path;
    return `${BASE_URL}/storage/${path}`;
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
        originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
        discount_percent: maxDiscount, is_on_sale: lowest?.is_on_sale || false,
        image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
        badge: item.is_featured ? "Hot" : (maxDiscount > 0 ? "Sale" : null),
        slug: item.slug, category_name: item.category_name || '',
    };
};

Products.value = [
    { id: 1, name: 'Vợt Cầu Lông Yonex Astrox 88D Pro', price: '3.500.000 ₫', originalPrice: '4.000.000 ₫', discount_percent: 15, is_on_sale: true, image: 'https://cdn.shopvnb.com/uploads/gallery/vot-cau-long-yonex-astrox-88d-pro-chinh-hang_1711234907.webp', badge: 'Hot', slug: 'yonex-astrox-88d-pro', category_name: 'Vợt Cầu Lông' },
    { id: 2, name: 'Giày Cầu Lông Mizuno Wave Claw 3', price: '2.100.000 ₫', originalPrice: '2.500.000 ₫', discount_percent: 16, is_on_sale: true, image: 'https://cdn.shopvnb.com/uploads/gallery/giay-cau-long-mizuno-wave-claw-3-trang-hong-chinh-hang_1714529068.webp', badge: 'Sale', slug: 'mizuno-wave-claw-3', category_name: 'Giày Cầu Lông' },
    { id: 3, name: 'Balo Cầu Lông Yonex BA92012EX', price: '1.200.000 ₫', originalPrice: null, discount_percent: 0, is_on_sale: false, image: 'https://bizweb.dktcdn.net/100/172/291/products/26359-ba92012ex-black-2.jpg?v=1630138924040', badge: null, slug: 'yonex-ba92012ex', category_name: 'Balo Cầu Lông' },
    { id: 4, name: 'Quả Cầu Lông Vina Star', price: '250.000 ₫', originalPrice: null, discount_percent: 0, is_on_sale: false, image: 'https://cdn.shopvnb.com/uploads/gallery/ong-cau-long-vina-star-xanh-2_1707283626.webp', badge: 'Hot', slug: 'vina-star', category_name: 'Phụ kiện' },
    { id: 5, name: 'Vợt Cầu Lông Victor Thruster Ryuga II', price: '3.800.000 ₫', originalPrice: null, discount_percent: 0, is_on_sale: false, image: 'https://cdn.shopvnb.com/uploads/gallery/vot-cau-long-victor-thruster-ryuga-ii-chinh-hang-1.webp', badge: null, slug: 'victor-ryuga-ii', category_name: 'Vợt Cầu Lông' },
    { id: 6, name: 'Áo Cầu Lông Yonex Trắng', price: '350.000 ₫', originalPrice: '450.000 ₫', discount_percent: 22, is_on_sale: true, image: 'https://bizweb.dktcdn.net/100/172/291/products/ao-cau-long-yonex-trang-1.jpg', badge: 'Sale', slug: 'ao-yonex-trang', category_name: 'Quần Áo' },
    { id: 7, name: 'Giày Cầu Lông Yonex 65Z3', price: '2.800.000 ₫', originalPrice: '3.200.000 ₫', discount_percent: 12, is_on_sale: true, image: 'https://bizweb.dktcdn.net/100/172/291/products/giay-cau-long-yonex-shb-65z3-men-trang-do-chinh-hang.jpg', badge: null, slug: 'yonex-65z3', category_name: 'Giày Cầu Lông' },
    { id: 8, name: 'Cước Cầu Lông Yonex BG66', price: '150.000 ₫', originalPrice: null, discount_percent: 0, is_on_sale: false, image: 'https://bizweb.dktcdn.net/100/172/291/products/bg66um-4-1.jpg', badge: 'Hot', slug: 'yonex-bg66', category_name: 'Phụ kiện' }
];

Categories.value = [
    { id: 1, name: 'Bóng chuyền', slug: 'bong-chuyen', image: BASE_URL + '/storage/categories/category_bong_chuyen_1776325154591.png' },
    { id: 2, name: 'Cầu lông', slug: 'cau-long', image: BASE_URL + '/storage/categories/category_cau_long_1776325167694.png' },
    { id: 3, name: 'Pickleball', slug: 'pickleball', image: BASE_URL + '/storage/categories/category_pickleball_1776325197526.png' },
    { id: 4, name: 'Phụ kiện', slug: 'phu-kien', image: BASE_URL + '/storage/categories/category_phu_kien_the_thao_1776325207304.png' }
];

isLoadingFeatured.value = false;
isLoadingCategories.value = false;

// Featured category for the big card
const featuredCategory = computed(() => Categories.value[0] || null);
const featuredCatProduct = computed(() => null);
const sideCategories = computed(() => Categories.value.slice(1, 3));

const catIcons = ['👟','🎒','⌚','👔','👗','🏃','🏷️'];
const getCatIcon = (idx) => catIcons[idx % catIcons.length];

onMounted(() => { 
    // fetchProducts(); 
    // fetchCategories(); 
});
</script>

<template>
    <main class="home-main">

        <!-- ══════════════════════════════════════════
             1. HERO BANNER
        ══════════════════════════════════════════ -->
        <section class="hero-section">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1461896836934-bd45ba8fcf9b?w=1920&q=80" alt="hero" class="hero-bg-img" />
                <div class="hero-overlay"></div>
            </div>
            <div class="hero-content">
                <span class="hero-tag">BỘ SƯU TẬP MỚI 2026</span>
                <h1 class="hero-title">Tốc độ.<br/>Sức mạnh.<br/>Chính xác.</h1>
                <p class="hero-desc">Khám phá những thiết bị chất lượng cao, được thiết kế để nâng tầm kỹ năng và đưa bạn đến chiến thắng. Đam mê bắt đầu từ đây.</p>
                <div class="hero-btns">
                    <button class="btn-primary-hero" @click="() => {}">
                        Khám phá ngay
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                    <button class="btn-outline-hero" @click="() => {}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Xem Video
                    </button>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             2. TRANG BỊ THIẾT YẾU (Essential Equipment)
        ══════════════════════════════════════════ -->
        <section class="section-inner equip-section">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Trang bị thiết yếu</h2>
                    <p class="section-subtitle">Lựa chọn hàng đầu cho các lông thủ.</p>
                </div>
                <button class="link-more  btn">
                    Xem tất cả
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>

            <div class="equip-grid-custom">
                <!-- Big featured card -->
                <div class="equip-big-card-custom">
                    <div class="equip-big-img-custom">
                        <img src="https://cdn.shopvnb.com/uploads/gallery/vot-cau-long-yonex-astrox-88d-pro-chinh-hang_1711234907.webp" alt="Vợt cầu lông" />
                    </div>
                    <div class="equip-big-info-custom">
                        <span class="equip-discount-badge">Giảm giá 15%</span>
                        <h3 class="equip-big-name-custom">Vợt cầu lông cao cấp</h3>
                        <p class="equip-big-desc-custom">Trọng lượng nhẹ, độ cứng tối ưu cho những cú đập cháy sân.</p>
                        <button class="equip-big-link-custom">
                            Mua ngay
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Side category cards -->
                <div class="equip-side-custom">
                    <!-- Top card: Shoes -->
                    <div class="equip-small-card-custom">
                        <div class="equip-small-img-custom">
                            <img src="https://cdn.shopvnb.com/uploads/gallery/giay-cau-long-mizuno-wave-claw-3-trang-hong-chinh-hang_1714529068.webp" alt="Giày chuyên dụng" />
                        </div>
                        <div class="equip-small-info-custom">
                            <h4 class="equip-small-name-custom">Giày chuyên dụng</h4>
                            <button class="equip-small-link-custom">
                                Xem thêm
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Bottom card: Accessories -->
                    <div class="equip-small-card-custom accessory-card">
                        <div class="equip-acc-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="5.5" stroke="#E63B6F" stroke-width="2.5" />
                                <path d="M14 14L18.5 18.5" stroke="#E63B6F" stroke-width="3" stroke-linecap="round" />
                                <circle cx="17.5" cy="12.5" r="2.5" fill="#E63B6F" />
                            </svg>
                        </div>
                        <h4 class="equip-small-name-custom text-center">Phụ kiện & Balo</h4>
                        <p class="equip-small-desc-custom text-center">Túi, quấn cán, cước...</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             3. SẢN PHẨM BÁN CHẠY (Best Sellers)
        ══════════════════════════════════════════ -->
        <section class="section-inner bestseller-section">
            <div class="section-head section-head-center">
                <h2 class="section-title">Sản phẩm bán chạy</h2>
            </div>
            <div class="products-grid">
                <template v-if="isLoadingFeatured">
                    <ProductSkeleton v-for="i in 4" :key="i" />
                </template>
                <template v-else>
                    <ProductCard v-for="product in Products.slice(0, 8)" :key="product.id" :product="product" />
                </template>
            </div>
            <div class="view-more-wrap">
                <button  class="btn-view-more">
                    Xem thêm sản phẩm
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>
        </section>

        <!-- ══════════════════════════════════════════
             4. COMMUNITY / STORY BANNER
        ══════════════════════════════════════════ -->
        <section class="community-section">
            <div class="community-inner">
                <div class="community-content">
                    <span class="community-tag">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        CỘNG ĐỒNG QUYỀN SPORT
                    </span>
                    <h2 class="community-title">Hơn cả một cửa hàng.<br/>Chúng tôi là <em>đam mê</em>.</h2>
                    <p class="community-desc">Tham gia câu lạc bộ của chúng tôi để nhận ưu đãi độc quyền, kết nối với cộng đồng yêu thể thao và nâng cao kỹ năng qua các buổi giao lưu.</p>
                    <ul class="community-list">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Giảm giá 10% cho mọi đơn hàng.
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Tham gia các buổi workshop nâng cao kỹ năng miễn phí.
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Cập nhật sản phẩm mới nhất trước mọi người.
                        </li>
                    </ul>
                    <button class="btn-community" @click="() => {}">
                        Đăng ký tham gia ngay
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div class="community-images">
                    <img src="https://images.unsplash.com/photo-1526232761682-d26e03ac148e?w=800&q=80" alt="community" class="community-img" />
                </div>
            </div>
        </section>

    </main>
</template>

<style scoped>
/* ============================================
   GLOBAL TOKENS
============================================ */
.home-main { width: 100%; padding: 0; color: #2D3436; }

.section-inner { padding: 56px 0; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 32px; }
.section-head-center { justify-content: center; text-align: center; }
.section-title { font-size: 1.75rem; font-weight: 800; color: #2D3436; letter-spacing: -0.5px; margin: 0; }
.section-subtitle { color: #636E72; font-size: 0.95rem; margin: 4px 0 0; }
.link-more { display: inline-flex; align-items: center; gap: 6px; color: #E63B6F; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: gap 0.2s; }
.link-more:hover { gap: 10px; }

/* ============================================
   1. HERO BANNER
============================================ */
.hero-section {
    position: relative;
    width: 100vw;
    margin-left: calc(-50vw + 50%);
    height: 560px;
    overflow: hidden;
}

.hero-bg { position: absolute; inset: 0; }
.hero-bg-img { width: 100%; height: 100%; object-fit: cover; object-position: center 30%; }
.hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(105deg, rgba(20, 20, 30, 0.88) 0%, rgba(20, 20, 30, 0.6) 45%, transparent 75%);
}

.hero-content {
    position: relative; z-index: 2;
    max-width: 1400px; margin: 0 auto; padding: 0 40px;
    height: 100%; display: flex; flex-direction: column; justify-content: center;
}

.hero-tag {
    display: inline-block; background: rgba(230, 59, 111, 0.15); border: 1px solid rgba(230, 59, 111, 0.3);
    color: #ffb2bf; font-size: 0.7rem; font-weight: 700; letter-spacing: 2px;
    padding: 5px 14px; border-radius: 4px; margin-bottom: 20px; text-transform: uppercase; width: fit-content;
}

.hero-title {
    font-size: 3.5rem; font-weight: 900; line-height: 1.08; color: #ffffff;
    letter-spacing: -1px; margin: 0 0 20px; text-shadow: 0 2px 20px rgba(0,0,0,0.2);
}
.hero-title em, .hero-title i { font-style: italic; color: #E63B6F; }

.hero-desc { font-size: 1rem; color: #cbd5e1; line-height: 1.7; margin-bottom: 32px; max-width: 480px; }

.hero-btns { display: flex; gap: 12px; flex-wrap: wrap; }

.btn-primary-hero {
    display: inline-flex; align-items: center; gap: 8px;
    background: #E63B6F; color: white; border: none;
    padding: 14px 28px; border-radius: 8px;
    font-size: 0.9rem; font-weight: 700; cursor: pointer;
    transition: all 0.3s ease; box-shadow: 0 4px 16px rgba(230,59,111,0.35);
    font-family: inherit;
}
.btn-primary-hero:hover { background: #d82f65; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(230,59,111,0.45); }

.btn-outline-hero {
    display: inline-flex; align-items: center; gap: 8px;
    background: transparent; color: white;
    border: 2px solid rgba(255,255,255,0.4);
    padding: 12px 24px; border-radius: 8px;
    font-size: 0.9rem; font-weight: 600; cursor: pointer;
    transition: all 0.3s ease; font-family: inherit;
}
.btn-outline-hero:hover { border-color: #fff; background: rgba(255,255,255,0.08); }

/* ============================================
   2. EQUIPMENT SECTION
============================================ */
.equip-grid-custom {
    display: grid;
    grid-template-columns: 1.8fr 1fr;
    gap: 24px;
    margin-top: 24px;
}

.equip-big-card-custom {
    background: #FFFFFF;
    border: 1px solid #EAEAEA;
    border-radius: 12px;
    padding: 40px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}

.equip-big-img-custom {
    width: 100%;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 32px;
}

.equip-big-img-custom img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.equip-big-info-custom {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}

.equip-discount-badge {
    background: #FFFFFF;
    color: #E63B6F;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid #F1F3F5;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 4px;
}

.equip-big-name-custom {
    font-size: 1.6rem;
    font-weight: 800;
    color: #2D3436;
    margin: 0;
}

.equip-big-desc-custom {
    font-size: 0.95rem;
    color: #636E72;
    margin: 0;
    line-height: 1.5;
    max-width: 80%;
}

.equip-big-link-custom {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #E63B6F;
    font-weight: 700;
    font-size: 0.95rem;
    text-decoration: none;
    margin-top: 4px;
    transition: gap 0.2s;
}
.equip-big-link-custom:hover { gap: 10px; }

.equip-side-custom {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.equip-small-card-custom {
    background: #FFFFFF;
    border: 1px solid #EAEAEA;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    flex: 1;
    position: relative;
    min-height: 200px;
}

.equip-small-img-custom {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 75%;
    height: 55%;
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
}

.equip-small-img-custom img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.equip-small-info-custom {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.equip-small-name-custom {
    font-size: 1.1rem;
    font-weight: 800;
    color: #2D3436;
    margin: 0;
}
.equip-small-name-custom.text-center {
    text-align: center;
}

.equip-small-desc-custom {
    font-size: 0.85rem;
    color: #636E72;
    margin: 0;
}
.equip-small-desc-custom.text-center {
    text-align: center;
}

.equip-small-link-custom {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #E63B6F;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    transition: gap 0.2s;
}
.equip-small-link-custom:hover { gap: 8px; }

.accessory-card {
    background: #F1F3F5;
    border: 1px solid #EAEAEA;
    box-shadow: none;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.equip-acc-icon {
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ============================================
   3. BEST SELLERS
============================================ */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.view-more-wrap { text-align: center; margin-top: 40px; }

.btn-view-more {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 32px; border: 2px solid #E63B6F;
    color: #E63B6F; background: transparent;
    border-radius: 8px; font-size: 0.9rem; font-weight: 700;
    text-decoration: none; transition: all 0.2s; font-family: inherit;
}
.btn-view-more:hover { background: #E63B6F; color: white; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(230,59,111,0.25); }

/* ============================================
   4. COMMUNITY SECTION
============================================ */
.community-section {
    width: 100vw; margin-left: calc(-50vw + 50%);
    margin-top: 16px; margin-bottom: 0;
}

.community-inner {
    display: grid; grid-template-columns: 1fr 1fr;
    min-height: 480px;
}

.community-content {
    background: #1a1a2e; padding: 64px 48px 64px 64px;
    display: flex; flex-direction: column; justify-content: center;
}

.community-tag {
    display: inline-flex; align-items: center; gap: 8px;
    color: #94a3b8; font-size: 0.75rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 20px;
}
.community-tag svg { color: #636E72; }

.community-title {
    font-size: 2rem; font-weight: 800; color: #ffffff;
    line-height: 1.2; margin: 0 0 16px; letter-spacing: -0.5px;
}
.community-title em { font-style: italic; color: #E63B6F; }

.community-desc { color: #94a3b8; font-size: 0.95rem; line-height: 1.6; margin: 0 0 24px; max-width: 480px; }

.community-list {
    list-style: none; padding: 0; margin: 0 0 32px;
    display: flex; flex-direction: column; gap: 12px;
}
.community-list li {
    display: flex; align-items: center; gap: 10px;
    color: #cbd5e1; font-size: 0.9rem; font-weight: 500;
}
.community-list li svg { flex-shrink: 0; }

.btn-community {
    display: inline-flex; align-items: center; gap: 8px;
    background: transparent; color: #E63B6F;
    border: 2px solid #E63B6F; padding: 12px 24px;
    border-radius: 8px; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.3s; width: fit-content; font-family: inherit;
}
.btn-community:hover { background: #E63B6F; color: white; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(230,59,111,0.3); }

.community-images {
    position: relative; overflow: hidden;
}
.community-img {
    width: 100%; height: 100%; object-fit: cover;
}
.equip-big-link-custom,.equip-small-link-custom {
    border: none;
    background-color: transparent;
}

/* ============================================
   RESPONSIVE
============================================ */
@media (max-width: 1200px) {
    .products-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
    .hero-section { height: 480px; }
    .hero-title { font-size: 2.8rem; }
    .equip-grid-custom { grid-template-columns: 1fr; }
    .community-inner { grid-template-columns: 1fr; }
    .community-images { height: 300px; }
    .products-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .hero-section { height: 420px; }
    .hero-title { font-size: 2.2rem; }
    .hero-content { padding: 0 24px; }
    .section-inner { padding: 40px 0; }
    .section-title { font-size: 1.4rem; }
    .community-content { padding: 40px 24px; }
    .community-title { font-size: 1.6rem; }
}

@media (max-width: 480px) {
    .hero-section { height: 380px; }
    .hero-title { font-size: 1.8rem; }
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .hero-btns { flex-direction: column; }
    .btn-primary-hero, .btn-outline-hero { width: 100%; justify-content: center; }
}
</style>
