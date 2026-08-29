<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { getAppBaseUrl } from '@/utils/url';

const BASE_URL = getAppBaseUrl();
import { useToast } from '@/composables/useToast';
import { authService } from '@/services/authService';
import { getDefaultRouteForRole, useAuthStore } from '@/stores/auth';

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const rememberMe = ref(false);
const isSubmitting = ref(false);
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();

const turnstileToken = ref('');
let turnstileWidgetId = null;

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
  const container = document.getElementById('turnstile-login');
  if (!container || !window.turnstile) return;
  container.innerHTML = '';
  turnstileWidgetId = window.turnstile.render('#turnstile-login', {
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

const resolveRedirectTarget = (user) => {
  const redirect = Array.isArray(route.query.redirect)
    ? route.query.redirect[0]
    : route.query.redirect;

  return redirect || getDefaultRouteForRole(user?.role);
};

const isFormValid = computed(() => {
  return email.value.trim() !== '' &&
    password.value.trim() !== '' &&
    !fieldErrors.email && !fieldErrors.password;
});

// Field-level validation errors
const fieldErrors = reactive({ email: '', password: '' });
const touched = reactive({ email: false, password: false });

const validateField = (field) => {
  if (field === 'email') {
    if (!email.value) fieldErrors.email = 'Vui lòng nhập email';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) fieldErrors.email = 'Email không hợp lệ';
    else fieldErrors.email = '';
  }
  if (field === 'password') {
    if (!password.value) fieldErrors.password = 'Vui lòng nhập mật khẩu';
    else fieldErrors.password = '';
  }
};

const onBlur = (field) => {
  touched[field] = true;
  validateField(field);
};

// Google OAuth
const loginWithGoogle = () => {
  const redirect = Array.isArray(route.query.redirect) ? route.query.redirect[0] : route.query.redirect;
  if (redirect) {
    sessionStorage.setItem('auth_redirect', redirect);
  }
  const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
  const redirectUri = import.meta.env.VITE_GOOGLE_REDIRECT_URI || `${window.location.origin}/client/auth/google/callback`;
  const scope = 'openid email profile';
  const url = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUri)}&response_type=code&scope=${encodeURIComponent(scope)}&access_type=offline&prompt=consent`;
  window.location.href = url;
};

// Facebook OAuth
const loginWithFacebook = () => {
  const redirect = Array.isArray(route.query.redirect) ? route.query.redirect[0] : route.query.redirect;
  if (redirect) {
    sessionStorage.setItem('auth_redirect', redirect);
  }
  const clientId = import.meta.env.VITE_FACEBOOK_ID || '1969230567301526';
  const redirectUri = import.meta.env.VITE_FACEBOOK_REDIRECT_URI || `${window.location.origin}/client/auth/facebook/callback`;
  const url = `https://www.facebook.com/v19.0/dialog/oauth?client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUri)}&response_type=code&scope=public_profile,email`;
  window.location.href = url;
};

const login = async () => {
  // Validate all fields
  touched.email = true;
  touched.password = true;
  validateField('email');
  validateField('password');

  if (fieldErrors.email || fieldErrors.password) return;

  if (!turnstileToken.value) {
    showToast('Vui lòng xác nhận bạn không phải là robot', 'danger');
    return;
  }

  isSubmitting.value = true;
  try {
    const response = await authService.login({
      email: email.value,
      password: password.value,
      turnstile_token: turnstileToken.value
    });

    if (response.data.status === 'success') {
      await authStore.setSession(response.data.access_token, {
        isLoggedIn: true,
        ...response.data.user
      });

      // Luôn lưu vào sessionStorage — SessionSync sẽ tự đồng bộ sang tab mới
      // Xóa sạch localStorage khỏi session cũ (nếu có)
      router.push(resolveRedirectTarget(response.data.user));
    }
  } catch (error) {
    if (error.validationErrors) {
      Object.assign(fieldErrors, error.validationErrors);
      showToast('Vui lòng kiểm tra lại thông tin nhập!', 'danger');
    } else {
      let msg = error.response?.data?.message || 'Đăng nhập thất bại!';
      if (error.response?.status === 429) {
        msg = 'Bạn đã thử quá nhiều lần! Vui lòng đợi 1 phút rồi thử lại.';
      }
      showToast(msg, 'danger');
    }

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
    <div class="auth-page container d-flex justify-content-center">
      <!-- Bootstrap container wrapping the boxed layout -->
      <div class="auth-box-classic">

        <!-- LEFT: Editorial Form Column -->
        <div class="auth-form-column">
          <div class="auth-form-card">

            <div class="brand">
              <router-link to="/" class="brand-logo-link">
                <img :src="BASE_URL + '/storage/logo/OCEAN_SPORT_LOGO_v0_tranperant.png'" alt="Ocean Sport"
                  class="brand-logo-img" />
              </router-link>
              <span class="brand-text">Ocean Sport</span>
            </div>

            <div class="auth-header">
              <h1 class="auth-title">Welcome back</h1>
              <p class="auth-subtitle">Vui lòng đăng nhập để tiếp tục bứt phá sân chơi của bạn.</p>
            </div>

            <form @submit.prevent="login" class="auth-form" novalidate>

              <!-- Setup Fields -->
              <div class="form-fields">
                <!-- Email -->
                <div class="form-field-item" :class="{ 'has-error': touched.email && fieldErrors.email }">
                  <div class="input-modern-wrapper">
                    <span class="icon">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                      </svg>
                    </span>
                    <input id="login-email" type="email" v-model="email" placeholder="Địa chỉ Email"
                      :disabled="isSubmitting" @blur="onBlur('email')" @input="validateField('email')" />
                  </div>
                  <p v-if="touched.email && fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
                </div>
                <!-- Password -->
                <div class="form-field-item" :class="{ 'has-error': touched.password && fieldErrors.password }">
                  <div class="input-modern-wrapper input-password">
                    <span class="icon">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                      </svg>
                    </span>
                    <input id="login-password" :type="showPassword ? 'text' : 'password'" v-model="password"
                      placeholder="Mật khẩu của bạn" :disabled="isSubmitting" @blur="onBlur('password')"
                      @input="validateField('password')" />
                    <button type="button" class="toggle-pw" @click="showPassword = !showPassword" tabindex="-1">
                      <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                      <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path
                          d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                        <line x1="1" y1="1" x2="23" y2="23" />
                      </svg>
                    </button>
                  </div>
                  <p v-if="touched.password && fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
                </div>
              </div>



              <div class="form-options">
                <label class="remember-me">
                  <input type="checkbox" v-model="rememberMe" />
                  <span class="checkmark"></span>
                  <span>Ghi nhớ đăng nhập</span>
                </label>
                <router-link to="/client/forgot-password" class="recover-link">Quên mật khẩu?</router-link>
              </div>

              <!-- Cloudflare Turnstile CAPTCHA -->
              <div class="turnstile-wrapper">
                <div id="turnstile-login"></div>
              </div>

              <!-- Action -->
              <button type="submit" class="btn-primary" :disabled="!isFormValid || isSubmitting || !turnstileToken">
                <span v-if="isSubmitting"></span>
                <span>{{ isSubmitting ? 'ĐANG TIẾN HÀNH...' : 'ĐĂNG NHẬP' }}</span>
                <svg v-if="!isSubmitting" class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </button>

              <div class="divider">
                <span>Hoặc tiếp tục bằng</span>
              </div>

              <!-- Social -->
              <div class="social-login-grid">
                <button class="btn-social google" @click="loginWithGoogle" type="button">
                  <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" width="20" height="20" />
                  Google
                </button>
                <button class="btn-social facebook" @click="loginWithFacebook" type="button">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877f2" xmlns="http://www.w3.org/2000/svg">
                    <path
                      d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                  </svg>
                  Facebook
                </button>
              </div>

              <p class="register-hint">Chưa có tài khoản? <router-link to="/client/register">Tạo tài khoản</router-link>
              </p>
            </form>
          </div>
        </div>

        <!-- RIGHT: Full Coverage Image Tile -->
        <div class="auth-art-column" style="background-image: url('/images/image_loginpage.png');">
          <!-- Image acts as cover background instead of floating element -->
        </div>

      </div>
    </div>
  </AuthLayout>
</template>

<style scoped>
.auth-page {
  flex: 1;
  width: 100%;

  /* Classic soft background */
  padding: 60px 0;
  font-family: var(--font-inter, 'Inter', sans-serif);
  display: flex;
  align-items: center;
  justify-content: center;
}

.auth-box-classic {
  width: 100%;
  max-width: 1000px;
  background: var(--card-bg);
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  /* Clean boxed shadow */
  display: flex;
  overflow: hidden;
  /* This makes the image cleanly cropped inside the box */
}

/* LEFT COLUMN - Editorial Form Area */
.auth-form-column {
  flex: 1;
  /* 50% width */
  padding: 48px;
  background: var(--card-bg);
  display: flex;
  align-items: center;
  position: relative;
  z-index: 2;
}

.auth-form-card {
  width: 100%;
  max-width: 420px;
  margin: 0 auto;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
}

.brand-logo-link {
  display: flex;
  align-items: center;
}

.brand-logo-img {
  height: 48px;
  width: auto;
  object-fit: contain;
}

.brand-text {
  color: var(--text-main);
  font-weight: 800;
  font-size: 1.3rem;
  letter-spacing: -0.5px;
}

.auth-header {
  margin-bottom: 32px;
}

.auth-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: #020617;
  margin-bottom: 8px;
  letter-spacing: -0.5px;
}

.auth-subtitle {
  color: #64748b;
  font-size: 0.95rem;
  line-height: 1.5;
  font-weight: 500;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-field-item {
  display: block;
  width: 100%;
  position: relative;
}

.input-modern-wrapper {
  display: flex;
  align-items: center;
  background: #f8fafc;
  border-radius: 10px;
  padding: 0 14px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
  height: 48px;
  width: 100%;
}

.input-modern-wrapper:focus-within {
  background: var(--card-bg);
  border-color: #ffd9de;
  box-shadow: 0 0 0 3px rgba(230, 59, 111, 0.1);
}

.form-field-item.has-error .input-modern-wrapper {
  border-color: #ef4444;
}

.input-modern-wrapper input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 0;
  font-size: 0.95rem;
  color: var(--text-main);
  outline: none;
  font-weight: 500;
  height: 100%;
  width: 100%;
}

.input-modern-wrapper input::placeholder {
  color: #94a3b8;
  font-weight: 400;
}

.input-modern-wrapper .icon {
  color: #94a3b8;
  display: flex;
  margin-right: 12px;
  align-items: center;
  justify-content: center;
}

.input-modern-wrapper:focus-within .icon {
  color: var(--primary);
}

.input-password {
  position: relative;
}

.toggle-pw {
  background: none;
  border: none;
  cursor: pointer;
  color: #64748b;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  transition: color 0.2s;
  margin-left: 8px;
}

.toggle-pw:hover {
  color: var(--primary);
}

.field-error {
  font-size: 0.82rem;
  color: #ef4444;
  margin-top: 6px;
  padding-left: 4px;
  margin-bottom: 0;
  display: block;
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
}

.remember-me {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #475569;
  cursor: pointer;
  position: relative;
  font-weight: 500;
}

.remember-me input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.remember-me .checkmark {
  height: 16px;
  width: 16px;
  background-color: #f8fafc;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  border: 1px solid #cbd5e1;
}

.remember-me:hover input~.checkmark {
  border-color: var(--primary);
}

.remember-me input:checked~.checkmark {
  background-color: var(--primary);
  border-color: var(--primary);
}

.remember-me input:checked~.checkmark:after {
  content: "";
  width: 3px;
  height: 7px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
  display: block;
  margin-bottom: 2px;
}

.recover-link {
  color: var(--primary);
  font-weight: 600;
  text-decoration: none;
  transition: 0.2s;
}

.recover-link:hover {
  text-decoration: underline;
}

.turnstile-wrapper {
  display: flex;
  justify-content: center;
  margin: 12px 0;
}

.captcha-box {
  display: flex;
  justify-content: flex-start;
}

.captcha-box.success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.captcha-text {
  color: #15803d;
  font-weight: 600;
  font-size: 0.85rem;
}

.text-success {
  color: #22c55e !important;
}

.btn-primary {
  background: var(--primary);
  color: white;
  border: none;
  border-radius: 8px;
  height: 44px;
  padding: 0 16px;
  font-weight: 700;
  font-size: 0.92rem;
  width: 100%;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 4px;
}

.btn-primary:hover:not(:disabled) {
  background: #d82f65;
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(230, 59, 111, 0.2);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary .btn-icon {
  opacity: 0.7;
  transition: opacity 0.3s;
}

.btn-primary:hover .btn-icon {
  opacity: 1;
}

.divider {
  display: flex;
  align-items: center;
  margin: 6px 0;
}

.divider::before,
.divider::after {
  content: "";
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
}

.divider span {
  padding: 0 16px;
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 600;
}

.social-login-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.btn-social {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--card-bg);
  color: #334155;
}

.btn-social:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.register-hint {
  text-align: center;
  font-size: 0.9rem;
  color: #64748b;
  margin-top: 4px;
  font-weight: 500;
}

.register-hint a {
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
  position: relative;
}

.register-hint a:hover {
  text-decoration: underline;
}

/* RIGHT COLUMN - Fully covered Image */
.auth-art-column {
  flex: 1;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  /* Image fills the entire right column without floating */
}

@media (max-width: 992px) {
  .auth-box-classic {
    flex-direction: column;
    max-width: 500px;
  }

  .auth-art-column {
    display: none;
    /* or define a fixed height for mobile, e.g. height: 250px; */
  }
}

@media (max-width: 576px) {
  .auth-form-column {
    padding: 32px 24px;
  }
}
</style>
