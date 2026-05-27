<script setup>
import { ref, computed, onMounted } from 'vue';
import { affiliateService } from '@/services/affiliateService';
import { useToast } from '@/composables/useToast';

const { showToast } = useToast();

// --- State ---
const loading = ref(true);
const profile = ref(null);
const isAffiliate = computed(() => profile.value?.is_affiliate === true);

// Statistics
const statsType = ref('month');
const statsData = ref([]);
const loadingStats = ref(false);

// Conversions
const conversions = ref([]);
const loadingConversions = ref(false);

// Withdrawals
const withdrawals = ref([]);
const loadingWithdrawals = ref(false);
const showWithdrawForm = ref(false);
const submittingWithdraw = ref(false);
const withdrawForm = ref({
  amount: '',
  bank_name: '',
  bank_account_name: '',
  bank_account_number: '',
});

// Registration
const registering = ref(false);

// Copy
const copied = ref('');

// --- Format ---
const formatPrice = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const formatDate = (val) => {
  if (!val) return '—';
  return new Date(val).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const statusLabel = (status) => {
  const map = {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    cancelled: 'Đã hủy',
    paid: 'Đã thanh toán',
    rejected: 'Từ chối',
  };
  return map[status] || status;
};

const statusClass = (status) => {
  const map = {
    pending: 'status-pending',
    approved: 'status-approved',
    cancelled: 'status-cancelled',
    paid: 'status-paid',
    rejected: 'status-cancelled',
  };
  return map[status] || '';
};

// --- Computed ---
const referralLink = computed(() => {
  if (!profile.value?.referral_code) return '';
  const base = window.location.origin;
  return `${base}/product?ref=${profile.value.referral_code}`;
});

const availableBalance = computed(() => {
  if (!profile.value?.summary) return 0;
  const s = profile.value.summary;
  // Available = approved + paid commission - total withdrawn
  return Math.max(0, (s.approved_commission || 0) + (s.paid_commission || 0));
});

// --- Actions ---
const fetchProfile = async () => {
  try {
    const res = await affiliateService.getProfile();
    if (res.data?.status) {
      profile.value = res.data.data;
    }
  } catch (e) {
    console.error('Fetch affiliate profile error:', e);
  }
};

const fetchStatistics = async () => {
  loadingStats.value = true;
  try {
    const res = await affiliateService.getStatistics(statsType.value);
    if (res.data?.status) {
      statsData.value = res.data.data?.data || [];
    }
  } catch (e) {
    console.error('Fetch statistics error:', e);
  } finally {
    loadingStats.value = false;
  }
};

const fetchConversions = async () => {
  loadingConversions.value = true;
  try {
    const res = await affiliateService.getConversions();
    if (res.data?.status) {
      const raw = res.data.data;
      conversions.value = raw?.data || raw || [];
    }
  } catch (e) {
    console.error('Fetch conversions error:', e);
  } finally {
    loadingConversions.value = false;
  }
};

const fetchWithdrawals = async () => {
  loadingWithdrawals.value = true;
  try {
    const res = await affiliateService.getWithdrawals();
    if (res.data?.status) {
      withdrawals.value = res.data.data || [];
    }
  } catch (e) {
    console.error('Fetch withdrawals error:', e);
  } finally {
    loadingWithdrawals.value = false;
  }
};

const registerAffiliate = async () => {
  registering.value = true;
  try {
    const res = await affiliateService.register();
    if (res.data?.status) {
      showToast('Đăng ký affiliate thành công!', 'success');
      await fetchProfile();
      await loadAffiliateData();
    } else {
      showToast(res.data?.message || 'Lỗi đăng ký!', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi đăng ký affiliate!', 'error');
  } finally {
    registering.value = false;
  }
};

const copyToClipboard = async (text, label) => {
  try {
    await navigator.clipboard.writeText(text);
    copied.value = label;
    showToast('Đã sao chép!', 'success');
    setTimeout(() => { copied.value = ''; }, 2000);
  } catch {
    showToast('Không thể sao chép!', 'error');
  }
};

const changeStatsType = (type) => {
  statsType.value = type;
  fetchStatistics();
};

const submitWithdrawal = async () => {
  if (!withdrawForm.value.amount || !withdrawForm.value.bank_name || !withdrawForm.value.bank_account_name || !withdrawForm.value.bank_account_number) {
    showToast('Vui lòng điền đầy đủ thông tin!', 'error');
    return;
  }
  submittingWithdraw.value = true;
  try {
    const res = await affiliateService.requestWithdrawal(withdrawForm.value);
    if (res.data?.status) {
      showToast('Gửi yêu cầu rút tiền thành công!', 'success');
      showWithdrawForm.value = false;
      withdrawForm.value = { amount: '', bank_name: '', bank_account_name: '', bank_account_number: '' };
      await fetchWithdrawals();
      await fetchProfile();
    } else {
      showToast(res.data?.message || 'Lỗi gửi yêu cầu!', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.message || 'Lỗi gửi yêu cầu rút tiền!', 'error');
  } finally {
    submittingWithdraw.value = false;
  }
};

const loadAffiliateData = async () => {
  await Promise.all([fetchStatistics(), fetchConversions(), fetchWithdrawals()]);
};

onMounted(async () => {
  await fetchProfile();
  if (isAffiliate.value) {
    await loadAffiliateData();
  }
  loading.value = false;
});
</script>

<template>
  <div class="aff-page">
    <!-- LOADING -->
    <div v-if="loading" class="aff-loading">
      <div class="aff-spinner"></div>
      <p>Đang tải thông tin affiliate...</p>
    </div>

    <template v-else>
      <!-- ======================== CHƯA ĐĂNG KÝ ======================== -->
      <div v-if="!isAffiliate" class="aff-register-section">
        <div class="aff-register-card">
          <div class="aff-register-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#E63B6F" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
          </div>
          <h2>Chương trình Affiliate</h2>
          <p>Giới thiệu bạn bè mua hàng và nhận hoa hồng <strong>{{ 5 }}%</strong> trên mỗi đơn hàng thành công.</p>
          <ul class="aff-benefits">
            <li>✅ Nhận mã giới thiệu riêng</li>
            <li>✅ Theo dõi lượt click & đơn hàng</li>
            <li>✅ Thống kê hoa hồng chi tiết</li>
            <li>✅ Rút tiền hoa hồng dễ dàng</li>
          </ul>
          <button class="aff-btn-register" @click="registerAffiliate" :disabled="registering">
            <span v-if="registering" class="aff-btn-spinner"></span>
            <span v-else>Đăng ký ngay</span>
          </button>
        </div>
      </div>

      <!-- ======================== ĐÃ ĐĂNG KÝ ======================== -->
      <template v-else>

        <!-- HEADER INFO -->
        <div class="aff-info-card">
          <div class="aff-info-left">
            <h2>Chương trình Affiliate</h2>
            <p class="aff-registered-date">Tham gia từ: {{ formatDate(profile.affiliate_registered_at) }}</p>
          </div>
          <div class="aff-info-right">
            <div class="aff-code-group">
              <label>Mã giới thiệu</label>
              <div class="aff-code-box">
                <span class="aff-code">{{ profile.referral_code }}</span>
                <button class="aff-btn-copy" @click="copyToClipboard(profile.referral_code, 'code')" :class="{ copied: copied === 'code' }">
                  {{ copied === 'code' ? '✓' : 'Copy' }}
                </button>
              </div>
            </div>
            <div class="aff-link-group">
              <label>Link giới thiệu</label>
              <div class="aff-code-box">
                <span class="aff-link-text">{{ referralLink }}</span>
                <button class="aff-btn-copy" @click="copyToClipboard(referralLink, 'link')" :class="{ copied: copied === 'link' }">
                  {{ copied === 'link' ? '✓' : 'Copy' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- DASHBOARD CARDS -->
        <div class="aff-cards-grid">
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-blue">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ profile.total_clicks || 0 }}</span>
              <span class="aff-stat-label">Lượt click</span>
            </div>
          </div>
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-green">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ profile.summary?.total_conversions || 0 }}</span>
              <span class="aff-stat-label">Lượt mua</span>
            </div>
          </div>
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-purple">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ formatPrice(profile.summary?.total_revenue) }}</span>
              <span class="aff-stat-label">Doanh thu</span>
            </div>
          </div>
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-yellow">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ formatPrice(profile.summary?.pending_commission) }}</span>
              <span class="aff-stat-label">Chờ duyệt</span>
            </div>
          </div>
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-teal">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ formatPrice(profile.summary?.approved_commission) }}</span>
              <span class="aff-stat-label">Đã duyệt</span>
            </div>
          </div>
          <div class="aff-stat-card">
            <div class="aff-stat-icon bg-pink">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <div class="aff-stat-info">
              <span class="aff-stat-value">{{ formatPrice(profile.summary?.paid_commission) }}</span>
              <span class="aff-stat-label">Đã thanh toán</span>
            </div>
          </div>
        </div>

        <!-- STATISTICS TABLE -->
        <div class="aff-section">
          <div class="aff-section-header">
            <h3>Thống kê hoa hồng</h3>
            <div class="aff-filter-group">
              <button v-for="t in ['day','month','year']" :key="t" class="aff-filter-btn" :class="{ active: statsType === t }" @click="changeStatsType(t)">
                {{ t === 'day' ? 'Ngày' : t === 'month' ? 'Tháng' : 'Năm' }}
              </button>
            </div>
          </div>
          <div v-if="loadingStats" class="aff-table-loading"><div class="aff-spinner-sm"></div></div>
          <div v-else-if="statsData.length === 0" class="aff-empty">Chưa có dữ liệu thống kê.</div>
          <div v-else class="aff-table-wrap">
            <table class="aff-table">
              <thead>
                <tr>
                  <th>Thời gian</th>
                  <th>Số lượt mua</th>
                  <th>Doanh thu</th>
                  <th>Hoa hồng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in statsData" :key="row.period">
                  <td>{{ row.period }}</td>
                  <td>{{ row.total_orders }}</td>
                  <td>{{ formatPrice(row.total_revenue) }}</td>
                  <td class="aff-commission-cell">{{ formatPrice(row.total_commission) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CONVERSIONS TABLE -->
        <div class="aff-section">
          <div class="aff-section-header">
            <h3>Đơn hàng Affiliate</h3>
          </div>
          <div v-if="loadingConversions" class="aff-table-loading"><div class="aff-spinner-sm"></div></div>
          <div v-else-if="conversions.length === 0" class="aff-empty">Chưa có đơn hàng phát sinh hoa hồng.</div>
          <div v-else class="aff-table-wrap">
            <table class="aff-table">
              <thead>
                <tr>
                  <th>Mã đơn</th>
                  <th>Người mua</th>
                  <th>Tổng tiền</th>
                  <th>Tỷ lệ</th>
                  <th>Hoa hồng</th>
                  <th>Trạng thái</th>
                  <th>Ngày</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in conversions" :key="c.id">
                  <td class="aff-code-cell">{{ c.order?.order_code || '—' }}</td>
                  <td>{{ c.buyer?.full_name || '—' }}</td>
                  <td>{{ formatPrice(c.total_amount) }}</td>
                  <td>{{ c.commission_rate }}%</td>
                  <td class="aff-commission-cell">{{ formatPrice(c.commission_amount) }}</td>
                  <td><span class="aff-status" :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span></td>
                  <td>{{ formatDate(c.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- WITHDRAWALS -->
        <div class="aff-section">
          <div class="aff-section-header">
            <h3>Rút tiền hoa hồng</h3>
            <button class="aff-btn-withdraw" @click="showWithdrawForm = !showWithdrawForm">
              {{ showWithdrawForm ? 'Đóng' : '+ Yêu cầu rút tiền' }}
            </button>
          </div>

          <!-- WITHDRAW FORM -->
          <div v-if="showWithdrawForm" class="aff-withdraw-form">
            <div class="aff-form-row">
              <div class="aff-form-group">
                <label>Số tiền muốn rút (VND)</label>
                <input v-model.number="withdrawForm.amount" type="number" placeholder="Ví dụ: 500000" />
              </div>
              <div class="aff-form-group">
                <label>Tên ngân hàng</label>
                <input v-model="withdrawForm.bank_name" type="text" placeholder="Ví dụ: Vietcombank" />
              </div>
            </div>
            <div class="aff-form-row">
              <div class="aff-form-group">
                <label>Tên chủ tài khoản</label>
                <input v-model="withdrawForm.bank_account_name" type="text" placeholder="Ví dụ: NGUYEN VAN A" />
              </div>
              <div class="aff-form-group">
                <label>Số tài khoản</label>
                <input v-model="withdrawForm.bank_account_number" type="text" placeholder="Ví dụ: 1234567890" />
              </div>
            </div>
            <button class="aff-btn-submit" @click="submitWithdrawal" :disabled="submittingWithdraw">
              <span v-if="submittingWithdraw" class="aff-btn-spinner"></span>
              <span v-else>Gửi yêu cầu</span>
            </button>
          </div>

          <!-- WITHDRAWAL HISTORY -->
          <div v-if="loadingWithdrawals" class="aff-table-loading"><div class="aff-spinner-sm"></div></div>
          <div v-else-if="withdrawals.length === 0 && !showWithdrawForm" class="aff-empty">Chưa có yêu cầu rút tiền nào.</div>
          <div v-else-if="withdrawals.length > 0" class="aff-table-wrap">
            <table class="aff-table">
              <thead>
                <tr>
                  <th>Số tiền</th>
                  <th>Ngân hàng</th>
                  <th>Chủ TK</th>
                  <th>Số TK</th>
                  <th>Trạng thái</th>
                  <th>Ghi chú</th>
                  <th>Ngày</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="w in withdrawals" :key="w.id">
                  <td class="aff-commission-cell">{{ formatPrice(w.amount) }}</td>
                  <td>{{ w.bank_name }}</td>
                  <td>{{ w.bank_account_name }}</td>
                  <td>{{ w.bank_account_number }}</td>
                  <td><span class="aff-status" :class="statusClass(w.status)">{{ statusLabel(w.status) }}</span></td>
                  <td>{{ w.note || '—' }}</td>
                  <td>{{ formatDate(w.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </template>
    </template>
  </div>
</template>

<style scoped>
.aff-page {
  font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
  color: #1e293b;
}

/* Loading */
.aff-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 0;
  gap: 12px;
  color: #94a3b8;
}
.aff-spinner {
  width: 32px; height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.aff-spinner-sm {
  width: 20px; height: 20px;
  border: 2px solid #e2e8f0;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Register Section */
.aff-register-section {
  display: flex;
  justify-content: center;
  padding: 20px 0;
}
.aff-register-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  max-width: 500px;
  width: 100%;
}
.aff-register-icon {
  margin-bottom: 16px;
}
.aff-register-card h2 {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0 0 8px;
  color: #1e293b;
}
.aff-register-card p {
  color: #64748b;
  font-size: 0.95rem;
  margin: 0 0 20px;
  line-height: 1.6;
}
.aff-benefits {
  list-style: none;
  padding: 0;
  margin: 0 0 24px;
  text-align: left;
}
.aff-benefits li {
  padding: 6px 0;
  font-size: 0.9rem;
  color: #475569;
}
.aff-btn-register {
  background: #E63B6F;
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 14px 32px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.aff-btn-register:hover { background: #d1305f; }
.aff-btn-register:disabled { opacity: 0.6; cursor: not-allowed; }
.aff-btn-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

/* Info Card */
.aff-info-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.aff-info-left h2 {
  font-size: 1.2rem;
  font-weight: 700;
  margin: 0 0 4px;
}
.aff-registered-date {
  color: #94a3b8;
  font-size: 0.85rem;
  margin: 0;
}
.aff-info-right {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.aff-code-group label,
.aff-link-group label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: block;
  margin-bottom: 4px;
}
.aff-code-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 12px;
}
.aff-code {
  font-weight: 700;
  font-size: 1rem;
  color: #E63B6F;
  letter-spacing: 1px;
}
.aff-link-text {
  font-size: 0.82rem;
  color: #475569;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.aff-btn-copy {
  background: #E63B6F;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 6px 14px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.aff-btn-copy:hover { background: #d1305f; }
.aff-btn-copy.copied { background: #10b981; }

/* Dashboard Cards */
.aff-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.aff-stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 18px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  transition: box-shadow 0.2s;
}
.aff-stat-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.aff-stat-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.bg-blue { background: #dbeafe; color: #2563eb; }
.bg-green { background: #dcfce7; color: #16a34a; }
.bg-purple { background: #ede9fe; color: #7c3aed; }
.bg-yellow { background: #fef9c3; color: #ca8a04; }
.bg-teal { background: #ccfbf1; color: #0d9488; }
.bg-pink { background: #fce7f3; color: #E63B6F; }
.aff-stat-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.aff-stat-value {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.aff-stat-label {
  font-size: 0.78rem;
  color: #94a3b8;
  margin-top: 2px;
}

/* Sections */
.aff-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 20px;
}
.aff-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}
.aff-section-header h3 {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
}

/* Filter */
.aff-filter-group {
  display: flex;
  gap: 6px;
}
.aff-filter-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 16px;
  font-size: 0.82rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}
.aff-filter-btn.active {
  background: #E63B6F;
  color: #fff;
  border-color: #E63B6F;
}
.aff-filter-btn:hover:not(.active) {
  background: #e2e8f0;
}

/* Table */
.aff-table-wrap {
  overflow-x: auto;
}
.aff-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.aff-table th {
  text-align: left;
  padding: 10px 12px;
  background: #f8fafc;
  color: #64748b;
  font-weight: 600;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.aff-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  color: #475569;
}
.aff-table tbody tr:hover {
  background: #fafbfc;
}
.aff-code-cell {
  font-family: 'SFMono-Regular', Consolas, monospace;
  font-weight: 600;
  font-size: 0.82rem;
  color: #1e293b;
}
.aff-commission-cell {
  font-weight: 600;
  color: #E63B6F;
}

/* Status badges */
.aff-status {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}
.status-pending { background: #fef9c3; color: #a16207; }
.status-approved { background: #dcfce7; color: #15803d; }
.status-cancelled { background: #fee2e2; color: #dc2626; }
.status-paid { background: #dbeafe; color: #1d4ed8; }

/* Empty */
.aff-empty {
  text-align: center;
  padding: 32px;
  color: #94a3b8;
  font-size: 0.9rem;
}

.aff-table-loading {
  display: flex;
  justify-content: center;
  padding: 24px;
}

/* Withdraw */
.aff-btn-withdraw {
  background: #E63B6F;
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 8px 18px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.aff-btn-withdraw:hover { background: #d1305f; }
.aff-withdraw-form {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}
.aff-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 14px;
}
.aff-form-group label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 6px;
}
.aff-form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.9rem;
  color: #1e293b;
  background: #fff;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.aff-form-group input:focus {
  outline: none;
  border-color: #E63B6F;
  box-shadow: 0 0 0 3px rgba(230,59,111,0.1);
}
.aff-btn-submit {
  background: #E63B6F;
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 12px 28px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.aff-btn-submit:hover { background: #d1305f; }
.aff-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

/* Responsive */
@media (max-width: 768px) {
  .aff-info-card {
    flex-direction: column;
  }
  .aff-info-right {
    flex-direction: column;
    width: 100%;
  }
  .aff-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .aff-form-row {
    grid-template-columns: 1fr;
  }
  .aff-link-text {
    max-width: 180px;
  }
}

@media (max-width: 480px) {
  .aff-cards-grid {
    grid-template-columns: 1fr;
  }
  .aff-section {
    padding: 16px;
  }
}
</style>
