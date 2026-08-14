<template>
  <div class="flash-board">

    <!-- ── HEADER ── -->
    <div class="board-header">
      <div class="sale-badge">
        <span>⚡</span>
        <span class="badge-text">FLASH SALE</span>
      </div>
      <span class="hot-chip" v-if="stockPercent >= 70 && !isEnded">🔥 Sắp hết hàng</span>
    </div>

    <!-- ── LOADING ── -->
    <template v-if="isLoading">
      <div class="sk sk-img"></div>
      <div class="sk sk-line" style="width:60%"></div>
      <div class="sk sk-line" style="width:40%;height:28px;margin-bottom:16px"></div>
      <div class="sk sk-bar"></div>
      <div class="sk sk-btn"></div>
    </template>

    <template v-else-if="sale">

      <!-- ── SẢN PHẨM ── -->
      <div class="product-row">
        <div class="img-wrap">
          <img :src="productThumb" :alt="sale.product_name" class="product-img" @error="imgFallback" />
          <span class="disc-chip">-{{ sale.discount_percent }}%</span>
        </div>
        <div class="product-info">
          <h2 class="product-name">{{ sale.product_name }}</h2>
          <p class="product-desc" v-if="sale.description">{{ sale.description }}</p>
          <div class="price-row">
            <span class="sale-price">{{ fmtPrice(sale.sale_price) }}</span>
            <span class="orig-price">{{ fmtPrice(sale.original_price) }}</span>
          </div>
          <p class="limit-note">🛒 Tối đa {{ sale.max_per_user }} sản phẩm / khách</p>
        </div>
      </div>

      <!-- ── COUNTDOWN — DOM refs, không dùng reactive ── -->
      <div class="timer-section">
        <p class="timer-label" ref="timerLabelEl">⏰ Kết thúc sau:</p>
        <div class="countdown" ref="countdownEl">
          <div class="time-unit">
            <span class="time-num" ref="hoursEl">00</span>
            <span class="time-lbl">Giờ</span>
          </div>
          <span class="sep">:</span>
          <div class="time-unit">
            <span class="time-num" ref="minsEl">00</span>
            <span class="time-lbl">Phút</span>
          </div>
          <span class="sep">:</span>
          <div class="time-unit">
            <span class="time-num" ref="secsEl">00</span>
            <span class="time-lbl">Giây</span>
          </div>
        </div>
      </div>

      <!-- ── PROGRESS ── -->
      <div class="progress-section" v-if="stockData">
        <div class="progress-labels">
          <span>Đã bán <strong>{{ stockData.sold_count }}</strong> / {{ sale.total_stock }}</span>
          <span :class="['remain-text', { danger: stockData.remaining <= 10 }]">
            Còn {{ stockData.remaining }}
          </span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" :class="fillClass" :style="{ width: fillWidth }"></div>
        </div>
      </div>

      <!-- ── NÚT MUA ── -->
      <div class="action-area" v-if="!ended">
        <button
          id="flash-sale-buy-btn"
          class="buy-btn"
          :class="btnClass"
          :disabled="isBuying || soldOut || isBought"
          @click="handleBuy"
        >
          <span v-if="isBought">✅ Đặt hàng thành công!</span>
          <span v-else-if="isBuying">Đang xử lý...</span>
          <span v-else-if="soldOut">Đã hết hàng</span>
          <span v-else>⚡ Săn Deal Ngay</span>
        </button>
        <p class="auth-note" v-if="!isLoggedIn">
          <router-link to="/client/login">Đăng nhập</router-link> để tham gia
        </p>
      </div>
      <div class="action-area" v-else>
        <div class="ended-box">Chiến dịch đã kết thúc</div>
      </div>

    </template>

    <!-- ── TOAST ── -->
    <div v-if="toast.visible" class="toast-box" :class="`toast--${toast.type}`">
      {{ toast.message }}
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api, { getUser } from '@/axios.js';
import { getStorageUrl } from '@/utils/url';

const router = useRouter();

const props = defineProps({
  flashSaleId: { type: Number, default: null },
  itemId: { type: Number, default: null },
});

// ── Vue reactive state (chỉ cho dữ liệu thực sự cần render) ──
const sale      = ref(null);
const stockData = ref(null);
const isBuying  = ref(false);
const isBought  = ref(false);
const isLoading = ref(true);
const ended     = ref(false);
const toast     = ref({ visible: false, type: 'info', message: '' });

// ── Template refs cho countdown (cập nhật DOM trực tiếp, KHÔNG qua Vue reactivity) ──
const hoursEl     = ref(null);
const minsEl      = ref(null);
const secsEl      = ref(null);
const countdownEl = ref(null);
const timerLabelEl = ref(null);

// ── Non-reactive ──
let serverOffset = 0;
let timerInterval = null;
let stockInterval = null;
let toastTimer    = null;
let stockRequest  = null;

// ── Computed ──
const isLoggedIn   = computed(() => !!getUser());
const soldOut      = computed(() => !!stockData.value?.is_sold_out);
const stockPercent = computed(() => {
  if (!sale.value || !stockData.value) return 0;
  return (stockData.value.sold_count / sale.value.total_stock) * 100;
});
const fillWidth = computed(() => Math.min(stockPercent.value, 100) + '%');
const fillClass = computed(() => {
  const p = stockPercent.value;
  return p >= 80 ? 'fill--danger' : p >= 50 ? 'fill--warn' : 'fill--ok';
});
const btnClass = computed(() => {
  if (isBought.value) return 'btn--success';
  if (soldOut.value || ended.value) return 'btn--disabled';
  if (isBuying.value) return 'btn--loading';
  return 'btn--active';
});
const isEnded = computed(() => ended.value);
const productThumb = computed(() => {
  const t = sale.value?.product_thumbnail;
  if (!t || t === '0') return 'https://placehold.co/400x400/E63B6F/FFF?text=Sale';
  return getStorageUrl(t);
});

// ── Helpers ──
const pad      = n => String(n).padStart(2, '0');
const fmtPrice = p => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p);
const imgFallback = e => { e.target.src = 'https://placehold.co/400x400/E63B6F/FFF?text=Sale'; };

function showToast(type, msg, ms = 4000) {
  clearTimeout(toastTimer);
  toast.value = { visible: true, type, message: msg };
  toastTimer  = setTimeout(() => { toast.value.visible = false; }, ms);
}

// ── Countdown: cập nhật DOM trực tiếp, không trigger Vue re-render ──
function serverNow() { return new Date(Date.now() + serverOffset); }

function tickTimer() {
  if (!sale.value) return;

  const now    = serverNow();
  const start  = new Date(sale.value.starts_at);
  const end    = new Date(sale.value.ends_at);

  // Xác định trạng thái
  if (now > end || sale.value.status === 'ended') {
    ended.value = true;
    clearInterval(timerInterval);
    if (countdownEl.value) countdownEl.value.style.display = 'none';
    if (timerLabelEl.value) timerLabelEl.value.textContent = 'Chiến dịch đã kết thúc';
    return;
  }

  const target = now < start ? start : end;

  if (now < start && timerLabelEl.value) timerLabelEl.value.textContent = '⏳ Bắt đầu sau:';
  if (now >= start && timerLabelEl.value) timerLabelEl.value.textContent = '⏰ Kết thúc sau:';

  const diff = Math.max(0, target - now);
  const h = Math.floor(diff / 3_600_000);
  const m = Math.floor((diff % 3_600_000) / 60_000);
  const s = Math.floor((diff % 60_000) / 1_000);

  // ⚡ Direct DOM mutation — zero Vue re-render
  if (hoursEl.value) hoursEl.value.textContent = pad(h);
  if (minsEl.value)  minsEl.value.textContent  = pad(m);
  if (secsEl.value)  secsEl.value.textContent  = pad(s);
}

function startTimer() {
  clearInterval(timerInterval);
  tickTimer(); // Chạy ngay lần đầu
  timerInterval = setInterval(tickTimer, 1000);
}

// ── Fetch ──
async function fetchSale() {
  isLoading.value = true;
  try {
    const { data } = await api.get('flash-sale');
    const list  = data.data ?? [];
    let found = null;
    if (props.itemId) {
      found = list.find(s => s.item_id === props.itemId);
    } else if (props.flashSaleId) {
      found = list.find(s => s.id === props.flashSaleId);
    } else {
      found = list[0];
    }
    if (found) {
      serverOffset = new Date(found.server_time) - Date.now();
      sale.value   = found;
      ended.value  = found.status === 'ended'
        || serverNow() > new Date(found.ends_at);
    }
  } catch (e) {
    console.error('[FlashSaleBoard]', e);
  } finally {
    isLoading.value = false;
  }
}

async function fetchStock() {
  if (!sale.value || document.hidden) return;
  if (stockRequest) return stockRequest;

  stockRequest = (async () => {
    const { data } = await api.get(`flash-sale/${sale.value.id}/stock?product_id=${sale.value.product_id}`);
    stockData.value = data;
  })().catch(() => {}).finally(() => {
    stockRequest = null;
  });

  return stockRequest;
}

// ── Buy ──
async function handleBuy() {
  if (!isLoggedIn.value) {
    showToast('warn', 'Vui lòng đăng nhập để tham gia Flash Sale!'); return;
  }
  if (isBuying.value || isBought.value || soldOut.value) return;

  router.push({
    path: '/checkout',
    query: {
      flash_sale_id: sale.value.id,
      product_id: sale.value.product_id
    }
  });
}

// ── Lifecycle ──
onMounted(async () => {
  await fetchSale();
  await fetchStock();
  startTimer();
  stockInterval = setInterval(fetchStock, 30_000);
  document.addEventListener('visibilitychange', handleVisibilityChange);
});
onUnmounted(() => {
  clearInterval(timerInterval);
  clearInterval(stockInterval);
  clearTimeout(toastTimer);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
const handleVisibilityChange = () => {
  if (document.hidden) return;
  tickTimer();
  fetchStock();
};
watch(() => props.flashSaleId, async () => {
  await fetchSale();
  await fetchStock();
  startTimer();
});
</script>

<style scoped>
/* ════════════════════════════════════════════════
   FLASH SALE BOARD — Ocean Blue, Zero Animation
════════════════════════════════════════════════ */
.flash-board {
  background: var(--card-bg, #fff);
  border: 1px solid var(--border-subtle, #F1F3F5);
  border-radius: var(--radius-lg, 16px);
  padding: 32px 24px;
  position: relative;
  max-width: 520px;
  width: 100%;
  box-shadow: var(--shadow-card, 0 4px 15px rgba(0,0,0,0.08));
}
.flash-board::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary), var(--primary), var(--primary-light));
  border-radius: 16px 16px 0 0;
}

/* ── HEADER ── */
.board-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.sale-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(90deg, var(--primary), var(--primary));
  color: #fff;
  border-radius: 50px;
  padding: 5px 14px;
}
.badge-text { font-size: 12px; font-weight: 800; letter-spacing: 1.5px; }
.hot-chip {
  background: #fff4f3;
  border: 1px solid #f4bcb8;
  color: #c0392b;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 50px;
}

/* ── PRODUCT ── */
.product-row { display: flex; gap: 16px; margin-bottom: 20px; }
.img-wrap { position: relative; flex-shrink: 0; }
.product-img {
  width: 100px; height: 100px;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--border-subtle);
  display: block;
}
.disc-chip {
  position: absolute;
  top: -6px; right: -6px;
  background: var(--primary); color: #fff;
  font-size: 10px; font-weight: 800;
  padding: 2px 7px;
  border-radius: 50px;
  border: 1.5px solid #fff;
}
.product-info { flex: 1; min-width: 0; }
.product-name {
  color: var(--primary); font-size: 15px; font-weight: 700;
  margin: 0 0 4px; line-height: 1.35;
  overflow: hidden;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.product-desc {
  color: var(--text-muted); font-size: 12px;
  margin: 0 0 8px; line-height: 1.4;
  overflow: hidden;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.price-row { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; }
.sale-price { color: var(--primary); font-size: 20px; font-weight: 800; }
.orig-price { color: var(--text-light); font-size: 13px; text-decoration: line-through; }
.limit-note { color: var(--text-muted); font-size: 11px; margin: 0; }

/* ── TIMER ── */
.timer-section {
  background: var(--surface);
  border: 1px solid var(--border-subtle);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
  text-align: center;
}
.timer-label {
  color: var(--text-muted); font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 1px;
  margin: 0 0 10px;
}
.countdown {
  display: flex; align-items: center;
  justify-content: center; gap: 6px;
}
.time-unit {
  display: flex; flex-direction: column;
  align-items: center;
  background: var(--card-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 10px;
  padding: 6px 14px; min-width: 58px;
}
.time-num {
  color: var(--primary);
  font-size: 26px; font-weight: 800; line-height: 1;
  font-variant-numeric: tabular-nums;
}
.time-lbl {
  color: var(--text-light); font-size: 9px;
  font-weight: 600; letter-spacing: 1px; margin-top: 2px;
}
.sep { color: var(--primary); font-size: 22px; font-weight: 800; margin-bottom: 12px; }

/* ── PROGRESS ── */
.progress-section { margin-bottom: 16px; }
.progress-labels {
  display: flex; justify-content: space-between;
  font-size: 12px; color: var(--text-muted); margin-bottom: 6px;
}
.remain-text { color: var(--primary); }
.remain-text.danger { color: var(--error); font-weight: 700; }
.progress-track {
  background: var(--surface-container-low);
  border-radius: 50px; height: 8px; overflow: hidden;
}
.progress-fill { height: 100%; border-radius: 50px; }
.fill--ok     { background: linear-gradient(90deg, var(--primary), var(--primary-light)); }
.fill--warn   { background: linear-gradient(90deg, var(--warning), #f39c12); }
.fill--danger { background: linear-gradient(90deg, var(--error), #c0392b); }

/* ── BUY BUTTON ── */
.action-area { margin-top: 4px; }
.buy-btn {
  width: 100%; padding: 14px 24px;
  border: none; border-radius: 12px;
  font-family: inherit; font-size: 15px; font-weight: 700;
  cursor: pointer;
}
.btn--active {
  background: linear-gradient(135deg, var(--primary), var(--primary));
  color: #fff;
  box-shadow: 0 4px 14px rgba(230, 59, 111, 0.3);
}
.btn--active:hover { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.btn--loading { background: var(--surface-container-low); color: var(--text-muted); cursor: not-allowed; }
.btn--success { background: linear-gradient(135deg, var(--tertiary), #2ecc71); color: #fff; cursor: default; }
.btn--disabled { background: var(--surface-container); color: var(--text-light); cursor: not-allowed; }

.ended-box {
  text-align: center;
  padding: 12px;
  background: var(--surface);
  border-radius: 10px;
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 600;
}

.auth-note { text-align: center; margin: 8px 0 0; font-size: 12px; color: var(--text-light); }
.auth-note a { color: var(--primary); text-decoration: underline; }

/* ── TOAST (không dùng Transition wrapper) ── */
.toast-box {
  position: absolute;
  bottom: 16px; left: 50%;
  transform: translateX(-50%);
  padding: 10px 18px;
  border-radius: 50px;
  font-size: 13px; font-weight: 600;
  white-space: nowrap;
  z-index: 10;
  box-shadow: 0 4px 16px rgba(0,0,0,0.1);
  pointer-events: none;
}
.toast--success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.toast--error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.toast--warn    { background: #fff3cd; color: #856404; border: 1px solid #ffd700; }
.toast--info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

/* ── SKELETON ── */
.sk { background: var(--surface-container-low); border-radius: 8px; margin-bottom: 10px; }
.sk-img  { height: 100px; }
.sk-line { height: 16px; }
.sk-bar  { height: 8px; margin-bottom: 16px; }
.sk-btn  { height: 48px; border-radius: 12px; }
</style>
