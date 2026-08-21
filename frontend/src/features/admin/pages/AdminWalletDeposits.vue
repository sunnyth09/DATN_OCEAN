<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';
import api from '@/axios.js';
import Swal from 'sweetalert2';

const deposits = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const pagination = ref(null);

const formatPrice = (v) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0);
const formatDate = (d) => {
  if (!d) return '—';
  return new Date(d).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const STATUS_MAP = {
  pending:   { text: 'Chờ thanh toán',  tone: 'status-warning' },
  completed: { text: 'Thành công',   tone: 'status-success' },
  failed:    { text: 'Thất bại',     tone: 'status-danger' },
  expired:   { text: 'Hết hạn',     tone: 'status-info' },
};

const METHOD_MAP = {
  bank_transfer: 'Chuyển khoản',
  vnpay: 'VNPay',
};

const summaryTotal = computed(() => deposits.value.reduce((s, d) => s + Number(d.amount), 0));

const fetchDeposits = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/wallets/deposits/pending', {
      params: { status: 'completed', per_page: 10, page, search: searchQuery.value },
    });
    if (res.data?.status === 'success') {
      deposits.value = res.data.data.data || [];
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total,
      };
    }
  } catch (e) {
    console.error('Fetch deposits error', e);
  } finally {
    isLoading.value = false;
  }
};


onMounted(() => fetchDeposits());
</script>

<template>
  <div class="admin-deposits-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-info">
        <h1 class="page-title">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/>
            <path d="M2 10h2"/>
            <path d="M20 10h2"/>
          </svg>
          Lịch sử Nạp tiền
        </h1>
        <p class="page-subtitle">Theo dõi lịch sử giao dịch nạp tiền vào ví của khách hàng.</p>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row" style="grid-template-columns: 1fr;">
      <div class="summary-card">
        <div class="summary-icon summary-icon--primary">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ formatPrice(summaryTotal) }}</span>
          <span class="summary-label">Tổng tiền nạp</span>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar" style="justify-content: space-between;">
      <div class="search-box">
        <input type="text" v-model="searchQuery" placeholder="Tìm mã nạp, tên hoặc email..." class="search-input" @keyup.enter="fetchDeposits(1)">
        <button class="btn-search" @click="fetchDeposits(1)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </button>
      </div>
      <button class="btn-reload" @click="fetchDeposits(1)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Tải lại
      </button>
    </div>

    <!-- Loading -->
    <AdminTableSkeleton v-if="isLoading" :columns="6" :rows="5" />

    <!-- Table -->
    <div v-else class="table-card">
      <div class="table-responsive">
        <table class="data-table">
        <thead>
          <tr>
            <th>Mã nạp</th>
            <th>Khách hàng</th>
            <th>Số tiền</th>
            <th>Phương thức</th>
            <th>Thời gian</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="deposits.length === 0">
            <td colspan="5" class="empty-cell">Không có giao dịch nạp tiền nào.</td>
          </tr>
          <tr v-for="d in deposits" :key="d.id">
            <td class="strong">{{ d.deposit_code }}</td>
            <td>
              <div class="cell-stack">
                <strong>{{ d.full_name }}</strong>
                <span>{{ d.email }}</span>
              </div>
            </td>
            <td>
              <span class="amount-text">{{ formatPrice(d.amount) }}</span>
            </td>
            <td>
              <span class="method-badge">{{ METHOD_MAP[d.method] || d.method }}</span>
            </td>
            <td>{{ formatDate(d.created_at) }}</td>
          </tr>
        </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination-controls">
          <button :disabled="pagination.current_page === 1" @click="fetchDeposits(pagination.current_page - 1)" class="btn-page">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              Trước
          </button>
          <div class="page-numbers">
              <button 
                  v-for="page in pagination.last_page" 
                  :key="page" 
                  @click="fetchDeposits(page)" 
                  class="btn-page-number" 
                  :class="{'active': pagination.current_page === page}"
              >
                  {{ page }}
              </button>
          </div>
          <button :disabled="pagination.current_page === pagination.last_page" @click="fetchDeposits(pagination.current_page + 1)" class="btn-page">
              Sau
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-deposits-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: var(--font-inter);
}

/* Header */
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

/* Summary Cards */
.summary-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: var(--shadow-card);
  transition: transform 0.2s, box-shadow 0.2s;
}

.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.summary-icon--warning { background: #fef3c7; color: #d97706; }
.summary-icon--success { background: #dcfce7; color: #16a34a; }
.summary-icon--primary { background: #fce7f3; color: var(--primary); }

.summary-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.summary-value {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-main);
}

.summary-label {
  font-size: 0.82rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  background: var(--card-bg);
  padding: 14px 20px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-card);
}

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
  min-width: 180px;
}

.status-select:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.btn-reload {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  color: var(--text-main);
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-reload:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(230, 59, 111, 0.04);
}

/* Search Box */
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface-container-low);
  border: 1.5px solid var(--border-color);
  border-radius: 8px;
  padding: 4px 8px 4px 16px;
  min-width: 350px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-family: inherit;
  font-size: 0.9rem;
  color: var(--text-main);
  width: 100%;
}

.btn-search {
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn-search:hover {
  background: rgba(230, 59, 111, 0.1);
  color: var(--primary);
}

.btn-reload:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(230, 59, 111, 0.04);
}

/* Table */
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

.strong {
  font-weight: 800;
  color: var(--primary);
  font-size: 0.9rem;
  font-family: 'Roboto Mono', monospace;
}

.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--text-main);
}

.cell-stack span {
  color: var(--text-muted);
  font-size: 0.85rem;
}

.amount-text {
  font-weight: 800;
  color: #16a34a;
  font-size: 0.95rem;
}

.method-badge {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  background: var(--surface-container-low);
  color: var(--text-main);
  border: 1px solid var(--border-color);
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

.status-badge.status-warning { color: #d97706; background: #fef3c7; border: 1px solid #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce7; border: 1px solid #bbf7d0; }
.status-badge.status-danger  { color: #dc2626; background: #fee2e2; border: 1px solid #fecaca; }
.status-badge.status-info    { color: #475569; background: var(--surface-container); border: 1px solid #cbd5e1; }

.empty-cell {
  text-align: center;
  color: var(--text-muted);
  padding: 50px 20px;
}

/* Action buttons */
.action-group {
  display: flex;
  gap: 8px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  border: 1.5px solid;
  transition: all 0.2s;
  font-family: inherit;
}

.btn-action--approve {
  color: #16a34a;
  background: #dcfce7;
  border-color: #bbf7d0;
}

.btn-action--approve:hover {
  background: #16a34a;
  color: #fff;
  border-color: #16a34a;
}

.btn-action--reject {
  color: #dc2626;
  background: #fee2e2;
  border-color: #fecaca;
  padding: 7px 10px;
}

.btn-action--reject:hover {
  background: #dc2626;
  color: #fff;
  border-color: #dc2626;
}

.processed-text {
  font-size: 0.82rem;
  color: var(--text-muted);
}

/* Loading */
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

/* Pagination Controls */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
}
.btn-page:hover:not(:disabled) { background: var(--hover-bg, #f4f6f8); border-color: var(--primary, #e63b6f); color: var(--primary, #e63b6f); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest, #fff); color: var(--text-main, #333);
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.btn-page-number:hover:not(.active) { background: var(--hover-bg, #f4f6f8); }
.btn-page-number.active {
    background: var(--primary, #e63b6f); color: white; border-color: var(--primary, #e63b6f);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .summary-row { grid-template-columns: 1fr; }
}
</style>
