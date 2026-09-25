import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Platform CSS (landing, dashboard) + one stylesheet per theme, built once at deploy (spec §5).
// Fonts are self-hosted through fontsource packages (spec §10): nothing loads from a CDN.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/themes/atlas/theme.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        cssMinify: true,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
