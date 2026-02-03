import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/mplace.scss',
                'resources/scss/cases.scss',
                'resources/js/mplace.js',
                'resources/js/cases.js'
            ],
            refresh: ['resources/views/**'],
        }),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/node_modules/**', '**/vendor/**', '**/storage/**', '**/.git/**'],
        },
    },
    optimizeDeps: {
        include: ['vue', 'axios', 'bootstrap'],
    },
    css: {
        devSourcemap: false,
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
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
        sourcemap: false,
        minify: 'esbuild',
        target: 'es2020',
        rollupOptions: {
            output: {
                manualChunks: {
                    vue: ['vue'],
                }
            }
        }
    }
});
