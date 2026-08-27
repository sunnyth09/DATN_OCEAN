<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import api from '@/axios';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  orderCode: {
    type: String,
    required: true,
  },
  amount: {
    type: [Number, String],
    default: 0,
  },
  bankingInfo: {
    type: Object,
    default: () => null,
  },
  createdAt: {
    type: [String, Date],
    default: null,
  },
  isGuest: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'success', 'expired', 'later']);

const router = useRouter();

// Trạng thái đếm ngược & polling
const TOTAL_WINDOW_SECONDS = 15 * 60; // 15 phút = 900s
const timeLeft = ref(TOTAL_WINDOW_SECONDS);
const isExpired = ref(false);
const isSuccess = ref(false);
const copiedField = ref(null);

let countdownTimer = null;
let pollingTimer = null;

// Lấy thông tin tài khoản ngân hàng (dùng bankingInfo truyền vào hoặc fallback cấu hình .env / mặc định)
const bankAccount = computed(() => props.bankingInfo?.bank_name || props.bankingInfo?.gateway || 'MBBANK');
const bankAccountNo = computed(() => props.bankingInfo?.account_number || import.meta.env.BANK_ACCOUNT_NUMBER);
const bankAccountName = computed(() => props.bankingInfo?.account_name || import.meta.env.BANK_ACCOUNT_NAME);
const bankBin = computed(() => props.bankingInfo?.bank_bin || import.meta.env.BANK_BIN);

const qrUrl = computed(() => {
  if (props.bankingInfo?.qr_url) return props.bankingInfo.qr_url;
  const acc = bankAccountNo.value;
  const bank = bankBin.value;
  const amt = Math.round(Number(props.amount || 0));
  const des = encodeURIComponent(props.orderCode || '');
  return `https://qr.sepay.vn/img?acc=${acc}&bank=${bank}&amount=${amt}&des=${des}`;
});

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(price) || 0);
};

// Định dạng thời gian mm:ss
const formattedTimeLeft = computed(() => {
  const m = Math.floor(timeLeft.value / 60);
  const s = timeLeft.value % 60;
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
});

// Phần trăm thời gian còn lại (cho thanh progress)
const progressPercent = computed(() => {
  return Math.max(0, Math.min(100, (timeLeft.value / TOTAL_WINDOW_SECONDS) * 100));
});

// Sao chép thông tin
const copyToClipboard = async (text, fieldName) => {
  try {
    await navigator.clipboard.writeText(String(text));
    copiedField.value = fieldName;
    setTimeout(() => {
      if (copiedField.value === fieldName) copiedField.value = null;
    }, 2000);
  } catch (err) {
    console.error('Lỗi khi sao chép:', err);
  }
};

// Tính toán thời gian còn lại dựa trên createdAt hoặc 900s
const calculateInitialTimeLeft = () => {
  if (!props.createdAt) {
    timeLeft.value = TOTAL_WINDOW_SECONDS;
    isExpired.value = false;
    return;
  }
  const createdTime = new Date(props.createdAt).getTime();
  const now = Date.now();
  const elapsedSeconds = Math.floor((now - createdTime) / 1000);
  const remaining = TOTAL_WINDOW_SECONDS - elapsedSeconds;

  if (remaining <= 0) {
    timeLeft.value = 0;
    isExpired.value = true;
  } else {
    timeLeft.value = remaining;
    isExpired.value = false;
  }
};

// Bắt đầu đồng hồ đếm ngược 1s
const startCountdown = () => {
  stopCountdown();
  calculateInitialTimeLeft();
  if (isExpired.value) {
    emit('expired');
    return;
  }

  countdownTimer = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value -= 1;
    } else {
      stopCountdown();
      stopPolling();
      isExpired.value = true;
      emit('expired');
    }
  }, 1000);
};

const stopCountdown = () => {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
};

// Polling kiểm tra trạng thái đơn hàng (tự động phát hiện SePay payment)
const checkStatus = async () => {
  if (!props.orderCode || isExpired.value || isSuccess.value) return;
  try {
    const res = await api.get(`/orders/status/${props.orderCode}`);
    const data = res.data?.data;
    if (data?.payment_status === 'paid') {
      isSuccess.value = true;
      stopCountdown();
      stopPolling();

      emit('success', data);

      // Chuyển hướng tự động sau 1.2s để người dùng kịp nhìn thấy thông báo thành công
      setTimeout(() => {
        router.push({ name: 'order-success', params: { order_code: props.orderCode } });
      }, 1200);
    }
  } catch (err) {
    // Silent fail để polling tiếp tục chạy
  }
};

const startPolling = () => {
  stopPolling();
  checkStatus(); // Gọi kiểm tra ngay lập tức
  pollingTimer = setInterval(checkStatus, 3000); // Poll mỗi 3 giây
};

const stopPolling = () => {
  if (pollingTimer) {
    clearInterval(pollingTimer);
    pollingTimer = null;
  }
};

// Xử lý khi user chọn "Chuyển khoản sau"
const handlePayLater = () => {
  stopCountdown();
  stopPolling();
  emit('later');
  emit('close');
};

// Xử lý khi Guest click Đóng modal
const handleCloseModal = () => {
  stopCountdown();
  stopPolling();
  emit('close');
};

watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      isSuccess.value = false;
      isExpired.value = false;
      startCountdown();
      startPolling();
    } else {
      stopCountdown();
      stopPolling();
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (props.show) {
    startCountdown();
    startPolling();
  }
});

onUnmounted(() => {
  stopCountdown();
  stopPolling();
});
</script>

<template>
  <Teleport to="body">
    <Transition name="sepay-modal-fade">
      <div v-if="show" class="sepay-modal-overlay" @click.self="isGuest ? handleCloseModal() : handlePayLater()">
        <div class="sepay-modal-card animate-scale">

          <!-- Header -->
          <div class="sepay-modal-header">
            <div class="header-title">
              <span class="bank-icon-badge">
                <AppIcon name="credit-card" size="22" color="#ffffff" />
              </span>
              <div>
                <h3 class="modal-heading">Thanh toán Chuyển khoản (SePay)</h3>
                <p class="modal-subheading">Hệ thống sẽ tự động xác nhận ngay khi bạn chuyển tiền thành công</p>
              </div>
            </div>
            <button class="btn-close-modal" aria-label="Close" @click="isGuest ? handleCloseModal() : handlePayLater()">
              <AppIcon name="x" size="18" />
            </button>
          </div>

          <!-- Banner Thành Công (Hiện khi SePay đã nhận tiền) -->
          <div v-if="isSuccess" class="success-banner-overlay">
            <div class="success-content">
              <div class="success-icon-bounce">
                <AppIcon name="check" size="46" color="#ffffff" stroke-width="3.5" />
              </div>
              <h4>Thanh toán thành công!</h4>
              <p>Đơn hàng <strong>#{{ orderCode }}</strong> đã được xác nhận thanh toán.</p>
              <span class="redirect-spinner">
                <span class="spinner-dot"></span> Đang chuyển hướng sang trang kết quả...
              </span>
            </div>
          </div>

          <!-- Banner Hết Hạn 15 Phút -->
          <div v-else-if="isExpired" class="expired-banner-box">
            <div class="expired-icon">
              <AppIcon name="clock" size="44" color="#e11d48" />
            </div>
            <div>
              <h5>Đã hết thời hạn 15 phút thanh toán</h5>
              <p>Thời gian dành cho việc thanh toán đơn hàng <strong>#{{ orderCode }}</strong> đã hết. Vui lòng đóng cửa sổ này hoặc đặt lại đơn hàng mới.</p>
            </div>
          </div>

          <!-- Body Thanh toán chính -->
          <div v-else class="sepay-modal-body">

            <!-- Thanh Đếm Ngược 15 Phút -->
            <div class="timer-card" :class="{ 'warning-timer': timeLeft < 180 }">
              <div class="timer-header">
                <span class="timer-label">
                  <span class="live-dot"></span>
                  Thời gian hoàn tất thanh toán:
                </span>
                <span class="timer-clock">
                  <AppIcon name="clock" size="15" class="timer-icon" />
                  {{ formattedTimeLeft }}
                </span>
              </div>
              <div class="timer-progress-track">
                <div class="timer-progress-bar" :style="{ width: progressPercent + '%' }"></div>
              </div>
            </div>

            <div class="payment-grid">
              <!-- Cột QR Code -->
              <div class="qr-col">
                <div class="qr-box">
                  <img :src="qrUrl" alt="QR Code Chuyển Khoản SePay" class="qr-code-img" />
                  <div class="qr-pulse-ring"></div>
                </div>
                <p class="qr-guide">Quét bằng ứng dụng Ngân hàng hoặc Ví MoMo/VNPay bất kỳ</p>
              </div>

              <!-- Cột Thông tin tài khoản -->
              <div class="info-col">
                <div class="info-row">
                  <span class="info-label">Ngân hàng</span>
                  <span class="info-value font-semibold">MB Bank (Ngân hàng Quân Đội)</span>
                </div>

                <div class="info-row">
                  <span class="info-label">Số tài khoản</span>
                  <div class="info-value-wrap">
                    <span class="info-value highlight">{{ bankAccountNo }}</span>
                    <button class="btn-copy-chip" @click="copyToClipboard(bankAccountNo, 'acc')">
                      <template v-if="copiedField === 'acc'">
                        <AppIcon name="check" size="13" /> Đã chép
                      </template>
                      <template v-else>
                        <AppIcon name="copy" size="13" /> Copy
                      </template>
                    </button>
                  </div>
                </div>

                <div class="info-row">
                  <span class="info-label">Chủ tài khoản</span>
                  <span class="info-value">{{ bankAccountName }}</span>
                </div>

                <div class="info-row">
                  <span class="info-label">Số tiền cần chuyển</span>
                  <div class="info-value-wrap">
                    <span class="info-value amount-text">{{ formatPrice(amount) }}</span>
                    <button class="btn-copy-chip" @click="copyToClipboard(Math.round(Number(amount)), 'amt')">
                      <template v-if="copiedField === 'amt'">
                        <AppIcon name="check" size="13" /> Đã chép
                      </template>
                      <template v-else>
                        <AppIcon name="copy" size="13" /> Copy
                      </template>
                    </button>
                  </div>
                </div>

                <div class="info-row highlight-box">
                  <span class="info-label">Nội dung chuyển khoản</span>
                  <div class="info-value-wrap">
                    <span class="info-value code-text">{{ orderCode }}</span>
                    <button class="btn-copy-chip primary" @click="copyToClipboard(orderCode, 'code')">
                      <template v-if="copiedField === 'code'">
                        <AppIcon name="check" size="13" /> Đã chép
                      </template>
                      <template v-else>
                        <AppIcon name="copy" size="13" /> Copy mã
                      </template>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ghi chú lưu ý -->
            <div class="payment-alert-note">
              <AppIcon name="alert-circle" size="18" color="#d97706" class="flex-shrink-0" />
              <span>Vui lòng giữ đúng <strong>Nội dung chuyển khoản là {{ orderCode }}</strong> để hệ thống tự động xác nhận đơn hàng trong vài giây.</span>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="sepay-modal-footer">
            <div v-if="isGuest" class="guest-notice-bar">
              <span class="guest-warn-text">
                <AppIcon name="alert-circle" size="15" color="#d97706" /> Đơn hàng vãng lai không có tài khoản. Vui lòng thanh toán trong modal này trước khi thoát.
              </span>
              <button class="btn-close-guest" @click="handleCloseModal">Tắt cửa sổ</button>
            </div>

            <template v-else>
              <div class="polling-status-indicator" v-if="!isExpired && !isSuccess">
                <span class="pulse-loader"></span>
                <span>Đang chờ chuyển tiền từ ngân hàng...</span>
              </div>
              <button v-if="!isSuccess && !isExpired" class="btn-pay-later" @click="handlePayLater">
                Chuyển khoản sau
              </button>
              <button v-else-if="isExpired" class="btn-pay-later" @click="handleCloseModal">
                Đóng cửa sổ
              </button>
            </template>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sepay-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(6px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  font-family: var(--font-inter, 'Inter', sans-serif);
}

.sepay-modal-card {
  background: #ffffff;
  border-radius: 20px;
  max-width: 680px;
  width: 100%;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  position: relative;
  border: 1px solid #e2e8f0;
}

/* Header */
.sepay-modal-header {
  background: linear-gradient(135deg, #e63b6f 0%, #c2185b 100%);
  color: #ffffff;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 14px;
}

.bank-icon-badge {
  width: 42px;
  height: 42px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  backdrop-filter: blur(4px);
}

.modal-heading {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #ffffff;
}

.modal-subheading {
  margin: 2px 0 0;
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.85);
}

.btn-close-modal {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: #ffffff;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-close-modal:hover {
  background: rgba(255, 255, 255, 0.35);
  transform: rotate(90deg);
}

/* Body */
.sepay-modal-body {
  padding: 24px;
}

/* Timer Card */
.timer-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 20px;
}

.timer-card.warning-timer {
  background: #fff1f2;
  border-color: #fecdd3;
}

.timer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.timer-label {
  font-size: 0.88rem;
  font-weight: 600;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 6px;
}

.warning-timer .timer-label {
  color: #e11d48;
}

.live-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulseGreen 1.5s infinite;
}

.warning-timer .live-dot {
  background: #e11d48;
}

.timer-clock {
  font-size: 1.15rem;
  font-weight: 800;
  font-family: monospace;
  color: #0f172a;
}

.warning-timer .timer-clock {
  color: #e11d48;
}

.timer-progress-track {
  height: 6px;
  background: #cbd5e1;
  border-radius: 3px;
  overflow: hidden;
}

.timer-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #2563eb);
  transition: width 1s linear;
}

.warning-timer .timer-progress-bar {
  background: linear-gradient(90deg, #f43f5e, #e11d48);
}

/* Payment Grid */
.payment-grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 24px;
  align-items: start;
}

.qr-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.qr-box {
  background: #ffffff;
  padding: 10px;
  border-radius: 16px;
  border: 2px dashed #cbd5e1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  position: relative;
}

.qr-code-img {
  width: 190px;
  height: 190px;
  object-fit: contain;
  border-radius: 8px;
}

.qr-guide {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 10px;
  line-height: 1.4;
}

/* Info Col */
.info-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-bottom: 10px;
  border-bottom: 1px solid #f1f5f9;
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
}

.info-value-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.info-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
}

.info-value.highlight {
  font-family: monospace;
  font-size: 1.05rem;
  color: #0284c7;
}

.info-value.amount-text {
  font-size: 1.15rem;
  font-weight: 800;
  color: #16a34a;
}

.info-row.highlight-box {
  background: #eff6ff;
  border: 1.5px dashed #60a5fa;
  border-radius: 12px;
  padding: 10px 14px;
}

.info-value.code-text {
  font-size: 1.15rem;
  font-weight: 800;
  color: #2563eb;
  letter-spacing: 0.5px;
}

.btn-copy-chip {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-copy-chip:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-copy-chip.primary {
  background: #2563eb;
  border-color: #2563eb;
  color: #ffffff;
}

.btn-copy-chip.primary:hover {
  background: #1d4ed8;
}

/* Alert Note */
.payment-alert-note {
  margin-top: 18px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 0.82rem;
  color: #b45309;
  display: flex;
  align-items: center;
  gap: 10px;
  line-height: 1.45;
}

/* Success Banner Overlay */
.success-banner-overlay {
  padding: 60px 30px 65px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.success-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.success-icon-bounce {
  width: 88px;
  height: 88px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 12px auto 28px;
  color: #ffffff;
  box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4), 0 0 0 14px rgba(16, 185, 129, 0.12);
  animation: bounceSuccess 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
  padding: 0;
}

.success-icon-bounce :deep(svg),
.success-icon-bounce svg {
  display: block;
  margin: 0 auto;
}

.success-content h4 {
  font-size: 1.6rem;
  font-weight: 800;
  color: #059669;
  margin: 0 0 10px;
}

.success-content p {
  color: #475569;
  font-size: 1rem;
  margin: 0 0 20px;
}

.redirect-spinner {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.spinner-dot {
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  animation: pulseDot 1s infinite ease-in-out;
}

/* Expired Banner Box */
.expired-banner-box {
  padding: 50px 30px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.expired-icon {
  font-size: 3rem;
}

.expired-banner-box h5 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #e11d48;
  margin: 0;
}

.expired-banner-box p {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0;
  max-width: 420px;
  line-height: 1.5;
}

/* Footer */
.sepay-modal-footer {
  background: #f8fafc;
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.polling-status-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 600;
}

.pulse-loader {
  width: 10px;
  height: 10px;
  background: #3b82f6;
  border-radius: 50%;
  animation: pulseDot 1.2s infinite ease-in-out;
}

.btn-pay-later {
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  color: #334155;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 22px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  margin-left: auto;
}

.btn-pay-later:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
}

/* Guest Notice Bar */
.guest-notice-bar {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.guest-warn-text {
  font-size: 0.8rem;
  color: #c2410c;
  font-weight: 600;
}

.btn-close-guest {
  background: #ea580c;
  color: #ffffff;
  border: none;
  font-weight: 700;
  font-size: 0.85rem;
  padding: 8px 18px;
  border-radius: 8px;
  cursor: pointer;
  flex-shrink: 0;
}

.btn-close-guest:hover {
  background: #c2410c;
}

/* Animations */
@keyframes pulseGreen {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(0.85); }
}

@keyframes pulseDot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.3; transform: scale(0.6); }
}

@keyframes bounceSuccess {
  0% { transform: scale(0.3); opacity: 0; }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); opacity: 1; }
}

.sepay-modal-fade-enter-active,
.sepay-modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.sepay-modal-fade-enter-from,
.sepay-modal-fade-leave-to {
  opacity: 0;
}

.animate-scale {
  animation: scaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes scaleUp {
  from { transform: scale(0.95) translateY(10px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}

@media (max-width: 640px) {
  .payment-grid {
    grid-template-columns: 1fr;
  }
  .qr-box {
    margin: 0 auto;
  }
  .sepay-modal-footer {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }
  .btn-pay-later {
    width: 100%;
    margin-left: 0;
    text-align: center;
  }
}
</style>
