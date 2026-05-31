<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import {
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getReturnRefundStatusLabel,
  getPaymentStatusLabel,
  getOrderStatusLabel,
} from '@/utils/orderStatus';

const route = useRoute();
const store = useReturnRequestStore();
const { currentRequest, detailLoading } = storeToRefs(store);
const APP_URL = import.meta.env.VITE_BASE_URL || window.location.origin;

const detail = computed(() => currentRequest.value);

const formatDate = (value) => {
  if (!value) return '—';
  return new Date(value).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatPrice = (value) => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
}).format(Number(value || 0));

const imageUrl = (path) => `${APP_URL}/storage/${path}`;

onMounted(() => {
  store.fetchMyReturnRequestDetail(route.params.id);
});
</script>

<template>
  <div class="return-request-detail-page">
    <div v-if="detailLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải chi tiết yêu cầu hoàn hàng...</p>
    </div>

    <div v-else-if="detail" class="detail-grid">
      <section class="detail-card">
        <div class="detail-card__head">
          <div>
            <p class="eyebrow">Yêu cầu hoàn hàng</p>
            <h2>#{{ detail.order?.order_code || detail.order_id }}</h2>
          </div>
          <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
            {{ getReturnRequestStatusLabel(detail.status) }}
          </span>
        </div>

        <div class="detail-block">
          <h3>Lý do</h3>
          <p>{{ detail.reason }}</p>
        </div>

        <div class="detail-block">
          <h3>Mô tả chi tiết</h3>
          <p>{{ detail.description || 'Không có mô tả bổ sung.' }}</p>
        </div>

        <div v-if="detail.images?.length" class="detail-block">
          <h3>Ảnh minh chứng</h3>
          <div class="evidence-grid">
            <img
              v-for="image in detail.images"
              :key="image"
              :src="imageUrl(image)"
              alt="Ảnh minh chứng hoàn hàng"
            />
          </div>
        </div>

        <div class="detail-block" v-if="detail.admin_note">
          <h3>Ghi chú từ admin</h3>
          <p>{{ detail.admin_note }}</p>
        </div>
      </section>

      <aside class="detail-card">
        <h3>Thông tin xử lý</h3>
        <div class="info-row">
          <span>Gửi lúc</span>
          <strong>{{ formatDate(detail.requested_at || detail.created_at) }}</strong>
        </div>
        <div class="info-row">
          <span>Hoàn tiền</span>
          <strong>{{ getReturnRefundStatusLabel(detail.refund_status) }}</strong>
        </div>
        <div class="info-row" v-if="Number(detail.refund_amount || 0) > 0">
          <span>Số tiền hoàn</span>
          <strong>{{ formatPrice(detail.refund_amount) }}</strong>
        </div>
        <div class="info-row" v-if="detail.refund_method">
          <span>Phương thức</span>
          <strong>{{ detail.refund_method }}</strong>
        </div>
        <div class="info-row">
          <span>Đơn hàng</span>
          <strong>{{ getOrderStatusLabel(detail.order?.fulfillment_status) }}</strong>
        </div>
        <div class="info-row">
          <span>Thanh toán</span>
          <strong>{{ getPaymentStatusLabel(detail.order?.payment_status) }}</strong>
        </div>

        <router-link
          v-if="detail.order"
          :to="{ name: 'profile-order-detail', params: { id: detail.order.order_id } }"
          class="back-link"
        >
          Xem lại đơn hàng
        </router-link>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.return-request-detail-page { min-height: 420px; }

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.9fr);
  gap: 20px;
}

.detail-card {
  background: #fff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.detail-card__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 20px;
}

.detail-card__head h2,
.detail-card h3 {
  margin: 0;
  color: #0f172a;
}

.eyebrow {
  margin: 0 0 6px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
  font-size: 0.75rem;
  font-weight: 700;
}

.detail-block + .detail-block {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #f1f5f9;
}

.detail-block p {
  margin: 10px 0 0;
  color: #475569;
  line-height: 1.6;
}

.evidence-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.evidence-grid img {
  width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.info-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f8fafc;
  color: #475569;
}

.back-link {
  margin-top: 20px;
  display: inline-flex;
  color: #E63B6F;
  font-weight: 700;
  text-decoration: none;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
}

.status-badge.status-info { color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border: 1px solid #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border: 1px solid #fecaca; }

.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.spinner {
  width: 38px;
  height: 38px;
  border: 3px solid #f1f5f9;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
  margin: 0 auto 16px;
}

@media (max-width: 900px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
