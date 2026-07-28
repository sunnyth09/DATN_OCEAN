<template>
  <router-view></router-view>
  <AppToast />
</template>

<script setup>
import { onMounted } from 'vue';
import AppToast from './components/AppToast.vue';

onMounted(() => {
  // Dọn dẹp token/user cũ ở localStorage (di sản từ phiên bản cũ dùng localStorage)
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
});
</script>

<style>
/* Đã loại bỏ overflow-x: clip trên html, body để không làm mất thuộc tính sticky của header và window.scrollY */
html, body {
  max-width: 100%;
}
</style>
