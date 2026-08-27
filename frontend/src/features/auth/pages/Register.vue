<script setup>
import { ref, computed, reactive, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { Modal } from 'bootstrap';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useToast } from '@/composables/useToast';
import { authService } from '@/services/authService';
import { useAuthStore, getDefaultRouteForRole } from '@/stores/auth';

const name = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const agreeTerms = ref(false);
const errorMsg = ref('');
const isSubmitting = ref(false);
const router = useRouter();
const authStore = useAuthStore();
const turnstileToken = ref('');
let turnstileWidgetId = null;
const { showToast } = useToast();

// Field-level validation
const fieldErrors = reactive({ name: '', email: '', password: '', confirm: '', terms: '' });
const touched = reactive({ name: false, email: false, password: false, confirm: false, terms: false });

const validateField = (field) => {
  if (field === 'name') {
    fieldErrors.name = !name.value ? 'Vui lòng nhập họ tên' : '';
  }
  if (field === 'email') {
    if (!email.value) fieldErrors.email = 'Vui lòng nhập email';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) fieldErrors.email = 'Email không hợp lệ';
    else fieldErrors.email = '';
  }
  if (field === 'password') {
    fieldErrors.password = !password.value ? 'Vui lòng nhập mật khẩu' : '';
  }
  if (field === 'confirm') {
    if (!password_confirmation.value) fieldErrors.confirm = 'Vui lòng xác nhận mật khẩu';
    else if (password.value !== password_confirmation.value) fieldErrors.confirm = 'Mật khẩu xác nhận không khớp';
    else fieldErrors.confirm = '';
  }
  if (field === 'terms') {
    fieldErrors.terms = !agreeTerms.value ? 'Bạn cần đồng ý với điều khoản dịch vụ' : '';
  }
};

const onBlur = (field) => {
  touched[field] = true;
  validateField(field);
};

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
  password.value && password_confirmation.value && password.value === password_confirmation.value
);

// Cloudflare Turnstile
const loadTurnstile = () => {
  if (window.turnstile) { renderTurnstile(); return; }
  const script = document.createElement('script');
  script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
  script.async = true;
  window.onTurnstileLoad = () => renderTurnstile();
  document.head.appendChild(script);
};

const renderTurnstile = () => {
  const container = document.getElementById('turnstile-register');
  if (!container || !window.turnstile) return;
  container.innerHTML = '';
  turnstileWidgetId = window.turnstile.render('#turnstile-register', {
    sitekey: import.meta.env.VITE_TURNSTILE_SITE_KEY || '0x4AAAAAADfskJpxs4bqJtS_',
    callback: (token) => { turnstileToken.value = token; },
    'expired-callback': () => { turnstileToken.value = ''; },
    'error-callback': () => { turnstileToken.value = ''; },
    theme: 'light',
  });
};

onMounted(() => { loadTurnstile(); });
onBeforeUnmount(() => {
  if (turnstileWidgetId !== null && window.turnstile) window.turnstile.remove(turnstileWidgetId);
});

const handleRegister = async () => {
    // Touch & validate all
    Object.keys(touched).forEach(k => { touched[k] = true; validateField(k); });

    if (!name.value || !email.value || !password.value || !password_confirmation.value) return;
    if (!isPasswordValid.value) { errorMsg.value = 'Mật khẩu chưa đáp ứng đủ yêu cầu bảo mật'; return; }
    if (password.value !== password_confirmation.value) return;
    if (!agreeTerms.value) return;
    if (!turnstileToken.value) { errorMsg.value = 'Vui lòng xác thực CAPTCHA'; return; }

    errorMsg.value = '';
    isSubmitting.value = true;

    try {
        const response = await authService.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value,
            turnstile_token: turnstileToken.value
        });

        if (response.data.status === 'success') {
            // Lưu session (token + user) và chuyển thẳng về trang chủ
            await authStore.setSession(response.data.access_token, {
                isLoggedIn: true,
                ...response.data.user
            });

            showToast('Đăng ký tài khoản thành công! Chào mừng bạn đến với Ocean.', 'success');
            router.push(getDefaultRouteForRole(response.data.user?.role));
        }
    } catch (error) {
        let errorText = 'Có lỗi xảy ra, vui lòng thử lại.';
        if (error.response?.status === 429) {
            errorText = 'Bạn đã thử quá nhiều lần! Vui lòng đợi 1 phút rồi thử lại.';
        } else if (error.response?.data?.errors) {
            errorText = Object.values(error.response.data.errors)[0][0];
        } else if (error.response?.data?.message) {
            errorText = error.response.data.message;
        }
        errorMsg.value = errorText;
        showToast(errorText, 'danger');
        if (window.turnstile && turnstileWidgetId !== null) {
          window.turnstile.reset(turnstileWidgetId);
          turnstileToken.value = '';
        }
    } finally {
        isSubmitting.value = false;
    }
};

</script>

<template>
  <AuthLayout>
      <div class="auth-page">
        <div class="auth-card">
          <!-- Icon -->
          <div class="auth-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
          </div>

          <h1 class="auth-title">Tạo tài khoản</h1>
          <p class="auth-subtitle">Bắt đầu hành trình mua sắm cùng Ocean</p>

          <!-- Error Banner -->
          <div v-if="errorMsg" class="error-banner">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            {{ errorMsg }}
          </div>

          <form @submit.prevent="handleRegister" class="auth-form" novalidate>
            <!-- Name -->
            <div class="form-group" :class="{ 'has-error': touched.name && fieldErrors.name }">
              <label for="reg-name">Họ và tên</label>
              <input id="reg-name" type="text" v-model="name" placeholder="Nguyễn Văn A" :disabled="isSubmitting" @blur="onBlur('name')" @input="validateField('name')" />
              <p v-if="touched.name && fieldErrors.name" class="field-error">{{ fieldErrors.name }}</p>
            </div>

            <!-- Email -->
            <div class="form-group" :class="{ 'has-error': touched.email && fieldErrors.email }">
              <label for="reg-email">Email</label>
              <input id="reg-email" type="email" v-model="email" placeholder="your@email.com" :disabled="isSubmitting" @blur="onBlur('email')" @input="validateField('email')" />
              <p v-if="touched.email && fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
            </div>

            <!-- Password -->
            <div class="form-group" :class="{ 'has-error': touched.password && fieldErrors.password }">
              <label for="reg-password">Mật khẩu</label>
              <div class="input-password">
                <input id="reg-password" :type="showPassword ? 'text' : 'password'" v-model="password" placeholder="Mật khẩu" :disabled="isSubmitting" @blur="onBlur('password')" @input="validateField('password')" />
                <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                  <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <p v-if="touched.password && fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
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
            <div class="form-group" :class="{ 'has-error': touched.confirm && fieldErrors.confirm }">
              <label for="reg-confirm">Xác nhận mật khẩu</label>
              <div class="input-password">
                <input id="reg-confirm" :type="showConfirmPassword ? 'text' : 'password'" v-model="password_confirmation" placeholder="Nhập lại mật khẩu" :disabled="isSubmitting" @blur="onBlur('confirm')" @input="validateField('confirm')" />
                <button type="button" class="toggle-pw" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1">
                  <svg v-if="!showConfirmPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <p v-if="touched.confirm && fieldErrors.confirm" class="field-error">{{ fieldErrors.confirm }}</p>
            </div>

            <!-- Terms -->
            <div :class="{ 'has-error-terms': touched.terms && fieldErrors.terms }">
              <label class="checkbox-label">
                <input type="checkbox" v-model="agreeTerms" @change="touched.terms = true; validateField('terms')" />
                <span>Tôi đồng ý với <router-link to="/terms" class="link">Điều khoản dịch vụ</router-link> và <router-link to="/privacy" class="link">Chính sách bảo mật</router-link></span>
              </label>
              <p v-if="touched.terms && fieldErrors.terms" class="field-error" style="margin-left: 24px;">{{ fieldErrors.terms }}</p>
            </div>

            <!-- Cloudflare Turnstile CAPTCHA -->
            <div class="turnstile-wrapper">
              <div id="turnstile-register"></div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-primary" :disabled="isSubmitting || !turnstileToken">
              <span v-if="isSubmitting" class="spinner"></span>
              {{ isSubmitting ? 'Đang xử lý...' : 'Đăng ký' }}
            </button>
          </form>

          <p class="auth-switch">
            Đã có tài khoản?
            <router-link to="/client/login">Đăng nhập</router-link>
          </p>
        </div>
      </div>



  </AuthLayout>
</template>

<style scoped>
.auth-page { flex: 1; padding: 48px 24px; display: flex; align-items: center; justify-content: center; font-family: var(--font-inter, 'Inter', sans-serif); }
.auth-card { width: 100%; max-width: 440px; background: var(--card-bg); border-radius: 16px; padding: 40px 36px 36px; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04); }
.auth-icon { width: 56px; height: 56px; border-radius: 14px; background: #FFF0F3; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.auth-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
.auth-subtitle { text-align: center; font-size: 0.85rem; color: #636E72; margin-bottom: 24px; }
.error-banner { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #fff0f0; border: 1px solid #fecaca; border-radius: 10px; color: #dc2626; font-size: 0.85rem; font-weight: 500; margin-bottom: 16px; }
.auth-form { display: flex; flex-direction: column; gap: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-group input { width: 100%; padding: 12px 14px; border: 1.5px solid #E9ECEF; border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: var(--text-main); background: #F8F9FA; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1); background: var(--card-bg); }
.form-group input::placeholder { color: #636E72; }

/* Error state */
.form-group.has-error input { border-color: #ef4444; }
.form-group.has-error input:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
.field-error { font-size: 0.78rem; color: #ef4444; margin-top: 5px; display: flex; align-items: center; gap: 4px; }

.input-password { position: relative; }
.input-password input { padding-right: 44px; }
.toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #636E72; display: flex; padding: 4px; transition: color 0.2s; }
.toggle-pw:hover { color: var(--primary); }

.password-checklist { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; background: #F8F9FA; border-radius: 10px; border: 1px solid #FFE0E8; }
.check-item { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; color: #636E72; transition: color 0.2s; }
.check-item.valid { color: #16a34a; }

.turnstile-wrapper { display: flex; justify-content: center; margin: 4px 0; }

.checkbox-label { display: flex; align-items: flex-start; gap: 8px; font-size: 0.82rem; color: #555; cursor: pointer; line-height: 1.4; }
.checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; margin-top: 1px; flex-shrink: 0; }
.has-error-terms .checkbox-label { color: #ef4444; }
.link { color: var(--primary); text-decoration: none; font-weight: 500; }
.link:hover { text-decoration: underline; }
.btn-primary { width: 100%; padding: 13px; border: none; border-radius: 10px; background: var(--primary); color: #fff; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: background 0.2s, transform 0.1s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-primary:hover:not(:disabled) { background: #C4305D; }
.btn-primary:active:not(:disabled) { transform: scale(0.98); }
.btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
.spinner { width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, 0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.auth-switch { text-align: center; font-size: 0.85rem; color: #666; margin-top: 24px; }
.auth-switch a { color: var(--primary); font-weight: 600; text-decoration: underline; }
.auth-switch a:hover { color: #C4305D; }
</style>
