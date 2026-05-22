import axios from 'axios';
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// add token to request
window.axios.interceptors.request.use(config => {
    const token = useAuthStore(pinia).token || sessionStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});
