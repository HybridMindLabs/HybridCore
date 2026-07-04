<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    game: string;
    size?: string;  /* tailwind size class, default 'w-[72px] h-[72px]' */
}>();

const failed = ref(false);

const src = `/images/games/icons/64x64/${props.game}.png`;

function onError() {
    failed.value = true;
}

const meta: Record<string, { color: string; bg: string; abbr: string }> = {
    cs16:      { color: '#f59e0b', bg: 'rgba(245,158,11,0.18)',  abbr: 'CS' },
    cs2:       { color: '#22d3ee', bg: 'rgba(34,211,238,0.18)',  abbr: 'CS' },
    minecraft: { color: '#22c55e', bg: 'rgba(34,197,94,0.18)',   abbr: 'MC' },
    fivem:     { color: '#a78bfa', bg: 'rgba(167,139,250,0.18)', abbr: 'FM' },
    rust:      { color: '#f97316', bg: 'rgba(249,115,22,0.18)',  abbr: 'RS' },
};

function m() {
    return meta[props.game] ?? { color: '#64748b', bg: 'rgba(100,116,139,0.18)', abbr: '??' };
}
</script>

<template>
    <div :class="['rounded-xl overflow-hidden shrink-0 flex items-center justify-center', size ?? 'w-[72px] h-[72px]']">
        <!-- Real image -->
        <img
            v-if="!failed"
            :src="src"
            :alt="game"
            class="w-full h-full object-contain"
            loading="lazy"
            decoding="async"
            @error="onError"
        />
        <!-- Fallback coloured box -->
        <div
            v-else
            class="w-full h-full flex items-center justify-center text-sm font-black border"
            :style="{ background: m().bg, borderColor: m().color + '33', color: m().color }"
        >
            {{ m().abbr }}
        </div>
    </div>
</template>
