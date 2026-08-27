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

        const pusherConn = window.Echo?.connector?.pusher?.connection;
        if (pusherConn) {
            pusherConn.bind('error', (err) => {
                const msg = err?.error?.data?.message || err?.message || 'Reconnecting...';
                console.debug('[Echo] Realtime connection info:', msg);
            });
            pusherConn.bind('unavailable', () => {
                console.debug('[Echo] Realtime service temporarily unavailable, will retry automatically.');
            });
        }

        // ==========================================
        // Page Lifecycle & Back-Forward Cache (bfcache) Handling
        // ==========================================
        let isSuspended = false;

        const handleDisconnect = () => {
            if (window.Echo?.connector?.pusher) {
                try {
                    isSuspended = true;
                    window.Echo.disconnect();
                } catch (e) {
                    // Ignore teardown errors
                }
            }
        };

        const handleReconnect = () => {
            if (window.Echo?.connector?.pusher) {
                try {
                    isSuspended = false;
                    const state = window.Echo.connector.pusher.connection?.state;
                    if (state === 'disconnected' || state === 'unavailable' || state === 'failed') {
                        window.Echo.connect();
                    }
                } catch (e) {
                    // Ignore reconnect errors
                }
            }
        };

        if (typeof window !== 'undefined') {
            // 1. Back-Forward Cache (bfcache) entry:
            // Gracefully disconnect WebSocket so browser does not abort it forcefully with "Page entered Back-Forward Cache"
            window.addEventListener('pagehide', () => {
                handleDisconnect();
            });

            // 2. Back-Forward Cache (bfcache) restore:
            // Re-establish connection and resubscribe channels when user navigates back/forward to this page
            window.addEventListener('pageshow', (event) => {
                if (event.persisted || isSuspended) {
                    handleReconnect();
                }
            });

            // 3. Page Lifecycle API (freeze & resume for modern browsers / mobile devices)
            document.addEventListener('freeze', handleDisconnect);
            document.addEventListener('resume', handleReconnect);

            // 4. Tab visibility change (reconnect if tab was backgrounded and woke up)
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    handleReconnect();
                }
            });

            // 5. Network connectivity
            window.addEventListener('online', handleReconnect);
            window.addEventListener('offline', handleDisconnect);
        }
    } catch (e) {
        console.debug('[Echo] Initialization skipped, running in polling mode.');
    }
} else {
    console.debug('[Echo] WebSocket realtime chưa được cấu hình hoặc đã tắt. Chạy chế độ dự phòng.');
}

export default window.Echo;
