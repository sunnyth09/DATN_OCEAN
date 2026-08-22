<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import { useToast } from '@/composables/useToast';
import MediaPreviewModal from '@/components/MediaPreviewModal.vue';
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
  refund_amount: '',
  refund_method: 'bank_transfer',
  admin_note: '',
});
const logisticsForm = reactive({
  return_tracking_code: '',
  return_carrier: '',
});
const receivedItems = ref({});
const inspectionItems = ref({});

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

const parseNumberVND = (val) => {
  if (!val) return 0;
  return Number(String(val).replace(/\D/g, ''));
};

const handleRefundAmountInput = (e) => {
  const cleanVal = e.target.value.replace(/\D/g, '');
  const rawValue = cleanVal ? parseInt(cleanVal, 10) : 0;
  refundForm.refund_amount = rawValue;
  refundAmountDisplay.value = formatNumberVND(rawValue);
  e.target.value = refundAmountDisplay.value;
};

const detail = computed(() => currentRequest.value);
const refundAmountLabel = computed(() => detail.value?.refund_status === 'success' ? 'Đã hoàn:' : 'Dự kiến hoàn:');

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

const storageBaseUrl = () => {
  const apiUrl = import.meta.env.VITE_API_URL;
  if (apiUrl && /^https?:\/\//i.test(apiUrl)) {
    return apiUrl.replace(/\/api\/?$/, '').replace(/\/+$/, '');
  }

  return `${window.location.protocol}//${window.location.hostname}:8383`;
};

const imageUrl = (path) => {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;

  const normalizedPath = String(path)
    .replace(/^\/+/, '')
    .replace(/^storage\/+/, '');

  return `${storageBaseUrl()}/storage/${normalizedPath}`;
};
const isStatus = (...statuses) => statuses.includes(detail.value?.status);

const getReasonTone = (reason) => {
  if (!reason) return 'neutral';
  const r = reason.toLowerCase();
  if (r.includes('lỗi') || r.includes('hư') || r.includes('hỏng') || r.includes('rách') || r.includes('vỡ') || r.includes('kém chất lượng')) return 'danger';
  if (r.includes('mô tả') || r.includes('sai') || r.includes('thiếu') || r.includes('nhầm') || r.includes('giao sai')) return 'warning';
  if (r.includes('size') || r.includes('vừa') || r.includes('kích') || r.includes('màu')) return 'info';
  if (r.includes('đổi ý') || r.includes('nhu cầu') || r.includes('không thích') || r.includes('mua nhầm')) return 'purple';
  return 'neutral';
};

const refreshDetail = async () => {
  await store.fetchAdminReturnRequestDetail(route.params.id);

  if (detail.value) {
    adminNote.value = detail.value.admin_note || '';
    refundForm.refund_amount = detail.value.refund_amount || detail.value.order?.grand_total || '';
    refundAmountDisplay.value = formatNumberVND(refundForm.refund_amount);
    refundForm.refund_method = detail.value.refund_method || 'bank_transfer';
    refundForm.admin_note = detail.value.admin_note || '';
    logisticsForm.return_tracking_code = detail.value.return_tracking_code || '';
    logisticsForm.return_carrier = detail.value.return_carrier || '';
    receivedItems.value = Object.fromEntries((detail.value.items || []).map((item) => [item.id, item.received_quantity || item.requested_quantity || 0]));
    inspectionItems.value = Object.fromEntries((detail.value.items || []).map((item) => [item.id, {
      qc_pass_quantity: item.qc_pass_quantity || item.received_quantity || 0,
      qc_fail_quantity: item.qc_fail_quantity || 0,
      qc_note: item.qc_note || '',
    }]));
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

const normalizeQuantity = (value, max) => {
  const digits = String(value ?? '').replace(/\D/g, '');
  const parsed = Number(digits || 0);
  return Math.max(0, Math.min(parsed, Number(max || 0)));
};

const updateReceivedQuantity = (item, value) => {
  receivedItems.value = {
    ...receivedItems.value,
    [item.id]: normalizeQuantity(value, item.requested_quantity),
  };
};

const updateInspectionQuantity = (item, field, value) => {
  const current = inspectionItems.value[item.id] || {};
  inspectionItems.value = {
    ...inspectionItems.value,
    [item.id]: {
      ...current,
      [field]: normalizeQuantity(value, item.received_quantity),
    },
  };
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

const markReturning = () => runAction(() => store.markReturnReturning(route.params.id, {
  admin_note: adminNote.value || null,
  return_tracking_code: logisticsForm.return_tracking_code || null,
  return_carrier: logisticsForm.return_carrier || null,
}));

const markReceived = () => runAction(() => store.markReturnReceived(route.params.id, {
  admin_note: adminNote.value || null,
  items: (detail.value?.items || []).map((item) => ({
    return_request_item_id: item.id,
    received_quantity: Number(receivedItems.value[item.id] ?? item.requested_quantity ?? 0),
  })),
}));

const inspect = () => runAction(() => store.inspectReturnRequest(route.params.id, {
  inspection_note: adminNote.value || null,
  items: (detail.value?.items || []).map((item) => ({
    return_request_item_id: item.id,
    qc_pass_quantity: Number(inspectionItems.value[item.id]?.qc_pass_quantity ?? 0),
    qc_fail_quantity: Number(inspectionItems.value[item.id]?.qc_fail_quantity ?? 0),
    qc_note: inspectionItems.value[item.id]?.qc_note || null,
  })),
}));

const refund = () => {
  if (refundForm.refund_amount === '' || Number(refundForm.refund_amount) < 0) {
    showToast('Vui lòng nhập số tiền hoàn hợp lệ.', 'error');
    return;
  }

  return runAction(() => store.refundReturnRequest(route.params.id, {
    refund_amount: Number(refundForm.refund_amount),
    refund_method: refundForm.refund_method || detail.value?.refund_method || 'wallet',
    admin_note: adminNote.value || null,
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
              <h1 class="page-title">Yêu cầu <span class="order-code">{{ detail.return_code || `#${detail.order?.order_code || detail.order_id}` }}</span></h1>
            </div>
          </div>
          <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
            {{ getReturnRequestStatusLabel(detail.status) }}
          </span>
        </div>

        <div class="info-list">
          <div class="info-row"><span>Khách hàng</span><strong>{{ detail.user?.full_name || detail.order?.recipient_name }}</strong></div>
          <div class="info-row"><span>Email / SĐT</span><strong>{{ detail.user?.email || detail.order?.recipient_phone || '—' }}</strong></div>
          <div class="info-row">
            <span>Lý do</span>
            <div class="reason-pill" :class="`reason-pill--${getReasonTone(detail.reason)}`">
              <span class="reason-icon">
                <svg v-if="getReasonTone(detail.reason) === 'danger'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <svg v-else-if="getReasonTone(detail.reason) === 'warning'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <svg v-else-if="getReasonTone(detail.reason) === 'info'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                  <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <svg v-else-if="getReasonTone(detail.reason) === 'purple'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="16" x2="12" y2="12"></line>
                  <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
              </span>
              <strong class="reason-text">{{ detail.reason }}</strong>
            </div>
          </div>
          <div class="info-row"><span>Đơn hàng</span><strong>{{ getOrderStatusLabel(detail.order?.fulfillment_status) }}</strong></div>
          <div class="info-row"><span>Thanh toán</span><strong>{{ getPaymentStatusLabel(detail.order?.payment_status) }}</strong></div>
          <div class="info-row"><span>Hoàn tiền</span><strong>{{ getReturnRefundStatusLabel(detail.refund_status) }}</strong></div>
          <div class="info-row"><span>Gửi lúc</span><strong>{{ formatDate(detail.requested_at || detail.created_at) }}</strong></div>
        </div>

        <div class="detail-block">
          <h3>Mô tả</h3>
          <div class="description-content" v-html="detail.description || 'Không có mô tả bổ sung.'"></div>
        </div>

        <div v-if="detail.images?.length || detail.videos?.length" class="detail-block">
          <h3>Minh chứng</h3>
          <div v-if="detail.images?.length" class="evidence-grid">
            <img
              v-for="image in detail.images"
              :key="image"
              :src="imageUrl(image)"
              alt="Ảnh minh chứng"
              class="clickable-evidence"
              @click="openPreview(imageUrl(image), 'image')"
            />
          </div>
          <div v-if="detail.videos?.length" class="evidence-grid evidence-grid--video">
            <div
              v-for="video in detail.videos"
              :key="video"
              class="video-thumbnail-container"
              @click="openPreview(imageUrl(video), 'video')"
            >
              <video :src="imageUrl(video)" preload="metadata" />
              <div class="play-overlay">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <div class="detail-block">
          <h3>Cách gửi hàng hoàn</h3>
          <p><strong>{{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</strong></p>
          <template v-if="detail.return_shipping_method === 'pickup_original_address'">
            <p>Địa chỉ lấy hàng: {{ detail.return_pickup_name || detail.order?.recipient_name }} · {{ detail.return_pickup_phone || detail.order?.recipient_phone }}</p>
            <p>{{ detail.return_pickup_address || detail.order?.shipping_address }}</p>
          </template>
          <p v-else-if="detail.return_shipping_method === 'dropoff_post_office'">
            Khách chọn tự gửi/mang hàng lên bưu cục sau khi yêu cầu được duyệt.
          </p>
          <p v-if="detail.return_carrier || detail.return_tracking_code">
            Vận chuyển: <strong>{{ detail.return_carrier || '—' }}</strong> · Mã vận đơn: <strong>{{ detail.return_tracking_code || '—' }}</strong>
          </p>
          <p v-else-if="detail.status === 'return_approved' && detail.return_shipping_method === 'pickup_original_address'" class="warning-text">
            Chưa có mã vận đơn hoàn. Có thể GHN chưa tạo được vận đơn, vui lòng xử lý thủ công nếu cần.
          </p>
        </div>

        <div v-if="detail.refund_method === 'vnpay' && detail.refund_status === 'pending'" class="detail-block warning-text">
          <h3>Hoàn tiền VNPay</h3>
          <p>Yêu cầu hoàn tiền VNPay đang chờ xử lý/đối soát, chưa tự đánh dấu hoàn tiền thành công.</p>
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

        <div v-if="detail.refund_transactions?.length" class="detail-block">
          <h3>Lịch sử hoàn tiền</h3>
          <div class="refund-history-row" v-for="tx in detail.refund_transactions" :key="tx.id">
            <span>{{ getRefundMethodLabel(tx.method) }}</span>
            <strong>{{ formatPrice(tx.amount) }}</strong>
            <em>{{ tx.status }}</em>
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

        <div v-if="isStatus('pending', 'return_pending')" class="action-group">
          <div class="status-note">
            <p><strong>Cách gửi:</strong> {{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</p>
            <p v-if="detail.return_shipping_method === 'pickup_original_address'">Khi duyệt, hệ thống sẽ tự tạo vận đơn lấy hàng hoàn tại địa chỉ đơn cũ nếu GHN khả dụng.</p>
            <p v-else>Khách sẽ tự gửi hàng lên bưu cục/điểm gửi sau khi yêu cầu được duyệt.</p>
          </div>
          <button class="action-btn action-btn--approve" :disabled="actionLoading" @click="approve">Duyệt yêu cầu</button>
          <button class="action-btn action-btn--reject" :disabled="actionLoading" @click="reject">Từ chối yêu cầu</button>
        </div>

        <div v-else-if="isStatus('approved', 'return_approved')" class="action-group">
          <div class="status-note">
            <p><strong>Cách gửi:</strong> {{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</p>
            <p v-if="detail.return_tracking_code">Mã vận đơn hoàn: <strong>{{ detail.return_tracking_code }}</strong></p>
            <p v-else-if="detail.return_shipping_method === 'pickup_original_address'">GHN chưa tạo được mã vận đơn tự động. Bạn vẫn có thể xác nhận kho nhận hàng khi hàng được gửi về.</p>
            <p v-else>Khách tự gửi hàng tại bưu cục/điểm gửi.</p>
          </div>
          <button class="action-btn action-btn--received" :disabled="actionLoading" @click="markReceived">
            Xác nhận kho đã nhận hàng
          </button>
        </div>

        <div v-else-if="isStatus('returning')" class="action-group">
          <div v-for="item in detail.items" :key="item.id" class="process-item-row">
            <span>{{ item.order_item?.product_name || item.product?.name }}</span>
            <input
              :value="receivedItems[item.id]"
              type="text"
              inputmode="numeric"
              pattern="[0-9]*"
              class="field-input quantity-input"
              placeholder="0"
              @input="updateReceivedQuantity(item, $event.target.value)"
              @blur="updateReceivedQuantity(item, $event.target.value)"
            />
          </div>
          <button class="action-btn action-btn--received" :disabled="actionLoading" @click="markReceived">
            Xác nhận kho đã nhận hàng
          </button>
        </div>

        <div v-else-if="isStatus('warehouse_received', 'received')" class="action-group">
          <div v-for="item in detail.items" :key="item.id" class="process-qc-card">
            <strong class="qc-product-title">{{ item.order_item?.product_name || item.product?.name }}</strong>
            <div class="qc-inputs-grid">
              <div class="qc-input-group">
                <label>QC đạt</label>
                <input
                  :value="inspectionItems[item.id].qc_pass_quantity"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  class="field-input quantity-input text-center"
                  placeholder="0"
                  @input="updateInspectionQuantity(item, 'qc_pass_quantity', $event.target.value)"
                  @blur="updateInspectionQuantity(item, 'qc_pass_quantity', $event.target.value)"
                />
              </div>
              <div class="qc-input-group">
                <label>QC lỗi</label>
                <input
                  :value="inspectionItems[item.id].qc_fail_quantity"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  class="field-input quantity-input text-center"
                  placeholder="0"
                  @input="updateInspectionQuantity(item, 'qc_fail_quantity', $event.target.value)"
                  @blur="updateInspectionQuantity(item, 'qc_fail_quantity', $event.target.value)"
                />
              </div>
            </div>
            <input v-model="inspectionItems[item.id].qc_note" class="field-input qc-note-input" placeholder="Ghi chú QC..." />
          </div>
          <button class="action-btn action-btn--approve" :disabled="actionLoading" @click="inspect">
            Lưu kết quả QC
          </button>
        </div>

        <div v-else-if="isStatus('inspected_ok', 'refund_pending', 'refund_failed')" class="action-group">
          <label class="field-label">Số tiền hoàn</label>
          <div class="currency-input-wrapper">
            <input
              :value="refundAmountDisplay"
              type="text"
              class="field-input refund-amount-input"
              placeholder="0"
              @input="handleRefundAmountInput"
            />
            <span class="currency-unit">VND</span>
          </div>

          <label class="field-label">Phương thức hoàn tiền (Khách đã chọn)</label>
          <div class="refund-method-readonly-badge">
            <span class="method-icon">
              <svg v-if="refundForm.refund_method === 'wallet'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>
              <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </span>
            <strong>{{ getRefundMethodLabel(refundForm.refund_method) }}</strong>
          </div>
          <p v-if="refundForm.refund_method === 'wallet'" class="method-help-text">
            💡 Tiền sẽ được <strong>tự động cộng vào Ví điện tử</strong> của khách hàng ngay sau khi bấm Xác nhận.
          </p>
          <p v-else class="method-help-text">
            💡 Admin thực hiện chuyển khoản thủ công cho khách hàng theo thông tin tài khoản bên dưới, sau đó bấm Xác nhận.
          </p>

          <button class="action-btn action-btn--refund" :disabled="actionLoading" @click="refund">
            {{ isStatus('refund_failed', 'refund_pending') ? 'Thử hoàn tiền lại' : 'Xác nhận hoàn tiền' }}
          </button>
        </div>

        <div v-else class="status-note">
          <p>Yêu cầu này không còn thao tác xử lý tiếp theo hoặc đã hoàn tất.</p>
        </div>

        <div class="summary-box">
          <p><strong>Số tiền đơn hàng:</strong> {{ formatPrice(detail.order?.grand_total) }}</p>
          <p v-if="Number(detail.refund_amount || 0) > 0"><strong>{{ refundAmountLabel }}</strong> {{ formatPrice(detail.refund_amount) }}</p>
          <p v-if="detail.refund_method"><strong>Phương thức:</strong> {{ getRefundMethodLabel(detail.refund_method) }}</p>
          
          <div v-if="detail.refund_method === 'bank_transfer' && detail.user?.bank_accounts?.length" style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border-color);">
            <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; margin-bottom: 6px;">THÔNG TIN CHUYỂN KHOẢN (MẶC ĐỊNH):</p>
            <div v-for="bank in detail.user.bank_accounts.filter(b => b.is_default)" :key="bank.id" style="background: var(--surface-container-low); padding: 10px; border-radius: 8px; font-size: 0.9rem;">
              <p style="margin: 0 0 4px 0;">Ngân hàng: <strong>{{ bank.bank_name }}</strong></p>
              <p style="margin: 0 0 4px 0;">STK: <strong>{{ bank.account_number }}</strong></p>
              <p style="margin: 0;">Chủ TK: <strong>{{ bank.account_name }}</strong></p>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Image & Video Media Preview Lightbox -->
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
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 12px;
  color: var(--text-main);
  font-size: 0.95rem;
}

.info-row span:first-child {
  color: var(--text-muted);
}

.reason-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 600;
  line-height: 1.4;
  border: 1px solid transparent;
}

.reason-pill--danger {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fecaca;
}

.reason-pill--warning {
  background: #fffbeb;
  color: #b45309;
  border-color: #fde68a;
}

.reason-pill--info {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.reason-pill--purple {
  background: #faf5ff;
  color: #7e22ce;
  border-color: #e9d5ff;
}

.reason-pill--neutral {
  background: #f8fafc;
  color: #334155;
  border-color: #e2e8f0;
}

:global(html.dark) .reason-pill--danger {
  background: rgba(239, 68, 68, 0.15);
  color: #fca5a5;
  border-color: rgba(239, 68, 68, 0.3);
}
:global(html.dark) .reason-pill--warning {
  background: rgba(245, 158, 11, 0.15);
  color: #fcd34d;
  border-color: rgba(245, 158, 11, 0.3);
}
:global(html.dark) .reason-pill--info {
  background: rgba(59, 130, 246, 0.15);
  color: #93c5fd;
  border-color: rgba(59, 130, 246, 0.3);
}
:global(html.dark) .reason-pill--purple {
  background: rgba(168, 85, 247, 0.15);
  color: #d8b4fe;
  border-color: rgba(168, 85, 247, 0.3);
}
:global(html.dark) .reason-pill--neutral {
  background: rgba(148, 163, 184, 0.15);
  color: #cbd5e1;
  border-color: rgba(148, 163, 184, 0.25);
}

.reason-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
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

.warning-text,
.warning-text p {
  color: #d97706 !important;
}

.evidence-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.evidence-grid img,
.evidence-grid video {
  width: 100%;
  height: 120px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.return-items-table {
  margin-top: 12px;
  border: 1px solid var(--border-color);
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
  background: var(--surface-container-low);
  font-weight: 800;
  color: var(--text-main);
}

.return-items-row {
  border-top: 1px solid var(--border-color);
  color: var(--text-main);
}

.return-product {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.return-product small {
  color: var(--text-muted);
}

.refund-history-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: center;
  padding: 10px 0;
  color: var(--text-main);
}

.refund-history-row + .refund-history-row {
  border-top: 1px dashed var(--border-color);
}

.process-item-row,
.process-qc-row {
  display: grid;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-container-low);
}

.process-item-row {
  grid-template-columns: minmax(0, 1fr) 100px;
  align-items: center;
}

.process-qc-row label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted);
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

/* Clickable evidence images */
.clickable-evidence {
  cursor: zoom-in;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.clickable-evidence:hover {
  opacity: 0.9;
  transform: scale(1.02);
}

/* Video Thumbnail Container for Lightbox Preview */
.video-thumbnail-container {
  position: relative;
  width: 100%;
  height: 120px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  cursor: zoom-in;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.video-thumbnail-container video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.85;
  pointer-events: none; /* Let clicks pass to the container */
}

.play-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(15, 23, 42, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s, transform 0.2s;
}

.video-thumbnail-container:hover .play-overlay {
  background: rgba(15, 23, 42, 0.2);
  transform: scale(1.05);
}

/* VND Currency Input styling */
.currency-input-wrapper {
  position: relative;
  width: 100%;
}

.refund-amount-input {
  padding-right: 60px !important;
  font-weight: 700 !important;
  color: var(--primary) !important;
  font-size: 1.1rem !important;
}

.currency-unit {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  font-weight: 700;
  color: var(--text-muted);
  font-size: 0.85rem;
  letter-spacing: 0.5px;
  pointer-events: none;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* QC Card Styling */
.process-qc-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-container-low);
  margin-bottom: 12px;
}

.qc-product-title {
  font-weight: 700;
  font-size: 0.88rem;
  color: var(--text-main);
  line-height: 1.4;
}

.qc-inputs-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.qc-input-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.qc-input-group label {
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--text-muted);
}

.qc-input-group .quantity-input {
  text-align: center;
  font-weight: 700;
}

.qc-note-input {
  font-size: 0.82rem;
  padding: 8px 12px !important;
}

.text-center {
  text-align: center;
}

.refund-method-readonly-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 10px;
  font-size: 0.95rem;
  color: var(--text-main);
}

.method-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
}

.method-help-text {
  margin: 6px 0 12px;
  font-size: 0.84rem;
  color: #0369a1;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  padding: 8px 12px;
  border-radius: 8px;
  line-height: 1.45;
}
</style>
