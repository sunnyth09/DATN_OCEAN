import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import api from '@/axios';
import { getApiBaseUrl } from '@/utils/url';

const isHttps = typeof window !== 'undefined' && window.location.protocol === 'https:';
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const isReverbEnabled = import.meta.env.VITE_REVERB_ENABLED !== 'false';
const broadcastingAuthEndpoint = `${getApiBaseUrl()}/broadcasting/auth`;

// Resolve backend host from API base URL if VITE_REVERB_HOST is not explicitly specified
const resolveDefaultWsHost = () => {
    try {
        const apiUrl = getApiBaseUrl();
        const urlObj = new URL(apiUrl);
        return urlObj.hostname;
    } catch (e) {
        return typeof window !== 'undefined' ? window.location.hostname : 'localhost';
    }
};

if (reverbKey && isReverbEnabled) {
    window.Pusher = Pusher;

    try {
        const rawHost = import.meta.env.VITE_REVERB_HOST;
        const wsHost = (rawHost && rawHost !== 'localhost')
            ? rawHost
            : (isHttps ? resolveDefaultWsHost() : (rawHost || 'localhost'));

        const wsPort = isHttps ? 443 : (import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 8383);
        const useTls = isHttps || import.meta.env.VITE_REVERB_SCHEME === 'https';

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wsPort,
            forceTLS: useTls,
            enabledTransports: useTls ? ['wss', 'ws'] : ['ws'],
            disableStats: true,
            authEndpoint: broadcastingAuthEndpoint,
            authorizer: (channel, options) => {
                return {
                    authorize: (socketId, callback) => {
                        api.post('/broadcasting/auth', {
                            socket_id: socketId,
                            channel_name: channel.name
                        })
                        .then(response => {
                            callback(false, response.data);
                        })
                        .catch(error => {
                            callback(true, error);
                        });
                    }
                };
            }
        });

        if (window.Echo?.connector?.pusher?.connection) {
            window.Echo.connector.pusher.connection.bind('error', (err) => {
                console.debug('[Echo] Realtime connection:', err?.error?.data?.message || err?.message || 'Reconnecting...');
            });
            window.Echo.connector.pusher.connection.bind('unavailable', () => {
                console.debug('[Echo] Realtime service currently unavailable, falling back to REST APIs.');
            });
        }
    } catch (e) {
        console.debug('[Echo] Initialization skipped, running in polling mode.');
    }
} else {
    console.debug('[Echo] WebSocket realtime chưa được cấu hình hoặc đã tắt. Chạy chế độ dự phòng.');
}
