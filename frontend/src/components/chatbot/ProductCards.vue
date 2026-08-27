<template>
  <div class="product-cards">
    <div v-for="product in products" :key="product.product_id" class="product-card">
      <div class="product-card-img-wrap" @click="$emit('go-to-product', product.slug)">
        <img :src="getProductImage(product.thumbnail)" :alt="product.name" class="product-card-img" loading="lazy" />
        <span v-if="product.has_sale && product.sale_percentage > 0" class="sale-badge">-{{ product.sale_percentage }}%</span>
      </div>
      <div class="product-card-info">
        <p class="product-card-name" @click="$emit('go-to-product', product.slug)">{{ product.name }}</p>
        <div class="product-card-price-row">
          <span class="product-card-price">{{ product.price }}</span>
          <span v-if="product.compare_at_price" class="product-card-compare-price">{{ product.compare_at_price }}</span>
        </div>
        <span v-if="product.category" class="product-card-cat">{{ product.category }}</span>
        <!-- Action buttons -->
        <div class="product-card-actions">
          <button class="card-action-btn cart-btn" @click.stop="handleAddToCart(product)" title="Thêm vào giỏ hàng">
            Thêm giỏ
          </button>
          <button class="card-action-btn buy-btn" @click.stop="handleBuyNow(product)" title="Mua ngay">
            Mua ngay
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { getAppBaseUrl } from '@/utils/url';
const BASE_URL = getAppBaseUrl();

const props = defineProps({
  products: {
    type: Array,
    required: true
  }
});

const emit = defineEmits(['go-to-product', 'add-variant', 'show-variant-picker', 'buy-now']);

function getProductImage(thumbnail) {
  if (!thumbnail) return '';
  if (thumbnail.startsWith('http')) return thumbnail;
  const cleaned = thumbnail.replace(/^\/+/, '').replace(/^storage\//, '');
  return `${BASE_URL}/storage/${cleaned}`;
}

/**
 * Nếu sản phẩm có đúng 1 variant còn hàng → thêm thẳng vào giỏ
 * Nếu nhiều variant → mở variant picker để chọn
 */
function handleAddToCart(product) {
  if (product.variants && product.variants.length > 0) {
    const availableVariants = product.variants.filter(v => v.stock > 0);
    if (availableVariants.length === 1) {
      emit('add-variant', product, availableVariants[0]);
    } else {
      emit('show-variant-picker', product);
    }
  } else {
    // Không có thông tin variant → mở variant picker để load từ API
    emit('show-variant-picker', product);
  }
}

/**
 * Mua ngay: thêm vào giỏ hàng rồi chuyển thẳng trang thanh toán
 * 1 variant còn hàng → buy-now luôn
 * Nhiều variant → mở picker (user chọn xong sẽ buy-now từ picker)
 */
function handleBuyNow(product) {
  if (product.variants && product.variants.length > 0) {
    const availableVariants = product.variants.filter(v => v.stock > 0);
    if (availableVariants.length === 1) {
      emit('buy-now', product, availableVariants[0]);
    } else {
      emit('show-variant-picker', product);
    }
  } else {
    emit('show-variant-picker', product);
  }
}
</script>

<style scoped>
.product-cards { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }
.product-card { display: flex; gap: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.2s; }
.product-card:hover { border-color: #fbcfe8; box-shadow: 0 4px 12px rgba(230, 59, 111, 0.1); }
.product-card-img-wrap { position: relative; flex-shrink: 0; cursor: pointer; width: 64px; height: 64px; }
.product-card-img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.sale-badge { position: absolute; top: -4px; left: -4px; background: linear-gradient(135deg, #E63B6F, #b50c4d); color: #fff; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 999px; box-shadow: 0 2px 6px rgba(230, 59, 111, 0.4); pointer-events: none;}
.product-card-info { flex: 1; min-width: 0; }
.product-card-name { font-weight: 600; font-size: 0.85rem; color: #111827; margin: 0 0 4px; line-height: 1.3; cursor: pointer; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.product-card-name:hover { color: #E63B6F; }
.product-card-price-row { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; margin: 2px 0; }
.product-card-price { font-weight: 700; color: #E63B6F; font-size: 0.85rem; margin: 0; }
.product-card-compare-price { font-size: 0.72rem; color: #9ca3af; text-decoration: line-through; font-weight: 400; }
.product-card-cat { display: inline-block; font-size: 0.65rem; background: #f3f4f6; color: #4b5563; padding: 2px 8px; border-radius: 4px; margin-top: 4px; }

/* ─── Action Buttons ─── */
.product-card-actions { display: flex; gap: 6px; margin-top: 6px; }
.card-action-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 16px;
  font-size: 0.7rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s ease;
  border: 1px solid transparent; line-height: 1;
}
.card-action-btn svg { flex-shrink: 0; }
.cart-btn {
  background: #f0fdf4; border-color: #86efac; color: #15803d;
}
.cart-btn:hover {
  background: #16a34a; border-color: #16a34a; color: #fff;
  box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
}
.buy-btn {
  background: linear-gradient(135deg, #E63B6F, #d62e5f); border-color: #E63B6F; color: #fff;
}
.buy-btn:hover {
  background: linear-gradient(135deg, #d62e5f, #b50c4d); border-color: #d62e5f;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.35);
}

.chatbot-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.chatbot-action-btn { background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.chatbot-action-btn:hover { background: #f9fafb; border-color: #9ca3af; }
.chatbot-action-btn.primary { background: #E63B6F; border-color: #E63B6F; color: white; }
.chatbot-action-btn.primary:hover { background: #d62e5f; border-color: #d62e5f; box-shadow: 0 2px 6px rgba(230, 59, 111, 0.25); }
.chatbot-action-btn.outline { background: transparent; border-color: #d1d5db; color: #374151; }
.chatbot-action-btn.outline:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; }
</style>
