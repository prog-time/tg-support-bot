// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'public/live_chat/widget.js', // виджет
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            input: {
                widget: 'public/live_chat/widget.js', // наш виджет
            },
            output: {
                entryFileNames: 'widget.js', // фиксированное имя
            },
        },
        outDir: 'public/live_chat/dist', // кладём прямо в dist
        emptyOutDir: false,               // чтобы не удалять другие файлы
    },
});
