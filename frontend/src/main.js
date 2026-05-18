import { createApp } from 'vue';
import App from './App.vue';
import './assets/main.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './bootstrap';
import './echo';
import { createPinia } from 'pinia';
const pinia = createPinia();
import router from './router';
import { initSessionSync } from './sessionSync';

// Khởi tạo session sync trước khi mount app
// Đảm bảo tab mới có thể nhận session từ tab cũ trong ~150ms
initSessionSync().then(() => {
    const app = createApp(App);
    app.use(router);
    app.use(pinia);
    app.mount('#app');
});
