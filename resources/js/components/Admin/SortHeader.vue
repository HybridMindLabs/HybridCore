<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    column: string;
    sort: string | null;
    dir: string;
    routeName: string;
    /** The page's other active filters, carried along so sorting doesn't reset them. */
    params?: Record<string, string | number | null | undefined>;
}>();

const active = computed(() => props.sort === props.column);

function toggle() {
    const dir = active.value && props.dir === 'desc' ? 'asc' : 'desc';
    const params = Object.fromEntries(Object.entries(props.params ?? {}).filter(([, v]) => v !== null && v !== undefined && v !== ''));

    router.get(route(props.routeName), { ...params, sort: props.column, dir }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-zinc-200 transition-colors"
        :class="active ? 'text-zinc-200' : ''"
        :aria-label="`Sort by ${label}`"
        @click="toggle"
    >
        {{ label }}
        <ArrowDown v-if="active && dir === 'desc'" :size="12" :stroke-width="2.25" />
        <ArrowUp v-else-if="active" :size="12" :stroke-width="2.25" />
        <ArrowUpDown v-else :size="12" :stroke-width="2" class="opacity-40" />
    </button>
</template>
