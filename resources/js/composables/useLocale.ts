import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface SupportedLocale {
    code: string;
    name: string;
    native_name: string;
    flag: string;
    direction: 'ltr' | 'rtl';
}

interface LocalizationProps {
    localization: {
        currentLocale: string;
        fallbackLocale: string;
        supportedLocales: SupportedLocale[];
        localeDirection: 'ltr' | 'rtl';
        languageSwitcherEnabled: boolean;
    };
    translations: Record<string, Record<string, unknown>>;
    [key: string]: unknown;
}

/**
 * Localization state shared by HandleInertiaRequests.
 * switchLocale() posts to /locale (or /admin/locale inside the admin panel)
 * and Inertia refreshes the shared props on redirect-back.
 */
export function useLocale() {
    const page = usePage<LocalizationProps>();

    const currentLocale = computed(() => page.props.localization?.currentLocale ?? 'en');
    const fallbackLocale = computed(() => page.props.localization?.fallbackLocale ?? 'en');
    const supportedLocales = computed<SupportedLocale[]>(() => page.props.localization?.supportedLocales ?? []);
    const localeDirection = computed(() => page.props.localization?.localeDirection ?? 'ltr');
    const switcherEnabled = computed(() => page.props.localization?.languageSwitcherEnabled ?? false);

    /**
     * Translate a shared key like "auth.sign_in" or "account.profile".
     * Falls back to the key itself when missing — same as Laravel's __().
     */
    function t(key: string, replace?: Record<string, string | number>): string {
        const [group, ...rest] = key.split('.');
        let node: unknown = page.props.translations?.[group];
        for (const part of rest) {
            if (node && typeof node === 'object') {
                node = (node as Record<string, unknown>)[part];
            } else {
                node = undefined;
                break;
            }
        }
        let result = typeof node === 'string' ? node : key;
        if (replace) {
            for (const [param, value] of Object.entries(replace)) {
                result = result.replace(`:${param}`, String(value));
            }
        }
        return result;
    }

    function isCurrentLocale(locale: string): boolean {
        return currentLocale.value === locale;
    }

    function switchLocale(locale: string): void {
        if (isCurrentLocale(locale)) return;

        const isAdmin = window.location.pathname.startsWith('/admin');
        router.post(isAdmin ? '/admin/locale' : '/locale', { locale }, { preserveScroll: true, preserveState: false });
    }

    return {
        currentLocale,
        fallbackLocale,
        supportedLocales,
        localeDirection,
        switcherEnabled,
        isCurrentLocale,
        switchLocale,
        t,
    };
}
