<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
const router = useRouter();
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

const goBack = () => {
  router.push({ name: 'profile-return-requests' });
};
</script>

<template>
  <div class="profile-order-detail-page animate-in">
    <div class="page-header">
      <div class="header-left">
        <button class="btn-back" @click="goBack">
          <span>&larr;</span> Quay lại
        </button>
        <h2 class="page-title">Chi tiết yêu cầu hoàn hàng</h2>
      </div>
      <div v-if="detail" class="header-right">
        <span class="order-code">#{{ detail.order?.order_code || detail.order_id }}</span>
      </div>
    </div>
    <div v-if="detailLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải chi tiết yêu cầu hoàn hàng...</p>
    </div>

    <div v-else-if="detail" class="detail-grid">
      <section class="detail-card">
        <div class="card-header">
          <h3>Nội dung yêu cầu</h3>
          <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
            {{ getReturnRequestStatusLabel(detail.status) }}
          </span>
        </div>
        <div class="card-body">
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
            <p class="admin-note">{{ detail.admin_note }}</p>
          </div>
        </div>
      </section>

      <aside class="detail-card summary-card">
        <div class="card-header">
          <h3>Thông tin xử lý</h3>
        </div>
        <div class="card-body">
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
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.profile-order-detail-page {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 24px;
  min-height: 500px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-back {
  background: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
}
.btn-back:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

.page-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.order-code {
  font-weight: 800;
  color: #E63B6F;
  font-size: 1rem;
  background: rgba(230, 59, 111, 0.1);
  padding: 6px 18px;
  border-radius: 20px;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.9fr);
  gap: 20px;
}

.detail-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e293b;
}

.card-body {
  padding: 20px;
}

.detail-block + .detail-block {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #f1f5f9;
}

.detail-block h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}

.detail-block p {
  margin: 10px 0 0;
  color: #475569;
  line-height: 1.6;
}

.admin-note {
  color: #dc2626 !important;
  font-weight: 500;
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
  margin-bottom: 12px;
  color: #475569;
}
.info-row:last-of-type {
  margin-bottom: 0;
}

.back-link {
  margin-top: 20px;
  display: inline-flex;
  color: #E63B6F;
  font-weight: 700;
  text-decoration: none;
  background: rgba(230, 59, 111, 0.1);
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.2s;
}

.back-link:hover {
  background: #E63B6F;
  color: white;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 0.85rem;
  font-weight: 700;
  background: white;
  border: 1px solid #e2e8f0;
}

.status-badge.status-info { color: #475569; background: #f8fafc; border-color: #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.loading-state {
  text-align: center;
  padding: 60px 0;
  color: #64748b;
}

.spinner {
  width: 40px; height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}

.animate-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
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
