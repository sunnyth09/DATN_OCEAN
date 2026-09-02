<script setup>
import { ref, nextTick, onMounted, onUnmounted, computed } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { useAdminKeepAlive } from '@/composables/useAdminKeepAlive';
import api from '@/axios';
import Swal from 'sweetalert2';
import AppIcon from '@/components/AppIcon.vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

const showToastNotify = (message, type = 'success') => {
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
  success: (msg) => showToastNotify(msg, 'success'),
  error: (msg) => showToastNotify(msg, 'danger'),
  info: (msg) => showToastNotify(msg, 'info'),
};

const route = useRoute();
const router = useRouter();

const orders = ref([]);
const loading = ref(true);
const currentStatus = ref('');
const searchQuery = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const selectedOrders = ref([]);
const bulkActionLoading = ref(false);
const bulkFulfillmentStatus = ref('');
const statusActionLoadingId = ref(null);

const pagination = ref({
    current_page: 1,
    last_page: 1,
});

const statuses = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'confirmed', label: 'Đã duyệt' },
  { value: 'processing', label: 'Đang xử lý' },
  { value: 'packing', label: 'Đóng gói' },
  { value: 'awaiting_pickup', label: 'Chờ lấy hàng' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'delivered', label: 'Đã giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' },
  { value: 'return_requested', label: 'Yêu cầu hoàn' },
  { value: 'return_approved', label: 'Đã duyệt hoàn' },
  { value: 'return_rejected', label: 'Từ chối hoàn' },
  { value: 'returning', label: 'Khách đang hoàn' },
  { value: 'warehouse_received', label: 'Kho đã nhận hàng' },
  { value: 'inspection_failed', label: 'Kiểm tra thất bại' },
  { value: 'inspected_ok', label: 'Kiểm tra đạt' },
  { value: 'returned', label: 'Đã nhận hàng hoàn' },
  { value: 'refunded', label: 'Đã hoàn tiền' },
];

const fulfillmentOptions = [
  { value: 'confirmed', label: 'Duyệt đơn (Đã duyệt)' },
  { value: 'processing', label: 'Chuyển sang đang xử lý' },
  { value: 'packing', label: 'Chuyển sang đóng gói' },
  { value: 'awaiting_pickup', label: 'Chờ lấy hàng' },
  { value: 'cancelled', label: 'Hủy đơn hàng' },
];

const isLockedFulfillmentStatus = (status) => {
  return ['shipping', 'delivered', 'completed', 'cancelled', 'return_requested', 'return_approved', 'return_rejected', 'returning', 'warehouse_received', 'inspection_failed', 'inspected_ok', 'returned', 'refunded'].includes(status);
};

// ===== Payment Status (Chỉ hiển thị, không cho admin sửa) =====
const paymentLabels = {
  'unpaid': 'Chưa TT',
  'paid': 'Đã TT',
  'pending': 'Đang xử lý',
  'failed': 'Thất bại',
  'refunded': 'Hoàn tiền',
  'partially_refunded': 'Hoàn 1 phần',
};

paymentLabels.refund_pending = 'Chờ hoàn';
paymentLabels.refund_failed = 'Hoàn lỗi';

const fetchOrders = async (page = 1, showLoading = true) => {
  if (showLoading) loading.value = true;
  if (showLoading) selectedOrders.value = []; // Clear selection when changing page

  try {
    const res = await api.get('/admin/orders', {
      params: {
        page: page,
        status: currentStatus.value || 'all',
        search: searchQuery.value || null,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null
      },
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        'Expires': '0',
      }
    });
    if (res.data.status === 'success') {
      orders.value = res.data.data.data.map(o => ({ ...o, _prevFulfillmentStatus: o.fulfillment_status, _prevPaymentStatus: o.payment_status }));
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
      };
    }
  } catch (error) {
    console.error('Fetch orders failed', error);
    toast.error('Không thể tải danh sách đơn hàng');
  } finally {
    if (showLoading) loading.value = false;
  }
};

useAdminKeepAlive({
  resourceKey: 'orders',
  fetchFn: () => fetchOrders(pagination.value.current_page || 1, false),
  ttl: 180000,
});

const handleSearch = () => {
    fetchOrders(1);
};

const handleClearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    currentStatus.value = '';
    fetchOrders(1);
};

const handleFilterStatus = (status) => {
    currentStatus.value = currentStatus.value === status ? '' : status;
    fetchOrders(1);
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    // Sync trang vào URL để khi bấm Back trên trình duyệt URL đúng
    const q = { ...route.query };
    if (page <= 1) { delete q.page; } else { q.page = String(page); }
    router.replace({ query: q });
    fetchOrders(page);
  }
}

// Lý do hủy đơn Admin (chuẩn ecommerce)
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
const cancellingOrder = ref(null);
const selectedCancelReason = ref('');
const customCancelReason = ref('');
const selectedRefundType = ref('wallet'); // 'wallet' hoặc 'manual'
const cancelValidationError = ref('');
let cancelReasonResolver = null;

const showCancelReasonModal = (order) => {
  cancellingOrder.value = order;
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
  cancellingOrder.value = null;
  if (cancelReasonResolver) cancelReasonResolver(null);
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
  warehouse_received: { icon: 'box', label: 'Kho đã nhận', success: 'Kho đã nhận sản phẩm hoàn trả!' },
  inspection_failed: { icon: 'x-circle', label: 'Không đạt kiểm tra', success: 'Hàng hoàn trả không đạt yêu cầu!' },
  inspected_ok: { icon: 'check-circle', label: 'Đạt kiểm tra', success: 'Hàng hoàn trả đạt yêu cầu!' },
  returned: { icon: 'package-check', label: 'Đã nhận hàng hoàn', success: 'Đã xác nhận nhận hàng hoàn!' },
  refunded: { icon: 'corner-down-left', label: 'Đã hoàn tiền', success: 'Đã hoàn tiền thành công!' },
};

const getOrderStatusActions = (order) => {
  const allowed = order.available_transitions || [];
  return allowed
    .filter((status) => {
      if (order.tracking_number && order.tracking_number !== 'SELF-DELIVERY') {
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

const updateOrderStatus = async (order, action) => {
  const nextStatus = action.value;
  const oldStatus = order._prevFulfillmentStatus || order.fulfillment_status || 'pending';
  if (nextStatus === oldStatus) return;

  const payload = { fulfillment_status: nextStatus };
  if (nextStatus === 'cancelled') {
    const cancelResult = await showCancelReasonModal(order);
    if (!cancelResult) return;
    payload.note = cancelResult.reason;
    payload.refund_type = cancelResult.refund_type;
  } else {
    const confirmResult = await Swal.fire({
      title: 'Xác nhận',
      text: `Bạn có chắc chắn muốn ${action.label.toLowerCase()} cho đơn hàng #${order.order_code}?`,
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

  statusActionLoadingId.value = order.order_id;
  try {
    const res = await api.put(`/admin/orders/${order.order_id}/status`, payload);
    if (res.data.status === 'success') {
      order.fulfillment_status = nextStatus;
      order._prevFulfillmentStatus = nextStatus;
      if (nextStatus === 'cancelled') order.cancel_reason = payload.note;
      toast.success(action.success || 'Cập nhật trạng thái thành công!');
      window.dispatchEvent(new Event('admin-order-updated'));
      await fetchOrders(pagination.value.current_page, false);
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi cập nhật trạng thái');
  } finally {
    statusActionLoadingId.value = null;
  }
};

const syncingOrderId = ref(null);
const syncGhn = async (order) => {
  const confirmResult = await Swal.fire({
    title: 'Đẩy đơn vận chuyển',
    text: `Tạo vận đơn và đẩy đơn hàng #${order.order_code} sang đối tác Ocean Express?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#E63B6F',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Đồng ý đẩy đơn',
    cancelButtonText: 'Hủy'
  });
  if (!confirmResult.isConfirmed) return;

  syncingOrderId.value = order.order_id;
  try {
    const res = await api.post(`/admin/orders/${order.order_id}/ghn-sync`);
    if (res.data.status === 'success') {
      toast.success(res.data.message || 'Đã tạo vận đơn trên Ocean Express thành công!');
      window.dispatchEvent(new Event('admin-order-updated'));
      await fetchOrders(pagination.value.current_page, false);
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Không thể đẩy đơn sang nhà vận chuyển');
  } finally {
    syncingOrderId.value = null;
  }
};


const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const formatDate = (dateString, includeTime=true) => {
  const date = new Date(dateString);
  if (includeTime) {
      return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getStatusLabel = (status) => statuses.find(s => s.value === status)?.label || status;

const isAllSelected = computed({
  get() {
    const selectableOrders = orders.value.filter(o => !isLockedFulfillmentStatus(o.fulfillment_status));
    return selectableOrders.length > 0 && selectedOrders.value.length === selectableOrders.length;
  },
  set(value) {
    if (value) {
      selectedOrders.value = orders.value.filter(o => !isLockedFulfillmentStatus(o.fulfillment_status)).map(o => o.order_id);
    } else {
      selectedOrders.value = [];
    }
  }
});

const applyBulkStatus = async () => {
    if (selectedOrders.value.length === 0) return;
    if (!bulkFulfillmentStatus.value) {
        toast.error('Vui lòng chọn trạng thái muốn cập nhật hàng loạt');
        return;
    }

    // Kiểm tra tất cả đơn đã chọn có đang ở trạng thái được chọn chưa
    const selectedOrdersList = orders.value.filter(o => selectedOrders.value.includes(o.order_id));
    const allSameStatus = selectedOrdersList.every(o => o.fulfillment_status === bulkFulfillmentStatus.value);
    if (allSameStatus) {
        const label = statuses.find(s => s.value === bulkFulfillmentStatus.value)?.label || bulkFulfillmentStatus.value;
        toast.error(`Tất cả ${selectedOrdersList.length} đơn đã ở trạng thái "${label}" rồi. Vui lòng chọn trạng thái tiếp theo!`);
        return;
    }

    // Kiểm tra tính hợp lệ của luồng trạng thái
    const invalidTransitions = selectedOrdersList.filter(o => {
        const allowed = o.available_transitions || [];
        return !allowed.includes(bulkFulfillmentStatus.value);
    });

    if (invalidTransitions.length > 0) {
        toast.error(`Có ${invalidTransitions.length} đơn hàng không thể chuyển sang trạng thái này do sai quy trình (vượt cấp hoặc đi lùi)!`);
        return;
    }

    let note = '';
    if (bulkFulfillmentStatus.value === 'cancelled') {
        const reason = await showCancelReasonModal();
        if (!reason) return;
        note = reason;
    } else {
        const label = statuses.find(s => s.value === bulkFulfillmentStatus.value)?.label || bulkFulfillmentStatus.value;
        const confirmResult = await Swal.fire({
            title: 'Xác nhận',
            text: `Bạn có chắc chắn muốn cập nhật hàng loạt ${selectedOrdersList.length} đơn hàng sang trạng thái "${label}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#E63B6F',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Hủy'
        });
        if (!confirmResult.isConfirmed) return;
    }

    bulkActionLoading.value = true;
    try {
        const payload = {
            order_ids: selectedOrders.value,
        };
        if (bulkFulfillmentStatus.value) payload.fulfillment_status = bulkFulfillmentStatus.value;
        if (note) payload.note = note;

        const res = await api.put('/admin/orders/bulk-status', payload);

        if (res.data.status === 'success') {
            toast.success(res.data.message);
            selectedOrders.value = [];
            bulkFulfillmentStatus.value = '';
            window.dispatchEvent(new Event('admin-order-updated'));
            fetchOrders(pagination.value.current_page, false);
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi khi cập nhật hàng loạt');
    } finally {
        bulkActionLoading.value = false;
    }
};

// Lưu trạng thái trang khi rời khỏi trang danh sách (sang chi tiết đơn hàng)
const ORDER_STATE_KEY = 'admin_order_list_state';
onBeforeRouteLeave(() => {
  sessionStorage.setItem(ORDER_STATE_KEY, JSON.stringify({
    page: pagination.value.current_page,
    status: currentStatus.value,
    search: searchQuery.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
  }));
});

onMounted(() => {
  // Ưu tiên URL query (?page=2), sau đó sessionStorage (khi back từ chi tiết đơn)
  const urlPage = parseInt(route.query.page);
  const savedRaw = sessionStorage.getItem(ORDER_STATE_KEY);

  if (urlPage && urlPage > 1) {
    // URL có page → dùng luôn (trường hợp user share link hay reload)
    sessionStorage.removeItem(ORDER_STATE_KEY);
    fetchOrders(urlPage);
  } else if (savedRaw) {
    // Có state lưu từ lần rời trang trước → khôi phục toàn bộ
    sessionStorage.removeItem(ORDER_STATE_KEY);
    try {
      const state = JSON.parse(savedRaw);
      currentStatus.value = state.status || '';
      searchQuery.value   = state.search  || '';
      dateFrom.value      = state.dateFrom || '';
      dateTo.value        = state.dateTo   || '';
      const pg = parseInt(state.page) || 1;
      // Cập nhật URL cho đúng
      if (pg > 1) router.replace({ query: { ...route.query, page: String(pg) } });
      fetchOrders(pg);
    } catch {
      fetchOrders(1);
    }
  } else {
    fetchOrders(1);
  }

  if (window.Echo) {
    window.Echo.private('admin-notifications')
      .listen('.OrderCreatedAdmin', (event) => {
        toast.info(`🛒 Có đơn hàng mới: ${event.order_code}`);
        if (pagination.value.current_page === 1 && (!currentStatus.value || currentStatus.value === 'pending')) {
            orders.value.unshift({
                ...event,
                order_id: event.order_id,
                is_new: true
            });
            if (orders.value.length > 15) orders.value.pop();
        }
      })
      .listen('.OrderPaymentUpdated', (event) => {
        // Cập nhật payment_status của đơn đã có trong list — không thêm row mới
        const idx = orders.value.findIndex(o => o.order_id === event.order_id);
        if (idx !== -1) {
            orders.value[idx] = { ...orders.value[idx], payment_status: event.payment_status };
        }
        toast.success(`💳 Đơn hàng #${event.order_code} đã thanh toán thành công!`);
      });
  }
});

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leave('admin-notifications');
  }
});
</script>

<template>
  <div class="orders-page">
    <!-- Page Header -->
    <div class="page-header animate-in">
        <div class="header-info">
            <h1 class="page-title">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                Quản Lý Đơn Hàng
            </h1>
            <p class="page-subtitle">Theo dõi và cập nhật trạng thái các đơn đặt hàng</p>
        </div>
        <div class="header-btns">
            <!-- Tương lai có thể thêm nút Export CSV ở đây -->
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
        <div class="search-date-wrap">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input 
                    type="text" 
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Tìm mã đơn, tên khách, SĐT..." 
                    class="search-input"
                />
            </div>
            
            <div class="date-picker-box">
                <span class="date-lbl">Từ</span>
                <input type="date" v-model="dateFrom" class="date-input" @change="handleSearch" />
                <span class="date-lbl">Đến</span>
                <input type="date" v-model="dateTo" class="date-input" @change="handleSearch" />
            </div>

            <button v-if="searchQuery || dateFrom || dateTo || currentStatus" class="btn-clear-filters" @click="handleClearFilters" title="Xóa bộ lọc">
                ❌
            </button>
        </div>

        <div class="filter-actions mt-2">
            <button class="filter-btn" :class="{ active: !currentStatus }" @click="handleFilterStatus('')">Tất cả</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'pending' }" @click="handleFilterStatus('pending')">Chờ duyệt</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'confirmed' }" @click="handleFilterStatus('confirmed')">Đã duyệt</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'processing' }" @click="handleFilterStatus('processing')">Đang xử lý</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'packing' }" @click="handleFilterStatus('packing')">Đóng gói</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'awaiting_pickup' }" @click="handleFilterStatus('awaiting_pickup')">Chờ lấy hàng</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'shipping' }" @click="handleFilterStatus('shipping')">Đang giao</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'delivered' }" @click="handleFilterStatus('delivered')">Đã giao</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'completed' }" @click="handleFilterStatus('completed')">Hoàn thành</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'cancelled' }" @click="handleFilterStatus('cancelled')">Đã hủy</button>
        </div>
    </div>

    <!-- Loading State -->
    <AdminTableSkeleton v-if="loading" :columns="7" :rows="6" />

    <div v-else>
        <!-- Bulk Actions -->
        <div v-if="selectedOrders.length > 0" class="bulk-actions-bar animate-in">
            <span class="selected-count">Đã chọn <b>{{ selectedOrders.length }}</b> đơn hàng</span>
            <div class="bulk-controls">
                <select v-model="bulkFulfillmentStatus" class="bulk-select">
                    <option value="">-- Trạng thái Giao hàng --</option>
                    <option v-for="s in fulfillmentOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <button class="btn-bulk-apply" @click="applyBulkStatus" :disabled="bulkActionLoading">
                    {{ bulkActionLoading ? 'Đang xử lý...' : 'Áp dụng' }}
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" v-model="isAllSelected" class="order-checkbox" />
                        </th>
                        <th>Mã đơn & Ngày đặt</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái (Fulfillment)</th>
                        <th>Thanh toán</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="orders.length === 0">
                        <td colspan="7">
                            <div class="empty-state">
                                <span class="empty-emoji">
                                  <AppIcon name="shopping-cart" size="60"/>
                                </span>
                                <h3>Không tìm thấy đơn hàng</h3>
                                <p>Thử tìm kiếm với từ khóa hoặc ngày khác.</p>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="order in orders" :key="order.order_id" :class="{'is-new-order': order.is_new, 'is-selected': selectedOrders.includes(order.order_id)}">
                        <td class="checkbox-cell">
                            <input type="checkbox" :value="order.order_id" v-model="selectedOrders" class="order-checkbox" :disabled="isLockedFulfillmentStatus(order.fulfillment_status)" :title="isLockedFulfillmentStatus(order.fulfillment_status) ? 'Không thể thao tác hàng loạt với đơn hàng đã đóng' : ''"/>
                        </td>
                        <td>
                            <div class="order-code-cell">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span class="badge-id">#{{ order.order_code }}</span>
                                    <span v-if="order.order_code && order.order_code.startsWith('FS-')" class="badge bg-warning text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><AppIcon name="zap" size="10" /> Flash Sale</span>
                                </div>
                                <span class="order-date">{{ formatDate(order.created_at) }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="customer-cell">
                                <span class="cus-name">{{ order.recipient_name }}</span>
                                <span class="cus-phone">{{ order.recipient_phone }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="val-price">{{ formatPrice(order.grand_total) }}</span>
                        </td>
                        <td>
                            <div class="status-action-cell">
                                <span class="status-readonly-badge" :class="'f-'+order.fulfillment_status">
                                    {{ getStatusLabel(order.fulfillment_status) }}
                                </span>
                                <button
                                    v-for="action in getOrderStatusActions(order)"
                                    :key="action.value"
                                    class="btn-status-action"
                                    :class="action.value"
                                    @click="updateOrderStatus(order, action)"
                                    :disabled="statusActionLoadingId === order.order_id"
                                    :title="action.label"
                                    :aria-label="action.label"
                                ><AppIcon :name="action.icon" size="14" stroke-width="3" /></button>
                            </div>
                        </td>
                        <td>
                            <span class="payment-badge" :class="'p-'+order.payment_status">
                                {{ paymentLabels[order.payment_status] || order.payment_status }}
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button
                                    v-if="!order.tracking_number && ['confirmed', 'processing', 'packing', 'awaiting_pickup'].includes(order.fulfillment_status)"
                                    class="btn-icon ship-btn"
                                    @click="syncGhn(order)"
                                    :disabled="syncingOrderId === order.order_id"
                                    title="Đẩy đơn cho nhà vận chuyển"
                                >
                                    <AppIcon name="truck" size="16" />
                                </button>
                                <router-link :to="{ name: 'admin-order-detail', params: { id: order.order_id } }" class="btn-icon view" title="Chi tiết">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </router-link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination">
            <button class="page-btn" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">‹</button>
            <button
                v-for="page in pagination.last_page"
                :key="page"
                class="page-btn"
                :class="{ active: page === pagination.current_page }"
                @click="changePage(page)"
            >{{ page }}</button>
            <button class="page-btn" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">›</button>
        </div>
    </div> <!-- Close table-container -->
    </div> <!-- Close v-else main content -->

    <!-- Cancel Reason Modal -->
    <Transition name="modal">
      <div v-if="showCancelModal" class="cancel-modal-overlay" @click.self="dismissCancelModal">
        <div class="cancel-modal-box">
          <div class="cancel-modal-header">
            <h5>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Hủy đơn hàng <span v-if="cancellingOrder">#{{ cancellingOrder.order_code }}</span>
            </h5>
            <button class="cancel-modal-close" @click="dismissCancelModal">×</button>
          </div>
          <div class="cancel-modal-body">
            <!-- Cảnh báo hoàn tiền nếu đơn đã thanh toán -->
            <div v-if="cancellingOrder && (cancellingOrder.payment_status === 'paid' || cancellingOrder.payment_status === 'refunded')" class="refund-alert-box mb-3">
              <div class="refund-alert-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Đơn hàng đã thanh toán thành công</span>
              </div>
              <div class="refund-alert-amount">
                Số tiền hoàn trả: <strong>{{ formatPrice(cancellingOrder.grand_total) }}</strong>
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
.orders-page { font-family: var(--font-inter); }

/* Bulk Actions */
.bulk-actions-bar {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--ocean-deepest); border: 2px dashed rgba(230, 59, 111, 0.5);
    border-radius: 8px; padding: 12px 20px; margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(230, 59, 111, 0.05);
}
.selected-count { font-size: 0.95rem; color: var(--text-main); }
.bulk-controls { display: flex; gap: 12px; }
.bulk-select {
    border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 12px;
    font-family: inherit; font-size: 0.85rem; outline: none; background: var(--card-bg);
}
.btn-bulk-apply {
    background: var(--primary); color: white; border: none; border-radius: 6px;
    padding: 8px 20px; font-weight: 600; cursor: pointer; transition: 0.2s;
}
.btn-bulk-apply:hover:not(:disabled) { background: #d82f65; }
.btn-bulk-apply:disabled { opacity: 0.6; cursor: not-allowed; }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px; margin: 0;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; margin-bottom: 0;}

/* Filters */
.filters-bar {
    padding: 18px 20px;
    margin-bottom: 24px;
    border-radius: 16px;
}
.search-date-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--ocean-deepest, #f8fafc);
    border: 1.5px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 8px 16px;
    min-width: 320px;
    max-width: 440px;
    flex: 1;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.search-box:focus-within {
    border-color: var(--primary, #E63B6F);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.12);
}
.search-box svg {
    color: var(--text-light, #94a3b8);
    flex-shrink: 0;
}
.search-input {
    background: transparent !important;
    border: none !important;
    outline: none !important;
    color: var(--text-main, #1e293b) !important;
    font-family: inherit !important;
    font-size: 0.9rem !important;
    width: 100% !important;
    padding: 0 !important;
    box-shadow: none !important;
}
.search-input::placeholder {
    color: var(--text-light, #94a3b8);
}
 
.date-picker-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--ocean-deepest, #f8fafc);
    padding: 7px 16px;
    border-radius: 12px;
    border: 1.5px solid var(--border-color, #e2e8f0);
    transition: all 0.2s ease;
}
.date-picker-box:focus-within {
    border-color: var(--primary, #E63B6F);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.12);
}
.date-lbl {
    font-size: 0.85rem;
    color: var(--text-secondary, #64748b);
    font-weight: 700;
}
.date-input {
    border: none !important;
    outline: none !important;
    background: transparent !important;
    font-family: inherit !important;
    font-size: 0.85rem !important;
    color: var(--text-main, #1e293b) !important;
    cursor: pointer;
    font-weight: 600;
    padding: 0 !important;
    box-shadow: none !important;
}
.btn-clear-filters {
    background: none;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    line-height: 1;
    padding: 6px 10px;
    border-radius: 8px;
    transition: background 0.2s;
}
.btn-clear-filters:hover {
    background: var(--hover-bg, rgba(0, 0, 0, 0.05));
}
 
.filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 8px 18px;
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--surface-container-low, #f8fafc);
    color: var(--text-main, #334155);
    font-family: var(--font-inter);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-btn:hover {
    border-color: var(--primary, #E63B6F);
    color: var(--primary, #E63B6F);
    transform: translateY(-1px);
}
.filter-btn.active {
    background: rgba(230, 59, 111, 0.1);
    border-color: var(--primary, #E63B6F);
    color: var(--primary, #E63B6F);
    box-shadow: 0 4px 10px rgba(230, 59, 111, 0.15);
}
 
/* Loading */
.loading-state { text-align: center; padding: 60px 20px; }
 
/* Table */
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.data-table td { padding: 16px 24px; border-bottom: 1px solid var(--border-color); transition: background 0.15s; vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--hover-bg); }
.is-new-order td { background: rgba(251, 191, 36, 0.05); }
 
/* Checkbox */
.checkbox-cell { width: 40px; text-align: center; padding-left: 16px !important; padding-right: 8px !important; }
.order-checkbox { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
.is-selected td { background: rgba(230, 59, 111, 0.05) !important; }
 
.order-code-cell { display: flex; flex-direction: column; gap: 4px; align-items: flex-start;}
.badge-id { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; background: rgba(230, 59, 111, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; }
.order-date { font-size: 0.75rem; color: var(--text-muted); }
 
.customer-cell { display: flex; flex-direction: column; gap: 2px; }
.cus-name { font-size: 0.95rem; font-weight: 700; color: var(--text-main); }
.cus-phone { font-size: 0.8rem; color: var(--text-light); }
 
.val-price { font-size: 0.95rem; font-weight: 800; color: var(--coral); }
 
/* Status action badges */
.status-action-cell {
    display: grid;
    grid-template-columns: minmax(88px, max-content) repeat(3, 30px);
    align-items: center;
    justify-content: start;
    gap: 6px;
    min-width: 190px;
    min-height: 34px;
}
.status-readonly-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 82px; height: 30px;
    border-radius: 9999px; padding: 0 12px;
    font-family: var(--font-inter); font-size: 0.78rem; font-weight: 800;
    white-space: nowrap;
    line-height: 1;
}
.btn-status-action {
    width: 30px; height: 30px; border: none; border-radius: 50%; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    font-family: var(--font-inter); font-size: 0.86rem; line-height: 1; font-weight: 900;
    cursor: pointer; transition: all 0.2s; flex-shrink: 0;
}
.btn-status-action.confirmed,
.btn-status-action.completed { background: #dcfce7; color: #15803d; }
.btn-status-action.processing { background: #e0f2fe; color: #0369a1; }
.btn-status-action.packing { background: #ede9fe; color: #6d28d9; }
.btn-status-action.awaiting_pickup { background: #fef3c7; color: #b45309; }
.btn-status-action.shipping,
.btn-status-action.delivered { background: #dbeafe; color: #1d4ed8; }
.btn-status-action.cancelled { background: #fee2e2; color: #b91c1c; }
.btn-status-action:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(0.96); }
.btn-status-action:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Colors for Fulfillment */
.f-pending { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.f-confirmed { background: rgba(230, 59, 111, 0.1); color: var(--primary); }
.f-packing, .f-awaiting_pickup, .f-shipping { background: rgba(0, 188, 212, 0.15); color: #0097a7; }
.f-delivered, .f-completed { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.f-cancelled { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.f-return_requested, .f-return_approved { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.f-return_rejected { background: rgba(244, 114, 182, 0.15); color: #be185d; }
.f-returned, .f-refunded { background: rgba(148, 163, 184, 0.18); color: #475569; }
:global(html.dark) .f-pending { background: rgba(255, 167, 38, 0.15) !important; color: #ffb74d !important; }
:global(html.dark) .f-confirmed { background: rgba(230, 59, 111, 0.15) !important; color: #ffb2bf !important; }
:global(html.dark) .f-packing, :global(html.dark) .f-awaiting_pickup, :global(html.dark) .f-shipping { background: rgba(0, 188, 212, 0.15) !important; color: #4fc3f7 !important; }
:global(html.dark) .f-delivered, :global(html.dark) .f-completed { background: rgba(38, 166, 154, 0.15) !important; color: #4db6ac !important; }
:global(html.dark) .f-cancelled { background: rgba(239, 83, 80, 0.15) !important; color: #e57373 !important; }
:global(html.dark) .f-return_requested, :global(html.dark) .f-return_approved { background: rgba(245, 158, 11, 0.16) !important; color: #fdba74 !important; }
:global(html.dark) .f-return_rejected { background: rgba(244, 114, 182, 0.16) !important; color: #f9a8d4 !important; }
:global(html.dark) .f-returned, :global(html.dark) .f-refunded { background: rgba(148, 163, 184, 0.2) !important; color: #cbd5e1 !important; }

/* Payment Badge (read-only) */
.payment-badge {
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; padding: 6px 12px;
    font-family: var(--font-inter); font-size: 0.8rem; font-weight: 700;
    letter-spacing: 0.2px; white-space: nowrap;
}
.p-unpaid { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.p-pending { background: rgba(255, 193, 7, 0.15); color: #f57f17; }
.p-paid { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.p-failed { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.p-refund_pending { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.p-refunded { background: rgba(158, 158, 158, 0.15); color: #616161; }
.p-refund_failed { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.p-partially_refunded { background: rgba(158, 158, 158, 0.15); color: #616161; }

.actions-cell { display: flex; gap: 6px; }
.btn-icon {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; text-decoration: none;
}
.btn-icon:hover { border-color: currentColor; background: var(--card-bg);}
.view:hover { color: #8e24aa; border-color: #8e24aa; background: rgba(142, 36, 170, 0.05); }
.ship-btn:hover { color: #0284c7; border-color: #0284c7; background: rgba(2, 132, 199, 0.08); }

/* Empty state */
.empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 16px; }
.empty-state h3 { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }

/* Pagination */
.pagination {
    display: flex; justify-content: center; align-items: center; gap: 8px; padding: 20px;
    border-top: 1px solid var(--border-color);
}
.page-btn {
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg);
    font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s; font-family: var(--font-inter);
}
.page-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-btn:disabled { opacity: 0.5; cursor: not-allowed; background: var(--ocean-deepest); }

/* Cancel Modal */
.cancel-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1050; }
.cancel-modal-box { background: var(--card-bg); border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden; }
.cancel-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; }
.cancel-modal-header h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #dc2626; display: flex; align-items: center; gap: 10px; }
.cancel-modal-header h5 svg { color: #dc2626; }
.cancel-modal-close { background: none; border: none; cursor: pointer; color: var(--text-light); font-size: 1.5rem; line-height: 1; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; }
.cancel-modal-close:hover { background: var(--surface-container); color: #dc2626; }
.cancel-modal-body { padding: 20px 24px; }
.cancel-modal-desc { color: var(--text-muted); font-size: 0.88rem; margin: 0 0 14px; }
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
.cancel-reason-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; background: var(--card-bg); transition: all 0.15s; font-size: 0.88rem; color: #334155; }
.cancel-reason-item:hover { border-color: #dc2626; background: #fef2f2; }
.cancel-reason-item.selected { border-color: #dc2626; background: #fef2f2; }
.cancel-reason-item input[type="radio"] { accent-color: #dc2626; width: 16px; height: 16px; flex-shrink: 0; }
.cancel-custom-input { width: 100%; margin-top: 12px; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; min-height: 70px; resize: vertical; outline: none; font-family: inherit; box-sizing: border-box; }
.cancel-custom-input:focus { border-color: #dc2626; }
.cancel-validation-error { color: #dc2626; font-size: 0.82rem; font-weight: 600; margin: 10px 0 0; }
.cancel-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #e2e8f0; }
.btn-cancel-dismiss { padding: 8px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: var(--card-bg); color: var(--text-muted); font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-dismiss:hover { background: var(--surface-container); }
.btn-cancel-confirm { padding: 8px 20px; border-radius: 8px; border: none; background: #dc2626; color: white; font-weight: 600; font-size: 0.88rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.btn-cancel-confirm:hover { background: #b91c1c; }
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .cancel-modal-box, .modal-leave-to .cancel-modal-box { transform: scale(0.95) translateY(10px); }
</style>
