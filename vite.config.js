import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/growth-mail-editor.js'],
            refresh: true,
        }),
    ],
    cacheDir: 'node_modules/.vite',
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        origin: 'http://localhost:5173',
        cors: {
            origin: ['http://localhost:8083', 'http://127.0.0.1:8083', 'http://adm.localhost:8083'],
        },
    },
});
