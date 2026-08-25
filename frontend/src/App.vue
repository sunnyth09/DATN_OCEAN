<template>
  <router-view></router-view>
  <AppToast />
</template>

<script setup>
import AppToast from './components/AppToast.vue';
// NOTE: Không được xóa auth_token/user ở đây.
// Token được quản lý tập trung bởi authStore (stores/auth.js).
// Việc xóa storage tại đây sẽ gây văng trang ngay khi app khởi động.
</script>

<style>
/* Đã loại bỏ overflow-x: clip trên html, body để không làm mất thuộc tính sticky của header và window.scrollY */
html, body {
  max-width: 100%;
}
</style>
