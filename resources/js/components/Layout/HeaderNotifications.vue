<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useClickOutside } from '@/composables/useClickOutside';
import { useDesktopNotifications } from '@/composables/useDesktopNotifications';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { csrfHeaders } from '@/lib/csrf';

/** The bell: unread count (live over Reverb), the latest few and "mark all read". */
const props = defineProps<{ userId: number | null }>();

interface RecentNotif { id: string; type: string; data: Record<string, unknown>; read: boolean; created_at: string }

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const { ensurePermission, notifyFromData } = useDesktopNotifications();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
useClickOutside(root, () => { open.value = false; });

const unreadCount = ref(0);
const recent = ref<RecentNotif[]>([]);
const loading = ref(false);
const markingAll = ref(false);

const ajax = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };

async function fetchUnread() {
    try {
        const r = await fetch('/api/notifications/unread-count', { headers: ajax });
        if (r.ok) unreadCount.value = (await r.json()).count ?? 0;
    } catch {
        // The badge just stays as it was.
    }
}

async function fetchRecent() {
    loading.value = true;
    try {
        const r = await fetch('/api/notifications/recent', { headers: ajax });
        if (r.ok) recent.value = (await r.json()).notifications ?? [];
    } catch {
        // Leave the previous list in place.
    } finally {
        loading.value = false;
    }
}

async function toggle() {
    ensurePermission();
    open.value = !open.value;
    if (open.value) await fetchRecent();
}

async function markAllRead() {
    if (markingAll.value || unreadCount.value === 0) return;
    markingAll.value = true;
    try {
        const r = await fetch(route('account.notifications.read-all'), {
            method: 'POST',
            headers: { ...csrfHeaders(), ...ajax },
        });
        // Only clear the badge once the server agreed.
        if (r.ok) {
            unreadCount.value = 0;
            recent.value = recent.value.map((n) => ({ ...n, read: true }));
        }
    } catch {
        // Nothing changed; the button can be tried again.
    } finally {
        markingAll.value = false;
    }
}

function label(n: RecentNotif): string {
    if (n.type === 'new_message') {
        return t('navigation.new_message_from', { name: String(n.data.sender_username ?? t('navigation.someone')) });
    }
    return (n.data.message as string | undefined) ?? (n.data.preview as string | undefined) ?? t('navigation.new_notification');
}

// Real-time count over Reverb, when Echo is loaded.
type Echo = { private: (channel: string) => { listen: (event: string, cb: (payload: RecentNotif) => void) => void }; leave: (channel: string) => void };
const echo = (): Echo | null => (typeof window !== 'undefined' ? ((window as unknown as { Echo?: Echo }).Echo ?? null) : null);
const channel = computed(() => `App.Models.User.${props.userId}`);

onMounted(() => {
    fetchUnread();

    if (props.userId) {
        echo()?.private(channel.value).listen('.notification.created', (payload) => {
            unreadCount.value++;
            notifyFromData(payload.data ?? {});
            recent.value = [payload, ...recent.value].slice(0, 8);
        });
    }
});

onUnmounted(() => {
    if (props.userId) echo()?.leave(channel.value);
});
</script>

<template>
    <div ref="root" class="relative">
        <button type="button"
            class="relative w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
            :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-400 hover:text-zinc-500 hover:bg-zinc-100'"
            :aria-label="t('navigation.notifications')"
            :aria-expanded="open"
            @click.stop="toggle">
            <Bell :size="14" :stroke-width="1.75" aria-hidden="true" />
            <span v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 min-w-[16px] h-4 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center px-1 leading-none ring-2"
                :class="dark ? 'ring-zinc-950' : 'ring-white'">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div v-if="open"
            class="absolute right-0 top-full mt-1 w-80 rounded-lg border shadow-lg z-50 overflow-hidden"
            :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/40' : 'bg-white border-zinc-200 shadow-zinc-200/60'">
            <div class="flex items-center justify-between px-3 py-2.5 border-b" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                <span class="text-[12px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ t('navigation.notifications') }}</span>
                <button type="button"
                    class="text-[11px] font-semibold transition-colors disabled:opacity-40 text-hc-accent hover:text-hc-accent-hover"
                    :disabled="markingAll || unreadCount === 0"
                    @click.stop="markAllRead">
                    {{ markingAll ? t('navigation.marking') : t('navigation.mark_all_read') }}
                </button>
            </div>

            <div class="max-h-80 overflow-y-auto" aria-live="polite">
                <p v-if="loading" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('navigation.loading') }}</p>
                <p v-else-if="recent.length === 0" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('navigation.no_notifications') }}</p>
                <div v-for="n in recent" :key="n.id"
                    class="px-3 py-2.5 border-b last:border-0 text-[12px]"
                    :class="[dark ? 'border-zinc-800/60' : 'border-zinc-100', !n.read ? (dark ? 'bg-hc-accent/5' : 'bg-hc-accent/10') : '']">
                    <p :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ label(n) }}</p>
                    <p class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ n.created_at }}</p>
                </div>
            </div>

            <Link :href="route('account.index', { tab: 'notifications' })"
                class="block text-center px-3 py-2 text-[12px] font-semibold border-t transition-colors"
                :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100' : 'border-zinc-100 text-zinc-500 hover:text-zinc-800'"
                @click="open = false">
                {{ t('navigation.view_all') }}
            </Link>
        </div>
    </div>
</template>
