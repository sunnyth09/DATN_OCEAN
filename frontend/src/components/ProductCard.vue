<script setup>
import { computed } from "vue";
// import { useFavorites } from "@/composables/useFavorites";

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const isFavorited = (id) => false;
const toggleFavorite = async (id) => false;

const handleToggleFav = async () => {
    // Feature temporarily disabled
};

const handleAddToCart = (e) => {
    e.preventDefault();
    e.stopPropagation();
    // Phát event add-to-cart nếu cần, hoặc xử lý thêm vào giỏ hàng ở đây
    console.log("Thêm vào giỏ", props.product.name);
};
</script>

<template>
    <div class="product-card">
        <router-link
            to="/"
            class="text-decoration-none card-link"
        >
            <div class="img-frame">
                <div class="img-wrapper">
                    <!-- Badges -->
                    <div class="badges-container">
                        <span
                            class="product-badge"
                            v-if="props.product.badge"
                            :class="{
                                'badge-hot': props.product.badge === 'Hot',
                                'badge-new': props.product.badge === 'New',
                            }"
                        >
                            {{ props.product.badge }}
                        </span>
                    </div>

                    <!-- Main Image -->
                    <img
                        :src="props.product.image"
                        :alt="props.product.name"
                        loading="lazy"
                    />

                    <!-- Top Right action (Favorite) -->
                    <button
                        class="fav-top-btn"
                        :class="{ 'is-active': isFavorited(props.product.id || props.product.product_id) }"
                        @click.prevent="handleToggleFav"
                        title="Yêu thích"
                    >
                        <svg v-if="isFavorited(props.product.id || props.product.product_id)" width="18" height="18" viewBox="0 0 24 24" fill="#ff4757" stroke="#ff4757" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                    </button>
                </div>

                <!-- Floating bag icon (Now outside overflow:hidden but inside relative frame) -->
                <button class="fab-btn" @click.prevent="handleAddToCart" title="Thêm vào giỏ">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </button>
            </div>

            <div class="info">
                <p class="category text-truncate" v-if="props.product.category_name">
                    {{ props.product.category_name }}
                </p>
                <h3 class="name" :title="props.product.name">
                    {{ props.product.name }}
                </h3>
                <span class="price">{{ props.product.price }}</span>
            </div>
        </router-link>
    </div>
</template>

<style scoped>
/* ================== S17: FAB BUTTON ================== */
.product-card {
    width: 100%;
    margin-bottom: 20px;
}

.card-link {
    display: flex;
    flex-direction: column;
    width: 100%;
    background:#fff; 
    border-radius: 16px; 
    box-shadow: 0 4px 15px rgba(45, 52, 70, 0.08); 
    padding-bottom: 16px;
    height: 100%;
    transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.3s;
}

.card-link:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(45, 52, 70, 0.1); 
}

.img-frame {
    position: relative;
    width: 100%;
}

.img-wrapper { 
    position:relative; 
    width:100%; 
    aspect-ratio:1/1; 
    border-radius: 16px; 
    overflow:hidden;
    background: #f8fafc;
}

.img-wrapper img { 
    width:100%; 
    height:100%; 
    object-fit:cover; 
    transition:0.5s cubic-bezier(0.25, 1, 0.5, 1);
}

.card-link:hover .img-wrapper img { 
    transform:scale(1.05);
}

/* Badges */
.badges-container {
    position: absolute;
    top: 12px;
    left: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 10;
}

.product-badge {
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-hot { background: #0f172a; }
.badge-new { background: #E63B6F; }

/* Favorite button top right */
.fav-top-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateX(10px);
    z-index: 10;
}

.card-link:hover .fav-top-btn,
.fav-top-btn.is-active {
    opacity: 1;
    transform: translateX(0);
}

.fav-top-btn:hover {
    color: #111111;
    transform: scale(1.1) !important;
}

.fav-top-btn.is-active {
    color: #ff4757;
}

/* FAB button bottom right */
.fab-btn { 
    position:absolute; 
    bottom:-20px; 
    right: 20px; 
    width:46px; 
    height:46px; 
    border-radius:50%; 
    background:#E63B6F; 
    color:white; 
    border: 3px solid #ffffff; 
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.35); 
    z-index:11; 
    cursor:pointer; 
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s cubic-bezier(0.25, 1, 0.5, 1);
}

.card-link:hover .fab-btn {
    bottom: -22px; /* Slight dip when card lifts */
}

.fab-btn:hover { 
    transform:scale(1.1); 
    background: #d82f65;
}

.info { 
    padding: 24px 16px 0; 
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.category { 
    font-size: 0.7rem; 
    color: #64748b; 
    font-weight: 600; 
    text-transform:uppercase; 
    margin-bottom:4px;
}

.name { 
    font-size: 0.95rem; 
    font-weight: 700; 
    color: #1e293b; 
    margin-bottom:6px; 
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden; 
    padding-right: 30px; /* leave space so text text doesn't hit FAB completely if wrapping */
    line-height: 1.4;
    transition: 0.2s;
}

.card-link:hover .name {
    color: #E63B6F;
}

.price { 
    font-weight: 700; 
    color: #E63B6F;
    font-size: 1rem;
    margin-top: auto;
}
</style>
