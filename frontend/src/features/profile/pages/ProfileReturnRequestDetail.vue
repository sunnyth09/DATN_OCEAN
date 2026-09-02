<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import {
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getReturnRefundStatusLabel,
  getOrderStatusLabel,
  getRefundMethodLabel,
  getReturnShippingMethodLabel,
} from '@/utils/orderStatus';

import MediaPreviewModal from '@/components/MediaPreviewModal.vue';
import { getStorageUrl } from '@/utils/url';

const route = useRoute();
const router = useRouter();
const store = useReturnRequestStore();
const { currentRequest, detailLoading } = storeToRefs(store);

const detail = computed(() => currentRequest.value);

const copiedCode = ref(false);
const copyCode = async (text) => {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    copiedCode.value = true;
    setTimeout(() => {
      copiedCode.value = false;
    }, 2000);
  } catch (err) {
    console.error('Copy failed', err);
  }
};

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

const heroInfo = computed(() => {
  if (!detail.value) return {};
  const status = detail.value.status;

  switch (status) {
    case 'return_pending':
    case 'pending':
      return {
        tone: 'pending',
        title: 'Yêu cầu đang chờ Shop xét duyệt',
        desc: 'Hệ thống đã tiếp nhận yêu cầu hoàn trả. Đội ngũ CSKH Ocean Sport sẽ kiểm tra và phản hồi trong vòng 24 giờ làm việc.',
      };
    case 'return_approved':
    case 'approved':
      return {
        tone: 'approved',
        title: 'Yêu cầu hoàn hàng đã được chấp thuận',
        desc: 'Vui lòng đóng gói sản phẩm cẩn thận. Đơn vị vận chuyển sẽ thu hồi hoặc bạn có thể gửi tại bưu cục theo phương thức đã chọn.',
      };
    case 'returning':
      return {
        tone: 'returning',
        title: 'Kiện hàng đang được vận chuyển về kho',
        desc: 'Đơn vị vận chuyển đang trung chuyển gói hàng về trung tâm tiếp nhận & thẩm định của Ocean Sport.',
      };
    case 'warehouse_received':
    case 'received':
      return {
        tone: 'received',
        title: 'Kho đã tiếp nhận kiện hàng hoàn',
        desc: 'Chuyên viên kỹ thuật đang kiểm tra tình trạng thực tế của sản phẩm (QC) để xác thực điều kiện hoàn trả.',
      };
    case 'inspected_ok':
    case 'refund_pending':
    case 'refunding':
      return {
        tone: 'refunding',
        title: 'Kiểm định đạt chuẩn — Đang xử lý hoàn tiền',
        desc: 'Sản phẩm hoàn toàn hợp lệ. Lệnh hoàn tiền đang được thực hiện tới tài khoản / ví điện tử của bạn.',
      };
    case 'return_completed':
    case 'refunded':
      return {
        tone: 'completed',
        title: 'Hoàn hàng & Hoàn tiền thành công',
        desc: 'Số tiền hoàn đã được cộng thành công vào tài khoản của bạn. Cảm ơn bạn đã đồng hành cùng Ocean Sport!',
      };
    case 'return_rejected':
    case 'rejected':
      return {
        tone: 'rejected',
        title: 'Yêu cầu hoàn hàng bị từ chối',
        desc: 'Yêu cầu chưa đáp ứng điều kiện hoàn trả theo quy định. Vui lòng xem lý do chi tiết bên dưới hoặc liên hệ CSKH.',
      };
    case 'inspection_failed':
      return {
        tone: 'failed',
        title: 'Kiểm định sản phẩm không đạt điều kiện',
        desc: 'Sản phẩm gửi về không khớp tình trạng ban đầu hoặc không đủ tiêu chuẩn hoàn. Đội ngũ CSKH sẽ liên hệ trực tiếp với bạn.',
      };
    default:
      return {
        tone: 'neutral',
        title: 'Chi tiết yêu cầu hoàn hàng',
        desc: 'Theo dõi tiến trình xử lý chi tiết bên dưới.',
      };
  }
});

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

const openPreview = (url) => {
  const foundIdx = mediaList.value.findIndex((item) => item.url === url);
  previewIndex.value = foundIdx >= 0 ? foundIdx : 0;
  previewShow.value = true;
};

const closePreview = () => {
  previewShow.value = false;
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

const showCustomerTrackingModal = ref(false);
const customerTrackingLoading = ref(false);
const customerTrackingInfo = ref(null);

const openCustomerTrackingModal = async () => {
  showCustomerTrackingModal.value = true;
  customerTrackingLoading.value = true;
  try {
    const res = await store.getMyReturnTracking(route.params.id);
    if (res.status === 'success') {
      customerTrackingInfo.value = res.data;
    }
  } catch (err) {
    console.error('Failed to get return tracking', err);
  } finally {
    customerTrackingLoading.value = false;
  }
};

const closeCustomerTrackingModal = () => {
  showCustomerTrackingModal.value = false;
};

onMounted(() => {
  store.fetchMyReturnRequestDetail(route.params.id);
});

const goBack = () => {
  router.push({ name: 'profile-return-requests' });
};
</script>

<template>
  <div class="profile-return-detail-page animate-in">
    <!-- Top Action & Breadcrumb Navigation -->
    <div class="top-nav-bar">
      <button class="btn-back-link" @click="goBack">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        <span>Danh sách yêu cầu</span>
      </button>

      <div v-if="detail" class="header-meta-group">
        <div class="return-code-pill" @click="copyCode(detail.return_code)" title="Bấm để sao chép mã">
          <span class="code-label">MÃ YÊU CẦU:</span>
          <span class="code-value">{{ detail.return_code || `#${detail.order?.order_code || detail.order_id}` }}</span>
          <svg v-if="!copiedCode" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          <span v-else class="copied-indicator">Đã chép</span>
        </div>

        <span class="status-badge" :class="getReturnRequestStatusTone(detail.status)">
          <span class="pulse-dot"></span>
          {{ getReturnRequestStatusLabel(detail.status) }}
        </span>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="detailLoading" class="skeleton-container">
      <div class="skeleton-banner"></div>
      <div class="skeleton-grid">
        <div class="skeleton-main-card"></div>
        <div class="skeleton-side-card"></div>
      </div>
    </div>

    <!-- Main Detail Content -->
    <div v-else-if="detail" class="main-content-flow">
      <!-- 1. Hero Status Banner (Clean White Card with Subtle Top Accent) -->
      <div class="hero-status-card">
        <div class="hero-header">
          <div class="hero-icon-box">
            <!-- Icon pending -->
            <svg v-if="heroInfo.tone === 'pending'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <!-- Icon approved/returning -->
            <svg v-else-if="['approved', 'returning'].includes(heroInfo.tone)" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <!-- Icon received/QC -->
            <svg v-else-if="['received', 'refunding'].includes(heroInfo.tone)" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            <!-- Icon completed -->
            <svg v-else-if="heroInfo.tone === 'completed'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <!-- Icon rejected/failed -->
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          </div>

          <div class="hero-text-box">
            <h1 class="hero-title">{{ heroInfo.title }}</h1>
            <p class="hero-desc">{{ heroInfo.desc }}</p>
          </div>
        </div>

        <!-- 5-Stage Seamless Stepper -->
        <div class="stepper-track">
          <!-- Step 1 -->
          <div class="stepper-step" :class="getStepStatus('pending')">
            <div class="step-head">
              <div class="step-bullet">
                <svg v-if="getStepStatus('pending') === 'done'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else>1</span>
              </div>
              <div class="step-line step-line--right" :class="{ 'done': ['active', 'done'].includes(getStepStatus('approved')) }"></div>
            </div>
            <div class="step-body">
              <span class="step-title">Gửi yêu cầu</span>
              <span class="step-sub">{{ formatDate(detail.requested_at || detail.created_at) }}</span>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="stepper-step" :class="getStepStatus('approved')">
            <div class="step-head">
              <div class="step-line step-line--left" :class="{ 'done': ['active', 'done'].includes(getStepStatus('approved')) }"></div>
              <div class="step-bullet">
                <svg v-if="getStepStatus('approved') === 'done'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else-if="getStepStatus('approved') === 'rejected'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <span v-else>2</span>
              </div>
              <div class="step-line step-line--right" :class="{ 'done': ['active', 'done'].includes(getStepStatus('returning')) }"></div>
            </div>
            <div class="step-body">
              <span class="step-title">Shop xét duyệt</span>
              <span class="step-sub">{{ detail.approved_at ? formatDate(detail.approved_at) : (getStepStatus('approved') === 'rejected' ? 'Từ chối' : 'Chờ xử lý') }}</span>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="stepper-step" :class="getStepStatus('returning')">
            <div class="step-head">
              <div class="step-line step-line--left" :class="{ 'done': ['active', 'done'].includes(getStepStatus('returning')) }"></div>
              <div class="step-bullet">
                <svg v-if="getStepStatus('returning') === 'done'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else>3</span>
              </div>
              <div class="step-line step-line--right" :class="{ 'done': ['active', 'done'].includes(getStepStatus('warehouse_received')) }"></div>
            </div>
            <div class="step-body">
              <span class="step-title">Thu hồi / Gửi hàng</span>
              <span class="step-sub">{{ detail.return_tracking_code ? (detail.return_carrier === 'dropoff_post_office' ? 'Tự gửi bưu điện' : 'Ocean Express') : 'Vận chuyển' }}</span>
            </div>
          </div>

          <!-- Step 4 -->
          <div class="stepper-step" :class="getStepStatus('warehouse_received')">
            <div class="step-head">
              <div class="step-line step-line--left" :class="{ 'done': ['active', 'done'].includes(getStepStatus('warehouse_received')) }"></div>
              <div class="step-bullet">
                <svg v-if="getStepStatus('warehouse_received') === 'done'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else-if="getStepStatus('warehouse_received') === 'failed'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                <span v-else>4</span>
              </div>
              <div class="step-line step-line--right" :class="{ 'done': getStepStatus('return_completed') === 'done' }"></div>
            </div>
            <div class="step-body">
              <span class="step-title">Kiểm định (QC)</span>
              <span class="step-sub">{{ detail.inspected_at ? formatDate(detail.inspected_at) : (detail.received_at ? 'Đã nhận hàng' : 'Tại kho Ocean') }}</span>
            </div>
          </div>

          <!-- Step 5 -->
          <div class="stepper-step" :class="getStepStatus('return_completed')">
            <div class="step-head">
              <div class="step-line step-line--left" :class="{ 'done': getStepStatus('return_completed') === 'done' }"></div>
              <div class="step-bullet">
                <svg v-if="getStepStatus('return_completed') === 'done'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else>5</span>
              </div>
            </div>
            <div class="step-body">
              <span class="step-title">Hoàn tiền</span>
              <span class="step-sub">{{ detail.refunded_at ? formatDate(detail.refunded_at) : getReturnRefundStatusLabel(detail.refund_status) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2-Column Responsive Layout -->
      <div class="detail-two-col-grid">
        <!-- LEFT COLUMN: Main Info Cards -->
        <main class="col-main">
          <!-- Card: Return Reason & Description -->
          <div class="content-card">
            <div class="card-header">
              <div class="card-title-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <h2>Nội dung & Lý do Hoàn trả</h2>
              </div>
            </div>

            <div class="card-body">
              <div class="reason-highlight-row">
                <span class="reason-chip">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#e63b6f" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  <span>{{ detail.reason || 'Sản phẩm lỗi / hư hỏng' }}</span>
                </span>
              </div>

              <div class="description-section">
                <label class="desc-label">Mô tả chi tiết từ bạn:</label>
                <div class="description-text-box">
                  <p v-if="detail.description">{{ detail.description }}</p>
                  <p v-else class="text-muted italic">Không có mô tả bổ sung.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Card: Products to Return -->
          <div class="content-card">
            <div class="card-header">
              <div class="card-title-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <h2>Sản phẩm Hoàn trả</h2>
              </div>
              <span class="item-count-badge">{{ detail.items?.length || 0 }} sản phẩm</span>
            </div>

            <div class="card-body p-0">
              <div class="products-list-wrapper">
                <div v-for="item in detail.items" :key="item.id" class="product-item-row">
                  <div class="product-img-wrapper">
                    <img
                      :src="imageUrl(item.order_item?.product_image || item.product?.thumbnail_url || item.order_item?.variant_image || item.variant?.image_url)"
                      :alt="item.order_item?.product_name || item.product?.name"
                      class="product-thumb"
                      @error="(e) => e.target.src = 'https://placehold.co/64x64?text=SP'"
                    />
                  </div>

                  <div class="product-info-col">
                    <h3 class="product-title" :title="item.order_item?.product_name || item.product?.name">
                      {{ item.order_item?.product_name || item.product?.name || 'Sản phẩm' }}
                    </h3>
                    <div class="product-meta-tags">
                      <span class="variant-tag">
                        Phân loại: <strong>{{ item.order_item?.variant_name || item.variant?.variant_name || 'Tiêu chuẩn' }}</strong>
                      </span>
                      <span class="qty-tag">Số lượng: <strong>x{{ item.requested_quantity }}</strong></span>
                    </div>

                    <!-- QC inspection result if available -->
                    <div v-if="item.qc_pass_quantity !== null || item.qc_fail_quantity !== null" class="qc-badge-row">
                      <span v-if="Number(item.qc_pass_quantity) > 0" class="qc-tag pass">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        QC Đạt: {{ item.qc_pass_quantity }}
                      </span>
                      <span v-if="Number(item.qc_fail_quantity) > 0" class="qc-tag fail">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        QC Lỗi: {{ item.qc_fail_quantity }}
                      </span>
                    </div>
                  </div>

                  <div class="product-price-col">
                    <span class="price-label">Tiền hoàn dự kiến:</span>
                    <strong class="price-value">{{ formatPrice(item.refundable_amount) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Card: Evidence Media Gallery -->
          <div v-if="detail.images?.length || detail.videos?.length" class="content-card">
            <div class="card-header">
              <div class="card-title-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <h2>Hình ảnh & Video Minh chứng</h2>
              </div>
              <span class="item-count-badge">{{ (detail.images?.length || 0) + (detail.videos?.length || 0) }} tệp</span>
            </div>

            <div class="card-body">
              <p class="evidence-guide">Bấm vào tệp để xem chế độ toàn màn hình.</p>
              <div class="evidence-grid">
                <!-- Images -->
                <div
                  v-for="image in detail.images"
                  :key="image"
                  class="evidence-card"
                  @click="openPreview(imageUrl(image))"
                >
                  <img
                    :src="imageUrl(image)"
                    alt="Minh chứng hoàn hàng"
                    class="evidence-media-img"
                    @error="(e) => e.target.src = 'https://placehold.co/180x180?text=L%E1%BB%97i+%E1%BA%A3nh'"
                  />
                  <div class="evidence-overlay">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                  </div>
                </div>

                <!-- Videos -->
                <div
                  v-for="video in detail.videos"
                  :key="video"
                  class="evidence-card evidence-card--video"
                  @click="openPreview(imageUrl(video))"
                >
                  <video :src="imageUrl(video)" preload="metadata" muted class="evidence-media-vid" />
                  <div class="play-btn-circle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                  </div>
                  <span class="video-tag">VIDEO</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Card: Return Shipping Logistics & Instructions -->
          <div class="content-card">
            <div class="card-header">
              <div class="card-title-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <h2>Phương thức & Hướng dẫn Gửi hàng hoàn</h2>
              </div>
            </div>

            <div class="card-body">
              <div class="shipping-method-box">
                <div class="shipping-method-title">
                  <strong>{{ getReturnShippingMethodLabel(detail.return_shipping_method) }}</strong>
                </div>

                <!-- Case 1: Ocean Express Pickup from Original Address -->
                <template v-if="detail.return_shipping_method === 'pickup_original_address'">
                  <p class="shipping-desc">
                    Bưu tá đối tác vận chuyển (Ocean Express) sẽ đến lấy hàng trực tiếp tại địa chỉ giao ban đầu của bạn:
                  </p>

                  <div class="pickup-address-card">
                    <div class="pickup-contact">
                      <strong>{{ detail.return_pickup_name || detail.order?.recipient_name }}</strong>
                      <span class="phone-chip">{{ detail.return_pickup_phone || detail.order?.recipient_phone }}</span>
                    </div>
                    <p class="pickup-address-text">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                      <span>{{ detail.return_pickup_address || detail.order?.shipping_address }}</span>
                    </p>
                  </div>

                  <div v-if="detail.return_tracking_code" class="tracking-code-banner">
                    <div class="tracking-left">
                      <span class="tracking-label">MÃ VẬN ĐƠN THU HỒI:</span>
                      <strong class="tracking-val">{{ detail.return_tracking_code }}</strong>
                      <span class="carrier-badge">{{ detail.return_carrier === 'dropoff_post_office' ? 'Bưu điện / Tự gửi' : 'Ocean Express' }}</span>
                    </div>
                    <div class="tracking-actions-group">
                      <button class="btn-track-sm" @click="openCustomerTrackingModal">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                        <span>Hành trình</span>
                      </button>
                      <button class="btn-copy-sm" @click="copyCode(detail.return_tracking_code)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Sao chép</span>
                      </button>
                    </div>
                  </div>
                </template>

                <!-- Case 2: Dropoff Post Office -->
                <template v-else>
                  <p class="shipping-desc">
                    Sau khi yêu cầu được duyệt, bạn vui lòng đóng gói sản phẩm và mang tới bưu cục gần nhất theo chỉ dẫn:
                  </p>
                  <div class="post-office-guide-box">
                    <h4>Quy cách đóng gói hàng hoàn:</h4>
                    <ul>
                      <li>Giữ nguyên tem mác, phụ kiện và hộp/bao bì ban đầu (nếu có).</li>
                      <li>Bọc chống sốc hoặc dán kín miệng hộp để tránh va đập khi vận chuyển.</li>
                      <li>Ghi rõ Mã yêu cầu <strong>{{ detail.return_code }}</strong> bên ngoài kiện hàng.</li>
                    </ul>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <!-- Card: Admin & Processing Notes (if any) -->
          <div v-if="detail.admin_note || detail.reject_reason || detail.inspection_note" class="content-card alert-card">
            <div class="card-header">
              <div class="card-title-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <h2>Thông báo từ Quản trị viên</h2>
              </div>
            </div>

            <div class="card-body">
              <div v-if="detail.reject_reason" class="admin-callout reject">
                <strong>Lý do từ chối:</strong>
                <p>{{ detail.reject_reason }}</p>
              </div>
              <div v-if="detail.admin_note" class="admin-callout note">
                <strong>Ghi chú từ CSKH:</strong>
                <p>{{ detail.admin_note }}</p>
              </div>
              <div v-if="detail.inspection_note" class="admin-callout qc">
                <strong>Kết quả thẩm định (QC):</strong>
                <p>{{ detail.inspection_note }}</p>
              </div>
            </div>
          </div>
        </main>

        <!-- RIGHT COLUMN: Financial & Quick Actions Sidebar -->
        <aside class="col-side">
          <!-- Card: Financial Settlement (Clean & Minimal) -->
          <div class="side-card settlement-card">
            <div class="settlement-head">
              <span class="settlement-sub-title">TỔNG TIỀN HOÀN DỰ KIẾN</span>
              <div class="settlement-amount">
                {{ formatPrice(detail.refund_amount || detail.items?.reduce((sum, i) => sum + Number(i.refundable_amount || 0), 0)) }}
              </div>
              <span class="refund-badge" :class="`refund--${detail.refund_status}`">
                {{ getReturnRefundStatusLabel(detail.refund_status) }}
              </span>
            </div>

            <div class="settlement-body">
              <div class="settlement-row">
                <span class="row-lbl">Phương thức</span>
                <strong class="row-val">{{ getRefundMethodLabel(detail.refund_method) }}</strong>
              </div>

              <!-- Extra info for wallet refund -->
              <div v-if="detail.refund_method === 'wallet'" class="wallet-promo-box">
                <div class="wallet-promo-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div class="wallet-promo-text">
                  <p>Tiền hoàn sẽ được cộng thẳng vào <strong>Ví Ocean Sport</strong>, có thể dùng mua sắm hoặc rút về tài khoản ngân hàng ngay.</p>
                </div>
              </div>

              <div class="divider-line"></div>

              <!-- Processing Timestamps -->
              <div class="timeline-rows">
                <div class="time-item">
                  <span class="time-lbl">Gửi yêu cầu:</span>
                  <span class="time-val">{{ formatDate(detail.requested_at || detail.created_at) }}</span>
                </div>
                <div v-if="detail.approved_at" class="time-item">
                  <span class="time-lbl">Shop duyệt:</span>
                  <span class="time-val">{{ formatDate(detail.approved_at) }}</span>
                </div>
                <div v-if="detail.received_at" class="time-item">
                  <span class="time-lbl">Kho nhận hàng:</span>
                  <span class="time-val">{{ formatDate(detail.received_at) }}</span>
                </div>
                <div v-if="detail.refunded_at" class="time-item">
                  <span class="time-lbl">Hoàn tất:</span>
                  <span class="time-val text-success">{{ formatDate(detail.refunded_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Card: Original Order Reference -->
          <div class="side-card">
            <div class="side-card-header">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              <h3>Đơn hàng liên quan</h3>
            </div>

            <div class="side-card-body">
              <div class="order-ref-box">
                <div class="order-ref-title">
                  <span>Mã đơn hàng:</span>
                  <strong>#{{ detail.order?.order_code || detail.order_id }}</strong>
                </div>
                <div class="order-ref-status">
                  <span class="order-status-pill">{{ getOrderStatusLabel(detail.order?.status) }}</span>
                </div>
              </div>

              <router-link
                :to="{ name: 'profile-order-detail', params: { id: detail.order?.order_id || detail.order_id } }"
                class="btn-view-order"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>Xem lại đơn hàng gốc</span>
              </router-link>

              <router-link
                v-if="detail.refund_method === 'wallet'"
                :to="{ name: 'profile-wallet' }"
                class="btn-view-wallet"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Kiểm tra số dư Ví điện tử</span>
              </router-link>
            </div>
          </div>

          <!-- Card: Customer Support Center -->
          <div class="side-card support-card">
            <div class="support-content">
              <div class="support-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div class="support-text">
                <h4>Cần thêm hỗ trợ?</h4>
                <p>Nếu bạn có thắc mắc về tiến trình hoàn tiền, hãy gửi yêu cầu hỗ trợ.</p>
                <router-link :to="{ name: 'profile-tickets' }" class="support-link">
                  Gửi yêu cầu hỗ trợ (Ticket) →
                </router-link>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>

    <!-- Media Fullscreen Lightbox Preview Modal -->
    <MediaPreviewModal
      :show="previewShow"
      :media-list="mediaList"
      :initial-index="previewIndex"
      @close="closePreview"
    />

    <!-- Real-time Ocean Express Tracking Modal for Customer -->
    <div v-if="showCustomerTrackingModal" class="modal-overlay" @click.self="closeCustomerTrackingModal">
      <div class="tracking-modal-box">
        <div class="modal-header">
          <div class="modal-title-wrap">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
            <h3>Hành trình Vận đơn Thu hồi</h3>
          </div>
          <button class="btn-close-modal" @click="closeCustomerTrackingModal">✕</button>
        </div>

        <div class="modal-body">
          <div v-if="customerTrackingLoading" class="tracking-loading-state">
            <div class="spinner-ocean"></div>
            <p>Đang kết nối hệ thống Ocean Express...</p>
          </div>

          <div v-else-if="customerTrackingInfo" class="tracking-content">
            <div class="tracking-meta-banner">
              <div class="tracking-code-badge">
                <span class="label">Mã vận đơn:</span>
                <strong>{{ customerTrackingInfo.tracking_code }}</strong>
              </div>
              <span class="carrier-pill">Ocean Express</span>
            </div>

            <!-- Route visualizer -->
            <div class="tracking-route-card">
              <div class="route-node">
                <span class="node-dot sender"></span>
                <div class="node-info">
                  <span class="node-type">Điểm lấy hàng</span>
                  <p class="node-addr">{{ customerTrackingInfo.sender_address || detail?.return_pickup_address || detail?.order?.shipping_address || 'Địa chỉ của bạn' }}</p>
                </div>
              </div>
              <div class="route-line"></div>
              <div class="route-node">
                <span class="node-dot receiver"></span>
                <div class="node-info">
                  <span class="node-type">Điểm nhận hàng (Kho shop)</span>
                  <p class="node-addr">{{ customerTrackingInfo.receiver_address || '300/6 Hà Huy Tập, Phường Tân An, Tỉnh Đắk Lắk' }}</p>
                </div>
              </div>
            </div>

            <!-- Timeline logs -->
            <div class="tracking-timeline-wrap">
              <h4 class="timeline-title">Lịch sử di chuyển</h4>
              <div v-if="customerTrackingInfo.logs && customerTrackingInfo.logs.length" class="timeline-list">
                <div v-for="(log, idx) in customerTrackingInfo.logs" :key="idx" class="timeline-entry" :class="{ 'is-latest': idx === 0 }">
                  <div class="entry-bullet"></div>
                  <div class="entry-body">
                    <span class="entry-time">{{ formatDate(log.timestamp || log.created_at) }}</span>
                    <strong class="entry-status">{{ log.status || log.action }}</strong>
                    <p v-if="log.note || log.description" class="entry-desc">{{ log.note || log.description }}</p>
                  </div>
                </div>
              </div>
              <div v-else class="empty-logs">
                <p>Kiện hàng mới được tạo vận đơn, đang chờ shipper Ocean Express tiếp nhận.</p>
              </div>
            </div>
          </div>

          <div v-else class="empty-logs">
            <p>Chưa có dữ liệu hành trình từ Ocean Express.</p>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeCustomerTrackingModal">Đóng</button>
          <button class="btn-refresh-tracking" :disabled="customerTrackingLoading" @click="openCustomerTrackingModal">
            Cập nhật lại
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-return-detail-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Top Nav Bar */
.top-nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.btn-back-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 7px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.btn-back-link:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

.header-meta-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.return-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.return-code-pill:hover {
  border-color: #cbd5e1;
  background: #f1f5f9;
}

.code-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.04em;
}

.code-value {
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.copied-indicator {
  font-size: 0.72rem;
  font-weight: 600;
  color: #10b981;
}

/* Status Badges - Clean & Refined */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid transparent;
  white-space: nowrap;
}

.pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-badge.status-info, .status-badge.info {
  background: #fdf2f6;
  color: #be185d;
  border-color: #fce7f3;
}

.status-badge.status-warning, .status-badge.warning {
  background: #fefce8;
  color: #854d0e;
  border-color: #fef08a;
}

.status-badge.status-success, .status-badge.success {
  background: #ecfdf5;
  color: #065f46;
  border-color: #a7f3d0;
}

.status-badge.status-danger, .status-badge.danger {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}

.status-badge.status-default, .status-badge.neutral {
  background: #f1f5f9;
  color: #475569;
  border-color: #e2e8f0;
}

/* Main Content Flow */
.main-content-flow {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Hero Status Card - Clean Minimalist Style */
.hero-status-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 1px 2px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.hero-header {
  padding: 20px 24px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.hero-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #e63b6f;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hero-text-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.hero-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
}

.hero-desc {
  margin: 0;
  font-size: 0.88rem;
  color: #475569;
  line-height: 1.5;
}

/* 5-Stage Stepper: Integrated Seamlessly in the Card */
.stepper-track {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  background: #fafbfc;
  border-top: 1px solid #f1f5f9;
  padding: 18px 16px 16px;
}

.stepper-step {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  position: relative;
  min-width: 0;
}

.step-head {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  position: relative;
  margin-bottom: 8px;
}

.step-bullet {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #94a3b8;
  border: 2px solid #e2e8f0;
  z-index: 2;
  position: relative;
  transition: all 0.2s ease;
}

.stepper-step.done .step-bullet {
  background: #10b981;
  border-color: #10b981;
  color: #ffffff;
}

.stepper-step.active .step-bullet {
  background: #e63b6f;
  border-color: #e63b6f;
  color: #ffffff;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.18);
}

.stepper-step.rejected .step-bullet, .stepper-step.failed .step-bullet {
  background: #ef4444;
  border-color: #ef4444;
  color: #ffffff;
}

.step-line {
  position: absolute;
  top: 50%;
  height: 2px;
  background: #e2e8f0;
  transform: translateY(-50%);
  z-index: 1;
}

.step-line--left {
  left: 0;
  right: 50%;
}

.step-line--right {
  left: 50%;
  right: 0;
}

.step-line.done {
  background: #10b981;
}

.step-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 0 4px;
}

.step-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.3;
}

.stepper-step.inactive .step-title {
  color: #94a3b8;
}

.step-sub {
  font-size: 0.72rem;
  color: #64748b;
  line-height: 1.25;
}

/* Two Column Layout */
.detail-two-col-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
}

@media (max-width: 992px) {
  .detail-two-col-grid {
    grid-template-columns: 1fr;
  }
}

.col-main, .col-side {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Standard Content Cards */
.content-card, .side-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 1px 2px rgba(0, 0, 0, 0.02);
  overflow: hidden;
}

.card-header, .side-card-header {
  padding: 14px 18px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title-box, .side-card-header {
  display: flex;
  align-items: center;
  gap: 9px;
  color: #0f172a;
}

.card-title-box h2, .side-card-header h3 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
}

.card-body, .side-card-body {
  padding: 18px;
}

.item-count-badge {
  font-size: 0.72rem;
  font-weight: 600;
  color: #475569;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  padding: 2px 8px;
  border-radius: 12px;
}

/* Reason section */
.reason-highlight-row {
  margin-bottom: 12px;
}

.reason-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #f8fafc;
  color: #334155;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.description-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.desc-label {
  font-size: 0.74rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.description-text-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.88rem;
  color: #334155;
  line-height: 1.5;
}

.description-text-box p {
  margin: 0;
}

/* Products List */
.products-list-wrapper {
  display: flex;
  flex-direction: column;
}

.product-item-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s ease;
}

.product-item-row:last-child {
  border-bottom: none;
}

.product-item-row:hover {
  background: #f8fafc;
}

.product-img-wrapper {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  flex-shrink: 0;
}

.product-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-info-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.product-title {
  margin: 0;
  font-size: 0.88rem;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-meta-tags {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 0.78rem;
  color: #64748b;
}

.variant-tag strong, .qty-tag strong {
  color: #1e293b;
}

.qc-badge-row {
  display: flex;
  gap: 6px;
  margin-top: 3px;
}

.qc-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
}

.qc-tag.pass { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.qc-tag.fail { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

.product-price-col {
  flex-shrink: 0;
  min-width: 130px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  text-align: right;
}

.price-label {
  font-size: 0.7rem;
  color: #64748b;
  white-space: nowrap;
}

.price-value {
  font-size: 0.98rem;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
}

/* Evidence Media */
.evidence-guide {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0 0 10px 0;
}

.evidence-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
  gap: 10px;
}

.evidence-card {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  background: #000000;
}

.evidence-media-img, .evidence-media-vid {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s ease;
}

.evidence-card:hover .evidence-media-img {
  transform: scale(1.05);
}

.evidence-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.evidence-card:hover .evidence-overlay {
  opacity: 1;
}

.play-btn-circle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding-left: 2px;
  border: 2px solid white;
}

.video-tag {
  position: absolute;
  bottom: 5px;
  left: 5px;
  background: rgba(0,0,0,0.7);
  color: white;
  font-size: 0.62rem;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 3px;
}

/* Shipping Logistics */
.shipping-method-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.shipping-method-title strong {
  font-size: 0.9rem;
  color: #0f172a;
}

.shipping-desc {
  margin: 0;
  font-size: 0.84rem;
  color: #475569;
  line-height: 1.45;
}

.pickup-address-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 11px 14px;
}

.pickup-contact {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.pickup-contact strong {
  font-size: 0.88rem;
  color: #0f172a;
}

.phone-chip {
  font-size: 0.75rem;
  color: #475569;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.pickup-address-text {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 0;
  font-size: 0.82rem;
  color: #475569;
  line-height: 1.35;
}

.tracking-code-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 9px 12px;
  border-radius: 8px;
}

.tracking-left {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.tracking-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
}

.tracking-val {
  font-family: ui-monospace, monospace;
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
}

.carrier-badge {
  font-size: 0.68rem;
  font-weight: 600;
  background: #334155;
  color: #ffffff;
  padding: 1px 6px;
  border-radius: 4px;
}

.btn-copy-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-copy-sm:hover {
  background: #f1f5f9;
}

.post-office-guide-box {
  background: #f8fafc;
  border-radius: 8px;
  padding: 11px 14px;
  border: 1px solid #e2e8f0;
}

.post-office-guide-box h4 {
  margin: 0 0 5px 0;
  font-size: 0.82rem;
  font-weight: 700;
  color: #0f172a;
}

.post-office-guide-box ul {
  margin: 0;
  padding-left: 16px;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.5;
}

/* Alert / Admin Notes Card */
.alert-card {
  border-color: #fecaca;
  background: #fffafa;
}

.alert-card .card-header {
  border-color: #fee2e2;
  background: #fffafa;
}

.admin-callout {
  padding: 10px 12px;
  border-radius: 6px;
  margin-bottom: 8px;
  font-size: 0.82rem;
  line-height: 1.45;
}

.admin-callout:last-child {
  margin-bottom: 0;
}

.admin-callout.reject {
  background: #ffffff;
  border-left: 3px solid #ef4444;
  color: #7f1d1d;
}

.admin-callout.note {
  background: #ffffff;
  border-left: 3px solid #e63b6f;
  color: #831843;
}

.admin-callout.qc {
  background: #ffffff;
  border-left: 3px solid #f59e0b;
  color: #78350f;
}

.admin-callout p {
  margin: 3px 0 0 0;
}

/* Settlement Sidebar Card - Refined & High-End */
.settlement-card {
  background: #ffffff;
}

.settlement-head {
  padding: 20px 18px 16px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 4px;
}

.settlement-sub-title {
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.05em;
}

.settlement-amount {
  font-size: 1.65rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
  line-height: 1.25;
}

/* Refund status badge */
.refund-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  border: 1px solid transparent;
  margin-top: 2px;
}

.refund--none, .refund--not_refunded, .refund--unrefunded, .refund--pending {
  background: #fefce8;
  color: #854d0e;
  border-color: #fef08a;
}

.refund--success, .refund--completed, .refund--refunded {
  background: #ecfdf5;
  color: #065f46;
  border-color: #a7f3d0;
}

.refund--failed {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fecaca;
}

.settlement-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.settlement-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.84rem;
}

.row-lbl {
  color: #64748b;
}

.row-val {
  color: #0f172a;
}

.wallet-promo-box {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #10b981;
  border-radius: 6px;
  padding: 8px 10px;
}

.wallet-promo-icon {
  margin-top: 1px;
}

.wallet-promo-text p {
  margin: 0;
  font-size: 0.76rem;
  color: #334155;
  line-height: 1.35;
}

.divider-line {
  height: 1px;
  background: #f1f5f9;
}

.timeline-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.time-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
}

.time-lbl {
  color: #64748b;
}

.time-val {
  color: #1e293b;
  font-weight: 500;
}

/* Original Order Card */
.side-card-body {
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.order-ref-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.order-ref-title {
  display: flex;
  flex-direction: column;
  font-size: 0.78rem;
}

.order-ref-title span {
  color: #64748b;
}

.order-ref-title strong {
  color: #0f172a;
  font-size: 0.86rem;
}

.order-status-pill {
  font-size: 0.7rem;
  font-weight: 600;
  color: #475569;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 2px 7px;
  border-radius: 4px;
}

.btn-view-order, .btn-view-wallet {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 8px 12px;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.btn-view-order {
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}

.btn-view-order:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.btn-view-wallet {
  background: #fff1f5;
  color: #e63b6f;
  border: 1px solid #fbcfe8;
}

.btn-view-wallet:hover {
  background: #ffe4ec;
}

/* Support Card */
.support-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.support-content {
  padding: 14px 18px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.support-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.support-text h4 {
  margin: 0 0 3px 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
}

.support-text p {
  margin: 0 0 6px 0;
  font-size: 0.76rem;
  color: #64748b;
  line-height: 1.35;
}

.support-link {
  font-size: 0.78rem;
  font-weight: 600;
  color: #e63b6f;
  text-decoration: none;
}

.support-link:hover {
  text-decoration: underline;
}

/* Skeletons */
.skeleton-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-banner {
  height: 150px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: skeletonPulse 1.5s infinite;
}

.skeleton-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
}

.skeleton-main-card, .skeleton-side-card {
  height: 360px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: skeletonPulse 1.5s infinite;
}

@keyframes skeletonPulse {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Tracking Button & Actions */
.tracking-actions-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-track-sm {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #0284c7;
  color: #ffffff;
  border: none;
  padding: 5px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-track-sm:hover {
  background: #0369a1;
}

/* Tracking Modal Styles */
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

.tracking-modal-box {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 540px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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

.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

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
</style>
