<template>
  <div class="profile-coupon">
    <div class="section-header">
      <div>
        <h1 class="section-title">Mã giảm giá của tôi</h1>
        <p class="section-desc">Danh sách các mã giảm giá bạn đã lưu trong ví</p>
      </div>
      <router-link to="/coupon" class="btn-discover-more">
        <AppIcon name="ticket" size="16" />
        <span>Săn thêm voucher</span>
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="coupon-list-skeleton">
      <div class="skeleton-card" v-for="i in 4" :key="i">
        <div class="skeleton-pulse sk-left"></div>
        <div class="skeleton-pulse sk-mid"></div>
        <div class="skeleton-pulse sk-right"></div>
      </div>
    </div>

    <!-- Coupon List -->
    <div v-else-if="coupons.length > 0" class="coupon-list">
      <div
        v-for="coupon in coupons"
        :key="coupon.id"
        class="coupon-ticket"
        :class="[coupon.type, { 'is-expired': isExpired(coupon.end_date) }]"
      >
        <!-- Cột Trái: Icon & Loại giảm giá -->
        <div class="ticket-left">
          <div class="ticket-icon-box" :class="coupon.type">
            <AppIcon :name="getCouponIcon(coupon)" size="24" stroke-width="2.2" />
          </div>
          <span class="ticket-type-label" :class="coupon.type">
            {{ getCouponTypeLabel(coupon) }}
          </span>
        </div>

        <!-- Cột Giữa: Giá trị & Điều kiện -->
        <div class="ticket-center">
          <div class="ticket-value">
            {{ formatValue(coupon) }}
          </div>
          <div class="ticket-condition">
            <span v-if="coupon.min_order_value">
              Đơn từ <strong>{{ formatCurrency(coupon.min_order_value) }}</strong>
            </span>
            <span v-else class="text-success-cond">Áp dụng mọi đơn hàng</span>
          </div>
          <div v-if="coupon.max_discount_value && coupon.type === 'percent'" class="ticket-sub-condition">
            Tối đa {{ formatCurrency(coupon.max_discount_value) }}
          </div>
          <div class="ticket-expiry">
            <AppIcon name="clock" size="13" />
            <span>HSD: {{ formatDate(coupon.end_date) }}</span>
          </div>
        </div>

        <!-- Đường đục lỗ & Răng cưa Ticket -->
        <div class="ticket-perforation">
          <div class="perforation-notch notch-top"></div>
          <div class="perforation-line"></div>
          <div class="perforation-notch notch-bottom"></div>
        </div>

        <!-- Cột Phải: Mã & Nút sao chép -->
        <div class="ticket-right">
          <div class="code-box" :title="coupon.code">
            <span class="code-eyebrow">MÃ</span>
            <span class="code-badge">{{ coupon.code }}</span>
          </div>

          <button
            type="button"
            class="btn-ticket-action"
            :class="{ 'is-copied': copiedCode === coupon.code }"
            :disabled="isExpired(coupon.end_date)"
            @click="copyCode(coupon.code)"
          >
            <AppIcon v-if="copiedCode === coupon.code" name="check" size="14" stroke-width="2.5" />
            <AppIcon v-else name="copy" size="14" stroke-width="2" />
            <span>{{ copiedCode === coupon.code ? 'Đã chép' : 'Sao chép' }}</span>
          </button>
        </div>

        <!-- Nhãn hết hạn nếu coupon đã qua HSD -->
        <div v-if="isExpired(coupon.end_date)" class="expired-watermark">
          <span>HẾT HẠN</span>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <div class="empty-icon-wrap">
        <AppIcon name="voucher" size="56" stroke-width="1.5" />
      </div>
      <h3 class="empty-title">Bạn chưa lưu mã giảm giá nào</h3>
      <p class="empty-desc">Khám phá ngay kho voucher ưu đãi hấp dẫn để tiết kiệm chi phí mua sắm!</p>
      <router-link to="/coupon" class="btn-explore">
        <AppIcon name="ticket" size="18" />
        <span>Khám phá kho voucher</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';
import { useToast } from '@/composables/useToast';
import AppIcon from '@/components/AppIcon.vue';

const { showToast } = useToast();

const coupons = ref([]);
const loading = ref(true);
const copiedCode = ref(null);

const fetchUserCoupons = async () => {
  loading.value = true;
  try {
    const response = await api.get('/profile/coupons');
    if (response.data.status === 'success') {
      coupons.value = response.data.data || [];
    }
  } catch (error) {
    console.error('Error fetching user coupons:', error);
  } finally {
    loading.value = false;
  }
};

const formatValue = (coupon) => {
  if (coupon.type === 'percent') return `Giảm ${Number(coupon.value || 0)}%`;
  if (coupon.type === 'free_ship') return `Freeship ${formatCurrency(coupon.value)}`;
  return `Giảm ${formatCurrency(coupon.value)}`;
};

const getCouponIcon = (coupon) => {
  if (coupon.type === 'free_ship') return 'shipping';
  if (coupon.type === 'percent') return 'ticket';
  return 'tag';
};

const getCouponTypeLabel = (coupon) => {
  if (coupon.type === 'free_ship') return 'Vận chuyển';
  if (coupon.type === 'percent') return 'Phần trăm';
  return 'Giảm giá';
};

const formatCurrency = (val) => {
  if (!val) return '0₫';
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
  if (!code) return;
  navigator.clipboard.writeText(code);
  copiedCode.value = code;
  showToast('Đã sao chép mã giảm giá vào bộ nhớ tạm!', 'success');

  setTimeout(() => {
    if (copiedCode.value === code) {
      copiedCode.value = null;
    }
  }, 2500);
};

onMounted(fetchUserCoupons);
</script>

<style scoped>
.profile-coupon {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main, #1e293b);
  margin: 0;
  letter-spacing: -0.02em;
}

.section-desc {
  font-size: 0.9rem;
  color: #64748b;
  margin: 4px 0 0;
}

.btn-discover-more {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #fff;
  border: 1.5px solid var(--primary, #e63b6f);
  color: var(--primary, #e63b6f);
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 10px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-discover-more:hover {
  background: var(--primary, #e63b6f);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

/* Skeletons */
.coupon-list-skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
}

.skeleton-card {
  display: flex;
  height: 124px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  padding: 16px;
  gap: 16px;
}

.skeleton-pulse {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: pulse 1.5s infinite;
  border-radius: 10px;
}

.sk-left { width: 64px; height: 100%; }
.sk-mid { flex: 1; height: 100%; }
.sk-right { width: 110px; height: 100%; }

@keyframes pulse {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Coupon Grid */
.coupon-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
}

/* Ticket Card */
.coupon-ticket {
  position: relative;
  display: flex;
  align-items: stretch;
  background: #ffffff;
  border: 1.5px solid #eef2f6;
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.coupon-ticket:hover {
  transform: translateY(-3px);
  border-color: #fbcfe8;
  box-shadow: 0 10px 25px rgba(230, 59, 111, 0.1);
}

.coupon-ticket.is-expired {
  opacity: 0.65;
  filter: grayscale(0.85);
  border-color: #e2e8f0;
}

.coupon-ticket.is-expired:hover {
  transform: none;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}

/* Cột Trái */
.ticket-left {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 16px 14px;
  background: #f8fafc;
  min-width: 88px;
  gap: 8px;
  border-right: 1px solid #f1f5f9;
}

.ticket-icon-box {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s ease;
}

.coupon-ticket:hover .ticket-icon-box {
  transform: scale(1.08);
}

.ticket-icon-box.fixed,
.ticket-icon-box:not(.percent):not(.free_ship) {
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  color: #15803d;
}

.ticket-icon-box.percent {
  background: linear-gradient(135deg, #ffe4e6, #fecdd3);
  color: var(--primary, #e63b6f);
}

.ticket-icon-box.free_ship {
  background: linear-gradient(135deg, #e0f2fe, #bae6fd);
  color: #0284c7;
}

.ticket-type-label {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 8px;
  border-radius: 6px;
}

.ticket-type-label.fixed,
.ticket-type-label:not(.percent):not(.free_ship) {
  background: #f0fdf4;
  color: #166534;
}

.ticket-type-label.percent {
  background: #fff1f2;
  color: var(--primary, #e63b6f);
}

.ticket-type-label.free_ship {
  background: #f0f9ff;
  color: #0369a1;
}

/* Cột Giữa */
.ticket-center {
  flex: 1;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
  min-width: 0;
}

.ticket-value {
  font-size: 1.18rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.25;
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ticket-condition {
  font-size: 0.84rem;
  color: #64748b;
  line-height: 1.3;
}

.ticket-condition strong {
  color: #334155;
  font-weight: 600;
}

.text-success-cond {
  color: #16a34a;
  font-weight: 600;
}

.ticket-sub-condition {
  font-size: 0.76rem;
  color: #94a3b8;
}

.ticket-expiry {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.76rem;
  color: #94a3b8;
  margin-top: 4px;
  font-weight: 500;
}

/* Perforation Divider */
.ticket-perforation {
  position: relative;
  width: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
}

.perforation-line {
  height: calc(100% - 28px);
  margin-top: 14px;
  border-left: 2px dashed #cbd5e1;
}

.perforation-notch {
  position: absolute;
  width: 16px;
  height: 16px;
  background: #f8fafc; /* Màu nền profile trang */
  border-radius: 50%;
  left: -8px;
  z-index: 2;
  box-shadow: inset 0 0 0 1.5px #eef2f6;
}

.notch-top {
  top: -9px;
}

.notch-bottom {
  bottom: -9px;
}

/* Cột Phải */
.ticket-right {
  width: 120px;
  padding: 14px 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: #fafbfc;
}

.code-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.code-eyebrow {
  font-size: 0.65rem;
  font-weight: 700;
  color: #94a3b8;
  letter-spacing: 0.08em;
}

.code-badge {
  font-size: 0.88rem;
  font-weight: 800;
  color: var(--primary, #e63b6f);
  letter-spacing: 0.04em;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-ticket-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 8px 12px;
  background: var(--primary, #e63b6f);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 3px 8px rgba(230, 59, 111, 0.25);
}

.btn-ticket-action:hover:not(:disabled) {
  background: #d62f62;
  transform: translateY(-1px);
  box-shadow: 0 5px 12px rgba(230, 59, 111, 0.35);
}

.btn-ticket-action.is-copied {
  background: #10b981;
  box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);
}

.btn-ticket-action:disabled {
  background: #cbd5e1;
  color: #64748b;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

/* Watermark hết hạn */
.expired-watermark {
  position: absolute;
  top: 10px;
  right: -24px;
  transform: rotate(45deg);
  background: #64748b;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 3px 30px;
  letter-spacing: 0.08em;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  z-index: 3;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 60px 24px;
  background: #ffffff;
  border-radius: 20px;
  border: 1.5px dashed #cbd5e1;
  margin-top: 8px;
}

.empty-icon-wrap {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: #fdf2f8;
  color: var(--primary, #e63b6f);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.empty-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 8px;
}

.empty-desc {
  font-size: 0.92rem;
  color: #64748b;
  max-width: 400px;
  margin: 0 0 24px;
  line-height: 1.5;
}

.btn-explore {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 28px;
  background: var(--primary, #e63b6f);
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.3);
}

.btn-explore:hover {
  background: #d62f62;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(230, 59, 111, 0.4);
}

/* Responsive */
@media (max-width: 640px) {
  .coupon-list,
  .coupon-list-skeleton {
    grid-template-columns: 1fr;
  }

  .ticket-left {
    min-width: 76px;
    padding: 12px 8px;
  }

  .ticket-right {
    width: 105px;
    padding: 12px 10px;
  }

  .ticket-value {
    font-size: 1.05rem;
  }
}

/* ===== Modern Skeleton Loading Styles ===== */
.coupon-list-skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
  pointer-events: none;
}

.coupon-list-skeleton .skeleton-card {
  display: flex;
  height: 120px;
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 14px;
  overflow: hidden;
}

.sk-left {
  width: 90px;
  height: 100%;
}

.sk-mid {
  flex: 1;
  margin: 16px;
  border-radius: 8px;
}

.sk-right {
  width: 100px;
  height: 100%;
}

.skeleton-pulse {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-pulse::after {
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
