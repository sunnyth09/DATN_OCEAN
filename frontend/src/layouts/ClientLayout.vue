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
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

.page-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  color: #2D3436;
  background: #FFFFFF;
  overflow-x: clip;
}

/* site-main không cần max-width riêng —
   mỗi section tự dùng Bootstrap .container để căn lề */
.site-main {
  flex: 1;
  width: 100%;
}
</style>
