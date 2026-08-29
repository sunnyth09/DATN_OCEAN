<template>
  <router-view></router-view>
  <AppToast />
</template>

<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import AppToast from './components/AppToast.vue';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();
const { unreadNotificationCount } = storeToRefs(authStore);
const route = useRoute();

// Helper: lấy base title từ route hiện tại
const getBaseTitle = () => {
  const title = route.meta?.title;
  const isAdmin = route.matched?.some(r => r.path === '/admin');
  if (title) return isAdmin ? `${title} | QS Admin` : `${title} | Ocean Sport`;
  return 'Ocean Sport';
};

// ── Blinking tab title ──────────────────────────────────────────────────────
let blinkInterval = null;
let blinkTimeout  = null;
let isBlinkOn = false;

const stopBlink = () => {
  if (blinkInterval) { clearInterval(blinkInterval); blinkInterval = null; }
  if (blinkTimeout)  { clearTimeout(blinkTimeout);   blinkTimeout  = null; }
  document.title = getBaseTitle();
};

const startBlink = (count) => {
  stopBlink(); // dọn interval/timeout cũ nếu có
  const notifTitle = `(${count}) 🔔 thông báo mới!`;
  const baseTitle  = getBaseTitle();

  document.title = notifTitle;
  isBlinkOn = true;

  blinkInterval = setInterval(() => {
    isBlinkOn = !isBlinkOn;
    document.title = isBlinkOn ? notifTitle : baseTitle;
  }, 1200); // nhấp nháy mỗi 1.2 giây

  // Tự động dừng sau 10 giây
  blinkTimeout = setTimeout(stopBlink, 10_000);
};

// Theo dõi số thông báo thay đổi
watch(unreadNotificationCount, (count) => {
  if (count > 0) {
    startBlink(count);
  } else {
    stopBlink();
  }
}, { immediate: false });

onMounted(() => {
  // Dọn dẹp token/user cũ ở localStorage (di sản từ phiên bản cũ dùng localStorage)
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
});

onUnmounted(() => {
  stopBlink();
});
</script>

<style>
/* Đã loại bỏ overflow-x: clip trên html, body để không làm mất thuộc tính sticky của header và window.scrollY */
html, body {
  max-width: 100%;
}
</style>

