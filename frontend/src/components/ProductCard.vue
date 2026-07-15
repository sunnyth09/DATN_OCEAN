<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import { useFavorites } from "@/composables/useFavorites";
import { useCartStore } from "@/stores/cart";
import { useFlyToCart } from "@/composables/useFlyToCart";
import AppIcon from "@/icons/AppIcon.vue";
import api from "@/axios";
import { getStorageUrl } from "@/utils/url";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(["unfavorite"]);
const router = useRouter();
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

const getHexCode = (colorName) => {
    if (!colorName) return '#ccc';
    const colorMap = {
        'đỏ': '#ef4444', 'red': '#ef4444',
        'xanh dương': '#3b82f6', 'xanh': '#3b82f6', 'blue': '#3b82f6',
        'xanh lá': '#22c55e', 'green': '#22c55e',
        'vàng': '#eab308', 'yellow': '#eab308',
        'đen': '#171717', 'black': '#171717',
        'trắng': '#ffffff', 'white': '#ffffff',
        'hồng': '#ec4899', 'pink': '#ec4899',
        'tím': '#a855f7', 'purple': '#a855f7',
        'nâu': '#78350f', 'brown': '#78350f',
        'cam': '#f97316', 'orange': '#f97316',
        'xám': '#6b7280', 'grey': '#6b7280', 'gray': '#6b7280'
    };
    const key = colorName.toString().toLowerCase().trim();
    return colorMap[key] || '#e2e8f0'; // fallback color
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

    if (selectedVariant.value.stock <= 0) {
        showToast("Sản phẩm phiên bản này đã hết hàng!", "danger");
        return;
    }

    if (selectedVariant.value.stock <= 0) {
        showToast("Sản phẩm phiên bản này đã hết hàng!", "danger");
        return;
    }

    if (quantity.value > selectedVariant.value.stock) {
        showToast(`Số lượng trong kho không đủ (chỉ còn ${selectedVariant.value.stock} sản phẩm)!`, "danger");
        return;
    }

    confirming.value = true;
    try {
        const result = await cartStore.addItem({
            variantId: selectedVariant.value.variant_id,
            quantity: quantity.value,
        });

        if (result.status === "unauthenticated") {
            // lưu localstorage khi chưa login từ modal
            let cartItems = JSON.parse(localStorage.getItem("cart_items") || "[]");
            const index = cartItems.findIndex(
                (item) => item.variant_id === selectedVariant.value.variant_id
            );
            if (index !== -1) {
                cartItems[index].quantity += quantity.value;
            } else {
                cartItems.push({
                    variant_id: selectedVariant.value.variant_id,
                    quantity: quantity.value,
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

    <!-- Variant Selection Modal -->
    <Teleport to="body">
        <Transition name="vmodal">
            <div v-if="showVariantModal" class="vmodal-overlay" @click.self="showVariantModal = false">
                <div class="vmodal-box" role="dialog" aria-modal="true" aria-labelledby="vmodal-title">
                    <!-- Header -->
                    <div class="vmodal-header">
                        <div class="vmodal-product-snippet">
                            <img 
                                :src="variantImageUrl" 
                                :alt="product.name" 
                                class="vmodal-product-img" 
                            />
                            <div class="vmodal-product-info">
                                <h3 id="vmodal-title" class="vmodal-title">Chọn phân loại hàng</h3>
                                <p class="vmodal-product-name" :title="product.name">{{ product.name }}</p>
                            </div>
                        </div>
                        <button class="vmodal-close" @click="showVariantModal = false" title="Đóng">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="vmodal-body-content">
                        <!-- Color Section -->
                        <div class="vmodal-section" v-if="hasColors">
                            <p class="vmodal-label">Màu sắc:</p>
                            <div class="vmodal-options">
                                <button
                                    v-for="color in uniqueColors"
                                    :key="color"
                                    class="vmodal-opt-btn"
                                    :class="{ active: selectedColor === color }"
                                    @click="selectColor(color)"
                                    :title="color"
                                >
                                    <span class="color-swatch-circle" :style="{ backgroundColor: getHexCode(color) }"></span>
                                    {{ color }}
                                </button>
                            </div>
                        </div>

                        <!-- Size Section -->
                        <div class="vmodal-section" v-if="availableSizes.some(s => s.size)">
                            <p class="vmodal-label">Kích thước:</p>
                            <div class="vmodal-options">
                                <button
                                    v-for="s in availableSizes"
                                    :key="s.size"
                                    class="vmodal-opt-btn"
                                    :class="{ active: selectedSize === s.size, 'out-of-stock': s.stock <= 0 }"
                                    :disabled="s.stock <= 0"
                                    @click="selectedSize = s.size"
                                >
                                    {{ s.size }}
                                    <span v-if="s.stock > 0 && s.stock <= 5" class="vmodal-opt-stock">(còn {{ s.stock }})</span>
                                    <span v-else-if="s.stock <= 0" class="vmodal-opt-stock">Hết</span>
                                </button>
                            </div>
                        </div>

                        <!-- Selection Summary -->
                        <div class="vmodal-selected-info" v-if="selectedVariant">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>
                                Đã chọn:
                                <strong>{{ [selectedVariant.color, selectedVariant.size].filter(Boolean).join(' / ') || selectedVariant.variant_name }}</strong>
                                — {{ formatCurrency(selectedVariant.price) }}
                                <span v-if="selectedVariant.stock <= 5" class="vmodal-low-stock">(còn {{ selectedVariant.stock }})</span>
                            </span>
                        </div>
                        <div class="vmodal-selected-info vmodal-unselected" v-else>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>Vui lòng chọn {{ hasColors ? 'màu sắc' : '' }}{{ hasColors && availableSizes.some(s=>s.size) ? ' và ' : '' }}{{ availableSizes.some(s=>s.size) ? 'kích thước' : '' }}</span>
                        </div>

                        <!-- Quantity Section -->
                        <div class="vmodal-section vmodal-qty-section">
                            <p class="vmodal-label">Số lượng:</p>
                            <div class="vmodal-qty-row">
                                <div class="vmodal-qty">
                                    <button type="button" @click="decreaseQuantity" :disabled="!selectedVariant">−</button>
                                    <input type="number" inputmode="numeric" pattern="[0-9]*" v-model.number="quantity" min="1" :max="selectedVariant?.stock || 999" :disabled="!selectedVariant" />
                                    <button type="button" @click="increaseQuantity" :disabled="!selectedVariant">+</button>
                                </div>
                                <span class="vmodal-stock-info" v-if="selectedVariant">
                                    Còn lại: <strong>{{ selectedVariant.stock }}</strong> sản phẩm
                                </span>
                                <span class="vmodal-stock-info" v-else-if="variants.length > 0">
                                    Còn lại: <strong>{{ variants.reduce((sum, v) => sum + v.stock, 0) }}</strong> sản phẩm
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="vmodal-footer">
                        <button
                            class="vmodal-btn-confirm"
                            :disabled="!selectedVariant || selectedVariant.stock <= 0 || confirming"
                            @click="handleConfirmAddToCart"
                        >
                            <span v-if="confirming">Đang thêm...</span>
                            <span v-else-if="selectedVariant && selectedVariant.stock <= 0">Hết hàng</span>
                            <span v-else>Thêm vào giỏ</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
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
    /* height: 210px; */
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

/* ====== VARIANT MODAL STYLES ====== */
.vmodal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 40, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 16px;
}
.vmodal-box {
    background: var(--card-bg);
    border-radius: 18px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 24px 60px rgba(230, 59, 111, 0.15), 0 8px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.vmodal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4f8;
    gap: 16px;
}
.vmodal-product-snippet {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.vmodal-product-img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #eef2f6;
    flex-shrink: 0;
}
.vmodal-product-info {
    flex: 1;
    min-width: 0;
}
.vmodal-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a2b4a;
    margin: 0 0 4px;
}
.vmodal-product-name {
    font-size: 0.85rem;
    color: #627d98;
    margin: 0;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vmodal-close {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    transition: all 0.18s;
    flex-shrink: 0;
}
.vmodal-close:hover { background: #f1f5f9; color: var(--text-main); }

.vmodal-body-content {
    max-height: 280px;
    overflow-y: auto;
}

.vmodal-section { padding: 16px 24px 0; }
.vmodal-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334e68;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 10px;
}
.vmodal-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.vmodal-opt-btn {
    padding: 7px 16px;
    border: 1.5px solid #d9e2ec;
    border-radius: 8px;
    background: var(--card-bg);
    font-size: 0.88rem;
    font-weight: 600;
    color: #334e68;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.18s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.vmodal-opt-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.vmodal-opt-btn.active {
    border-color: var(--primary);
    background: var(--primary);
    color: #fff;
}
.vmodal-opt-btn.out-of-stock {
    opacity: 0.4;
    cursor: not-allowed;
    text-decoration: line-through;
}
.vmodal-opt-stock { font-size: 0.72rem; font-weight: 500; opacity: 0.85; }

.vmodal-selected-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 24px 0;
    padding: 12px 16px;
    background: #FFF0F3;
    border: 1px solid #FFE3E8;
    border-radius: 10px;
    font-size: 0.88rem;
    color: var(--primary);
}
.vmodal-selected-info strong { font-weight: 700; }
.vmodal-low-stock { color: #f59e0b; font-weight: 600; }
.vmodal-unselected {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.vmodal-footer {
    display: flex;
    gap: 10px;
    padding: 20px 24px;
    border-top: 1px solid #f0f4f8;
    margin-top: 16px;
}

.vmodal-qty-section {
    padding-bottom: 8px;
}
.vmodal-qty-row {
    display: flex;
    align-items: center;
    gap: 16px;
}
.vmodal-qty {
    display: flex;
    align-items: center;
    border: 1.5px solid #e63b6e7d;
    border-radius: 20px;
    overflow: hidden;
}
.vmodal-qty button {
    width: 32px;
    height: 32px;
    background: var(--card-bg);
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: var(--text-main);
    transition: background 0.2s;
}
.vmodal-qty button:hover:not(:disabled) {
    background: #F8F9FA;
}
.vmodal-qty button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
.vmodal-qty input {
    width: 40px;
    height: 32px;
    text-align: center;
    border: none;
    border-left: 1px solid #E9ECEF;
    border-right: 1px solid #E9ECEF;
    font-weight: 700;
    font-size: 0.9rem;
    outline: none;
    background: var(--card-bg);
    font-family: inherit;
}
.vmodal-qty input:disabled {
    cursor: not-allowed;
    background: #f1f5f9;
}
.vmodal-stock-info {
    font-size: 0.85rem;
    color: #627d98;
}
.vmodal-stock-info strong {
    color: var(--text-main);
    font-weight: 700;
}
.color-swatch-circle {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
    border: 1px solid rgba(0,0,0,0.15);
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);
    flex-shrink: 0;
}
.vmodal-btn-confirm {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}
.vmodal-btn-confirm:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    transform: translateY(-1px);
}
.vmodal-btn-confirm:disabled {
    background: #c8d6e0;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

/* Modal Transition */
.vmodal-enter-active, .vmodal-leave-active { transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.vmodal-enter-from, .vmodal-leave-to { opacity: 0; }
.vmodal-enter-from .vmodal-box, .vmodal-leave-to .vmodal-box { transform: scale(0.94) translateY(20px); }

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
    min-height: 46px;
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
        min-height: 332px;
        border-radius: 16px;
    }

    .media {
        padding: 12px 12px 0;
    }

    .image-shell {
        height: 168px;
    }

    .content {
        padding: 12px 14px 16px;
    }

    .category {
        font-size: 0.84rem;
    }

    .name {
        font-size: 0.95rem;
        min-height: 42px;
    }

    .original-price {
        font-size: 0.88rem;
    }

    .current-price {
        font-size: 1rem;
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
