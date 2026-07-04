<script setup lang="ts">
import { computed, resolveComponent, h } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Renders all Vue components registered by extensions for a named slot.
 *
 * Usage in any page or layout:
 *   <ExtensionSlot name="home.right.bottom" />
 *   <ExtensionSlot name="server.show.sidebar" :context="{ serverId: server.id }" />
 *
 * Context is merged with the component's registered props — the extension
 * component receives both its own data and whatever the host page passes.
 */

interface SlotEntry {
    component: string;
    props: Record<string, unknown>;
    priority: number;
    permission: string | null;
}

interface PageProps {
    extensionSlots?: Record<string, SlotEntry[]>;
    auth?: { user?: { permissions?: string[] } | null } | null;
    [key: string]: unknown;
}

const props = defineProps<{
    name: string;
    context?: Record<string, unknown>;
}>();

const page = usePage<PageProps>();

const entries = computed((): SlotEntry[] => {
    const all = page.props.extensionSlots ?? {};
    return all[props.name] ?? [];
});
</script>

<template>
    <template v-for="entry in entries" :key="`${name}-${entry.component}-${entry.priority}`">
        <component
            :is="resolveComponent(entry.component)"
            v-bind="{ ...entry.props, ...(context ?? {}) }"
        />
    </template>
</template>
