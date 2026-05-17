<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';

const route = useRoute();
const router = useRouter();

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('orderDetailToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};
const toast = {
  success: (msg) => showToast(msg, 'success'),
  error: (msg) => showToast(msg, 'danger'),
};

const order = ref(null);
const loading = ref(true);

const statuses = [
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'confirmed', label: 'Đã duyệt' },
  { value: 'packing', label: 'Đóng gói' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'delivered', label: 'Đã giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' }
];

// Luồng trạng thái tuần tự
const statusTransitions = {
  'pending':   ['pending', 'confirmed', 'cancelled'],
  'confirmed': ['confirmed', 'packing', 'cancelled'],
  'packing':   ['packing', 'shipping', 'cancelled'],
  'shipping':  ['shipping', 'delivered'],
  'delivered': ['delivered', 'completed'],
  'completed': ['completed'],
  'cancelled': ['cancelled'],
};

const getAllowedFulfillmentOptions = (currentStatus) => {
  const allowed = statusTransitions[currentStatus] || [currentStatus];
  return statuses.filter(s => allowed.includes(s.value));
};

// ====== Payment Status (Chỉ hiển thị, tự động bởi hệ thống) ======
const paymentOptions = [
  { value: 'unpaid', label: 'Chưa thanh toán' },
  { value: 'paid', label: 'Đã thanh toán' },
  { value: 'pending', label: 'Đang xử lý' },
  { value: 'failed', label: 'Thất bại' },
  { value: 'refunded', label: 'Hoàn tiền' },
  { value: 'partially_refunded', label: 'Hoàn một phần' },
];

const isTerminalOrder = (or) => {
  if (!or) return true;
  return or.fulfillment_status === 'cancelled' || or.fulfillment_status === 'completed';
};

const getStatusLabel = (value) => statuses.find(s => s.value === value)?.label || paymentOptions.find(p => p.value === value)?.label || value;

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

const fetchOrder = async () => {
  loading.value = true;
  try {
    const res = await api.get(`/admin/orders/${route.params.id}`);
    if (res.data.status === 'success') {
      order.value = { ...res.data.data, _prevFulfillmentStatus: res.data.data.fulfillment_status, _prevPaymentStatus: res.data.data.payment_status };
    }
  } catch (error) {
    console.error('Fetch order detail failed', error);
    toast.error('Không thể tải thông tin đơn hàng');
    router.push({ name: 'admin-order' });
  } finally {
    loading.value = false;
  }
};

const adminCancelReasons = [
  'Khách hàng yêu cầu hủy',
  'Hết hàng / Không đủ tồn kho',
  'Sản phẩm bị lỗi / hư hỏng',
  'Thông tin đơn hàng không hợp lệ',
  'Không liên lạc được với khách hàng',
  'Đơn hàng trùng lặp',
  'Vi phạm chính sách đặt hàng',
  'Lý do khác',
];

// Cancel modal state
const showCancelModal = ref(false);
const selectedCancelReason = ref('');
const customCancelReason = ref('');
const cancelValidationError = ref('');
let cancelReasonResolver = null;

const showCancelReasonModal = () => {
  selectedCancelReason.value = '';
  customCancelReason.value = '';
  cancelValidationError.value = '';
  showCancelModal.value = true;
  return new Promise((resolve) => { cancelReasonResolver = resolve; });
};

const confirmCancelReason = () => {
  if (!selectedCancelReason.value) {
    cancelValidationError.value = 'Vui lòng chọn lý do hủy đơn';
    return;
  }
  if (selectedCancelReason.value === 'Lý do khác' && !customCancelReason.value.trim()) {
    cancelValidationError.value = 'Vui lòng nhập lý do cụ thể';
    return;
  }
  const reason = selectedCancelReason.value === 'Lý do khác' ? customCancelReason.value.trim() : selectedCancelReason.value;
  showCancelModal.value = false;
  if (cancelReasonResolver) cancelReasonResolver(reason);
};

const dismissCancelModal = () => {
  showCancelModal.value = false;
  if (cancelReasonResolver) cancelReasonResolver(null);
};

const updateFulfillment = async () => {
  const oldStatus = order.value._prevFulfillmentStatus;

  // Nếu chọn lại đúng trạng thái hiện tại → báo lỗi
  if (order.value.fulfillment_status === oldStatus) {
    toast.error(`Đơn hàng đang ở trạng thái "${getStatusLabel(oldStatus)}" rồi. Vui lòng chọn trạng thái tiếp theo!`);
    return;
  }

  // Nếu chọn hủy → bắt buộc nhập lý do
  if (order.value.fulfillment_status === 'cancelled') {
    const cancelReason = await showCancelReasonModal();
    if (!cancelReason) {
      order.value.fulfillment_status = oldStatus;
      return;
    }
    try {
      const res = await api.put(`/admin/orders/${order.value.order_id}/status`, {
        fulfillment_status: 'cancelled',
        note: cancelReason
      });
      if (res.data.status === 'success') {
        order.value._prevFulfillmentStatus = 'cancelled';
        toast.success('Đã hủy đơn hàng thành công!');
        fetchOrder();
      }
    } catch (error) {
      order.value.fulfillment_status = oldStatus;
      toast.error(error.response?.data?.message || 'Lỗi hủy đơn hàng');
    }
    return;
  }

  // Các trạng thái khác
  try {
    const res = await api.put(`/admin/orders/${order.value.order_id}/status`, {
      fulfillment_status: order.value.fulfillment_status
    });
    if (res.data.status === 'success') {
      order.value._prevFulfillmentStatus = order.value.fulfillment_status;
      toast.success('Cập nhật trạng thái xử lý thành công!');
      fetchOrder();
    }
  } catch (error) {
    order.value.fulfillment_status = oldStatus;
    toast.error(error.response?.data?.message || 'Lỗi cập nhật trạng thái');
  }
};



const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getStatusBadgeClass = (status) => {
  const map = {
    pending: 'badge-warning',
    confirmed: 'badge-primary',
    packing: 'badge-info',
    shipping: 'badge-info',
    delivered: 'badge-success',
    completed: 'badge-success',
    cancelled: 'badge-danger',
    unpaid: 'badge-warning',
    paid: 'badge-success',
    failed: 'badge-danger',
    refunded: 'badge-secondary',
    partially_refunded: 'badge-secondary',
  };
  return map[status] || 'badge-secondary';
};

const getPaymentBadgeClass = (status) => {
  const map = { unpaid: 'badge-warning', paid: 'badge-success', failed: 'badge-danger', refunded: 'badge-secondary' };
  return map[status] || 'badge-secondary';
};

const getProductImage = (item) => {
  if (item.variant?.image_url) return `http://localhost:8383/storage/${item.variant.image_url}`;
  if (item.product?.main_image) return `http://localhost:8383/storage/${item.product.main_image}`;
  if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `http://localhost:8383/storage/${item.product.thumbnail_url}`;
  return 'https://placehold.co/80x80?text=No+Img';
};

// Timeline steps
const timelineSteps = [
  { key: 'pending', label: 'Đặt hàng', field: 'created_at' },
  { key: 'confirmed', label: 'Xác nhận', field: 'confirmed_at' },
  { key: 'packing', label: 'Đóng gói', field: null },
  { key: 'shipping', label: 'Vận chuyển', field: 'shipped_at' },
  { key: 'delivered', label: 'Đã giao', field: 'delivered_at' },
  { key: 'completed', label: 'Hoàn thành', field: 'completed_at' },
];

const getStepStatus = (stepKey) => {
  if (!order.value) return 'inactive';
  if (order.value.fulfillment_status === 'cancelled') {
    return 'cancelled';
  }
  const stepOrder = ['pending', 'confirmed', 'packing', 'shipping', 'delivered', 'completed'];
  const currentIdx = stepOrder.indexOf(order.value.fulfillment_status);
  const stepIdx = stepOrder.indexOf(stepKey);
  
  // Nếu đã hoàn thành thì tất cả các bước (kể cả bước hoàn thành) đều là 'done'
  if (order.value.fulfillment_status === 'completed') {
      return stepIdx <= currentIdx ? 'done' : 'inactive';
  }
  
  if (stepIdx < currentIdx) return 'done';
  if (stepIdx === currentIdx) return 'active';
  return 'inactive';
};

const isSyncingGhn = ref(false);

const syncGhn = async () => {
  isSyncingGhn.value = true;
  try {
    const res = await api.post(`/admin/orders/${order.value.order_id}/ghn-sync`);
    if (res.data.status === 'success') {
      toast.success(res.data.message || 'Đã đẩy đơn lên GHN thành công!');
      fetchOrder();
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Lỗi đồng bộ GHN',
      text: error.response?.data?.message || 'Không thể đồng bộ',
      confirmButtonText: 'Đóng'
    });
  } finally {
    isSyncingGhn.value = false;
  }
};

onMounted(() => fetchOrder());
</script>

<template>
  <div class="order-detail-page">
    <!-- Loading -->
    <div v-if="loading" class="loading-box">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-if="order && !loading">
      <!-- Header -->
      <div class="detail-header">
        <div class="header-left">
          <button class="btn-back" @click="router.push({ name: 'admin-order' })">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Quay lại
          </button>
          <div>
            <h1 class="page-title">Đơn hàng <span class="order-code">#{{ order.order_code }}</span></h1>
            <p class="page-sub">Ngày đặt: {{ formatDate(order.created_at) }}</p>
          </div>
        </div>
        <div class="header-badges">
          <span class="status-badge" :class="getStatusBadgeClass(order.fulfillment_status)">{{ getStatusLabel(order.fulfillment_status) }}</span>
          <span class="status-badge" :class="getPaymentBadgeClass(order.payment_status)">{{ paymentLabels[order.payment_status] || order.payment_status }}</span>
        </div>
      </div>

      <!-- Timeline -->
      <div class="timeline-card" v-if="order.fulfillment_status !== 'cancelled'">
        <div class="timeline-card-header">
          <div class="timeline-title-group">
            <div class="timeline-title-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h3 class="card-title">Tiến trình đơn hàng</h3>
          </div>
          <span class="timeline-badge" :class="getStatusBadgeClass(order.fulfillment_status)">
            {{ getStatusLabel(order.fulfillment_status) }}
          </span>
        </div>
        <div class="timeline">
          <div v-for="(step, idx) in timelineSteps" :key="step.key" class="timeline-step" :class="getStepStatus(step.key)">
            <div class="step-connector" v-if="idx > 0"></div>
            <div class="step-dot">
              <div class="step-dot-inner">
                <!-- Đặt hàng -->
                <svg v-if="step.key === 'pending'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
                <!-- Xác nhận -->
                <svg v-else-if="step.key === 'confirmed'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <!-- Đóng gói -->
                <svg v-else-if="step.key === 'packing'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                <!-- Vận chuyển -->
                <svg v-else-if="step.key === 'shipping'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
                <!-- Đã giao -->
                <svg v-else-if="step.key === 'delivered'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                <!-- Hoàn thành -->
                <svg v-else-if="step.key === 'completed'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                </svg>
              </div>
              <div class="step-pulse" v-if="getStepStatus(step.key) === 'active'"></div>
            </div>
            <div class="step-info">
              <span class="step-label">{{ step.label }}</span>
              <span class="step-time" v-if="step.field && order[step.field]">{{ formatDate(order[step.field]) }}</span>
              <span class="step-time" v-else-if="getStepStatus(step.key) === 'active'">Đang xử lý...</span>
            </div>
          </div>
        </div>
      </div>
      <div class="timeline-card cancelled-banner" v-else>
        <div class="cancelled-content">
          <div class="cancelled-icon-wrap">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
          </div>
          <div>
            <h3>Đơn hàng đã bị hủy</h3>
            <p v-if="order.cancel_reason">Lý do: {{ order.cancel_reason }}</p>
            <p v-if="order.cancelled_at">Thời gian: {{ formatDate(order.cancelled_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="detail-grid">
        <!-- LEFT -->
        <div class="detail-main">
          <!-- Sản phẩm -->
          <div class="info-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              Sản phẩm ({{ order.items?.length || 0 }})
            </h3>
            <div class="items-list">
              <div v-for="item in order.items" :key="item.order_item_id" class="order-item">
                <img :src="getProductImage(item)" :alt="item.product_name" class="item-img" />
                <div class="item-info">
                  <h4 class="item-name">{{ item.product_name }}</h4>
                  <div class="item-variant" v-if="item.color || item.size">
                    <span v-if="item.color" class="variant-tag">{{ item.color }}</span>
                    <span v-if="item.size" class="variant-tag">{{ item.size }}</span>
                  </div>
                  <div class="item-sku" v-if="item.sku">SKU: {{ item.sku }}</div>
                </div>
                <div class="item-qty">x{{ item.quantity }}</div>
                <div class="item-price">{{ formatPrice(item.line_total) }}</div>
              </div>
            </div>

            <!-- Tổng kết -->
            <div class="order-summary">
              <div class="summary-row"><span>Tạm tính</span><span>{{ formatPrice(order.subtotal) }}</span></div>
              <div class="summary-row"><span>Phí vận chuyển</span><span>{{ order.shipping_fee == 0 ? 'Miễn phí' : formatPrice(order.shipping_fee) }}</span></div>
              <div class="summary-row discount" v-if="order.discount_amount > 0"><span>Giảm giá</span><span>-{{ formatPrice(order.discount_amount) }}</span></div>
              <div class="summary-row total"><span>Tổng cộng</span><span>{{ formatPrice(order.grand_total) }}</span></div>
            </div>
          </div>

          <!-- Lịch sử trạng thái -->
          <div class="info-card" v-if="order.status_histories && order.status_histories.length > 0">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Lịch sử trạng thái
            </h3>
            <div class="history-list">
              <div v-for="h in [...order.status_histories].reverse()" :key="h.history_id" class="history-item">
                <div class="history-dot" :class="getStatusBadgeClass(h.new_status)"></div>
                <div class="history-content">
                  <div class="history-transition">
                    <span class="status-badge sm" :class="getStatusBadgeClass(h.old_status)" v-if="h.old_status">{{ getStatusLabel(h.old_status) }}</span>
                    <svg v-if="h.old_status" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <span class="status-badge sm" :class="getStatusBadgeClass(h.new_status)">{{ getStatusLabel(h.new_status) }}</span>
                  </div>
                  <p class="history-note" v-if="h.note">{{ h.note }}</p>
                  <span class="history-time">{{ formatDate(h.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT SIDEBAR -->
        <div class="detail-sidebar">
          <!-- Cập nhật trạng thái -->
          <div class="info-card action-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Cập nhật trạng thái
            </h3>
            <div class="action-group">
              <label class="action-label">Xử lý đơn hàng</label>
              <select class="form-select" v-model="order.fulfillment_status" @change="updateFulfillment" :disabled="order.fulfillment_status === 'completed' || order.fulfillment_status === 'cancelled'">
                <option v-for="s in getAllowedFulfillmentOptions(order._prevFulfillmentStatus)" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div class="action-group">
              <label class="action-label">Thanh toán</label>
              <div class="payment-auto-badge" :class="'ps-' + order.payment_status">
                <span class="payment-auto-icon">
                  <svg v-if="order.payment_status === 'paid'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <svg v-else-if="order.payment_status === 'refunded'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                  <svg v-else-if="order.payment_status === 'failed'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <span class="payment-auto-text">{{ paymentLabels[order.payment_status] || order.payment_status }}</span>
                <span class="payment-auto-hint">(Tự động)</span>
              </div>
            </div>
          </div>

          <!-- Thông tin khách hàng -->
          <div class="info-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Khách hàng
            </h3>
            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Tên</span>
                <span class="info-value">{{ order.user?.name || order.recipient_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ order.user?.email || '—' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Điện thoại</span>
                <span class="info-value">{{ order.user?.phone || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Thông tin giao hàng -->
          <div class="info-card">
            <h3 class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
              <div style="display:flex; align-items:center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Giao hàng
              </div>
              <button class="btn-ghn" @click="syncGhn" :disabled="isSyncingGhn || order.fulfillment_status === 'cancelled'">
                 {{ isSyncingGhn ? 'Đang đẩy...' : 'Đẩy qua GHN' }}
              </button>
            </h3>
            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Người nhận</span>
                <span class="info-value fw-bold">{{ order.recipient_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">SĐT</span>
                <span class="info-value">{{ order.recipient_phone }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Địa chỉ</span>
                <span class="info-value">{{ order.shipping_address }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">PT Thanh toán</span>
                <span class="info-value fw-bold">{{ paymentMethodLabels[order.payment_method] || order.payment_method }}</span>
              </div>
              <div class="info-row" v-if="order.note">
                <span class="info-label">Ghi chú</span>
                <span class="info-value note-text">{{ order.note }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Cancel Reason Modal -->
    <Transition name="modal">
      <div v-if="showCancelModal" class="cancel-modal-overlay" @click.self="dismissCancelModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Hủy đơn hàng
            </h5>
            <button class="cancel-modal-close" @click="dismissCancelModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="cancel-modal-body">
            <p class="cancel-modal-desc">Chọn lý do hủy đơn:</p>
            <div class="cancel-reason-list">
              <label v-for="r in adminCancelReasons" :key="r" class="cancel-reason-item" :class="{ selected: selectedCancelReason === r }">
                <input type="radio" v-model="selectedCancelReason" :value="r" @change="cancelValidationError = ''" />
                <span>{{ r }}</span>
              </label>
            </div>
            <textarea v-if="selectedCancelReason === 'Lý do khác'" v-model="customCancelReason" placeholder="Nhập lý do cụ thể..." class="cancel-custom-input" @input="cancelValidationError = ''"></textarea>
            <p v-if="cancelValidationError" class="cancel-validation-error">{{ cancelValidationError }}</p>
          </div>
          <div class="cancel-modal-footer">
            <button class="btn-cancel-dismiss" @click="dismissCancelModal">Quay lại</button>
            <button class="btn-cancel-confirm" @click="confirmCancelReason">Xác nhận hủy</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Bootstrap Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
      <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="orderDetailToast" role="alert">
        <div class="d-flex">
          <div class="toast-body">{{ toastData.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.order-detail-page {
  padding: 24px;
  background-color: #f8f9fa;
  min-height: calc(100vh - 60px);
  font-family: 'Inter', sans-serif;
}

/* Loading */
.loading-box { text-align: center; padding: 80px 0; }

/* Header */
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
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
  border: 1.5px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-back:hover { background: #0d6efd; color: white; border-color: #0d6efd; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; color: #0f172a; }
.order-code { color: #0d6efd; }
.page-sub { margin: 4px 0 0; font-size: 0.9rem; color: #64748b; }
.header-badges { display: flex; gap: 8px; align-items: center; }

/* Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.status-badge.sm { padding: 3px 10px; font-size: 0.75rem; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-primary { background: #dbeafe; color: #1e40af; }
.badge-info { background: #cffafe; color: #155e75; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #e2e8f0; color: #475569; }

/* ====== Timeline Card — Premium Design ====== */
.timeline-card {
  background: white;
  border-radius: 16px;
  padding: 28px 32px;
  margin-bottom: 24px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  position: relative;
  overflow: hidden;
}

.timeline-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
}
.timeline-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.timeline-title-icon {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3b82f6;
  flex-shrink: 0;
}
.timeline-card-header .card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.timeline-badge {
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.timeline {
  display: flex;
  align-items: flex-start;
  gap: 0;
  overflow-x: auto;
  padding: 8px 0 12px;
}
.timeline-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  min-width: 110px;
  position: relative;
}

/* Connector line */
.step-connector {
  position: absolute;
  top: 23px;
  right: 50%;
  width: 100%;
  height: 3px;
  background: #e2e8f0;
  z-index: 0;
  border-radius: 2px;
}
.timeline-step.done .step-connector {
  background: linear-gradient(90deg, #0284c7, #0ea5e9);
}
.timeline-step.active .step-connector {
  background: linear-gradient(90deg, #0284c7, #0ea5e9);
}

/* Step dot */
.step-dot {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 2.5px solid #e2e8f0;
  z-index: 1;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}
.step-dot-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  transition: color 0.3s;
}

/* Done state */
.timeline-step.done .step-dot {
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.08), #bae6fd);
  border-color: #0ea5e9;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.18);
}
.timeline-step.done .step-dot-inner { color: #0284c7; }

/* Active state */
.timeline-step.active .step-dot {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-color: #3b82f6;
  box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.1), 0 4px 12px rgba(59, 130, 246, 0.2);
  transform: scale(1.08);
}
.timeline-step.active .step-dot-inner { color: #2563eb; }

/* Pulse animation for active step */
.step-pulse {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 48px; height: 48px;
  border-radius: 50%;
  border: 2px solid #3b82f6;
  animation: pulseRing 2s ease-out infinite;
  pointer-events: none;
}
@keyframes pulseRing {
  0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
  70% { transform: translate(-50%, -50%) scale(1.5); opacity: 0; }
  100% { transform: translate(-50%, -50%) scale(1.5); opacity: 0; }
}

/* Step info */
.step-info {
  text-align: center;
  margin-top: 14px;
  min-height: 36px;
}
.step-label {
  font-weight: 600;
  font-size: 0.82rem;
  color: #94a3b8;
  display: block;
  transition: color 0.3s;
  letter-spacing: 0.1px;
}
.timeline-step.done .step-label { color: #0369a1; font-weight: 700; }
.timeline-step.active .step-label { color: #1d4ed8; font-weight: 700; }
.step-time {
  font-size: 0.7rem;
  color: #94a3b8;
  margin-top: 3px;
  display: block;
  font-weight: 500;
}
.timeline-step.active .step-time { color: #3b82f6; font-weight: 600; font-style: italic; }
.timeline-step.done .step-time { color: #0284c7; }

/* ====== Cancelled Banner ====== */
.cancelled-banner {
  background: linear-gradient(135deg, #fef2f2, #fff1f2);
  border-color: #fecaca;
}

.cancelled-content { display: flex; align-items: center; gap: 20px; }
.cancelled-icon-wrap {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #dc2626;
  flex-shrink: 0;
}
.cancelled-content h3 { margin: 0; color: #991b1b; font-size: 1.1rem; font-weight: 700; }
.cancelled-content p { margin: 4px 0 0; color: #b91c1c; font-size: 0.88rem; }

/* Grid Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 7fr 5fr;
  gap: 24px;
}

/* Cards */
.info-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.card-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 20px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.card-title svg { color: #0d6efd; }

/* Action Card */
.action-card { }
.action-group { margin-bottom: 16px; }
.action-group:last-child { margin-bottom: 0; }
.action-label { font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
.form-select {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s;
  background-color: white;
  cursor: pointer;
}
.form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,0.1); }
.form-select:disabled { background-color: #f1f5f9; cursor: not-allowed; opacity: 0.7; }

/* Info Rows */
.info-rows { display: flex; flex-direction: column; gap: 14px; }
.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.info-label { font-size: 0.85rem; color: #64748b; font-weight: 500; min-width: 90px; flex-shrink: 0; }
.info-value { font-size: 0.9rem; color: #0f172a; text-align: right; word-break: break-word; }
.fw-bold { font-weight: 700 !important; }
.note-text { background: #fffbeb; padding: 8px 12px; border-radius: 6px; font-style: italic; font-size: 0.85rem; border: 1px dashed #fbbf24; text-align: left; }

.btn-ghn {
  background-color: #f97316;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-ghn:hover { background-color: #ea580c; }
.btn-ghn:disabled { background-color: #fdba74; cursor: not-allowed; }

/* Items List */
.items-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
.order-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  transition: all 0.2s;
}
.order-item:hover { border-color: #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.item-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.item-info { flex: 1; min-width: 0; }
.item-name { margin: 0; font-size: 0.95rem; font-weight: 600; color: #0f172a; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.item-variant { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.variant-tag { padding: 2px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: rgba(230, 59, 111, 0.08); color: #0369a1; }
.item-sku { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }
.item-qty { font-weight: 700; color: #64748b; font-size: 0.95rem; padding: 0 8px; }
.item-price { font-weight: 700; color: #0f172a; font-size: 1rem; min-width: 110px; text-align: right; }

/* Order Summary */
.order-summary {
  border-top: 2px dashed #e2e8f0;
  padding-top: 20px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: 0.92rem;
  color: #475569;
}
.summary-row.discount span:last-child { color: #16a34a; font-weight: 600; }
.summary-row.total {
  border-top: 2px solid #e2e8f0;
  margin-top: 8px;
  padding-top: 14px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
}
.summary-row.total span:last-child { color: #dc2626; font-size: 1.2rem; }

/* History */
.history-list { display: flex; flex-direction: column; gap: 16px; }
.history-item { display: flex; gap: 14px; align-items: flex-start; }
.history-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
}
.history-content { flex: 1; }
.history-transition { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.history-note { margin: 6px 0 2px; font-size: 0.85rem; color: #64748b; font-style: italic; }
.history-time { font-size: 0.75rem; color: #94a3b8; }

/* Responsive */
@media (max-width: 992px) {
  .detail-grid { grid-template-columns: 1fr; }
  .detail-header { flex-direction: column; }
  .timeline-card { padding: 20px; }
  .timeline-card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .timeline { flex-wrap: wrap; gap: 16px; justify-content: center; }
  .timeline-step { min-width: 70px; }
  .step-connector { display: none; }
  .step-dot { width: 42px; height: 42px; }
  .step-dot-inner svg { width: 16px; height: 16px; }
  .step-pulse { width: 42px; height: 42px; }
}

/* Cancel Modal */
.cancel-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1050;
}
.cancel-modal-box {
  background: white; border-radius: 16px; width: 100%; max-width: 480px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;
}
.cancel-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 24px; border-bottom: 1px solid #e2e8f0;
}
.cancel-modal-header h5 {
  margin: 0; font-size: 1.05rem; font-weight: 700; color: #dc2626;
  display: flex; align-items: center; gap: 10px;
}
.cancel-modal-header h5 svg { color: #dc2626; }
.cancel-modal-close {
  background: none; border: none; cursor: pointer; color: #94a3b8;
  display: flex; padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.cancel-modal-close:hover { background: #f1f5f9; color: #dc2626; }
.cancel-modal-body { padding: 20px 24px; }
.cancel-modal-desc { color: #64748b; font-size: 0.88rem; margin: 0 0 14px; }
.cancel-reason-list { display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto; }
.cancel-reason-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
  cursor: pointer; background: white; transition: all 0.15s; font-size: 0.88rem; color: #334155;
}
.cancel-reason-item:hover { border-color: #dc2626; background: #fef2f2; }
.cancel-reason-item.selected { border-color: #dc2626; background: #fef2f2; }
.cancel-reason-item input[type="radio"] { accent-color: #dc2626; width: 16px; height: 16px; flex-shrink: 0; }
.cancel-custom-input {
  width: 100%; margin-top: 12px; padding: 12px; border: 1.5px solid #e2e8f0;
  border-radius: 10px; font-size: 0.88rem; min-height: 70px; resize: vertical;
  outline: none; font-family: inherit; box-sizing: border-box;
}
.cancel-custom-input:focus { border-color: #dc2626; }
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 24px; border-top: 1px solid #e2e8f0;
}
.btn-cancel-dismiss {
  padding: 8px 20px; border-radius: 8px; border: 1px solid #e2e8f0;
  background: white; color: #64748b; font-weight: 600; font-size: 0.88rem;
  cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-cancel-dismiss:hover { background: #f1f5f9; }
.btn-cancel-confirm {
  padding: 8px 20px; border-radius: 8px; border: none;
  background: #dc2626; color: white; font-weight: 600; font-size: 0.88rem;
  cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-cancel-confirm:hover { background: #b91c1c; }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .cancel-modal-box, .modal-leave-to .cancel-modal-box { transform: scale(0.95) translateY(10px); }

/* ===== Payment Auto Badge (Read-only) ===== */
.payment-auto-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1.5px solid;
  transition: all 0.3s ease;
}
.payment-auto-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border-radius: 8px;
  flex-shrink: 0;
}
.payment-auto-text {
  font-weight: 700;
  font-size: 0.92rem;
}
.payment-auto-hint {
  font-size: 0.75rem;
  font-weight: 500;
  opacity: 0.6;
  margin-left: auto;
}

/* Paid */
.ps-paid {
  background: #ecfdf5;
  border-color: #a7f3d0;
}
.ps-paid .payment-auto-icon {
  background: #d1fae5;
  color: #059669;
}
.ps-paid .payment-auto-text { color: #065f46; }

/* Unpaid */
.ps-unpaid {
  background: #fffbeb;
  border-color: #fde68a;
}
.ps-unpaid .payment-auto-icon {
  background: #fef3c7;
  color: #d97706;
}
.ps-unpaid .payment-auto-text { color: #92400e; }

/* Pending */
.ps-pending {
  background: #fffbeb;
  border-color: #fde68a;
}
.ps-pending .payment-auto-icon {
  background: #fef3c7;
  color: #f59e0b;
}
.ps-pending .payment-auto-text { color: #92400e; }

/* Failed */
.ps-failed {
  background: #fef2f2;
  border-color: #fecaca;
}
.ps-failed .payment-auto-icon {
  background: #fee2e2;
  color: #dc2626;
}
.ps-failed .payment-auto-text { color: #991b1b; }

/* Refunded */
.ps-refunded {
  background: #f1f5f9;
  border-color: #cbd5e1;
}
.ps-refunded .payment-auto-icon {
  background: #e2e8f0;
  color: #475569;
}
.ps-refunded .payment-auto-text { color: #475569; }

/* Partially refunded */
.ps-partially_refunded {
  background: #f1f5f9;
  border-color: #cbd5e1;
}
.ps-partially_refunded .payment-auto-icon {
  background: #e2e8f0;
  color: #475569;
}
.ps-partially_refunded .payment-auto-text { color: #475569; }
</style>
