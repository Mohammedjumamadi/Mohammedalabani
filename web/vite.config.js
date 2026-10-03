import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // نسختان من التنسيق: واحدة لاتجاه RTL (العربية) وأخرى لاتجاه LTR (الإنجليزية)
            input: ['resources/css/app-rtl.css', 'resources/css/app-ltr.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
