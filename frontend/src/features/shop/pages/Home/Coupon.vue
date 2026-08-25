<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import api from '@/axios';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import CouponDetailModal from '@/features/shop/components/CouponDetailModal.vue';
import AppIcon from '@/components/AppIcon.vue';

const coupons = ref([]);
const savedCoupons = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const selectedCoupon = ref(null);
const visibleLimit = ref(8);
const router = useRouter();

const showToast = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    title: type === 'success' ? 'Thành công' : (type === 'error' || type === 'danger' ? 'Lỗi' : 'Thông báo'),
    text: message,
    icon: type === 'danger' ? 'error' : (type === 'info' ? 'info' : 'success'),
    showConfirmButton: false,
    timer: 3500
  });
};

const isLoggedIn = computed(() => sessionStorage.getItem('user') !== null);

const isSaved = (couponId) => {
  return savedCoupons.value.some(c => c.id === couponId);
};

const fetchPublicCoupons = async () => {
  try {
    isLoading.value = true;
    const response = await api.get('/coupons/public');
    if (response.data.status === 'success') {
      coupons.value = response.data.data || [];
    }
    if (isLoggedIn.value) {
      const savedResponse = await api.get('/profile/coupons');
      if (savedResponse.data.status === 'success') {
        savedCoupons.value = savedResponse.data.data || [];
      }
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
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(c =>
      c.code?.toLowerCase().includes(q) ||
      c.description?.toLowerCase().includes(q)
    );
  }
  return [...list].sort((a, b) => {
    const aActive = a.is_active && !isExpired(a.end_date) ? 1 : 0;
    const bActive = b.is_active && !isExpired(b.end_date) ? 1 : 0;
    return bActive - aActive;
  });
});

const visibleCoupons = computed(() => filteredCoupons.value.slice(0, visibleLimit.value));

const hasMoreCoupons = computed(() => visibleCoupons.value.length < filteredCoupons.value.length);

const activeCouponCount = computed(() => coupons.value.filter(c => c.is_active && !isExpired(c.end_date)).length);

const loadMoreCoupons = () => {
  visibleLimit.value += 8;
};

watch(searchQuery, () => {
  visibleLimit.value = 8;
});

const formatValue = (coupon) => {
  if (coupon.type === 'percent') return `${Number(coupon.value || 0).toFixed(2)}%`;
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

const getCouponLabel = (coupon) => {
  if (coupon.type === 'free_ship') return 'Miễn phí vận chuyển';
  if (coupon.type === 'percent') return 'Giảm giá phần trăm';
  return 'Giảm giá trực tiếp';
};

const getCouponIcon = (coupon) => {
  if (coupon.type === 'free_ship') return 'shipping';
  if (coupon.type === 'percent') return 'percent';
  return 'tag';
};

const isExpired = (endDate) => {
  if (!endDate) return false;
  return new Date(endDate) < new Date();
};

const isDisabled = (coupon) => isExpired(coupon.end_date) || !coupon.is_active;

const copyCode = async (code) => {
  try {
    await navigator.clipboard.writeText(code);
    showToast(`Đã sao chép mã: ${code}`, 'success');
  } catch (error) {
    showToast('Không thể sao chép mã. Vui lòng thử lại!', 'danger');
  }
};

const openCouponDetail = (coupon) => {
  selectedCoupon.value = coupon;
};

const closeCouponDetail = () => {
  selectedCoupon.value = null;
};

const goLoginToSave = () => {
  router.push('/client/login?redirect=' + router.currentRoute.value.fullPath);
};

const saveCoupon = async (couponId) => {
  if (!isLoggedIn.value) {
    showToast('Vui lòng đăng nhập để lưu và sử dụng voucher khi thanh toán.', 'info');
    goLoginToSave();
    return;
  }
  try {
    const response = await api.post('/profile/coupons/save', { coupon_id: couponId });
    if (response.data.status === 'success' || response.data.status === 'info') {
      showToast(response.data.message, response.data.status);
      const coupon = coupons.value.find(c => c.id === couponId);
      if (coupon && !isSaved(couponId)) {
        savedCoupons.value.push(coupon);
      }
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Không thể lưu mã giảm giá!';
    showToast(msg, 'danger');
  }
};

onMounted(() => {
  fetchPublicCoupons();
  window.addEventListener('coupon-saved', fetchPublicCoupons);
});

onUnmounted(() => {
  window.removeEventListener('coupon-saved', fetchPublicCoupons);
});
</script>

<template>
  <main class="coupon-page">
    <section class="coupon-hero">
      <div class="container coupon-hero-inner">
        <div class="coupon-hero-copy">
          <span class="coupon-kicker">OCEAN SPORT DEALS</span>
          <h1>Săn Voucher</h1>
          <p>Thu thập ưu đãi mới nhất, sao chép mã trước và đăng nhập khi thanh toán để sử dụng voucher.</p>
          <div class="coupon-hero-actions">
            <a href="#coupon-list" class="btn-hero-primary">Khám phá mã</a>
            <router-link to="/product" class="btn-hero-outline">Mua sắm ngay</router-link>
          </div>
        </div>
        <div class="coupon-hero-panel">
          <div class="hero-ticket-icon" aria-hidden="true">
            <AppIcon name="voucher" width="34" height="34" :stroke-width="2.2" />
          </div>
          <div>
            <strong>{{ activeCouponCount }}</strong>
            <span>voucher đang khả dụng</span>
          </div>
        </div>
      </div>
    </section>

    <section id="coupon-list" class="coupon-content">
      <div class="container">
        <div class="coupon-toolbar">
          <div>
            <h2>Kho voucher của bạn</h2>
            <p>Bạn có thể xem và sao chép mã. Để lưu và dùng voucher khi thanh toán, bạn cần đăng nhập.</p>
          </div>
          <div class="coupon-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input v-model="searchQuery" type="text" placeholder="Tìm mã giảm giá..." />
          </div>
        </div>

        <div v-if="!isLoggedIn" class="coupon-login-banner">
          <div class="login-banner-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
          </div>
          <div>
            <strong>Đăng nhập để lưu và sử dụng voucher</strong>
            <p>Bạn vẫn có thể sao chép mã, nhưng voucher chỉ áp dụng khi thanh toán bằng tài khoản đã đăng nhập.</p>
          </div>
          <button type="button" @click="goLoginToSave">Đăng nhập</button>
        </div>

        <div v-if="isLoading" class="coupon-grid">
          <div v-for="n in 8" :key="n" class="coupon-skeleton"></div>
        </div>

        <div v-else-if="filteredCoupons.length === 0" class="coupon-empty">
          <div class="empty-icon">%</div>
          <h3>Không tìm thấy voucher nào</h3>
          <p>Thử tìm kiếm bằng mã khác hoặc quay lại sau.</p>
        </div>

        <template v-else>
          <div class="coupon-grid">
            <article v-for="coupon in visibleCoupons" :key="coupon.id" class="coupon-card"
              :class="{ 'coupon-card--disabled': isDisabled(coupon) }" @click="openCouponDetail(coupon)">
            <div class="coupon-cut coupon-cut--left"></div>
            <div class="coupon-cut coupon-cut--right"></div>

            <div class="coupon-card-head">
              <span class="coupon-type-pill" :class="`coupon-type-pill--${coupon.type}`">
                <AppIcon :name="getCouponIcon(coupon)" width="14" height="14" :stroke-width="2.2" />
                {{ getCouponLabel(coupon) }}
              </span>
              <span v-if="coupon.is_first_order" class="coupon-first-order-badge">Đơn đầu tiên</span>
              <span v-else-if="isDisabled(coupon)" class="coupon-status">Hết hiệu lực</span>
            </div>

            <button class="coupon-code-box" type="button" @click.stop="copyCode(coupon.code)">
              <span>{{ coupon.code }}</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
              </svg>
            </button>

            <div class="coupon-value">{{ formatValue(coupon) }}</div>
            <p class="coupon-condition">
              <span v-if="coupon.is_first_order" class="first-order-tag" style="color: #e63b6f; font-weight: 700; display: block; margin-bottom: 2px;">★ Dành cho đơn hàng đầu tiên</span>
              <template v-if="coupon.min_order_value">
                Đơn từ <strong>{{ formatCurrency(coupon.min_order_value) }}</strong>
              </template>
              <template v-else>Không yêu cầu đơn tối thiểu</template>
            </p>

            <div class="coupon-divider"></div>

            <div class="coupon-footer">
              <span class="coupon-expiry">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                HSD: {{ formatDate(coupon.end_date) }}
              </span>
              <div class="coupon-actions">
                <button class="btn-save-coupon" :disabled="isDisabled(coupon) || isSaved(coupon.id)"
                  @click.stop="saveCoupon(coupon.id)">
                  {{ isSaved(coupon.id) ? 'Đã lưu mã' : isLoggedIn ? 'Lưu mã' : 'Đăng nhập để lưu' }}
                </button>
                <button class="btn-copy-coupon" type="button" @click.stop="copyCode(coupon.code)">
                  Sao chép mã
                </button>
              </div>
            </div>
            </article>
          </div>
          <div v-if="hasMoreCoupons" class="coupon-load-more-wrap">
            <button class="btn-load-more-coupons" type="button" @click="loadMoreCoupons">
              Xem thêm voucher
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 5v14M5 12h14" />
              </svg>
            </button>
          </div>
        </template>
      </div>
    </section>

    <CouponDetailModal
      :coupon="selectedCoupon"
      @close="closeCouponDetail"
      @copy="copyCode"
      @view-all="closeCouponDetail"
    />
  </main>
</template>

<style scoped>
.coupon-page {
  min-height: 100vh;
  background: linear-gradient(180deg, #fff 0%, #fff7fb 42%, #f8fafc 100%);
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding-bottom: 72px;
}

.coupon-hero {
  position: relative;
  width: 100vw;
  margin-left: calc(-50vw + 50%);
  padding: 76px 0;
  color: #fff;
  overflow: hidden;
  background:
    radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.18), transparent 28%),
    radial-gradient(circle at 80% 0%, rgba(255, 255, 255, 0.16), transparent 32%),
    linear-gradient(135deg, #c80f55 0%, var(--primary) 52%, #ff6b9d 100%);
}

.coupon-hero::after {
  content: '';
  position: absolute;
  inset: auto -8% -45% -8%;
  height: 160px;
  background: rgba(255, 255, 255, 0.16);
  filter: blur(40px);
}

.coupon-hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
}

.coupon-hero-copy {
  max-width: 650px;
}

.coupon-kicker {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  margin-bottom: 16px;
  border: 1px solid rgba(255, 255, 255, 0.32);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 1.6px;
}

.coupon-hero h1 {
  margin: 0;
  font-size: clamp(2.2rem, 4vw, 4rem);
  font-weight: 900;
  letter-spacing: -1.5px;
}

.coupon-hero p {
  max-width: 520px;
  margin: 14px 0 0;
  color: rgba(255, 255, 255, 0.82);
  font-size: 1rem;
  line-height: 1.7;
}

.coupon-hero-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 28px;
}

.btn-hero-primary,
.btn-hero-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 24px;
  border-radius: 999px;
  font-weight: 800;
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.btn-hero-primary {
  color: var(--primary);
  background: #fff;
  box-shadow: 0 14px 28px rgba(0, 0, 0, 0.12);
}

.btn-hero-outline {
  color: #fff;
  border: 1.5px solid rgba(255, 255, 255, 0.45);
}

.btn-hero-primary:hover,
.btn-hero-outline:hover {
  transform: translateY(-2px);
}

.btn-hero-outline:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
}

.coupon-hero-panel {
  min-width: 260px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(12px);
}

.hero-ticket-icon {
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 18px;
  color: #fff;
  background: rgba(255, 255, 255, 0.16);
}

.coupon-hero-panel strong {
  display: block;
  font-size: 2.2rem;
  font-weight: 900;
  line-height: 1;
}

.coupon-hero-panel span {
  color: rgba(255, 255, 255, 0.78);
  font-size: 0.88rem;
}

.coupon-content {
  padding: 56px 0 0;
}

.coupon-toolbar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.coupon-toolbar h2 {
  margin: 0 0 6px;
  color: var(--text-main);
  font-size: 1.6rem;
  font-weight: 900;
}

.coupon-toolbar p {
  margin: 0;
  color: #64748b;
  font-size: 0.92rem;
}

.coupon-search {
  width: min(420px, 100%);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 16px;
  min-height: 46px;
  border: 1px solid #ffe0ea;
  border-radius: 999px;
  background: #fff;
  color: var(--primary);
  box-shadow: 0 10px 26px rgba(230, 59, 111, 0.08);
}

.coupon-search input {
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  font-family: inherit;
  font-size: 0.94rem;
}

.coupon-login-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  margin-bottom: 28px;
  border: 1px solid #ffe0ea;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(230, 59, 111, 0.06);
}

.login-banner-icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  color: var(--primary);
  background: #fff0f5;
  flex-shrink: 0;
}

.coupon-login-banner strong {
  color: #111827;
  font-size: 0.95rem;
}

.coupon-login-banner p {
  margin: 3px 0 0;
  color: #64748b;
  font-size: 0.82rem;
}

.coupon-login-banner button {
  margin-left: auto;
  min-height: 40px;
  padding: 0 20px;
  border: 0;
  border-radius: 999px;
  background: var(--primary);
  color: #fff;
  font-family: inherit;
  font-weight: 800;
}

.coupon-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 24px;
}

.coupon-card {
  position: relative;
  min-height: 350px;
  padding: 22px 18px 18px;
  border: 1px solid #ffe0ea;
  border-radius: 22px;
  background:
    radial-gradient(circle at top right, rgba(230, 59, 111, 0.11), transparent 34%),
    #fff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.coupon-card:hover:not(.coupon-card--disabled) {
  transform: translateY(-6px);
  border-color: rgba(230, 59, 111, 0.35);
  box-shadow: 0 22px 46px rgba(230, 59, 111, 0.12);
}

.coupon-card--disabled {
  opacity: 0.58;
  filter: grayscale(0.35);
}

.coupon-cut {
  position: absolute;
  top: 58%;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff7fb;
  border: 1px solid #ffe0ea;
  transform: translateY(-50%);
}

.coupon-cut--left {
  left: -10px;
}

.coupon-cut--right {
  right: -10px;
}

.coupon-card-head {
  min-height: 32px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 16px;
}

.coupon-type-pill,
.coupon-status,
.coupon-first-order-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: 999px;
  color: var(--primary);
  background: #fff0f5;
  border: 1px solid #ffd7e3;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.coupon-first-order-badge {
  color: #c80f55;
  background: #fff0f3;
  border-color: #ffb3c6;
  white-space: nowrap;
}

.coupon-status {
  color: #94a3b8;
  background: #f8fafc;
  border-color: #e2e8f0;
  white-space: nowrap;
}

.coupon-code-box {
  width: 100%;
  min-height: 46px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0 14px;
  border: 1.5px dashed var(--primary);
  border-radius: 13px;
  background: #fff7fa;
  color: var(--primary);
  font-family: inherit;
  cursor: pointer;
}

.coupon-code-box span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 1rem;
  font-weight: 900;
  letter-spacing: 0.5px;
}

.coupon-value {
  margin-top: 20px;
  color: #111827;
  font-size: 1.65rem;
  font-weight: 900;
  letter-spacing: -0.8px;
  line-height: 1.2;
}

.coupon-condition {
  min-height: 24px;
  margin: 8px 0 16px;
  color: #64748b;
  font-size: 0.85rem;
}

.coupon-condition strong {
  color: #111827;
}

.coupon-divider {
  height: 1px;
  margin: 18px 0;
  border-top: 1px dashed #ffd7e3;
}

.coupon-expiry {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #64748b;
  font-size: 0.78rem;
}

.coupon-actions {
  display: grid;
  gap: 8px;
  margin-top: 16px;
}

.btn-save-coupon,
.btn-copy-coupon {
  min-height: 40px;
  border-radius: 11px;
  font-family: inherit;
  font-weight: 800;
  font-size: 0.85rem;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.btn-save-coupon {
  border: 0;
  color: #fff;
  background: linear-gradient(135deg, var(--primary), #ff6b9d);
  box-shadow: 0 10px 20px rgba(230, 59, 111, 0.18);
}

.btn-copy-coupon {
  border: 1.5px solid var(--primary);
  color: var(--primary);
  background: #fff;
}

.btn-save-coupon:hover:not(:disabled),
.btn-copy-coupon:hover {
  transform: translateY(-2px);
}

.btn-copy-coupon:hover {
  color: #fff;
  background: var(--primary);
}

.btn-save-coupon:disabled {
  background: #e2e8f0;
  color: #94a3b8;
  box-shadow: none;
  cursor: not-allowed;
}

.coupon-load-more-wrap {
  display: flex;
  justify-content: center;
  margin-top: 38px;
}

.btn-load-more-coupons {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 30px;
  border: 1.5px solid var(--primary);
  border-radius: 999px;
  background: #fff;
  color: var(--primary);
  font-family: inherit;
  font-weight: 900;
  box-shadow: 0 12px 26px rgba(230, 59, 111, 0.1);
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.btn-load-more-coupons:hover {
  transform: translateY(-2px);
  background: var(--primary);
  color: #fff;
  box-shadow: 0 16px 34px rgba(230, 59, 111, 0.18);
}

.coupon-skeleton {
  min-height: 320px;
  border-radius: 22px;
  background: linear-gradient(90deg, #fff0f5, #fff, #fff0f5);
  background-size: 200% 100%;
  animation: couponSkeleton 1.4s ease infinite;
}

@keyframes couponSkeleton {
  from {
    background-position: 200% 0;
  }

  to {
    background-position: -200% 0;
  }
}

.coupon-empty {
  padding: 72px 20px;
  text-align: center;
  color: #64748b;
}

.empty-icon {
  width: 58px;
  height: 58px;
  margin: 0 auto 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 18px;
  color: var(--primary);
  background: #fff0f5;
  font-size: 1.8rem;
  font-weight: 900;
}

.coupon-empty h3 {
  color: #111827;
  font-weight: 900;
}

@media (max-width: 1199px) {
  .coupon-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 991px) {

  .coupon-hero-inner,
  .coupon-toolbar,
  .coupon-login-banner {
    align-items: stretch;
    flex-direction: column;
  }

  .coupon-hero-panel,
  .coupon-search {
    width: 100%;
  }

  .coupon-login-banner button {
    width: 100%;
    margin-left: 0;
  }

  .coupon-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 576px) {
  .coupon-hero {
    padding: 52px 0;
  }

  .coupon-grid {
    grid-template-columns: 1fr;
  }
}
</style>
