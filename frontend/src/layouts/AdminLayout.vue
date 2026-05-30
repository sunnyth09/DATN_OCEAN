<template>
  <BackOfficeShell
    :sidebar-component="AdminAside"
    default-title="Tổng quan"
    section-label="Bảng điều khiển"
  >
    <router-view />
  </BackOfficeShell>
</template>

<script setup>
import BackOfficeShell from '../components/BackOfficeShell.vue';
import AdminAside from '../components/AdminAside.vue';
</script>
