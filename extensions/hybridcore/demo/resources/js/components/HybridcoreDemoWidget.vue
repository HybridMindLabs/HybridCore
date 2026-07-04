<script setup lang="ts">
import { Puzzle } from '@lucide/vue';

defineProps<{ message: string }>();
</script>

<template>
    <div class="flex items-center gap-2.5 rounded-xl border border-zinc-800/60 bg-zinc-900/40 px-3 py-2.5 text-[12px]">
        <Puzzle :size="13" :stroke-width="1.75" class="text-zinc-500 shrink-0" />
        <span class="text-zinc-400">{{ message }}</span>
    </div>
</template>
