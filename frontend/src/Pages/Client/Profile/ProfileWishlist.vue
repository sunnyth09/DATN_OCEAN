<template>
  <div class="profile-wishlist">
    <div class="section-header">
      <h1 class="section-title">Sản phẩm yêu thích</h1>
      <p class="section-desc">Danh sách các sản phẩm bạn đã lưu</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="wishlist-grid">
      <ProductSkeleton v-for="n in 4" :key="n" />
    </div>

    <!-- Empty State -->
    <div v-else-if="favorites.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
        </svg>
      </div>
      <h3>Chưa có sản phẩm yêu thích</h3>
      <p>Bạn chưa lưu sản phẩm nào. Hãy khám phá và lưu lại những sản phẩm bạn thích nhé!</p>
      <router-link to="/product" class="btn-primary">Tiếp tục mua sắm</router-link>
    </div>

    <!-- List State -->
    <div v-else class="wishlist-grid">
      <ProductCard 
        v-for="item in favorites" 
        :key="item.favorite_id" 
        :product="formatProduct(item.product)"
        @unfavorite="fetchFavorites"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';
import ProductCard from '@/components/ProductCard.vue';
import ProductSkeleton from '@/components/ProductSkeleton.vue';

// Lấy base URL từ env API
const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const favorites = ref([]);
const loading = ref(true);

const fetchFavorites = async () => {
    loading.value = true;
    try {
        const response = await api.get('/profile/favorites');
        if (response.data && response.data.status === 'success') {
            favorites.value = response.data.data;
        }
    } catch (error) {
        console.error('Lỗi lấy danh sách yêu thích:', error);
    } finally {
        loading.value = false;
    }
};

const formatProduct = (p) => {
    if (!p) return {};
    
    // Tìm ảnh chính
    let imageUrl = 'https://via.placeholder.com/300x400?text=No+Image';
    if (p.main_image && p.main_image.image_url) {
        imageUrl = p.main_image.image_url.startsWith('http') ? p.main_image.image_url : `${BASE_URL}/storage/${p.main_image.image_url}`;
    } else if (p.thumbnail_url) {
        imageUrl = p.thumbnail_url.startsWith('http') ? p.thumbnail_url : `${BASE_URL}/storage/${p.thumbnail_url}`;
    }
    
    // Gắn giá
    let priceStr = 'Liên hệ';
    if (p.lowest_price_variant && p.lowest_price_variant.price) {
        priceStr = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p.lowest_price_variant.price);
    } else if (p.min_price) {
        priceStr = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p.min_price);
    }

    return {
        id: p.product_id,
        name: p.name,
        slug: p.slug,
        image: imageUrl,
        price: priceStr,
        badge: p.is_featured ? 'Hot' : null
    };
};

onMounted(() => {
    fetchFavorites();
});
</script>

<style scoped>
.profile-wishlist {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header */
.section-header { margin-bottom: 4px; }
.section-title  { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.section-desc   { font-size: 0.875rem; color: #6b7280; margin: 4px 0 0; }

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px dashed #d1d5db;
    border-radius: 16px;
    text-align: center;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #fdf2f8;
    color: #db2777;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px;
}

.empty-state p {
    color: #6b7280;
    margin: 0 0 24px;
    max-width: 400px;
}

.btn-primary {
  padding: 10px 28px;
  background: #4f46e5;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: background 0.2s;
}

.btn-primary:hover { background: #4338ca; }

@media (max-width: 640px) {
    .wishlist-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
</style>
