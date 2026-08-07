<template>
  <div class="page-wrapper">
    <!-- SPLASH SCREEN -->
    <Transition name="splash">
        <div v-if="showSplash" class="splash-screen">
            <div class="splash-logo-container">
                <img :src="splashLogo" alt="Ocean Sport Logo" class="splash-logo pulse-animation" />
            </div>
        </div>
    </Transition>

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
import { ref, onMounted } from 'vue';
import ClientHeader from '../components/ClientHeader.vue';
import ClientFooter from '../components/ClientFooter.vue';
import ChatbotWidget from '../components/ChatbotWidget.vue';
import splashLogo from '@/assets/images/OCEAN_SPORT_LOGO_v0_tranperant.png';

const showSplash = ref(true);

onMounted(() => {
    // Ẩn splash screen sau khoảng 800ms để đảm bảo UI kịp render mượt mà
    setTimeout(() => {
        showSplash.value = false;
    }, 800);
});
</script>

<style scoped>
/* SPLASH SCREEN CSS */
.splash-screen {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.splash-leave-active {
    transition: opacity 0.6s ease, transform 0.6s ease;
}
.splash-leave-to {
    opacity: 0;
    transform: scale(1.05);
}

.splash-logo-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}

.splash-logo {
    width: 200px;
    height: auto;
}

.pulse-animation {
    animation: fadeGlow 2s ease-in-out infinite alternate;
}

@keyframes fadeGlow {
    0% {
        opacity: 0.3;
        filter: brightness(0.8) drop-shadow(0 0 0px rgba(230, 59, 111, 0));
        transform: scale(0.98);
    }

    100% {
        opacity: 1;
        filter: brightness(1.1) drop-shadow(0 0 20px rgba(230, 59, 111, 0.3));
        transform: scale(1.02);
    }
}

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
