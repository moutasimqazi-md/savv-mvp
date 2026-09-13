import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/rbi-viewer.js'],
            refresh: true,
        }),
    ],
    // @novnc/novnc uses top-level await internally, which needs a newer
    // target than Vite's default (chrome87/es2020/etc).
    build: {
        target: 'es2022',
    },
});
