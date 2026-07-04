<script setup lang="ts">
import { Users } from '@lucide/vue';
import GameBadge from '@/components/UI/GameBadge.vue';

defineProps<{
    name: string;
    subtitle?: string;
    game: string;
    status: 'online' | 'offline' | 'maintenance';
    players?: number;
    maxPlayers?: number;
    map?: string;
    tag?: string;
    ping?: number;
}>();
</script>

<template>
    <div class="group flex items-center gap-3 sm:gap-4 bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3.5 hc-card-hover cursor-default">

        <!-- Status dot -->
        <div class="shrink-0 flex items-center justify-center">
            <span
                v-if="status === 'online'"
                class="hc-live-dot"
            />
            <span
                v-else
                class="inline-block w-1.5 h-1.5 rounded-full"
                :class="status === 'maintenance' ? 'bg-[#f59e0b]' : 'bg-red-500'"
            />
        </div>

        <!-- Game icon placeholder / avatar -->
        <div class="shrink-0 w-9 h-9 rounded-lg bg-zinc-900/60 border border-zinc-800/70 flex items-center justify-center text-[10px] font-bold text-zinc-600 uppercase select-none">
            {{ name.substring(0, 2) }}
        </div>

        <!-- Name + meta -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-zinc-100 text-sm font-semibold truncate">{{ name }}</span>
                <GameBadge :game="game" size="sm" />
                <span v-if="tag" class="text-[10px] text-zinc-600 bg-zinc-900/60 border border-zinc-800/70 rounded px-1.5 py-0.5">{{ tag }}</span>
            </div>
            <div class="flex items-center gap-3 mt-0.5">
                <span v-if="subtitle" class="text-zinc-600 text-xs truncate">{{ subtitle }}</span>
                <span v-if="map" class="text-zinc-700 text-xs">{{ map }}</span>
            </div>
        </div>

        <!-- Players -->
        <div v-if="players !== undefined" class="shrink-0 flex items-center gap-1.5 text-zinc-500 text-xs">
            <Users :size="12" :stroke-width="1.75" />
            <span>{{ players }}<span v-if="maxPlayers" class="text-zinc-700">/{{ maxPlayers }}</span></span>
        </div>

        <!-- Ping -->
        <div v-if="ping !== undefined && status === 'online'" class="shrink-0 hidden sm:block">
            <span
                class="text-[11px] font-mono font-medium px-1.5 py-0.5 rounded"
                :class="ping < 60 ? 'text-emerald-400 bg-emerald-500/10' : ping < 120 ? 'text-[#f59e0b] bg-[#f59e0b]/10' : 'text-red-400 bg-red-500/10'"
            >{{ ping }}ms</span>
        </div>

        <!-- Offline label -->
        <div v-else-if="status !== 'online'" class="shrink-0 hidden sm:block">
            <span class="text-[11px] text-red-400 bg-red-500/10 px-1.5 py-0.5 rounded font-medium">
                {{ status === 'maintenance' ? 'Maint.' : 'Offline' }}
            </span>
        </div>

    </div>
</template>
