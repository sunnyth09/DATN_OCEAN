<script setup>
import { ref, reactive, computed, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import { Toast, Modal } from 'bootstrap';
import Swal from 'sweetalert2';
import { getStorageUrl } from '@/utils/url';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();
const router = useRouter();

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
  { value: 'awaiting_pickup', label: 'Chờ lấy hàng' },
  { value: 'ready_to_pick', label: 'Chờ lấy hàng' },
  { value: 'picking', label: 'Đang lấy hàng' },
  { value: 'picked_up', label: 'Đã lấy hàng' },
  { value: 'stored', label: 'Đã nhập kho' },
  { value: 'storing', label: 'Đang lưu kho' },
  { value: 'hub_inbound', label: 'Đã nhập kho trung chuyển' },
  { value: 'in_transit', label: 'Đang trung chuyển' },
  { value: 'transporting', label: 'Đang trung chuyển' },
  { value: 'hub_outbound', label: 'Đã xuất kho giao' },
  { value: 'delivering', label: 'Đang giao hàng' },
  { value: 'delivery_fail', label: 'Giao không thành công' },
  { value: 'return_requested', label: 'Yêu cầu hoàn' },
  { value: 'returning', label: 'Đang chuyển hoàn' },
  { value: 'return_approved', label: 'Đã duyệt hoàn' },
  { value: 'return_rejected', label: 'Từ chối hoàn' },
  { value: 'returned', label: 'Đã nhận hàng hoàn' },
  { value: 'refunded', label: 'Đã hoàn tiền' },
);

const shippingStatusMap = {
  ready_to_pick: { label: 'Chờ lấy hàng', color: '#d97706', bg: '#fef3c7', border: '#fcd34d', desc: 'Đơn hàng đã được tạo trên hệ thống, đang chờ bưu tá tiếp nhận và đến lấy hàng.' },
  picking: { label: 'Đang lấy hàng', color: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', desc: 'Bưu tá đang trên đường đến địa chỉ người gửi để lấy hàng.' },
  picked_up: { label: 'Đã lấy hàng', color: '#0284c7', bg: '#f0f9ff', border: '#bae6fd', desc: 'Bưu tá đã lấy hàng thành công từ người gửi.' },
  stored: { label: 'Đã nhập kho', color: '#7c3aed', bg: '#f5f3ff', border: '#ddd6fe', desc: 'Kiện hàng đã nhập kho để kiểm đếm và phân loại.' },
  storing: { label: 'Đang lưu kho', color: '#7c3aed', bg: '#f5f3ff', border: '#ddd6fe', desc: 'Kiện hàng đang được lưu giữ tại bưu cục/kho.' },
  hub_inbound: { label: 'Đã nhập kho trung chuyển', color: '#7c3aed', bg: '#f5f3ff', border: '#ddd6fe', desc: 'Kiện hàng đã đến kho trung chuyển khu vực.' },
  in_transit: { label: 'Đang trung chuyển', color: '#0284c7', bg: '#f0f9ff', border: '#bae6fd', desc: 'Kiện hàng đang được vận chuyển giữa các bưu cục/kho trung chuyển.' },
  transporting: { label: 'Đang trung chuyển', color: '#0284c7', bg: '#f0f9ff', border: '#bae6fd', desc: 'Kiện hàng đang được vận chuyển giữa các bưu cục/kho trung chuyển.' },
  hub_outbound: { label: 'Đã xuất kho giao', color: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', desc: 'Kiện hàng đã xuất kho trung chuyển, chuyển tới bưu cục phát.' },
  delivering: { label: 'Đang giao hàng', color: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', desc: 'Bưu tá đang trên đường giao kiện hàng tới địa chỉ người nhận.' },
  delivered: { label: 'Giao thành công', color: '#16a34a', bg: '#f0fdf4', border: '#bbf7d0', desc: 'Đơn hàng đã được giao thành công tới người nhận.' },
  delivery_fail: { label: 'Giao không thành công', color: '#dc2626', bg: '#fef2f2', border: '#fecaca', desc: 'Giao hàng chưa thành công, bưu tá sẽ liên hệ phát lại.' },
  failed: { label: 'Giao thất bại', color: '#dc2626', bg: '#fef2f2', border: '#fecaca', desc: 'Giao hàng không thành công.' },
  return_requested: { label: 'Yêu cầu chuyển hoàn', color: '#ea580c', bg: '#fff7ed', border: '#fed7aa', desc: 'Đơn hàng có yêu cầu chuyển hoàn về cho Shop.' },
  returning: { label: 'Đang chuyển hoàn', color: '#ea580c', bg: '#fff7ed', border: '#fed7aa', desc: 'Kiện hàng đang trên đường chuyển hoàn lại cho người gửi.' },
  returned: { label: 'Đã hoàn trả Shop', color: '#4b5563', bg: '#f3f4f6', border: '#e5e7eb', desc: 'Kiện hàng đã được hoàn trả thành công về Shop.' },
  cancelled: { label: 'Đã hủy', color: '#dc2626', bg: '#fef2f2', border: '#fecaca', desc: 'Vận đơn đã bị hủy.' },
  cancel: { label: 'Đã hủy', color: '#dc2626', bg: '#fef2f2', border: '#fecaca', desc: 'Vận đơn đã bị hủy.' },
  awaiting_pickup: { label: 'Chờ lấy hàng', color: '#d97706', bg: '#fef3c7', border: '#fcd34d', desc: 'Đang chờ hãng vận chuyển tiếp nhận kiện hàng.' },
  shipping: { label: 'Đang vận chuyển', color: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', desc: 'Đơn hàng đang trong quá trình vận chuyển.' },
};

const getShippingStatusTitle = (item) => {
  if (!item) return 'Đang xử lý';
  if (item.status_name) return item.status_name;
  if (item.status_label) return item.status_label;
  const rawKey = item.ghn_status || item.status || item.mapped_status || item.local_status;
  return shippingStatusMap[rawKey]?.label || getStatusLabel(rawKey) || rawKey;
};

const getShippingStatusDesc = (item) => {
  if (!item) return '';
  if (item.status_description) return item.status_description;
  if (item.description && !item.description.startsWith('Trạng thái:')) return item.description;
  const rawKey = item.ghn_status || item.status || item.mapped_status || item.local_status;
  return shippingStatusMap[rawKey]?.desc || item.description || '';
};

const getShippingBadgeStyle = (item) => {
  const rawKey = item?.ghn_status || item?.status || item?.mapped_status || item?.local_status;
  const meta = shippingStatusMap[rawKey];
  if (item?.status_badge?.badge_bg) {
    return {
      color: item.status_badge.badge_text || '#0f172a',
      backgroundColor: item.status_badge.badge_bg,
      borderColor: item.status_badge.badge_bg,
    };
  }
  if (meta) {
    return {
      color: meta.color,
      backgroundColor: meta.bg,
      borderColor: meta.border,
    };
  }
  return {
    color: '#2563eb',
    backgroundColor: '#eff6ff',
    borderColor: '#bfdbfe',
  };
};

const getShippingLogStageTheme = (log) => {
  const rawKey = (log?.ghn_status || log?.status || log?.mapped_status || log?.local_status || '').toLowerCase();
  const title = getShippingStatusTitle(log).toLowerCase();

  if (rawKey === 'delivered' || title.includes('giao thành công') || title.includes('đã giao')) {
    return {
      stageClass: 'stage-delivered',
      icon: 'check',
      label: 'Thành công',
    };
  }
  if (
    rawKey.includes('delivering') ||
    rawKey.includes('hub_outbound') ||
    rawKey.includes('shipping') ||
    title.includes('đang giao') ||
    title.includes('xuất kho giao') ||
    title.includes('đang vận chuyển')
  ) {
    return {
      stageClass: 'stage-delivering',
      icon: 'truck',
      label: 'Đang giao',
    };
  }
  if (
    rawKey.includes('stored') ||
    rawKey.includes('storing') ||
    rawKey.includes('in_transit') ||
    rawKey.includes('transporting') ||
    rawKey.includes('hub_inbound') ||
    rawKey.includes('in_hub') ||
    title.includes('nhập kho') ||
    title.includes('trung chuyển') ||
    title.includes('lưu kho')
  ) {
    return {
      stageClass: 'stage-transit',
      icon: 'box',
      label: 'Trung chuyển',
    };
  }
  if (
    rawKey.includes('picked') ||
    rawKey.includes('picking') ||
    title.includes('lấy hàng') ||
    title.includes('đã lấy')
  ) {
    return {
      stageClass: 'stage-picking',
      icon: 'package',
      label: 'Lấy hàng',
    };
  }
  if (
    rawKey.includes('ready_to_pick') ||
    rawKey.includes('awaiting_pickup') ||
    title.includes('chờ lấy') ||
    title.includes('chờ hãng')
  ) {
    return {
      stageClass: 'stage-waiting',
      icon: 'clock',
      label: 'Chờ lấy',
    };
  }
  if (
    rawKey.includes('fail') ||
    rawKey.includes('cancel') ||
    rawKey.includes('return') ||
    title.includes('thất bại') ||
    title.includes('hủy') ||
    title.includes('hoàn')
  ) {
    return {
      stageClass: 'stage-failed',
      icon: 'x',
      label: 'Ngoại lệ',
    };
  }
  return {
    stageClass: 'stage-default',
    icon: 'package',
    label: 'Cập nhật',
  };
};

// statusTransitions was removed because we use backend available_transitions now

const isLockedFulfillmentStatus = (status) => {
  return ['completed', 'cancelled', 'return_requested', 'return_approved', 'return_rejected', 'returned', 'refunded'].includes(status);
};

const statusActionDefinitions = {
  confirmed: { icon: 'check', label: 'Duyệt đơn', success: 'Đã duyệt đơn hàng thành công!' },
  processing: { icon: 'clock', label: 'Chuyển sang đang xử lý', success: 'Đã chuyển đơn sang đang xử lý!' },
  packing: { icon: 'clipboard-list', label: 'Chuyển sang đóng gói', success: 'Đã chuyển đơn sang đóng gói!' },
  awaiting_pickup: { icon: 'package', label: 'Chờ lấy hàng', success: 'Đã chuyển đơn sang chờ lấy hàng!' },
  completed: { icon: 'check', label: 'Hoàn thành đơn', success: 'Đã hoàn thành đơn hàng!' },
  cancelled: { icon: 'x', label: 'Hủy đơn', success: 'Đã hủy đơn hàng thành công!' },
  return_requested: { icon: 'rotate-ccw', label: 'Yêu cầu hoàn trả', success: 'Đã chuyển sang yêu cầu hoàn trả!' },
  return_approved: { icon: 'check', label: 'Duyệt hoàn trả', success: 'Đã duyệt yêu cầu hoàn trả!' },
  return_rejected: { icon: 'x', label: 'Từ chối hoàn trả', success: 'Đã từ chối yêu cầu hoàn trả!' },
  returned: { icon: 'package-check', label: 'Đã nhận hàng hoàn', success: 'Đã xác nhận nhận hàng hoàn!' },
  refunded: { icon: 'corner-down-left', label: 'Đã hoàn tiền', success: 'Đã hoàn tiền thành công!' },
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
      
      // Khóa toàn bộ các thao tác nội bộ & giao hàng nếu đơn đã đẩy sang hãng vận chuyển (GHN/OceanExpress)
      if (order.value.tracking_number && order.value.tracking_number !== 'SELF-DELIVERY') {
        const carrierBlockedStatuses = [
          'pending', 'confirmed', 'processing', 'packing', 'awaiting_pickup',
          'shipping', 'delivered', 'returning', 'returned', 'warehouse_received',
          'cancelled'
        ];
        if (carrierBlockedStatuses.includes(status)) {
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

const fetchOrder = async (autoLookup = true) => {
  loading.value = true;
  try {
    const res = await api.get(`/admin/orders/${route.params.id}`);
    if (res.data.status === 'success') {
      order.value = { ...res.data.data, _prevFulfillmentStatus: res.data.data.fulfillment_status, _prevPaymentStatus: res.data.data.payment_status };
      if (autoLookup && order.value?.tracking_number && order.value.tracking_number !== 'SELF-DELIVERY') {
        lookupGhnStatus(false, true);
      }
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
const selectedRefundType = ref('wallet'); // 'wallet' hoặc 'manual'
const cancelValidationError = ref('');
let cancelReasonResolver = null;

const showCancelReasonModal = () => {
  selectedCancelReason.value = '';
  customCancelReason.value = '';
  selectedRefundType.value = 'wallet';
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
  if (cancelReasonResolver) cancelReasonResolver({
    reason,
    refund_type: selectedRefundType.value || 'wallet'
  });
};

const dismissCancelModal = () => {
  showCancelModal.value = false;
  if (cancelReasonResolver) cancelReasonResolver(null);
};

const updateOrderStatus = async (action) => {
  if (!order.value || action.value === order.value._prevFulfillmentStatus) return;

  const payload = { fulfillment_status: action.value };
  if (action.value === 'cancelled') {
    const cancelResult = await showCancelReasonModal();
    if (!cancelResult) return;
    payload.note = cancelResult.reason;
    payload.refund_type = cancelResult.refund_type;
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

const getOtherDiscount = (orderData) => {
  if (!orderData) return 0;
  const subtotal = Number(orderData.subtotal) || 0;
  const shippingFee = Number(orderData.shipping_fee) || 0;
  const grandTotal = Number(orderData.grand_total) || 0;
  const knownDiscount = (Number(orderData.discount_amount) || 0) +
    (Number(orderData.combo_discount) || 0) +
    (Number(orderData.wallet_deposit_discount) || 0) +
    (Number(orderData.wallet_commission_discount) || 0);

  const totalCalculatedDiscount = Math.max(0, subtotal + shippingFee - grandTotal);
  const remaining = totalCalculatedDiscount - knownDiscount;
  return remaining > 0 ? remaining : 0;
};

const isPosOrder = computed(() => {
  if (!order.value) return false;
  return order.value.order_type === 'pos' || order.value.order_code?.startsWith('POS');
});

const isPosGuest = computed(() => {
  if (!order.value) return false;
  if (!isPosOrder.value) return false;
  return !order.value.user_id || order.value.user_id === order.value.seller_id;
});

const isDownloadingPosPdf = ref(false);
const downloadPosReceipt = async () => {
  if (!order.value) return;
  try {
    isDownloadingPosPdf.value = true;
    const response = await api.get(`/admin/pos/orders/${order.value.order_id}/receipt-pdf`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `hoadon_${order.value.order_code}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.parentNode.removeChild(link);
    toast.success('Đã tải hóa đơn POS thành công!');
  } catch (error) {
    toast.error('Lỗi khi tải hóa đơn POS. Vui lòng thử lại!');
    console.error('POS receipt error:', error);
  } finally {
    isDownloadingPosPdf.value = false;
  }
};

const copyToClipboard = async (text, label) => {
  if (!text) return;
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
    }
    toast.success(`Đã sao chép ${label || 'thông tin'}!`);
  } catch (e) {
    toast.error('Không thể tự động sao chép');
  }
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
    processing: 'badge-info',
    packing: 'badge-info',
    awaiting_pickup: 'badge-info',
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

  const statusLevels = {
    pending: 1,
    confirmed: 2,
    processing: 2,
    packing: 3,
    awaiting_pickup: 3, // Chờ lấy hàng = hoàn thành xác nhận/đóng gói, đang chờ shipper
    shipping: 4,
    delivered: 5,
    completed: 6,
  };
  const stepLevels = {
    pending: 1,
    confirmed: 2,
    packing: 3,
    shipping: 4,
    delivered: 5,
    completed: 6,
  };

  const currentLevel = statusLevels[order.value.fulfillment_status] || 0;
  const stepLevel = stepLevels[stepKey] || 0;

  if (order.value.fulfillment_status === 'completed') {
    return 'done';
  }

  if (stepLevel < currentLevel) return 'done';
  if (stepLevel === currentLevel) return 'active';
  return 'inactive';
};

const getStepTimestamp = (step) => {
  if (!order.value) return null;
  if (step.field && order.value[step.field]) {
    return order.value[step.field];
  }
  if (order.value.status_histories && order.value.status_histories.length > 0) {
    const history = order.value.status_histories.find(h => {
      if (h.new_status === step.key) return true;
      if (step.key === 'packing' && (h.new_status === 'awaiting_pickup' || h.new_status === 'processing')) return true;
      return false;
    });
    if (history) return history.happened_at || history.created_at;
  }
  return null;
};

const isSyncingGhn = ref(false);
const isPrinting = ref(false);
const isCanceling = ref(false);

// Multi-carrier dispatch state
const showDispatchModal = ref(false);
const isDispatching = ref(false);
const dispatchForm = reactive({
  carrier: 'ocean_express',
  weight: 500,
  length: 20,
  width: 15,
  height: 10,
  required_note: 'CHOXEMHANGKHONGTHU',
  cod_amount: 0,
});

// Shipping Label Print state
const showShippingLabelModal = ref(false);
const isFetchingLabel = ref(false);
const shippingLabelData = ref(null);

const openDispatchModal = () => {
  if (!order.value) return;
  const isPaid = order.value.payment_status === 'paid';
  const calculatedWeight = (order.value.items || []).reduce((sum, item) => sum + (Number(item.quantity || 1) * 300), 200);

  dispatchForm.carrier = 'ocean_express';
  dispatchForm.weight = calculatedWeight;
  dispatchForm.length = 20;
  dispatchForm.width = 15;
  dispatchForm.height = 10;
  dispatchForm.required_note = 'CHOXEMHANGKHONGTHU';
  dispatchForm.cod_amount = isPaid ? 0 : Number(order.value.grand_total || 0);

  showDispatchModal.value = true;
};

const closeDispatchModal = () => {
  showDispatchModal.value = false;
};

const submitDispatchShipping = async () => {
  if (!order.value) return;
  isDispatching.value = true;
  try {
    const res = await api.post(`/admin/orders/${order.value.order_id}/dispatch-shipping`, {
      carrier: dispatchForm.carrier,
      weight: dispatchForm.weight,
      length: dispatchForm.length,
      width: dispatchForm.width,
      height: dispatchForm.height,
      required_note: dispatchForm.required_note,
      cod_amount: dispatchForm.cod_amount,
    });

    if (res.data.status === 'success') {
      toast.success(res.data.message || 'Đã tạo vận đơn vận chuyển thành công!');
      showDispatchModal.value = false;
      window.dispatchEvent(new Event('admin-order-updated'));
      await fetchOrder();
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Không thể tạo vận đơn vận chuyển';
    toast.error(errorMsg);
  } finally {
    isDispatching.value = false;
  }
};

const syncGhn = async () => {
  openDispatchModal();
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

const lookupGhnStatus = async (sync = true, silent = false) => {
  if (!order.value?.tracking_number || order.value.tracking_number === 'SELF-DELIVERY') return;
  isLookingUpGhn.value = true;
  try {
    const res = await api.post('/ghn/order-detail', {
      order_code: order.value.tracking_number,
      sync,
    });
    if (res.data.status === 'success') {
      ghnLookup.value = res.data.data;
      if (!silent) toast.success(res.data.message || 'Đã tra cứu trạng thái vận chuyển');
      if (sync) await fetchOrder(false);
    }
  } catch (error) {
    if (!silent) toast.error(error.response?.data?.message || 'Không thể tra cứu trạng thái vận chuyển');
  } finally {
    isLookingUpGhn.value = false;
  }
};

const printLabel = async () => {
  if (!order.value) return;
  isFetchingLabel.value = true;
  try {
    const res = await api.get(`/admin/orders/${order.value.order_id}/shipping-label`);
    if (res.data.status === 'success') {
      shippingLabelData.value = res.data.data;
      showShippingLabelModal.value = true;
    } else {
      toast.error('Không thể lấy thông tin phiếu giao hàng');
    }
  } catch (error) {
    toast.error('Lỗi khi tải thông tin phiếu giao hàng');
  } finally {
    isFetchingLabel.value = false;
  }
};

const triggerPrintWindow = () => {
  window.print();
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

const sortedShippingLogs = computed(() => {
  const logs = ghnLookup.value?.logs;
  if (!Array.isArray(logs) || logs.length === 0) return [];
  const withIndex = logs.map((log, originalIdx) => ({ log, originalIdx }));
  withIndex.sort((a, b) => {
    const timeA = new Date(a.log.created_at || a.log.timestamp || a.log.happened_at || 0).getTime();
    const timeB = new Date(b.log.created_at || b.log.timestamp || b.log.happened_at || 0).getTime();
    if (timeB !== timeA) {
      return timeB - timeA;
    }
    return b.originalIdx - a.originalIdx;
  });
  return withIndex.map(item => item.log);
});

onMounted(() => fetchOrder());
</script>

<template>
  <div class="order-detail-page">
    <!-- Modern Skeleton Loading -->
    <div v-if="loading" class="order-detail-skeleton">
      <!-- Header Skeleton -->
      <div class="skeleton-header">
        <div class="skeleton-header-left">
          <div class="skeleton-box" style="width: 100px; height: 38px; border-radius: 8px;"></div>
          <div>
            <div class="skeleton-box" style="width: 240px; height: 30px; border-radius: 8px; margin-bottom: 8px;"></div>
            <div class="skeleton-box" style="width: 180px; height: 16px; border-radius: 6px;"></div>
          </div>
        </div>
        <div class="skeleton-header-right">
          <div class="skeleton-box" style="width: 90px; height: 32px; border-radius: 20px;"></div>
          <div class="skeleton-box" style="width: 110px; height: 32px; border-radius: 20px;"></div>
        </div>
      </div>

      <!-- Timeline Skeleton Card -->
      <div class="skeleton-card" style="margin-bottom: 24px; padding: 28px 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div class="skeleton-box" style="width: 38px; height: 38px; border-radius: 10px;"></div>
            <div class="skeleton-box" style="width: 160px; height: 22px; border-radius: 6px;"></div>
          </div>
          <div class="skeleton-box" style="width: 90px; height: 28px; border-radius: 20px;"></div>
        </div>
        <div class="skeleton-timeline-steps">
          <div v-for="i in 6" :key="i" class="skeleton-step-item">
            <div class="skeleton-box" style="width: 48px; height: 48px; border-radius: 50%; margin: 0 auto 12px;"></div>
            <div class="skeleton-box" style="width: 70px; height: 14px; margin: 0 auto 6px; border-radius: 4px;"></div>
            <div class="skeleton-box" style="width: 50px; height: 10px; margin: 0 auto; border-radius: 4px;"></div>
          </div>
        </div>
      </div>

      <!-- Grid Skeleton -->
      <div class="detail-grid">
        <!-- Left: Products & Histories Skeleton -->
        <div class="detail-main">
          <!-- Products Card Skeleton -->
          <div class="skeleton-card" style="margin-bottom: 24px;">
            <div class="skeleton-box" style="width: 140px; height: 22px; margin-bottom: 20px; border-radius: 6px;"></div>
            <div v-for="i in 2" :key="i" style="display: flex; gap: 16px; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-color, #f1f5f9);">
              <div class="skeleton-box" style="width: 64px; height: 64px; border-radius: 10px; flex-shrink: 0;"></div>
              <div style="flex: 1;">
                <div class="skeleton-box" style="width: 65%; height: 18px; margin-bottom: 8px; border-radius: 4px;"></div>
                <div class="skeleton-box" style="width: 35%; height: 14px; border-radius: 4px;"></div>
              </div>
              <div class="skeleton-box" style="width: 40px; height: 16px; border-radius: 4px;"></div>
              <div class="skeleton-box" style="width: 90px; height: 18px; border-radius: 4px;"></div>
            </div>
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
              <div style="display: flex; justify-content: space-between;"><div class="skeleton-box" style="width: 80px; height: 14px; border-radius: 4px;"></div><div class="skeleton-box" style="width: 100px; height: 14px; border-radius: 4px;"></div></div>
              <div style="display: flex; justify-content: space-between;"><div class="skeleton-box" style="width: 100px; height: 14px; border-radius: 4px;"></div><div class="skeleton-box" style="width: 80px; height: 14px; border-radius: 4px;"></div></div>
              <div style="display: flex; justify-content: space-between;"><div class="skeleton-box" style="width: 90px; height: 18px; border-radius: 4px;"></div><div class="skeleton-box" style="width: 130px; height: 22px; border-radius: 6px;"></div></div>
            </div>
          </div>

          <!-- History Card Skeleton -->
          <div class="skeleton-card">
            <div class="skeleton-box" style="width: 160px; height: 22px; margin-bottom: 20px; border-radius: 6px;"></div>
            <div v-for="i in 3" :key="i" style="display: flex; gap: 14px; margin-bottom: 16px;">
              <div class="skeleton-box" style="width: 12px; height: 12px; border-radius: 50%; margin-top: 4px; flex-shrink: 0;"></div>
              <div style="flex: 1;">
                <div class="skeleton-box" style="width: 45%; height: 16px; margin-bottom: 6px; border-radius: 4px;"></div>
                <div class="skeleton-box" style="width: 30%; height: 12px; border-radius: 4px;"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Sidebar Skeleton -->
        <div class="detail-sidebar">
          <!-- Status Action Card Skeleton -->
          <div class="skeleton-card" style="margin-bottom: 24px;">
            <div class="skeleton-box" style="width: 170px; height: 22px; margin-bottom: 16px; border-radius: 6px;"></div>
            <div class="skeleton-box" style="width: 100%; height: 42px; border-radius: 8px; margin-bottom: 16px;"></div>
            <div class="skeleton-box" style="width: 100%; height: 38px; border-radius: 8px;"></div>
          </div>

          <!-- Customer Card Skeleton -->
          <div class="skeleton-card" style="margin-bottom: 24px;">
            <div class="skeleton-box" style="width: 130px; height: 22px; margin-bottom: 16px; border-radius: 6px;"></div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <div class="skeleton-box" style="width: 70%; height: 14px; border-radius: 4px;"></div>
              <div class="skeleton-box" style="width: 85%; height: 14px; border-radius: 4px;"></div>
              <div class="skeleton-box" style="width: 60%; height: 14px; border-radius: 4px;"></div>
            </div>
          </div>

          <!-- Shipping Card Skeleton -->
          <div class="skeleton-card">
            <div class="skeleton-box" style="width: 120px; height: 22px; margin-bottom: 16px; border-radius: 6px;"></div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <div class="skeleton-box" style="width: 60%; height: 14px; border-radius: 4px;"></div>
              <div class="skeleton-box" style="width: 50%; height: 14px; border-radius: 4px;"></div>
              <div class="skeleton-box" style="width: 90%; height: 14px; border-radius: 4px;"></div>
            </div>
          </div>
        </div>
      </div>
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
                <span v-if="order.order_code && order.order_code.startsWith('FS-')" class="badge bg-warning text-dark" style="font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><AppIcon name="zap" size="14" /> Flash Sale</span>
            </h1>
            <p class="page-sub">Ngày đặt: {{ formatDate(order.created_at) }}</p>
          </div>
        </div>
        <div class="header-badges" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <button v-if="isPosOrder" class="btn-print-pos" @click="downloadPosReceipt" :disabled="isDownloadingPosPdf" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; background: #0284c7; color: #fff; font-size: 0.82rem; font-weight: 700; border: none; cursor: pointer; transition: background 0.2s;" title="Tải/In hóa đơn bán hàng tại quầy">
            <AppIcon name="printer" size="15" />
            <span>{{ isDownloadingPosPdf ? 'Đang tải...' : 'In hóa đơn POS' }}</span>
          </button>
          <span class="status-badge" :class="getStatusBadgeClass(order.fulfillment_status)">{{ getStatusLabel(order.fulfillment_status) }}</span>
          <span class="status-badge" :class="getPaymentBadgeClass(order.payment_status)">{{ paymentLabels[order.payment_status] || order.payment_status }}</span>
        </div>
      </div>

      <!-- Timeline -->
      <div class="timeline-card" v-if="order.fulfillment_status !== 'cancelled'">
        <div class="timeline-card-header">
          <div class="timeline-title-group">
            <div class="timeline-title-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="timeline-title-text">
              <div class="timeline-title-row">
                <h3 class="card-title">Tiến trình đơn hàng</h3>
                <span class="timeline-step-indicator">Bước {{ (timelineSteps.findIndex(s => getStepStatus(s.key) === 'active') + 1) || 3 }}/{{ timelineSteps.length }}</span>
              </div>
              <span class="timeline-subtitle">Theo dõi trạng thái xử lý và vận chuyển theo thời gian thực</span>
            </div>
          </div>
          <span class="timeline-badge" :class="getStatusBadgeClass(order.fulfillment_status)">
            <span class="status-indicator-dot"></span>
            {{ getStatusLabel(order.fulfillment_status) }}
          </span>
        </div>
        <div class="timeline-wrapper">
          <div class="timeline">
            <div v-for="(step, idx) in timelineSteps" :key="step.key" class="timeline-step" :class="getStepStatus(step.key)">
              <div class="step-connector" v-if="idx > 0"></div>
              <div class="step-top-meta">
                <span class="step-index">0{{ idx + 1 }}</span>
              </div>
              <div class="step-dot">
                <div class="step-dot-inner">
                  <!-- Đặt hàng -->
                  <svg v-if="step.key === 'pending'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                  </svg>
                  <!-- Xác nhận -->
                  <svg v-else-if="step.key === 'confirmed'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                  </svg>
                  <!-- Đóng gói -->
                  <svg v-else-if="step.key === 'packing'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                  </svg>
                  <!-- Vận chuyển -->
                  <svg v-else-if="step.key === 'shipping'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                  </svg>
                  <!-- Đã giao -->
                  <svg v-else-if="step.key === 'delivered'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                  </svg>
                  <!-- Hoàn thành -->
                  <svg v-else-if="step.key === 'completed'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                  </svg>
                </div>
                <div class="step-check-badge" v-if="getStepStatus(step.key) === 'done'">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="step-pulse" v-if="getStepStatus(step.key) === 'active'"></div>
              </div>
              <div class="step-info">
                <span class="step-label">{{ step.label }}</span>
                <span class="step-time is-timestamp" v-if="getStepTimestamp(step)">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  {{ formatDate(getStepTimestamp(step)) }}
                </span>
                <span class="step-time is-active-pulse" v-else-if="getStepStatus(step.key) === 'active'">
                  <span class="live-dot"></span> Đang xử lý...
                </span>
                <span class="step-time is-waiting" v-else>
                  Chờ xử lý
                </span>
              </div>
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
          <div style="flex: 1">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #dc2626;">Đơn hàng đã bị hủy</h3>
              <span v-if="order.payment_status === 'refunded'" class="status-badge badge-success sm" style="font-weight: 700;">
                ✓ Đã hoàn tiền: {{ formatPrice(order.grand_total) }}
              </span>
            </div>
            <p v-if="order.cancel_reason" style="margin: 6px 0 2px; font-size: 0.92rem; color: var(--text-main);"><strong>Lý do hủy:</strong> {{ order.cancel_reason }}</p>
            <p v-if="order.cancelled_at" style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Thời gian hủy: {{ formatDate(order.cancelled_at) }}</p>
            <div v-if="order.payment_status === 'refunded'" class="mt-2 p-2 rounded" style="background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; font-size: 0.85rem; color: #065f46;">
              💰 <strong>Xử lý hoàn tiền:</strong> Số tiền <strong>{{ formatPrice(order.grand_total) }}</strong> đã được hoàn trả lại vào Ví cá nhân của khách hàng / Chuyển khoản.
            </div>
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
              <div class="summary-row"><span>Phí vận chuyển</span><span>{{ Number(order.shipping_fee) === 0 ? 'Miễn phí (0 đ)' : formatPrice(order.shipping_fee) }}</span></div>
              <div class="summary-row discount" v-if="Number(order.discount_amount) > 0"><span>Giảm giá Voucher / Ưu đãi</span><span>-{{ formatPrice(order.discount_amount) }}</span></div>
              <div class="summary-row discount" v-if="Number(order.combo_discount) > 0"><span>Giảm giá Combo thể thao</span><span>-{{ formatPrice(order.combo_discount) }}</span></div>
              <div class="summary-row discount" v-if="Number(order.wallet_deposit_discount) > 0"><span>Khấu trừ Ví Ocean (Tiền nạp)</span><span>-{{ formatPrice(order.wallet_deposit_discount) }}</span></div>
              <div class="summary-row discount" v-if="Number(order.wallet_commission_discount) > 0"><span>Khấu trừ Ví Ocean (Hoa hồng)</span><span>-{{ formatPrice(order.wallet_commission_discount) }}</span></div>
              <div class="summary-row discount" v-if="getOtherDiscount(order) > 0"><span>Chiết khấu / Ưu đãi khác</span><span>-{{ formatPrice(getOtherDiscount(order)) }}</span></div>
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
          <!-- CARD 1: Điều phối & Thao tác đơn hàng -->
          <div class="info-card action-hub-card">
            <div class="action-hub-header">
              <div class="action-hub-title-group">
                <div class="action-hub-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                  </svg>
                </div>
                <div>
                  <h3 class="action-hub-title">Thao tác đơn hàng</h3>
                  <span class="action-hub-subtitle">Xử lý tiến trình & giao vận</span>
                </div>
              </div>

              <!-- Trạng thái thanh toán Pill ở góc phải Header -->
              <div class="payment-auto-pill" :class="'ps-' + order.payment_status">
                <span class="payment-pill-dot"></span>
                <span class="payment-pill-text">{{ paymentLabels[order.payment_status] || order.payment_status }}</span>
                <span class="payment-pill-sub">(Tự động)</span>
              </div>
            </div>

            <!-- CỤM THAO TÁC GIAO VẬN CHÍNH (PRIMARY FULFILLMENT CTAs) -->
            <div class="fulfillment-actions-box">
              <!-- Trường hợp A: Chưa đẩy đơn & Có thể giao hàng -->
              <div v-if="!order.tracking_number && ['confirmed', 'processing', 'packing', 'awaiting_pickup'].includes(order.fulfillment_status)" class="dispatch-cta-group">
                <button
                  class="btn-cta-dispatch-primary"
                  @click="syncGhn"
                  :disabled="isSyncingGhn || order.fulfillment_status === 'cancelled'"
                >
                  <div class="btn-cta-icon-wrap">
                    <AppIcon name="truck" size="22" stroke-width="2.5" />
                  </div>
                  <div class="btn-cta-text-wrap">
                    <span class="btn-cta-main-text">{{ isSyncingGhn ? 'Đang kết nối nhà vận chuyển...' : 'Đẩy đơn cho nhà vận chuyển' }}</span>
                    <span class="btn-cta-sub-text">Hệ thống Ocean Express / GHN tự động</span>
                  </div>
                </button>

                <div class="dispatch-secondary-row">
                  <button
                    class="btn-cta-self-delivery"
                    @click="confirmSelfDelivery"
                    :disabled="isSelfDelivering || order.fulfillment_status === 'cancelled'"
                  >
                    <AppIcon name="user-check" size="16" class="me-1" />
                    <span>{{ isSelfDelivering ? 'Đang xử lý...' : 'Shop tự đi giao' }}</span>
                  </button>

                  <button v-if="isPosOrder" class="btn-cta-pos-receipt" @click="downloadPosReceipt" :disabled="isDownloadingPosPdf">
                    <AppIcon name="printer" size="16" class="me-1" />
                    <span>{{ isDownloadingPosPdf ? 'Đang tải...' : 'In hóa đơn POS' }}</span>
                  </button>
                </div>
              </div>

              <!-- Trường hợp B: Đã đẩy đơn sang Hãng vận chuyển (Có tracking code) -->
              <div v-else-if="order.tracking_number && order.tracking_number !== 'SELF-DELIVERY'" class="waybill-cta-group">
                <div class="waybill-badge-banner">
                  <div class="waybill-badge-left">
                    <div class="waybill-carrier-dot"></div>
                    <div>
                      <span class="waybill-lbl">Mã vận đơn đối tác</span>
                      <strong class="waybill-code">{{ order.tracking_number }}</strong>
                    </div>
                  </div>
                  <button type="button" class="btn-copy-chip" @click="copyToClipboard(order.tracking_number, 'Mã vận đơn')" title="Sao chép mã">
                    <AppIcon name="copy" size="12" />
                  </button>
                </div>

                <div class="waybill-action-buttons">
                  <button class="btn-lookup-ghn" @click="lookupGhnStatus(true)" :disabled="isLookingUpGhn">
                    <AppIcon name="search" size="15" class="me-1" />
                    <span>{{ isLookingUpGhn ? 'Đang tra...' : 'Tra cứu đơn' }}</span>
                  </button>
                  <button class="btn-print" @click="printLabel" :disabled="isFetchingLabel">
                    <AppIcon name="printer" size="15" class="me-1" />
                    <span>{{ isFetchingLabel ? 'Đang tải...' : 'In phiếu vận đơn' }}</span>
                  </button>
                  <button class="btn-cancel-ghn" @click="cancelGhnOrder" :disabled="isCanceling">
                    <AppIcon name="x-circle" size="15" class="me-1" />
                    <span>Hủy vận đơn</span>
                  </button>
                </div>
              </div>

              <!-- Trường hợp C: Shop tự giao -->
              <div v-else-if="order.tracking_number === 'SELF-DELIVERY'" class="self-delivery-banner">
                <div class="self-delivery-info">
                  <AppIcon name="user-check" size="20" class="text-success" />
                  <div>
                    <strong class="text-success">Đơn do Shop tự giao</strong>
                    <p class="mb-0 text-muted" style="font-size: 0.8rem">Nhân viên cửa hàng giao hàng trực tiếp cho khách</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- THÔNG BÁO TỰ ĐỘNG NẾU CÓ VẬN ĐƠN -->
            <div v-if="order.tracking_number && order.tracking_number !== 'SELF-DELIVERY' && !getCurrentOrderStatusActions().length" class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size: 0.84rem">
              <AppIcon name="info" size="14" class="me-1" /> Trạng thái đang được đồng bộ tự động từ hãng vận chuyển.
            </div>

            <!-- CÁC THAO TÁC TRẠNG THÁI KHÁC (HỦY ĐƠN, DUYỆT ĐƠN...) -->
            <div class="status-other-section" v-if="getCurrentOrderStatusActions().length">
              <div class="section-micro-title">Thao tác trạng thái</div>
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
                  <AppIcon :name="action.icon" size="16" stroke-width="2.5" />
                  <span>{{ action.label }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- CARD 2: Khách hàng & Giao nhận -->
          <div class="info-card customer-shipping-card">
            <div class="card-header-flex">
              <h3 class="card-title mb-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Khách hàng & Nhận hàng
              </h3>
              <span v-if="isPosOrder" class="status-badge badge-info sm">Đơn tại quầy (POS)</span>
            </div>

            <!-- Dải thông tin khách hàng đặt đơn -->
            <div class="customer-profile-strip">
              <div class="customer-avatar-badge">
                {{ (order.recipient_name || order.user?.name || 'K').charAt(0).toUpperCase() }}
              </div>
              <div class="customer-meta-info">
                <div class="customer-name-row">
                  <strong class="customer-name">{{ isPosGuest ? (order.recipient_name || 'Khách lẻ') : (order.user?.full_name || order.user?.name || order.recipient_name || 'Khách lẻ') }}</strong>
                  <span v-if="order.user_id && !isPosGuest" class="member-chip">Khách hàng</span>
                </div>
                <div class="customer-sub-contacts">
                  <span v-if="order.user?.email && !isPosGuest" class="contact-email">
                    <AppIcon name="mail" size="12" class="me-1" />{{ order.user.email }}
                  </span>
                  <span v-if="order.seller" class="contact-seller">
                    <AppIcon name="user" size="12" class="me-1" />Thu ngân: {{ order.seller.full_name || order.seller.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Phân cách sang thông tin giao hàng -->
            <div class="shipping-section-divider">
              <span class="shipping-divider-label">
                <AppIcon name="map-pin" size="13" class="me-1 text-primary" /> Thông tin giao nhận
              </span>
            </div>

            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Người nhận</span>
                <span class="info-value fw-bold">{{ order.recipient_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Điện thoại</span>
                <span class="info-value text-phone-highlight">
                  <a :href="'tel:' + order.recipient_phone" class="phone-link">{{ order.recipient_phone || '—' }}</a>
                  <button v-if="order.recipient_phone" type="button" class="btn-copy-chip ms-2" @click="copyToClipboard(order.recipient_phone, 'Số điện thoại')" title="Sao chép SĐT">
                    <AppIcon name="copy" size="12" />
                  </button>
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">Địa chỉ giao</span>
                <span class="info-value address-highlight">
                  <span>{{ order.shipping_address }}</span>
                  <button v-if="order.shipping_address" type="button" class="btn-copy-chip ms-2" @click="copyToClipboard(order.shipping_address, 'Địa chỉ giao hàng')" title="Sao chép địa chỉ">
                    <AppIcon name="copy" size="12" />
                  </button>
                </span>
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
            <!-- Panel thông tin vận đơn chuyên nghiệp -->
            <div v-if="ghnLookup" class="shipping-carrier-panel">
              <div class="shipping-panel-header">
                <div class="carrier-brand">
                  <div class="carrier-icon" :class="order.tracking_number?.startsWith('OE-') ? 'is-oe' : 'is-ghn'">
                    <AppIcon name="truck" size="20" stroke-width="2.5" />
                  </div>
                  <div>
                    <div class="carrier-name">{{ ghnLookup.carrier || (order.tracking_number?.startsWith('OE-') ? 'Ocean Express' : 'Giao Hàng Nhanh') }}</div>
                    <div class="carrier-code-wrap">
                      <span class="carrier-code-label">Mã vận đơn:</span>
                      <strong class="carrier-tracking-code">{{ ghnLookup.order_code || order.tracking_number }}</strong>
                    </div>
                  </div>
                </div>
                
                <span class="carrier-status-badge" :style="getShippingBadgeStyle(ghnLookup)">
                  {{ getShippingStatusTitle(ghnLookup) }}
                </span>
              </div>

              <!-- Mô tả trạng thái thực tế -->
              <div class="shipping-status-desc" v-if="getShippingStatusDesc(ghnLookup)">
                <div class="desc-icon">
                  <AppIcon name="alert-circle" size="16" />
                </div>
                <p>{{ getShippingStatusDesc(ghnLookup) }}</p>
              </div>

              <!-- Chi tiết thông tin -->
              <div class="shipping-info-grid">
                <div class="shipping-info-item" v-if="ghnLookup.happened_at">
                  <span class="info-k">
                    <AppIcon name="clock" size="13" /> Cập nhật:
                  </span>
                  <span class="info-v">{{ formatDate(ghnLookup.happened_at) }}</span>
                </div>
                <div class="shipping-info-item" v-if="ghnLookup.location">
                  <span class="info-k">
                    <AppIcon name="map-pin" size="13" /> Địa chỉ phát:
                  </span>
                  <span class="info-v">{{ ghnLookup.location }}</span>
                </div>
              </div>

              <!-- Lịch sử hành trình / Tracking Logs nếu có -->
              <div v-if="sortedShippingLogs.length > 0" class="shipping-logs-section">
                <div class="logs-title-bar">
                  <div class="logs-title-left">
                    <span class="logs-title-icon">
                      <AppIcon name="clock" size="14" />
                    </span>
                    <span class="logs-title-text">Hành trình vận đơn</span>
                  </div>
                  <span class="logs-count-chip">{{ sortedShippingLogs.length }} mốc</span>
                </div>

                <div class="shipping-logs-timeline">
                  <div
                    v-for="(log, idx) in sortedShippingLogs"
                    :key="idx"
                    class="log-item"
                    :class="[getShippingLogStageTheme(log).stageClass, { 'latest': idx === 0 }]"
                  >
                    <div class="log-dot">
                      <span class="log-dot-icon">
                        <AppIcon :name="getShippingLogStageTheme(log).icon" size="10" stroke-width="3" />
                      </span>
                      <div v-if="idx === 0" class="log-dot-pulse"></div>
                    </div>
                    <div class="log-content">
                      <div class="log-status-row">
                        <div class="log-title-group">
                          <span class="log-status">{{ getShippingStatusTitle(log) }}</span>
                          <span v-if="idx === 0" class="badge-latest-pill">Mới nhất</span>
                        </div>
                        <span class="log-time">
                          <AppIcon name="clock" size="11" />
                          {{ formatDate(log.created_at || log.timestamp || log.happened_at) }}
                        </span>
                      </div>
                      <div class="log-note" v-if="log.note">
                        <span class="log-note-bar"></span>
                        <p class="log-note-text">{{ log.note }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Shipping Dispatch Modal -->
    <Transition name="modal">
      <div v-if="showDispatchModal" class="cancel-modal-overlay" @click.self="closeDispatchModal">
        <div class="dispatch-modal-box">
          <div class="cancel-modal-header">
            <h5>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              Điều phối Vận chuyển Đơn hàng
            </h5>
            <button class="cancel-modal-close" @click="closeDispatchModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="dispatch-modal-body">
            <!-- Carrier Selection -->
            <label class="dispatch-section-label">1. Chọn Đối tác Vận chuyển</label>
            <div class="carrier-selector-grid">
              <label class="carrier-card" :class="{ active: dispatchForm.carrier === 'ocean_express' }">
                <input type="radio" v-model="dispatchForm.carrier" value="ocean_express" />
                <div class="carrier-card-content">
                  <div class="carrier-badge oe">Ocean Express</div>
                  <strong class="carrier-title">Logistics Tích Hợp</strong>
                  <span class="carrier-desc">Đồng bộ tự động theo tọa độ phường/xã và quản trị nội bộ</span>
                </div>
              </label>

              <label class="carrier-card" :class="{ active: dispatchForm.carrier === 'ghn' }">
                <input type="radio" v-model="dispatchForm.carrier" value="ghn" />
                <div class="carrier-card-content">
                  <div class="carrier-badge ghn">GHN Express</div>
                  <strong class="carrier-title">Giao Hàng Nhanh</strong>
                  <span class="carrier-desc">Đối tác vận tải liên tỉnh phủ sóng 63 tỉnh thành</span>
                </div>
              </label>

              <label class="carrier-card" :class="{ active: dispatchForm.carrier === 'self_delivery' }">
                <input type="radio" v-model="dispatchForm.carrier" value="self_delivery" />
                <div class="carrier-card-content">
                  <div class="carrier-badge self">Shop tự giao</div>
                  <strong class="carrier-title">Nội bộ / Shipper riêng</strong>
                  <span class="carrier-desc">Giao hỏa tốc nội thành bởi đội ngũ nhân viên cửa hàng</span>
                </div>
              </label>
            </div>

            <!-- Package Specs -->
            <label class="dispatch-section-label mt-3">2. Thông số Kiện hàng & Thu hộ (COD)</label>
            <div class="package-specs-grid">
              <div class="spec-field">
                <label>Trọng lượng (gram)</label>
                <input v-model.number="dispatchForm.weight" type="number" min="10" class="dispatch-input" />
              </div>
              <div class="spec-field">
                <label>Dài x Rộng x Cao (cm)</label>
                <div class="dimensions-input-group">
                  <input v-model.number="dispatchForm.length" type="number" min="1" placeholder="D" />
                  <span>×</span>
                  <input v-model.number="dispatchForm.width" type="number" min="1" placeholder="R" />
                  <span>×</span>
                  <input v-model.number="dispatchForm.height" type="number" min="1" placeholder="C" />
                </div>
              </div>
              <div class="spec-field full">
                <label>Tiền thu hộ COD (0 đ nếu khách đã thanh toán trước)</label>
                <input v-model.number="dispatchForm.cod_amount" type="number" min="0" class="dispatch-input text-danger fw-bold" />
              </div>
              <div class="spec-field full">
                <label>Lưu ý giao hàng</label>
                <select v-model="dispatchForm.required_note" class="dispatch-select">
                  <option value="CHOXEMHANGKHONGTHU">Cho xem hàng không cho thử</option>
                  <option value="CHOTHUHANG">Cho thử hàng</option>
                  <option value="KHONGCHOXEMHANG">Không cho xem hàng</option>
                </select>
              </div>
            </div>
          </div>
          <div class="cancel-modal-footer">
            <button class="btn-cancel-dismiss" @click="closeDispatchModal">Hủy bỏ</button>
            <button class="btn-dispatch-submit" :disabled="isDispatching" @click="submitDispatchShipping">
              {{ isDispatching ? 'Đang tạo vận đơn...' : 'Xác nhận Đẩy Vận Đơn' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Shipping Label (Waybill) Print Modal -->
    <Transition name="modal">
      <div v-if="showShippingLabelModal" class="cancel-modal-overlay" @click.self="showShippingLabelModal = false">
        <div class="waybill-modal-box">
          <div class="cancel-modal-header no-print">
            <h5 style="display: flex; align-items: center; gap: 8px;">
              <AppIcon name="file-text" size="18" />
              <span>Phiếu Giao Hàng & Mã Vận Đơn</span>
            </h5>
            <div class="d-flex gap-2">
              <button class="btn-print-action" @click="triggerPrintWindow">
                <AppIcon name="printer" size="16" />
                In phiếu ngay
              </button>
              <button class="cancel-modal-close" @click="showShippingLabelModal = false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>

          <!-- Printable Waybill Document -->
          <div v-if="shippingLabelData" class="printable-waybill-content" id="printable-waybill">
            <div class="waybill-header">
              <div class="waybill-brand">
                <h2>OCEAN SPORT</h2>
                <p>Hotline: 1900 8888 · Website: oceansport.vn</p>
              </div>
              <div class="waybill-carrier-badge">
                <strong>{{ shippingLabelData.carrier }}</strong>
                <span class="waybill-barcode-text">{{ shippingLabelData.tracking_number }}</span>
              </div>
            </div>

            <div class="waybill-addresses-grid">
              <div class="address-box sender">
                <span class="box-title">TỪ (GỬI):</span>
                <strong>{{ shippingLabelData.sender?.name || 'Ocean Sport Store' }}</strong>
                <p>{{ shippingLabelData.sender?.phone }}</p>
                <p>{{ shippingLabelData.sender?.address }}</p>
              </div>
              <div class="address-box receiver">
                <span class="box-title">ĐẾN (NHẬN):</span>
                <strong>{{ shippingLabelData.receiver?.name }}</strong>
                <p class="font-bold text-danger">{{ shippingLabelData.receiver?.phone }}</p>
                <p>{{ shippingLabelData.receiver?.address }}</p>
              </div>
            </div>

            <div class="waybill-items-table">
              <table class="table-compact">
                <thead>
                  <tr>
                    <th>Sản phẩm</th>
                    <th>Phân loại</th>
                    <th class="text-center">SL</th>
                    <th class="text-right">Đơn giá</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(it, i) in shippingLabelData.items" :key="i">
                    <td><strong>{{ it.name }}</strong></td>
                    <td>{{ it.variant || 'Tiêu chuẩn' }}</td>
                    <td class="text-center">{{ it.quantity }}</td>
                    <td class="text-right">{{ formatPrice(it.price) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="waybill-footer-grid">
              <div class="cod-settlement-box">
                <span class="cod-title">TIỀN THU HỘ (COD):</span>
                <strong class="cod-amount">{{ formatPrice(shippingLabelData.cod_amount) }}</strong>
                <span v-if="shippingLabelData.is_paid" class="badge-paid">ĐÃ THANH TOÁN TRƯỚC (0 đ)</span>
              </div>
              <div class="signature-box">
                <p>Chữ ký người nhận</p>
                <span>(Xác nhận nguyên vẹn, đủ hàng)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Cancel Reason Modal -->
    <Transition name="modal">
      <div v-if="showCancelModal" class="cancel-modal-overlay" @click.self="dismissCancelModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Hủy đơn hàng
            </h5>
            <button class="cancel-modal-close" @click="dismissCancelModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="cancel-modal-body">
            <!-- Cảnh báo hoàn tiền nếu đơn đã thanh toán -->
            <div v-if="order && (order.payment_status === 'paid' || order.payment_status === 'refunded')" class="refund-alert-box mb-3">
              <div class="refund-alert-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Đơn hàng đã thanh toán thành công</span>
              </div>
              <div class="refund-alert-amount">
                Số tiền hoàn trả: <strong>{{ formatPrice(order.grand_total) }}</strong>
              </div>
              <div class="refund-method-options mt-2">
                <label class="refund-option" :class="{ active: selectedRefundType === 'wallet' }">
                  <input type="radio" v-model="selectedRefundType" value="wallet" />
                  <div class="refund-option-text">
                    <span class="refund-option-title">⚡ Tự động hoàn vào Ví Ocean Sport</span>
                    <small class="refund-option-sub">Cộng trực tiếp vào số dư ví của khách để mua sắm lại hoặc rút tiền</small>
                  </div>
                </label>
                <label class="refund-option" :class="{ active: selectedRefundType === 'manual' }">
                  <input type="radio" v-model="selectedRefundType" value="manual" />
                  <div class="refund-option-text">
                    <span class="refund-option-title">🏦 Đã hoàn tiền thủ công</span>
                    <small class="refund-option-sub">Admin đã chuyển khoản qua STK ngân hàng của khách ngoài hệ thống</small>
                  </div>
                </label>
              </div>
            </div>

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
 
/* ===== Modern Skeleton Loading ===== */
.order-detail-skeleton {
  width: 100%;
  pointer-events: none;
}

.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.skeleton-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.skeleton-header-right {
  display: flex;
  gap: 8px;
  align-items: center;
}

.skeleton-card {
  background: var(--card-bg, #ffffff);
  border-radius: 16px;
  padding: 24px;
  border: 1px solid var(--border-color, #e9ecef);
  box-shadow: var(--shadow-card, 0 4px 15px rgba(45, 52, 70, 0.08));
}

.skeleton-timeline-steps {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  text-align: center;
}

@media (max-width: 768px) {
  .skeleton-timeline-steps {
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }
}

.skeleton-step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.35) 30%,
    rgba(255, 255, 255, 0.7) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% { transform: translateX(100%); }
}
 
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
 
/* ====== Timeline Card — Ultra Premium Stepper Design ====== */
.timeline-card {
  background: var(--card-bg, #ffffff);
  border-radius: 20px;
  padding: 28px 32px 32px;
  margin-bottom: 24px;
  border: 1px solid var(--border-color, rgba(226, 232, 240, 0.8));
  box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}
.timeline-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3.5px;
  background: linear-gradient(90deg, #e63b6f 0%, #fb7185 50%, #f43f5e 100%);
}

.timeline-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 28px;
  padding-bottom: 18px;
  border-bottom: 1px solid var(--border-color, rgba(241, 245, 249, 0.9));
}
.timeline-title-group {
  display: flex;
  align-items: center;
  gap: 14px;
}
.timeline-title-icon {
  width: 44px;
  height: 44px;
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.12) 0%, rgba(244, 63, 94, 0.2) 100%);
  border: 1px solid rgba(230, 59, 111, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #e63b6f;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.12);
}
.timeline-title-text {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.timeline-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.timeline-card-header .card-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
  letter-spacing: -0.2px;
}
.timeline-step-indicator {
  font-size: 0.72rem;
  font-weight: 700;
  background: rgba(230, 59, 111, 0.08);
  color: #e63b6f;
  border: 1px solid rgba(230, 59, 111, 0.2);
  padding: 2px 10px;
  border-radius: 999px;
  letter-spacing: 0.3px;
}
.timeline-subtitle {
  font-size: 0.8rem;
  color: var(--text-light, #64748b);
  font-weight: 500;
}
.timeline-badge {
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.3px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.status-indicator-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
  animation: liveDotBlink 1.4s ease-in-out infinite alternate;
}

.timeline-wrapper {
  position: relative;
  width: 100%;
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
  min-width: 125px;
  position: relative;
  transition: transform 0.25s ease;
}
.timeline-step:hover {
  transform: translateY(-2px);
}

/* Step top meta (01, 02, 03) */
.step-top-meta {
  height: 20px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.step-index {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.6px;
  color: var(--text-light, #94a3b8);
  font-family: 'Inter', system-ui, sans-serif;
  transition: all 0.3s ease;
}
.timeline-step.done .step-index {
  color: var(--text-main, #0f172a);
  font-weight: 800;
}
.timeline-step.active .step-index {
  color: #e63b6f;
  font-weight: 900;
}

/* Connector line - Strictly spans between circles, never crossing inside */
.step-connector {
  position: absolute;
  top: 54px;
  left: calc(-50% + 28px);
  right: calc(50% + 28px);
  height: 4px;
  background: var(--border-color, #eef2f6);
  z-index: 0;
  border-radius: 999px;
  transition: all 0.4s ease;
}
.timeline-step.done .step-connector {
  background: linear-gradient(90deg, #e63b6f, #f43f5e);
  box-shadow: 0 1px 4px rgba(230, 59, 111, 0.25);
}
.timeline-step.active .step-connector {
  left: calc(-50% + 28px);
  right: calc(50% + 32px);
  background: linear-gradient(90deg, #e63b6f, #f43f5e);
  box-shadow: 0 1px 4px rgba(230, 59, 111, 0.25);
}
.timeline-step.active + .timeline-step .step-connector {
  left: calc(-50% + 32px);
  right: calc(50% + 28px);
  background: linear-gradient(90deg, rgba(230, 59, 111, 0.35) 0%, var(--border-color, #eef2f6) 100%);
}

/* Step dot */
.step-dot {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--card-bg, #ffffff);
  border: 2px solid var(--border-color, #e2e8f0);
  z-index: 2;
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
  position: relative;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.step-dot-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-light, #94a3b8);
  transition: all 0.3s ease;
}
.step-dot-inner svg {
  transition: all 0.3s ease;
}

/* Done state */
.timeline-step.done .step-dot {
  background: var(--card-bg, #ffffff);
  border-color: #e63b6f;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.16);
}
.timeline-step.done .step-dot-inner {
  color: #e63b6f;
}

/* Checkmark badge */
.step-check-badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #10b981;
  color: #ffffff;
  border: 2px solid #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.35);
  animation: checkPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  z-index: 3;
}
@keyframes checkPop {
  0% { transform: scale(0); }
  100% { transform: scale(1); }
}

/* Active state - Centerpiece */
.timeline-step.active .step-dot {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #e63b6f 0%, #ff4778 50%, #f43f5e 100%);
  border: 3.5px solid #ffffff;
  box-shadow: 0 0 0 4px rgba(230, 59, 111, 0.2), 0 10px 24px -2px rgba(230, 59, 111, 0.45);
  transform: translateY(-3px);
  z-index: 2;
}
.timeline-step.active .step-dot-inner {
  color: #ffffff;
}
.timeline-step.active .step-dot-inner svg {
  width: 24px;
  height: 24px;
  filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.2));
  animation: iconGlow 2.4s ease-in-out infinite alternate;
}

@keyframes iconGlow {
  0% { transform: scale(1); }
  100% { transform: scale(1.1); }
}

/* Pulse aura for active step */
.step-pulse {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(230, 59, 111, 0.25) 0%, rgba(230, 59, 111, 0.08) 55%, transparent 75%);
  animation: pulseAura 2.4s ease-out infinite;
  pointer-events: none;
  z-index: -1;
}
@keyframes pulseAura {
  0% { transform: translate(-50%, -50%) scale(1); opacity: 0.9; }
  60% { transform: translate(-50%, -50%) scale(1.45); opacity: 0; }
  100% { transform: translate(-50%, -50%) scale(1.45); opacity: 0; }
}

/* Step info */
.step-info {
  text-align: center;
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-height: 56px;
}
.step-label {
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--text-light, #64748b);
  display: block;
  transition: all 0.3s ease;
  letter-spacing: 0.1px;
}
.timeline-step.done .step-label {
  color: var(--text-main, #0f172a);
  font-weight: 700;
}
.timeline-step.active .step-label {
  color: #e63b6f;
  font-weight: 800;
  font-size: 0.94rem;
}

/* Harmonized Step Time Capsules */
.step-time {
  height: 26px;
  border-radius: 999px;
  padding: 0 12px;
  font-size: 0.72rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  transition: all 0.3s ease;
  line-height: 1;
}
.step-time.is-timestamp {
  color: var(--text-main, #334155);
  background: var(--surface-container-low, #f8fafc);
  border: 1px solid var(--border-color, #e2e8f0);
}
.timeline-step.active .step-time.is-timestamp {
  color: #e63b6f;
  background: rgba(230, 59, 111, 0.08);
  border-color: rgba(230, 59, 111, 0.25);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.15);
}
.step-time.is-active-pulse {
  color: #e63b6f;
  background: rgba(230, 59, 111, 0.08);
  border: 1px solid rgba(230, 59, 111, 0.25);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(230, 59, 111, 0.15);
}
.step-time.is-waiting {
  color: var(--text-light, #94a3b8);
  background: var(--surface-container-low, #f8fafc);
  border: 1px dashed var(--border-color, #cbd5e1);
  font-weight: 500;
}
.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #e63b6f;
  display: inline-block;
  animation: liveDotBlink 1.4s ease-in-out infinite alternate;
}
@keyframes liveDotBlink {
  0% { opacity: 0.3; transform: scale(0.8); }
  100% { opacity: 1; transform: scale(1.2); }
}
 
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
 
/* ====== ACTION HUB CARD ====== */
.action-hub-card {
  border-top: 3.5px solid #e63b6f;
  position: relative;
}
.action-hub-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 14px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border-color, #eef2f6);
  flex-wrap: wrap;
  gap: 10px;
}
.action-hub-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}
.action-hub-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #fff0f4 0%, #ffe4ec 100%);
  color: #e63b6f;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.15);
}
.action-hub-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
  line-height: 1.25;
}
.action-hub-subtitle {
  font-size: 0.76rem;
  color: var(--text-light, #64748b);
  font-weight: 500;
  display: block;
}

/* Payment Status Compact Pill */
.payment-auto-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 700;
  border: 1px solid;
}
.payment-pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
  animation: liveDotBlink 1.4s ease-in-out infinite alternate;
}
.payment-pill-sub {
  font-size: 0.7rem;
  opacity: 0.7;
  font-weight: 500;
}

/* Primary Dispatch Button */
.fulfillment-actions-box {
  margin-bottom: 12px;
}
.btn-cta-dispatch-primary {
  width: 100%;
  padding: 13px 18px;
  border-radius: 12px;
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%);
  color: #ffffff;
  border: none;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.32);
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: left;
}
.btn-cta-dispatch-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 7px 22px rgba(37, 99, 235, 0.45);
  filter: brightness(1.04);
}
.btn-cta-dispatch-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  transform: none;
}
.btn-cta-icon-wrap {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  backdrop-filter: blur(4px);
}
.btn-cta-text-wrap {
  display: flex;
  flex-direction: column;
}
.btn-cta-main-text {
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 0.2px;
  line-height: 1.25;
}
.btn-cta-sub-text {
  font-size: 0.74rem;
  opacity: 0.85;
  margin-top: 2px;
  font-weight: 500;
}

/* Secondary Dispatch Buttons */
.dispatch-secondary-row {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}
.btn-cta-self-delivery {
  flex: 1;
  padding: 10px 14px;
  border-radius: 10px;
  background: #f0fdf4;
  color: #15803d;
  border: 1.5px solid #bbf7d0;
  font-weight: 700;
  font-size: 0.86rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-cta-self-delivery:hover:not(:disabled) {
  background: #dcfce7;
  border-color: #86efac;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
}
.btn-cta-self-delivery:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cta-pos-receipt {
  padding: 10px 14px;
  border-radius: 10px;
  background: #f8fafc;
  color: #334155;
  border: 1.5px solid #e2e8f0;
  font-weight: 700;
  font-size: 0.86rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-cta-pos-receipt:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
  transform: translateY(-1px);
}
.btn-cta-pos-receipt:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Waybill Status Banner (When dispatched) */
.waybill-cta-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.waybill-badge-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 1.5px solid #bae6fd;
  border-radius: 12px;
  padding: 12px 14px;
}
.waybill-badge-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.waybill-carrier-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #0284c7;
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25);
}
.waybill-lbl {
  font-size: 0.74rem;
  color: #0369a1;
  font-weight: 600;
  display: block;
}
.waybill-code {
  font-size: 0.94rem;
  font-family: monospace;
  color: #0c4a6e;
  font-weight: 800;
  letter-spacing: 0.5px;
}
.waybill-action-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* Self Delivery Banner */
.self-delivery-banner {
  background: #f0fdf4;
  border: 1.5px solid #bbf7d0;
  border-radius: 12px;
  padding: 14px;
}
.self-delivery-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Status Other Actions (Cancel, Confirmed, etc.) */
.status-other-section {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px dashed var(--border-color, #e2e8f0);
}
.section-micro-title {
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-light, #94a3b8);
  margin-bottom: 10px;
}

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
.btn-status-large:disabled { opacity: 0.6; cursor: not-allowed; }

/* ====== CUSTOMER & SHIPPING CARD ====== */
.customer-shipping-card {
  position: relative;
}
.card-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.customer-profile-strip {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-container-low, #f8fafc);
  border: 1px solid var(--border-color, #e2e8f0);
  margin-bottom: 16px;
}
.customer-avatar-badge {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e63b6f 0%, #f43f5e 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 800;
  flex-shrink: 0;
  box-shadow: 0 3px 8px rgba(230, 59, 111, 0.25);
}
.customer-meta-info {
  flex: 1;
  min-width: 0;
}
.customer-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.customer-name {
  font-size: 0.94rem;
  font-weight: 800;
  color: var(--text-main, #0f172a);
}
.member-chip {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  background: rgba(230, 59, 111, 0.1);
  color: #e63b6f;
}
.customer-sub-contacts {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.78rem;
  color: var(--text-light, #64748b);
  margin-top: 2px;
  flex-wrap: wrap;
}
.contact-email, .contact-seller {
  display: inline-flex;
  align-items: center;
}

/* Shipping Section Divider */
.shipping-section-divider {
  display: flex;
  align-items: center;
  padding-bottom: 12px;
  margin-bottom: 12px;
  border-bottom: 1px dashed var(--border-color, #e2e8f0);
}
.shipping-divider-label {
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-secondary, #475569);
  display: inline-flex;
  align-items: center;
}

/* Copy chip button */
.btn-copy-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid var(--border-color, #cbd5e1);
  background: var(--card-bg, #ffffff);
  color: var(--text-light, #64748b);
  cursor: pointer;
  transition: all 0.2s ease;
  vertical-align: middle;
}
.btn-copy-chip:hover {
  color: #e63b6f;
  border-color: #e63b6f;
  background: rgba(230, 59, 111, 0.08);
  transform: scale(1.08);
}
.phone-link {
  color: #0284c7;
  text-decoration: none;
  font-weight: 700;
}
.phone-link:hover {
  text-decoration: underline;
}
.text-phone-highlight {
  display: inline-flex;
  align-items: center;
}
.address-highlight {
  display: inline-flex;
  align-items: center;
  text-align: right;
  line-height: 1.4;
}

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
 
/* Carrier Shipping Panel */
.shipping-carrier-panel {
  margin-top: 16px;
  padding: 16px;
  background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}
.shipping-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
  flex-wrap: wrap;
}
.carrier-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.carrier-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
  flex-shrink: 0;
}
.carrier-icon.is-ghn {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25);
}
.carrier-name {
  font-size: 0.92rem;
  font-weight: 800;
  color: #0f172a;
}
.carrier-code-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
}
.carrier-code-label {
  font-size: 0.76rem;
  color: #64748b;
}
.carrier-tracking-code {
  font-size: 0.84rem;
  font-family: monospace;
  color: #0284c7;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 1px 6px;
  border-radius: 5px;
  letter-spacing: 0.3px;
}
.carrier-status-badge {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid;
  letter-spacing: 0.2px;
  white-space: nowrap;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}
.shipping-status-desc {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 10px;
  background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
  border: 1px solid #bfdbfe;
  margin-bottom: 14px;
}
.shipping-status-desc .desc-icon {
  color: #2563eb;
  margin-top: 1px;
  flex-shrink: 0;
}
.shipping-status-desc p {
  margin: 0;
  font-size: 0.82rem;
  color: #1e40af;
  line-height: 1.45;
  font-weight: 600;
}
.shipping-info-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 14px;
}
.shipping-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.82rem;
}
.shipping-info-item .info-k {
  color: #64748b;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.shipping-info-item .info-v {
  font-weight: 700;
  color: #0f172a;
  text-align: right;
}
.shipping-logs-section {
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1.5px dashed #cbd5e1;
}
.logs-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.logs-title-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.logs-title-icon {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  background: #e0f2fe;
  color: #0284c7;
  display: flex;
  align-items: center;
  justify-content: center;
}
.logs-title-text {
  font-size: 0.9rem;
  font-weight: 800;
  color: #0f172a;
}
.logs-count-chip {
  background: #f1f5f9;
  color: #475569;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
}
.shipping-logs-timeline {
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  padding-left: 26px;
  margin-left: 10px;
}
.shipping-logs-timeline::before {
  content: '';
  position: absolute;
  top: 16px;
  bottom: 16px;
  left: 2px;
  width: 3px;
  background: linear-gradient(180deg, #10b981 0%, #3b82f6 30%, #8b5cf6 60%, #06b6d4 85%, #cbd5e1 100%);
  border-radius: 2px;
}
.log-item {
  position: relative;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.log-item:hover {
  transform: translateX(3px);
}
.log-dot {
  position: absolute;
  left: -33px;
  top: 10px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  box-shadow: 0 0 0 3px #f1f5f9;
  transition: all 0.2s ease;
}
.log-dot-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  color: inherit;
}
.log-dot-pulse {
  position: absolute;
  top: -6px;
  left: -6px;
  right: -6px;
  bottom: -6px;
  border-radius: 50%;
  animation: logDotPulseAnim 2s infinite cubic-bezier(0.45, 0, 0.55, 1);
}
@keyframes logDotPulseAnim {
  0% { transform: scale(0.9); opacity: 0.8; }
  50% { transform: scale(1.6); opacity: 0; }
  100% { transform: scale(0.9); opacity: 0; }
}
.log-content {
  border-radius: 12px;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease;
}
.log-item:hover .log-content {
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}
.log-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.log-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}
.log-status {
  font-weight: 800;
  font-size: 0.86rem;
  letter-spacing: 0.1px;
}
.badge-latest-pill {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.log-time {
  font-size: 0.74rem;
  font-weight: 600;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  padding: 2px 8px;
  border-radius: 6px;
}
.log-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid rgba(0, 0, 0, 0.04);
}
.log-note-bar {
  width: 3px;
  height: 14px;
  border-radius: 2px;
  background: currentColor;
  opacity: 0.5;
  margin-top: 3px;
  flex-shrink: 0;
}
.log-note-text {
  margin: 0;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.45;
  word-break: break-word;
}

/* === STAGE THEMES (Rich Vibrant Colors) === */

/* 1. DELIVERED (Giao thành công) */
.log-item.stage-delivered .log-dot {
  background: #10b981;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #d1fae5, 0 2px 8px rgba(16, 185, 129, 0.35);
}
.log-item.stage-delivered .log-dot-pulse {
  background: rgba(16, 185, 129, 0.3);
}
.log-item.stage-delivered .log-content {
  background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
  border: 1.5px solid #a7f3d0;
  border-left: 4.5px solid #10b981;
}
.log-item.stage-delivered .log-status {
  color: #047857;
}

/* 2. DELIVERING (Đang giao hàng / Xuất kho giao) */
.log-item.stage-delivering .log-dot {
  background: #2563eb;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #dbeafe, 0 2px 8px rgba(37, 99, 235, 0.35);
}
.log-item.stage-delivering .log-dot-pulse {
  background: rgba(37, 99, 235, 0.3);
}
.log-item.stage-delivering .log-content {
  background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
  border: 1.5px solid #bfdbfe;
  border-left: 4.5px solid #2563eb;
}
.log-item.stage-delivering .log-status {
  color: #1d4ed8;
}

/* 3. TRANSIT (Nhập kho / Đang trung chuyển) */
.log-item.stage-transit .log-dot {
  background: #8b5cf6;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #ede9fe, 0 2px 8px rgba(139, 92, 246, 0.35);
}
.log-item.stage-transit .log-dot-pulse {
  background: rgba(139, 92, 246, 0.3);
}
.log-item.stage-transit .log-content {
  background: linear-gradient(135deg, #f5f3ff 0%, #ffffff 100%);
  border: 1.5px solid #ddd6fe;
  border-left: 4.5px solid #8b5cf6;
}
.log-item.stage-transit .log-status {
  color: #6d28d9;
}

/* 4. PICKING (Đã lấy hàng / Đang lấy hàng) */
.log-item.stage-picking .log-dot {
  background: #06b6d4;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #cffafe, 0 2px 8px rgba(6, 182, 212, 0.35);
}
.log-item.stage-picking .log-dot-pulse {
  background: rgba(6, 182, 212, 0.3);
}
.log-item.stage-picking .log-content {
  background: linear-gradient(135deg, #ecfeff 0%, #ffffff 100%);
  border: 1.5px solid #a5f3fc;
  border-left: 4.5px solid #06b6d4;
}
.log-item.stage-picking .log-status {
  color: #0e7490;
}

/* 5. WAITING (Chờ lấy hàng / Tạo đơn) */
.log-item.stage-waiting .log-dot {
  background: #f59e0b;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #fef3c7, 0 2px 8px rgba(245, 158, 11, 0.35);
}
.log-item.stage-waiting .log-dot-pulse {
  background: rgba(245, 158, 11, 0.3);
}
.log-item.stage-waiting .log-content {
  background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
  border: 1.5px solid #fde68a;
  border-left: 4.5px solid #f59e0b;
}
.log-item.stage-waiting .log-status {
  color: #b45309;
}

/* 6. FAILED / CANCELLED / RETURN */
.log-item.stage-failed .log-dot {
  background: #ef4444;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3.5px #fee2e2, 0 2px 8px rgba(239, 68, 68, 0.35);
}
.log-item.stage-failed .log-dot-pulse {
  background: rgba(239, 68, 68, 0.3);
}
.log-item.stage-failed .log-content {
  background: linear-gradient(135deg, #fff1f2 0%, #ffffff 100%);
  border: 1.5px solid #fecdd3;
  border-left: 4.5px solid #ef4444;
}
.log-item.stage-failed .log-status {
  color: #be123c;
}

/* 7. DEFAULT / FALLBACK */
.log-item.stage-default .log-dot {
  background: #64748b;
  border-color: #ffffff;
  color: #ffffff;
  box-shadow: 0 0 0 3px #f1f5f9;
}
.log-item.stage-default .log-content {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 4.5px solid #64748b;
}
.log-item.stage-default .log-status {
  color: #334155;
}

/* Dark mode adjustments */
:global(html.dark) .shipping-carrier-panel {
  background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
  border-color: #334155;
}
:global(html.dark) .carrier-name { color: #f8fafc; }
:global(html.dark) .shipping-info-grid {
  background: #1e293b;
  border-color: #334155;
}
:global(html.dark) .shipping-info-item .info-v { color: #f8fafc; }
:global(html.dark) .logs-title-text { color: #f8fafc; }
:global(html.dark) .log-content {
  background: #1e293b;
  border-color: #334155;
}
:global(html.dark) .log-item.stage-delivered .log-content {
  background: linear-gradient(135deg, rgba(6, 78, 59, 0.25) 0%, #1e293b 100%);
  border-color: rgba(16, 185, 129, 0.4);
}
:global(html.dark) .log-item.stage-delivered .log-status { color: #34d399; }
:global(html.dark) .log-item.stage-delivering .log-content {
  background: linear-gradient(135deg, rgba(30, 58, 138, 0.25) 0%, #1e293b 100%);
  border-color: rgba(59, 130, 246, 0.4);
}
:global(html.dark) .log-item.stage-delivering .log-status { color: #60a5fa; }
:global(html.dark) .log-item.stage-transit .log-content {
  background: linear-gradient(135deg, rgba(91, 33, 182, 0.25) 0%, #1e293b 100%);
  border-color: rgba(139, 92, 246, 0.4);
}
:global(html.dark) .log-item.stage-transit .log-status { color: #a78bfa; }
:global(html.dark) .log-item.stage-picking .log-content {
  background: linear-gradient(135deg, rgba(21, 94, 117, 0.25) 0%, #1e293b 100%);
  border-color: rgba(6, 182, 212, 0.4);
}
:global(html.dark) .log-item.stage-picking .log-status { color: #22d3ee; }
:global(html.dark) .log-item.stage-waiting .log-content {
  background: linear-gradient(135deg, rgba(146, 64, 14, 0.25) 0%, #1e293b 100%);
  border-color: rgba(245, 158, 11, 0.4);
}
:global(html.dark) .log-item.stage-waiting .log-status { color: #fbbf24; }
:global(html.dark) .log-item.stage-failed .log-content {
  background: linear-gradient(135deg, rgba(159, 18, 57, 0.25) 0%, #1e293b 100%);
  border-color: rgba(239, 68, 68, 0.4);
}
:global(html.dark) .log-item.stage-failed .log-status { color: #f87171; }
:global(html.dark) .log-note-text { color: #94a3b8; }
:global(html.dark) .log-time {
  background: #0f172a;
  border-color: #334155;
  color: #94a3b8;
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
.refund-alert-box {
  background: #fffbeb;
  border: 1.5px solid #fde68a;
  border-radius: 12px;
  padding: 14px;
}
.refund-alert-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  color: #b45309;
  font-size: 0.9rem;
}
.refund-alert-amount {
  font-size: 0.88rem;
  color: #78350f;
  margin-top: 4px;
}
.refund-alert-amount strong {
  color: #b91c1c;
  font-size: 1rem;
}
.refund-method-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.refund-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: all 0.15s;
}
.refund-option:hover {
  border-color: #f59e0b;
}
.refund-option.active {
  border-color: #f59e0b;
  background: #fffdf5;
}
.refund-option input[type="radio"] {
  accent-color: #f59e0b;
  margin-top: 3px;
}
.refund-option-text {
  display: flex;
  flex-direction: column;
}
.refund-option-title {
  font-weight: 700;
  font-size: 0.86rem;
  color: #1f2937;
}
.refund-option-sub {
  font-size: 0.78rem;
  color: #6b7280;
  margin-top: 2px;
}
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

/* ===== Dispatch Modal ===== */
.dispatch-modal-box {
  background: #ffffff;
  border-radius: 16px;
  max-width: 620px;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
}

.dispatch-modal-body {
  padding: 20px 24px;
  max-height: 75vh;
  overflow-y: auto;
}

.dispatch-section-label {
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
  display: block;
  margin-bottom: 8px;
}

.carrier-selector-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

@media (max-width: 600px) {
  .carrier-selector-grid {
    grid-template-columns: 1fr;
  }
}

.carrier-card {
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  cursor: pointer;
  display: flex;
  gap: 8px;
  align-items: flex-start;
  transition: all 0.2s ease;
  background: #f8fafc;
}

.carrier-card input {
  margin-top: 4px;
}

.carrier-card.active {
  border-color: #e63b6f;
  background: #fff1f2;
}

.carrier-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  margin-bottom: 4px;
}

.carrier-badge.oe { background: #0284c7; color: white; }
.carrier-badge.ghn { background: #ea580c; color: white; }
.carrier-badge.self { background: #16a34a; color: white; }

.carrier-title {
  font-size: 0.82rem;
  color: #0f172a;
  display: block;
}

.carrier-desc {
  font-size: 0.72rem;
  color: #64748b;
  line-height: 1.3;
  display: block;
  margin-top: 2px;
}

.package-specs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  background: #f8fafc;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.spec-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.spec-field.full {
  grid-column: 1 / -1;
}

.spec-field label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
}

.dispatch-input, .dispatch-select {
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  font-size: 0.85rem;
  outline: none;
  background: #ffffff;
}

.dispatch-input:focus, .dispatch-select:focus {
  border-color: #e63b6f;
}

.dimensions-input-group {
  display: flex;
  align-items: center;
  gap: 4px;
}

.dimensions-input-group input {
  width: 100%;
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  text-align: center;
  font-size: 0.85rem;
  background: #ffffff;
}

.btn-dispatch-submit {
  padding: 10px 20px;
  background: #e63b6f;
  color: #ffffff;
  border-radius: 10px;
  border: none;
  font-weight: 700;
  cursor: pointer;
}

.btn-dispatch-submit:hover {
  background: #d0295d;
}

/* ===== Waybill Modal & Print ===== */
.waybill-modal-box {
  background: #ffffff;
  border-radius: 16px;
  max-width: 680px;
  width: 100%;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
}

.btn-print-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  background: #0284c7;
  color: #ffffff;
  border: none;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
}

.printable-waybill-content {
  padding: 24px;
  background: #ffffff;
  color: #000000;
  font-family: Arial, sans-serif;
  font-size: 13px;
}

.waybill-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid #000;
  padding-bottom: 12px;
  margin-bottom: 14px;
}

.waybill-brand h2 {
  font-size: 1.4rem;
  font-weight: 900;
  margin: 0;
  letter-spacing: 0.05em;
}

.waybill-brand p {
  margin: 2px 0 0 0;
  font-size: 11px;
  color: #444;
}

.waybill-carrier-badge {
  text-align: right;
}

.waybill-carrier-badge strong {
  display: block;
  font-size: 1.1rem;
}

.waybill-barcode-text {
  font-family: monospace;
  font-size: 1.1rem;
  font-weight: 900;
  letter-spacing: 2px;
  border: 1px dashed #000;
  padding: 2px 8px;
  display: inline-block;
  margin-top: 4px;
}

.waybill-addresses-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 14px;
  margin-bottom: 14px;
}

.address-box {
  background: #fdfdfd;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 10px;
}

.box-title {
  font-size: 10px;
  font-weight: 800;
  color: #666;
  display: block;
  margin-bottom: 4px;
}

.address-box p {
  margin: 2px 0;
  font-size: 12px;
}

.waybill-items-table {
  margin-bottom: 14px;
}

.table-compact {
  width: 100%;
  border-collapse: collapse;
}

.table-compact th, .table-compact td {
  border: 1px solid #ddd;
  padding: 6px 8px;
  font-size: 12px;
}

.table-compact th {
  background: #f3f3f3;
  font-weight: 700;
}

.waybill-footer-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 16px;
  border-top: 2px solid #000;
  padding-top: 12px;
}

.cod-settlement-box {
  border: 1px dashed #000;
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}

.cod-title {
  font-size: 11px;
  font-weight: 800;
  display: block;
}

.cod-amount {
  font-size: 1.3rem;
  font-weight: 900;
  color: #dc2626;
  display: block;
  margin: 4px 0;
}

.badge-paid {
  display: inline-block;
  background: #16a34a;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
}

.signature-box {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 10px;
  text-align: center;
  min-height: 80px;
}

.signature-box p {
  margin: 0;
  font-weight: 700;
}

.signature-box span {
  font-size: 10px;
  color: #666;
}

@media print {
  body * {
    visibility: hidden;
  }
  #printable-waybill, #printable-waybill * {
    visibility: visible;
  }
  #printable-waybill {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    padding: 0;
  }
  .no-print {
    display: none !important;
  }
}
</style>

