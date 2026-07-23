<script setup>
import { onMounted, ref } from 'vue';
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
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Yêu cầu hoàn hàng
        </h1>
        <p class="page-subtitle">Theo dõi, duyệt, từ chối và xác nhận hoàn tiền cho các đơn hàng hoàn trả.</p>
      </div>
    </div>

    <div class="toolbar">
      <input
        v-model="filters.search"
        type="text"
        class="search-input"
        placeholder="Tìm theo mã đơn, khách hàng, lý do..."
        @keyup.enter="fetchData(1)"
      />
      <select v-model="filters.status" class="status-select" @change="fetchData(1)">
        <option v-for="option in RETURN_REQUEST_ADMIN_STATUS_OPTIONS" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
    </div>

    <div v-if="adminLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải yêu cầu hoàn hàng...</p>
    </div>

    <div v-else class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Lý do</th>
            <th>Trạng thái</th>
            <th>Hoàn tiền</th>
            <th>Gửi lúc</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="adminRequests.length === 0">
            <td colspan="7" class="empty-cell">Không có yêu cầu hoàn hàng phù hợp.</td>
          </tr>
          <tr v-for="item in adminRequests" :key="item.id">
            <td class="strong">#{{ item.order?.order_code || item.order_id }}</td>
            <td>
              <div class="cell-stack">
                <strong>{{ item.user?.full_name || item.order?.recipient_name || 'Khách hàng' }}</strong>
                <span>{{ item.user?.email || item.order?.recipient_phone || '—' }}</span>
              </div>
            </td>
            <td>{{ item.reason }}</td>
            <td>
              <span class="status-badge" :class="getReturnRequestStatusTone(item.status)">
                {{ getReturnRequestStatusLabel(item.status) }}
              </span>
            </td>
            <td>
              <div class="cell-stack">
                <span>{{ getReturnRefundStatusLabel(item.refund_status) }}</span>
                <strong v-if="Number(item.refund_amount || 0) > 0">{{ formatPrice(item.refund_amount) }}</strong>
              </div>
            </td>
            <td>{{ formatDate(item.requested_at || item.created_at) }}</td>
            <td>
              <router-link :to="{ name: 'admin-return-request-detail', params: { id: item.id } }" class="view-link">
                Xem chi tiết
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
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
  gap: 20px;
  font-family: var(--font-inter);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.page-subtitle {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-top: 4px;
  font-weight: 500;
  margin-bottom: 0;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 14px;
  background: var(--card-bg);
  padding: 18px 20px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
}

.search-input,
.status-select {
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  padding: 10px 16px;
  min-height: 44px;
  color: var(--text-main);
  font-family: inherit;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.search-input:focus,
.status-select:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.search-input {
  flex: 1;
  min-width: 260px;
}

.table-card {
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  overflow: hidden;
  box-shadow: var(--shadow-card);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  padding: 14px 24px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
  border-bottom: 1px solid var(--border-color);
  background: var(--ocean-deepest);
  text-align: left;
}

.data-table td {
  padding: 16px 24px;
  border-bottom: 1px solid var(--border-color);
  transition: background 0.15s;
  vertical-align: middle;
  background: var(--card-bg);
  color: var(--text-main);
}

.data-table tbody tr:hover td {
  background: var(--hover-bg);
}

.strong { font-weight: 800; color: var(--primary); font-size: 0.95rem; }
.cell-stack { display: flex; flex-direction: column; gap: 4px; color: var(--text-main); }
.cell-stack span { color: var(--text-muted); font-size: 0.85rem; }
.empty-cell { text-align: center; color: var(--text-muted); padding: 50px 20px; }
.view-link { color: var(--primary); font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--surface-container-low); transition: all 0.2s; font-size: 0; }
.view-link::before { content: "→"; font-size: 1.1rem; }
.view-link:hover { border-color: var(--primary); background: rgba(230, 59, 111, 0.05); color: var(--primary); }

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

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
}

.page-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: var(--card-bg);
  cursor: pointer;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
