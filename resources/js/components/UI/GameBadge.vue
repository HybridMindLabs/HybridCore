<script setup lang="ts">
const props = defineProps<{ game: string; size?: 'sm' | 'md' }>();

type GameConfig = { label: string; color: string; bg: string };

const gameMap: Record<string, GameConfig> = {
    'cs16':      { label: 'CS 1.6',     color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
    'cs2':       { label: 'CS2',         color: '#22d3ee', bg: 'rgba(34,211,238,0.12)' },
    'minecraft': { label: 'Minecraft',   color: '#22c55e', bg: 'rgba(34,197,94,0.12)'  },
    'fivem':     { label: 'FiveM',       color: '#a78bfa', bg: 'rgba(167,139,250,0.12)'},
    'rust':      { label: 'Rust',        color: '#f97316', bg: 'rgba(249,115,22,0.12)' },
    'default':   { label: 'Unknown',     color: '#64748b', bg: 'rgba(100,116,139,0.12)'},
};

const config = gameMap[props.game] ?? gameMap['default'];
</script>

<template>
    <span
        class="inline-flex items-center rounded-md font-medium border"
        :class="size === 'sm' ? 'text-[10px] px-1.5 py-0.5' : 'text-[11px] px-2 py-0.5'"
        :style="{
            color: config.color,
            background: config.bg,
            borderColor: config.color + '33',
        }"
    >
        {{ config.label }}
    </span>
</template>
