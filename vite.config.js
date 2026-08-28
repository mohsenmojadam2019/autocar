import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel({
        input: [
            'resources/css/app.css',
            'resources/css/extensions.css',
            'resources/css/ux.css',
            'resources/css/autocar-theme.css',
            'resources/js/vendor.js',
            'resources/js/app.js',
            'resources/js/ux.js',
        ],
        refresh: true,
    })],
    build: {
        sourcemap: false,
        cssCodeSplit: true,
        chunkSizeWarningLimit: 750,
    },
});
