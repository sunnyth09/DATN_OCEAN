<template>
  <BackOfficeShell
    :sidebar-component="SellerAside"
    default-title="Kênh bán hàng"
    section-label="Vận hành cửa hàng"
  >
    <router-view />
  </BackOfficeShell>
</template>

<script setup>
import BackOfficeShell from '../components/BackOfficeShell.vue';
import SellerAside from '../components/SellerAside.vue';
</script>
