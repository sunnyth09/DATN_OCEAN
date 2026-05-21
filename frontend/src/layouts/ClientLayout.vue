<template>
  <div class="page-wrapper">
    <ClientHeader />

    <!-- Main Content -->
    <main class="site-main">
      <router-view></router-view>
    </main>

    <ClientFooter />

    <!-- AI Chatbot Floating Widget -->
    <ChatbotWidget />
  </div>
</template>

<script setup>
import ClientHeader from '../components/ClientHeader.vue';
import ClientFooter from '../components/ClientFooter.vue';
import ChatbotWidget from '../components/ChatbotWidget.vue';
</script>

<style scoped>
.page-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: var(--font-primary);
  color: var(--text-main);
  background: var(--background);
  overflow-x: hidden;
}

.site-main {
  flex: 1;
  max-width: var(--layout-max-width);
  margin: 0 auto;
  padding: 0 var(--page-gutter-desktop);
  width: 100%;
}

@media (max-width: 1024px) {
  .site-main {
    padding: 0 var(--page-gutter-tablet);
  }
}

@media (max-width: 768px) {
  .site-main {
    padding: 0 var(--page-gutter-mobile);
  }
}
</style>
