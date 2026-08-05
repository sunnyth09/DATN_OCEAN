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
}

/* site-main không cần max-width riêng —
   mỗi section tự dùng Bootstrap .container để căn lề */
.site-main {
  flex: 1;
  width: 100%;
  overflow-x: clip;
}
</style>
