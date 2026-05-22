<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Toast } from 'bootstrap';
import { orderService } from '@/services/orderService';

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
const orderId = route.params.id;

const order = ref(null);
const loading = ref(true);
const actionLoading = ref(false);

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const getProductImage = (item) => {
    if (item.variant?.image_url) {
        return item.variant.image_url.startsWith('http') ? item.variant.image_url : `http://localhost:8383/storage/${item.variant.image_url}`;
    }
    
    if (item.product?.images && item.product.images.length > 0) {
        const defaultImage = item.product.images.find(img => img.is_main) || item.product.images[0];
        return defaultImage.image_url.startsWith('http') ? defaultImage.image_url : `http://localhost:8383/storage/${defaultImage.image_url}`;
    }
    
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') {
        return item.product.thumbnail_url.startsWith('http') ? item.product.thumbnail_url : `http://localhost:8383/storage/${item.product.thumbnail_url}`;
    }
    
    return '/placeholder.png';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
};

const getStatusText = (status) => {
  switch (status) {
    case 'pending': return 'Đơn hàng đang chờ xác nhận';
    case 'confirmed': return 'Đơn hàng đã được xác nhận';
    case 'packing': return 'Đang đóng gói sản phẩm';
    case 'shipping': return 'Shipper đang giao hàng đến bạn';
    case 'delivered': return 'Đã giao hàng thành công';
    case 'completed': return 'Đơn hàng đã hoàn thành';
    case 'cancelled': return 'Đơn hàng đã bị hủy';
    case 'returned': return 'Đơn hàng đã hoàn trả';
    default: return 'Đang xử lý';
  }
};

const getStatusClass = (status) => {
  if (['pending', 'confirmed', 'packing'].includes(status)) return 'status-info';
  if (['shipping'].includes(status)) return 'status-warning';
  if (['delivered', 'completed'].includes(status)) return 'status-success';
  if (['cancelled', 'returned'].includes(status)) return 'status-danger';
  return 'status-default';
};

const getStatusIcon = (status) => {
  if (status === 'pending') return '📋';
  if (status === 'shipping') return '🚚';
  if (status === 'cancelled') return '❌';
  if (status === 'completed' || status === 'delivered') return '✅';
  return '📦';
};

const fetchOrderDetail = async () => {
  loading.value = true;
  try {
    const res = await orderService.getProfileOrderDetail(orderId);
    if (res.data.status === 'success') {
      order.value = res.data.data;
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

onMounted(() => {
  fetchOrderDetail();
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
      <div v-if="order" class="header-right">
        <span class="order-code">#{{ order.order_code }}</span>
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
            <span class="status-icon" v-html="getStatusIcon(order.fulfillment_status)"></span>
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
               <span>Tạm tính</span>
               <span>{{ formatPrice(order.subtotal) }}</span>
             </div>
             <div class="summary-row">
               <span>Phí vận chuyển</span>
               <span>{{ formatPrice(order.shipping_fee) }}</span>
             </div>
             <div class="summary-row discount" v-if="order.discount_amount > 0">
               <span>Mã giảm giá</span>
               <span>-{{ formatPrice(order.discount_amount) }}</span>
             </div>
             <div class="summary-row total">
               <span>Tổng cộng</span>
               <span class="total-price">{{ formatPrice(order.grand_total) }}</span>
             </div>
             
             <div class="payment-status mt-3">
               <strong>Trạng thái thanh toán: </strong>
               <span :class="['pay-badge', order.payment_status]">{{ order.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</span>
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
      <div class="history-card mt-4" v-if="order.status_history && order.status_history.length > 0">
        <div class="card-header">
          <h3>Lịch sử đơn hàng</h3>
        </div>
        <div class="card-body">
          <div class="timeline">
            <div v-for="(history, index) in order.status_history" :key="history.id" class="timeline-item">
              <div class="timeline-marker" :class="{ 'latest': index === 0 }"></div>
              <div class="timeline-content">
                <div class="timeline-time">{{ formatDate(history.created_at) }}</div>
                <div class="timeline-note">{{ history.note }}</div>
              </div>
            </div>
          </div>
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
  font-size: 1.1rem;
  background: #e0f2fe;
  padding: 6px 12px;
  border-radius: 8px;
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
  border-top-color: #E63B6F;
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
  background: white;
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
  background: white;
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
  color: #1e293b;
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
.recipient-name { font-weight: 700; font-size: 1.05rem; color: #0f172a; margin: 0 0 4px 0; }
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
  color: #0f172a;
  font-size: 1.1rem;
}
.total-price { color: #E63B6F; font-size: 1.25rem; }
.pay-badge {
  padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;
}
.pay-badge.unpaid { background: #fee2e2; color: #dc2626; }
.pay-badge.paid { background: #dcfce3; color: #16a34a; }

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
.item-name { font-weight: 600; color: #1e293b; margin-bottom: 4px; line-height: 1.4; }
.item-variant { font-size: 0.85rem; color: #64748b; margin-bottom: 4px; }
.item-qty { font-size: 0.9rem; font-weight: 700; color: #E63B6F; }
.item-price { text-align: right; min-width: 100px; }
.line-total { font-weight: 700; color: #0f172a; margin-bottom: 4px; }
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
  background: #E63B6F;
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
.cancel-modal-box { background: white; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
.cancel-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; }
.cancel-modal-header h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
.cancel-modal-close { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 1.5rem; line-height: 1; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; }
.cancel-modal-close:hover { background: #f1f5f9; color: #dc2626; }
.cancel-modal-body { padding: 20px 24px; }
.cancel-modal-desc { color: #64748b; font-size: 0.88rem; margin: 0 0 14px; }
.cancel-reason-list { display: flex; flex-direction: column; gap: 6px; max-height: 260px; overflow-y: auto; }
.cancel-reason-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; background: white; transition: all 0.15s; font-size: 0.88rem; color: #334155; }
.cancel-reason-item:hover { border-color: #E63B6F; background: #f0f9ff; }
.cancel-reason-item.selected { border-color: #E63B6F; background: #f0f9ff; }
.cancel-reason-item input[type="radio"] { accent-color: #E63B6F; width: 16px; height: 16px; flex-shrink: 0; }
.cancel-custom-input { width: 100%; margin-top: 12px; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; min-height: 80px; resize: vertical; outline: none; font-family: inherit; box-sizing: border-box; }
.cancel-custom-input:focus { border-color: #E63B6F; }
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #e2e8f0; }
.btn-cancel-dismiss { padding: 8px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-dismiss:hover { background: #f1f5f9; }
.btn-cancel-confirm { padding: 8px 20px; border-radius: 8px; border: none; background: #dc2626; color: white; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-confirm:hover { background: #b91c1c; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .cancel-modal-box, .modal-leave-to .cancel-modal-box { transform: scale(0.95) translateY(10px); }
</style>
