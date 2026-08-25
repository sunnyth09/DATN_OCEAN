<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import api from '@/axios';

const props = defineProps({
  coupon: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'copy', 'viewAll']);

const router = useRouter();
const route = useRoute();

const localIsSaved = ref(false);
const isSaving = ref(false);
const isCopied = ref(false);
let copyTimeout = null;

const isLoggedIn = computed(() => sessionStorage.getItem('user') !== null);

const formatCurrency = (val) => {
  if (!val) return '0₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatCouponValue = (coupon) => {
  if (!coupon) return '';
  if (coupon.type === 'percent') {
    const num = Number(coupon.value);
    return `Giảm ${isNaN(num) ? coupon.value : num}%`;
  }
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

const checkSavedStatus = async () => {
  if (!isLoggedIn.value || !props.coupon) return;
  try {
    const res = await api.get('/profile/coupons');
    if (res.data?.status === 'success') {
      const savedList = res.data.data || [];
      localIsSaved.value = savedList.some(c => c.id === props.coupon.id);
    }
  } catch (e) {
    console.error('Lỗi kiểm tra trạng thái lưu coupon:', e);
  }
};

const handleSaveCoupon = async () => {
  if (!props.coupon || isSaving.value || localIsSaved.value) return;
  isSaving.value = true;
  try {
    const res = await api.post('/profile/coupons/save', { coupon_id: props.coupon.id });
    if (res.data?.status === 'success' || res.data?.status === 'info') {
      localIsSaved.value = true;
      window.dispatchEvent(new Event('coupon-saved'));
    }
  } catch (e) {
    console.error('Lỗi lưu coupon:', e);
  } finally {
    isSaving.value = false;
  }
};

const handleCopyCode = async () => {
  if (!props.coupon?.code) return;
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(props.coupon.code);
    } else {
      const textArea = document.createElement('textarea');
      textArea.value = props.coupon.code;
      textArea.style.position = 'fixed';
      textArea.style.opacity = '0';
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
    }
  } catch (e) {
    console.warn('Clipboard copy error:', e);
  }

  isCopied.value = true;
  emit('copy', props.coupon.code);

  if (copyTimeout) clearTimeout(copyTimeout);
  copyTimeout = setTimeout(() => {
    isCopied.value = false;
  }, 2000);
};

const goToLogin = () => {
  emit('close');
  router.push('/client/login?redirect=' + route.fullPath);
};

watch(() => props.coupon, (newVal) => {
  if (newVal) {
    localIsSaved.value = false;
    isCopied.value = false;
    checkSavedStatus();
  }
}, { immediate: true });

onUnmounted(() => {
  if (copyTimeout) clearTimeout(copyTimeout);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="coupon-modal-fade">
      <div v-if="coupon" class="coupon-modal-backdrop" @click.self="emit('close')">
        <Transition name="coupon-modal-card" appear>
          <section class="coupon-modal-card" role="dialog" aria-modal="true" aria-label="Chi tiết voucher">
            <button class="coupon-modal-close" type="button" aria-label="Đóng" @click="emit('close')">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>

            <div class="coupon-modal-hero">
              <div class="coupon-modal-icon">
                <AppIcon :name="getCouponIcon(coupon)" width="22" height="22" :stroke-width="2.2" />
              </div>
              <span class="coupon-modal-kicker">{{ couponLabel(coupon) }}</span>
              <h3>{{ formatCouponValue(coupon) }}</h3>
              <p>Sao chép mã trước, sau đó đăng nhập khi thanh toán để áp dụng voucher cho đơn hàng đủ điều kiện.</p>
            </div>

            <div class="coupon-modal-body">
              <div class="coupon-code-panel" :class="{ 'is-copied-panel': isCopied }">
                <span>{{ coupon.code }}</span>
                <button 
                  type="button" 
                  class="btn-copy-code"
                  :class="{ 'is-copied': isCopied }"
                  @click="handleCopyCode"
                >
                  <svg v-if="!isCopied" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                  </svg>
                  <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                  {{ isCopied ? 'Đã sao chép' : 'Sao chép mã' }}
                </button>
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
                  <strong class="status-active">{{ coupon.is_active ? 'Đang khả dụng' : 'Tạm ngưng' }}</strong>
                </div>
              </div>

              <div class="coupon-modal-note">
                <strong>Lưu ý:</strong> Voucher public có thể xem và sao chép, nhưng chỉ tài khoản đã đăng nhập mới được sử dụng tại bước thanh toán.
              </div>

              <div class="coupon-modal-actions">
                <template v-if="isLoggedIn">
                  <button 
                    class="btn-copy" 
                    type="button" 
                    :disabled="localIsSaved || isSaving" 
                    @click="handleSaveCoupon"
                  >
                    <span v-if="isSaving" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    {{ localIsSaved ? 'Đã lưu mã' : 'Lưu mã' }}
                  </button>
                </template>
                <template v-else>
                  <button class="btn-copy" type="button" @click="goToLogin">Đăng nhập để lưu</button>
                </template>

                <button class="btn-view" type="button" @click="emit('viewAll')">Xem tất cả mã</button>
              </div>
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
  padding: 16px;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(6px);
}

.coupon-modal-card {
  position: relative;
  width: min(420px, calc(100vw - 32px));
  max-height: min(88vh, 600px);
  display: flex;
  flex-direction: column;
  border-radius: 20px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
}

.coupon-modal-close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 3;
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 50%;
  color: #fff;
  background: rgba(255, 255, 255, 0.22);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.coupon-modal-close:hover {
  background: rgba(255, 255, 255, 0.38);
  transform: scale(1.06);
}

.coupon-modal-hero {
  flex-shrink: 0;
  padding: 18px 20px 16px;
  color: #fff;
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 30%),
    linear-gradient(135deg, var(--primary, #e63b6f), #ff6b9d);
}

.coupon-modal-icon {
  width: 40px;
  height: 40px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.2);
}

.coupon-modal-kicker {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.85);
}

.coupon-modal-hero h3 {
  margin: 4px 0 6px;
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.4px;
  line-height: 1.25;
}

.coupon-modal-hero p {
  margin: 0;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.9);
  line-height: 1.45;
}

.coupon-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 14px 18px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}

.coupon-modal-body::-webkit-scrollbar {
  width: 5px;
}

.coupon-modal-body::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.coupon-code-panel {
  padding: 8px 10px 8px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1.5px dashed var(--primary, #e63b6f);
  border-radius: 12px;
  background: #fff5f8;
  transition: all 0.25s ease;
}

.coupon-code-panel.is-copied-panel {
  border-color: #10b981;
  background: #ecfdf5;
}

.coupon-code-panel span {
  min-width: 0;
  flex: 1;
  color: var(--primary, #e63b6f);
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: 0.6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: color 0.25s ease;
}

.coupon-code-panel.is-copied-panel span {
  color: #059669;
}

.btn-copy-code,
.btn-copy,
.btn-view {
  border: 0;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.btn-copy-code {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 7px 12px;
  font-size: 0.8rem;
  border-radius: 8px;
  color: #fff;
  background: var(--primary, #e63b6f);
  white-space: nowrap;
}

.btn-copy-code:hover {
  transform: translateY(-1px);
}

.btn-copy-code.is-copied {
  background: #10b981;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
  transform: scale(1.03);
}

.coupon-detail-list {
  display: grid;
  gap: 0;
}

.coupon-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 7px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.84rem;
}

.coupon-detail-row:last-child {
  border-bottom: none;
}

.coupon-detail-row span {
  color: #64748b;
}

.coupon-detail-row strong {
  color: #1e293b;
  text-align: right;
}

.coupon-detail-row strong.status-active {
  color: #059669;
}

.coupon-modal-note {
  padding: 8px 11px;
  border-radius: 8px;
  color: #9a3412;
  background: #fff7ed;
  font-size: 0.78rem;
  line-height: 1.4;
}

.coupon-modal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 2px;
}

.btn-copy,
.btn-view {
  min-height: 38px;
  font-size: 0.84rem;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-copy {
  color: #fff;
  background: var(--primary, #e63b6f);
  box-shadow: 0 8px 18px rgba(230, 59, 111, 0.2);
}

.btn-view {
  color: var(--primary, #e63b6f);
  background: #fff0f5;
}

.btn-copy:hover,
.btn-view:hover,
.coupon-code-panel button:hover {
  transform: translateY(-1px);
}

.coupon-modal-fade-enter-active,
.coupon-modal-fade-leave-active {
  transition: opacity 0.22s ease;
}

.coupon-modal-fade-enter-from,
.coupon-modal-fade-leave-to {
  opacity: 0;
}

.coupon-modal-card-enter-active,
.coupon-modal-card-leave-active {
  transition: opacity 0.24s ease, transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
}

.coupon-modal-card-enter-from,
.coupon-modal-card-leave-to {
  opacity: 0;
  transform: translateY(14px) scale(0.97);
}

@media (max-width: 480px) {
  .coupon-modal-backdrop {
    padding: 12px;
  }

  .coupon-modal-hero {
    padding: 16px 16px 14px;
  }

  .coupon-modal-body {
    padding: 12px 14px 16px;
  }

  .coupon-modal-actions {
    grid-template-columns: 1fr;
  }
}
</style>
