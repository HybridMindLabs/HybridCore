<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Settings, FileText, ScrollText } from '@lucide/vue';

defineProps<{ active: 'settings' | 'templates' | 'logs' }>();

const tabs = [
    { key: 'settings', label: 'Settings', icon: Settings, href: () => route('admin.email.settings') },
    { key: 'templates', label: 'Templates', icon: FileText, href: () => route('admin.email.templates.index') },
    { key: 'logs', label: 'Logs', icon: ScrollText, href: () => route('admin.email.logs') },
] as const;
</script>

<template>
    <div class="flex items-center gap-1 mb-5 border-b border-zinc-800/70">
        <Link v-for="tab in tabs" :key="tab.key" :href="tab.href()"
            class="flex items-center gap-1.5 px-3 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
            :class="active === tab.key
                ? 'text-cyan-400 border-cyan-500'
                : 'text-zinc-500 border-transparent hover:text-zinc-300'">
            <component :is="tab.icon" :size="13" :stroke-width="1.8" />
            {{ tab.label }}
        </Link>
    </div>
</template>
