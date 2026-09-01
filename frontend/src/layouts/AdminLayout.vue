<template>
  <BackOfficeShell
    :sidebar-component="AdminAside"
    default-title="Tổng quan"
    section-label="Bảng điều khiển"
    :fluid="true"
  >
    <router-view v-slot="{ Component, route }">
      <keep-alive :max="15">
        <component :is="Component" :key="route.fullPath" />
      </keep-alive>
    </router-view>
  </BackOfficeShell>
</template>

<script setup>
import BackOfficeShell from '../components/BackOfficeShell.vue';
import AdminAside from '../components/AdminAside.vue';
</script>
