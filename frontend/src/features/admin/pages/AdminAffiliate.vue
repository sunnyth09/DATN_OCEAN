<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/axios.js';
import Swal from 'sweetalert2';
import AppIcon from '@/components/AppIcon.vue';
import AdminTableSkeleton from '@/components/AdminTableSkeleton.vue';

const activeTab = ref('affiliates'); // 'affiliates', 'conversions' or 'withdrawals'
const affiliates = ref([]);
const conversions = ref([]);
const withdrawals = ref([]);
const isLoading = ref(false);

const paginationAff = ref(null);
const paginationConv = ref(null);
const paginationWith = ref(null);

const formatPrice = (v) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0);
const formatDate = (d) => {
  if (!d) return '—';
  return new Date(d).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const CONV_STATUS_MAP = {
  pending:   { text: 'Chờ duyệt',  tone: 'status-warning' },
  approved:  { text: 'Đã duyệt',   tone: 'status-success' },
  canceled:  { text: 'Đã hủy',     tone: 'status-danger' },
};

const WITHDRAW_STATUS_MAP = {
  pending:   { text: 'Chờ xử lý',  tone: 'status-warning' },
  approved:  { text: 'Đã duyệt',   tone: 'status-info' },
  paid:      { text: 'Đã thanh toán',tone: 'status-success' },
  rejected:  { text: 'Từ chối',    tone: 'status-danger' },
};

const summaryPendingConversions = computed(() => conversions.value.filter(c => c.status === 'pending').length);
const summaryPendingWithdrawals = computed(() => withdrawals.value.filter(w => w.status === 'pending').length);

const fetchAffiliates = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/affiliate/users', { params: { page, per_page: 10 } });
    if (res.data) {
      affiliates.value = res.data.data?.data || [];
      paginationAff.value = {
        current_page: res.data.data?.current_page,
        last_page: res.data.data?.last_page,
        total: res.data.data?.total,
      };
    }
  } catch (e) {
    console.error('Fetch affiliates error', e);
  } finally {
    isLoading.value = false;
  }
};

const fetchConversions = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/affiliate/conversions', { params: { page, per_page: 10 } });
    if (res.data) {
      conversions.value = res.data.data?.data || [];
      paginationConv.value = {
        current_page: res.data.data?.current_page,
        last_page: res.data.data?.last_page,
        total: res.data.data?.total,
      };
    }
  } catch (e) {
    console.error('Fetch conversions error', e);
  } finally {
    isLoading.value = false;
  }
};

const fetchWithdrawals = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/admin/affiliate/withdrawals', { params: { page, per_page: 10 } });
    if (res.data) {
      withdrawals.value = res.data.data?.data || [];
      paginationWith.value = {
        current_page: res.data.data?.current_page,
        last_page: res.data.data?.last_page,
        total: res.data.data?.total,
      };
    }
  } catch (e) {
    console.error('Fetch withdrawals error', e);
  } finally {
    isLoading.value = false;
  }
};

const switchTab = (tab) => {
  activeTab.value = tab;
  if (tab === 'conversions') fetchConversions(1);
  else if (tab === 'withdrawals') fetchWithdrawals(1);
  else fetchAffiliates(1);
};

// Conversions Actions
const approveConversion = async (conv) => {
  const result = await Swal.fire({
    title: 'Xác nhận duyệt hoa hồng?',
    html: `<p>CTV: <strong>${conv.affiliate_user?.name || 'Không rõ'}</strong></p><p>Đơn hàng: <strong>${conv.order?.order_code || conv.order_id}</strong></p><p>Hoa hồng: <strong style="color:#16a34a">${formatPrice(conv.commission_amount)}</strong></p>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Duyệt ngay',
    cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;

  try {
    const res = await api.put(`/admin/affiliate/conversions/${conv.id}/approve`);
    Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: res.data.message || 'Đã duyệt hoa hồng', icon: 'success', showConfirmButton: false, timer: 3000 });
    fetchConversions(paginationConv.value?.current_page || 1);
  } catch (e) {
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
  }
};

const cancelConversion = async (conv) => {
  const result = await Swal.fire({
    title: 'Hủy hoa hồng này?',
    text: 'Hành động này sẽ đánh dấu hoa hồng là đã hủy và không thể khôi phục.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Hủy hoa hồng',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Thoát'
  });
  if (!result.isConfirmed) return;

  try {
    const res = await api.put(`/admin/affiliate/conversions/${conv.id}/cancel`);
    Swal.fire({ toast: true, position: 'top-end', title: 'Đã hủy', text: res.data.message || 'Đã hủy hoa hồng', icon: 'success', showConfirmButton: false, timer: 3000 });
    fetchConversions(paginationConv.value?.current_page || 1);
  } catch (e) {
    Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
  }
};

// Withdrawals Actions
const approveWithdrawal = async (w) => {
  const result = await Swal.fire({
    title: 'Duyệt yêu cầu rút tiền?',
    html: `<p>Duyệt yêu cầu rút <strong style="color:#16a34a">${formatPrice(w.amount)}</strong> của <strong>${w.user?.name}</strong>.</p>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Duyệt',
    cancelButtonText: 'Hủy'
  });
  if (result.isConfirmed) {
    try {
      const res = await api.put(`/admin/affiliate/withdrawals/${w.id}/approve`);
      Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: res.data.message || 'Đã duyệt yêu cầu', icon: 'success', showConfirmButton: false, timer: 3000 });
      fetchWithdrawals(paginationWith.value?.current_page || 1);
    } catch (e) {
      Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
    }
  }
};

const rejectWithdrawal = async (w) => {
  const { value: note } = await Swal.fire({
    title: 'Từ chối rút tiền',
    input: 'text',
    inputLabel: 'Lý do từ chối',
    inputPlaceholder: 'Nhập lý do (tuỳ chọn)...',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Từ chối'
  });
  
  if (note !== undefined) {
    try {
      const res = await api.put(`/admin/affiliate/withdrawals/${w.id}/reject`, { note });
      Swal.fire({ toast: true, position: 'top-end', title: 'Đã từ chối', text: res.data.message || 'Đã từ chối yêu cầu', icon: 'success', showConfirmButton: false, timer: 3000 });
      fetchWithdrawals(paginationWith.value?.current_page || 1);
    } catch (e) {
      Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
    }
  }
};

const markPaidWithdrawal = async (w) => {
  const result = await Swal.fire({
    title: 'Xác nhận đã thanh toán?',
    html: `<p>Bạn xác nhận đã chuyển khoản <strong style="color:#16a34a">${formatPrice(w.amount)}</strong> thành công?</p>`,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Đã thanh toán',
    cancelButtonText: 'Hủy'
  });
  if (result.isConfirmed) {
    try {
      const res = await api.put(`/admin/affiliate/withdrawals/${w.id}/paid`);
      Swal.fire({ toast: true, position: 'top-end', title: 'Thành công', text: res.data.message || 'Đã chuyển trạng thái Đã thanh toán', icon: 'success', showConfirmButton: false, timer: 3000 });
      fetchWithdrawals(paginationWith.value?.current_page || 1);
    } catch (e) {
      Swal.fire({ toast: true, position: 'top-end', title: 'Lỗi', text: e.response?.data?.message || 'Có lỗi xảy ra', icon: 'error', showConfirmButton: false, timer: 3000 });
    }
  }
};

onMounted(() => {
  fetchAffiliates(1);
  // Prefetch for summaries
  fetchConversions(1);
  fetchWithdrawals(1);
});
</script>

<template>
  <div class="admin-affiliate-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Affiliate</h1>
        <p class="page-subtitle">Duyệt đơn hàng giới thiệu và xử lý yêu cầu rút tiền</p>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
      <div class="summary-card">
        <div class="summary-icon summary-icon--warning">
          <AppIcon name="clock" size="24" />
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ summaryPendingConversions }}</span>
          <span class="summary-label">Đơn chờ duyệt</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-icon summary-icon--primary">
          <AppIcon name="credit-card" size="24" />
        </div>
        <div class="summary-body">
          <span class="summary-value">{{ summaryPendingWithdrawals }}</span>
          <span class="summary-label">Yêu cầu rút tiền mới</span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="custom-tabs">
        <button class="tab-btn" :class="{'active': activeTab === 'affiliates'}" @click="switchTab('affiliates')">
            Cộng tác viên (Affiliates)
        </button>
        <button class="tab-btn" :class="{'active': activeTab === 'conversions'}" @click="switchTab('conversions')">
            Đơn hàng giới thiệu (Conversions)
        </button>
        <button class="tab-btn" :class="{'active': activeTab === 'withdrawals'}" @click="switchTab('withdrawals')">
            Yêu cầu rút tiền (Withdrawals)
        </button>
    </div>

    <!-- Loading -->
    <AdminTableSkeleton v-if="isLoading" :columns="7" :rows="5" />

    <!-- Tab: Affiliates -->
    <div v-else-if="activeTab === 'affiliates'" class="table-card">
      <div class="table-responsive">
        <table class="data-table">
        <thead>
          <tr>
            <th>Cộng tác viên</th>
            <th>Mã giới thiệu</th>
            <th>Ngày đăng ký</th>
            <th>Số đơn</th>
            <th>Tổng hoa hồng</th>
            <th>Số dư khả dụng</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="affiliates.length === 0">
            <td colspan="6" class="empty-cell">Không có cộng tác viên nào.</td>
          </tr>
          <tr v-for="a in affiliates" :key="a.user_id">
            <td>
              <div class="cell-stack">
                <strong>{{ a.full_name || a.name }}</strong>
                <span>{{ a.email }}</span>
              </div>
            </td>
            <td><span class="method-badge">{{ a.referral_code }}</span></td>
            <td>{{ formatDate(a.affiliate_registered_at) }}</td>
            <td><span class="strong">{{ a.total_conversions || 0 }}</span></td>
            <td><span class="amount-text">{{ formatPrice(a.total_commission || 0) }}</span></td>
            <td><span class="amount-text" style="color: #d97706">{{ formatPrice(a.wallet?.commission_balance || 0) }}</span></td>
          </tr>
        </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="paginationAff && paginationAff.last_page > 1" class="pagination-controls">
          <button :disabled="paginationAff.current_page === 1" @click="fetchAffiliates(paginationAff.current_page - 1)" class="btn-page">Trước</button>
          <div class="page-numbers">
              <button v-for="page in paginationAff.last_page" :key="page" @click="fetchAffiliates(page)" class="btn-page-number" :class="{'active': paginationAff.current_page === page}">{{ page }}</button>
          </div>
          <button :disabled="paginationAff.current_page === paginationAff.last_page" @click="fetchAffiliates(paginationAff.current_page + 1)" class="btn-page">Sau</button>
      </div>
    </div>

    <!-- Tab: Conversions -->
    <div v-else-if="activeTab === 'conversions'" class="table-card">
      <div class="table-responsive">
        <table class="data-table">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Cộng tác viên</th>
            <th>Tiền đơn hàng</th>
            <th>Hoa hồng</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="conversions.length === 0">
            <td colspan="7" class="empty-cell">Không có đơn hàng giới thiệu nào.</td>
          </tr>
          <tr v-for="c in conversions" :key="c.id">
            <td class="strong">{{ c.order?.order_code || c.order_id }}</td>
            <td>
              <div class="cell-stack">
                <strong>{{ c.affiliate_user?.name }}</strong>
                <span>{{ c.affiliate_user?.email }}</span>
              </div>
            </td>
            <td>{{ formatPrice(c.order_amount) }}</td>
            <td><span class="amount-text">{{ formatPrice(c.commission_amount) }}</span></td>
            <td>
              <span class="status-badge" :class="CONV_STATUS_MAP[c.status]?.tone || 'status-info'">
                {{ CONV_STATUS_MAP[c.status]?.text || c.status }}
              </span>
            </td>
            <td>{{ formatDate(c.created_at) }}</td>
            <td>
              <div v-if="c.status === 'pending'" class="action-group">
                <button class="btn-action btn-action--approve" @click="approveConversion(c)" title="Duyệt">
                  Duyệt
                </button>
                <button class="btn-action btn-action--reject" @click="cancelConversion(c)" title="Hủy">
                  Hủy
                </button>
              </div>
              <span v-else class="processed-text">—</span>
            </td>
          </tr>
        </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="paginationConv && paginationConv.last_page > 1" class="pagination-controls">
          <button :disabled="paginationConv.current_page === 1" @click="fetchConversions(paginationConv.current_page - 1)" class="btn-page">Trước</button>
          <div class="page-numbers">
              <button v-for="page in paginationConv.last_page" :key="page" @click="fetchConversions(page)" class="btn-page-number" :class="{'active': paginationConv.current_page === page}">{{ page }}</button>
          </div>
          <button :disabled="paginationConv.current_page === paginationConv.last_page" @click="fetchConversions(paginationConv.current_page + 1)" class="btn-page">Sau</button>
      </div>
    </div>

    <!-- Tab: Withdrawals -->
    <div v-else-if="activeTab === 'withdrawals'" class="table-card">
      <div class="table-responsive">
        <table class="data-table">
        <thead>
          <tr>
            <th>Cộng tác viên</th>
            <th>Số tiền rút</th>
            <th>Ngân hàng</th>
            <th>Trạng thái</th>
            <th>Ngày yêu cầu</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="withdrawals.length === 0">
            <td colspan="6" class="empty-cell">Không có yêu cầu rút tiền nào.</td>
          </tr>
          <tr v-for="w in withdrawals" :key="w.id">
            <td>
              <div class="cell-stack">
                <strong>{{ w.user?.name }}</strong>
                <span>{{ w.user?.email }}</span>
              </div>
            </td>
            <td><span class="amount-text">{{ formatPrice(w.amount) }}</span></td>
            <td>
              <div class="cell-stack">
                <strong>{{ w.bank_name }}</strong>
                <span>{{ w.account_number }} - {{ w.account_name }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="WITHDRAW_STATUS_MAP[w.status]?.tone || 'status-info'">
                {{ WITHDRAW_STATUS_MAP[w.status]?.text || w.status }}
              </span>
            </td>
            <td>{{ formatDate(w.created_at) }}</td>
            <td>
              <div v-if="w.status === 'pending'" class="action-group">
                <button class="btn-action btn-action--approve" @click="approveWithdrawal(w)">Duyệt</button>
                <button class="btn-action btn-action--reject" @click="rejectWithdrawal(w)">Từ chối</button>
              </div>
              <div v-else-if="w.status === 'approved'" class="action-group">
                <button class="btn-action btn-action--approve" @click="markPaidWithdrawal(w)">Đã CK xong</button>
              </div>
              <span v-else class="processed-text">—</span>
            </td>
          </tr>
        </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="paginationWith && paginationWith.last_page > 1" class="pagination-controls">
          <button :disabled="paginationWith.current_page === 1" @click="fetchWithdrawals(paginationWith.current_page - 1)" class="btn-page">Trước</button>
          <div class="page-numbers">
              <button v-for="page in paginationWith.last_page" :key="page" @click="fetchWithdrawals(page)" class="btn-page-number" :class="{'active': paginationWith.current_page === page}">{{ page }}</button>
          </div>
          <button :disabled="paginationWith.current_page === paginationWith.last_page" @click="fetchWithdrawals(paginationWith.current_page + 1)" class="btn-page">Sau</button>
      </div>
    </div>

  </div>
</template>

<style scoped>
.admin-affiliate-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: var(--font-inter);
}

.page-header {
  margin-bottom: 8px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-main);
  margin: 0;
}

.page-subtitle {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-top: 4px;
  font-weight: 500;
  margin-bottom: 0;
}

.summary-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
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

/* Tabs */
.custom-tabs {
  display: flex;
  gap: 12px;
  border-bottom: 2px solid var(--border-color);
  margin-bottom: 10px;
}

.tab-btn {
  padding: 12px 20px;
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--text-muted);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--text-main);
}

.tab-btn.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
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
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  border-bottom: 1px solid var(--border-color);
  background: var(--ocean-deepest);
  text-align: left;
}

.data-table td {
  padding: 16px 24px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  background: var(--card-bg);
  color: var(--text-main);
}

.data-table tbody tr:hover td {
  background: var(--hover-bg);
}

.strong {
  font-weight: 700;
  color: var(--text-main);
  font-family: monospace;
}

.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cell-stack span {
  color: var(--text-muted);
  font-size: 0.85rem;
}

.amount-text {
  font-weight: 700;
  color: #16a34a;
}

.status-badge {
  display: inline-flex;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
}

.status-badge.status-warning { color: #d97706; background: #fef3c7; }
.status-badge.status-success { color: #16a34a; background: #dcfce7; }
.status-badge.status-danger  { color: #dc2626; background: #fee2e2; }
.status-badge.status-info    { color: #475569; background: var(--surface-container); }

.empty-cell {
  text-align: center;
  color: var(--text-muted);
  padding: 50px 20px;
}

/* Actions */
.action-group {
  display: flex;
  gap: 8px;
}

.btn-action {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
}

.btn-action--approve {
  color: #16a34a;
  background: #dcfce7;
}

.btn-action--reject {
  color: #dc2626;
  background: #fee2e2;
}

.processed-text {
  font-size: 0.85rem;
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
@keyframes spin { to { transform: rotate(360deg); } }

/* Pagination Controls */
.pagination-controls {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid var(--border-color);
}
.btn-page {
    display: flex; align-items: center; gap: 6px; padding: 6px 12px;
    border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.btn-page:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
.page-numbers { display: flex; gap: 6px; }
.btn-page-number {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.btn-page-number.active { background: var(--primary); color: white; border-color: var(--primary); }
</style>
