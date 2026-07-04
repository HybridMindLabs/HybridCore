import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';

// ── Laravel Echo (Reverb) ─────────────────────────────────────────────────────
(window as unknown as Record<string, unknown>).Pusher = Pusher;
(window as unknown as Record<string, unknown>).Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

// ── Theme resolution ──────────────────────────────────────────────────────────
//
// Read active theme slug from the initial Inertia page payload (embedded in
// the #app data-page attribute by the server). This runs BEFORE the Vue app
// mounts so the page resolver can pick the correct theme at boot time.

const initialPage = JSON.parse(
    (document.getElementById('app') as HTMLElement)?.dataset.page ?? '{}',
);

const activeTheme: string =
    (initialPage?.props?.app?.theme as { slug?: string })?.slug ?? 'Default';

const themeSettings: Record<string, string> =
    (initialPage?.props?.app?.theme as { settings?: Record<string, string> })?.settings ?? {};

// Apply theme CSS custom properties to :root before first paint.
if (Object.keys(themeSettings).length > 0) {
    const root = document.documentElement;
    const map: Record<string, string> = {
        accent_color:     '--color-accent',
        background_color: '--color-background',
        surface_color:    '--color-surface',
        primary_color:    '--color-primary',
    };
    for (const [key, cssVar] of Object.entries(map)) {
        if (themeSettings[key]) {
            root.style.setProperty(cssVar, themeSettings[key]);
        }
    }
}

// ── Page resolution ───────────────────────────────────────────────────────────

// Core pages: resources/js/pages/**
const corePages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

// Theme pages: themes/{ThemeName}/resources/js/pages/**
// All theme page files are discovered at build time; only the active theme's
// files are actually loaded at runtime.
const themePages = import.meta.glob<DefineComponent>(
    '../../themes/*/resources/js/pages/**/*.vue',
);

// Extension pages: extensions/{vendor}/{name}/resources/js/pages/**
const extensionPages = import.meta.glob<DefineComponent>(
    '../../extensions/*/*/resources/js/pages/**/*.vue',
);

function resolvePage(name: string): Promise<DefineComponent> {
    // Extension pages — routed as: Inertia::render('Extensions/vendor/name/Page')
    if (name.startsWith('Extensions/')) {
        const [, vendor, ext, ...rest] = name.split('/');
        const path = `../../extensions/${vendor}/${ext}/resources/js/pages/${rest.join('/')}.vue`;
        return resolvePageComponent(path, extensionPages);
    }

    // Theme override: themes/{ActiveTheme}/resources/js/pages/{name}.vue
    const themeKey = `../../themes/${activeTheme}/resources/js/pages/${name}.vue`;
    if (themePages[themeKey]) {
        return resolvePageComponent(themeKey, themePages);
    }

    // Fall back to core pages
    return resolvePageComponent(`./pages/${name}.vue`, corePages);
}

// ── Extension slot components ─────────────────────────────────────────────────
//
// Extensions place Vue components in:
//   extensions/{vendor}/{name}/resources/js/components/{ComponentName}.vue
//
// Naming convention: vendor-prefixed to avoid collisions across extensions.
//   e.g. HybridcoreStoreProductCard, HybridcoreBansBanWidget

const extensionComponents = import.meta.glob<{ default: DefineComponent }>(
    '../../extensions/*/*/resources/js/components/**/*.vue',
    { eager: true },
);

// ── App bootstrap ─────────────────────────────────────────────────────────────

createInertiaApp({
    title: (title) => {
        const appName = (window as unknown as { __APP_NAME__?: string }).__APP_NAME__ || 'HybridCore';
        return title ? `${title} — ${appName}` : appName;
    },

    resolve: resolvePage,

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        app.component('ExtensionSlot', ExtensionSlot);

        Object.entries(extensionComponents).forEach(([path, mod]) => {
            const name = path.split('/').pop()?.replace('.vue', '');
            if (name && mod.default) {
                app.component(name, mod.default);
            }
        });

        app.mount(el);
    },

    progress: {
        color: '#22d3ee',
        showSpinner: false,
    },
});
