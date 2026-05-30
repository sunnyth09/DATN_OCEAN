<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import { useFavorites } from "@/composables/useFavorites";
import { useCartStore } from "@/stores/cart";
import AppIcon from "@/icons/AppIcon.vue";

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

const formatCurrency = (value) => {
    if (value === null || value === undefined || value === "") {
        return "Liên hệ";
    }

    if (typeof value === "number") {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(value);
    }

    return value;
};

const numericDiscount = computed(() => {
    const value = Number(props.product.discount_percent || 0);
    return Number.isFinite(value) ? Math.round(value) : 0;
});

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

const productImage = computed(() => props.product.image || props.product.thumbnail_url || "");
const productCategory = computed(() => props.product.category_name || props.product.category || "");
const productId = computed(() => props.product.id || props.product.product_id || null);
const defaultVariantId = computed(() =>
    props.product.variant_id ||
    props.product.default_variant_id ||
    props.product.lowest_price_variant?.variant_id ||
    props.product.lowestPriceVariant?.variant_id ||
    null,
);
const currentPrice = computed(() => formatCurrency(props.product.price));
const originalPrice = computed(() => {
    if (!props.product.originalPrice) return "";
    if (props.product.originalPrice === props.product.price) return "";
    return formatCurrency(props.product.originalPrice);
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

const handleAddToCart = async (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (!defaultVariantId.value) {
        router.push(productLink.value);
        return;
    }

    try {
        const result = await cartStore.addItem({
            variantId: defaultVariantId.value,
            quantity: 1,
        });

        if (result.status === "unauthenticated") {
            router.push({ name: "login", query: { redirect: productLink.value } });
            return;
        }

        showToast(result.message || "Đã thêm vào giỏ hàng", "success");
    } catch (error) {
        const message = error.response?.data?.message || "Không thể thêm vào giỏ hàng.";
        showToast(message, "danger");
    }
};
</script>

<template>
    <article class="product-card">
        <router-link :to="productLink" class="card-link">
            <div class="media">
                <span v-if="badgeLabel" class="product-badge" :class="badgeClass">
                    {{ badgeLabel || HOT }}
                </span>

                <button
                    class="icon-btn favorite-btn"
                    :class="{ 'is-active': isFavorited(product.id || product.product_id) }"
                    @click="handleToggleFav"
                    title="Yêu thích"
                    aria-label="Yêu thích"
                >
                    <AppIcon name="heart" size="18" stroke-width="1.8" />
                </button>

                <div class="image-shell" :class="{ 'is-empty': !productImage }">
                    <img
                        v-if="productImage"
                        :src="productImage"
                        :alt="product.name"
                        class="product-image"
                        loading="lazy"
                    />

                    <div v-else class="image-placeholder" aria-hidden="true">
                        <div class="placeholder-circle">
                            <AppIcon name="bag" size="34" stroke-width="1.8" />
                        </div>
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
                    </div>

                    <button
                        class="icon-btn cart-btn"
                        @click="handleAddToCart"
                        title="Thêm vào giỏ"
                        aria-label="Thêm vào giỏ"
                    >
                        <AppIcon name="cart-plus" size="18" stroke-width="1.9" />
                    </button>
                </div>
            </div>
        </router-link>
    </article>
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
    background: #ffffff;
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

.favorite-btn:hover,
.favorite-btn.is-active {
    color: #111827;
    transform: scale(1.06);
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
</style>
