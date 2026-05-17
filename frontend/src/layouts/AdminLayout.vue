<template>
  <div class="admin-layout">
    <AdminAside />

    <div class="admin-main">
      <!-- Header -->
      <header class="admin-header">
        <div class="header-left">
          <h1 class="page-title">Dashboard</h1>
        </div>

        <div class="header-right">
          <!-- Dark Mode Toggle -->
          <button @click="toggleDarkMode" class="theme-toggle-btn" title="Chuyển đổi Sáng/Tối">
            <svg v-if="isDarkMode" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
          </button>
          
          <router-link to="/" class="back-home-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Trang chủ</span>
          </router-link>
        </div>
      </header>

      <!-- Content -->
      <main class="admin-content">
        <div class="content-inner">
          <router-view></router-view>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminAside from '../components/AdminAside.vue';

const isDarkMode = ref(false);

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value;
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark');
    localStorage.setItem('admin_theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('admin_theme', 'light');
  }
};

onMounted(() => {
  const savedTheme = localStorage.getItem('admin_theme');
  if (savedTheme === 'dark') {
    isDarkMode.value = true;
    document.documentElement.classList.add('dark');
  }
});
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--ocean-deepest);
  font-family: var(--font-inter);
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* Header */
.admin-header {
  height: 70px;
  background: var(--card-bg, #f8f9fa);
  border-bottom: 1px solid var(--border-color, #eee);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
}

.page-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-main, #1a1a1a);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-left {
  display: flex;
  align-items: center;
}

.theme-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--ocean-deepest);
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}

.theme-toggle-btn:hover {
  color: #E63B6F;
  background: var(--hover-bg);
}

.back-home-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: var(--card-bg, white);
  border: 1px solid var(--border-color, #ddd);
  border-radius: 6px;
  color: var(--text-muted, #555);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s;
}

.back-home-btn:hover {
  background: var(--hover-bg, #f0f7ff);
  border-color: var(--ocean-blue, #1d4ed8);
  color: var(--ocean-blue, #1d4ed8);
}

.back-home-btn span {
  font-weight: 600;
}

/* Icon button */
.icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--ocean-deepest);
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}



/* Content */
.admin-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  background: var(--ocean-deepest);
}

.content-inner {
  max-width: 1400px;
  margin: 0 auto;
}
</style>
