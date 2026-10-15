import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // report-capture.js is also loaded alone by the admin panel (docs/06_USER_REPORTS.md §5).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/report-capture.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
