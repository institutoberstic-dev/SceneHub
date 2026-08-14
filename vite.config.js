import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
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
