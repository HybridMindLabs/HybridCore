<script setup lang="ts">
import { Puzzle } from '@lucide/vue';

defineProps<{ username: string }>();
</script>

<template>
    <div class="flex items-center gap-2 text-sm text-zinc-400">
        <Puzzle :size="14" :stroke-width="1.5" class="shrink-0" />
        profileTabs() example — hello, {{ username }}.
    </div>
</template>
