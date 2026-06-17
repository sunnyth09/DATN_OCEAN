<script setup>
import { onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { authService } from '@/services/authService';
import { getDefaultRouteForRole, useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const status = ref('loading'); // loading | success | error
const errorMsg = ref('');

onMounted(async () => {
  const code = route.query.code;

  if (!code) {
    status.value = 'error';
    errorMsg.value = 'Không nhận được mã xác thực từ Facebook!';
    setTimeout(() => router.push('/client/login'), 3000);
    return;
  }

  try {
    const response = await authService.exchangeFacebookCode(code);

    if (response.data.status === 'success') {
      await authStore.setSession(response.data.access_token, {
        isLoggedIn: true,
        ...response.data.user
      });

      status.value = 'success';

      const redirect = sessionStorage.getItem('auth_redirect');
      sessionStorage.removeItem('auth_redirect');

      setTimeout(() => {
        router.push(redirect || getDefaultRouteForRole(response.data.user?.role));
      }, 1500);
    }
  } catch (error) {
    status.value = 'error';
    errorMsg.value = error.response?.data?.message || 'Đăng nhập Facebook thất bại!';
    setTimeout(() => router.push('/client/login'), 3000);
  }
});
</script>

<template>
  <div class="callback-page">
    <div class="callback-card">
      <!-- Loading -->
      <template v-if="status === 'loading'">
        <div class="callback-spinner"></div>
        <h2>Đang xử lý đăng nhập...</h2>
        <p>Vui lòng đợi trong giây lát</p>
      </template>

      <!-- Success -->
      <template v-if="status === 'success'">
        <div class="callback-icon success">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2>Đăng nhập thành công!</h2>
        <p>Đang chuyển hướng...</p>
      </template>

      <!-- Error -->
      <template v-if="status === 'error'">
        <div class="callback-icon error">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <h2>Đăng nhập thất bại</h2>
        <p>{{ errorMsg }}</p>
        <p class="redirect-note">Đang quay lại trang đăng nhập...</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.callback-page {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  background: #F8F9FA; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
}
.callback-card {
  text-align: center; background: #fff; border-radius: 16px;
  padding: 48px 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.06);
  max-width: 400px; width: 100%;
}
.callback-card h2 { font-size: 1.3rem; font-weight: 700; color: #2D3436; margin: 20px 0 8px; }
.callback-card p { font-size: 0.9rem; color: #636E72; margin: 0; }

.callback-spinner {
  width: 48px; height: 48px; border: 4px solid #E9ECEF;
  border-top-color: #E63B6F; border-radius: 50%;
  animation: spin 0.8s linear infinite; margin: 0 auto;
}
@keyframes spin { to { transform: rotate(360deg); } }

.callback-icon {
  width: 64px; height: 64px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; margin: 0 auto;
}
.callback-icon.success { background: #dcfce7; color: #16a34a; }
.callback-icon.error { background: #fee2e2; color: #dc2626; }

.redirect-note { font-size: 0.78rem; color: #636E72; margin-top: 12px !important; }
</style>
