<template>
  <div class="table-card ocean-card">
    <div class="card-header">
      <div class="header-title-group">
        <div class="title-with-badge">
          <div class="alert-icon-pulse">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <div>
            <h3 class="card-title">Cảnh báo hàng tồn lâu & Bán chậm</h3>
            <p class="card-subtitle">Sản phẩm đăng bán lâu, lượng bán 30 ngày qua thấp cần ưu tiên xả kho</p>
          </div>
        </div>
      </div>

      <div class="header-actions">
        <!-- Ngưỡng ngày lọc -->
        <div class="threshold-selector">
          <span class="threshold-label">Tồn trên:</span>
          <button 
            v-for="t in [30, 60, 90]" 
            :key="t"
            class="btn-threshold"
            :class="{ active: selectedDays === t }"
            @click="changeThreshold(t)"
          >
            {{ t }} ngày
          </button>
        </div>

        <!-- Nút xả kho hàng loạt -->
        <button 
          class="btn-clearance-bulk"
          :disabled="selectedProducts.length === 0"
          @click="handleBulkClearance"
          title="Tạo Flash Sale xả kho cho các sản phẩm đã chọn"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
          </svg>
          Xả kho Flash Sale ({{ selectedProducts.length }})
        </button>
      </div>
    </div>

    <!-- Summary Metrics Strip -->
    <div class="summary-strip">
      <div class="summary-item">
        <span class="s-label">Số sản phẩm bán chậm:</span>
        <strong class="s-val text-warning">{{ summary.slow_moving_count || products.length }} SP</strong>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <span class="s-label">Tổng tồn kho đọng:</span>
        <strong class="s-val text-danger">{{ summary.total_stagnant_stock || totalStock }} cái</strong>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <span class="s-label">Vốn tồn đọng ước tính:</span>
        <strong class="s-val text-primary">{{ formatCurrency(summary.tied_up_capital || totalCapital) }}</strong>
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="ocean-table">
        <thead>
          <tr>
            <th width="40" class="text-center">
              <input 
                type="checkbox" 
                class="ocean-checkbox" 
                :checked="isAllSelected" 
                @change="toggleSelectAll"
                :disabled="products.length === 0"
              />
            </th>
            <th>Sản phẩm</th>
            <th class="text-center">Ngày đăng / Tuổi hàng</th>
            <th class="text-center">Tồn kho</th>
            <th class="text-center">Đã bán (30 ngày)</th>
            <th class="text-right">Vốn tồn đọng</th>
            <th class="text-center" width="140">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="products && products.length > 0">
            <tr v-for="product in products" :key="product.id" :class="{ 'row-selected': selectedProducts.includes(product.id) }">
              <!-- Checkbox -->
              <td class="text-center">
                <input 
                  type="checkbox" 
                  class="ocean-checkbox" 
                  :value="product.id"
                  v-model="selectedProducts"
                />
              </td>

              <!-- Sản phẩm -->
              <td>
                <div class="product-cell">
                  <div class="image-wrapper">
                    <img v-if="getProductImage(product)" :src="getProductImage(product)" alt="" class="product-img" />
                    <div v-else class="product-img-placeholder">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                  </div>
                  <div class="product-info">
                    <span class="product-name" :title="product.name">{{ product.name }}</span>
                    <div class="product-meta">
                      <span class="badge-tag">{{ product.category_name }}</span>
                      <span class="product-price">{{ formatCurrency(product.base_price) }}</span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Tuổi hàng -->
              <td class="text-center">
                <div class="age-wrapper">
                  <span class="age-badge" :class="getAgeClass(product.days_in_inventory)">
                    ⏳ {{ product.days_in_inventory }} ngày
                  </span>
                  <span class="publish-date">{{ product.published_at }}</span>
                </div>
              </td>

              <!-- Tồn kho -->
              <td class="text-center">
                <span class="stock-pill">{{ product.stock }} cái</span>
              </td>

              <!-- Đã bán 30 ngày -->
              <td class="text-center">
                <span class="sold-pill" :class="{ 'zero-sold': product.sold_last_30d === 0 }">
                  {{ product.sold_last_30d }} cái
                </span>
              </td>

              <!-- Vốn tồn đọng -->
              <td class="text-right">
                <strong class="capital-val">{{ formatCurrency(product.tied_up_capital) }}</strong>
              </td>

              <!-- Thao tác 1-Click Flash Sale -->
              <td class="text-center">
                <button 
                  class="btn-sale-single"
                  @click="handleSingleClearance(product)"
                  title="Đưa sản phẩm vào Flash Sale xả hàng ngay"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                  </svg>
                  Xả kho
                </button>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td colspan="7" class="text-center empty-cell">
              <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                <p class="mt-2 mb-0">Tuyệt vời! Không có sản phẩm nào tồn kho quá hạn cần xả hàng.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { getStorageUrl } from '@/utils/url';

const props = defineProps({
  products: {
    type: Array,
    default: () => []
  },
  summary: {
    type: Object,
    default: () => ({})
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['threshold-change']);
const router = useRouter();

const selectedDays = ref(60);
const selectedProducts = ref([]);

const totalStock = computed(() => {
  return props.products.reduce((sum, p) => sum + (p.stock || 0), 0);
});

const totalCapital = computed(() => {
  return props.products.reduce((sum, p) => sum + (p.tied_up_capital || 0), 0);
});

const isAllSelected = computed(() => {
  return props.products.length > 0 && selectedProducts.value.length === props.products.length;
});

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedProducts.value = props.products.map(p => p.id);
  } else {
    selectedProducts.value = [];
  }
};

const changeThreshold = (days) => {
  selectedDays.value = days;
  selectedProducts.value = [];
  emit('threshold-change', days);
};

const getProductImage = (product) => {
  const path = product.thumbnail_url || product.image;
  if (!path || path === '0') return null;
  return getStorageUrl(path);
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const getAgeClass = (days) => {
  if (days >= 90) return 'age-critical';
  if (days >= 60) return 'age-warning';
  return 'age-normal';
};

// 1-Click tạo Flash Sale cho 1 sản phẩm
const handleSingleClearance = (product) => {
  sessionStorage.setItem('clearance_products', JSON.stringify([product]));
  router.push({
    path: '/admin/flash-sale',
    query: {
      auto_create: '1',
      clearance_product_ids: product.id
    }
  });
};

// Tạo Flash Sale hàng loạt cho các sản phẩm đã chọn
const handleBulkClearance = () => {
  if (selectedProducts.value.length === 0) return;
  const items = props.products.filter(p => selectedProducts.value.includes(p.id));
  sessionStorage.setItem('clearance_products', JSON.stringify(items));
  router.push({
    path: '/admin/flash-sale',
    query: {
      auto_create: '1',
      clearance_product_ids: selectedProducts.value.join(',')
    }
  });
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
  margin-bottom: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.alert-icon-pulse {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  animation: pulseGlow 2s infinite;
}

@keyframes pulseGlow {
  0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
  100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

.card-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-main, #1e293b);
  margin: 0 0 4px 0;
  letter-spacing: -0.01em;
}

.card-subtitle {
  font-size: 0.82rem;
  color: var(--text-muted, #64748b);
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.threshold-selector {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-container-low, #f8fafc);
  padding: 4px 8px;
  border-radius: 10px;
  border: 1px solid var(--border-color, #e2e8f0);
}

.threshold-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
  margin-right: 4px;
}

.btn-threshold {
  padding: 4px 10px;
  border-radius: 6px;
  border: none;
  background: transparent;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-main, #475569);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-threshold.active {
  background: var(--primary, #0ea5e9);
  color: white;
}

.btn-clearance-bulk {
  display: inline-flex;
  align-items: center;
  padding: 8px 16px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
  color: white;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
  transition: all 0.25s ease;
}

.btn-clearance-bulk:hover:not(:disabled) {
  transform: translateY(-1.5px);
  box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
}

.btn-clearance-bulk:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Summary Strip */
.summary-strip {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 12px 18px;
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.06);
  border: 1px dashed rgba(245, 158, 11, 0.25);
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
}

.s-label {
  color: var(--text-muted, #64748b);
  font-weight: 600;
}

.s-val {
  font-size: 0.95rem;
  font-weight: 800;
}

.summary-divider {
  width: 1px;
  height: 20px;
  background: rgba(245, 158, 11, 0.25);
}

/* Table */
.table-responsive {
  overflow-x: auto;
  border-radius: 8px;
}

.ocean-table {
  width: 100%;
  border-collapse: collapse;
}

.ocean-table th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  background: var(--surface-container, #f8fafc);
  white-space: nowrap;
}

.ocean-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-color, #f1f5f9);
  vertical-align: middle;
}

.ocean-table tr.row-selected td {
  background: rgba(14, 165, 233, 0.04);
}

.ocean-checkbox {
  width: 17px;
  height: 17px;
  cursor: pointer;
  accent-color: var(--primary, #0ea5e9);
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.product-img {
  width: 44px;
  height: 44px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.06);
}

.product-img-placeholder {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
}

.product-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.product-name {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-main, #334155);
  max-width: 280px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.product-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge-tag {
  font-size: 0.72rem;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--surface-container, #f1f5f9);
  color: var(--text-muted, #64748b);
  font-weight: 600;
}

.product-price {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--primary, #0ea5e9);
}

.age-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.age-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}

.age-critical {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.age-warning {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
}

.age-normal {
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
}

.publish-date {
  font-size: 0.72rem;
  color: var(--text-muted, #94a3b8);
}

.stock-pill {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  background: rgba(239, 68, 68, 0.08);
  color: #dc2626;
  font-weight: 800;
  font-size: 0.82rem;
}

.sold-pill {
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--text-main, #334155);
}

.sold-pill.zero-sold {
  color: #dc2626;
  font-weight: 800;
}

.capital-val {
  font-size: 0.92rem;
  color: #b91c1c;
}

.btn-sale-single {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid rgba(245, 158, 11, 0.4);
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-sale-single:hover {
  background: #f59e0b;
  color: white;
  border-color: #f59e0b;
  transform: translateY(-1px);
}

.empty-cell {
  padding: 40px !important;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #10b981;
  font-weight: 600;
  font-size: 0.95rem;
}

.text-center { text-align: center; }
.text-right { text-align: right; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.text-primary { color: var(--primary, #0ea5e9); }
</style>
