<template>
  <div class="page-wrapper checkout-layout">
    <!-- Minimal Header without Search/Cart/Flash Sale widgets -->
    <header class="checkout-header shadow-sm">
      <div class="container py-3 d-flex align-items-center justify-content-between">
        <router-link to="/" class="checkout-logo">
          <img :src="BASE_URL + '/storage/logo/LOGO_QS.png'" alt="Logo" class="logo-img" width="70" height="auto" />
        </router-link>
        <div class="header-right d-flex align-items-center gap-4">
          <div v-if="!authStore.isAuthenticated" class="auth-links">
            <span class="text-muted small me-2">Đã có tài khoản?</span>
            <router-link to="/client/login" class="text-primary fw-medium text-decoration-none">Đăng nhập ngay</router-link>
          </div>
          <div class="secure-badge d-flex align-items-center text-success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0110 0v4"></path>
            </svg>
            <span>Thanh toán an toàn</span>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="site-main">
      <router-view></router-view>
    </main>

    <!-- Minimal Footer -->
    <footer class="checkout-footer text-center py-4">
      <div class="container">
        <p class="text-muted small mb-0">&copy; {{ new Date().getFullYear() }} Ocean. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';
import { getAppBaseUrl } from "@/utils/url";
const authStore = useAuthStore();
const BASE_URL = getAppBaseUrl();
</script>

<style scoped>
.page-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  color: var(--text-main);
  background: var(--card-bg);
}

.site-main {
  flex: 1;
  width: 100%;
}

.checkout-header {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
}

.checkout-header .container {
  max-width: 1200px;
  margin: 0 auto;
}

.checkout-logo {
  text-decoration: none;
  display: flex;
  align-items: center;
}

.logo-text {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--primary);
  letter-spacing: -0.5px;
}

.secure-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #10b981;
  font-weight: 600;
  font-size: 0.9rem;
}

.checkout-footer {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.checkout-footer .container {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
