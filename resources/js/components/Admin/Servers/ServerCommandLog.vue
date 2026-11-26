<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Ban, CheckCircle2, Clock, RefreshCw, XCircle } from '@lucide/vue';
import { confirm } from '@/composables/useConfirm';
import type { AdminServer, CommandRow } from './serverForm';

/** The last commands queued for a server's in-game plugin, under its row. */
const props = defineProps<{ server: AdminServer }>();

/** The server sends at most this many; reaching it means older ones exist. */
const SHOWN = 10;

const statusStyle: Record<CommandRow['status'], { icon: typeof Clock; class: string; label: string }> = {
    pending: { icon: Clock, class: 'text-amber-400', label: 'Pending' },
    delivered: { icon: RefreshCw, class: 'text-blue-400', label: 'Delivered' },
    acked: { icon: CheckCircle2, class: 'text-emerald-400', label: 'Acked' },
    expired: { icon: XCircle, class: 'text-zinc-500', label: 'Expired' },
    cancelled: { icon: Ban, class: 'text-zinc-500', label: 'Cancelled' },
};

async function cancel(command: CommandRow) {
    if (!await confirm(`Cancel queued command "${command.command}"? It will never be delivered.`, { confirmLabel: 'Cancel command', cancelLabel: 'Keep', danger: true })) return;
    router.post(route('admin.servers.commands.cancel', [props.server.id, command.id]), {}, { preserveScroll: true });
}
</script>

<template>
    <tr class="border-b border-zinc-800/50 bg-zinc-900/20">
        <td colspan="7" class="px-4 py-3">
            <p v-if="!server.commands.length" class="text-zinc-500 text-xs py-2">
                No commands queued for this server yet — extensions and core queue them via <code class="text-zinc-400">BridgeService::queue()</code>.
            </p>
            <div v-else class="flex flex-col gap-1">
                <div v-for="cmd in server.commands" :key="cmd.id"
                    class="flex items-center justify-between gap-3 px-2.5 py-1.5 rounded-lg bg-zinc-900/50 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <component :is="statusStyle[cmd.status].icon" :size="12" :stroke-width="2"
                            :class="statusStyle[cmd.status].class" class="shrink-0" aria-hidden="true" />
                        <code class="text-zinc-300 font-mono truncate" :title="cmd.command">{{ cmd.command }}</code>
                        <span class="shrink-0 text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 font-mono" title="Queued by">{{ cmd.source }}</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span :class="statusStyle[cmd.status].class" class="font-medium">{{ statusStyle[cmd.status].label }}</span>
                        <span v-if="cmd.attempts > 0" class="text-zinc-500">{{ cmd.attempts }}x</span>
                        <span class="text-zinc-500">{{ cmd.acked_at ?? cmd.delivered_at ?? cmd.created_at }}</span>
                        <button v-if="cmd.status === 'pending'" type="button"
                            class="text-zinc-500 hover:text-red-400 transition-colors"
                            title="Cancel command" aria-label="Cancel command"
                            @click="cancel(cmd)">
                            <Ban :size="12" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
                <p v-if="server.commands.length >= SHOWN" class="text-zinc-500 text-[11px] pt-1">
                    Showing the {{ SHOWN }} most recent commands.
                </p>
            </div>
        </td>
    </tr>
</template>
