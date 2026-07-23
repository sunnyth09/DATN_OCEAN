<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import { useToast } from '@/composables/useToast';
import {
  RETURN_REQUEST_REFUND_METHOD_OPTIONS,
  getOrderStatusLabel,
  getPaymentStatusLabel,
  getReturnRefundStatusLabel,
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
} from '@/utils/orderStatus';

const route = useRoute();
const router = useRouter();
const store = useReturnRequestStore();
const { showToast } = useToast();
const { currentRequest, detailLoading } = storeToRefs(store);
const APP_URL = import.meta.env.VITE_BASE_URL || window.location.origin;

const actionLoading = ref(false);
const adminNote = ref('');
const refundForm = reactive({
  refund_amount: '',
  refund_method: 'bank_transfer',
  admin_note: '',
});

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

const refreshDetail = async () => {
  await store.fetchAdminReturnRequestDetail(route.params.id);

  if (detail.value) {
    adminNote.value = detail.value.admin_note || '';
    refundForm.refund_amount = detail.value.refund_amount || detail.value.order?.grand_total || '';
    refundForm.refund_method = detail.value.refund_method || 'bank_transfer';
    refundForm.admin_note = detail.value.admin_note || '';
  }
};

const runAction = async (handler) => {
  actionLoading.value = true;
  try {
    const response = await handler();
    if (response.status === 'success') {
      showToast(response.message || 'Đã cập nhật thành công.', 'success');
      await refreshDetail();
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể cập nhật yêu cầu hoàn hàng.', 'error');
  } finally {
    actionLoading.value = false;
  }
};

const approve = () => runAction(() => store.approveReturnRequest(route.params.id, {
  admin_note: adminNote.value || null,
}));

const reject = () => {
  if (!adminNote.value.trim()) {
    showToast('Vui lòng nhập lý do từ chối.', 'error');
    return;
  }

  return runAction(() => store.rejectReturnRequest(route.params.id, {
    admin_note: adminNote.value.trim(),
  }));
};

const markReceived = () => runAction(() => store.markReturnReceived(route.params.id, {
  admin_note: adminNote.value || null,
}));

const refund = () => {
  if (refundForm.refund_amount === '' || Number(refundForm.refund_amount) < 0) {
    showToast('Vui lòng nhập số tiền hoàn hợp lệ.', 'error');
    return;
  }

  return runAction(() => store.refundReturnRequest(route.params.id, {
    refund_amount: Number(refundForm.refund_amount),
    refund_method: refundForm.refund_method,
    admin_note: refundForm.admin_note || null,
  }));
};

onMounted(() => {
  refreshDetail();
});
</script>

<template>
  <div class="admin-return-detail-page">
    <div class="detail-header">
      <div class="header-left">
        <button class="btn-back" @click="router.push({ name: 'admin-return-requests' })">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Quay lại danh sách
        </button>
      </div>
    </div>

    <div v-if="detailLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải chi tiết yêu cầu hoàn hàng...</p>
    </div>

    <div v-else-if="detail" class="detail-grid">
      <section class="detail-card">
        <div class="detail-head">
          <div class="timeline-title-group">
            <div class="timeline-title-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div>
              <p class="eyebrow">Yêu cầu hoàn hàng</p>
              <h1 class="page-title">Mã đơn <span class="order-code">#{{ detail.order?.order_code || detail.order_id }}</span></h1>
            </div>
          </div>
          <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
            {{ getReturnRequestStatusLabel(detail.status) }}
          </span>
        </div>

        <div class="info-list">
          <div class="info-row"><span>Khách hàng</span><strong>{{ detail.user?.full_name || detail.order?.recipient_name }}</strong></div>
          <div class="info-row"><span>Email / SĐT</span><strong>{{ detail.user?.email || detail.order?.recipient_phone || '—' }}</strong></div>
          <div class="info-row"><span>Lý do</span><strong>{{ detail.reason }}</strong></div>
          <div class="info-row"><span>Đơn hàng</span><strong>{{ getOrderStatusLabel(detail.order?.fulfillment_status) }}</strong></div>
          <div class="info-row"><span>Thanh toán</span><strong>{{ getPaymentStatusLabel(detail.order?.payment_status) }}</strong></div>
          <div class="info-row"><span>Hoàn tiền</span><strong>{{ getReturnRefundStatusLabel(detail.refund_status) }}</strong></div>
          <div class="info-row"><span>Gửi lúc</span><strong>{{ formatDate(detail.requested_at || detail.created_at) }}</strong></div>
        </div>

        <div class="detail-block">
          <h3>Mô tả</h3>
          <p>{{ detail.description || 'Không có mô tả bổ sung.' }}</p>
        </div>

        <div v-if="detail.images?.length" class="detail-block">
          <h3>Ảnh minh chứng</h3>
          <div class="evidence-grid">
            <img v-for="image in detail.images" :key="image" :src="imageUrl(image)" alt="Ảnh minh chứng" />
          </div>
        </div>

        <div class="detail-block" v-if="detail.order">
          <router-link :to="{ name: 'admin-order-detail', params: { id: detail.order.order_id } }" class="jump-link">
            Mở chi tiết đơn hàng
          </router-link>
        </div>
      </section>

      <aside class="detail-card">
        <h3>Thao tác xử lý</h3>

        <label class="field-label">Ghi chú admin</label>
        <textarea v-model="adminNote" class="field-textarea" placeholder="Nhập ghi chú xử lý..."></textarea>

        <div v-if="detail.status === 'pending'" class="action-group">
          <button class="action-btn action-btn--approve" :disabled="actionLoading" @click="approve">Duyệt yêu cầu</button>
          <button class="action-btn action-btn--reject" :disabled="actionLoading" @click="reject">Từ chối yêu cầu</button>
        </div>

        <div v-else-if="detail.status === 'approved'" class="action-group">
          <button class="action-btn action-btn--received" :disabled="actionLoading" @click="markReceived">
            Xác nhận đã nhận hàng hoàn
          </button>
        </div>

        <div v-else-if="detail.status === 'received'" class="action-group">
          <label class="field-label">Số tiền hoàn</label>
          <input v-model="refundForm.refund_amount" type="number" min="0" class="field-input" />

          <label class="field-label">Phương thức hoàn tiền</label>
          <select v-model="refundForm.refund_method" class="field-input">
            <option v-for="method in RETURN_REQUEST_REFUND_METHOD_OPTIONS" :key="method.value" :value="method.value">
              {{ method.label }}
            </option>
          </select>

          <label class="field-label">Ghi chú hoàn tiền</label>
          <textarea v-model="refundForm.admin_note" class="field-textarea" placeholder="Nhập thông tin hoàn tiền..."></textarea>

          <button class="action-btn action-btn--refund" :disabled="actionLoading" @click="refund">
            Xác nhận hoàn tiền
          </button>
        </div>

        <div v-else class="status-note">
          <p>Yêu cầu này đã được xử lý xong. Bạn có thể xem lại thông tin ở cột trạng thái và hoàn tiền.</p>
        </div>

        <div class="summary-box">
          <p><strong>Số tiền đơn hàng:</strong> {{ formatPrice(detail.order?.grand_total) }}</p>
          <p v-if="Number(detail.refund_amount || 0) > 0"><strong>Đã hoàn:</strong> {{ formatPrice(detail.refund_amount) }}</p>
          <p v-if="detail.refund_method"><strong>Phương thức:</strong> {{ detail.refund_method }}</p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.admin-return-detail-page {
  padding: 24px;
  background-color: var(--background);
  color: var(--text-main);
  min-height: calc(100vh - 60px);
  font-family: var(--font-primary);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1.5px solid var(--border-color);
  background: var(--surface-container-low);
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text-main);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-back:hover {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

.detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.9fr);
  gap: 20px;
}

.detail-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 28px 32px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
}

.detail-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 28px;
}

.timeline-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.timeline-title-icon {
  width: 48px; height: 48px;
  background: rgba(230, 59, 111, 0.08);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
  flex-shrink: 0;
}

.page-title {
  font-size: 1.35rem;
  font-weight: 800;
  margin: 0;
  color: var(--text-main);
}

.order-code {
  color: var(--primary);
}

.detail-card h3 {
  margin: 0 0 16px;
  color: var(--text-main);
  font-size: 1.15rem;
  font-weight: 700;
}

.eyebrow {
  margin: 0 0 4px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 12px;
  color: var(--text-main);
  font-size: 0.95rem;
}

.info-row span:first-child {
  color: var(--text-muted);
}

.detail-block {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid var(--border-color);
}

.detail-block p {
  margin: 10px 0 0;
  color: var(--text-main);
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
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.field-label {
  display: block;
  margin: 16px 0 8px;
  color: var(--text-main);
  font-size: 0.9rem;
  font-weight: 700;
}

.field-input,
.field-textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1.5px solid var(--border-color);
  background: var(--surface-container-low);
  border-radius: 10px;
  padding: 12px 14px;
  font: inherit;
  color: var(--text-main);
  transition: all 0.2s;
}

.field-input:focus,
.field-textarea:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.field-textarea {
  min-height: 110px;
  resize: vertical;
}

.action-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 24px;
}

.action-btn {
  border: none;
  border-radius: 10px;
  padding: 14px 16px;
  font-weight: 700;
  font-size: 0.95rem;
  color: #fff;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.action-btn--approve,
.action-btn--received,
.action-btn--refund {
  background: var(--primary);
}

.action-btn--reject {
  background: transparent;
  border: 1.5px solid var(--danger, #ef5350);
  color: var(--danger, #ef5350);
}

.action-btn--reject:hover {
  background: var(--danger, #ef5350);
  color: white;
}

.summary-box,
.status-note {
  margin-top: 24px;
  padding: 16px;
  border-radius: 12px;
  background: rgba(230, 59, 111, 0.05);
  border: 1px solid rgba(230, 59, 111, 0.1);
  color: var(--text-main);
}

.summary-box p {
  margin: 0 0 8px;
  display: flex;
  justify-content: space-between;
}

.summary-box p:last-child {
  margin-bottom: 0;
}

.jump-link {
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: rgba(230, 59, 111, 0.08);
  border-radius: 8px;
  transition: all 0.2s;
}

.jump-link:hover {
  background: var(--primary);
  color: white;
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

.status-badge.status-info { color: #475569; background: var(--surface-container); border: 1px solid #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border: 1px solid #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border: 1px solid #fecaca; }

.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-muted);
}

.spinner {
  width: 38px;
  height: 38px;
  border: 3px solid #f1f5f9;
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
  margin: 0 auto 16px;
}

@media (max-width: 960px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
