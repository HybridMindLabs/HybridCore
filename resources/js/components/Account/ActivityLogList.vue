<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Monitor, Smartphone, MapPin, Clock, ShieldAlert, ArrowRight, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

interface LoginEntry {
    id: number; ip: string | null; user_agent: string | null;
    country: string | null; city: string | null;
    at: string; at_full: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
/**
 * The controller passes the raw Eloquent paginator straight to Inertia for the
 * standalone /account/activity route, which serializes it flat — current_page,
 * last_page and links sit at the top level, not nested under a "meta" object.
 * The embedded Account tab instead sends a hand-built {data:[...20 most
 * recent], links:[], meta:[]} (no pagination there by design), which is why
 * every pagination field below is optional.
 */
interface Paginator {
    data: LoginEntry[];
    links: PageLink[];
    meta: unknown;
    current_page?: number;
    last_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
}

const props = defineProps<{ history: Paginator }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const showPagination = computed(() => (props.history.last_page ?? 0) > 1);

/** A window around the current page, same as the News archive pager. */
const pageWindow = computed(() => {
    const current = props.history.current_page ?? 1;
    const last = props.history.last_page ?? 1;
    const span = 2;
    const from = Math.max(1, current - span);
    const to = Math.min(last, current + span);

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});

function pageHref(page: number): string {
    return route('account.activity', { page });
}

function parseBrowser(ua: string | null): string | null {
    if (!ua) return null;
    if (ua.includes('Edg/')) return 'Edge';
    if (ua.includes('OPR/') || ua.includes('Opera')) return 'Opera';
    if (ua.includes('Chrome/')) return 'Chrome';
    if (ua.includes('Firefox/')) return 'Firefox';
    if (ua.includes('Safari/') && !ua.includes('Chrome')) return 'Safari';
    return null;
}

function parseOS(ua: string | null): string | null {
    if (!ua) return null;
    if (ua.includes('Windows NT')) return 'Windows';
    if (ua.includes('Mac OS X')) return 'macOS';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
    if (ua.includes('Linux')) return 'Linux';
    return null;
}

function isMobile(ua: string | null): boolean {
    return !!ua && /Android|iPhone|iPad|Mobile/.test(ua);
}

/**
 * The old markup built "{browser} on {os}" by concatenation, which printed a
 * dangling "on" whenever the OS was unrecognised, and could not be reordered
 * for languages that do not put the device last.
 */
function deviceLabel(entry: LoginEntry): string {
    const browser = parseBrowser(entry.user_agent);
    const os = parseOS(entry.user_agent);

    if (browser && os) return t('account.act_device_on', { browser, os });
    if (browser) return browser;
    if (os) return os;

    return t('account.act_device_unknown');
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

            <div class="px-5 sm:px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.act_title') }}
                </h2>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.act_subtitle') }}
                </p>
            </div>

            <div v-if="history.data.length === 0" class="flex flex-col items-center text-center px-6 py-14">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Monitor :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                    {{ t('account.act_empty') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.act_empty_hint') }}
                </p>
            </div>

            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.act_list_label')">
                <li v-for="(entry, i) in history.data" :key="entry.id"
                    class="flex items-start gap-4 px-5 py-4">

                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border mt-0.5"
                        :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'"
                        aria-hidden="true">
                        <component :is="isMobile(entry.user_agent) ? Smartphone : Monitor"
                            :size="14" :stroke-width="1.9" :class="dark ? 'text-zinc-400' : 'text-zinc-500'" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">
                                {{ deviceLabel(entry) }}
                            </p>
                            <span v-if="i === 0 && (history.current_page ?? 1) === 1"
                                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                                :class="dark ? 'bg-emerald-500/15 text-emerald-400' : 'bg-emerald-500/10 text-emerald-800'">
                                {{ t('account.act_latest') }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4 mt-1 flex-wrap">
                            <span v-if="entry.ip" class="text-[11.5px] font-mono"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'"
                                :title="t('account.act_ip_hint')">
                                {{ entry.ip }}
                            </span>
                            <span v-if="entry.city || entry.country" class="flex items-center gap-1 text-[11.5px]"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'"
                                :title="t('account.act_location_hint')">
                                <MapPin :size="11" :stroke-width="2" aria-hidden="true" />
                                {{ [entry.city, entry.country].filter(Boolean).join(', ') }}
                            </span>
                            <span class="flex items-center gap-1 text-[11.5px]"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'" :title="entry.at_full">
                                <Clock :size="11" :stroke-width="2" aria-hidden="true" /> {{ entry.at }}
                            </span>
                        </div>
                    </div>
                </li>
            </ul>

            <nav v-if="showPagination" :aria-label="t('account.act_pagination_label')"
                class="flex flex-col items-center gap-2 px-5 py-4 border-t"
                :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
                <div class="flex items-center gap-1">
                    <Link v-if="(history.current_page ?? 1) > 1" :href="pageHref((history.current_page ?? 1) - 1)" rel="prev"
                        class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        <ChevronLeft :size="13" :stroke-width="2" aria-hidden="true" />
                        {{ t('account.act_prev') }}
                    </Link>

                    <Link v-for="p in pageWindow" :key="p" :href="pageHref(p)"
                        :aria-current="p === history.current_page ? 'page' : undefined"
                        :aria-label="t('account.act_page_number', { page: p })"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="p === history.current_page
                            ? dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-300 bg-blue-50 text-blue-700'
                            : dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        {{ p }}
                    </Link>

                    <Link v-if="(history.current_page ?? 1) < (history.last_page ?? 1)" :href="pageHref((history.current_page ?? 1) + 1)" rel="next"
                        class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        {{ t('account.act_next') }}
                        <ChevronRight :size="13" :stroke-width="2" aria-hidden="true" />
                    </Link>
                </div>
                <p class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.act_showing', { current: history.current_page, last: history.last_page }) }}
                </p>
            </nav>
        </div>

        <!-- A login history is only useful if you know what to do with it. -->
        <div v-if="history.data.length" class="flex items-start gap-3.5 rounded-2xl border px-5 py-4"
            :class="dark ? 'border-amber-500/20 bg-amber-500/[0.05]' : 'border-amber-300/70 bg-amber-500/[0.07]'">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-amber-500/15"
                aria-hidden="true">
                <ShieldAlert :size="17" :stroke-width="1.9"
                    :class="dark ? 'text-amber-400' : 'text-amber-700'" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">
                    {{ t('account.act_security_title') }}
                </p>
                <p class="text-[12.5px] leading-relaxed mt-1" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                    {{ t('account.act_security_body') }}
                </p>
                <Link :href="route('account.index')"
                    class="inline-flex items-center gap-1.5 text-[12.5px] font-bold mt-2 rounded transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50"
                    :class="dark ? 'text-amber-400 hover:text-amber-300' : 'text-amber-800 hover:text-amber-900'">
                    {{ t('account.act_security_action') }}
                    <ArrowRight :size="12" :stroke-width="2.2" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </div>
</template>
