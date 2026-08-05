import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/filament-calendar.js', 'resources/js/filament-sortable.js', 'resources/js/event-program-tree-sortable.js', 'resources/js/program-point-tree-dnd-entry.js'],
            refresh: true,
            buildDirectory: 'vite-dist',
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['images/bprafa-pilot-logo.svg'],
            manifest: {
                name: 'Portal pilota bprafa',
                short_name: 'Pilot',
                description: 'Panel pilota wycieczek',
                theme_color: '#0f766e',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/pilot',
                scope: '/pilot',
                icons: [
                    {
                        src: '/images/bprafa-pilot-logo.svg',
                        sizes: 'any',
                        type: 'image/svg+xml',
                        purpose: 'any maskable',
                    },
                ],
            },
            workbox: {
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
            },
        }),
    ],

    optimizeDeps: {
        include: [
            'sortablejs',
            'axios',
            '@fullcalendar/core',
            '@fullcalendar/daygrid',
            '@fullcalendar/list',
            '@fullcalendar/interaction',
        ],
        exclude: ['@vite/client', '@vite/env'],
    },

    build: {
        chunkSizeWarningLimit: 1000,
        cssCodeSplit: true,
        cssMinify: true,
        minify: 'esbuild',
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['axios'],
                    sortable: ['sortablejs'],
                },
                chunkFileNames: 'assets/js/[name].[hash].js',
                entryFileNames: 'assets/js/[name].[hash].js',
                assetFileNames: (assetInfo) => {
                    const extType = assetInfo.name.split('.').at(1);
                    if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(extType)) {
                        return 'assets/images/[name].[hash][extname]';
                    }
                    if (/woff2?|eot|ttf|otf/i.test(extType)) {
                        return 'assets/fonts/[name].[hash][extname]';
                    }
                    return 'assets/[ext]/[name].[hash][extname]';
                },
            },
        },
        sourcemap: false,
        target: 'es2020',
        reportCompressedSize: true,
    },

    server: {
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: false,
            ignored: ['**/storage/**', '**/vendor/**', '**/node_modules/**'],
        },
    },

    cacheDir: 'node_modules/.vite',

    define: {
        __VUE_OPTIONS_API__: false,
        __VUE_PROD_DEVTOOLS__: false,
    },
});
