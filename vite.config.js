import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            buildDirectory: 'build',
        }),
        tailwindcss(),
    ],
    
    // Optymalizacja zależności
    optimizeDeps: {
        include: [
            'sortablejs',
            'axios',
        ],
        exclude: ['@vite/client', '@vite/env'],
    },
    
    // Konfiguracja budowania
    build: {
        // Zwiększ limit rozmiaru dla chunków (Filament może mieć duże pliki)
        chunkSizeWarningLimit: 1000,
        
        // Optymalizacja CSS
        cssCodeSplit: true,
        cssMinify: true,
        
        // Optymalizacja JS
        minify: 'esbuild', // Używamy esbuild zamiast terser dla lepszej kompatybilności
        
        // Rollup options
        rollupOptions: {
            output: {
                // Inteligentne dzielenie chunków
                manualChunks: {
                    // Vendor libraries
                    vendor: ['axios'],
                    sortable: ['sortablejs'],
                },
                
                // Nazewnictwo plików z hash dla cache busting
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
        
        // Sourcemaps tylko w dev
        sourcemap: false,
        
        // Target dla lepszej kompatybilności
        target: 'es2020',
        
        // Kompresja wbudowana w Vite
        reportCompressedSize: true,
    },
    
    // Konfiguracja serwera deweloperskiego
    server: {
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: false,
            ignored: ['**/storage/**', '**/vendor/**', '**/node_modules/**'],
        },
    },
    
    // Cache dla lepszej wydajności
    cacheDir: 'node_modules/.vite',
    
    // Definicje środowiskowe
    define: {
        __VUE_OPTIONS_API__: false,
        __VUE_PROD_DEVTOOLS__: false,
    },
});
