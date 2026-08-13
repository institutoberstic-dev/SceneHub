import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        viteStaticCopy({
            targets: [
                {
                    src: 'public/fontawesome_flat_webfonts/*',
                    dest: 'assets/webfonts',
                    flatten: true
                }
            ]
        })
    ],
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (chunkInfo) => {
                    if (/\.(woff2|woff|ttf|eot|svg)$/.test(chunkInfo.name || '')) {
                        return 'assets/webfonts/[name][extname]';
                    }
                    return 'assets/[name][extname]';
                }
            }
        }
    }
});
