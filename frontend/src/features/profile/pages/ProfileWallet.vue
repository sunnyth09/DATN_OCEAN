<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { walletService } from '@/services/walletService';
import { useToast } from '@/composables/useToast';
import Swal from 'sweetalert2';

const { showToast } = useToast();

const copyToClipboard = async (text) => {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    showToast('Đã sao chép thành công', 'success');
  } catch (err) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    showToast('Đã sao chép thành công', 'success');
  }
};

const preventInvalidNumber = (e) => {
  if (['e', 'E', '+', '-', '.'].includes(e.key)) {
    e.preventDefault();
  }
};

// State
const loading = ref(true);
const summary = ref(null);
const history = ref([]);
const historyPagination = ref(null);
const historyPage = ref(1);
const historyFilter = ref('all'); // all, deposit, commission, order_discount, refund
const loadingHistory = ref(false);

// Deposit modal
const showDepositModal = ref(false);
const depositAmount = ref('');
const depositMethod = ref('bank_transfer');
const depositLoading = ref(false);
const depositResult = ref(null); // { qr_url, redirect_url, ... }

// Withdraw modal
const showWithdrawModal = ref(false);
const withdrawAmount = ref('');
const selectedBankId = ref(null);
const withdrawLoading = ref(false);
const withdrawResult = ref(null);

const cleanText = (val) => {
  if (!val) return '';
  return String(val).replace(/<[^>]*>/g, '').trim();
};

// Bank accounts
const bankAccounts = ref([]);
const showBankModal = ref(false);
const bankForm = ref({ bank_name: '', bank_short_name: '', bank_bin: '', account_name: '', account_number: '' });
const bankFormLoading = ref(false);
const editingBankId = ref(null);

const formatPrice = (v) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0);

const totalBalance = computed(() => (summary.value?.deposit_balance || 0) + (summary.value?.commission_balance || 0));

// Fetch summary
const fetchSummary = async () => {
  try {
    const res = await walletService.getSummary();
    if (res.data?.status === 'success') {
      summary.value = res.data.data;
    }
  } catch (e) {
    console.error('Wallet summary error', e);
  }
};

// Fetch history
const fetchHistory = async (page = 1) => {
  loadingHistory.value = true;
  try {
    const params = { page, per_page: 15 };
    if (historyFilter.value !== 'all') params.type = historyFilter.value;

    const res = await walletService.getHistory(params);
    if (res.data?.status === 'success') {
      history.value = res.data.data.data || [];
      historyPagination.value = res.data.data;
    }
  } catch (e) {
    console.error('Wallet history error', e);
  } finally {
    loadingHistory.value = false;
  }
};

watch(historyFilter, () => { historyPage.value = 1; fetchHistory(1); });

const goToPage = (p) => {
  historyPage.value = p;
  fetchHistory(p);
};

// Deposit
const presetAmounts = [50000, 100000, 200000, 500000, 1000000, 2000000];

const selectPreset = (amount) => {
  depositAmount.value = amount;
};

const initDeposit = async () => {
  const amount = parseInt(depositAmount.value);
  if (!amount || amount < 10000) {
    showToast('Số tiền nạp tối thiểu 10,000₫', 'error');
    return;
  }
  if (amount > 50000000) {
    showToast('Số tiền nạp tối đa 50,000,000₫', 'error');
    return;
  }

  depositLoading.value = true;
  try {
    const res = await walletService.initDeposit(amount, depositMethod.value);
    if (res.data?.status === 'success') {
      const data = res.data.data;
      
      if (data.redirect_url) {
        showToast('Đang chuyển đến cổng thanh toán...', 'success');
        setTimeout(() => { window.location.href = data.redirect_url; }, 500);
        return;
      }

      // Bank transfer → show QR
      depositResult.value = data;
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi khởi tạo nạp tiền', 'error');
  } finally {
    depositLoading.value = false;
  }
};

const closeDepositModal = () => {
  showDepositModal.value = false;
  depositResult.value = null;
  depositAmount.value = '';
  depositMethod.value = 'bank_transfer';
  fetchSummary();
};

// ── Bank Accounts ──
const fetchBankAccounts = async () => {
  try {
    const res = await walletService.getBankAccounts();
    if (res.data?.status === 'success') {
      bankAccounts.value = res.data.data || [];
      // Auto-select default
      const def = bankAccounts.value.find(b => b.is_default);
      if (def && !selectedBankId.value) selectedBankId.value = def.id;
    }
  } catch (e) { console.error('Bank accounts error', e); }
};

const vietqrBanks = ref([]);
const fetchVietQRBanks = async () => {
  try {
    const res = await fetch('https://api.vietqr.io/v2/banks');
    const data = await res.json();
    if (data.code === '00') {
      vietqrBanks.value = data.data;
    }
  } catch (e) { console.error('Error fetching VietQR banks:', e); }
};

const lookupLoading = ref(false);

const onBankSelectionChange = () => {
    const bank = vietqrBanks.value.find(b => b.shortName === bankForm.value.bank_short_name);
    if (bank) {
        bankForm.value.bank_name = bank.name;
        bankForm.value.bank_bin = bank.bin;
        lookupBankAccount();
    }
};

const lookupBankAccount = async () => {
    if (!bankForm.value.bank_bin || !bankForm.value.account_number) {
        bankForm.value.account_name = '';
        return;
    }
    
    lookupLoading.value = true;
    bankForm.value.account_name = '';
    
    try {
        const res = await walletService.verifyBankAccount({
            bank_bin: bankForm.value.bank_bin,
            account_number: bankForm.value.account_number
        });
        if (res.data?.status === 'success') {
            bankForm.value.account_name = res.data.data.accountName;
        } else {
            showToast(res.data?.message || 'Không tìm thấy tài khoản', 'error');
        }
    } catch (e) {
        showToast(e.response?.data?.message || 'Lỗi kiểm tra tài khoản', 'error');
    } finally {
        lookupLoading.value = false;
    }
};

let lookupTimer = null;
watch(() => bankForm.value.account_number, (newVal) => {
    clearTimeout(lookupTimer);
    if (!newVal || newVal.length < 5) {
        bankForm.value.account_name = '';
        return;
    }
    lookupTimer = setTimeout(() => {
        lookupBankAccount();
    }, 600);
});

const getBankLogo = (bin) => {
    const bank = vietqrBanks.value.find(b => String(b.bin) === String(bin));
    return bank ? bank.logo : 'https://vietqr.net/portal/v2021/assets/images/vietqr.png';
};

const openAddBank = () => {
  editingBankId.value = null;
  bankForm.value = { bank_name: '', bank_short_name: '', bank_bin: '', account_name: '', account_number: '' };
  showBankModal.value = true;
};

const openEditBank = (acc) => {
  editingBankId.value = acc.id;
  bankForm.value = {
    bank_name: acc.bank_name,
    bank_short_name: acc.bank_short_name || '',
    bank_bin: acc.bank_bin || '',
    account_name: acc.account_name,
    account_number: acc.account_number,
  };
  showBankModal.value = true;
};

const saveBankAccount = async () => {
  if (!bankForm.value.bank_name.trim()) return showToast('Nhập tên ngân hàng', 'error');
  if (!bankForm.value.account_name.trim()) return showToast('Nhập tên chủ TK', 'error');
  if (!bankForm.value.account_number.trim()) return showToast('Nhập số tài khoản', 'error');

  bankFormLoading.value = true;
  try {
    if (editingBankId.value) {
      await walletService.updateBankAccount(editingBankId.value, bankForm.value);
      showToast('Đã cập nhật tài khoản ngân hàng', 'success');
    } else {
      await walletService.addBankAccount(bankForm.value);
      showToast('Đã liên kết tài khoản ngân hàng', 'success');
    }
    showBankModal.value = false;
    fetchBankAccounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi lưu tài khoản', 'error');
  } finally {
    bankFormLoading.value = false;
  }
};

const deleteBank = async (id) => {
  const result = await Swal.fire({
    title: 'Xác nhận xóa',
    text: 'Bạn có chắc chắn muốn xóa tài khoản ngân hàng này?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#9ca3af',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy'
  });
  if (!result.isConfirmed) return;
  try {
    await walletService.deleteBankAccount(id);
    showToast('Đã xóa tài khoản', 'success');
    if (selectedBankId.value === id) selectedBankId.value = null;
    fetchBankAccounts();
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi xóa', 'error');
  }
};

const setDefaultBank = async (id) => {
  try {
    await walletService.setDefaultBankAccount(id);
    fetchBankAccounts();
  } catch (e) { showToast('Lỗi đặt mặc định', 'error'); }
};

// ── Withdraw ──
const selectedBank = computed(() => bankAccounts.value.find(b => b.id === selectedBankId.value));

const submitWithdraw = async () => {
  const amount = parseInt(withdrawAmount.value);
  if (!amount || amount < 10000) {
    showToast('Số tiền rút tối thiểu 10,000₫', 'error');
    return;
  }
  
  const fee = 1000;
  if ((amount + fee) > (summary.value?.deposit_balance || 0)) {
    showToast(`Số dư không đủ. Cần tối thiểu ${formatPrice(amount + fee)} (đã bao gồm phí)`, 'error');
    return;
  }

  if (!selectedBank.value) {
    showToast('Vui lòng chọn tài khoản ngân hàng', 'error');
    return;
  }

  withdrawLoading.value = true;
  try {
    const res = await walletService.withdraw({
      amount,
      bank_name: selectedBank.value.bank_name,
      bank_account_name: selectedBank.value.account_name,
      bank_account_number: selectedBank.value.account_number,
    });
    if (res.data?.status === 'success') {
      withdrawResult.value = res.data.data;
      showToast('Rút tiền thành công! Số dư đã được trừ.', 'success');
      fetchSummary();
      fetchHistory();
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi rút tiền', 'error');
  } finally {
    withdrawLoading.value = false;
  }
};

const closeWithdrawModal = () => {
  showWithdrawModal.value = false;
  withdrawResult.value = null;
  withdrawAmount.value = '';
};

const getTypeLabel = (type) => {
  const map = {
    deposit: 'Nạp tiền',
    commission: 'Hoa hồng',
    refund: 'Hoàn tiền',
    order_discount: 'Giảm giá đơn',
    withdrawal: 'Rút tiền',
    loyalty_convert: 'Đổi điểm',
    promo_credit: 'Khuyến mãi',
    adjustment: 'Điều chỉnh',
    booking_payment: 'Thanh toán sân',
  };
  return map[type] || type;
};

const getTypeColor = (type) => {
  const map = {
    deposit: '#10b981',
    commission: '#8b5cf6',
    refund: '#0ea5e9',
    order_discount: '#ef4444',
    withdrawal: '#f97316',
    adjustment: '#f59e0b',
  };
  return map[type] || '#6b7280';
};

onMounted(async () => {
  await Promise.all([fetchSummary(), fetchHistory(), fetchBankAccounts(), fetchVietQRBanks()]);
  loading.value = false;
});
</script>

<template>
  <div class="wallet-page">
    <!-- Modern Skeleton Loading -->
    <div v-if="loading" class="wallet-skeleton">
      <div class="skeleton-main-card">
        <div style="display: flex; gap: 16px; align-items: center;">
          <div class="skeleton-box" style="width: 56px; height: 56px; border-radius: 50%;"></div>
          <div>
            <div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
            <div class="skeleton-box" style="width: 200px; height: 32px; border-radius: 6px;"></div>
          </div>
        </div>
        <div style="display: flex; gap: 10px;">
          <div class="skeleton-box" style="width: 120px; height: 44px; border-radius: 12px;"></div>
          <div class="skeleton-box" style="width: 120px; height: 44px; border-radius: 12px;"></div>
        </div>
      </div>

      <div class="skeleton-balance-grid" style="margin-top: 20px;">
        <div v-for="i in 3" :key="i" class="skeleton-card" style="padding: 20px;">
          <div class="skeleton-box" style="width: 100px; height: 14px; border-radius: 4px; margin-bottom: 10px;"></div>
          <div class="skeleton-box" style="width: 140px; height: 24px; border-radius: 6px;"></div>
        </div>
      </div>

      <div class="skeleton-card" style="margin-top: 24px; padding: 24px;">
        <div class="skeleton-box" style="width: 180px; height: 22px; border-radius: 6px; margin-bottom: 20px;"></div>
        <div v-for="i in 4" :key="i" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9;">
          <div style="display: flex; gap: 12px; align-items: center;">
            <div class="skeleton-box" style="width: 40px; height: 40px; border-radius: 10px;"></div>
            <div>
              <div class="skeleton-box" style="width: 160px; height: 16px; border-radius: 4px; margin-bottom: 6px;"></div>
              <div class="skeleton-box" style="width: 100px; height: 12px; border-radius: 4px;"></div>
            </div>
          </div>
          <div class="skeleton-box" style="width: 90px; height: 20px; border-radius: 4px;"></div>
        </div>
      </div>
    </div>

    <template v-else>
      <!-- ═══ BALANCE CARDS ═══ -->
      <div class="wallet-balance-section">
        <div class="balance-main-card">
          <div class="balance-main-header">
            <div class="balance-icon-circle">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M16 12h.01"/><path d="M2 10h20"/>
              </svg>
            </div>
            <div>
              <p class="balance-label">Tổng số dư ví</p>
              <h2 class="balance-amount">{{ formatPrice(totalBalance) }}</h2>
            </div>
          </div>
          <div class="balance-actions">
            <button class="btn-deposit" @click="showDepositModal = true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Nạp tiền
            </button>
            <button class="btn-withdraw" @click="showWithdrawModal = true" :disabled="!summary?.deposit_balance || summary.deposit_balance < 11000">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 19V5"/><polyline points="5 12 12 5 19 12"/>
              </svg>
              Rút tiền
            </button>
          </div>
        </div>

        <div class="balance-detail-cards">
          <div class="balance-card balance-card--deposit">
            <div class="bc-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
              </svg>
            </div>
            <div class="bc-info">
              <span class="bc-label">Số dư nạp</span>
              <span class="bc-amount">{{ formatPrice(summary?.deposit_balance) }}</span>
              <span class="bc-note">Dùng không giới hạn</span>
            </div>
          </div>

          <div class="balance-card balance-card--commission">
            <div class="bc-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
              </svg>
            </div>
            <div class="bc-info">
              <span class="bc-label">Hoa hồng Affiliate</span>
              <span class="bc-amount">{{ formatPrice(summary?.commission_balance) }}</span>
              <span class="bc-note">Max {{ formatPrice(summary?.max_commission_per_order) }}/đơn (10%)</span>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="wallet-stats">
          <div class="stat-item">
            <span class="stat-label">Tháng này nhận</span>
            <span class="stat-value stat-value--green">+{{ formatPrice(summary?.this_month_earned) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Tháng này dùng</span>
            <span class="stat-value stat-value--red">-{{ formatPrice(summary?.this_month_used) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Tổng đã nạp</span>
            <span class="stat-value">{{ formatPrice(summary?.total_deposited) }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Tổng đã dùng</span>
            <span class="stat-value">{{ formatPrice(summary?.total_used) }}</span>
          </div>
        </div>
      </div>

      <!-- ═══ BANK ACCOUNTS SECTION ═══ -->
      <div class="bank-accounts-section">
        <div class="ba-header">
          <h3>Tài khoản ngân hàng liên kết</h3>
          <button class="btn-add-bank" @click="openAddBank" :disabled="bankAccounts.length >= 3">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Thêm TK
          </button>
        </div>
        <div v-if="bankAccounts.length === 0" class="ba-empty">
          <p>Chưa liên kết tài khoản ngân hàng nào.</p>
          <button class="btn-add-bank-lg" @click="openAddBank">+ Liên kết ngân hàng</button>
        </div>
        <div v-else class="ba-list">
          <div v-for="acc in bankAccounts" :key="acc.id" class="ba-item" :class="{ 'ba-default': acc.is_default }">
            <div class="ba-icon">
              <img :src="getBankLogo(acc.bank_bin)" :alt="acc.bank_name" style="width: 84px; height: 56px; object-fit: contain; border-radius: 4px;" />
            </div>
            <div class="ba-info">
              <span class="ba-bank-name">{{ acc.bank_name }}
                <span v-if="acc.is_default" class="ba-badge">Mặc định</span>
              </span>
              <span class="ba-acc-name">{{ acc.account_name }}</span>
              <span class="ba-acc-num">{{ acc.account_number }}</span>
            </div>
            <div class="ba-actions">
              <button v-if="!acc.is_default" class="ba-btn" title="Đặt mặc định" @click="setDefaultBank(acc.id)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
              </button>
              <button class="ba-btn" title="Sửa" @click="openEditBank(acc)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              <button class="ba-btn ba-btn-del" title="Xóa" @click="deleteBank(acc.id)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ TRANSACTION HISTORY ═══ -->
      <div class="wallet-history-section">
        <div class="history-header">
          <h3>Lịch sử giao dịch</h3>
          <div class="history-filters">
            <button v-for="f in [
              { key: 'all', label: 'Tất cả' },
              { key: 'deposit', label: 'Nạp tiền' },
              { key: 'commission', label: 'Hoa hồng' },
              { key: 'order_discount', label: 'Giảm giá' },
              { key: 'withdrawal', label: 'Rút tiền' },
              { key: 'refund', label: 'Hoàn tiền' },
            ]" :key="f.key"
              class="filter-btn" :class="{ active: historyFilter === f.key }"
              @click="historyFilter = f.key">
              {{ f.label }}
            </button>
          </div>
        </div>

        <div v-if="loadingHistory" class="history-loading">
          <div class="wallet-spinner small"></div>
        </div>

        <div v-else-if="history.length === 0" class="history-empty">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M16 12h.01"/><path d="M2 10h20"/>
          </svg>
          <p>Chưa có giao dịch nào</p>
        </div>

        <div v-else class="history-list">
          <div v-for="tx in history" :key="tx.id" class="history-item">
            <div class="hi-icon" :style="{ background: getTypeColor(tx.type) + '15', color: getTypeColor(tx.type) }">
              <span>{{ tx.type_icon || '💰' }}</span>
            </div>
            <div class="hi-info">
              <span class="hi-label">{{ tx.type_label || getTypeLabel(tx.type) }}</span>
              <span class="hi-code">{{ tx.transaction_code }}</span>
              <span class="hi-desc" v-if="tx.description">{{ cleanText(tx.description) }}</span>
              <span class="hi-date">{{ new Date(tx.created_at).toLocaleString('vi-VN') }}</span>
            </div>
            <div class="hi-amount" :class="tx.direction === 'credit' ? 'credit' : 'debit'">
              {{ tx.direction === 'credit' ? '+' : '-' }}{{ formatPrice(tx.amount) }}
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="historyPagination && historyPagination.last_page > 1" class="history-pagination">
          <button :disabled="historyPagination.current_page <= 1" @click="goToPage(historyPagination.current_page - 1)">‹</button>
          <span class="page-info">{{ historyPagination.current_page }} / {{ historyPagination.last_page }}</span>
          <button :disabled="historyPagination.current_page >= historyPagination.last_page" @click="goToPage(historyPagination.current_page + 1)">›</button>
        </div>
      </div>
    </template>

    <!-- ═══ DEPOSIT MODAL ═══ -->
    <Teleport to="body">
      <div v-if="showDepositModal" class="deposit-overlay" @click.self="closeDepositModal">
        <div class="deposit-modal">
          <!-- Step 1: Nhập số tiền -->
          <template v-if="!depositResult">
            <div class="dm-header">
              <h3>Nạp tiền vào ví</h3>
              <button class="dm-close" @click="closeDepositModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>

            <div class="dm-body">
              <label class="dm-label">Số tiền nạp</label>
              <input type="number" v-model="depositAmount" class="dm-input" placeholder="Nhập số tiền (tối thiểu 10,000₫)" min="10000" @keydown="preventInvalidNumber" />

              <div class="preset-amounts">
                <button v-for="amt in presetAmounts" :key="amt"
                  class="preset-btn" :class="{ active: depositAmount == amt }"
                  @click="selectPreset(amt)">
                  {{ (amt / 1000) + 'K' }}
                </button>
              </div>

              <label class="dm-label mt-16">Phương thức thanh toán</label>
              <div class="deposit-methods">
                <label class="dep-method" :class="{ active: depositMethod === 'bank_transfer' }">
                  <input type="radio" v-model="depositMethod" value="bank_transfer" />
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="21" width="20" height="2"/><polygon points="12 2 2 7 22 7 12 2"/><path d="M5 21V9"/><path d="M19 21V9"/><path d="M12 21V9"/>
                  </svg>
                  <span>Chuyển khoản</span>
                </label>
                <label class="dep-method" :class="{ active: depositMethod === 'vnpay' }">
                  <input type="radio" v-model="depositMethod" value="vnpay" />
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                  </svg>
                  <span>VNPay</span>
                </label>

              </div>

              <button class="btn-confirm-deposit" @click="initDeposit" :disabled="depositLoading || !depositAmount">
                <div v-if="depositLoading" class="wallet-spinner small white"></div>
                <span v-else>Nạp {{ depositAmount ? formatPrice(depositAmount) : '' }}</span>
              </button>
            </div>
          </template>

          <!-- Step 2: QR chuyển khoản -->
          <template v-else>
            <div class="dm-header">
              <h3>Chuyển khoản nạp ví</h3>
              <button class="dm-close" @click="closeDepositModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <div class="dm-body qr-body">
              <img v-if="depositResult.banking_info?.qr_url" :src="depositResult.banking_info.qr_url" alt="QR" class="deposit-qr" />
              <div class="deposit-info-rows">
                <div class="dir-row">
                  <span>Số tài khoản</span>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <strong>{{ depositResult.banking_info?.account_number }}</strong>
                    <button class="btn-copy" @click="copyToClipboard(depositResult.banking_info?.account_number)" title="Sao chép">📋</button>
                  </div>
                </div>
                <div class="dir-row">
                  <span>Chủ TK</span>
                  <strong>{{ depositResult.banking_info?.account_name }}</strong>
                </div>
                <div class="dir-row">
                  <span>Số tiền</span>
                  <strong class="text-green">{{ formatPrice(depositResult.banking_info?.amount) }}</strong>
                </div>
                <div class="dir-row">
                  <span>Nội dung CK</span>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <strong class="text-primary">{{ depositResult.deposit_code }}</strong>
                    <button class="btn-copy" @click="copyToClipboard(depositResult.deposit_code)" title="Sao chép">📋</button>
                  </div>
                </div>
              </div>
              <div class="deposit-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>Nhập đúng <strong>nội dung chuyển khoản</strong> để hệ thống tự động nạp ví!</span>
              </div>
              <button class="btn-confirm-deposit" @click="closeDepositModal">Tôi đã chuyển khoản xong</button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- ═══ WITHDRAW MODAL ═══ -->
    <Teleport to="body">
      <div v-if="showWithdrawModal" class="deposit-overlay" @click.self="closeWithdrawModal">
        <div class="deposit-modal">
          <template v-if="!withdrawResult">
            <div class="dm-header">
              <h3>Rút tiền về ngân hàng</h3>
              <button class="dm-close" @click="closeWithdrawModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <div class="dm-body">
              <div class="withdraw-balance-info">
                <span>Số dư nạp khả dụng:</span>
                <strong>{{ formatPrice(summary?.deposit_balance) }}</strong>
              </div>

              <label class="dm-label">Số tiền rút</label>
              <input type="number" v-model="withdrawAmount" class="dm-input" placeholder="Tối thiểu 10,000₫" min="10000" @keydown="preventInvalidNumber" />
              <p class="withdraw-fee-note">Phí rút: <strong>1,000₫</strong>/lần · Thực nhận: <strong>{{ withdrawAmount ? formatPrice(Math.max(0, withdrawAmount)) : '—' }}</strong></p>

              <!-- Chọn TK ngân hàng -->
              <label class="dm-label mt-16">Tài khoản nhận tiền</label>
              <div v-if="bankAccounts.length === 0" class="withdraw-no-bank">
                <p>Chưa liên kết tài khoản ngân hàng.</p>
                <button class="btn-link-bank" @click="closeWithdrawModal(); openAddBank();">+ Liên kết ngay</button>
              </div>
              <div v-else class="withdraw-bank-list">
                <label v-for="acc in bankAccounts" :key="acc.id"
                  class="withdraw-bank-option" :class="{ active: selectedBankId === acc.id }">
                  <input type="radio" :value="acc.id" v-model="selectedBankId" />
                  <div class="wbo-info">
                    <span class="wbo-name">{{ acc.bank_name }}
                      <span v-if="acc.is_default" class="ba-badge">Mặc định</span>
                    </span>
                    <span class="wbo-detail">{{ acc.account_name }} · {{ acc.account_number }}</span>
                  </div>
                  <svg v-if="selectedBankId === acc.id" class="wbo-check" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                  </svg>
                </label>
              </div>

              <button class="btn-confirm-deposit btn-withdraw-confirm" @click="submitWithdraw"
                :disabled="withdrawLoading || !withdrawAmount || !selectedBankId">
                <div v-if="withdrawLoading" class="wallet-spinner small white"></div>
                <span v-else>Rút {{ withdrawAmount ? formatPrice(withdrawAmount) : '' }}</span>
              </button>
            </div>
          </template>

          <!-- Kết quả -->
          <template v-else>
            <div class="dm-header">
              <h3>Rút tiền thành công</h3>
              <button class="dm-close" @click="closeWithdrawModal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <div class="dm-body qr-body">
              <div class="withdraw-success-icon">✅</div>
              <h4 class="withdraw-success-title">Đã trừ số dư ví</h4>
              <div class="deposit-info-rows">
                <div class="dir-row">
                  <span>Mã rút tiền</span>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <strong class="text-primary">{{ withdrawResult.withdrawal_code }}</strong>
                    <button class="btn-copy" @click="copyToClipboard(withdrawResult.withdrawal_code)" title="Sao chép">📋</button>
                  </div>
                </div>
                <div class="dir-row"><span>Số tiền rút</span><strong>{{ formatPrice(withdrawResult.amount) }}</strong></div>
                <div class="dir-row"><span>Phí rút</span><strong>{{ formatPrice(withdrawResult.fee) }}</strong></div>
                <div class="dir-row"><span>Tổng trừ ví</span><strong class="text-red">{{ formatPrice(withdrawResult.total_deducted) }}</strong></div>
                <div class="dir-row"><span>Số dư mới</span><strong class="text-green">{{ formatPrice(withdrawResult.new_balance) }}</strong></div>
              </div>
              <div class="deposit-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                  <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <span>Tiền sẽ được chuyển về tài khoản ngân hàng của bạn trong thời gian sớm nhất.</span>
              </div>
              <button class="btn-confirm-deposit" @click="closeWithdrawModal">Đóng</button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- ═══ BANK ACCOUNT MODAL (Add/Edit) ═══ -->
    <Teleport to="body">
      <div v-if="showBankModal" class="deposit-overlay" @click.self="showBankModal = false">
        <div class="deposit-modal">
          <div class="dm-header">
            <h3>{{ editingBankId ? 'Sửa tài khoản' : 'Liên kết ngân hàng' }}</h3>
            <button class="dm-close" @click="showBankModal = false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
          <div class="dm-body">
            <label class="dm-label">Tên ngân hàng *</label>
            <select v-model="bankForm.bank_short_name" class="dm-input" @change="onBankSelectionChange">
                <option value="">-- Chọn ngân hàng --</option>
                <option v-for="bank in vietqrBanks" :key="bank.bin" :value="bank.shortName">
                    {{ bank.shortName }} - {{ bank.name }}
                </option>
            </select>

            <label class="dm-label mt-16">Số tài khoản *</label>
            <input type="text" v-model="bankForm.account_number" class="dm-input" placeholder="VD: 1234567890" />

            <label class="dm-label mt-16">Tên chủ tài khoản *</label>
            <div style="position: relative;">
                <input type="text" v-model="bankForm.account_name" class="dm-input" placeholder="Tự động hiển thị" disabled style="background-color: #f3f4f6; color: #374151; font-weight: 600;" />
                <div v-if="lookupLoading" class="wallet-spinner small" style="position: absolute; right: 12px; top: 12px; border-color: #d1d5db; border-top-color: var(--primary);"></div>
            </div>

            <button class="btn-confirm-deposit mt-16" @click="saveBankAccount" :disabled="bankFormLoading || !bankForm.account_name">
              <div v-if="bankFormLoading" class="wallet-spinner small white"></div>
              <span v-else>{{ editingBankId ? 'Cập nhật' : 'Liên kết' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.wallet-page {
  font-family: var(--font-inter, 'Inter', sans-serif);
}

/* Loading */
.wallet-loading {
  text-align: center;
  padding: 80px 0;
  color: #9ca3af;
}
.wallet-spinner {
  width: 36px; height: 36px;
  border: 3px solid #e5e7eb;
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: wspin 0.7s linear infinite;
  margin: 0 auto 12px;
}
.wallet-spinner.small { width: 20px; height: 20px; border-width: 2px; margin: 0; display: inline-block; }
.wallet-spinner.white { border-color: rgba(255,255,255,.3); border-top-color: #fff; }
@keyframes wspin { 100% { transform: rotate(360deg); } }

/* ═══ BALANCE SECTION ═══ */
.wallet-balance-section {
  margin-bottom: 28px;
}
.balance-main-card {
  background: linear-gradient(135deg, var(--primary) 0%, #c02758 100%);
  border-radius: 20px;
  padding: 28px 24px;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  box-shadow: 0 8px 32px rgba(230, 59, 111, 0.25);
}
.balance-main-header { display: flex; align-items: center; gap: 16px; }
.balance-icon-circle {
  width: 56px; height: 56px;
  background: rgba(255,255,255,0.15);
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(10px);
}
.balance-label { font-size: 0.85rem; opacity: 0.85; margin: 0; }
.balance-amount { font-size: 1.8rem; font-weight: 800; margin: 4px 0 0; letter-spacing: -0.5px; }
.btn-deposit {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  height: 40px;
  padding: 0 18px;
  background: rgba(255,255,255,0.2);
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 8px;
  color: #fff;
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 0.2s;
  backdrop-filter: blur(10px);
  white-space: nowrap;
}
.btn-deposit:hover {
  background: rgba(255,255,255,0.3);
  transform: translateY(-1px);
}

.balance-detail-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.balance-card {
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 20px;
  display: flex; align-items: flex-start; gap: 14px;
}
.bc-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.balance-card--deposit .bc-icon { background: #ecfdf5; color: #10b981; }
.balance-card--commission .bc-icon { background: #f5f3ff; color: #8b5cf6; }
.bc-info { display: flex; flex-direction: column; min-width: 0; }
.bc-label { font-size: 0.8rem; color: #6b7280; font-weight: 500; }
.bc-amount { font-size: 1.2rem; font-weight: 700; color: #1f2937; margin: 4px 0 2px; }
.bc-note { font-size: 0.75rem; color: #9ca3af; }

/* Stats */
.wallet-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 16px 20px;
}
.stat-item { display: flex; flex-direction: column; align-items: center; text-align: center; }
.stat-label { font-size: 0.75rem; color: #9ca3af; margin-bottom: 4px; }
.stat-value { font-size: 0.9rem; font-weight: 700; color: #374151; }
.stat-value--green { color: #10b981; }
.stat-value--red { color: #ef4444; }

/* ═══ BANK ACCOUNTS SECTION ═══ */
.bank-accounts-section {
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 20px;
}
.ba-header {
  padding: 20px 24px 16px;
  display: flex; justify-content: space-between; align-items: center;
}
.ba-header h3 { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin: 0; }
.btn-add-bank {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  background: var(--card-bg); border: 1.5px solid var(--primary);
  border-radius: 10px; color: var(--primary);
  font-size: 0.82rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.btn-add-bank:hover:not(:disabled) { background: #fff0f3; }
.btn-add-bank:disabled { opacity: 0.4; cursor: not-allowed; }
.ba-empty {
  padding: 32px 24px; text-align: center; color: #9ca3af;
}
.btn-add-bank-lg {
  margin-top: 12px; padding: 10px 24px;
  background: var(--primary); color: #fff;
  border: none; border-radius: 10px;
  font-weight: 600; cursor: pointer;
  transition: background 0.2s;
}
.btn-add-bank-lg:hover { background: #d1345f; }
.ba-list { padding: 0 24px 16px; }
.ba-item {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 8px;
  transition: all 0.2s;
}
.ba-item:last-child { margin-bottom: 0; }
.ba-default { border-color: var(--primary); background: #fff8fa; }
.ba-icon { font-size: 1.5rem; flex-shrink: 0; }
.ba-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.ba-bank-name { font-size: 0.9rem; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px; }
.ba-badge {
  display: inline-block; padding: 2px 8px;
  background: var(--primary); color: #fff;
  border-radius: 6px; font-size: 0.65rem; font-weight: 700;
}
.ba-acc-name { font-size: 0.82rem; color: #6b7280; }
.ba-acc-num { font-size: 0.78rem; color: #9ca3af; font-family: monospace; }
.ba-actions { display: flex; gap: 4px; flex-shrink: 0; }
.ba-btn {
  width: 32px; height: 32px;
  border: none; background: #f3f4f6;
  border-radius: 8px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; transition: background 0.2s;
}
.ba-btn:hover { background: #e5e7eb; }
.ba-btn-del:hover { background: #fee2e2; }

/* Withdraw bank selector */
.withdraw-no-bank {
  text-align: center; padding: 16px;
  background: #f9fafb; border: 1px dashed #d1d5db;
  border-radius: 10px; color: #6b7280; font-size: 0.88rem;
}
.btn-link-bank {
  margin-top: 8px; padding: 8px 20px;
  background: var(--primary); color: #fff;
  border: none; border-radius: 8px;
  font-weight: 600; font-size: 0.85rem;
  cursor: pointer;
}
.btn-link-bank:hover { background: #d1345f; }
.withdraw-bank-list { display: flex; flex-direction: column; gap: 8px; }
.withdraw-bank-option {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer; transition: all 0.2s;
}
.withdraw-bank-option input { display: none; }
.withdraw-bank-option:hover { border-color: #f97316; }
.withdraw-bank-option.active { border-color: #f97316; background: #fff7ed; }
.wbo-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.wbo-name { font-size: 0.9rem; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 6px; }
.wbo-detail { font-size: 0.8rem; color: #6b7280; }
.wbo-check { flex-shrink: 0; }

/* ═══ HISTORY SECTION ═══ */
.wallet-history-section {
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
}
.history-header {
  padding: 20px 24px 0;
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
}
.history-header h3 { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin: 0; }
.history-filters { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-btn {
  padding: 6px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 20px;
  background: var(--card-bg);
  font-size: 0.8rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}
.filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

.history-loading { padding: 40px; text-align: center; }
.history-empty { padding: 60px 24px; text-align: center; color: #9ca3af; }
.history-empty p { margin-top: 12px; }

.history-list { padding: 16px 24px; }
.history-item {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 0;
  border-bottom: 1px solid #f3f4f6;
}
.history-item:last-child { border-bottom: none; }
.hi-icon {
  width: 42px; height: 42px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.hi-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.hi-label { font-size: 0.9rem; font-weight: 600; color: #1f2937; }
.hi-code { font-size: 0.75rem; font-family: monospace; font-weight: 700; color: var(--primary); background: rgba(230,59,111,0.08); padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; }
.hi-desc { font-size: 0.78rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hi-date { font-size: 0.75rem; color: #d1d5db; }
.hi-amount { font-size: 0.95rem; font-weight: 700; white-space: nowrap; }
.hi-amount.credit { color: #10b981; }
.hi-amount.debit { color: #ef4444; }

/* Pagination */
.history-pagination {
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: center; gap: 16px;
  border-top: 1px solid #f3f4f6;
}
.history-pagination button {
  width: 36px; height: 36px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: var(--card-bg);
  font-size: 1.1rem;
  cursor: pointer;
  transition: all 0.2s;
}
.history-pagination button:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.history-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.85rem; color: #6b7280; font-weight: 500; }

/* ═══ DEPOSIT MODAL ═══ */
.deposit-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.deposit-modal {
  background: var(--card-bg);
  border-radius: 20px;
  width: 100%;
  max-width: 440px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.dm-header {
  padding: 14px 20px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid #f3f4f6;
}
.dm-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
.dm-close {
  width: 36px; height: 36px;
  border-radius: 10px;
  border: none;
  background: #f3f4f6;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
.dm-close:hover { background: #e5e7eb; }
.dm-body { padding: 16px 20px; }
.dm-label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 8px; }
.mt-16 { margin-top: 16px; }
.dm-input {
  width: 100%;
  padding: 14px 16px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.dm-input:focus { border-color: var(--primary); }

.preset-amounts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
.preset-btn {
  padding: 10px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: var(--card-bg);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
}
.preset-btn:hover { border-color: var(--primary); color: var(--primary); }
.preset-btn.active { background: #fff0f3; border-color: var(--primary); color: var(--primary); }

.deposit-methods { display: flex; gap: 8px; flex-wrap: wrap; }
.dep-method {
  flex: 1;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 14px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.8rem;
  font-weight: 500;
  color: #6b7280;
  min-width: 100px;
}
.dep-method input { display: none; }
.dep-method:hover { border-color: var(--primary); }
.dep-method.active { border-color: var(--primary); background: #fff0f3; color: var(--primary); }

.btn-confirm-deposit {
  width: 100%;
  padding: 12px;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  margin-top: 12px;
  transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-confirm-deposit:hover:not(:disabled) { background: #d1345f; transform: translateY(-1px); }
.btn-confirm-deposit:disabled { opacity: 0.5; cursor: not-allowed; }

/* QR body */
.qr-body { text-align: center; }
.deposit-qr { width: 180px; height: 180px; border-radius: 12px; margin-bottom: 12px; }
.deposit-info-rows { text-align: left; margin-bottom: 16px; }
.dir-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid #f3f4f6;
  font-size: 0.88rem;
}
.dir-row span { color: #6b7280; }
.dir-row strong { color: #1f2937; }
.text-green { color: #10b981 !important; }
.text-primary { color: var(--primary) !important; }
.btn-copy { background: none; border: none; cursor: pointer; font-size: 1.05rem; padding: 0; filter: grayscale(1); opacity: 0.6; transition: all 0.2s; }
.btn-copy:hover { opacity: 1; filter: grayscale(0); transform: scale(1.1); }
.deposit-note {
  display: flex; align-items: flex-start; gap: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 12px;
  font-size: 0.8rem;
  color: #92400e;
  text-align: left;
}

/* Balance actions */
.balance-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-withdraw {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 20px;
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 12px;
  color: #fff;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.btn-withdraw:hover:not(:disabled) { background: rgba(255,255,255,0.2); transform: translateY(-1px); }
.btn-withdraw:disabled { opacity: 0.4; cursor: not-allowed; }

/* Withdraw modal extras */
.withdraw-balance-info {
  display: flex; justify-content: space-between; align-items: center;
  background: #ecfdf5; border: 1px solid #a7f3d0;
  border-radius: 10px; padding: 12px 16px;
  margin-bottom: 16px; font-size: 0.88rem;
}
.withdraw-balance-info strong { color: #10b981; font-size: 1rem; }
.withdraw-fee-note {
  font-size: 0.78rem; color: #9ca3af; margin: 6px 0 0;
}
.withdraw-fee-note strong { color: #374151; }
.btn-withdraw-confirm { background: #f97316 !important; }
.btn-withdraw-confirm:hover:not(:disabled) { background: #ea580c !important; }
.withdraw-success-icon { font-size: 3rem; margin-bottom: 8px; }
.withdraw-success-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin: 0 0 16px; }
.text-red { color: #ef4444 !important; }

/* Responsive */
@media (max-width: 768px) {
  .balance-main-card { flex-direction: column; text-align: center; }
  .balance-main-header { flex-direction: column; }
  .balance-actions { width: 100%; }
  .btn-deposit, .btn-withdraw { flex: 1; justify-content: center; }
  .balance-detail-cards { grid-template-columns: 1fr; }
  .wallet-stats { grid-template-columns: repeat(2, 1fr); }
  .history-header { flex-direction: column; align-items: flex-start; }
}

/* ===== Modern Skeleton Loading Styles ===== */
.wallet-skeleton {
  width: 100%;
  pointer-events: none;
}

.skeleton-main-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 16px;
  padding: 28px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

.skeleton-balance-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.skeleton-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 16px;
}

.skeleton-box {
  background: var(--surface-container, #e2e8f0);
  position: relative;
  overflow: hidden;
}

.skeleton-box::after {
  content: '';
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  transform: translateX(-100%);
  background-image: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0,
    rgba(255, 255, 255, 0.4) 30%,
    rgba(255, 255, 255, 0.75) 60%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  100% {
    transform: translateX(100%);
  }
}
</style>
