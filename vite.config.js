import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        port: 8002,
        origin: 'http://excel.ionut.live:8002',
        host: true,
        allowedHosts: ['excel.ionut.live', 'ssh.ionut.live'],
        cors: {
            origin: [
                'http://excel.ionut.live:8001',
                'http://excel.ionut.live:8002',
            ],
            credentials: true,
        },
        hmr: {
            host: 'excel.ionut.live',
            port: 8002,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
