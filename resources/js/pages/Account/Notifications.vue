<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bell, Check, Trash2, Mail, MessageSquare, AlertCircle } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { computed } from 'vue';

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

defineProps<{
    notifications: { data: Notif[]; links: any; meta: any };
}>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

function markRead(id: string) {
    router.post(route('account.notifications.read', id), {}, { preserveScroll: true });
}
function markAllRead() {
    router.post(route('account.notifications.read-all'), {}, { preserveScroll: true });
}
function destroy(id: string) {
    router.delete(route('account.notifications.destroy', id), { preserveScroll: true });
}

function notifIcon(type: string) {
    if (type === 'new_message') return MessageSquare;
    if (type === 'system') return AlertCircle;
    return Bell;
}
function notifColor(n: Notif) {
    if (n.data.type === 'new_message') return 'text-blue-400';
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
    if (n.data.type === 'new_message') return `New message from @${n.data.sender_username}: "${n.data.preview}"`;
    return n.data.message ?? 'System notification';
}
function notifLink(n: Notif): string | null {
    if (n.data.type === 'new_message' && n.data.conversation_id) return route('account.messages.show', n.data.conversation_id);
    return n.data.action_url ?? null;
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
        <div class="px-6 py-4 border-b flex items-center justify-between" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <div>
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Notifications</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Your recent activity and alerts.</p>
            </div>
            <button v-if="notifications.data.some(n => !n.read)" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold transition"
                :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300'"
                @click="markAllRead">
                <Check :size="11" :stroke-width="2" /> Mark all read
            </button>
        </div>

        <div v-if="notifications.data.length === 0" class="p-12 text-center">
            <Bell :size="28" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No notifications yet</p>
        </div>

        <div v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
            <div v-for="n in notifications.data" :key="n.id"
                class="flex items-start gap-3 px-5 py-4 transition"
                :class="[!n.read ? (dark ? 'bg-zinc-900/40' : 'bg-zinc-50/70') : '', dark ? 'hover:bg-zinc-900/30' : 'hover:bg-zinc-50']">

                <!-- Icon -->
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 border mt-0.5"
                    :class="[notifBg(n), dark ? 'border-zinc-800' : 'border-transparent']">
                    <component :is="notifIcon(n.data.type)" :size="14" :stroke-width="1.8" :class="notifColor(n)" />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] leading-relaxed" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ notifText(n) }}</p>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ n.created_at }}</span>
                        <a v-if="notifLink(n)" :href="notifLink(n)!" class="text-[11px] font-semibold text-blue-400 hover:underline">
                            {{ n.data.action_label ?? 'View' }} →
                        </a>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 shrink-0">
                    <button v-if="!n.read" type="button" title="Mark read"
                        class="w-7 h-7 rounded-lg flex items-center justify-center transition"
                        :class="dark ? 'text-zinc-600 hover:text-blue-400 hover:bg-blue-500/10' : 'text-zinc-300 hover:text-blue-500 hover:bg-blue-50'"
                        @click="markRead(n.id)">
                        <Check :size="12" :stroke-width="2.5" />
                    </button>
                    <button type="button" title="Delete"
                        class="w-7 h-7 rounded-lg flex items-center justify-center transition"
                        :class="dark ? 'text-zinc-600 hover:text-red-400 hover:bg-red-500/10' : 'text-zinc-300 hover:text-red-500 hover:bg-red-50'"
                        @click="destroy(n.id)">
                        <Trash2 :size="12" :stroke-width="2" />
                    </button>
                </div>

                <!-- Unread dot -->
                <div v-if="!n.read" class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-2" />
            </div>
        </div>
    </div>
</template>
