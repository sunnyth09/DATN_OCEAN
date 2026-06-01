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
    <div class="page-header">
      <div>
        <h1>Yêu cầu hoàn hàng</h1>
        <p>Theo dõi, duyệt, từ chối và xác nhận hoàn tiền cho các đơn hàng hoàn trả.</p>
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
}

.page-header h1 {
  margin: 0;
  font-size: 1.55rem;
  color: var(--text-main);
}

.page-header p {
  margin: 8px 0 0;
  color: var(--text-muted);
}

.toolbar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.search-input,
.status-select {
  background: #fff;
  border: 1px solid #dbe2ea;
  border-radius: 10px;
  padding: 10px 14px;
  min-height: 44px;
}

.search-input {
  flex: 1;
  min-width: 260px;
}

.table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
  vertical-align: top;
}

.data-table th {
  background: #f8fafc;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.strong { font-weight: 800; color: #0f172a; }
.cell-stack { display: flex; flex-direction: column; gap: 4px; color: #475569; }
.empty-cell { text-align: center; color: #64748b; padding: 40px 20px; }
.view-link { color: #E63B6F; font-weight: 700; text-decoration: none; }

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
}

.status-badge.status-info { color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border: 1px solid #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border: 1px solid #fecaca; }

.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: #64748b;
}

.spinner {
  width: 38px;
  height: 38px;
  border: 3px solid #f1f5f9;
  border-top-color: #E63B6F;
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
  background: #fff;
  cursor: pointer;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
