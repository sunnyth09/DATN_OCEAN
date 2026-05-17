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
                  <div class="product-rank">#{{ index + 1 }}</div>
                  <img :src="product.image || '/default-product.png'" alt="" class="product-img" />
                  <div class="product-info">
                    <span class="product-name">{{ product.name }}</span>
                  </div>
                </div>
              </td>
              <td class="text-right"><strong>{{ product.sold }}</strong></td>
              <td class="text-right text-ocean"><strong>{{ formatCurrency(product.revenue) }}</strong></td>
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
const props = defineProps({
  products: {
    type: Array,
    default: () => []
  }
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};
</script>

<style scoped>
.table-card {
  background: white;
  padding: 24px;
}

.card-header {
  margin-bottom: 20px;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
}

.table-responsive {
  overflow-x: auto;
}

.ocean-table {
  width: 100%;
  border-collapse: collapse;
}

.ocean-table th {
  text-align: left;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color);
  background: white;
  position: sticky;
  top: 0;
  z-index: 10;
}

.ocean-table td {
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  transition: background 0.2s;
}

.ocean-table tr:hover td {
  background: var(--hover-bg);
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.product-rank {
  font-weight: 800;
  font-size: 0.9rem;
  color: #E63B6F;
  width: 24px;
}

.product-img {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.product-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-main);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.text-right { text-align: right; }
.text-center { text-align: center; }
.text-ocean { color: #E63B6F; }

.stock-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}
.in-stock { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.out-stock { background: rgba(239, 83, 80, 0.15); color: #c62828; }

.empty-cell {
  padding: 40px !important;
  color: var(--text-muted);
  font-weight: 500;
}
</style>
