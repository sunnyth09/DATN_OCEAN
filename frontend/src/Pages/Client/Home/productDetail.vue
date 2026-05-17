<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { useFavorites } from '@/composables/useFavorites';
import PremiumUpgrade from '@/components/PremiumUpgrade.vue';
const route = useRoute();
const router = useRouter();
// slug là computed để watch được khi route thay đổi
const slug = computed(() => route.params.slug);
const product = ref(null);
const selectedVariant = ref(null);
const selectedColor = ref(null);
const selectedSize = ref(null);
const relatedProducts = ref([]);
const addingToCart = ref(false);
const toast = ref({ show: false, message: '', type: 'success' });
const showSizeGuide = ref(false);

const { isFavorited, toggleFavorite } = useFavorites();
const handleToggleFav = async () => {
    if (!product.value || !product.value.product_id) return;
    await toggleFavorite(product.value.product_id);
};

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#E63B6F" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/></svg>`);

const getImageUrl = (path) => {
    if (!path || path === '0') return defaultSvg;
    if (path.startsWith('http')) return path;
    if (path.startsWith('/storage/') || path.startsWith('storage/')) {
        const cleanPath = path.startsWith('/') ? path : `/${path}`;
        return `${BASE_URL}${cleanPath}`;
    }
    return `${BASE_URL}/storage/${path}`;
};

const allImages = computed(() => {
    if (!product.value) return [];
    const imgs = product.value.images || [];
    const variants = product.value.variants || [];
    const hasVariants = variants.length > 0;

    const getUniqueImages = (imageArray) => {
        const seen = new Set();
        return imageArray.filter(img => {
            if (!img.image_url) return false;
            const isDuplicate = seen.has(img.image_url);
            seen.add(img.image_url);
            return !isDuplicate;
        });
    };

    // Sản phẩm biến thể + đã chọn màu → hiện ảnh của variant màu đó
    if (hasVariants && selectedColor.value) {
        const colorVariants = variants.filter(v => v.color === selectedColor.value);
        const variantIds = colorVariants.map(v => v.variant_id);

        const variantImgs = imgs.filter(img => img.variant_id && variantIds.includes(img.variant_id));
        if (variantImgs.length > 0) return getUniqueImages(variantImgs);

        const directImgs = colorVariants
            .filter(v => v.image_url)
            .map(v => ({ image_url: v.image_url, variant_id: v.variant_id }));
        if (directImgs.length > 0) return getUniqueImages(directImgs);

        // Fallback: NẾU BIẾN THỂ KHÔNG CÓ ẢNH -> Trả về Ảnh chung của sản phẩm (ảnh không thuộc biến thể nào)
        const generalImgs = imgs.filter(img => !img.variant_id);
        if (generalImgs.length > 0) return getUniqueImages(generalImgs);

        if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
            return [{ image_url: product.value.thumbnail_url }];
        }
        return [{ image_url: null }];
    }

    // Tất cả các trường hợp khác: hiển thị tất cả các ảnh nhưng lọc trùng
    if (imgs.length > 0) return getUniqueImages(imgs);

    if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
        return [{ image_url: product.value.thumbnail_url }];
    }
    return [{ image_url: null }];
});

const fetchProduct = async (currentSlug) => {
    try {
        const response = await api.get(`/products/${currentSlug}`);
        product.value = response.data;
        // Reset selections khi đổi sản phẩm
        selectedVariant.value = null;
        selectedColor.value = null;
        selectedSize.value = null;
        activeImageIndex.value = 0;

        if (product.value.product_id) {
            fetchReviews(product.value.product_id);
        }
        fetchRelatedProducts(currentSlug);

        // Auto-select variant if it's a simple product (no colors, no sizes)
        if (product.value.variants && product.value.variants.length > 0) {
            const hasColors = product.value.variants.some(v => v.color);
            const hasSizes = product.value.variants.some(v => v.size);
            
            if (!hasColors && !hasSizes) {
                selectedVariant.value = product.value.variants[0];
            }
        }
    } catch (error) {
        console.error("Error fetching product:", error);
    }
};

const fetchRelatedProducts = async (currentSlug) => {
    try {
        const res = await api.get(`/products/${currentSlug}/related`);
        if (res.data.status === 'success') {
            relatedProducts.value = res.data.data;
        }
    } catch (err) {
        console.error('Related products error:', err);
        relatedProducts.value = [];
    }
};

const reviews = ref([]);
const fetchReviews = async (productId) => {
    try {
        const res = await api.get(`/products/${productId}/comments`);
        if (res.data.status === 'success') {
            reviews.value = res.data.data.data || [];
        }
    } catch (err) {
        console.error(err);
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
};

const isDescriptionExpanded = ref(false);
const activeImageIndex = ref(0);
const quantity = ref(1);

// Lấy danh sách màu duy nhất
const uniqueColors = computed(() => {
    if (!product.value?.variants) return [];
    const colors = [...new Set(product.value.variants.map(v => v.color).filter(Boolean))];
    return colors;
});

// Lấy danh sách size khả dụng — luôn hiện tất cả size, đánh dấu disabled theo màu đã chọn
const availableSizes = computed(() => {
    if (!product.value?.variants) return [];
    const variants = product.value.variants;

    // Lấy tất cả sizes duy nhất
    const allSizes = [...new Set(variants.map(v => v.size).filter(Boolean))];

    return allSizes.map(size => {
        // Nếu đã chọn màu, kiểm tra variant (color + size) có tồn tại và khả dụng không
        if (selectedColor.value) {
            const match = variants.find(v => v.color === selectedColor.value && v.size === size);
            return {
                size,
                stock: match?.stock ?? 0,
                status: match?.status ?? 'inactive',
                variant_id: match?.variant_id ?? null,
                available: !!match && match.status === 'active' && match.stock > 0,
            };
        }
        // Chưa chọn màu → kiểm tra có BẤT KỲ variant nào có size này khả dụng không
        const anyAvailable = variants.some(v => v.size === size && v.status === 'active' && v.stock > 0);
        const first = variants.find(v => v.size === size);
        return {
            size,
            stock: first?.stock ?? 0,
            status: first?.status ?? 'inactive',
            variant_id: first?.variant_id ?? null,
            available: anyAvailable,
        };
    });
});

// Khi chọn màu → reset size + reset gallery, auto-select nếu chỉ có 1 size
watch(selectedColor, (newColor) => {
    selectedSize.value = null;
    selectedVariant.value = null;
    activeImageIndex.value = 0;
    if (newColor) {
        const sizes = product.value?.variants?.filter(v => v.color === newColor) || [];
        if (sizes.length === 1) {
            selectedSize.value = sizes[0].size;
            selectedVariant.value = sizes[0];
        }
    }
});

// Khi chọn size → tìm variant đúng
watch(selectedSize, (newSize) => {
    if (newSize && product.value?.variants) {
        let match = null;
        if (selectedColor.value) {
            match = product.value.variants.find(
                v => v.color === selectedColor.value && v.size === newSize
            );
        } else {
            // Trường hợp sản phẩm KHÔNG có màu, chỉ có size
            match = product.value.variants.find(v => v.size === newSize);
        }
        selectedVariant.value = match || null;
    } else {
        selectedVariant.value = null;
    }
});
const mainImageUrl = computed(() => {
    const imgs = allImages.value;
    if (imgs.length === 0) return getImageUrl(null);
    const idx = activeImageIndex.value < imgs.length ? activeImageIndex.value : 0;
    return getImageUrl(imgs[idx]?.image_url);
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const displayPriceInfo = computed(() => {
    if (!product.value) return { current: 0, original: null, discount: 0 };
    
    if (selectedVariant.value) {
        let orig = null;
        if (selectedVariant.value.is_on_sale) orig = selectedVariant.value.price;
        else if (selectedVariant.value.compare_at_price > selectedVariant.value.price) orig = selectedVariant.value.compare_at_price;
        
        return {
            current: selectedVariant.value.effective_price,
            original: orig,
            discount: selectedVariant.value.discount_percent || 0
        };
    }
    
    // Nếu chưa chọn variant, tìm variant có effective_price thấp nhất
    const variants = product.value.variants || [];
    if (variants.length === 0) return { current: product.value.min_price || 0, original: null, discount: 0 };
    
    const lowest = variants.reduce((min, v) => ((v.effective_price || v.price) < (min.effective_price || min.price) ? v : min), variants[0]);
    
    let orig = null;
    if (lowest.is_on_sale) orig = lowest.price;
    else if (lowest.compare_at_price > lowest.price) orig = lowest.compare_at_price;
    
    return {
        current: lowest.effective_price || lowest.price,
        original: orig,
        discount: lowest.discount_percent || 0
    };
});

const increaseQuantity = () => quantity.value++;
const decreaseQuantity = () => { if (quantity.value > 1) quantity.value-- };

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const addToCart = async () => {
    const token = sessionStorage.getItem('auth_token');
    if (!token) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return false;
    }

    if (!selectedVariant.value) {
        showToast('Vui lòng chọn phiên bản sản phẩm!', 'error');
        return false;
    }

    if (quantity.value < 1) {
        showToast('Số lượng tối thiểu là 1!', 'error');
        return false;
    }

    addingToCart.value = true;
    try {
        const response = await api.post('/cart/items', {
            variant_id: selectedVariant.value.variant_id,
            quantity: quantity.value,
        });
        if (response.data.status === 'success') {
            showToast(response.data.message, 'success');
            window.dispatchEvent(new Event('cart-updated'));
            return true;
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Không thể thêm vào giỏ hàng.';
        showToast(msg, 'error');
    } finally {
        addingToCart.value = false;
    }
    return false;
};

const buyNow = async () => {
    const success = await addToCart();
    if (success) {
        router.push('/checkout');
    }
};

/**
 * sortedVariants: danh sách variants active, sắp xếp theo giá tăng dần.
 * API đã sort sẵn, nhưng computed này đảm bảo thứ tự đúng ở client.
 */
const sortedVariants = computed(() => {
    if (!product.value?.variants) return [];
    return [...product.value.variants]
        .filter((v) => v.status === 'active' || v.status === undefined)
        .sort((a, b) => a.price - b.price);
});

/**
 * handleUpgrade: Khi khách nhấn "Nâng cấp", tự động cập nhật
 * selectedColor, selectedSize, selectedVariant sang variant premium.
 */
const handleUpgrade = (premiumVariant) => {
    if (!premiumVariant) return;
    // Cập nhật màu (nếu có)
    if (premiumVariant.color) {
        selectedColor.value = premiumVariant.color;
    }
    // Cập nhật size (nếu có)
    if (premiumVariant.size) {
        selectedSize.value = premiumVariant.size;
    }
    // Gán trực tiếp để đảm bảo selectedVariant luôn đúng
    selectedVariant.value = premiumVariant;
    // Cuộn ô chọn variant lên để user thấy sự thay đổi
    const variantSection = document.querySelector('.variant-selector');
    if (variantSection) {
        variantSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    showToast(`Đã nâng cấp lên phiên bản ${premiumVariant.color || ''} ${premiumVariant.size || ''}`.trim(), 'success');
};

// Watch route slug để reload dữ liệu khi điều hướng sang SP khác
watch(slug, (newSlug, oldSlug) => {
    if (newSlug && newSlug !== oldSlug) {
        product.value = null;
        relatedProducts.value = [];
        window.scrollTo({ top: 0, behavior: 'smooth' });
        fetchProduct(newSlug);
    }
});

onMounted(() => {
    fetchProduct(slug.value);
});
</script>

<template>
  <main class="product-detail-wrapper" v-if="product">
    <!-- Breadcrumb (Đường dẫn) -->
    <nav class="breadcrumb">
      <router-link to="/">Trang chủ</router-link>
      <span class="separator">/</span>
      <router-link to="/product">Sản phẩm</router-link>
      <span class="separator">/</span>
      <span class="current-page">{{ product.name }}</span>
    </nav>

    <!-- Main Content: Ảnh và Info -->
    <section class="product-main-grid">
      
      <!-- Cột Trái: Ảnh sản phẩm (Gallery) -->
      <div class="product-gallery">
        <!-- Ảnh chính -->
        <div class="main-image-container ocean-card">
          <img :src="mainImageUrl" :alt="product.name" class="main-image animate-fade-in" :key="activeImageIndex" />
        </div>
        
        <!-- Danh sách Ảnh nhỏ (Thumbnails) -->
        <div class="thumbnail-list" v-if="allImages.length > 1">
          <div 
            v-for="(img, index) in allImages" 
            :key="index"
            class="thumbnail-item"
            :class="{ 'active': activeImageIndex === index }"
            @click="activeImageIndex = index"
          >
            <img :src="getImageUrl(img.image_url)" :alt="`${product.name} - ảnh ${index + 1}`" />
          </div>
        </div>
      </div>

      <!-- Cột Phải: Thông tin sản phẩm -->
      <div class="product-info-box">
        <div class="category-badge" v-if="product.category">{{ product.category.name }}</div>
        <h1 class="product-title">{{ product.name }}</h1>
        
        <!-- Đánh giá sao -->
        <div class="product-rating">
          <div class="stars">
            <i class="fas fa-star" v-for="i in 5" :key="i" :class="{'active': i <= Math.round(product.rating_avg || 0)}"></i>
          </div>
          <span class="rating-text">{{ product.rating_avg ?? 0 }} <span class="reviews-count">({{ product.rating_count ?? 0 }} đánh giá)</span></span>
        </div>

        <!-- Giá tiền -->
        <div class="product-pricing">
          <span class="current-price">{{ formatPrice(displayPriceInfo.current) }}</span>
          <span class="original-price" v-if="displayPriceInfo.original">{{ formatPrice(displayPriceInfo.original) }}</span>
          <span class="discount-badge" v-if="displayPriceInfo.discount > 0">
            -{{ displayPriceInfo.discount }}%
          </span>
        </div>

        <!-- Mô tả ngắn (Kỹ thuật) -->
        <div class="short-description" v-html="product.short_description">
        </div>

        <!-- Chọn Màu sắc -->
        <div class="variant-selector" v-if="uniqueColors.length > 0">
          <h4 class="variant-label">Màu sắc:</h4>
          <div class="variant-options">
            <button
              v-for="color in uniqueColors"
              :key="color"
              class="variant-btn"
              :class="{ active: selectedColor === color }"
              @click="selectedColor = selectedColor === color ? null : color"
            >
              <span>{{ color }}</span>
            </button>
          </div>
        </div>

        <!-- Chọn Kích cỡ -->
        <div class="variant-selector" v-if="availableSizes.length > 0">
          <div class="variant-header-row">
            <h4 class="variant-label">Kích cỡ:</h4>
            <button class="btn-text size-guide-trigger" @click="showSizeGuide = true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19V5a2 2 0 00-2-2H4a2 2 0 00-2 2v14a2 2 0 002 2h16a2 2 0 002-2z"/><line x1="6" y1="3" x2="6" y2="7"/><line x1="10" y1="3" x2="10" y2="7"/><line x1="14" y1="3" x2="14" y2="7"/><line x1="18" y1="3" x2="18" y2="7"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
              Bảng size chuẩn
            </button>
          </div>
          <div class="variant-options">
            <button
              v-for="s in availableSizes"
              :key="s.size"
              class="variant-btn"
              :class="{ active: selectedSize === s.size, disabled: !s.available }"
              @click="s.available && (selectedSize = selectedSize === s.size ? null : s.size)"
              :disabled="!s.available"
            >
              <span>{{ s.size || 'Mặc định' }}</span>
              <span class="variant-stock" v-if="selectedColor && s.stock <= 5 && s.stock > 0">(còn {{ s.stock }})</span>
              <span class="variant-stock out" v-if="selectedColor && s.stock <= 0">Hết hàng</span>
            </button>
          </div>
        </div>

        <!-- Chức năng Số lượng & Mua hàng -->
        <div class="purchase-actions">
          <div class="quantity-selector">
            <button @click="decreaseQuantity"><i class="fas fa-minus"></i></button>
            <input type="number" v-model="quantity" readonly />
            <button @click="increaseQuantity"><i class="fas fa-plus"></i></button>
          </div>
          <button class="btn-primary btn-addToCart" @click="addToCart" :disabled="addingToCart">
            <i class="fas fa-cart-plus" v-if="!addingToCart"></i>
            <span v-if="addingToCart">Đang thêm...</span>
            <span v-else>Thêm vào giỏ</span>
          </button>
        </div>
        <div class="action-buttons-row">
          <button class="btn-outline btn-hero-fav" 
                  :class="{'is-active': product && isFavorited(product.product_id)}" 
                  @click="handleToggleFav" 
                  title="Yêu thích">
            <i class="fas fa-heart" v-if="product && isFavorited(product.product_id)"></i>
            <i class="far fa-heart" v-else></i>
          </button>
          <button class="btn-primary btn-buyNow" @click="buyNow" :disabled="addingToCart">Mua ngay</button>
        </div>

        <!-- ✦ Premium Variant Upsell Box ✦ -->
        <PremiumUpgrade
          :current-variant="selectedVariant"
          :all-variants="sortedVariants"
          @upgrade="handleUpgrade"
        />

        <!-- Tiện ích đi kèm -->
        <div class="service-perks">
          <div class="perk-item"><i class="fas fa-truck-fast"></i> Giao hàng miễn phí toàn quốc</div>
          <div class="perk-item"><i class="fas fa-rotate-left"></i> Đổi trả dễ dàng trong 7 ngày</div>
          <div class="perk-item"><i class="fas fa-shield-halved"></i> Bảo hành chính hãng 12 tháng</div>
        </div>
      </div>
    </section>

    <!-- Hàng dưới: Tabs Mô tả chi tiết & Đánh giá -->
    <section class="product-details-reviews">
      <!-- Cột Trái: Mô tả chi tiết -->
      <div class="content-box description-section ocean-card">
        <h2 class="section-title">Chi tiết sản phẩm</h2>
        <div class="long-description" :class="{ 'expanded': isDescriptionExpanded }">
          <div v-html="product.description"></div>
          <div class="fade-overlay" v-if="!isDescriptionExpanded"></div>
        </div>
        <button class="btn-outline toggle-desc-btn" @click="isDescriptionExpanded = !isDescriptionExpanded">
          {{ isDescriptionExpanded ? 'Thu gọn' : 'Xem thêm nội dung' }}
          <i class="fas" :class="isDescriptionExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>
      </div>

      <!-- Cột Phải: Feedback & Đánh giá -->
      <div class="content-box reviews-section ocean-card">
        <h2 class="section-title">Khách hàng đánh giá</h2>
        
        <div class="review-list">
          <div v-if="reviews.length === 0" class="no-reviews">Chưa có đánh giá nào cho sản phẩm này.</div>
          <div class="review-item" v-for="review in reviews" :key="review.comment_id">
            <div class="review-header">
              <img :src="(review.commenter_info || review.user)?.avatar_url ? getImageUrl((review.commenter_info || review.user).avatar_url) : 'https://placehold.co/44x44?text=U'" :alt="(review.commenter_info || review.user)?.full_name" class="reviewer-avatar" />
              <div class="reviewer-info">
                <h4 class="reviewer-name">{{ (review.commenter_info || review.user)?.full_name || 'Người dùng Ẩn danh' }}</h4>
                <div class="stars small">
                  <i class="fas fa-star active" v-for="i in review.rating" :key="`star-${i}`"></i>
                  <i class="fas fa-star" v-for="i in 5 - review.rating" :key="`empty-${i}`"></i>
                </div>
              </div>
              <span class="review-date">{{ formatDate(review.created_at) }}</span>
            </div>
            <p class="review-content">{{ review.content }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Section Sản phẩm liên quan -->
    <section class="related-products-section" v-if="relatedProducts.length > 0">
      <div class="related-header">
        <div class="related-title-group">
          <h2 class="related-title">Sản phẩm liên quan</h2>
          <div class="related-title-line"></div>
        </div>
      </div>
      <div class="related-grid">
        <router-link
          v-for="item in relatedProducts"
          :key="item.product_id"
          :to="`/product/${item.slug}`"
          class="related-card"
        >
          <div class="related-card-image">
            <img
              :src="getImageUrl(item.thumbnail_url)"
              :alt="item.name"
              loading="lazy"
            />
            <div class="related-card-overlay">
              <span class="related-view-btn">Xem chi tiết</span>
            </div>
          </div>
          <div class="related-card-body">
            <p class="related-card-name">{{ item.name }}</p>
            <span class="related-card-price">{{ formatPrice(item.min_price) }}</span>
          </div>
        </router-link>
      </div>
    </section>

  </main>

  <!-- Toast -->
  <Transition name="toast">
    <div v-if="toast.show" class="toast-notification" :class="toast.type">
      <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <span>{{ toast.message }}</span>
    </div>
  </Transition>

  <!-- Modal Bảng Size -->
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="showSizeGuide" class="modal-overlay" @click.self="showSizeGuide = false">
        <div class="modal-content size-modal">
          <div class="modal-header">
            <h2 class="modal-title">Bảng size tham khảo (Vóc dáng người Việt)</h2>
            <button class="modal-close" @click="showSizeGuide = false">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="modal-body">
            <p class="size-desc">Bảng tính mặc định được thiết kế dựa trên số đo chuẩn của người Việt Nam. Nếu bạn có số đo nằm giữa 2 size, lời khuyên là nên chọn size lớn hơn để có sự thoải mái nhất.</p>
            
            <div class="table-responsive">
              <table class="size-table">
                <thead>
                  <tr>
                    <th>Size</th>
                    <th>Cân nặng (kg)</th>
                    <th>Chiều cao (cm)</th>
                    <th>Gợi ý form dáng</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>S</strong></td>
                    <td>45 - 52 kg</td>
                    <td>Dưới 1m60</td>
                    <td>Ôm gọn, tôn dáng</td>
                  </tr>
                  <tr>
                    <td><strong>M</strong></td>
                    <td>53 - 59 kg</td>
                    <td>1m60 - 1m65</td>
                    <td>Vừa vặn, thoải mái</td>
                  </tr>
                  <tr>
                    <td><strong>L</strong></td>
                    <td>60 - 68 kg</td>
                    <td>1m66 - 1m72</td>
                    <td>Thoải mái vận động</td>
                  </tr>
                  <tr>
                    <td><strong>XL</strong></td>
                    <td>69 - 76 kg</td>
                    <td>1m73 - 1m78</td>
                    <td>Rộng rãi, che khuyết điểm</td>
                  </tr>
                  <tr>
                    <td><strong>XXL</strong></td>
                    <td>Trên 76 kg</td>
                    <td>Trên 1m78</td>
                    <td>Oversize trần viền rộng rãi</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="size-tips">
              <div class="tip-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="tip-icon" stroke="#E63B6F" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Sản phẩm có độ co giãn nhẹ khoảng 2-3cm ở vòng bụng.</span>
              </div>
              <div class="tip-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="tip-icon" stroke="#E63B6F" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Màu sắc thực tế có thể chênh lệch 3-5% do độ phân giải và ánh sáng màn hình.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.product-detail-wrapper {
  padding: 30px 0;
  font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
  color: var(--text-main, #102a43);
  width: 100%;
}

/* Breadcrumb */
.breadcrumb {
  font-size: 0.9rem;
  margin-bottom: 24px;
  color: var(--text-muted, #627d98);
}
.breadcrumb a {
  color: #E63B6F;
  text-decoration: none;
  font-weight: 500;
}
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb .separator { margin: 0 8px; color: #bcccdc; }
.breadcrumb .current-page { color: #102a43; font-weight: 600; }

/* Grid Layout */
.product-main-grid {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: 40px;
  margin-bottom: 40px;
}

.product-main-grid > div {
  min-width: 0;
}

/* Thư viện ảnh */
.product-gallery {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.main-image-container {
  width: 100%;
  aspect-ratio: 1/1;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid transparent;
  background: white;
}
.main-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.thumbnail-list {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 8px;
}
.thumbnail-list::-webkit-scrollbar { height: 6px; }
.thumbnail-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 0; }
.thumbnail-item {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid transparent;
  opacity: 0.6;
  transition: all 0.2s;
  flex-shrink: 0;
}
.thumbnail-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumbnail-item.active, .thumbnail-item:hover {
  opacity: 1;
  border-color: #E63B6F;
}

/* Khối thông tin */
.category-badge {
  display: inline-block;
  background: #E63B6F;
  color: white;
  padding: 4px 14px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 16px;
}
.product-title {
  font-size: 1.85rem;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 16px;
  color: #E63B6F;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.product-rating {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}
.stars i { color: #d9e8f0; }
.stars i.active { color: #ffb300; }
.rating-text { font-weight: 600; font-size: 0.95rem; }
.reviews-count { color: var(--text-muted, #627d98); font-weight: 400; }

/* Pricing */
.product-pricing {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.current-price {
  font-size: 2rem;
  font-weight: 800;
  color: var(--coral, #ef5350);
}
.original-price {
  font-size: 1.25rem;
  color: #a0aec0;
  text-decoration: line-through;
}
.discount-badge {
  background: #ffebee;
  color: var(--coral, #ef5350);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 700;
}

/* Short Desc */
.short-description {
  font-size: 1.05rem;
  line-height: 1.6;
  color: #486581;
  margin-bottom: 30px;
}

/* Variant Header Row */
.variant-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.size-guide-trigger {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #E63B6F;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
  transition: opacity 0.2s;
  background: none;
  border: none;
  cursor: pointer;
}
.size-guide-trigger:hover {
  opacity: 0.8;
}

/* Modal Bảng Size */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(3px); display: flex; justify-content: center; align-items: center; z-index: 9999; padding: 20px; }
.size-modal { background: #fff; border-radius: 16px; width: 100%; max-width: 650px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.modal-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; }
.modal-close { background: none; border: none; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; }
.modal-close:hover { background: #e2e8f0; color: #0f172a; }
.modal-body { padding: 24px; }
.size-desc { font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 20px; }
.table-responsive { overflow-x: auto; margin-bottom: 24px; border-radius: 12px; border: 1px solid #e2e8f0; }
.size-table { width: 100%; border-collapse: collapse; text-align: left; }
.size-table th { background: #f1f5f9; color: #334155; font-weight: 700; padding: 14px 16px; font-size: 0.95rem; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.size-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #475569; font-size: 0.95rem; }
.size-table tbody tr:hover { background: #f8fafc; }
.size-table td strong { color: #0f172a; font-size: 1.1rem; }
.size-tips { display: flex; flex-direction: column; gap: 10px; background: #f0f9ff; border: 1px dashed #bae6fd; padding: 16px; border-radius: 12px; }
.tip-item { display: flex; align-items: flex-start; gap: 10px; }
.tip-icon { flex-shrink: 0; margin-top: 2px; }
.tip-item span { font-size: 0.9rem; color: #0369a1; line-height: 1.5; font-weight: 500; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: all 0.3s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; transform: scale(0.95); }

/* Actions */
.purchase-actions {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}
.quantity-selector {
  display: flex;
  align-items: center;
  border: 1px solid #E9ECEF;
  border-radius: 8px;
  overflow: hidden;
  background: transparent;
}
.quantity-selector button {
  width: 44px;
  height: 48px;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #2D3436;
  transition: background 0.2s;
}
.quantity-selector button:hover { background: #f8fafc; }
.quantity-selector input {
  width: 50px;
  text-align: center;
  border: none;
  font-weight: 700;
  font-size: 1rem;
  outline: none;
  background: transparent;
}
.btn-addToCart {
  flex: 1;
  padding: 0;
  height: 48px;
  border-radius: 8px;
  font-size: 1.05rem;
  font-weight: 700;
  background: #E63B6F;
  border: 1px solid #E63B6F;
  color: white;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.btn-addToCart:hover {
  background: transparent;
  color: #E63B6F;
}
.btn-buyNow {
  flex: 1;
  height: 48px;
  border-radius: 8px;
  background: transparent;
  color: #E63B6F;
  border: 1px solid #E63B6F;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.btn-buyNow:hover { background: #E63B6F; color: white; }

.action-buttons-row {
  display: flex;
  gap: 16px;
  width: 100%;
  margin-bottom: 30px;
}
.btn-hero-fav {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 1.25rem;
  color: #636E72;
  flex-shrink: 0;
  padding: 0;
  border: 1px solid #E9ECEF;
  background: transparent;
}
.btn-hero-fav:hover {
  background: transparent;
  color: #E63B6F;
  border-color: #E63B6F;
}
.btn-hero-fav.is-active {
  color: #E63B6F;
  border-color: #E63B6F;
  background: transparent;
}

/* Perks */
.service-perks {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.perk-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.95rem;
  font-weight: 500;
  color: #334e68;
}
.perk-item i {
  color: #E63B6F;
  font-size: 1.2rem;
  width: 24px;
  text-align: center;
}

/* Bottom Sections (Description & Reviews) */
.product-details-reviews {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 30px;
}
.content-box {
  background: white;
  border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 16px rgba(0,0,0, 0.02);
}
.section-title {
  font-size: 1.35rem;
  font-weight: 800;
  margin-bottom: 24px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f0f4f8;
}

/* Long Description */
.long-description {
  position: relative;
  max-height: 250px;
  overflow: hidden;
  transition: max-height 0.4s ease;
  line-height: 1.7;
  color: #334e68;
}
.long-description.expanded {
  max-height: 2000px; /* Số lớn đủ để hiện full */
}
.long-description p { margin-bottom: 12px; }
.long-description ul { margin-left: 20px; margin-bottom: 16px; }
.long-description li { margin-bottom: 6px; }
.fade-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100px;
  background: linear-gradient(rgba(255,255,255,0), rgba(255,255,255,1));
  pointer-events: none;
}
.toggle-desc-btn {
  margin-top: 16px;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

/* Reviews */
.review-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.review-item {
  border-bottom: 1px dashed var(--border-color, #d9e8f0);
  padding-bottom: 20px;
}
.review-item:last-child { border-bottom: none; padding-bottom: 0; }
.review-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.reviewer-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}
.reviewer-info { flex: 1; }
.reviewer-name {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 4px;
}
.stars.small i { font-size: 0.8rem; }
.review-date {
  font-size: 0.85rem;
  color: #829ab1;
}
.review-content {
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334e68;
}

.w-100 { width: 100%; }
.mt-3 { margin-top: 16px; }

/* Buttons & Utils */
.btn-primary {
  background: #E63B6F;
  color: white;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.btn-primary:hover { background: #039be5; transform: translateY(-1px); }
.btn-outline {
  background: white;
  color: #E63B6F;
  border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 8px;
  padding: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-outline:hover {
  background: #f0f7fa;
  border-color: #E63B6F;
}

.animate-fade-in { animation: fadeIn 0.4s ease; }
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ── Related Products Section ─────────────────────────────── */
.related-products-section {
  margin-top: 52px;
  padding-top: 8px;
}
.related-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
}
.related-title-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.related-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #E63B6F;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.related-title-line {
  width: 60px;
  height: 3px;
  background: linear-gradient(90deg, #E63B6F, var(--coral, #ef5350));
  border-radius: 2px;
}
.related-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}
.related-card {
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid var(--border-color, #d9e8f0);
  background: #fff;
  text-decoration: none;
  display: flex;
  flex-direction: column;
  transition: transform 0.22s ease, box-shadow 0.22s ease;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}
.related-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 32px rgba(230, 59, 111,0.14);
  border-color: #E63B6F;
}
.related-card-image {
  position: relative;
  aspect-ratio: 1/1;
  overflow: hidden;
  background: #f4f9f9;
}
.related-card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.35s ease;
}
.related-card:hover .related-card-image img {
  transform: scale(1.07);
}
.related-card-overlay {
  position: absolute;
  inset: 0;
  background: rgba(230, 59, 111,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.25s ease;
}
.related-card:hover .related-card-overlay {
  opacity: 1;
}
.related-view-btn {
  background: white;
  color: #E63B6F;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 8px 18px;
  border-radius: 99px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.related-card-body {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}
.related-card-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #102a43;
  line-height: 1.4;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.related-card-price {
  font-size: 1rem;
  font-weight: 800;
  color: var(--coral, #ef5350);
}

/* Responsive */
@media (max-width: 900px) {
  .product-main-grid { grid-template-columns: 1fr; gap: 24px; }
  .product-details-reviews { grid-template-columns: 1fr; }
  .related-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
}
@media (max-width: 500px) {
  .related-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
}

/* Variant Selector */
.variant-selector { margin-bottom: 24px; }
.variant-label {
  font-size: 0.95rem;
  font-weight: 700;
  color: #334e68;
  margin-bottom: 10px;
}
.variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.variant-btn {
  padding: 8px 16px;
  border: 1.5px solid #d9e2ec;
  border-radius: 8px;
  background: #fff;
  font-size: 0.88rem;
  font-weight: 600;
  color: #334e68;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  display: flex;
  align-items: center;
  gap: 6px;
}
.variant-btn:hover:not(:disabled) {
  border-color: #E63B6F;
  color: #E63B6F;
}
.variant-btn.active {
  border-color: #E63B6F;
  background: rgba(230, 59, 111, 0.08);
  color: #E63B6F;
}
.variant-btn.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.variant-stock {
  font-size: 0.75rem;
  color: #f59e0b;
  font-weight: 500;
}
.variant-stock.out { color: #dc2626; }

.btn-primary:disabled,
.btn-addToCart:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Toast */
.toast-notification {
  position: fixed;
  top: 90px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  border-radius: 10px;
  font-size: 0.92rem;
  font-weight: 600;
  z-index: 999;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.toast-notification.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}
.toast-notification.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.toast-enter-active { animation: slideInRight 0.3s ease; }
.toast-leave-active { animation: slideOutRight 0.3s ease; }
@keyframes slideInRight {
  from { opacity: 0; transform: translateX(40px); }
  to { opacity: 1; transform: translateX(0); }
}
@keyframes slideOutRight {
  from { opacity: 1; transform: translateX(0); }
  to { opacity: 0; transform: translateX(40px); }
}
</style>
