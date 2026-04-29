import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import basicSsl from '@vitejs/plugin-basic-ssl';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/mplace.scss',
                'resources/scss/mplace-mobile.scss',
                'resources/scss/cases.scss',
                'resources/js/mplace.js',
                'resources/js/mplace-mobile.js',
                'resources/js/cases.js'
            ],
            refresh: ['resources/views/**'],
        }),
        vue(),
        basicSsl(),
    ],
    server: {
        https: true,
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        origin: 'https://100.67.243.55:5173',
        hmr: {
            host: '100.67.243.55',
        },
        watch: {
            ignored: ['**/node_modules/**', '**/vendor/**', '**/storage/**', '**/.git/**'],
        },
    },
    optimizeDeps: {
        include: ['vue', 'axios', 'bootstrap'],
    },
    css: {
        devSourcemap: true,
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import'],
                additionalData: `$public-path: '/';`
            }
        }
    },
    resolve: {
        alias: {
            '/images': path.resolve(__dirname, 'public/images')
        }
    },
    build: {
        sourcemap: true,
        minify: 'esbuild',
        target: 'es2020',
        rollupOptions: {
            output: {
                manualChunks: {
                    vue: ['vue'],
                    'date-fns': ['date-fns', '@vuepic/vue-datepicker'],
                }
            }
        }
    }
});
