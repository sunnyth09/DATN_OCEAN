<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { orderService } from '@/services/orderService';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';
import { getOrderStatusDescription, getOrderStatusTone } from '@/utils/orderStatus';
import { getStorageUrl } from '@/utils/url';

const route = useRoute();

const form = ref({ order_code: '', phone: '' });
const tracking = ref(null);
const loading = ref(false);
const errorMessage = ref('');

const hasResult = computed(() => Boolean(tracking.value));
const token = computed(() => route.params.token || '');

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return '';
  return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
};

const getStatusText = (status) => getOrderStatusDescription(status || 'pending');
const getStatusBadgeClass = (status) => {
  const tone = getOrderStatusTone(status || 'pending');
  if (tone === 'success') return 'badge-success';
  if (tone === 'danger') return 'badge-danger';
  if (tone === 'warning') return 'badge-warning';
  if (tone === 'info') return 'badge-info';
  if (tone === 'primary') return 'badge-primary';
  return 'badge-secondary';
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '';
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(value);
};

const paymentLabels = {
  unpaid: 'Chưa thanh toán',
  paid: 'Đã thanh toán',
  failed: 'Thất bại',
  refunded: 'Hoàn tiền',
  partially_refunded: 'Hoàn một phần',
};

const paymentMethodLabels = {
  cod: 'Thanh toán khi nhận hàng (COD)',
  vnpay: 'VNPay',
  momo: 'Ví MoMo',
  bank_transfer: 'Chuyển khoản ngân hàng',
};

const getPaymentBadgeClass = (status) => {
  const map = { unpaid: 'badge-warning', paid: 'badge-success', failed: 'badge-danger', refunded: 'badge-secondary' };
  return map[status] || 'badge-secondary';
};

const setError = (error) => {
  errorMessage.value = error.response?.data?.message || 'Không thể tra cứu đơn hàng. Vui lòng thử lại sau.';
};

const loadByToken = async () => {
  if (!token.value) return;

  loading.value = true;
  errorMessage.value = '';
  tracking.value = null;
  try {
    const res = await orderService.trackOrderByToken(token.value);
    tracking.value = res.data.data;
  } catch (error) {
    setError(error);
  } finally {
    loading.value = false;
  }
};

const submitGuestTracking = async () => {
  errorMessage.value = '';
  tracking.value = null;

  if (!form.value.order_code.trim() || !form.value.phone.trim()) {
    errorMessage.value = 'Vui lòng nhập mã đơn hàng và số điện thoại.';
    return;
  }

  loading.value = true;
  try {
    const res = await orderService.trackGuestOrder({
      order_code: form.value.order_code.trim(),
      phone: form.value.phone.trim(),
    });
    tracking.value = res.data.data;
  } catch (error) {
    setError(error);
  } finally {
    loading.value = false;
  }
};

onMounted(loadByToken);
</script>

<template>
  <main class="guest-tracking-page container">
    <section class="tracking-hero">
      <h1>Tra cứu vận đơn</h1>
      <p>Theo dõi trạng thái giao hàng bằng link tracking hoặc mã đơn hàng và số điện thoại đặt hàng.</p>
    </section>

    <section class="tracking-card">
      <form v-if="!token" class="tracking-form" @submit.prevent="submitGuestTracking">
        <div class="form-group">
          <label for="order_code">Mã đơn hàng</label>
          <input id="order_code" v-model="form.order_code" type="text" placeholder="VD: QS123456" autocomplete="off" />
        </div>
        <div class="form-group">
          <label for="phone">Số điện thoại</label>
          <input id="phone" v-model="form.phone" type="tel" placeholder="VD: 0901234567" autocomplete="tel" />
        </div>
        <button class="btn-track" type="submit" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          <span v-else>Tra cứu đơn hàng</span>
        </button>
      </form>

      <div v-if="loading && token" class="loading-state">
        <div class="spinner large"></div>
        <p>Đang tải thông tin vận đơn...</p>
      </div>

      <p v-if="errorMessage" class="tracking-error">{{ errorMessage }}</p>

      <div v-if="hasResult" class="tracking-result">
        <div class="result-header">
          <div>
            <span class="result-label">Mã đơn hàng</span>
            <h2>#{{ tracking.order_code }}</h2>
          </div>
          <span class="status-pill" :class="getStatusBadgeClass(tracking.fulfillment_status)">
            {{ getStatusText(tracking.fulfillment_status) }}
          </span>
        </div>

        <div class="result-grid">
          <div class="info-box">
            <span>Người nhận</span>
            <strong>{{ tracking.receiver_name || 'Đã ẩn' }}</strong>
          </div>
          <div class="info-box">
            <span>Số điện thoại</span>
            <strong>{{ tracking.receiver_phone || 'Đã ẩn' }}</strong>
          </div>
          <div class="info-box" v-if="tracking.ghn_order_code">
            <span>Mã GHN</span>
            <strong>{{ tracking.ghn_order_code }}</strong>
          </div>
        </div>

        <div class="result-grid mt-4">
          <div class="info-box">
              <span>Trạng thái thanh toán</span>
              <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                <span class="status-pill" style="padding: 4px 10px; font-size: 0.85rem;"
                  :class="getPaymentBadgeClass(tracking.payment_status)">
                  {{ paymentLabels[tracking.payment_status] || tracking.payment_status }}
                </span>
              </div>
            </div>
            <div class="info-box">
              <span>Phương thức thanh toán</span>
              <strong>{{ paymentMethodLabels[tracking.payment_method] || tracking.payment_method }}</strong>
            </div>
            <div class="info-box">
              <span>Tổng tiền</span>
              <strong style="color: #d4145a; font-size: 1.1rem;">{{ formatCurrency(tracking.grand_total) }}</strong>
            </div>
          </div>

          <div class="timeline-section" v-if="tracking.items?.length">
            <h3>Sản phẩm đã đặt</h3>
            <div class="tracking-items-list">
              <div v-for="(item, index) in tracking.items" :key="index" class="tracking-item">
                <img :src="item.image ? getStorageUrl(item.image) : 'https://placehold.co/80x80?text=No+Img'"
                  :alt="item.name" class="tracking-item-img" />
                <div class="tracking-item-info">
                  <div class="tracking-item-name">{{ item.name }}</div>
                  <div class="tracking-item-variant">
                    <span v-if="item.color">{{ item.color }}</span>
                    <span v-if="item.color && item.size"> - </span>
                    <span v-if="item.size">{{ item.size }}</span>
                  </div>
                </div>
                <div class="tracking-item-price-qty">
                  <div class="tracking-item-price">{{ formatCurrency(item.price) }}</div>
                  <div class="tracking-item-qty">x{{ item.quantity }}</div>
                </div>
              </div>
            </div>
          </div>

          <a v-if="tracking.ghn_tracking_url" class="ghn-link mt-4" :href="tracking.ghn_tracking_url" target="_blank"
            rel="noopener">
            Xem trực tiếp trên GHN
          </a>

        <div class="timeline-section" v-if="tracking.timeline?.length">
          <h3>Lịch trình đơn hàng</h3>
          <OrderStatusTimeline :histories="tracking.timeline" :show-ghn-meta="true" :get-status-label="getStatusText"
            :get-status-badge-class="getStatusBadgeClass" :format-date="formatDate" />
        </div>
        <p v-else class="empty-timeline">Chưa có lịch sử vận chuyển cho đơn hàng này.</p>
      </div>
    </section>
  </main>
</template>

<style scoped>
.guest-tracking-page {
  /* Căn giữa & max-width do Bootstrap .container đảm nhiệm */
  padding-top: 48px;
  padding-bottom: 72px;
}

.tracking-hero {
  text-align: center;
  margin-bottom: 28px;
}

.tracking-hero h1 {
  margin: 14px 0 8px;
  font-size: clamp(2rem, 4vw, 3.25rem);
  font-weight: 900;
  color: var(--text-main);
}

.tracking-hero p {
  max-width: 640px;
  margin: 0 auto;
  color: #64748b;
}

.tracking-card {
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
  padding: 28px;
}

.tracking-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr)) auto;
  gap: 16px;
  align-items: end;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #334155;
  font-weight: 800;
}

.form-group input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  padding: 13px 14px;
  font-size: 1rem;
  outline: none;
}

.form-group input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}

.btn-track,
.ghn-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  border: none;
  border-radius: 14px;
  padding: 0 20px;
  background: linear-gradient(135deg, var(--primary), #c22b56);
  color: #fff;
  font-weight: 900;
  text-decoration: none;
  cursor: pointer;
}

.btn-track:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.tracking-error {
  margin: 18px 0 0;
  padding: 14px 16px;
  border-radius: 14px;
  background: #fef2f2;
  color: #b91c1c;
  font-weight: 700;
}

.loading-state {
  text-align: center;
  color: #64748b;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.5);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner.large {
  width: 34px;
  height: 34px;
  border-color: #bfdbfe;
  border-top-color: #2563eb;
  margin: 0 auto 12px;
}

.tracking-result {
  margin-top: 28px;
}

.result-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 18px;
  border-bottom: 1px solid #e2e8f0;
}

.result-label,
.info-box span {
  display: block;
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.result-header h2 {
  margin: 4px 0 0;
  color: var(--text-main);
}

.status-pill {
  padding: 8px 14px;
  border-radius: 999px;
  font-weight: 900;
  white-space: nowrap;
}

.result-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin: 20px 0;
}

.info-box {
  padding: 16px;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.info-box strong {
  display: block;
  margin-top: 6px;
  color: var(--text-main);
}

.timeline-section {
  margin-top: 28px;
}

.timeline-section h3 {
  margin-bottom: 18px;
  color: var(--text-main);
}

.empty-timeline {
  color: #64748b;
  font-style: italic;
}

.badge-warning {
  background: #fff7ed;
  color: #ea580c;
}

.badge-primary {
  background: #eff6ff;
  color: #2563eb;
}

.badge-info {
  background: #ecfeff;
  color: #0891b2;
}

.badge-success {
  background: #ecfdf5;
  color: #059669;
}

.badge-danger {
  background: #fef2f2;
  color: #dc2626;
}

.badge-secondary {
  background: #f1f5f9;
  color: #64748b;
}

.mt-4 {
  margin-top: 1.5rem !important;
}

.tracking-items-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 16px;
}

.tracking-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px dashed #e2e8f0;
}

.tracking-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.tracking-item-img {
  width: 64px;
  height: 64px;
  object-fit: contain;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.tracking-item-info {
  flex: 1;
  min-width: 0;
}

.tracking-item-name {
  font-weight: 700;
  color: var(--text-main);
  font-size: 0.95rem;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tracking-item-variant {
  font-size: 0.85rem;
  color: #64748b;
}

.tracking-item-price-qty {
  text-align: right;
}

.tracking-item-price {
  font-weight: 800;
  color: var(--primary);
  font-size: 0.95rem;
}

.tracking-item-qty {
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 4px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 768px) {

  .tracking-form,
  .result-grid {
    grid-template-columns: 1fr;
  }

  .result-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .tracking-card {
    padding: 20px;
  }
}
</style>
