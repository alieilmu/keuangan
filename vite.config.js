import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    build: {
        // Vendor dipisah supaya perubahan kode aplikasi tidak membuang cache Vue/Inertia.
        rollupOptions: {
            output: {
                manualChunks: (id) =>
                    id.includes('node_modules/vue') || id.includes('node_modules/@inertiajs')
                        ? 'vendor'
                        : undefined,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
