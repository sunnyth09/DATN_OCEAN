<template>
  <div class="profile-coupon">
    <div class="section-header">
      <div>
        <h1 class="section-title">Mã giảm giá của tôi</h1>
        <p class="section-desc">Danh sách các mã giảm giá bạn đã lưu</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <span>Đang tải mã giảm giá...</span>
    </div>

    <!-- Coupon List -->
    <div v-else-if="coupons.length > 0" class="coupon-list">
      <div
        v-for="coupon in coupons"
        :key="coupon.id"
        class="coupon-card"
        :class="{ 'expired': isExpired(coupon.end_date) }"
      >
        <div class="coupon-card-inner">
          <div class="coupon-left">
            <div class="coupon-type-icon" :class="coupon.type">
              <svg v-if="coupon.type === 'free_ship'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polyline points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </div>
            <div class="coupon-main-info">
              <h3 class="coupon-value">
                {{ formatValue(coupon) }}
              </h3>
              <p class="coupon-condition">
                <span v-if="coupon.min_order_value">Đơn từ {{ formatCurrency(coupon.min_order_value) }}</span>
                <span v-else>Mọi đơn hàng</span>
              </p>
            </div>
          </div>
          <div class="coupon-right">
            <div class="coupon-code-box">
              <span class="code-label">Mã:</span>
              <span class="code-text">{{ coupon.code }}</span>
            </div>
            <div class="coupon-expiry">
              HSD: {{ formatDate(coupon.end_date) }}
            </div>
            <button class="btn-copy" @click="copyCode(coupon.code)">
              Sao chép
            </button>
          </div>
        </div>
        <div v-if="isExpired(coupon.end_date)" class="expired-overlay">
          <span>Hết hạn</span>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>
        </svg>
      </div>
      <h3 class="empty-title">Bạn chưa lưu mã nào</h3>
      <p class="empty-desc">Hãy khám phá kho voucher để nhận ưu đãi hấp dẫn</p>
      <router-link to="/coupon" class="btn-explore"> Khám phá ngay </router-link>
    </div>

    <!-- Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
      <div class="toast align-items-center text-bg-success border-0" id="profileCouponToast" role="alert">
        <div class="d-flex">
          <div class="toast-body">Đã sao chép mã giảm giá!</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '@/axios';
import { Toast } from 'bootstrap';

const coupons = ref([]);
const loading = ref(true);

const fetchUserCoupons = async () => {
  loading.value = true;
  try {
    const response = await api.get('/profile/coupons');
    if (response.data.status === 'success') {
      coupons.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching user coupons:', error);
  } finally {
    loading.value = false;
  }
};

const formatValue = (coupon) => {
  if (coupon.type === 'percent') return `Giảm ${coupon.value}%`;
  if (coupon.type === 'free_ship') return `Freeship ${formatCurrency(coupon.value)}`;
  return `Giảm ${formatCurrency(coupon.value)}`;
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (dateString) => {
  if (!dateString) return 'Vô thời hạn';
  return new Date(dateString).toLocaleDateString('vi-VN');
};

const isExpired = (endDate) => {
  if (!endDate) return false;
  return new Date(endDate) < new Date();
};

const copyCode = (code) => {
  navigator.clipboard.writeText(code);
  nextTick(() => {
    const el = document.getElementById('profileCouponToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2000 }).show();
  });
};

onMounted(fetchUserCoupons);
</script>

<style scoped>
.profile-coupon {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.section-desc {
  font-size: 0.9rem;
  color: #6b7280;
  margin: 4px 0 0;
}

/* Loading */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 0;
  color: #6b7280;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #1a56db;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Coupon List */
.coupon-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.coupon-card {
  position: relative;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s;
}

.coupon-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  border-color: #c7d2fe;
}

.coupon-card.expired {
  opacity: 0.6;
}

.coupon-card-inner {
  display: flex;
  height: 100%;
}

.coupon-left {
  flex: 1;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-right: 1px dashed #e5e7eb;
  position: relative;
}

/* Răng cưa giữa thẻ */
.coupon-left::before, .coupon-left::after {
  content: '';
  position: absolute;
  right: -6px;
  width: 12px;
  height: 12px;
  background: #fdfdfd; /* Trùng với màu nền profile ngoài */
  border-radius: 50%;
  z-index: 2;
}

.coupon-left::before { top: -6px; }
.coupon-left::after { bottom: -6px; }

.coupon-type-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.coupon-type-icon.percent { background: #fee2e2; color: #dc2626; }
.coupon-type-icon.fixed { background: #dcfce7; color: #166534; }
.coupon-type-icon.free_ship { background: #e0f2fe; color: #0369a1; }

.coupon-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.coupon-condition {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 4px 0 0;
}

.coupon-right {
  width: 110px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  background: #fafafa;
}

.coupon-code-box {
  margin-bottom: 8px;
}

.code-label {
  display: block;
  font-size: 0.7rem;
  color: #9ca3af;
  text-transform: uppercase;
  font-weight: 600;
}

.code-text {
  font-size: 0.9rem;
  font-weight: 700;
  color: #1a56db;
}

.coupon-expiry {
  font-size: 0.7rem;
  color: #6b7280;
  margin-bottom: 12px;
}

.btn-copy {
  padding: 6px 12px;
  background: #1a56db;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-copy:hover {
  background: #1648b8;
}

.expired-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 5;
}

.expired-overlay span {
  padding: 4px 12px;
  background: #6b7280;
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 4px;
  transform: rotate(-15deg);
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
  background: #fff;
  border-radius: 16px;
  border: 1.5px dashed #e5e7eb;
}

.empty-icon {
  color: #e5e7eb;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #374151;
  margin: 0;
}

.empty-desc {
  font-size: 0.9rem;
  color: #9ca3af;
  margin: 8px 0 20px;
}

.btn-explore {
  padding: 10px 24px;
  background: #1a56db;
  color: #fff;
  text-decoration: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-explore:hover {
  background: #1648b8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(26, 86, 219, 0.2);
}
</style>
