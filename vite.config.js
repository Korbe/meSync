import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
        // viteStaticCopy({
        //     targets: [
        //       {
        //         src: 'resources/images/**/*',
        //         dest: 'images'
        //       },
        //       {
        //         src: 'resources/public/**/*',
        //         dest: ''
        //       }
        //     ],
        //     watch: true,
        // }),
        tailwindcss()
    ],
});
