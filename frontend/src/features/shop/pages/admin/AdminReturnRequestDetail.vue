<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import { useToast } from '@/composables/useToast';
import { getStorageUrl } from '@/utils/url';
import MediaPreviewModal from '@/components/MediaPreviewModal.vue';
import Swal from 'sweetalert2';
import {
  RETURN_REQUEST_REFUND_METHOD_OPTIONS,
  getOrderStatusLabel,
  getPaymentStatusLabel,
  getReturnRefundStatusLabel,
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getRefundMethodLabel,
  getReturnShippingMethodLabel,
} from '@/utils/orderStatus';

const route = useRoute();
const router = useRouter();
const store = useReturnRequestStore();
const { showToast } = useToast();
const { currentRequest, detailLoading } = storeToRefs(store);

const actionLoading = ref(false);
const adminNote = ref('');
const refundForm = reactive({
  refund_amount: 0,
  refund_method: 'wallet',
  admin_note: '',
});
const bankReferenceCode = ref('');

const logisticsForm = reactive({
  return_tracking_code: '',
  return_carrier: 'ocean_express',
});

// Modal states
const showDispatchModal = ref(false);
const dispatchLoading = ref(false);
const dispatchForm = reactive({
  carrier: 'ocean_express',
  weight: 500,
  length: 20,
  width: 15,
  height: 10,
  required_note: 'Thu hồi hàng hoàn từ khách',
  admin_note: '',
  tracking_code: '',
});

const showTrackingModal = ref(false);
const trackingLoading = ref(false);
const trackingInfo = ref(null);

const printLoading = ref(false);
const receivedItems = ref({});
const inspectionItems = ref({});

const detail = computed(() => currentRequest.value);

const returnSteps = [
  { key: 'pending', label: '1. Tiếp nhận', sub: 'Khách gửi yêu cầu' },
  { key: 'approved', label: '2. Duyệt yêu cầu', sub: 'Cấp mã vận chuyển' },
  { key: 'returning', label: '3. Khách gửi hàng', sub: 'Vận chuyển về kho' },
  { key: 'warehouse_received', label: '4. Kho nhận & QC', sub: 'Kiểm định chất lượng' },
  { key: 'return_completed', label: '5. Hoàn tất', sub: 'Đã hoàn tiền' },
];

const getStepStatus = (stepKey) => {
  if (!detail.value) return 'inactive';
  const status = detail.value.status;

  if (status === 'return_rejected' || status === 'rejected') {
    if (stepKey === 'pending') return 'done';
    if (stepKey === 'approved') return 'rejected';
    return 'inactive';
  }

  if (status === 'inspection_failed') {
    if (['pending', 'approved', 'returning', 'warehouse_received'].includes(stepKey)) return 'done';
    if (stepKey === 'warehouse_received') return 'failed';
    return 'inactive';
  }

  const levelMap = {
    return_pending: 1,
    pending: 1,
    return_approved: 2,
    approved: 2,
    returning: 3,
    warehouse_received: 4,
    received: 4,
    inspected_ok: 4,
    refunding: 5,
    refund_pending: 5,
    return_completed: 5,
    refunded: 5,
  };

  const stepLevelMap = {
    pending: 1,
    approved: 2,
    returning: 3,
    warehouse_received: 4,
    return_completed: 5,
  };

  const currentLevel = levelMap[status] || 1;
  const stepLevel = stepLevelMap[stepKey] || 1;

  if (currentLevel > stepLevel) return 'done';
  if (currentLevel === stepLevel) return 'active';
  return 'inactive';
};

const mediaList = computed(() => {
  const list = [];
  if (detail.value?.images?.length) {
    detail.value.images.forEach((img) => {
      list.push({ url: imageUrl(img), type: 'image' });
    });
  }
  if (detail.value?.videos?.length) {
    detail.value.videos.forEach((vid) => {
      list.push({ url: imageUrl(vid), type: 'video' });
    });
  }
  return list;
});

const previewShow = ref(false);
const previewIndex = ref(0);

const openPreview = (url, type = 'image') => {
  const foundIdx = mediaList.value.findIndex((item) => item.url === url);
  previewIndex.value = foundIdx >= 0 ? foundIdx : 0;
  previewShow.value = true;
};

const closePreview = () => {
  previewShow.value = false;
};

const refundAmountDisplay = ref('');

const formatNumberVND = (val) => {
  if (!val && val !== 0) return '';
  const clean = String(val).replace(/\D/g, '');
  if (!clean) return '';
  return new Intl.NumberFormat('vi-VN').format(Number(clean));
};

const handleRefundAmountInput = (e) => {
  const cleanVal = e.target.value.replace(/\D/g, '');
  const rawValue = cleanVal ? parseInt(cleanVal, 10) : 0;
  refundForm.refund_amount = rawValue;
  refundAmountDisplay.value = formatNumberVND(rawValue);
  e.target.value = refundAmountDisplay.value;
};

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

const imageUrl = (path) => getStorageUrl(path);

const getReasonTone = (reason) => {
  if (!reason) return 'neutral';
  const r = reason.toLowerCase();
  if (r.includes('lỗi') || r.includes('hư') || r.includes('hỏng') || r.includes('rách') || r.includes('vỡ') || r.includes('kém chất lượng')) return 'danger';
  if (r.includes('mô tả') || r.includes('sai') || r.includes('thiếu') || r.includes('nhầm') || r.includes('giao sai')) return 'warning';
  if (r.includes('size') || r.includes('vừa') || r.includes('kích') || r.includes('màu')) return 'info';
  if (r.includes('đổi ý') || r.includes('nhu cầu') || r.includes('không thích') || r.includes('mua nhầm')) return 'purple';
  return 'neutral';
};

const isStatus = (...statuses) => {
  if (!detail.value) return false;
  return statuses.includes(detail.value.status);
};

// Map trạng thái kỹ thuật Ocean Express → nhãn tiếng Việt thân thiện (không dùng emoji)
const OE_STATUS_MAP = {
  ready_to_pick: { label: 'Chờ lấy hàng', color: '#f59e0b' },
  picking:       { label: 'Shipper đang đến lấy hàng', color: '#3b82f6' },
  picked:        { label: 'Đã lấy hàng từ khách', color: '#10b981' },
  picked_up:     { label: 'Đã lấy hàng từ khách', color: '#10b981' },
  hub_inbound:   { label: 'Nhập kho trung chuyển', color: '#6366f1' },
  in_hub:        { label: 'Tại kho trung chuyển', color: '#6366f1' },
  storing:       { label: 'Tại kho trung chuyển', color: '#6366f1' },
  stored:        { label: 'Tại kho trung chuyển', color: '#6366f1' },
  hub_outbound:  { label: 'Xuất kho — Lên đường', color: '#8b5cf6' },
  transporting:  { label: 'Đang vận chuyển', color: '#8b5cf6' },
  in_transit:    { label: 'Đang trên đường', color: '#8b5cf6' },
  shipping:      { label: 'Đang vận chuyển', color: '#8b5cf6' },
  delivering:    { label: 'Shipper đang giao về kho', color: '#f97316' },
  delivered:     { label: 'Đã giao về kho thành công', color: '#16a34a' },
  completed:     { label: 'Đã hoàn thành', color: '#16a34a' },
  returned:      { label: 'Đã giao về kho thành công', color: '#16a34a' },
  cancelled:     { label: 'Hủy vận đơn', color: '#dc2626' },
  delivery_fail: { label: 'Giao thất bại', color: '#dc2626' },
  damaged:       { label: 'Hàng bị hư hỏng', color: '#dc2626' },
  lost:          { label: 'Hàng thất lạc', color: '#dc2626' },
};

const formatOeStatus = (rawStatus) => {
  if (!rawStatus) return { label: 'Không rõ', color: '#94a3b8' };
  const key = String(rawStatus).toLowerCase().trim();
  return OE_STATUS_MAP[key] || { label: rawStatus, color: '#94a3b8' };
};


const refreshDetail = async () => {
  const response = await store.fetchAdminReturnRequestDetail(route.params.id);
  if (detail.value) {
    adminNote.value = detail.value.admin_note || '';
    refundForm.refund_amount = Number(detail.value.refund_amount || 0);
    refundAmountDisplay.value = formatNumberVND(refundForm.refund_amount);
    refundForm.refund_method = detail.value.refund_method || 'wallet';
    logisticsForm.return_tracking_code = detail.value.return_tracking_code || '';
    logisticsForm.return_carrier = detail.value.return_carrier || 'ocean_express';
    dispatchForm.carrier = detail.value.return_carrier || 'ocean_express';

    receivedItems.value = Object.fromEntries((detail.value.items || []).map((item) => [
      item.id,
      item.received_quantity || item.requested_quantity || 0,
    ]));

    inspectionItems.value = Object.fromEntries((detail.value.items || []).map((item) => [
      item.id,
      {
        qc_pass_quantity: item.qc_pass_quantity !== undefined ? item.qc_pass_quantity : (item.received_quantity || item.requested_quantity || 0),
        qc_fail_quantity: item.qc_fail_quantity !== undefined ? item.qc_fail_quantity : 0,
        qc_note: item.qc_note || '',
      },
    ]));
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

// QC quick actions
const setAllPass = () => {
  (detail.value?.items || []).forEach((item) => {
    const qty = Number(item.received_quantity || item.requested_quantity || 1);
    inspectionItems.value[item.id] = {
      qc_pass_quantity: qty,
      qc_fail_quantity: 0,
      qc_note: 'Đạt chuẩn kiểm định 100%',
    };
  });
  showToast('Đã đặt tất cả sản phẩm đạt QC!', 'info');
};

const setAllFail = () => {
  (detail.value?.items || []).forEach((item) => {
    const qty = Number(item.received_quantity || item.requested_quantity || 1);
    inspectionItems.value[item.id] = {
      qc_pass_quantity: 0,
      qc_fail_quantity: qty,
      qc_note: 'Hàng không đạt chuẩn hoàn trả',
    };
  });
  showToast('Đã đặt tất cả sản phẩm không đạt QC!', 'info');
};

// Actions
const openDispatchModal = () => {
  let totalW = 0;
  (detail.value?.items || []).forEach((item) => {
    totalW += (item.product?.weight || 500) * (item.requested_quantity || 1);
  });
  dispatchForm.weight = Math.max(100, totalW);
  dispatchForm.carrier = detail.value?.return_carrier === 'dropoff_post_office' ? 'dropoff_post_office' : 'ocean_express';
  dispatchForm.tracking_code = detail.value?.return_tracking_code || '';
  const customerName = detail.value?.return_pickup_name || detail.value?.order?.recipient_name || '';
  dispatchForm.required_note = `Thu hồi kiện hàng hoàn từ khách ${customerName}`.trim();
  showDispatchModal.value = true;
};

const closeDispatchModal = () => {
  showDispatchModal.value = false;
};

const submitDispatchShipping = async () => {
  dispatchLoading.value = true;
  try {
    const res = await store.dispatchReturnShipping(route.params.id, {
      carrier: dispatchForm.carrier,
      weight: dispatchForm.weight,
      length: dispatchForm.length,
      width: dispatchForm.width,
      height: dispatchForm.height,
      required_note: dispatchForm.required_note,
      admin_note: dispatchForm.admin_note,
      tracking_code: dispatchForm.tracking_code,
    });
    if (res.status === 'success') {
      showToast(res.message || 'Đã điều phối vận đơn thu hồi thành công!', 'success');
      showDispatchModal.value = false;
      await refreshDetail();
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể đẩy vận đơn thu hồi.', 'error');
  } finally {
    dispatchLoading.value = false;
  }
};

const handlePrintShippingLabel = async () => {
  printLoading.value = true;
  try {
    const res = await store.getReturnShippingLabel(route.params.id);
    if (res.status === 'success' && res.data?.print_url) {
      window.open(res.data.print_url, '_blank');
    } else {
      showToast(res.message || 'Không tìm thấy link in vận đơn.', 'warning');
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể lấy thông tin in phiếu vận đơn.', 'error');
  } finally {
    printLoading.value = false;
  }
};

const openTrackingModal = async () => {
  showTrackingModal.value = true;
  trackingLoading.value = true;
  try {
    const res = await store.getReturnTracking(route.params.id);
    if (res.status === 'success') {
      trackingInfo.value = res.data;
      // Auto-sync: khi backend dong bo trang thai tu OE thanh cong
      // -> dong modal va reload detail de stepper cap nhat dung buoc
      if (res.data?.status_synced) {
        showToast('Trạng thái đã được đồng bộ từ Ocean Express!', 'success');
        showTrackingModal.value = false; // Dong modal de user thay stepper thay doi
        await refreshDetail();
      }
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể tra cứu hành trình Ocean Express.', 'error');
  } finally {
    trackingLoading.value = false;
  }
};




const closeTrackingModal = () => {
  showTrackingModal.value = false;
};

const markReturning = () => {
  Swal.fire({
    title: 'Xác nhận kiện hàng đang gửi về kho?',
    text: 'Chuyển trạng thái sang Khách đang gửi hàng (vận chuyển thu hồi).',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#e63b6f',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Xác nhận',
    cancelButtonText: 'Đóng',
  }).then((res) => {
    if (res.isConfirmed) {
      runAction(() => store.markReturnReturning(route.params.id, {
        admin_note: adminNote.value || null,
        return_carrier: detail.value?.return_carrier || 'ocean_express',
        return_tracking_code: detail.value?.return_tracking_code || null,
      }));
    }
  });
};

const approve = () => {
  Swal.fire({
    title: 'Xác nhận duyệt hoàn hàng?',
    text: 'Hệ thống sẽ chuyển trạng thái sang Đã duyệt và tự động tạo mã vận đơn thu hồi qua Ocean Express.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#e63b6f',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Duyệt & Đẩy Ocean Express',
    cancelButtonText: 'Đóng',
  }).then((res) => {
    if (res.isConfirmed) {
      runAction(() => store.approveReturnRequest(route.params.id, {
        admin_note: adminNote.value || null,
      }));
    }
  });
};

const reject = () => {
  if (!adminNote.value.trim()) {
    showToast('Vui lòng nhập lý do từ chối vào ô Ghi chú.', 'error');
    return;
  }

  Swal.fire({
    title: 'Từ chối yêu cầu hoàn hàng?',
    text: `Lý do: "${adminNote.value.trim()}"`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Từ chối',
    cancelButtonText: 'Hủy',
  }).then((res) => {
    if (res.isConfirmed) {
      runAction(() => store.rejectReturnRequest(route.params.id, {
        admin_note: adminNote.value.trim(),
      }));
    }
  });
};

const markReceived = () => {
  runAction(() => store.markReturnReceived(route.params.id, {
    admin_note: adminNote.value || null,
    items: (detail.value?.items || []).map((item) => ({
      return_request_item_id: item.id,
      received_quantity: Number(receivedItems.value[item.id] ?? item.requested_quantity ?? 0),
    })),
  }));
};

const inspect = () => {
  runAction(() => store.inspectReturnRequest(route.params.id, {
    inspection_note: adminNote.value || null,
    items: (detail.value?.items || []).map((item) => ({
      return_request_item_id: item.id,
      qc_pass_quantity: Number(inspectionItems.value[item.id]?.qc_pass_quantity ?? 0),
      qc_fail_quantity: Number(inspectionItems.value[item.id]?.qc_fail_quantity ?? 0),
      qc_note: inspectionItems.value[item.id]?.qc_note || null,
    })),
  }));
};

const refund = () => {
  if (refundForm.refund_amount === '' || Number(refundForm.refund_amount) <= 0) {
    showToast('Vui lòng nhập số tiền hoàn hợp lệ (> 0đ).', 'error');
    return;
  }

  const isWallet = (refundForm.refund_method || detail.value?.refund_method) === 'wallet';
  const methodText = isWallet ? '⚡ Tự động cộng vào Ví Ocean Sport của khách' : '🏦 Đã chuyển khoản ngân hàng thủ công';

  Swal.fire({
    title: 'Xác nhận hoàn tiền?',
    html: `
      <div class="text-start">
        <p class="mb-1">Số tiền hoàn: <strong class="text-danger">${formatPrice(refundForm.refund_amount)}</strong></p>
        <p class="mb-2">Phương thức: <strong>${methodText}</strong></p>
        <p class="small text-muted mb-0">Hành động này sẽ cập nhật trạng thái đơn sang Hoàn tất hoàn trả.</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Xác nhận hoàn tiền',
    cancelButtonText: 'Đóng',
  }).then((res) => {
    if (res.isConfirmed) {
      runAction(() => store.refundReturnRequest(route.params.id, {
        refund_amount: Number(refundForm.refund_amount),
        refund_method: refundForm.refund_method || detail.value?.refund_method || 'wallet',
        bank_reference_code: bankReferenceCode.value || null,
        admin_note: adminNote.value || null,
      }));
    }
  });
};

onMounted(() => {
  refreshDetail();
});
</script>

<template>
  <div class="admin-return-detail-page">
    <!-- Top Navigation -->
    <div class="detail-header">
      <div class="header-left">
        <button class="btn-back" @click="router.push({ name: 'admin-return-requests' })">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Danh sách hoàn hàng
        </button>
        <span class="header-divider">/</span>
        <span class="header-current-code">{{ detail?.return_code || `#${route.params.id}` }}</span>
      </div>

      <div v-if="detail" class="header-right">
        <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
          <span class="dot-indicator"></span>
          {{ getReturnRequestStatusLabel(detail.status) }}
        </span>
      </div>
    </div>

    <!-- 5-Stage Stepper Progression -->
    <div v-if="detail" class="stepper-card">
      <div class="stepper-track">
        <div
          v-for="(step, idx) in returnSteps"
          :key="step.key"
          class="stepper-step"
          :class="`step--${getStepStatus(step.key)}`"
        >
          <div class="step-circle">
            <svg v-if="getStepStatus(step.key) === 'done'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span v-else-if="getStepStatus(step.key) === 'rejected' || getStepStatus(step.key) === 'failed'">✕</span>
            <span v-else>{{ idx + 1 }}</span>
          </div>
          <div class="step-info">
            <span class="step-label">{{ step.label }}</span>
            <span class="step-sub">{{ step.sub }}</span>
          </div>
          <div v-if="idx < returnSteps.length - 1" class="step-connector"></div>
        </div>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div v-if="detail" class="detail-grid">
      <!-- Left Column: Order & Return Details -->
      <section class="detail-main-col">
        <!-- Customer & Order Summary Card -->
        <div class="info-card">
          <div class="card-head">
            <div class="card-title-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <h2>Thông tin Khách hàng & Đơn hàng</h2>
            </div>
            <router-link
              v-if="detail.order"
              :to="{ name: 'admin-order-detail', params: { id: detail.order.order_id } }"
              class="order-link-btn"
            >
              Xem Đơn #{{ detail.order.order_code }} ↗
            </router-link>
          </div>

          <div class="info-grid-2">
            <div class="info-item">
              <span class="item-label">Khách hàng</span>
              <strong class="item-value">{{ detail.user?.full_name || detail.order?.recipient_name || 'Khách vãng lai' }}</strong>
            </div>
            <div class="info-item">
              <span class="item-label">Số điện thoại</span>
              <strong class="item-value">{{ detail.user?.phone || detail.order?.recipient_phone || '—' }}</strong>
            </div>
            <div class="info-item">
              <span class="item-label">Email</span>
              <span class="item-value">{{ detail.user?.email || detail.order?.email || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="item-label">Thời gian gửi yêu cầu</span>
              <span class="item-value">{{ formatDate(detail.requested_at || detail.created_at) }}</span>
            </div>
          </div>

          <!-- Reason & Description -->
          <div class="reason-banner" :class="`reason-banner--${getReasonTone(detail.reason)}`">
            <div class="reason-banner-head">
              <span class="reason-tag">Lý do hoàn: <strong>{{ detail.reason }}</strong></span>
            </div>
            <p class="reason-desc-text">{{ detail.description || 'Không có mô tả chi tiết kèm theo.' }}</p>
          </div>
        </div>

        <!-- Return Items Table -->
        <div class="info-card">
          <div class="card-head">
            <div class="card-title-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              <h2>Danh sách Sản phẩm Hoàn</h2>
            </div>
            <span class="card-sub-tag">{{ detail.items?.length || 0 }} sản phẩm</span>
          </div>

          <div class="items-table-wrapper">
            <table class="items-table">
              <thead>
                <tr>
                  <th>Sản phẩm</th>
                  <th class="text-center">Số lượng</th>
                  <th class="text-center">Kho nhận</th>
                  <th class="text-center">QC Đạt</th>
                  <th class="text-center">QC Lỗi</th>
                  <th class="text-right">Tiền hoàn dự kiến</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in detail.items" :key="item.id">
                  <td>
                    <div class="product-info-cell">
                      <img
                        :src="imageUrl(item.order_item?.product_image || item.product?.thumbnail_url || item.order_item?.variant_image || item.variant?.image_url)"
                        class="product-thumb-mini"
                        alt="Product"
                        @error="(e) => e.target.src = 'https://placehold.co/48x48?text=SP'"
                      />
                      <div class="product-info-text">
                        <strong>{{ item.order_item?.product_name || item.product?.name || 'Sản phẩm' }}</strong>
                        <span class="variant-chip">{{ item.order_item?.variant_name || item.variant?.variant_name || 'Tiêu chuẩn' }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="text-center"><span class="qty-pill">{{ item.requested_quantity }}</span></td>
                  <td class="text-center font-bold">{{ item.received_quantity ?? '—' }}</td>
                  <td class="text-center text-success font-bold">{{ item.qc_pass_quantity ?? '—' }}</td>
                  <td class="text-center text-danger font-bold">{{ item.qc_fail_quantity ?? '—' }}</td>
                  <td class="text-right font-bold text-primary">{{ formatPrice(item.refundable_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Media Evidence Preview -->
        <div v-if="detail.images?.length || detail.videos?.length" class="info-card">
          <div class="card-head">
            <div class="card-title-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              <h2>Hình ảnh & Video Minh chứng từ Khách hàng</h2>
            </div>
            <span class="card-sub-tag">{{ (detail.images?.length || 0) + (detail.videos?.length || 0) }} tệp</span>
          </div>

          <div class="evidence-grid">
            <div
              v-for="image in detail.images"
              :key="image"
              class="evidence-item"
              @click="openPreview(imageUrl(image), 'image')"
            >
              <img
                :src="imageUrl(image)"
                alt="Minh chứng hoàn hàng"
                @error="(e) => e.target.src = 'https://placehold.co/150x150?text=L%E1%BB%97i+%E1%BA%A3nh'"
              />
              <div class="item-overlay">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
            </div>

            <div
              v-for="video in detail.videos"
              :key="video"
              class="evidence-item evidence-item--video"
              @click="openPreview(imageUrl(video), 'video')"
            >
              <video :src="imageUrl(video)" preload="metadata" muted />
              <div class="play-btn-circle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Right Column: Actions & Financial Settlement -->
      <aside class="detail-side-col">
        <!-- Interactive Workflow Action Card -->
        <div class="action-card">
          <div class="action-card-head">
            <div class="action-title-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
              <h2>Hành động Xử lý</h2>
            </div>
          </div>

          <!-- Ghi chú Admin -->
          <div class="action-block">
            <label class="block-label">Ghi chú quản trị viên</label>
            <textarea
              v-model="adminNote"
              class="admin-textarea"
              placeholder="Nhập ghi chú hoặc lý do xử lý yêu cầu..."
              rows="3"
            ></textarea>
          </div>

          <!-- State 1: Pending -> Approve / Reject -->
          <div v-if="isStatus('pending', 'return_pending')" class="action-block">
            <div class="guide-box info">
              <p><strong>Hình thức gửi hàng:</strong> {{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</p>
              <p v-if="detail.return_shipping_method === 'pickup_original_address'" class="small text-muted">
                Khi bấm <strong>Duyệt</strong>, hệ thống sẽ tự động kích hoạt tạo vận đơn thu hồi qua <strong>Ocean Express</strong>.
              </p>
              <p v-else class="small text-muted">
                Khách sẽ tự mang hàng đến bưu cục / gửi hàng hoàn sau khi được duyệt.
              </p>
            </div>

            <div class="action-btn-row">
              <button class="btn-flow btn-approve" :disabled="actionLoading" @click="approve">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span>Duyệt & Đẩy Ocean Express</span>
              </button>
              <button class="btn-flow btn-dispatch-open" :disabled="actionLoading" @click="openDispatchModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span>Điều phối lấy hàng</span>
              </button>
              <button class="btn-flow btn-reject" :disabled="actionLoading" @click="reject">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <span>Từ chối</span>
              </button>
            </div>
          </div>

          <!-- State 2: Approved -> Waiting for Carrier Pickup -->
          <div v-else-if="isStatus('approved', 'return_approved')" class="action-block">
            <div class="guide-box info">
              <p><strong>Đã duyệt yêu cầu — Đang chờ lấy hàng:</strong></p>
              <template v-if="detail.return_tracking_code">
                <p class="small text-muted mb-0">
                  Vận đơn thu hồi đã tạo thành công trên <strong>Ocean Express</strong>. Khi shipper đến lấy hàng từ khách, hệ thống sẽ tự động cập nhật qua Webhook.
                </p>
                <!-- Nut dong bo thu cong khi webhook OE khong cap nhat duoc -->
                <button
                  class="btn-sync-oe mt-2"
                  :disabled="trackingLoading || actionLoading"
                  @click="openTrackingModal"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/></svg>
                  <span>{{ trackingLoading ? 'Đang đồng bộ...' : 'Đồng bộ trạng thái từ Ocean Express' }}</span>
                </button>
              </template>
              <template v-else>
                <p class="small text-muted mb-2">
                  Yêu cầu đã được duyệt nhưng chưa đẩy sang đơn vị vận chuyển.
                </p>
                <button class="btn-flow btn-dispatch-open" @click="openDispatchModal">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                  <span>Đẩy vận đơn sang Ocean Express</span>
                </button>
              </template>

              <!-- Option for dropoff post office only -->
              <div v-if="detail.return_carrier === 'dropoff_post_office' || detail.return_shipping_method === 'dropoff_post_office'" class="dropoff-note-box mt-2 pt-2">
                <span class="small text-muted d-block mb-1">Khách tự mang gửi bưu cục / trung tâm ngoài:</span>
                <button class="btn-subtle-outline" :disabled="actionLoading" @click="markReturning">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                  <span>Ghi nhận khách đã gửi bưu cục</span>
                </button>
              </div>
            </div>
          </div>


          <!-- State 3: Returning -> In transit to warehouse -->
          <div v-else-if="isStatus('returning')" class="action-block">
            <div class="guide-box warning">
              <p><strong>Kiện hàng đang trên đường về kho shop:</strong></p>
              <p class="small text-muted mb-0">
                Kiện hàng đang được shipper vận chuyển về kho. Khi nhận được kiện hàng thực tế tại kho, hãy nhập số lượng và bấm xác nhận để chuyển sang bước kiểm định chất lượng (QC).
              </p>
            </div>

            <!-- Nhập số lượng thực nhận -->
            <div class="receive-items-list">
              <div v-for="item in detail.items" :key="item.id" class="receive-item-row">
                <span class="receive-item-name">{{ item.order_item?.product_name || item.product?.name }}</span>
                <div class="receive-qty-box">
                  <label class="receive-qty-label">Số lượng nhận</label>
                  <input
                    v-model.number="receivedItems[item.id]"
                    type="number" min="0" :max="item.requested_quantity"
                    class="receive-qty-input"
                  />
                  <span class="receive-qty-max">/ {{ item.requested_quantity }}</span>
                </div>
              </div>
            </div>

            <button class="btn-flow btn-received" :disabled="actionLoading" @click="markReceived">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
              <span>Xác nhận Kho đã nhận hàng</span>
            </button>
          </div>

          <!-- State 4: Warehouse Received -> QC Inspection -->
          <!-- Cũng hiển thị khi webhook OE đẩy delivered mà bỏ qua bước returning -->
          <div v-else-if="isStatus('warehouse_received', 'received')" class="action-block">
            <div class="guide-box success mb-3">
              <p><strong>🎉 Kiện hàng đã về đến kho &mdash; Thực hiện QC:</strong></p>
              <p class="small text-muted mb-0">
                Kiện hàng hoàn đã được giao về kho. Hãy kiểm tra từng sản phẩm và nhập kết quả QC rồi lưu để tiến hành hoàn tiền.
              </p>
            </div>
            <div class="qc-head-bar">
              <span class="qc-head-title">Kiểm định chất lượng (QC)</span>
              <div class="qc-quick-btns">
                <button class="qc-quick-btn pass" @click="setAllPass">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>Tất cả đạt</span>
                </button>
                <button class="qc-quick-btn fail" @click="setAllFail">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  <span>Tất cả lỗi</span>
                </button>
              </div>
            </div>

            <div class="qc-items-list">
              <div v-for="item in detail.items" :key="item.id" class="qc-item-card">
                <strong class="qc-item-name">{{ item.order_item?.product_name || item.product?.name }}</strong>
                <div class="qc-input-grid">
                  <div class="qc-field">
                    <label>QC Đạt</label>
                    <input
                      v-model.number="inspectionItems[item.id].qc_pass_quantity"
                      type="number"
                      min="0"
                      :max="item.received_quantity || item.requested_quantity"
                      class="qc-num-input success"
                    />
                  </div>
                  <div class="qc-field">
                    <label>QC Lỗi</label>
                    <input
                      v-model.number="inspectionItems[item.id].qc_fail_quantity"
                      type="number"
                      min="0"
                      :max="item.received_quantity || item.requested_quantity"
                      class="qc-num-input danger"
                    />
                  </div>
                </div>
                <input
                  v-model="inspectionItems[item.id].qc_note"
                  class="qc-note-input"
                  placeholder="Ghi chú tình trạng sản phẩm..."
                />
              </div>
            </div>

            <button class="btn-flow btn-qc-submit" :disabled="actionLoading" @click="inspect">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <span>Lưu kết quả QC &amp; Tiến hành Hoàn tiền</span>
            </button>
          </div>

          <!-- State 4: Inspected OK -> Refund Settlement -->
          <div v-else-if="isStatus('inspected_ok', 'refund_pending', 'refund_failed')" class="action-block">
            <div class="refund-settlement-box">
              <label class="block-label">Số tiền hoàn cho khách</label>
              <div class="currency-input-box">
                <input
                  :value="refundAmountDisplay"
                  type="text"
                  class="refund-input"
                  placeholder="0"
                  @input="handleRefundAmountInput"
                />
                <span class="currency-tag">VND</span>
              </div>

              <div class="refund-method-card">
                <span class="method-title">Phương thức hoàn tiền:</span>
                <strong class="method-badge">
                  {{ getRefundMethodLabel(refundForm.refund_method || detail.refund_method) }}
                </strong>
                <p v-if="refundForm.refund_method === 'wallet'" class="method-note">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="inline-icon"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                  Tiền sẽ tự động cộng thẳng vào <strong>Ví điện tử Ocean Sport</strong> của khách hàng ngay sau khi xác nhận.
                </p>
                <div v-else class="bank-refund-detail-wrap">
                  <div v-if="detail.user?.bank_accounts?.length" class="bank-details-box">
                    <div v-for="b in detail.user.bank_accounts.filter(acc => acc.is_default)" :key="b.id">
                      <p>Ngân hàng: <strong>{{ b.bank_name }}</strong></p>
                      <p>Số tài khoản: <strong>{{ b.account_number }}</strong></p>
                      <p>Chủ tài khoản: <strong>{{ b.account_name }}</strong></p>
                    </div>
                  </div>

                  <div class="bank-ref-field mt-3">
                    <label class="block-label">Mã giao dịch ngân hàng (FT Code / Ref)</label>
                    <input
                      v-model="bankReferenceCode"
                      type="text"
                      class="bank-ref-input"
                      placeholder="VD: FT260903889922..."
                    />
                    <span class="small text-muted">Lưu mã giao dịch chuyển tiền để đối soát sổ sách kế toán.</span>
                  </div>
                </div>
              </div>

              <button class="btn-flow btn-refund-submit" :disabled="actionLoading" @click="refund">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Xác nhận Hoàn tiền ngay</span>
              </button>
            </div>
          </div>

          <!-- Terminal Completed State -->
          <div v-else class="completed-state-box">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p class="completed-title">Yêu cầu đã hoàn tất</p>
            <span class="completed-desc">Tất cả các thủ tục kiểm định và hoàn tiền đã được thực hiện thành công.</span>
          </div>
        </div>

        <!-- Return Logistics Info Card -->
        <div class="info-card">
          <div class="card-head">
            <div class="card-title-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              <h2>Vận chuyển Thu hồi</h2>
            </div>
            <button
              v-if="isStatus('approved', 'return_approved', 'returning')"
              class="btn-subtle-link"
              @click="openDispatchModal"
              title="Cập nhật hoặc điều phối lại"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
              <span>{{ detail.return_tracking_code ? 'Đổi thông tin' : 'Tạo vận đơn' }}</span>
            </button>
          </div>

          <div class="logistics-info-list">
            <div class="logistics-item">
              <span class="logistics-label">Phương thức lấy hàng:</span>
              <strong>{{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</strong>
            </div>

            <template v-if="detail.return_shipping_method === 'pickup_original_address'">
              <div class="logistics-item">
                <span class="logistics-label">Địa chỉ thu hồi:</span>
                <span>{{ detail.return_pickup_address || detail.order?.shipping_address }}</span>
              </div>
              <div class="logistics-item">
                <span class="logistics-label">Người liên hệ:</span>
                <span>{{ detail.return_pickup_name || detail.order?.recipient_name }} · {{ detail.return_pickup_phone || detail.order?.recipient_phone }}</span>
              </div>
            </template>

            <div v-if="detail.return_tracking_code" class="tracking-display-box">
              <div class="tracking-top">
                <span class="carrier-tag">{{ detail.return_carrier === 'dropoff_post_office' ? 'Bưu điện / Tự gửi' : 'Ocean Express' }}</span>
                <span class="tracking-code-text">{{ detail.return_tracking_code }}</span>
              </div>
              <div class="tracking-actions-row mt-2">
                <button class="btn-micro tracking-btn" @click="openTrackingModal">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                  Hành trình
                </button>
                <button class="btn-micro print-btn" :disabled="printLoading" @click="handlePrintShippingLabel">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                  In phiếu
                </button>
              </div>
            </div>

            <div v-else-if="isStatus('approved', 'return_approved')" class="empty-tracking-box">
              <span class="empty-tracking-text">Chưa tạo vận đơn thu hồi</span>
              <button class="btn-micro-cta" @click="openDispatchModal">Đẩy sang Ocean Express →</button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Return Shipping Dispatch Modal -->
    <div v-if="showDispatchModal" class="modal-overlay" @click.self="closeDispatchModal">
      <div class="dispatch-modal-box">
        <div class="modal-header">
          <div class="modal-title-wrap">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <h3>Điều phối Vận đơn Thu hồi</h3>
          </div>
          <button class="btn-close-modal" @click="closeDispatchModal">✕</button>
        </div>

        <div class="modal-body">
          <div class="form-section">
            <label class="section-heading">1. Đối tác Vận chuyển Thu hồi</label>
            <div class="carrier-options-grid">
              <label class="carrier-choice" :class="{ 'is-selected': dispatchForm.carrier === 'ocean_express' }">
                <input type="radio" v-model="dispatchForm.carrier" value="ocean_express" />
                <div class="carrier-info">
                  <div class="carrier-title">
                    <strong>Ocean Express</strong>
                    <span class="carrier-badge">Chính thức</span>
                  </div>
                  <span class="carrier-sub">Shipper lấy hàng tận nhà khách · Tự động webhook & in vận đơn</span>
                </div>
              </label>

              <label class="carrier-choice" :class="{ 'is-selected': dispatchForm.carrier === 'dropoff_post_office' }">
                <input type="radio" v-model="dispatchForm.carrier" value="dropoff_post_office" />
                <div class="carrier-info">
                  <div class="carrier-title">
                    <strong>Khách tự gửi bưu cục</strong>
                  </div>
                  <span class="carrier-sub">Khách tự gửi qua Vietnam Post / Viettel Post / J&T ngoài</span>
                </div>
              </label>
            </div>
          </div>

          <div v-if="dispatchForm.carrier === 'dropoff_post_office'" class="form-section">
            <label class="section-heading">Mã vận đơn bưu điện / hãng ngoài</label>
            <input
              v-model="dispatchForm.tracking_code"
              type="text"
              class="modal-input"
              placeholder="Nhập mã vận đơn khách đã gửi (nếu có)..."
            />
          </div>

          <!-- Ocean Express Route Preview -->
          <div v-if="dispatchForm.carrier === 'ocean_express'" class="form-section dispatch-route-preview">
            <label class="section-heading">2. Lộ trình Thu hồi Thực tế</label>
            <div class="dispatch-route-box">
              <div class="route-point sender">
                <span class="point-badge pickup">Điểm lấy hàng (Khách)</span>
                <strong class="point-name">{{ detail?.return_pickup_name || detail?.order?.recipient_name || 'Khách hàng' }} <span v-if="detail?.return_pickup_phone || detail?.order?.recipient_phone" class="point-phone">({{ detail?.return_pickup_phone || detail?.order?.recipient_phone }})</span></strong>
                <p class="point-addr">{{ detail?.return_pickup_address || detail?.order?.shipping_address || 'Địa chỉ khách hàng' }}</p>
              </div>
              <div class="route-divider-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </div>
              <div class="route-point receiver">
                <span class="point-badge dropoff">Kho nhận hàng (Shop)</span>
                <strong class="point-name">Kho Tổng Ocean Sport <span class="point-phone">(0905094644)</span></strong>
                <p class="point-addr">300/6 Hà Huy Tập, Phường Tân An, Tỉnh Đắk Lắk</p>
              </div>
            </div>
            <div class="dispatch-sync-notice">
              <span class="sync-icon">💡</span>
              <p><strong>Cơ chế điều phối:</strong> Hệ thống tự động lấy thông tin từ đơn hàng làm <strong>Nơi gửi (Điểm lấy hàng hoàn từ Khách)</strong> và thiết lập địa chỉ Kho Shop làm <strong>Nơi nhận</strong> để Shipper chuyển hàng hoàn về kho.</p>
            </div>
          </div>

          <div v-if="dispatchForm.carrier === 'ocean_express'" class="form-section">
            <label class="section-heading">3. Thông số Kiện hàng Hoàn</label>
            <div class="dimension-grid">
              <div class="dim-field">
                <label>Trọng lượng (gram)</label>
                <input v-model.number="dispatchForm.weight" type="number" min="50" class="modal-input" />
              </div>
              <div class="dim-field">
                <label>Dài (cm)</label>
                <input v-model.number="dispatchForm.length" type="number" min="1" class="modal-input" />
              </div>
              <div class="dim-field">
                <label>Rộng (cm)</label>
                <input v-model.number="dispatchForm.width" type="number" min="1" class="modal-input" />
              </div>
              <div class="dim-field">
                <label>Cao (cm)</label>
                <input v-model.number="dispatchForm.height" type="number" min="1" class="modal-input" />
              </div>
            </div>

            <div class="note-field mt-3">
              <label>Ghi chú lấy hàng cho Shipper Ocean Express</label>
              <input
                v-model="dispatchForm.required_note"
                type="text"
                class="modal-input"
                placeholder="VD: Gọi khách trước khi đến lấy hàng..."
              />
            </div>
          </div>

          <div class="form-section">
            <label class="section-heading">Ghi chú quản trị</label>
            <textarea
              v-model="dispatchForm.admin_note"
              class="modal-textarea"
              rows="2"
              placeholder="Ghi chú nội bộ cho yêu cầu hoàn này..."
            ></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeDispatchModal">Đóng</button>
          <button class="btn-confirm-dispatch" :disabled="dispatchLoading" @click="submitDispatchShipping">
            <span v-if="dispatchLoading" class="spinner-inline"></span>
            <span v-else>Xác nhận Đẩy Vận đơn Ocean Express</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Real-time Ocean Express Tracking Modal -->
    <div v-if="showTrackingModal" class="modal-overlay" @click.self="closeTrackingModal">
      <div class="tracking-modal-box">
        <div class="modal-header">
          <div class="modal-title-wrap">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
            <h3>Hành trình Vận đơn Ocean Express</h3>
          </div>
          <button class="btn-close-modal" @click="closeTrackingModal">✕</button>
        </div>

        <div class="modal-body">
          <div v-if="trackingLoading" class="tracking-loading-state">
            <div class="spinner-ocean"></div>
            <p>Đang kết nối hệ thống Ocean Express...</p>
          </div>

          <div v-else-if="trackingInfo" class="tracking-content">
            <div class="tracking-meta-banner">
              <div class="tracking-code-badge">
                <span class="label">Mã vận đơn:</span>
                <strong>{{ trackingInfo.tracking_code }}</strong>
              </div>
              <span class="carrier-pill">Ocean Express</span>
            </div>

            <!-- Route visualizer -->
            <div class="tracking-route-card">
              <div class="route-node">
                <span class="node-dot sender"></span>
                <div class="node-info">
                  <span class="node-type">Điểm lấy hàng (Khách)</span>
                  <p class="node-party" v-if="trackingInfo.sender_name">
                    <strong>{{ trackingInfo.sender_name }}</strong>
                    <span v-if="trackingInfo.sender_phone" class="phone-meta"> ({{ trackingInfo.sender_phone }})</span>
                  </p>
                  <p class="node-addr">{{ trackingInfo.sender_address || detail?.return_pickup_address || detail?.order?.shipping_address || 'Địa chỉ khách hàng' }}</p>
                </div>
              </div>
              <div class="route-line"></div>
              <div class="route-node">
                <span class="node-dot receiver"></span>
                <div class="node-info">
                  <span class="node-type">Điểm nhận hàng (Kho shop)</span>
                  <p class="node-party">
                    <strong>{{ trackingInfo.receiver_name || 'Kho Tổng Ocean Sport' }}</strong>
                    <span v-if="trackingInfo.receiver_phone" class="phone-meta"> ({{ trackingInfo.receiver_phone }})</span>
                  </p>
                  <p class="node-addr">{{ trackingInfo.receiver_address || '300/6 Hà Huy Tập, Phường Tân An, Tỉnh Đắk Lắk' }}</p>
                </div>
              </div>
            </div>

            <!-- Timeline logs -->
            <div class="tracking-timeline-wrap">
              <h4 class="timeline-title">Lịch sử di chuyển</h4>
              <div v-if="trackingInfo.logs && trackingInfo.logs.length" class="timeline-list">
                <div v-for="(log, idx) in trackingInfo.logs" :key="idx" class="timeline-entry" :class="{ 'is-latest': idx === 0 }">
                  <div class="entry-bullet"></div>
                  <div class="entry-body">
                    <span class="entry-time">{{ formatDate(log.timestamp || log.created_at) }}</span>
                    <span
                      class="entry-status-badge"
                      :style="{ color: formatOeStatus(log.status || log.action).color }"
                    >
                      {{ formatOeStatus(log.status || log.action).label }}
                    </span>
                    <p v-if="log.note || log.description" class="entry-desc">{{ log.note || log.description }}</p>
                  </div>
                </div>
              </div>
              <div v-else class="empty-logs">
                <p>Kiện hàng mới được tạo vận đơn, đang chờ shipper Ocean Express tiếp nhận điều phối.</p>
              </div>
            </div>
          </div>

          <div v-else class="empty-logs">
            <p>Chưa có dữ liệu hành trình từ Ocean Express.</p>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeTrackingModal">Đóng</button>
          <button class="btn-refresh-tracking" :disabled="trackingLoading" @click="openTrackingModal">
            Cập nhật lại
          </button>
        </div>
      </div>
    </div>

    <!-- Media Fullscreen Lightbox Modal -->
    <MediaPreviewModal
      :show="previewShow"
      :media-list="mediaList"
      :initial-index="previewIndex"
      @close="closePreview"
    />
  </div>
</template>

<style scoped>
.admin-return-detail-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: var(--font-inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  width: 100%;
  box-sizing: border-box;
}

.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-back:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.header-divider {
  color: #cbd5e1;
}

.header-current-code {
  font-weight: 800;
  font-size: 1.1rem;
  color: #0f172a;
}

/* Stepper */
.stepper-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.stepper-track {
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
}

.stepper-step {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  flex: 1;
}

.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.9rem;
  border: 2px solid #e2e8f0;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.step-info {
  display: flex;
  flex-direction: column;
}

.step-label {
  font-weight: 700;
  font-size: 0.85rem;
  color: #64748b;
}

.step-sub {
  font-size: 0.72rem;
  color: #94a3b8;
}

.step-connector {
  flex: 1;
  height: 2px;
  background: #e2e8f0;
  margin: 0 12px;
}

.stepper-step.step--active .step-circle {
  background: #e63b6f;
  color: #ffffff;
  border-color: #e63b6f;
  box-shadow: 0 0 0 4px rgba(230, 59, 111, 0.15);
}

.stepper-step.step--active .step-label {
  color: #e63b6f;
}

.stepper-step.step--done .step-circle {
  background: #10b981;
  color: #ffffff;
  border-color: #10b981;
}

.stepper-step.step--done .step-label {
  color: #0f172a;
}

.stepper-step.step--done .step-connector {
  background: #10b981;
}

.stepper-step.step--rejected .step-circle,
.stepper-step.step--failed .step-circle {
  background: #ef4444;
  color: #ffffff;
  border-color: #ef4444;
}

/* Grid Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 20px;
}

@media (max-width: 1024px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

.info-card, .action-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 20px;
}

.card-head, .action-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.card-title-box, .action-title-box {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0f172a;
}

.card-title-box h2, .action-title-box h2 {
  font-size: 1rem;
  font-weight: 800;
  margin: 0;
}

.order-link-btn {
  font-size: 0.82rem;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
  background: #eff6ff;
  padding: 4px 10px;
  border-radius: 8px;
}

.card-sub-tag {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  padding: 3px 8px;
  border-radius: 6px;
}

.info-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.item-label {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 600;
}

.item-value {
  font-size: 0.9rem;
  color: #0f172a;
}

.reason-banner {
  padding: 14px;
  border-radius: 12px;
  border: 1px solid transparent;
}

.reason-banner--danger { background: #fef2f2; border-color: #fecaca; }
.reason-banner--warning { background: #fffbeb; border-color: #fde68a; }
.reason-banner--info { background: #eff6ff; border-color: #bfdbfe; }
.reason-banner--purple { background: #faf5ff; border-color: #e9d5ff; }
.reason-banner--neutral { background: #f8fafc; border-color: #e2e8f0; }

.reason-tag {
  font-size: 0.88rem;
  color: #0f172a;
}

.reason-desc-text {
  margin: 6px 0 0 0;
  font-size: 0.85rem;
  color: #475569;
  line-height: 1.5;
}

/* Items Table */
.items-table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th {
  background: #f8fafc;
  padding: 10px 12px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
}

.items-table td {
  padding: 12px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.88rem;
}

.product-info-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.product-thumb-mini {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  flex-shrink: 0;
}

.product-info-text {
  display: flex;
  flex-direction: column;
}

.variant-chip {
  font-size: 0.75rem;
  color: #64748b;
  display: block;
}

.qty-pill {
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
}

/* Evidence Gallery */
.evidence-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 12px;
}

.evidence-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid #e2e8f0;
}

.evidence-item img, .evidence-item video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.evidence-item:hover .item-overlay {
  opacity: 1;
}

.play-btn-circle {
  position: absolute;
  inset: 0;
  margin: auto;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Actions in Side Column */
.block-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 6px;
  display: block;
}

.admin-textarea {
  width: 100%;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  padding: 10px;
  font-size: 0.85rem;
  outline: none;
  font-family: inherit;
  box-sizing: border-box;
}

.admin-textarea:focus {
  border-color: #e63b6f;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.guide-box {
  padding: 12px;
  border-radius: 10px;
  font-size: 0.85rem;
  line-height: 1.4;
  margin-bottom: 12px;
}

.guide-box.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.guide-box.warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

.action-btn-row {
  display: flex;
  gap: 10px;
}

.btn-flow {
  width: 100%;
  padding: 10px 16px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.88rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-approve {
  background: #e63b6f;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.2);
}

.btn-approve:hover { background: #d0295d; }

.btn-reject {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #dc2626;
}

.btn-reject:hover { background: #fee2e2; }

.btn-received {
  background: #0284c7;
  color: #ffffff;
}

.btn-received:hover { background: #0369a1; }

.btn-qc-submit {
  background: #8b5cf6;
  color: #ffffff;
  margin-top: 10px;
}

.btn-qc-submit:hover { background: #7c3aed; }

.btn-refund-submit {
  background: #16a34a;
  color: #ffffff;
  margin-top: 12px;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
}

.btn-refund-submit:hover { background: #15803d; }

/* QC section */
.qc-head-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.qc-head-title {
  font-weight: 700;
  font-size: 0.85rem;
  color: #0f172a;
}

.qc-quick-btns {
  display: flex;
  gap: 6px;
}

.qc-quick-btn {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

.qc-quick-btn.pass { background: #dcfce7; color: #16a34a; }
.qc-quick-btn.fail { background: #fee2e2; color: #dc2626; }

.qc-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.qc-item-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.qc-item-name {
  font-size: 0.82rem;
  color: #0f172a;
}

.qc-input-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.qc-field label {
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  display: block;
  margin-bottom: 2px;
}

.qc-num-input {
  width: 100%;
  padding: 6px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  text-align: center;
  font-weight: 700;
}

.qc-num-input.success { color: #16a34a; border-color: #86efac; }
.qc-num-input.danger { color: #dc2626; border-color: #fca5a5; }

.qc-note-input {
  width: 100%;
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-size: 0.78rem;
}

/* Refund Settlement */
.currency-input-box {
  position: relative;
  display: flex;
  align-items: center;
}

.refund-input {
  width: 100%;
  padding: 10px 48px 10px 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  font-size: 1.1rem;
  font-weight: 800;
  color: #e63b6f;
  outline: none;
}

.currency-tag {
  position: absolute;
  right: 12px;
  font-weight: 800;
  color: #94a3b8;
  font-size: 0.85rem;
}

.refund-method-card {
  margin-top: 10px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}

.method-title {
  font-size: 0.75rem;
  color: #64748b;
  display: block;
}

.method-badge {
  font-size: 0.9rem;
  color: #0f172a;
}

.method-note {
  font-size: 0.78rem;
  color: #059669;
  margin: 6px 0 0 0;
  line-height: 1.4;
}

.bank-details-box {
  margin-top: 8px;
  font-size: 0.82rem;
  line-height: 1.4;
  color: #334155;
  background: #ffffff;
  padding: 8px;
  border-radius: 8px;
  border: 1px dashed #cbd5e1;
}

.completed-state-box {
  text-align: center;
  padding: 24px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.completed-title {
  font-weight: 800;
  color: #16a34a;
  font-size: 1rem;
  margin: 0;
}

.completed-desc {
  font-size: 0.82rem;
  color: #64748b;
}

/* Logistics info */
.logistics-info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-size: 0.85rem;
}

.logistics-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.logistics-label {
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 600;
}

.tracking-display-box {
  margin-top: 8px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  padding: 10px;
}

.carrier-tag {
  display: inline-block;
  font-size: 0.72rem;
  font-weight: 700;
  background: #0284c7;
  color: #ffffff;
  padding: 2px 6px;
  border-radius: 4px;
  margin-right: 6px;
}

.tracking-code-text {
  font-weight: 800;
  color: #0369a1;
  font-family: monospace;
}

/* Quick Logistics Tools */
.logistics-quick-tools {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #0f172a;
}

.tool-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.tool-btn.tracking-btn {
  color: #0284c7;
  border-color: #bae6fd;
  background: #f0f9ff;
}

.tool-btn.tracking-btn:hover {
  background: #e0f2fe;
}

.tool-btn.print-btn {
  color: #475569;
}

.tool-btn.redispatch-btn {
  color: #e63b6f;
  border-color: #fbcfe8;
  background: #fff1f5;
}

.tool-btn.redispatch-btn:hover {
  background: #ffe4ec;
}

.btn-flow.btn-dispatch-open {
  background: #0ea5e9;
  color: #ffffff;
}

.btn-flow.btn-dispatch-open:hover {
  background: #0284c7;
}

.btn-flow.btn-mark-returning {
  background: #6366f1;
  color: #ffffff;
}

.btn-flow.btn-mark-returning:hover {
  background: #4f46e5;
}

.tracking-actions-row {
  display: flex;
  gap: 6px;
}

.btn-micro {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-micro:hover {
  background: #f1f5f9;
}

.btn-micro.tracking-btn {
  color: #0284c7;
  border-color: #bae6fd;
  background: #f0f9ff;
}

.btn-micro.tracking-btn:hover {
  background: #e0f2fe;
}

.btn-micro.print-btn {
  color: #475569;
}

.btn-subtle-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: #e63b6f;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 6px;
  transition: all 0.15s ease;
}

.btn-subtle-link:hover {
  background: #fff1f5;
}

.dropoff-note-box {
  border-top: 1px solid #f1f5f9;
}

.btn-subtle-outline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px dashed #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-subtle-outline:hover {
  border-color: #64748b;
  color: #0f172a;
  background: #f8fafc;
}

.empty-tracking-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  margin-top: 6px;
}

.empty-tracking-text {
  font-size: 0.75rem;
  color: #64748b;
}

.btn-micro-cta {
  background: none;
  border: none;
  color: #e63b6f;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 4px;
}

.btn-micro-cta:hover {
  text-decoration: underline;
}

/* Bank Reference input */
.bank-ref-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bank-ref-input {
  width: 100%;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  font-size: 0.85rem;
  font-family: monospace;
}

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 16px;
}

.dispatch-modal-box,
.tracking-modal-box {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 540px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0f172a;
}

.modal-title-wrap h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.btn-close-modal {
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.2rem;
  cursor: pointer;
  padding: 4px;
}

.btn-close-modal:hover {
  color: #0f172a;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-heading {
  font-size: 0.82rem;
  font-weight: 700;
  color: #0f172a;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.carrier-options-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.carrier-choice {
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.carrier-choice.is-selected {
  border-color: #e63b6f;
  background: #fff1f5;
}

.carrier-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: #0f172a;
}

.carrier-badge {
  font-size: 0.65rem;
  font-weight: 700;
  background: #e63b6f;
  color: #ffffff;
  padding: 1px 6px;
  border-radius: 4px;
}

.carrier-sub {
  font-size: 0.72rem;
  color: #64748b;
  display: block;
  margin-top: 4px;
  line-height: 1.3;
}

/* Dispatch Route Preview */
.dispatch-route-box {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
}

.route-point {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.point-badge {
  display: inline-block;
  align-self: flex-start;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 4px;
}

.point-badge.pickup {
  background: #e0f2fe;
  color: #0369a1;
}

.point-badge.dropoff {
  background: #dcfce7;
  color: #15803d;
}

.point-name {
  font-size: 0.88rem;
  color: #0f172a;
}

.point-phone {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: normal;
}

.point-addr {
  margin: 0;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.35;
}

.route-divider-icon {
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
}

.dispatch-sync-notice {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 10px;
  padding: 10px 12px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: 0.78rem;
  color: #1e40af;
  line-height: 1.4;
}

.dispatch-sync-notice p {
  margin: 0;
}

.dispatch-sync-notice code {
  background: #dbeafe;
  padding: 1px 5px;
  border-radius: 4px;
  font-weight: 700;
  color: #1d4ed8;
}

.node-party {
  margin: 2px 0 0 0;
  font-size: 0.88rem;
  color: #0f172a;
}

.phone-meta {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: normal;
}

.dimension-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.dim-field label {
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 600;
  display: block;
  margin-bottom: 4px;
}

.modal-input,
.modal-textarea {
  width: 100%;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 0.85rem;
  color: #0f172a;
  outline: none;
  box-sizing: border-box;
}

.modal-input:focus,
.modal-textarea:focus {
  border-color: #e63b6f;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
}

.btn-cancel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
}

.btn-confirm-dispatch {
  background: #e63b6f;
  border: none;
  border-radius: 8px;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-confirm-dispatch:hover {
  background: #d42a5e;
}

/* Tracking Modal Details */
.tracking-loading-state {
  text-align: center;
  padding: 40px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #64748b;
}

.spinner-ocean {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #e63b6f;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.tracking-meta-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: #f0f9ff;
  border-radius: 10px;
  border: 1px solid #bae6fd;
}

.tracking-code-badge {
  font-size: 0.88rem;
  color: #0369a1;
  display: flex;
  gap: 6px;
  align-items: center;
}

.tracking-code-badge strong {
  font-family: monospace;
  font-size: 1rem;
}

.carrier-pill {
  font-size: 0.72rem;
  font-weight: 700;
  background: #0284c7;
  color: #ffffff;
  padding: 3px 8px;
  border-radius: 6px;
}

.tracking-route-card {
  padding: 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.route-node {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.node-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
}

.node-dot.sender {
  background: #0284c7;
}

.node-dot.receiver {
  background: #16a34a;
}

.node-type {
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 700;
  text-transform: uppercase;
  display: block;
}

.node-addr {
  margin: 2px 0 0 0;
  font-size: 0.85rem;
  color: #0f172a;
  font-weight: 500;
}

.route-line {
  position: absolute;
  left: 19px;
  top: 24px;
  bottom: 24px;
  width: 2px;
  background: #cbd5e1;
}

.tracking-timeline-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.timeline-title {
  margin: 0;
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
}

.timeline-list {
  display: flex;
  flex-direction: column;
  position: relative;
  padding-left: 16px;
  border-left: 2px solid #e2e8f0;
  gap: 16px;
}

.timeline-entry {
  position: relative;
}

.entry-bullet {
  position: absolute;
  left: -22px;
  top: 3px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #cbd5e1;
  border: 2px solid #ffffff;
}

.timeline-entry.is-latest .entry-bullet {
  background: #e63b6f;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.2);
}

.entry-time {
  font-size: 0.72rem;
  color: #94a3b8;
  display: block;
}

.entry-status {
  font-size: 0.85rem;
  color: #0f172a;
  display: block;
}

.entry-desc {
  margin: 2px 0 0 0;
  font-size: 0.78rem;
  color: #64748b;
}

.empty-logs {
  text-align: center;
  padding: 20px;
  font-size: 0.82rem;
  color: #64748b;
}

.entry-status-badge {
  font-size: 0.84rem;
  font-weight: 700;
  display: block;
  margin-bottom: 2px;
}

.btn-refresh-tracking {
  background: #0f172a;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 8px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

/* guide-box success variant */
.guide-box.success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 0.84rem;
  color: #166534;
}

.guide-box.success p {
  margin: 0 0 4px 0;
}

/* receive items list (nhập số lượng nhận hàng tại kho) */
.receive-items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0;
}

.receive-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
}

.receive-item-name {
  font-size: 0.84rem;
  font-weight: 600;
  color: #0f172a;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.receive-qty-box {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.receive-qty-label {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 600;
}

.receive-qty-input {
  width: 60px;
  padding: 5px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 700;
  text-align: center;
  color: #0f172a;
  background: #fff;
}

.receive-qty-input:focus {
  outline: none;
  border-color: #e63b6f;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.12);
}

.receive-qty-max {
  font-size: 0.78rem;
  color: #94a3b8;
}

/* Nut dong bo trang thai OE thu cong */
.btn-sync-oe {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  padding: 7px 14px;
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  border-radius: 8px;
  color: #1d4ed8;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-sync-oe:hover:not(:disabled) {
  background: #dbeafe;
  border-color: #93c5fd;
}

.btn-sync-oe:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>


