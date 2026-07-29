<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/icons/AppIcon.vue';
import FlashSaleBoard from '@/features/shop/components/FlashSaleBoard.vue';
import api from '@/axios.js';

const route = useRoute();
const targetId = route.query.id ? parseInt(route.query.id) : null;
const items = ref([]);
const loading = ref(true);

// ── Phân trang ──
const currentPage = ref(1);
const perPage = ref(6); // 6 sản phẩm/trang (3 cột x 2 hàng)

const totalPages = computed(() => {
  return Math.ceil(items.value.length / perPage.value) || 1;
});

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return items.value.slice(start, start + perPage.value);
});

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    window.scrollTo({ top: 120, behavior: 'smooth' });
  }
};

onMounted(async () => {
  try {
    loading.value = true;
    const { data } = await api.get('flash-sale');
    const list = data.data ?? [];
    if (targetId) {
      items.value = list.filter(item => item.id === targetId);
    } else {
      items.value = list;
    }
  } catch (e) {
    console.error('Lỗi tải danh sách flash sale', e);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="flash-sale-page">
    <div class="flash-sale-container">
      
      <!-- ══ HERO BANNER ══ -->
      <section class="page-hero">
        <div class="hero-inner">
          <h1 class="hero-title">
            <AppIcon name="zap" size="28" stroke-width="2.5" />
            Flash Sale
            <span class="hero-accent">Giá Sốc</span>
          </h1>
          <p class="hero-sub">Số lượng cực kỳ có hạn — Cơ hội săn deal không thể bỏ lỡ!</p>
        </div>

        <!-- ══ ĐIỀU KIỆN THAM GIA (HIỂN THỊ LOGIC NỔI BẬT NGAY TRÊN BANNER) ══ -->
        <div class="hero-rules-bar">
          <div class="rule-chip">
            <span class="rule-chip-icon"><AppIcon name="lock" size="14" /></span>
            <span>Phải <strong>đăng nhập</strong> để mua</span>
          </div>
          <div class="rule-chip">
            <span class="rule-chip-icon"><AppIcon name="cart" size="14" /></span>
            <span>Tối đa <strong>1 SP / khách</strong></span>
          </div>
          <div class="rule-chip">
            <span class="rule-chip-icon"><AppIcon name="truck" size="14" /></span>
            <span><strong>Freeship</strong> 100% đơn Flash Sale</span>
          </div>
          <div class="rule-chip">
            <span class="rule-chip-icon"><AppIcon name="zap" size="14" /></span>
            <span>Ưu tiên khách <strong>đặt sớm</strong></span>
          </div>
          <div class="rule-chip">
            <span class="rule-chip-icon"><AppIcon name="x" size="14" /></span>
            <span>Không áp dụng voucher khác</span>
          </div>
        </div>
      </section>

      <!-- ══ DANH SÁCH SẢN PHẨM FLASH SALE ══ -->
      <section class="content">
        <!-- Đang tải -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-pink" role="status">
            <span class="visually-hidden">Đang tải...</span>
          </div>
          <p class="text-muted mt-2">Đang tải sản phẩm Flash Sale...</p>
        </div>

        <!-- Co danh sach sản phẩm -->
        <div v-else-if="items.length > 0">
          <div class="toolbar-info mb-3 d-flex justify-content-between align-items-center">
            <span class="toolbar-count">
              Hiển thị <strong>{{ paginatedItems.length }}</strong> trong tổng số <strong>{{ items.length }}</strong> deal Flash Sale
            </span>
            <span v-if="totalPages > 1" class="page-indicator">
              Trang {{ currentPage }} / {{ totalPages }}
            </span>
          </div>

          <div class="boards-container">
            <div class="board-wrapper" v-for="item in paginatedItems" :key="item.item_id">
              <FlashSaleBoard :item-id="item.item_id" />
            </div>
          </div>

          <!-- ══ THANH PHÂN TRANG (PAGINATION) ══ -->
          <div v-if="totalPages > 1" class="pagination-wrapper">
            <button 
              class="page-btn nav-btn" 
              :disabled="currentPage === 1" 
              @click="goToPage(currentPage - 1)"
            >
              « Trước
            </button>

            <button 
              v-for="p in totalPages" 
              :key="p" 
              class="page-btn" 
              :class="{ active: currentPage === p }"
              @click="goToPage(p)"
            >
              {{ p }}
            </button>

            <button 
              class="page-btn nav-btn" 
              :disabled="currentPage === totalPages" 
              @click="goToPage(currentPage + 1)"
            >
              Sau »
            </button>
          </div>
        </div>

        <!-- Trống -->
        <div v-else class="empty-flash-sale">
          <AppIcon name="zap" size="48" style="color: #cbd5e1; margin-bottom: 12px;" />
          <h4>Hiện tại chưa có chương trình Flash Sale nào diễn ra.</h4>
          <p>Hãy quay lại sau để săn các phần quà và mức giá cực ưu đãi nhé!</p>
        </div>
      </section>

    </div>
  </div>
</template>

<style scoped>
.flash-sale-page {
  min-height: 100vh;
  background: #fff;
}

.flash-sale-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 32px 40px 60px;
}

/* ── HERO BANNER (Đồng bộ với Product.vue) ── */
.page-hero {
  background: linear-gradient(135deg, #e63b6f 0%, #a0204e 100%);
  color: #fff;
  border-radius: 16px;
  padding: 32px 36px;
  margin: 0 0 28px 0;
  position: relative;
  overflow: hidden;
  text-align: left;
  box-shadow: 0 8px 24px rgba(230, 59, 111, 0.15);
}

.page-hero::after {
  content: '';
  position: absolute;
  top: -40%;
  right: -8%;
  width: 320px;
  height: 320px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 50%;
  pointer-events: none;
}

.hero-inner {
  position: relative;
  z-index: 2;
  margin-bottom: 24px;
}

.hero-title {
  color: #fff;
  font-size: 1.85rem;
  font-weight: 800;
  margin: 0 0 8px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.hero-accent {
  display: inline-flex;
  align-items: center;
  color: #fff;
  background: rgba(255, 255, 255, 0.22);
  backdrop-filter: blur(4px);
  border-radius: 8px;
  padding: 2px 12px;
  font-size: 0.95rem;
  font-weight: 700;
}

.hero-sub {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.95rem;
  max-width: 550px;
  margin: 0;
  line-height: 1.5;
}

/* ── ĐIỀU KIỆN THAM GIA BANNER BAR (HIỂN THỊ LOGIC NGAY ĐẦU TRANG) ── */
.hero-rules-bar {
  position: relative;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.rule-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.82rem;
  color: #ffffff;
  font-weight: 500;
  transition: transform 0.2s, background 0.2s;
}

.rule-chip:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-1px);
}

.rule-chip-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.25);
  border-radius: 50%;
  width: 20px;
  height: 20px;
}

/* ── TOOLBAR INFO ── */
.toolbar-info {
  border-bottom: 1px solid #e9ecef;
  padding-bottom: 12px;
}

.toolbar-count {
  font-size: 0.9rem;
  color: #636e72;
}

.toolbar-count strong {
  color: var(--primary);
  font-weight: 700;
}

.page-indicator {
  font-size: 0.85rem;
  font-weight: 600;
  color: #636e72;
  background: #f1f3f5;
  padding: 4px 12px;
  border-radius: 20px;
}

/* ── BOARD GRID ── */
.boards-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 24px;
  margin-bottom: 36px;
}

.board-wrapper {
  display: flex;
  justify-content: center;
  width: 100%;
}

/* ── PHÂN TRANG (PAGINATION) ── */
.pagination-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin-top: 32px;
  padding-top: 20px;
  border-top: 1px dashed #e9ecef;
}

.page-btn {
  background: #ffffff;
  border: 1.5px solid #dee2e6;
  color: #495057;
  min-width: 40px;
  height: 40px;
  padding: 0 12px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled) {
  border-color: #e63b6f;
  color: #e63b6f;
  background: #fff0f5;
}

.page-btn.active {
  background: #e63b6f;
  border-color: #e63b6f;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.3);
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  background: #f8f9fa;
  border-color: #e9ecef;
}

.nav-btn {
  font-weight: 700;
  padding: 0 16px;
}

/* ── EMPTY STATE ── */
.empty-flash-sale {
  text-align: center;
  padding: 60px 20px;
  background: #fafafa;
  border-radius: 16px;
  border: 1px dashed #e2e8f0;
}

.empty-flash-sale h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 6px;
}

.empty-flash-sale p {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0;
}

@media (max-width: 768px) {
  .flash-sale-container { padding: 16px; }
  .page-hero { padding: 24px 20px; }
  .hero-rules-bar { gap: 6px; }
  .rule-chip { font-size: 0.76rem; padding: 4px 10px; }
  .boards-container { grid-template-columns: 1fr; }
}
</style>
