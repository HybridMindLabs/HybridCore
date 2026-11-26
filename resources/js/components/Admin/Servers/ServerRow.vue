<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Minus, Pencil, Plug, PlugZap, RefreshCw, Terminal, Trash2, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { confirm } from '@/composables/useConfirm';
import type { AdminServer } from './serverForm';

const props = defineProps<{ server: AdminServer; selected: boolean; commandsOpen: boolean }>();
const emit = defineEmits<{ toggleSelect: []; toggleCommands: []; edit: [] }>();

const label = computed(() => props.server.name ?? props.server.address);

async function issueToken() {
    const message = props.server.bridge.enabled
        ? `Rotate the bridge token for "${props.server.address}"? The current token stops working immediately.`
        : `Generate a bridge token for "${props.server.address}"? The in-game plugin uses it to pull commands from the site.`;
    if (!await confirm(message)) return;
    router.post(route('admin.servers.bridge.issue', props.server.id), {}, { preserveScroll: true });
}

async function revokeToken() {
    if (!await confirm(`Revoke the bridge token for "${props.server.address}"? The game server will be disconnected.`)) return;
    router.delete(route('admin.servers.bridge.revoke', props.server.id), { preserveScroll: true });
}

function refresh() {
    router.post(route('admin.servers.refresh', props.server.id), {}, { preserveScroll: true });
}

async function destroy() {
    if (!await confirm(`Delete server "${label.value}"?`)) return;
    router.delete(route('admin.servers.destroy', props.server.id), { preserveScroll: true });
}

const bridgeTitle = computed(() => {
    const b = props.server.bridge;
    if (!b.enabled) return 'Generate bridge token for the in-game plugin';
    return `Bridge: ${b.online ? 'connected' : 'no recent heartbeat'}${b.last_seen ? ' · last seen ' + b.last_seen : ''} — click to rotate token`;
});
</script>

<template>
    <tr class="border-b border-zinc-800/50 last:border-0 hover:bg-zinc-900/30 transition-colors"
        :class="[!server.is_active ? 'opacity-40' : '', selected ? 'bg-blue-500/[0.04]' : '']">
        <td class="px-4 py-3 w-8">
            <input type="checkbox" :checked="selected" :aria-label="`Select ${label}`"
                class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer"
                @change="emit('toggleSelect')" />
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <p class="text-zinc-100 text-sm font-medium leading-tight">{{ label }}</p>
                <span v-if="!server.is_visible"
                    class="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20"
                    title="Hidden from public server browser">Hidden</span>
            </div>
            <p class="text-zinc-500 text-xs font-mono mt-0.5">
                {{ server.address }}
                <span v-if="server.display_address && server.display_address !== `${server.ip}:${server.port}`" class="text-zinc-500 ml-1">
                    ({{ server.ip }}:{{ server.port }})
                </span>
            </p>
            <div v-if="server.tags.length" class="flex flex-wrap gap-1 mt-1.5">
                <span v-for="tag in server.tags" :key="tag" class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800/80 text-zinc-400">{{ tag }}</span>
            </div>
        </td>
        <td class="px-4 py-3 hidden sm:table-cell">
            <div class="inline-flex items-center gap-2">
                <GameIcon :slug="server.game.slug" :alt="server.game.name" :size="32" img-class="w-6 h-6 rounded object-contain" />
                <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full"
                    :style="{ background: server.game.color + '1a', color: server.game.color }">{{ server.game.name }}</span>
            </div>
        </td>
        <td class="px-4 py-3">
            <div v-if="server.status">
                <div class="flex items-center gap-1.5">
                    <CheckCircle2 v-if="server.status.is_online" :size="13" :stroke-width="2" class="text-emerald-400 shrink-0" aria-hidden="true" />
                    <XCircle v-else :size="13" :stroke-width="2" class="text-red-400 shrink-0" aria-hidden="true" />
                    <span class="text-xs font-medium" :class="server.status.is_online ? 'text-emerald-400' : 'text-red-400'">
                        {{ server.status.is_online ? 'Online' : 'Offline' }}
                    </span>
                </div>
                <p v-if="!server.status.is_online && server.status.failure_reason"
                    class="text-red-400/70 text-[11px] mt-0.5 max-w-[16rem] truncate"
                    :title="server.status.failure_reason">{{ server.status.failure_reason }}</p>
            </div>
            <div v-else class="flex items-center gap-1.5">
                <Minus :size="13" :stroke-width="2" class="text-zinc-600" aria-hidden="true" />
                <span class="text-xs text-zinc-500">Not queried</span>
            </div>
        </td>
        <td class="px-4 py-3 hidden md:table-cell">
            <span v-if="server.status?.is_online" class="text-zinc-300 text-xs tabular-nums">
                {{ server.status.players_online }}<span class="text-zinc-500">/{{ server.status.players_max }}</span>
            </span>
            <span v-else class="text-zinc-600 text-xs">—</span>
        </td>
        <td class="px-4 py-3 hidden lg:table-cell">
            <span class="text-zinc-500 text-xs">{{ server.last_queried_at ?? 'Never' }}</span>
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1">
                <button type="button" :title="bridgeTitle" :aria-label="bridgeTitle"
                    class="relative w-7 h-7 flex items-center justify-center rounded-lg transition-colors"
                    :class="server.bridge.enabled
                        ? (server.bridge.online ? 'text-emerald-400 hover:bg-emerald-500/10' : 'text-amber-400 hover:bg-amber-500/10')
                        : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800'"
                    @click="issueToken">
                    <Plug :size="13" :stroke-width="1.75" />
                    <span v-if="server.bridge.enabled" class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full"
                        :class="server.bridge.online ? 'bg-emerald-400' : 'bg-amber-400'" aria-hidden="true" />
                </button>
                <!-- Revoking used to be a hidden right-click on the plug; it has its own button now. -->
                <button v-if="server.bridge.enabled" type="button"
                    title="Revoke bridge token" aria-label="Revoke bridge token"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                    @click="revokeToken">
                    <PlugZap :size="13" :stroke-width="1.75" />
                </button>
                <button type="button"
                    :title="`Bridge command log${server.bridge.pending_count ? ` — ${server.bridge.pending_count} pending` : ''}`"
                    aria-label="Bridge command log"
                    :aria-expanded="commandsOpen"
                    class="relative w-7 h-7 flex items-center justify-center rounded-lg transition-colors"
                    :class="commandsOpen ? 'text-blue-400 bg-blue-500/10' : 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800'"
                    @click="emit('toggleCommands')">
                    <Terminal :size="13" :stroke-width="1.75" />
                    <span v-if="server.bridge.pending_count"
                        class="absolute -top-0.5 -right-0.5 min-w-[14px] h-[14px] px-0.5 flex items-center justify-center rounded-full bg-amber-400 text-[9px] font-bold text-zinc-950 leading-none">{{ server.bridge.pending_count }}</span>
                </button>
                <button type="button" title="Refresh status" aria-label="Refresh status"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-blue-400 hover:bg-blue-500/10 transition-colors"
                    @click="refresh">
                    <RefreshCw :size="13" :stroke-width="1.75" />
                </button>
                <button type="button" title="Edit server" aria-label="Edit server"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    @click="emit('edit')">
                    <Pencil :size="13" :stroke-width="1.75" />
                </button>
                <button type="button" title="Delete server" aria-label="Delete server"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                    @click="destroy">
                    <Trash2 :size="13" :stroke-width="1.75" />
                </button>
            </div>
        </td>
    </tr>
</template>
