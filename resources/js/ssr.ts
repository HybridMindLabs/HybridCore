import { createSSRApp, h, type DefineComponent } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue, route as ziggyRoute, type Config as ZiggyConfig } from 'ziggy-js';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';

// The server-side twin of app.ts. It deliberately does NOT mirror it in full:
//
//  - No Echo/Pusher. There is no socket to open when rendering a string, and
//    the client opens one anyway as soon as it hydrates.
//  - Theme and app name come from the page object rather than from
//    document/window, which do not exist here.
//  - Ziggy's route list comes from the `ziggy` shared prop (see
//    HandleInertiaRequests) instead of the global @routes writes.
//
// Anything that needs a real browser belongs in onMounted, which SSR never
// runs — not in setup, which it does.

const corePages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

const themePages = import.meta.glob<DefineComponent>(
    '../../themes/*/resources/js/pages/**/*.vue',
);

const extensionPages = import.meta.glob<DefineComponent>(
    '../../extensions/*/*/resources/js/pages/**/*.vue',
);

const extensionComponents = import.meta.glob<{ default: DefineComponent }>(
    '../../extensions/*/*/resources/js/components/**/*.vue',
    { eager: true },
);

type PageProps = {
    app?: { name?: string; theme?: { slug?: string } };
    ziggy?: ZiggyConfig;
};

createServer((page) => {
    // Ziggy's @routes directive gives the browser a global route() function.
    // Node has no such global, and 421 call sites across 107 components expect
    // one — so it is recreated here from the shared prop.
    //
    // A global that is reassigned per request looks like a race, but is not one
    // here: the route list is identical for every visitor. The only per-request
    // field is `location`, which is read solely by route().current() — and
    // nothing in this codebase calls it. Should that change, this has to become
    // a per-render binding instead.
    const ziggy = (page.props as PageProps)?.ziggy;

    (globalThis as unknown as Record<string, unknown>).route = (
        name?: string,
        params?: unknown,
        absolute?: boolean,
    ) => ziggyRoute(name as never, params as never, absolute, ziggy);

    return createInertiaApp({
        page,

        render: renderToString,

        title: (title) => {
            const appName = (page.props as PageProps)?.app?.name || 'HybridCore';

            return title ? `${title} — ${appName}` : appName;
        },

        resolve: (name) => {
            // Resolved per request: unlike the client, this process serves
            // every visitor, so the active theme cannot be read once at boot.
            const activeTheme = (page.props as PageProps)?.app?.theme?.slug ?? 'Default';

            if (name.startsWith('Extensions/')) {
                const [, vendor, ext, ...rest] = name.split('/');
                const path = `../../extensions/${vendor}/${ext}/resources/js/pages/${rest.join('/')}.vue`;

                return resolvePageComponent(path, extensionPages);
            }

            const themeKey = `../../themes/${activeTheme}/resources/js/pages/${name}.vue`;
            if (themePages[themeKey]) {
                return resolvePageComponent(themeKey, themePages);
            }

            return resolvePageComponent(`./pages/${name}.vue`, corePages);
        },

        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, (page.props as PageProps)?.ziggy);

            app.component('ExtensionSlot', ExtensionSlot);

            Object.entries(extensionComponents).forEach(([path, mod]) => {
                const name = path.split('/').pop()?.replace('.vue', '');
                if (name && mod.default) {
                    app.component(name, mod.default);
                }
            });

            return app;
        },
    });
});
