<script setup>
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  coupon: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'copy', 'viewAll']);

const formatCurrency = (val) => {
  if (!val) return '0₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatCouponValue = (coupon) => {
  if (!coupon) return '';
  if (coupon.type === 'percent') return `Giảm ${coupon.value}%`;
  if (coupon.type === 'free_ship') return 'Miễn phí vận chuyển';
  return `Giảm ${formatCurrency(coupon.value)}`;
};

const formatDate = (dateString) => {
  if (!dateString) return 'Vô hạn';
  return new Date(dateString).toLocaleDateString('vi-VN');
};

const couponLabel = (coupon) => {
  if (!coupon) return '';
  if (coupon.type === 'free_ship') return 'Freeship';
  if (coupon.type === 'percent') return 'Giảm theo phần trăm';
  return 'Giảm trực tiếp';
};

const getCouponIcon = (coupon) => {
  if (!coupon) return 'voucher';
  if (coupon.type === 'free_ship') return 'shipping';
  if (coupon.type === 'percent') return 'percent';
  return 'tag';
};
</script>

<template>
  <Teleport to="body">
    <Transition name="coupon-modal-fade">
      <div v-if="coupon" class="coupon-modal-backdrop" @click.self="emit('close')">
        <Transition name="coupon-modal-card" appear>
          <section class="coupon-modal-card" role="dialog" aria-modal="true" aria-label="Chi tiết voucher">
            <button class="coupon-modal-close" type="button" aria-label="Đóng" @click="emit('close')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>

            <div class="coupon-modal-hero">
              <div class="coupon-modal-icon">
                <AppIcon :name="getCouponIcon(coupon)" width="34" height="34" :stroke-width="2.1" />
              </div>
              <span class="coupon-modal-kicker">{{ couponLabel(coupon) }}</span>
              <h3>{{ formatCouponValue(coupon) }}</h3>
              <p>Sao chép mã trước, sau đó đăng nhập khi thanh toán để áp dụng voucher cho đơn hàng đủ điều kiện.</p>
            </div>

            <div class="coupon-code-panel">
              <span>{{ coupon.code }}</span>
              <button type="button" @click="emit('copy', coupon.code)">Sao chép mã</button>
            </div>

            <div class="coupon-detail-list">
              <div class="coupon-detail-row">
                <span>Điều kiện đơn hàng</span>
                <strong v-if="coupon.min_order_value">Từ {{ formatCurrency(coupon.min_order_value) }}</strong>
                <strong v-else>Không yêu cầu</strong>
              </div>
              <div class="coupon-detail-row">
                <span>Hạn sử dụng</span>
                <strong>{{ formatDate(coupon.end_date) }}</strong>
              </div>
              <div class="coupon-detail-row" v-if="coupon.max_discount_value">
                <span>Giảm tối đa</span>
                <strong>{{ formatCurrency(coupon.max_discount_value) }}</strong>
              </div>
              <div class="coupon-detail-row">
                <span>Trạng thái</span>
                <strong>{{ coupon.is_active ? 'Đang khả dụng' : 'Tạm ngưng' }}</strong>
              </div>
            </div>

            <div class="coupon-modal-note">
              <strong>Lưu ý:</strong> Voucher public có thể xem và sao chép, nhưng chỉ tài khoản đã đăng nhập mới được sử dụng tại bước thanh toán.
            </div>

            <div class="coupon-modal-actions">
              <button class="btn-copy" type="button" @click="emit('copy', coupon.code)">Sao chép mã</button>
              <button class="btn-view" type="button" @click="emit('viewAll')">Xem tất cả voucher</button>
            </div>
          </section>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.coupon-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.46);
  backdrop-filter: blur(8px);
}

.coupon-modal-card {
  position: relative;
  width: min(520px, 100%);
  border-radius: 26px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
}

.coupon-modal-close {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 50%;
  color: #64748b;
  background: rgba(255, 255, 255, 0.86);
  display: flex;
  align-items: center;
  justify-content: center;
}

.coupon-modal-hero {
  padding: 34px 30px 28px;
  color: #fff;
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 28%),
    linear-gradient(135deg, var(--primary), #ff6b9d);
}

.coupon-modal-icon {
  width: 64px;
  height: 64px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.16);
}

.coupon-modal-kicker {
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 1.3px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.78);
}

.coupon-modal-hero h3 {
  margin: 8px 0;
  font-size: 2rem;
  font-weight: 900;
  letter-spacing: -0.8px;
}

.coupon-modal-hero p {
  max-width: 420px;
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
  line-height: 1.6;
}

.coupon-code-panel {
  margin: 24px 30px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1.5px dashed var(--primary);
  border-radius: 16px;
  background: #fff5f8;
}

.coupon-code-panel span {
  min-width: 0;
  flex: 1;
  color: var(--primary);
  font-size: 1.2rem;
  font-weight: 900;
  letter-spacing: 0.8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-code-panel button,
.btn-copy,
.btn-view {
  border: 0;
  border-radius: 12px;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.coupon-code-panel button {
  padding: 10px 14px;
  color: #fff;
  background: var(--primary);
}

.coupon-detail-list {
  display: grid;
  gap: 10px;
  padding: 0 30px;
}

.coupon-detail-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid #f1f5f9;
}

.coupon-detail-row span {
  color: #64748b;
}

.coupon-detail-row strong {
  color: #111827;
  text-align: right;
}

.coupon-modal-note {
  margin: 20px 30px 0;
  padding: 14px 16px;
  border-radius: 14px;
  color: #92400e;
  background: #fff7ed;
  font-size: 0.86rem;
  line-height: 1.55;
}

.coupon-modal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 24px 30px 30px;
}

.btn-copy,
.btn-view {
  min-height: 44px;
}

.btn-copy {
  color: #fff;
  background: var(--primary);
  box-shadow: 0 12px 24px rgba(230, 59, 111, 0.2);
}

.btn-view {
  color: var(--primary);
  background: #fff0f5;
}

.btn-copy:hover,
.btn-view:hover,
.coupon-code-panel button:hover {
  transform: translateY(-2px);
}

.coupon-modal-fade-enter-active,
.coupon-modal-fade-leave-active {
  transition: opacity 0.24s ease;
}

.coupon-modal-fade-enter-from,
.coupon-modal-fade-leave-to {
  opacity: 0;
}

.coupon-modal-card-enter-active,
.coupon-modal-card-leave-active {
  transition: opacity 0.26s ease, transform 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}

.coupon-modal-card-enter-from,
.coupon-modal-card-leave-to {
  opacity: 0;
  transform: translateY(18px) scale(0.96);
}

@media (max-width: 576px) {
  .coupon-modal-actions {
    grid-template-columns: 1fr;
  }

  .coupon-code-panel {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
