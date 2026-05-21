<template>
  <div class="backoffice-shell">
    <component :is="sidebarComponent" />

    <div class="backoffice-main">
      <header class="backoffice-header">
        <div class="backoffice-header__meta">
          <p v-if="sectionLabel" class="backoffice-header__eyebrow">
            {{ sectionLabel }}
          </p>
          <h1 class="backoffice-header__title">{{ resolvedTitle }}</h1>
        </div>

        <div class="backoffice-header__actions">
          <button
            type="button"
            class="shell-icon-btn"
            :aria-pressed="isDarkMode"
            :title="isDarkMode ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
            @click="toggleDarkMode"
          >
            <svg
              v-if="isDarkMode"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
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
            <svg
              v-else
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
          </button>

          <router-link to="/" class="back-home-btn">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Trang chủ</span>
          </router-link>
        </div>
      </header>

      <main class="backoffice-content">
        <div class="backoffice-content__inner">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const props = defineProps({
  sidebarComponent: {
    type: [Object, Function],
    required: true,
  },
  defaultTitle: {
    type: String,
    default: 'Dashboard',
  },
  sectionLabel: {
    type: String,
    default: '',
  },
});

const route = useRoute();
const isDarkMode = ref(false);

const resolvedTitle = computed(() => {
  for (let i = route.matched.length - 1; i >= 0; i -= 1) {
    const matchedTitle = route.matched[i]?.meta?.title;
    if (matchedTitle) return matchedTitle;
  }

  return props.defaultTitle;
});

const syncTheme = (theme) => {
  isDarkMode.value = theme === 'dark';
  document.documentElement.classList.toggle('dark', isDarkMode.value);
  localStorage.setItem('admin_theme', isDarkMode.value ? 'dark' : 'light');
};

const toggleDarkMode = () => {
  syncTheme(isDarkMode.value ? 'light' : 'dark');
};

onMounted(() => {
  syncTheme(localStorage.getItem('admin_theme') || 'light');
});
</script>

<style scoped>
.backoffice-shell {
  display: flex;
  min-height: 100vh;
  background: var(--ocean-deepest);
  color: var(--text-main);
  font-family: var(--font-primary);
}

.backoffice-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.backoffice-header {
  min-height: var(--shell-header-height);
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid var(--border-color);
  backdrop-filter: blur(14px);
  box-shadow: var(--shadow-sm);
  position: sticky;
  top: 0;
  z-index: 20;
}

:global(html.dark) .backoffice-header {
  background: rgba(22, 24, 25, 0.92);
}

.backoffice-header__meta {
  min-width: 0;
}

.backoffice-header__eyebrow {
  margin: 0 0 4px;
  color: var(--text-light);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.backoffice-header__title {
  margin: 0;
  color: var(--text-main);
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.2;
}

.backoffice-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.shell-icon-btn,
.back-home-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 40px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-color);
  background: var(--card-bg);
  color: var(--text-muted);
  transition: all 0.2s ease;
}

.shell-icon-btn {
  width: 40px;
  cursor: pointer;
}

.back-home-btn {
  padding: 0 14px;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
}

.shell-icon-btn:hover,
.back-home-btn:hover {
  color: var(--primary);
  border-color: rgba(230, 59, 111, 0.28);
  background: var(--hover-bg);
}

.backoffice-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.backoffice-content__inner {
  max-width: var(--layout-max-width);
  margin: 0 auto;
}

@media (max-width: 1024px) {
  .backoffice-header {
    padding: 0 20px;
  }

  .backoffice-content {
    padding: 20px;
  }
}

@media (max-width: 768px) {
  .backoffice-shell {
    flex-direction: column;
  }

  .backoffice-header {
    padding: 14px 16px;
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .backoffice-header__title {
    font-size: 1.05rem;
  }

  .backoffice-header__actions {
    width: 100%;
    justify-content: flex-end;
  }

  .backoffice-content {
    padding: 16px;
  }
}
</style>
