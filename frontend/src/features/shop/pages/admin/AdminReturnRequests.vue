<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import { storeToRefs } from 'pinia';
import { useReturnRequestStore } from '@/stores/returnRequestStore';
import {
  RETURN_REQUEST_ADMIN_STATUS_OPTIONS,
  getReturnRequestStatusLabel,
  getReturnRequestStatusTone,
  getReturnRefundStatusLabel,
} from '@/utils/orderStatus';

const store = useReturnRequestStore();
const { adminRequests, adminPagination, adminLoading } = storeToRefs(store);

const filters = ref({
  status: 'all',
  search: '',
});

const statusTabs = [
  { value: 'all', label: 'Tất cả', icon: 'all' },
  { value: 'return_pending', label: 'Chờ duyệt', icon: 'clock' },
  { value: 'returning', label: 'Đang gửi hoàn', icon: 'truck' },
  { value: 'warehouse_received', label: 'Kho nhận & QC', icon: 'box' },
  { value: 'inspected_ok', label: 'Chờ hoàn tiền', icon: 'wallet' },
  { value: 'return_completed', label: 'Đã hoàn tất', icon: 'check' },
  { value: 'return_rejected', label: 'Đã từ chối', icon: 'x' },
];

const formatDateTime = (value) => {
  if (!value) return { time: '—', date: '' };
  const d = new Date(value);
  const time = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
  const date = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  return { time, date };
};

const formatPrice = (value) => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
}).format(Number(value || 0));

const getReasonTone = (reason) => {
  if (!reason) return 'neutral';
  const r = reason.toLowerCase();
  if (r.includes('lỗi') || r.includes('hư') || r.includes('hỏng') || r.includes('rách') || r.includes('vỡ') || r.includes('kém chất lượng')) return 'danger';
  if (r.includes('mô tả') || r.includes('sai') || r.includes('thiếu') || r.includes('nhầm') || r.includes('giao sai')) return 'warning';
  if (r.includes('size') || r.includes('vừa') || r.includes('kích') || r.includes('màu')) return 'info';
  if (r.includes('đổi ý') || r.includes('nhu cầu') || r.includes('không thích') || r.includes('mua nhầm')) return 'purple';
  return 'neutral';
};

const setTab = (tabValue) => {
  filters.value.status = tabValue;
  fetchData(1);
};

const clearSearch = () => {
  filters.value.search = '';
  fetchData(1);
};

const fetchData = (page = 1) => {
  store.fetchAdminReturnRequests({
    page,
    status: filters.value.status,
    search: filters.value.search || null,
  });
};

onMounted(() => {
  fetchData();
});
</script>

<template>
  <div class="admin-returns-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-info">
        <div class="title-with-badge">
          <div class="header-icon-box">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div>
            <h1 class="page-title">Quản lý Hoàn hàng & Đổi trả</h1>
            <p class="page-subtitle">Kiểm duyệt, theo dõi vận đơn thu hồi, giám định QC và thực hiện hoàn tiền cho khách.</p>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div v-if="adminPagination?.total !== undefined" class="total-counter-badge">
          <span class="counter-num">{{ adminPagination.total }}</span>
          <span class="counter-label">yêu cầu</span>
        </div>
      </div>
    </div>

    <!-- Status Tabs Navigation -->
    <div class="tabs-toolbar">
      <div class="status-tabs-container">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          class="status-tab-btn"
          :class="{ 'is-active': filters.status === tab.value }"
          @click="setTab(tab.value)"
        >
          <span class="tab-label">{{ tab.label }}</span>
        </button>
      </div>

      <!-- Search Box -->
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          v-model="filters.search"
          type="text"
          class="search-input"
          placeholder="Tìm mã đơn, tên khách, lý do hoàn..."
          @keyup.enter="fetchData(1)"
        />
        <button v-if="filters.search" class="clear-search-btn" @click="clearSearch">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <AdminTableSkeleton v-if="adminLoading" :columns="8" :rows="5" />

    <!-- Table Card -->
    <div v-else class="table-card">
      <div class="table-scroll-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-code">Mã yêu cầu</th>
              <th class="col-user">Khách hàng</th>
              <th class="col-reason">Lý do hoàn trả</th>
              <th class="col-items text-center">Sản phẩm</th>
              <th class="col-status text-center">Trạng thái</th>
              <th class="col-refund">Hoàn tiền</th>
              <th class="col-date">Thời gian</th>
              <th class="col-action text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="adminRequests.length === 0">
              <td colspan="8" class="empty-cell">
                <div class="empty-state">
                  <div class="empty-icon-box">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3m18 0v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8m18 0H3m6 4h6"/>
                    </svg>
                  </div>
                  <p class="empty-title">Không tìm thấy yêu cầu hoàn hàng</p>
                  <span class="empty-desc">Chưa có yêu cầu nào trong danh mục này hoặc thử tìm kiếm với từ khóa khác.</span>
                </div>
              </td>
            </tr>
            <tr v-for="item in adminRequests" :key="item.id" class="return-row">
              <!-- Mã yêu cầu -->
              <td>
                <div class="cell-stack">
                  <strong class="strong-code">{{ item.return_code || `#${item.id}` }}</strong>
                  <span class="order-sub-code">Đơn: #{{ item.order?.order_code || item.order_id }}</span>
                </div>
              </td>

              <!-- Khách hàng -->
              <td>
                <div class="user-cell-box">
                  <div class="user-avatar-placeholder">
                    {{ (item.user?.full_name || item.order?.recipient_name || 'U').charAt(0).toUpperCase() }}
                  </div>
                  <div class="cell-stack">
                    <strong class="user-name" :title="item.user?.full_name || item.order?.recipient_name">
                      {{ item.user?.full_name || item.order?.recipient_name || 'Khách vãng lai' }}
                    </strong>
                    <span class="user-sub">{{ item.user?.email || item.order?.recipient_phone || '—' }}</span>
                  </div>
                </div>
              </td>

              <!-- Lý do -->
              <td class="reason-cell">
                <div class="reason-pill" :class="`reason-pill--${getReasonTone(item.reason)}`" :title="item.reason">
                  <span class="reason-icon">
                    <svg v-if="getReasonTone(item.reason) === 'danger'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'warning'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                      <line x1="12" y1="9" x2="12" y2="13"></line>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'info'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                      <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'purple'" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                  </span>
                  <span class="reason-text">{{ item.reason || 'Chưa ghi lý do' }}</span>
                </div>
              </td>

              <!-- Sản phẩm -->
              <td class="text-center">
                <div class="cell-stack text-center">
                  <span class="qty-badge">{{ (item.items || []).reduce((sum, row) => sum + Number(row.requested_quantity || 0), 0) }} sản phẩm</span>
                  <span class="sub-qty">{{ item.items?.length || 0 }} loại</span>
                </div>
              </td>

              <!-- Trạng thái -->
              <td class="text-center">
                <span class="status-badge" :class="getReturnRequestStatusTone(item.status)">
                  <span class="dot-indicator"></span>
                  {{ getReturnRequestStatusLabel(item.status) }}
                </span>
                <div v-if="item.return_tracking_code" class="tracking-chip" title="Mã vận đơn lấy hàng">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                  <span>{{ item.return_carrier === 'dropoff_post_office' ? 'Bưu cục' : (item.return_carrier || 'Ocean Express') }}: {{ item.return_tracking_code }}</span>
                </div>
              </td>

              <!-- Hoàn tiền -->
              <td>
                <div class="cell-stack">
                  <span class="refund-status-label" :class="`refund-status--${item.refund_status}`">
                    {{ getReturnRefundStatusLabel(item.refund_status) }}
                  </span>
                  <strong v-if="Number(item.refund_amount || 0) > 0" class="refund-amount-text">
                    {{ formatPrice(item.refund_amount) }}
                  </strong>
                </div>
              </td>

              <!-- Gửi lúc -->
              <td class="date-cell">
                <div class="cell-stack">
                  <span class="time-text">{{ formatDateTime(item.requested_at || item.created_at).time }}</span>
                  <span class="date-sub">{{ formatDateTime(item.requested_at || item.created_at).date }}</span>
                </div>
              </td>

              <!-- Thao tác -->
              <td class="text-right">
                <router-link :to="{ name: 'admin-return-request-detail', params: { id: item.id } }" class="btn-action-view" title="Xem chi tiết">
                  <span>Chi tiết</span>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="adminPagination && adminPagination.last_page > 1" class="pagination-bar">
      <div class="pagination-info">
        Hiển thị <strong>{{ (adminPagination.current_page - 1) * (adminPagination.per_page || 10) + 1 }}</strong> - 
        <strong>{{ Math.min(adminPagination.current_page * (adminPagination.per_page || 10), adminPagination.total) }}</strong> 
        trên tổng số <strong>{{ adminPagination.total }}</strong> yêu cầu
      </div>
      <div class="pagination-controls">
        <button class="page-btn" :disabled="adminPagination.current_page === 1" @click="fetchData(adminPagination.current_page - 1)">
          ‹ Trang trước
        </button>
        <span class="current-page-pill">{{ adminPagination.current_page }} / {{ adminPagination.last_page }}</span>
        <button class="page-btn" :disabled="adminPagination.current_page === adminPagination.last_page" @click="fetchData(adminPagination.current_page + 1)">
          Trang sau ›
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-returns-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: var(--font-inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  width: 100%;
  box-sizing: border-box;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 0;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(230, 59, 111, 0.12) 0%, rgba(244, 63, 94, 0.06) 100%);
  color: #e63b6f;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(230, 59, 111, 0.2);
  box-shadow: 0 4px 12px rgba(230, 59, 111, 0.08);
}

.page-title {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
}

.total-counter-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 6px 18px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.counter-num {
  font-size: 1.15rem;
  font-weight: 800;
  color: #e63b6f;
  line-height: 1.2;
}

.counter-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Tabs & Toolbar */
.tabs-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  background: #ffffff;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.status-tabs-container {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  padding: 2px;
}

.status-tab-btn {
  padding: 7px 14px;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.status-tab-btn:hover {
  color: #0f172a;
  background: #f8fafc;
}

.status-tab-btn.is-active {
  color: #e63b6f;
  background: #fff1f2;
  border-color: #fecdd3;
  font-weight: 700;
  box-shadow: 0 2px 6px rgba(230, 59, 111, 0.08);
}

.search-wrap {
  display: flex;
  align-items: center;
  position: relative;
  min-width: 280px;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 8px 32px 8px 36px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  font-size: 0.85rem;
  color: #0f172a;
  outline: none;
  transition: all 0.2s ease;
}

.search-input:focus {
  background: #ffffff;
  border-color: #e63b6f;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.clear-search-btn:hover {
  color: #0f172a;
}

/* Table Card */
.table-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  overflow: hidden;
}

.table-scroll-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.data-table thead th {
  background: #f8fafc;
  padding: 12px 16px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.data-table tbody td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.875rem;
  vertical-align: middle;
}

.return-row {
  transition: background-color 0.15s ease;
}

.return-row:hover {
  background-color: #f8fafc;
}

.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.strong-code {
  font-weight: 700;
  color: #0f172a;
  font-size: 0.9rem;
}

.order-sub-code {
  font-size: 0.75rem;
  color: #64748b;
  font-family: monospace;
}

.user-cell-box {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar-placeholder {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
  color: #334155;
  font-weight: 700;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-name {
  font-weight: 600;
  color: #0f172a;
}

.user-sub {
  font-size: 0.75rem;
  color: #64748b;
}

/* Reason Pill */
.reason-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.reason-pill--danger {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.reason-pill--warning {
  background: #fffbeb;
  color: #d97706;
  border: 1px solid #fde68a;
}

.reason-pill--info {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

.reason-pill--purple {
  background: #faf5ff;
  color: #9333ea;
  border: 1px solid #e9d5ff;
}

.reason-pill--neutral {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.qty-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 6px;
  background: #f1f5f9;
  font-weight: 700;
  color: #0f172a;
  font-size: 0.8rem;
}

.sub-qty {
  font-size: 0.72rem;
  color: #64748b;
}

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
}

.dot-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status-badge.warning, .status-badge.tone-warning {
  background: #fffbeb;
  color: #d97706;
  border: 1px solid #fde68a;
}

.status-badge.info, .status-badge.tone-info {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
}

.status-badge.purple, .status-badge.tone-purple {
  background: #faf5ff;
  color: #9333ea;
  border: 1px solid #e9d5ff;
}

.status-badge.success, .status-badge.tone-success {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
}

.status-badge.danger, .status-badge.tone-danger {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.status-badge.neutral, .status-badge.tone-neutral {
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.tracking-chip {
  margin-top: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #0284c7;
  background: #f0f9ff;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid #bae6fd;
}

.refund-status-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
}

.refund-status--success {
  color: #16a34a;
}

.refund-amount-text {
  color: #0f172a;
  font-weight: 700;
  font-size: 0.85rem;
}

.time-text {
  font-weight: 600;
  color: #0f172a;
}

.date-sub {
  font-size: 0.75rem;
  color: #64748b;
}

/* Action button */
.btn-action-view {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.btn-action-view:hover {
  background: #e63b6f;
  border-color: #e63b6f;
  color: #ffffff;
  box-shadow: 0 4px 10px rgba(230, 59, 111, 0.2);
}

/* Empty state */
.empty-cell {
  padding: 60px 20px !important;
  text-align: center;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon-box {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  margin-bottom: 8px;
}

.empty-title {
  font-weight: 700;
  color: #0f172a;
  font-size: 1rem;
  margin: 0;
}

.empty-desc {
  font-size: 0.85rem;
  color: #64748b;
}

/* Pagination */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.pagination-info {
  font-size: 0.85rem;
  color: #64748b;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-btn {
  padding: 6px 14px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  transition: all 0.15s ease;
}

.page-btn:hover:not(:disabled) {
  border-color: #e63b6f;
  color: #e63b6f;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.current-page-pill {
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
}
</style>
