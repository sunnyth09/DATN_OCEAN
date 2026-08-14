<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { useFavorites } from '@/composables/useFavorites';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import PremiumUpgrade from '@/components/PremiumUpgrade.vue';
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';
import AppIcon from '@/components/AppIcon.vue';
import VirtualTryOnModal from '@/features/shop/components/VirtualTryOnModal.vue';
import { useFlyToCart } from '@/composables/useFlyToCart';
import { getStorageUrl } from '@/utils/url';
import { sanitizeHtml } from '@/utils/sanitize';
import { useToast } from '@/composables/useToast';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const cartStore = useCartStore();
// slug là computed để watch được khi route thay đổi (route param là :id, có thể là slug hoặc id)
const slug = computed(() => route.params.id);
const product = ref(null);
const isLoading = ref(true);
const isNotFound = ref(false);
const safeDescription = computed(() => sanitizeHtml(product.value?.description));
const safeShortDescription = computed(() => sanitizeHtml(product.value?.short_description));
const productImageRef = ref(null);
const showTryOn = ref(false);
const { flyToCart } = useFlyToCart();
const tryOnEnabled = import.meta.env.VITE_TRYON_ENABLED !== 'false';
const selectedVariant = ref(null);
const selectedColor = ref(null);
const selectedSize = ref(null);
const relatedProducts = ref([]);
const addingToCart = ref(false);
const buyingNow = ref(false);
const cartVersion = ref(0);
const showSizeGuide = ref(false);

const { showToast } = useToast();
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
  isLoading.value = true;
  isNotFound.value = false;
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
      let sessionId = localStorage.getItem('guest_session_id');
      if (!sessionId) {
          sessionId = 'sess_' + Math.random().toString(36).substring(2, 15);
          localStorage.setItem('guest_session_id', sessionId);
      }
      api.post('/tracking/view-product', { 
          product_id: product.value.product_id,
          session_id: sessionId
      }).catch(() => { });
    }
    fetchRelatedProducts(currentSlug);

    // Tự động chọn variant có giá thấp nhất (ưu tiên variant còn hàng)
    if (product.value.variants && product.value.variants.length > 0) {
      const purchasable = product.value.variants.filter(v => v.status === 'active' && v.stock > 0);
      const candidates = purchasable.length > 0 ? purchasable : product.value.variants;

      const lowestVariant = candidates.reduce((min, v) =>
        ((v.effective_price || v.price) < (min.effective_price || min.price) ? v : min), candidates[0]
      );

      if (lowestVariant.color) selectedColor.value = lowestVariant.color;
      if (lowestVariant.size) selectedSize.value = lowestVariant.size;
      selectedVariant.value = lowestVariant;
    }
  } catch (error) {
    console.error("Error fetching product:", error);
    isNotFound.value = true;
  } finally {
    isLoading.value = false;
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

const productTotalStock = computed(() => {
  if (product.value?.variants_sum_stock !== undefined && product.value?.variants_sum_stock !== null) {
    return Number(product.value.variants_sum_stock);
  }
  return product.value?.variants?.reduce((sum, variant) => sum + Number(variant.stock || 0), 0) || 0;
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

const isVariantPurchasable = (variant) => variant && variant.status === 'active' && variant.stock > 0;

const findVariantForSelection = (color = selectedColor.value, size = selectedSize.value) => {
  const variants = product.value?.variants || [];
  if (!variants.length) return null;

  if (color && size) {
    return variants.find(v => v.color === color && v.size === size) || null;
  }

  if (size) {
    return variants.find(v => v.size === size && isVariantPurchasable(v))
      || variants.find(v => v.size === size)
      || null;
  }

  if (color) {
    const colorVariants = variants.filter(v => v.color === color);
    return colorVariants.length === 1 ? colorVariants[0] : null;
  }

  return null;
};

watch(selectedColor, (newColor) => {
  activeImageIndex.value = 0;

  if (!newColor) {
    selectedVariant.value = findVariantForSelection(null, selectedSize.value);
    return;
  }

  if (selectedSize.value) {
    const match = findVariantForSelection(newColor, selectedSize.value);
    if (isVariantPurchasable(match)) {
      selectedVariant.value = match;
      return;
    }
    selectedSize.value = null;
    selectedVariant.value = null;
  }

  const colorVariants = product.value?.variants?.filter(v => v.color === newColor) || [];
  if (colorVariants.length === 1) {
    selectedSize.value = colorVariants[0].size;
    selectedVariant.value = colorVariants[0];
  } else if (colorVariants.length > 1 && availableSizes.value.length === 0) {
    // If there are multiple variants with the same color but NO sizes available,
    // we should still select one so the user can purchase.
    const active = colorVariants.find(v => isVariantPurchasable(v));
    selectedVariant.value = active || colorVariants[0];
  } else if (colorVariants.length > 1) {
    // There are sizes to choose from, clear the selected variant until size is chosen
    selectedVariant.value = null;
  }
});

// Khi chọn size → tìm variant đúng theo màu hiện tại, không làm mất lựa chọn màu.
watch(selectedSize, (newSize) => {
  if (newSize) {
    selectedVariant.value = findVariantForSelection(selectedColor.value, newSize);
  } else {
    // If size is cleared but color is selected, try to find the color variant
    selectedVariant.value = findVariantForSelection(selectedColor.value, null);
  }
});
const mainImageUrl = computed(() => {
  const imgs = allImages.value;
  if (imgs.length === 0) return getImageUrl(null);
  const idx = activeImageIndex.value < imgs.length ? activeImageIndex.value : 0;
  return getImageUrl(imgs[idx]?.image_url);
});

const nextImage = () => {
  if (allImages.value.length === 0) return;
  activeImageIndex.value = (activeImageIndex.value + 1) % allImages.value.length;
};
const prevImage = () => {
  if (allImages.value.length === 0) return;
  activeImageIndex.value = (activeImageIndex.value - 1 + allImages.value.length) % allImages.value.length;
};

const zoomStyle = ref({});
const handleZoom = (e) => {
  if (window.innerWidth <= 768 || ('ontouchstart' in window && window.innerWidth <= 1024)) return;
  const { left, top, width, height } = e.currentTarget.getBoundingClientRect();
  const x = ((e.clientX - left) / width) * 100;
  const y = ((e.clientY - top) / height) * 100;
  zoomStyle.value = {
    transformOrigin: `${x}% ${y}%`,
    transform: 'scale(2)'
  };
};
const resetZoom = () => {
  zoomStyle.value = {
    transformOrigin: 'center center',
    transform: 'scale(1)'
  };
};

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

const selectedVariantCartQty = computed(() => {
  cartVersion.value;
  if (!selectedVariant.value || authStore.isAuthenticated) return 0;

  const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
  const item = localItems.find(i => i.variant_id === selectedVariant.value.variant_id);
  return Number(item?.quantity || 0);
});

const selectedVariantRemainingQty = computed(() => {
  if (!selectedVariant.value) return 0;
  return Math.max(selectedVariant.value.stock - selectedVariantCartQty.value, 0);
});

const canPurchaseSelectedVariant = computed(() => {
  return isVariantPurchasable(selectedVariant.value)
    && quantity.value >= 1
    && quantity.value <= selectedVariant.value.stock
    && (authStore.isAuthenticated || quantity.value <= selectedVariantRemainingQty.value);
});

const ctaDisabledReason = computed(() => {
  if (productTotalStock.value <= 0) return 'Hết hàng';
  if (!selectedVariant.value) return 'Vui lòng chọn phiên bản';
  if (!isVariantPurchasable(selectedVariant.value)) return 'Phiên bản hết hàng';
  if (quantity.value > selectedVariant.value.stock) return `Chỉ còn ${selectedVariant.value.stock} sản phẩm`;
  if (quantity.value < 1) return 'Số lượng tối thiểu là 1';
  if (!authStore.isAuthenticated && selectedVariantRemainingQty.value <= 0) {
    return `Đã đạt tối đa tồn kho (${selectedVariant.value.stock})`;
  }
  if (!authStore.isAuthenticated && quantity.value > selectedVariantRemainingQty.value) {
    return `Chỉ thêm được ${selectedVariantRemainingQty.value} sản phẩm nữa`;
  }
  return '';
});

const normalizeQuantity = () => {
  const max = selectedVariant.value?.stock || null;
  const raw = Number.parseInt(quantity.value, 10);

  if (!Number.isFinite(raw) || raw < 1) {
    quantity.value = 1;
    return;
  }

  if (max && raw > max) {
    quantity.value = max;
    return;
  }

  quantity.value = raw;
};

const onQuantityInput = (event) => {
  const value = event.target.value.replace(/[^0-9]/g, '');
  event.target.value = value;
  quantity.value = value === '' ? '' : Number(value);
};

const increaseQuantity = () => {
  normalizeQuantity();
  const max = selectedVariant.value?.stock;
  if (max && quantity.value >= max) {
    return;
  }
  quantity.value++;
};
const decreaseQuantity = () => {
  normalizeQuantity();
  if (quantity.value > 1) quantity.value--;
};

const syncCartVersion = () => {
  cartVersion.value++;
};

const addToCart = async () => {
  if (addingToCart.value) return false;

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

  // Khách vãng lai (chưa đăng nhập): lưu giỏ vào localStorage,
  // đồng bộ với trang giỏ hàng (/cart) và checkout — KHÔNG ép đăng nhập.
  if (!authStore.isAuthenticated) {
    try {
      const localItems = JSON.parse(localStorage.getItem('cart_items') || '[]');
      const idx = localItems.findIndex(i => i.variant_id === selectedVariant.value.variant_id);
      const currentCartQty = idx !== -1 ? Number(localItems[idx].quantity || 0) : 0;
      const nextCartQty = currentCartQty + quantity.value;

      if (nextCartQty > selectedVariant.value.stock) {
        const remaining = Math.max(selectedVariant.value.stock - currentCartQty, 0);
        showToast(
          remaining > 0
            ? `Bạn đã có ${currentCartQty} sản phẩm trong giỏ. Chỉ có thể thêm tối đa ${remaining} sản phẩm nữa!`
            : `Sản phẩm này trong giỏ đã đạt tối đa tồn kho (${selectedVariant.value.stock})!`,
          'error'
        );
        return false;
      }

      if (idx !== -1) {
        localItems[idx].quantity = nextCartQty;
      } else {
        localItems.push({
          variant_id: selectedVariant.value.variant_id,
          quantity: quantity.value,
          selected: true,
        });
      }
      localStorage.setItem('cart_items', JSON.stringify(localItems));
      cartVersion.value++;
      await cartStore.fetchCount();
      if (productImageRef.value) {
        await flyToCart(productImageRef.value, '#cart-icon');
      }
      showToast('Đã thêm vào giỏ hàng!', 'cart', {
        name: product.value.name,
        variant_id: selectedVariant.value.variant_id,
        variant: (selectedVariant.value.color || '') + ' ' + (selectedVariant.value.size || ''),
        qty: quantity.value,
        image: selectedVariant.value.image_url ? getImageUrl(selectedVariant.value.image_url) : getImageUrl(allImages.value[0]?.image_url)
      });
      return true;
    } finally {
      addingToCart.value = false;
    }
  }
  try {
    const response = await cartStore.addItem({
      variantId: selectedVariant.value.variant_id,
      quantity: quantity.value,
    });
    if (response.status === 'success') {
      if (productImageRef.value) {
        await flyToCart(productImageRef.value, '#cart-icon');
      }
      showToast(response.message, 'cart', {
        name: product.value.name,
        variant_id: selectedVariant.value.variant_id,
        variant: (selectedVariant.value.color || '') + ' ' + (selectedVariant.value.size || ''),
        qty: quantity.value,
        image: selectedVariant.value.image_url ? getImageUrl(selectedVariant.value.image_url) : getImageUrl(allImages.value[0]?.image_url)
      });
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
  if (buyingNow.value) return;

  // Mua nhanh: CHỈ đặt riêng sản phẩm này, KHÔNG đụng tới giỏ hàng,
  // KHÔNG gộp các sản phẩm khác đang có trong giỏ.
  if (!selectedVariant.value) {
    showToast('Vui lòng chọn phiên bản sản phẩm!', 'error');
    return;
  }
  if (selectedVariant.value.stock <= 0) {
    showToast('Sản phẩm phiên bản này đã hết hàng!', 'error');
    return;
  }
  if (quantity.value > selectedVariant.value.stock) {
    showToast(`Số lượng trong kho không đủ (chỉ còn ${selectedVariant.value.stock} sản phẩm)!`, 'error');
    return;
  }
  if (quantity.value < 1) {
    showToast('Số lượng tối thiểu là 1!', 'error');
    return;
  }

  buyingNow.value = true;
  sessionStorage.setItem('buy_now_item', JSON.stringify({
    variant_id: selectedVariant.value.variant_id,
    quantity: quantity.value,
  }));
  router.push({ path: '/checkout', query: { buy_now: '1' } });
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

const openImage = (img) => {
  if (!img) return;
  const url = getImageUrl(img);
  window.open(url, '_blank');
};

const parseReviewImages = (images) => {
  if (!images) return [];
  
  let parsed = images;
  
  // Keep parsing if it's a string that looks like JSON
  while (typeof parsed === 'string') {
    try {
      let temp = JSON.parse(parsed);
      if (temp === parsed) break;
      parsed = temp;
    } catch (e) {
      parsed = parsed.replace(/[\[\]"]/g, '').split(',').map(s => s.trim());
      break;
    }
  }

  // At this point, parsed should be an array
  if (Array.isArray(parsed)) {
    return parsed.map(s => {
      let cleaned = String(s).trim();
      
      // Replace all backslashes with forward slashes FIRST! (Important for Windows paths)
      cleaned = cleaned.replace(/\\/g, '/');
      
      // Remove any leftover double quotes or single quotes from double-encoding artifacts
      cleaned = cleaned.replace(/["']/g, '');
      
      // Deduplicate forward slashes
      cleaned = cleaned.replace(/\/+/g, '/');
      
      return cleaned;
    }).filter(s => s);
  }
  
  return [];
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
  window.addEventListener('cart-updated', syncCartVersion);
  window.addEventListener('storage', syncCartVersion);
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

onBeforeUnmount(() => {
  window.removeEventListener('cart-updated', syncCartVersion);
  window.removeEventListener('storage', syncCartVersion);
});

</script>

<template>
  <main class="pd-wrapper container" v-if="isLoading">
    <!-- Skeleton Loading -->
    <div style="padding: 40px 0;">
      <ProductSkeleton />
    </div>
  </main>
  
  <main class="pd-wrapper container" v-else-if="isNotFound">
    <!-- 404 State -->
    <div class="empty-state text-center" style="padding: 100px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
       <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:24px; opacity: 0.9;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
       <h2 style="font-weight: 800; font-size: 2rem; margin-bottom: 12px; color: var(--text-main);">Sản phẩm không tồn tại</h2>
       <p style="color: #636E72; margin-bottom: 32px; font-size: 1.1rem; max-width: 500px;">Sản phẩm này có thể đã bị xóa, tạm ẩn hoặc đường dẫn không chính xác. Bạn vui lòng kiểm tra lại nhé!</p>
       <router-link to="/" style="display: inline-flex; align-items: center; background-color: var(--primary); color: white; padding: 12px 28px; border-radius: 12px; font-size: 1rem; font-weight: 600; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);">Quay lại Trang chủ</router-link>
    </div>
  </main>

  <main class="pd-wrapper container" v-else-if="product">
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
        <div class="pd-main-img" :class="{ 'is-out-of-stock': productTotalStock <= 0 }"
          @mousemove="handleZoom" @mouseleave="resetZoom">
          <img ref="productImageRef" :src="mainImageUrl" :alt="product.name" :key="activeImageIndex" :style="zoomStyle" />

          <button v-if="allImages.length > 1" class="pd-gallery-nav prev" @click.stop="prevImage">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <button v-if="allImages.length > 1" class="pd-gallery-nav next" @click.stop="nextImage">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
          </button>

          <div v-if="productTotalStock <= 0" class="stock-overlay" aria-label="Hết hàng">
            <span class="stock-overlay-text">Hết hàng</span>
          </div>
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
          <span class="pd-stock-value" v-else>{{ productTotalStock }}</span>
        </div>



        <!-- Variant chips -->
        <div class="pd-variants" v-if="uniqueColors.length > 0">
          <h4 class="pd-var-label">Phân bản / Màu sắc</h4>
          <div class="pd-var-options">
            <button v-for="color in uniqueColors" :key="color" class="pd-var-btn"
              :class="{ active: selectedColor === color }"
              @click="selectedColor = color">{{ color }}</button>
          </div>
        </div>

        <div class="pd-variants" v-if="availableSizes.length > 0">
          <h4 class="pd-var-label">Kích cỡ</h4>
          <div class="pd-var-options">
            <button v-for="s in availableSizes" :key="s.size" class="pd-var-btn"
              :class="{ active: selectedSize === s.size, disabled: !s.available }" :disabled="!s.available"
              @click="s.available && (selectedSize = s.size)">{{ s.size || 'Mặc định'
              }}</button>
          </div>
        </div>

        <!-- Số lượng -->
        <div class="pd-qty-row">
          <span class="pd-qty-label">Số lượng</span>
          <div class="pd-qty">
            <button type="button" @click="decreaseQuantity" :disabled="quantity <= 1" class="qty-btn-align">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
            </button>
            <input
              type="text"
              inputmode="numeric"
              pattern="[0-9]*"
              :value="quantity"
              aria-label="Số lượng sản phẩm"
              @input="onQuantityInput"
              @blur="normalizeQuantity()"
              @keydown.enter.prevent="normalizeQuantity()"
            />
            <button type="button" @click="increaseQuantity" :disabled="selectedVariant && quantity >= selectedVariant.stock" class="qty-btn-align">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
            </button>
          </div>
        </div>

        <!-- Lỗi validation inline -->
        <p v-if="ctaDisabledReason" style="color: #ef4444; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; margin-top: -10px; margin-bottom: 16px;">
          <AppIcon name="alert-circle" size="16" style="margin-right: 4px;" />
          {{ ctaDisabledReason }}
        </p>

        <!-- CTA -->
        <div class="pd-cta">
          <button class="pd-btn-cart" @click="addToCart"
            :disabled="addingToCart || buyingNow || !canPurchaseSelectedVariant"
            :title="ctaDisabledReason || 'Thêm vào giỏ hàng'">
            <AppIcon name="cart" size="18" />
            {{ addingToCart ? 'Đang thêm...' : 'Thêm Vào Giỏ Hàng' }}
          </button>
          <button class="pd-btn-buy" @click="buyNow"
            :disabled="addingToCart || buyingNow || !canPurchaseSelectedVariant"
            :title="ctaDisabledReason || 'Đặt hàng nhanh'">
            {{ buyingNow ? 'Đang chuyển...' : 'Đặt Hàng Nhanh' }}
          </button>
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
            <AppIcon name="truck" size="16" /> Giao hàng miễn phí
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
          khách hàng ({{ product.rating_count || 0 }})</button>
      </div>

      <div class="pd-tab-content">
        <!-- Tab: Mô tả -->
        <div v-if="activeTab === 'description'" class="pd-desc-panel">
          <div class="pd-desc-body">
            <h3 class="pd-desc-title">Giới thiệu về {{ product.name }}</h3>
            <div class="pd-desc-text" :class="{ expanded: isDescriptionExpanded }">
              <div v-html="safeDescription"></div>
              <div class="pd-desc-fade" v-if="!isDescriptionExpanded"></div>
            </div>
            <button class="pd-desc-toggle" @click="isDescriptionExpanded = !isDescriptionExpanded">
              {{ isDescriptionExpanded ? 'Thu gọn' : 'Xem thêm' }}
              <svg v-if="!isDescriptionExpanded" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9l6 6 6-6"/>
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 15l-6-6-6 6"/>
              </svg>
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
                <td>{{ product.brand?.name || product.brand }}</td>
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
              <td>{{ product.brand?.name || product.brand }}</td>
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
          <div class="pd-desc-text expanded" v-if="product.short_description" v-html="safeShortDescription"
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
            <div class="pd-review-text" v-html="sanitizeHtml(review.content)"></div>
            <div class="pd-review-images" v-if="parseReviewImages(review.images).length > 0">
              <img v-for="(img, idx) in parseReviewImages(review.images)" :key="idx" :src="getImageUrl(img)" alt="Review image" @click="openImage(img)" style="cursor: pointer;" title="Nhấn để xem ảnh lớn" />
            </div>
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
          min_price: item.min_price,
          original_price: item.compare_at_price,
          thumbnail_url: item.thumbnail_url,
          badge: item.is_featured ? 'Hot' : null,
          category_name: item.category?.name || '',
          discount_percent: 0, is_on_sale: false,
        }" />
      </div>
    </section>
  </main>

  <!-- Virtual Try-On Modal -->
  <VirtualTryOnModal v-if="product?.product_id" :show="showTryOn" :product-id="product?.product_id" :product-name="product?.name"
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
  color: var(--text-main);
}

/* Breadcrumb */
.pd-breadcrumb {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  font-size: 0.85rem;
  color: #636E72;
  padding: 16px 0;
  line-height: 1.5;
}

.pd-breadcrumb a {
  color: #636E72;
  text-decoration: none;
  transition: color 0.2s;
}

.pd-breadcrumb a:hover {
  color: var(--primary);
}

.pd-breadcrumb .sep {
  margin: 0 8px;
  color: #B2BEC3;
}

.pd-breadcrumb .current {
  color: var(--text-main);
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
  border-color: var(--primary);
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
  background: var(--card-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: zoom-in;
}

.pd-main-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s ease-in-out;
}

.pd-gallery-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(255, 255, 255, 0.9);
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0,0,0,0.15);
  transition: all 0.2s;
  z-index: 10;
  color: #333;
}
.pd-gallery-nav:hover {
  background: var(--card-bg);
  color: var(--primary);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.pd-gallery-nav.prev {
  left: 10px;
}
.pd-gallery-nav.next {
  right: 10px;
}

.pd-main-img.is-out-of-stock img {
  opacity: 0.6;
  filter: grayscale(80%);
}

.stock-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  backdrop-filter: blur(2px);
  z-index: 3;
}

.stock-overlay-text {
  background: rgba(255, 255, 255, 0.95);
  color: var(--text-main);
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  padding: 8px 24px;
  border-radius: 999px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
  background: var(--primary);
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
  color: var(--text-main);
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
  color: var(--primary);
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
  color: var(--text-main);
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
  background: var(--card-bg);
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text-main);
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-var-btn:hover:not(:disabled) {
  border-color: var(--primary);
  color: var(--primary);
}

.pd-var-btn.active {
  border-color: var(--primary);
  background: rgba(230, 59, 111, 0.06);
  color: var(--primary);
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
  background: var(--card-bg);
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  color: var(--text-main);
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
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
  background: var(--card-bg);
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
  background: var(--primary);
  color: #fff;
  border: 2px solid var(--primary);
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
  background: var(--card-bg);
  color: var(--primary);
  border: 2px solid var(--primary);
  border-radius: 28px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.pd-btn-buy:hover {
  background: var(--primary);
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
  color: var(--primary);
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
  border-color: var(--primary);
}

/* Perks */
.pd-perks { display: flex; gap: 24px; padding: 16px 0; border-top: 1px solid #E9ECEF; margin-bottom: 16px; }
.pd-perk { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #636E72; font-weight: 500; }

/* Share & QR Group */
.pd-share-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 20px;
}

.pd-btn-share {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 12px;
  background: #1877F2;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  margin: 0 !important;
  font-family: inherit;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pd-btn-share:hover { background: #166FE5; }
.pd-btn-share:disabled { opacity: 0.7; cursor: not-allowed; }

.pd-btn-share.qr {
  background-color: #f8f9fa !important;
  color: #212529 !important;
  border: 1px solid #dee2e6 !important;
}
.pd-btn-share.qr:hover {
  background-color: #e9ecef !important;
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
  color: var(--primary);
}

.pd-tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
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
  color: var(--text-main);
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: none;
  border: 1px solid #E9ECEF;
  border-radius: 8px;
  padding: 10px 20px;
  color: var(--primary);
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  margin-top: 16px;
  font-family: inherit;
  transition: all 0.2s;
}

.pd-desc-toggle:hover {
  background: #FFF0F3;
  border-color: var(--primary);
}
/* Specs Table */
.pd-specs-side {
  background: #F8F9FA;
  border-radius: 12px;
  padding: 24px;
}

.pd-reviews-panel { padding-top: 10px; }
.pd-no-reviews { text-align: center; color: #64748b; padding: 40px 0; }
.pd-review {
  padding: 24px 0; border-bottom: 1px solid #f1f5f9;
}
.pd-review-head {
  display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
}
.pd-review-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.pd-review-meta { display: flex; flex-direction: column; gap: 4px; }
.pd-review-meta strong { font-size: 0.95rem; color: var(--text-main); }
.pd-review-date { margin-left: auto; font-size: 0.85rem; color: #94a3b8; }
.pd-review-text { font-size: 0.95rem; line-height: 1.6; color: #475569; margin-bottom: 12px; }
.pd-review-images { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.pd-review-images img { width: 80px; height: 80px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 1px solid #e2e8f0; transition: transform 0.2s; }
.pd-review-images img:hover { transform: scale(1.05); }

.pd-specs-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
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
  color: var(--text-main);
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
  color: var(--text-main);
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
  color: var(--text-main);
  margin: 0 0 4px;
}

.pd-related-sub {
  font-size: 0.9rem;
  color: #636E72;
  margin: 0;
}

.pd-related-link {
  color: var(--primary);
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
  background: var(--card-bg);
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
  color: var(--text-main);
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
  color: var(--text-main);
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
  color: var(--text-main);
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
  color: var(--text-main);
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
  color: var(--primary);
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

  .pd-desc  .description-content img {
    max-width: 100%;
    height: auto;
  }

  .pd-desc-panel {
    grid-template-columns: 1fr;
  }

  .pd-related-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .pd-breadcrumb {
    flex-wrap: nowrap;
    white-space: nowrap;
    overflow: hidden;
    padding: 12px 0 14px;
    font-size: 0.8rem;
  }

  .pd-breadcrumb a,
  .pd-breadcrumb .sep {
    flex-shrink: 0;
  }

  .pd-breadcrumb .current {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
  }

  .pd-main-img {
    cursor: default !important;
  }

  .pd-main-img img {
    transform: none !important;
  }

  .pd-gallery {
    flex-direction: column-reverse;
  }

  .pd-thumbs {
    flex-direction: row;
    width: 100%;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
  }
  .pd-thumbs::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
  }

  .pd-thumb {
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    scroll-snap-align: center;
  }

  .pd-tab {
    padding: 10px 14px;
    font-size: 0.85rem;
  }

  .pd-var-options {
    flex-wrap: wrap;
  }

  .pd-cta {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px 16px;
    background: #fff;
    box-shadow: 0 -4px 16px rgba(0,0,0,0.1);
    z-index: 1000;
    flex-direction: row;
    gap: 8px;
    margin: 0;
  }

  .pd-cta button {
    flex: 1;
    font-size: 0.85rem;
    padding: 12px 0;
  }

  /* Để nội dung không bị che bởi sticky bottom bar */
  .pd-wrapper {
    padding-bottom: 80px;
  }

  .pd-perks {
    flex-direction: row;
    justify-content: space-around;
    background: #f8f9fa;
    padding: 10px 8px;
    border-radius: 8px;
    border: none;
    margin-bottom: 16px;
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
