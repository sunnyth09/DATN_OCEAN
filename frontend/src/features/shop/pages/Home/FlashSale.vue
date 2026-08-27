<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import FlashSaleBoard from '@/features/shop/components/FlashSaleBoard.vue';
import api from '@/axios.js';

const route = useRoute();
const targetId = route.query.id ? parseInt(route.query.id) : null;
const items = ref([]);
const loading = ref(true);
const activeTab = ref('ongoing'); // 'ongoing' | 'upcoming'

const ongoingCount = computed(() => items.value.filter(i => i.is_ongoing || !i.is_upcoming).length);
const upcomingCount = computed(() => items.value.filter(i => i.is_upcoming).length);

const tabItems = computed(() => {
  if (activeTab.value === 'upcoming') {
    return items.value.filter(i => i.is_upcoming);
  }
  return items.value.filter(i => i.is_ongoing || !i.is_upcoming);
});

// ── Phân trang ──
const currentPage = ref(1);
const perPage = ref(6); // 6 sản phẩm/trang (3 cột x 2 hàng)

const totalPages = computed(() => {
  return Math.ceil(tabItems.value.length / perPage.value) || 1;
});

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  return tabItems.value.slice(start, start + perPage.value);
});

const setTab = (tab) => {
  activeTab.value = tab;
  currentPage.value = 1;
};

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
      items.value = list.filter(item => item.id === targetId || item.item_id === targetId);
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

      <!-- ══ TABS FILTER ══ -->
      <div class="fs-tabs-bar">
        <button 
          class="fs-tab-btn" 
          :class="{ active: activeTab === 'ongoing' }"
          @click="setTab('ongoing')"
        >
          <AppIcon name="zap" size="16" />
          <span>Đang diễn ra</span>
          <span class="tab-badge" v-if="ongoingCount > 0">{{ ongoingCount }}</span>
        </button>
        <button 
          class="fs-tab-btn" 
          :class="{ active: activeTab === 'upcoming' }"
          @click="setTab('upcoming')"
        >
          <AppIcon name="clock" size="16" />
          <span>Sắp diễn ra</span>
          <span class="tab-badge upcoming" v-if="upcomingCount > 0">{{ upcomingCount }}</span>
        </button>
      </div>

      <!-- ══ DANH SÁCH SẢN PHẨM FLASH SALE ══ -->
      <section class="content">
        <!-- Modern Skeleton Loading -->
        <div v-if="loading" class="flash-sale-skeleton-grid">
          <div v-for="i in 4" :key="i" class="fs-skeleton-card">
            <div class="fs-skeleton-img skeleton-box"></div>
            <div class="fs-skeleton-body">
              <div class="skeleton-box" style="width: 75%; height: 20px; border-radius: 4px; margin-bottom: 12px;"></div>
              <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px;">
                <div class="skeleton-box" style="width: 100px; height: 24px; border-radius: 6px;"></div>
                <div class="skeleton-box" style="width: 70px; height: 16px; border-radius: 4px;"></div>
              </div>
              <div class="skeleton-box" style="width: 100%; height: 14px; border-radius: 20px; margin-bottom: 16px;"></div>
              <div class="skeleton-box" style="width: 100%; height: 42px; border-radius: 10px;"></div>
            </div>
          </div>
        </div>

        <!-- Co danh sach sản phẩm -->
        <div v-else-if="tabItems.length > 0">
          <div class="toolbar-info mb-3 d-flex justify-content-between align-items-center">
            <span class="toolbar-count">
              Hiển thị <strong>{{ paginatedItems.length }}</strong> trong tổng số <strong>{{ tabItems.length }}</strong> deal {{ activeTab === 'ongoing' ? 'đang diễn ra' : 'sắp diễn ra' }}
            </span>
            <span v-if="totalPages > 1" class="page-indicator">
              Trang {{ currentPage }} / {{ totalPages }}
            </span>
          </div>

          <div class="boards-container">
            <div class="board-wrapper" v-for="item in paginatedItems" :key="item.item_id || item.id">
              <FlashSaleBoard :item-id="item.item_id" :flash-sale-id="item.flash_sale_id || item.id" />
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

/* ── TABS BAR ── */
.fs-tabs-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  background: #ffffff;
  padding: 8px;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.fs-tab-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  border: 1.5px solid transparent;
  background: transparent;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
  color: #64748b;
  cursor: pointer;
  transition: all 0.22s ease;
}

.fs-tab-btn:hover:not(.active) {
  background: #fff0f5;
  color: var(--primary, #E63B6F);
  border-color: #ffd7e3;
  transform: translateY(-1px);
}

.fs-tab-btn:hover:not(.active) .tab-badge {
  background: #ffd7e3;
  color: var(--primary, #E63B6F);
}

.fs-tab-btn.active {
  background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%);
  color: #ffffff;
  border-color: transparent;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 800;
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
  transition: all 0.2s ease;
}

.fs-tab-btn:not(.active) .tab-badge {
  background: #e2e8f0;
  color: #475569;
}

.fs-tab-btn:not(.active) .tab-badge.upcoming {
  background: #e0f2fe;
  color: #0284c7;
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

/* ===== Modern Skeleton Loading Styles ===== */
.flash-sale-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
}

.fs-skeleton-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.fs-skeleton-img {
  width: 100%;
  height: 220px;
}

.fs-skeleton-body {
  padding: 16px;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
