import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
<<<<<<< HEAD
        host: 'localhost',
=======
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
>>>>>>> d2cf4e51a439ed290ebd586882a0326d22e65460
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
