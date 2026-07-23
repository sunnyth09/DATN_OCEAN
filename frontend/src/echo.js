import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import api from '@/axios';
import { getApiBaseUrl } from '@/utils/url';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbPort = import.meta.env.VITE_REVERB_PORT ?? 8383;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.replace(':', '');
const broadcastingAuthEndpoint = `${getApiBaseUrl()}/broadcasting/auth`;

if (reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: reverbScheme === 'https' ? ['ws', 'wss'] : ['ws'],
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
} else {
    console.warn('[Echo] VITE_REVERB_APP_KEY chưa được cấu hình. WebSocket bị tắt.');
}
