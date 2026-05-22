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
  overflow-x: hidden;
}

.site-main {
  flex: 1;
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 32px; /* 32px for desktop */
  width: 100%;
}

@media (max-width: 1024px) {
  .site-main {
    padding: 0 24px; /* 24px on tablet */
  }
}

@media (max-width: 768px) {
  .site-main {
    padding: 0 16px; /* 16px on mobile */
  }
}
</style>
