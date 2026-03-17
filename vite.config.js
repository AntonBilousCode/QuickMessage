import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/crypto.js',
                'resources/js/auth-crypto.js',
                'resources/js/key-init.js',
            ],
            refresh: true,
        }),
    ],
    esbuild: {
        // Prevents leaking crypto implementation details via browser console
        drop: mode === 'production' ? ['console', 'debugger'] : [],
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
    },
}));
