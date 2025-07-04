import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/style.scss', 'resources/js/app.js'],
            refresh: true,
        }),
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
            '/images': '/images'
        }
    }
});
