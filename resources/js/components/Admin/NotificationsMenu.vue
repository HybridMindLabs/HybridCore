<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Mail } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface RecentNotification {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read: boolean;
    created_at: string;
}

const page = usePage<{ auth: { user: { id: number } | null }; adminBadges?: { unread_contact: number } }>();

const open = ref(false);
const loading = ref(false);
const unread = ref(0);
const items = ref<RecentNotification[]>([]);
const unreadContact = computed(() => page.props.adminBadges?.unread_contact ?? 0);
const total = computed(() => unread.value + unreadContact.value);

const jsonHeaders = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };

function csrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function fetchUnread() {
    const r = await fetch(route('api.notifications.unread-count'), { headers: jsonHeaders });
    if (r.ok) unread.value = (await r.json()).count ?? 0;
}

async function fetchRecent() {
    loading.value = true;
    try {
        const r = await fetch(route('api.notifications.recent'), { headers: jsonHeaders });
        if (r.ok) items.value = (await r.json()).notifications ?? [];
    } finally {
        loading.value = false;
    }
}

async function toggle() {
    open.value = !open.value;
    if (open.value) await fetchRecent();
}

function label(n: RecentNotification): string {
    if (n.type === 'new_message') return `New message from ${n.data.sender_username ?? 'someone'}`;
    return (n.data.message as string | undefined) ?? 'New notification';
}

function levelClass(n: RecentNotification): string {
    return ({ warning: 'bg-amber-400', error: 'bg-red-400', success: 'bg-emerald-400' } as Record<string, string>)[n.data.level as string] ?? 'bg-blue-400';
}

async function openItem(n: RecentNotification) {
    if (!n.read) {
        await fetch(route('account.notifications.read', n.id), { method: 'POST', headers: { ...jsonHeaders, 'X-CSRF-TOKEN': csrf() } });
        n.read = true;
        unread.value = Math.max(0, unread.value - 1);
    }
    open.value = false;
    if (typeof n.data.action_url === 'string') router.visit(n.data.action_url);
}

async function markAllRead() {
    await fetch(route('account.notifications.read-all'), { method: 'POST', headers: { ...jsonHeaders, 'X-CSRF-TOKEN': csrf() } });
    unread.value = 0;
    items.value = items.value.map((n) => ({ ...n, read: true }));
}

let channel: string | null = null;

onMounted(() => {
    fetchUnread();

    const userId = page.props.auth?.user?.id;
    const echo = (window as unknown as { Echo?: typeof window.Echo }).Echo;
    if (userId && echo) {
        channel = `App.Models.User.${userId}`;
        echo.private(channel).listen('.notification.created', (payload: RecentNotification) => {
            unread.value++;
            items.value = [payload, ...items.value].slice(0, 8);
        });
    }
});

onUnmounted(() => {
    const echo = (window as unknown as { Echo?: typeof window.Echo }).Echo;
    if (channel && echo) echo.leave(channel);
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative w-9 h-9 rounded-lg flex items-center justify-center text-zinc-500 hover:text-zinc-200 hover:bg-zinc-900/60 transition-colors"
            :aria-label="total > 0 ? `Notifications, ${total} unread` : 'Notifications'"
            :aria-expanded="open"
            @click="toggle"
        >
            <Bell :size="16" :stroke-width="1.75" />
            <span
                v-if="total > 0"
                class="absolute top-0.5 right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-4 text-center ring-2 ring-admin-sunken"
            >{{ total > 99 ? '99+' : total }}</span>
        </button>

        <template v-if="open">
            <div class="fixed inset-0 z-40" @click="open = false" />
            <div class="absolute right-0 top-full mt-1.5 w-80 bg-admin-surface border border-zinc-800/70 rounded-xl shadow-xl shadow-black/40 z-50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800/70">
                    <span class="text-sm font-semibold text-zinc-100">Notifications</span>
                    <button
                        type="button"
                        class="text-xs font-medium text-blue-400 hover:text-blue-300 disabled:opacity-40 disabled:pointer-events-none"
                        :disabled="unread === 0"
                        @click="markAllRead"
                    >Mark all read</button>
                </div>

                <Link
                    v-if="unreadContact > 0"
                    :href="route('admin.contact.index')"
                    class="flex items-center gap-3 px-4 py-3 border-b border-zinc-800/70 bg-blue-500/[0.06] hover:bg-blue-500/10 transition-colors"
                    @click="open = false"
                >
                    <Mail :size="15" :stroke-width="1.75" class="text-blue-400 shrink-0" />
                    <span class="text-sm text-zinc-200">{{ unreadContact }} unread contact {{ unreadContact === 1 ? 'message' : 'messages' }}</span>
                </Link>

                <div class="admin-scroll max-h-80 overflow-y-auto">
                    <p v-if="loading && items.length === 0" class="px-4 py-8 text-center text-xs text-zinc-500">Loading…</p>
                    <p v-else-if="items.length === 0" class="px-4 py-8 text-center text-xs text-zinc-500">You're all caught up.</p>
                    <button
                        v-for="n in items"
                        :key="n.id"
                        type="button"
                        class="w-full flex gap-3 px-4 py-3 text-left border-b border-zinc-800/40 last:border-0 hover:bg-admin-elevated transition-colors"
                        @click="openItem(n)"
                    >
                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="n.read ? 'bg-zinc-700' : levelClass(n)" />
                        <span class="min-w-0">
                            <span class="block text-sm break-words" :class="n.read ? 'text-zinc-400' : 'text-zinc-100'">{{ label(n) }}</span>
                            <span class="block text-xs text-zinc-500 mt-0.5">{{ n.created_at }}</span>
                        </span>
                    </button>
                </div>

                <Link
                    :href="route('account.notifications')"
                    class="block text-center px-4 py-2.5 text-xs font-medium text-zinc-400 hover:text-zinc-100 border-t border-zinc-800/70 transition-colors"
                    @click="open = false"
                >View all</Link>
            </div>
        </template>
    </div>
</template>
