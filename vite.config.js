import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/style.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import', 'mixed-decls'],
                additionalData: `$public-path: '/';`
            }
        }
    },
    resolve: {
        alias: {
            '/images': path.resolve(__dirname, 'public/images')
        }
    }
});
