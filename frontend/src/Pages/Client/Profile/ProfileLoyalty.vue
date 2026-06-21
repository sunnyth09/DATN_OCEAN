<script setup>
import { ref, computed, onMounted } from 'vue';
import { loyaltyService } from '@/services/loyaltyService';
import { useToast } from '@/composables/useToast';

const { showToast } = useToast();

// ── State ──────────────────────────────────────────────────────────
const loading = ref(true);
const summary = ref(null);
const history = ref([]);
const historyMeta = ref(null);
const loadingHistory = ref(false);
const activeFilter = ref('all');
const currentPage = ref(1);

// ── Earn events list ───────────────────────────────────────────────
const earnEvents = [
  { icon: '🛒', label: 'Mua hàng thành công',          points: '+1 điểm / 10.000đ', color: '#10b981' },
  { icon: '⭐', label: 'Đánh giá sản phẩm có nội dung', points: '+20 điểm',           color: '#f59e0b' },
  { icon: '📸', label: 'Đánh giá kèm hình ảnh',         points: '+50 điểm',           color: '#8b5cf6' },
  { icon: '🎂', label: 'Sinh nhật khách hàng',          points: '+100 điểm',          color: '#ec4899' },
  { icon: '👥', label: 'Giới thiệu bạn bè thành công',  points: '+200 điểm',          color: '#3b82f6' },
  { icon: '🛍️', label: 'Hoàn tất đơn bỏ quên giỏ',    points: '+30 điểm',           color: '#f97316' },
  { icon: '📣', label: 'Chia sẻ sản phẩm mạng xã hội', points: '+30 điểm',           color: '#06b6d4' },
];

// ── Computed ───────────────────────────────────────────────────────
const currentBalance = computed(() => summary.value?.current_balance ?? 0);
const totalEarned    = computed(() => summary.value?.total_earned ?? 0);
const totalBurned    = computed(() => summary.value?.total_burned ?? 0);
const expiringSoon   = computed(() => summary.value?.expiring_soon ?? 0);

const filteredHistory = computed(() => history.value);

const balanceInVND = computed(() => {
  // 10 điểm = 1.000đ → 1 điểm = 100đ
  return currentBalance.value * 100;
});

// ── Methods ────────────────────────────────────────────────────────
const formatPoints = (n) => {
  return new Intl.NumberFormat('vi-VN').format(n ?? 0);
};

const formatMoney = (n) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(n ?? 0);
};

const formatDate = (val) => {
  if (!val) return '—';
  return new Date(val).toLocaleDateString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const typeIcon = (type) => ({
  earn:   '➕',
  burn:   '➖',
  expire: '⏰',
  adjust: '✏️',
  refund: '↩️',
}[type] ?? '📊');

const typeColor = (type) => ({
  earn:   '#10b981',
  burn:   '#ef4444',
  expire: '#f59e0b',
  adjust: '#6366f1',
  refund: '#3b82f6',
}[type] ?? '#64748b');

const typeBg = (type) => ({
  earn:   '#ecfdf5',
  burn:   '#fef2f2',
  expire: '#fffbeb',
  adjust: '#eef2ff',
  refund: '#eff6ff',
}[type] ?? '#f8fafc');

const signedPoints = (tx) => {
  const sign = ['earn', 'refund', 'adjust'].includes(tx.type) ? '+' : '-';
  return `${sign}${formatPoints(tx.points)}`;
};

const pointSignColor = (tx) => {
  return ['earn', 'refund', 'adjust'].includes(tx.type) ? '#10b981' : '#ef4444';
};

// ── Fetch ──────────────────────────────────────────────────────────
const fetchSummary = async () => {
  try {
    const res = await loyaltyService.getSummary();
    if (res.data?.status === 'success') {
      summary.value = res.data.data;
    }
  } catch (e) {
    console.error('Fetch summary error:', e);
  }
};

const fetchHistory = async (page = 1) => {
  loadingHistory.value = true;
  try {
    const type = activeFilter.value === 'all' ? undefined : activeFilter.value;
    const res = await loyaltyService.getHistory({ page, per_page: 15, type });
    if (res.data?.status === 'success') {
      const data = res.data.data;
      history.value = data.data ?? [];
      historyMeta.value = {
        current_page: data.current_page,
        last_page:    data.last_page,
        total:        data.total,
      };
      currentPage.value = page;
    }
  } catch (e) {
    console.error('Fetch history error:', e);
  } finally {
    loadingHistory.value = false;
  }
};

const changeFilter = (f) => {
  activeFilter.value = f;
  fetchHistory(1);
};

const prevPage = () => {
  if (currentPage.value > 1) fetchHistory(currentPage.value - 1);
};
const nextPage = () => {
  if (historyMeta.value && currentPage.value < historyMeta.value.last_page) {
    fetchHistory(currentPage.value + 1);
  }
};

onMounted(async () => {
  await Promise.all([fetchSummary(), fetchHistory()]);
  loading.value = false;
});
</script>

<template>
  <div class="loyalty-page">

    <!-- ── LOADING ─────────────────────────────────────────── -->
    <div v-if="loading" class="lp-loading">
      <div class="lp-spinner"></div>
      <p>Đang tải dữ liệu điểm thưởng...</p>
    </div>

    <template v-else>

      <!-- ── HERO BALANCE CARD ────────────────────────────────── -->
      <div class="lp-hero">
        <div class="lp-hero-left">
          <div class="lp-coin-icon">🏆</div>
          <div>
            <p class="lp-hero-label">Điểm thưởng hiện tại</p>
            <div class="lp-hero-balance">
              <span class="lp-balance-num">{{ formatPoints(currentBalance) }}</span>
              <span class="lp-balance-unit">điểm</span>
            </div>
            <p class="lp-hero-vnd">≈ {{ formatMoney(balanceInVND) }} giảm giá</p>
          </div>
        </div>
        <div class="lp-hero-stats">
          <div class="lp-stat-chip">
            <span class="lp-stat-icon" style="color:#10b981">↑</span>
            <div>
              <div class="lp-stat-num">{{ formatPoints(totalEarned) }}</div>
              <div class="lp-stat-lbl">Tổng tích lũy</div>
            </div>
          </div>
          <div class="lp-stat-chip">
            <span class="lp-stat-icon" style="color:#ef4444">↓</span>
            <div>
              <div class="lp-stat-num">{{ formatPoints(totalBurned) }}</div>
              <div class="lp-stat-lbl">Đã sử dụng</div>
            </div>
          </div>
          <div class="lp-stat-chip" v-if="expiringSoon > 0">
            <span class="lp-stat-icon" style="color:#f59e0b">⏰</span>
            <div>
              <div class="lp-stat-num" style="color:#f59e0b">{{ formatPoints(expiringSoon) }}</div>
              <div class="lp-stat-lbl">Sắp hết hạn</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── CÁCH QUY ĐỔI ─────────────────────────────────────── -->
      <div class="lp-exchange-bar">
        <span class="lp-ex-item">💡 Quy đổi:</span>
        <span class="lp-ex-item">10 điểm = <strong>1.000đ</strong></span>
        <span class="lp-ex-sep">•</span>
        <span class="lp-ex-item">100 điểm = <strong>10.000đ</strong></span>
        <span class="lp-ex-sep">•</span>
        <span class="lp-ex-item">1.000 điểm = <strong>100.000đ</strong></span>
        <span class="lp-ex-sep">•</span>
        <span class="lp-ex-item">Tối thiểu <strong>100 điểm</strong> để đổi</span>
      </div>

      <!-- ── CÁCH KIẾM ĐIỂM ──────────────────────────────────── -->
      <div class="lp-section">
        <div class="lp-section-header">
          <span class="lp-section-icon">✨</span>
          <h3 class="lp-section-title">Cách kiếm điểm thưởng</h3>
        </div>
        <div class="lp-earn-grid">
          <div
            v-for="ev in earnEvents"
            :key="ev.label"
            class="lp-earn-card"
            :style="{ '--ev-color': ev.color }"
          >
            <div class="lp-earn-emoji">{{ ev.icon }}</div>
            <div class="lp-earn-info">
              <div class="lp-earn-label">{{ ev.label }}</div>
              <div class="lp-earn-pts">{{ ev.points }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── LỊCH SỬ GIAO DỊCH ─────────────────────────────────── -->
      <div class="lp-section">
        <div class="lp-section-header">
          <span class="lp-section-icon">📋</span>
          <h3 class="lp-section-title">Lịch sử tích điểm</h3>
          <!-- Filter -->
          <div class="lp-filter-group">
            <button
              v-for="f in [
                { key: 'all',    label: 'Tất cả'  },
                { key: 'earn',   label: '+ Nhận'  },
                { key: 'burn',   label: '- Dùng'  },
                { key: 'expire', label: '⏰ Hết hạn' },
              ]"
              :key="f.key"
              class="lp-filter-btn"
              :class="{ active: activeFilter === f.key }"
              @click="changeFilter(f.key)"
            >
              {{ f.label }}
            </button>
          </div>
        </div>

        <!-- History loading -->
        <div v-if="loadingHistory" class="lp-history-loading">
          <div class="lp-spinner-sm"></div>
        </div>

        <!-- Empty -->
        <div v-else-if="history.length === 0" class="lp-empty">
          <div class="lp-empty-icon">📭</div>
          <p>Chưa có giao dịch điểm nào.</p>
        </div>

        <!-- Table -->
        <div v-else class="lp-history-wrap">
          <div
            v-for="tx in history"
            :key="tx.id"
            class="lp-tx-row"
            :style="{ '--tx-bg': typeBg(tx.type) }"
          >
            <div class="lp-tx-icon" :style="{ background: typeBg(tx.type), color: typeColor(tx.type) }">
              {{ typeIcon(tx.type) }}
            </div>
            <div class="lp-tx-info">
              <div class="lp-tx-desc">{{ tx.description }}</div>
              <div class="lp-tx-meta">
                <span class="lp-tx-badge" :style="{ background: typeBg(tx.type), color: typeColor(tx.type) }">
                  {{ tx.type_label }}
                </span>
                <span class="lp-tx-date">{{ formatDate(tx.created_at) }}</span>
                <span v-if="tx.expires_at && !tx.is_expired" class="lp-tx-expire">
                  hết hạn {{ formatDate(tx.expires_at) }}
                </span>
                <span v-if="tx.is_expired" class="lp-tx-expired-tag">Đã hết hạn</span>
              </div>
            </div>
            <div class="lp-tx-right">
              <div class="lp-tx-points" :style="{ color: pointSignColor(tx) }">
                {{ signedPoints(tx) }}
              </div>
              <div class="lp-tx-balance">Số dư: {{ formatPoints(tx.balance_after) }}</div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="historyMeta && historyMeta.last_page > 1" class="lp-pagination">
          <button class="lp-page-btn" :disabled="currentPage <= 1" @click="prevPage">← Trước</button>
          <span class="lp-page-info">{{ currentPage }} / {{ historyMeta.last_page }}</span>
          <button class="lp-page-btn" :disabled="currentPage >= historyMeta.last_page" @click="nextPage">Sau →</button>
        </div>
      </div>

      <!-- ── HƯỚNG DẪN SỬ DỤNG ───────────────────────────────── -->
      <div class="lp-section lp-guide">
        <div class="lp-section-header">
          <span class="lp-section-icon">💳</span>
          <h3 class="lp-section-title">Cách sử dụng điểm khi thanh toán</h3>
        </div>
        <div class="lp-guide-steps">
          <div class="lp-step">
            <div class="lp-step-num">1</div>
            <div class="lp-step-text">Vào trang <strong>Thanh toán</strong> khi đặt đơn hàng</div>
          </div>
          <div class="lp-step-arrow">→</div>
          <div class="lp-step">
            <div class="lp-step-num">2</div>
            <div class="lp-step-text">Tích chọn <strong>"Dùng điểm thưởng"</strong> và nhập số điểm muốn dùng</div>
          </div>
          <div class="lp-step-arrow">→</div>
          <div class="lp-step">
            <div class="lp-step-num">3</div>
            <div class="lp-step-text">Hệ thống tự động <strong>trừ tiền</strong> tương ứng khỏi tổng đơn</div>
          </div>
        </div>
        <div class="lp-guide-note">
          📌 Tối thiểu <strong>100 điểm</strong> để đổi giảm giá. Tối đa <strong>30%</strong> giá trị đơn hàng. Điểm có hiệu lực <strong>365 ngày</strong> kể từ ngày tích.
        </div>
      </div>

    </template>
  </div>
</template>

<style scoped>
/* ── Base ─────────────────────────────────────────────────────── */
.loyalty-page {
  font-family: var(--font-jakarta, 'Plus Jakarta Sans', sans-serif);
  color: #1e293b;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Loading ──────────────────────────────────────────────────── */
.lp-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 0;
  gap: 12px;
  color: #94a3b8;
}
.lp-spinner {
  width: 36px; height: 36px;
  border: 3px solid #e2e8f0;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.lp-spinner-sm {
  width: 22px; height: 22px;
  border: 3px solid #e2e8f0;
  border-top-color: #E63B6F;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 30px auto;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Hero Card ────────────────────────────────────────────────── */
.lp-hero {
  background: linear-gradient(135deg, #E63B6F 0%, #c0195a 40%, #7c1d85 100%);
  border-radius: 20px;
  padding: 28px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  color: #fff;
  box-shadow: 0 8px 32px rgba(230, 59, 111, 0.3);
}
.lp-hero-left {
  display: flex;
  align-items: center;
  gap: 20px;
}
.lp-coin-icon {
  font-size: 3.5rem;
  line-height: 1;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
  animation: float 3s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-6px); }
}
.lp-hero-label {
  font-size: 0.85rem;
  opacity: 0.85;
  margin: 0 0 4px;
  font-weight: 500;
  letter-spacing: 0.3px;
}
.lp-hero-balance {
  display: flex;
  align-items: baseline;
  gap: 8px;
  line-height: 1;
}
.lp-balance-num {
  font-size: 2.6rem;
  font-weight: 800;
  letter-spacing: -1px;
}
.lp-balance-unit {
  font-size: 1.1rem;
  font-weight: 600;
  opacity: 0.85;
}
.lp-hero-vnd {
  font-size: 0.85rem;
  opacity: 0.75;
  margin: 6px 0 0;
}

.lp-hero-stats {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.lp-stat-chip {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(8px);
  border-radius: 14px;
  padding: 12px 18px;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 110px;
}
.lp-stat-icon {
  font-size: 1.4rem;
  line-height: 1;
}
.lp-stat-num {
  font-size: 1.15rem;
  font-weight: 700;
  color: #fff;
}
.lp-stat-lbl {
  font-size: 0.72rem;
  opacity: 0.8;
  color: #fff;
  margin-top: 1px;
}

/* ── Exchange Bar ─────────────────────────────────────────────── */
.lp-exchange-bar {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 0.87rem;
  color: #475569;
}
.lp-ex-item strong { color: #E63B6F; }
.lp-ex-sep { color: #cbd5e1; }

/* ── Section ──────────────────────────────────────────────────── */
.lp-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 22px 24px;
}
.lp-section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.lp-section-icon {
  font-size: 1.2rem;
}
.lp-section-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  flex: 1;
}

/* ── Earn Events Grid ─────────────────────────────────────────── */
.lp-earn-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}
.lp-earn-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #fafbfc;
  transition: all 0.2s;
  cursor: default;
}
.lp-earn-card:hover {
  border-color: var(--ev-color, #E63B6F);
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}
.lp-earn-emoji {
  font-size: 1.7rem;
  line-height: 1;
  flex-shrink: 0;
}
.lp-earn-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #334155;
  line-height: 1.3;
}
.lp-earn-pts {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--ev-color, #10b981);
  margin-top: 3px;
}

/* ── Filter ───────────────────────────────────────────────────── */
.lp-filter-group {
  display: flex;
  gap: 6px;
  margin-left: auto;
}
.lp-filter-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 5px 14px;
  font-size: 0.8rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}
.lp-filter-btn.active {
  background: #E63B6F;
  color: #fff;
  border-color: #E63B6F;
}
.lp-filter-btn:hover:not(.active) {
  background: #e2e8f0;
}

/* ── History ──────────────────────────────────────────────────── */
.lp-history-loading {
  text-align: center;
  padding: 20px 0;
}
.lp-empty {
  text-align: center;
  padding: 40px 0;
  color: #94a3b8;
}
.lp-empty-icon {
  font-size: 2.5rem;
  margin-bottom: 8px;
}

.lp-history-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.lp-tx-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 13px 16px;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
  background: var(--tx-bg, #fafbfc);
  transition: box-shadow 0.2s;
}
.lp-tx-row:hover {
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.lp-tx-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.lp-tx-info {
  flex: 1;
  min-width: 0;
}
.lp-tx-desc {
  font-size: 0.88rem;
  font-weight: 500;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lp-tx-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  flex-wrap: wrap;
}
.lp-tx-badge {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}
.lp-tx-date {
  font-size: 0.75rem;
  color: #94a3b8;
}
.lp-tx-expire {
  font-size: 0.72rem;
  color: #f59e0b;
  background: #fffbeb;
  border-radius: 6px;
  padding: 1px 7px;
}
.lp-tx-expired-tag {
  font-size: 0.72rem;
  color: #dc2626;
  background: #fef2f2;
  border-radius: 6px;
  padding: 1px 7px;
}

.lp-tx-right {
  text-align: right;
  flex-shrink: 0;
}
.lp-tx-points {
  font-size: 1.05rem;
  font-weight: 700;
}
.lp-tx-balance {
  font-size: 0.72rem;
  color: #94a3b8;
  margin-top: 2px;
}

/* ── Pagination ───────────────────────────────────────────────── */
.lp-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 16px;
}
.lp-page-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 7px 18px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.lp-page-btn:hover:not(:disabled) {
  background: #E63B6F;
  color: #fff;
  border-color: #E63B6F;
}
.lp-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.lp-page-info {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 500;
}

/* ── Guide ────────────────────────────────────────────────────── */
.lp-guide-steps {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.lp-step {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 18px;
  flex: 1;
  min-width: 180px;
}
.lp-step-num {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #E63B6F;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.lp-step-text {
  font-size: 0.87rem;
  color: #475569;
  line-height: 1.4;
}
.lp-step-arrow {
  font-size: 1.3rem;
  color: #cbd5e1;
}
.lp-guide-note {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 0.85rem;
  color: #92400e;
  line-height: 1.6;
}

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 768px) {
  .lp-hero {
    padding: 20px;
    flex-direction: column;
    align-items: flex-start;
  }
  .lp-balance-num { font-size: 2rem; }
  .lp-hero-stats { gap: 10px; }
  .lp-stat-chip { min-width: 90px; padding: 10px 14px; }
  .lp-earn-grid { grid-template-columns: 1fr; }
  .lp-filter-group { margin-left: 0; }
  .lp-section-header { flex-direction: column; align-items: flex-start; }
  .lp-guide-steps { flex-direction: column; }
  .lp-step-arrow { transform: rotate(90deg); }
}
</style>
