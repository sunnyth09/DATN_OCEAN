import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import api from '@/axios';
import { getApiBaseUrl } from '@/utils/url';

const isHttps = window.location.protocol === 'https:';
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbPort = import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : (isHttps ? 443 : 8383);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? (isHttps ? 'https' : 'http');
const broadcastingAuthEndpoint = `${getApiBaseUrl()}/broadcasting/auth`;

if (reverbKey) {
    window.Pusher = Pusher;

    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: reverbScheme === 'https' ? ['wss', 'ws'] : ['ws'],
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
                console.debug('[Echo] WebSocket connection notification:', err?.error?.data?.message || err);
            });
        }
    } catch (e) {
        console.warn('[Echo] Initialization failed, running in polling mode:', e);
    }
} else {
    console.warn('[Echo] VITE_REVERB_APP_KEY chưa được cấu hình. WebSocket bị tắt.');
}
