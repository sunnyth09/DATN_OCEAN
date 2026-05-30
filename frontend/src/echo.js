import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';
import { pinia } from '@/stores';
import { useAuthStore } from '@/stores/auth';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8383,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 8383,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https' ? ['ws', 'wss'] : ['ws'],
        authEndpoint: `${import.meta.env.VITE_REVERB_SCHEME ?? 'http'}://${window.location.hostname}:8383/api/broadcasting/auth`,
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    axios.post(`${import.meta.env.VITE_REVERB_SCHEME ?? 'http'}://${window.location.hostname}:8383/api/broadcasting/auth`, {
                        socket_id: socketId,
                        channel_name: channel.name
                    }, {
                        headers: {
                            Authorization: `Bearer ${useAuthStore(pinia).token || sessionStorage.getItem('auth_token')}`,
                            Accept: 'application/json'
                        }
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
