<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { useFavorites } from '@/composables/useFavorites';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import PremiumUpgrade from '@/components/PremiumUpgrade.vue';
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
import AppIcon from '@/icons/AppIcon.vue';
import VirtualTryOnModal from '@/components/VirtualTryOnModal.vue';
import { useFlyToCart } from '@/composables/useFlyToCart';
import { getStorageUrl } from '@/utils/url';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const cartStore = useCartStore();
// slug là computed để watch được khi route thay đổi (route param là :id, có thể là slug hoặc id)
const slug = computed(() => route.params.id);
const product = ref(null);
const productImageRef = ref(null);
const showTryOn = ref(false);
const { flyToCart } = useFlyToCart();
const tryOnEnabled = import.meta.env.VITE_TRYON_ENABLED !== 'false';
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

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#E63B6F" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#E63B6F" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#FF6B9D" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#FF6B9D" opacity="0.4"/></svg>`);

const getImageUrl = (path) => {
  if (!path || path === '0') return defaultSvg;
  return getStorageUrl(path);
};

const getUniqueImages = (imageArray = []) => {
  const seen = new Set();
  return imageArray.filter(img => {
    if (!img?.image_url) return false;
    if (seen.has(img.image_url)) return false;
    seen.add(img.image_url);
    return true;
  });
};

const allImages = computed(() => {
  if (!product.value) return [];
  const imgs = product.value.images || [];
  const variants = product.value.variants || [];
  const hasVariants = variants.length > 0;
  const generalImgs = getUniqueImages(imgs.filter(img => !img.variant_id));

  if (selectedVariant.value?.variant_id) {
    const exactVariantImgs = getUniqueImages(
      imgs.filter(img => img.variant_id === selectedVariant.value.variant_id)
    );
    if (exactVariantImgs.length > 0) return exactVariantImgs;
  }

  // Sản phẩm biến thể + đã chọn màu → hiện ảnh của variant màu đó
  if (hasVariants && selectedColor.value) {
    const colorVariants = variants.filter(v => v.color === selectedColor.value);
    const variantIds = colorVariants.map(v => v.variant_id);

    const variantImgs = getUniqueImages(imgs.filter(img => img.variant_id && variantIds.includes(img.variant_id)));
    if (variantImgs.length > 0) return variantImgs;

    // Fallback: NẾU BIẾN THỂ KHÔNG CÓ ẢNH -> Trả về Ảnh chung của sản phẩm (ảnh không thuộc biến thể nào)
    if (generalImgs.length > 0) return generalImgs;

    if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
      return [{ image_url: product.value.thumbnail_url }];
    }
    return [{ image_url: null }];
  }

  // Tất cả các trường hợp khác: hiển thị tất cả ảnh, bao gồm ảnh của variant
  if (imgs.length > 0) return getUniqueImages(imgs);

  if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
    return [{ image_url: product.value.thumbnail_url }];
  }
  return [{ image_url: null }];
});

const fetchProduct = async (currentSlug) => {
  try {
    const response = await api.get(`/products/${currentSlug}`);
    product.value = response.data.data;
    // Reset selections khi đổi sản phẩm
    selectedVariant.value = null;
    selectedColor.value = null;
    selectedSize.value = null;
    activeImageIndex.value = 0;

    if (product.value.product_id) {
      fetchReviews(product.value.product_id);
      // Ghi nhận lịch sử xem sản phẩm
      api.post('/tracking/view-product', { product_id: product.value.product_id }).catch(() => { });
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
const activeTab = ref('description');

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

  // Lấy tất cả sizes duy nhất, sắp xếp theo thứ tự chuẩn
  const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];
  const allSizes = [...new Set(variants.map(v => v.size).filter(Boolean))]
    .sort((a, b) => {
      const idxA = sizeOrder.indexOf(a);
      const idxB = sizeOrder.indexOf(b);
      // Nếu cả 2 đều có trong bảng chuẩn → sort theo thứ tự chuẩn
      if (idxA !== -1 && idxB !== -1) return idxA - idxB;
      // Nếu chỉ 1 trong 2 có → ưu tiên cái có trong bảng lên trước
      if (idxA !== -1) return -1;
      if (idxB !== -1) return 1;
      // Cả 2 đều không có (size số) → sort theo alphabet/số
      return a.localeCompare(b, undefined, { numeric: true });
    });

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
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return false;
  }

  if (!selectedVariant.value) {
    showToast('Vui lòng chọn phiên bản sản phẩm!', 'error');
    return false;
  }

  if (selectedVariant.value.stock <= 0) {
    showToast('Sản phẩm phiên bản này đã hết hàng!', 'error');
    return false;
  }

  if (quantity.value > selectedVariant.value.stock) {
    showToast(`Số lượng trong kho không đủ (chỉ còn ${selectedVariant.value.stock} sản phẩm)!`, 'error');
    return false;
  }

  if (quantity.value < 1) {
    showToast('Số lượng tối thiểu là 1!', 'error');
    return false;
  }

  addingToCart.value = true;
  try {
    const response = await cartStore.addItem({
      variantId: selectedVariant.value.variant_id,
      quantity: quantity.value,
    });
    if (response.status === 'success') {
      if (productImageRef.value) {
        await flyToCart(productImageRef.value, '#cart-icon');
      }
      showToast(response.message, 'success');
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
  // === Affiliate: ghi nhận referral code từ URL ===
  const refCode = route.query.ref;
  if (refCode) {
    // Lưu vào localStorage với TTL 30 ngày
    localStorage.setItem('affiliate_ref', refCode);
    localStorage.setItem('affiliate_ref_expiry', Date.now() + 30 * 24 * 60 * 60 * 1000);

    // Gọi API track click (fire-and-forget, không block UI)
    api.post('/affiliate/track-click', {
      referral_code: refCode,
      product_id: null, // Sẽ cập nhật sau khi fetch product
    }).catch(() => { }); // Bỏ qua lỗi
  } else {
    // Kiểm tra localStorage đã hết hạn chưa
    const expiry = localStorage.getItem('affiliate_ref_expiry');
    if (expiry && Date.now() > Number(expiry)) {
      localStorage.removeItem('affiliate_ref');
      localStorage.removeItem('affiliate_ref_expiry');
    }
  }
});
console.log(quantity.value);

</script>

<template>
  <main class="pd-wrapper" v-if="product">
    <!-- Breadcrumb -->
    <nav class="pd-breadcrumb">
      <router-link to="/">Trang chủ</router-link>
      <span class="sep">&gt;</span>
      <router-link v-if="product.category" :to="'/product?category=' + product.category_id">{{ product.category.name
        }}</router-link>
      <span class="sep" v-if="product.category">&gt;</span>
      <span class="current">{{ product.name }}</span>
    </nav>

    <!-- ═══ MAIN: Gallery + Info ═══ -->
    <section class="pd-main">
      <!-- Gallery: thumbnails trái + ảnh chính phải -->
      <div class="pd-gallery">
        <div class="pd-thumbs" v-if="allImages.length > 1">
          <div v-for="(img, i) in allImages" :key="i" class="pd-thumb" :class="{ active: activeImageIndex === i }"
            @click="activeImageIndex = i">
            <img :src="getImageUrl(img.image_url)" :alt="product.name + ' ảnh ' + (i + 1)" />
          </div>
        </div>
        <div class="pd-main-img">
          <img ref="productImageRef" :src="mainImageUrl" :alt="product.name" :key="activeImageIndex" />
        </div>
      </div>

      <!-- Info Panel -->
      <div class="pd-info">
        <span class="pd-badge" v-if="product.category">{{ product.category.name }}</span>
        <h1 class="pd-title">{{ product.name }}</h1>

        <!-- Rating -->
        <div class="pd-rating">
          <div class="pd-stars">
            <svg v-for="i in 5" :key="i" width="16" height="16" viewBox="0 0 24 24"
              :fill="i <= Math.round(product.rating_avg || 0) ? '#F59E0B' : '#E5E7EB'" stroke="none">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
          </div>
          <span class="pd-rating-text">({{ product.rating_count ?? 0 }} đánh giá)</span>
        </div>

        <!-- Price -->
        <div class="pd-price-row">
          <span class="pd-price">{{ formatPrice(displayPriceInfo.current) }}</span>
          <span class="pd-price-old" v-if="displayPriceInfo.original">{{ formatPrice(displayPriceInfo.original)
            }}</span>
        </div>

        <!-- Stock -->
        <div class="pd-stock">
          <span class="pd-stock-label me-2">Số lượng còn:</span>
          <span class="pd-stock-value" v-if="selectedVariant">{{ selectedVariant.stock }}</span>
          <span class="pd-stock-value" v-else-if="product.variants"> {{product.variants?.reduce((sum, variant) => sum +
            variant.stock, 0) ?? '---' }}</span>
        </div>

        <!-- Tình trạng -->
        <div class="pd-status">
          <span class="pd-status-label">Tình trạng:</span>
          <span class="pd-status-value in-stock"
            v-if="selectedVariant ? selectedVariant.stock > 0 : (product.variants?.reduce((sum, v) => sum + v.stock, 0) > 0)">
            <AppIcon name="check" size="14" stroke-width="2.5" />
            Còn hàng
          </span>
          <span class="pd-status-value out-of-stock" style="color: #ef4444;" v-else>
            <AppIcon name="x" size="14" stroke-width="2.5" />
            Hết hàng
          </span>
        </div>

        <!-- Variant chips -->
        <div class="pd-variants" v-if="uniqueColors.length > 0">
          <h4 class="pd-var-label">Phân bản / Màu sắc</h4>
          <div class="pd-var-options">
            <button v-for="color in uniqueColors" :key="color" class="pd-var-btn"
              :class="{ active: selectedColor === color }"
              @click="selectedColor = selectedColor === color ? null : color">{{ color }}</button>
          </div>
        </div>

        <div class="pd-variants" v-if="availableSizes.length > 0">
          <h4 class="pd-var-label">Kích cỡ</h4>
          <div class="pd-var-options">
            <button v-for="s in availableSizes" :key="s.size" class="pd-var-btn"
              :class="{ active: selectedSize === s.size, disabled: !s.available }" :disabled="!s.available"
              @click="s.available && (selectedSize = selectedSize === s.size ? null : s.size)">{{ s.size || 'Mặc định'
              }}</button>
          </div>
        </div>

        <!-- Số lượng -->
        <div class="pd-qty-row">
          <span class="pd-qty-label">Số lượng</span>
          <div class="pd-qty">
            <button @click="decreaseQuantity">−</button>
            <input type="number" v-model="quantity" />
            <button @click="increaseQuantity">+</button>
          </div>
        </div>

        <!-- CTA -->
        <div class="pd-cta">
          <button class="pd-btn-cart" @click="addToCart"
            :disabled="addingToCart || (selectedVariant && selectedVariant.stock <= 0)">
            <AppIcon name="cart" size="18" />
            {{ addingToCart ? 'Đang thêm...' : (selectedVariant && selectedVariant.stock <= 0 ? 'Hết hàng'
              : 'Thêm Vào Giỏ Hàng' ) }} </button>
              <button class="pd-btn-buy" @click="buyNow"
                :disabled="addingToCart || (selectedVariant && selectedVariant.stock <= 0)">
                {{ selectedVariant && selectedVariant.stock <= 0 ? 'Hết hàng' : 'Đặt Hàng Nhanh' }} </button>
        </div>

        <!-- AI Try-On -->
        <button v-if="tryOnEnabled" class="pd-btn-tryon" @click="showTryOn = true" title="Thử đồ bằng AI">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
            <circle cx="12" cy="13" r="3"></circle>
          </svg>
          Thử đồ bằng AI
        </button>

        <!-- Perks -->
        <div class="pd-perks">
          <div class="pd-perk">
            <AppIcon name="arrow-right" size="16" /> Giao hàng miễn phí
          </div>
          <div class="pd-perk">
            <AppIcon name="shield" size="16" /> Bảo hành chính hãng
          </div>
        </div>

        <PremiumUpgrade :current-variant="selectedVariant" :all-variants="sortedVariants" @upgrade="handleUpgrade" />
      </div>
    </section>

    <!-- ═══ TABS: Mô tả / Thông số / Đánh giá ═══ -->
    <section class="pd-tabs-section">
      <div class="pd-tab-bar">
        <button class="pd-tab" :class="{ active: activeTab === 'description' }" @click="activeTab = 'description'">Mô tả
          chi tiết</button>
        <button class="pd-tab" :class="{ active: activeTab === 'specs' }" @click="activeTab = 'specs'">Thông số kỹ
          thuật</button>
        <button class="pd-tab" :class="{ active: activeTab === 'reviews' }" @click="activeTab = 'reviews'">Đánh giá
          khách hàng ({{ reviews.length }})</button>
      </div>

      <div class="pd-tab-content">
        <!-- Tab: Mô tả -->
        <div v-if="activeTab === 'description'" class="pd-desc-panel">
          <div class="pd-desc-body">
            <h3 class="pd-desc-title">Giới thiệu về {{ product.name }}</h3>
            <div class="pd-desc-text" :class="{ expanded: isDescriptionExpanded }">
              <div v-html="product.description"></div>
              <div class="pd-desc-fade" v-if="!isDescriptionExpanded"></div>
            </div>
            <button class="pd-desc-toggle" @click="isDescriptionExpanded = !isDescriptionExpanded">
              {{ isDescriptionExpanded ? 'Thu gọn ▲' : 'Xem thêm ▼' }}
            </button>
          </div>
          <div class="pd-specs-side">
            <h3 class="pd-specs-title">Chi tiết thông số</h3>
            <table class="pd-specs-table">
              <tr v-if="product.category">
                <td>Danh mục</td>
                <td>{{ product.category.name }}</td>
              </tr>
              <tr v-if="product.brand">
                <td>Thương hiệu</td>
                <td>{{ product.brand }}</td>
              </tr>
              <tr v-if="product.sku">
                <td>Mã SKU</td>
                <td>{{ product.sku }}</td>
              </tr>
              <tr v-if="product.weight">
                <td>Trọng lượng</td>
                <td>{{ product.weight }}</td>
              </tr>
              <tr v-if="product.material">
                <td>Chất liệu</td>
                <td>{{ product.material }}</td>
              </tr>
              <tr v-if="product.origin">
                <td>Xuất xứ</td>
                <td>{{ product.origin }}</td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Tab: Thông số -->
        <div v-if="activeTab === 'specs'" class="pd-specs-full">
          <table class="pd-specs-table full">
            <tr v-if="product.category">
              <td>Danh mục</td>
              <td>{{ product.category.name }}</td>
            </tr>
            <tr v-if="product.brand">
              <td>Thương hiệu</td>
              <td>{{ product.brand }}</td>
            </tr>
            <tr v-if="product.sku">
              <td>Mã SKU</td>
              <td>{{ product.sku }}</td>
            </tr>
            <tr v-if="product.weight">
              <td>Trọng lượng</td>
              <td>{{ product.weight }}</td>
            </tr>
            <tr v-if="product.material">
              <td>Chất liệu</td>
              <td>{{ product.material }}</td>
            </tr>
            <tr v-if="product.origin">
              <td>Xuất xứ</td>
              <td>{{ product.origin }}</td>
            </tr>
          </table>
          <div class="pd-desc-text expanded" v-if="product.short_description" v-html="product.short_description"
            style="margin-top:24px"></div>
        </div>

        <!-- Tab: Đánh giá -->
        <div v-if="activeTab === 'reviews'" class="pd-reviews-panel">
          <div v-if="reviews.length === 0" class="pd-no-reviews">Chưa có đánh giá nào cho sản phẩm này.</div>
          <div class="pd-review" v-for="review in reviews" :key="review.comment_id">
            <div class="pd-review-head">
              <img
                :src="(review.commenter_info || review.user)?.avatar_url ? getImageUrl((review.commenter_info || review.user).avatar_url) : 'https://placehold.co/40x40?text=U'"
                class="pd-review-avatar" />
              <div class="pd-review-meta">
                <strong>{{ (review.commenter_info || review.user)?.full_name || 'Ẩn danh' }}</strong>
                <div class="pd-stars sm">
                  <svg v-for="i in 5" :key="i" width="13" height="13" viewBox="0 0 24 24"
                    :fill="i <= review.rating ? '#F59E0B' : '#E5E7EB'" stroke="none">
                    <path
                      d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                  </svg>
                </div>
              </div>
              <span class="pd-review-date">{{ formatDate(review.created_at) }}</span>
            </div>
            <p class="pd-review-text">{{ review.content }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══ SẢN PHẨM TƯƠNG TỰ ═══ -->
    <section class="pd-related" v-if="relatedProducts.length > 0">
      <div class="pd-related-head">
        <div>
          <h2 class="pd-related-title">Sản phẩm tương tự</h2>
          <p class="pd-related-sub">Gợi ý những mẫu vợt cao cấp khác dành cho bạn.</p>
        </div>
        <router-link to="/product" class="pd-related-link">Xem tất cả →</router-link>
      </div>
      <div class="pd-related-grid">
        <ProductCard v-for="item in relatedProducts.slice(0, 4)" :key="item.product_id" :product="{
          id: item.product_id, name: item.name, slug: item.slug,
          price: formatPrice(item.min_price),
          originalPrice: item.compare_at_price ? formatPrice(item.compare_at_price) : null,
          image: getImageUrl(item.thumbnail_url),
          badge: item.is_featured ? 'Hot' : null,
          category_name: item.category?.name || '',
          discount_percent: 0, is_on_sale: false,
        }" />
      </div>
    </section>
  </main>

  <!-- Toast -->
  <Transition name="toast">
    <div v-if="toast.show" class="toast-notification" :class="toast.type">
      <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2.5">
        <polyline points="20 6 9 17 4 12" />
      </svg>
      <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="12" r="10" />
        <line x1="15" y1="9" x2="9" y2="15" />
        <line x1="9" y1="9" x2="15" y2="15" />
      </svg>
      <span>{{ toast.message }}</span>
    </div>
  </Transition>

  <!-- Virtual Try-On Modal -->
  <VirtualTryOnModal :show="showTryOn" :product-id="product?.product_id" :product-name="product?.name"
    :product-image-url="mainImageUrl" @close="showTryOn = false" />

  <!-- Modal Bảng Size -->
  <teleport to="body">
    <transition name="modal-fade">
      <div v-if="showSizeGuide" class="modal-overlay" @click.self="showSizeGuide = false">
        <div class="modal-content size-modal">
          <div class="modal-header">
            <h2 class="modal-title">Bảng size tham khảo (Vóc dáng người Việt)</h2>
            <button class="modal-close" @click="showSizeGuide = false">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
          <div class="modal-body">
            <p class="size-desc">Bảng tính mặc định được thiết kế dựa trên số đo chuẩn của người Việt Nam. Nếu bạn có số
              đo nằm giữa 2 size, lời khuyên là nên chọn size lớn hơn để có sự thoải mái nhất.</p>

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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="tip-icon" stroke="#E63B6F"
                  stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="16" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <span>Sản phẩm có độ co giãn nhẹ khoảng 2-3cm ở vòng bụng.</span>
              </div>
              <div class="tip-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="tip-icon" stroke="#E63B6F"
                  stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="16" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
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
.pd-wrapper {
  padding: 0 0 40px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: #2D3436;
}

/* Breadcrumb */
.pd-breadcrumb {
  font-size: 0.85rem;
  color: #636E72;
  padding: 16px 0;
}

.pd-breadcrumb a {
  color: #636E72;
  text-decoration: none;
}

.pd-breadcrumb a:hover {
  color: #E63B6F;
}

.pd-breadcrumb .sep {
  margin: 0 8px;
  color: #B2BEC3;
}

.pd-breadcrumb .current {
  color: #2D3436;
  font-weight: 600;
}

/* Main Grid */
.pd-main {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  margin-bottom: 48px;
}

/* Gallery */
.pd-gallery {
  display: flex;
  gap: 12px;
}

.pd-thumbs {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 72px;
  flex-shrink: 0;
}

.pd-thumb {
  width: 72px;
  height: 72px;
  border: 2px solid transparent;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  opacity: 0.5;
  transition: all 0.2s;
}

.pd-thumb.active,
.pd-thumb:hover {
  opacity: 1;
  border-color: #E63B6F;
}

.pd-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pd-main-img {
  flex: 1;
  aspect-ratio: 3/4;
  max-height: 520px;
  border: 1px solid #E9ECEF;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pd-main-img img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  animation: fadeIn 0.3s ease;
}

/* Info Panel */
.pd-info {
  display: flex;
  flex-direction: column;
}

.pd-badge {
  display: inline-block;
  background: #E63B6F;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 16px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
  width: fit-content;
}

.pd-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: #2D3436;
  line-height: 1.3;
  margin: 0 0 12px;
}

.pd-rating {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.pd-stars {
  display: flex;
  gap: 2px;
}

.pd-stars.sm {
  display: flex;
  gap: 1px;
}

.pd-rating-text {
  font-size: 0.85rem;
  color: #636E72;
}

.pd-price-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #E9ECEF;
}

.pd-price {
  font-size: 1.8rem;
  font-weight: 800;
  color: #E63B6F;
}

.pd-price-old {
  font-size: 1.1rem;
  color: #B2BEC3;
  text-decoration: line-through;
}

.pd-status {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  font-size: 0.9rem;
}

.pd-status-label {
  color: #636E72;
}

.pd-status-value {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
}

.pd-status-value.in-stock {
  color: #10B981;
}

/* Variants */
.pd-variants {
  margin-bottom: 20px;
}

.pd-var-label {
  font-size: 0.85rem;
  font-weight: 700;
  color: #2D3436;
  margin: 0 0 10px;
}

.pd-var-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pd-var-btn {
  padding: 8px 18px;
  border: 1.5px solid #E9ECEF;
  border-radius: 20px;
  background: #fff;
  font-size: 0.88rem;
  font-weight: 600;
  color: #2D3436;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-var-btn:hover:not(:disabled) {
  border-color: #E63B6F;
  color: #E63B6F;
}

.pd-var-btn.active {
  border-color: #E63B6F;
  background: rgba(230, 59, 111, 0.06);
  color: #E63B6F;
}

.pd-var-btn.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Quantity */
.pd-qty-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
}

.pd-qty-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #636E72;
}

.pd-qty {
  display: flex;
  align-items: center;
  border: 1.5px solid #e63b6e7d;
  border-radius: 20px;
  overflow: hidden;
}

.pd-qty button {
  width: 40px;
  height: 40px;
  background: #fff;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  color: #2D3436;
  transition: background 0.2s;
}

.pd-qty button:hover {
  background: #F8F9FA;
}

.pd-qty input {
  width: 48px;
  text-align: center;
  border: none;
  border-left: 1px solid #E9ECEF;
  border-right: 1px solid #E9ECEF;
  font-weight: 700;
  font-size: 0.95rem;
  outline: none;
  background: #fff;
  font-family: inherit;
}

/* CTA Buttons */
.pd-cta {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.pd-btn-cart {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 12px;
  background: #E63B6F;
  color: #fff;
  border: 2px solid #E63B6F;
  border-radius: 28px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-btn-cart:hover {
  background: #C4305D;
  border-color: #C4305D;
}

.pd-btn-cart:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.pd-btn-buy {
  flex: 1;
  padding: 12px 12px;
  background: #fff;
  color: #E63B6F;
  border: 2px solid #E63B6F;
  border-radius: 28px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-btn-buy:hover {
  background: #E63B6F;
  color: #fff;
}

/* AR Try-On Button */
.pd-btn-tryon {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  background: #FFF0F3;
  color: #E63B6F;
  border: 2px dashed #FFB8CC;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 20px;
  font-family: inherit;
}

.pd-btn-tryon:hover {
  background: #FFE4E9;
  border-color: #E63B6F;
}

/* Perks */
.pd-perks {
  display: flex;
  gap: 24px;
  padding: 16px 0;
  border-top: 1px solid #E9ECEF;
  margin-bottom: 20px;
}

.pd-perk {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: #636E72;
  font-weight: 500;
}

/* ── TABS ── */
.pd-tabs-section {
  margin-bottom: 48px;
}

.pd-tab-bar {
  display: flex;
  border-bottom: 2px solid #E9ECEF;
  gap: 0;
}

.pd-tab {
  padding: 14px 24px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  font-size: 0.95rem;
  font-weight: 600;
  color: #636E72;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-tab:hover {
  color: #E63B6F;
}

.pd-tab.active {
  color: #E63B6F;
  border-bottom-color: #E63B6F;
}

.pd-tab-content {
  padding: 32px 0;
}

/* Tab: Description */
.pd-desc-panel {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 40px;
}

.pd-desc-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #2D3436;
  margin: 0 0 16px;
}

.pd-desc-text {
  position: relative;
  max-height: 300px;
  overflow: hidden;
  line-height: 1.8;
  color: #636E72;
  font-size: 0.95rem;
  transition: max-height 0.4s ease;
}

.pd-desc-text.expanded {
  max-height: 5000px;
}

.pd-desc-text p {
  margin-bottom: 12px;
}

.pd-desc-fade {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 80px;
  background: linear-gradient(transparent, #fff);
  pointer-events: none;
}

.pd-desc-toggle {
  background: none;
  border: 1px solid #E9ECEF;
  border-radius: 8px;
  padding: 10px 20px;
  color: #E63B6F;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  margin-top: 16px;
  font-family: inherit;
  transition: all 0.2s;
}

.pd-desc-toggle:hover {
  background: rgba(230, 59, 111, 0.05);
  border-color: #E63B6F;
}

/* Specs Table */
.pd-specs-side {
  background: #F8F9FA;
  border-radius: 12px;
  padding: 24px;
}

.pd-specs-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: #2D3436;
  margin: 0 0 16px;
}

.pd-specs-table {
  width: 100%;
  border-collapse: collapse;
}

.pd-specs-table td {
  padding: 10px 0;
  font-size: 0.9rem;
  border-bottom: 1px solid #E9ECEF;
}

.pd-specs-table td:first-child {
  color: #636E72;
  font-weight: 500;
  width: 40%;
}

.pd-specs-table td:last-child {
  color: #2D3436;
  font-weight: 600;
}

.pd-specs-table.full {
  max-width: 600px;
}

.pd-specs-full {
  padding: 0;
}

/* Tab: Reviews */
.pd-reviews-panel {
  max-width: 800px;
}

.pd-no-reviews {
  color: #636E72;
  font-size: 0.95rem;
  padding: 24px 0;
}

.pd-review {
  border-bottom: 1px solid #E9ECEF;
  padding: 20px 0;
}

.pd-review:last-child {
  border-bottom: none;
}

.pd-review-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.pd-review-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.pd-review-meta {
  flex: 1;
}

.pd-review-meta strong {
  font-size: 0.95rem;
  color: #2D3436;
  display: block;
  margin-bottom: 2px;
}

.pd-review-date {
  font-size: 0.8rem;
  color: #B2BEC3;
}

.pd-review-text {
  font-size: 0.95rem;
  color: #636E72;
  line-height: 1.6;
  margin: 0;
}

/* ── RELATED ── */
.pd-related {
  margin-bottom: 40px;
}

.pd-related-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 24px;
}

.pd-related-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: #2D3436;
  margin: 0 0 4px;
}

.pd-related-sub {
  font-size: 0.9rem;
  color: #636E72;
  margin: 0;
}

.pd-related-link {
  color: #E63B6F;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
}

.pd-related-link:hover {
  text-decoration: underline;
}

.pd-related-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
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

.toast-enter-active {
  animation: slideInRight 0.3s ease;
}

.toast-leave-active {
  animation: slideOutRight 0.3s ease;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(3px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  padding: 20px;
}

.size-modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 650px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #E9ECEF;
  background: #F8F9FA;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #2D3436;
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #636E72;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #E9ECEF;
  color: #2D3436;
}

.modal-body {
  padding: 24px;
}

.size-desc {
  font-size: 0.95rem;
  color: #636E72;
  line-height: 1.6;
  margin-bottom: 20px;
}

.table-responsive {
  overflow-x: auto;
  margin-bottom: 24px;
  border-radius: 12px;
  border: 1px solid #E9ECEF;
}

.size-table {
  width: 100%;
  border-collapse: collapse;
}

.size-table th {
  background: #F8F9FA;
  color: #2D3436;
  font-weight: 700;
  padding: 14px 16px;
  font-size: 0.9rem;
  border-bottom: 2px solid #E9ECEF;
}

.size-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #F1F3F5;
  color: #636E72;
  font-size: 0.9rem;
}

.size-table td strong {
  color: #2D3436;
}

.size-tips {
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #FFF0F3;
  border: 1px dashed #FFB8CC;
  padding: 16px;
  border-radius: 12px;
}

.tip-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.tip-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.tip-item span {
  font-size: 0.9rem;
  color: #E63B6F;
  line-height: 1.5;
  font-weight: 500;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: all 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(40px);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideOutRight {
  from {
    opacity: 1;
    transform: translateX(0);
  }

  to {
    opacity: 0;
    transform: translateX(40px);
  }
}

/* Responsive */
@media (max-width: 1024px) {
  .pd-main {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .pd-desc-panel {
    grid-template-columns: 1fr;
  }

  .pd-related-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .pd-gallery {
    flex-direction: column-reverse;
  }

  .pd-thumbs {
    flex-direction: row;
    width: 100%;
    overflow-x: auto;
  }

  .pd-thumb {
    width: 60px;
    height: 60px;
  }

  .pd-tab {
    padding: 10px 14px;
    font-size: 0.85rem;
  }

  .pd-cta {
    flex-direction: column;
  }

  .pd-perks {
    flex-direction: column;
    gap: 10px;
  }
}

@media (max-width: 480px) {
  .pd-related-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }

  .pd-price {
    font-size: 1.4rem;
  }

  .pd-title {
    font-size: 1.3rem;
  }
}
</style>
