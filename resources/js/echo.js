import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Initialize Laravel Echo with Reverb broadcaster.
 *
 * VITE_REVERB_HOST must be the PUBLIC hostname (localhost for dev),
 * NOT the Docker internal service name.
 */
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

// Debug: log WebSocket connection events (dev only)
if (import.meta.env.DEV) {
    echo.connector.pusher.connection.bind('connecting', () => {
        console.debug('[Echo] Connecting to Reverb...', {
            host: import.meta.env.VITE_REVERB_HOST,
            port: import.meta.env.VITE_REVERB_PORT,
        });
    });

    echo.connector.pusher.connection.bind('connected', () => {
        console.debug('[Echo] Connected to Reverb WebSocket ✓');
    });

    echo.connector.pusher.connection.bind('disconnected', () => {
        console.debug('[Echo] Disconnected from Reverb WebSocket');
    });

    echo.connector.pusher.connection.bind('error', (err) => {
        console.error('[Echo] WebSocket error:', err);
    });
}

export default echo;
