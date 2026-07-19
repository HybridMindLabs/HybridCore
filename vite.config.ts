import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: ['resources/views/**', 'routes/**'],
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
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
        dedupe: [
            '@codemirror/state',
            '@codemirror/view',
            '@codemirror/language',
            'codemirror',
        ],
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('@codemirror') || id.includes('codemirror')) {
                        return 'codemirror';
                    }
                    if (id.includes('highcharts')) {
                        return 'highcharts';
                    }
                    if (id.includes('marked')) {
                        return 'marked';
                    }
                    // Each icon was emitted as its own chunk of a few hundred
                    // bytes: the home page pulled 30 of them, 83% of its
                    // requests carrying 2% of its bytes, none of which could be
                    // requested until the page chunk had been parsed.
                    //
                    // Measured, gzipped: home 36 requests/141.7 KB becomes 8
                    // requests/146.3 KB. The 4.6 KB is paid once — this chunk
                    // is then cached for every Inertia navigation after it,
                    // where the per-page icon chunks were partly re-fetched.
                    if (id.includes('@lucide/vue')) {
                        return 'icons';
                    }
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
