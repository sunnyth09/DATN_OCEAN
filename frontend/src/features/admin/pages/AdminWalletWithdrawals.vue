<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/axios.js';
import Swal from 'sweetalert2';

const withdrawals = ref([]);
const isLoading = ref(false);
const statusFilter = ref('processing');
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
  processing: { text: 'Chờ xử lý',  tone: 'status-warning' },
  completed:  { text: 'Đã chuyển',   tone: 'status-success' },
  failed:     { text: 'Từ chối',     tone: 'status-danger' },
};

const summaryPending = computed(() => withdrawals.value.filter(d => d.status === 'processing').length);
const summaryCompleted = computed(() => withdrawals.value.filter(d => d.status === 'completed').length);
const summaryTotal = computed(() => withdrawals.value.filter(d => d.status === 'completed').reduce((s, d) => s + Number(d.amount), 0));

const fetchWithdrawals = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/wallets/withdrawals/pending', {
      params: { status: statusFilter.value, per_page: 5, page },
    });
    if (res.data?.status === 'success') {
      withdrawals.value = res.data.data.data || [];
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total,
      };
    }
  } catch (e) {
    console.error('Fetch withdrawals error', e);
  } finally {
    isLoading.value = false;
  }
};

const completeWithdrawal = async (withdrawal) => {
  const result = await Swal.fire({
    title: 'Xác nhận đã chuyển khoản?',
    html: `
      <div style="text-align: left; font-size: 0.9rem;">
        <p>Khách hàng: <strong>${withdrawal.full_name}</strong></p>
        <p>Số tiền thực nhận: <strong style="color: #16a34a; font-size: 1.1rem;">${formatPrice(withdrawal.amount)}</strong></p>
        <p>Ngân hàng: <strong>${withdrawal.bank_name}</strong></p>
        <p>Số tài khoản: <strong>${withdrawal.bank_account_number}</strong></p>
        <p>Chủ thẻ: <strong>${withdrawal.bank_account_name}</strong></p>
      </div>
      <p style="margin-top: 15px; color: #dc2626; font-weight: bold; font-size: 0.85rem;">⚠️ Bạn chỉ thao tác nút này SAU KHI đã chuyển khoản thành công ngoài thực tế!</p>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Đã chuyển khoản xong',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#16a34a',
  });
  if (!result.isConfirmed) return;

  try {
    const res = await api.put(`/admin/wallets/withdrawals/${withdrawal.id}/complete`);
    await Swal.fire({ title: 'Thành công!', text: res.data?.message || 'Đã duyệt', icon: 'success', timer: 2000 });
    fetchWithdrawals();
  } catch (e) {
    Swal.fire({ title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error' });
  }
};

const rejectWithdrawal = async (withdrawal) => {
  const { value: note, isConfirmed } = await Swal.fire({
    title: 'Từ chối rút tiền?',
    html: `
      <p>Số tiền <strong style="color: #dc2626;">${formatPrice(withdrawal.total_deducted)}</strong> sẽ được hoàn lại vào ví của khách hàng.</p>
    `,
    input: 'text',
    inputLabel: 'Lý do từ chối',
    inputPlaceholder: 'Nhập lý do từ chối...',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Từ chối & Hoàn tiền',
    cancelButtonText: 'Hủy',
    confirmButtonColor: '#dc2626',
    inputValidator: (value) => {
      if (!value) {
        return 'Vui lòng nhập lý do từ chối!'
      }
    }
  });

  if (!isConfirmed) return;

  try {
    const res = await api.put(`/admin/wallets/withdrawals/${withdrawal.id}/reject`, { note });
    await Swal.fire({ title: 'Đã từ chối', text: res.data?.message, icon: 'success', timer: 2000 });
    fetchWithdrawals();
  } catch (e) {
    Swal.fire({ title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error' });
  }
};

onMounted(() => fetchWithdrawals());
</script>

<template>
  <div class="admin-withdrawals-page">
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
          Quản lý Yêu cầu Rút tiền
        </h1>
        <p class="page-subtitle">Quản lý và duyệt các yêu cầu rút tiền từ ví của khách hàng về tài khoản ngân hàng.</p>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
      <div class="summary-card">
        <div class="summary-icon summary-icon--warning">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ summaryPending }}</span>
          <span class="summary-label">Chờ xử lý</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon summary-icon--success">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ summaryCompleted }}</span>
          <span class="summary-label">Đã chuyển</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon summary-icon--primary">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ formatPrice(summaryTotal) }}</span>
          <span class="summary-label">Tổng tiền đã chuyển</span>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <select v-model="statusFilter" class="status-select" @change="fetchWithdrawals(1)">
        <option value="processing">⏳ Chờ xử lý</option>
        <option value="completed">✅ Đã chuyển khoản</option>
        <option value="failed">❌ Đã từ chối</option>
        <option value="all">📋 Tất cả</option>
      </select>
      <button class="btn-reload" @click="fetchWithdrawals(1)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Tải lại
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải danh sách rút tiền...</p>
    </div>

    <!-- Table -->
    <div v-else class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Mã rút</th>
            <th>Khách hàng</th>
            <th>Ngân hàng nhận</th>
            <th>Số tiền</th>
            <th>Trạng thái</th>
            <th>Thời gian</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="withdrawals.length === 0">
            <td colspan="7" class="empty-cell">Không có yêu cầu rút tiền nào.</td>
          </tr>
          <tr v-for="d in withdrawals" :key="d.id">
            <td class="strong">{{ d.withdrawal_code }}</td>
            <td>
              <div class="cell-stack">
                <strong>{{ d.full_name }}</strong>
                <span>{{ d.email }}</span>
                <span>{{ d.phone }}</span>
              </div>
            </td>
            <td>
              <div class="cell-stack">
                <span class="method-badge">{{ d.bank_name }}</span>
                <strong>{{ d.bank_account_number }}</strong>
                <span>{{ d.bank_account_name }}</span>
              </div>
            </td>
            <td>
              <div class="cell-stack">
                <span class="amount-text" title="Thực nhận">{{ formatPrice(d.amount) }}</span>
                <span style="font-size: 0.75rem" title="Phí rút">-{{ formatPrice(d.fee) }}</span>
                <span style="font-size: 0.75rem; text-decoration: line-through; color: #94a3b8" title="Trừ ví">{{ formatPrice(d.total_deducted) }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="STATUS_MAP[d.status]?.tone || 'status-info'">
                {{ STATUS_MAP[d.status]?.text || d.status }}
              </span>
              <div v-if="d.status === 'failed' && d.note" style="font-size: 0.75rem; margin-top: 4px; color: #ef4444; max-width: 150px; line-height: 1.2;">
                Lý do: {{ d.note }}
              </div>
            </td>
            <td>{{ formatDate(d.created_at) }}</td>
            <td>
              <div v-if="d.status === 'processing'" class="action-group">
                <button class="btn-action btn-action--approve" @click="completeWithdrawal(d)" title="Xác nhận đã chuyển khoản">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Đã chuyển
                </button>
                <button class="btn-action btn-action--reject" @click="rejectWithdrawal(d)" title="Từ chối">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
              <span v-else class="processed-text">{{ formatDate(d.completed_at) }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination-controls">
          <button :disabled="pagination.current_page === 1" @click="fetchWithdrawals(pagination.current_page - 1)" class="btn-page">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
              Trước
          </button>
          <div class="page-numbers">
              <button 
                  v-for="page in pagination.last_page" 
                  :key="page" 
                  @click="fetchWithdrawals(page)" 
                  class="btn-page-number" 
                  :class="{'active': pagination.current_page === page}"
              >
                  {{ page }}
              </button>
          </div>
          <button :disabled="pagination.current_page === pagination.last_page" @click="fetchWithdrawals(pagination.current_page + 1)" class="btn-page">
              Sau
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
          </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-withdrawals-page {
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
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  background: var(--surface-container-low);
  color: var(--text-main);
  border: 1px solid var(--border-color);
  width: fit-content;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
  width: fit-content;
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
