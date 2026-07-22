<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '@/axios';
import { useRouter } from 'vue-router';
import { Toast } from 'bootstrap';

const coupons = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const router = useRouter();

const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('couponToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};

const fetchPublicCoupons = async () => {
  try {
    isLoading.value = true;
    const response = await api.get('/coupons/public');
    if (response.data.status === 'success') {
      coupons.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching coupons:', error);
  } finally {
    isLoading.value = false;
  }
};

const filteredCoupons = computed(() => {
  let list = coupons.value;
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(c => 
      c.code.toLowerCase().includes(q) || 
      c.description?.toLowerCase().includes(q)
    );
  }
  return list.sort((a, b) => {
    const aActive = a.is_active && !isExpired(a.end_date) ? 1 : 0;
    const bActive = b.is_active && !isExpired(b.end_date) ? 1 : 0;
    return bActive - aActive;
  });
});

const formatValue = (coupon) => {
  if (coupon.type === 'percent') return `${coupon.value}%`;
  if (coupon.type === 'free_ship') return `Freeship ${formatCurrency(coupon.value)}`;
  return formatCurrency(coupon.value);
};

const formatCurrency = (val) => {
  if (!val) return '0₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (dateString) => {
  if (!dateString) return 'Vô hạn';
  return new Date(dateString).toLocaleDateString('vi-VN');
};

const isExpired = (endDate) => {
  if (!endDate) return false;
  return new Date(endDate) < new Date();
};

const copyCode = (code) => {
  navigator.clipboard.writeText(code);
  showToast(`Đã sao chép mã: ${code}`, 'success');
};

const saveCoupon = async (couponId) => {
  if ( sessionStorage.getItem('user') == null ){
    router.push('/client/login?redirect=' + router.currentRoute.value.fullPath);
    return;
  }
  try {
    const response = await api.post('/profile/coupons/save', { coupon_id: couponId });
    if (response.data.status === 'success') {
      showToast(response.data.message, 'success');
    } else if (response.data.status === 'info') {
      showToast(response.data.message, 'info');
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Không thể lưu mã giảm giá!';
    showToast(msg, 'danger');
  }
};

onMounted(fetchPublicCoupons);
</script>

<template>
  <main class="coupon-page min-vh-100 pb-5">
    <!-- Hero Section -->
    <div class="hero-section text-white text-center mb-5 shadow-sm">
      <div class="container py-2">
        <h1 class="h2 fw-bold mb-2">Săn Voucher</h1>
        <p class="small opacity-75 mb-0">Ưu đãi hấp dẫn dành riêng cho bạn</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="container px-4">
      <!-- Search Bar -->
      <div class="row justify-content-center mb-5">
        <div class="col-md-5">
          <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden border">
            <span class="input-group-text bg-white border-0 ps-3">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
            </span>
            <input 
              v-model="searchQuery" 
              type="text" 
              class="form-control border-0 py-2" 
              placeholder="Tìm mã giảm giá..."
            >
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="row g-3">
        <div v-for="n in 8" :key="n" class="col-6 col-md-4 col-lg-3">
          <div class="placeholder-glow">
            <div class="placeholder rounded-3 w-100" style="height: 180px;"></div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredCoupons.length === 0" class="text-center py-5">
        <h5 class="text-muted">Không tìm thấy voucher nào</h5>
      </div>

      <!-- Vouchers Grid (4 columns) -->
      <div v-else class="row g-4">
        <div 
          v-for="coupon in filteredCoupons" 
          :key="coupon.id" 
          class="col-6 col-md-4 col-lg-3"
        >
          <div 
            class="card coupon-card h-100 border-0 shadow-sm rounded-4 overflow-visible d-flex flex-column"
            :class="{ 'coupon-disabled': isExpired(coupon.end_date) || !coupon.is_active }"
          >
            <!-- Content Top -->
            <div class="coupon-top p-3 flex-grow-1 d-flex flex-column">
              <!-- Header Badge -->
              <div class="d-flex mb-3">
                <span class="badge-custom" :class="{
                  'badge-percent': coupon.type === 'percent',
                  'badge-freeship': coupon.type === 'free_ship',
                  'badge-fixed': coupon.type === 'fixed'
                }">
                  <!-- Percent Icon -->
                  <svg v-if="coupon.type === 'percent'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1">
                    <line x1="19" y1="5" x2="5" y2="19"></line>
                    <circle cx="6.5" cy="6.5" r="2.5"></circle>
                    <circle cx="17.5" cy="17.5" r="2.5"></circle>
                  </svg>
                  <!-- Freeship Icon -->
                  <svg v-else-if="coupon.type === 'free_ship'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                  </svg>
                  <!-- Fixed Icon -->
                  <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                  </svg>
                  <span>{{ coupon.type === 'free_ship' ? 'Miễn phí vận chuyển' : coupon.type === 'fixed' ? 'Giảm giá trực tiếp' : coupon.type === 'percent' ? 'Giảm giá phần trăm' : '' }}</span>
                </span>
              </div>

              <!-- Code Display Box (Clickable to Copy) -->
              <div class="coupon-code-box d-flex align-items-center justify-content-between mb-3" @click="copyCode(coupon.code)" role="button" title="Click để sao chép mã">
                <span class="coupon-code-text fw-bold">{{ coupon.code }}</span>
                <span class="copy-icon">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                  </svg>
                </span>
              </div>

              <!-- Value -->
              <div class="coupon-value mb-1">
                {{ formatValue(coupon) }}
              </div>
              
              <!-- Min Order Value -->
              <div v-if="coupon.min_order_value" class="coupon-min-order text-muted mt-auto">
                Đơn từ <strong class="text-dark">{{ formatCurrency(coupon.min_order_value) }}</strong>
              </div>
            </div>

            <!-- Ticket Divider -->
            <div class="coupon-divider"></div>

            <!-- Content Bottom -->
            <div class="coupon-bottom p-3">
              <div class="coupon-expiry mb-3">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                HSD: {{ formatDate(coupon.end_date) }}
              </div>
              <div class="d-grid gap-2">
                <button 
                  class="btn btn-save-coupon fw-bold text-white shadow-sm"
                  :disabled="!coupon.is_active || isExpired(coupon.end_date)"
                  @click="saveCoupon(coupon.id)"
                >
                  Lưu mã
                </button>
                <button 
                  class="btn btn-copy-coupon fw-bold"
                  @click="copyCode(coupon.code)"
                >
                  Sao chép mã
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast UI -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000">
      <div 
        class="toast align-items-center border-0 shadow-sm rounded-3" 
        :class="{
          'text-bg-success': toast.type === 'success',
          'text-bg-danger': toast.type === 'danger',
          'text-bg-info': toast.type === 'info'
        }" 
        id="couponToast" 
        role="alert"
      >
        <div class="d-flex">
          <div class="toast-body small fw-bold">{{ toast.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
/* Main Page Background */
.coupon-page {
  background-color: #FCF9FA;
}

/* Hero Banner Breakout to fill screen width */
.hero-section {
  background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
  position: relative;
  width: 100vw;
  margin-left: calc(-50vw + 50%);
  padding: 56px 0;
  box-shadow: 0 4px 20px rgba(181, 12, 77, 0.15);
}

.hero-section h1 {
  font-size: 2.5rem;
  letter-spacing: -0.5px;
}

/* Search bar styling */
.input-group {
  border-color: #FFE3E8 !important;
  transition: all 0.3s ease;
}

.input-group:focus-within {
  border-color: var(--primary) !important;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.15) !important;
}

.input-group-text svg {
  color: var(--primary);
}

.form-control:focus {
  box-shadow: none;
}

/* Coupon Card Layout */
.coupon-card {
  background: var(--card-bg);
  border: 1px solid #FFE3E8 !important;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(230, 59, 111, 0.03);
  transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
  position: relative;
}

.coupon-card:hover:not(.coupon-disabled) {
  transform: translateY(-6px);
  box-shadow: 0 15px 35px rgba(230, 59, 111, 0.08);
  border-color: #FFE0E6 !important;
}

/* Badge Custom */
.badge-custom {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  background: #FFF0F3;
  color: var(--primary);
  border: 1px solid rgba(230, 59, 111, 0.1);
}

/* Code Display Box */
.coupon-code-box {
  background: #FFF5F7;
  border: 1.5px dashed var(--primary);
  border-radius: 10px;
  padding: 8px 14px;
  color: var(--primary);
  cursor: pointer;
  transition: all 0.2s ease;
}

.coupon-code-box:hover {
  background: #FFF0F3;
  transform: scale(1.02);
}

.coupon-code-text {
  font-size: 1rem;
  letter-spacing: 0.5px;
}

.copy-icon {
  opacity: 0.8;
  display: inline-flex;
}

/* Value Display */
.coupon-value {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--text-main);
  letter-spacing: -0.5px;
}

.coupon-min-order {
  font-size: 0.78rem;
}

/* Expiry Date */
.coupon-expiry {
  font-size: 0.75rem;
  display: flex;
  align-items: center;
  color: #64748B !important;
}

.coupon-expiry svg {
  opacity: 0.7;
}

/* Ticket Divider & Side Notches */
.coupon-divider {
  position: relative;
  height: 1px;
  border-top: 1px dashed #FFE3E8;
  margin: 0 12px;
}

.coupon-divider::before,
.coupon-divider::after {
  content: '';
  position: absolute;
  top: -8px;
  width: 16px;
  height: 16px;
  background-color: #FCF9FA; /* matches the page body background */
  border-radius: 50%;
  border: 1.5px solid #FFE3E8;
  z-index: 2;
  transition: background-color 0.3s ease;
}

.coupon-divider::before {
  left: -21px; /* Align perfectly centered on the card border */
  clip-path: polygon(50% 0%, 100% 0%, 100% 100%, 50% 100%);
}

.coupon-divider::after {
  right: -21px;
  clip-path: polygon(0% 0%, 50% 0%, 50% 100%, 0% 100%);
}

/* Buttons */
.btn-save-coupon {
  background: var(--primary);
  border: none;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}

.btn-save-coupon:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-save-coupon:disabled {
  background: #E2E8F0;
  color: #94A3B8 !important;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-copy-coupon {
  background: transparent;
  color: var(--primary);
  border: 1.5px solid var(--primary);
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}

.btn-copy-coupon:hover {
  background: #FFF0F3;
  color: var(--primary);
}

/* Disabled Coupon Card Styling */
.coupon-disabled {
  opacity: 0.6;
  filter: grayscale(30%);
}
</style>