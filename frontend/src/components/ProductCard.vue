<script setup>
import { computed, ref } from "vue";
import { useToast } from "@/composables/useToast";
import { useFavorites } from "@/composables/useFavorites";
import { useCartStore } from "@/stores/cart";
import { useFlyToCart } from "@/composables/useFlyToCart";
import AppIcon from "@/icons/AppIcon.vue";
import ProductVariantAddToCartModal from "@/components/ProductVariantAddToCartModal.vue";
import api from "@/axios";
import { getStorageUrl } from "@/utils/url";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(["unfavorite"]);
const cartStore = useCartStore();
const { showToast } = useToast();
const { isFavorited, toggleFavorite } = useFavorites();
const { flyToCart } = useFlyToCart();

const productImageRef = ref(null);
const isAddingToCart = ref(false);

// === VARIANT MODAL STATE ===
const showVariantModal = ref(false);
const variants = ref([]);
const hasFetchedVariants = ref(false);
const selectedColor = ref(null);
const selectedSize = ref(null);
const confirming = ref(false);
const quantity = ref(1);

const normalizeQuantity = (value) => {
    const parsed = Number.parseInt(String(value ?? '').replace(/[^0-9]/g, ''), 10);
    return Number.isSafeInteger(parsed) ? parsed : 1;
};

const increaseQuantity = () => {
    if (selectedVariant.value && quantity.value < selectedVariant.value.stock) {
        quantity.value++;
    } else if (!selectedVariant.value) {
        quantity.value++;
    } else {
        showToast(`Chỉ còn ${selectedVariant.value.stock} sản phẩm trong kho!`, "warning");
    }
};

const decreaseQuantity = () => {
    if (quantity.value > 1) quantity.value--;
};

const uniqueColors = computed(() => {
    const colors = [...new Set(variants.value.map(v => v.color).filter(Boolean))];
    return colors;
});

const hasColors = computed(() => uniqueColors.value.length > 0);

const availableSizes = computed(() => {
    const vars = variants.value;
    if (!vars.length) return [];

    const filtered = selectedColor.value
        ? vars.filter(v => v.color === selectedColor.value)
        : vars;

    const sizeMap = {};
    filtered.forEach(v => {
        const key = v.size || '__no_size__';
        if (!sizeMap[key]) sizeMap[key] = { size: v.size, stock: 0, variant_id: v.variant_id };
        sizeMap[key].stock += v.stock;
        sizeMap[key].variant_id = v.variant_id;
    });
    return Object.values(sizeMap);
});

const selectedVariant = computed(() => {
    const vars = variants.value;
    const color = selectedColor.value;
    const size = selectedSize.value;

    if (!vars.length) return null;

    if (!hasColors.value && size) {
        return vars.find(v => v.size === size) || null;
    }
    if (color && size) {
        return vars.find(v => v.color === color && v.size === size) || null;
    }
    if (color && !availableSizes.value.some(s => s.size)) {
        return vars.find(v => v.color === color) || null;
    }
    return null;
});

const selectColor = (color) => {
    selectedColor.value = color;
    const available = variants.value
        .filter(v => v.color === color)
        .map(v => v.size);
    if (!available.includes(selectedSize.value)) {
        selectedSize.value = null;
    }
};

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === "") {
        return "Liên hệ";
    }

    const num = Number(value);
    if (!isNaN(num)) {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(num);
    }

    return value;
};

const numericDiscount = computed(() => {
    const value = Number(props.product.discount_percent || 0);
    return Number.isFinite(value) ? Math.round(value) : 0;
});

// ── Stock status ────────────────────────────────────────────────────────
const totalStock = computed(() => {
    // Ưu tiên dùng variants_sum_stock từ API (do withSum trả về)
    if (props.product.variants_sum_stock !== undefined && props.product.variants_sum_stock !== null) {
        return Number(props.product.variants_sum_stock);
    }
    // Fallback: tính từ mảng variants nếu có
    if (Array.isArray(props.product.variants)) {
        return props.product.variants.reduce((sum, v) => sum + (Number(v.stock) || 0), 0);
    }
    return null; // Không có thông tin → không hiển thị
});

// Sản phẩm đã hết hàng hoàn toàn
const isOutOfStock = computed(() => totalStock.value !== null && totalStock.value <= 0);

// Sản phẩm sắp hết hàng (còn nhưng <= ngưỡng cảnh báo)
const LOW_STOCK_THRESHOLD = 5;
const isLowStock = computed(() => totalStock.value !== null && totalStock.value > 0 && totalStock.value <= LOW_STOCK_THRESHOLD);

const badgeLabel = computed(() => {
    if (numericDiscount.value > 0) {
        return `-${numericDiscount.value}%`;
    }

    if (props.product.badge === "New" || props.product.badge === "Mới") {
        return "Mới";
    }

    if (props.product.badge === "Hot") {
        return "Mới";
    }

    return props.product.badge || "";
});

const badgeClass = computed(() => {
    if (numericDiscount.value > 0) return "badge-sale";
    return "badge-new";
});

const productLink = computed(() => {
    if (props.product.slug) {
        return `/product/${props.product.slug}`;
    }

    return "/product";
});

const productImage = computed(() => props.product.image || props.product.thumbnail_url || props.product.main_image?.thumbnail_url || "");
const productImageUrl = computed(() => {
    let path = productImage.value;
    if (!path || path === "0") return "";
    return getStorageUrl(path);
});
const variantImageUrl = computed(() => {
    if (!selectedVariant.value?.image_url || selectedVariant.value.image_url === '0') return productImageUrl.value;
    return getStorageUrl(selectedVariant.value.image_url);
});

const productCategory = computed(() => props.product.category_name || props.product.category?.name || "");
const productId = computed(() => props.product.id || props.product.product_id || null);
const defaultVariantId = computed(() =>
    props.product.variant_id ||
    props.product.default_variant_id ||
    props.product.lowest_price_variant?.variant_id ||
    props.product.lowestPriceVariant?.variant_id ||
    null,
);
const currentPrice = computed(() => formatCurrency(props.product.min_price || 0));
const originalPrice = computed(() => {
    if (!props.product.original_price) return "";
    if (props.product.original_price === props.product.min_price) return "";
    return formatCurrency(props.product.original_price);
});

const handleToggleFav = async (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (!productId.value) return;

    const wasFavorited = isFavorited(productId.value);
    const success = await toggleFavorite(productId.value);
    if (success && wasFavorited) {
        emit("unfavorite", productId.value);
    }
};

const handleConfirmAddToCart = async () => {
    if (!selectedVariant.value || confirming.value) return;

    const safeQuantity = normalizeQuantity(quantity.value);
    if (safeQuantity < 1) {
        quantity.value = 1;
        showToast("Số lượng tối thiểu là 1.", "danger");
        return;
    }

    if (selectedVariant.value.stock <= 0) {
        showToast("Sản phẩm phiên bản này đã hết hàng!", "danger");
        return;
    }

    if (safeQuantity > selectedVariant.value.stock) {
        quantity.value = selectedVariant.value.stock;
        showToast(`Số lượng trong kho không đủ (chỉ còn ${selectedVariant.value.stock} sản phẩm)!`, "danger");
        return;
    }

    quantity.value = safeQuantity;
    confirming.value = true;
    try {
        const result = await cartStore.addItem({
            variantId: selectedVariant.value.variant_id,
            quantity: safeQuantity,
        });

        if (result.status === "unauthenticated") {
            // lưu localstorage khi chưa login từ modal
            let cartItems = JSON.parse(localStorage.getItem("cart_items") || "[]");
            const index = cartItems.findIndex(
                (item) => item.variant_id === selectedVariant.value.variant_id
            );
            if (index !== -1) {
                cartItems[index].quantity += safeQuantity;
            } else {
                cartItems.push({
                    variant_id: selectedVariant.value.variant_id,
                    quantity: safeQuantity,
                });
            }
            localStorage.setItem("cart_items", JSON.stringify(cartItems));

            if (productImageRef.value) {
                await flyToCart(productImageRef.value, '#cart-icon');
            }

            showToast("Đã thêm vào giỏ hàng (tạm thời)", "success");
            showVariantModal.value = false;
            window.dispatchEvent(new Event('cart-updated'));
            return;
        }

        if (productImageRef.value) {
            await flyToCart(productImageRef.value, '#cart-icon');
        }

        showToast(result.message || "Đã thêm vào giỏ hàng", "success");
        showVariantModal.value = false;
        window.dispatchEvent(new Event('cart-updated'));
    } catch (error) {
        const message = error.response?.data?.message || "Không thể thêm vào giỏ hàng.";
        showToast(message, "danger");
    } finally {
        confirming.value = false;
    }
};

const handleAddToCart = async (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (isAddingToCart.value) return;

    // 1. Fetch variants if not fetched yet
    if (!hasFetchedVariants.value) {
        try {
            isAddingToCart.value = true;
            const res = await api.get(`/products/${productId.value}/variants`);
            variants.value = res.data.data || [];
            hasFetchedVariants.value = true;
        } catch (error) {
            console.error("Error fetching variants:", error);
            showToast("Không thể tải thông tin sản phẩm.", "danger");
            return;
        } finally {
            isAddingToCart.value = false;
        }
    }

    // 2. Check if product has selectable variants
    const hasSelectable = variants.value.length > 0 && (variants.value.some(v => v.color) || variants.value.some(v => v.size));

    if (hasSelectable) {
        // Reset selections and open modal
        selectedColor.value = null;
        selectedSize.value = null;
        quantity.value = 1;
        showVariantModal.value = true;
    } else {
        // Add direct to cart
        const activeVariant = variants.value.find(v => v.variant_id === defaultVariantId.value) || (variants.value.length > 0 ? variants.value[0] : null);
        if (!activeVariant || activeVariant.stock <= 0) {
            showToast("Sản phẩm tạm thời hết hàng.", "danger");
            return;
        }

        try {
            isAddingToCart.value = true;
            const result = await cartStore.addItem({
                variantId: activeVariant.variant_id,
                quantity: 1,
            });

            // lưu localstorage khi chưa login
            if (result.status === "unauthenticated") {
                let cartItems = JSON.parse(localStorage.getItem("cart_items") || "[]");
                const index = cartItems.findIndex(
                    (item) => item.variant_id === activeVariant.variant_id
                );
                if (index !== -1) {
                    cartItems[index].quantity += 1;
                } else {
                    cartItems.push({
                        variant_id: activeVariant.variant_id,
                        quantity: 1,
                    });
                }
                localStorage.setItem("cart_items", JSON.stringify(cartItems));

                if (productImageRef.value) {
                    await flyToCart(productImageRef.value, '#cart-icon');
                }

                showToast("Đã thêm vào giỏ hàng (tạm thời)", "success");
                window.dispatchEvent(new Event('cart-updated'));
                return;
            }

            if (productImageRef.value) {
                await flyToCart(productImageRef.value, '#cart-icon');
            }

            showToast(result.message || "Đã thêm vào giỏ hàng", "success");
            window.dispatchEvent(new Event('cart-updated'));
        } catch (error) {
            const message = error.response?.data?.message || "Không thể thêm vào giỏ hàng.";
            showToast(message, "danger");
        } finally {
            isAddingToCart.value = false;
        }
    }
};
</script>

<template>
    <article class="product-card" :class="{ 'is-out-of-stock': isOutOfStock }">
        <router-link :to="productLink" class="card-link">
            <div class="media">
                <span v-if="badgeLabel" class="product-badge" :class="badgeClass">
                    {{ badgeLabel }}
                </span>

                <button
                    class="icon-btn favorite-btn"
                    :class="{ 'is-active': isFavorited(productId) }"
                    @click="handleToggleFav"
                    title="Yêu thích"
                    aria-label="Yêu thích"
                >
                    <AppIcon name="heart" size="18" stroke-width="1.8" />
                </button>

                <div class="image-shell" :class="{ 'is-empty': !productImage }">
                    <img
                        ref="productImageRef"
                        v-if="productImage"
                        :src="productImageUrl"
                        :alt="product.name"
                        class="product-image"
                        loading="lazy"
                    />

                    <div v-else class="image-placeholder" aria-hidden="true">
                        <div class="placeholder-circle">
                            <AppIcon name="bag" size="34" stroke-width="1.8" />
                        </div>
                    </div>

                    <!-- Overlay Hết hàng -->
                    <div v-if="isOutOfStock" class="stock-overlay" aria-label="Hết hàng">
                        <span class="stock-overlay-text">Hết hàng</span>
                    </div>
                </div>
            </div>

            <div class="content">
                <p v-if="productCategory" class="category">
                    {{ productCategory }}
                </p>

                <h3 class="name" :title="product.name">
                    {{ product.name }}
                </h3>

                <!-- Star Rating -->
                <div class="star-rating">
                    <span class="stars">
                        <svg v-for="s in 5" :key="s" class="star-icon" :class="s <= 4 ? 'star-filled' : 'star-half'" viewBox="0 0 24 24">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </span>
                    <span class="rating-count">4.5 <span class="rating-num">({{ (product.id % 80) + 20 }})</span></span>
                </div>

                <div class="footer-row">
                    <div class="price-block">
                        <span v-if="originalPrice" class="original-price">
                            {{ originalPrice }}
                        </span>
                        <span class="current-price">
                            {{ currentPrice }}
                        </span>
                        <!-- Hiển thị số lượng (giữ layout đồng nhất) -->
                        <span class="stock-info" :class="{ 'is-low-stock': isLowStock }">
                            <template v-if="totalStock !== null && totalStock > 0">
                                {{ isLowStock ? 'Chỉ còn ' + totalStock + ' sản phẩm' : 'Còn ' + totalStock + ' sản phẩm' }}
                            </template>
                        </span>
                    </div>

                    <button
                        class="icon-btn cart-btn"
                        @click="handleAddToCart"
                        :disabled="isAddingToCart || isOutOfStock"
                        :class="{ 'is-disabled': isOutOfStock }"
                        :title="isOutOfStock ? 'Sản phẩm đã hết hàng' : 'Thêm vào giỏ'"
                        :aria-label="isOutOfStock ? 'Hết hàng' : 'Thêm vào giỏ'"
                    >
                        <AppIcon v-if="!isAddingToCart" :name="isOutOfStock ? 'x' : 'cart'" size="18" stroke-width="1.9" />
                        <span v-else class="small-spinner"></span>
                    </button>
                </div>
            </div>
        </router-link>
    </article>

    <ProductVariantAddToCartModal
        :show="showVariantModal"
        :product-name="product.name"
        :image-url="variantImageUrl"
        :variants="variants"
        :unique-colors="uniqueColors"
        :has-colors="hasColors"
        :available-sizes="availableSizes"
        :selected-variant="selectedVariant"
        :selected-color="selectedColor"
        :selected-size="selectedSize"
        :quantity="quantity"
        :confirming="confirming"
        @close="showVariantModal = false"
        @select-color="selectColor"
        @update:selected-size="selectedSize = $event"
        @update:quantity="quantity = $event"
        @increase="increaseQuantity"
        @decrease="decreaseQuantity"
        @confirm="handleConfirmAddToCart"
    />
</template>

<style scoped>
.product-card {
    width: 100%;
    height: 100%;
}

.card-link {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 388px;
    text-decoration: none;
    background: var(--card-bg);
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
    transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
}

.card-link:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
    border-color: #d9dee8;
}

.media {
    position: relative;
    padding: 14px 14px 0;
}

.image-shell {
    height: 210px;
    width: 100%;
    position: relative;
    border-radius: 16px 16px 0 0;
    background:
        radial-gradient(circle at 50% 78%, rgba(15, 23, 42, 0.08), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #ffffff 68%, #fcfcfd 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.image-shell.is-empty {
    background: linear-gradient(180deg, #f4f5f7 0%, #efeff1 100%);
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 14px;
    transition: transform 0.35s ease;
}

.card-link:hover .product-image {
    transform: scale(1.04);
}

.image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.placeholder-circle {
    width: 116px;
    height: 116px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.52);
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1;
}

.badge-new {
    background: #ffe6f0;
    color: #d81b60;
}

.badge-sale {
    background: #e7f6ea;
    color: #2f8f4e;
}

.icon-btn {
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.favorite-btn {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 2;
    background: rgba(255, 255, 255, 0.92);
    color: #5f6672;
}

.favorite-btn:hover {
    color: var(--primary);
    transform: scale(1.06);
}

.favorite-btn.is-active {
    color: var(--primary);
    transform: scale(1.06);
}

.favorite-btn.is-active :deep(svg) {
    fill: var(--primary);
    stroke: var(--primary);
}

/* Spinner for cart button loading state */
.small-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: #20242c;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
}

/* Star Rating */
.star-rating {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.stars {
    display: flex;
    align-items: center;
    gap: 1px;
}

.star-icon {
    width: 13px;
    height: 13px;
    stroke: none;
}

.star-filled {
    fill: #FBBF24;
}

.star-half {
    fill: url(#half-star-gradient);
    fill: #FBBF24;
    opacity: 0.55;
}

.rating-count {
    font-size: 0.78rem;
    color: #94a3b8;
    font-weight: 500;
    line-height: 1;
}

.rating-num {
    color: #b0b8c4;
}

.content {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    padding: 14px 20px 20px;
}

.category {
    margin: 0;
    color: #6b7280;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.3;
}

.name {
    margin: 0;
    color: #1f2937;
    font-size: 1.02rem;
    font-weight: 500;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    height: 48px;
}

.footer-row {
    margin-top: auto;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
}

.price-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.original-price {
    color: #6b7280;
    font-size: 0.98rem;
    text-decoration: line-through;
}

.current-price {
    color: #d4145a;
    font-size: 1.14rem;
    font-weight: 800;
    line-height: 1.1;
}

.cart-btn {
    flex: 0 0 auto;
    background: #f1f1f1;
    color: #20242c;
}

.cart-btn:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .card-link {
        min-height: unset; /* Bỏ min-height, để card tự co theo nội dung */
        border-radius: 12px;
    }

    .media {
        padding: 8px 8px 0;
    }

    .image-shell {
        height: 120px; /* Giảm mạnh từ 168px xuống 120px */
        width: 100%;
        border-radius: 8px 8px 0 0;
    }

    .product-image {
        padding: 8px; /* Giảm padding ảnh */
    }

    .content {
        padding: 8px 10px 10px;
        gap: 4px;
    }

    .category {
        display: none; /* Ẩn dòng danh mục trên mobile để tiết kiệm diện tích */
    }

    .name {
        font-size: 0.82rem;
        height: 36px;
        line-height: 1.35;
        -webkit-line-clamp: 2;
    }

    .star-rating {
        margin-bottom: 2px;
    }

    .star-icon {
        width: 11px;
        height: 11px;
    }

    .rating-count {
        font-size: 0.7rem;
    }

    .footer-row {
        gap: 8px;
    }

    .original-price {
        font-size: 0.75rem;
    }

    .current-price {
        font-size: 0.88rem;
    }

    .price-block {
        gap: 2px;
    }

    .stock-info {
        font-size: 0.68rem;
        min-height: 14px;
    }

    .icon-btn {
        width: 30px;
        height: 30px;
    }

    .product-badge {
        min-height: 22px;
        padding: 2px 8px;
        font-size: 0.72rem;
    }

    .favorite-btn {
        top: 8px;
        right: 8px;
    }
}

/* ====== STOCK STATUS STYLES ====== */

/* Làm mờ nhẹ toàn bộ card khi hết hàng */
.product-card.is-out-of-stock .card-link {
    opacity: 0.72;
}

/* Lớp phủ overlay "Hết hàng" trên ảnh */
.stock-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.52);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    backdrop-filter: blur(1.5px);
    z-index: 3;
}

.stock-overlay-text {
    background: rgba(255, 255, 255, 0.95);
    color: var(--text-main);
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    padding: 6px 16px;
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
}

/* Dòng thông tin số lượng bên dưới giá */
.stock-info {
    display: block;
    font-size: 0.74rem;
    font-weight: 600;
    color: #829ab1;
    margin-top: 3px;
    letter-spacing: 0.2px;
    min-height: 16px; /* Giữ cứng chiều cao để các card thẳng hàng */
}
.stock-info.is-low-stock {
    color: #f97316;
    font-weight: 700;
    animation: pulse-text 1.8s ease-in-out infinite;
}

@keyframes pulse-text {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.65; }
}

/* Nút giỏ hàng khi sản phẩm hết hàng */
.cart-btn.is-disabled {
    background: #f1f1f1;
    color: #b0b7c3;
    cursor: not-allowed;
}

.cart-btn.is-disabled:hover {
    transform: none;
    background: #f1f1f1;
}
</style>
