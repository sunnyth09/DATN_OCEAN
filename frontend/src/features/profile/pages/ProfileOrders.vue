<script setup>
import { useCartStore } from '@/stores/cart';
const cartStore = useCartStore();
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { orderService } from '@/services/orderService';
import {
  getOrderStatusDescription,
  getOrderStatusSummaryLabel,
  getOrderStatusTone,
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
} from '@/utils/orderStatus';

const orders = ref([]);
const loading = ref(true);
const actionLoading = ref(null);
const currentPage = ref(1);
const pagination = ref(null);
const currentFilter = ref('all');
const router = useRouter();

import FeedbackModal from '@/components/FeedbackModal.vue';
const showFeedbackModal = ref(false);
const selectedOrderForFeedback = ref(null);

const openFeedback = (order) => {
    selectedOrderForFeedback.value = order;
    showFeedbackModal.value = true;
};

const onFeedbackSubmitted = () => {
    // Tải lại danh sách đơn hàng để cập nhật trạng thái nếu cần
    fetchOrders(currentPage.value);
};

const filterTabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Chờ xác nhận' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' },
];

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

const getStatusText = (status) => getOrderStatusDescription(status);
const getSummaryStatusText = (status) => getOrderStatusSummaryLabel(status);
const getStatusClass = (status) => getOrderStatusTone(status);

const statusIconSvg = {
  pending: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  shipping: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
  cancelled: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  completed: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  delivered: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
};
const defaultIconSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
const getStatusIcon = (status) => statusIconSvg[status] || defaultIconSvg;

const fetchOrders = async (page = 1) => {
  loading.value = true;
  try {
    const res = await orderService.listProfileOrders({
      page,
      status: currentFilter.value,
    });
    if (res.data.status === 'success') {
      orders.value = res.data.data.data;
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total
      };
      currentPage.value = page;
    }
  } catch (err) {
    console.error('Lỗi lấy danh sách đơn hàng: ', err);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchOrders(page);
  }
};

const setFilter = (status) => {
  if (currentFilter.value !== status) {
    currentFilter.value = status;
    fetchOrders(1);
  }
};

import { Toast } from 'bootstrap';
import { nextTick } from 'vue';

const toastData = ref({ message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toastData.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('ordersToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
  });
};

// Lý do hủy đơn phổ biến (chuẩn ecommerce)
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
const cancellingOrderId = ref(null);
const selectedCancelReason = ref('');
const customCancelReason = ref('');
const cancelValidationError = ref('');

const openCancelModal = (orderId) => {
  cancellingOrderId.value = orderId;
  selectedCancelReason.value = '';
  customCancelReason.value = '';
  cancelValidationError.value = '';
  showCancelModal.value = true;
};

const dismissCancelModal = () => {
  showCancelModal.value = false;
  cancellingOrderId.value = null;
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

  actionLoading.value = cancellingOrderId.value;
  try {
    const res = await orderService.cancelProfileOrder(cancellingOrderId.value, reason);
    if (res.data.status === 'success') {
      showToast('Đơn hàng của bạn đã được hủy thành công.', 'success');
      await fetchOrders(currentPage.value);
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi hủy đơn hàng';
    showToast(msg, 'danger');
  } finally {
    actionLoading.value = null;
    cancellingOrderId.value = null;
  }
};

const buyAgain = async (orderId) => {
  actionLoading.value = orderId;
  try {
    const res = await orderService.buyAgain(orderId);
    if (res.data.status === 'success') {
      if (res.data.errors && res.data.errors.length > 0) {
        showToast(res.data.message + " Lưu ý: " + res.data.errors.join('. '), 'warning');
      } else {
        showToast('Thêm vào giỏ hàng thành công!', 'success');
      }
      cartStore.fetchCount()
      router.push('/cart');
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi thêm vào giỏ hàng';
    showToast(msg, 'danger');
  } finally {
    actionLoading.value = null;
  }
};

onMounted(() => {
  fetchOrders();
});
</script>

<template>
  <div class="profile-orders-page animate-in">
    <div class="page-header">
      <h2 class="page-title">Tất cả đơn hàng</h2>
    </div>

    <!-- Thanh lọc trạng thái đơn hàng -->
    <div class="order-status-tabs">
      <button 
        v-for="tab in filterTabs" 
        :key="tab.value" 
        class="status-tab" 
        :class="{ active: currentFilter === tab.value }"
        @click="setFilter(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="profile-orders-skeleton">
      <div class="skeleton-pulse" style="height:150px; border-radius:12px; margin-bottom: 20px;" v-for="i in 3" :key="i"></div>
    </div>

    <div v-else-if="orders.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
      <h3>Chưa có đơn hàng nào</h3>
      <p>Bạn chưa đặt bất kỳ đơn hàng nào. Hãy mua sắm ngay nhé!</p>
      <router-link to="/product" class="btn-primary mt-4 rounded-5">Tiếp tục mua sắm</router-link>
    </div>

    <div v-else class="orders-list">
      <div v-for="order in orders" :key="order.order_id" class="order-card">
        <div class="order-header">
          <div class="order-header-left">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="order-code">#{{ order.order_code }}</span>
                <span v-if="order.order_code && order.order_code.startsWith('FS-')" class="badge bg-warning text-dark" style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;">⚡ Flash Sale</span>
            </div>
            <div class="order-meta">
              <span>{{ formatDate(order.created_at) }}</span>
              <span class="dot">•</span>
              <span>{{ order.items ? order.items.length : 0 }} sản phẩm</span>
            </div>
          </div>
          <div class="order-header-center">
            <div class="status-badge" :class="getStatusClass(order.fulfillment_status)">
              <span class="status-icon" v-html="getStatusIcon(order.fulfillment_status)"></span>
              {{ getStatusText(order.fulfillment_status) }}
            </div>
          </div>
          <div class="order-header-right">
            <span class="order-total">{{ formatPrice(order.grand_total) }}</span>
            <div class="payment-status-badge" :class="order.fulfillment_status">
              {{ getSummaryStatusText(order.fulfillment_status) }}
            </div>

            <router-link
              v-if="order.latest_return_request"
              :to="{ name: 'profile-return-request-detail', params: { id: order.latest_return_request.id } }"
              class="return-status-chip"
              :class="getReturnRequestStatusTone(order.latest_return_request.status)"
            >
              {{ getReturnRequestStatusLabel(order.latest_return_request.status) }}
            </router-link>
            
            <button 
              v-if="order.fulfillment_status === 'pending'" 
              class="btn-action btn-cancel-order"
              @click="openCancelModal(order.order_id)"
              :disabled="actionLoading === order.order_id"
            >
              <span v-if="actionLoading === order.order_id" class="spinner-small"></span>
              <span v-else>⊗ Yêu cầu hủy</span>
            </button>
            <button 
              v-else-if="['completed', 'cancelled', 'returned'].includes(order.fulfillment_status)" 
              class="btn-action btn-buy-again" 
              @click="buyAgain(order.order_id)"
            >
              ↻ Mua lại
            </button>
            <template v-if="order.fulfillment_status === 'completed' || order.fulfillment_status === 'delivered'">
              <button 
                v-if="!order.is_reviewed"
                class="btn-action btn-feedback"
                @click="openFeedback(order)"
              >
                ★ Đánh giá
              </button>
              <p v-else class="evaluation-status-text">Bạn đã đánh giá</p>
            </template>
            <router-link :to="{ name: 'profile-order-detail', params: { id: order.order_id } }" class="btn-action btn-detail mt-2">
              {{ order.can_request_return ? 'Xem chi tiết / hoàn hàng' : 'Xem chi tiết' }}
            </router-link>
          </div>
        </div>
        
        <!-- Hiển thị sản phẩm tóm tắt (tuỳ chọn) -->
        <div class="order-items-preview" v-if="order.items && order.items.length > 0">
           <div class="preview-item">
              <span class="item-name">{{ order.items[0].product_name }}</span>
              <span class="item-variant" v-if="order.items[0].variant_name">({{ order.items[0].variant_name }})</span>
              <span class="item-qty">x{{ order.items[0].quantity }}</span>
           </div>
           <div class="preview-more" v-if="order.items.length > 1">
              và {{ order.items.length - 1 }} sản phẩm khác...
           </div>
        </div>
      </div>
      
      <!-- Phân trang -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button 
          class="page-btn" 
          :disabled="currentPage === 1" 
          @click="changePage(currentPage - 1)">«</button>
        <span class="page-info">Trang {{ currentPage }} / {{ pagination.last_page }}</span>
        <button 
          class="page-btn" 
          :disabled="currentPage === pagination.last_page" 
          @click="changePage(currentPage + 1)">»</button>
      </div>
    </div>
    
    <!-- Feedback Modal -->
    <FeedbackModal 
        v-model="showFeedbackModal" 
        :order="selectedOrderForFeedback" 
        @feedback-submitted="onFeedbackSubmitted" 
    />

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
      <div class="toast align-items-center border-0" :class="toastData.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="ordersToast" role="alert">
        <div class="d-flex">
          <div class="toast-body">{{ toastData.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-orders-page {
  background: var(--card-bg);
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 24px;
  min-height: 500px;
}

.page-header {
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.page-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-main);
  margin: 0;
}

/* Order Status Tabs */
.order-status-tabs {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 24px;
}
.order-status-tabs::-webkit-scrollbar {
  height: 4px;
}
.order-status-tabs::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.status-tab {
  background: var(--card-bg);
  border: 1px solid #cbd5e1;
  padding: 6px 18px;
  border-radius: 20px;
  white-space: nowrap;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}
.status-tab:hover {
  background: #f8fafc;
  color: var(--text-main);
  border-color: #94a3b8;
}
.status-tab.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
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

.empty-state {
  text-align: center;
  padding: 60px 20px;
}
.empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1.1rem; color: #334155; margin-bottom: 8px; font-weight: 700; }
.empty-state p { color: #64748b; font-size: 0.95rem; }
.btn-primary { display: inline-block; background: var(--primary); color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center; transition: background 0.2s; }
.btn-primary:hover { background: #cb184d; transition: 0.2s ease; }
.mt-4 { margin-top: 16px; }

/* Orders List */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.order-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: box-shadow 0.2s;
  background: #fafafb;
}
.order-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.03); border-color: #cbd5e1; }

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.order-header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.order-code {
  font-weight: 800;
  color: var(--text-main);
  font-size: 1.05rem;
}
.order-meta {
  color: #64748b;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 6px;
}
.dot { font-size: 0.8rem; opacity: 0.5; }

.order-header-center {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px;
  border-radius: 30px;
  font-size: 0.85rem;
  font-weight: 600;
  background: var(--card-bg);
  border: 1px solid #e2e8f0;
}
.status-badge.status-info { color: #475569; background: #f8fafc; border-color: #e2e8f0; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.order-header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  min-width: 140px;
}

.order-total {
  font-weight: 800;
  color: var(--primary);
  font-size: 1.1rem;
}

.payment-status-badge {
  font-size: 0.75rem;
  padding: 4px 12px;
  border-radius: 12px;
  font-weight: 600;
}
.payment-status-badge.pending { background: #f1f5f9; color: #64748b; }
.payment-status-badge.shipping { background: #e0f2fe; color: var(--primary); }
.payment-status-badge.completed { background: #fee2e2; color: #ef4444; }
.payment-status-badge.cancelled { background: #fee2e2; color: #dc2626; }
.payment-status-badge.return_requested,
.payment-status-badge.return_approved { background: #fff7ed; color: #c2410c; }
.payment-status-badge.return_rejected { background: #fee2e2; color: #be123c; }
.payment-status-badge.returned,
.payment-status-badge.refunded { background: #ecfeff; color: #0f766e; }

.return-status-chip {
  text-decoration: none;
  font-size: 0.76rem;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid transparent;
}
.return-status-chip.status-info { color: #475569; background: #f8fafc; border-color: #cbd5e1; }
.return-status-chip.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.return-status-chip.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.return-status-chip.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.btn-action {
  background: var(--card-bg);
  border: 1.5px solid;
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}
.btn-cancel-order {
  border-color: #fca5a5;
  color: #ef4444;
}
.btn-cancel-order:hover {
  background: #fef2f2;
  border-color: #ef4444;
}
.btn-cancel-order:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-buy-again {
  border-color: var(--primary);
  color: var(--primary);
}
.btn-buy-again:hover {
  background: #f0f9ff;
  border-color: var(--primary);
}

.btn-feedback {
  border-color: #fbbf24;
  color: #d97706;
}
.btn-feedback:hover {
  background: #fef3c7;
  border-color: #fbbf24;
}

.evaluation-status-text {
  font-size: 0.85rem;
  font-weight: 700;
  color: #16a34a;
  margin: 4px 0;
}

.btn-detail {
  border-color: #cbd5e1;
  color: #475569;
  text-decoration: none;
}
.btn-detail:hover {
  background: #f8fafc;
  border-color: #475569;
  color: var(--text-main);
}

/* Order items preview */
.order-items-preview {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px dashed #e2e8f0;
  font-size: 0.85rem;
}
.preview-item {
  color: #334155;
}
.item-name { font-weight: 600; }
.item-variant { color: #64748b; margin-left: 4px; }
.item-qty { font-weight: 700; color: var(--primary); margin-left: 6px; }
.preview-more { color: #94a3b8; margin-top: 4px; font-style: italic; }

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 20px;
}
.page-btn {
  background: var(--card-bg);
  border: 1px solid #cbd5e1;
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-weight: 700; color: #334155;
}
.page-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--primary); border-color: var(--primary); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; font-weight: 600; color: #64748b; }

.animate-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .order-header { flex-direction: column; gap: 12px; }
  .order-header-center { justify-content: flex-start; }
  .order-header-right { align-items: flex-start; }
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
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #e2e8f0; }
.btn-cancel-dismiss { padding: 8px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: var(--card-bg); color: #64748b; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-dismiss:hover { background: #f1f5f9; }
.btn-cancel-confirm { padding: 8px 20px; border-radius: 8px; border: none; background: #dc2626; color: white; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-confirm:hover { background: #b91c1c; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .cancel-modal-box, .modal-leave-to .cancel-modal-box { transform: scale(0.95) translateY(10px); }
</style>
