<script setup>
import { computed, ref } from "vue";
import { useToast } from "@/composables/useToast";
import { useFavorites } from "@/composables/useFavorites";
import { useCartStore } from "@/stores/cart";
import { useFlyToCart } from "@/composables/useFlyToCart";
import AppIcon from "@/components/AppIcon.vue";
import ProductVariantAddToCartModal from "@/components/ProductVariantAddToCartModal.vue";
import api from "@/axios";
import { getStorageUrl } from "@/utils/url";
import { useVariantSelection } from "@/composables/useVariantSelection";

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
const {
    showVariantModal,
    variants,
    hasFetchedVariants,
    selectedColor,
    selectedSize,
    quantity,
    increaseQuantity,
    decreaseQuantity,
    uniqueColors,
    hasColors,
    availableSizes,
    selectedVariant,
    selectColor,
    fetchVariants,
    normalizeQuantity
} = useVariantSelection();

const confirming = ref(false);

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

const isFlashSale = computed(() => {
    return props.product.flash_sold !== undefined || !!props.product.flash_sale || !!props.product.is_flash_sale;
});

const normalSoldCount = computed(() => {
    if (isFlashSale.value) return null;
    const value = props.product.sold_count ?? props.product.total_sold ?? props.product.sold;
    const sold = Number(value);
    return Number.isFinite(sold) && sold >= 0 ? sold : null;
});

const formatSoldCount = (count) => {
    const num = Number(count);
    if (!Number.isFinite(num) || num <= 0) return '0';

    if (num >= 1000000) {
        const tr = (num / 1000000).toFixed(1).replace(/\.0$/, '');
        return num % 1000000 === 0 ? `${tr} triệu` : `${tr} triệu+`;
    }

    if (num >= 1000) {
        const k = (num / 1000).toFixed(1).replace(/\.0$/, '');
        return num % 1000 === 0 ? `${k}k` : `${k}k+`;
    }

    return String(num);
};

const badgeLabel = computed(() => {
    if (isFlashSale.value) {
        return numericDiscount.value > 0 ? `-${numericDiscount.value}%` : "Flash Sale";
    }

    if (numericDiscount.value > 0) {
        return `-${numericDiscount.value}%`;
    }

    if (props.product.badge === "New" || props.product.badge === "Mới") {
        return "Mới";
    }

    if (props.product.badge === "Hot") {
        return "Hot";
    }

    return props.product.badge || "";
});

const badgeClass = computed(() => {
    if (isFlashSale.value) return "badge-flash";
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
    const orig = Number(props.product.original_price || props.product.compare_at_price || props.product.max_price || 0);
    const curr = Number(props.product.min_price || props.product.price || 0);
    if (orig > curr && curr > 0) {
        return formatCurrency(orig);
    }
    return "";
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

            showToast("Đã thêm vào giỏ hàng (tạm thời)", "cart", {
                name: props.product.name,
                variant_id: selectedVariant.value.variant_id,
                variant: (selectedVariant.value.color || '') + ' ' + (selectedVariant.value.size || ''),
                qty: safeQuantity,
                image: variantImageUrl.value || productImageUrl.value
            });
            showVariantModal.value = false;
            await cartStore.fetchCount();
            window.dispatchEvent(new Event('cart-updated'));
            return;
        }

        if (productImageRef.value) {
            await flyToCart(productImageRef.value, '#cart-icon');
        }

        showToast(result.message || "Đã thêm vào giỏ hàng", "cart", {
            name: props.product.name,
            variant_id: selectedVariant.value.variant_id,
            variant: (selectedVariant.value.color || '') + ' ' + (selectedVariant.value.size || ''),
            qty: safeQuantity,
            image: variantImageUrl.value || productImageUrl.value
        });
        showVariantModal.value = false;
        await cartStore.fetchCount();
        window.dispatchEvent(new Event('cart-updated'));
    } catch (error) {
        const message = error.response?.data?.message || "Không thể thêm vào giỏ hàng.";
        showToast(message, "danger");
    } finally {
        confirming.value = false;
    }
};

const handleAddToCart = async (event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    if (isAddingToCart.value) return;

    // 1. Fetch variants if not fetched yet
    if (!hasFetchedVariants.value) {
        try {
            isAddingToCart.value = true;
            await fetchVariants(productId.value, defaultVariantId.value);
        } catch (error) {
            console.error("Error fetching variants:", error);
            return;
        } finally {
            isAddingToCart.value = false;
        }
    }

    // 2. Check if product has selectable variants
    const hasSelectable = variants.value.length > 0 && (variants.value.some(v => v.color) || variants.value.some(v => v.size));

    if (hasSelectable) {
        // Auto-select valid initial variant or default
        const defaultVar = (defaultVariantId.value ? variants.value.find(v => v.variant_id === defaultVariantId.value) : null)
            || variants.value.find(v => v.stock > 0)
            || variants.value[0];
        if (defaultVar) {
            selectedColor.value = defaultVar.color || null;
            selectedSize.value = defaultVar.size || null;
        }
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

                showToast("Đã thêm vào giỏ hàng (tạm thời)", "cart", {
                    name: props.product.name,
                    variant_id: activeVariant.variant_id,
                    variant: '',
                    qty: 1,
                    image: productImageUrl.value
                });
                await cartStore.fetchCount();
                window.dispatchEvent(new Event('cart-updated'));
                return;
            }

            if (productImageRef.value) {
                await flyToCart(productImageRef.value, '#cart-icon');
            }

            showToast(result.message || "Đã thêm vào giỏ hàng", "cart", {
                name: props.product.name,
                variant_id: activeVariant.variant_id,
                variant: '',
                qty: 1,
                image: productImageUrl.value
            });
            await cartStore.fetchCount();
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
                    <AppIcon v-if="isFlashSale" name="zap" size="13" style="margin-right: 2px;" />
                    {{ badgeLabel }}
                </span>

                <button class="icon-btn favorite-btn" :class="{ 'is-active': isFavorited(productId) }"
                    @click.stop.prevent="handleToggleFav" title="Yêu thích" aria-label="Yêu thích">
                    <AppIcon name="heart" size="18" stroke-width="1.8" />
                </button>

                <div class="image-shell" :class="{ 'is-empty': !productImage }">
                    <img ref="productImageRef" v-if="productImage" :src="productImageUrl" :alt="product.name"
                        class="product-image" loading="lazy" />

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

                <div class="footer-row">
                    <div class="price-block">
                        <span v-if="originalPrice" class="original-price">
                            {{ originalPrice }}
                        </span>
                        <span class="current-price">
                            {{ currentPrice }}
                        </span>
                        <!-- Hiển thị số lượng / đã bán (giữ layout đồng nhất) -->
                        <div v-if="(totalStock !== null && totalStock > 0) || normalSoldCount !== null" class="product-meta-row">
                            <span v-if="totalStock !== null && totalStock > 0" class="stock-info" :class="{ 'is-low-stock': isLowStock }">
                                {{ isLowStock ? 'Chỉ còn ' + totalStock + ' sản phẩm' : 'Còn ' + totalStock + ' sản phẩm' }}
                            </span>
                            <span v-else></span>
                            <span v-if="normalSoldCount !== null" class="sold-info">
                                Đã bán {{ formatSoldCount(normalSoldCount) }}
                            </span>
                        </div>
                    </div>

                    <button class="icon-btn cart-btn" @click.stop.prevent="handleAddToCart"
                        :disabled="isAddingToCart || isOutOfStock" :class="{ 'is-disabled': isOutOfStock }"
                        :title="isOutOfStock ? 'Sản phẩm đã hết hàng' : 'Thêm vào giỏ'"
                        :aria-label="isOutOfStock ? 'Hết hàng' : 'Thêm vào giỏ'">
                        <AppIcon v-if="!isAddingToCart" :name="isOutOfStock ? 'x' : 'cart'" size="16"
                            stroke-width="1.9" />
                        <span v-else class="small-spinner"></span>
                    </button>
                </div>

                <slot name="bottom-content"></slot>
            </div>
        </router-link>
    </article>

    <ProductVariantAddToCartModal :show="showVariantModal" :product-name="product.name" :image-url="variantImageUrl"
        :variants="variants" :unique-colors="uniqueColors" :has-colors="hasColors" :available-sizes="availableSizes"
        :selected-variant="selectedVariant" :selected-color="selectedColor" :selected-size="selectedSize"
        :quantity="quantity" :confirming="confirming"
        :original-price="product.compare_at_price || product.original_price || product.originalPrice || product.max_price"
        @close="showVariantModal = false" @select-color="selectColor"
        @update:selected-size="selectedSize = $event" @update:quantity="quantity = $event" @increase="increaseQuantity"
        @decrease="decreaseQuantity" @confirm="handleConfirmAddToCart" />
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
    /* 388px -> 360px: đã bỏ dòng rating giả nên nội dung thấp hơn.
       Giá trị này phải khớp ProductSkeleton để không giật layout khi load xong. */
    min-height: 360px;
    text-decoration: none;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
}

.card-link:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.media {
    position: relative;
    padding: 14px 14px 0;
}

.image-shell {
    height: 210px;
    width: 100%;
    position: relative;
    border-radius: var(--radius-sm);
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
    padding: 2px 8px;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.2;
}

/* Badge "Mới": neutral, không cạnh tranh với badge giảm giá */
.badge-new {
    background: var(--surface-container);
    color: var(--text-main);
}

/* Badge giảm giá: màu thương hiệu (trước đây là xanh lá #2f8f4e, lệch palette) */
.badge-sale {
    background: var(--primary);
    color: #fff;
}

/* Badge Flash Sale: glowing flame pink */
.badge-flash {
    background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.4);
}

.icon-btn {
    width: 34px;
    height: 34px;
    min-height: unset;
    aspect-ratio: 1 / 1;
    flex-shrink: 0;
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
    background: rgba(255, 255, 255, 0.94);
    color: #5f6672;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
}

.favorite-btn:hover {
    color: var(--primary);
    transform: translateY(-1px) scale(1.06);
}

.favorite-btn.is-active {
    color: var(--primary);
    transform: translateY(-1px) scale(1.06);
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

.content {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 10px 14px 14px;
}

.category {
    margin: 0 0 2px;
    color: var(--text-secondary, #64748b);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.2;
}

.name {
    margin: 0 0 6px;
    color: var(--text-main, #1e293b);
    font-size: 0.88rem;
    font-weight: 500;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
    height: 38px;
    transition: color 0.2s ease;
}

.card-link:hover .name {
    color: var(--primary, #E63B6F);
}

.footer-row {
    margin-top: auto;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px;
}

.price-block {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.original-price {
    color: #94a3b8;
    font-size: 0.78rem;
    text-decoration: line-through;
    line-height: 1.2;
}

.current-price {
    color: #d4145a;
    font-size: 1.02rem;
    font-weight: 800;
    line-height: 1.2;
}

.cart-btn {
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    background: rgba(230, 59, 111, 0.08);
    color: var(--primary, #E63B6F);
    border: 1px solid rgba(230, 59, 111, 0.16);
    border-radius: 8px;
    margin-bottom: 2px;
    transition: all 0.2s ease;
}

.cart-btn:hover {
    background: var(--primary, #E63B6F);
    border-color: var(--primary, #E63B6F);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.28);
}

html.dark .cart-btn {
    background: rgba(230, 59, 111, 0.16);
    border-color: rgba(230, 59, 111, 0.25);
    color: #ff8fab;
}

html.dark .cart-btn:hover {
    background: var(--primary, #E63B6F);
    color: #fff;
}

@media (max-width: 768px) {
    .card-link {
        min-height: unset;
        /* Bỏ min-height, để card tự co theo nội dung */
        border-radius: 12px;
    }

    .media {
        padding: 8px 8px 0;
    }

    .image-shell {
        height: 120px;
        /* Giảm mạnh từ 168px xuống 120px */
        width: 100%;
        border-radius: 8px 8px 0 0;
    }

    .product-image {
        padding: 8px;
        /* Giảm padding ảnh */
    }

    .content {
        padding: 8px 10px 10px;
        gap: 4px;
    }

    .category {
        display: none;
        /* Ẩn dòng danh mục trên mobile để tiết kiệm diện tích */
    }

    .name {
        font-size: 0.82rem;
        height: 36px;
        line-height: 1.35;
        -webkit-line-clamp: 2;
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

    .stock-info,
    .sold-info {
        font-size: 0.68rem;
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

/* Dòng thông tin số lượng / đã bán */
.product-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    margin-top: 4px;
}

.stock-info,
.sold-info {
    display: inline-block;
    min-width: 0;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.2;
}

.stock-info {
    color: #64748b;
}

.sold-info {
    margin-left: auto;
    text-align: right;
    color: #475569;
    font-weight: 750;
    white-space: nowrap;
}

html.dark .sold-info {
    color: #94a3b8;
}

.stock-info.is-low-stock {
    color: #ea580c;
    font-weight: 800;
    animation: pulse-text 1.8s ease-in-out infinite;
}

@keyframes pulse-text {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.65;
    }
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
