<script setup>
import { ref, nextTick, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Toast } from 'bootstrap';
import { orderService } from '@/services/orderService';
import { returnRequestService } from '@/services/returnRequestService';
import AppIcon from '@/icons/AppIcon.vue';
import {
  RETURN_REASON_OPTIONS,
  getOrderStatusDescription,
  getOrderStatusTone,
  getPaymentStatusLabel,
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
} from '@/utils/orderStatus';
import api from '@/axios';
import { getStorageUrl } from '@/utils/url';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('orderDetailToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};

const route = useRoute();
const router = useRouter();
const orderId = computed(() => route.params.id);

const order = ref(null);
const tracking = ref(null);
const trackingLoading = ref(false);
const loading = ref(true);
const actionLoading = ref(false);

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const getProductImage = (item) => {
    if (item.variant?.image_url) {
        return getStorageUrl(item.variant.image_url);
    }
    
    if (item.product?.images && item.product.images.length > 0) {
        const defaultImage = item.product.images.find(img => img.is_main) || item.product.images[0];
        return getStorageUrl(defaultImage.image_url);
    }
    
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') {
        return getStorageUrl(item.product.thumbnail_url);
    }
    
    return '/placeholder.png';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
};

const getStatusText = (status) => getOrderStatusDescription(status);
const getStatusClass = (status) => getOrderStatusTone(status);
const getStatusBadgeClass = (status) => {
  if (status === 'unpaid') return 'badge-warning';
  if (status === 'paid' || status === 'refunded') return 'badge-success';

  const tone = getOrderStatusTone(status);
  if (tone === 'status-success') return 'badge-success';
  if (tone === 'status-danger') return 'badge-danger';
  if (tone === 'status-warning') return 'badge-warning';
  if (tone === 'status-info') return 'badge-info';
  if (tone === 'status-primary') return 'badge-primary';
  return 'badge-secondary';
};

const getStatusIcon = (status) => {
  if (status === 'pending') return 'clipboard';
  if (status === 'shipping') return 'truck';
  if (status === 'cancelled') return 'x';
  if (status === 'completed' || status === 'delivered') return 'check';
  return 'shopping-cart';
};

const fetchOrderTracking = async () => {
  if (!orderId.value) return;
  trackingLoading.value = true;
  try {
    const res = await orderService.getOrderTracking(orderId.value);
    if (res.data.status === 'success') {
      tracking.value = res.data.data;
    }
  } catch (err) {
    console.warn('Không thể lấy tracking GHN, dùng lịch sử đơn hàng hiện có.', err);
    tracking.value = null;
  } finally {
    trackingLoading.value = false;
  }
};

const fetchOrderDetail = async () => {
  if (!orderId.value || orderId.value === 'null' || orderId.value === 'undefined') {
    router.replace({ name: 'profile-orders' });
    return;
  }
  loading.value = true;
  try {
    const res = await orderService.getProfileOrderDetail(orderId.value);
    if (res.data.status === 'success') {
      order.value = res.data.data;
      await fetchOrderTracking();
    }
  } catch (err) {
    console.error('Lỗi lấy chi tiết đơn hàng: ', err);
    showToast('Không thể lấy thông tin đơn hàng này.', 'danger');
    setTimeout(() => router.push({ name: 'profile-orders' }), 2000);
  } finally {
    loading.value = false;
  }
};

// Lý do hủy đơn phổ biến
const cancelReasons = [
  'Tôi muốn thay đổi sản phẩm (kích thước, màu sắc, số lượng)',
  'Tôi muốn thay đổi địa chỉ giao hàng',
  'Tôi tìm thấy giá rẻ hơn ở nơi khác',
  'Tôi không còn nhu cầu mua nữa',
  'Thời gian giao hàng quá lâu',
  'Đặt nhầm sản phẩm / đặt trùng đơn',
  'Muốn thay đổi phương thức thanh toán',
  'Lý do khác',
];

const returnReasons = RETURN_REASON_OPTIONS;
const showReturnModal = ref(false);
const submittingReturnRequest = ref(false);
const returnReason = ref('');
const returnDescription = ref('');
const returnImages = ref([]);
const returnValidationError = ref('');

// Cancel modal state
const showCancelModal = ref(false);
const selectedCancelReason = ref('');
const customCancelReason = ref('');
const cancelValidationError = ref('');

const openCancelModal = () => {
  selectedCancelReason.value = '';
  customCancelReason.value = '';
  cancelValidationError.value = '';
  showCancelModal.value = true;
};

const dismissCancelModal = () => {
  showCancelModal.value = false;
};

const openReturnModal = () => {
  returnReason.value = '';
  returnDescription.value = '';
  returnImages.value = [];
  returnValidationError.value = '';
  showReturnModal.value = true;
};

const dismissReturnModal = () => {
  showReturnModal.value = false;
};

const onReturnImagesChange = (event) => {
  returnImages.value = Array.from(event.target.files || []);
  returnValidationError.value = '';
};

const submitReturnRequest = async () => {
  if (!returnReason.value) {
    returnValidationError.value = 'Vui lòng chọn lý do hoàn hàng';
    return;
  }

  const payload = new FormData();
  payload.append('reason', returnReason.value);
  if (returnDescription.value.trim()) {
    payload.append('description', returnDescription.value.trim());
  }
  returnImages.value.forEach((file) => payload.append('images[]', file));

  submittingReturnRequest.value = true;
  try {
    const res = await returnRequestService.createReturnRequest(order.value.order_id, payload);
    if (res.data.status === 'success') {
      showToast('Đã gửi yêu cầu hoàn hàng thành công.', 'success');
      showReturnModal.value = false;
      await fetchOrderDetail();
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể gửi yêu cầu hoàn hàng.', 'danger');
  } finally {
    submittingReturnRequest.value = false;
  }
};

const confirmCancelOrder = async () => {
  if (!selectedCancelReason.value) {
    cancelValidationError.value = 'Vui lòng chọn một lý do hủy đơn';
    return;
  }
  if (selectedCancelReason.value === 'Lý do khác' && !customCancelReason.value.trim()) {
    cancelValidationError.value = 'Vui lòng nhập lý do cụ thể';
    return;
  }
  const reason = selectedCancelReason.value === 'Lý do khác' ? customCancelReason.value.trim() : selectedCancelReason.value;
  showCancelModal.value = false;

  actionLoading.value = true;
  try {
    const res = await orderService.cancelProfileOrder(order.value.order_id, reason);
    if (res.data.status === 'success') {
      showToast('Đơn hàng của bạn đã được hủy thành công.', 'success');
      await fetchOrderDetail();
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi hủy đơn hàng';
    showToast(msg, 'danger');
  } finally {
    actionLoading.value = false;
  }
};

const goBack = () => {
  router.push({ name: 'profile-orders' });
};

// ====== TICKET (KHIẾU NẠI) LOGIC ======
const showTicketModal = ref(false);
const selectedItemForTicket = ref(null);
const ticketReason = ref('Hàng lỗi, hỏng');
const ticketDesc = ref('');
const ticketImage = ref(null);
const ticketError = ref('');

const ticketReasons = [
  'Hàng lỗi, hỏng',
  'Giao sai sản phẩm / Phân loại',
  'Thiếu hàng',
  'Sản phẩm không giống mô tả',
  'Hàng giả, hàng nhái',
  'Lý do khác'
];

const openTicketModal = (item) => {
  selectedItemForTicket.value = item;
  ticketReason.value = 'Hàng lỗi, hỏng';
  ticketDesc.value = '';
  ticketImage.value = null;
  ticketError.value = '';
  showTicketModal.value = true;
};

const closeTicketModal = () => {
  showTicketModal.value = false;
  selectedItemForTicket.value = null;
};

const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      ticketError.value = 'Kích thước ảnh tối đa 2MB';
      ticketImage.value = null;
      return;
    }
    ticketImage.value = file;
    ticketError.value = '';
  }
};

const submitTicket = async () => {
  if (!ticketDesc.value.trim()) {
    ticketError.value = 'Vui lòng nhập mô tả chi tiết';
    return;
  }
  
  actionLoading.value = true;
  ticketError.value = '';
  
  try {
    const formData = new FormData();
    formData.append('order_id', order.value.order_id);
    formData.append('product_id', selectedItemForTicket.value.product_id);
    formData.append('reason', ticketReason.value);
    formData.append('description', ticketDesc.value);
    if (ticketImage.value) {
      formData.append('image', ticketImage.value);
    }
    
    const res = await api.post('/profile/tickets', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    if (res.data.status === 'success') {
      showToast('Đã gửi khiếu nại thành công! Chúng tôi sẽ xử lý sớm nhất.', 'success');
      closeTicketModal();
    }
  } catch (error) {
    console.error("Lỗi gửi khiếu nại:", error);
    ticketError.value = error.response?.data?.message || 'Có lỗi xảy ra khi gửi khiếu nại';
  } finally {
    actionLoading.value = false;
  }
};

onMounted(() => {
  fetchOrderDetail();
});

watch(orderId, (newId) => {
  if (newId) {
    fetchOrderDetail();
  }
});
</script>

<template>
  <div class="profile-order-detail-page animate-in">
    <div class="page-header">
      <div class="header-left">
        <button class="btn-back" @click="goBack">
          <span>&larr;</span> Quay lại
        </button>
        <h2 class="page-title">Chi tiết đơn hàng</h2>
      </div>
      <div v-if="order" class="header-right" style="display: flex; align-items: center; gap: 8px;">
        <span class="order-code">#{{ order.order_code }}</span>
        <span v-if="order.order_code && order.order_code.startsWith('FS-')" class="badge bg-warning text-dark" style="font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; font-weight: 700;">⚡ Flash Sale</span>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải chi tiết đơn hàng...</p>
    </div>

    <div v-else-if="order" class="order-content">
      <!-- Status & Actions -->
      <div class="status-section">
        <div class="status-info">
          <div class="status-badge" :class="getStatusClass(order.fulfillment_status)">
            <span class="status-icon"><AppIcon :name="getStatusIcon(order.fulfillment_status)" /></span>
            {{ getStatusText(order.fulfillment_status) }}
          </div>
          <span class="order-date">Đặt lúc: {{ formatDate(order.created_at) }}</span>
        </div>
        <div class="status-actions">
          <button 
            v-if="order.fulfillment_status === 'pending'" 
            class="btn-action btn-cancel-order"
            @click="openCancelModal"
            :disabled="actionLoading"
          >
            <span v-if="actionLoading" class="spinner-small"></span>
            <span v-else>⊗ Yêu cầu hủy đơn</span>
          </button>
          <button
            v-if="order.can_request_return"
            class="btn-action btn-return-order"
            @click="openReturnModal"
            :disabled="submittingReturnRequest"
          >
            <span v-if="submittingReturnRequest" class="spinner-small"></span>
            <span v-else>↺ Yêu cầu hoàn hàng</span>
          </button>
        </div>
      </div>

      <div v-if="order.latest_return_request" class="return-request-banner">
        <div class="return-request-banner__meta">
          <span class="return-request-label">Yêu cầu hoàn hàng gần nhất</span>
          <span class="status-badge" :class="getReturnRequestStatusTone(order.latest_return_request.status)">
            {{ getReturnRequestStatusLabel(order.latest_return_request.status) }}
          </span>
        </div>
        <p>{{ order.latest_return_request.reason }}</p>
        <router-link
          :to="{ name: 'profile-return-request-detail', params: { id: order.latest_return_request.id } }"
          class="return-request-link"
        >
          Xem chi tiết yêu cầu
        </router-link>
      </div>

      <div v-if="tracking?.ghn_order_code || trackingLoading" class="ghn-tracking-card mt-4">
        <div class="card-header">
          <h3>Thông tin vận chuyển GHN</h3>
        </div>
        <div class="card-body">
          <p v-if="trackingLoading" class="tracking-muted">Đang tải thông tin vận chuyển...</p>
          <template v-else>
            <div class="ghn-info-row">
              <span>Mã vận đơn</span>
              <strong>{{ tracking.ghn_order_code }}</strong>
            </div>
            <div class="ghn-info-row" v-if="tracking.receiver_phone">
              <span>SĐT nhận hàng</span>
              <strong>{{ tracking.receiver_phone }}</strong>
            </div>
            <a v-if="tracking.ghn_tracking_url" class="ghn-tracking-link" :href="tracking.ghn_tracking_url" target="_blank" rel="noopener">
              Xem trực tiếp trên GHN
            </a>
          </template>
        </div>
      </div>

      <div class="detail-grid">
        <!-- Address Info -->
        <div class="address-card">
          <div class="card-header">
            <h3>Địa chỉ nhận hàng</h3>
          </div>
          <div class="card-body">
            <p class="recipient-name">{{ order.recipient_name }}</p>
            <p class="recipient-phone">{{ order.recipient_phone }}</p>
            <p class="recipient-address">{{ order.shipping_address }}</p>
            <div v-if="order.note" class="order-note">
              <strong>Ghi chú:</strong> {{ order.note }}
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="summary-card">
          <div class="card-header">
            <h3>Tổng quan thanh toán</h3>
            <span class="payment-method-badge">{{ order.payment_method?.toUpperCase() }}</span>
          </div>
          <div class="card-body">
             <div class="summary-row">
               <span class="fw-bold">Tạm tính</span>
               <span>{{ formatPrice(order.subtotal) }}</span>
             </div>
             <div class="summary-row">
               <span class="fw-bold">Phí vận chuyển</span>
               <span>{{ formatPrice(order.shipping_fee) }}</span>
             </div>
             <div class="summary-row discount" v-if="order.discount_amount > 0">
               <span>Giảm giá</span>
               <span>-{{ formatPrice(order.discount_amount) }}</span>
             </div>
             <div class="summary-row total">
               <span>Tổng cộng</span>
               <span class="total-price">{{ formatPrice(order.grand_total) }}</span>
             </div>
             
             <div class="payment-status mt-3">
               <strong>Trạng thái thanh toán: </strong>
               <span :class="['pay-badge', order.payment_status]">{{ getPaymentStatusLabel(order.payment_status) }}</span>
             </div>
          </div>
        </div>
      </div>

      <!-- Items List -->
      <div class="items-card mt-4">
        <div class="card-header">
          <h3>Sản phẩm đã mua</h3>
        </div>
        <div class="card-body p-0">
          <div class="item-list">
            <div v-for="item in order.items" :key="item.order_item_id" class="order-item">
              <div class="item-image">
                <img :src="getProductImage(item)" :alt="item.product_name" @error="$event.target.src='/placeholder.png'">
              </div>
              <div class="item-info">
                <div class="item-name">{{ item.product_name }}</div>
                <div class="item-variant" v-if="item.variant_name">Phân loại: {{ item.variant_name }}</div>
                <div class="item-qty">x{{ item.quantity }}</div>
                
                <button 
                  v-if="['delivered', 'completed'].includes(order.fulfillment_status)"
                  class="btn-ticket mt-2"
                  @click="openTicketModal(item)"
                >
                  ⚠ Khiếu nại
                </button>
              </div>
              <div class="item-price">
                <div class="line-total">{{ formatPrice(item.line_total) }}</div>
                <div class="unit-price" v-if="item.quantity > 1">{{ formatPrice(item.unit_price) }}/sp</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Order History -->
      <div class="history-card mt-4" v-if="(tracking?.timeline && tracking.timeline.length > 0) || (order.status_history && order.status_history.length > 0)">
        <div class="card-header">
          <h3>Lịch sử đơn hàng</h3>
        </div>
        <div class="card-body">
          <OrderStatusTimeline
            :histories="tracking?.timeline?.length ? tracking.timeline : order.status_history"
            :show-ghn-meta="Boolean(tracking?.timeline?.length)"
            :get-status-label="getStatusText"
            :get-status-badge-class="getStatusBadgeClass"
            :format-date="formatDate"
          />
        </div>
      </div>
      
    </div>

    <!-- Cancel Reason Modal -->
    <Transition name="modal">
      <div v-if="showCancelModal" class="cancel-modal-overlay" @click.self="dismissCancelModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>Hủy đơn hàng</h5>
            <button class="cancel-modal-close" @click="dismissCancelModal">×</button>
          </div>
          <div class="cancel-modal-body">
            <p class="cancel-modal-desc">Vui lòng cho chúng tôi biết lý do bạn muốn hủy đơn hàng:</p>
            <div class="cancel-reason-list">
              <label v-for="r in cancelReasons" :key="r" class="cancel-reason-item" :class="{ selected: selectedCancelReason === r }">
                <input type="radio" v-model="selectedCancelReason" :value="r" @change="cancelValidationError = ''" />
                <span>{{ r }}</span>
              </label>
            </div>
            <textarea v-if="selectedCancelReason === 'Lý do khác'" v-model="customCancelReason" placeholder="Nhập lý do cụ thể của bạn..." class="cancel-custom-input" @input="cancelValidationError = ''"></textarea>
            <p v-if="cancelValidationError" class="cancel-validation-error">{{ cancelValidationError }}</p>
          </div>
          <div class="cancel-modal-footer">
            <button class="btn-cancel-dismiss" @click="dismissCancelModal">Quay lại</button>
            <button class="btn-cancel-confirm" @click="confirmCancelOrder">Xác nhận hủy đơn</button>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="modal">
      <div v-if="showReturnModal" class="cancel-modal-overlay" @click.self="dismissReturnModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>Yêu cầu hoàn hàng</h5>
            <button class="cancel-modal-close" @click="dismissReturnModal">×</button>
          </div>
          <div class="cancel-modal-body">
            <p class="cancel-modal-desc">Chọn lý do và cung cấp thêm thông tin nếu cần để admin xử lý nhanh hơn.</p>
            <div class="cancel-reason-list">
              <label v-for="reason in returnReasons" :key="reason" class="cancel-reason-item" :class="{ selected: returnReason === reason }">
                <input type="radio" v-model="returnReason" :value="reason" @change="returnValidationError = ''" />
                <span>{{ reason }}</span>
              </label>
            </div>
            <textarea
              v-model="returnDescription"
              placeholder="Mô tả thêm tình trạng sản phẩm, lý do hoàn hàng hoặc thông tin cần hỗ trợ..."
              class="cancel-custom-input"
              @input="returnValidationError = ''"
            ></textarea>
            <input class="return-file-input" type="file" accept="image/*" multiple @change="onReturnImagesChange" />
            <p v-if="returnImages.length" class="return-file-count">Đã chọn {{ returnImages.length }} ảnh minh chứng</p>
            <p v-if="returnValidationError" class="cancel-validation-error">{{ returnValidationError }}</p>
          </div>
          <div class="cancel-modal-footer">
            <button class="btn-cancel-dismiss" @click="dismissReturnModal">Quay lại</button>
            <button class="btn-cancel-confirm return-confirm-btn" :disabled="submittingReturnRequest" @click="submitReturnRequest">
              {{ submittingReturnRequest ? 'Đang gửi...' : 'Gửi yêu cầu hoàn hàng' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
    <!-- Ticket Modal -->
    <Transition name="modal">
      <div v-if="showTicketModal" class="cancel-modal-overlay" @click.self="closeTicketModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>Gửi Khiếu Nại Sản Phẩm</h5>
            <button class="cancel-modal-close" @click="closeTicketModal">×</button>
          </div>
          <div class="cancel-modal-body">
            <p class="cancel-modal-desc">
              Sản phẩm: <strong>{{ selectedItemForTicket?.product_name }}</strong>
            </p>
            
            <div class="ticket-form-group">
              <label>Lý do khiếu nại:</label>
              <select v-model="ticketReason" class="ticket-select">
                <option v-for="r in ticketReasons" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>
            
            <div class="ticket-form-group">
              <label>Mô tả chi tiết vấn đề:</label>
              <textarea 
                v-model="ticketDesc" 
                placeholder="Vui lòng mô tả rõ tình trạng sản phẩm..." 
                class="cancel-custom-input"
                rows="3"
                @input="ticketError = ''"
              ></textarea>
            </div>
            
            <div class="ticket-form-group">
              <label>Hình ảnh minh chứng (Tùy chọn, tối đa 2MB):</label>
              <input type="file" accept="image/*" @change="handleImageUpload" class="ticket-file-input">
            </div>

            <p v-if="ticketError" class="cancel-validation-error">{{ ticketError }}</p>
          </div>
          <div class="cancel-modal-footer">
            <button class="btn-cancel-dismiss" @click="closeTicketModal">Hủy</button>
            <button class="btn-cancel-confirm ticket-submit-btn" @click="submitTicket" :disabled="actionLoading">
              <span v-if="actionLoading" class="spinner-small"></span>
              <span v-else>Gửi Khiếu Nại</span>
            </button>
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
  background: #e0f2fe;
  padding: 6px 18px;
  border-radius: 20px;
}

/* Loading & Empty */
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
.spinner-small {
  display: inline-block; width: 14px; height: 14px;
  border: 2px solid rgba(220, 38, 38, 0.3);
  border-radius: 50%; border-top-color: #dc2626;
  animation: spin 1s ease infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 20px; }
.p-0 { padding: 0 !important; }

/* Status Section */
.status-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  border: 1px solid #e2e8f0;
}

.status-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 30px;
  font-size: 0.95rem;
  font-weight: 700;
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
}
.status-badge.status-info { color: #475569; background: #f8fafc; border-color: #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.order-date {
  color: #64748b;
  font-size: 0.9rem;
}

.btn-action {
  background: var(--card-bg);
  border: 1.5px solid;
  border-radius: 20px;
  padding: 8px 16px;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-cancel-order {
  border-color: #fca5a5;
  color: #ef4444;
}
.btn-cancel-order:hover:not(:disabled) {
  background: #fef2f2;
}
.btn-cancel-order:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-return-order {
  border-color: #fdba74;
  color: #b45309;
}
.btn-return-order:hover:not(:disabled) {
  background: #fff7ed;
}

.return-request-banner {
  margin-bottom: 24px;
  padding: 16px 20px;
  border-radius: 12px;
  background: #fffaf0;
  border: 1px solid #fed7aa;
}

.return-request-banner__meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.return-request-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: #b45309;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.return-request-banner p {
  margin: 0 0 8px;
  color: #7c2d12;
}

.return-request-link {
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
}

/* Grid Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 768px) {
  .detail-grid { grid-template-columns: 1fr; }
  .status-section { flex-direction: column; align-items: flex-start; gap: 12px; }
  .status-actions { width: 100%; display: flex; justify-content: flex-end; }
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
.address-card, .summary-card, .items-card, .history-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

/* Address Card */
.recipient-name { font-weight: 700; font-size: 1.05rem; color: var(--text-main); margin: 0 0 4px 0; }
.recipient-phone { color: #334155; margin: 0 0 12px 0; font-weight: 600; }
.recipient-address { color: #475569; line-height: 1.5; margin: 0; }
.order-note {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed #cbd5e1;
  color: #16a34a;
  font-size: 0.9rem;
}

/* Summary Card */
.payment-method-badge {
  background: #e2e8f0;
  color: #475569;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  color: #475569;
}
.summary-row.discount { color: #16a34a; }
.summary-row.total {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
  font-weight: 800;
  color: var(--text-main);
  font-size: 1.1rem;
}
.total-price { color: var(--primary); font-size: 1.25rem; }
.pay-badge {
  padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;
}
.pay-badge.unpaid { background: #fee2e2; color: #dc2626; }
.pay-badge.paid { background: #dcfce3; color: #16a34a; }
.pay-badge.failed,
.pay-badge.refund_failed { background: #fee2e2; color: #dc2626; }
.pay-badge.refund_pending,
.pay-badge.partially_refunded { background: #fef3c7; color: #b45309; }
.pay-badge.refunded { background: #e0f2fe; color: #0f766e; }

/* Items List */
.item-list {
  display: flex;
  flex-direction: column;
}
.order-item {
  display: flex;
  gap: 16px;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}
.order-item:last-child { border-bottom: none; }
.item-image {
  width: 70px; height: 70px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  flex-shrink: 0;
}
.item-image img { width: 100%; height: 100%; object-fit: cover; }
.item-info { flex: 1; }
.item-name { font-weight: 600; color: var(--text-main); margin-bottom: 4px; line-height: 1.4; }
.item-variant { font-size: 0.85rem; color: #64748b; margin-bottom: 4px; }
.item-qty { font-size: 0.9rem; font-weight: 700; color: var(--primary); }
.item-price { text-align: right; min-width: 100px; }
.line-total { font-weight: 700; color: var(--text-main); margin-bottom: 4px; }
.unit-price { font-size: 0.8rem; color: #94a3b8; }

/* Timeline */
.timeline {
  display: flex;
  flex-direction: column;
}
.timeline-item {
  display: flex;
  gap: 16px;
  position: relative;
  padding-bottom: 24px;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 12px;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}
.timeline-marker {
  width: 12px; height: 12px;
  border-radius: 50%;
  background: #cbd5e1;
  position: relative;
  z-index: 1;
  margin-top: 4px;
  flex-shrink: 0;
}
.timeline-marker.latest {
  background: var(--primary);
  box-shadow: 0 0 0 4px #e0f2fe;
}
.timeline-content {
  flex: 1;
}
.timeline-time {
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 4px;
}
.timeline-note {
  font-weight: 500;
  color: #334155;
}

.animate-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Cancel Modal */
.cancel-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1050; }
.cancel-modal-box { background: var(--card-bg); border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
.cancel-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; }
.cancel-modal-header h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--text-main); }
.cancel-modal-close { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 1.5rem; line-height: 1; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; }
.cancel-modal-close:hover { background: #f1f5f9; color: #dc2626; }
.cancel-modal-body { padding: 20px 24px; }
.cancel-modal-desc { color: #64748b; font-size: 0.88rem; margin: 0 0 14px; }
.cancel-reason-list { display: flex; flex-direction: column; gap: 6px; max-height: 260px; overflow-y: auto; }
.cancel-reason-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; background: var(--card-bg); transition: all 0.15s; font-size: 0.88rem; color: #334155; }
.cancel-reason-item:hover { border-color: var(--primary); background: #f0f9ff; }
.cancel-reason-item.selected { border-color: var(--primary); background: #f0f9ff; }
.cancel-reason-item input[type="radio"] { accent-color: var(--primary); width: 16px; height: 16px; flex-shrink: 0; }
.cancel-custom-input { width: 100%; margin-top: 12px; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; min-height: 80px; resize: vertical; outline: none; font-family: inherit; box-sizing: border-box; }
.cancel-custom-input:focus { border-color: var(--primary); }
.return-file-input {
  width: 100%;
  margin-top: 12px;
  font: inherit;
}
.return-file-count {
  margin: 8px 0 0;
  font-size: 0.82rem;
  color: #475569;
}
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #e2e8f0; }
.btn-cancel-dismiss { padding: 8px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: var(--card-bg); color: #64748b; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-dismiss:hover { background: #f1f5f9; }
.btn-cancel-confirm { padding: 8px 20px; border-radius: 8px; border: none; background: #dc2626; color: white; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-confirm:hover { background: #b91c1c; }
.return-confirm-btn {
  background: var(--primary);
}
.return-confirm-btn:hover {
  background: #c53061;
}
.ticket-submit-btn { background: #1d4ed8; }
.ticket-submit-btn:hover { background: #1e40af; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .cancel-modal-box, .modal-leave-to .cancel-modal-box { transform: scale(0.95) translateY(10px); }

/* Ticket Styles */
.btn-ticket {
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #dc2626;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.btn-ticket:hover {
  background: #fef2f2;
  border-color: #fca5a5;
}
.ticket-form-group {
  margin-bottom: 16px;
}
.ticket-form-group label {
  display: block;
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
}
.ticket-select {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  font-family: inherit;
  color: var(--text-main);
}
.ticket-select:focus { border-color: #1d4ed8; }
.ticket-file-input {
  width: 100%;
  font-size: 0.85rem;
  padding: 8px 0;
  color: #64748b;
}

.ghn-tracking-card {
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}
.ghn-info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px dashed #e2e8f0;
}
.ghn-info-row:last-of-type { border-bottom: none; }
.ghn-info-row span,
.tracking-muted {
  color: #64748b;
  font-size: 0.9rem;
}
.ghn-info-row strong { color: var(--text-main); }
.ghn-tracking-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 14px;
  padding: 10px 16px;
  border-radius: 999px;
  background: #eff6ff;
  color: #2563eb;
  font-weight: 800;
  text-decoration: none;
}
.ghn-tracking-link:hover { background: #dbeafe; }
</style>
