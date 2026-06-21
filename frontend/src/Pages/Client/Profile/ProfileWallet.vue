<script setup>
import { ref, onMounted, computed } from 'vue';
import { walletService } from '@/services/walletService';
import { useToast } from '@/composables/useToast';

const { showToast } = useToast();

// ─── STATE ──────────────────────────────────────────────────────────
const loadingSummary = ref(true);
const loadingHistory = ref(true);
const summary = ref({
  balance: 0,
  affiliate_earnings: 0,
  withdrawn_amount: 0,
  pending_withdrawals: 0,
  is_affiliate: false,
});

const transactions = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 10,
});

// Withdrawal request form
const activeTab = ref('withdraw'); // withdraw | history | guide
const submittingWithdraw = ref(false);
const withdrawForm = ref({
  amount: '',
  withdrawal_method: 'bank', // bank | vnpay
  bank_name: '',
  bank_account_name: '',
  bank_account_number: '',
});

// ─── COMPUTED ───────────────────────────────────────────────────────
const availableBalance = computed(() => {
  return summary.value.balance;
});

// ─── METHODS ────────────────────────────────────────────────────────
const formatMoney = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleDateString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const fetchSummary = async () => {
  loadingSummary.value = true;
  try {
    const res = await walletService.getSummary();
    if (res.data?.status === 'success') {
      summary.value = res.data.data;
    }
  } catch (e) {
    showToast('Lỗi khi tải thông tin ví điện tử', 'error');
  } finally {
    loadingSummary.value = false;
  }
};

const fetchHistory = async (page = 1) => {
  loadingHistory.value = true;
  try {
    const res = await walletService.getHistory(page, pagination.value.per_page);
    if (res.data?.status === 'success') {
      transactions.value = res.data.data.data;
      pagination.value.current_page = res.data.data.current_page;
      pagination.value.last_page = res.data.data.last_page;
      pagination.value.total = res.data.data.total;
    }
  } catch (e) {
    showToast('Lỗi khi tải lịch sử giao dịch ví', 'error');
  } finally {
    loadingHistory.value = false;
  }
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchHistory(page);
  }
};

const handleWithdraw = async () => {
  if (!withdrawForm.value.amount || parseFloat(withdrawForm.value.amount) <= 0) {
    return showToast('Số tiền rút phải lớn hơn 0đ.', 'error');
  }
  if (parseFloat(withdrawForm.value.amount) > availableBalance.value) {
    return showToast('Số tiền rút không được vượt quá số dư khả dụng.', 'error');
  }
  if (parseFloat(withdrawForm.value.amount) < 100000) {
    return showToast('Số tiền rút tối thiểu là 100.000đ.', 'error');
  }

  if (withdrawForm.value.withdrawal_method === 'bank') {
    if (!withdrawForm.value.bank_name.trim()) return showToast('Vui lòng điền tên ngân hàng.', 'error');
    if (!withdrawForm.value.bank_account_name.trim()) return showToast('Vui lòng điền tên chủ tài khoản.', 'error');
    if (!withdrawForm.value.bank_account_number.trim()) return showToast('Vui lòng điền số tài khoản.', 'error');
  }

  submittingWithdraw.value = true;
  try {
    const res = await walletService.requestWithdrawal(withdrawForm.value);
    if (res.data?.status === 'success') {
      showToast(res.data.message || 'Gửi yêu cầu rút tiền thành công!', 'success');
      // Reset form
      withdrawForm.value.amount = '';
      withdrawForm.value.bank_name = '';
      withdrawForm.value.bank_account_name = '';
      withdrawForm.value.bank_account_number = '';
      // Refetch
      await Promise.all([fetchSummary(), fetchHistory(1)]);
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Có lỗi xảy ra khi gửi yêu cầu rút tiền', 'error');
  } finally {
    submittingWithdraw.value = false;
  }
};

onMounted(() => {
  fetchSummary();
  fetchHistory(1);
});
</script>

<template>
  <div class="wallet-container">
    <div class="wallet-header-row animate-in">
      <div>
        <h1 class="wallet-page-title">Ví Tiền Của Tôi</h1>
        <p class="wallet-page-subtitle">Quản lý số dư, tiền hoa hồng Affiliate, rút tiền mặt và thanh toán đơn hàng.</p>
      </div>
      <div v-if="!summary.is_affiliate" class="affiliate-banner-mini">
        <span>Kiếm tiền thụ động cùng Ocean Sport?</span>
        <router-link to="/profile/affiliate" class="btn-join-aff">Kích hoạt Affiliate</router-link>
      </div>
    </div>

    <!-- ─── CARDS SUMMARY ──────────────────────────────────────────────── -->
    <div class="wallet-cards-grid animate-in" style="animation-delay: 0.1s">
      <!-- MAIN WALLET CREDIT CARD -->
      <div class="wallet-card-main glassmorphism">
        <div class="card-chip-row">
          <div class="card-chip"></div>
          <span class="card-logo">OCEAN PAY</span>
        </div>
        <div class="card-balance-block">
          <span class="card-balance-label">Số dư khả dụng</span>
          <h2 class="card-balance-val">{{ formatMoney(availableBalance) }}</h2>
        </div>
        <div class="card-footer-info">
          <div class="cf-item">
            <span class="cf-label">Chủ ví</span>
            <span class="cf-value">{{ $parent?.userName || 'Khách hàng' }}</span>
          </div>
          <div class="cf-item">
            <span class="cf-label">Loại ví</span>
            <span class="cf-value">Electronic Wallet</span>
          </div>
        </div>
        <div class="card-glow"></div>
      </div>

      <!-- EARNINGS STAT CARD -->
      <div class="stat-card stat-earnings block-border">
        <div class="stat-card-icon bg-green-light">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <div class="stat-card-content">
          <span class="stat-label">Tổng hoa hồng Affiliate đã nhận</span>
          <h3 class="stat-value text-green">{{ formatMoney(summary.affiliate_earnings) }}</h3>
          <p class="stat-desc">Tự động cộng khi bạn bè mua hàng thành công</p>
        </div>
      </div>

      <!-- WITHDRAWN STAT CARD -->
      <div class="stat-card stat-withdrawn block-border">
        <div class="stat-card-icon bg-blue-light">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="17 8 12 3 7 8"></polyline>
            <line x1="12" y1="3" x2="12" y2="15"></line>
          </svg>
        </div>
        <div class="stat-card-content">
          <span class="stat-label">Đã rút / Đang rút</span>
          <h3 class="stat-value text-blue">
            {{ formatMoney(summary.withdrawn_amount) }}
            <span v-if="summary.pending_withdrawals > 0" class="pending-amount-text">
              (+{{ formatMoney(summary.pending_withdrawals) }} đang xử lý)
            </span>
          </h3>
          <p class="stat-desc">Tiền mặt đã rút về tài khoản ngân hàng</p>
        </div>
      </div>
    </div>

    <!-- ─── TABS NAVIGATION ────────────────────────────────────────────── -->
    <div class="wallet-tabs-wrapper animate-in" style="animation-delay: 0.2s">
      <div class="wallet-tabs">
        <button
          class="wallet-tab-btn"
          :class="{ 'active': activeTab === 'withdraw' }"
          @click="activeTab = 'withdraw'"
        >
          💸 Rút tiền mặt
        </button>
        <button
          class="wallet-tab-btn"
          :class="{ 'active': activeTab === 'history' }"
          @click="activeTab = 'history'"
        >
          📜 Lịch sử giao dịch
        </button>
        <button
          class="wallet-tab-btn"
          :class="{ 'active': activeTab === 'guide' }"
          @click="activeTab = 'guide'"
        >
          💡 Hướng dẫn sử dụng
        </button>
      </div>

      <!-- ─── TAB CONTENT: WITHDRAWAL FORM ────────────────────────────── -->
      <div v-if="activeTab === 'withdraw'" class="tab-content withdraw-tab block-border">
        <div class="withdraw-section-split">
          <form @submit.prevent="handleWithdraw" class="withdraw-form">
            <h3 class="section-form-title">Yêu Cầu Rút Tiền Về Ngân Hàng / VNPay</h3>
            
            <div class="form-group">
              <label class="form-label">Chọn phương thức rút tiền</label>
              <div class="method-radio-group">
                <label class="method-radio-card" :class="{ 'selected': withdrawForm.withdrawal_method === 'bank' }">
                  <input type="radio" v-model="withdrawForm.withdrawal_method" value="bank" class="hidden-radio" />
                  <span class="method-icon">🏦</span>
                  <span class="method-label">Chuyển khoản Ngân hàng</span>
                </label>
                <label class="method-radio-card" :class="{ 'selected': withdrawForm.withdrawal_method === 'vnpay' }">
                  <input type="radio" v-model="withdrawForm.withdrawal_method" value="vnpay" class="hidden-radio" />
                  <span class="method-icon">📱</span>
                  <span class="method-label">Rút nhanh về VNPay</span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Số tiền cần rút (VND)</label>
              <div class="amount-input-wrapper">
                <input
                  v-model.number="withdrawForm.amount"
                  type="number"
                  class="form-input amount-input"
                  placeholder="Nhập số tiền muốn rút..."
                  required
                />
                <span class="currency-tag">VND</span>
              </div>
              <p class="amount-helper-text">Số dư khả dụng: <strong>{{ formatMoney(availableBalance) }}</strong>. Tối thiểu rút: 100.000đ.</p>
            </div>

            <!-- Conditional fields for Bank -->
            <div v-if="withdrawForm.withdrawal_method === 'bank'" class="bank-details-block animate-in">
              <div class="form-group">
                <label class="form-label">Tên ngân hàng</label>
                <input
                  v-model="withdrawForm.bank_name"
                  type="text"
                  class="form-input"
                  placeholder="Ví dụ: Vietcombank, Techcombank, BIDV..."
                />
              </div>

              <div class="form-group-row">
                <div class="form-group">
                  <label class="form-label">Tên chủ tài khoản</label>
                  <input
                    v-model="withdrawForm.bank_account_name"
                    type="text"
                    class="form-input text-uppercase"
                    placeholder="Ví dụ: NGUYEN VAN A"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Số tài khoản ngân hàng</label>
                  <input
                    v-model="withdrawForm.bank_account_number"
                    type="text"
                    class="form-input"
                    placeholder="Nhập số tài khoản ngân hàng..."
                  />
                </div>
              </div>
            </div>

            <!-- Informative fields for VNPay -->
            <div v-else class="vnpay-details-block animate-in">
              <div class="vnpay-alert-info">
                💡 Hệ thống sẽ tự động chuyển khoản về số điện thoại đăng ký tài khoản của bạn: <strong>{{ $parent?.userPhone || 'Đang xác thực' }}</strong> liên kết với Ví VNPay. Hãy chắc chắn số điện thoại này đã kích hoạt tài khoản ví VNPay.
              </div>
            </div>

            <button type="submit" class="btn-submit-withdraw btn-brown-gradient" :disabled="submittingWithdraw">
              <span v-if="submittingWithdraw" class="spinner-inline"></span>
              <span v-else>Xác Nhận Rút Tiền</span>
            </button>
          </form>

          <div class="withdraw-notes-panel">
            <h4 class="notes-title">⚠️ Quy định rút tiền:</h4>
            <ul class="notes-list">
              <li>Mỗi yêu cầu rút tiền tối thiểu là <strong>100.000đ</strong>.</li>
              <li>Hệ thống chỉ cho phép duy nhất <strong>01 yêu cầu đang chờ duyệt</strong> tại một thời điểm. Bạn không thể tạo yêu cầu tiếp theo nếu yêu cầu trước chưa được xử lý.</li>
              <li>Sau khi gửi yêu cầu, số tiền tương ứng sẽ tạm thời bị trừ khỏi Số dư khả dụng của bạn để chờ duyệt.</li>
              <li>Nếu Admin từ chối yêu cầu, số tiền sẽ được hoàn trả lại ngay lập tức vào Ví của bạn.</li>
              <li>Thời gian xử lý chuyển khoản: từ 1-3 ngày làm việc.</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ─── TAB CONTENT: TRANSACTION HISTORY ─────────────────────────── -->
      <div v-if="activeTab === 'history'" class="tab-content history-tab block-border">
        <h3 class="section-form-title">Lịch Sử Biến Động Số Dư Ví</h3>
        
        <div v-if="loadingHistory" class="history-loading">
          <div class="spinner"></div>
          <p>Đang tải lịch sử giao dịch...</p>
        </div>

        <div v-else-if="transactions.length === 0" class="history-empty">
          <span class="empty-icon">📭</span>
          <p>Chưa có giao dịch phát sinh nào từ ví này.</p>
        </div>

        <div v-else class="history-table-wrapper">
          <table class="history-table">
            <thead>
              <tr>
                <th>Thời gian</th>
                <th>Mã GD</th>
                <th>Loại giao dịch</th>
                <th>Số tiền</th>
                <th>Trạng thái</th>
                <th>Nội dung chi tiết</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in transactions" :key="tx.id">
                <td class="td-date">{{ formatDate(tx.created_at) }}</td>
                <td class="td-id">#{{ tx.id }}</td>
                <td>
                  <span class="type-badge" :class="tx.type">
                    {{ tx.type === 'deposit' ? 'Nạp tiền' : 
                       tx.type === 'spend' ? 'Thanh toán' : 
                       tx.type === 'refund' ? 'Hoàn tiền' : 
                       tx.type === 'withdraw' ? 'Rút tiền' : 
                       tx.type === 'commission' ? 'Hoa hồng' : tx.type }}
                  </span>
                </td>
                <td class="td-amount fw-bold" :class="tx.amount > 0 ? 'text-green' : 'text-red'">
                  {{ tx.amount > 0 ? '+' : '' }}{{ formatMoney(tx.amount) }}
                </td>
                <td>
                  <span class="status-badge" :class="tx.status">
                    {{ tx.status === 'completed' ? 'Thành công' : 
                       tx.status === 'pending' ? 'Đang xử lý' : 
                       tx.status === 'cancelled' ? 'Đã hủy' : tx.status }}
                  </span>
                </td>
                <td class="td-desc">{{ tx.description }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="history-pagination">
            <button 
              class="page-nav-btn" 
              :disabled="pagination.current_page === 1" 
              @click="changePage(pagination.current_page - 1)"
            >
              &laquo; Trước
            </button>
            <span class="page-indicator">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button 
              class="page-nav-btn" 
              :disabled="pagination.current_page === pagination.last_page" 
              @click="changePage(pagination.current_page + 1)"
            >
              Sau &raquo;
            </button>
          </div>
        </div>
      </div>

      <!-- ─── TAB CONTENT: USER GUIDE ─────────────────────────────────── -->
      <div v-if="activeTab === 'guide'" class="tab-content guide-tab block-border">
        <h3 class="section-form-title">Giải Đáp & Hướng Dẫn Sử Dụng Ví Tiền</h3>
        <div class="guide-grid">
          <div class="guide-card">
            <h5>1. Làm sao để kiếm tiền vào ví?</h5>
            <p>Hệ thống có chính sách Affiliate (Tiếp thị liên kết). Bạn chỉ cần kích hoạt tài khoản Affiliate, chia sẻ link giới thiệu sản phẩm. Khi bạn bè click vào link và mua hàng thành công, bạn sẽ nhận được <strong>5% hoa hồng</strong> trên tổng giá trị đơn hàng cộng trực tiếp vào ví.</p>
          </div>
          <div class="guide-card">
            <h5>2. Làm sao dùng ví để thanh toán đơn hàng?</h5>
            <p>Tại trang thanh toán khi mua sắm, bên cạnh các phương thức như COD, VNPay, Chuyển khoản, bạn có thể chọn <strong>Ví Tiền (Ocean Pay)</strong>. Hệ thống sẽ tự động trừ toàn bộ số tiền đơn hàng từ số dư ví nếu số dư của bạn đủ dùng.</p>
          </div>
          <div class="guide-card">
            <h5>3. Tiền thanh toán đơn hàng bằng ví có được hoàn khi huỷ đơn?</h5>
            <p><strong>Có!</strong> Khi bạn hủy đơn hàng hoặc yêu cầu trả hàng/hoàn tiền được duyệt, số tiền bạn đã thanh toán bằng ví sẽ được hoàn trả lại ví của bạn ngay lập tức dưới giao dịch "Hoàn tiền".</p>
          </div>
          <div class="guide-card">
            <h5>4. Rút tiền từ ví về tài khoản ngân hàng mất bao lâu?</h5>
            <p>Khi bạn gửi yêu cầu rút tiền mặt, Admin sẽ kiểm tra và thực hiện chuyển khoản trong vòng <strong>24h đến 72h làm việc</strong> (trừ Thứ 7 & Chủ Nhật). Trạng thái giao dịch sẽ cập nhật từ Đang xử lý thành Thành công sau khi chuyển khoản hoàn tất.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ─── CONTAINER & GENERAL ────────────────────────────────────────── */
.wallet-container {
  padding: 8px 0;
  color: #333;
}

.wallet-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 24px;
}

.wallet-page-title {
  font-size: 26px;
  font-weight: 700;
  color: #3e2723;
  margin: 0 0 6px 0;
}

.wallet-page-subtitle {
  font-size: 14px;
  color: #777;
  margin: 0;
}

.affiliate-banner-mini {
  background: #efebe9;
  border: 1px dashed #8d6e63;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: #5d4037;
}

.btn-join-aff {
  background: #8d6e63;
  color: #fff;
  border-radius: 4px;
  padding: 6px 12px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.2s;
}

.btn-join-aff:hover {
  background: #5d4037;
}

/* ─── SUMMARY CARDS ──────────────────────────────────────────────── */
.wallet-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.wallet-card-main {
  background: linear-gradient(135deg, #4e342e 0%, #1a0c0a 100%);
  border-radius: 16px;
  padding: 24px;
  min-height: 190px;
  position: relative;
  overflow: hidden;
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.card-chip-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-chip {
  width: 42px;
  height: 30px;
  background: linear-gradient(135deg, #d4af37 0%, #b8972e 100%);
  border-radius: 4px;
}

.card-logo {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 2px;
  color: rgba(255, 255, 255, 0.85);
}

.card-balance-block {
  margin: 16px 0;
}

.card-balance-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.card-balance-val {
  font-size: 28px;
  font-weight: 700;
  margin: 4px 0 0 0;
  letter-spacing: 0.5px;
}

.card-footer-info {
  display: flex;
  justify-content: space-between;
}

.cf-item {
  display: flex;
  flex-direction: column;
}

.cf-label {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.5);
  text-transform: uppercase;
}

.cf-value {
  font-size: 13px;
  font-weight: 600;
  color: #fff;
}

.card-glow {
  position: absolute;
  top: -50%;
  right: -30%;
  width: 180px;
  height: 180px;
  background: radial-gradient(circle, rgba(141, 110, 99, 0.4) 0%, rgba(255,255,255,0) 70%);
  pointer-events: none;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stat-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-green-light { background: #f0fdf4; }
.bg-blue-light { background: #eff6ff; }

.stat-card-content {
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 13px;
  color: #666;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 4px 0;
}

.text-green { color: #16a34a; }
.text-blue { color: #2563eb; }

.pending-amount-text {
  font-size: 12px;
  font-weight: 500;
  color: #7c2d12;
  display: block;
}

.stat-desc {
  font-size: 11px;
  color: #999;
  margin: 0;
}

/* ─── TABS NAVIGATION ────────────────────────────────────────────── */
.wallet-tabs {
  display: flex;
  border-bottom: 2px solid #efebe9;
  gap: 16px;
  margin-bottom: 20px;
}

.wallet-tab-btn {
  background: none;
  border: none;
  outline: none;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  color: #777;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.wallet-tab-btn:hover {
  color: #8d6e63;
}

.wallet-tab-btn.active {
  color: #5d4037;
  border-bottom-color: #5d4037;
}

.tab-content {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  min-height: 300px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
}

/* ─── WITHDRAW FORM & LAYOUT ────────────────────────────────────── */
.withdraw-section-split {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 30px;
}

@media (max-width: 768px) {
  .withdraw-section-split {
    grid-template-columns: 1fr;
  }
}

.section-form-title {
  font-size: 18px;
  font-weight: 700;
  color: #3e2723;
  margin: 0 0 20px 0;
  border-left: 4px solid #8d6e63;
  padding-left: 10px;
}

.form-group {
  margin-bottom: 18px;
}

.form-group-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 576px) {
  .form-group-row {
    grid-template-columns: 1fr;
  }
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
  color: #444;
}

.method-radio-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.method-radio-card {
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.method-radio-card:hover {
  border-color: #8d6e63;
  background: #fdfbfb;
}

.method-radio-card.selected {
  border-color: #5d4037;
  background: #efebe9;
  color: #5d4037;
  font-weight: 600;
}

.hidden-radio {
  display: none;
}

.method-icon {
  font-size: 20px;
}

.method-label {
  font-size: 13px;
}

.amount-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.amount-input {
  padding-right: 50px !important;
  font-size: 16px !important;
  font-weight: 600;
}

.currency-tag {
  position: absolute;
  right: 16px;
  font-size: 13px;
  font-weight: 600;
  color: #777;
}

.amount-helper-text {
  font-size: 12px;
  color: #888;
  margin: 6px 0 0 0;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #dcd1cd;
  border-radius: 6px;
  outline: none;
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-input:focus {
  border-color: #8d6e63;
}

.text-uppercase {
  text-transform: uppercase;
}

.vnpay-alert-info {
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  color: #1e3a8a;
  padding: 14px;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.5;
}

.btn-submit-withdraw {
  width: 100%;
  padding: 12px;
  border: none;
  outline: none;
  color: #fff;
  font-weight: 600;
  font-size: 15px;
  border-radius: 6px;
  cursor: pointer;
  transition: opacity 0.2s;
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-submit-withdraw:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-brown-gradient {
  background: linear-gradient(135deg, #8d6e63 0%, #5d4037 100%);
}

.btn-brown-gradient:hover {
  background: linear-gradient(135deg, #7b5e54 0%, #4e342e 100%);
}

.withdraw-notes-panel {
  background: #fcfbfb;
  border: 1px solid #e0dbda;
  border-radius: 8px;
  padding: 18px;
  font-size: 12.5px;
  color: #555;
  height: fit-content;
}

.notes-title {
  font-size: 14px;
  font-weight: 700;
  color: #5d4037;
  margin: 0 0 10px 0;
}

.notes-list {
  padding-left: 18px;
  margin: 0;
  line-height: 1.6;
}

.notes-list li {
  margin-bottom: 8px;
}

/* ─── TRANSACTION HISTORY TAB ──────────────────────────────────── */
.history-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 0;
}

.history-empty {
  text-align: center;
  padding: 40px 20px;
  color: #999;
}

.empty-icon {
  font-size: 40px;
  display: block;
  margin-bottom: 10px;
}

.history-table-wrapper {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
  text-align: left;
}

.history-table th,
.history-table td {
  padding: 12px;
  border-bottom: 1px solid #efebe9;
}

.history-table th {
  font-weight: 700;
  color: #5d4037;
  background: #fbf9f9;
}

.td-date {
  color: #666;
  white-space: nowrap;
}

.td-id {
  font-family: monospace;
  font-size: 12.5px;
  color: #888;
}

.type-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.type-badge.deposit { background: #e0f2fe; color: #0369a1; }
.type-badge.spend { background: #fef3c7; color: #b45309; }
.type-badge.refund { background: #ecfdf5; color: #047857; }
.type-badge.withdraw { background: #fee2e2; color: #b91c1c; }
.type-badge.commission { background: #f0fdf4; color: #16a34a; }

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

.status-badge.completed { background: #d1fae5; color: #065f46; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.td-desc {
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #555;
}

.history-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
}

.page-nav-btn {
  background: #fff;
  border: 1px solid #dcd1cd;
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.page-nav-btn:hover:not(:disabled) {
  border-color: #8d6e63;
  color: #8d6e63;
}

.page-nav-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-indicator {
  font-size: 13px;
  color: #666;
}

/* ─── USER GUIDE TAB ────────────────────────────────────────────── */
.guide-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-top: 10px;
}

@media (max-width: 768px) {
  .guide-grid {
    grid-template-columns: 1fr;
  }
}

.guide-card {
  background: #fdfbfb;
  border: 1px solid #efebe9;
  border-radius: 8px;
  padding: 16px;
  transition: box-shadow 0.2s;
}

.guide-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.guide-card h5 {
  font-size: 14px;
  font-weight: 700;
  color: #5d4037;
  margin: 0 0 8px 0;
}

.guide-card p {
  font-size: 12.5px;
  line-height: 1.6;
  color: #666;
  margin: 0;
}

/* ─── ANIMATIONS & SHADOWS ──────────────────────────────────────── */
.block-border {
  border: 1.5px solid #efebe9;
}

.animate-in {
  animation: fadeInUp 0.4s ease forwards;
  opacity: 0;
}

@keyframes fadeInUp {
  from {
    transform: translateY(12px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(141, 110, 99, 0.1);
  border-radius: 50%;
  border-top-color: #8d6e63;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

.spinner-inline {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  border-top-color: #fff;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
