<template>
  <div class="table-card ocean-card">
    <div class="card-header">
      <h3 class="card-title">Top sản phẩm bán chạy</h3>
    </div>
    <div class="table-responsive">
      <table class="ocean-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th class="text-right">Đã bán</th>
            <th class="text-right">Doanh thu</th>
            <th class="text-center">Tồn kho</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="products && products.length > 0">
            <tr v-for="(product, index) in products" :key="product.id">
              <td>
                <div class="product-cell">
                  <div class="product-rank" :class="'rank-' + (index + 1)">
                    <span v-if="index < 3">#{{ index + 1 }}</span>
                    <span v-else>{{ index + 1 }}</span>
                  </div>
                  <div class="image-wrapper">
                    <img v-if="getProductImage(product)" :src="getProductImage(product)" alt="" class="product-img" />
                    <div v-else class="product-img-placeholder">
                        <AppIcon name="bag" size="20" stroke-width="1.8" />
                    </div>
                  </div>
                  <div class="product-info">
                    <span class="product-name" :title="product.name">{{ product.name }}</span>
                  </div>
                </div>
              </td>
              <td class="text-right">
                <span class="sold-count">{{ product.sold }}</span>
              </td>
              <td class="text-right text-ocean">
                <strong>{{ formatCurrency(product.revenue) }}</strong>
              </td>
              <td class="text-center">
                <span class="stock-badge" :class="product.stock > 0 ? 'in-stock' : 'out-stock'">
                  {{ product.stock > 0 ? product.stock : 'Hết hàng' }}
                </span>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td colspan="4" class="text-center empty-cell">Không có dữ liệu</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { getStorageUrl } from '@/utils/url';
import AppIcon from '@/icons/AppIcon.vue';

const props = defineProps({
  products: {
    type: Array,
    default: () => []
  }
});

const getProductImage = (product) => {
  const path = product.image || product.thumbnail_url || product.main_image?.thumbnail_url;
  if (!path || path === "0") return null;
  return getStorageUrl(path);
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};
</script>

<style scoped>
.table-card {
  background: var(--card-bg, #ffffff);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
  border: 1px solid var(--border-color, #f1f5f9);
  transition: box-shadow 0.3s ease;
}

.table-card:hover {
  box-shadow: 0 10px 32px rgba(15, 23, 42, 0.08);
}

.card-header {
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main, #1e293b);
  letter-spacing: -0.01em;
}

.table-responsive {
  overflow-x: auto;
}

.ocean-table {
  width: 100%;
  min-width: 550px;
  border-collapse: collapse;
}

.ocean-table th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: transparent;
  position: sticky;
  top: 0;
  z-index: 10;
  white-space: nowrap;
}

.ocean-table td {
  padding: 16px;
  border-bottom: 1px solid var(--border-color, #f1f5f9);
  vertical-align: middle;
  transition: all 0.2s ease;
}

.ocean-table tr:last-child td {
  border-bottom: none;
}

.ocean-table tr:hover td {
  background: var(--hover-bg, #f8fafc);
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Rank Styling */
.product-rank {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-muted, #94a3b8);
  background: var(--surface-container, #f1f5f9);
  flex-shrink: 0;
}

.product-rank.rank-1 {
  background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%);
  color: #fff;
  box-shadow: 0 4px 10px rgba(245, 158, 11, 0.25);
  font-size: 0.9rem;
}

.product-rank.rank-2 {
  background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
  color: #fff;
  box-shadow: 0 4px 10px rgba(148, 163, 184, 0.25);
}

.product-rank.rank-3 {
  background: linear-gradient(135deg, #fdba74 0%, #ea580c 100%);
  color: #fff;
  box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);
}

/* Image */
.image-wrapper {
  position: relative;
  flex-shrink: 0;
  border-radius: 10px;
  overflow: hidden;
}

.product-img {
  width: 50px;
  height: 50px;
  object-fit: cover;
  border: 1px solid rgba(0,0,0,0.04);
  background: #fff;
  transition: transform 0.3s ease;
}

.product-img-placeholder {
  width: 50px;
  height: 50px;
  background: var(--surface-container, #f1f5f9);
  border: 1px dashed var(--border-color, #cbd5e1);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted, #94a3b8);
}

.ocean-table tr:hover .product-img {
  transform: scale(1.08);
}

/* Product Info */
.product-info {
  display: flex;
  flex-direction: column;
}

.product-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-main, #334155);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.4;
  transition: color 0.2s ease;
  cursor: pointer;
}

.ocean-table tr:hover .product-name {
  color: var(--primary, #0ea5e9);
}

/* Values */
.sold-count {
  font-weight: 700;
  color: var(--text-main, #475569);
  font-size: 0.95rem;
}

.text-right { text-align: right; }
.text-center { text-align: center; }

.text-ocean { 
  color: var(--primary, #0ea5e9); 
  font-size: 0.95rem;
}

/* Badges */
.stock-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 12px;
  border-radius: 24px;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
  letter-spacing: 0.02em;
  transition: transform 0.2s ease;
}

.ocean-table tr:hover .stock-badge {
  transform: translateY(-1px);
}

.in-stock { 
  background: rgba(16, 185, 129, 0.1); 
  color: #059669; 
}

.out-stock { 
  background: rgba(239, 68, 68, 0.1); 
  color: #dc2626; 
}

.empty-cell {
  padding: 60px 20px !important;
  color: var(--text-muted, #94a3b8);
  font-weight: 500;
  font-size: 0.95rem;
}
</style>
