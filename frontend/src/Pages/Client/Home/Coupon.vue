<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '@/axios';
import { useRouter } from 'vue-router';
import { Toast } from 'bootstrap';

const coupons = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');

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
    <div class="hero-section text-white py-4 mb-4 shadow-sm">
      <div class="container text-center">
        <h1 class="h2 fw-bold mb-2">Săn Voucher</h1>
        <p class="small opacity-75 mb-0">Ưu đãi hấp dẫn dành riêng cho bạn</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="container px-4">
      <!-- Search Bar -->
      <div class="row justify-content-center mb-4">
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
      <div v-else class="row g-3">
        <div 
          v-for="coupon in filteredCoupons" 
          :key="coupon.id" 
          class="col-6 col-md-4 col-lg-3"
        >
          <div 
            class="card coupon-card h-100 border-0 shadow-sm rounded-3 overflow-hidden"
            :class="{ 'opacity-50': isExpired(coupon.end_date) || !coupon.is_active }"
          >
            <!-- Content -->
            <div class="card-body p-3 d-flex flex-column">
              <!-- Header -->
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="badge rounded-pill" :class="{
                  'bg-danger-subtle text-danger': coupon.type === 'percent',
                  'bg-primary-subtle text-primary': coupon.type === 'free_ship',
                  'bg-success-subtle text-success': coupon.type === 'fixed'
                }">
                  <span class="x-small fw-bold">{{ coupon.type === 'free_ship' ? 'Miễn phí vận chuyển' : coupon.type === 'fixed' ? 'Giảm giá trực tiếp' : coupon.type === 'percent' ? 'Giảm giá theo phần trăm' : '' }}</span>
                </div>
              </div>

              <!-- Code -->
              <div class="h5 fw-bold text-primary mb-1 text-truncate" title="Click to copy" role="button" @click="copyCode(coupon.code)">
                {{ coupon.code }}
              </div>

              <!-- Value -->
              <div class="h4 fw-black text-dark mb-0">
                {{ formatValue(coupon) }}
              </div>
              <div v-if="coupon.min_order_value" class="x-small text-muted mb-2">
                Đơn từ {{ formatCurrency(coupon.min_order_value) }}
              </div>

              <!-- Footer -->
              <div class="mt-auto pt-2">
                <div class="x-small text-muted mb-2 text-truncate">HSD: {{ formatDate(coupon.end_date) }}</div>
                <div class="d-grid gap-1">
                  <button 
                    class="btn btn-primary btn-sm rounded-2 fw-bold p-2"
                    :disabled="!coupon.is_active || isExpired(coupon.end_date)"
                    @click="saveCoupon(coupon.id)"
                  >
                    Lưu mã
                  </button>
                  <button 
                    class="btn btn-outline-secondary btn-sm rounded-2 border-0 x-small p-2"
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
.hero-section {
  background: linear-gradient(135deg, #B50C4D 0%, #E63B6F 100%);
}

.coupon-card {
  transition: transform 0.2s;
}

.coupon-card:hover:not(.opacity-50) {
  transform: translateY(-4px);
}

.fw-black {
  font-weight: 900;
}

.x-small {
  font-size: 0.7rem;
}

.max-w-100 {
  max-width: 100%;
}
</style>