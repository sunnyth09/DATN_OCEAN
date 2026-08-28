import axios from 'axios';
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || `${window.location.protocol}//${window.location.hostname}:8383/api`,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 30000,
});

const getAuthStore = () => useAuthStore(pinia);

export const getToken = () => {
    const authStore = getAuthStore();
    return authStore.token || localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token') || '';
};

export const getUser = () => {
    const authStore = getAuthStore();
    if (authStore.user) {
        return authStore.user;
    }

    try {
        const raw = localStorage.getItem('user') || sessionStorage.getItem('user');
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

const isAuthEndpoint = (url = '') => AUTH_ENDPOINTS.some((endpoint) => url.includes(endpoint));

/**
 * Kiểm tra xem token có sắp hết hạn không.
 * Mặc định leeway 300s (5 phút) để chủ động refresh ngầm trước khi token chết.
 */
const isTokenExpiring = (token, leewaySeconds = 300) => {
    if (!token) return true;
    try {
        const encodedPayload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        const payload = JSON.parse(atob(encodedPayload.padEnd(Math.ceil(encodedPayload.length / 4) * 4, '=')));
        if (!payload.exp) return false;
        return payload.exp <= Math.floor(Date.now() / 1000) + leewaySeconds;
    } catch {
        return false;
    }
};

const redirectToLogin = () => {
    if (window.location.pathname !== '/client/login') {
        window.dispatchEvent(new CustomEvent('auth-logout'));
        const currentPath = window.location.pathname + window.location.search;
        window.location.href = `/client/login?redirect=${encodeURIComponent(currentPath)}`;
    }
};

// ==================== SINGLETON REFRESH PROMISE PATTERN ====================
let refreshPromise = null;

/**
 * Hàm gọi API /refresh một cách an toàn và trả về token mới.
 * Sử dụng Singleton Promise: tất cả các request đồng thời (ở cả Request và Response Interceptor)
 * đều chia sẻ một Promise duy nhất, loại bỏ hoàn toàn Race Condition.
 */
const getFreshToken = async () => {
    if (refreshPromise) {
        return refreshPromise;
    }

    refreshPromise = (async () => {
        try {
            const response = await api.post('/refresh', null, { skipAuthRefresh: true });
            const newToken = response.data?.access_token;
            if (!newToken) throw new Error('No access_token received from /refresh endpoint');

            saveToken(newToken);
            api.defaults.headers.common.Authorization = `Bearer ${newToken}`;
            return newToken;
        } finally {
            refreshPromise = null;
        }
    })();

    return refreshPromise;
};

// ==================== REQUEST INTERCEPTOR ====================
api.interceptors.request.use(
    async (config) => {
        let token = getToken();

        // 1. Silent Proactive Refresh: nếu token sắp hết hạn trong 5 phút tới và không phải auth endpoint
        if (token && !config.skipAuthRefresh && !isAuthEndpoint(config.url) && isTokenExpiring(token, 300)) {
            try {
                const newToken = await getFreshToken();
                if (newToken) {
                    token = newToken;
                }
            } catch {
                // Nếu refresh ngầm tạm thời thất bại (mạng chập chờn), vẫn thử gửi với token hiện tại
            }
        }

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Đảm bảo có device_id ổn định
        let deviceId = localStorage.getItem('device_id');
        if (!deviceId) {
            deviceId = 'dev_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('device_id', deviceId);
        }
        config.headers['X-Device-ID'] = deviceId;

        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => Promise.reject(error),
);

// ==================== RESPONSE INTERCEPTOR ====================
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // 1. Validation 422: chuẩn hóa lỗi FormRequest từ Laravel
        if (error.response?.status === 422) {
            const raw = error.response.data?.errors ?? {};
            error.validationErrors = Object.fromEntries(
                Object.entries(raw).map(([field, messages]) => [
                    field,
                    Array.isArray(messages) ? messages[0] : messages,
                ]),
            );
            return Promise.reject(error);
        }

        // 2. Kiểm tra điều kiện có phải lỗi 401 cần xử lý Refresh không
        if (
            !error.response ||
            error.response.status !== 401 ||
            originalRequest?._retry ||
            isAuthEndpoint(originalRequest?.url || '')
        ) {
            return Promise.reject(error);
        }

        const currentToken = getToken();
        if (!currentToken) {
            return Promise.reject(error);
        }

        originalRequest._retry = true;

        try {
            const newToken = await getFreshToken();
            if (newToken) {
                originalRequest.headers.Authorization = `Bearer ${newToken}`;
                return api(originalRequest);
            }
            return Promise.reject(error);
        } catch (refreshError) {
            const status = refreshError.response?.status;
            // Chỉ redirect khi server thực sự từ chối token (401/403)
            if (status === 401 || status === 403) {
                clearAuth();
                redirectToLogin();
            }
            return Promise.reject(refreshError);
        }
    },
);

export default api;
