<script setup>
import { onMounted, ref } from 'vue';
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
    <div class="page-header animate-in">
      <div class="header-info">
        <h1 class="page-title">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Yêu cầu hoàn hàng
        </h1>
        <p class="page-subtitle">Theo dõi, duyệt, từ chối và xác nhận hoàn tiền cho các đơn hàng hoàn trả.</p>
      </div>
      <div v-if="adminPagination?.total !== undefined" class="header-badge">
        <span>{{ adminPagination.total }} yêu cầu</span>
      </div>
    </div>

    <div class="toolbar">
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input
          v-model="filters.search"
          type="text"
          class="search-input"
          placeholder="Tìm theo mã đơn, khách hàng, lý do..."
          @keyup.enter="fetchData(1)"
        />
      </div>
      <select v-model="filters.status" class="status-select" @change="fetchData(1)">
        <option v-for="option in RETURN_REQUEST_ADMIN_STATUS_OPTIONS" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
    </div>

    <AdminTableSkeleton v-if="adminLoading" :columns="8" :rows="5" />

    <div v-else class="table-card">
      <div class="table-scroll-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-code">Mã yêu cầu</th>
              <th class="col-user">Khách hàng</th>
              <th class="col-reason">Lý do</th>
              <th class="col-items text-center">Sản phẩm</th>
              <th class="col-status text-center">Trạng thái</th>
              <th class="col-refund">Hoàn tiền</th>
              <th class="col-date">Gửi lúc</th>
              <th class="col-action text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="adminRequests.length === 0">
              <td colspan="8" class="empty-cell">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="M7 15h0M2 9.5h20"></path>
                  </svg>
                  <p>Không có yêu cầu hoàn hàng phù hợp</p>
                </div>
              </td>
            </tr>
            <tr v-for="item in adminRequests" :key="item.id" class="return-row">
              <td>
                <div class="cell-stack">
                  <strong class="strong-code">{{ item.return_code || `#${item.id}` }}</strong>
                  <span class="order-sub-code">Đơn #{{ item.order?.order_code || item.order_id }}</span>
                </div>
              </td>
              <td>
                <div class="cell-stack">
                  <strong class="user-name">{{ item.user?.full_name || item.order?.recipient_name || 'Khách hàng' }}</strong>
                  <span class="user-sub">{{ item.user?.email || item.order?.recipient_phone || '—' }}</span>
                </div>
              </td>
              <td class="reason-cell">
                <div class="reason-pill" :class="`reason-pill--${getReasonTone(item.reason)}`" :title="item.reason">
                  <span class="reason-icon">
                    <svg v-if="getReasonTone(item.reason) === 'danger'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'warning'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                      <line x1="12" y1="9" x2="12" y2="13"></line>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'info'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                      <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    <svg v-else-if="getReasonTone(item.reason) === 'purple'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                  </span>
                  <span class="reason-text">{{ item.reason || 'Chưa ghi lý do' }}</span>
                </div>
              </td>
              <td class="text-center">
                <div class="cell-stack text-center">
                  <strong>{{ item.items?.length || 0 }} dòng</strong>
                  <span class="sub-qty">{{ (item.items || []).reduce((sum, row) => sum + Number(row.requested_quantity || 0), 0) }} SP</span>
                </div>
              </td>
              <td class="text-center">
                <span class="status-badge" :class="getReturnRequestStatusTone(item.status)">
                  {{ getReturnRequestStatusLabel(item.status) }}
                </span>
              </td>
              <td>
                <div class="cell-stack">
                  <span class="refund-status-label">{{ getReturnRefundStatusLabel(item.refund_status) }}</span>
                  <strong v-if="Number(item.refund_amount || 0) > 0" class="refund-amount-text">{{ formatPrice(item.refund_amount) }}</strong>
                </div>
              </td>
              <td class="date-cell">
                <div class="cell-stack">
                  <span class="time-text">{{ formatDateTime(item.requested_at || item.created_at).time }}</span>
                  <span class="date-sub">{{ formatDateTime(item.requested_at || item.created_at).date }}</span>
                </div>
              </td>
              <td class="text-right">
                <router-link :to="{ name: 'admin-return-request-detail', params: { id: item.id } }" class="btn-action-view" title="Xem chi tiết">
                  <span>Xem</span>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="adminPagination && adminPagination.last_page > 1" class="pagination">
      <button class="page-btn" :disabled="adminPagination.current_page === 1" @click="fetchData(adminPagination.current_page - 1)">
        «
      </button>
      <span>Trang {{ adminPagination.current_page }} / {{ adminPagination.last_page }}</span>
      <button class="page-btn" :disabled="adminPagination.current_page === adminPagination.last_page" @click="fetchData(adminPagination.current_page + 1)">
        »
      </button>
    </div>
  </div>
</template>

<style scoped>
.admin-returns-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  font-family: var(--font-inter);
  padding: 20px;
  box-sizing: border-box;
  max-width: 100%;
  width: 100%;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.page-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.page-subtitle {
  font-size: 0.88rem;
  color: var(--text-muted);
  margin-top: 4px;
  font-weight: 500;
  margin-bottom: 0;
}

.header-badge {
  background: var(--primary, #e63b6f);
  color: #fff;
  padding: 5px 14px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.82rem;
  white-space: nowrap;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  flex-wrap: wrap;
  background: var(--card-bg);
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 240px;
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-input {
  width: 100%;
  box-sizing: border-box;
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  padding: 8px 14px 8px 36px;
  min-height: 38px;
  color: var(--text-main);
  font-family: inherit;
  font-size: 0.88rem;
  transition: all 0.2s;
}

.status-select {
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  padding: 8px 12px;
  min-height: 38px;
  color: var(--text-main);
  font-family: inherit;
  font-size: 0.88rem;
  transition: all 0.2s;
  cursor: pointer;
}

.search-input:focus,
.status-select:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.table-card {
  background: var(--card-bg);
  border-radius: 10px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  width: 100%;
  box-sizing: border-box;
}

.table-scroll-container {
  overflow-x: auto;
  width: 100%;
  -webkit-overflow-scrolling: touch;
}

.table-scroll-container::-webkit-scrollbar {
  height: 6px;
}
.table-scroll-container::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 3px;
}
.table-scroll-container::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
.table-scroll-container::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

.data-table th {
  padding: 12px 14px;
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border-color);
  background: var(--surface-container-low, #f8fafc);
  text-align: left;
  white-space: nowrap;
}

.data-table th.col-code { min-width: 130px; }
.data-table th.col-user { min-width: 140px; }
.data-table th.col-reason { min-width: 150px; }
.data-table th.col-items { min-width: 80px; }
.data-table th.col-status { min-width: 100px; }
.data-table th.col-refund { min-width: 110px; }
.data-table th.col-date { min-width: 95px; }
.data-table th.col-action { min-width: 65px; }

.data-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-color);
  transition: background 0.15s;
  vertical-align: middle;
  background: var(--card-bg);
  color: var(--text-main);
  font-size: 0.85rem;
}

.return-row:hover td {
  background: var(--hover-bg, #f8fafc);
}

.strong-code { font-weight: 700; color: var(--primary); font-size: 0.86rem; }
.order-sub-code { color: var(--text-muted); font-size: 0.78rem; font-weight: 500; }

.user-name { font-weight: 600; font-size: 0.85rem; }
.user-sub { color: var(--text-muted); font-size: 0.78rem; }

.cell-stack { display: flex; flex-direction: column; gap: 2px; color: var(--text-main); }
.cell-stack.text-center { align-items: center; }

/* ─── Reason Pill Styling ───────────────────────────────────────────────────────── */
.reason-cell {
  min-width: 140px;
}

.reason-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.35;
  border: 1px solid transparent;
  transition: all 0.2s ease;
  white-space: nowrap;
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

.reason-text {
  flex: 1;
}

.sub-qty { font-size: 0.76rem; color: var(--text-muted); }
.refund-status-label { font-size: 0.78rem; color: var(--text-muted); }
.refund-amount-text { font-size: 0.85rem; color: #059669; }

.date-cell .time-text { font-weight: 600; font-size: 0.82rem; color: var(--text-main); }
.date-cell .date-sub { font-size: 0.76rem; color: var(--text-muted); }

.text-center { text-align: center !important; }
.text-right { text-align: right !important; }

/* ─── Action Link / Button ──────────────────────────────────────────────────────── */
.btn-action-view {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  font-size: 0.78rem;
  font-weight: 600;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-action-view:hover {
  background: var(--primary, #e63b6f);
  color: #fff;
  border-color: var(--primary, #e63b6f);
  transform: translateX(1px);
}

/* ─── Status Badges ─────────────────────────────────────────────────────────────── */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 16px;
  font-size: 0.76rem;
  font-weight: 600;
  white-space: nowrap;
}

.status-badge.status-info { color: #475569; background: #f1f5f9; border: 1px solid #cbd5e1; }
.status-badge.status-warning { color: #b45309; background: #fef3c7; border: 1px solid #fde68a; }
.status-badge.status-success { color: #065f46; background: #d1fae5; border: 1px solid #a7f3d0; }
.status-badge.status-danger { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }

.empty-cell { text-align: center; padding: 40px 20px; }
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--text-muted);
}
.empty-state p { margin: 0; font-size: 0.88rem; }

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  margin-top: 6px;
}

.page-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: var(--card-bg);
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.page-btn:hover:not(:disabled) {
  background: var(--primary, #e63b6f);
  color: #fff;
  border-color: var(--primary, #e63b6f);
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
