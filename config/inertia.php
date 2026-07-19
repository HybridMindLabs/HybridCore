<?php

return [

    'ssr' => [

        /*
         * Server-side rendering is opt-in, because it needs a Node process
         * (`php artisan inertia:start-ssr`) running alongside PHP. An owner who
         * has not set one up must not get blank pages — with this false, Laravel
         * serves the same client-rendered markup it always did.
         */
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

    ],

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
