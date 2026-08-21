import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import api from '@/axios';
import { getApiBaseUrl } from '@/utils/url';

const isHttps = typeof window !== 'undefined' && window.location.protocol === 'https:';
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbPort = import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : (isHttps ? 443 : 8383);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? (isHttps ? 'https' : 'http');
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

if (reverbKey) {
    window.Pusher = Pusher;

    try {
        const wsHost = import.meta.env.VITE_REVERB_HOST || resolveDefaultWsHost();

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: wsHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: reverbScheme === 'https' ? ['wss', 'ws'] : ['ws'],
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
                // Keep console clean if Reverb daemon is unavailable in production
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
    console.debug('[Echo] VITE_REVERB_APP_KEY chưa được cấu hình. WebSocket chạy chế độ dự phòng.');
}
