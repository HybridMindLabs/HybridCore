import js from '@eslint/js';
import ts from 'typescript-eslint';
import vue from 'eslint-plugin-vue';
import globals from 'globals';

export default ts.config(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/build/**',
            'bootstrap/ssr/**',
            'storage/**',
            'extensions/*/*/vendor/**',
            '**/*.d.ts',
        ],
    },

    js.configs.recommended,
    ...ts.configs.recommended,
    ...vue.configs['flat/recommended'],

    {
        files: ['**/*.{ts,vue}'],
        languageOptions: {
            parserOptions: {
                // Vue SFCs need the TS parser for their <script setup lang="ts">.
                parser: ts.parser,
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: {
                ...globals.browser,
                // Ziggy exposes route() globally via @routes.
                route: 'readonly',
            },
        },
        rules: {
            // Inertia pages are single-word by design (Home.vue, Player.vue).
            'vue/multi-word-component-names': 'off',

            // No Prettier in this project — these only enforce whitespace and
            // fight the existing dense template style. Correctness rules stay on.
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/html-closing-bracket-spacing': 'off',
            'vue/attributes-order': 'off',
            'vue/first-attribute-linebreak': 'off',
            'vue/no-multi-spaces': 'off',

            // Page props mirror Laravel payload keys verbatim (nav_counts,
            // active_count), so snake_case is intentional at that boundary.
            'vue/prop-name-casing': 'off',
            'vue/require-default-prop': 'off',

            // Inertia's useForm object is created by the parent and passed down
            // to field components, which mutate it — that is the framework's
            // own pattern, not accidental prop mutation.
            'vue/no-mutating-props': 'off',

            // v-html here renders Laravel paginator labels ("&laquo; Previous")
            // and server-rendered markdown, never user input.
            'vue/no-v-html': 'off',
            'vue/no-v-text-v-html-on-component': 'off',

            // Misfires on TypeScript unions inside templates: an expression like
            // `form.format as 'markdown' | 'html'` is read as a Vue 2 filter.
            'vue/no-deprecated-filter': 'off',

            // TypeScript merges the type and value namespaces (an icon import
            // and an interface may share a name); tsc is authoritative here.
            'no-redeclare': 'off',

            // Ternary-used-as-statement is a deliberate style in this codebase.
            '@typescript-eslint/no-unused-expressions': 'off',

            // Flags typed initializers that every branch overwrites.
            'no-useless-assignment': 'off',

            // Best-effort background fetches intentionally swallow failures.
            'no-empty': ['error', { allowEmptyCatch: true }],

            // Deliberate escape hatch while the Inertia boundary stays untyped;
            // tighten once page props carry real types.
            '@typescript-eslint/no-explicit-any': 'off',

            // Dead code is worth surfacing — kept as a warning so it does not
            // block CI while the existing 49 get cleaned up.
            '@typescript-eslint/no-unused-vars': ['warn', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrors: 'none',
            }],
        },
    },
);
