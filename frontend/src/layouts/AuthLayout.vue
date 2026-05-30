<script setup>
import ClientHeader from '@/components/ClientHeader.vue';
import ClientFooter from '@/components/ClientFooter.vue';
</script>

<template>
  <div class="auth-shell">
    <ClientHeader />
    <main class="auth-shell__main">
      <slot />
    </main>
    <ClientFooter />
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--background);
}

.auth-shell__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 120px);
  background: #f8fafc;
}
</style>
