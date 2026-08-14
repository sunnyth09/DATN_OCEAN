<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { getStorageUrl } from '@/utils/url';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();
const router = useRouter();

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    title: type === 'success' ? 'Thành công' : (type === 'error' || type === 'danger' ? 'Lỗi' : 'Thông báo'),
    text: message,
    icon: type === 'danger' ? 'error' : type,
    showConfirmButton: false,
    timer: 3000
  });
};
const toast = {
  success: (msg) => showToast(msg, 'success'),
  error: (msg) => showToast(msg, 'danger'),
};

const order = ref(null);
const loading = ref(true);
const ghnLookup = ref(null);
const isLookingUpGhn = ref(false);
const isStatusActionLoading = ref(false);

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
statuses.splice(2, 0, { value: 'processing', label: 'Đang xử lý' });
statuses.push(
  { value: 'return_requested', label: 'Yêu cầu hoàn' },
  { value: 'return_approved', label: 'Đã duyệt hoàn' },
  { value: 'return_rejected', label: 'Từ chối hoàn' },
  { value: 'returned', label: 'Đã nhận hàng hoàn' },
  { value: 'refunded', label: 'Đã hoàn tiền' },
);

// statusTransitions was removed because we use backend available_transitions now

const isLockedFulfillmentStatus = (status) => {
  return ['completed', 'cancelled', 'return_requested', 'return_approved', 'return_rejected', 'returned', 'refunded'].includes(status);
};

const statusActionDefinitions = {
  confirmed: { icon: 'check', label: 'Duyệt đơn', success: 'Đã duyệt đơn hàng thành công!' },
  processing: { icon: 'clock', label: 'Chuyển sang đang xử lý', success: 'Đã chuyển đơn sang đang xử lý!' },
  packing: { icon: 'clipboard-list', label: 'Chuyển sang đóng gói', success: 'Đã chuyển đơn sang đóng gói!' },
  awaiting_pickup: { icon: 'package', label: 'Chờ lấy hàng', success: 'Đã chuyển đơn sang chờ lấy hàng!' },
  shipping: { icon: 'truck', label: 'Chuyển sang đang giao', success: 'Đã chuyển đơn sang đang giao!' },
  delivered: { icon: 'check', label: 'Đánh dấu đã giao', success: 'Đã đánh dấu đơn hàng đã giao!' },
  completed: { icon: 'check', label: 'Hoàn thành đơn', success: 'Đã hoàn thành đơn hàng!' },
  cancelled: { icon: 'x', label: 'Hủy đơn', success: 'Đã hủy đơn hàng thành công!' },
  return_requested: { icon: 'rotate-ccw', label: 'Yêu cầu hoàn trả', success: 'Đã chuyển sang yêu cầu hoàn trả!' },
  return_approved: { icon: 'check', label: 'Duyệt hoàn trả', success: 'Đã duyệt yêu cầu hoàn trả!' },
  return_rejected: { icon: 'x', label: 'Từ chối hoàn trả', success: 'Đã từ chối yêu cầu hoàn trả!' },
  returned: { icon: 'package-check', label: 'Đã nhận hàng hoàn', success: 'Đã xác nhận nhận hàng hoàn!' },
  refunded: { icon: 'corner-down-left', label: 'Đã hoàn tiền', success: 'Đã hoàn tiền thành công!' },
  returning: { icon: 'truck', label: 'Khách đang gửi trả', success: 'Khách hàng đang gửi trả sản phẩm!' },
  warehouse_received: { icon: 'box', label: 'Kho đã nhận', success: 'Kho đã nhận sản phẩm hoàn trả!' },
  inspection_failed: { icon: 'x-circle', label: 'Không đạt kiểm tra', success: 'Hàng hoàn trả không đạt yêu cầu!' },
  inspected_ok: { icon: 'check-circle', label: 'Đạt kiểm tra', success: 'Hàng hoàn trả đạt yêu cầu!' },
};

const getCurrentOrderStatusActions = () => {
  if (!order.value) return [];
  const allowed = order.value.available_transitions || [];
  return allowed
    .filter((status) => {
      if (status === 'shipping') return false; // Shipping is handled by dedicated UI now
      
      // Khóa toàn bộ các trạng thái thuộc về Vận chuyển (Carrier) nếu đang dùng đối tác thứ 3 (GHN/OceanExpress)
      if (order.value.tracking_number && order.value.tracking_number !== 'SELF-DELIVERY') {
        const carrierControlledStatuses = ['delivered', 'returning', 'returned', 'warehouse_received'];
        if (carrierControlledStatuses.includes(status)) {
          return false;
        }
      }
      
      return Boolean(statusActionDefinitions[status]);
    })
    .map((status) => ({ value: status, ...statusActionDefinitions[status] }));
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
  bank_transfer: 'Chuyển khoản ngân hàng',
};

paymentLabels.refund_pending = 'Chờ hoàn tiền';
paymentLabels.refund_failed = 'Hoàn tiền lỗi';

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

const updateOrderStatus = async (action) => {
  if (!order.value || action.value === order.value._prevFulfillmentStatus) return;

  const payload = { fulfillment_status: action.value };
  if (action.value === 'cancelled') {
    const cancelReason = await showCancelReasonModal();
    if (!cancelReason) return;
    payload.note = cancelReason;
  } else {
    const confirmResult = await Swal.fire({
      title: 'Xác nhận',
      text: `Bạn có chắc chắn muốn ${action.label.toLowerCase()} cho đơn hàng #${order.value.order_code}?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#E63B6F',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Đồng ý',
      cancelButtonText: 'Hủy'
    });
    
    if (!confirmResult.isConfirmed) {
      return;
    }
  }

  isStatusActionLoading.value = true;
  try {
    const res = await api.put(`/admin/orders/${order.value.order_id}/status`, payload);
    if (res.data.status === 'success') {
      toast.success(action.success || 'Cập nhật trạng thái thành công!');
      window.dispatchEvent(new Event('admin-order-updated'));
      await fetchOrder();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi cập nhật trạng thái');
  } finally {
    isStatusActionLoading.value = false;
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
  if (item.variant?.image_url) return getStorageUrl(item.variant.image_url);
  if (item.product?.main_image) return getStorageUrl(item.product.main_image);
  if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return getStorageUrl(item.product.thumbnail_url);
  return 'https://placehold.co/80x80?text=No+Img';
};

// Timeline steps
const timelineSteps = [
  { key: 'pending', label: 'Đặt hàng', field: 'created_at' },
  { key: 'confirmed', label: 'Xác nhận', field: 'confirmed_at' },
  { key: 'packing', label: 'Đóng gói', field: 'packing_at' },
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

const getStepTimestamp = (step) => {
  if (!order.value) return null;
  if (step.field && order.value[step.field]) {
    return order.value[step.field];
  }
  if (order.value.status_histories && order.value.status_histories.length > 0) {
    const history = order.value.status_histories.find(h => h.new_status === step.key);
    if (history) return history.created_at;
  }
  return null;
};

const isSyncingGhn = ref(false);
const isPrinting = ref(false);
const isCanceling = ref(false);

const syncGhn = async () => {
  isSyncingGhn.value = true;
  try {
    const res = await api.post(`/admin/orders/${order.value.order_id}/ghn-sync`);
    if (res.data.status === 'success') {
      toast.success(res.data.message || 'Đã đẩy đơn thành công!');
      fetchOrder();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể đồng bộ vận chuyển');
  } finally {
    isSyncingGhn.value = false;
  }
};

const isSelfDelivering = ref(false);
const confirmSelfDelivery = async () => {
  if (!order.value) return;
  const confirmResult = await Swal.fire({
    title: 'Xác nhận tự giao hàng',
    text: `Bạn sẽ tự đi giao đơn hàng này? Hệ thống sẽ chuyển sang trạng thái Đang giao.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });
  
  if (!confirmResult.isConfirmed) return;

  isSelfDelivering.value = true;
  try {
    const res = await api.post(`/admin/orders/${order.value.order_id}/self-delivery`);
    if (res.data.status === 'success') {
      toast.success(res.data.message || 'Đã chuyển sang tự giao hàng!');
      window.dispatchEvent(new Event('admin-order-updated'));
      await fetchOrder();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể cập nhật tự giao hàng');
  } finally {
    isSelfDelivering.value = false;
  }
};

const lookupGhnStatus = async (sync = true) => {
  if (!order.value?.tracking_number || order.value.tracking_number === 'SELF-DELIVERY') return;
  isLookingUpGhn.value = true;
  try {
    const res = await api.post('/ghn/order-detail', {
      order_code: order.value.tracking_number,
      sync,
    });
    if (res.data.status === 'success') {
      ghnLookup.value = res.data.data;
      toast.success(res.data.message || 'Đã tra cứu trạng thái vận chuyển');
      if (sync) await fetchOrder();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể tra cứu trạng thái vận chuyển');
  } finally {
    isLookingUpGhn.value = false;
  }
};

const printLabel = async () => {
  if (!order.value?.tracking_number) return;
  isPrinting.value = true;
  try {
    const res = await api.post('/ghn/print-label', { order_code: order.value.tracking_number });
    if (res.data.code === 200 && res.data.data?.token) {
      const printUrl = res.data.data.print_url;
      if (printUrl) window.open(printUrl, '_blank');
      else toast.error('Không thể lấy link in vận đơn');
    } else {
      toast.error('Không thể in vận đơn');
    }
  } catch (error) {
    toast.error('Lỗi khi in vận đơn');
  } finally {
    isPrinting.value = false;
  }
};

const cancelGhnOrder = async () => {
  if (!order.value?.tracking_number) return;
  const reason = window.prompt('Nhập lý do hủy vận đơn:');
  if (!reason?.trim()) return;
  if (!confirm('Bạn có chắc chắn muốn hủy vận đơn này trên hệ thống GHN?')) return;
  isCanceling.value = true;
  try {
    const res = await api.post('/ghn/cancel-order', { order_code: order.value.tracking_number, reason: reason.trim() });
    if (res.data.code === 200) {
      toast.success('Đã hủy vận đơn trên thành công!');
    } else {
      toast.error(res.data.message || 'Không thể hủy vận đơn');
    }
  } catch (error) {
    toast.error('Lỗi khi hủy vận đơn');
  } finally {
    isCanceling.value = false;
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
            <h1 class="page-title" style="display: flex; align-items: center; gap: 8px;">
                Đơn hàng <span class="order-code">#{{ order.order_code }}</span>
                <span v-if="order.order_code && order.order_code.startsWith('FS-')" class="badge bg-warning text-dark" style="font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center;">⚡ Flash Sale</span>
            </h1>
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
              <span class="step-time" v-if="getStepTimestamp(step)">{{ formatDate(getStepTimestamp(step)) }}</span>
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
            <OrderStatusTimeline
              :histories="order.status_histories"
              :show-ghn-meta="true"
              :get-status-label="getStatusLabel"
              :get-status-badge-class="getStatusBadgeClass"
              :format-date="formatDate"
            />
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
              <label class="action-label mb-2">Thao tác khả dụng</label>
              
              <div v-if="order.tracking_number && order.tracking_number !== 'SELF-DELIVERY' && !getCurrentOrderStatusActions().length" class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.85rem">
                Trạng thái đang được đồng bộ tự động từ hãng vận chuyển.
              </div>

              <div class="status-action-buttons">
                <button
                  v-for="action in getCurrentOrderStatusActions()"
                  :key="action.value"
                  class="btn-status-large"
                  :class="'btn-' + action.value"
                  @click="updateOrderStatus(action)"
                  :disabled="isStatusActionLoading"
                  :title="action.label"
                >
                  <AppIcon :name="action.icon" size="18" stroke-width="2.5" />
                  <span>{{ action.label }}</span>
                </button>
              </div>
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
            <h3 class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
              <div style="display:flex; align-items:center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Giao hàng
                <span v-if="order.tracking_number === 'SELF-DELIVERY'" class="status-badge badge-success sm ms-2">
                  <AppIcon name="user-check" size="14" class="me-1" /> Shop tự giao
                </span>
                <span v-else-if="order.tracking_number" class="status-badge badge-info sm ms-2">Mã vận đơn: {{ order.tracking_number }}</span>
              </div>
              
              <!-- Khối thao tác đẩy đơn/tự giao -->
              <div style="display: flex; gap: 8px; flex-wrap: wrap;" v-if="order.available_transitions?.includes('shipping')">
                <button class="btn-lookup-ghn" @click="syncGhn" :disabled="isSyncingGhn || order.fulfillment_status === 'cancelled'">
                   <AppIcon name="truck" size="16" class="me-1" />
                   {{ isSyncingGhn ? 'Đang đẩy...' : 'Giao qua đối tác' }}
                </button>
                <button class="btn-print" style="background-color: #28a745" @click="confirmSelfDelivery" :disabled="isSelfDelivering || order.fulfillment_status === 'cancelled'">
                   <AppIcon name="user" size="16" class="me-1" />
                   {{ isSelfDelivering ? 'Đang xử lý...' : 'Shop tự đi giao' }}
                </button>
              </div>
              
              <!-- Khối tracking đối tác -->
              <div style="display: flex; gap: 8px; flex-wrap: wrap;" v-if="order.tracking_number && order.tracking_number !== 'SELF-DELIVERY'">
                <button class="btn-lookup-ghn" @click="lookupGhnStatus(true)" :disabled="isLookingUpGhn">
                   {{ isLookingUpGhn ? 'Đang tra...' : 'Tra cứu đơn' }}
                </button>
                <button class="btn-print" @click="printLabel" :disabled="isPrinting">
                   {{ isPrinting ? 'Đang tạo...' : 'In vận đơn' }}
                </button>
                <button class="btn-cancel-ghn" @click="cancelGhnOrder" :disabled="isCanceling">
                   Hủy vận đơn
                </button>
              </div>
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
            <div v-if="ghnLookup" class="ghn-lookup-panel">
              <div class="ghn-lookup-title">Trạng thái vận chuyển</div>
              <div class="ghn-lookup-row"><span>Trạng thái vận chuyển</span><strong>{{ ghnLookup.ghn_status || '—' }}</strong></div>
              <div class="ghn-lookup-row"><span>Trạng thái local</span><strong>{{ getStatusLabel(ghnLookup.local_status) }}</strong></div>
              <div class="ghn-lookup-row"><span>Mapping</span><strong>{{ getStatusLabel(ghnLookup.mapped_status) }}</strong></div>
              <div class="ghn-lookup-row" v-if="ghnLookup.happened_at"><span>Thời gian</span><strong>{{ formatDate(ghnLookup.happened_at) }}</strong></div>
              <div class="ghn-lookup-row" v-if="ghnLookup.location"><span>Vị trí</span><strong>{{ ghnLookup.location }}</strong></div>
              <p v-if="ghnLookup.description" class="ghn-lookup-desc">{{ ghnLookup.description }}</p>
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
  background-color: var(--background);
  color: var(--text-main);
  min-height: calc(100vh - 60px);
  font-family: var(--font-primary);
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
  border: 1.5px solid var(--border-color);
  background: var(--surface-container-low);
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--text-main);
  cursor: pointer;
  transition: all 0.2s;
}
.btn-back:hover { background: var(--primary); color: white; border-color: var(--primary); }
.page-title { font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main); word-break: break-word; }
.order-code { color: var(--primary); word-break: break-all; }
.page-sub { margin: 4px 0 0; font-size: 0.9rem; color: var(--text-muted); }
.header-badges { display: flex; gap: 8px; align-items: center; }

/* Custom Buttons */
.btn-ghn, .btn-print, .btn-cancel-ghn, .btn-lookup-ghn {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: opacity 0.2s;
}
.btn-ghn:disabled, .btn-print:disabled, .btn-cancel-ghn:disabled, .btn-lookup-ghn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-ghn {
  background: var(--primary);
  color: #fff;
}
.btn-lookup-ghn {
  background: #2563eb;
  color: #fff;
}
.btn-print {
  background: #4db6ac;
  color: #fff;
}
.btn-cancel-ghn {
  background: #e57373;
  color: #fff;
}
 
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
.badge-warning { background: rgba(255, 167, 38, 0.15); color: #ffb74d; }
.badge-primary { background: rgba(230, 59, 111, 0.15); color: #ffb2bf; }
.badge-info { background: rgba(0, 188, 212, 0.15); color: #4fc3f7; }
.badge-success { background: rgba(38, 166, 154, 0.15); color: #4db6ac; }
.badge-danger { background: rgba(239, 83, 80, 0.15); color: #e57373; }
.badge-secondary { background: rgba(158, 158, 158, 0.15); color: #b0bec5; }
 
/* ====== Timeline Card — Premium Design ====== */
.timeline-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 28px 32px;
  margin-bottom: 24px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
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
  background: rgba(230, 59, 111, 0.08);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
  flex-shrink: 0;
}
.timeline-card-header .card-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
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
  background: var(--border-color);
  z-index: 0;
  border-radius: 2px;
}
.timeline-step.done .step-connector {
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
}
.timeline-step.active .step-connector {
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
}
 
/* Step dot */
.step-dot {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface-container-low);
  border: 2.5px solid var(--border-color);
  z-index: 1;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
}
.step-dot-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-light);
  transition: color 0.3s;
}
 
/* Done state */
.timeline-step.done .step-dot {
  background: rgba(230, 59, 111, 0.08);
  border-color: var(--primary);
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.15);
}
.timeline-step.done .step-dot-inner { color: var(--primary); }
 
/* Active state */
.timeline-step.active .step-dot {
  background: rgba(230, 59, 111, 0.15);
  border-color: var(--primary);
  box-shadow: 0 0 0 6px rgba(230, 59, 111, 0.1), 0 4px 12px rgba(230, 59, 111, 0.2);
  transform: scale(1.08);
}
.timeline-step.active .step-dot-inner { color: var(--primary); }
 
/* Pulse animation for active step */
.step-pulse {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 48px; height: 48px;
  border-radius: 50%;
  border: 2px solid var(--primary);
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
  color: var(--text-light);
  display: block;
  transition: color 0.3s;
  letter-spacing: 0.1px;
}
.timeline-step.done .step-label { color: var(--primary); font-weight: 700; }
.timeline-step.active .step-label { color: var(--primary); font-weight: 800; }
.step-time {
  font-size: 0.7rem;
  color: var(--text-light);
  margin-top: 3px;
  display: block;
  font-weight: 500;
}
.timeline-step.active .step-time { color: var(--primary); font-weight: 600; font-style: italic; }
.timeline-step.done .step-time { color: var(--primary-dark); }
 
/* ====== Cancelled Banner ====== */
.cancelled-banner {
  background: rgba(239, 83, 80, 0.08);
  border-color: rgba(239, 83, 80, 0.2);
}
 
.cancelled-content { display: flex; align-items: center; gap: 20px; }
.cancelled-icon-wrap {
  width: 52px; height: 52px;
  background: rgba(239, 83, 80, 0.15);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ef5350;
  flex-shrink: 0;
}
.cancelled-content h3 { margin: 0; color: #ef5350; font-size: 1.1rem; font-weight: 700; }
.cancelled-content p { margin: 4px 0 0; color: #e57373; font-size: 0.88rem; }
 
/* Grid Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 7fr 5fr;
  gap: 24px;
}
 
/* Cards */
.info-card {
  background: var(--card-bg);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
  color: var(--text-main);
}
.card-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-main);
  margin: 0 0 20px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.card-title svg { color: var(--primary); }
 
/* Action Card */
.action-card { }
.action-group { margin-bottom: 16px; }
.action-group:last-child { margin-bottom: 0; }
.action-label { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block; }
.status-inline-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}
.status-readonly-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 0.85rem;
  font-weight: 800;
  white-space: nowrap;
}
.btn-status-action {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 50%;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  line-height: 1;
  font-weight: 900;
  cursor: pointer;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-status-action.confirmed,
.btn-status-action.completed { background: #dcfce7; color: #15803d; }
.btn-status-action.processing { background: #e0f2fe; color: #0369a1; }
.btn-status-action.packing { background: #ede9fe; color: #6d28d9; }
.btn-status-action.shipping,
.btn-status-action.delivered { background: #dbeafe; color: #1d4ed8; }
.btn-status-action.cancelled { background: #fee2e2; color: #b91c1c; }
.btn-status-action:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(0.96); }

.status-action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.btn-status-large {
  display: inline-flex;
  align-items: center;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s ease;
  background: var(--surface-container-low);
  color: var(--text-main);
  border-color: var(--border-color);
}
.btn-status-large.btn-confirmed,
.btn-status-large.btn-completed { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.btn-status-large.btn-processing { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
.btn-status-large.btn-packing { background: #ede9fe; color: #6d28d9; border-color: #ddd6fe; }
.btn-status-large.btn-shipping,
.btn-status-large.btn-delivered { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
.btn-status-large.btn-cancelled { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
.btn-status-large:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); filter: brightness(0.96); }
.btn-status-large:disabled { opacity: 0.6; cursor: not-allowed; }.btn-status-action:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Info Rows */
.info-rows { display: flex; flex-direction: column; gap: 14px; }
.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.info-label { font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; min-width: 90px; flex-shrink: 0; }
.info-value { font-size: 0.9rem; color: var(--text-main); text-align: right; word-break: break-all; overflow-wrap: anywhere; }
.fw-bold { font-weight: 700 !important; }
.note-text { background: rgba(251, 191, 36, 0.08); padding: 8px 12px; border-radius: 6px; font-style: italic; font-size: 0.85rem; border: 1.5px dashed rgba(251, 191, 36, 0.4); text-align: left; color: #fbbf24; }
 
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
.btn-ghn:disabled { background-color: rgba(249, 115, 22, 0.5); cursor: not-allowed; }
 
/* Items List */
.items-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
.order-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border-radius: 10px;
  background: var(--surface-container-low);
  border: 1px solid var(--border-color);
  transition: all 0.2s;
}
.order-item:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(230, 59, 111, 0.08); }
.item-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  flex-shrink: 0;
}
.item-info { flex: 1; min-width: 0; }
.item-name { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-main); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.item-variant { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.variant-tag { padding: 2px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: rgba(230, 59, 111, 0.08); color: var(--primary); }
.item-sku { font-size: 0.75rem; color: var(--text-light); margin-top: 4px; word-break: break-all; overflow-wrap: anywhere; }
.item-qty { font-weight: 700; color: var(--text-secondary); font-size: 0.95rem; padding: 0 8px; }
.item-price { font-weight: 700; color: var(--text-main); font-size: 1rem; min-width: 110px; text-align: right; }
 
/* Order Summary */
.order-summary {
  border-top: 2px dashed var(--border-color);
  padding-top: 20px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: 0.92rem;
  color: var(--text-secondary);
}
.summary-row.discount span:last-child { color: #10b981; font-weight: 700; }
.summary-row.total {
  border-top: 2px solid var(--border-color);
  margin-top: 8px;
  padding-top: 14px;
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text-main);
}
.summary-row.total span:last-child { color: var(--primary); font-size: 1.2rem; }
 
/* History */
.ghn-lookup-panel {
  margin-top: 16px;
  padding: 14px;
  border: 1px dashed #93c5fd;
  border-radius: 12px;
  background: #eff6ff;
}
.ghn-lookup-title {
  font-weight: 800;
  color: #1d4ed8;
  margin-bottom: 10px;
}
.ghn-lookup-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 0.85rem;
  margin-bottom: 6px;
}
.ghn-lookup-row span { color: var(--text-muted); }
.ghn-lookup-row strong { color: var(--text-main); text-align: right; }
.ghn-lookup-desc {
  margin: 8px 0 0;
  font-size: 0.82rem;
  color: var(--text-muted);
  font-style: italic;
}
 
/* Responsive */
@media (max-width: 992px) {
  .detail-grid { grid-template-columns: 1fr; }
  .detail-header { flex-direction: column; align-items: flex-start; }
  .header-left { flex-wrap: wrap; max-width: 100%; }
  .page-title { font-size: 1.3rem; line-height: 1.4; }
  
  .timeline-card { padding: 20px; }
  .timeline-card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .timeline { flex-wrap: wrap; gap: 16px; justify-content: center; }
  .timeline-step { min-width: 70px; }
  .step-connector { display: none; }
  .step-dot { width: 42px; height: 42px; }
  .step-dot-inner svg { width: 16px; height: 16px; }
  .step-pulse { width: 42px; height: 42px; }
}

@media (max-width: 768px) {
  .order-detail-page { padding: 12px; }
  .info-card { padding: 16px; }
  
  /* Chuyển các hàng thông tin thành cột dọc trên màn hình nhỏ */
  .info-row { flex-direction: column; align-items: flex-start; gap: 4px; }
  .info-value { text-align: left; width: 100%; word-break: break-all; }
  
  /* Điều chỉnh danh sách sản phẩm */
  .order-item { flex-wrap: wrap; gap: 10px; position: relative; padding-bottom: 40px; }
  .item-img { width: 60px; height: 60px; }
  .item-qty { position: absolute; bottom: 14px; left: 14px; padding: 0; }
  .item-price { position: absolute; bottom: 14px; right: 14px; min-width: auto; }
  .item-info { width: 100%; padding-right: 0; }
  
  /* Xử lý các tiêu đề dài chứa mã */
  .card-title { flex-wrap: wrap; word-break: break-word; line-height: 1.4; }
  .ghn-lookup-row { flex-direction: column; gap: 2px; }
  .ghn-lookup-row strong { text-align: left; }
}
 
/* Cancel Modal */
.cancel-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; z-index: 1050;
}
.cancel-modal-box {
  background: var(--card-bg); border-radius: 16px; width: 100%; max-width: 480px;
  border: 1px solid var(--border-color);
  box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;
}
.cancel-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 24px; border-bottom: 1px solid var(--border-color);
}
.cancel-modal-header h5 {
  margin: 0; font-size: 1.05rem; font-weight: 700; color: #dc2626;
  display: flex; align-items: center; gap: 10px;
}
.cancel-modal-header h5 svg { color: #dc2626; }
.cancel-modal-close {
  background: none; border: none; cursor: pointer; color: var(--text-light);
  display: flex; padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.cancel-modal-close:hover { background: var(--hover-bg); color: #dc2626; }
.cancel-modal-body { padding: 20px 24px; }
.cancel-modal-desc { color: var(--text-secondary); font-size: 0.88rem; margin: 0 0 14px; }
.cancel-reason-list { display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto; }
.cancel-reason-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border: 1.5px solid var(--border-color); border-radius: 10px;
  cursor: pointer; background: var(--background); transition: all 0.15s; font-size: 0.88rem; color: var(--text-main);
}
.cancel-reason-item:hover { border-color: #dc2626; background: rgba(239, 83, 80, 0.05); }
.cancel-reason-item.selected { border-color: #dc2626; background: rgba(239, 83, 80, 0.05); }
.cancel-reason-item input[type="radio"] { accent-color: #dc2626; width: 16px; height: 16px; flex-shrink: 0; }
.cancel-custom-input {
  width: 100%; margin-top: 12px; padding: 12px; border: 1.5px solid var(--border-color);
  background: var(--background); color: var(--text-main);
  border-radius: 10px; font-size: 0.88rem; min-height: 70px; resize: vertical;
  outline: none; font-family: inherit; box-sizing: border-box;
}
.cancel-custom-input:focus { border-color: #dc2626; }
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-cancel-dismiss {
  padding: 8px 20px; border-radius: 8px; border: 1px solid var(--border-color);
  background: var(--background); color: var(--text-muted); font-weight: 600; font-size: 0.88rem;
  cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.btn-cancel-dismiss:hover { background: var(--hover-bg); }
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
  background: rgba(38, 166, 154, 0.08);
  border-color: rgba(38, 166, 154, 0.2);
}
.ps-paid .payment-auto-icon {
  background: rgba(38, 166, 154, 0.15);
  color: #4db6ac;
}
.ps-paid .payment-auto-text { color: #4db6ac; }
 
/* Unpaid */
.ps-unpaid {
  background: rgba(255, 167, 38, 0.08);
  border-color: rgba(255, 167, 38, 0.2);
}
.ps-unpaid .payment-auto-icon {
  background: rgba(255, 167, 38, 0.15);
  color: #ffb74d;
}
.ps-unpaid .payment-auto-text { color: #ffb74d; }
 
/* Pending */
.ps-pending {
  background: rgba(255, 167, 38, 0.08);
  border-color: rgba(255, 167, 38, 0.2);
}
.ps-pending .payment-auto-icon {
  background: rgba(255, 167, 38, 0.15);
  color: #ffb74d;
}
.ps-pending .payment-auto-text { color: #ffb74d; }

.ps-refund_pending {
  background: rgba(255, 167, 38, 0.08);
  border-color: rgba(255, 167, 38, 0.2);
}
.ps-refund_pending .payment-auto-icon {
  background: rgba(255, 167, 38, 0.15);
  color: #ffb74d;
}
.ps-refund_pending .payment-auto-text { color: #ffb74d; }

/* Failed */
.ps-failed {
  background: rgba(239, 83, 80, 0.08);
  border-color: rgba(239, 83, 80, 0.2);
}
.ps-failed .payment-auto-icon {
  background: rgba(239, 83, 80, 0.15);
  color: #e57373;
}
.ps-failed .payment-auto-text { color: #e57373; }

.ps-refund_failed {
  background: rgba(239, 83, 80, 0.08);
  border-color: rgba(239, 83, 80, 0.2);
}
.ps-refund_failed .payment-auto-icon {
  background: rgba(239, 83, 80, 0.15);
  color: #e57373;
}
.ps-refund_failed .payment-auto-text { color: #e57373; }
 
/* Refunded */
.ps-refunded {
  background: rgba(158, 158, 158, 0.08);
  border-color: rgba(158, 158, 158, 0.2);
}
.ps-refunded .payment-auto-icon {
  background: rgba(158, 158, 158, 0.15);
  color: #90a4ae;
}
.ps-refunded .payment-auto-text { color: #90a4ae; }
 
/* Partially refunded */
.ps-partially_refunded {
  background: rgba(158, 158, 158, 0.08);
  border-color: rgba(158, 158, 158, 0.2);
}
.ps-partially_refunded .payment-auto-icon {
  background: rgba(158, 158, 158, 0.15);
  color: #90a4ae;
}
.ps-partially_refunded .payment-auto-text { color: #90a4ae; }
</style>
