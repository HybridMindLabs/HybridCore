<script setup lang="ts">
import { Clock, Users } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import ServerPanel from './ServerPanel.vue';

/** Who is on the server right now, from the last query. */
defineProps<{
    online: boolean;
    playersMax: number;
    players: { name: string; score: number; duration: string }[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <ServerPanel :title="t('servers.current_players')">
        <template #aside>
            <span v-if="online" class="text-[12px] text-zinc-500 tabular-nums">{{ players.length }} / {{ playersMax }}</span>
        </template>

        <table v-if="online && players.length" class="w-full text-left">
            <caption class="sr-only">{{ t('servers.current_players') }}</caption>
            <thead>
                <tr class="border-b text-[10px] font-semibold uppercase tracking-wider text-zinc-500"
                    :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                    <th scope="col" class="px-5 py-2.5 font-semibold">{{ t('servers.player_name') }}</th>
                    <th scope="col" class="px-3 py-2.5 font-semibold text-right w-[70px]">{{ t('servers.player_score') }}</th>
                    <th scope="col" class="px-5 py-2.5 font-semibold text-right w-[96px]">{{ t('servers.player_time') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                <tr v-for="(player, idx) in players" :key="player.name + idx"
                    class="transition-colors" :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50/80'">
                    <td class="px-5 py-3">
                        <span class="flex items-center gap-2.5 min-w-0">
                            <span class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-bold shrink-0 uppercase select-none"
                                :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-100 text-zinc-500'" aria-hidden="true">{{ (player.name || '?')[0] }}</span>
                            <span class="text-[13px] font-medium truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ player.name || '—' }}</span>
                        </span>
                    </td>
                    <td class="px-3 py-3 text-[12px] font-semibold text-right tabular-nums" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ player.score }}</td>
                    <td class="px-5 py-3 text-[11px] text-right text-zinc-500">
                        <span class="inline-flex items-center justify-end gap-1">
                            <Clock :size="10" :stroke-width="1.8" aria-hidden="true" />{{ player.duration }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-else class="flex flex-col items-center justify-center px-5 py-12 text-center">
            <Users :size="24" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" aria-hidden="true" />
            <p class="text-[14px] text-zinc-500">{{ online ? t('servers.no_players') : t('servers.offline') }}</p>
        </div>
    </ServerPanel>
</template>
