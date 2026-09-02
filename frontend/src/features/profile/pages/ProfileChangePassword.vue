<template>
  <div class="change-pw-page">
    <div class="section-header">
      <h1 class="section-title">Đổi mật khẩu</h1>
      <p class="section-desc">Để bảo mật tài khoản, vui lòng không chia sẻ mật khẩu cho người khác</p>
    </div>

    <div class="form-card">

      <!-- Thông báo lỗi mật khẩu hiện tại sai -->
      <div v-if="serverError" class="alert alert-error">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        {{ serverError }}
      </div>

      <form @submit.prevent="submitChangePassword" class="pw-form">

        <div class="form-group">
          <label class="form-label">Mật khẩu hiện tại</label>
          <div class="input-wrapper">
            <input
              :type="show.current ? 'text' : 'password'"
              v-model="form.current_password"
              class="form-input"
              :class="{ 'form-input--error': errors.current_password }"
              placeholder="Nhập mật khẩu hiện tại"
              autocomplete="current-password"
              required
            />
            <button type="button" class="eye-btn" @click="show.current = !show.current" tabindex="-1">
              <svg v-if="show.current" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <span v-if="errors.current_password" class="error-text">{{ errors.current_password[0] }}</span>
        </div>

        <div class="form-divider"></div>

        <div class="form-group">
          <label class="form-label">Mật khẩu mới</label>
          <div class="input-wrapper">
            <input
              :type="show.newPw ? 'text' : 'password'"
              v-model="form.new_password"
              class="form-input"
              :class="{ 'form-input--error': errors.new_password }"
              placeholder="Ít nhất 8 ký tự"
              autocomplete="new-password"
              minlength="8"
              required
            />
            <button type="button" class="eye-btn" @click="show.newPw = !show.newPw" tabindex="-1">
              <svg v-if="show.newPw" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <span v-if="errors.new_password" class="error-text">{{ errors.new_password[0] }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Xác nhận mật khẩu mới</label>
          <div class="input-wrapper">
            <input
              :type="show.confirm ? 'text' : 'password'"
              v-model="form.new_password_confirmation"
              class="form-input"
              :class="{ 'form-input--error': confirmMismatch }"
              placeholder="Nhập lại mật khẩu mới"
              autocomplete="new-password"
              minlength="8"
              required
            />
            <button type="button" class="eye-btn" @click="show.confirm = !show.confirm" tabindex="-1">
              <svg v-if="show.confirm" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <span v-if="confirmMismatch" class="error-text">Mật khẩu xác nhận không khớp.</span>
        </div>

        <!-- Strength indicator -->
        <div v-if="form.new_password" class="strength-wrap">
          <div class="strength-header">
            <span class="strength-title">Độ bảo mật:</span>
            <span class="strength-label" :class="strength.cls">{{ strength.label }}</span>
          </div>
          <div class="strength-bar">
            <div class="strength-fill" :class="strength.cls" :style="{ width: strength.pct + '%' }"></div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary" :disabled="loading">
            <span v-if="loading" class="spinner"></span>
            <span v-else>Xác nhận đổi mật khẩu</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/axios';
import { useToast } from '@/composables/useToast';

const { showToast } = useToast();

const form = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
});

const errors      = ref({});
const serverError = ref('');
const loading     = ref(false);
const show        = ref({ current: false, newPw: false, confirm: false });

const confirmMismatch = computed(() =>
  form.value.new_password_confirmation.length > 0 &&
  form.value.new_password !== form.value.new_password_confirmation
);

// Đánh giá độ mạnh mật khẩu
const strength = computed(() => {
  const pw = form.value.new_password;
  if (!pw) return { pct: 0, label: '', cls: '' };
  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const map = [
    { pct: 20,  label: 'Rất yếu',  cls: 'str-veryweak'  },
    { pct: 40,  label: 'Yếu',      cls: 'str-weak'      },
    { pct: 60,  label: 'Trung bình', cls: 'str-medium'  },
    { pct: 80,  label: 'Mạnh',     cls: 'str-strong'    },
    { pct: 100, label: 'Rất mạnh', cls: 'str-verystrong' },
  ];
  return map[Math.min(score, 4)];
});

const submitChangePassword = async () => {
  errors.value      = {};
  serverError.value = '';

  if (confirmMismatch.value) return;
  if (form.value.new_password.length < 8) return;

  loading.value = true;
  try {
    const res = await api.put('/profile/password', {
      current_password:      form.value.current_password,
      new_password:          form.value.new_password,
      new_password_confirmation: form.value.new_password_confirmation,
    });

    showToast(res.data.message || 'Đổi mật khẩu thành công!');
    form.value = { current_password: '', new_password: '', new_password_confirmation: '' };
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {};
    } else if (err.response?.status === 400) {
      serverError.value = err.response.data.message || 'Mật khẩu hiện tại không đúng.';
    } else {
      serverError.value = 'Đã xảy ra lỗi, vui lòng thử lại sau.';
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.change-pw-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header { margin-bottom: 4px; }
.section-title  { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0; }
.section-desc   { font-size: 0.875rem; color: #6b7280; margin: 4px 0 0; }

.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
}
.alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

.form-card {
  background: var(--card-bg);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 28px;
}

.pw-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 480px;
}

.form-group { display: flex; flex-direction: column; gap: 7px; }

.form-label { font-size: 0.875rem; font-weight: 500; color: #374151; }

.input-wrapper { position: relative; display: flex; align-items: center; }

.form-input {
  width: 100%;
  padding: 10px 40px 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  color: var(--text-main);
  outline: none;
  transition: border 0.15s, box-shadow 0.15s;
  background: var(--card-bg);
  box-sizing: border-box;
}
.form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230,59,111,0.12); }
.form-input--error { border-color: #ef4444; }

.eye-btn {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  cursor: pointer;
  color: #9ca3af;
  padding: 4px;
  display: flex;
  align-items: center;
}
.eye-btn:hover { color: var(--primary); }

.form-divider { height: 1px; background: #f3f4f6; }

.error-text { font-size: 0.78rem; color: #ef4444; }

/* Password Strength */
.strength-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
.strength-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; }
.strength-title { color: #6b7280; font-weight: 500; }
.strength-label { font-weight: 700; transition: color 0.3s ease; }
.strength-bar  { width: 100%; height: 6px; background: #e5e7eb; border-radius: 99px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease-out, background-color 0.4s ease; }

.strength-fill.str-veryweak { background-color: #ef4444; }
.strength-label.str-veryweak { color: #ef4444; }

.strength-fill.str-weak { background-color: #f97316; }
.strength-label.str-weak { color: #f97316; }

.strength-fill.str-medium { background-color: #f59e0b; }
.strength-label.str-medium { color: #f59e0b; }

.strength-fill.str-strong { background-color: #22c55e; }
.strength-label.str-strong { color: #22c55e; }

.strength-fill.str-verystrong { background-color: #10b981; }
.strength-label.str-verystrong { color: #10b981; }

/* Actions */
.form-actions { margin-top: 8px; }
.btn-primary {
  padding: 11px 28px;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 180px;
  transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: var(--primary-dark); }
.btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }

.spinner {
  width: 17px;
  height: 17px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 640px) {
  .form-card { padding: 16px; }
}
</style>
