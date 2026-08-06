<script setup lang="ts">
import { Info } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{ text: string }>();

const open = ref(false);
</script>

<template>
    <span class="relative inline-flex" @mouseenter="open = true" @mouseleave="open = false">
        <button
            type="button"
            class="flex items-center justify-center text-zinc-600 hover:text-zinc-300 transition-colors rounded-full
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/70"
            :aria-expanded="open"
            aria-label="More info"
            @click.stop="open = !open"
        >
            <Info :size="12" :stroke-width="2" />
        </button>
        <div
            v-if="open"
            role="tooltip"
            class="absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 rounded-lg border border-zinc-800
                   bg-[#18181b] px-3 py-2 text-[11px] leading-relaxed text-zinc-300 shadow-xl shadow-black/40
                   pointer-events-none"
        >
            {{ text }}
            <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-zinc-800" aria-hidden="true" />
        </div>
    </span>
</template>
