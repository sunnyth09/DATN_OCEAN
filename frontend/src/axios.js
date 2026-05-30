import axios from 'axios';
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || `${window.location.protocol}//${window.location.hostname}:8383/api`,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 300000,
});

const getAuthStore = () => useAuthStore(pinia);

export const getToken = () =>
    getAuthStore().token || sessionStorage.getItem('auth_token');

export const getUser = () => {
    const authStore = getAuthStore();
    if (authStore.user) {
        return authStore.user;
    }

    try {
        const raw = sessionStorage.getItem('user');
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const saveToken = (token) => {
    getAuthStore().setToken(token);
};

const clearAuth = () => {
    getAuthStore().clearSession({ notify: false });
};

let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
    failedQueue.forEach((promise) => {
        if (error) promise.reject(error);
        else promise.resolve(token);
    });
    failedQueue = [];
};

api.interceptors.request.use(
    (config) => {
        const token = getToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => Promise.reject(error),
);

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        if (
            !error.response ||
            error.response.status !== 401 ||
            originalRequest._retry ||
            originalRequest.url?.includes('/refresh') ||
            originalRequest.url?.includes('/login')
        ) {
            return Promise.reject(error);
        }

        const currentToken = getToken();
        if (!currentToken) {
            return Promise.reject(error);
        }

        if (isRefreshing) {
            return new Promise((resolve, reject) => {
                failedQueue.push({ resolve, reject });
            })
                .then((token) => {
                    originalRequest.headers.Authorization = `Bearer ${token}`;
                    return api(originalRequest);
                })
                .catch((refreshError) => Promise.reject(refreshError));
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
            const response = await api.post('/refresh');
            const newToken = response.data.access_token;

            if (!newToken) throw new Error('No token in refresh response');

            saveToken(newToken);
            api.defaults.headers.common.Authorization = `Bearer ${newToken}`;
            processQueue(null, newToken);

            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            return api(originalRequest);
        } catch (refreshError) {
            processQueue(refreshError, null);
            clearAuth();

            if (window.location.pathname !== '/client/login') {
                window.dispatchEvent(new CustomEvent('auth-logout'));
                window.location.href = '/client/login';
            }

            return Promise.reject(refreshError);
        } finally {
            isRefreshing = false;
        }
    },
);

export default api;
