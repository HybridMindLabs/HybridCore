<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

defineProps<{
    items: { label: string; href?: string }[];
}>();
</script>

<template>
    <nav class="flex items-center gap-1.5 text-[12px] mb-4 flex-wrap">
        <template v-for="(item, i) in items" :key="i">
            <Link v-if="item.href" :href="item.href"
                class="text-zinc-500 hover:text-zinc-300 transition-colors font-medium">
                {{ item.label }}
            </Link>
            <span v-else class="text-zinc-300 font-semibold">{{ item.label }}</span>
            <ChevronRight v-if="i < items.length - 1" :size="11" :stroke-width="2.5" class="text-zinc-700 shrink-0" />
        </template>
    </nav>
</template>
