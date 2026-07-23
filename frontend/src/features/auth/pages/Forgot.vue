<script setup>
import { ref, nextTick, onBeforeUnmount, computed } from 'vue';
import { useRouter } from 'vue-router';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { authService } from '@/services/authService';

const router = useRouter();
const currentStep = ref(1);
const email = ref('');
const otp = ref(['', '', '', '', '', '']);
const password = ref('');
const passwordConfirmation = ref('');
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const isSubmitting = ref(false);
const errorMsg = ref('');
const successMsg = ref('');
const resetToken = ref('');

// Countdown timer (15 phút)
const countdown = ref(0);
let countdownInterval = null;

const countdownDisplay = computed(() => {
  const minutes = Math.floor(countdown.value / 60);
  const seconds = countdown.value % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const startCountdown = () => {
  countdown.value = 15 * 60; // 15 phút
  clearInterval(countdownInterval);
  countdownInterval = setInterval(() => {
    if (countdown.value > 0) {
      countdown.value--;
    } else {
      clearInterval(countdownInterval);
    }
  }, 1000);
};

onBeforeUnmount(() => {
  clearInterval(countdownInterval);
});

// Password validation
const passwordChecks = computed(() => ({
  minLength: password.value.length >= 8,
  hasUpper: /[A-Z]/.test(password.value),
  hasNumber: /[0-9]/.test(password.value),
  hasSpecial: /[^A-Za-z0-9]/.test(password.value),
}));

const isPasswordValid = computed(() =>
  passwordChecks.value.minLength &&
  passwordChecks.value.hasUpper &&
  passwordChecks.value.hasNumber &&
  passwordChecks.value.hasSpecial
);

const passwordsMatch = computed(() =>
  password.value && passwordConfirmation.value && password.value === passwordConfirmation.value
);

// OTP input handling
const otpInputs = ref([]);
const otpString = computed(() => otp.value.join(''));

const handleOtpInput = (index, event) => {
  const value = event.target.value;
  if (value && !/^\d$/.test(value)) {
    otp.value[index] = '';
    event.target.value = '';
    return;
  }
  otp.value[index] = value;
  if (value && index < 5) {
    nextTick(() => {
      const nextInput = document.getElementById(`otp-${index + 1}`);
      if (nextInput) nextInput.focus();
    });
  }
};

const handleOtpKeydown = (index, event) => {
  if (event.key === 'Backspace' && !otp.value[index] && index > 0) {
    nextTick(() => {
      const prevInput = document.getElementById(`otp-${index - 1}`);
      if (prevInput) prevInput.focus();
    });
  }
};

const handleOtpPaste = (event) => {
  event.preventDefault();
  const pastedData = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
  for (let i = 0; i < 6; i++) {
    otp.value[i] = pastedData[i] || '';
  }
  const focusIndex = Math.min(pastedData.length, 5);
  nextTick(() => {
    const input = document.getElementById(`otp-${focusIndex}`);
    if (input) input.focus();
  });
};

// Step 1: Gửi OTP
const handleSendOtp = async () => {
  if (!email.value) {
    errorMsg.value = 'Vui lòng nhập email!';
    return;
  }
  errorMsg.value = '';
  successMsg.value = '';
  isSubmitting.value = true;

  try {
    const response = await authService.sendForgotPasswordOtp(email.value);
    if (response.data.status === 'success') {
      successMsg.value = response.data.message;
      currentStep.value = 2;
      startCountdown();
      nextTick(() => {
        const firstInput = document.getElementById('otp-0');
        if (firstInput) firstInput.focus();
      });
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra. Vui lòng thử lại!';
    errorMsg.value = msg;
  } finally {
    isSubmitting.value = false;
  }
};

// Gửi lại OTP
const handleResendOtp = async () => {
  otp.value = ['', '', '', '', '', ''];
  errorMsg.value = '';
  isSubmitting.value = true;

  try {
    const response = await authService.sendForgotPasswordOtp(email.value);
    if (response.data.status === 'success') {
      successMsg.value = 'Mã OTP mới đã được gửi!';
      startCountdown();
    }
  } catch (error) {
    errorMsg.value = error.response?.data?.message || 'Không thể gửi lại mã OTP!';
  } finally {
    isSubmitting.value = false;
  }
};

// Step 2: Xác thực OTP
const handleVerifyOtp = async () => {
  if (otpString.value.length !== 6) {
    errorMsg.value = 'Vui lòng nhập đủ 6 số OTP!';
    return;
  }
  errorMsg.value = '';
  successMsg.value = '';
  isSubmitting.value = true;

  try {
    const response = await authService.verifyForgotPasswordOtp({
      email: email.value,
      otp: otpString.value
    });
    if (response.data.status === 'success') {
      resetToken.value = response.data.reset_token;
      successMsg.value = response.data.message;
      currentStep.value = 3;
      clearInterval(countdownInterval);
    }
  } catch (error) {
    errorMsg.value = error.response?.data?.message || 'Mã OTP không chính xác!';
  } finally {
    isSubmitting.value = false;
  }
};

// Step 3: Đặt lại mật khẩu
const handleResetPassword = async () => {
  if (!isPasswordValid.value) {
    errorMsg.value = 'Mật khẩu chưa đáp ứng đủ yêu cầu!';
    return;
  }
  if (!passwordsMatch.value) {
    errorMsg.value = 'Mật khẩu xác nhận không khớp!';
    return;
  }
  errorMsg.value = '';
  successMsg.value = '';
  isSubmitting.value = true;

  try {
    const response = await authService.resetForgotPassword({
      email: email.value,
      reset_token: resetToken.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    });
    if (response.data.status === 'success') {
      successMsg.value = response.data.message;
      // Chuyển hướng sau 2 giây
      setTimeout(() => {
        router.push('/client/login');
      }, 2000);
    }
  } catch (error) {
    errorMsg.value = error.response?.data?.message || 'Đặt lại mật khẩu thất bại!';
  } finally {
    isSubmitting.value = false;
  }
};

// Step indicator
const steps = [
  { num: 1, label: 'Email' },
  { num: 2, label: 'OTP' },
  { num: 3, label: 'Mật khẩu' },
];
</script>

<template>
  <AuthLayout>
      <div class="auth-page">
        <div class="auth-card">
          <!-- Icon -->
          <div class="auth-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
          </div>

          <h1 class="auth-title">Quên mật khẩu</h1>

          <!-- Step Indicator -->
          <div class="step-indicator">
            <div v-for="step in steps" :key="step.num" class="step-item" :class="{ active: currentStep >= step.num, current: currentStep === step.num }">
              <div class="step-circle">
                <svg v-if="currentStep > step.num" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else>{{ step.num }}</span>
              </div>
              <span class="step-label">{{ step.label }}</span>
            </div>
          </div>

          <!-- Error / Success Banner -->
          <div v-if="errorMsg" class="error-banner">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            {{ errorMsg }}
          </div>
          <div v-if="successMsg" class="success-banner">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ successMsg }}
          </div>

          <!-- ==================== STEP 1: Nhập Email ==================== -->
          <form v-if="currentStep === 1" @submit.prevent="handleSendOtp" class="auth-form">
            <p class="step-desc">Nhập email đã đăng ký để nhận mã OTP xác thực.</p>
            <div class="form-group">
              <label for="forgot-email">Địa chỉ Email</label>
              <input id="forgot-email" type="email" v-model="email" placeholder="your@email.com" :disabled="isSubmitting" />
            </div>

            <button type="submit" class="btn-primary" :disabled="isSubmitting">
              <span v-if="isSubmitting" class="spinner"></span>
              {{ isSubmitting ? 'Đang gửi...' : 'Gửi mã OTP' }}
            </button>
          </form>

          <!-- ==================== STEP 2: Nhập OTP ==================== -->
          <form v-if="currentStep === 2" @submit.prevent="handleVerifyOtp" class="auth-form">
            <!-- OTP Inputs -->
            <div class="otp-group" @paste="handleOtpPaste">
              <input
                v-for="(digit, index) in otp"
                :key="index"
                :id="`otp-${index}`"
                type="text"
                inputmode="numeric"
                maxlength="1"
                class="otp-input"
                :value="digit"
                @input="handleOtpInput(index, $event)"
                @keydown="handleOtpKeydown(index, $event)"
                :disabled="isSubmitting"
                autocomplete="off"
              />
            </div>

            <!-- Countdown Timer -->
            <div class="countdown-wrapper">
              <div class="countdown-timer" :class="{ expired: countdown === 0 }">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span v-if="countdown > 0">Mã hết hạn sau {{ countdownDisplay }}</span>
                <span v-else>Mã đã hết hạn!</span>
              </div>
              <button type="button" class="resend-btn" @click="handleResendOtp" :disabled="isSubmitting || countdown > 0">
                Gửi lại mã
              </button>
            </div>

            <button type="submit" class="btn-primary" :disabled="isSubmitting || otpString.length !== 6">
              <span v-if="isSubmitting" class="spinner"></span>
              {{ isSubmitting ? 'Đang xác thực...' : 'Xác thực OTP' }}
            </button>
          </form>

          <!-- ==================== STEP 3: Mật khẩu mới ==================== -->
          <form v-if="currentStep === 3" @submit.prevent="handleResetPassword" class="auth-form">
            <p class="step-desc">Tạo mật khẩu mới cho tài khoản của bạn.</p>

            <!-- New Password -->
            <div class="form-group">
              <label for="new-password">Mật khẩu mới</label>
              <div class="input-password">
                <input id="new-password" :type="showPassword ? 'text' : 'password'" v-model="password" placeholder="Nhập mật khẩu mới" :disabled="isSubmitting" />
                <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                  <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </div>

            <!-- Password Checklist -->
            <div class="password-checklist" v-if="password">
              <div class="check-item" :class="{ valid: passwordChecks.minLength }">
                <svg v-if="passwordChecks.minLength" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
                Tối thiểu 8 ký tự
              </div>
              <div class="check-item" :class="{ valid: passwordChecks.hasUpper }">
                <svg v-if="passwordChecks.hasUpper" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
                Ít nhất 1 chữ hoa (A-Z)
              </div>
              <div class="check-item" :class="{ valid: passwordChecks.hasNumber }">
                <svg v-if="passwordChecks.hasNumber" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
                Ít nhất 1 chữ số (0-9)
              </div>
              <div class="check-item" :class="{ valid: passwordChecks.hasSpecial }">
                <svg v-if="passwordChecks.hasSpecial" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
                Ít nhất 1 ký tự đặc biệt (!@#$...)
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label for="confirm-password">Xác nhận mật khẩu</label>
              <div class="input-password">
                <input id="confirm-password" :type="showConfirmPassword ? 'text' : 'password'" v-model="passwordConfirmation" placeholder="Nhập lại mật khẩu" :disabled="isSubmitting" />
                <button type="button" class="toggle-pw" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1">
                  <svg v-if="!showConfirmPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <p v-if="passwordConfirmation && !passwordsMatch" class="field-error">Mật khẩu xác nhận không khớp</p>
            </div>

            <button type="submit" class="btn-primary" :disabled="isSubmitting || !isPasswordValid || !passwordsMatch">
              <span v-if="isSubmitting" class="spinner"></span>
              {{ isSubmitting ? 'Đang xử lý...' : 'Đặt lại mật khẩu' }}
            </button>
          </form>

          <!-- Back to Login -->
          <div class="auth-switch" style="margin-top: 24px;">
            <router-link to="/client/login" class="back-link">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
              Quay lại đăng nhập
            </router-link>
          </div>
        </div>
      </div>
  </AuthLayout>
</template>

<style scoped>
.auth-page { flex: 1; padding: 48px 24px; display: flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
.auth-card { width: 100%; max-width: 460px; background: var(--card-bg); border-radius: 16px; padding: 40px 36px 36px; box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04); }

.auth-icon { width: 56px; height: 56px; border-radius: 14px; background: #FFF0F3; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.auth-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 20px; }

/* Step Indicator */
.step-indicator { display: flex; justify-content: center; gap: 32px; margin-bottom: 28px; position: relative; }
.step-item { display: flex; flex-direction: column; align-items: center; gap: 6px; position: relative; }
.step-circle {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 600;
  background: #FFE0E8; color: #636E72;
  transition: all 0.3s ease;
}
.step-item.active .step-circle { background: var(--primary); color: #fff; }
.step-item.current .step-circle { box-shadow: 0 0 0 4px rgba(230, 59, 111, 0.2); }
.step-label { font-size: 0.72rem; font-weight: 500; color: #636E72; }
.step-item.active .step-label { color: var(--primary); font-weight: 600; }

.step-desc { font-size: 0.85rem; color: #666; line-height: 1.5; margin-bottom: 4px; }

/* Error / Success banners */
.error-banner { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #fff0f0; border: 1px solid #fecaca; border-radius: 10px; color: #dc2626; font-size: 0.85rem; font-weight: 500; margin-bottom: 16px; }
.success-banner { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #f0fdf4; border: 1px solid #a7f3d0; border-radius: 10px; color: #16a34a; font-size: 0.85rem; font-weight: 500; margin-bottom: 16px; }

/* Form */
.auth-form { display: flex; flex-direction: column; gap: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-group input {
  width: 100%; padding: 12px 14px;
  border: 1.5px solid #E9ECEF; border-radius: 10px;
  font-size: 0.9rem; font-family: inherit; color: var(--text-main);
  background: #F8F9FA; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); background: var(--card-bg); }
.form-group input::placeholder { color: #636E72; }

.input-password { position: relative; }
.input-password input { padding-right: 44px; }
.toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #636E72; display: flex; padding: 4px; transition: color 0.2s; }
.toggle-pw:hover { color: var(--primary); }

.field-error { font-size: 0.78rem; color: #dc2626; margin-top: 4px; }

/* OTP Inputs */
.otp-group { display: flex; justify-content: center; gap: 10px; }
.otp-input {
  width: 48px; height: 56px;
  text-align: center; font-size: 1.3rem; font-weight: 700; font-family: 'JetBrains Mono', 'Fira Code', monospace;
  border: 2px solid #E9ECEF; border-radius: 12px;
  background: #F8F9FA; color: var(--text-main); outline: none;
  transition: all 0.2s;
}
.otp-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.15); background: var(--card-bg); }

/* Countdown */
.countdown-wrapper { display: flex; align-items: center; justify-content: space-between; }
.countdown-timer { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: var(--primary); font-weight: 500; }
.countdown-timer.expired { color: #dc2626; }
.resend-btn {
  background: none; border: none; color: var(--primary); font-size: 0.82rem; font-weight: 600;
  cursor: pointer; text-decoration: underline; font-family: inherit;
  transition: color 0.2s;
}
.resend-btn:hover:not(:disabled) { color: #C4305D; }
.resend-btn:disabled { color: #636E72; cursor: not-allowed; text-decoration: none; }

/* Password Checklist */
.password-checklist { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; background: #F8F9FA; border-radius: 10px; border: 1px solid #FFE0E8; }
.check-item { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: #636E72; transition: color 0.2s; }
.check-item.valid { color: #16a34a; }

/* Buttons */
.btn-primary {
  width: 100%; padding: 13px; border: none; border-radius: 10px;
  background: var(--primary); color: #fff; font-size: 0.95rem; font-weight: 600;
  font-family: inherit; cursor: pointer;
  transition: background 0.2s, transform 0.1s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-primary:hover:not(:disabled) { background: #C4305D; }
.btn-primary:active:not(:disabled) { transform: scale(0.98); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Auth switch */
.auth-switch { text-align: center; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #666; font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: color 0.2s; }
.back-link:hover { color: var(--primary); }
</style>
