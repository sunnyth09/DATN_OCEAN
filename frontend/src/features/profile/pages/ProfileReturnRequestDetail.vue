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
  getRefundMethodLabel,
  getReturnShippingMethodLabel,
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
        <span class="order-code">{{ detail.return_code || `#${detail.order?.order_code || detail.order_id}` }}</span>
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
            <div class="description-content" v-html="detail.description || 'Không có mô tả bổ sung.'"></div>
          </div>

          <div v-if="detail.images?.length || detail.videos?.length" class="detail-block">
            <h3>Minh chứng</h3>
            <div v-if="detail.images?.length" class="evidence-grid">
              <img v-for="image in detail.images" :key="image" :src="imageUrl(image)" alt="Ảnh minh chứng hoàn hàng" />
            </div>
            <div v-if="detail.videos?.length" class="evidence-grid evidence-grid--video">
              <video v-for="video in detail.videos" :key="video" :src="imageUrl(video)" controls />
            </div>
          </div>

          <div class="detail-block">
            <h3>Cách gửi hàng hoàn</h3>
            <p><strong>{{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</strong></p>
            <template v-if="detail.return_shipping_method === 'pickup_original_address'">
              <p>Đơn vị vận chuyển sẽ đến lấy hàng tại địa chỉ:</p>
              <p>{{ detail.return_pickup_name || detail.order?.recipient_name }} · {{ detail.return_pickup_phone || detail.order?.recipient_phone }}</p>
              <p>{{ detail.return_pickup_address || detail.order?.shipping_address }}</p>
            </template>
            <p v-else-if="detail.return_shipping_method === 'dropoff_post_office'">
              Sau khi yêu cầu được duyệt, bạn vui lòng tự mang hàng đến bưu cục/điểm gửi theo hướng dẫn của shop.
            </p>
          </div>

          <div v-if="detail.refund_method === 'vnpay' && detail.refund_status === 'pending'" class="detail-block refund-pending-note">
            <h3>Hoàn tiền VNPay</h3>
            <p>Hoàn tiền VNPay đang chờ xử lý/đối soát. Shop sẽ cập nhật sau khi hoàn tất.</p>
          </div>

          <div v-if="detail.items?.length" class="detail-block">
            <h3>Sản phẩm hoàn hàng</h3>
            <div class="return-items-table">
              <div class="return-items-head">
                <span>Sản phẩm</span>
                <span>Yêu cầu</span>
                <span>Kho nhận</span>
                <span>QC đạt</span>
                <span>QC lỗi</span>
                <span>Tiền hoàn</span>
              </div>
              <div v-for="item in detail.items" :key="item.id" class="return-items-row">
                <span class="return-product">
                  <strong>{{ item.order_item?.product_name || item.product?.name || 'Sản phẩm' }}</strong>
                  <small>{{ item.order_item?.variant_name || item.variant?.variant_name || 'Không phân loại' }}</small>
                </span>
                <span>{{ item.requested_quantity }}</span>
                <span>{{ item.received_quantity }}</span>
                <span>{{ item.qc_pass_quantity }}</span>
                <span>{{ item.qc_fail_quantity }}</span>
                <span>{{ formatPrice(item.refundable_amount) }}</span>
              </div>
            </div>
          </div>

          <div v-if="detail.return_tracking_code || detail.return_carrier" class="detail-block">
            <h3>Thông tin gửi hàng hoàn</h3>
            <p v-if="detail.return_carrier">Đơn vị vận chuyển: <strong>{{ detail.return_carrier }}</strong></p>
            <p v-if="detail.return_tracking_code">Mã vận đơn hoàn: <strong>{{ detail.return_tracking_code }}</strong></p>
          </div>

          <div class="detail-block" v-if="detail.admin_note || detail.reject_reason || detail.inspection_note">
            <h3>Ghi chú xử lý</h3>
            <p v-if="detail.admin_note" class="admin-note">{{ detail.admin_note }}</p>
            <p v-if="detail.reject_reason" class="admin-note">Lý do từ chối: {{ detail.reject_reason }}</p>
            <p v-if="detail.inspection_note">QC: {{ detail.inspection_note }}</p>
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
          <strong>{{ getRefundMethodLabel(detail.refund_method) }}</strong>
        </div>
        <div class="info-row" v-if="detail.approved_at">
          <span>Duyệt lúc</span>
          <strong>{{ formatDate(detail.approved_at) }}</strong>
        </div>
        <div class="info-row" v-if="detail.warehouse_received_at || detail.received_at">
          <span>Kho nhận lúc</span>
          <strong>{{ formatDate(detail.warehouse_received_at || detail.received_at) }}</strong>
        </div>
        <div class="info-row" v-if="detail.inspected_at">
          <span>QC lúc</span>
          <strong>{{ formatDate(detail.inspected_at) }}</strong>
        </div>
        <div class="info-row" v-if="detail.completed_at || detail.refunded_at">
          <span>Hoàn tất lúc</span>
          <strong>{{ formatDate(detail.completed_at || detail.refunded_at) }}</strong>
        </div>
        <div class="info-row">
          <span>Đơn hàng</span>
          <strong>{{ getOrderStatusLabel(detail.order?.fulfillment_status) }}</strong>
        </div>
        <div class="info-row">
          <span>Thanh toán</span>
          <strong>{{ getPaymentStatusLabel(detail.order?.payment_status) }}</strong>
        </div>

          <div v-if="detail.refund_transactions?.length" class="refund-history">
            <h4>Lịch sử hoàn tiền</h4>
            <div v-for="tx in detail.refund_transactions" :key="tx.id" class="refund-history-row">
              <span>{{ getRefundMethodLabel(tx.method) }}</span>
              <strong>{{ formatPrice(tx.amount) }}</strong>
              <em>{{ tx.status }}</em>
            </div>
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
  background: var(--card-bg);
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
  color: var(--text-main);
  border-color: #cbd5e1;
}

.page-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main);
  margin: 0;
}

.order-code {
  font-weight: 800;
  color: var(--primary);
  font-size: 1rem;
  background: rgba(230, 59, 111, 0.1);
  padding: 6px 18px;
  border-radius: 20px;
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.9fr);
  gap: 20px;
  align-items: start;
}

.detail-card {
  background: var(--card-bg);
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
  color: var(--text-main);
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
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
}

.detail-block p, .description-content {
  margin: 8px 0 0;
  color: #334155;
  line-height: 1.6;
  font-size: 0.95rem;
}

.admin-note {
  color: #dc2626 !important;
  font-weight: 500;
}

.evidence-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.evidence-grid img,
.evidence-grid video {
  width: 100%;
  height: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.return-items-table {
  margin-top: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.return-items-head,
.return-items-row {
  display: grid;
  grid-template-columns: minmax(160px, 1.4fr) repeat(5, minmax(70px, 0.7fr));
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  font-size: 0.86rem;
}

.return-items-head {
  background: #f8fafc;
  font-weight: 800;
  color: #334155;
}

.return-items-row {
  border-top: 1px solid #f1f5f9;
  color: #475569;
}

.return-product {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.return-product strong {
  color: var(--text-main);
}

.return-product small {
  color: #64748b;
}

.refund-history {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #f1f5f9;
}

.refund-history h4 {
  margin: 0 0 10px;
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--text-main);
}

.refund-history-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: center;
  padding: 8px 0;
  color: #475569;
  font-size: 0.86rem;
}

.refund-history-row + .refund-history-row {
  border-top: 1px dashed #e2e8f0;
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  align-items: center;
}

.info-row span {
  color: #64748b;
  font-size: 0.9rem;
}

.info-row strong {
  color: #1e293b;
  font-size: 0.95rem;
  font-weight: 600;
}

.info-row:last-of-type {
  margin-bottom: 0;
}

.back-link {
  margin-top: 20px;
  display: inline-flex;
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
  background: rgba(230, 59, 111, 0.1);
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.2s;
}

.back-link:hover {
  background: var(--primary);
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
  background: var(--card-bg);
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
  border-top-color: var(--primary);
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
