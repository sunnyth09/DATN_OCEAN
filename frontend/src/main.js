
import { createApp } from 'vue';
import App from './App.vue';
import './assets/main.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './bootstrap';
import './echo';
import router from './router';
import { initSessionSync } from './sessionSync';
import { pinia } from './stores';
import { useAuthStore } from './stores/auth';
import { useCartStore } from './stores/cart';
import { useUiStore } from './stores/ui';

// Khởi tạo session sync trước khi mount app
// Đảm bảo tab mới có thể nhận session từ tab cũ trong ~150ms
initSessionSync().then(() => {
    const app = createApp(App);
    app.use(pinia);
    app.use(router);

    const authStore = useAuthStore(pinia);
    const cartStore = useCartStore(pinia);
    const uiStore = useUiStore(pinia);

    authStore.hydrate();
    cartStore.bindWindowListeners();
    if (!authStore.isAdminUser) {
        cartStore.fetchCount();
    }
    uiStore.initializeBackofficeTheme();

    window.addEventListener('user-updated', () => authStore.hydrate());
    window.addEventListener('auth-logout', () => {
        authStore.clearSession({ notify: false });
        cartStore.reset();
    });

    app.mount('#app');
});
