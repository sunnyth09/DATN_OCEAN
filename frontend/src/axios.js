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

const AUTH_ENDPOINTS = ['/login', '/register', '/refresh', '/forgot-password/', '/auth/'];
let refreshPromise = null;

const isAuthEndpoint = (url = '') => AUTH_ENDPOINTS.some((endpoint) => url.includes(endpoint));

const isTokenExpiring = (token, leewaySeconds = 30) => {
    try {
        const encodedPayload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        const payload = JSON.parse(atob(encodedPayload.padEnd(Math.ceil(encodedPayload.length / 4) * 4, '=')));
        return !payload.exp || payload.exp <= Math.floor(Date.now() / 1000) + leewaySeconds;
    } catch {
        return true;
    }
};

const redirectToLogin = () => {
    if (window.location.pathname !== '/client/login') {
        window.dispatchEvent(new CustomEvent('auth-logout'));
        window.location.href = '/client/login';
    }
};

const refreshAccessToken = () => {
    if (!refreshPromise) {
        refreshPromise = api.post('/refresh', null, { skipAuthRefresh: true })
            .then((response) => {
                const newToken = response.data.access_token;
                if (!newToken) throw new Error('No token in refresh response');

                saveToken(newToken);
                api.defaults.headers.common.Authorization = `Bearer ${newToken}`;
                return newToken;
            })
            .catch((error) => {
                clearAuth();
                redirectToLogin();
                throw error;
            })
            .finally(() => {
                refreshPromise = null;
            });
    }

    return refreshPromise;
};

api.interceptors.request.use(
    async (config) => {
        let token = getToken();
        if (token && !config.skipAuthRefresh && !isAuthEndpoint(config.url) && isTokenExpiring(token)) {
            token = await refreshAccessToken();
        }

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

        originalRequest._retry = true;

        try {
            const newToken = await refreshAccessToken();
            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            return api(originalRequest);
        } catch (refreshError) {
            return Promise.reject(refreshError);
        }
    },
);

export default api;
