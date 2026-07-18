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

    /**
     * Format an ISO timestamp in the reader's language.
     *
     * Dates formatted server-side with format('d M Y') come out with English
     * month names whatever locale the page is in, so pages should ship the ISO
     * value and render it through here.
     */
    function formatDate(iso: string | null | undefined, options?: Intl.DateTimeFormatOptions): string {
        if (!iso) return '';

        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return '';

        return new Intl.DateTimeFormat(currentLocale.value, options ?? { dateStyle: 'medium' }).format(date);
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
        formatDate,
    };
}
