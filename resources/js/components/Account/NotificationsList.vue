<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Check, ChevronLeft, ChevronRight, Trash2, MessageSquare, AlertCircle, ThumbsUp, Gift, Trophy, Award } from '@lucide/vue';
import type { Component } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

// Extension-registered notification types (type => { icon, accent }).
const page = usePage<{ notificationTypes?: Record<string, { icon: string; accent: string }> }>();
const extTypes = computed(() => page.props.notificationTypes ?? {});
const extIconMap: Record<string, Component> = { Bell, MessageSquare, ThumbsUp, Gift, Trophy, Award, AlertCircle };
const accentText: Record<string, string> = {
    blue: 'text-blue-400', emerald: 'text-emerald-400', amber: 'text-amber-400',
    red: 'text-red-400', violet: 'text-violet-400', zinc: 'text-zinc-400',
};

interface NotifData {
    type: string;
    level?: string;
    message?: string;
    sender_username?: string;
    preview?: string;
    conversation_id?: number;
    action_url?: string;
    action_label?: string;
}
interface Notif {
    id: string;
    type: string;
    data: NotifData;
    read: boolean;
    created_at: string;
}

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    notifications: {
        data: Notif[];
        links: PageLink[];
        meta: unknown;
        current_page?: number;
        last_page?: number;
        total?: number;
        from?: number | null;
        to?: number | null;
    };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const unreadCount = computed(() => props.notifications.data.filter(n => !n.read).length);
const markingAll = ref(false);

/**
 * The controller passes the raw Eloquent paginator straight to Inertia, which
 * serializes it flat (current_page/last_page/links at the top level) — not the
 * {data,links,meta} API-resource shape the props were typed for. The embedded
 * Account tab sends a hand-built {data:[...20 most recent], links:[], meta:[]}
 * instead (no pagination there by design), so this quietly renders nothing
 * for that source since last_page is never set.
 */
const showPagination = computed(() => (props.notifications.last_page ?? 0) > 1);

/** A window around the current page, same as the News archive pager. */
const pageWindow = computed(() => {
    const current = props.notifications.current_page ?? 1;
    const last = props.notifications.last_page ?? 1;
    const span = 2;
    const from = Math.max(1, current - span);
    const to = Math.min(last, current + span);

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});

function pageHref(page: number): string {
    return route('account.notifications', { page });
}

function markRead(id: string) {
    router.post(route('account.notifications.read', id), {}, { preserveScroll: true });
}

function markAllRead() {
    if (!unreadCount.value || markingAll.value) return;
    router.post(route('account.notifications.read-all'), {}, {
        preserveScroll: true,
        onStart: () => { markingAll.value = true; },
        onFinish: () => { markingAll.value = false; },
    });
}
function destroy(id: string) {
    router.delete(route('account.notifications.destroy', id), { preserveScroll: true });
}

function notifIcon(type: string) {
    if (type === 'new_message') return MessageSquare;
    if (type === 'system') return AlertCircle;
    const ext = extTypes.value[type];
    if (ext) return extIconMap[ext.icon] ?? Bell;
    return Bell;
}
function notifColor(n: Notif) {
    if (n.data.type === 'new_message') return 'text-blue-400';
    const ext = extTypes.value[n.data.type];
    if (ext) return accentText[ext.accent] ?? 'text-zinc-400';
    const lvl = n.data.level ?? 'info';
    return { info: 'text-blue-400', success: 'text-emerald-400', warning: 'text-amber-400', danger: 'text-red-400' }[lvl] ?? 'text-zinc-400';
}
function notifBg(n: Notif) {
    if (n.data.type === 'new_message') return dark.value ? 'bg-blue-500/8' : 'bg-blue-50';
    const lvl = n.data.level ?? 'info';
    const map = {
        info: dark.value ? 'bg-blue-500/8' : 'bg-blue-50',
        success: dark.value ? 'bg-emerald-500/8' : 'bg-emerald-50',
        warning: dark.value ? 'bg-amber-500/8' : 'bg-amber-50',
        danger: dark.value ? 'bg-red-500/8' : 'bg-red-50',
    };
    return map[lvl as keyof typeof map] ?? '';
}

function notifText(n: Notif): string {
    if (n.data.type === 'new_message') {
        const heading = t('account.notif_new_message', { sender: n.data.sender_username ?? '' });
        return n.data.preview ? `${heading}: "${n.data.preview}"` : heading;
    }
    return n.data.message ?? t('account.notif_system');
}
function notifLink(n: Notif): string | null {
    if (n.data.type === 'new_message' && n.data.conversation_id) return route('account.messages.show', n.data.conversation_id);
    return n.data.action_url ?? null;
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-5 sm:px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap"
            :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.notif_title') }}
                    </h2>
                    <span v-if="unreadCount" class="px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-blue-600 text-white tabular-nums"
                        aria-live="polite">
                        {{ t('account.notif_unread_count', { count: unreadCount }) }}
                    </span>
                </div>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.notif_subtitle') }}
                </p>
            </div>

            <!-- Always rendered, disabled when there is nothing to mark, so the
                 action is discoverable before anything is unread. -->
            <button type="button"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border text-[12px] font-bold transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="unreadCount
                    ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-500'
                    : dark ? 'border-zinc-800 text-zinc-500' : 'border-zinc-300 text-zinc-500'"
                :disabled="!unreadCount || markingAll"
                :title="t('account.notif_mark_all_hint')"
                @click="markAllRead">
                <Check :size="13" :stroke-width="2.4" aria-hidden="true" />
                {{ unreadCount ? t('account.notif_mark_all') : t('account.notif_mark_all_done') }}
            </button>
        </div>

        <div v-if="notifications.data.length === 0" class="flex flex-col items-center text-center px-6 py-14">
            <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                <Bell :size="26" :stroke-width="1.4" />
            </span>
            <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                {{ t('account.notif_empty') }}
            </p>
            <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('account.notif_empty_hint') }}
            </p>
        </div>

        <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
            :aria-label="t('account.notif_list_label')">
            <li v-for="n in notifications.data" :key="n.id"
                class="flex items-start gap-3 px-5 py-4 transition"
                :class="[!n.read ? (dark ? 'bg-zinc-900/40' : 'bg-blue-500/[0.04]') : '', dark ? 'hover:bg-zinc-900/30' : 'hover:bg-zinc-100/70']">

                <!-- Icon -->
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 border mt-0.5"
                    :class="[notifBg(n), dark ? 'border-zinc-800' : 'border-transparent']" aria-hidden="true">
                    <component :is="notifIcon(n.data.type)" :size="14" :stroke-width="1.9" :class="notifColor(n)" />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] leading-relaxed" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                        <!-- Not colour alone: unread rows say so in text for
                             screen readers, and the tint is a second signal. -->
                        <span v-if="!n.read" class="sr-only">{{ t('account.notif_unread_marker') }} — </span>
                        {{ notifText(n) }}
                    </p>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ n.created_at }}</span>
                        <a v-if="notifLink(n)" :href="notifLink(n)!"
                            class="text-[11px] font-bold rounded transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-700 hover:text-blue-800'">
                            {{ n.data.action_label ?? t('account.notif_view') }} →
                        </a>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 shrink-0">
                    <button v-if="!n.read" type="button"
                        :title="t('account.notif_mark_one')" :aria-label="t('account.notif_mark_one')"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'text-zinc-500 hover:text-blue-400 hover:bg-blue-500/10' : 'text-zinc-500 hover:text-blue-600 hover:bg-blue-500/10'"
                        @click="markRead(n.id)">
                        <Check :size="13" :stroke-width="2.5" aria-hidden="true" />
                    </button>
                    <button type="button"
                        :title="t('account.notif_delete')" :aria-label="t('account.notif_delete')"
                        class="w-8 h-8 rounded-lg flex items-center justify-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50"
                        :class="dark ? 'text-zinc-500 hover:text-red-400 hover:bg-red-500/10' : 'text-zinc-500 hover:text-red-600 hover:bg-red-500/10'"
                        @click="destroy(n.id)">
                        <Trash2 :size="13" :stroke-width="2" aria-hidden="true" />
                    </button>
                </div>

                <div v-if="!n.read" class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-3" aria-hidden="true" />
            </li>
        </ul>

        <nav v-if="showPagination" :aria-label="t('account.notif_pagination_label')"
            class="flex flex-col items-center gap-2 px-5 py-4 border-t"
            :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
            <div class="flex items-center gap-1">
                <Link v-if="(notifications.current_page ?? 1) > 1" :href="pageHref((notifications.current_page ?? 1) - 1)" rel="prev"
                    class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                    <ChevronLeft :size="13" :stroke-width="2" aria-hidden="true" />
                    {{ t('account.notif_prev') }}
                </Link>

                <Link v-for="p in pageWindow" :key="p" :href="pageHref(p)"
                    :aria-current="p === notifications.current_page ? 'page' : undefined"
                    :aria-label="t('account.notif_page_number', { page: p })"
                    class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="p === notifications.current_page
                        ? dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-300 bg-blue-50 text-blue-700'
                        : dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                    {{ p }}
                </Link>

                <Link v-if="(notifications.current_page ?? 1) < (notifications.last_page ?? 1)" :href="pageHref((notifications.current_page ?? 1) + 1)" rel="next"
                    class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                    {{ t('account.notif_next') }}
                    <ChevronRight :size="13" :stroke-width="2" aria-hidden="true" />
                </Link>
            </div>
            <p class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('account.notif_showing', { current: notifications.current_page, last: notifications.last_page }) }}
            </p>
        </nav>
    </div>
</template>
